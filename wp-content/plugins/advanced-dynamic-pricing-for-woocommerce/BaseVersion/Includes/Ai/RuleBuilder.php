<?php

namespace ADP\BaseVersion\Includes\Ai;

use ADP\BaseVersion\Includes\Database\Models\Rule;

defined('ABSPATH') or exit;

/**
 * Builds pricing rule data from an action name and its plain fields ("create_product_discount"
 * + filter_type/filter_value/discount_type/...). Knows nothing about who asked: the admin chat
 * (RuleAssistant) feeds it what the model extracted, the abilities (Abilities) what an external
 * client sent. Never writes to the database - the caller decides what happens to the result.
 */
class RuleBuilder
{
    /**
     * @param string     $actionName   one of the `create_*` action names applyAction() knows
     * @param array      $params       that action's fields, exactly as documented for the chat
     * @param array|null $existingRule Rule::getData() shape of the rule being changed, or null
     *
     * @return array{rule: array, found: bool, note: string, pro_feature: string|null, pro_active: bool}|null
     *         null when the fields don't add up to a rule
     */
    public static function buildRule($actionName, $params, $existingRule = null)
    {
        $action = self::applyAction($actionName, $params, $existingRule);
        if ( ! $action || empty($action['rule'])) {
            return null;
        }

        return array(
            'rule'        => $action['rule'],
            'found'       => ! empty($action['found']),
            'note'        => (string)($action['note'] ?? ''),
            'pro_feature' => ProFeatureDetector::detectProFeature($action['rule'], $params, $actionName),
            'pro_active'  => ProFeatureDetector::isProActive(),
        );
    }

    /**
     * @param array|null $existingRule Rule::getData() shape of the rule being edited (from loadRule()), or null
     *                                  to draft a brand new rule
     *
     * @return array{note: string, found: bool, rule: array|null, titles: array|null, links: array|null}|null
     */
    public static function applyAction($action, $decoded, $existingRule = null)
    {
        switch ($action) {
            case 'create_product_discount':
                $result = self::buildProductDiscount($decoded, $existingRule);
                break;
            // Pro-only action (the rule editor's "Product set" mechanism) - buildSetDiscount()
            // itself returns null when Pro isn't active, same defense-in-depth as
            // create_bogo_discount below (the instructions already keep the model from using
            // this action at all on a free-only site).
            case 'create_set_discount':
                $result = self::buildSetDiscount($decoded, $existingRule);
                break;
            case 'create_bulk_discount':
                $result = self::buildBulkDiscount($decoded, $existingRule);
                break;
            case 'create_role_discount':
                $result = self::buildRoleDiscount($decoded, $existingRule);
                break;
            case 'create_role_bulk_discount':
                $result = self::buildRoleBulkDiscount($decoded, $existingRule);
                break;
            case 'create_cart_discount':
                $result = self::buildCartDiscount($decoded, $existingRule);
                break;
            case 'create_gift':
                $result = self::buildGift($decoded, $existingRule);
                break;
            case 'create_bogo_free':
                $result = self::buildBogoFree($decoded, $existingRule);
                break;
            // Pro-only action - buildBogoDiscount() itself returns null when Pro isn't
            // active, same defense-in-depth as the pro_conditions/exclusive handling in
            // applyProExtras() below (the instructions already keep the model from using
            // this action at all on a free-only site).
            case 'create_bogo_discount':
                $result = self::buildBogoDiscount($decoded, $existingRule);
                break;
            // Pro-only as well: a two-row set with a split discount
            case 'create_combo_deal':
                $result = self::buildComboDeal($decoded, $existingRule);
                break;
            default:
                $result = null;
        }

        if ($result) {
            $result = self::applyCommonConditionExtras($decoded, $result);
        }

        if ($result) {
            $result = self::applyRuleOptions($decoded, $result);
        }

        if ($result) {
            // Always applied, regardless of isProActive() - detectProFeature() (called from
            // RuleAssistant::handle(), after this returns) is the one place licensing is actually checked, so
            // the rule still needs to be built correctly here even on a free-only site.
            $result = self::applyProExtras($decoded, $result);
        }

        return $result;
    }

    /**
     * `require_products_in_cart` - works on ANY action, free or Pro, no license needed.
     * Requires several SPECIFIC products to all be in the cart together (an AND, not "any one
     * of them") - e.g. "if the cart has both Cap and Sunglasses, make Beanie free" needs this
     * alongside create_product_discount/create_gift/etc., since a plain `filter_type`/
     * `filter_value` only ever names ONE target. Built from `products_all` cart condition rows
     * (one per required product, each requiring qty >= 1) - a condition type that's been in
     * the free engine all along (BaseVersion/Includes/Core/Rule/CartCondition/Impl/
     * ProductsAll.php), just never wired up to the chat before.
     */
    /**
     * The rule editor's options that are not tied to one kind of rule: the "Exclude" checkboxes
     * of a filter row, the simple free conditions (dates, logged-in customers, items in the
     * cart), usage limits, and the settings in the rule's header. Each is touched only when its
     * field is present, so an edit that doesn't mention one leaves it as it is.
     */
    private static function applyRuleOptions($decoded, $action)
    {
        $rule = $action['rule'];
        $bool = function ($value) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        };

        // --- filter rows: "Exclude: on sale / modified by other pricing rules / on backorder"
        $excludeFlags = array(
            'exclude_on_sale'            => 'on_wc_sale',
            'exclude_already_discounted' => 'already_affected',
            'exclude_backorder'          => 'backorder',
        );
        foreach ($excludeFlags as $field => $flag) {
            if (array_key_exists($field, $decoded) && ! empty($rule['filters'])) {
                foreach ($rule['filters'] as $i => $filter) {
                    $rule['filters'][$i]['product_exclude'][$flag] = $bool($decoded[$field]) ? '1' : '';
                }
            }
        }

        // --- conditions; each replaces the rule's existing rows of its own type
        $replaceConditions = function ($type, $rows) use (&$rule) {
            $kept = array_values(array_filter($rule['conditions'] ?? array(), function ($row) use ($type) {
                return ($row['type'] ?? '') !== $type;
            }));
            $rule['conditions'] = array_merge($kept, $rows);
        };

        // whole days, the free "Date" condition - a time of day needs the Pro date_time one.
        // Each end of the period is replaced on its own: "extend it to the 10th" keeps the start.
        foreach (array('date_from' => 'from', 'date_to' => 'to') as $field => $method) {
            if ( ! array_key_exists($field, $decoded)) {
                continue;
            }
            $rule['conditions'] = array_values(array_filter($rule['conditions'] ?? array(), function ($row) use ($method) {
                return ! (($row['type'] ?? '') === 'date' && ($row['options']['comparison_datetime_method'] ?? '') === $method);
            }));
            $date = substr(trim((string)$decoded[$field]), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $rule['conditions'][] = array(
                    'type'    => 'date',
                    'options' => array('comparison_datetime' => $date, 'comparison_datetime_method' => $method),
                );
            }
        }

        if (array_key_exists('logged_in_only', $decoded)) {
            $replaceConditions('customer_logged', $decoded['logged_in_only'] === null || $decoded['logged_in_only'] === '' ? array() : array(array(
                'type'    => 'customer_logged',
                'options' => array('comparison_bin_value' => $bool($decoded['logged_in_only']) ? 'yes' : 'no'),
            )));
        }

        if (array_key_exists('min_cart_items', $decoded)) {
            $replaceConditions('cart_items_count', (int)$decoded['min_cart_items'] > 0 ? array(array(
                'type'    => 'cart_items_count',
                'options' => array('comparison_value' => (string)(int)$decoded['min_cart_items'], 'comparison_value_method' => '>='),
            )) : array());
        }

        // --- limits: how many times the rule may be used at all / by one customer (Pro)
        foreach (array('max_usage' => 'max_usage', 'max_usage_per_customer' => 'max_usage_per_customer') as $field => $type) {
            if (array_key_exists($field, $decoded)) {
                $limits = array_values(array_filter($rule['limits'] ?? array(), function ($row) use ($type) {
                    return ($row['type'] ?? '') !== $type;
                }));
                if ((int)$decoded[$field] > 0) {
                    $limits[] = array('type' => $type, 'options' => (string)(int)$decoded[$field]);
                }
                $rule['limits'] = $limits;
            }
        }

        // --- the rule's header
        if (array_key_exists('apply_first_to', $decoded) && in_array($decoded['apply_first_to'], array('expensive', 'cheap', 'appeared'), true)) {
            $rule['options']['apply_to'] = $decoded['apply_first_to'];
        }
        if (array_key_exists('times_per_cart', $decoded)) {
            $times                     = (int)$decoded['times_per_cart'];
            $rule['options']['repeat'] = $times >= 1 && $times <= 10 ? (string)$times : '-1';
        }
        if (array_key_exists('trigger_coupon_code', $decoded)) {
            $rule['additional']['trigger_coupon_code'] = sanitize_text_field((string)$decoded['trigger_coupon_code']);
        }
        // "Limit discount to amount" of the product discount block
        if (array_key_exists('max_discount_amount', $decoded) && ! empty($rule['product_adjustments'])) {
            $rule['product_adjustments']['max_discount_sum'] = (float)$decoded['max_discount_amount'] > 0 ? (string)(float)$decoded['max_discount_amount'] : '';
        }

        // "Fixed price by the formula" of the product discount block
        if ( ! empty($decoded['price_formula']) && ! empty($rule['product_adjustments'])) {
            $rule['product_adjustments']['type']  = 'total';
            $rule['product_adjustments']['total'] = array(
                'type'  => 'discount__expression_price',
                'value' => sanitize_text_field((string)$decoded['price_formula']),
            );
        }

        // --- bulk block
        if ( ! empty($rule['bulk_adjustments']['ranges'])) {
            if (in_array($decoded['bulk_mode'] ?? '', array('bulk', 'tier'), true)) {
                $rule['bulk_adjustments']['type'] = $decoded['bulk_mode'];
            }
            if (in_array($decoded['measure_by'] ?? '', array('qty', 'sum', 'weight'), true)) {
                $rule['bulk_adjustments']['measurement'] = $decoded['measure_by'];
            }
            if (array_key_exists('table_message', $decoded)) {
                $rule['bulk_adjustments']['table_message'] = sanitize_text_field((string)$decoded['table_message']);
            }
        }

        // --- role block: several rows, each for its own role(s) with its own discount
        if ( ! empty($decoded['role_rows']) && is_array($decoded['role_rows'])) {
            $rows = array();
            foreach ($decoded['role_rows'] as $row) {
                $slugs = array();
                foreach ((array)($row['roles'] ?? ($row['role_name'] ?? array())) as $roleName) {
                    $slug = TargetResolver::findRole((string)$roleName);
                    if ( ! $slug) {
                        $action['found'] = false;
                        $action['note']  = trim(($action['note'] ?? '') . ' ' . sprintf('Could not find the role "%s".', $roleName));

                        return $action;
                    }
                    $slugs[] = $slug;
                }
                $engineType = self::mapDiscountEngineType($row['discount_type'] ?? 'percentage');
                if ($slugs && $engineType && isset($row['discount_value'])) {
                    $rows[] = array('roles' => $slugs, 'discount_type' => $engineType, 'discount_value' => (string)(float)$row['discount_value']);
                }
            }
            if ($rows) {
                $rule['role_discounts']['rows'] = $rows;
            }
        }
        if (array_key_exists('skip_bulk_for_roles', $decoded) && ! empty($rule['role_discounts'])) {
            $rule['role_discounts']['dont_apply_bulk_if_roles_matched'] = $bool($decoded['skip_bulk_for_roles']) ? '1' : '0';
        }

        // --- gifts and auto-added products: which product is given, and how
        $giftBlocks = array(
            'get_products'      => array(
                'prefix'     => 'gift',
                'mode_field' => 'gift_mode',
                'modes'      => array(
                    'first_available'                      => 'giftable_products',
                    'rotation'                             => 'giftable_products_in_rotation',
                    'random'                               => 'giftable_products_in_random',
                    'same_as_bought'                       => 'use_product_from_filter',
                    'first_bought_only'                    => 'use_only_first_product_from_filter',
                    'customer_choice'                      => 'allow_to_choose',
                    'customer_choice_required'             => 'require_to_choose',
                    'customer_choice_from_categories'          => 'allow_to_choose_from_product_cat',
                    'customer_choice_from_categories_required' => 'require_to_choose_from_product_cat',
                ),
                'coupon'     => array('free_products_replace_name', 'is_replace_free_products_with_discount'),
            ),
            'auto_add_products' => array(
                'prefix'     => 'auto_add',
                'mode_field' => 'auto_add_mode',
                'modes'      => array(
                    'first_available' => 'auto_add_products',
                    'rotation'        => 'auto_add_products_in_rotation',
                    'same_as_bought'  => 'use_product_from_filter',
                ),
                'coupon'     => array('auto_add_products_replace_name', 'is_replace_auto_add_products_with_discount'),
            ),
        );
        foreach ($giftBlocks as $block => $spec) {
            if (empty($rule[$block]['value'][0])) {
                continue;
            }
            $prefix = $spec['prefix'];
            $mode   = $spec['modes'][$decoded[$prefix . '_mode'] ?? ''] ?? null;
            if ($mode) {
                $rule[$block]['value'][0][$block === 'get_products' ? 'gift_mode' : 'auto_add_mode'] = $mode;
            }

            // several products to give from / to let the customer choose from
            foreach (array($prefix . '_products' => 'product', $prefix . '_categories' => 'category') as $field => $kind) {
                if (empty($decoded[$field]) || ! is_array($decoded[$field])) {
                    continue;
                }
                $ids = array();
                foreach ($decoded[$field] as $name) {
                    $found = $kind === 'product' ? TargetResolver::findProduct((string)$name) : TargetResolver::findCategory((string)$name);
                    if ( ! $found) {
                        $action['found'] = false;
                        $action['note']  = trim(($action['note'] ?? '') . ' ' . sprintf('Could not find "%s".', $name));

                        return $action;
                    }
                    $ids[] = (string)($kind === 'product' ? $found->get_id() : $found->term_id);
                }
                $rule[$block]['value'][0]['value'] = $ids;
            }

            if (isset($decoded[$prefix . '_times'])) {
                $times                  = (int)$decoded[$prefix . '_times'];
                $rule[$block]['repeat'] = $times >= 1 ? (string)$times : '-1';
            }
            if (array_key_exists($prefix . '_per_subtotal', $decoded)) {
                $rule[$block]['repeat_subtotal'] = (float)$decoded[$prefix . '_per_subtotal'] > 0 ? (string)(float)$decoded[$prefix . '_per_subtotal'] : '';
            }
            if (array_key_exists($prefix . '_coupon_name', $decoded)) {
                $name                                 = sanitize_text_field((string)$decoded[$prefix . '_coupon_name']);
                $rule['additional'][$spec['coupon'][0]] = $name;
                $rule['additional'][$spec['coupon'][1]] = $name !== '' ? 'on' : '';
            }
        }
        if ( ! empty($rule['get_products']['value'][0])) {
            if (array_key_exists('gift_max_amount', $decoded)) {
                $rule['get_products']['max_amount_for_gifts'] = (float)$decoded['gift_max_amount'] > 0 ? (string)(float)$decoded['gift_max_amount'] : '';
            }
            if (array_key_exists('gifts_cheaper_than_cart_items', $decoded)) {
                $rule['get_products']['is_gifts_below_cheapest'] = $bool($decoded['gifts_cheaper_than_cart_items']) ? 'on' : '';
            }
        }
        if ( ! empty($rule['auto_add_products']['value'][0])) {
            if (array_key_exists('auto_add_cant_be_removed', $decoded)) {
                $rule['additional']['auto_add_cant_be_removed_from_cart'] = $bool($decoded['auto_add_cant_be_removed']) ? 'on' : '';
            }
            if (array_key_exists('auto_add_as_recommended', $decoded)) {
                $rule['additional']['auto_add_show_as_recommended_product'] = $bool($decoded['auto_add_as_recommended']) ? 'on' : '';
            }
        }

        // --- cart adjustments: the cap of a cart discount, the tax class of a fee
        if ( ! empty($rule['cart_adjustments'][0]['type'])) {
            $cartType = $rule['cart_adjustments'][0]['type'];
            if (array_key_exists('max_discount_amount', $decoded) && strpos($cartType, 'discount__') === 0 && isset($rule['cart_adjustments'][0]['options'][1])) {
                $rule['cart_adjustments'][0]['options'][2] = (float)$decoded['max_discount_amount'] > 0 ? (string)(float)$decoded['max_discount_amount'] : '';
            }
            if (array_key_exists('fee_tax_class', $decoded) && strpos($cartType, 'fee') === 0) {
                $rule['cart_adjustments'][0]['options'][2] = sanitize_title((string)$decoded['fee_tax_class']);
            }
        }

        // --- what the customer sees: badge, messages, timer (Pro)
        $advertising = array(
            'sale_badge'            => 'sale_badge',
            'discount_message'      => 'discount_message',
            'cart_item_message'     => 'discount_message_cart_item',
            'long_discount_message' => 'long_discount_message',
            'cart_notice'           => 'message_notice_discount',
            'timer_message'         => 'timer_message',
        );
        foreach ($advertising as $field => $key) {
            if (array_key_exists($field, $decoded)) {
                $rule['advertising'][$key] = sanitize_text_field((string)$decoded[$field]);
                if ($field === 'timer_message') {
                    $rule['advertising']['enabled_timer'] = $rule['advertising'][$key] !== '' ? 'on' : '';
                }
            }
        }
        // the "you are close to the deal" message shown while the rule's conditions are not met yet
        if (array_key_exists('offer_message', $decoded)) {
            $rule['condition_message']['type']  = 'total';
            $rule['condition_message']['total'] = array('message' => sanitize_text_field((string)$decoded['offer_message']));
        }

        if ( ! empty($rule['conditions']) && count($rule['conditions']) > 1 && empty($rule['additional']['conditions_relationship'])) {
            $rule['additional']['conditions_relationship'] = 'and';
        }

        $action['rule'] = Rule::fromArray($rule)->getData();

        return $action;
    }

    private static function applyCommonConditionExtras($decoded, $action)
    {
        $hasRequireProducts = array_key_exists('require_products_in_cart', $decoded);
        $hasShipCountries   = array_key_exists('ship_to_countries', $decoded);
        $hasDaysOfWeek      = array_key_exists('days_of_week', $decoded);

        if ( ! $hasRequireProducts && ! $hasShipCountries && ! $hasDaysOfWeek) {
            return $action;
        }

        $newRows = array();

        if ($hasRequireProducts) {
            $values = is_array($decoded['require_products_in_cart']) ? $decoded['require_products_in_cart'] : array();
            foreach ($values as $name) {
                $product = TargetResolver::findProduct((string)$name);
                if ( ! $product) {
                    $action['found'] = false;
                    $action['note']  = trim(
                        ($action['note'] ?? '') . ' ' .
                        sprintf(
                            /* translators: %s: product name the AI couldn't find */
                            __('Could not find "%s" to require it in the cart too - add that condition yourself in the Conditions section if needed.', 'advanced-dynamic-pricing-for-woocommerce'),
                            $name
                        )
                    );

                    return $action;
                }

                $newRows[] = array(
                    'type'    => 'products_all',
                    'options' => array(
                        'product_measure'        => 'qty',
                        'start_range'            => '1',
                        'end_range'              => '',
                        'comparison_list'        => array((string)$product->get_id()),
                        'comparison_list_method' => 'in_list',
                    ),
                );
            }
        }

        if ($hasShipCountries) {
            $values = is_array($decoded['ship_to_countries']) ? $decoded['ship_to_countries'] : array();
            $codes  = array();
            foreach ($values as $name) {
                $code = TargetResolver::resolveHelperListValue('getCountries', (string)$name);
                if ($code === null) {
                    $action['found'] = false;
                    $action['note']  = trim(
                        ($action['note'] ?? '') . ' ' .
                        sprintf(
                            /* translators: %s: country name the AI couldn't find */
                            __('Could not find the country "%s" - add that condition yourself in the Conditions section if needed.', 'advanced-dynamic-pricing-for-woocommerce'),
                            $name
                        )
                    );

                    return $action;
                }
                $codes[] = (string)$code;
            }
            if ( ! empty($codes)) {
                $newRows[] = array(
                    'type'    => 'shipping_country',
                    'options' => array(
                        'comparison_list'        => $codes,
                        'comparison_list_method' => 'in_list',
                    ),
                );
            }
        }

        $existingRows = $action['rule']['conditions'] ?? array();

        // `days_of_week` - the free "Days of week" condition: the rule only applies on these
        // weekdays ("20% off on Fridays"). Stored as day numbers, 0 = Sunday ... 6 = Saturday.
        // Unlike the two above it REPLACES the rule's existing weekdays, which is what
        // "make it Fridays and Saturdays" means when editing.
        if ($hasDaysOfWeek) {
            $dayNumbers = array('sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6);
            $days       = array();
            foreach ((array)$decoded['days_of_week'] as $day) {
                $key = strtolower(substr(trim((string)$day), 0, 3));
                if (isset($dayNumbers[$key])) {
                    $days[] = (string)$dayNumbers[$key];
                } elseif (is_numeric($day) && (int)$day >= 0 && (int)$day <= 6) {
                    $days[] = (string)(int)$day;
                } else {
                    $action['found'] = false;
                    $action['note']  = trim(
                        ($action['note'] ?? '') . ' ' .
                        sprintf(
                            /* translators: %s: the weekday name the AI gave */
                            __('Could not tell which weekday "%s" is - add the Days of week condition yourself in the Conditions section.', 'advanced-dynamic-pricing-for-woocommerce'),
                            $day
                        )
                    );

                    return $action;
                }
            }
            $existingRows = array_values(array_filter($existingRows, function ($row) {
                return ($row['type'] ?? '') !== 'days_of_week';
            }));
            $days = array_values(array_unique($days));
            sort($days);
            if ($days) {
                $newRows[] = array(
                    'type'    => 'days_of_week',
                    'options' => array(
                        'comparison_list'        => $days,
                        'comparison_list_method' => 'in_list',
                    ),
                );
            }
        }

        if (empty($newRows) && ! $hasDaysOfWeek) {
            return $action;
        }

        $action['rule']['conditions'] = array_merge($existingRows, $newRows);
        if (empty($action['rule']['additional']['conditions_relationship'])) {
            $action['rule']['additional']['conditions_relationship'] = 'and';
        }

        $filterTitles     = RuleDescriber::filterTitlesHelper();
        $action['titles'] = $filterTitles->getTitles(array($action['rule']));
        $action['links']  = $filterTitles->getLinks(array($action['rule']));

        return $action;
    }

    // -----------------------------------------------------------------
    // Product discount (create_product_discount): a flat discount on whatever
    // `filter_type`/`filter_value` resolves to (product, category, tag, SKU,
    // custom taxonomy term, or attribute term - see resolveFilterTarget()).
    // -----------------------------------------------------------------

    private static function buildProductDiscount($decoded, $existingRule = null)
    {
        $existingAdjustment = $existingRule['product_adjustments']['total'] ?? null;
        $discount = self::resolveDiscount($decoded, $existingAdjustment);
        if ( ! $discount) {
            return null;
        }

        $target = self::keepOrResolveFilters($decoded, $existingRule, false);
        if ( ! $target) {
            return null;
        }

        $overrides = array();
        if ( ! $discount['kept']) {
            $overrides['product_adjustments'] = self::simpleProductAdjustment($discount);
        }
        $additionalOverride = self::additionalOverride($decoded, $existingRule);
        if ($additionalOverride !== null) {
            $overrides['additional'] = $additionalOverride;
        }
        $title = $target['label'] !== null ? self::draftTitle($target['label'], $discount) : '';

        if ( ! $target['found']) {
            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($target['label'], __('discount settings', 'advanced-dynamic-pricing-for-woocommerce')),
                array(),
                $existingRule
            );
        }

        if ($existingRule) {
            $note = $target['label'] !== null
                ? sprintf(
                    /* translators: 1: discount description (e.g. "10% off"), 2: target name */
                    __('Updated the rule: %1$s "%2$s". Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                    self::discountLabel($discount),
                    $target['label']
                )
                : __('Updated the rule. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = sprintf(
                /* translators: 1: discount description (e.g. "10% off"), 2: target name */
                __('Added a draft rule: %1$s "%2$s". Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                self::discountLabel($discount),
                $target['label']
            );
        }

        return self::successResult($target['filters'], $title, $overrides, $note, $existingRule);
    }

    private static function simpleProductAdjustment($discount)
    {
        return array(
            'type'              => 'total',
            'total'             => array('type' => $discount['engineType'], 'value' => (string)$discount['value']),
            'split_discount_by' => 'cost',
        );
    }

    // -----------------------------------------------------------------
    // Product set (create_set_discount, Pro-only): "buy N of the SAME item for a
    // fixed/discounted total" - e.g. "3 for $30", "3=2" (33% off every complete set of 3). NOT
    // the same mechanism as create_bulk_discount, even though both are triggered by a phrase
    // like "buy 3...": a bulk discount's tiers apply per-unit from the threshold up (buying 4
    // of a "3+" tier gets the discount on all 4), while a set discount applies ONLY to each
    // COMPLETE group of `set_qty` (buying 4 of a 3-item set pays full price for the 4th) - see
    // 08-set-discount.md for the full explanation given to the model.
    //
    // Mechanically this reuses create_product_discount's own product_adjustments.total
    // discount shape, on a filter row whose `qty` is the set size instead of 1 -
    // RuleStorage::buildRules() detects any single filter row with qty != 1 and builds the
    // rule as a PackageRule ("Product set") instead of a plain SingleItemRule. This is the SAME
    // mechanism as the rule editor's own "Product set" button (`wdp-btn-add-product-set`),
    // which per the plugin's author is Pro-only. Built unconditionally regardless of
    // isProActive() - RuleAssistant::handle() checks licensing programmatically AFTER the rule exists (see
    // detectProFeature()), rather than gating construction here. PackageRule's own
    // packagesCountLimit already defaults to -1 (apply to every complete set found, not just
    // one), so `options` is left untouched here.
    // -----------------------------------------------------------------

    private static function buildSetDiscount($decoded, $existingRule = null)
    {
        // the set is spelled out product by product (or the open rule is such a set and its
        // composition isn't being replaced by a filter): one row per product, see buildBundle()
        $isBundleEdit = $existingRule && count($existingRule['filters'] ?? array()) > 1 && empty($decoded['filter_type']);
        if ( ! empty($decoded['bundle_products']) || $isBundleEdit) {
            return self::buildBundle($decoded, $existingRule);
        }

        $existingAdjustment = $existingRule['product_adjustments']['total'] ?? null;
        $discount = self::resolveDiscount($decoded, $existingAdjustment);
        if ( ! $discount) {
            return null;
        }

        $existingQty = isset($existingRule['filters'][0]['qty']) ? (int)$existingRule['filters'][0]['qty'] : null;
        $qtyGiven    = isset($decoded['set_qty']) && (int)$decoded['set_qty'] >= 2;
        if ( ! $qtyGiven && ! $existingQty) {
            return null;
        }
        $qty = $qtyGiven ? (int)$decoded['set_qty'] : $existingQty;

        $filterTypeGiven = array_key_exists('filter_type', $decoded) && $decoded['filter_type'] !== '';

        if ( ! $filterTypeGiven && ! $existingRule) {
            // "I'll pick the product myself" on a brand new set - still needs ONE filter row
            // with the right qty so the backend recognizes this as a product SET at all (an
            // empty filters array would never become a PackageRule); an empty "Products" row
            // is left for the admin to fill in, matching what manually clicking "Add product
            // filter" shows by default
            $filters = array(array_merge(TargetResolver::filterRow('products', array()), array(
                'qty'     => (string)$qty,
                'qty_end' => (string)$qty,
            )));
            $target = array('filters' => $filters, 'label' => null, 'found' => true);
        } else {
            $target = self::keepOrResolveFilters($decoded, $existingRule, false);
            if ( ! $target) {
                return null;
            }
            if ( ! empty($target['filters'])) {
                $target['filters'][0]['qty']     = (string)$qty;
                $target['filters'][0]['qty_end'] = (string)$qty;
            }
        }

        // "3 of the SAME product for $30" on a target wider than one product (any product, a
        // category): every unit of a set must be one product. This is what the rule editor's
        // "Buy 3 for X" template sets; without it any N matching units make a set.
        if (array_key_exists('same_product', $decoded) && ! empty($target['filters'])) {
            $target['filters'][0]['limitation'] = ! empty($decoded['same_product']) ? 'product' : 'none';
        }

        $overrides = array();
        if ( ! $discount['kept']) {
            $overrides['product_adjustments'] = self::simpleProductAdjustment($discount);
        }
        $additionalOverride = self::additionalOverride($decoded, $existingRule);
        if ($additionalOverride !== null) {
            $overrides['additional'] = $additionalOverride;
        }

        $title = $target['label'] !== null
            ? self::draftTitle(sprintf('%s x%d', $target['label'], $qty), $discount)
            : sprintf('Set of %d', $qty);

        if ( ! $target['found']) {
            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($target['label'], __('product set settings', 'advanced-dynamic-pricing-for-woocommerce')),
                // an empty "Products" row of the right size - without it the draft wouldn't
                // be a product set at all
                array(array_merge(TargetResolver::filterRow('products', array()), array(
                    'qty'     => (string)$qty,
                    'qty_end' => (string)$qty,
                ))),
                $existingRule
            );
        }

        $discountLabel = self::discountLabel($discount);

        if ($existingRule) {
            $note = $target['label'] !== null
                ? sprintf(
                    /* translators: 1: set size, 2: target name, 3: discount description (e.g. "33.33% off") */
                    __('Updated the rule: a set of %1$d "%2$s" for %3$s. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $qty,
                    $target['label'],
                    $discountLabel
                )
                : __('Updated the rule. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = $target['label'] !== null
                ? sprintf(
                    /* translators: 1: set size, 2: target name, 3: discount description (e.g. "33.33% off") */
                    __('Added a draft: a set of %1$d "%2$s" for %3$s. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $qty,
                    $target['label'],
                    $discountLabel
                )
                : sprintf(
                    /* translators: 1: set size, 2: discount description (e.g. "33.33% off") */
                    __('Added a draft set-of-%1$d rule at %2$s - pick the product yourself in the rules list, then click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $qty,
                    $discountLabel
                );
        }

        return self::successResult($target['filters'], $title, $overrides, $note, $existingRule);
    }

    // -----------------------------------------------------------------
    // Bulk discount (create_bulk_discount): one filter target, qty-tiered pricing.
    // Scoped via `filters` + `bulk_adjustments.qty_based = 'product'` - this counts each
    // matching product's own cart quantity against the ranges (GROUP_BY_PRODUCT), the one
    // grouping mode that's correct regardless of which filter type scopes eligibility.
    // -----------------------------------------------------------------

    private static function buildBulkDiscount($decoded, $existingRule = null)
    {
        $bulk = self::resolveBulkAdjustments($decoded, $existingRule['bulk_adjustments'] ?? null);
        if ( ! $bulk) {
            return null;
        }

        $target = self::keepOrResolveFilters($decoded, $existingRule, false);
        if ( ! $target) {
            return null;
        }

        $overrides = array();
        if ( ! $bulk['kept']) {
            $overrides['bulk_adjustments'] = $bulk['adjustments'];
        }
        $title = $target['label'] !== null ? sprintf('%s - bulk pricing', $target['label']) : '';

        if ( ! $target['found']) {
            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($target['label'], __('bulk pricing settings', 'advanced-dynamic-pricing-for-woocommerce')),
                array(),
                $existingRule
            );
        }

        if ($existingRule) {
            $note = $target['label'] !== null
                ? sprintf(
                    /* translators: %s: target name */
                    __('Updated the bulk pricing rule for "%s". Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $target['label']
                )
                : __('Updated the bulk pricing rule. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = sprintf(
                /* translators: %s: target name */
                __('Added a draft bulk pricing rule for "%s". Review the quantity ranges below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $target['label']
            );
        }

        return self::successResult($target['filters'], $title, $overrides, $note, $existingRule);
    }

    /**
     * @param array|null $existingBulkAdjustments when editing, the rule's current `bulk_adjustments`
     *
     * @return array{adjustments: array|null, kept: bool}|null null = invalid (no discount_type/ranges
     *         given, and nothing to fall back to); `kept` true means the caller should leave
     *         `bulk_adjustments` out of the overrides entirely and keep the existing block as-is
     */
    /**
     * What a tier's quantity counts: each product on its own ("3 or more of the same product",
     * the default), all the items the filter matches together ("any 3 items from Accessories"),
     * or everything in the cart.
     */
    private static function bulkCountBy($decoded, $existingBulkAdjustments)
    {
        $map = array('each_product' => 'product', 'each_variation' => 'variation', 'all_matched' => 'all', 'whole_cart' => 'total_qty_in_cart');
        if (isset($map[$decoded['count_by'] ?? ''])) {
            return $map[$decoded['count_by']];
        }

        return $existingBulkAdjustments['qty_based'] ?? 'product';
    }

    private static function resolveBulkAdjustments($decoded, $existingBulkAdjustments)
    {
        // only the way of counting changes - keep the tiers and rebuild around them
        if (isset($decoded['count_by']) && $existingBulkAdjustments && empty($decoded['ranges']) && empty($decoded['discount_type'])) {
            $existingBulkAdjustments['qty_based'] = self::bulkCountBy($decoded, $existingBulkAdjustments);

            return array('adjustments' => $existingBulkAdjustments, 'kept' => false);
        }

        $hasType   = array_key_exists('discount_type', $decoded) && $decoded['discount_type'] !== '';
        $hasRanges = isset($decoded['ranges']) && is_array($decoded['ranges']) && ! empty($decoded['ranges']);

        if ( ! $hasType && ! $hasRanges) {
            return $existingBulkAdjustments ? array('adjustments' => null, 'kept' => true) : null;
        }

        $engineType = $hasType
            ? self::mapDiscountEngineType($decoded['discount_type'])
            : ($existingBulkAdjustments['discount_type'] ?? null);
        $ranges = $hasRanges
            ? self::resolveRanges($decoded['ranges'])
            : ($existingBulkAdjustments['ranges'] ?? null);

        if ( ! $engineType || ! $ranges) {
            return null;
        }

        return array(
            'adjustments' => array(
                'type'                => 'bulk',
                'qty_based'           => self::bulkCountBy($decoded, $existingBulkAdjustments),
                'measurement'         => 'qty',
                'discount_type'       => $engineType,
                'selected_categories' => $existingBulkAdjustments['selected_categories'] ?? array(),
                'selected_products'   => $existingBulkAdjustments['selected_products'] ?? array(),
                'ranges'              => $ranges,
                'table_message'       => $existingBulkAdjustments['table_message'] ?? '',
            ),
            'kept' => false,
        );
    }

    // -----------------------------------------------------------------
    // Role discount (create_role_discount): store-wide (no `filter_type`) or
    // scoped to one filter target.
    // -----------------------------------------------------------------

    private static function buildRoleDiscount($decoded, $existingRule = null)
    {
        // several role rows in one rule (`role_rows`): the block is built from the first and
        // applyRuleOptions() then puts the full list in its place
        if (empty($decoded['role_name']) && ! empty($decoded['role_rows'][0]) && is_array($decoded['role_rows'][0])) {
            $first                     = $decoded['role_rows'][0];
            $firstRoles                = (array)($first['roles'] ?? array());
            $decoded['role_name']      = (string)reset($firstRoles);
            $decoded['discount_type']  = $first['discount_type'] ?? 'percentage';
            $decoded['discount_value'] = $first['discount_value'] ?? '';
        }

        $existingRow = $existingRule['role_discounts']['rows'][0] ?? null;

        $roleNameGiven = $decoded['role_name'] ?? '';
        if ($roleNameGiven !== '') {
            $roleSlug = TargetResolver::findRole($roleNameGiven);
            if ( ! $roleSlug) {
                return null;
            }
        } elseif ($existingRow && ! empty($existingRow['roles'][0])) {
            $roleSlug = $existingRow['roles'][0];
        } else {
            return null;
        }

        $existingAdjustment = $existingRow ? array('type' => $existingRow['discount_type'] ?? null, 'value' => $existingRow['discount_value'] ?? null) : null;
        $discount = self::resolveDiscount($decoded, $existingAdjustment);
        if ( ! $discount) {
            return null;
        }

        $target = self::keepOrResolveFilters($decoded, $existingRule, true);
        if ( ! $target) {
            return null;
        }

        $overrides = array();
        if ( ! $discount['kept'] || $roleNameGiven !== '') {
            $overrides['role_discounts'] = array(
                'rows' => array(
                    array('roles' => array($roleSlug), 'discount_type' => $discount['engineType'], 'discount_value' => (string)$discount['value']),
                ),
                'dont_apply_bulk_if_roles_matched' => '0',
            );
        }
        // "make it 20% and only for Subscribers" on a plain product discount: the role row
        // replaces that discount - kept, it would still apply to everyone
        if ($existingRule && ! $existingRow && ! empty($existingRule['product_adjustments'])) {
            $overrides['product_adjustments'] = array();
        }
        $additionalOverride = self::additionalOverride($decoded, $existingRule);
        if ($additionalOverride !== null) {
            $overrides['additional'] = $additionalOverride;
        }
        $title = $target['label'] !== null ? sprintf('%s - role discount', $target['label']) : '';

        if ( ! $target['found']) {
            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($target['label'], __('role discount settings', 'advanced-dynamic-pricing-for-woocommerce')),
                array(),
                $existingRule
            );
        }

        if ($target['label'] === null) {
            $note = $existingRule
                ? __('Updated the role discount. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce')
                : __('Added a draft store-wide role discount. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = sprintf(
                /* translators: %s: target name */
                __('Added a draft role discount for "%s". Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $target['label']
            );
        }

        return self::successResult($target['filters'], $title, $overrides, $note, $existingRule);
    }

    // -----------------------------------------------------------------
    // Role + bulk combined (create_role_bulk_discount): bulk ranges gated to one role
    // via a customer_role CONDITION, so the whole rule (not just a parallel discount)
    // only ever matches for that role - unlike role_discounts, which is a separate
    // discount mechanism layered on top rather than a gate.
    // -----------------------------------------------------------------

    private static function buildRoleBulkDiscount($decoded, $existingRule = null)
    {
        $existingRoleCondition = null;
        foreach ($existingRule['conditions'] ?? array() as $condition) {
            if (($condition['type'] ?? '') === 'customer_role') {
                $existingRoleCondition = $condition;
                break;
            }
        }

        $roleNameGiven = $decoded['role_name'] ?? '';
        if ($roleNameGiven !== '') {
            $roleSlug = TargetResolver::findRole($roleNameGiven);
            if ( ! $roleSlug) {
                return null;
            }
        } elseif ($existingRoleCondition && ! empty($existingRoleCondition['options']['comparison_list'][0])) {
            $roleSlug = $existingRoleCondition['options']['comparison_list'][0];
        } else {
            return null;
        }

        $bulk = self::resolveBulkAdjustments($decoded, $existingRule['bulk_adjustments'] ?? null);
        if ( ! $bulk) {
            return null;
        }

        $target = self::keepOrResolveFilters($decoded, $existingRule, true);
        if ( ! $target) {
            return null;
        }

        $overrides = array();
        if ( ! $bulk['kept']) {
            $overrides['bulk_adjustments'] = $bulk['adjustments'];
        }
        if ($roleNameGiven !== '' || ! $existingRoleCondition) {
            $overrides['conditions'] = array(
                array(
                    'type'    => 'customer_role',
                    'options' => array(
                        'comparison_list'        => array($roleSlug),
                        'comparison_list_method' => 'in_list',
                    ),
                ),
            );
        }
        $title = $target['label'] !== null ? sprintf('%s - role+bulk pricing', $target['label']) : '';

        if ( ! $target['found']) {
            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($target['label'], __('role-restricted bulk pricing settings', 'advanced-dynamic-pricing-for-woocommerce')),
                array(),
                $existingRule
            );
        }

        if ($existingRule) {
            $note = $target['label'] !== null
                ? sprintf(
                    /* translators: %s: target name */
                    __('Updated the role-restricted bulk pricing rule for "%s". Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $target['label']
                )
                : __('Updated the role-restricted bulk pricing rule. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = $target['label'] !== null
                ? __('Added a draft role-restricted bulk pricing rule. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce')
                : __('Added a draft store-wide, role-restricted bulk pricing rule. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce');
        }

        return self::successResult($target['filters'], $title, $overrides, $note, $existingRule);
    }

    // -----------------------------------------------------------------
    // Cart discount (create_cart_discount): whole-cart percentage/fixed discount or
    // free shipping, optionally gated by a minimum subtotal. Implemented as an
    // auto-applied WooCommerce coupon (cart_adjustments), not a per-product change.
    // -----------------------------------------------------------------

    private static function buildCartDiscount($decoded, $existingRule = null)
    {
        // Pro-only alternate shape for this same action - a fee, tax exemption, shipping
        // price override, or coupon suppression/auto-apply, instead of a discount/free
        // shipping. See 05-cart-discount.md. Checked first since when given, it
        // replaces the free discount_type branch below entirely rather than combining with it.
        // Built unconditionally regardless of isProActive() - licensing is checked
        // programmatically after the fact, see detectProFeature().
        if ( ! empty($decoded['pro_adjustment_type'])) {
            return self::buildProCartAdjustment($decoded, $existingRule);
        }

        $existingAdjustment = $existingRule['cart_adjustments'][0] ?? null;

        // editing and the AI didn't mention the discount type at all -> keep whatever kind
        // (percentage/fixed/free shipping) the rule already uses
        $type = $decoded['discount_type'] ?? '';
        if ($type === '' && $existingAdjustment) {
            $type = array(
                'free__shipping'       => 'free_shipping',
                'discount__percentage' => 'percentage',
                'discount__amount'     => 'fixed_amount',
                'fee__amount'          => 'fee',
            )[$existingAdjustment['type'] ?? ''] ?? '';
        }

        if ($type === 'free_shipping') {
            $adjustment = array('type' => 'free__shipping', 'options' => array());
            $label      = __('free shipping', 'advanced-dynamic-pricing-for-woocommerce');
        } elseif ($type === 'percentage' || $type === 'fixed_amount') {
            if (isset($decoded['discount_value']) && $decoded['discount_value'] !== '') {
                $value = (string)(float)$decoded['discount_value'];
            } elseif ($existingAdjustment && isset($existingAdjustment['options'][0]) && $existingAdjustment['options'][0] !== '') {
                $value = (string)$existingAdjustment['options'][0];
            } else {
                return null;
            }
            $couponCode = ! empty($decoded['coupon_name'])
                ? sanitize_text_field($decoded['coupon_name'])
                : ($existingAdjustment['options'][1] ?? self::generateCouponCode());
            $engineType = $type === 'percentage' ? 'discount__percentage' : 'discount__amount';
            $adjustment = array('type' => $engineType, 'options' => array(0 => $value, 1 => $couponCode, 2 => ''));
            $label      = $type === 'percentage'
                ? sprintf(
                    /* translators: %s: discount percent */
                    __('%s%% off the cart', 'advanced-dynamic-pricing-for-woocommerce'),
                    self::formatNumber((float)$value)
                )
                : sprintf(
                    /* translators: %s: discount amount */
                    __('%s off the cart', 'advanced-dynamic-pricing-for-woocommerce'),
                    self::formatNumber((float)$value)
                );
        } elseif ($type === 'fee') {
            // a FIXED-amount cart fee (fee__amount) is free-tier - only the PERCENTAGE fee
            // variant (fee__percentage, via pro_adjustment_type) needs Pro
            if (isset($decoded['discount_value']) && $decoded['discount_value'] !== '') {
                $value = (string)(float)$decoded['discount_value'];
            } elseif ($existingAdjustment && isset($existingAdjustment['options'][0]) && $existingAdjustment['options'][0] !== '') {
                $value = (string)$existingAdjustment['options'][0];
            } else {
                return null;
            }
            $feeName = ! empty($decoded['fee_name'])
                ? sanitize_text_field($decoded['fee_name'])
                : ($existingAdjustment['options'][1] ?? __('Fee', 'advanced-dynamic-pricing-for-woocommerce'));
            $adjustment = array('type' => 'fee__amount', 'options' => array(0 => $value, 1 => $feeName, 2 => ''));
            $label      = sprintf(
                /* translators: %s: fee amount */
                __('%s fee', 'advanced-dynamic-pricing-for-woocommerce'),
                self::formatNumber((float)$value)
            );
        } else {
            return null;
        }

        $conditions = self::cartSubtotalConditions($decoded, $existingRule);

        $filters = $existingRule ? $existingRule['filters'] : array();
        $note    = $existingRule
            ? sprintf(
                /* translators: %s: discount description, e.g. "10% off the cart" */
                __('Updated the cart discount: %s. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                $label
            )
            : sprintf(
                /* translators: %s: discount description, e.g. "10% off the cart" */
                __('Added a draft cart discount: %s. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $label
            );

        return self::successResult(
            $filters,
            sprintf('%s - cart discount', $label),
            array('cart_adjustments' => array($adjustment), 'conditions' => $conditions),
            $note,
            $existingRule
        );
    }

    /**
     * The `min_subtotal` trigger shared by both shapes of create_cart_discount.
     */
    private static function cartSubtotalConditions($decoded, $existingRule)
    {
        // when editing and the AI didn't mention a subtotal threshold at all, leave the
        // rule's existing condition(s) alone instead of wiping them
        if ( ! array_key_exists('min_subtotal', $decoded)) {
            return $existingRule ? $existingRule['conditions'] : array();
        }

        $conditions = array();
        if ($decoded['min_subtotal'] !== '' && $decoded['min_subtotal'] !== null) {
            $conditions[] = array(
                'type'    => 'cart_subtotal',
                'options' => array(
                    'comparison_value'        => (string)(float)$decoded['min_subtotal'],
                    'comparison_value_method' => '>=',
                ),
            );
        }

        return $conditions;
    }

    /**
     * @return array<string, array{0: string, 1: bool}> pro_adjustment_type => [engine type, is a fee]
     */
    private static function repeatableCartAdjustments()
    {
        return array(
            'discount_per_execution'          => array('discount_repeatable__amount', false),
            'discount_per_item_line'          => array('discount_repeatable_sets_count__amount', false),
            'fee_per_execution'               => array('fee_repeatable__amount', true),
            'fee_per_item_line'               => array('fee_repeatable_sets_count__amount', true),
            'fee_percentage_on_matched_products' => array('fee_matched_products__percentage', true),
        );
    }

    private static function generateCouponCode()
    {
        return 'ADP' . wp_rand(1000, 9999);
    }

    // -----------------------------------------------------------------
    // Pro-only cart adjustment variants of create_cart_discount - a fee, tax exemption, a
    // shipping price override, or coupon suppression/auto-apply. Field shapes confirmed
    // against each adjustment class's own admin view template (BaseVersion/views/
    // cart_adjustments/{fee,empty,shipping-cost}.php, reused by these Pro classes, and
    // ProVersion/views/cart_adjustments/{apply_wc_coupons,suppress_shipping,
    // suppress_payment}.php). See 05-cart-discount.md for the AI-facing contract.
    // -----------------------------------------------------------------

    private static function buildProCartAdjustment($decoded, $existingRule = null)
    {
        $type = (string)$decoded['pro_adjustment_type'];

        $simpleTypes = array(
            'tax_exempt'           => array('tax__exempt', __('tax exempt', 'advanced-dynamic-pricing-for-woocommerce')),
            'free_shipping_adp'    => array('free__shipping_adp', __('free shipping unlocked', 'advanced-dynamic-pricing-for-woocommerce')),
            'disable_all_coupons'  => array('discount__disable_all_wc_coupons', __('all WooCommerce coupons disabled', 'advanced-dynamic-pricing-for-woocommerce')),
        );

        if (isset($simpleTypes[$type])) {
            list($engineType, $label) = $simpleTypes[$type];
            $adjustment = array('type' => $engineType, 'options' => array());
        } elseif (isset(self::repeatableCartAdjustments()[$type])) {
            // charged or given once per time the rule fires / per item line it touched, so
            // these go with a product filter (resolved below)
            if ( ! isset($decoded['pro_adjustment_value']) || $decoded['pro_adjustment_value'] === '') {
                return null;
            }
            list($engineType, $isFee) = self::repeatableCartAdjustments()[$type];
            $value                    = (string)(float)$decoded['pro_adjustment_value'];
            $name                     = ! empty($decoded['pro_adjustment_name'])
                ? sanitize_text_field($decoded['pro_adjustment_name'])
                : ($isFee ? __('Fee', 'advanced-dynamic-pricing-for-woocommerce') : self::generateCouponCode());
            $adjustment               = array('type' => $engineType, 'options' => array(0 => $value, 1 => $name, 2 => ''));
            $label                    = sprintf('%s %s', self::formatNumber((float)$value), str_replace('_', ' ', $type));
        } elseif ($type === 'fee_percentage') {
            if ( ! isset($decoded['pro_adjustment_value']) || $decoded['pro_adjustment_value'] === '') {
                return null;
            }
            $value   = (string)(float)$decoded['pro_adjustment_value'];
            $feeName = ! empty($decoded['pro_adjustment_name']) ? sanitize_text_field($decoded['pro_adjustment_name']) : __('Fee', 'advanced-dynamic-pricing-for-woocommerce');
            // options[2] is the WC tax class slug - left empty ("Not taxable"), not exposed
            // via chat to keep this field set small
            $adjustment = array('type' => 'fee__percentage', 'options' => array(0 => $value, 1 => $feeName, 2 => ''));
            $label      = sprintf(
                /* translators: %s: fee percent */
                __('%s%% fee', 'advanced-dynamic-pricing-for-woocommerce'),
                self::formatNumber((float)$value)
            );
        } elseif ($type === 'disable_coupons' || $type === 'apply_coupons') {
            $codesInput = isset($decoded['pro_adjustment_values']) && is_array($decoded['pro_adjustment_values']) ? $decoded['pro_adjustment_values'] : array();
            if (empty($codesInput)) {
                return null;
            }
            $codes = array();
            foreach ($codesInput as $code) {
                $code = sanitize_text_field((string)$code);
                if ($code === '' || ! wc_get_coupon_id_by_code($code)) {
                    return null;
                }
                $codes[] = $code;
            }
            $engineType = $type === 'disable_coupons' ? 'discount__disable_wc_coupons' : 'discount__apply_wc_coupons';
            $adjustment = array('type' => $engineType, 'options' => $codes);
            $label      = $type === 'disable_coupons'
                ? sprintf(
                    /* translators: %s: comma-separated coupon codes */
                    __('coupon(s) disabled: %s', 'advanced-dynamic-pricing-for-woocommerce'),
                    implode(', ', $codes)
                )
                : sprintf(
                    /* translators: %s: comma-separated coupon codes */
                    __('coupon(s) auto-applied: %s', 'advanced-dynamic-pricing-for-woocommerce'),
                    implode(', ', $codes)
                );
        } elseif (in_array($type, array('shipping_discount_amount', 'shipping_discount_percentage', 'shipping_fixed_price'), true)) {
            $methodName = (string)($decoded['pro_adjustment_target'] ?? '');
            // "all_shipping_methods" list (not "shipping_methods") - matches shipping-cost.php
            $methodId = TargetResolver::resolveHelperListValue('getAllShippingMethods', $methodName);
            if ($methodId === null || ! isset($decoded['pro_adjustment_value']) || $decoded['pro_adjustment_value'] === '') {
                return null;
            }
            $value      = (string)(float)$decoded['pro_adjustment_value'];
            $engineType = array(
                'shipping_discount_amount'     => 'shipping__amount',
                'shipping_discount_percentage' => 'shipping__percentage',
                'shipping_fixed_price'         => 'shipping__fixed',
            )[$type];
            $adjustment = array('type' => $engineType, 'options' => array(0 => (string)$methodId, 1 => $value));
            $label      = sprintf(
                /* translators: %s: shipping method name */
                __('shipping adjustment on "%s"', 'advanced-dynamic-pricing-for-woocommerce'),
                $methodName
            );
        } elseif ($type === 'suppress_shipping_method') {
            $methodName = (string)($decoded['pro_adjustment_target'] ?? '');
            // "shipping_methods" list (not "all_shipping_methods") - matches suppress_shipping.php
            $methodId = TargetResolver::resolveHelperListValue('getShippingMethods', $methodName);
            if ($methodId === null) {
                return null;
            }
            $adjustment = array('type' => 'shipping__suppress_method', 'options' => array(0 => (string)$methodId));
            $label      = sprintf(
                /* translators: %s: shipping method name */
                __('shipping method "%s" hidden', 'advanced-dynamic-pricing-for-woocommerce'),
                $methodName
            );
        } elseif ($type === 'suppress_payment_method') {
            $values = isset($decoded['pro_adjustment_values']) && is_array($decoded['pro_adjustment_values']) ? $decoded['pro_adjustment_values'] : array();
            if (empty($values)) {
                return null;
            }
            $ids = array();
            foreach ($values as $value) {
                $id = TargetResolver::resolveHelperListValue('getPaymentMethods', (string)$value);
                if ($id === null) {
                    return null;
                }
                $ids[] = (string)$id;
            }
            $adjustment = array('type' => 'payment__suppress_method', 'options' => $ids);
            $label      = __('payment method(s) hidden', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            return null;
        }

        $filters    = $existingRule ? $existingRule['filters'] : array();
        $conditions = self::cartSubtotalConditions($decoded, $existingRule);

        // an adjustment that follows the products a rule matches needs to know which those are
        if ( ! empty($decoded['filter_type'])) {
            $target = TargetResolver::resolveFilterTarget($decoded['filter_type'], self::targetValue($decoded, 'filter_value'), $decoded);
            if ( ! $target) {
                return null;
            }
            if ( ! $target['found']) {
                return self::missingTargetResult(
                    sprintf('%s - cart adjustment', $label),
                    array('cart_adjustments' => array($adjustment), 'conditions' => $conditions),
                    self::notFoundNote($target['label'], __('cart adjustment', 'advanced-dynamic-pricing-for-woocommerce')),
                    array(),
                    $existingRule
                );
            }
            $filters = array($target['row']);
        }

        $note = $existingRule
            ? sprintf(
                /* translators: %s: adjustment description, e.g. "10% fee" */
                __('Updated the cart adjustment: %s. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                $label
            )
            : sprintf(
                /* translators: %s: adjustment description, e.g. "10% fee" */
                __('Added a draft cart adjustment: %s. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $label
            );

        return self::successResult(
            $filters,
            sprintf('%s - cart adjustment', $label),
            array('cart_adjustments' => array($adjustment), 'conditions' => $conditions),
            $note,
            $existingRule
        );
    }

    // -----------------------------------------------------------------
    // Gifts (create_gift): free, admin-chosen product given away when a trigger
    // (a filter target in the cart, a minimum subtotal, or unconditionally) is met.
    // Free version only supports a fixed, admin-picked gift - letting the customer
    // choose from a list/category is Pro-only (gift_mode allow_to_choose*).
    // -----------------------------------------------------------------

    private static function buildGift($decoded, $existingRule = null)
    {
        $giftNameGuess = $decoded['gift_product_name'] ?? '';
        // a list to give from / to choose from (`gift_products`, `gift_categories`) is put in
        // place by applyRuleOptions(); the block still has to be built around ONE product, so
        // the first of the list - or, for categories, any product at all - stands in for it
        if ($giftNameGuess === '' && ! empty($decoded['gift_products']) && is_array($decoded['gift_products'])) {
            $giftNameGuess = (string)reset($decoded['gift_products']);
        } elseif ($giftNameGuess === '' && ! empty($decoded['gift_categories'])) {
            $standIn       = wc_get_products(array('limit' => 1, 'status' => 'publish', 'return' => 'ids'));
            $giftNameGuess = $standIn ? (string)wc_get_product($standIn[0])->get_name() : '';
        }
        // editing and the AI left the gift out entirely (only changing the trigger, qty, etc.)
        // -> keep the rule's existing gift untouched
        $giftKept = $giftNameGuess === '' && $existingRule && ! empty($existingRule['get_products']);
        if ($giftNameGuess === '' && ! $giftKept) {
            return null;
        }

        $giftProduct = $giftKept ? null : TargetResolver::findProduct($giftNameGuess);
        $giftQty     = isset($decoded['gift_qty']) && (int)$decoded['gift_qty'] > 0 ? (string)(int)$decoded['gift_qty'] : '1';

        $missingNotes = array();

        // editing and the AI left trigger_type out entirely -> keep this rule's existing
        // trigger (filter and/or subtotal condition) untouched, same convention as filter_type
        $triggerType = $decoded['trigger_type'] ?? '';
        if ($triggerType === '') {
            if ( ! $existingRule) {
                return null;
            }
            $filters    = $existingRule['filters'];
            $conditions = $existingRule['conditions'];
        } else {
            $filters    = array();
            $conditions = array();

            if ($triggerType === 'filter') {
                $target = TargetResolver::resolveFilterTarget($decoded['filter_type'] ?? '', self::targetValue($decoded, 'filter_value'), $decoded);
                if ( ! $target) {
                    return null;
                }
                if ($target['found']) {
                    $filters[] = $target['row'];
                } else {
                    $missingNotes[] = self::notFoundNote($target['label'], __('trigger', 'advanced-dynamic-pricing-for-woocommerce'));
                }
            } elseif ($triggerType === 'subtotal') {
                if ( ! isset($decoded['min_subtotal']) || $decoded['min_subtotal'] === '') {
                    return null;
                }
                $conditions[] = array(
                    'type'    => 'cart_subtotal',
                    'options' => array(
                        'comparison_value'        => (string)(float)$decoded['min_subtotal'],
                        'comparison_value_method' => '>=',
                    ),
                );
            }
            // triggerType === 'none': unconditional gift, both $filters and $conditions stay empty
        }

        $overrides = array('conditions' => $conditions);
        $giftLabel = null;

        if ($giftKept) {
            // only the qty may still change - rebuild get_products with the SAME product(s)
            // but the new qty, unless even qty was left out, in which case don't touch it at all
            $qtyGiven = isset($decoded['gift_qty']) && (int)$decoded['gift_qty'] > 0;
            if ($qtyGiven) {
                $existingGiftValue = $existingRule['get_products']['value'][0] ?? array();
                $overrides['get_products'] = array_merge(
                    $existingRule['get_products'],
                    array('value' => array(array_merge($existingGiftValue, array('qty' => $giftQty))))
                );
            }
        } else {
            $overrides['get_products'] = array(
                'value' => array(
                    array(
                        'qty'       => $giftQty,
                        'type'      => 'giftable_products',
                        'gift_mode' => 'giftable_products',
                        'value'     => $giftProduct ? array((string)$giftProduct->get_id()) : array(),
                    ),
                ),
                'repeat'          => '-1',
                'repeat_subtotal' => '',
            );

            if ( ! $giftProduct) {
                $missingNotes[] = self::notFoundNote($giftNameGuess, __('gift', 'advanced-dynamic-pricing-for-woocommerce'));
            } else {
                $giftLabel = $giftProduct->get_name();
            }
        }

        $title = $giftLabel !== null ? sprintf('Gift: %s', $giftLabel) : sprintf('Gift: %s', $giftNameGuess);

        if ( ! empty($missingNotes)) {
            return self::missingTargetResult($title, $overrides, implode(' ', $missingNotes), $filters, $existingRule);
        }

        if ($existingRule) {
            $note = $giftLabel !== null
                ? sprintf(
                    /* translators: %s: gift product name */
                    __('Updated the gift rule: free "%s". Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $giftLabel
                )
                : __('Updated the gift rule. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = sprintf(
                /* translators: %s: gift product name */
                __('Added a draft gift rule: free "%s". Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $giftLabel
            );
        }

        return self::successResult($filters, $title, $overrides, $note, $existingRule);
    }

    // -----------------------------------------------------------------
    // BOGO free (create_bogo_free): buy one filter target, get a product free.
    // If no separate gift is named and the trigger is a single product, the SAME
    // product is given free (gift_mode "use_product_from_filter").
    // -----------------------------------------------------------------

    private static function buildBogoFree($decoded, $existingRule = null)
    {
        $target = self::keepOrResolveFilters($decoded, $existingRule, false);
        if ( ! $target) {
            return null;
        }

        $giftNameGuess = $decoded['gift_product_name'] ?? '';
        // editing and the AI left the gift out entirely (only changing the buy condition) ->
        // keep the rule's existing gift untouched, whatever it is (same-product or a named one)
        $giftKept = $giftNameGuess === '' && $existingRule && ! empty($existingRule['get_products']);

        if ( ! $giftKept) {
            // "same item for free" (no gift named) only makes sense when the (new-or-kept)
            // buy condition is a single specific product - any other filter type can match
            // many different products, so a specific gift must be named for those
            $firstFilter        = reset($target['filters']);
            $buyIsSingleProduct = $firstFilter && isset($firstFilter['type']) && $firstFilter['type'] === 'products';
            if ( ! $buyIsSingleProduct && $giftNameGuess === '') {
                return null;
            }
        }

        $title = $target['label'] !== null ? sprintf('BOGO: %s', $target['label']) : '';

        if ( ! $target['found']) {
            $overrides = array();
            if ( ! $giftKept) {
                $overrides['get_products'] = $giftNameGuess !== '' ? self::explicitGiftProducts() : self::sameProductGift();
            }

            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($target['label'], __('BOGO settings', 'advanced-dynamic-pricing-for-woocommerce')),
                array(),
                $existingRule
            );
        }

        $overrides = array();
        $noteName  = null;

        if ( ! $giftKept) {
            if ($giftNameGuess !== '') {
                $giftProduct = TargetResolver::findProduct($giftNameGuess);
                if ( ! $giftProduct) {
                    return self::missingTargetResult(
                        $title,
                        array('get_products' => self::sameProductGift()),
                        self::notFoundNote($giftNameGuess, __('gift', 'advanced-dynamic-pricing-for-woocommerce')),
                        $target['filters'],
                        $existingRule
                    );
                }
                $getProducts = self::explicitGiftProducts();
                $getProducts['value'][0]['value'] = array((string)$giftProduct->get_id());
                $overrides['get_products'] = $getProducts;
                $noteName = $giftProduct->get_name();
            } else {
                $overrides['get_products'] = self::sameProductGift();
                $noteName = $target['label'];
            }
        }

        if ($existingRule) {
            $note = $noteName !== null
                ? sprintf(
                    /* translators: %s: gift product name */
                    __('Updated the BOGO rule: free "%s" with the qualifying purchase. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $noteName
                )
                : __('Updated the BOGO rule. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = sprintf(
                /* translators: %s: gift product name */
                __('Added a draft BOGO rule: free "%s" with the qualifying purchase. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $noteName
            );
        }

        return self::successResult($target['filters'], $title, $overrides, $note, $existingRule);
    }

    private static function sameProductGift()
    {
        return array(
            'value' => array(
                array('qty' => '1', 'type' => 'giftable_products', 'gift_mode' => 'use_product_from_filter', 'value' => array()),
            ),
            'repeat'          => '-1',
            'repeat_subtotal' => '',
        );
    }

    private static function explicitGiftProducts()
    {
        return array(
            'value' => array(
                array('qty' => '1', 'type' => 'giftable_products', 'gift_mode' => 'giftable_products', 'value' => array()),
            ),
            'repeat'          => '-1',
            'repeat_subtotal' => '',
        );
    }

    // -----------------------------------------------------------------
    // BOGO discount (create_bogo_discount, Pro-only): same shape as create_bogo_free
    // (a qualifying filter target triggers a second item), except the second item is sold
    // at a REDUCED, non-zero price via Auto Add to Cart (`auto_add_products`) rather than
    // given away free via Gifts (`get_products`) - the free Gifts mechanism has no "at a
    // discount" mode, only free. See 07-bogo.md for the AI-facing contract.
    // Built unconditionally regardless of isProActive() - licensing is checked
    // programmatically after the fact, see detectProFeature().
    // -----------------------------------------------------------------

    private static function buildBogoDiscount($decoded, $existingRule = null)
    {
        $target = self::keepOrResolveFilters($decoded, $existingRule, false);
        if ( ! $target) {
            return null;
        }

        $existingRow = $existingRule['auto_add_products']['value'][0] ?? null;
        $existingAdjustment = $existingRow
            ? array('type' => $existingRow['discount_type'] ?? '', 'value' => $existingRow['discount_value'] ?? '')
            : null;
        $discount = self::resolveDiscount($decoded, $existingAdjustment);
        if ( ! $discount) {
            return null;
        }

        $giftNameGuess = $decoded['gift_product_name'] ?? '';
        // editing and the AI left the discounted item out entirely (only changing the buy
        // condition or the discount) -> keep the rule's existing discounted item untouched
        $giftKept = $giftNameGuess === '' && $existingRule && ! empty($existingRule['auto_add_products']);

        if ( ! $giftKept) {
            // "same item at a discount" (no item named) only makes sense when the
            // (new-or-kept) buy condition is a single specific product
            $firstFilter        = reset($target['filters']);
            $buyIsSingleProduct = $firstFilter && isset($firstFilter['type']) && $firstFilter['type'] === 'products';
            if ( ! $buyIsSingleProduct && $giftNameGuess === '') {
                return null;
            }
        }

        $title = $target['label'] !== null ? sprintf('BOGO discount: %s', $target['label']) : '';

        if ( ! $target['found']) {
            $overrides = array();
            if ( ! $giftKept) {
                $overrides['auto_add_products'] = self::autoAddProducts(
                    $giftNameGuess !== '' ? 'auto_add_products' : 'use_product_from_filter',
                    array(),
                    $discount
                );
            }

            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($target['label'], __('BOGO discount settings', 'advanced-dynamic-pricing-for-woocommerce')),
                array(),
                $existingRule
            );
        }

        $overrides = array();
        $noteName  = null;

        if ( ! $giftKept) {
            if ($giftNameGuess !== '') {
                $giftProduct = TargetResolver::findProduct($giftNameGuess);
                if ( ! $giftProduct) {
                    return self::missingTargetResult(
                        $title,
                        array('auto_add_products' => self::autoAddProducts('use_product_from_filter', array(), $discount)),
                        self::notFoundNote($giftNameGuess, __('discounted item', 'advanced-dynamic-pricing-for-woocommerce')),
                        $target['filters'],
                        $existingRule
                    );
                }
                $overrides['auto_add_products'] = self::autoAddProducts('auto_add_products', array((string)$giftProduct->get_id()), $discount);
                $noteName = $giftProduct->get_name();
            } else {
                $overrides['auto_add_products'] = self::autoAddProducts('use_product_from_filter', array(), $discount);
                $noteName = $target['label'];
            }
        } elseif ( ! $discount['kept']) {
            $existingValue = $existingRule['auto_add_products']['value'][0] ?? array();
            $overrides['auto_add_products'] = array_merge($existingRule['auto_add_products'], array(
                'value' => array(array_merge($existingValue, array(
                    'discount_type'  => $discount['engineType'],
                    'discount_value' => (string)$discount['value'],
                ))),
            ));
            $noteName = $target['label'];
        }

        $discountLabel = self::discountLabel($discount);

        if ($existingRule) {
            $note = $noteName !== null
                ? sprintf(
                    /* translators: 1: discount description (e.g. "50% off"), 2: item name */
                    __('Updated the BOGO discount rule: %1$s "%2$s" with the qualifying purchase. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $discountLabel,
                    $noteName
                )
                : __('Updated the BOGO discount rule. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce');
        } else {
            $note = sprintf(
                /* translators: 1: discount description (e.g. "50% off"), 2: item name */
                __('Added a draft BOGO discount rule: %1$s "%2$s" with the qualifying purchase. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $discountLabel,
                $noteName
            );
        }

        return self::successResult($target['filters'], $title, $overrides, $note, $existingRule);
    }

    private static function autoAddProducts($mode, $value, $discount)
    {
        return array(
            'value' => array(
                array(
                    'qty'            => '1',
                    'type'           => 'auto_add_products',
                    'auto_add_mode'  => $mode,
                    'value'          => $value,
                    'discount_type'  => $discount['engineType'],
                    'discount_value' => (string)$discount['value'],
                ),
            ),
            'repeat'          => '-1',
            'repeat_subtotal' => '',
        );
    }

    // -----------------------------------------------------------------
    // The other way to describe a product set (create_set_discount with `bundle_products`):
    // SPECIFIC products sold together for one price - "one Cap and one Sunglasses for $40". One
    // filter row per product (each with its own quantity) and ONE discount for the whole set,
    // unlike a combo deal, where the discount goes to one of the rows only.
    // -----------------------------------------------------------------

    private static function buildBundle($decoded, $existingRule = null)
    {
        $existingTotal = ($existingRule['product_adjustments']['type'] ?? '') === 'total' ? ($existingRule['product_adjustments']['total'] ?? null) : null;

        // "for $40" is a price, so that is what a bare number means here
        if (isset($decoded['discount_value']) && $decoded['discount_value'] !== '' && empty($decoded['discount_type'])) {
            $decoded['discount_type'] = 'fixed_price';
        }
        $discount = self::resolveDiscount($decoded, $existingTotal);
        if ( ! $discount) {
            return null;
        }

        $missing = null;
        $labels  = array();
        if ( ! empty($decoded['bundle_products']) && is_array($decoded['bundle_products'])) {
            // the same product named twice means two units of it
            $quantities = array();
            foreach ($decoded['bundle_products'] as $name) {
                $product = TargetResolver::findProduct((string)$name);
                if ( ! $product) {
                    $missing = (string)$name;
                    continue;
                }
                $id              = (string)$product->get_id();
                $quantities[$id] = ($quantities[$id] ?? 0) + 1;
                $labels[$id]     = $product->get_name();
            }

            $filters = array();
            foreach ($quantities as $id => $qty) {
                $filters[] = array_merge(TargetResolver::filterRow('products', array((string)$id)), array(
                    'qty'        => (string)$qty,
                    'limitation' => 'none',
                ));
                if ($qty > 1) {
                    $labels[$id] = $qty . ' x ' . $labels[$id];
                }
            }
        } elseif ($existingRule && ! empty($existingRule['filters'])) {
            $filters = $existingRule['filters'];
        } else {
            return null;
        }

        // a single row of one unit would be a plain product discount, not a bundle
        if ($missing === null && count($filters) === 1 && (int)$filters[0]['qty'] < 2) {
            return null;
        }

        $overrides = array();
        if ( ! $discount['kept']) {
            $overrides['product_adjustments'] = self::simpleProductAdjustment($discount);
        }
        $additionalOverride = self::additionalOverride($decoded, $existingRule);
        if ($additionalOverride !== null) {
            $overrides['additional'] = $additionalOverride;
        }

        $bundleLabel = $labels ? implode(' + ', $labels) : 'bundle';
        $title       = self::draftTitle('Bundle: ' . $bundleLabel, $discount);

        if ($missing !== null) {
            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($missing, __('bundle settings', 'advanced-dynamic-pricing-for-woocommerce')),
                $filters,
                $existingRule
            );
        }

        $note = $existingRule
            ? __('Updated the bundle. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce')
            : sprintf(
                /* translators: 1: the bundled products, 2: discount description */
                __('Added a draft bundle: %1$s together at %2$s. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $bundleLabel,
                self::discountLabel($discount)
            );

        return self::successResult($filters, $title, $overrides, $note, $existingRule);
    }

    // -----------------------------------------------------------------
    // Combo deal (create_combo_deal, Pro-only): the rule editor's "3x2" template - a set made
    // of TWO filter rows with a discount split between them. Row 1 is what the customer buys
    // at full price (`buy_qty` units, the more expensive ones), row 2 is what gets the discount
    // (`get_qty` units, the cheaper ones). Covers both "buy Cap and Sunglasses, get Album free"
    // (named products per row) and "add any 3 products, the cheapest one is free" (`any_product`:
    // both rows match any product, and the priorities pick which unit lands where).
    // The discount applies once per complete set, which a plain discount gated by a
    // products-in-cart condition can't do (that one discounts every unit of the target).
    // -----------------------------------------------------------------

    private static function buildComboDeal($decoded, $existingRule = null)
    {
        $existingRows  = isset($existingRule['filters']) && count($existingRule['filters']) === 2 ? array_values($existingRule['filters']) : null;
        $existingSplit = $existingRule['product_adjustments']['split'][1] ?? null;

        $discount = self::resolveDiscount($decoded, $existingSplit);
        if ( ! $discount) {
            // "the cheapest one is free" is the deal this action exists for
            $discount = array('uiType' => 'percentage', 'engineType' => 'discount__percentage', 'value' => 100.0, 'kept' => false);
        }

        $missing = null;
        $resolve = function ($names) use (&$missing) {
            $ids    = array();
            $labels = array();
            foreach ((array)$names as $name) {
                $product = TargetResolver::findProduct((string)$name);
                if ( ! $product) {
                    $missing = (string)$name;
                    continue;
                }
                $ids[]    = (string)$product->get_id();
                $labels[] = $product->get_name();
            }

            return array($ids, $labels);
        };

        $buyGiven = ! empty($decoded['buy_products']);
        $getGiven = ! empty($decoded['get_products']);

        list($buyIds, $buyLabels) = $buyGiven ? $resolve($decoded['buy_products']) : array($existingRows ? (array)$existingRows[0]['value'] : array(), array());
        list($getIds, $getLabels) = $getGiven ? $resolve($decoded['get_products']) : array($existingRows ? (array)$existingRows[1]['value'] : array(), array());

        if (isset($decoded['buy_qty']) && (int)$decoded['buy_qty'] >= 1) {
            $buyQty = (int)$decoded['buy_qty'];
        } elseif ($existingRows && ! $buyGiven) {
            $buyQty = (int)$existingRows[0]['qty'];
        } else {
            // one of each named product; with nothing named, the classic 3x2
            $buyQty = $buyGiven ? count((array)$decoded['buy_products']) : 2;
        }
        if (isset($decoded['get_qty']) && (int)$decoded['get_qty'] >= 1) {
            $getQty = (int)$decoded['get_qty'];
        } else {
            $getQty = $existingRows ? (int)$existingRows[1]['qty'] : 1;
        }

        // with no products named: `any_product` makes both rows match every product ("any 3
        // products, the cheapest is free"); otherwise they are left as empty "Products" rows for
        // the admin to fill in, which is what the rule editor's own 3x2 template starts from
        $emptyType = ! empty($decoded['any_product']) ? 'any' : 'products';
        if ($existingRows && ! array_key_exists('any_product', $decoded) && ! $buyGiven && ! $buyIds) {
            $emptyType = $existingRows[0]['type'];
        }
        $row = function ($ids, $qty, $priority) use ($emptyType) {
            return array_merge(TargetResolver::filterRow($ids ? 'products' : $emptyType, $ids), array(
                'qty'             => (string)$qty,
                'select_priority' => $priority,
                'limitation'      => 'none',
            ));
        };
        $filters = array($row($buyIds, $buyQty, 'expensive'), $row($getIds, $getQty, 'cheap'));

        $overrides = array(
            'product_adjustments' => array(
                'type'              => 'split',
                'total'             => array('value' => ''),
                'split'             => array(
                    array('type' => 'discount__amount', 'value' => ''),
                    array('type' => $discount['engineType'], 'value' => (string)$discount['value']),
                ),
                'max_discount_sum'  => '',
                'split_discount_by' => 'cost',
            ),
        );
        $additionalOverride = self::additionalOverride($decoded, $existingRule);
        if ($additionalOverride !== null) {
            $overrides['additional'] = $additionalOverride;
        }

        $buyLabel = $buyLabels ? implode(' + ', $buyLabels) : sprintf($emptyType === 'any' ? 'any %d' : '%d products', $buyQty);
        $getLabel = $getLabels ? implode(' / ', $getLabels) : 'the cheapest';
        $title    = sprintf('%d+%d: %s', $buyQty, $getQty, self::draftTitle($buyLabel . ' -> ' . $getLabel, $discount));

        if ($missing !== null) {
            return self::missingTargetResult(
                $title,
                $overrides,
                self::notFoundNote($missing, __('combo deal settings', 'advanced-dynamic-pricing-for-woocommerce')),
                $filters,
                $existingRule
            );
        }

        $note = $existingRule
            ? __('Updated the combo deal. Review the changes below and click Save to apply them.', 'advanced-dynamic-pricing-for-woocommerce')
            : sprintf(
                /* translators: 1: what is bought, 2: what is discounted, 3: discount description */
                __('Added a draft combo deal: buy %1$s, get %2$s at %3$s. Review it below and click Save to apply it.', 'advanced-dynamic-pricing-for-woocommerce'),
                $buyLabel,
                $getLabel,
                self::discountLabel($discount)
            );

        return self::successResult($filters, $title, $overrides, $note, $existingRule);
    }

    // -----------------------------------------------------------------
    // Pro extras: `pro_conditions`, `conditions_relationship`, and `exclusive` are accepted
    // on top of ANY action (not just create_cart_discount) - they're rule-level settings
    // independent of which discount mechanism the rule uses. Applied to the already-built
    // rule array (applyAction() calls this right after dispatching to the per-action
    // builder) rather than threaded through every builder individually, since every builder
    // already produces the same Rule::getData()-shaped array regardless of action type. See
    // 09-conditions.md for the AI-facing `pro_conditions` contract.
    // -----------------------------------------------------------------

    private static function applyProExtras($decoded, $action)
    {
        if ( ! $action) {
            return $action;
        }

        $touched = false;

        // `conditions` takes any condition type of the rule editor; `pro_conditions` is the older
        // name for the same list and is read the same way
        if (array_key_exists('pro_conditions', $decoded) || array_key_exists('conditions', $decoded)) {
            $touched  = true;
            $input    = array_merge(
                is_array($decoded['conditions'] ?? null) ? $decoded['conditions'] : array(),
                is_array($decoded['pro_conditions'] ?? null) ? $decoded['pro_conditions'] : array()
            );
            $resolved = self::resolveProConditions($input);

            if ($resolved === null) {
                $action['found'] = false;
                $action['note']  = trim(
                    ($action['note'] ?? '') . ' ' . ConditionBuilder::$problem . ' ' .
                    __('Could not set up the extra condition(s) you asked for - the rest of the rule was still drafted, so please add/adjust them yourself in the Conditions section.', 'advanced-dynamic-pricing-for-woocommerce')
                );
            } else {
                // additive, on top of whatever the per-action builder already put in
                // `conditions` (e.g. create_cart_discount's own min_subtotal condition) -
                // not a wholesale replacement of it
                $existingRows = $action['rule']['conditions'] ?? array();
                $action['rule']['conditions'] = array_merge($existingRows, $resolved);
            }
        }

        if ( ! empty($decoded['conditions_relationship']) && in_array($decoded['conditions_relationship'], array('and', 'or'), true)) {
            $touched = true;
            $action['rule']['additional']['conditions_relationship'] = $decoded['conditions_relationship'];
        }

        if (array_key_exists('exclusive', $decoded)) {
            $touched = true;
            $action['rule']['exclusive'] = ! empty($decoded['exclusive']) ? 1 : 0;
        }

        if ($touched && $action['found']) {
            // titles/links were computed by the per-action builder BEFORE these extras were
            // merged in - recompute so any newly-referenced id (a product in
            // customer_ordered_products, a collection, etc.) resolves to a real name instead
            // of showing as a raw id in the chat/UI
            $filterTitles     = RuleDescriber::filterTitlesHelper();
            $action['titles'] = $filterTitles->getTitles(array($action['rule']));
            $action['links']  = $filterTitles->getLinks(array($action['rule']));
        }

        return $action;
    }

    /**
     * @param array $input raw `pro_conditions` array from the model, each item
     *                      `{type, ...type-specific fields}` per 09-conditions.md
     *
     * @return array[]|null resolved `{type, options}` condition rows (rule[conditions][...]
     *                       shape), or null if ANY item couldn't be resolved
     */
    private static function resolveProConditions($input)
    {
        if (empty($input)) {
            return array();
        }

        $rows = array();
        foreach ($input as $item) {
            if ( ! is_array($item) || empty($item['type'])) {
                return null;
            }

            ConditionBuilder::$problem = '';
            $resolvedRows = self::resolveProCondition($item['type'], $item);
            if ($resolvedRows === null) {
                // not one of the types spelled out below: the general builder knows every
                // condition type the rule editor has
                $resolvedRows = ConditionBuilder::build($item);
            }
            if ($resolvedRows === null) {
                return null;
            }

            foreach ($resolvedRows as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Resolves ONE `pro_conditions` item into one or more `{type, options}` condition rows
     * (date_time with both `from` and `to` produces two rows - the engine's date_time
     * condition is single-sided per row: "from" or "to", not a range in one row). Returns
     * null if the type is unknown/unsupported here or a required field couldn't be resolved.
     */
    private static function resolveProCondition($type, $item)
    {
        static $listHelperMap = array(
            'customer_shipping_zone'       => 'getShippingZones',
            'shipping_state'                => 'getStates',
            'customer_geolocation_state'    => 'getStates',
            'customer_geolocation_country'  => 'getCountries',
            'customer_payment_method'       => 'getPaymentMethods',
            'cart_currency'                 => 'getCurrencies',
        );

        static $numericTypes = array(
            'customer_paid_order_count',
            'customer_spent_within',
            'customer_avg_order_amount',
            'customer_last_order_amount',
        );

        if (isset($listHelperMap[$type])) {
            $values = isset($item['values']) && is_array($item['values']) ? $item['values'] : array();
            if (empty($values)) {
                return null;
            }

            $resolved = array();
            foreach ($values as $value) {
                $id = TargetResolver::resolveHelperListValue($listHelperMap[$type], (string)$value);
                if ($id === null) {
                    return null;
                }
                $resolved[] = (string)$id;
            }

            $method = ($item['match'] ?? '') === 'not_in_list' ? 'not_in_list' : 'in_list';

            return array(array(
                'type'    => $type,
                'options' => array(
                    'comparison_list'        => $resolved,
                    'comparison_list_method' => $method,
                ),
            ));
        }

        if ($type === 'cart_coupons') {
            // no list needed: the cart has no coupon at all / has some coupon, whichever it is
            $anyOrNone = array('none_at_all' => 'none_at_all', 'any_applied' => 'at_least_one_any');
            if (isset($anyOrNone[$item['match'] ?? ''])) {
                return array(array(
                    'type'    => 'cart_coupons',
                    'options' => array('comparison_list_method' => $anyOrNone[$item['match']]),
                ));
            }

            $values = isset($item['values']) && is_array($item['values']) ? $item['values'] : array();
            if (empty($values)) {
                return null;
            }

            $codes = array();
            foreach ($values as $value) {
                $code = sanitize_text_field((string)$value);
                if ($code === '' || ! wc_get_coupon_id_by_code($code)) {
                    return null;
                }
                $codes[] = $code;
            }

            // CartCoupons uses the 6-value compareLists() method set (at_least_one_any /
            // at_least_one / all / only / none / none_at_all), not plain in_list/not_in_list
            $method = ($item['match'] ?? '') === 'not_in_list' ? 'none' : 'at_least_one';

            return array(array(
                'type'    => 'cart_coupons',
                'options' => array(
                    'comparison_list'        => $codes,
                    'comparison_list_method' => $method,
                ),
            ));
        }

        if ($type === 'customer_ordered_products') {
            $values = isset($item['values']) && is_array($item['values']) ? $item['values'] : array();
            if (empty($values)) {
                return null;
            }

            $ids = array();
            foreach ($values as $value) {
                $product = TargetResolver::findProduct((string)$value);
                if ( ! $product) {
                    return null;
                }
                $ids[] = (string)$product->get_id();
            }

            $method = ($item['match'] ?? '') === 'not_in_list' ? 'not_in_list' : 'in_list';

            return array(array(
                'type'    => 'customer_ordered_products',
                'options' => array(
                    'comparison_list'        => $ids,
                    'comparison_list_method' => $method,
                ),
            ));
        }

        if (in_array($type, $numericTypes, true)) {
            if ( ! isset($item['operator'], $item['value']) || $item['value'] === '') {
                return null;
            }
            $operator = (string)$item['operator'];
            if ( ! in_array($operator, array('<', '<=', '>=', '>'), true)) {
                return null;
            }

            return array(array(
                'type'    => $type,
                'options' => array(
                    'comparison_value'        => (string)(float)$item['value'],
                    'comparison_value_method' => $operator,
                    // all four numeric Pro conditions accept a time_range option; default to
                    // "all_time" when the model doesn't give one, matching the UI's own
                    // default - the engine needs SOME value here to be valid
                    'time_range'              => ! empty($item['time_range']) ? (string)$item['time_range'] : 'all_time',
                ),
            ));
        }

        if ($type === 'customer_is_first_order') {
            if ( ! array_key_exists('value', $item)) {
                return null;
            }

            return array(array(
                'type'    => 'customer_is_first_order',
                'options' => array('comparison_bin_value' => ! empty($item['value']) ? 'yes' : 'no'),
            ));
        }

        if ($type === 'date_time') {
            $rows = array();
            if ( ! empty($item['from'])) {
                $rows[] = array('type' => 'date_time', 'options' => array(
                    'comparison_datetime'        => sanitize_text_field((string)$item['from']),
                    'comparison_datetime_method' => 'from',
                ));
            }
            if ( ! empty($item['to'])) {
                $rows[] = array('type' => 'date_time', 'options' => array(
                    'comparison_datetime'        => sanitize_text_field((string)$item['to']),
                    'comparison_datetime_method' => 'to',
                ));
            }

            return ! empty($rows) ? $rows : null;
        }

        return null;
    }

    /**
     * A filter's value(s): the list from `<field>s` (`filter_values`) when several targets were
     * named, otherwise the single `<field>` - which may itself hold a list.
     *
     * @return string|string[]
     */
    private static function targetValue($decoded, $field)
    {
        if ( ! empty($decoded[$field . 's']) && is_array($decoded[$field . 's'])) {
            return $decoded[$field . 's'];
        }

        return $decoded[$field] ?? '';
    }

    private static function notFoundNote($value, $context)
    {
        return sprintf(
            /* translators: 1: the name/value the AI tried to match, 2: short noun phrase for what was being drafted, e.g. "discount settings" */
            __('Could not find "%1$s" - added the %2$s as a draft, but you\'ll need to pick it yourself in the filter field above.', 'advanced-dynamic-pricing-for-woocommerce'),
            $value,
            $context
        );
    }

    /**
     * Resolves the filter target for an action, same as resolveFilterTarget() - except when the AI
     * omitted `filter_type` entirely: when editing (`$existingRule` given), that means "don't change
     * the target", so the rule's current `filters` are kept as-is; when drafting a brand new rule,
     * that's only valid for actions where a blank target legitimately means "store-wide"
     * ($allowEmptyWhenCreating) - role discount and role+bulk discount, not a plain product discount
     * or bulk discount, which always need an explicit target (use filter_type "any" for that).
     *
     * @return array{filters: array, label: string|null, found: bool}|null null = invalid input, abort
     */
    private static function keepOrResolveFilters($decoded, $existingRule, $allowEmptyWhenCreating)
    {
        $filterType = $decoded['filter_type'] ?? '';

        // "also add the Cap to this rule": new targets are named but not their kind - it is the
        // kind the rule's filter already has
        $newTargets = self::targetValue($decoded, 'filter_value');
        if ($filterType === '' && $existingRule && $newTargets !== '' && $newTargets !== array()) {
            $kinds = array(
                'products'              => 'product',
                'product_categories'    => 'category',
                'product_category_slug' => 'category_slug',
                'product_tags'          => 'tag',
                'product_sku'           => 'sku',
                'product_collections'   => 'collection',
            );
            $filterType = $kinds[$existingRule['filters'][0]['type'] ?? ''] ?? '';
        }

        if ($filterType === '') {
            if ($existingRule) {
                return array('filters' => self::applyExclude($existingRule['filters'], $decoded), 'label' => null, 'found' => true);
            }
            if ($allowEmptyWhenCreating) {
                return array('filters' => array(), 'label' => null, 'found' => true);
            }

            return null;
        }

        $target = TargetResolver::resolveFilterTarget($filterType, self::targetValue($decoded, 'filter_value'), $decoded);
        if ( ! $target) {
            return null;
        }

        $filters = $target['found'] ? array($target['row']) : array();

        return array(
            'filters' => self::applyExclude($filters, $decoded),
            'label'   => $target['label'],
            'found'   => $target['found'],
        );
    }

    /**
     * Applies an "exclude this from the filter" request onto the resolved filter row's
     * `excludes` sub-array - e.g. "apply to all products except Cap" (filter_type: any,
     * exclude_filter_type: product, exclude_filter_value: Cap). Reuses the exact same
     * filter_type/filter_value (+ taxonomy_name/attribute_name) vocabulary as the main filter,
     * just prefixed `exclude_`. Silently no-ops if there's nothing to exclude from, no exclude
     * was requested, or the exclude target itself doesn't resolve - excluding is a nice-to-have
     * on top of the main filter, not worth failing the whole draft over.
     */
    private static function applyExclude($filters, $decoded)
    {
        $excludeType = $decoded['exclude_filter_type'] ?? '';
        if (empty($filters) || $excludeType === '') {
            return $filters;
        }

        $target = TargetResolver::resolveFilterTarget($excludeType, self::targetValue($decoded, 'exclude_filter_value'), array(
            'taxonomy_name'  => $decoded['exclude_taxonomy_name'] ?? '',
            'attribute_name' => $decoded['exclude_attribute_name'] ?? '',
        ));

        if ($target && $target['found']) {
            $excludes = $filters[0]['excludes'] ?? array();
            $excludes[] = array('type' => $target['row']['type'], 'value' => $target['row']['value']);
            $filters[0]['excludes'] = $excludes;
        }

        return $filters;
    }

    // -----------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------

    /**
     * @param array|null $existingAdjustment when editing, the rule's current `product_adjustments.total`
     *                                       (or `role_discounts.rows[0]` reshaped the same way: {type, value})
     *                                       to fall back to when the AI omitted discount_type/discount_value
     *                                       entirely (i.e. isn't changing the discount, just something else)
     *
     * @return array{uiType: string|null, engineType: string, value: float, kept: bool}|null `kept` true means
     *         the caller should leave the corresponding override out entirely and keep the existing block as-is
     */
    private static function resolveDiscount($decoded, $existingAdjustment = null)
    {
        $hasType  = array_key_exists('discount_type', $decoded) && $decoded['discount_type'] !== '';
        $hasValue = array_key_exists('discount_value', $decoded) && $decoded['discount_value'] !== '';

        // the price comes from a formula (`price_formula`): there is no number to give, and
        // applyRuleOptions() puts the formula in place of this stand-in
        if ( ! $hasType && ! $hasValue && ! empty($decoded['price_formula'])) {
            return array('uiType' => 'fixed_price', 'engineType' => 'price__fixed', 'value' => 0.0, 'kept' => false);
        }

        if ( ! $hasType && ! $hasValue && $existingAdjustment) {
            if ( ! isset($existingAdjustment['type']) || ! isset($existingAdjustment['value']) || $existingAdjustment['value'] === '') {
                return null;
            }

            return array(
                'uiType'     => null,
                'engineType' => $existingAdjustment['type'],
                'value'      => (float)$existingAdjustment['value'],
                'kept'       => true,
            );
        }

        $uiType = $decoded['discount_type'] ?? 'percentage';
        $engineType = self::mapDiscountEngineType($uiType);

        if ( ! $engineType || ! $hasValue) {
            return null;
        }

        return array(
            'uiType'     => $uiType,
            'engineType' => $engineType,
            'value'      => (float)$decoded['discount_value'],
            'kept'       => false,
        );
    }

    private static function mapDiscountEngineType($uiType)
    {
        $typeMap = array(
            'percentage'            => 'discount__percentage',
            'fixed_amount'          => 'discount__amount',
            'fixed_price'           => 'price__fixed',
            // for a set: the amount / the price of EACH unit rather than of the set as a whole
            'fixed_amount_per_item' => 'discount__amount_per_item',
            'fixed_price_per_item'  => 'price__fixed_per_item',
        );

        return $typeMap[$uiType] ?? null;
    }

    /**
     * @param array|null $ranges raw AI-provided ranges: [{from, to, value}, ...]
     *
     * @return array|null normalized ranges, or null if input is empty/invalid
     */
    private static function resolveRanges($ranges)
    {
        if ( ! is_array($ranges) || empty($ranges)) {
            return null;
        }

        $normalized = array();
        foreach ($ranges as $range) {
            if ( ! isset($range['from']) || ! isset($range['value']) || $range['from'] === '' || $range['value'] === '') {
                continue;
            }
            // an open-ended last tier may come as no `to`, null, or - from a client whose schema has
            // no null for it - 0; a `to` below `from` means the same
            $from = (int)$range['from'];
            $to   = isset($range['to']) && $range['to'] !== null && $range['to'] !== '' ? (int)$range['to'] : 0;
            $normalized[] = array(
                'from'  => (string)$from,
                'to'    => $to >= $from ? (string)$to : '',
                'value' => (string)(float)$range['value'],
            );
        }

        return ! empty($normalized) ? $normalized : null;
    }

    private static function replaceAdditional($asCoupon, $couponName)
    {
        if ( ! $asCoupon) {
            return array();
        }

        return array('is_replace' => true, 'replace_name' => $couponName);
    }

    /**
     * Builds the `additional` override for the coupon-replace toggle, OR signals that it
     * should be left out of the overrides entirely - when editing and the AI didn't mention
     * `as_coupon` at all, the existing rule's `additional` (which may hold unrelated settings
     * too, e.g. a date range) must be preserved rather than reset to `replaceAdditional(false)`.
     *
     * @return array|null null = omit this key from $overrides (keep whatever's already there)
     */
    private static function additionalOverride($decoded, $existingRule)
    {
        if ($existingRule && ! array_key_exists('as_coupon', $decoded)) {
            return null;
        }

        $asCoupon   = ! empty($decoded['as_coupon']);
        $couponName = $asCoupon && ! empty($decoded['coupon_name']) ? sanitize_text_field($decoded['coupon_name']) : '';

        return self::replaceAdditional($asCoupon, $couponName);
    }

    private static function draftTitle($name, $discount)
    {
        $value = self::formatNumber($discount['value']);

        switch ($discount['engineType']) {
            case 'discount__amount':
                return $name . ' -' . $value;
            case 'price__fixed':
                return $name . ' @ ' . $value;
            default:
                return $name . ' -' . $value . '%';
        }
    }

    private static function discountLabel($discount)
    {
        $value = self::formatNumber($discount['value']);

        switch ($discount['engineType']) {
            case 'discount__amount':
                return sprintf(
                    /* translators: %s: discount amount */
                    __('%s off', 'advanced-dynamic-pricing-for-woocommerce'),
                    $value
                );
            case 'price__fixed':
                return sprintf(
                    /* translators: %s: fixed price */
                    __('price set to %s', 'advanced-dynamic-pricing-for-woocommerce'),
                    $value
                );
            default:
                return sprintf(
                    /* translators: %s: discount percentage */
                    __('%s%% off', 'advanced-dynamic-pricing-for-woocommerce'),
                    $value
                );
        }
    }

    private static function formatNumber($value)
    {
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }

    /**
     * Builds the common Rule::getData() shape with all blocks empty, then merges
     * caller-supplied overrides (filters + whichever of product_adjustments /
     * bulk_adjustments / role_discounts / cart_adjustments / get_products /
     * conditions / additional the action actually needs) on top.
     *
     * `type` (single_item) and `rule_type` (common/persistent/exclusive) are two
     * different enums - mixing them up makes the admin rules.js pick a non-existent
     * "<rule_type>_rule" template and silently fall back to the wrong one. The
     * engine itself doesn't actually read `type` for routing (that's inferred from
     * `filters`/adjustment shape), but rules.js's own template selection does.
     *
     * Filter values must be strings, not ints: rules.js's update_product_filter_fields()
     * does `id.replace(...)` on each filter value while rendering the select2 option.
     *
     * @param array      $filters      zero or more filterRow() entries
     * @param string     $title        ignored when `$existingRule` is given - an edit never
     *                                 renames the rule, only an explicit future "rename" action would
     * @param array      $overrides    keys from the Rule::getData() shape to merge in
     * @param array|null $existingRule when editing, the rule's current Rule::getData() shape
     *                                 (from loadRule()) to start from instead of a blank template -
     *                                 keeps its `id` (so Save updates it in place) and every block
     *                                 this particular action doesn't touch (e.g. drafting a new
     *                                 discount amount for a rule that also has a gift configured
     *                                 doesn't wipe the gift)
     *
     * @return array Rule::getData() shape, ready for the admin JS to render
     */
    private static function buildRuleData($filters, $title, $overrides, $existingRule = null)
    {
        if ($existingRule) {
            $data = $existingRule;
        } else {
            $data = array(
                'id'                       => null,
                'deleted'                  => 0,
                'enabled'                  => 1,
                'exclusive'                => 0,
                'title'                    => sanitize_text_field($title),
                'rule_type'                => 'common',
                'type'                     => 'single_item',
                'priority'                 => 0,
                'options'                  => array(),
                'conditions'               => array(),
                'limits'                   => array(),
                'cart_adjustments'         => array(),
                'product_adjustments'      => array(),
                'sortable_blocks_priority' => array(),
                'bulk_adjustments'         => array(),
                'role_discounts'           => array(),
                'get_products'             => array(),
                'auto_add_products'        => array(),
                'additional'               => array(),
                'advertising'              => array(),
                'condition_message'        => array(),
            );
        }

        $data['filters'] = $filters;
        $data = array_merge($data, $overrides);

        return Rule::fromArray($data)->getData();
    }

    private static function successResult($filters, $title, $overrides, $note, $existingRule = null)
    {
        $ruleData     = self::buildRuleData($filters, $title, $overrides, $existingRule);
        $filterTitles = RuleDescriber::filterTitlesHelper();

        return array(
            'note'    => $note,
            'found'   => true,
            'rule'    => $ruleData,
            // same AdminPageFilterTitles the normal rule list uses for its own
            // wdp_data.titles / wdp_data.links - so the admin JS shows the real
            // name (and, on Pro with the option on, a link) the same way it
            // would for any other rule, and links stay a no-op on Free for free
            'titles'  => $filterTitles->getTitles(array($ruleData)),
            'links'   => $filterTitles->getLinks(array($ruleData)),
            // tells the frontend this is an edit of the rule it told us was open, not a
            // brand new draft - needed on top of $ruleData['id'] because an edit of a
            // rule that was never saved (no id yet) still has no id to match the existing
            // DOM row against, so the frontend has to be told explicitly to replace it
            // rather than falling back to its usual "no id -> must be new, append it" rule
            'edited'  => (bool)$existingRule,
        );
    }

    /**
     * Still builds a draft - with the right settings already filled in - just with
     * the filter picker empty (or partially filled via $filters, e.g. a BOGO rule
     * whose "buy" side resolved fine but whose gift product didn't), so the admin
     * only has to fix the one missing piece in the (now empty, focused) picker
     * instead of redoing the whole request.
     */
    private static function missingTargetResult($title, $overrides, $note, $filters = array(), $existingRule = null)
    {
        $ruleData = self::buildRuleData($filters, $title, $overrides, $existingRule);

        return array(
            'note'   => $note,
            'found'  => false,
            'rule'   => $ruleData,
            'titles' => null,
            'links'  => null,
            'edited' => (bool)$existingRule,
        );
    }
}
