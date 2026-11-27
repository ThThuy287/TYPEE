<?php
/**
 * Dữ liệu & cấu hình cho Màn 05: Trang So Sánh Linh Kiện & Bàn Phím Cơ (Figma 82:2526).
 *
 * Kết nối trực tiếp với TYPEE_Compare_API (typee-core) để bảo đảm hoạt động ổn định
 * ngay cả khi plugin được bật sau hoặc gọi trực tiếp qua REST API.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

// Dự phòng nạp class nếu plugin typee-core chưa kích hoạt trong môi trường dev mới.
if ( ! class_exists( 'TYPEE_Compare_API' ) ) {
	$fallback_api = WP_CONTENT_DIR . '/plugins/typee-core/includes/class-compare-api.php';
	if ( file_exists( $fallback_api ) ) {
		require_once $fallback_api;
		TYPEE_Compare_API::init();
	}
}

/**
 * Lấy bộ gợi ý so sánh nhanh từ sản phẩm thật đang bán trong DB (không dùng dữ liệu mẫu cứng).
 *
 * @return array Danh sách các bộ gợi ý theo nhóm (Kit & Pre-built, Switch, Keycap).
 */
function typee_compare_presets() {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$cached_ver = (int) wp_cache_get( 'ver', 'typee' );
	$cache_key  = 'compare_presets_v1_' . $cached_ver;
	$cached     = wp_cache_get( $cache_key, 'typee' );
	if ( false !== $cached ) {
		return $cached;
	}

	$presets = array();

	// Bộ 1: Bàn phím Pre-built & Khung Kit nổi bật (tối đa 3 mẫu để khách thử ngay cả khe trống thứ 4).
	$kb_ids = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 3,
			'category' => array( 'pre-built', 'kit' ),
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'ids',
		)
	);
	if ( count( $kb_ids ) >= 2 ) {
		$presets[] = array(
			'label' => 'Đối chiếu 3 mẫu Kit & Phím cơ mới nhất',
			'ids'   => array_map( 'intval', $kb_ids ),
		);
	}

	// Bộ 2: Switch cơ học.
	$sw_ids = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 3,
			'category' => array( 'switch' ),
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'ids',
		)
	);
	if ( count( $sw_ids ) >= 2 ) {
		$presets[] = array(
			'label' => 'Đối chiếu 3 dòng Switch cơ học',
			'ids'   => array_map( 'intval', $sw_ids ),
		);
	}

	wp_cache_set( $cache_key, $presets, 'typee', HOUR_IN_SECONDS );
	return $presets;
}

/**
 * Cấu hình khởi tạo truyền xuống compare.js.
 *
 * @return array
 */
function typee_compare_client_config() {
	$initial_ids = array();
	if ( ! empty( $_GET['ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $raw as $tok ) {
			$id = absint( trim( $tok ) );
			if ( $id > 0 && ! in_array( $id, $initial_ids, true ) ) {
				$initial_ids[] = $id;
			}
			if ( count( $initial_ids ) >= 4 ) {
				break;
			}
		}
	}

	$initial_data = null;
	if ( ! empty( $initial_ids ) && class_exists( 'TYPEE_Compare_API' ) ) {
		$initial_data = TYPEE_Compare_API::get_comparison_data( $initial_ids );
	}

	return array(
		'compareApiUrl' => esc_url_raw( rest_url( 'typee/v1/compare' ) ),
		'searchApiUrl'  => esc_url_raw( rest_url( 'typee/v1/compare/products' ) ),
		'addToCartUrl'  => esc_url_raw( add_query_arg( 'wc-ajax', 'add_to_cart', home_url( '/' ) ) ),
		'comparePageUrl'=> esc_url_raw( typee_url( 'compare' ) ),
		'catalogUrl'    => esc_url_raw( typee_url( 'catalog' ) ),
		'builderUrl'    => esc_url_raw( typee_url( 'builder' ) ),
		'maxSlots'      => 4,
		'urlIds'        => $initial_ids,
		'initialData'   => $initial_data,
		'presets'       => typee_compare_presets(),
	);
}
