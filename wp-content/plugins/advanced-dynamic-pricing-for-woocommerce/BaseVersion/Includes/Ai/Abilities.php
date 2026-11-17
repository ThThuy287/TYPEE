<?php

namespace ADP\BaseVersion\Includes\Ai;

use ADP\BaseVersion\Includes\Cache\CacheHelper;
use ADP\BaseVersion\Includes\Database\Models\Rule;
use ADP\BaseVersion\Includes\Database\Repository\PersistentRuleRepository;
use ADP\BaseVersion\Includes\Database\Repository\RuleRepository;
use ADP\BaseVersion\Includes\Helpers\Helpers;
use WP_Error;

defined('ABSPATH') or exit;

/**
 * Pricing rules as WordPress Abilities (WP 6.9+), so an external AI client can manage them
 * through any adapter that exposes abilities as tools (e.g. an MCP server plugin).
 *
 * Rules are built by the same code the admin chat uses (RuleBuilder::buildRule()), so both
 * accept the same fields. What differs is what happens to the result: the chat hands the admin
 * an unsaved draft in the rule editor, while an ability has no editor to hand it to and SAVES
 * the rule - disabled unless asked otherwise.
 */
class Abilities
{
    const CATEGORY   = 'adp-pricing-rules';
    const NS         = 'adp/';
    const CAPABILITY = 'manage_woocommerce';

    public static function init()
    {
        add_action('wp_abilities_api_categories_init', array(__CLASS__, 'registerCategory'));
        add_action('wp_abilities_api_init', array(__CLASS__, 'register'));
        add_filter('rest_post_dispatch', array(__CLASS__, 'renameEasyMcpGroup'), 10, 3);
    }

    /**
     * Easy MCP AI labels a group of abilities with its name prefix ("Adp") and has no hook
     * for it, so the label is replaced in the response of its admin "tools" endpoint.
     *
     * @param \WP_REST_Response $response
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public static function renameEasyMcpGroup($response, $server, $request)
    {
        if ( ! in_array($request->get_route(), array('/easy-mcp-ai-admin/v1/tools', '/easy-mcp-ai-admin/v1/tools/enabled'), true)
            || ! $response instanceof \WP_REST_Response) {
            return $response;
        }

        $data = $response->get_data();
        if (empty($data['data']['abilities']) || ! is_array($data['data']['abilities'])) {
            return $response;
        }

        foreach ($data['data']['abilities'] as &$group) {
            if (isset($group['key']) && $group['key'] . '/' === self::NS) {
                $group['label'] = 'Advanced Dynamic Pricing for WooCommerce';
            }
        }
        unset($group);
        $response->set_data($data);

        return $response;
    }

    public static function registerCategory()
    {
        wp_register_ability_category(self::CATEGORY, array(
            'label'       => __('Dynamic pricing rules', 'advanced-dynamic-pricing-for-woocommerce'),
            'description' => __('Pricing and discount rules of Advanced Dynamic Pricing for WooCommerce.', 'advanced-dynamic-pricing-for-woocommerce'),
        ));
    }

    public static function register()
    {
        self::add('rules-list', array(
            'label'       => 'List pricing rules',
            'description' => 'Lists the pricing/discount rules of Advanced Dynamic Pricing for WooCommerce. '
                             . 'Returns { rules: [{ id, title, enabled, priority, kinds }], total, page, per_page }. '
                             . '`kinds` says what the rule does (product_discount, set_discount, combo_deal, bulk_discount, role_discount, cart_adjustment, gift, auto_add). '
                             . 'Rules apply in ascending `priority`. Use adp/rule-get for the details of one rule.',
            'properties'  => array(
                'status'   => array('type' => 'string', 'enum' => array('any', 'enabled', 'disabled'), 'default' => 'any'),
                'search'   => array('type' => 'string', 'description' => 'Only rules whose title contains this text.'),
                'page'     => array('type' => 'integer', 'minimum' => 1, 'default' => 1),
                'per_page' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20),
            ),
            'callback'    => array(__CLASS__, 'listRules'),
            'readonly'    => true,
        ));

        self::add('rule-get', array(
            'label'       => 'Get a pricing rule',
            'description' => 'Returns one pricing rule: { id, title, enabled, priority, exclusive, kinds, details, edit_url }. '
                             . '`details` holds the rule\'s non-empty blocks as stored (filters, conditions, product_adjustments, bulk_adjustments, '
                             . 'role_discounts, cart_adjustments, get_products, auto_add_products) and `target_names`, which maps the ids used in them to names.',
            'properties'  => array(
                'rule_id' => array('type' => 'integer', 'minimum' => 1),
            ),
            'required'    => array('rule_id'),
            'callback'    => array(__CLASS__, 'getRule'),
            'readonly'    => true,
        ));

        self::add('reference-get', array(
            'label'       => 'Pricing rules reference',
            'description' => 'Returns what the adp/rule-* abilities can use on this site: whether the Pro version is active, the rule types '
                             . '(and which need Pro), customer roles, payment methods, shipping methods and zones, product collections, currency, '
                             . 'and what cannot be built through these abilities. Call it once before creating rules instead of guessing names; for WHICH rule type to use, see adp/guide-get.',
            'properties'  => array(),
            'callback'    => array(__CLASS__, 'getReference'),
            'readonly'    => true,
        ));

        self::add('guide-get', array(
            'label'       => 'Pricing rules guide',
            'description' => 'Returns the guide for choosing and filling the adp/rule-* abilities: which rule type fits which kind of request '
                             . '("3=2", "buy X get Y", "any 3 for $30", a discount while something is in the cart...), what each field means, what is not supported, '
                             . 'and worked examples. Read it before creating or changing a rule whenever the request is more than a plain discount on one target - '
                             . 'similar-sounding requests map to different rule types. `topic` limits the answer to one section; omit it for the whole guide. '
                             . 'Returns { how_to_read, topics, guide }.',
            'properties'  => array(
                'topic' => array('type' => 'string', 'enum' => array_keys(self::guideTopics()), 'description' => 'One section of the guide. Omit for all of it.'),
            ),
            'callback'    => array(__CLASS__, 'getGuide'),
            'readonly'    => true,
        ));

        foreach (self::ruleTypes() as $type => $definition) {
            self::add('rule-' . str_replace('_', '-', $type), array(
                'label'       => $definition['label'],
                'description' => $definition['description'] . ' ' . self::requiredNote($definition) . ' ' . self::writeNote(),
                'properties'  => self::pick(array_merge($definition['fields'], self::commonFields())),
                'callback'    => function ($input) use ($type) {
                    return self::saveRule($type, $input);
                },
            ));
        }

        // One ability for every rule type - for clients that do better with a few broad tools
        // than with many narrow ones. Enable either this one or the adp/rule-<type> set in the
        // adapter, not both: they do the same thing.
        $allFields = array();
        $typeLines = array();
        foreach (self::ruleTypes() as $type => $definition) {
            $allFields   = array_merge($allFields, $definition['fields']);
            $typeLines[] = sprintf('`%s` - %s %s', $type, $definition['description'], self::requiredNote($definition));
        }
        $properties         = self::pick(array_merge(array_unique($allFields), self::commonFields()));
        $properties['type'] = array(
            'type'        => 'string',
            'enum'        => array_keys(self::ruleTypes()),
            'description' => 'The kind of rule. Required for a new rule; with `rule_id`, the kind of change being made.',
        );
        $properties['discount_type'] = self::field('discount_type_any');
        self::add('rule-create', array(
            'label'       => 'Create or update a pricing rule',
            'description' => 'Creates a pricing rule of the given `type`, or changes an existing one when `rule_id` is given. Types: '
                             . implode(' | ', $typeLines) . ' ' . self::writeNote(),
            'properties'  => $properties,
            'required'    => array('type'),
            'callback'    => function ($input) {
                $input = is_array($input) ? $input : array();
                $type  = (string)($input['type'] ?? '');
                if ( ! isset(self::ruleTypes()[$type])) {
                    return self::error('adp_invalid_type', 'Unknown `type`. Use one of: ' . implode(', ', array_keys(self::ruleTypes())) . '.');
                }

                return self::saveRule($type, $input);
            },
        ));

        self::add('rule-set-status', array(
            'label'       => 'Enable or disable pricing rules',
            'description' => 'Turns one pricing rule (`rule_id`) or several at once (`rule_ids`) on or off. An enabled rule changes prices on the storefront immediately. '
                             . 'Returns { rule_id, enabled } for one rule; for several { enabled, changed, results: [{ rule_id, enabled } or { rule_id, error }] } - a rule that does not exist is reported, the others still change.',
            'properties'  => array(
                'rule_id'  => array('type' => 'integer', 'minimum' => 1),
                'rule_ids' => self::ruleIdsSchema(),
                'enabled'  => array('type' => 'boolean'),
            ),
            'required'    => array('enabled'),
            'callback'    => array(__CLASS__, 'setStatus'),
            'idempotent'  => true,
        ));

        self::add('rule-set-priority', array(
            'label'       => 'Set a pricing rule\'s priority',
            'description' => 'Sets the position of a rule in the order rules are applied (ascending; lower runs first). Returns { rule_id, priority }.',
            'properties'  => array(
                'rule_id'  => array('type' => 'integer', 'minimum' => 1),
                'priority' => array('type' => 'integer', 'minimum' => 0),
            ),
            'required'    => array('rule_id', 'priority'),
            'callback'    => array(__CLASS__, 'setPriority'),
            'idempotent'  => true,
        ));

        self::add('rule-delete', array(
            'label'       => 'Delete pricing rules',
            'description' => 'Deletes one pricing rule (`rule_id`) or several at once (`rule_ids`), the same as the Delete button in the rule editor. '
                             . 'Returns { rule_id, deleted } for one rule; for several { deleted (how many), results: [{ rule_id, deleted } or { rule_id, error }] } - a rule that does not exist is reported, the others are still deleted.',
            'properties'  => array(
                'rule_id'  => array('type' => 'integer', 'minimum' => 1),
                'rule_ids' => self::ruleIdsSchema(),
            ),
            'callback'    => array(__CLASS__, 'deleteRule'),
            'destructive' => true,
        ));
    }

    private static function add($name, $definition)
    {
        $schema = array(
            'type'                 => 'object',
            'properties'           => $definition['properties'],
            'additionalProperties' => false,
        );
        if ( ! empty($definition['required'])) {
            $schema['required'] = $definition['required'];
        } else {
            $schema['default'] = array();
        }
        if (empty($schema['properties'])) {
            unset($schema['properties'], $schema['additionalProperties']);
        }

        wp_register_ability(self::NS . $name, array(
            'label'               => $definition['label'],
            'description'         => $definition['description'],
            'category'            => self::CATEGORY,
            'input_schema'        => $schema,
            'execute_callback'    => $definition['callback'],
            'permission_callback' => array(__CLASS__, 'canManage'),
            'meta'                => array(
                'show_in_rest' => true,
                'mcp'          => array('public' => true, 'type' => 'tool'),
                'annotations'  => array(
                    'readonly'    => ! empty($definition['readonly']),
                    'destructive' => ! empty($definition['destructive']),
                    'idempotent'  => ! empty($definition['readonly']) || ! empty($definition['idempotent']),
                ),
            ),
        ));
    }

    public static function canManage()
    {
        return current_user_can(self::CAPABILITY);
    }

    // -----------------------------------------------------------------
    // Rule types and their fields. The field names and meanings are the chat's own (see
    // instructions/NN-*.md) - both end up in RuleBuilder::buildRule().
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{action: string, label: string, description: string, fields: string[], required: string[], pro: bool}>
     */
    private static function ruleTypes()
    {
        $filter  = array('filter_type', 'filter_value', 'filter_values', 'taxonomy_name', 'attribute_name');
        $exclude = array('exclude_filter_type', 'exclude_filter_value', 'exclude_filter_values', 'exclude_taxonomy_name', 'exclude_attribute_name', 'exclude_on_sale', 'exclude_already_discounted', 'exclude_backorder');

        return array(
            'product_discount'   => array(
                'action'      => 'create_product_discount',
                'label'       => 'Product discount rule',
                'description' => 'A flat discount (percentage, fixed amount off, or fixed price) on everything matching one filter target: a product, category, tag, SKU, taxonomy term, attribute term, collection, or all products.',
                'fields'      => array_merge($filter, $exclude, array('discount_type', 'discount_value', 'price_formula', 'as_coupon', 'coupon_name')),
                'required'    => array('filter_type'),
                'pro'         => false,
            ),
            'bulk_discount'      => array(
                'action'      => 'create_bulk_discount',
                'label'       => 'Bulk (tiered) discount rule',
                'description' => 'Quantity-tiered pricing on one filter target: once the quantity in the cart reaches a tier, that tier\'s discount applies to every unit. Not for a fixed group deal like "3 for $30" - that is set_discount.',
                'fields'      => array_merge($filter, $exclude, array('discount_type', 'ranges', 'count_by', 'bulk_mode', 'measure_by', 'table_message')),
                'required'    => array('filter_type', 'discount_type', 'ranges'),
                'pro'         => false,
            ),
            'role_discount'      => array(
                'action'      => 'create_role_discount',
                'label'       => 'Role discount rule',
                'description' => 'A discount only for customers with one account role, store-wide (omit filter_type) or limited to one filter target.',
                'fields'      => array_merge(array('role_name', 'discount_type', 'discount_value', 'role_rows', 'skip_bulk_for_roles'), $filter, $exclude, array('as_coupon', 'coupon_name')),
                'required'    => array(),
                'pro'         => false,
            ),
            'role_bulk_discount' => array(
                'action'      => 'create_role_bulk_discount',
                'label'       => 'Role + bulk discount rule',
                'description' => 'Quantity-tiered pricing only for customers with one account role, store-wide (omit filter_type) or limited to one filter target.',
                'fields'      => array_merge(array('role_name', 'discount_type', 'ranges', 'count_by', 'bulk_mode', 'measure_by', 'table_message'), $filter, $exclude),
                'required'    => array('role_name', 'discount_type', 'ranges'),
                'pro'         => false,
            ),
            'cart_discount'      => array(
                'action'      => 'create_cart_discount',
                'label'       => 'Cart discount / fee rule',
                'description' => 'An adjustment of the whole cart. Plain shape: `discount_type` percentage / fixed_amount (a discount shown as a coupon), free_shipping, or fee (a fixed amount ADDED to the order). Pro-only shape: `pro_adjustment_type` INSTEAD OF discount_type - percentage fee, tax exemption, shipping price override, hiding payment/shipping methods, auto-applying or disabling coupons. Optionally only from `min_subtotal`.',
                'fields'      => array_merge(array('discount_type_cart', 'discount_value', 'coupon_name', 'fee_name', 'fee_tax_class', 'min_subtotal', 'pro_adjustment_type', 'pro_adjustment_value', 'pro_adjustment_name', 'pro_adjustment_values', 'pro_adjustment_target'), $filter),
                'required'    => array(),
                'pro'         => false,
            ),
            'gift'               => array(
                'action'      => 'create_gift',
                'label'       => 'Gift rule',
                'description' => 'Adds one specific product to the cart for free when a trigger is met: a filter target is in the cart (one unit is enough; to require 2+ units use combo_deal), the cart reaches a minimum subtotal, or always.',
                'fields'      => array_merge(array('gift_product_name', 'gift_qty', 'trigger_type'), $filter, array('min_subtotal', 'gift_mode', 'gift_products', 'gift_categories', 'gift_times', 'gift_per_subtotal', 'gift_max_amount', 'gifts_cheaper_than_cart_items', 'gift_coupon_name')),
                'required'    => array('trigger_type'),
                'pro'         => false,
            ),
            'bogo_free'          => array(
                'action'      => 'create_bogo_free',
                'label'       => 'Buy X get Y free rule',
                'description' => 'Buying ONE item matching the filter target gives a free product: the same one (omit gift_product_name, filter_type "product" only) or another named product. To require 2+ bought items use combo_deal (there the extra item is not added to the cart automatically).',
                'fields'      => array_merge($filter, array('gift_product_name', 'gift_mode', 'gift_products', 'gift_categories', 'gift_times', 'gift_per_subtotal', 'gift_max_amount', 'gifts_cheaper_than_cart_items', 'gift_coupon_name')),
                'required'    => array('filter_type'),
                'pro'         => false,
            ),
            'bogo_discount'      => array(
                'action'      => 'create_bogo_discount',
                'label'       => 'Buy X get Y at a discount rule (Pro)',
                'description' => 'Pro-only. Buying ONE item matching the filter target ADDS another product to the cart at a reduced, non-zero price: the same one (omit gift_product_name, filter_type "product" only) or another named product the user wants added automatically. Use bogo_free when the extra item is free. A discount on a different product the customer adds himself ("buy 1kg bread, get 10% off one 500g bread") is combo_deal.',
                'fields'      => array_merge($filter, array('gift_product_name', 'discount_type', 'discount_value', 'auto_add_mode', 'auto_add_products', 'auto_add_times', 'auto_add_per_subtotal', 'auto_add_cant_be_removed', 'auto_add_as_recommended', 'auto_add_coupon_name')),
                'required'    => array('filter_type', 'discount_type', 'discount_value'),
                'pro'         => true,
            ),
            'set_discount'       => array(
                'action'      => 'create_set_discount',
                'label'       => 'Product set rule (Pro)',
                'description' => 'Pro-only. A set of products for one discounted total, applied to each complete set only. Describe the set in ONE of two ways. (1) By a filter: `set_qty` units matching one filter target - "3 Beanies for $30", "3=2 on the Album", and with filter_type "any" or a category the units may be different products ("any 3 products for $30"). (2) By a list: `bundle_products` names every unit of the set, all of which must be in the cart - "a Cap and Sunglasses together for $40" is bundle_products [Cap, Sunglasses]; repeat a product for more units of it; set_qty and filter_type are not used. The discount is for the whole set: fixed_price is the price of the set, for "buy 3 pay for 2" use percentage 33.33. Use combo_deal instead when only one of the products gets the discount.',
                'fields'      => array_merge($filter, $exclude, array('set_qty', 'same_product', 'bundle_products', 'discount_type', 'discount_value', 'price_formula', 'as_coupon', 'coupon_name')),
                'required'    => array('discount_type', 'discount_value'),
                'pro'         => true,
            ),
            'combo_deal'         => array(
                'action'      => 'create_combo_deal',
                'label'       => 'Combo deal rule, "3x2" (Pro)',
                'description' => 'Pro-only. A deal across DIFFERENT products, applied once per complete set: the customer buys `buy_qty` units from `buy_products` at full price and gets `get_qty` units from `get_products` at a discount (free by default). Use it for "buy Cap and Sunglasses, get Album free", for "if Cap and Sunglasses are bought, Sunglasses get 50% off" (the discounted product goes to get_products only: buy_products [Cap], get_products [Sunglasses]) and for "3=2 / any 3 products, the cheapest is free" (`buy_qty` 2 with `any_product` true, or the same product list in buy_products and get_products). This is the default for any discount on one product tied to buying other products; require_products_in_cart is only for discounts on something broader than a product, or on every unit regardless of quantity.',
                'fields'      => array('buy_products', 'buy_qty', 'get_products', 'get_qty', 'discount_type', 'discount_value', 'any_product'),
                'required'    => array(),
                'pro'         => true,
            ),
        );
    }

    private static function commonFields()
    {
        return array('rule_id', 'title', 'enabled', 'date_from', 'date_to', 'days_of_week', 'logged_in_only', 'min_cart_items', 'max_usage', 'max_usage_per_customer', 'trigger_coupon_code', 'apply_first_to', 'times_per_cart', 'max_discount_amount', 'conditions', 'sale_badge', 'discount_message', 'cart_item_message', 'long_discount_message', 'cart_notice', 'timer_message', 'offer_message', 'ship_to_countries', 'require_products_in_cart', 'pro_conditions', 'conditions_relationship', 'exclusive');
    }

    private static function requiredNote($definition)
    {
        if ($definition['action'] === 'create_cart_discount') {
            return 'Required for a new rule: discount_type or pro_adjustment_type.';
        }
        if ($definition['action'] === 'create_combo_deal') {
            return 'Required for a new rule: buy_products and get_products, or any_product true.';
        }
        if ($definition['action'] === 'create_role_discount') {
            return 'Required for a new rule: role_name, discount_type and discount_value, or role_rows.';
        }
        if ($definition['action'] === 'create_gift') {
            return 'Required for a new rule: trigger_type and gift_product_name (or gift_products / gift_categories).';
        }
        if ($definition['action'] === 'create_product_discount') {
            return 'Required for a new rule: filter_type, and discount_type + discount_value (or price_formula).';
        }
        if ($definition['action'] === 'create_set_discount') {
            return 'Required for a new rule: discount_type, discount_value, and set_qty or bundle_products.';
        }

        return 'Required for a new rule: ' . implode(', ', $definition['required']) . '.';
    }

    private static function writeNote()
    {
        return 'Without `rule_id` a new rule is SAVED - disabled unless `enabled` is true; an enabled rule changes storefront prices immediately. '
               . 'With `rule_id` that rule is changed: send only the fields to change, the rest keep their values. '
               . 'Names (products, categories, roles...) are matched on the site; if one is not found nothing is saved and an error names it. '
               . 'Returns { rule_id, status (created|updated), enabled, title, details, edit_url }. '
               . 'If it is not obvious which rule type a request needs, read adp/guide-get first.';
    }

    /**
     * @param string[] $names
     *
     * @return array<string, array> JSON Schema properties
     */
    private static function pick($names)
    {
        $properties = array();
        foreach ($names as $name) {
            // variants of one input field that only differ in what the schema allows
            $inputName              = strpos($name, 'discount_type_') === 0 ? 'discount_type' : $name;
            $properties[$inputName] = self::field($name);
        }

        return $properties;
    }

    private static function field($name)
    {
        $filterTypes = array('any', 'product', 'category', 'category_slug', 'tag', 'sku', 'taxonomy', 'attribute', 'collection', 'custom_field', 'custom_attribute', 'seller');
        $string      = function ($description) {
            return array('type' => 'string', 'description' => $description);
        };
        $names = function ($description) {
            return array('type' => 'array', 'items' => array('type' => 'string'), 'description' => $description);
        };

        switch ($name) {
            case 'rule_id':
                return array('type' => 'integer', 'minimum' => 1, 'description' => 'Change this existing rule instead of creating a new one.');
            case 'title':
                return $string('Rule title shown in the admin. Generated from the rule\'s content if omitted.');
            case 'enabled':
                return array('type' => 'boolean', 'description' => 'Whether the rule is active. A new rule is saved disabled unless this is true.');

            case 'filter_type':
                return array(
                    'type'        => 'string',
                    'enum'        => $filterTypes,
                    'description' => 'What `filter_value` names. any = all products (no filter_value); product = product name or SKU; category = category name; '
                                     . 'category_slug = category slug; tag = product tag name; sku = exact SKU; taxonomy = a term of a custom product taxonomy such as Brand '
                                     . '(also give taxonomy_name); attribute = a term of a global product attribute such as Color (also give attribute_name); '
                                     . 'collection = a Product Collection by name (Pro-only); custom_field = a product meta field, filter_value "key=value"; '
                                     . 'custom_attribute = an attribute typed on the product itself, filter_value "name:value"; seller = the product\'s author, by login or e-mail.',
                );
            case 'filter_value':
                return $string('The name, slug, SKU or term for `filter_type`. Omit for "any". For several targets of the same kind use filter_values instead.');
            case 'taxonomy_name':
                return $string('The taxonomy\'s label, e.g. "Brand". Only with filter_type "taxonomy".');
            case 'attribute_name':
                return $string('The attribute\'s label, e.g. "Color". Only with filter_type "attribute".');
            case 'exclude_filter_type':
                return array('type' => 'string', 'enum' => array_values(array_diff($filterTypes, array('any'))), 'description' => 'Leaves ONE thing out of the filter ("all products except Cap"). Same meaning as filter_type.');
            case 'filter_values':
                return $names('Several targets of the kind `filter_type` names, INSTEAD OF filter_value: the rule then covers a product that matches ANY of them ("15% off the brands A, B and C" is ONE rule with filter_values [A, B, C], not three rules).');
            case 'exclude_on_sale':
                return array('type' => 'boolean', 'description' => 'true = the rule skips products that already have a WooCommerce sale price ("except items that are already discounted / on sale").');
            case 'exclude_already_discounted':
                return array('type' => 'boolean', 'description' => 'true = the rule skips products whose price another pricing rule of this plugin has already changed.');
            case 'exclude_backorder':
                return array('type' => 'boolean', 'description' => 'Pro-only. true = the rule skips products that are on backorder.');
            case 'date_from':
                return $string('First day the rule applies, YYYY-MM-DD. For a period given in whole days ("from September 4 to 6") use date_from/date_to; only a period with a time of day needs a date_time entry in pro_conditions.');
            case 'date_to':
                return $string('Last day the rule applies (inclusive), YYYY-MM-DD.');
            case 'logged_in_only':
                return array('type' => 'boolean', 'description' => 'true = only for logged-in customers; false = only for guests.');
            case 'min_cart_items':
                return array('type' => 'integer', 'minimum' => 1, 'description' => 'The rule applies only when the cart holds at least this many items.');
            case 'max_usage':
                return array('type' => 'integer', 'minimum' => 0, 'description' => 'How many times the rule may be used in total, across all orders; 0 removes the limit.');
            case 'max_usage_per_customer':
                return array('type' => 'integer', 'minimum' => 0, 'description' => 'Pro-only. How many times one customer may use the rule; 0 removes the limit.');
            case 'trigger_coupon_code':
                return $string('Pro-only. The rule applies only after the customer enters this code ("Activated by" in the rule editor). It is a code word for this rule, not an existing WooCommerce coupon.');
            case 'apply_first_to':
                return array('type' => 'string', 'enum' => array('expensive', 'cheap', 'appeared'), 'description' => 'Which matching items the rule takes first when it cannot cover all of them: the expensive ones, the cheap ones, or in the order they appear in the cart.');
            case 'times_per_cart':
                return array('type' => 'integer', 'minimum' => 0, 'maximum' => 10, 'description' => 'How many times the rule may apply within one cart (1-10); 0 = unlimited, the default.');
            case 'max_discount_amount':
                return array('type' => 'number', 'description' => 'Cap on the total discount the rule gives in one cart, as a currency amount ("Limit discount to amount"); 0 removes the cap. Only for rules with a product discount.');
            case 'price_formula':
                return $string('Pro-only. A formula for the new price INSTEAD OF discount_type/discount_value ("Fixed price by the formula" in the rule editor), e.g. "{price}*0.9".');
            case 'bulk_mode':
                return array('type' => 'string', 'enum' => array('bulk', 'tier'), 'description' => 'bulk (default) = the tier reached applies to EVERY unit; tier = each tier\'s discount applies only to the units that fall inside that tier.');
            case 'measure_by':
                return array('type' => 'string', 'enum' => array('qty', 'sum', 'weight'), 'description' => 'What the tiers\' from/to measure: qty (default), sum = the amount spent, weight. sum and weight are Pro-only.');
            case 'table_message':
                return $string('Text shown above the bulk pricing table on the product page.');
            case 'role_rows':
                return array(
                    'type'        => 'array',
                    'description' => 'Several role discounts in ONE rule, INSTEAD OF role_name/discount_type/discount_value: each row has its own roles and discount ("Subscribers 5%, Editors and Shop managers $3 off").',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'roles'          => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Role names or slugs.'),
                            'discount_type'  => array('type' => 'string', 'enum' => array('percentage', 'fixed_amount', 'fixed_price')),
                            'discount_value' => array('type' => 'number'),
                        ),
                        'required'   => array('roles', 'discount_type', 'discount_value'),
                    ),
                );
            case 'skip_bulk_for_roles':
                return array('type' => 'boolean', 'description' => 'true = when a role row matches, the rule\'s bulk tiers are not applied on top of it.');
            case 'gift_mode':
                return array('type' => 'string', 'enum' => array('first_available', 'rotation', 'random', 'same_as_bought', 'first_bought_only', 'customer_choice', 'customer_choice_required', 'customer_choice_from_categories', 'customer_choice_from_categories_required'), 'description' => 'How the gift is picked. first_available (default) = the first of gift_products that is in stock; rotation / random = from gift_products in turn / at random; same_as_bought = the product the filter matched; customer_choice = the customer picks from gift_products (…_required = must pick); customer_choice_from_categories = picks from gift_categories. Everything except first_available and same_as_bought is Pro-only.');
            case 'gift_products':
                return $names('Several products to give from / to choose from, INSTEAD OF gift_product_name.');
            case 'gift_categories':
                return $names('Categories the customer chooses the gift from. Only with gift_mode customer_choice_from_categories(_required).');
            case 'gift_times':
                return array('type' => 'integer', 'minimum' => 0, 'description' => 'How many times the gift may be given in one cart; 0 = unlimited (default).');
            case 'gift_per_subtotal':
                return array('type' => 'number', 'description' => 'Give the gift once per this much cart subtotal ("a gift for every $100").');
            case 'gift_max_amount':
                return array('type' => 'number', 'description' => 'Pro-only. Total value of gifts the rule may give in one cart.');
            case 'gifts_cheaper_than_cart_items':
                return array('type' => 'boolean', 'description' => 'Pro-only. true = only gifts cheaper than the items in the cart.');
            case 'gift_coupon_name':
                return $string('Add the gift at its normal price and show its value as a coupon with this name instead.');
            case 'auto_add_mode':
                return array('type' => 'string', 'enum' => array('first_available', 'rotation', 'same_as_bought'), 'description' => 'How the auto-added product is picked from auto_add_products: the first available (default), in rotation, or the product the filter matched.');
            case 'auto_add_products':
                return $names('Several products to auto-add from, INSTEAD OF gift_product_name.');
            case 'auto_add_times':
                return array('type' => 'integer', 'minimum' => 0, 'description' => 'How many times the product may be auto-added in one cart; 0 = unlimited (default).');
            case 'auto_add_per_subtotal':
                return array('type' => 'number', 'description' => 'Auto-add once per this much cart subtotal.');
            case 'auto_add_cant_be_removed':
                return array('type' => 'boolean', 'description' => 'true = the customer cannot remove the auto-added product from the cart.');
            case 'auto_add_as_recommended':
                return array('type' => 'boolean', 'description' => 'true = offer the product as a recommendation instead of adding it silently.');
            case 'auto_add_coupon_name':
                return $string('Add the product at its normal price and show the discount as a coupon with this name instead.');
            case 'fee_tax_class':
                return $string('Tax class slug of a fee. Empty = not taxable.');
            case 'sale_badge':
                return $string('Pro-only. Text of the sale badge shown on the discounted products ("Hot Deal").');
            case 'discount_message':
                return $string('Pro-only. Short message shown on the product page while the rule applies.');
            case 'cart_item_message':
                return $string('Pro-only. Message shown next to the discounted item in the cart; may use the tag {{discount_amount}}.');
            case 'long_discount_message':
                return $string('Pro-only. Long description of the offer shown on the product page.');
            case 'cart_notice':
                return $string('Pro-only. Notice shown in the cart once the discount has been applied.');
            case 'timer_message':
                return $string('Pro-only. Turns on the countdown timer and sets the text next to it ("till the end of the sale"). Needs an end date on the rule.');
            case 'offer_message':
                return $string('Pro-only. Message shown while the rule\'s conditions are NOT met yet ("Add {{qty}} more to get {{discount}}"); tags: {{qty}}, {{product}}, {{discount}}, {{cart_condition}}.');
            case 'conditions':
                return array(
                    'type'        => 'array',
                    'description' => 'Extra conditions of ANY type the rule editor has, added to the rule\'s existing ones. adp/reference-get lists every type with its fields under `condition_types`. '
                                     . 'In short: a number to compare uses `operator` + `value` (cart_weight, cart_items_count, customer_order_count, the cart_subtotal_* variants...); '
                                     . 'a list uses `values` + `match` (customer_role, customer_users, customer_shipping_method, customer_capability, cart_language...); '
                                     . 'products in the cart use `values` + `min_qty`/`max_qty` (products_all, product_categories_all, product_tags_all, product_sku_all...) or `measure` amount|weight with `operator` + `value`; '
                                     . 'time uses `from`/`to` as "HH:MM"; customer_postcode uses `value` (comma-separated codes, * as a wildcard). Prefer the dedicated fields (date_from, days_of_week, min_subtotal...) where one exists.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'type'       => array('type' => 'string', 'description' => 'Condition type, e.g. cart_weight.'),
                            'operator'   => array('type' => 'string', 'enum' => array('<', '<=', '>=', '>', '=', '!=', 'in_range', 'in_list', 'not_in_list')),
                            'value'      => array('type' => array('string', 'number', 'boolean'), 'description' => 'The number / text / true|false to compare with.'),
                            'max_value'  => array('type' => 'number'),
                            'match'      => array('type' => 'string', 'description' => 'How `values` are matched; the allowed words depend on the type (in_list, not_in_list, at_least_one, all, only, none, none_at_all, any_applied, not_containing, later, earlier).'),
                            'values'     => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Plain names; they are matched on the site.'),
                            'time_range' => array('type' => 'string'),
                            'from'       => array('type' => 'string'),
                            'to'         => array('type' => 'string'),
                            'on'         => array('type' => 'string', 'description' => 'One specific date, YYYY-MM-DD (type date).'),
                            'min_qty'    => array('type' => 'integer'),
                            'max_qty'    => array('type' => 'integer'),
                            'measure'    => array('type' => 'string', 'enum' => array('qty', 'amount', 'weight')),
                            'combine'    => array('type' => 'string', 'enum' => array('combine', 'any', 'each')),
                        ),
                        'required'   => array('type'),
                    ),
                );
            case 'count_by':
                return array('type' => 'string', 'enum' => array('each_product', 'each_variation', 'all_matched', 'whole_cart'), 'description' => 'What a tier\'s quantity counts: each_product = units of one product (default; "3 or more of the same product"); all_matched = all items the filter matches together ("any 3 items from Accessories"); whole_cart = everything in the cart.');
            case 'exclude_filter_values':
                return $names('Several things to leave out, INSTEAD OF exclude_filter_value.');
            case 'exclude_filter_value':
                return $string('The name, slug, SKU or term to exclude.');
            case 'exclude_taxonomy_name':
                return $string('Only with exclude_filter_type "taxonomy".');
            case 'exclude_attribute_name':
                return $string('Only with exclude_filter_type "attribute".');

            case 'discount_type':
                return array('type' => 'string', 'enum' => array('percentage', 'fixed_amount', 'fixed_price', 'fixed_amount_per_item', 'fixed_price_per_item'), 'description' => 'percentage = percent off; fixed_amount = currency amount off; fixed_price = the new price. For a set, the last two (Pro-only) give the amount / the price of EACH unit rather than of the whole set.');
            case 'discount_type_cart':
                return array('type' => 'string', 'enum' => array('percentage', 'fixed_amount', 'free_shipping', 'fee'), 'description' => 'percentage / fixed_amount = a discount on the cart; free_shipping; fee = a fixed amount ADDED to the order. Omit when pro_adjustment_type is given.');
            case 'discount_type_any':
                return array('type' => 'string', 'enum' => array('percentage', 'fixed_amount', 'fixed_price', 'free_shipping', 'fee'), 'description' => 'percentage = percent off; fixed_amount = currency amount off; fixed_price = the new price (not for cart_discount). Only for cart_discount: free_shipping, fee (a fixed amount ADDED to the order).');
            case 'discount_value':
                return array('type' => 'number', 'description' => 'A plain number: the percent, the amount, or the price. Not needed for free_shipping. A combo deal defaults to percentage 100 (free).');
            case 'as_coupon':
                return array('type' => 'boolean', 'description' => 'true = keep the displayed price and give the discount as a coupon instead.');
            case 'coupon_name':
                return $string('The coupon code. For a cart discount: the code shown in the cart (generated if omitted).');
            case 'ranges':
                return array(
                    'type'        => 'array',
                    'description' => 'Quantity tiers in ascending order, no overlaps. Always the COMPLETE list - it replaces the rule\'s existing tiers.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'from'  => array('type' => 'integer', 'minimum' => 1, 'description' => 'First quantity of the tier (inclusive).'),
                            'to'    => array('type' => array('integer', 'null'), 'description' => 'Last quantity (inclusive). For an open-ended last tier ("10 or more") omit it, or give null or 0.'),
                            'value' => array('type' => 'number', 'description' => 'The tier\'s discount in the unit of discount_type.'),
                        ),
                        'required'   => array('from', 'value'),
                    ),
                );
            case 'role_name':
                return $string('The customer role, by name or slug (see adp/reference-get).');
            case 'same_product':
                return array('type' => 'boolean', 'description' => 'Only matters with a target wider than one product (any, a category). true = all units of a set must be one and the same product ("3 of the same product for $30"). Omit to let a set mix products.');
            case 'set_qty':
                return array('type' => 'integer', 'minimum' => 2, 'description' => 'How many units make up one set.');

            case 'bundle_products':
                return $names('The set spelled out unit by unit, INSTEAD OF set_qty and filter_type: product names or SKUs, at least two units, every one of which must be in the cart. Repeat a product for more than one unit of it.');
            case 'buy_products':
                return $names('The full-price part of the set: product names or SKUs. Any `buy_qty` units from this list qualify.');
            case 'buy_qty':
                return array('type' => 'integer', 'minimum' => 1, 'description' => 'How many full-price units make one set. Default: the number of buy_products (2 with any_product).');
            case 'get_products':
                return $names('The discounted part of the set: product names or SKUs. Any one of them qualifies.');
            case 'get_qty':
                return array('type' => 'integer', 'minimum' => 1, 'description' => 'How many discounted units per set. Default 1.');
            case 'any_product':
                return array('type' => 'boolean', 'description' => 'true = the deal works on any products in the cart, the cheapest ones get the discount. Use instead of naming products.');

            case 'gift_product_name':
                return $string('The product given away / added at a discount, by name or SKU.');
            case 'gift_qty':
                return array('type' => 'integer', 'minimum' => 1, 'description' => 'How many units of the gift. Default 1.');
            case 'trigger_type':
                return array('type' => 'string', 'enum' => array('filter', 'subtotal', 'none'), 'description' => 'filter = a filter target is in the cart (give filter_type/filter_value); subtotal = the cart reaches min_subtotal; none = always.');
            case 'min_subtotal':
                return array('type' => 'number', 'description' => 'Minimum cart subtotal for the rule to apply.');
            case 'fee_name':
                return $string('Label of the fee on the order. Only with discount_type "fee".');

            case 'pro_adjustment_type':
                return array(
                    'type'        => 'string',
                    'enum'        => array('fee_percentage', 'tax_exempt', 'free_shipping_adp', 'disable_all_coupons', 'disable_coupons', 'apply_coupons', 'shipping_discount_amount', 'shipping_discount_percentage', 'shipping_fixed_price', 'suppress_shipping_method', 'suppress_payment_method', 'discount_per_execution', 'discount_per_item_line', 'fee_per_execution', 'fee_per_item_line', 'fee_percentage_on_matched_products'),
                    'description' => 'Pro-only cart adjustment, INSTEAD OF discount_type. fee_percentage (pro_adjustment_value, optional pro_adjustment_name); tax_exempt; free_shipping_adp; disable_all_coupons; '
                                     . 'disable_coupons / apply_coupons (pro_adjustment_values = existing coupon codes); shipping_discount_amount / shipping_discount_percentage / shipping_fixed_price '
                                     . '(pro_adjustment_target = shipping method name, pro_adjustment_value); suppress_shipping_method (pro_adjustment_target); '
                                     . 'suppress_payment_method (pro_adjustment_values = payment method names); '
                                     . 'with a product filter (filter_type/filter_value): discount_per_execution / fee_per_execution = a fixed amount each time the rule fires, '
                                     . 'discount_per_item_line / fee_per_item_line = a fixed amount per item line the rule touched, fee_percentage_on_matched_products = a percentage fee on the filtered products only (all take pro_adjustment_value, optional pro_adjustment_name).',
                );
            case 'pro_adjustment_value':
                return array('type' => 'number', 'description' => 'The percent, amount or price for pro_adjustment_type.');
            case 'pro_adjustment_name':
                return $string('Label of a percentage fee.');
            case 'pro_adjustment_values':
                return $names('Coupon codes or payment method names for pro_adjustment_type.');
            case 'pro_adjustment_target':
                return $string('Shipping method name for pro_adjustment_type.');

            case 'days_of_week':
                return $names('The rule only applies on these weekdays, as English day names ("Friday", "Saturday"). For "every Friday" style requests; a calendar period is date_from / date_to instead. Replaces the rule\'s existing weekdays.');
            case 'ship_to_countries':
                return $names('Only for orders shipped to these countries: English country names or two-letter ISO codes ("Canada" or "CA").');
            case 'require_products_in_cart':
                return $names('A plain condition: products (names or SKUs) that must ALL be in the cart for the rule to apply at all, e.g. "10% off the Accessories category while a Cap is in the cart". The rule\'s discount applies to EVERY unit of its target - for a discount on one product tied to buying others ("buy X and Y, get Z free", "buy Cap, Sunglasses 50% off") use the combo deal instead. Always the complete list.');
            case 'pro_conditions':
                return array(
                    'type'        => 'array',
                    'description' => 'Pro-only extra conditions, added to the rule\'s existing ones. List types use `match` + `values`: customer_shipping_zone, shipping_state, '
                                     . 'customer_geolocation_state, customer_geolocation_country, customer_payment_method, cart_currency, cart_coupons, customer_ordered_products. '
                                     . 'Numeric types use `operator` + `value` (+ optional `time_range`): customer_paid_order_count, customer_spent_within, customer_avg_order_amount, '
                                     . 'customer_last_order_amount. customer_is_first_order uses `value` true/false. date_time uses `from` and/or `to`.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'type'       => array('type' => 'string', 'enum' => array('customer_shipping_zone', 'shipping_state', 'customer_geolocation_state', 'customer_geolocation_country', 'customer_payment_method', 'cart_currency', 'cart_coupons', 'customer_ordered_products', 'customer_paid_order_count', 'customer_spent_within', 'customer_avg_order_amount', 'customer_last_order_amount', 'customer_is_first_order', 'date_time')),
                            'match'      => array('type' => 'string', 'enum' => array('in_list', 'not_in_list', 'none_at_all', 'any_applied'), 'description' => 'none_at_all / any_applied are for cart_coupons only and take no values: the cart has no coupon at all / has some coupon.'),
                            'values'     => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => 'Plain names (zones, states, countries, payment methods, currencies, coupon codes, products).'),
                            'operator'   => array('type' => 'string', 'enum' => array('<', '<=', '>=', '>')),
                            'value'      => array('type' => array('number', 'boolean')),
                            'time_range' => array('type' => 'string', 'description' => 'all_time (default), now, "this week", "this month", "this year", "last week", "last month", "last year", or a span like "-3 days", "-6 months".'),
                            'from'       => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:MM'),
                            'to'         => array('type' => 'string', 'description' => 'YYYY-MM-DD HH:MM'),
                        ),
                        'required'   => array('type'),
                    ),
                );
            case 'conditions_relationship':
                return array('type' => 'string', 'enum' => array('and', 'or'), 'description' => 'Pro-only. How the rule\'s conditions combine. Default and.');
            case 'exclusive':
                return array('type' => 'boolean', 'description' => 'Pro-only. true = products this rule discounts are not discounted further by other rules.');
        }

        return array('type' => 'string');
    }

    // -----------------------------------------------------------------
    // Callbacks
    // -----------------------------------------------------------------

    private static function saveRule($type, $input)
    {
        $input      = self::decodeListFields(is_array($input) ? $input : array());
        $definition = self::ruleTypes()[$type];
        $ruleId     = isset($input['rule_id']) ? (int)$input['rule_id'] : 0;

        $existingRule = null;
        if ($ruleId) {
            $existingRule = RuleDescriber::loadRule($ruleId);
            if ( ! $existingRule) {
                return self::notFound($ruleId);
            }
        } else {
            $missing = array();
            foreach ($definition['required'] as $field) {
                if ( ! isset($input[$field]) || $input[$field] === '' || $input[$field] === array()) {
                    $missing[] = $field;
                }
            }
            if ($definition['action'] === 'create_cart_discount' && empty($input['discount_type']) && empty($input['pro_adjustment_type'])) {
                $missing[] = 'discount_type or pro_adjustment_type';
            }
            if ($definition['action'] === 'create_set_discount' && empty($input['set_qty']) && empty($input['bundle_products'])) {
                $missing[] = 'set_qty or bundle_products';
            }
            if ($definition['action'] === 'create_product_discount' && empty($input['price_formula']) && ( ! isset($input['discount_type']) || ! isset($input['discount_value']))) {
                $missing[] = 'discount_type and discount_value (or price_formula)';
            }
            if ($definition['action'] === 'create_role_discount' && empty($input['role_rows']) && (empty($input['role_name']) || ! isset($input['discount_type']) || ! isset($input['discount_value']))) {
                $missing[] = 'role_name, discount_type and discount_value (or role_rows)';
            }
            if ($definition['action'] === 'create_gift' && empty($input['gift_product_name']) && empty($input['gift_products']) && empty($input['gift_categories'])) {
                $missing[] = 'gift_product_name (or gift_products / gift_categories)';
            }
            // the chat may leave the products for the admin to fill in; a saved rule can't
            if ($definition['action'] === 'create_combo_deal' && empty($input['any_product']) && (empty($input['buy_products']) || empty($input['get_products']))) {
                $missing[] = 'buy_products and get_products (or any_product true)';
            }
            if ($missing) {
                return self::error('adp_missing_fields', 'A new rule of this type needs: ' . implode(', ', $missing) . '. Nothing was saved.');
            }
        }

        $params = array_diff_key($input, array_flip(array('rule_id', 'title', 'enabled', 'type')));

        $built = RuleBuilder::buildRule($definition['action'], $params, $existingRule);
        if ( ! $built) {
            $hint = ! empty($input['role_name'])
                ? sprintf('Check first that the role "%s" exists (adp/reference-get lists the roles), then that', $input['role_name'])
                : 'Check that';

            return self::error(
                'adp_invalid_rule',
                'These fields do not add up to a rule, nothing was saved. ' . $hint . ' the values fit the field descriptions and that coupon codes, '
                . 'payment methods and shipping methods named in them exist on the site (see adp/reference-get).'
            );
        }

        if ( ! $built['found']) {
            $what = preg_match('/"([^"]+)"/u', $built['note'], $m) ? '"' . $m[1] . '"' : 'One of the named products/categories/terms';

            return self::error(
                'adp_target_not_found',
                $what . ' was not found on this site, so nothing was saved. Look up the exact name or SKU (for example with the WooCommerce product tools) and call again.',
                404
            );
        }

        if ($built['pro_feature'] && ! $built['pro_active']) {
            return self::error(
                'adp_pro_required',
                sprintf('This needs %s, which requires the Pro version of Advanced Dynamic Pricing. Nothing was saved.', $built['pro_feature']),
                403
            );
        }

        $data = $built['rule'];
        if (isset($input['title']) && $input['title'] !== '') {
            $data['title'] = sanitize_text_field($input['title']);
        } elseif ((string)$data['title'] === '') {
            // store-wide role rules come back untitled - fine for a draft the admin is looking
            // at, not for a row in the saved list
            $data['title'] = trim($definition['label'] . (empty($input['role_name']) ? '' : ': ' . sanitize_text_field($input['role_name'])));
        }
        if ($existingRule) {
            if (array_key_exists('enabled', $input)) {
                $data['enabled'] = $input['enabled'] ? 'on' : 'off';
            }
        } else {
            $data['id']       = null;
            $data['enabled']  = ! empty($input['enabled']) ? 'on' : 'off';
            $data['priority'] = self::nextPriority();
        }

        $id = (int)(new RuleRepository())->storeRule(Rule::fromArray($data));
        if ( ! $id) {
            return self::error('adp_save_failed', 'The rule could not be saved.', 500);
        }
        // what ajax_save_rule() does for a rule that is not a persistent one
        (new PersistentRuleRepository())->removeRule($id);
        if ($definition['action'] === 'create_combo_deal') {
            self::showFilterPriorityInEditor();
        }

        $saved = RuleDescriber::loadRule($id);

        return array_merge(
            array('rule_id' => $id, 'status' => $existingRule ? 'updated' : 'created'),
            array_diff_key(self::presentRule($saved), array('id' => 1))
        );
    }

    public static function listRules($input = array())
    {
        $input   = is_array($input) ? $input : array();
        $status  = $input['status'] ?? 'any';
        $search  = trim((string)($input['search'] ?? ''));
        $page    = max(1, (int)($input['page'] ?? 1));
        $perPage = min(100, max(1, (int)($input['per_page'] ?? 20)));

        $args = array();
        if ($status === 'enabled' || $status === 'disabled') {
            $args['active'] = $status === 'enabled';
        }

        $rows = array();
        foreach ((new RuleRepository())->getRules($args) as $rule) {
            $data = $rule->getData();
            if ($search !== '' && stripos((string)$data['title'], $search) === false) {
                continue;
            }
            $rows[] = array(
                'id'       => (int)$data['id'],
                'title'    => html_entity_decode((string)$data['title'], ENT_QUOTES),
                'enabled'  => $data['enabled'] === 'on',
                'priority' => (int)$data['priority'],
                'kinds'    => self::kinds($data),
            );
        }

        usort($rows, function ($a, $b) {
            return array($a['priority'], $a['id']) <=> array($b['priority'], $b['id']);
        });

        return array(
            'rules'    => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'total'    => count($rows),
            'page'     => $page,
            'per_page' => $perPage,
        );
    }

    public static function getRule($input)
    {
        $ruleId = (int)($input['rule_id'] ?? 0);
        $data   = $ruleId ? RuleDescriber::loadRule($ruleId) : null;

        return $data ? self::presentRule($data) : self::notFound($ruleId);
    }

    public static function getReference()
    {
        $texts = function ($list) {
            return array_values(array_filter(array_map(function ($item) {
                return (string)($item['text'] ?? '');
            }, is_array($list) ? $list : array())));
        };

        $roles = array();
        foreach (wp_roles()->roles as $slug => $details) {
            $roles[] = array('slug' => $slug, 'name' => translate_user_role($details['name']));
        }

        $ruleTypes = array();
        foreach (self::ruleTypes() as $type => $definition) {
            $ruleTypes[] = array(
                'type'     => $type,
                'ability'  => self::NS . 'rule-' . str_replace('_', '-', $type),
                'pro_only' => $definition['pro'],
                'summary'  => $definition['description'],
            );
        }

        return array(
            'pro_active'          => ProFeatureDetector::isProActive(),
            'pro_only'            => 'Rule types bogo_discount, set_discount and combo_deal; filter_type "collection"; pro_adjustment_type; pro_conditions; conditions_relationship; exclusive; exclude_backorder; max_usage_per_customer; trigger_coupon_code; price_formula and per-item discount types; gift modes other than first_available/same_as_bought, gift_max_amount, gifts_cheaper_than_cart_items; bulk measure_by sum/weight; badges, messages and the timer; the condition types marked pro in condition_types. Without Pro these are refused with adp_pro_required.',
            'currency'            => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
            'rule_types'          => $ruleTypes,
            'roles'               => $roles,
            'payment_methods'     => $texts(Helpers::getPaymentMethods()),
            'shipping_methods'    => $texts(Helpers::getAllShippingMethods()),
            'shipping_zones'      => $texts(Helpers::getShippingZones()),
            'product_collections' => TargetResolver::productCollectionNames(),
            'condition_types'     => ConditionBuilder::catalogue(),
            'not_supported'       => array(
                'Persistent ("product only") rules.',
                'A bundle where each product gets its own, different discount.',
                'Bulk tiers counted over hand-picked "selected products / selected categories", and auto-added gifts or BOGO items that require buying 2 or more qualifying items (combo_deal covers the latter, but the customer adds the item themselves).',
                'A cart discount with several subtotal thresholds in one rule.',
            ),
        );
    }

    /**
     * The chat's own rule instructions, as a guide for a client that has no other way to learn
     * which rule type a request maps to. They are written for the chat's reply format, so they
     * come with a note on how to read them from the abilities' side.
     */
    public static function getGuide($input = array())
    {
        $topics   = self::guideTopics();
        $sections = RuleAssistant::ruleInstructionSections();
        $topic    = is_array($input) && ! empty($input['topic']) ? (string)$input['topic'] : '';

        if ($topic !== '') {
            if ( ! isset($topics[$topic], $sections[$topics[$topic]])) {
                return self::error('adp_unknown_topic', 'Unknown `topic`. Use one of: ' . implode(', ', array_keys($topics)) . '.');
            }
            $guide = $sections[$topics[$topic]];
        } else {
            $guide = implode("\n\n---\n\n", $sections);
        }

        return array(
            'how_to_read' => 'This guide was written for the plugin\'s built-in admin chat, which answers with one JSON object. Read it for the abilities as follows. '
                             . 'An example\'s `action: "create_<type>"` is the ability adp/rule-<type> with dashes (create_product_discount -> adp/rule-product-discount), '
                             . 'or `type: "<type>"` in adp/rule-create; every other field of the example is passed as is. '
                             . 'Ignore `reply` and `action: "none"`/"need_settings": where the guide says to ask or to decline in `reply`, ask or tell the user yourself. '
                             . '`edit_current_rule: true` means: pass `rule_id` of the rule to change. '
                             . 'Where the guide speaks of an unsaved draft the admin reviews, an ability instead SAVES the rule, disabled unless `enabled` is true. '
                             . 'Where the chat leaves a product field empty for the admin to fill in, an ability needs the names. '
                             . 'Licensing is checked by the ability itself: a Pro-only rule on a site without Pro is refused with adp_pro_required.',
            'topics'      => array_keys($topics),
            'guide'       => $guide,
        );
    }

    /**
     * @return array<string, string> topic => instruction file name (without extension)
     */
    private static function guideTopics()
    {
        return array(
            'overview'           => '00-overview',
            'product_discount'   => '01-product-discount',
            'bulk_discount'      => '02-bulk-discount',
            'role_discount'      => '03-role-discount',
            'role_bulk_discount' => '04-role-bulk-discount',
            'cart_discount'      => '05-cart-discount',
            'gift'               => '06-gifts',
            'bogo'               => '07-bogo',
            'set_discount'       => '08-set-discount',
            'conditions'         => '09-conditions',
            'combo_deal'         => '10-combo-deal',
        );
    }

    public static function setStatus($input)
    {
        $enabled    = ! empty($input['enabled']);
        $repository = new RuleRepository();

        $outcome = self::forEachRule($input, function ($ruleId) use ($enabled, $repository) {
            if ($enabled) {
                $repository->enableRule($ruleId);
            } else {
                $repository->disableRule($ruleId);
            }

            return array('rule_id' => $ruleId, 'enabled' => $enabled);
        });
        if (is_wp_error($outcome) || isset($outcome['rule_id'])) {
            return $outcome;
        }

        return array('enabled' => $enabled, 'changed' => $outcome['done'], 'results' => $outcome['results']);
    }

    public static function setPriority($input)
    {
        $ruleId = (int)($input['rule_id'] ?? 0);
        if ( ! $ruleId || ! RuleDescriber::loadRule($ruleId)) {
            return self::notFound($ruleId);
        }

        $priority = max(0, (int)($input['priority'] ?? 0));
        (new RuleRepository())->changeRulePriority($ruleId, $priority);
        CacheHelper::flushRulesCache();

        return array('rule_id' => $ruleId, 'priority' => $priority);
    }

    public static function deleteRule($input)
    {
        $outcome = self::forEachRule($input, function ($ruleId) {
            (new RuleRepository())->markRuleAsDeleted($ruleId);
            (new PersistentRuleRepository())->removeRule($ruleId);

            return array('rule_id' => $ruleId, 'deleted' => true);
        });
        if (is_wp_error($outcome) || isset($outcome['rule_id'])) {
            return $outcome;
        }

        return array('deleted' => $outcome['done'], 'results' => $outcome['results']);
    }

    private static function ruleIdsSchema()
    {
        return array(
            'type'        => 'array',
            'items'       => array('type' => 'integer', 'minimum' => 1),
            'minItems'    => 1,
            'maxItems'    => 100,
            'description' => 'Several rules at once (1-100 ids), instead of rule_id.',
        );
    }

    /**
     * Runs $change for the one rule given as `rule_id` (returning its result as is) or for each
     * of `rule_ids` (returning { done, results } - a missing rule becomes an error entry and
     * the rest still go through).
     *
     * @return array|WP_Error
     */
    private static function forEachRule($input, $change)
    {
        $input = is_array($input) ? $input : array();

        $many = $input['rule_ids'] ?? null;
        if (is_string($many)) {
            $many = json_decode($many, true);
        }

        if ( ! is_array($many) || ! $many) {
            $ruleId = (int)($input['rule_id'] ?? 0);
            if ( ! $ruleId) {
                return self::error('adp_missing_fields', 'Give `rule_id` or `rule_ids`.');
            }

            return RuleDescriber::loadRule($ruleId) ? $change($ruleId) : self::notFound($ruleId);
        }

        $results = array();
        $done    = 0;
        foreach (array_slice(array_unique(array_map('intval', $many)), 0, 100) as $ruleId) {
            if ($ruleId < 1 || ! RuleDescriber::loadRule($ruleId)) {
                $results[] = array('rule_id' => $ruleId, 'error' => 'There is no pricing rule with this id.');
                continue;
            }
            $results[] = $change($ruleId);
            $done++;
        }

        return array('done' => $done, 'results' => $results);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private static function presentRule($data)
    {
        return array(
            'id'        => (int)$data['id'],
            'title'     => html_entity_decode((string)$data['title'], ENT_QUOTES),
            'enabled'   => $data['enabled'] === 'on',
            'priority'  => (int)$data['priority'],
            'exclusive' => ! empty($data['exclusive']),
            'kinds'     => self::kinds($data),
            'details'   => array_merge(
                RuleDescriber::describeRule($data),
                empty($data['auto_add_products']) ? array() : array('auto_add_products' => $data['auto_add_products'])
            ),
            'edit_url'  => admin_url('admin.php?page=wdp_settings&tab=rules&rule_id=' . (int)$data['id']),
        );
    }

    /**
     * @return string[] what the rule does, read off its non-empty blocks
     */
    private static function kinds($data)
    {
        $kinds = array();
        if ( ! empty($data['product_adjustments'])) {
            $isSet = isset($data['filters'][0]['qty']) && (int)$data['filters'][0]['qty'] > 1;
            if (count($data['filters'] ?? array()) > 1) {
                $kinds[] = ($data['product_adjustments']['type'] ?? '') === 'split' ? 'combo_deal' : 'set_discount';
            } else {
                $kinds[] = $isSet ? 'set_discount' : 'product_discount';
            }
        }
        if ( ! empty($data['bulk_adjustments']['ranges'])) {
            $kinds[] = 'bulk_discount';
        }
        if ( ! empty($data['role_discounts']['rows'])) {
            $kinds[] = 'role_discount';
        }
        if ( ! empty($data['cart_adjustments'])) {
            $kinds[] = 'cart_adjustment';
        }
        if ( ! empty($data['get_products']['value'])) {
            $kinds[] = 'gift';
        }
        if ( ! empty($data['auto_add_products']['value'])) {
            $kinds[] = 'auto_add';
        }

        return $kinds;
    }

    /**
     * A combo deal relies on each filter row's "select priority" - the rule editor only shows
     * that control once this option is on, and turns it on itself when its own 3x2 template is
     * picked (ajax_check_filter_priority()).
     */
    private static function showFilterPriorityInEditor()
    {
        $context = adp_context();
        if ( ! $context->getOption('show_select_filter_priority', false)) {
            $settings = $context->getSettings();
            $settings->set('show_select_filter_priority', true);
            $settings->save();
        }
    }

    /**
     * Some clients send a list as its JSON text ('["Cap","Beanie"]') instead of a list - seen
     * with a client whose copy of the tool's schema was older than the field. Read it as the
     * list it is rather than failing on it.
     */
    private static function decodeListFields($input)
    {
        $listFields = array('conditions', 'role_rows', 'gift_products', 'gift_categories', 'auto_add_products', 'days_of_week', 'filter_values', 'exclude_filter_values', 'bundle_products', 'buy_products', 'get_products', 'require_products_in_cart', 'ship_to_countries', 'pro_adjustment_values', 'ranges', 'pro_conditions');
        foreach ($listFields as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $decoded = json_decode($input[$field], true);
                if (is_array($decoded)) {
                    $input[$field] = $decoded;
                }
            }
        }

        return $input;
    }

    private static function nextPriority()
    {
        global $wpdb;

        $table = $wpdb->prefix . Rule::TABLE_NAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $max = $wpdb->get_var("SELECT MAX(priority) FROM $table WHERE deleted = 0");

        return $max === null ? 0 : (int)$max + 1;
    }

    private static function notFound($ruleId)
    {
        return self::error('adp_rule_not_found', sprintf('There is no pricing rule with id %d. Use adp/rules-list to find it.', $ruleId), 404);
    }

    private static function error($code, $message, $status = 400)
    {
        return new WP_Error($code, $message, array('status' => $status));
    }
}
