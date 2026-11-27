<?php
/**
 * Checkout Payment Section – Danh sách Radio Card Phương Thức Thanh Toán (Figma 82:1385)
 *
 * Giữ nguyên `#payment.woocommerce-checkout-payment` và `woocommerce-process-checkout-nonce`
 * để WooCommerce AJAX `update_order_review` và `process_checkout` hoạt động chuẩn 100%.
 * Nút `#place_order` được đặt ở cột phải Order Summary (trong cùng `<form.checkout>`).
 *
 * @package typee-child
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>
<div id="payment" class="woocommerce-checkout-payment ty-co-payments">
	<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods ty-co-pay-list" aria-label="Phương thức thanh toán">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li class="ty-co-pay-empty">';
				wc_print_notice( 'Hiện chưa có phương thức thanh toán khả dụng. Vui lòng liên hệ Hotline 1900 8899 để được hỗ trợ.', 'notice' );
				echo '</li>';
			}
			?>
		</ul>
	<?php endif; ?>

	<noscript>
		<button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="Cập nhật tổng tiền">Cập nhật tổng tiền</button>
	</noscript>

	<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
