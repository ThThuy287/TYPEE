<?php
/**
 * Tìm Kiếm Sản Phẩm & Gợi Ý Live Search – Figma 82:1823.
 *
 * - Hỗ trợ tìm kiếm sản phẩm WooCommerce thật theo: Tên, Mô tả/Tóm tắt, Mã SKU (`_sku`), Tag & Danh mục.
 * - Tách biệt luồng `is_search()` để không ảnh hưởng trang Danh mục (`archive-product.php`) hay Header/Footer.
 * - Endpoint AJAX `typee_live_search` có xác thực Nonce (`typee_search_nonce`) cho Live Search Dropdown.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lấy từ khóa tìm kiếm sạch từ URL (`?q=` của trang ảo /search/ hoặc `?s=` chuẩn WordPress).
 */
function typee_search_get_keyword() {
	if ( isset( $_GET['q'] ) && '' !== trim( (string) $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return sanitize_text_field( wp_unslash( $_GET['q'] ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	}
	if ( isset( $_GET['s'] ) && '' !== trim( (string) $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	}
	return trim( (string) get_search_query( false ) );
}

/**
 * Tìm danh sách ID sản phẩm khớp với từ khóa (Tên, Mô tả, Tóm tắt, Mã SKU `_sku`, Tag, Danh mục).
 * Trả về mảng ID sắp xếp ưu tiên: khớp chính xác SKU / tiêu đề lên trước.
 *
 * @param string $keyword Từ khóa đã sanitize.
 * @return int[]
 */
function typee_search_find_product_ids( $keyword ) {
	$keyword = trim( (string) $keyword );
	if ( '' === $keyword ) {
		return array();
	}

	global $wpdb;
	$like = '%' . $wpdb->esc_like( $keyword ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID,
				CASE
					WHEN sku.meta_value = %s THEN 1
					WHEN p.post_title LIKE %s THEN 2
					WHEN sku.meta_value LIKE %s THEN 3
					ELSE 4
				END AS relevance
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} sku ON (sku.post_id = p.ID AND sku.meta_key = '_sku')
			LEFT JOIN {$wpdb->term_relationships} tr ON (tr.object_id = p.ID)
			LEFT JOIN {$wpdb->term_taxonomy} tt ON (tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy IN ('product_cat', 'product_tag'))
			LEFT JOIN {$wpdb->terms} t ON (t.term_id = tt.term_id)
			WHERE p.post_type = 'product'
				AND p.post_status = 'publish'
				AND (
					p.post_title LIKE %s
					OR p.post_excerpt LIKE %s
					OR p.post_content LIKE %s
					OR sku.meta_value LIKE %s
					OR t.name LIKE %s
				)
			ORDER BY relevance ASC, p.post_date DESC
			LIMIT 500",
			$keyword,
			$like,
			$like,
			$like,
			$like,
			$like,
			$like,
			$like
		)
	);

	if ( empty( $rows ) ) {
		return array();
	}

	return array_values( array_unique( array_map( 'intval', wp_list_pluck( $rows, 'ID' ) ) ) );
}

/**
 * Truy vấn WP_Query cho trang kết quả tìm kiếm kèm lọc phụ (danh mục, khoảng giá, còn hàng, sắp xếp, phân trang).
 *
 * @param string $keyword Từ khóa tìm kiếm.
 * @param array  $opts    Tuỳ chọn lọc { cat, price_range, instock, orderby, paged, per_page }.
 * @return array{query:WP_Query,all_ids:int[],cat_counts:array<string,array{name:string,count:int}>,instock_count:int}
 */
function typee_search_run_query( $keyword, $opts = array() ) {
	$all_ids  = typee_search_find_product_ids( $keyword );
	$cat      = isset( $opts['cat'] ) ? sanitize_title( $opts['cat'] ) : '';
	$prange   = isset( $opts['price_range'] ) ? sanitize_text_field( $opts['price_range'] ) : '';
	$instock  = ! empty( $opts['instock'] );
	$orderby  = isset( $opts['orderby'] ) ? sanitize_text_field( $opts['orderby'] ) : 'relevance';
	$paged    = isset( $opts['paged'] ) ? max( 1, absint( $opts['paged'] ) ) : 1;
	$per_page = isset( $opts['per_page'] ) ? max( 1, absint( $opts['per_page'] ) ) : 12;

	$cat_counts    = array();
	$instock_count = 0;

	if ( ! empty( $all_ids ) ) {
		global $wpdb;
		$ids_sql = implode( ',', array_map( 'intval', $all_ids ) );

		// Đếm số kết quả khớp theo từng danh mục `product_cat`.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$c_rows = $wpdb->get_results(
			"SELECT t.slug, t.name, COUNT(DISTINCT tr.object_id) AS cnt
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE tr.object_id IN ({$ids_sql})
			GROUP BY t.slug, t.name
			ORDER BY cnt DESC"
		);
		foreach ( $c_rows as $cr ) {
			$cat_counts[ $cr->slug ] = array(
				'name'  => $cr->name,
				'count' => (int) $cr->cnt,
			);
		}

		// Đếm số sản phẩm sẵn hàng trong tập kết quả.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$instock_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			WHERE post_id IN ({$ids_sql}) AND meta_key = '_stock_status' AND meta_value = 'instock'"
		);
	}

	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'post__in'       => ! empty( $all_ids ) ? $all_ids : array( 0 ),
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog', 'exclude-from-search' ),
				'operator' => 'NOT IN',
			),
		),
	);

	if ( $cat ) {
		$args['tax_query'][] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $cat,
		);
	}

	if ( $instock ) {
		$args['meta_query'][] = array(
			'key'   => '_stock_status',
			'value' => 'instock',
		);
	}

	$price_map = array(
		'under-500k' => array( 0, 500000 ),
		'500k-1m5'   => array( 500000, 1500000 ),
		'1m5-3m'     => array( 1500000, 3000000 ),
		'over-3m'    => array( 3000000, 999999999 ),
	);
	if ( isset( $price_map[ $prange ] ) ) {
		$args['meta_query'][] = array(
			'key'     => '_price',
			'value'   => $price_map[ $prange ],
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		);
	}

	switch ( $orderby ) {
		case 'date':
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
		case 'popularity':
			$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
			break;
		case 'price':
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'ASC', 'date' => 'DESC' );
			break;
		case 'price-desc':
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
			break;
		case 'relevance':
		default:
			$args['orderby'] = 'post__in';
			break;
	}

	$query = new WP_Query( $args );

	return array(
		'query'         => $query,
		'all_ids'       => $all_ids,
		'cat_counts'    => $cat_counts,
		'instock_count' => $instock_count,
	);
}

/**
 * Từ khóa phổ biến để hiển thị gợi ý khi tìm kiếm hoặc khi không có kết quả.
 *
 * @return string[]
 */
function typee_search_popular_keywords() {
	return apply_filters(
		'typee_search_popular_keywords',
		array( 'AKKO', 'Keycap Cherry', 'Switch Linear', 'Kit 75%', 'Gasket', 'PBT' )
	);
}

/**
 * AJAX: Gợi ý tìm kiếm nhanh (Live Search Dropdown – Figma 82:2423).
 * Giới hạn tối đa 5 sản phẩm phù hợp nhất + tổng số kết quả.
 */
function typee_ajax_live_search() {
	check_ajax_referer( 'typee_search_nonce', 'nonce' );

	$keyword = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$keyword = trim( $keyword );

	if ( mb_strlen( $keyword, 'UTF-8' ) < 1 ) {
		wp_send_json_success(
			array(
				'keyword' => '',
				'total'   => 0,
				'items'   => array(),
				'popular' => typee_search_popular_keywords(),
				'allUrl'  => typee_url( 'search' ),
			)
		);
	}

	$ids   = typee_search_find_product_ids( $keyword );
	$total = count( $ids );
	$top5  = array_slice( $ids, 0, 5 );
	$items = array();

	foreach ( $top5 as $pid ) {
		$product = wc_get_product( $pid );
		if ( ! $product || ! $product->is_visible() ) {
			continue;
		}
		$img_id  = $product->get_image_id();
		$thumb   = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
		$cats    = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) );
		$cat_lbl = ( ! is_wp_error( $cats ) && ! empty( $cats ) ) ? $cats[0] : 'Sản phẩm';
		$stock   = typee_stock_label( $product );

		$now = (float) ( $product->is_type( 'variable' ) ? $product->get_variation_price( 'min', true ) : wc_get_price_to_display( $product ) );
		$reg = 0;
		if ( $product->is_on_sale() ) {
			$reg = (float) ( $product->is_type( 'variable' ) ? $product->get_variation_regular_price( 'min', true ) : wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) );
		}

		$items[] = array(
			'id'            => $pid,
			'name'          => $product->get_name(),
			'url'           => $product->get_permalink(),
			'thumb'         => $thumb,
			'sku'           => $product->get_sku(),
			'category'      => $cat_lbl,
			'stockText'     => $stock['text'],
			'stockState'    => $stock['state'],
			'priceText'     => $now > 0 ? typee_money( $now ) : 'Liên hệ',
			'regularText'   => ( $reg > $now ) ? typee_money( $reg ) : '',
		);
	}

	$all_url = add_query_arg( 'q', rawurlencode( $keyword ), typee_url( 'search' ) );

	wp_send_json_success(
		array(
			'keyword' => $keyword,
			'total'   => $total,
			'items'   => $items,
			'popular' => typee_search_popular_keywords(),
			'allUrl'  => $all_url,
		)
	);
}
add_action( 'wp_ajax_typee_live_search', 'typee_ajax_live_search' );
add_action( 'wp_ajax_nopriv_typee_live_search', 'typee_ajax_live_search' );

/**
 * Tách biệt luồng `is_search()` chuẩn WordPress/WooCommerce (`/?s=...&post_type=product`):
 * luôn nạp `search.php` của theme con thay vì để `archive-product.php` chặn lại.
 */
add_filter( 'template_include', function ( $template ) {
	if ( is_search() && ! typee_current_virtual() ) {
		$own = TYPEE_CHILD_DIR . '/search.php';
		if ( file_exists( $own ) ) {
			return $own;
		}
	}
	return $template;
}, 60 );
