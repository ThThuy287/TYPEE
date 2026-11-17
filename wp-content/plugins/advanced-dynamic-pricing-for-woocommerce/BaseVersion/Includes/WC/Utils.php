<?php

namespace ADP\BaseVersion\Includes\WC;

use ADP\BaseVersion\Includes\Cache\CacheHelper;
use ADP\BaseVersion\Includes\Functions;
use ADP\Factory;

if ( ! defined( 'ABSPATH' ) ) exit;

class Utils
{
    /**
     * @param array $needleNotice
     * @param array $newNotice
     */
    public static function replaceWcNotice($needleNotice, $newNotice)
    {
        if (!is_array($needleNotice) || !is_array($newNotice)) {
            return;
        }

        if (!function_exists("wc_get_notices")) {
            return;
        }

        $needleNotice = array(
            'type' => isset($needleNotice['type']) ? $needleNotice['type'] : null,
            'text' => isset($needleNotice['text']) ? $needleNotice['text'] : "",
        );

        $newNotice = array(
            'type' => isset($newNotice['type']) ? $newNotice['type'] : null,
            'text' => isset($newNotice['text']) ? $newNotice['text'] : "",
        );


        $newNotices = array();
        foreach (wc_get_notices() as $type => $notices) {
            if (!isset($newNotices[$type])) {
                $newNotices[$type] = array();
            }

            foreach ($notices as $loopNotice) {
                if (!empty($loopNotice['notice'])
                    && $needleNotice['text'] === $loopNotice['notice']
                    && (!$needleNotice['type'] || $needleNotice['type'] === $type)
                ) {
                    if ($newNotice['type'] === null) {
                        $newNotice['type'] = $type;
                    }

                    if (!isset($newNotices[$newNotice['type']])) {
                        $newNotices[$newNotice['type']] = array();
                    }

                    $newNotices[$newNotice['type']][] = array(
                        'notice' => $newNotice['text'],
                        'data' => array(),
                    );

                    continue;
                } else {
                    $newNotices[$type][] = $loopNotice;
                }
            }
        }
        wc_set_notices($newNotices);
    }

    /**
     * @param string $couponCode
     *
     * @return string
     */
    public static function getCouponNotApplicableMessage($couponCode)
    {
        $couponCode = wc_format_coupon_code($couponCode);

        foreach (CacheHelper::loadActiveRules()->getRules() as $rule) {
            if ($rule->getActivationCouponCode() === $couponCode && $rule->getMessageNoticeCouponNotApply()) {
                return $rule->getMessageNoticeCouponNotApply();
            }
        }

        return esc_html__('Sorry, this coupon is not applicable to cart.', 'advanced-dynamic-pricing-for-woocommerce');
    }

    /**
     * @param string $couponCode
     *
     * @return string
     */
    public static function getWcCouponRemovedMessage($couponCode)
    {
        $template = trim(html_entity_decode((string)adp_context()->getOption('cart_message_when_wc_coupon_removed'), ENT_QUOTES));

        if ($template === "") {
            //phpcs:ignore WordPress.WP.I18n
            $template = __('Sorry, it seems the coupon "%s" is invalid - it has now been removed from your order.', 'woocommerce');
        }

        return str_replace('%s', esc_html($couponCode), $template);
    }

    /**
     * @param string $couponCode
     *
     * @return string
     */
    public static function getWcCouponCantApplyMessage($couponCode)
    {
        $template = trim(html_entity_decode(
            (string)adp_context()->getOption('cart_message_when_wc_coupon_cant_apply'),
            ENT_QUOTES
        ));

        if ($template === "") {
            /* translators: %s: coupon code */
            $template = __('Sorry, coupon "%s" is not applicable to selected products.', 'woocommerce');
        }

        return str_replace('%s', esc_html($couponCode), $template);
    }

    public static function addPersistentProductsToSaleQuery() {
        add_filter('woocommerce_shortcode_products_query', function($query_args, $atts, $type) {
            // Check if the shortcode is sale_products
            if ($type === 'sale_products') {
                // $salePriceAdpProductIds = Functions::getInstance()->getProductsWithSalePriceAdp();

                $onSaleProducts = Factory::get("Shortcodes_OnSaleProducts");
                $salePriceAdpProductIds = $onSaleProducts->getCachedProductsIds();

                // Merge the product IDs with the existing post__in array if it exists
                if (!empty($query_args['post__in'])) {
                    $query_args['post__in'] = array_unique(array_merge($query_args['post__in'], $salePriceAdpProductIds));
                } else {
                    $query_args['post__in'] = $salePriceAdpProductIds;
                }
            }

            return $query_args;
        }, 10, 3);
    }
}
