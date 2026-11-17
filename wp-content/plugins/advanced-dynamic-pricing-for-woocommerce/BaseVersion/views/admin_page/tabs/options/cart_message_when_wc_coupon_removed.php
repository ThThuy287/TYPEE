<?php
defined('ABSPATH') or exit;

?>

<tr valign="top">
    <th scope="row" class="titledesc"><?php esc_html_e('Cart message when WooCommerce coupon removed',
            'advanced-dynamic-pricing-for-woocommerce') ?></th>
    <td class="forminp forminp-text">
        <fieldset>
            <label for="cart_message_when_wc_coupon_removed">
                <input style="min-width: 35rem;"
                       value="<?php echo esc_attr($options['cart_message_when_wc_coupon_removed']) ?>"
                       placeholder="<?php
                       //phpcs:ignore WordPress.WP.I18n
                       esc_attr_e('Sorry, it seems the coupon "%s" is invalid - it has now been removed from your order.', 'woocommerce') ?>"
                       name="cart_message_when_wc_coupon_removed" id="cart_message_when_wc_coupon_removed" type="text">
            </label>
        </fieldset>
    </td>
</tr>
