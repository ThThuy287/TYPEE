<?php
/**
 * Màn 02 – Danh mục Kit & Bàn phím cơ (Figma 1:1600) + Màn 03 – Bộ lọc Off-Canvas (Figma 1:3768).
 * Giữ nguyên các hook chuẩn của WooCommerce (before/after main content, before/after shop loop).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$term    = get_queried_object();
$is_cat  = $term instanceof WP_Term;
$tt      = typee_current_cat_ttid();
$facets  = typee_facet_counts( $tt );
$groups  = typee_filter_groups();
$active  = typee_active_filters();
$n_act   = array_sum( array_map( 'count', $active ) ) + ( empty( $_GET['instock'] ) ? 0 : 1 ) + ( ( empty( $_GET['min_price'] ) && empty( $_GET['max_price'] ) ) ? 0 : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
global $wp_query;
if ( function_exists( 'wc_setup_loop' ) ) {
	wc_setup_loop();
}
$total   = (int) $wp_query->found_posts;
$title   = $is_cat ? single_term_title( '', false ) : ( is_search() ? 'Kết quả tìm kiếm' : 'Tất cả sản phẩm' );
$desc    = $is_cat && $term->description ? wp_strip_all_tags( $term->description ) : 'Tuyển chọn Kit nhôm CNC, bàn phím nguyên chiếc, switch và keycap chính hãng. Lọc theo thông số kỹ thuật thật để chọn đúng linh kiện tương thích.';
$quick   = isset( $facets['layout'] ) ? array_slice( $facets['layout'], 0, 6, true ) : array();
$bounds  = typee_price_bounds( $tt );
$min_p   = isset( $_GET['min_price'] ) ? (float) $_GET['min_price'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
$max_p   = isset( $_GET['max_price'] ) ? (float) $_GET['max_price'] : ''; // phpcs:ignore WordPress.Security.NonceVerification

do_action( 'woocommerce_before_main_content' );
?>
<div class="ty-catalog">
	<!-- Hero danh mục (Figma 1:1603) -->
	<section class="ty-cathero">
		<div class="ty-container">
			<?php
			$crumbs = array( array( 'Cửa hàng', typee_url( 'catalog' ) ) );
			if ( $is_cat ) {
				$crumbs[] = array( $title, '' );
			}
			typee_breadcrumb( $crumbs );
			?>
			<div class="ty-cathero__row">
				<div>
					<h1 class="ty-cathero__title"><?php echo esc_html( $title ); ?> <span class="ty-badge ty-cathero__count"><?php echo esc_html( sprintf( '%d sản phẩm', $total ) ); ?></span></h1>
					<p class="ty-cathero__desc"><?php echo esc_html( $desc ); ?></p>
				</div>
				<span class="ty-cathero__ready"><span class="ty-dot"></span> Kho Hà Nội &amp; TP.HCM sẵn sàng giao 2H</span>
			</div>
			<?php if ( $quick ) : ?>
				<div class="ty-pills ty-cathero__quick" aria-label="Lọc nhanh theo layout">
					<a class="ty-pill<?php echo empty( $active['layout'] ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( typee_filter_url( array( 'layout' => null ) ) ); ?>">Tất cả</a>
					<?php foreach ( $quick as $val => $c ) : ?>
						<?php $on = ! empty( $active['layout'] ) && array( $val ) === $active['layout']; ?>
						<a class="ty-pill<?php echo $on ? ' is-active' : ''; ?>" href="<?php echo esc_url( typee_filter_url( array( 'layout' => $on ? null : array( $val ) ) ) ); ?>">Layout <?php echo esc_html( $val ); ?> <small>(<?php echo (int) $c; ?>)</small></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- Thanh công cụ & bộ lọc đang áp dụng (Figma 1:2122) -->
	<div class="ty-cattools">
		<div class="ty-container ty-cattools__inner">
			<div class="ty-cattools__left">
				<button type="button" class="ty-btn ty-btn--dark ty-cattools__filter" data-typee-open="#ty-filter-drawer" aria-controls="ty-filter-drawer">
					<?php typee_the_icon( 'tune', 16 ); ?> Bộ lọc chuyên sâu <?php if ( $n_act ) : ?><span class="ty-cattools__n"><?php echo (int) $n_act; ?></span><?php endif; ?>
				</button>
				<?php foreach ( $active as $key => $vals ) : ?>
					<?php foreach ( $vals as $v ) : ?>
						<a class="ty-chip" href="<?php echo esc_url( typee_filter_url( array( $key => array_values( array_diff( $vals, array( $v ) ) ) ) ) ); ?>" aria-label="<?php echo esc_attr( 'Bỏ lọc ' . $groups[ $key ][1] . ': ' . $v ); ?>">
							<?php echo esc_html( $groups[ $key ][1] . ': ' ); ?><strong><?php echo esc_html( $v ); ?></strong> <?php typee_the_icon( 'close', 12 ); ?>
						</a>
					<?php endforeach; ?>
				<?php endforeach; ?>
				<?php if ( ! empty( $_GET['instock'] ) ) : // phpcs:ignore ?>
					<a class="ty-chip" href="<?php echo esc_url( typee_filter_url( array( 'instock' => null ) ) ); ?>">Còn hàng <?php typee_the_icon( 'close', 12 ); ?></a>
				<?php endif; ?>
				<?php if ( $min_p || $max_p ) : ?>
					<a class="ty-chip" href="<?php echo esc_url( typee_filter_url( array( 'min_price' => null, 'max_price' => null ) ) ); ?>">Giá: <strong><?php echo esc_html( ( $min_p ? typee_money( $min_p ) : '0' ) . ' – ' . ( $max_p ? typee_money( $max_p ) : '∞' ) ); ?></strong> <?php typee_the_icon( 'close', 12 ); ?></a>
				<?php endif; ?>
				<?php if ( $n_act ) : ?>
					<a class="ty-cattools__clear" href="<?php echo esc_url( strtok( typee_filter_url( array() ), '?' ) ); ?>">Xóa tất cả</a>
				<?php endif; ?>
			</div>
			<div class="ty-cattools__right">
				<?php woocommerce_result_count(); ?>
				<?php woocommerce_catalog_ordering(); ?>
			</div>
		</div>
	</div>

	<div class="ty-container ty-catalog__body">
		<?php if ( woocommerce_product_loop() ) : ?>
			<?php do_action( 'woocommerce_before_shop_loop' ); ?>
			<div class="ty-grid-4 ty-catalog__grid">
				<?php
				while ( have_posts() ) {
					the_post();
					do_action( 'woocommerce_shop_loop' );
					get_template_part( 'template-parts/product/card', null, array( 'product' => get_the_ID() ) );
				}
				?>
			</div>
			<?php do_action( 'woocommerce_after_shop_loop' ); ?>
		<?php else : ?>
			<div class="ty-empty">
				<span class="ty-empty__ic"><?php typee_the_icon( 'filter_alt', 28 ); ?></span>
				<h2 class="ty-empty__title">Không có sản phẩm phù hợp bộ lọc</h2>
				<p class="ty-empty__desc">Thử bỏ bớt điều kiện lọc hoặc xem toàn bộ cửa hàng.</p>
				<div class="ty-empty__actions">
					<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( strtok( typee_filter_url( array() ), '?' ) ); ?>">Xóa bộ lọc</a>
					<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">Mở Custom Builder</a>
				</div>
			</div>
			<?php do_action( 'woocommerce_no_products_found' ); ?>
		<?php endif; ?>
	</div>

	<!-- Cam kết dịch vụ (cuối Figma 1:1600) -->
	<section class="ty-container ty-promises">
		<?php
		$promises = array(
			array( 'handyman', 'Dịch vụ Modding & Lube Studio', 'Hỗ trợ cân wire stab, lube switch, tape mod chuyên nghiệp tại phòng kỹ thuật TYPEE.' ),
			array( 'verified_user', 'Bảo hành chính hãng 12 tháng', 'Cam kết bảo hành 12 tháng với Kit CNC & mạch PCB; đổi mới trong 7 ngày nếu có lỗi từ nhà sản xuất.' ),
			array( 'bolt', 'Giao hoả tốc 2H nội thành', 'Kho hàng tại Hà Nội & TP.HCM, đóng gói chống sốc 3 lớp.' ),
			array( 'tune', 'Custom Builder trực quan', 'Tự phối Kit, Switch, Keycap với kiểm tra tương thích tức thì.' ),
		);
		foreach ( $promises as $p ) :
			?>
			<div class="ty-promise">
				<span class="ty-promise__ic"><?php typee_the_icon( $p[0], 18 ); ?></span>
				<h3><?php echo esc_html( $p[1] ); ?></h3>
				<p><?php echo esc_html( $p[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</section>

	<!-- Drawer bộ lọc (Figma 1:2364 / 1:3768) -->
	<aside class="ty-drawer ty-drawer--left ty-filter" id="ty-filter-drawer" role="dialog" aria-modal="true" aria-labelledby="ty-filter-title" aria-hidden="true" data-tt="<?php echo (int) $tt; ?>">
		<form class="ty-filter__form" method="get" action="<?php echo esc_url( strtok( typee_filter_url( array() ), '?' ) ); ?>" data-typee-filter>
			<?php
			// Giữ các tham số không thuộc bộ lọc (sắp xếp, xem trước theme…).
			foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( isset( $groups[ $k ] ) || in_array( $k, array( 'instock', 'min_price', 'max_price', 'paged' ), true ) || is_array( $v ) ) {
					continue;
				}
				echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( wp_unslash( $v ) ) . '">';
			}
			?>
			<div class="ty-drawer__head ty-filter__head">
				<div>
					<h2 class="ty-drawer__title" id="ty-filter-title"><?php typee_the_icon( 'tune', 18 ); ?> Bộ lọc chuyên sâu</h2>
					<span class="ty-filter__sub">Lọc theo thông số kỹ thuật EAV</span>
				</div>
				<div class="ty-filter__head-actions">
					<button type="button" class="ty-filter__reset" data-filter-reset>Đặt lại</button>
					<button type="button" class="ty-icon-btn" data-typee-close aria-label="Đóng bộ lọc"><?php typee_the_icon( 'close', 20 ); ?></button>
				</div>
			</div>
			<div class="ty-drawer__body ty-filter__body">
				<fieldset class="ty-fgroup">
					<legend>Tình trạng kho hàng</legend>
					<label class="ty-check ty-fopt"><input type="checkbox" name="instock" value="1" <?php checked( ! empty( $_GET['instock'] ) ); // phpcs:ignore ?>> Chỉ hiện hàng có sẵn</label>
				</fieldset>
				<fieldset class="ty-fgroup">
					<legend>Khoảng giá (VNĐ)</legend>
					<div class="ty-fprice">
						<label><span>Tối thiểu</span><input class="ty-input" type="number" inputmode="numeric" min="0" step="10000" name="min_price" value="<?php echo esc_attr( $min_p ); ?>" placeholder="<?php echo esc_attr( (int) $bounds['min'] ); ?>"></label>
						<label><span>Tối đa</span><input class="ty-input" type="number" inputmode="numeric" min="0" step="10000" name="max_price" value="<?php echo esc_attr( $max_p ); ?>" placeholder="<?php echo esc_attr( (int) $bounds['max'] ); ?>"></label>
					</div>
				</fieldset>
				<?php foreach ( $groups as $key => $g ) : ?>
					<?php
					if ( empty( $facets[ $key ] ) ) {
						continue;
					}
					?>
					<fieldset class="ty-fgroup">
						<legend><?php echo esc_html( $g[1] ); ?></legend>
						<?php foreach ( array_slice( $facets[ $key ], 0, 12, true ) as $val => $c ) : ?>
							<label class="ty-check ty-fopt">
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( ! empty( $active[ $key ] ) && in_array( (string) $val, $active[ $key ], true ) ); ?>>
								<span class="ty-fopt__label"><?php echo esc_html( $val ); ?></span>
								<span class="ty-fopt__count">(<?php echo (int) $c; ?>)</span>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
			</div>
			<div class="ty-drawer__foot">
				<button type="submit" class="ty-btn ty-btn--primary ty-filter__apply" data-filter-apply>
					<?php typee_the_icon( 'check', 16 ); ?> <span data-filter-label><?php echo esc_html( $n_act ? sprintf( 'Áp dụng %1$d bộ lọc (%2$d kết quả)', $n_act, $total ) : sprintf( 'Xem tất cả (%d kết quả)', $total ) ); ?></span>
				</button>
			</div>
		</form>
	</aside>
</div>
<?php
do_action( 'woocommerce_after_main_content' );
get_footer( 'shop' );
