<?php
/**
 * Màn 02–03: Trang Danh Mục Sản Phẩm / Cửa Hàng + Bộ lọc Off-Canvas (Figma 46:1482).
 * Giữ nguyên các hook chuẩn của WooCommerce (before/after main content, before/after shop loop).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$term     = get_queried_object();
$is_cat   = $term instanceof WP_Term && 'product_cat' === $term->taxonomy;
$tt       = typee_current_cat_ttid();
$facets   = typee_facet_counts( $tt );
$groups   = typee_filter_groups();
$active   = typee_active_filters();
$sel_cats = typee_active_cats();
$all_cats = typee_catalog_categories();
$base_url = typee_catalog_base_url();

global $wp_query;
if ( function_exists( 'wc_setup_loop' ) ) {
	wc_setup_loop();
}
$total    = (int) $wp_query->found_posts;
$paged    = max( 1, (int) get_query_var( 'paged' ) );
$per_page = (int) apply_filters( 'loop_shop_per_page', 16 );
$title    = $is_cat ? single_term_title( '', false ) : ( is_search() ? 'Kết quả tìm kiếm' : 'Tất cả sản phẩm' );
$desc     = $is_cat && $term->description ? wp_strip_all_tags( $term->description ) : 'Tuyển chọn các mẫu Kit phím cơ nhôm CNC 6063 nguyên khối, bàn phím cơ nguyên chiếc, switch và keycap chính hãng. Lọc trực tiếp theo thông số kỹ thuật thật từ kho hàng TYPEE.';
$bounds   = typee_price_bounds( $tt );
$min_p    = isset( $_GET['min_price'] ) && '' !== $_GET['min_price'] ? (float) $_GET['min_price'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
$max_p    = isset( $_GET['max_price'] ) && '' !== $_GET['max_price'] ? (float) $_GET['max_price'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
$orderby  = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification

$chips_data = typee_render_active_chips( $_GET, $base_url ); // phpcs:ignore WordPress.Security.NonceVerification
$n_act      = (int) $chips_data['count'];

// Quick Tag Pills: nếu danh mục có Layout thì ưu tiên Layout, nếu ở Shop tổng thì hiển thị nhanh theo Danh mục.
$quick_layouts = ! empty( $facets['layout'] ) ? array_slice( $facets['layout'], 0, 6, true ) : array();
$sort_options  = apply_filters(
	'woocommerce_catalog_orderby',
	array(
		'date'       => 'Sắp xếp: Mới nhất',
		'popularity' => 'Sắp xếp: Phổ biến nhất',
		'price'      => 'Sắp xếp: Giá thấp → cao',
		'price-desc' => 'Sắp xếp: Giá cao → thấp',
		'menu_order' => 'Sắp xếp: Mặc định',
	)
);

do_action( 'woocommerce_before_main_content' );
?>
<div
	class="ty-catalog"
	data-catalog-root
	data-node="46:1482"
	data-tt="<?php echo (int) $tt; ?>"
	data-base-url="<?php echo esc_url( $base_url ); ?>"
>
	<!-- 1. BREADCRUMB & ARCHIVE HERO SECTION (Figma 46:1485) -->
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
				<div class="ty-cathero__head">
					<h1 class="ty-cathero__title">
						<span><?php echo esc_html( $title ); ?></span>
						<span class="ty-cathero__count" data-catalog-hero-count><?php echo esc_html( sprintf( '%d sản phẩm', $total ) ); ?></span>
					</h1>
					<p class="ty-cathero__desc"><?php echo esc_html( $desc ); ?></p>
				</div>
				<span class="ty-cathero__ready">
					<span class="ty-dot" aria-hidden="true"></span>
					<span>Kho Hà Nội &amp; TP.HCM sẵn sàng giao 2H</span>
				</span>
			</div>

			<?php if ( ! $is_cat && $all_cats ) : ?>
				<div class="ty-pills ty-cathero__quick" aria-label="Lọc nhanh theo danh mục">
					<a class="ty-pill<?php echo empty( $sel_cats ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( typee_filter_url( array( 'cat' => null ) ) ); ?>" data-catalog-link>
						Tất cả <small>(<?php echo (int) $total; ?>)</small>
					</a>
					<?php foreach ( array_slice( $all_cats, 0, 6 ) as $cat_term ) : ?>
						<?php $on = array( $cat_term->slug ) === $sel_cats; ?>
						<a class="ty-pill<?php echo $on ? ' is-active' : ''; ?>" href="<?php echo esc_url( typee_filter_url( array( 'cat' => $on ? null : array( $cat_term->slug ) ) ) ); ?>" data-catalog-link>
							<?php echo esc_html( $cat_term->name ); ?> <small>(<?php echo (int) $cat_term->count; ?>)</small>
						</a>
					<?php endforeach; ?>
				</div>
			<?php elseif ( $quick_layouts ) : ?>
				<div class="ty-pills ty-cathero__quick" aria-label="Lọc nhanh theo layout">
					<a class="ty-pill<?php echo empty( $active['layout'] ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( typee_filter_url( array( 'layout' => null ) ) ); ?>" data-catalog-link>
						Tất cả
					</a>
					<?php foreach ( $quick_layouts as $val => $info ) : ?>
						<?php $on = ! empty( $active['layout'] ) && array( (string) $val ) === $active['layout']; ?>
						<a class="ty-pill<?php echo $on ? ' is-active' : ''; ?>" href="<?php echo esc_url( typee_filter_url( array( 'layout' => $on ? null : array( $val ) ) ) ); ?>" data-catalog-link>
							Layout <?php echo esc_html( $info['label'] ); ?> <small>(<?php echo (int) $info['count']; ?>)</small>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- 2. TOOLBAR & ACTIVE FILTERS STRIP (Figma 46:1526) -->
	<div class="ty-cattools">
		<div class="ty-container ty-cattools__inner">
			<div class="ty-cattools__left">
				<button type="button" class="ty-btn ty-btn--dark ty-cattools__filter" data-typee-open="#ty-filter-drawer" aria-controls="ty-filter-drawer">
					<?php typee_the_icon( 'tune', 16 ); ?>
					<span>Bộ lọc chuyên sâu</span>
					<span class="ty-cattools__n" data-catalog-active-count<?php echo $n_act ? '' : ' hidden'; ?>><?php echo (int) $n_act; ?></span>
				</button>
				<div class="ty-cattools__chips" data-catalog-chips>
					<?php echo $chips_data['html']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>

			<div class="ty-cattools__right">
				<p class="ty-cattools__count" data-catalog-count>
					<?php echo wp_kses_post( typee_render_result_count( $total, $paged, $per_page ) ); ?>
				</p>

				<form class="ty-catsort" method="get" action="<?php echo esc_url( $base_url ); ?>">
					<label class="ty-sr" for="ty-catalog-sort">Sắp xếp sản phẩm</label>
					<span class="ty-catsort__ic" aria-hidden="true"><?php typee_the_icon( 'sort', 15 ); ?></span>
					<select id="ty-catalog-sort" name="orderby" class="ty-catsort__select" data-catalog-sort>
						<?php foreach ( $sort_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $orderby, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</form>

				<div class="ty-viewswitch" role="group" aria-label="Chế độ hiển thị lưới">
					<button type="button" class="ty-viewswitch__btn is-active" data-view-mode="grid" aria-pressed="true" aria-label="Hiển thị dạng lưới">
						<?php typee_the_icon( 'grid_view', 16 ); ?>
					</button>
					<button type="button" class="ty-viewswitch__btn" data-view-mode="list" aria-pressed="false" aria-label="Hiển thị dạng danh sách">
						<?php typee_the_icon( 'view_list', 16 ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- 3. MAIN CATALOG CONTENT AREA (Figma 46:1574) -->
	<div class="ty-container ty-catalog__body" data-catalog-body>
		<!-- Loading Skeleton (hiện khi lọc/phân trang AJAX) -->
		<div class="ty-grid-4 ty-catalog__skeleton" data-catalog-skeleton aria-hidden="true" hidden>
			<?php for ( $s = 0; $s < 8; $s++ ) : ?>
				<div class="ty-sk-card">
					<div class="ty-sk-media"></div>
					<div class="ty-sk-line is-sm"></div>
					<div class="ty-sk-line is-lg"></div>
					<div class="ty-sk-line is-md"></div>
					<div class="ty-sk-btn"></div>
				</div>
			<?php endfor; ?>
		</div>

		<div class="ty-catalog__results" data-catalog-results>
			<?php if ( woocommerce_product_loop() ) : ?>
				<?php do_action( 'woocommerce_before_shop_loop' ); ?>
				<div class="ty-grid-4 ty-catalog__grid" data-catalog-grid>
					<?php
					while ( have_posts() ) {
						the_post();
						do_action( 'woocommerce_shop_loop' );
						wc_get_template_part( 'content', 'product' );
					}
					?>
				</div>
				<?php do_action( 'woocommerce_after_shop_loop' ); ?>
			<?php else : ?>
				<div class="ty-panel ty-empty">
					<img
						class="ty-empty__mascot"
						src="<?php echo esc_url( TYPEE_CHILD_URI . '/assets/images/logo-mascot.png' ); ?>"
						alt="TYPEE Mascot"
						width="84"
						height="96"
						loading="lazy"
						decoding="async"
					/>
					<h2 class="ty-empty__title">Không tìm thấy sản phẩm phù hợp bộ lọc</h2>
					<p class="ty-empty__desc">Thử bỏ bớt điều kiện lọc, nới rộng khoảng giá hoặc xem toàn bộ cửa hàng.</p>
					<div class="ty-empty__actions">
						<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $base_url ); ?>" data-catalog-link>Xóa tất cả bộ lọc</a>
						<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">Mở Custom Builder</a>
					</div>
				</div>
				<?php do_action( 'woocommerce_no_products_found' ); ?>
			<?php endif; ?>
		</div>

		<div class="ty-catalog__pagin" data-catalog-pagination>
			<?php echo typee_render_pagination( (int) $wp_query->max_num_pages, $paged, $base_url, $_GET ); // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.Security.NonceVerification ?>
		</div>
	</div>

	<!-- 4. CAM KẾT DỊCH VỤ (Figma 46:2024) -->
	<section class="ty-container ty-promises" aria-label="Cam kết dịch vụ TYPEE">
		<?php
		$promises = array(
			array( 'handyman', 'Dịch vụ Modding & Lube Studio', 'Hỗ trợ cân wire stab, lube switch, tape mod chuyên nghiệp tại phòng kỹ thuật TYPEE.' ),
			array( 'verified_user', 'Bảo hành chính hãng 12 tháng', 'Cam kết bảo hành 12 tháng với Kit CNC & mạch PCB; đổi mới trong 7 ngày nếu có lỗi từ nhà sản xuất.' ),
			array( 'bolt', 'Giao hỏa tốc 2H nội thành', 'Kho hàng trực tiếp tại Hà Nội & TP.HCM, đóng gói chống sốc bóng khí 3 lớp.' ),
			array( 'tune', 'Custom Builder trực quan', 'Tự do phối ghép Kit, Switch, Plate, Foam và Keycap với kiểm tra tương thích tức thì.' ),
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

	<!-- 5. OFF-CANVAS FILTER DRAWER (Figma 46:2246) -->
	<aside class="ty-drawer ty-drawer--left ty-filter" id="ty-filter-drawer" role="dialog" aria-modal="true" aria-labelledby="ty-filter-title" aria-hidden="true" data-tt="<?php echo (int) $tt; ?>">
		<form class="ty-filter__form" method="get" action="<?php echo esc_url( $base_url ); ?>" data-typee-filter>
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" data-filter-orderby />

			<div class="ty-drawer__head ty-filter__head">
				<div class="ty-filter__head-title">
					<span class="ty-filter__head-ic" aria-hidden="true"><?php typee_the_icon( 'tune', 18 ); ?></span>
					<div>
						<h2 class="ty-drawer__title" id="ty-filter-title">Bộ Lọc Chuyên Sâu</h2>
						<span class="ty-filter__sub">TYPEE EAV Filter Engine</span>
					</div>
				</div>
				<div class="ty-filter__head-actions">
					<button type="button" class="ty-filter__reset" data-filter-reset>Đặt lại</button>
					<button type="button" class="ty-filter__close" data-typee-close aria-label="Đóng bộ lọc"><?php typee_the_icon( 'close', 18 ); ?></button>
				</div>
			</div>

			<div class="ty-drawer__body ty-filter__body">
				<!-- Group 1: Tình trạng kho hàng -->
				<fieldset class="ty-fgroup">
					<legend>TÌNH TRẠNG KHO HÀNG</legend>
					<label class="ty-fstock">
						<span>Chỉ hiện hàng có sẵn (Giao 2H)</span>
						<input type="checkbox" name="instock" value="1" <?php checked( ! empty( $_GET['instock'] ) ); // phpcs:ignore WordPress.Security.NonceVerification ?> />
					</label>
				</fieldset>

				<!-- Group 2: Khoảng giá + Dual Range Slider (Figma 46:2270) -->
				<?php
				$b_min = (int) floor( $bounds['min'] / 10000 ) * 10000;
				$b_max = max( $b_min + 100000, (int) ceil( $bounds['max'] / 10000 ) * 10000 );
				$v_min = '' !== $min_p ? (int) $min_p : $b_min;
				$v_max = '' !== $max_p ? (int) $max_p : $b_max;
				?>
				<fieldset class="ty-fgroup" data-price-bounds data-min="<?php echo (int) $b_min; ?>" data-max="<?php echo (int) $b_max; ?>">
					<legend>KHOẢNG GIÁ (VNĐ)</legend>
					<div class="ty-fprice">
						<label class="ty-fprice__box">
							<span>Tối thiểu</span>
							<input class="ty-input" type="number" inputmode="numeric" min="0" step="50000" name="min_price" value="<?php echo esc_attr( $min_p ); ?>" placeholder="<?php echo esc_attr( $b_min ); ?>" data-price-input="min" />
						</label>
						<label class="ty-fprice__box">
							<span>Tối đa</span>
							<input class="ty-input" type="number" inputmode="numeric" min="0" step="50000" name="max_price" value="<?php echo esc_attr( $max_p ); ?>" placeholder="<?php echo esc_attr( $b_max ); ?>" data-price-input="max" />
						</label>
					</div>
					<div class="ty-fslider" data-price-slider>
						<div class="ty-fslider__track">
							<div class="ty-fslider__fill" data-price-fill></div>
						</div>
						<input type="range" class="ty-fslider__range" min="<?php echo (int) $b_min; ?>" max="<?php echo (int) $b_max; ?>" step="50000" value="<?php echo (int) max( $b_min, min( $v_min, $b_max ) ); ?>" aria-label="Giá tối thiểu" data-price-range="min" />
						<input type="range" class="ty-fslider__range" min="<?php echo (int) $b_min; ?>" max="<?php echo (int) $b_max; ?>" step="50000" value="<?php echo (int) max( $b_min, min( $v_max, $b_max ) ); ?>" aria-label="Giá tối đa" data-price-range="max" />
					</div>
				</fieldset>

				<!-- Group 3: Danh mục sản phẩm (chỉ hiện ở trang Cửa hàng tổng) -->
				<?php if ( ! $is_cat && $all_cats ) : ?>
					<fieldset class="ty-fgroup">
						<legend>DANH MỤC SẢN PHẨM</legend>
						<div class="ty-flist">
							<?php foreach ( $all_cats as $cat_term ) : ?>
								<?php $checked = in_array( $cat_term->slug, $sel_cats, true ); ?>
								<label class="ty-check ty-fopt<?php echo $checked ? ' is-checked' : ''; ?>">
									<input type="checkbox" name="cat[]" value="<?php echo esc_attr( $cat_term->slug ); ?>" <?php checked( $checked ); ?> />
									<span class="ty-fopt__label"><?php echo esc_html( $cat_term->name ); ?></span>
									<span class="ty-fopt__count">(<?php echo (int) $cat_term->count; ?>)</span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endif; ?>

				<!-- Group 4+: Thuộc tính kỹ thuật EAV & WooCommerce Attributes (pa_*) -->
				<?php foreach ( $groups as $key => $g ) : ?>
					<?php if ( empty( $facets[ $key ] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<fieldset class="ty-fgroup">
						<legend><?php echo esc_html( mb_strtoupper( $g[1], 'UTF-8' ) ); ?></legend>
						<div class="ty-flist">
							<?php foreach ( array_slice( $facets[ $key ], 0, 12, true ) as $val => $info ) : ?>
								<?php $checked = ! empty( $active[ $key ] ) && in_array( (string) $val, $active[ $key ], true ); ?>
								<label class="ty-check ty-fopt<?php echo $checked ? ' is-checked' : ''; ?>">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $checked ); ?> />
									<span class="ty-fopt__label"><?php echo esc_html( $info['label'] ); ?></span>
									<span class="ty-fopt__count">(<?php echo (int) $info['count']; ?>)</span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
			</div>

			<div class="ty-drawer__foot">
				<button type="submit" class="ty-btn ty-btn--primary ty-filter__apply" data-filter-apply>
					<?php typee_the_icon( 'check', 16 ); ?>
					<span data-filter-label><?php echo esc_html( $n_act ? sprintf( 'Áp dụng %1$d bộ lọc (%2$d kết quả)', $n_act, $total ) : sprintf( 'Xem tất cả (%d kết quả)', $total ) ); ?></span>
				</button>
			</div>
		</form>
	</aside>
</div>
<?php
do_action( 'woocommerce_after_main_content' );
get_footer( 'shop' );
