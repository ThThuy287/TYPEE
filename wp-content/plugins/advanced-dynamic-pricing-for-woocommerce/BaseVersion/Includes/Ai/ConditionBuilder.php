<?php

namespace ADP\BaseVersion\Includes\Ai;

use ADP\BaseVersion\Includes\Database\Repository\RuleRepository;
use ADP\Factory;

defined('ABSPATH') or exit;

/**
 * Builds a rule's condition rows for ANY condition type the rule editor has, from one plain
 * description: `{"type": "cart_weight", "operator": ">=", "value": 5}`.
 *
 * It does not keep a list of condition types of its own. What a type needs (a value to compare,
 * a list, a yes/no, a date...) is read off the plugin's condition class for that type, so a type
 * added to the editor later is understood here as well. What IS kept here is how the names a
 * request uses turn into what a condition stores (a category name into a term id, and so on).
 */
class ConditionBuilder
{
    /**
     * Why the last build() returned null, in words fit to show to whoever asked.
     *
     * @var string
     */
    public static $problem = '';

    /**
     * Condition types of the free version. Every other type the editor offers is Pro-only.
     */
    const FREE_TYPES = array(
        'cart_items_count', 'cart_weight', 'cart_subtotal', 'customer_logged', 'customer_order_count', 'customer_role',
        'subscriptions', 'date', 'days_of_week', 'time', 'shipping_country', 'products_all', 'product_categories_all',
        'product_category_slug_all', 'product_tags_all', 'product_sku_all', 'product_attributes_all', 'product_brands_all',
        'product_custom_attributes_all', 'product_custom_fields_all',
    );

    /**
     * @param array $item `type` plus that type's fields: `operator` + `value`, `match` + `values`,
     *                    `time_range`, `from` / `to` / `on`, `min_qty` / `max_qty` / `measure`
     *
     * @return array[]|null condition rows (a period gives two), or null - see self::$problem
     */
    public static function build($item)
    {
        self::$problem = '';

        $type = is_array($item) ? sanitize_key((string)($item['type'] ?? '')) : '';
        if ($type === '') {
            return self::fail('A condition needs a `type`.');
        }

        $class = self::conditionClass($type);
        if ( ! $class) {
            if ( ! ProFeatureDetector::isProActive()) {
                // not known to the free version: either Pro-only or a typo. Kept as it came, so
                // that the licensing check can say what it is rather than "could not build".
                return array(array('type' => $type, 'options' => array()));
            }

            return self::fail(sprintf('There is no condition type "%s".', $type));
        }

        $shapes  = self::shapes($class);
        $allowed = defined($class . '::AVAILABLE_COMP_METHODS') ? (array)constant($class . '::AVAILABLE_COMP_METHODS') : array();

        if (isset($shapes['ProductAll'])) {
            return self::productsInCart($type, $item);
        }
        if (isset($shapes['Combination'])) {
            return self::productsCombination($type, $item);
        }
        if (isset($shapes['DateTimeComparison'])) {
            return self::dateTime($type, $item, $allowed);
        }

        $options = array();

        if (isset($shapes['ListComparison'])) {
            $method = self::pick($item['match'] ?? '', $allowed, $allowed ? $allowed[0] : 'in_list');
            // "has no coupon at all" / "has some coupon" are complete without a list
            $listFree = in_array($method, array('none_at_all', 'at_least_one_any'), true);
            $values   = isset($item['values']) && is_array($item['values']) ? $item['values'] : array();
            if ( ! $values && ! $listFree) {
                return self::fail(sprintf('The "%s" condition needs `values`.', $type));
            }
            if ($values) {
                $list = self::resolveList($type, $values);
                if ($list === null) {
                    return null;
                }
                $options['comparison_list'] = $list;
            }
            $options['comparison_list_method'] = $method;
        }

        if (isset($shapes['ValueComparison'])) {
            if ( ! isset($item['value']) || $item['value'] === '') {
                return self::fail(sprintf('The "%s" condition needs a `value`.', $type));
            }
            $fallback = in_array('>=', $allowed, true) ? '>=' : ($allowed ? $allowed[0] : '>=');
            $method   = self::pick($item['operator'] ?? ($item['match'] ?? ''), $allowed, $fallback);
            // a postal code list is text; everything else compared here is a number
            $options['comparison_value']        = is_numeric($item['value']) ? (string)(float)$item['value'] : sanitize_text_field((string)$item['value']);
            $options['comparison_value_method'] = $method;
        }

        if (isset($shapes['TimeRange'])) {
            $options['time_range'] = ! empty($item['time_range']) ? sanitize_text_field((string)$item['time_range']) : 'all_time';
        }

        if (isset($shapes['Binary'])) {
            $options['comparison_bin_value'] = filter_var($item['value'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';
        }

        if (isset($shapes['RangeValue'])) {
            $options['start_range'] = isset($item['min_qty']) ? (string)(int)$item['min_qty'] : '';
            $options['end_range']   = isset($item['max_qty']) ? (string)(int)$item['max_qty'] : '';
        }

        if ( ! $options) {
            return self::fail(sprintf('The "%s" condition is not something that can be described here.', $type));
        }

        return array(array('type' => $type, 'options' => $options));
    }

    /**
     * @param array $row a stored condition row
     */
    public static function isProOnly($row)
    {
        $type = (string)($row['type'] ?? '');
        if ($type === '') {
            return false;
        }
        // products counted by money or by weight rather than by pieces
        if (in_array($row['options']['product_measure'] ?? 'qty', array('amount', 'weight'), true)) {
            return true;
        }

        return ! in_array($type, self::FREE_TYPES, true) && strpos($type, 'custom_taxonomy_all_') !== 0;
    }

    /**
     * @return array<string, array{label: string, pro: bool, fields: string}> every condition type
     *         the site's rule editor offers, with the fields it takes here
     */
    public static function catalogue()
    {
        $catalogue = array();
        foreach (self::conditionClasses() as $type => $class) {
            $shapes = self::shapes($class);
            $fields = array();
            if (isset($shapes['ProductAll'])) {
                $fields[] = 'values, match (in_list|not_in_list|not_containing), min_qty, max_qty; or measure (amount|weight) with operator + value';
            } elseif (isset($shapes['Combination'])) {
                $fields[] = 'values (products), combine (combine|any|each), operator, value, max_value';
            } elseif (isset($shapes['DateTimeComparison'])) {
                $allowed  = defined($class . '::AVAILABLE_COMP_METHODS') ? (array)constant($class . '::AVAILABLE_COMP_METHODS') : array();
                $fields[] = in_array('later', $allowed, true) ? 'match (later|earlier), value (e.g. "-1 month")' : 'from, to' . (in_array('specific_date', $allowed, true) ? ', on' : '');
            } else {
                $allowed = defined($class . '::AVAILABLE_COMP_METHODS') ? implode('|', (array)constant($class . '::AVAILABLE_COMP_METHODS')) : '';
                if (isset($shapes['ListComparison'])) {
                    $fields[] = 'values, match (' . $allowed . ')';
                }
                if (isset($shapes['ValueComparison'])) {
                    $fields[] = 'operator (' . $allowed . '), value';
                }
                if (isset($shapes['TimeRange'])) {
                    $fields[] = 'time_range';
                }
                if (isset($shapes['Binary'])) {
                    $fields[] = 'value (true|false)';
                }
            }
            $label            = method_exists($class, 'getLabel') ? trim(str_replace('*', '', (string)$class::getLabel())) : $type;
            $catalogue[$type] = array(
                'label'  => $label !== '' ? $label : $type,
                'pro'    => self::isProOnly(array('type' => $type)),
                'fields' => implode('; ', $fields),
            );
        }

        return $catalogue;
    }

    // -----------------------------------------------------------------

    private static function productsInCart($type, $item)
    {
        $values = isset($item['values']) && is_array($item['values']) ? $item['values'] : array();
        if ( ! $values) {
            return self::fail(sprintf('The "%s" condition needs `values`.', $type));
        }
        $list = self::resolveList($type, $values);
        if ($list === null) {
            return null;
        }

        $measure = in_array($item['measure'] ?? '', array('amount', 'weight'), true) ? $item['measure'] : 'qty';
        $options = array(
            'product_measure'        => $measure,
            'comparison_list'        => $list,
            'comparison_list_method' => self::pick($item['match'] ?? '', array('in_list', 'not_in_list', 'not_containing'), 'in_list'),
        );

        if ($measure === 'qty') {
            $options['start_range'] = (string)max(1, (int)($item['min_qty'] ?? 1));
            $options['end_range']   = isset($item['max_qty']) && (int)$item['max_qty'] > 0 ? (string)(int)$item['max_qty'] : '';
        } else {
            if ( ! isset($item['value']) || $item['value'] === '') {
                return self::fail(sprintf('The "%s" condition measured by %s needs a `value`.', $type, $measure));
            }
            $options['comparison_value']        = (string)(float)$item['value'];
            $options['comparison_value_method'] = self::pick($item['operator'] ?? '', array('<', '<=', '>=', '>'), '>=');
            if ($measure === 'amount' && array_key_exists('incl_tax', $item)) {
                $options['is_incl_tax'] = filter_var($item['incl_tax'], FILTER_VALIDATE_BOOLEAN) ? '1' : '';
            }
        }

        return array(array('type' => $type, 'options' => $options));
    }

    private static function productsCombination($type, $item)
    {
        $values = isset($item['values']) && is_array($item['values']) ? $item['values'] : array();
        $anyOne = filter_var($item['any_product'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $list   = array();
        if ($values) {
            $list = self::resolveList('products_all', $values);
            if ($list === null) {
                return null;
            }
        } elseif ( ! $anyOne) {
            return self::fail('A products combination needs `values` (the products).');
        }
        if ( ! isset($item['value']) || $item['value'] === '') {
            return self::fail('A products combination needs a `value` (the quantity to compare with).');
        }

        return array(array(
            'type'    => $type,
            'options' => array(
                'combine_type'            => self::pick($item['combine'] ?? '', array('combine', 'any', 'each'), 'combine'),
                'combine_list'            => $list,
                'combine_any_product'     => $anyOne ? '1' : '',
                'comparison_value_method' => self::pick($item['operator'] ?? '', array('<', '<=', '>=', '>', '=', '!=', 'in_range'), '>='),
                'comparison_value'        => (string)(float)$item['value'],
                'comparison_end_value'    => isset($item['max_value']) ? (string)(float)$item['max_value'] : '',
            ),
        ));
    }

    private static function dateTime($type, $item, $allowed)
    {
        // "the customer's last order was later/earlier than ..." - one row, a relative moment
        if (in_array('later', $allowed, true)) {
            if (empty($item['value'])) {
                return self::fail(sprintf('The "%s" condition needs a `value` such as "-1 month".', $type));
            }

            return array(array(
                'type'    => $type,
                'options' => array(
                    'comparison_datetime'        => sanitize_text_field((string)$item['value']),
                    'comparison_datetime_method' => self::pick($item['match'] ?? ($item['operator'] ?? ''), $allowed, 'later'),
                ),
            ));
        }

        $rows  = array();
        $sides = array('from' => 'from', 'to' => 'to', 'on' => 'specific_date');
        foreach ($sides as $field => $method) {
            if (empty($item[$field]) || ! in_array($method, $allowed, true)) {
                continue;
            }
            $rows[] = array(
                'type'    => $type,
                'options' => array(
                    'comparison_datetime'        => sanitize_text_field((string)$item[$field]),
                    'comparison_datetime_method' => $method,
                ),
            );
        }

        return $rows ?: self::fail(sprintf('The "%s" condition needs `from` and/or `to`.', $type));
    }

    /**
     * @return string[]|null what the condition stores for these names; null (with $problem) when
     *                       one of them is not on the site
     */
    private static function resolveList($type, $names)
    {
        $helperLists = array(
            'shipping_country'             => 'getCountries',
            'customer_geolocation_country' => 'getCountries',
            'shipping_state'               => 'getStates',
            'customer_geolocation_state'   => 'getStates',
            'customer_shipping_zone'       => 'getShippingZones',
            'customer_payment_method'      => 'getPaymentMethods',
            'customer_shipping_method'     => 'getShippingMethods',
            'cart_currency'                => 'getCurrencies',
            'days_of_week'                 => 'getWeekdays',
        );
        $termTaxonomies = array(
            'product_categories_all'     => 'product_cat',
            'product_tags_all'           => 'product_tag',
            'product_brands_all'         => 'product_brand',
            'product_shipping_class_all' => 'product_shipping_class',
        );
        if (strpos($type, 'custom_taxonomy_all_') === 0) {
            $termTaxonomies[$type] = substr($type, strlen('custom_taxonomy_all_'));
        }
        $productLists = array('products_all', 'customer_ordered_products', 'subscriptions');

        $resolved = array();
        foreach ($names as $name) {
            $name  = trim((string)$name);
            $value = null;

            if ($name === '') {
                continue;
            } elseif (isset($helperLists[$type])) {
                $value = TargetResolver::resolveHelperListValue($helperLists[$type], $name);
            } elseif (isset($termTaxonomies[$type])) {
                $term  = TargetResolver::findTermInTaxonomy($termTaxonomies[$type], $name);
                $value = $term ? $term->term_id : null;
            } elseif ($type === 'product_category_slug_all') {
                $term  = TargetResolver::findTermInTaxonomy('product_cat', $name);
                $value = $term ? $term->slug : null;
            } elseif ($type === 'product_attributes_all') {
                $value = self::findAttributeTerm($name);
            } elseif ($type === 'product_collections_all') {
                $collection = TargetResolver::findProductCollection($name);
                $value      = $collection ? $collection->id : null;
            } elseif (in_array($type, $productLists, true)) {
                $product = TargetResolver::findProduct($name);
                $value   = $product ? $product->get_id() : null;
            } elseif ($type === 'customer_role') {
                $value = TargetResolver::findRole($name);
            } elseif ($type === 'customer_users') {
                $user  = get_user_by('login', $name) ?: (get_user_by('email', $name) ?: get_user_by('slug', sanitize_title($name)));
                $value = $user ? $user->ID : null;
            } elseif ($type === 'cart_coupons') {
                $value = wc_get_coupon_id_by_code($name) ? $name : null;
            } elseif (in_array($type, array('cart_was_rule_applied', 'customer_list_rules_aplied'), true)) {
                $value = self::findRuleId($name);
            } else {
                // SKUs, custom fields and attributes, capabilities, languages, user meta: stored
                // as the text itself
                $value = sanitize_text_field($name);
            }

            if ($value === null || $value === '' || $value === false) {
                return self::fail(sprintf('"%s" was not found on this site (condition "%s").', $name, $type));
            }
            $resolved[] = (string)$value;
        }

        return $resolved ? array_values(array_unique($resolved)) : self::fail(sprintf('The "%s" condition needs `values`.', $type));
    }

    /**
     * "Color: Red", or just "Red" when only one attribute has such a term.
     */
    private static function findAttributeTerm($name)
    {
        $parts = array_map('trim', explode(':', $name, 2));
        foreach (wc_get_attribute_taxonomies() as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            if (count($parts) === 2 && strcasecmp($attribute->attribute_label, $parts[0]) !== 0 && strcasecmp($attribute->attribute_name, $parts[0]) !== 0) {
                continue;
            }
            $term = TargetResolver::findTermInTaxonomy($taxonomy, count($parts) === 2 ? $parts[1] : $parts[0]);
            if ($term) {
                return $term->term_id;
            }
        }

        return null;
    }

    private static function findRuleId($name)
    {
        $rules = (new RuleRepository())->getRules(is_numeric($name) ? array('id' => (int)$name) : array());
        foreach ($rules as $rule) {
            $data = $rule->getData();
            if (is_numeric($name) || strcasecmp(html_entity_decode((string)$data['title'], ENT_QUOTES), $name) === 0) {
                return $data['id'];
            }
        }

        return null;
    }

    private static function conditionClass($type)
    {
        $classes = self::conditionClasses();

        return $classes[$type] ?? null;
    }

    /**
     * @return array<string, string> condition type => its class, as the rule editor has them
     */
    private static function conditionClasses()
    {
        static $classes = null;
        if ($classes === null) {
            try {
                $classes = (array)Factory::get('Core_Rule_CartCondition_ConditionsLoader')->getItems();
            } catch (\Throwable $e) {
                $classes = array();
            }
        }

        return $classes;
    }

    /**
     * @return array<string, true> which kinds of input the condition class takes
     */
    private static function shapes($class)
    {
        $shapes = array();
        foreach (class_implements($class) as $interface) {
            if (strpos($interface, 'CartCondition\\Interfaces\\') !== false) {
                $shapes[str_replace('Condition', '', substr(strrchr($interface, '\\'), 1))] = true;
            }
        }

        return $shapes;
    }

    private static function pick($given, $allowed, $fallback)
    {
        $given = is_string($given) ? trim($given) : '';
        // the two names requests use for "no coupon" / "any coupon"
        $aliases = array('any_applied' => 'at_least_one_any', 'in_list' => 'at_least_one', 'not_in_list' => 'none');
        if ($given !== '' && in_array($given, $allowed, true)) {
            return $given;
        }
        if (isset($aliases[$given]) && in_array($aliases[$given], $allowed, true)) {
            return $aliases[$given];
        }

        return $fallback;
    }

    private static function fail($problem)
    {
        self::$problem = $problem;

        return null;
    }
}
