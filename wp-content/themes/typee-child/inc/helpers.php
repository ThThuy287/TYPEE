<?php
/**
 * Tiện ích chung: icon SVG, URL trang, định dạng tiền.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * In icon Material Symbols (Rounded, Apache-2.0) đóng gói trong theme, tô màu theo currentColor.
 *
 * @param string $name  Tên file trong assets/icons (không có .svg).
 * @param int    $size  Kích thước px.
 * @param string $class Class bổ sung.
 * @return string
 */
function typee_icon( $name, $size = 16, $class = '' ) {
	static $cache = array();
	$name = sanitize_file_name( $name );
	if ( ! isset( $cache[ $name ] ) ) {
		$file           = TYPEE_CHILD_DIR . '/assets/icons/' . $name . '.svg';
		$cache[ $name ] = file_exists( $file ) ? preg_replace( '#<metadata>.*?</metadata>#s', '', file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if ( '' === $cache[ $name ] ) {
		return '';
	}
	$svg = preg_replace(
		'/<svg\b[^>]*>/',
		sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="%1$d" height="%1$d" fill="currentColor" aria-hidden="true" focusable="false" class="ty-icon %2$s">',
			(int) $size,
			esc_attr( $class )
		),
		$cache[ $name ],
		1
	);
	return $svg;
}

/**
 * In icon trực tiếp.
 */
function typee_the_icon( $name, $size = 16, $class = '' ) {
	echo typee_icon( $name, $size, $class ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG nội bộ của theme.
}

/**
 * URL các trang chức năng. Trang nào chưa tồn tại trong DB sẽ trả về '' để template tự ẩn liên kết.
 * Nhóm có thể đổi qua filter `typee_page_urls` mà không cần sửa template.
 *
 * @param string $key Khóa trang.
 * @return string
 */
function typee_url( $key ) {
	static $urls = null;
	if ( null === $urls ) {
		$cat = function ( $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				return '';
			}
			$link = get_term_link( $term );
			return is_wp_error( $link ) ? '' : $link;
		};
		$page = function ( $slug ) {
			$p = get_page_by_path( $slug );
			return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
		};

		$urls = array(
			'home'       => home_url( '/' ),
			'shop'       => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
			'cart'       => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'checkout'   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'account'    => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '',
			'prebuilt'   => $cat( 'pre-built' ),
			'kit'        => $cat( 'kit' ),
			'switch'     => $cat( 'switch' ),
			'keycap'     => $cat( 'keycap' ),
			// Route theo PRD §3 (đăng ký trong inc/routes.php, không tạo trang trong DB).
			'catalog'      => home_url( '/catalog/' ),
			'builder'      => home_url( '/builder/' ),
			'compare'      => home_url( '/compare/' ),
			'tracking'     => home_url( '/tracking-rma/' ),
			'rma_new'      => home_url( '/rma/new-ticket/' ),
			'warranty'     => home_url( '/chinh-sach-bao-hanh/' ),
			'guides'       => home_url( '/modding-guides/' ),
			'search'       => home_url( '/search/' ),
			'saved_builds' => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'saved-builds' ) : '',
			'rma_tickets'  => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'rma-tickets' ) : '',
			'wishlist'     => function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'wishlist' ) : '',
			'blog'         => home_url( '/modding-guides/' ),
			'faq'          => home_url( '/modding-guides/' ),
			'privacy'    => get_privacy_policy_url(),
			'terms'      => function_exists( 'wc_terms_and_conditions_page_id' ) && wc_terms_and_conditions_page_id() ? get_permalink( wc_terms_and_conditions_page_id() ) : '',
		);
		$urls = apply_filters( 'typee_page_urls', $urls );
	}
	return isset( $urls[ $key ] ) ? $urls[ $key ] : '';
}

/**
 * Link nếu có URL, ngược lại in <span> (giữ đúng bố cục Figma nhưng không trỏ link chết).
 */
function typee_link( $key, $text, $class = '', $extra_html = '' ) {
	$url = typee_url( $key );
	if ( $url ) {
		printf( '<a class="%s" href="%s">%s%s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $text ), $extra_html ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		printf( '<span class="%s is-disabled" aria-disabled="true">%s%s</span>', esc_attr( $class ), esc_html( $text ), $extra_html ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Định dạng tiền theo cấu hình WooCommerce, trả về văn bản thuần (vd: 3.450.000₫).
 */
function typee_money( $amount ) {
	if ( ! function_exists( 'wc_price' ) ) {
		return number_format_i18n( (float) $amount );
	}
	return wp_strip_all_tags( wc_price( (float) $amount ) );
}

/**
 * Nội dung tiếp thị có thể chỉnh qua filter (không phải dữ liệu sản phẩm).
 * Ví dụ: add_filter( 'typee_copy', fn( $c ) => array_merge( $c, array( 'promo_code' => 'WELCOME10' ) ) );
 */
function typee_copy( $key ) {
	static $copy = null;
	if ( null === $copy ) {
		$copy = apply_filters(
			'typee_copy',
			array(
				'promo_label'   => 'ƯU ĐÃI',
				'promo_text'    => 'Giảm ngay 5% khi tự ráp trọn bộ Custom Bundle | Nhập mã ',
				'promo_code'    => 'TYPEENEW10',
				'promo_suffix'  => ' giảm 10% tối đa 100K',
				'hotline'       => '1900 8899',
				'showroom'      => 'Showroom mở cửa 09:00 - 21:00',
				'address_hn'    => '42 Triều Khúc, Thanh Xuân',
				'address_hcm'   => '188 Nam Kỳ Khởi Nghĩa, Quận 3',
				'hotline_note'  => '1900 8899 (Hỗ trợ 24/7)',
				'brand_tagline' => 'CUSTOM STUDIO',
			)
		);
	}
	return isset( $copy[ $key ] ) ? $copy[ $key ] : '';
}

/**
 * Cache danh sách ID (Redis object cache) cho các khối chạy trên mọi trang: gợi ý tìm kiếm, hero, ưu đãi…
 * Tự làm mới khi có sản phẩm được lưu/xóa (đổi số phiên bản trong cache, không ghi DB).
 *
 * @param string   $key Khóa.
 * @param callable $cb  Hàm trả về mảng ID.
 * @param int      $ttl Giây.
 * @return int[]
 */
function typee_cached_ids( $key, $cb, $ttl = 600 ) {
	$ver = (int) wp_cache_get( 'ver', 'typee' );
	$k   = $key . '_v' . $ver;
	$ids = wp_cache_get( $k, 'typee' );
	if ( false === $ids ) {
		$ids = array_values( array_map( 'intval', (array) call_user_func( $cb ) ) );
		wp_cache_set( $k, $ids, 'typee', $ttl );
	}
	return $ids;
}

/**
 * Lấy sản phẩm theo danh sách ID đã cache (giữ thứ tự, bỏ sản phẩm không còn).
 *
 * @param int[] $ids ID.
 * @return WC_Product[]
 */
function typee_products_by_ids( $ids ) {
	if ( $ids && function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( $ids, false, true );
	}
	return array_values( array_filter( array_map( 'wc_get_product', $ids ) ) );
}

function typee_bump_cache_ver() {
	wp_cache_set( 'ver', (int) wp_cache_get( 'ver', 'typee' ) + 1, 'typee' );
}
add_action( 'woocommerce_update_product', 'typee_bump_cache_ver' );
add_action( 'woocommerce_new_product', 'typee_bump_cache_ver' );
add_action( 'before_delete_post', function ( $id ) {
	if ( 'product' === get_post_type( $id ) ) {
		typee_bump_cache_ver();
	}
} );
