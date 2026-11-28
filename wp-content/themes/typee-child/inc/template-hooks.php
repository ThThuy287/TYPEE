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

/**
 * Nội dung bên trong Side Cart Drawer (cập nhật động qua WooCommerce AJAX fragments).
 */
function typee_sidecart_content() {
	$has_cart = function_exists( 'WC' ) && WC()->cart;
	$items    = $has_cart ? WC()->cart->get_cart() : array();
	$count    = $has_cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	$subtotal = $has_cart ? WC()->cart->get_cart_subtotal() : typee_money( 0 );

	ob_start();
	?>
	<div class="ty-sidecart__content" data-sidecart-count="<?php echo esc_attr( $count ); ?>">
		<?php if ( empty( $items ) ) : ?>
			<div class="ty-sidecart__empty">
				<img
					class="ty-empty__mascot"
					src="<?php echo esc_url( TYPEE_CHILD_URI . '/assets/images/logo-mascot.png' ); ?>"
					alt="TYPEE Mascot"
					width="72"
					height="82"
					loading="lazy"
					decoding="async"
				/>
				<p class="ty-sidecart__empty-title">Giỏ hàng của bạn đang trống</p>
				<p class="ty-sidecart__empty-desc">Khám phá các mẫu Kit CNC, Switch và Keycap chính hãng tại TYPEE.</p>
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>" data-typee-close>Khám phá sản phẩm</a>
			</div>
		<?php else : ?>
			<ul class="ty-sidecart__list">
				<?php
				foreach ( $items as $cart_item_key => $cart_item ) :
					$prod = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
					if ( ! $prod || ! $prod->exists() || $cart_item['quantity'] <= 0 ) {
						continue;
					}
					$link   = $prod->is_visible() ? $prod->get_permalink( $cart_item ) : '';
					$thumb  = $prod->get_image( 'woocommerce_thumbnail', array( 'class' => 'ty-sidecart__img', 'loading' => 'lazy' ) );
					$price  = WC()->cart->get_product_price( $prod );
					$remove = wc_get_cart_remove_url( $cart_item_key );
					?>
					<li class="ty-sidecart__item">
						<a class="ty-sidecart__thumb" href="<?php echo esc_url( $link ? $link : '#' ); ?>"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
						<div class="ty-sidecart__info">
							<a class="ty-sidecart__name" href="<?php echo esc_url( $link ? $link : '#' ); ?>"><?php echo esc_html( $prod->get_name() ); ?></a>
							<div class="ty-sidecart__meta">
								<span class="ty-sidecart__qty">SL: <strong><?php echo (int) $cart_item['quantity']; ?></strong></span>
								<span class="ty-sidecart__price"><?php echo wp_kses_post( $price ); ?></span>
							</div>
						</div>
						<a class="ty-sidecart__rm remove remove_from_cart_button" href="<?php echo esc_url( $remove ); ?>" data-product_id="<?php echo esc_attr( $prod->get_id() ); ?>" data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php echo esc_attr( 'Xóa ' . $prod->get_name() ); ?>">
							<?php typee_the_icon( 'close', 14 ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="ty-sidecart__foot">
				<div class="ty-sidecart__sub">
					<span>Tạm tính (<?php echo (int) $count; ?> sản phẩm)</span>
					<strong><?php echo wp_kses_post( $subtotal ); ?></strong>
				</div>
				<div class="ty-sidecart__actions">
					<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'cart' ) ); ?>">Xem giỏ hàng</a>
					<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'checkout' ) ); ?>">Thanh toán ngay</a>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_action( 'wp_footer', function () {
	if ( ! function_exists( 'WC' ) || is_checkout() || is_cart() ) {
		return;
	}
	?>
	<aside class="ty-drawer ty-drawer--right ty-sidecart" id="ty-sidecart" role="dialog" aria-modal="true" aria-labelledby="ty-sidecart-title" aria-hidden="true">
		<div class="ty-drawer__head ty-sidecart__head">
			<h2 class="ty-drawer__title" id="ty-sidecart-title">
				<?php typee_the_icon( 'shopping_bag', 18 ); ?>
				<span>Giỏ hàng của bạn</span>
			</h2>
			<button type="button" class="ty-icon-btn" data-typee-close aria-label="Đóng giỏ hàng"><?php typee_the_icon( 'close', 18 ); ?></button>
		</div>
		<div class="ty-sidecart__wrap">
			<?php echo typee_sidecart_content(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</aside>
	<?php
} );

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$fragments['a.ty-hcart']            = typee_header_cart_button();
	$fragments['div.ty-sidecart__content'] = typee_sidecart_content();
	return $fragments;
} );
