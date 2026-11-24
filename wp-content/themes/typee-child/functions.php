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

	if ( is_front_page() ) {
		typee_style( 'typee-home', 'home.css' );
	}

	wp_enqueue_script( 'typee-main', TYPEE_CHILD_URI . '/assets/js/main.js', array( 'jquery' ), typee_asset_ver( 'js/main.js' ), true );
	wp_localize_script(
		'typee-main',
		'TYPEE',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'restUrl'  => esc_url_raw( rest_url() ),
			'cartUrl'  => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'currency' => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol() ) : '₫',
		)
	);
}, 20 );

/**
 * Nạp sớm font chính để tránh nhảy chữ.
 */
add_action( 'wp_head', function () {
	$fonts = array( 'inter-vietnamese-400-normal.woff2', 'plus-jakarta-sans-vietnamese-700-normal.woff2' );
	foreach ( $fonts as $f ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( TYPEE_CHILD_URI . '/assets/fonts/' . $f ) );
	}
}, 1 );

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
