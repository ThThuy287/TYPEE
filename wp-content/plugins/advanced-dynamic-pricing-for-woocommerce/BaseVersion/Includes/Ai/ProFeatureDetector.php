<?php

namespace ADP\BaseVersion\Includes\Ai;

defined('ABSPATH') or exit;

/**
 * The one place that decides whether a built rule uses a Pro-only mechanism. Rules are always
 * built in full, as if Pro were available; callers check the result here and refuse it on a
 * site without Pro.
 */
class ProFeatureDetector
{
    /**
     * Whether the Pro version's code is actually loaded on this site - i.e. whether Pro rule
     * features (extra cart conditions/adjustments, Auto Add to Cart, Product Collections,
     * exclusive rules, etc.) are really available to draft right now, not just "the plugin
     * author's Pro feature list". Deliberately NOT gated on the EDD license status
     * (`edd_wc_adp_license_status`) - that option only controls automatic update checks
     * elsewhere in the plugin, it has never gated feature access, and no other part of this
     * codebase treats an expired/invalid license as "Pro is off". Matching that existing
     * behavior here (rather than inventing a new, stricter rule just for the chat) keeps the
     * assistant consistent with what the admin actually sees in the rest of the UI.
     *
     * @return bool
     */
    public static function isProActive()
    {
        return function_exists('adp_context') && ! adp_context()->isBaseVersion();
    }

    /**
     * The one programmatic licensing check in the whole chat flow (see RuleAssistant::handle()) - inspects
     * what was actually built/requested and returns a short, human-readable name for the
     * Pro-only mechanism involved, or null if nothing here needs Pro. Checked against BOTH the
     * final built rule data (`$rule` - catches an inherited Pro field an edit didn't even
     * mention, e.g. an existing `shipping_state` condition on a rule opened for an unrelated
     * change) AND the model's raw decoded reply (`$decoded`/`$actionName` - catches intent that
     * didn't make it into the built rule because resolving it failed for an unrelated reason,
     * e.g. a `collection` filter_type when the Collection repository class isn't even loaded on
     * a free-only site, so no row could be added at all).
     *
     * Note on i18n: the per-mechanism labels below are plain English, not wrapped in `__()` -
     * `__()` needs a literal string argument to be found by translation tooling at all, which
     * a shared lookup table can't give it. They're only ever substituted (as %s) into the one
     * translatable sentence that actually shows them, in RuleAssistant::handle() - that sentence is what
     * carries the translation.
     *
     * @param array|null $rule
     * @param array      $decoded
     * @param string     $actionName
     */
    public static function detectProFeature($rule, $decoded, $actionName)
    {
        static $proActions = array(
            'create_bogo_discount' => 'a BOGO where the second item is discounted rather than free (Auto Add to Cart)',
            'create_set_discount'  => 'a "Product set" deal (e.g. "3=2")',
            'create_combo_deal'    => 'a multi-product set deal (e.g. "3x2")',
        );
        if (isset($proActions[$actionName])) {
            return $proActions[$actionName];
        }

        if ( ! empty($decoded['pro_adjustment_type'])) {
            return 'that cart adjustment (fee/tax/shipping-price-override/coupon-suppression)';
        }

        if (($decoded['filter_type'] ?? '') === 'collection' || ($decoded['exclude_filter_type'] ?? '') === 'collection') {
            return 'targeting a Product Collection';
        }

        if ( ! empty($rule['exclusive']) || ! empty($decoded['exclusive'])) {
            return 'an exclusive rule';
        }

        static $proConditionLabels = array(
            'customer_shipping_zone'       => 'a shipping zone condition',
            'shipping_state'               => 'a shipping state condition',
            'customer_geolocation_state'   => 'a geolocation-based condition',
            'customer_geolocation_country' => 'a geolocation-based condition',
            'customer_payment_method'      => 'a payment method condition',
            'cart_currency'                => 'a currency condition',
            'cart_coupons'                 => 'a coupons-in-cart condition',
            'customer_ordered_products'    => 'a purchase-history condition',
            'customer_paid_order_count'    => 'a purchase-history condition',
            'customer_spent_within'        => 'a customer-spending condition',
            'customer_avg_order_amount'    => 'a customer-spending condition',
            'customer_last_order_amount'   => 'a customer-spending condition',
            'customer_is_first_order'      => 'a first-order condition',
            'date_time'                    => 'a date/time window condition',
        );
        foreach (($rule['conditions'] ?? array()) as $condition) {
            $type = $condition['type'] ?? '';
            if (isset($proConditionLabels[$type])) {
                return $proConditionLabels[$type];
            }
            if (ConditionBuilder::isProOnly($condition)) {
                return sprintf('the "%s" condition', str_replace('_', ' ', $type));
            }
        }

        static $proAdjustmentLabels = array(
            'fee__percentage'                 => 'a percentage cart fee',
            'discount__disable_wc_coupons'     => 'disabling specific coupons',
            'discount__apply_wc_coupons'       => 'auto-applying specific coupons',
            'discount__disable_all_wc_coupons' => 'disabling all coupons',
            'shipping__amount'                 => 'a shipping price override',
            'shipping__percentage'             => 'a shipping price override',
            'shipping__fixed'                  => 'a shipping price override',
            'shipping__suppress_method'        => 'hiding a shipping method',
            'payment__suppress_method'         => 'hiding a payment method',
            'tax__exempt'                      => 'tax exemption',
            'free__shipping_adp'               => 'the free-shipping override',
            'discount_repeatable__amount'            => 'a cart discount repeated on each rule execution',
            'discount_repeatable_sets_count__amount' => 'a cart discount per item line',
            'fee_repeatable__amount'                 => 'a cart fee repeated on each rule execution',
            'fee_repeatable_sets_count__amount'      => 'a cart fee per item line',
            'fee_matched_products__percentage'       => 'a percentage fee on the matched products',
        );
        foreach (($rule['cart_adjustments'] ?? array()) as $adjustment) {
            $type = $adjustment['type'] ?? '';
            if (isset($proAdjustmentLabels[$type])) {
                return $proAdjustmentLabels[$type];
            }
        }

        // Product Collections used as a filter target anywhere a product-filter-shaped row
        // can appear (main filters, a gift's/BOGO-discount's auto-added product)
        $filterGroups = array($rule['filters'] ?? array());
        if ( ! empty($rule['get_products']['value'])) {
            $filterGroups[] = $rule['get_products']['value'];
        }
        if ( ! empty($rule['auto_add_products']['value'])) {
            $filterGroups[] = $rule['auto_add_products']['value'];
        }
        foreach ($filterGroups as $group) {
            foreach ($group as $row) {
                if (($row['type'] ?? '') === 'product_collections') {
                    return 'targeting a Product Collection';
                }
            }
        }

        foreach (($rule['limits'] ?? array()) as $limit) {
            if (($limit['type'] ?? '') === 'max_usage_per_customer') {
                return 'a per-customer usage limit';
            }
        }
        if ( ! empty($rule['additional']['trigger_coupon_code'])) {
            return 'activating a rule by a coupon code';
        }
        foreach (($rule['filters'] ?? array()) as $filter) {
            if (($filter['product_exclude']['backorder'] ?? '') === '1') {
                return 'excluding products on backorder';
            }
        }

        $freeGiftModes = array('giftable_products', 'use_product_from_filter');
        foreach (($rule['get_products']['value'] ?? array()) as $gift) {
            if ( ! in_array($gift['gift_mode'] ?? 'giftable_products', $freeGiftModes, true)) {
                return 'that way of choosing the gift (rotation, random or the customer\'s choice)';
            }
        }
        if ( ! empty($rule['get_products']['max_amount_for_gifts']) || ! empty($rule['get_products']['is_gifts_below_cheapest'])) {
            return 'a limit on the gifts';
        }

        $proDiscountTypes = array('discount__amount_per_item', 'price__fixed_per_item', 'discount__expression_price');
        $adjustment       = $rule['product_adjustments'] ?? array();
        $usedTypes        = array($adjustment['total']['type'] ?? '');
        foreach (($adjustment['split'] ?? array()) as $part) {
            $usedTypes[] = $part['type'] ?? '';
        }
        if (array_intersect($usedTypes, $proDiscountTypes)) {
            return 'a per-item or formula-based discount';
        }

        if (in_array($rule['bulk_adjustments']['measurement'] ?? 'qty', array('sum', 'weight'), true) && ! empty($rule['bulk_adjustments']['ranges'])) {
            return 'bulk tiers measured by amount or weight';
        }

        if (array_filter((array)($rule['advertising'] ?? array())) || ! empty($rule['condition_message']['total']['message'])) {
            return 'discount messages, badges and timers';
        }

        return null;
    }
}
