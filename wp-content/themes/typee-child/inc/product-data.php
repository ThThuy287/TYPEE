<?php
/**
 * Đọc dữ liệu sản phẩm thật từ DB (WooCommerce + ACF/postmeta). Không ghi gì vào DB.
 *
 * Thông số kỹ thuật của TYPEE lưu bằng ACF dưới dạng postmeta `attribute_*` trên sản phẩm cha,
 * theo 4 field group gắn với danh mục: kit, switch, keycap, pre-built.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bản đồ meta key → nhãn hiển thị, theo danh mục. Nhãn lấy từ ACF nếu có (get_field_object),
 * ngược lại dùng nhãn dự phòng dưới đây (trùng nhãn ACF trong DB ngày 17/11).
 */
function typee_spec_map() {
	return apply_filters(
		'typee_spec_map',
		array(
			'kit'       => array(
				'attribute_layout' => 'Layout',
				'attribute_pcb'    => 'Mạch (PCB)',
				'attribute_mount'  => 'Mounting Style',
			),
			'switch'    => array(
				'attribute_switch_type' => 'Loại Switch',
				'attribute_pin'         => 'Số chân Switch',
				'attribute_bottom_out'  => 'Lực nhấn (Bottom-out)',
			),
			'keycap'    => array(
				'attribute_profile'          => 'Profile',
				'attribute_material'         => 'Chất liệu',
				'attribute_supported_layout' => 'Layout Hỗ trợ',
			),
			'pre-built' => array(
				'attribute_layout'          => 'Layout',
				'attribute_connection'      => 'Kết nối',
				'attribute_switch_included' => 'Switch đi kèm',
				'attribute_keycap_material' => 'Chất liệu Keycap',
			),
		)
	);
}

/**
 * Slug danh mục chính của sản phẩm (kit / switch / keycap / pre-built / '').
 *
 * @param WC_Product|int $product Sản phẩm.
 */
function typee_product_group( $product ) {
	$product = wc_get_product( $product );
	if ( ! $product ) {
		return '';
	}
	$id    = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$slugs = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $slugs ) ) {
		return '';
	}
	foreach ( array_keys( typee_spec_map() ) as $group ) {
		if ( in_array( $group, $slugs, true ) ) {
			return $group;
		}
	}
	return '';
}

/**
 * Danh sách thông số có giá trị: [ [ 'key' => ..., 'label' => ..., 'value' => ... ], ... ].
 * Trường rỗng bị bỏ qua để giao diện tự ẩn.
 *
 * @param WC_Product|int $product Sản phẩm.
 */
function typee_product_specs( $product ) {
	static $labels = array(); // Nhãn ACF giống nhau cho mọi sản phẩm → tra một lần mỗi request.
	$product = wc_get_product( $product );
	if ( ! $product ) {
		return array();
	}
	$id    = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$group = typee_product_group( $product );
	$map   = typee_spec_map();
	$out   = array();

	if ( ! $group || empty( $map[ $group ] ) ) {
		return $out;
	}
	foreach ( $map[ $group ] as $key => $fallback_label ) {
		$value = get_post_meta( $id, $key, true );
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			continue;
		}
		if ( ! isset( $labels[ $key ] ) ) {
			$labels[ $key ] = $fallback_label;
			if ( function_exists( 'get_field_object' ) ) {
				$obj = get_field_object( $key, $id, false, false );
				if ( is_array( $obj ) && ! empty( $obj['label'] ) ) {
					$labels[ $key ] = $obj['label'];
				}
			}
		}
		$label = $labels[ $key ];
		$out[] = array(
			'key'   => $key,
			'label' => $label,
			'value' => $value,
		);
	}
	return apply_filters( 'typee_product_specs', $out, $product );
}

/**
 * Nhãn tồn kho theo dữ liệu thật.
 *
 * @return array{text:string,state:string} state: low | in | out | backorder
 */
function typee_stock_label( $product ) {
	$product = wc_get_product( $product );
	if ( ! $product ) {
		return array( 'text' => '', 'state' => '' );
	}
	$status = $product->get_stock_status();
	if ( 'outofstock' === $status ) {
		return array( 'text' => 'Hết hàng', 'state' => 'out' );
	}
	if ( 'onbackorder' === $status ) {
		return array( 'text' => 'Đặt trước', 'state' => 'backorder' );
	}
	$threshold = (int) apply_filters( 'typee_low_stock_threshold', 5 );
	if ( $product->managing_stock() ) {
		$qty = (int) $product->get_stock_quantity();
		if ( $qty > 0 && $qty <= $threshold ) {
			return array( 'text' => sprintf( 'Còn %d sản phẩm', $qty ), 'state' => 'low' );
		}
	}
	return array( 'text' => 'Sẵn hàng', 'state' => 'in' );
}

/**
 * % giảm giá thực tế (0 nếu không giảm). Hỗ trợ cả sản phẩm biến thể.
 */
function typee_discount_percent( $product ) {
	$product = wc_get_product( $product );
	if ( ! $product || ! $product->is_on_sale() ) {
		return 0;
	}
	if ( $product->is_type( 'variable' ) ) {
		$max = 0;
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( $child && $child->is_on_sale() ) {
				$max = max( $max, typee_discount_percent( $child ) );
			}
		}
		return $max;
	}
	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();
	if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
		return 0;
	}
	return (int) round( ( 1 - $sale / $regular ) * 100 );
}

/**
 * Giá thấp nhất trong một danh mục (dùng cho "Từ ...₫"), cache 12 giờ.
 */
function typee_category_min_price( $slug ) {
	// Chỉ dùng object cache (Redis) – không ghi transient vào bảng wp_options.
	$key   = 'typee_minprice_' . md5( $slug ) . '_v' . (int) wp_cache_get( 'ver', 'typee' );
	$cache = wp_cache_get( $key, 'typee' );
	if ( false !== $cache ) {
		return (float) $cache;
	}
	global $wpdb;
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term ) {
		return 0;
	}
	// Đọc bảng lookup của WooCommerce (chỉ SELECT).
	$min = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT MIN(l.min_price) FROM {$wpdb->wc_product_meta_lookup} l
			 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = l.product_id
			 INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
			 WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish' AND l.min_price > 0",
			$term->term_taxonomy_id
		)
	);
	$min = (float) $min;
	wp_cache_set( $key, $min, 'typee', 12 * HOUR_IN_SECONDS );
	return $min;
}

/**
 * Ảnh đại diện sản phẩm. Nếu file ảnh thiếu trên máy, WooCommerce tự dùng placeholder.
 */
function typee_product_image( $product, $size = 'woocommerce_thumbnail', $attr = array() ) {
	$product = wc_get_product( $product );
	if ( ! $product ) {
		return '';
	}
	return $product->get_image( $size, array_merge( array( 'loading' => 'lazy' ), $attr ) );
}
