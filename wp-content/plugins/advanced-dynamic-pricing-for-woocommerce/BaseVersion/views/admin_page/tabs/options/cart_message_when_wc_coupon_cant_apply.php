<?php
defined('ABSPATH') or exit;

?>

<tr valign="top">
    <th scope="row" class="titledesc"><?php esc_html_e('Cart message when WooCommerce coupon can\'t be applied',
            'advanced-dynamic-pricing-for-woocommerce') ?></th>
    <td class="forminp forminp-text">
        <fieldset>
            <label for="cart_message_when_wc_coupon_cant_apply">
                <input style="min-width: 35rem;"
                       value="<?php echo esc_attr($options['cart_message_when_wc_coupon_cant_apply']) ?>"
                       placeholder="<?php
                       //phpcs:ignore WordPress.WP.I18n
                       esc_attr_e('Sorry, coupon "%s" is not applicable to selected products.', 'woocommerce') ?>"
                       name="cart_message_when_wc_coupon_cant_apply" id="cart_message_when_wc_coupon_cant_apply" type="text">
            </label>
        </fieldset>
    </td>
</tr>
