<?php
/**
 * Màn 02–03: Danh mục + Bộ lọc Off-Canvas (Figma 1:1600, 1:3768; PRD FR-01).
 * Lọc theo thông số ACF (postmeta attribute_*) bằng meta_query trên truy vấn hiển thị – chỉ đọc DB.
 * Giá dùng tham số min_price / max_price chuẩn của WooCommerce.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Các nhóm lọc: tham số URL => [meta key, nhãn, so khớp].
 */
function typee_filter_groups() {
	return apply_filters(
		'typee_filter_groups',
		array(
			'layout'      => array( 'attribute_layout', 'Layout bàn phím', '=' ),
			'mount'       => array( 'attribute_mount', 'Kiểu mount', '=' ),
			'connection'  => array( 'attribute_connection', 'Kết nối', '=' ),
			'switch_type' => array( 'attribute_switch_type', 'Loại switch', '=' ),
			'pin'         => array( 'attribute_pin', 'Số chân switch', 'LIKE' ),
			'profile'     => array( 'attribute_profile', 'Profile keycap', '=' ),
			'material'    => array( 'attribute_material', 'Chất liệu keycap', '=' ),
		)
	);
}

/**
 * Giá trị lọc đang chọn từ URL: [ 'layout' => ['75%','65%'], ... ].
 */
function typee_active_filters() {
	$out = array();
	foreach ( array_keys( typee_filter_groups() ) as $key ) {
		if ( empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			continue;
		}
		$raw  = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$vals = array_filter( array_map( 'sanitize_text_field', (array) $raw ) );
		if ( $vals ) {
			$out[ $key ] = array_values( array_unique( $vals ) );
		}
	}
	return $out;
}

/**
 * meta_query tương ứng các bộ lọc.
 */
function typee_filter_meta_query( $active ) {
	$groups = typee_filter_groups();
	$mq     = array( 'relation' => 'AND' );
	foreach ( $active as $key => $vals ) {
		list( $meta, , $cmp ) = $groups[ $key ];
		if ( 'LIKE' === $cmp ) {
			$or = array( 'relation' => 'OR' );
			foreach ( $vals as $v ) {
				$or[] = array( 'key' => $meta, 'value' => $v, 'compare' => 'LIKE' );
			}
			$mq[] = $or;
		} else {
			$mq[] = array( 'key' => $meta, 'value' => $vals, 'compare' => 'IN' );
		}
	}
	return count( $mq ) > 1 ? $mq : array();
}

function typee_is_catalog() {
	return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) );
}

/**
 * Áp bộ lọc vào truy vấn danh mục của WooCommerce (chỉ thay đổi điều kiện đọc).
 */
add_action( 'woocommerce_product_query', function ( $q ) {
	$mq = typee_filter_meta_query( typee_active_filters() );
	if ( $mq ) {
		$existing = (array) $q->get( 'meta_query' );
		$q->set( 'meta_query', array_merge( $existing, array( $mq ) ) );
	}
	if ( ! empty( $_GET['instock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$existing   = (array) $q->get( 'meta_query' );
		$existing[] = array( 'key' => '_stock_status', 'value' => 'instock' );
		$q->set( 'meta_query', $existing );
	}
} );

add_filter( 'loop_shop_per_page', function () {
	return 16;
}, 30 );
add_filter( 'loop_shop_columns', function () {
	return 4;
}, 30 );

/**
 * Đếm số sản phẩm theo từng giá trị thông số trong phạm vi danh mục hiện tại (facet), cache object cache.
 *
 * @param int $term_taxonomy_id 0 = toàn cửa hàng.
 * @return array [ 'layout' => [ '75%' => 127, ... ], ... ]
 */
function typee_facet_counts( $term_taxonomy_id = 0 ) {
	$key    = 'facets_' . (int) $term_taxonomy_id . '_v' . (int) wp_cache_get( 'ver', 'typee' );
	$cached = wp_cache_get( $key, 'typee' );
	if ( false !== $cached ) {
		return $cached;
	}
	global $wpdb;
	$groups = typee_filter_groups();
	$metas  = wp_list_pluck( $groups, 0 );
	$in     = implode( ',', array_fill( 0, count( $metas ), '%s' ) );
	$join   = $term_taxonomy_id ? "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID AND tr.term_taxonomy_id = " . (int) $term_taxonomy_id : '';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_key, pm.meta_value, COUNT(DISTINCT p.ID) c FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID {$join} WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value <> '' AND pm.meta_key IN ($in) GROUP BY pm.meta_key, pm.meta_value", $metas ) );
	$by_meta = array_flip( $metas );
	$out     = array();
	foreach ( $rows as $r ) {
		$param = array_search( $r->meta_key, $metas, true );
		if ( 'pin' === $param ) {
			foreach ( array_map( 'trim', explode( ',', $r->meta_value ) ) as $pin ) {
				$out['pin'][ $pin ] = ( isset( $out['pin'][ $pin ] ) ? $out['pin'][ $pin ] : 0 ) + (int) $r->c;
			}
			continue;
		}
		$out[ $param ][ $r->meta_value ] = (int) $r->c;
	}
	foreach ( $out as &$vals ) {
		arsort( $vals );
	}
	unset( $vals, $by_meta );
	wp_cache_set( $key, $out, 'typee', HOUR_IN_SECONDS );
	return $out;
}

/**
 * term_taxonomy_id của danh mục đang xem (0 nếu là trang cửa hàng tổng).
 */
function typee_current_cat_ttid() {
	$obj = get_queried_object();
	return ( $obj instanceof WP_Term && 'product_cat' === $obj->taxonomy ) ? (int) $obj->term_taxonomy_id : 0;
}

/**
 * URL hiện tại sau khi thay đổi một tham số lọc (giữ các tham số khác, bỏ phân trang).
 */
function typee_filter_url( $changes ) {
	$base = remove_query_arg( array( 'paged', 'product-page' ), add_query_arg( null, null ) );
	$base = preg_replace( '#/page/\d+/?#', '/', $base );
	foreach ( $changes as $k => $v ) {
		$base = remove_query_arg( $k, $base );
		if ( null !== $v && array() !== $v ) {
			// add_query_arg không mã hoá giá trị: "75%" phải thành "75%25", nếu không URL hỏng (trình duyệt/Customizer báo "URI malformed").
			$base = add_query_arg( $k, is_array( $v ) ? array_map( 'rawurlencode', array_values( $v ) ) : rawurlencode( (string) $v ), $base );
		}
	}
	return $base;
}

/**
 * AJAX: đếm số kết quả cho bộ lọc trong drawer trước khi áp dụng (đọc DB).
 */
function typee_ajax_filter_count() {
	$tt   = isset( $_GET['tt'] ) ? absint( $_GET['tt'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-catalog' ), 'operator' => 'NOT IN' ),
		),
	);
	if ( $tt ) {
		$term = get_term_by( 'term_taxonomy_id', $tt );
		if ( $term ) {
			$args['tax_query'][] = array( 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => $term->term_id );
		}
	}
	$mq = typee_filter_meta_query( typee_active_filters() );
	if ( $mq ) {
		$args['meta_query'][] = $mq;
	}
	if ( ! empty( $_GET['instock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$args['meta_query'][] = array( 'key' => '_stock_status', 'value' => 'instock' );
	}
	$min = isset( $_GET['min_price'] ) ? (float) $_GET['min_price'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$max = isset( $_GET['max_price'] ) ? (float) $_GET['max_price'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $min || $max ) {
		$args['meta_query'][] = array( 'key' => '_price', 'value' => array( $min, $max ? $max : PHP_INT_MAX ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' );
	}
	$ck    = 'count_' . md5( wp_json_encode( $args ) ) . '_v' . (int) wp_cache_get( 'ver', 'typee' );
	$count = wp_cache_get( $ck, 'typee' );
	if ( false === $count ) {
		$q     = new WP_Query( $args );
		$count = (int) $q->found_posts;
		wp_cache_set( $ck, $count, 'typee', 10 * MINUTE_IN_SECONDS );
	}
	wp_send_json_success( array( 'count' => (int) $count ) );
}
add_action( 'wp_ajax_typee_filter_count', 'typee_ajax_filter_count' );
add_action( 'wp_ajax_nopriv_typee_filter_count', 'typee_ajax_filter_count' );

/**
 * Khoảng giá của danh mục hiện tại (cho ô giá tối thiểu/tối đa).
 */
function typee_price_bounds( $tt = 0 ) {
	global $wpdb;
	$join = $tt ? "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = l.product_id AND tr.term_taxonomy_id = " . (int) $tt : '';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
	$row = $wpdb->get_row( "SELECT MIN(l.min_price) mn, MAX(l.max_price) mx FROM {$wpdb->wc_product_meta_lookup} l {$join} WHERE l.min_price > 0" );
	return array( 'min' => $row ? (float) $row->mn : 0, 'max' => $row ? (float) $row->mx : 0 );
}

/**
 * Trang danh mục dùng phần đầu trang riêng của TYPEE: bỏ thanh tiêu đề danh mục của Flatsome
 * (tránh in trùng ô sắp xếp/đếm kết quả rồi phải ẩn bằng CSS).
 */
add_action( 'wp', function () {
	if ( typee_is_catalog() ) {
		remove_action( 'flatsome_after_header', 'flatsome_category_header' );
	}
} );

/**
 * Mọi trang danh mục (cửa hàng, danh mục, thẻ, thuộc tính) dùng chung giao diện danh mục của TYPEE.
 * Với taxonomy, WooCommerce có thể chọn template khác trước khi tới archive-product.php của theme con.
 */
add_filter( 'template_include', function ( $template ) {
	if ( typee_is_catalog() && ! typee_current_virtual() ) {
		$own = TYPEE_CHILD_DIR . '/woocommerce/archive-product.php';
		if ( file_exists( $own ) ) {
			return $own;
		}
	}
	return $template;
}, 50 );
