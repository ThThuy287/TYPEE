<?php
/**
 * Router theo PRD §3 (17 màn hình). Các màn hình không có trang WordPress tương ứng được
 * đăng ký thành "trang ảo" bằng rewrite rule của theme – KHÔNG tạo post/page trong DB.
 *
 *  /catalog/                 → kho sản phẩm WooCommerce (archive-product)
 *  /builder/[?build_id=]     → templates/builder.php           (màn 06, 08)
 *  /compare/                 → templates/compare.php           (màn 05)
 *  /tracking-rma/            → templates/tracking.php          (màn 12)
 *  /rma/new-ticket/          → templates/rma-new.php           (màn 13)
 *  /chinh-sach-bao-hanh/     → templates/warranty.php          (màn 14)
 *  /modding-guides/[page/N]  → templates/guides.php            (màn 15)
 *  /modding-guides/{slug}/   → bài viết (single.php)           (màn 16)
 *  /search/?q=               → templates/search.php            (màn 17)
 *  /tai-khoan/saved-builds/  → endpoint My Account             (màn 07)
 *  /checkout/                → chuyển hướng tới trang Thanh toán WooCommerce (màn 10)
 *
 * Lưu ý: WordPress lưu bảng rewrite đã biên dịch trong option `rewrite_rules` (bộ nhớ đệm định tuyến,
 * tự sinh lại bất cứ lúc nào). Theme chỉ làm mới bảng này khi được kích hoạt / khi thiếu route.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Danh sách trang ảo: key => [ regex, template, tiêu đề ].
 */
function typee_virtual_pages() {
	return apply_filters(
		'typee_virtual_pages',
		array(
			'builder'  => array( '^builder/?$', 'builder', 'Custom Keyboard Builder' ),
			'compare'  => array( '^compare/?$', 'compare', 'So sánh linh kiện' ),
			'tracking' => array( '^tracking-rma/?$', 'tracking', 'Theo dõi đơn hàng & Tra cứu RMA' ),
			'rma_new'  => array( '^rma/new-ticket/?$', 'rma-new', 'Gửi yêu cầu bảo hành e-RMA' ),
			'warranty' => array( '^chinh-sach-bao-hanh/?$', 'warranty', 'Chính sách bảo hành & đổi trả' ),
			'guides'   => array( '^modding-guides/?$', 'guides', 'Cẩm nang Modding & Newbie' ),
			'search'   => array( '^search/?$', 'search', 'Kết quả tìm kiếm' ),
		)
	);
}

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'typee_page';
	$vars[] = 'typee_paged';
	return $vars;
} );

add_action( 'init', function () {
	foreach ( typee_virtual_pages() as $key => $def ) {
		add_rewrite_rule( $def[0], 'index.php?typee_page=' . $key, 'top' );
	}
	add_rewrite_rule( '^modding-guides/page/([0-9]+)/?$', 'index.php?typee_page=guides&typee_paged=$matches[1]', 'top' );
	add_rewrite_rule( '^modding-guides/([^/]+)/?$', 'index.php?name=$matches[1]', 'top' );
	add_rewrite_rule( '^catalog/?$', 'index.php?post_type=product', 'top' );
	add_rewrite_rule( '^catalog/page/([0-9]+)/?$', 'index.php?post_type=product&paged=$matches[1]', 'top' );

	// Màn 07: "Cấu hình đã lưu" và danh sách phiếu RMA trong Tài khoản.
	add_rewrite_endpoint( 'saved-builds', EP_ROOT | EP_PAGES );
	add_rewrite_endpoint( 'rma-tickets', EP_ROOT | EP_PAGES );
}, 20 );

/**
 * Làm mới bảng định tuyến khi kích hoạt theme, hoặc khi phát hiện route của theme chưa có.
 */
function typee_maybe_flush_routes() {
	$rules = get_option( 'rewrite_rules' );
	if ( ! is_array( $rules ) || ! isset( $rules['^builder/?$'] ) || ! isset( $rules['^modding-guides/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}
add_action( 'after_switch_theme', 'flush_rewrite_rules' );
add_action( 'init', 'typee_maybe_flush_routes', 99 );

/**
 * Key trang ảo hiện tại ('' nếu không phải).
 */
function typee_current_virtual() {
	$key = get_query_var( 'typee_page' );
	return ( $key && isset( typee_virtual_pages()[ $key ] ) ) ? $key : '';
}

/**
 * Không để main query của trang ảo bị coi là trang chủ/blog.
 */
add_action( 'parse_query', function ( $q ) {
	if ( $q->is_main_query() && $q->get( 'typee_page' ) ) {
		$q->is_home       = false;
		$q->is_front_page = false;
		$q->is_404        = false;
	}
} );
add_filter( 'pre_handle_404', function ( $handled, $q ) {
	return ( $q->get( 'typee_page' ) ) ? true : $handled;
}, 10, 2 );
add_filter( 'posts_pre_query', function ( $posts, $q ) {
	return ( $q->is_main_query() && $q->get( 'typee_page' ) ) ? array() : $posts;
}, 10, 2 );

add_filter( 'template_include', function ( $template ) {
	$key = typee_current_virtual();
	if ( ! $key ) {
		return $template;
	}
	status_header( 200 );
	$file = TYPEE_CHILD_DIR . '/templates/' . typee_virtual_pages()[ $key ][1] . '.php';
	return file_exists( $file ) ? $file : TYPEE_CHILD_DIR . '/templates/coming-soon.php';
}, 99 );

add_filter( 'pre_get_document_title', function ( $title ) {
	$key = typee_current_virtual();
	return $key ? typee_virtual_pages()[ $key ][2] . ' – ' . get_bloginfo( 'name' ) : $title;
} );

add_filter( 'body_class', function ( $classes ) {
	$key = typee_current_virtual();
	if ( $key ) {
		$classes   = array_diff( $classes, array( 'home', 'blog' ) );
		$classes[] = 'typee-page';
		$classes[] = 'typee-page-' . sanitize_html_class( $key );
	}
	return $classes;
} );

/**
 * Bài viết hiển thị dưới /modding-guides/{slug}/ (màn 16).
 */
add_filter( 'post_link', function ( $link, $post ) {
	if ( 'post' === $post->post_type && 'publish' === $post->post_status ) {
		return home_url( '/modding-guides/' . $post->post_name . '/' );
	}
	return $link;
}, 10, 2 );

/**
 * /checkout → trang Thanh toán WooCommerce (slug thật trong DB là /thanh-toan/).
 */
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( $home && 0 === strpos( $path, $home ) ) {
		$path = trim( substr( $path, strlen( $home ) ), '/' );
	}
	if ( 'checkout' === $path && function_exists( 'wc_get_checkout_url' ) ) {
		$target      = wc_get_checkout_url();
		$target_path = trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' );
		if ( $home && 0 === strpos( $target_path, $home ) ) {
			$target_path = trim( substr( $target_path, strlen( $home ) ), '/' );
		}
		if ( $target_path && 'checkout' !== $target_path ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}
}, 1 );

/**
 * WooCommerce: khai báo endpoint để is_wc_endpoint_url() và tiêu đề hoạt động.
 */
add_filter( 'woocommerce_get_query_vars', function ( $vars ) {
	$vars['saved-builds'] = 'saved-builds';
	$vars['rma-tickets']  = 'rma-tickets';
	return $vars;
} );

/**
 * Ẩn main query (tránh tải bài blog) cho trang ảo – đã xử lý bởi posts_pre_query.
 * Hàm tiện ích cho template: tiêu đề + breadcrumb.
 */
function typee_breadcrumb( $items ) {
	echo '<nav class="ty-breadcrumb" aria-label="Breadcrumb"><ol>';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">Trang chủ</a></li>';
	$last = count( $items ) - 1;
	foreach ( array_values( $items ) as $i => $item ) {
		if ( $i === $last || empty( $item[1] ) ) {
			echo '<li aria-current="page">' . esc_html( $item[0] ) . '</li>';
		} else {
			echo '<li><a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a></li>';
		}
	}
	echo '</ol></nav>';
}
