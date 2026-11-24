<?php
/**
 * Hook giao diện. Chỉ thêm/bọc markup – giữ nguyên toàn bộ hook chuẩn của WooCommerce
 * để giỏ hàng, thanh toán và các rule khuyến mãi (Advanced Dynamic Pricing) hoạt động như cũ.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'typee';
	return $classes;
} );

/**
 * Nút giỏ hàng trên header (dùng chung cho lần tải trang và AJAX fragments).
 */
function typee_header_cart_button() {
	$count = 0;
	$total = 0;
	if ( function_exists( 'WC' ) && WC()->cart ) {
		$count = (int) WC()->cart->get_cart_contents_count();
		$total = (float) WC()->cart->get_total( 'edit' );
	}
	ob_start();
	?>
	<a class="ty-hcart" href="<?php echo esc_url( typee_url( 'cart' ) ); ?>" data-typee-cart-toggle aria-label="<?php echo esc_attr( sprintf( 'Giỏ hàng: %d sản phẩm', $count ) ); ?>">
		<?php typee_the_icon( 'shopping_bag', 20, 'ty-hcart__icon' ); ?>
		<span class="ty-hcart__text">
			<span class="ty-hcart__label">GIỎ HÀNG</span>
			<span class="ty-hcart__total"><?php echo esc_html( typee_money( $total ) ); ?></span>
		</span>
		<span class="ty-hcart__count"><?php echo esc_html( $count ); ?></span>
	</a>
	<?php
	return ob_get_clean();
}

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$fragments['a.ty-hcart'] = typee_header_cart_button();
	return $fragments;
} );
