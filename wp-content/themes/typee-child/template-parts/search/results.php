<?php
/**
 * Partial dùng chung cho Trang Kết Quả Tìm Kiếm – Figma 82:1823.
 * Dùng cho cả `/search/?q=<keyword>` (`templates/search.php`) và `/?s=<keyword>&post_type=product` (`search.php`).
 * Toàn bộ class đều mang tiền tố `.typee-search-*` để không ảnh hưởng các trang khác.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$keyword     = typee_search_get_keyword();
$active_cat  = isset( $_GET['cat'] ) ? sanitize_title( wp_unslash( $_GET['cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$price_range = isset( $_GET['price_range'] ) ? sanitize_text_field( wp_unslash( $_GET['price_range'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$instock     = ! empty( $_GET['instock'] ); // phpcs:ignore WordPress.Security.NonceVerification
$orderby     = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'relevance'; // phpcs:ignore WordPress.Security.NonceVerification
$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : max( 1, (int) get_query_var( 'paged' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$per_page    = 12;

$search_base = typee_url( 'search' );
$popular_kws = typee_search_popular_keywords();

$result = typee_search_run_query(
	$keyword,
	array(
		'cat'         => $active_cat,
		'price_range' => $price_range,
		'instock'     => $instock,
		'orderby'     => $orderby,
		'paged'       => $paged,
		'per_page'    => $per_page,
	)
);

/** @var WP_Query $query */
$query         = $result['query'];
$total_all     = count( $result['all_ids'] );
$total_shown   = (int) $query->found_posts;
$cat_counts    = $result['cat_counts'];
$instock_count = (int) $result['instock_count'];

// Helper tạo URL giữ nguyên từ khóa và các bộ lọc tìm kiếm.
$build_url = function ( $overrides = array() ) use ( $search_base, $keyword, $active_cat, $price_range, $instock, $orderby ) {
	$params = array(
		'q'           => $keyword,
		'cat'         => $active_cat,
		'price_range' => $price_range,
		'instock'     => $instock ? '1' : '',
		'orderby'     => 'relevance' !== $orderby ? $orderby : '',
	);
	foreach ( $overrides as $k => $v ) {
		$params[ $k ] = $v;
	}
	$clean = array();
	foreach ( $params as $k => $v ) {
		if ( '' !== $v && null !== $v ) {
			$clean[ $k ] = $v;
		}
	}
	return add_query_arg( $clean, $search_base );
};

$from_idx = $total_shown > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
$to_idx   = min( $total_shown, $paged * $per_page );
?>
<div class="typee-search-page" data-typee-search-page data-node="82:1823">
	<div class="ty-container typee-search-wrap">

		<!-- 1. BREADCRUMB (Figma 82:1843) -->
		<nav class="typee-search-bc" aria-label="Breadcrumb">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang chủ</a></li>
				<li class="typee-search-bc__sep" aria-hidden="true"><?php typee_the_icon( 'chevron_right', 14 ); ?></li>
				<li><a href="<?php echo esc_url( $search_base ); ?>">Tìm kiếm</a></li>
				<?php if ( '' !== $keyword ) : ?>
					<li class="typee-search-bc__sep" aria-hidden="true"><?php typee_the_icon( 'chevron_right', 14 ); ?></li>
					<li aria-current="page">&ldquo;<?php echo esc_html( $keyword ); ?>&rdquo;</li>
				<?php endif; ?>
			</ol>
		</nav>

		<!-- 2. SEARCH HEADER & META STATS (Figma 82:1854) -->
		<header class="typee-search-header">
			<div class="typee-search-header__left">
				<div class="typee-search-badges">
					<span class="typee-search-badge typee-search-badge--brand">TYPEE Catalog Search</span>
					<?php if ( '' !== $keyword ) : ?>
						<span class="typee-search-badge typee-search-badge--count">
							<?php echo esc_html( sprintf( 'Tìm thấy %d sản phẩm phù hợp', $total_shown ) ); ?>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( '' !== $keyword ) : ?>
					<h1 class="typee-search-title">
						Kết quả tìm kiếm cho: <span class="typee-search-title__kw">&ldquo;<?php echo esc_html( $keyword ); ?>&rdquo;</span>
					</h1>
					<p class="typee-search-subtitle">
						Tìm kiếm trực tiếp trên toàn bộ kho bàn phím cơ, khung Kit CNC, Switch, Keycap và mã SKU của TYPEE.
					</p>
				<?php else : ?>
					<h1 class="typee-search-title">Tìm kiếm linh kiện &amp; bàn phím cơ</h1>
					<p class="typee-search-subtitle">
						Nhập tên sản phẩm, mã SKU (ví dụ <strong>KEY-085</strong>, <strong>PRE-214</strong>), loại switch hoặc profile keycap để tra cứu nhanh.
					</p>
				<?php endif; ?>
			</div>

			<div class="typee-search-related">
				<span class="typee-search-related__label">TỪ KHÓA PHỔ BIẾN:</span>
				<div class="typee-search-related__tags">
					<?php foreach ( $popular_kws as $kw ) : ?>
						<a class="typee-search-tag" href="<?php echo esc_url( add_query_arg( 'q', rawurlencode( $kw ), $search_base ) ); ?>">
							#<?php echo esc_html( str_replace( ' ', '', $kw ) ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</header>

		<?php if ( '' !== $keyword && $total_all > 0 ) : ?>
			<!-- 3. FILTER & SORT TOOLBAR (Figma 82:1879) -->
			<section class="typee-search-toolbar" aria-label="Bộ lọc kết quả tìm kiếm">
				<div class="typee-search-cats" role="navigation" aria-label="Lọc theo danh mục">
					<a class="typee-search-catpill<?php echo '' === $active_cat ? ' is-active' : ''; ?>" href="<?php echo esc_url( $build_url( array( 'cat' => '' ) ) ); ?>">
						Tất cả (<?php echo (int) $total_all; ?>)
					</a>
					<?php foreach ( $cat_counts as $slug => $cinfo ) : ?>
						<a class="typee-search-catpill<?php echo $slug === $active_cat ? ' is-active' : ''; ?>" href="<?php echo esc_url( $build_url( array( 'cat' => $slug ) ) ); ?>">
							<?php echo esc_html( $cinfo['name'] ); ?> (<?php echo (int) $cinfo['count']; ?>)
						</a>
					<?php endforeach; ?>
				</div>

				<div class="typee-search-controls">
					<form class="typee-search-qfilters" method="get" action="<?php echo esc_url( $search_base ); ?>" data-typee-search-filterform>
						<input type="hidden" name="q" value="<?php echo esc_attr( $keyword ); ?>" />
						<?php if ( $active_cat ) : ?>
							<input type="hidden" name="cat" value="<?php echo esc_attr( $active_cat ); ?>" />
						<?php endif; ?>
						<?php if ( 'relevance' !== $orderby ) : ?>
							<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
						<?php endif; ?>

						<select name="price_range" class="typee-search-select" aria-label="Khoảng giá" data-typee-search-autosubmit>
							<option value="" <?php selected( $price_range, '' ); ?>>Khoảng giá: Tất cả</option>
							<option value="under-500k" <?php selected( $price_range, 'under-500k' ); ?>>Dưới 500.000₫</option>
							<option value="500k-1m5" <?php selected( $price_range, '500k-1m5' ); ?>>500.000₫ – 1.500.000₫</option>
							<option value="1m5-3m" <?php selected( $price_range, '1m5-3m' ); ?>>1.500.000₫ – 3.000.000₫</option>
							<option value="over-3m" <?php selected( $price_range, 'over-3m' ); ?>>Trên 3.000.000₫</option>
						</select>

						<a class="typee-search-toggle<?php echo $instock ? ' is-active' : ''; ?>" href="<?php echo esc_url( $build_url( array( 'instock' => $instock ? '' : '1' ) ) ); ?>">
							<?php typee_the_icon( 'check_circle', 15 ); ?>
							<span>Sẵn hàng Lab (<?php echo (int) $instock_count; ?>)</span>
						</a>

						<?php if ( $active_cat || $price_range || $instock ) : ?>
							<a class="typee-search-clear" href="<?php echo esc_url( add_query_arg( 'q', rawurlencode( $keyword ), $search_base ) ); ?>">Xóa lọc</a>
						<?php endif; ?>
					</form>

					<div class="typee-search-sortwrap">
						<form method="get" action="<?php echo esc_url( $search_base ); ?>" class="typee-search-sortform">
							<input type="hidden" name="q" value="<?php echo esc_attr( $keyword ); ?>" />
							<?php if ( $active_cat ) : ?>
								<input type="hidden" name="cat" value="<?php echo esc_attr( $active_cat ); ?>" />
							<?php endif; ?>
							<?php if ( $price_range ) : ?>
								<input type="hidden" name="price_range" value="<?php echo esc_attr( $price_range ); ?>" />
							<?php endif; ?>
							<?php if ( $instock ) : ?>
								<input type="hidden" name="instock" value="1" />
							<?php endif; ?>
							<label for="typee-search-orderby" class="typee-search-sortlabel">Sắp xếp:</label>
							<select id="typee-search-orderby" name="orderby" class="typee-search-select" data-typee-search-autosubmit>
								<option value="relevance" <?php selected( $orderby, 'relevance' ); ?>>Phù hợp nhất</option>
								<option value="date" <?php selected( $orderby, 'date' ); ?>>Mới nhất</option>
								<option value="popularity" <?php selected( $orderby, 'popularity' ); ?>>Bán chạy nhất</option>
								<option value="price" <?php selected( $orderby, 'price' ); ?>>Giá thấp → cao</option>
								<option value="price-desc" <?php selected( $orderby, 'price-desc' ); ?>>Giá cao → thấp</option>
							</select>
						</form>

						<div class="typee-search-view" role="group" aria-label="Chế độ hiển thị">
							<button type="button" class="typee-search-view__btn is-active" data-search-view="grid" aria-pressed="true" aria-label="Dạng lưới">
								<?php typee_the_icon( 'grid_view', 16 ); ?>
							</button>
							<button type="button" class="typee-search-view__btn" data-search-view="list" aria-pressed="false" aria-label="Dạng danh sách">
								<?php typee_the_icon( 'view_list', 16 ); ?>
							</button>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- 4. RESULTS GRID OR EMPTY STATE -->
		<?php if ( '' !== $keyword && $query->have_posts() ) : ?>
			<div class="ty-grid-4 typee-search-grid" data-typee-search-grid>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					get_template_part( 'template-parts/product/card', null, array( 'product' => get_the_ID() ) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>

			<!-- 5. PAGINATION TOOLBAR (Figma 82:2196) -->
			<div class="typee-search-paginbar">
				<p class="typee-search-paginbar__info">
					Hiển thị <strong><?php echo (int) $from_idx; ?> - <?php echo (int) $to_idx; ?></strong> trong tổng số <strong><?php echo (int) $total_shown; ?></strong> sản phẩm
				</p>
				<?php if ( $query->max_num_pages > 1 ) : ?>
					<nav class="typee-search-pagin" aria-label="Phân trang kết quả tìm kiếm">
						<?php
						$pagin_args = array(
							'q'           => $keyword,
							'cat'         => $active_cat ? $active_cat : null,
							'price_range' => $price_range ? $price_range : null,
							'instock'     => $instock ? '1' : null,
							'orderby'     => 'relevance' !== $orderby ? $orderby : null,
						);
						echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
							array(
								'base'      => $search_base . '%_%',
								'format'    => '?paged=%#%',
								'add_args'  => array_filter( $pagin_args, fn( $v ) => null !== $v && '' !== $v ),
								'current'   => $paged,
								'total'     => (int) $query->max_num_pages,
								'prev_text' => '← Trước',
								'next_text' => 'Sau →',
								'type'      => 'list',
							)
						);
						?>
					</nav>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<!-- EMPTY SEARCH STATE -->
			<section class="ty-panel typee-search-empty">
				<img
					class="ty-empty__mascot"
					src="<?php echo esc_url( TYPEE_CHILD_URI . '/assets/images/logo-mascot.png' ); ?>"
					alt="TYPEE Mascot"
					width="88"
					height="100"
					loading="lazy"
					decoding="async"
				/>
				<?php if ( '' !== $keyword ) : ?>
					<h2 class="typee-search-empty__title">Không tìm thấy sản phẩm khớp với &ldquo;<?php echo esc_html( $keyword ); ?>&rdquo;</h2>
					<p class="typee-search-empty__desc">
						Hãy kiểm tra lại chính tả, thử từ khóa ngắn gọn hơn (ví dụ: <strong>AKKO</strong>, <strong>Keycap</strong>, <strong>Switch</strong>) hoặc khám phá toàn bộ cửa hàng.
					</p>
				<?php else : ?>
					<h2 class="typee-search-empty__title">Bạn đang tìm kiếm bàn phím hay linh kiện nào?</h2>
					<p class="typee-search-empty__desc">
						Nhập tên sản phẩm hoặc mã SKU vào ô bên dưới để tìm nhanh trong hơn 500 sản phẩm có sẵn tại TYPEE.
					</p>
				<?php endif; ?>

				<form class="typee-search-retry" method="get" action="<?php echo esc_url( $search_base ); ?>">
					<label class="ty-sr" for="typee-search-retry-input">Tìm kiếm lại</label>
					<input id="typee-search-retry-input" type="search" name="q" value="<?php echo esc_attr( $keyword ); ?>" placeholder="Nhập tên Kit, Switch, Keycap hoặc mã SKU..." class="ty-input" />
					<button type="submit" class="ty-btn ty-btn--primary">Tìm kiếm</button>
				</form>

				<div class="typee-search-empty__actions">
					<?php if ( $active_cat || $price_range || $instock ) : ?>
						<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( add_query_arg( 'q', rawurlencode( $keyword ), $search_base ) ); ?>">Bỏ bộ lọc phụ</a>
					<?php endif; ?>
					<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Xem toàn bộ cửa hàng</a>
					<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">Mở Custom Builder</a>
				</div>
			</section>
		<?php endif; ?>

		<!-- 6. SUPPORT CALLOUT STRIP (Figma 82:2219) -->
		<section class="typee-search-callout">
			<div class="typee-search-callout__left">
				<span class="typee-search-callout__ic"><?php typee_the_icon( 'support_agent', 24 ); ?></span>
				<div>
					<h3><?php echo '' !== $keyword ? esc_html( sprintf( 'Chưa tìm thấy linh kiện phù hợp cho "%s"?', $keyword ) ) : 'Cần tư vấn chọn Kit, Switch & Keycap tương thích?'; ?></h3>
					<p>Hãy thử công cụ Custom Keyboard Builder 6 bước hoặc liên hệ trực tiếp kỹ thuật viên TYPEE Lab qua Hotline <?php echo esc_html( typee_copy( 'hotline' ) ); ?>.</p>
				</div>
			</div>
			<div class="typee-search-callout__actions">
				<a class="ty-btn ty-btn--dark" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">Mở Custom Builder</a>
				<a class="ty-btn ty-btn--primary" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', typee_copy( 'hotline' ) ) ); ?>">
					<?php typee_the_icon( 'call', 15 ); ?>
					<span>Gọi TYPEE Lab</span>
				</a>
			</div>
		</section>

	</div>
</div>
