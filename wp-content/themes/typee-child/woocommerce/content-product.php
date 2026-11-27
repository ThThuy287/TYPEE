<?php
/**
 * Override chuẩn WooCommerce content-product.php.
 * Mọi vòng lặp sản phẩm (Shop, Category, Related, Shortcode) đều dùng thống nhất thẻ sản phẩm TYPEE.
 *
 * @package typee-child
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

get_template_part( 'template-parts/product/card', null, array( 'product' => $product ) );
