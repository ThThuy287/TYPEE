<?php
/**
 * Thẻ sản phẩm – Figma 1:315 (Product Item). Dùng ở trang chủ, cửa hàng, gợi ý…
 * Mọi giá trị lấy từ WC_Product + ACF meta; không có dữ liệu thì phần tương ứng tự ẩn.
 *
 * @var array $args { product: WC_Product }
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$product = isset( $args['product'] ) ? wc_get_product( $args['product'] ) : null;
if ( ! $product || ! $product->is_visible() ) {
	return;
}

$discount = typee_discount_percent( $product );
$stock    = typee_stock_label( $product );
$specs    = array_slice( typee_product_specs( $product ), 0, 3 );
$sku      = $product->get_sku();
$link     = $product->get_permalink();

// Giá: biến thể hiển thị "Từ X", sản phẩm đơn hiển thị giá + giá gốc khi đang giảm.
$price_now     = (float) $product->get_price();
$price_regular = 0;
$price_prefix  = '';
if ( $product->is_type( 'variable' ) ) {
	$price_now = (float) $product->get_variation_price( 'min', true );
	$max       = (float) $product->get_variation_price( 'max', true );
	if ( $max > $price_now ) {
		$price_prefix = 'Từ ';
	}
	if ( $product->is_on_sale() ) {
		$price_regular = (float) $product->get_variation_regular_price( 'min', true );
	}
} elseif ( $product->is_on_sale() ) {
	$price_regular = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
	$price_now     = (float) wc_get_price_to_display( $product );
} else {
	$price_now = (float) wc_get_price_to_display( $product );
}

// Nút thêm giỏ: giữ class/thuộc tính chuẩn WooCommerce để AJAX add-to-cart và fragments hoạt động.
$btn_classes = array( 'ty-card__cart', 'button', 'product_type_' . $product->get_type() );
if ( $product->is_purchasable() && $product->is_in_stock() ) {
	$btn_classes[] = 'add_to_cart_button';
	if ( $product->supports( 'ajax_add_to_cart' ) ) {
		$btn_classes[] = 'ajax_add_to_cart';
	}
}
$btn_text = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ? 'Thêm vào giỏ' : $product->add_to_cart_text();
?>
<article class="ty-card" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<div class="ty-card__body">
		<a class="ty-card__media" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo typee_product_image( $product, 'woocommerce_thumbnail', array( 'class' => 'ty-card__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ( $discount ) : ?>
				<span class="ty-card__badge ty-card__badge--sale"><?php echo esc_html( sprintf( 'GIẢM %d%%', $discount ) ); ?></span>
			<?php elseif ( $product->is_featured() ) : ?>
				<span class="ty-card__badge ty-card__badge--dark">HOT DEAL</span>
			<?php endif; ?>
		</a>
		<button type="button" class="ty-card__fav" data-typee-wishlist="<?php echo esc_attr( $product->get_id() ); ?>" aria-pressed="false" aria-label="Yêu thích">
			<?php typee_the_icon( 'favorite', 16 ); ?>
		</button>

		<div class="ty-card__meta">
			<span class="ty-card__sku"><?php echo $sku ? esc_html( 'SKU: ' . $sku ) : ''; ?></span>
			<?php if ( $stock['text'] ) : ?>
				<span class="ty-card__stock is-<?php echo esc_attr( $stock['state'] ); ?>"><?php echo esc_html( $stock['text'] ); ?></span>
			<?php endif; ?>
		</div>

		<h3 class="ty-card__title"><a href="<?php echo esc_url( $link ); ?>" title="<?php echo esc_attr( $product->get_name() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>

		<?php if ( $specs ) : ?>
			<ul class="ty-card__specs">
				<?php foreach ( $specs as $spec ) : ?>
					<li title="<?php echo esc_attr( $spec['label'] ); ?>"><?php echo esc_html( $spec['value'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="ty-card__price">
			<?php if ( $price_now > 0 ) : ?>
				<span class="ty-card__now"><?php echo esc_html( $price_prefix . typee_money( $price_now ) ); ?></span>
				<?php if ( $price_regular > $price_now ) : ?>
					<del class="ty-card__was"><?php echo esc_html( typee_money( $price_regular ) ); ?></del>
				<?php endif; ?>
			<?php else : ?>
				<span class="ty-card__now ty-card__now--contact">Liên hệ</span>
			<?php endif; ?>
		</div>
	</div>

	<div class="ty-card__actions">
		<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
			class="<?php echo esc_attr( implode( ' ', $btn_classes ) ); ?>"
			data-quantity="1"
			data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"
			data-product_sku="<?php echo esc_attr( $sku ); ?>"
			aria-label="<?php echo esc_attr( $product->add_to_cart_description() ); ?>"
			rel="nofollow">
			<?php typee_the_icon( 'add_shopping_cart', 16 ); ?>
			<span><?php echo esc_html( $btn_text ); ?></span>
		</a>
		<button type="button" class="ty-card__compare" data-typee-compare="<?php echo esc_attr( $product->get_id() ); ?>" aria-pressed="false" aria-label="So sánh">
			<?php typee_the_icon( 'compare_arrows', 16 ); ?>
		</button>
	</div>
</article>
