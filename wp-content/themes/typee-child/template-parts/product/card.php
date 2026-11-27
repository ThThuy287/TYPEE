<?php
/**
 * Thẻ sản phẩm – Figma 46:1576 (Product Card). Dùng ở trang chủ, danh mục, cửa hàng, gợi ý…
 * Mọi giá trị lấy từ WC_Product + ACF/postmeta; hỗ trợ đổi ảnh phụ khi hover, badge trạng thái,
 * đánh giá sao, thông số tóm tắt và AJAX thêm giỏ hàng chuẩn WooCommerce.
 *
 * @var array $args { product: WC_Product|int }
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$product = isset( $args['product'] ) ? wc_get_product( $args['product'] ) : null;
if ( ! $product || ! $product->is_visible() ) {
	return;
}

$prod_id     = $product->get_id();
$discount    = typee_discount_percent( $product );
$stock       = typee_stock_label( $product );
$specs       = typee_product_specs( $product );
$card_specs  = array_slice( $specs, 0, 3 );
$sku         = $product->get_sku();
$link        = $product->get_permalink();
$gallery_ids = $product->get_gallery_image_ids();
$hover_id    = ! empty( $gallery_ids ) ? (int) $gallery_ids[0] : 0;

// Nhãn MỚI cho sản phẩm đăng trong 30 ngày gần nhất.
$created = $product->get_date_created();
$is_new  = $created && ( time() - $created->getTimestamp() ) <= ( 30 * DAY_IN_SECONDS );

// Đánh giá sao thật từ WooCommerce hoặc danh mục chính nếu chưa có đánh giá.
$rating_avg   = (float) $product->get_average_rating();
$rating_count = (int) $product->get_review_count();
$cat_names    = wp_get_post_terms( $prod_id, 'product_cat', array( 'fields' => 'names' ) );
$primary_cat  = ( ! is_wp_error( $cat_names ) && ! empty( $cat_names ) ) ? $cat_names[0] : '';

// Overlay highlight chips trên ảnh (tối đa 2 nhãn ngắn từ thông số hoặc danh mục).
$img_tags = array();
foreach ( array_slice( $specs, 0, 2 ) as $sp ) {
	$img_tags[] = $sp['value'];
}
if ( empty( $img_tags ) && $primary_cat ) {
	$img_tags[] = $primary_cat;
}

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
$is_out      = 'out' === $stock['state'];
$btn_classes = array( 'ty-card__cart', 'button', 'product_type_' . $product->get_type() );
if ( $product->is_purchasable() && $product->is_in_stock() ) {
	$btn_classes[] = 'add_to_cart_button';
	if ( $product->supports( 'ajax_add_to_cart' ) ) {
		$btn_classes[] = 'ajax_add_to_cart';
	}
} elseif ( $is_out ) {
	$btn_classes[] = 'is-out';
}

if ( $is_out ) {
	$btn_text = 'Hết hàng';
} elseif ( 'backorder' === $stock['state'] ) {
	$btn_text = 'Đặt trước ngay';
} elseif ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
	$btn_text = 'Thêm vào giỏ';
} else {
	$btn_text = $product->add_to_cart_text();
}
?>
<article class="ty-card<?php echo $hover_id ? ' has-hover-img' : ''; ?><?php echo $is_out ? ' is-outofstock' : ''; ?>" data-product-id="<?php echo esc_attr( $prod_id ); ?>">
	<div class="ty-card__body">
		<div class="ty-card__media-wrap">
			<a class="ty-card__media" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo typee_product_image( $product, 'woocommerce_thumbnail', array( 'class' => 'ty-card__img ty-card__img--primary' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( $hover_id ) : ?>
					<?php echo wp_get_attachment_image( $hover_id, 'woocommerce_thumbnail', false, array( 'class' => 'ty-card__img ty-card__img--hover', 'loading' => 'lazy', 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endif; ?>
				<?php if ( $img_tags ) : ?>
					<span class="ty-card__img-tags">
						<?php foreach ( $img_tags as $tag ) : ?>
							<span><?php echo esc_html( $tag ); ?></span>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</a>

			<div class="ty-card__badges">
				<?php if ( $is_out ) : ?>
					<span class="ty-card__badge ty-card__badge--out">HẾT HÀNG</span>
				<?php elseif ( 'backorder' === $stock['state'] ) : ?>
					<span class="ty-card__badge ty-card__badge--pre">ĐẶT TRƯỚC</span>
				<?php elseif ( $product->is_featured() ) : ?>
					<span class="ty-card__badge ty-card__badge--sale">HOT DEAL</span>
				<?php elseif ( $is_new ) : ?>
					<span class="ty-card__badge ty-card__badge--new">MỚI</span>
				<?php endif; ?>
				<?php if ( $discount > 0 && ! $is_out ) : ?>
					<span class="ty-card__badge ty-card__badge--dark"><?php echo esc_html( sprintf( '-%d%%', $discount ) ); ?></span>
				<?php endif; ?>
			</div>

			<button type="button" class="ty-card__fav" data-typee-wishlist="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="Yêu thích">
				<?php typee_the_icon( 'favorite', 16 ); ?>
			</button>
		</div>

		<div class="ty-card__info">
			<div class="ty-card__meta">
				<span class="ty-card__sku"><?php echo $sku ? esc_html( 'SKU: ' . $sku ) : esc_html( $primary_cat ); ?></span>
				<?php if ( $rating_count > 0 ) : ?>
					<span class="ty-card__rating" aria-label="<?php echo esc_attr( sprintf( 'Đánh giá %.1f trên 5 từ %d lượt', $rating_avg, $rating_count ) ); ?>">
						<?php typee_the_icon( 'star', 13, 'ty-card__star' ); ?>
						<strong><?php echo esc_html( number_format( $rating_avg, 1 ) ); ?></strong>
						<span>(<?php echo (int) $rating_count; ?>)</span>
					</span>
				<?php elseif ( $primary_cat && $sku ) : ?>
					<span class="ty-card__cat"><?php echo esc_html( $primary_cat ); ?></span>
				<?php endif; ?>
			</div>

			<h3 class="ty-card__title">
				<a href="<?php echo esc_url( $link ); ?>" title="<?php echo esc_attr( $product->get_name() ); ?>">
					<?php echo esc_html( $product->get_name() ); ?>
				</a>
			</h3>

			<?php if ( $card_specs ) : ?>
				<ul class="ty-card__specs">
					<?php foreach ( $card_specs as $spec ) : ?>
						<li title="<?php echo esc_attr( $spec['label'] . ': ' . $spec['value'] ); ?>"><?php echo esc_html( $spec['value'] ); ?></li>
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

			<?php if ( $stock['text'] ) : ?>
				<div class="ty-card__stockline is-<?php echo esc_attr( $stock['state'] ); ?>">
					<span class="ty-card__stockdot" aria-hidden="true"></span>
					<span><?php echo esc_html( $stock['text'] ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="ty-card__actions">
		<?php if ( $is_out ) : ?>
			<a href="<?php echo esc_url( $link ); ?>" class="<?php echo esc_attr( implode( ' ', $btn_classes ) ); ?>">
				<span><?php echo esc_html( $btn_text ); ?></span>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
				class="<?php echo esc_attr( implode( ' ', $btn_classes ) ); ?>"
				data-quantity="1"
				data-product_id="<?php echo esc_attr( $prod_id ); ?>"
				data-product_sku="<?php echo esc_attr( $sku ); ?>"
				aria-label="<?php echo esc_attr( $product->add_to_cart_description() ); ?>"
				rel="nofollow">
				<?php typee_the_icon( 'add_shopping_cart', 16 ); ?>
				<span><?php echo esc_html( $btn_text ); ?></span>
			</a>
		<?php endif; ?>
		<button type="button" class="ty-card__compare" data-typee-compare="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="So sánh sản phẩm">
			<?php typee_the_icon( 'compare_arrows', 16 ); ?>
		</button>
	</div>
</article>
