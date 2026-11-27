<?php
/**
 * TYPEE Child – functions
 *
 * Nguyên tắc: chỉ làm giao diện. Không tạo/sửa dữ liệu trong DB, không đụng typee-core
 * hay cấu hình plugin. Mọi dữ liệu hiển thị lấy động từ WooCommerce + ACF/postmeta.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

define( 'TYPEE_CHILD_VERSION', '0.1.0' );
define( 'TYPEE_CHILD_DIR', get_stylesheet_directory() );
define( 'TYPEE_CHILD_URI', get_stylesheet_directory_uri() );

require_once TYPEE_CHILD_DIR . '/inc/helpers.php';
require_once TYPEE_CHILD_DIR . '/inc/product-data.php';
require_once TYPEE_CHILD_DIR . '/inc/template-hooks.php';
require_once TYPEE_CHILD_DIR . '/inc/routes.php';
require_once TYPEE_CHILD_DIR . '/inc/builder-data.php';
require_once TYPEE_CHILD_DIR . '/inc/compare-data.php';
require_once TYPEE_CHILD_DIR . '/inc/catalog.php';
require_once TYPEE_CHILD_DIR . '/inc/single-product.php';
require_once TYPEE_CHILD_DIR . '/inc/search.php';
require_once TYPEE_CHILD_DIR . '/inc/tracking-warranty.php';

/**
 * CSS & JS.
 */
/**
 * Phiên bản file theo thời gian sửa → trình duyệt luôn tải bản mới sau mỗi lần cập nhật.
 */
function typee_asset_ver( $rel ) {
	$path = TYPEE_CHILD_DIR . '/assets/' . $rel;
	return file_exists( $path ) ? TYPEE_CHILD_VERSION . '.' . filemtime( $path ) : TYPEE_CHILD_VERSION;
}

/**
 * Nạp một file CSS của theme.
 */
function typee_style( $handle, $file, $deps = array( 'typee-base' ) ) {
	wp_enqueue_style( $handle, TYPEE_CHILD_URI . '/assets/css/' . $file, $deps, typee_asset_ver( 'css/' . $file ) );
}

add_action( 'wp_enqueue_scripts', function () {
	typee_style( 'typee-fonts', 'fonts.css', array() );
	typee_style( 'typee-tokens', 'tokens.css', array( 'typee-fonts' ) );
	typee_style( 'typee-base', 'base.css', array( 'typee-tokens', 'flatsome-main' ) );
	typee_style( 'typee-header', 'header.css' );
	typee_style( 'typee-footer', 'footer.css' );
	typee_style( 'typee-product-card', 'product-card.css' );
	typee_style( 'typee-components', 'components.css' );
	typee_style( 'typee-search', 'search.css' );

	if ( is_front_page() && ! typee_current_virtual() ) {
		typee_style( 'typee-home', 'home.css' );
	}
	if ( typee_is_catalog() ) {
		typee_style( 'typee-catalog', 'catalog.css' );
		wp_enqueue_script( 'typee-catalog', TYPEE_CHILD_URI . '/assets/js/catalog.js', array( 'typee-main' ), typee_asset_ver( 'js/catalog.js' ), true );
	}
	if ( 'builder' === typee_current_virtual() ) {
		typee_style( 'typee-builder', 'builder.css' );
		wp_enqueue_script( 'typee-builder', TYPEE_CHILD_URI . '/assets/js/builder.js', array( 'typee-main' ), typee_asset_ver( 'js/builder.js' ), true );
	}
	if ( 'compare' === typee_current_virtual() ) {
		typee_style( 'typee-compare', 'compare.css' );
		wp_enqueue_script( 'typee-compare', TYPEE_CHILD_URI . '/assets/js/compare.js', array( 'typee-main' ), typee_asset_ver( 'js/compare.js' ), true );
	}
	if ( in_array( typee_current_virtual(), array( 'tracking', 'rma_new' ), true ) || is_page_template( 'page-tracking-warranty.php' ) ) {
		typee_style( 'typee-tracking-warranty', 'tracking-warranty.css' );
		wp_enqueue_script( 'typee-tracking-warranty', TYPEE_CHILD_URI . '/assets/js/tracking-warranty.js', array( 'typee-main' ), typee_asset_ver( 'js/tracking-warranty.js' ), true );
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		typee_style( 'typee-single-product', 'single-product.css' );
		wp_enqueue_script( 'wc-add-to-cart-variation' );
		wp_enqueue_script( 'typee-single-product', TYPEE_CHILD_URI . '/assets/js/single-product.js', array( 'jquery', 'typee-main', 'wc-add-to-cart-variation' ), typee_asset_ver( 'js/single-product.js' ), true );
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		typee_style( 'typee-checkout', 'checkout.css' );
		wp_enqueue_script( 'typee-checkout', TYPEE_CHILD_URI . '/assets/js/checkout.js', array( 'jquery', 'typee-main' ), typee_asset_ver( 'js/checkout.js' ), true );
	}

	wp_enqueue_script( 'typee-main', TYPEE_CHILD_URI . '/assets/js/main.js', array( 'jquery' ), typee_asset_ver( 'js/main.js' ), true );
	wp_enqueue_script( 'typee-search', TYPEE_CHILD_URI . '/assets/js/search.js', array( 'typee-main' ), typee_asset_ver( 'js/search.js' ), true );
	wp_localize_script(
		'typee-main',
		'TYPEE',
		array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'restUrl'         => esc_url_raw( rest_url() ),
			'cartUrl'         => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'checkoutUrl'     => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'searchUrl'       => typee_url( 'search' ),
			'currency'        => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '₫',
			'catalogNonce'    => wp_create_nonce( 'typee_catalog_nonce' ),
			'singleCartNonce' => wp_create_nonce( 'typee_single_cart_nonce' ),
			'searchNonce'     => wp_create_nonce( 'typee_search_nonce' ),
			'trackingNonce'   => wp_create_nonce( 'typee_tracking_nonce' ),
		)
	);
}, 20 );

/**
 * Nạp sớm font chính để tránh nhảy chữ & gắn Favicon / Apple Touch Icon thương hiệu TYPEE.
 */
add_action( 'wp_head', function () {
	$fonts = array( 'inter-vietnamese-400-normal.woff2', 'plus-jakarta-sans-vietnamese-700-normal.woff2' );
	foreach ( $fonts as $f ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( TYPEE_CHILD_URI . '/assets/fonts/' . $f ) );
	}

	$favicon_ver = rawurlencode( typee_asset_ver( 'images/favicon.png' ) );
	$apple_ver   = rawurlencode( typee_asset_ver( 'images/apple-touch-icon.png' ) );
	$favicon_url = TYPEE_CHILD_URI . '/assets/images/favicon.png?v=' . $favicon_ver;
	$apple_url   = TYPEE_CHILD_URI . '/assets/images/apple-touch-icon.png?v=' . $apple_ver;

	printf( '<link rel="icon" type="image/png" sizes="512x512" href="%s" />' . "\n", esc_url( $favicon_url ) );
	printf( '<link rel="shortcut icon" type="image/png" href="%s" />' . "\n", esc_url( $favicon_url ) );
	printf( '<link rel="apple-touch-icon" sizes="180x180" href="%s" />' . "\n", esc_url( $apple_url ) );
}, 1 );

/**
 * Fallback Site Icon cho WordPress (Admin / REST / PWA) khi chưa thiết lập trong DB.
 */
add_filter( 'get_site_icon_url', function ( $url, $size ) {
	if ( ! empty( $url ) ) {
		return $url;
	}
	$file = ( $size && (int) $size <= 180 ) ? 'apple-touch-icon.png' : 'favicon.png';
	return TYPEE_CHILD_URI . '/assets/images/' . $file . '?v=' . rawurlencode( typee_asset_ver( 'images/' . $file ) );
}, 10, 2 );

/**
 * Hỗ trợ theme.
 */
add_action( 'after_setup_theme', function () {
	load_child_theme_textdomain( 'typee-child', TYPEE_CHILD_DIR . '/languages' );
	register_nav_menus(
		array(
			'typee_primary' => __( 'TYPEE – Menu chính (thanh dưới header)', 'typee-child' ),
			'typee_topbar'  => __( 'TYPEE – Liên kết thanh trên cùng', 'typee-child' ),
		)
	);
}, 20 );
