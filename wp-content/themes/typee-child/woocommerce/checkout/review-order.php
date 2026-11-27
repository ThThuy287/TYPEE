<?php
/**
 * Checkout Order Review – Danh sách sản phẩm giỏ hàng thật, Voucher & Bảng tính tiền (Figma 82:1481 - 82:1581)
 *
 * Phần tử gốc giữ nguyên class `.woocommerce-checkout-review-order-table` để WooCommerce AJAX
 * (`update_order_review`) tự động làm mới mượt mà khi áp mã giảm giá hoặc đổi thông tin.
 *
 * @package typee-child
 * @version 11.2.2
 */

defined( 'ABSPATH' ) || exit;

$cart          = WC()->cart;
$item_count    = $cart ? (int) $cart->get_cart_contents_count() : 0;
$subtotal_num  = $cart ? (float) $cart->get_subtotal() : 0.0;
$discount_tot  = $cart ? (float) $cart->get_discount_total() : 0.0;
$sale_savings  = 0.0;
$fee_savings   = 0.0;

if ( $cart ) {
	foreach ( $cart->get_fees() as $fee ) {
		if ( (float) $fee->amount < 0 ) {
			$fee_savings += abs( (float) $fee->amount );
		}
	}
}
?>
<div class="shop_table woocommerce-checkout-review-order-table ty-co-review">
	<!-- 1. Micro Product Line Items (Figma 82:1481) -->
	<div class="ty-co-items">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$visible  = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

			if ( ! ( $_product instanceof WC_Product ) || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! $visible ) {
				continue;
			}

			$qty       = (int) $cart_item['quantity'];
			$parent_id = $_product->get_parent_id() ? $_product->get_parent_id() : $_product->get_id();
			$parent_p  = $_product->get_parent_id() ? wc_get_product( $parent_id ) : $_product;
			$img_id    = $_product->get_image_id() ? $_product->get_image_id() : ( $parent_p ? $parent_p->get_image_id() : 0 );
			$img_url   = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
			$title     = $parent_p ? $parent_p->get_name() : $_product->get_name();
			$line_sub  = apply_filters( 'woocommerce_cart_item_subtotal', $cart->get_product_subtotal( $_product, $qty ), $cart_item, $cart_item_key );

			// Tính khoản tiết kiệm nếu sản phẩm đang khuyến mãi (sale price < regular price).
			$reg_price = (float) $_product->get_regular_price();
			$cur_price = (float) $_product->get_price();
			if ( $reg_price > $cur_price && $cur_price > 0 ) {
				$sale_savings += ( $reg_price - $cur_price ) * $qty;
			}

			// Tạo dòng mô tả thuộc tính biến thể + thông số EAV gọn.
			$meta_parts = array();
			if ( $_product->is_type( 'variation' ) ) {
				foreach ( $_product->get_variation_attributes( false ) as $attr_name => $attr_val ) {
					if ( '' !== $attr_val ) {
						$meta_parts[] = $attr_val;
					}
				}
			}
			if ( function_exists( 'typee_product_specs' ) ) {
				foreach ( array_slice( typee_product_specs( $_product ), 0, 2 ) as $spec ) {
					if ( ! in_array( $spec['value'], $meta_parts, true ) ) {
						$meta_parts[] = $spec['value'];
					}
				}
			}
			$group_slug = function_exists( 'typee_product_group' ) ? typee_product_group( $_product ) : '';
			?>
			<article class="ty-co-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
				<div class="ty-co-item__thumb">
					<img src="<?php echo esc_url( $img_url ); ?>" alt="" width="64" height="64" loading="lazy" />
					<span class="ty-co-item__qty">x<?php echo esc_html( $qty ); ?></span>
				</div>
				<div class="ty-co-item__body">
					<div class="ty-co-item__top">
						<h3 class="ty-co-item__name"><?php echo esc_html( $title ); ?></h3>
						<span class="ty-co-item__price"><?php echo wp_kses_post( $line_sub ); ?></span>
					</div>
					<?php if ( ! empty( $meta_parts ) ) : ?>
						<p class="ty-co-item__meta"><?php echo esc_html( implode( ' • ', array_slice( $meta_parts, 0, 3 ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( 'kit' === $group_slug || 'pre-built' === $group_slug ) : ?>
						<div class="ty-co-item__tags">
							<span class="ty-co-item__tag ty-co-item__tag--accent">Bảo hành e-RMA 12T</span>
							<span class="ty-co-item__tag">KCS tiêu chuẩn Lab</span>
						</div>
					<?php endif; ?>
				</div>
			</article>
			<?php
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</div>

	<!-- 2. Coupon Input Box (Figma 82:1527) -->
	<?php if ( wc_coupons_enabled() ) : ?>
		<div class="ty-co-coupon">
			<div class="ty-co-coupon__bar">
				<div class="ty-co-coupon__input-wrap">
					<?php typee_the_icon( 'sell', 15, 'ty-co-coupon__ic' ); ?>
					<label class="ty-sr" for="ty-co-coupon-code">Nhập mã giảm giá</label>
					<input
						type="text"
						id="ty-co-coupon-code"
						class="ty-co-coupon__input"
						placeholder="Nhập mã ưu đãi (VD: TYPEENEW10)"
						autocomplete="off"
						data-ty-coupon-input
					/>
				</div>
				<button type="button" class="ty-co-coupon__btn" data-ty-coupon-apply>Áp dụng</button>
			</div>
			<p class="ty-co-coupon__msg" data-ty-coupon-msg role="status" hidden></p>

			<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
				<div class="ty-co-voucher-tag">
					<div class="ty-co-voucher-tag__left">
						<?php typee_the_icon( 'check_circle', 14 ); ?>
						<strong><?php echo esc_html( strtoupper( $code ) ); ?></strong>
						<span>• Đã áp dụng mã ưu đãi</span>
					</div>
					<button
						type="button"
						class="ty-co-voucher-tag__rm"
						data-ty-coupon-remove="<?php echo esc_attr( $code ); ?>"
						aria-label="<?php echo esc_attr( 'Gỡ mã ' . $code ); ?>"
					>
						<?php typee_the_icon( 'close', 13 ); ?>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- 3. Price Calculation Sheet (Figma 82:1546) -->
	<div class="ty-co-calc">
		<div class="ty-co-calc__row">
			<span><?php echo esc_html( sprintf( 'Tạm tính (%d sản phẩm)', $item_count ) ); ?></span>
			<strong><?php wc_cart_totals_subtotal_html(); ?></strong>
		</div>

		<?php foreach ( $cart->get_fees() as $fee ) : ?>
			<div class="ty-co-calc__row <?php echo (float) $fee->amount < 0 ? 'is-discount' : ''; ?>">
				<span><?php echo esc_html( $fee->name ); ?></span>
				<strong><?php wc_cart_totals_fee_html( $fee ); ?></strong>
			</div>
		<?php endforeach; ?>

		<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
			<div class="ty-co-calc__row is-discount">
				<span>Voucher <?php echo esc_html( strtoupper( $code ) ); ?></span>
				<strong>-<?php echo wp_kses_post( wc_price( $cart->get_coupon_discount_amount( $code, $cart->display_cart_ex_tax ) ) ); ?></strong>
			</div>
		<?php endforeach; ?>

		<div class="ty-co-calc__row">
			<span class="ty-co-calc__ship-label">
				Phí vận chuyển
				<em class="ty-co-calc__freeship">FREESHIP</em>
			</span>
			<strong class="is-emerald">0₫</strong>
		</div>

		<?php if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) : ?>
			<div class="ty-co-calc__row">
				<span><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
				<strong><?php wc_cart_totals_taxes_total_html(); ?></strong>
			</div>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<?php $total_saved = $discount_tot + $fee_savings + $sale_savings; ?>
		<div class="ty-co-grand">
			<div class="ty-co-grand__left">
				<strong>Tổng thanh toán</strong>
				<span>Đã bao gồm VAT &amp; Phí bảo hiểm hàng</span>
			</div>
			<div class="ty-co-grand__right">
				<div class="ty-co-grand__amount"><?php wc_cart_totals_order_total_html(); ?></div>
				<?php if ( $total_saved > 0 ) : ?>
					<span class="ty-co-grand__saved">Tiết kiệm <?php echo esc_html( typee_money( $total_saved ) ); ?> cho đơn này</span>
				<?php endif; ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</div>
</div>
