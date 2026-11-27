<?php
/**
 * Override chuẩn WooCommerce single-product.php (Figma 82:2).
 *
 * @package typee-child
 * @version 1.6.4
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

do_action( 'woocommerce_before_main_content' );

while ( have_posts() ) :
	the_post();
	require TYPEE_CHILD_DIR . '/woocommerce/content-single-product.php';
endwhile;

do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );
