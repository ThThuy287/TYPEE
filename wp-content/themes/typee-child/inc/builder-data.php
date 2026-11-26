<?php
/**
 * Dữ liệu cho Custom Builder (đọc DB, không ghi). Trả về danh sách gọn Kit / Switch / Keycap
 * kèm biến thể, giá, ảnh và thông số ACF để JS lọc & tính giá theo thời gian thực.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Số switch tối thiểu theo layout – khớp TYPEE_Rule_Engine::get_min_switches_needed() (typee-core).
 */
function typee_min_switches_map() {
	return array( '60%' => 64, '65%' => 68, '75%' => 84, 'TKL' => 87, '96%' => 100, '98%' => 100, 'Fullsize' => 108, '_default' => 110 );
}

/**
 * Nhãn biến thể dạng "Màu: Navy · Plate: FR4".
 */
function typee_variation_label( $variation ) {
	$parts = array();
	foreach ( $variation->get_variation_attributes( false ) as $attr => $value ) {
		if ( '' === $value ) {
			continue;
		}
		$label   = wc_attribute_label( $attr, wc_get_product( $variation->get_parent_id() ) );
		$parts[] = $label . ': ' . $value;
	}
	return implode( ' · ', $parts );
}

/**
 * Một sản phẩm → mảng gọn cho JS.
 */
function typee_builder_item( $product, $group ) {
	$specs = array();
	foreach ( typee_product_specs( $product ) as $s ) {
		$specs[ str_replace( 'attribute_', '', $s['key'] ) ] = $s['value'];
	}
	$thumb = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
	$large = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'large' ) : $thumb;
	$item  = array(
		'id'    => $product->get_id(),
		'name'  => $product->get_name(),
		'sku'   => $product->get_sku(),
		'url'   => $product->get_permalink(),
		'img'   => $thumb,
		'large' => $large,
		'price' => (float) wc_get_price_to_display( $product ),
		'stock' => $product->is_in_stock(),
		'specs' => $specs,
		'vars'  => array(),
	);
	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v || ! $v->is_purchasable() ) {
				continue;
			}
			$row = array(
				'id'    => $v->get_id(),
				'label' => typee_variation_label( $v ),
				'price' => (float) wc_get_price_to_display( $v ),
				'stock' => $v->is_in_stock(),
				'sku'   => $v->get_sku(),
			);
			if ( 'switch' === $group ) {
				$pack = $v->get_attribute( 'so-luong' );
				$row['pack'] = preg_match( '/(\d+)/', (string) $pack, $m ) ? (int) $m[1] : 0;
			}
			$item['vars'][] = $row;
		}
		if ( $item['vars'] ) {
			$item['price'] = min( wp_list_pluck( $item['vars'], 'price' ) );
		}
	}
	return $item;
}

/**
 * Toàn bộ danh mục builder, cache 1 giờ trong object cache (Redis), không ghi DB.
 */
function typee_builder_catalog() {
	$cached = wp_cache_get( 'builder_catalog_v1_' . (int) wp_cache_get( 'ver', 'typee' ), 'typee' );
	if ( false !== $cached ) {
		return $cached;
	}
	$out = array();
	foreach ( array( 'kit', 'switch', 'keycap' ) as $group ) {
		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => -1,
				'category' => array( $group ),
				'orderby'  => 'menu_order title',
				'order'    => 'ASC',
			)
		);
		$out[ $group ] = array();
		foreach ( $products as $p ) {
			if ( $p->is_purchasable() || $p->is_type( 'variable' ) ) {
				$out[ $group ][] = typee_builder_item( $p, $group );
			}
		}
	}
	wp_cache_set( 'builder_catalog_v1_' . (int) wp_cache_get( 'ver', 'typee' ), $out, 'typee', HOUR_IN_SECONDS );
	return $out;
}
