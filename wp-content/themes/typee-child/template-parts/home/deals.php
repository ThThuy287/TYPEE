<?php
/**
 * Flash Sale / Ưu đãi tuần – Figma 1:272.
 * - Có sản phẩm đang giảm giá (giá sale trong WooCommerce) → hiển thị đúng bản Flash Sale,
 *   đồng hồ đếm ngược theo ngày kết thúc khuyến mãi sớm nhất (nếu có đặt lịch).
 * - Chưa có sản phẩm giảm giá → hiển thị 4 sản phẩm mới của 4 danh mục, ẩn đồng hồ.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$sale_ids = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
$products = array();
$is_sale  = false;

if ( $sale_ids ) {
	// wc_get_product_ids_on_sale trả về cả ID biến thể → quy về sản phẩm cha.
	$parents = array();
	foreach ( $sale_ids as $id ) {
		$p = wc_get_product( $id );
		if ( $p ) {
			$parents[] = $p->get_parent_id() ? $p->get_parent_id() : $p->get_id();
		}
	}
	$products = wc_get_products(
		array(
			'status'  => 'publish',
			'include' => array_unique( $parents ),
			'limit'   => 4,
		)
	);
	$is_sale  = (bool) $products;
}

if ( ! $products ) {
	$products = typee_products_by_ids(
		typee_cached_ids(
			'deals_fallback',
			function () {
				$ids = array();
				foreach ( array( 'kit', 'pre-built', 'switch', 'keycap' ) as $slug ) {
					$found = wc_get_products(
						array(
							'status'   => 'publish',
							'limit'    => 1,
							'category' => array( $slug ),
							'orderby'  => 'date',
							'order'    => 'DESC',
							'return'   => 'ids',
						)
					);
					if ( $found ) {
						$ids[] = $found[0];
					}
				}
				return $ids;
			}
		)
	);
}
if ( ! $products ) {
	return;
}

// Ngày kết thúc khuyến mãi sớm nhất & tổng suất còn lại (chỉ tính sản phẩm có quản lý tồn kho).
$ends_at = 0;
$left    = 0;
if ( $is_sale ) {
	foreach ( $sale_ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$to = $p->get_date_on_sale_to();
		if ( $to && $to->getTimestamp() > time() ) {
			$ends_at = $ends_at ? min( $ends_at, $to->getTimestamp() ) : $to->getTimestamp();
		}
		if ( $p->managing_stock() ) {
			$left += max( 0, (int) $p->get_stock_quantity() );
		}
	}
}
?>
<section class="ty-deals" data-node="1:272">
	<div class="ty-container">
		<div class="ty-deals__head">
			<div class="ty-deals__title-wrap">
				<span class="ty-deals__ic"><?php typee_the_icon( 'local_fire_department', 22 ); ?></span>
				<div>
					<?php if ( $is_sale ) : ?>
						<div class="ty-deals__flags">
							<span class="ty-deals__flag">GIỚI HẠN TUẦN NÀY</span>
							<?php if ( $left ) : ?>
								<span class="ty-dot ty-dot--sm"></span>
								<span class="ty-deals__left"><?php echo esc_html( sprintf( 'Chỉ còn %d suất', $left ) ); ?></span>
							<?php endif; ?>
						</div>
						<h2 class="ty-deals__title">FLASH SALE: ƯU ĐÃI TUẦN NÀY</h2>
					<?php else : ?>
						<div class="ty-deals__flags"><span class="ty-deals__flag">MỚI VỀ KHO</span></div>
						<h2 class="ty-deals__title">GỢI Ý NỔI BẬT TUẦN NÀY</h2>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $ends_at ) : ?>
				<div class="ty-countdown" data-typee-countdown="<?php echo esc_attr( $ends_at ); ?>">
					<span class="ty-countdown__label">Kết thúc trong:</span>
					<span class="ty-countdown__box"><b data-u="d">00</b><small>NGÀY</small></span><i>:</i>
					<span class="ty-countdown__box"><b data-u="h">00</b><small>GIỜ</small></span><i>:</i>
					<span class="ty-countdown__box"><b data-u="m">00</b><small>PHÚT</small></span><i>:</i>
					<span class="ty-countdown__box is-accent"><b data-u="s">00</b><small>GIÂY</small></span>
				</div>
			<?php elseif ( typee_url( 'shop' ) ) : ?>
				<a class="ty-more" href="<?php echo esc_url( typee_url( 'shop' ) ); ?>">Xem cửa hàng <?php typee_the_icon( 'arrow_forward', 12 ); ?></a>
			<?php endif; ?>
		</div>

		<div class="ty-grid-4">
			<?php
			foreach ( $products as $p ) {
				get_template_part( 'template-parts/product/card', null, array( 'product' => $p ) );
			}
			?>
		</div>
	</div>
</section>
