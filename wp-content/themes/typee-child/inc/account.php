<?php
/**
 * Nền tảng khu vực Tài khoản & Xác thực TYPEE (My Account Shell + Auth / Password Recovery).
 *
 * Nguyên tắc:
 * - Không sửa DB schema hay option cấu hình hệ thống.
 * - Mọi số liệu sidebar lấy động từ WordPress / WooCommerce / CPT `typee_warranty`.
 * - Gỡ các khối mặc định trùng lặp của Flatsome CHỈ trong phạm vi `is_account_page()`.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Đảm bảo WooCommerce luôn nạp đúng template `myaccount/*` của child theme `typee-child`,
 * kể cả khi Redis Object Cache đang giữ đường dẫn template cũ của Flatsome / WooCommerce.
 */
add_filter(
	'wc_get_template',
	function ( $template, $template_name ) {
		if ( 0 !== strpos( (string) $template_name, 'myaccount/' ) ) {
			return $template;
		}
		$child_file = TYPEE_CHILD_DIR . '/woocommerce/' . ltrim( (string) $template_name, '/' );
		if ( file_exists( $child_file ) && wp_normalize_path( (string) $template ) !== wp_normalize_path( $child_file ) ) {
			static $cleared = false;
			if ( ! $cleared && function_exists( 'wc_clear_template_cache' ) ) {
				$cleared = true;
				wc_clear_template_cache();
			}
			return $child_file;
		}
		return $template;
	},
	20,
	2
);

add_filter(
	'wc_get_template_part',
	function ( $template, $slug, $name ) {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return $template;
		}
		if ( 0 !== strpos( (string) $slug, 'myaccount/' ) ) {
			return $template;
		}
		$candidates = array();
		if ( '' !== (string) $name ) {
			$candidates[] = TYPEE_CHILD_DIR . '/woocommerce/' . $slug . '-' . $name . '.php';
		}
		$candidates[] = TYPEE_CHILD_DIR . '/woocommerce/' . $slug . '.php';
		foreach ( $candidates as $child_file ) {
			if ( file_exists( $child_file ) ) {
				return $child_file;
			}
		}
		return $template;
	},
	20,
	3
);

/**
 * Cấu hình cố định cho từng mục điều hướng Tài khoản (thứ tự, nhãn, icon).
 *
 * @return array<string, array{label:string, icon:string}>
 */
function typee_account_menu_definitions() {
	return array(
		'dashboard'       => array(
			'label' => 'Tổng quan tài khoản',
			'icon'  => 'dashboard',
		),
		'edit-account'    => array(
			'label' => 'Hồ sơ cá nhân & Bảo mật',
			'icon'  => 'manage_accounts',
		),
		'orders'          => array(
			'label' => 'Đơn hàng của tôi',
			'icon'  => 'receipt_long',
		),
		'saved-builds'    => array(
			'label' => 'Cấu hình đã lưu',
			'icon'  => 'keyboard',
		),
		'rma-tickets'     => array(
			'label' => 'Yêu cầu bảo hành / e-RMA',
			'icon'  => 'verified_user',
		),
		'wishlist'        => array(
			'label' => 'Sản phẩm yêu thích',
			'icon'  => 'favorite',
		),
		'edit-address'    => array(
			'label' => 'Sổ địa chỉ giao hàng',
			'icon'  => 'location_on',
		),
		'payment-methods' => array(
			'label' => 'Phương thức thanh toán',
			'icon'  => 'credit_card',
		),
		'downloads'       => array(
			'label' => 'Tệp tải xuống',
			'icon'  => 'download',
		),
	);
}

/**
 * Chuẩn hóa thứ tự và nhãn menu Tài khoản WooCommerce theo bố cục TYPEE.
 * Nút Đăng xuất được render riêng ở cuối sidebar trong `navigation.php` để cố định vị trí và style.
 */
add_filter(
	'woocommerce_account_menu_items',
	function ( $items ) {
		$defs    = typee_account_menu_definitions();
		$ordered = array();

		$primary_keys = array(
			'dashboard',
			'edit-account',
			'orders',
			'saved-builds',
			'rma-tickets',
			'wishlist',
			'edit-address',
		);

		foreach ( $primary_keys as $key ) {
			$ordered[ $key ] = $defs[ $key ]['label'];
		}

		// Giữ lại các endpoint tùy biến khác từ plugin (nếu có), loại trừ customer-logout & downloads mặc định.
		foreach ( (array) $items as $key => $label ) {
			if ( isset( $ordered[ $key ] ) || in_array( $key, array( 'customer-logout', 'downloads' ), true ) ) {
				continue;
			}
			$ordered[ $key ] = isset( $defs[ $key ] ) ? $defs[ $key ]['label'] : $label;
		}

		return $ordered;
	},
	20
);

/**
 * Xác định endpoint Tài khoản hiện tại.
 *
 * @return string
 */
function typee_account_current_endpoint() {
	global $wp;

	if ( ! isset( $wp->query_vars ) || ! is_array( $wp->query_vars ) ) {
		return 'dashboard';
	}

	$wc_vars = ( function_exists( 'WC' ) && WC()->query ) ? WC()->query->get_query_vars() : array();
	$known   = array_unique(
		array_merge(
			array_keys( $wc_vars ),
			array( 'saved-builds', 'rma-tickets', 'wishlist' )
		)
	);

	foreach ( $known as $endpoint ) {
		if ( isset( $wp->query_vars[ $endpoint ] ) ) {
			return $endpoint;
		}
	}

	return 'dashboard';
}

/**
 * Kiểm tra xem một mục menu sidebar có đang active hay không.
 * Ánh xạ các trang con (vd `view-order` → `orders`, `add-payment-method` → `payment-methods`).
 *
 * @param string $endpoint Khóa endpoint.
 * @return bool
 */
function typee_account_is_endpoint_active( $endpoint ) {
	$current = typee_account_current_endpoint();

	if ( 'view-order' === $current && 'orders' === $endpoint ) {
		return true;
	}
	if ( 'add-payment-method' === $current && 'payment-methods' === $endpoint ) {
		return true;
	}
	if ( $current === $endpoint ) {
		return true;
	}
	if ( function_exists( 'wc_is_current_account_menu_item' ) && wc_is_current_account_menu_item( $endpoint ) ) {
		return true;
	}

	return false;
}

/**
 * Lấy icon tương ứng với từng endpoint trong sidebar.
 *
 * @param string $endpoint Khóa endpoint.
 * @return string
 */
function typee_account_endpoint_icon( $endpoint ) {
	$defs = typee_account_menu_definitions();
	return isset( $defs[ $endpoint ]['icon'] ) ? $defs[ $endpoint ]['icon'] : 'chevron_right';
}

/**
 * Lấy tiêu đề hiển thị trên breadcrumb cho endpoint hiện tại.
 *
 * @param string $endpoint Khóa endpoint.
 * @return string
 */
function typee_account_endpoint_breadcrumb_label( $endpoint ) {
	global $wp;
	$defs = typee_account_menu_definitions();

	if ( 'view-order' === $endpoint ) {
		$order_id = isset( $wp->query_vars['view-order'] ) ? absint( $wp->query_vars['view-order'] ) : 0;
		return $order_id ? sprintf( 'Chi tiết đơn hàng #%d', $order_id ) : 'Chi tiết đơn hàng';
	}
	if ( 'edit-address' === $endpoint && ! empty( $wp->query_vars['edit-address'] ) ) {
		$addr_type = sanitize_key( $wp->query_vars['edit-address'] );
		if ( 'billing' === $addr_type ) {
			return 'Chỉnh sửa địa chỉ thanh toán';
		}
		if ( 'shipping' === $addr_type ) {
			return 'Chỉnh sửa địa chỉ giao hàng';
		}
	}
	if ( 'edit-address' === $endpoint && ! empty( $_GET['addr_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$addr_action = sanitize_key( wp_unslash( $_GET['addr_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'new' === $addr_action ) {
			return 'Thêm địa chỉ mới';
		}
		if ( 'edit' === $addr_action ) {
			return 'Chỉnh sửa địa chỉ';
		}
	}
	if ( isset( $defs[ $endpoint ]['label'] ) ) {
		return $defs[ $endpoint ]['label'];
	}

	$menu = function_exists( 'wc_get_account_menu_items' ) ? wc_get_account_menu_items() : array();
	if ( isset( $menu[ $endpoint ] ) ) {
		return (string) $menu[ $endpoint ];
	}

	return 'Tổng quan tài khoản';
}

/**
 * Tổng hợp dữ liệu động của người dùng hiện tại cho Sidebar Tài khoản.
 *
 * @param int $user_id ID người dùng.
 * @return array<string, mixed>
 */
function typee_account_user_summary( $user_id = 0 ) {
	static $cache = array();

	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( isset( $cache[ $user_id ] ) ) {
		return $cache[ $user_id ];
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return array();
	}

	$display_name = trim( $user->display_name );
	if ( '' === $display_name ) {
		$display_name = trim( $user->first_name . ' ' . $user->last_name );
	}
	if ( '' === $display_name ) {
		$display_name = $user->user_login;
	}

	// Mã định danh thành viên TYPEE dạng #TYP-00001.
	$member_code = sprintf( '#TYP-%05d', $user_id );

	// Thống kê đơn hàng thật từ WooCommerce.
	$orders_total     = 0;
	$orders_active    = 0;
	$orders_completed = 0;
	$total_spent      = 0.0;

	if ( function_exists( 'wc_get_orders' ) ) {
		$order_ids    = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'return'      => 'ids',
				'status'      => array_keys( wc_get_order_statuses() ),
			)
		);
		$orders_total = is_array( $order_ids ) ? count( $order_ids ) : 0;

		$active_ids    = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'return'      => 'ids',
				'status'      => array( 'wc-pending', 'wc-processing', 'wc-on-hold' ),
			)
		);
		$orders_active = is_array( $active_ids ) ? count( $active_ids ) : 0;

		$completed_ids    = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'return'      => 'ids',
				'status'      => array( 'wc-completed' ),
			)
		);
		$orders_completed = is_array( $completed_ids ) ? count( $completed_ids ) : 0;
	}

	if ( function_exists( 'wc_get_customer_total_spent' ) ) {
		$total_spent = (float) wc_get_customer_total_spent( $user_id );
	}

	// Tính hạng thành viên & thanh tiến độ chi tiêu tích lũy.
	if ( $total_spent >= 15000000 ) {
		$tier_name   = 'VIP Lab (Lv. 04)';
		$tier_target = 15000000;
		$tier_pct    = 100;
		$tier_next   = 'Đặc quyền giảm 5% Custom Bundle trọn đời.';
	} elseif ( $total_spent >= 10000000 ) {
		$tier_name   = 'Custom Builder (Lv. 03)';
		$tier_target = 15000000;
		$tier_pct    = (int) min( 99, round( ( $total_spent / $tier_target ) * 100 ) );
		$tier_next   = 'VIP Lab (giảm 5% Custom Bundle trọn đời)';
	} elseif ( $total_spent >= 5000000 ) {
		$tier_name   = 'Enthusiast (Lv. 02)';
		$tier_target = 10000000;
		$tier_pct    = (int) min( 99, round( ( $total_spent / $tier_target ) * 100 ) );
		$tier_next   = 'Custom Builder Lv. 03';
	} else {
		$tier_name   = 'Starter Builder (Lv. 01)';
		$tier_target = 5000000;
		$tier_pct    = $total_spent > 0 ? (int) max( 5, min( 99, round( ( $total_spent / $tier_target ) * 100 ) ) ) : 0;
		$tier_next   = 'Enthusiast Lv. 02';
	}

	// Số cấu hình đã lưu (user meta `typee_saved_builds`).
	$saved_builds       = get_user_meta( $user_id, 'typee_saved_builds', true );
	$saved_builds_count = is_array( $saved_builds ) ? count( $saved_builds ) : 0;

	// Số phiếu e-RMA đang hoạt động (CPT `typee_warranty`).
	$rma_active = 0;
	if ( post_type_exists( 'typee_warranty' ) ) {
		$phone = (string) get_user_meta( $user_id, 'billing_phone', true );
		$meta  = array(
			'relation' => 'AND',
			array(
				'key'     => '_rma_status',
				'value'   => array( 'completed', 'rejected' ),
				'compare' => 'NOT IN',
			),
		);
		$contact_or = array( 'relation' => 'OR' );
		if ( ! empty( $user->user_email ) ) {
			$contact_or[] = array(
				'key'     => '_rma_contact',
				'value'   => $user->user_email,
				'compare' => 'LIKE',
			);
		}
		if ( '' !== trim( $phone ) ) {
			$contact_or[] = array(
				'key'     => '_rma_contact',
				'value'   => trim( $phone ),
				'compare' => 'LIKE',
			);
		}
		if ( count( $contact_or ) > 1 ) {
			$meta[] = $contact_or;
			$q      = new WP_Query(
				array(
					'post_type'      => 'typee_warranty',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			$rma_active = count( $q->posts );
		}
	}

	// Số sổ địa chỉ đã điền (giao hàng, thanh toán và các địa chỉ phụ).
	$addresses_list  = function_exists( 'typee_account_get_addresses' ) ? typee_account_get_addresses( $user_id ) : array();
	$addresses_count = count( $addresses_list );

	// Số sản phẩm yêu thích đã đồng bộ phía server (JS sẽ tiếp tục đồng bộ với localStorage).
	$wishlist_ids   = get_user_meta( $user_id, 'typee_wishlist_ids', true );
	$wishlist_count = is_array( $wishlist_ids ) ? count( array_filter( array_map( 'absint', $wishlist_ids ) ) ) : 0;

	$cache[ $user_id ] = array(
		'user'               => $user,
		'display_name'       => $display_name,
		'email'              => $user->user_email,
		'member_code'        => $member_code,
		'orders_total'       => $orders_total,
		'orders_active'      => $orders_active,
		'orders_completed'   => $orders_completed,
		'total_spent'        => $total_spent,
		'tier_name'          => $tier_name,
		'tier_target'        => $tier_target,
		'tier_pct'           => $tier_pct,
		'tier_next'          => $tier_next,
		'saved_builds_count' => $saved_builds_count,
		'rma_active'         => $rma_active,
		'addresses_count'    => $addresses_count,
		'wishlist_count'     => $wishlist_count,
	);

	return $cache[ $user_id ];
}

/**
 * Lấy badge tóm tắt bên phải từng mục điều hướng sidebar.
 *
 * @param string               $endpoint Khóa endpoint.
 * @param array<string, mixed> $summary  Dữ liệu tóm tắt user.
 * @return array{text:string, tone:string, attr:string}
 */
function typee_account_menu_badge( $endpoint, $summary ) {
	switch ( $endpoint ) {
		case 'orders':
			$total  = isset( $summary['orders_total'] ) ? (int) $summary['orders_total'] : 0;
			$active = isset( $summary['orders_active'] ) ? (int) $summary['orders_active'] : 0;
			if ( $active > 0 ) {
				return array(
					'text' => sprintf( '%d xử lý / %d', $active, $total ),
					'tone' => 'accent',
					'attr' => '',
				);
			}
			if ( $total > 0 ) {
				return array(
					'text' => sprintf( '%d đơn', $total ),
					'tone' => 'neutral',
					'attr' => '',
				);
			}
			return array( 'text' => '', 'tone' => '', 'attr' => '' );

		case 'saved-builds':
			$n = isset( $summary['saved_builds_count'] ) ? (int) $summary['saved_builds_count'] : 0;
			return array(
				'text' => $n > 0 ? sprintf( '%d build', $n ) : '',
				'tone' => 'neutral',
				'attr' => 'data-typee-account-builds-count',
			);

		case 'rma-tickets':
			$n = isset( $summary['rma_active'] ) ? (int) $summary['rma_active'] : 0;
			return array(
				'text' => $n > 0 ? sprintf( '%d active', $n ) : '',
				'tone' => 'accent',
				'attr' => '',
			);

		case 'wishlist':
			$n = isset( $summary['wishlist_count'] ) ? (int) $summary['wishlist_count'] : 0;
			return array(
				'text' => $n > 0 ? (string) $n : '',
				'tone' => 'neutral',
				'attr' => 'data-typee-account-wishlist-count',
			);

		case 'edit-address':
			$n = isset( $summary['addresses_count'] ) ? (int) $summary['addresses_count'] : 0;
			return array(
				'text' => $n > 0 ? sprintf( '%d địa chỉ', $n ) : '',
				'tone' => 'neutral',
				'attr' => '',
			);
	}

	return array( 'text' => '', 'tone' => '', 'attr' => '' );
}

/**
 * SVG icon ẩn/hiện mật khẩu (inline tối giản, tô màu theo currentColor).
 *
 * @param bool $off True = mắt gạch chéo (đang hiện mật khẩu → bấm để ẩn).
 * @return string
 */
function typee_auth_eye_svg( $off = false ) {
	if ( $off ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
	}
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}

/**
 * Cho phép đăng nhập bằng Số điện thoại (`billing_phone`) ngoài Email / Username chuẩn của WordPress.
 */
add_filter(
	'authenticate',
	function ( $user, $username, $password ) {
		if ( $user instanceof WP_User || empty( $username ) || empty( $password ) ) {
			return $user;
		}
		if ( is_email( $username ) || username_exists( $username ) ) {
			return $user;
		}
		$digits = preg_replace( '/\D+/', '', (string) $username );
		if ( strlen( $digits ) < 9 || strlen( $digits ) > 12 ) {
			return $user;
		}

		$candidates = array_unique( array( trim( (string) $username ), $digits ) );
		if ( 0 === strpos( $digits, '84' ) && strlen( $digits ) >= 11 ) {
			$candidates[] = '0' . substr( $digits, 2 );
		} elseif ( 0 === strpos( $digits, '0' ) ) {
			$candidates[] = '+84' . substr( $digits, 1 );
		}

		$users = get_users(
			array(
				'meta_key'     => 'billing_phone', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'   => $candidates, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare' => 'IN',
				'number'       => 1,
				'fields'       => 'all',
			)
		);

		if ( ! empty( $users[0] ) && $users[0] instanceof WP_User ) {
			if ( wp_check_password( $password, $users[0]->user_pass, $users[0]->ID ) ) {
				return $users[0];
			}
		}

		return $user;
	},
	15,
	3
);

/**
 * Lưu thêm Họ tên & Số điện thoại nếu người dùng nhập trong form Đăng ký TYPEE.
 */
add_action(
	'woocommerce_created_customer',
	function ( $customer_id ) {
		if ( ! empty( $_POST['typee_reg_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$full_name = sanitize_text_field( wp_unslash( $_POST['typee_reg_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( '' !== $full_name ) {
				$parts = preg_split( '/\s+/', $full_name );
				$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
				$first = implode( ' ', $parts );

				wp_update_user(
					array(
						'ID'           => $customer_id,
						'display_name' => $full_name,
						'first_name'   => $first ? $first : $full_name,
						'last_name'    => $last,
					)
				);
				update_user_meta( $customer_id, 'billing_first_name', $first ? $first : $full_name );
				if ( '' !== $last ) {
					update_user_meta( $customer_id, 'billing_last_name', $last );
				}
			}
		}

		if ( ! empty( $_POST['typee_reg_phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$phone = sanitize_text_field( wp_unslash( $_POST['typee_reg_phone'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( '' !== $phone ) {
				update_user_meta( $customer_id, 'billing_phone', $phone );
			}
		}
	}
);

/**
 * Lưu tạm email vừa yêu cầu đặt lại mật khẩu vào WC Session để hiển thị trên màn hình xác nhận.
 */
add_action(
	'woocommerce_reset_password_notification',
	function ( $user_login ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$user = get_user_by( 'login', $user_login );
		if ( ! $user && is_email( $user_login ) ) {
			$user = get_user_by( 'email', $user_login );
		}
		if ( $user && ! empty( $user->user_email ) ) {
			WC()->session->set( 'typee_lost_pw_email', $user->user_email );
		}
	},
	10,
	1
);

/**
 * Gắn class nhận diện riêng cho trang Tài khoản (phạm vi cô lập tuyệt đối).
 */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$classes[] = 'typee-account-page';
			$classes[] = is_user_logged_in() ? 'typee-account-view' : 'typee-auth-view';
		}
		return $classes;
	}
);

/**
 * Tắt khối notice/dashboard mặc định của Flatsome CHỈ trên trang Tài khoản
 * để hiển thị gọn gàng đúng vị trí bên trong Layout Shell của TYPEE.
 */
add_action(
	'wp',
	function () {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		remove_action( 'flatsome_after_header', 'flatsome_woocommerce_add_notice', 100 );
		remove_action( 'woocommerce_account_dashboard', 'flatsome_my_account_dashboard' );
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
			remove_action( 'woocommerce_view_order', 'woocommerce_order_details_table', 10 );
		}
	},
	20
);

/**
 * Fallback nội dung an toàn cho các endpoint tùy biến (`saved-builds`, `rma-tickets`, `wishlist`)
 * khi chưa có module chuyên biệt gắn vào priority < 20, giúp khung Layout Shell luôn hoạt động trọn vẹn.
 */
add_action(
	'woocommerce_account_saved-builds_endpoint',
	function () {
		if ( has_action( 'woocommerce_account_saved-builds_endpoint' ) && did_action( 'typee_rendered_saved_builds' ) ) {
			return;
		}
		// Nếu đã có handler khác ưu tiên cao hơn (< 20), nhường toàn quyền render.
		global $wp_filter;
		if ( isset( $wp_filter['woocommerce_account_saved-builds_endpoint'] ) ) {
			foreach ( $wp_filter['woocommerce_account_saved-builds_endpoint']->callbacks as $prio => $cbs ) {
				if ( (int) $prio < 20 && ! empty( $cbs ) ) {
					return;
				}
			}
		}
		?>
		<div class="typee-account-placeholder">
			<div class="typee-account-placeholder__icon"><?php typee_the_icon( 'keyboard', 28 ); ?></div>
			<h2 class="typee-account-placeholder__title">Cấu hình Custom Builder đã lưu</h2>
			<p class="typee-account-placeholder__desc">Tất cả bản phối Kit, Switch, Keycap bạn lưu từ Custom Keyboard Builder 6 bước sẽ xuất hiện tại đây.</p>
			<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">
				<?php typee_the_icon( 'build_circle', 18 ); ?>
				<span>Mở Custom Keyboard Builder</span>
			</a>
		</div>
		<?php
	},
	20
);

add_action(
	'woocommerce_account_rma-tickets_endpoint',
	function () {
		global $wp_filter;
		if ( isset( $wp_filter['woocommerce_account_rma-tickets_endpoint'] ) ) {
			foreach ( $wp_filter['woocommerce_account_rma-tickets_endpoint']->callbacks as $prio => $cbs ) {
				if ( (int) $prio < 20 && ! empty( $cbs ) ) {
					return;
				}
			}
		}
		?>
		<div class="typee-account-placeholder">
			<div class="typee-account-placeholder__icon"><?php typee_the_icon( 'verified_user', 28 ); ?></div>
			<h2 class="typee-account-placeholder__title">Yêu cầu bảo hành &amp; e-RMA</h2>
			<p class="typee-account-placeholder__desc">Theo dõi tiến trình kiểm định KCS, sửa chữa PCB hoặc tạo phiếu yêu cầu bảo hành e-RMA mới.</p>
			<div class="typee-account-placeholder__actions">
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'rma_new' ) ); ?>">Gửi yêu cầu e-RMA mới</a>
				<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'tracking' ) ); ?>">Tra cứu mã e-RMA</a>
			</div>
		</div>
		<?php
	},
	20
);

add_action(
	'woocommerce_account_wishlist_endpoint',
	function () {
		global $wp_filter;
		if ( isset( $wp_filter['woocommerce_account_wishlist_endpoint'] ) ) {
			foreach ( $wp_filter['woocommerce_account_wishlist_endpoint']->callbacks as $prio => $cbs ) {
				if ( (int) $prio < 20 && ! empty( $cbs ) ) {
					return;
				}
			}
		}
		?>
		<div class="typee-account-placeholder">
			<div class="typee-account-placeholder__icon"><?php typee_the_icon( 'favorite', 28 ); ?></div>
			<h2 class="typee-account-placeholder__title">Sản phẩm yêu thích (Wishlist)</h2>
			<p class="typee-account-placeholder__desc">Lưu nhanh các mẫu Kit CNC, Switch và Keycap bạn quan tâm để dễ dàng so sánh hoặc mua sau.</p>
			<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Khám phá danh mục sản phẩm</a>
		</div>
		<?php
	},
	20
);

/**
 * ============================================================================
 * ĐƠN HÀNG CỦA TÔI (ORDERS & VIEW ORDER) – Helpers lọc, tìm kiếm, trạng thái
 * ============================================================================
 */

/**
 * Danh sách nhóm lọc trạng thái trên thanh Segmented Filter Tabs (Figma 82:7261 & 46:6000).
 *
 * @return array<string, string>
 */
function typee_account_order_filter_groups() {
	return array(
		'all'        => 'Tất cả',
		'pending'    => 'Chờ xác nhận',
		'processing' => 'Đang xử lý & đóng gói',
		'shipping'   => 'Đang giao',
		'completed'  => 'Hoàn thành',
		'cancelled'  => 'Đã hủy',
	);
}

/**
 * Ánh xạ trạng thái WooCommerce sang nhãn tiếng Việt, tone màu, nhóm lọc và bước tiến trình (1..5).
 *
 * @param string $status Trạng thái không có tiền tố `wc-`.
 * @return array{label:string, tone:string, group:string, step:int, note:string}
 */
function typee_account_order_status_meta( $status ) {
	$status = preg_replace( '/^wc-/', '', (string) $status );

	switch ( $status ) {
		case 'pending':
			return array(
				'label' => 'Chờ thanh toán',
				'tone'  => 'warning',
				'group' => 'pending',
				'step'  => 1,
				'note'  => 'Đơn hàng đang chờ xác nhận thanh toán từ hệ thống.',
			);
		case 'on-hold':
			return array(
				'label' => 'Chờ xác nhận',
				'tone'  => 'warning',
				'group' => 'pending',
				'step'  => 2,
				'note'  => 'Đơn hàng đã được tiếp nhận và đang chờ bộ phận CSKH xác nhận.',
			);
		case 'processing':
			return array(
				'label' => 'Đang đóng gói tại Lab',
				'tone'  => 'accent',
				'group' => 'processing',
				'step'  => 3,
				'note'  => 'Kỹ thuật viên đang kiểm định linh kiện & đóng gói chống sốc 3 lớp.',
			);
		case 'shipping':
		case 'in-transit':
		case 'out-for-delivery':
			return array(
				'label' => 'Đang giao hàng',
				'tone'  => 'info',
				'group' => 'shipping',
				'step'  => 4,
				'note'  => 'Đơn hàng đã bàn giao cho đơn vị vận chuyển và đang trên đường giao tới bạn.',
			);
		case 'completed':
			return array(
				'label' => 'Hoàn thành',
				'tone'  => 'success',
				'group' => 'completed',
				'step'  => 5,
				'note'  => 'Đơn hàng đã giao thành công. Bảo hành điện tử e-RMA đã được kích hoạt.',
			);
		case 'cancelled':
			return array(
				'label' => 'Đã hủy',
				'tone'  => 'danger',
				'group' => 'cancelled',
				'step'  => 0,
				'note'  => 'Đơn hàng đã được hủy.',
			);
		case 'refunded':
			return array(
				'label' => 'Đã hoàn tiền',
				'tone'  => 'danger',
				'group' => 'cancelled',
				'step'  => 0,
				'note'  => 'Đơn hàng đã được xử lý hoàn tiền.',
			);
		case 'failed':
			return array(
				'label' => 'Thanh toán thất bại',
				'tone'  => 'danger',
				'group' => 'cancelled',
				'step'  => 0,
				'note'  => 'Giao dịch thanh toán chưa hoàn tất. Bạn có thể thử thanh toán lại.',
			);
		default:
			$wc_label = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : ucfirst( $status );
			return array(
				'label' => $wc_label,
				'tone'  => 'neutral',
				'group' => 'processing',
				'step'  => 2,
				'note'  => 'Đơn hàng đang được xử lý tại TYPEE Studio.',
			);
	}
}

/**
 * Xác định nhóm trạng thái hiệu lực của 1 đơn hàng (nếu có mã vận đơn khi đang processing thì tính là Đang giao).
 *
 * @param WC_Order $order Đơn hàng.
 * @return array{label:string, tone:string, group:string, step:int, note:string, tracking_code:string, carrier:string}
 */
function typee_account_order_display_status( $order ) {
	$status        = $order->get_status();
	$meta          = typee_account_order_status_meta( $status );
	$tracking_code = (string) $order->get_meta( '_typee_tracking_code', true );
	if ( '' === $tracking_code ) {
		$tracking_code = (string) $order->get_meta( '_tracking_number', true );
	}
	$carrier = (string) $order->get_meta( '_typee_shipping_carrier', true );
	if ( '' === $carrier ) {
		$carrier = $order->get_shipping_method();
	}

	if ( 'processing' === $status && '' !== trim( $tracking_code ) ) {
		$meta = typee_account_order_status_meta( 'shipping' );
	}

	$meta['tracking_code'] = trim( $tracking_code );
	$meta['carrier']       = trim( $carrier );
	return $meta;
}

/**
 * Danh sách 5 mốc tiến trình kỹ thuật & vận chuyển cho đơn hàng (Figma 46:6059).
 *
 * @return array<int, string>
 */
function typee_account_order_progress_steps() {
	return array(
		1 => '1. Tiếp nhận đơn',
		2 => '2. Xác nhận Lab',
		3 => '3. KCS & Đóng gói',
		4 => '4. Đang vận chuyển',
		5 => '5. Đã giao hàng',
	);
}

/**
 * Truy vấn danh sách đơn hàng của user có hỗ trợ bộ lọc trạng thái, tìm kiếm từ khóa, khoảng thời gian và phân trang.
 *
 * @param int                  $user_id ID user.
 * @param array<string, mixed> $args    Tham số lọc (`status`, `q`, `range`, `page`, `per_page`).
 * @return array{
 *   orders: WC_Order[],
 *   total: int,
 *   total_all: int,
 *   max_pages: int,
 *   page: int,
 *   per_page: int,
 *   counts: array<string, int>
 * }
 */
function typee_account_query_orders( $user_id, $args = array() ) {
	$defaults = array(
		'status'   => 'all',
		'q'        => '',
		'range'    => 'all',
		'page'     => 1,
		'per_page' => 5,
	);
	$args = wp_parse_args( $args, $defaults );

	$counts = array();
	foreach ( array_keys( typee_account_order_filter_groups() ) as $gk ) {
		$counts[ $gk ] = 0;
	}

	if ( ! function_exists( 'wc_get_orders' ) || $user_id <= 0 ) {
		return array(
			'orders'    => array(),
			'total'     => 0,
			'total_all' => 0,
			'max_pages' => 1,
			'page'      => 1,
			'per_page'  => (int) $args['per_page'],
			'counts'    => $counts,
		);
	}

	$all_orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array_keys( wc_get_order_statuses() ),
		)
	);

	$all_orders = is_array( $all_orders ) ? $all_orders : array();
	$counts['all'] = count( $all_orders );

	$now_ts = current_time( 'timestamp' );
	$min_ts = 0;
	if ( '30d' === $args['range'] ) {
		$min_ts = $now_ts - ( 30 * DAY_IN_SECONDS );
	} elseif ( '90d' === $args['range'] ) {
		$min_ts = $now_ts - ( 90 * DAY_IN_SECONDS );
	} elseif ( 'year' === $args['range'] ) {
		$min_ts = $now_ts - ( 365 * DAY_IN_SECONDS );
	}

	$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $args['q'] ) ) : strtolower( trim( (string) $args['q'] ) );
	$filtered = array();

	foreach ( $all_orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}
		$st = typee_account_order_display_status( $order );
		if ( isset( $counts[ $st['group'] ] ) ) {
			++$counts[ $st['group'] ];
		}

		// Lọc theo trạng thái.
		if ( 'all' !== $args['status'] && $st['group'] !== $args['status'] ) {
			continue;
		}

		// Lọc theo khoảng thời gian.
		if ( $min_ts > 0 ) {
			$created = $order->get_date_created();
			if ( ! $created || $created->getTimestamp() < $min_ts ) {
				continue;
			}
		}

		// Lọc theo từ khóa (mã đơn, mã vận đơn, tên sản phẩm hoặc SKU).
		if ( '' !== $needle ) {
			$haystack_parts = array(
				'#' . $order->get_order_number(),
				'#TY-' . $order->get_order_number(),
				$order->get_order_number(),
				$st['tracking_code'],
			);
			foreach ( $order->get_items() as $item ) {
				$haystack_parts[] = $item->get_name();
				if ( method_exists( $item, 'get_product' ) ) {
					$prod = $item->get_product();
					if ( $prod && $prod->get_sku() ) {
						$haystack_parts[] = $prod->get_sku();
					}
				}
			}
			$haystack = implode( ' ', $haystack_parts );
			$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
			if ( false === strpos( $haystack, $needle ) ) {
				continue;
			}
		}

		$filtered[] = $order;
	}

	$total     = count( $filtered );
	$per_page  = max( 1, (int) $args['per_page'] );
	$max_pages = max( 1, (int) ceil( $total / $per_page ) );
	$page      = max( 1, min( $max_pages, (int) $args['page'] ) );
	$offset    = ( $page - 1 ) * $per_page;

	return array(
		'orders'    => array_slice( $filtered, $offset, $per_page ),
		'total'     => $total,
		'total_all' => $counts['all'],
		'max_pages' => $max_pages,
		'page'      => $page,
		'per_page'  => $per_page,
		'counts'    => $counts,
	);
}

/**
 * Tạo URL mua lại đơn hàng chuẩn WooCommerce (Order Again).
 *
 * @param WC_Order $order Đơn hàng.
 * @return string
 */
function typee_account_order_again_url( $order ) {
	if ( ! $order instanceof WC_Order || ! function_exists( 'wc_get_cart_url' ) ) {
		return '';
	}
	$valid_statuses = apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) );
	if ( ! $order->has_status( $valid_statuses ) ) {
		return '';
	}
	return wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' );
}

/**
 * ============================================================================
 * HỒ SƠ CÁ NHÂN & BẢO MẬT (EDIT ACCOUNT) – Trường mở rộng & Ảnh đại diện
 * ============================================================================
 */

/**
 * Danh sách lựa chọn Layout phím quen thuộc (Figma 82:6826).
 *
 * @return array<string, string>
 */
function typee_account_keyboard_layouts() {
	return array(
		'60'       => '60% Compact (Poker / HHKB)',
		'65'       => '65% Compact (Arrow Keys)',
		'75'       => '75% Compact (Exploded / Knob)',
		'tkl'      => 'TKL 80% (Tenkeyless)',
		'fullsize' => 'Fullsize 100% / 1800 Compact',
		'alice'    => 'Alice / Arisu Ergonomic',
	);
}

/**
 * Cho phép form Edit Account hỗ trợ tải lên ảnh đại diện (`multipart/form-data`).
 */
add_action(
	'woocommerce_edit_account_form_tag',
	function () {
		echo ' enctype="multipart/form-data"';
	}
);

/**
 * Tự động ánh xạ trường "Họ và tên đầy đủ" (`typee_full_name`) sang `account_first_name`,
 * `account_last_name`, `account_display_name` trước khi WooCommerce kiểm tra dữ liệu.
 */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_POST['action'] ) || 'save_account_details' !== $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( isset( $_POST['typee_full_name'] ) && is_string( $_POST['typee_full_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$full = trim( sanitize_text_field( wp_unslash( $_POST['typee_full_name'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( '' !== $full ) {
				$parts = preg_split( '/\s+/', $full );
				$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
				$first = implode( ' ', $parts );

				$_POST['account_first_name'] = $first ? $first : $full;
				$_POST['account_last_name']  = $last;
				if ( empty( $_POST['account_display_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$_POST['account_display_name'] = $full;
				}
			}
		}
	},
	5
);

/**
 * Chuẩn hóa nhãn tiếng Việt cho trường bắt buộc và cho phép tên 1 từ không bị lỗi `account_last_name`.
 */
add_filter(
	'woocommerce_save_account_details_required_fields',
	function ( $fields ) {
		unset( $fields['account_last_name'] );
		if ( isset( $fields['account_first_name'] ) ) {
			$fields['account_first_name'] = 'Họ và tên đầy đủ';
		}
		if ( isset( $fields['account_display_name'] ) ) {
			$fields['account_display_name'] = 'Tên hiển thị';
		}
		if ( isset( $fields['account_email'] ) ) {
			$fields['account_email'] = 'Địa chỉ Email';
		}
		return $fields;
	}
);

/**
 * Lưu các trường hồ sơ bổ sung (SĐT, ngày sinh, giới tính, layout phím, 2FA, ảnh đại diện).
 */
add_action(
	'woocommerce_save_account_details',
	function ( $user_id ) {
		if ( isset( $_POST['typee_account_phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$phone = sanitize_text_field( wp_unslash( $_POST['typee_account_phone'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( $user_id, 'billing_phone', $phone );
		}

		// Đồng bộ billing_first_name / billing_last_name khi đổi Họ và tên.
		if ( ! empty( $_POST['account_first_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( $user_id, 'billing_first_name', sanitize_text_field( wp_unslash( $_POST['account_first_name'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( isset( $_POST['account_last_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( $user_id, 'billing_last_name', sanitize_text_field( wp_unslash( $_POST['account_last_name'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		// Ngày sinh (3 ô select Ngày / Tháng / Năm).
		$day   = isset( $_POST['typee_dob_day'] ) ? absint( $_POST['typee_dob_day'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$month = isset( $_POST['typee_dob_month'] ) ? absint( $_POST['typee_dob_month'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$year  = isset( $_POST['typee_dob_year'] ) ? absint( $_POST['typee_dob_year'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 1940 && $year <= (int) gmdate( 'Y' ) ) {
			update_user_meta( $user_id, 'typee_dob', sprintf( '%04d-%02d-%02d', $year, $month, $day ) );
		}

		// Giới tính.
		if ( isset( $_POST['typee_gender'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$gender = sanitize_key( wp_unslash( $_POST['typee_gender'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( in_array( $gender, array( 'male', 'female', 'other' ), true ) ) {
				update_user_meta( $user_id, 'typee_gender', $gender );
			}
		}

		// Layout phím quen thuộc.
		if ( isset( $_POST['typee_fav_layout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$layout = sanitize_key( wp_unslash( $_POST['typee_fav_layout'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$valid  = array_keys( typee_account_keyboard_layouts() );
			if ( in_array( $layout, $valid, true ) ) {
				update_user_meta( $user_id, 'typee_fav_layout', $layout );
			}
		}

		// Trạng thái ưu tiên 2FA SMS OTP.
		if ( isset( $_POST['typee_2fa_present'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$pref = ! empty( $_POST['typee_2fa_sms_pref'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( $user_id, 'typee_2fa_sms_pref', $pref );
		}

		// Tải lên ảnh đại diện (tùy chọn, < 5MB, chỉ JPG/PNG/WebP).
		if ( ! empty( $_FILES['typee_avatar_file']['name'] ) && empty( $_FILES['typee_avatar_file']['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$file = $_FILES['typee_avatar_file']; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
			if ( $size > 5 * 1024 * 1024 ) {
				wc_add_notice( 'Ảnh đại diện vượt quá dung lượng tối đa 5MB.', 'error' );
			} else {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';

				$overrides = array(
					'test_form' => false,
					'mimes'     => array(
						'jpg|jpeg|jpe' => 'image/jpeg',
						'png'          => 'image/png',
						'webp'         => 'image/webp',
					),
				);
				$uploaded = wp_handle_upload( $file, $overrides );
				if ( isset( $uploaded['error'] ) ) {
					wc_add_notice( 'Không thể tải lên ảnh đại diện: ' . esc_html( $uploaded['error'] ), 'error' );
				} elseif ( ! empty( $uploaded['file'] ) && ! empty( $uploaded['url'] ) ) {
					$att_id = wp_insert_attachment(
						array(
							'post_mime_type' => $uploaded['type'],
							'post_title'     => sprintf( 'TYPEE Avatar User %d', $user_id ),
							'post_content'   => '',
							'post_status'    => 'inherit',
						),
						$uploaded['file']
					);
					if ( ! is_wp_error( $att_id ) && $att_id ) {
						$meta = wp_generate_attachment_metadata( $att_id, $uploaded['file'] );
						wp_update_attachment_metadata( $att_id, $meta );
						update_user_meta( $user_id, 'typee_avatar_id', (int) $att_id );
					}
				}
			}
		}

		update_user_meta( $user_id, 'typee_profile_updated', current_time( 'timestamp' ) );
	}
);

/**
 * Dùng ảnh đại diện người dùng đã tải lên (`typee_avatar_id`) cho `get_avatar()` trên toàn bộ trang Tài khoản.
 */
add_filter(
	'get_avatar_data',
	function ( $args, $id_or_email ) {
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = (int) $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Comment && ! empty( $id_or_email->user_id ) ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$u = get_user_by( 'email', $id_or_email );
			if ( $u ) {
				$user_id = (int) $u->ID;
			}
		}

		if ( $user_id > 0 ) {
			$att_id = (int) get_user_meta( $user_id, 'typee_avatar_id', true );
			if ( $att_id > 0 ) {
				$url = wp_get_attachment_image_url( $att_id, 'thumbnail' );
				if ( $url ) {
					$args['url']          = $url;
					$args['found_avatar'] = true;
				}
			}
		}
		return $args;
	},
	10,
	2
);

/**
 * ============================================================================
 * ENDPOINT TEMPLATES & DỮ LIỆU JS CHO KHU VỰC TÀI KHOẢN
 * ============================================================================
 */

add_action(
	'woocommerce_account_saved-builds_endpoint',
	function () {
		if ( function_exists( 'wc_get_template' ) ) {
			wc_get_template( 'myaccount/saved-builds.php' );
			do_action( 'typee_rendered_saved_builds' );
		}
	},
	10
);

add_action(
	'woocommerce_account_wishlist_endpoint',
	function () {
		if ( function_exists( 'wc_get_template' ) ) {
			wc_get_template( 'myaccount/wishlist.php' );
		}
	},
	10
);

/**
 * Truyền cấu hình AJAX, nonce và đường dẫn tới `assets/js/account.js` trên trang Tài khoản.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! wp_script_is( 'typee-account', 'enqueued' ) ) {
			return;
		}
		$user_id         = get_current_user_id();
		$server_wishlist = array();
		$server_builds   = array();

		if ( $user_id > 0 ) {
			$raw_wl = get_user_meta( $user_id, 'typee_wishlist_ids', true );
			if ( is_array( $raw_wl ) ) {
				$server_wishlist = array_values( array_unique( array_map( 'strval', array_filter( array_map( 'absint', $raw_wl ) ) ) ) );
			}
			$raw_builds = get_user_meta( $user_id, 'typee_saved_builds', true );
			if ( is_array( $raw_builds ) ) {
				$server_builds = array_values( $raw_builds );
			}
		}

		wp_localize_script(
			'typee-account',
			'TYPEE_ACCOUNT',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'typee_account_ajax' ),
				'addToCartUrl'   => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'add_to_cart' ) : home_url( '/?wc-ajax=add_to_cart' ),
				'builderUrl'     => function_exists( 'typee_url' ) ? typee_url( 'builder' ) : home_url( '/custom-builder/' ),
				'cartUrl'        => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/gio-hang/' ),
				'catalogUrl'     => function_exists( 'typee_url' ) ? typee_url( 'catalog' ) : home_url( '/cua-hang/' ),
				'compareUrl'     => function_exists( 'typee_url' ) ? typee_url( 'compare' ) : home_url( '/so-sanh/' ),
				'rmaUrl'         => function_exists( 'typee_url' ) ? typee_url( 'rma_new' ) : home_url( '/gui-yeu-cau-rma/' ),
				'trackingUrl'    => function_exists( 'typee_url' ) ? typee_url( 'tracking' ) : home_url( '/tra-cuu-don-hang/' ),
				'bundleRate'     => 0.05,
				'isLoggedIn'     => $user_id > 0,
				'serverWishlist' => $server_wishlist,
				'serverBuilds'   => $server_builds,
			)
		);
	},
	25
);

/**
 * ============================================================================
 * SỔ ĐỊA CHỈ GIAO HÀNG (MY ADDRESS & EDIT ADDRESS) – Figma 82:6066 / 82:6205
 * ============================================================================
 */

/**
 * Danh mục nhãn loại địa chỉ (Nhà riêng, Văn phòng, Studio...).
 *
 * @return array<string, array{label:string, icon:string}>
 */
function typee_account_address_label_options() {
	return array(
		'home'     => array(
			'label' => 'Nhà riêng / Studio',
			'icon'  => 'home_pin',
		),
		'office'   => array(
			'label' => 'Văn phòng làm việc',
			'icon'  => 'location_on',
		),
		'workshop' => array(
			'label' => 'Studio / Modding Lab',
			'icon'  => 'handyman',
		),
		'other'    => array(
			'label' => 'Địa chỉ khác',
			'icon'  => 'location_on',
		),
	);
}

/**
 * Chuẩn hóa chuỗi địa chỉ hiển thị đầy đủ trên thẻ Sổ địa chỉ.
 *
 * @param array<string, string> $addr Bản ghi địa chỉ.
 * @return string
 */
function typee_account_format_address_line( $addr ) {
	$parts = array_filter(
		array(
			isset( $addr['address_1'] ) ? trim( (string) $addr['address_1'] ) : '',
			isset( $addr['address_2'] ) ? trim( (string) $addr['address_2'] ) : '',
			isset( $addr['city'] ) ? trim( (string) $addr['city'] ) : '',
			isset( $addr['state'] ) ? trim( (string) $addr['state'] ) : '',
		)
	);
	return implode( ', ', $parts );
}

/**
 * Lấy danh sách địa chỉ giao hàng & thanh toán đã lưu của user (sắp xếp địa chỉ mặc định lên đầu).
 *
 * @param int $user_id ID người dùng.
 * @return array<int, array<string, mixed>>
 */
function typee_account_get_addresses( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( $user_id <= 0 ) {
		return array();
	}

	$user         = get_userdata( $user_id );
	$fallback_who = $user ? trim( $user->display_name ) : '';
	$billing_tel  = (string) get_user_meta( $user_id, 'billing_phone', true );
	$label_opts   = typee_account_address_label_options();
	$addresses    = array();

	// 1. Địa chỉ giao hàng chuẩn WooCommerce (`shipping`).
	$ship_addr_1 = trim( (string) get_user_meta( $user_id, 'shipping_address_1', true ) );
	$ship_city   = trim( (string) get_user_meta( $user_id, 'shipping_city', true ) );
	if ( '' !== $ship_addr_1 || '' !== $ship_city ) {
		$ship_first = trim( (string) get_user_meta( $user_id, 'shipping_first_name', true ) );
		$ship_last  = trim( (string) get_user_meta( $user_id, 'shipping_last_name', true ) );
		$ship_name  = trim( $ship_first . ' ' . $ship_last );
		if ( '' === $ship_name ) {
			$ship_name = $fallback_who;
		}
		$ship_phone = trim( (string) get_user_meta( $user_id, 'shipping_phone', true ) );
		if ( '' === $ship_phone ) {
			$ship_phone = $billing_tel;
		}
		$label_key = sanitize_key( (string) get_user_meta( $user_id, 'typee_address_label_shipping', true ) );
		if ( ! isset( $label_opts[ $label_key ] ) ) {
			$label_key = 'home';
		}
		$addresses['shipping'] = array(
			'id'         => 'shipping',
			'wc_type'    => 'shipping',
			'label_key'  => $label_key,
			'label'      => $label_opts[ $label_key ]['label'],
			'icon'       => $label_opts[ $label_key ]['icon'],
			'recipient'  => $ship_name,
			'phone'      => $ship_phone,
			'company'    => trim( (string) get_user_meta( $user_id, 'shipping_company', true ) ),
			'address_1'  => $ship_addr_1,
			'address_2'  => trim( (string) get_user_meta( $user_id, 'shipping_address_2', true ) ),
			'city'       => $ship_city,
			'state'      => trim( (string) get_user_meta( $user_id, 'shipping_state', true ) ),
			'postcode'   => trim( (string) get_user_meta( $user_id, 'shipping_postcode', true ) ),
			'country'    => trim( (string) get_user_meta( $user_id, 'shipping_country', true ) ) ?: 'VN',
			'note'       => trim( (string) get_user_meta( $user_id, 'typee_address_note_shipping', true ) ),
			'verified'   => '' !== $ship_phone && $ship_phone === $billing_tel,
			'edit_url'   => function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address', 'shipping' ) : '',
			'type_badge' => 'Giao hàng',
		);
	}

	// 2. Địa chỉ thanh toán chuẩn WooCommerce (`billing`).
	$bill_addr_1 = trim( (string) get_user_meta( $user_id, 'billing_address_1', true ) );
	$bill_city   = trim( (string) get_user_meta( $user_id, 'billing_city', true ) );
	if ( '' !== $bill_addr_1 || '' !== $bill_city ) {
		$bill_first = trim( (string) get_user_meta( $user_id, 'billing_first_name', true ) );
		$bill_last  = trim( (string) get_user_meta( $user_id, 'billing_last_name', true ) );
		$bill_name  = trim( $bill_first . ' ' . $bill_last );
		if ( '' === $bill_name ) {
			$bill_name = $fallback_who;
		}
		$label_key = sanitize_key( (string) get_user_meta( $user_id, 'typee_address_label_billing', true ) );
		if ( ! isset( $label_opts[ $label_key ] ) ) {
			$label_key = 'office';
		}
		$addresses['billing'] = array(
			'id'         => 'billing',
			'wc_type'    => 'billing',
			'label_key'  => $label_key,
			'label'      => $label_opts[ $label_key ]['label'],
			'icon'       => $label_opts[ $label_key ]['icon'],
			'recipient'  => $bill_name,
			'phone'      => $billing_tel,
			'company'    => trim( (string) get_user_meta( $user_id, 'billing_company', true ) ),
			'address_1'  => $bill_addr_1,
			'address_2'  => trim( (string) get_user_meta( $user_id, 'billing_address_2', true ) ),
			'city'       => $bill_city,
			'state'      => trim( (string) get_user_meta( $user_id, 'billing_state', true ) ),
			'postcode'   => trim( (string) get_user_meta( $user_id, 'billing_postcode', true ) ),
			'country'    => trim( (string) get_user_meta( $user_id, 'billing_country', true ) ) ?: 'VN',
			'note'       => trim( (string) get_user_meta( $user_id, 'typee_address_note_billing', true ) ),
			'verified'   => '' !== $billing_tel,
			'edit_url'   => function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address', 'billing' ) : '',
			'type_badge' => 'Thanh toán',
		);
	}

	// 3. Các địa chỉ giao hàng bổ sung (`typee_extra_addresses`).
	$extras = get_user_meta( $user_id, 'typee_extra_addresses', true );
	if ( is_array( $extras ) ) {
		$base_url = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address' ) : '';
		foreach ( $extras as $extra_id => $item ) {
			if ( ! is_array( $item ) || ( empty( $item['address_1'] ) && empty( $item['city'] ) ) ) {
				continue;
			}
			$eid       = sanitize_key( (string) $extra_id );
			$label_key = isset( $item['label_key'] ) ? sanitize_key( (string) $item['label_key'] ) : 'other';
			if ( ! isset( $label_opts[ $label_key ] ) ) {
				$label_key = 'other';
			}
			$phone = isset( $item['phone'] ) ? trim( (string) $item['phone'] ) : '';

			$addresses[ $eid ] = array(
				'id'         => $eid,
				'wc_type'    => 'extra',
				'label_key'  => $label_key,
				'label'      => ! empty( $item['custom_label'] ) ? (string) $item['custom_label'] : $label_opts[ $label_key ]['label'],
				'icon'       => $label_opts[ $label_key ]['icon'],
				'recipient'  => isset( $item['recipient'] ) ? trim( (string) $item['recipient'] ) : $fallback_who,
				'phone'      => $phone,
				'company'    => isset( $item['company'] ) ? trim( (string) $item['company'] ) : '',
				'address_1'  => isset( $item['address_1'] ) ? trim( (string) $item['address_1'] ) : '',
				'address_2'  => isset( $item['address_2'] ) ? trim( (string) $item['address_2'] ) : '',
				'city'       => isset( $item['city'] ) ? trim( (string) $item['city'] ) : '',
				'state'      => isset( $item['state'] ) ? trim( (string) $item['state'] ) : '',
				'postcode'   => isset( $item['postcode'] ) ? trim( (string) $item['postcode'] ) : '',
				'country'    => isset( $item['country'] ) ? trim( (string) $item['country'] ) : 'VN',
				'note'       => isset( $item['note'] ) ? trim( (string) $item['note'] ) : '',
				'verified'   => '' !== $phone && $phone === $billing_tel,
				'edit_url'   => add_query_arg(
					array(
						'addr_action' => 'edit',
						'addr_id'     => $eid,
					),
					$base_url
				),
				'type_badge' => 'Giao hàng phụ',
			);
		}
	}

	if ( empty( $addresses ) ) {
		return array();
	}

	$default_key = sanitize_key( (string) get_user_meta( $user_id, 'typee_default_address', true ) );
	if ( ! isset( $addresses[ $default_key ] ) ) {
		$default_key = isset( $addresses['shipping'] ) ? 'shipping' : (string) array_key_first( $addresses );
	}

	$ordered = array();
	if ( isset( $addresses[ $default_key ] ) ) {
		$addresses[ $default_key ]['is_default'] = true;
		$ordered[]                               = $addresses[ $default_key ];
		unset( $addresses[ $default_key ] );
	}
	foreach ( $addresses as $addr ) {
		$addr['is_default'] = false;
		$ordered[]          = $addr;
	}

	return $ordered;
}

/**
 * Đồng bộ địa chỉ được chọn làm mặc định sang meta `shipping_*` của WooCommerce để Checkout & e-RMA tự điền.
 *
 * @param int                  $user_id ID user.
 * @param array<string, mixed> $addr    Dữ liệu địa chỉ.
 */
function typee_account_sync_default_to_wc_shipping( $user_id, $addr ) {
	if ( $user_id <= 0 || empty( $addr ) || 'shipping' === ( $addr['id'] ?? '' ) ) {
		return;
	}
	$recipient = trim( (string) ( $addr['recipient'] ?? '' ) );
	$parts     = '' !== $recipient ? preg_split( '/\s+/', $recipient ) : array();
	$last      = count( $parts ) > 1 ? array_pop( $parts ) : '';
	$first     = implode( ' ', $parts );

	if ( '' !== $first ) {
		update_user_meta( $user_id, 'shipping_first_name', $first );
	}
	update_user_meta( $user_id, 'shipping_last_name', $last );
	update_user_meta( $user_id, 'shipping_company', (string) ( $addr['company'] ?? '' ) );
	update_user_meta( $user_id, 'shipping_address_1', (string) ( $addr['address_1'] ?? '' ) );
	update_user_meta( $user_id, 'shipping_address_2', (string) ( $addr['address_2'] ?? '' ) );
	update_user_meta( $user_id, 'shipping_city', (string) ( $addr['city'] ?? '' ) );
	update_user_meta( $user_id, 'shipping_state', (string) ( $addr['state'] ?? '' ) );
	update_user_meta( $user_id, 'shipping_postcode', (string) ( $addr['postcode'] ?? '' ) );
	update_user_meta( $user_id, 'shipping_country', (string) ( $addr['country'] ?? 'VN' ) );
	if ( ! empty( $addr['phone'] ) ) {
		update_user_meta( $user_id, 'shipping_phone', (string) $addr['phone'] );
	}
}

/**
 * Xử lý các thao tác POST trên Sổ địa chỉ (Đặt mặc định, Xóa, Thêm / Sửa địa chỉ phụ).
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_user_logged_in() || empty( $_POST['typee_address_book_action'] ) ) {
			return;
		}
		if ( empty( $_POST['typee_address_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['typee_address_nonce'] ) ), 'typee_address_book' ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( 'Phiên làm việc đã hết hạn, vui lòng thử lại.', 'error' );
			}
			return;
		}

		$user_id   = get_current_user_id();
		$action    = sanitize_key( wp_unslash( $_POST['typee_address_book_action'] ) );
		$redirect  = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) ) : home_url( '/tai-khoan/' );
		$addresses = typee_account_get_addresses( $user_id );
		$by_id     = array();
		foreach ( $addresses as $a ) {
			$by_id[ $a['id'] ] = $a;
		}

		if ( 'set_default' === $action ) {
			$target_id = isset( $_POST['addr_id'] ) ? sanitize_key( wp_unslash( $_POST['addr_id'] ) ) : '';
			if ( isset( $by_id[ $target_id ] ) ) {
				update_user_meta( $user_id, 'typee_default_address', $target_id );
				typee_account_sync_default_to_wc_shipping( $user_id, $by_id[ $target_id ] );
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( 'Đã cập nhật địa chỉ giao hàng mặc định.', 'success' );
				}
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( 'delete' === $action ) {
			$target_id = isset( $_POST['addr_id'] ) ? sanitize_key( wp_unslash( $_POST['addr_id'] ) ) : '';
			if ( 'billing' === $target_id || 'shipping' === $target_id ) {
				$prefix = $target_id . '_';
				foreach ( array( 'company', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $f ) {
					delete_user_meta( $user_id, $prefix . $f );
				}
				delete_user_meta( $user_id, 'typee_address_note_' . $target_id );
				delete_user_meta( $user_id, 'typee_address_label_' . $target_id );
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( 'Đã xóa địa chỉ khỏi sổ địa chỉ của bạn.', 'success' );
				}
			} elseif ( '' !== $target_id ) {
				$extras = get_user_meta( $user_id, 'typee_extra_addresses', true );
				if ( is_array( $extras ) && isset( $extras[ $target_id ] ) ) {
					unset( $extras[ $target_id ] );
					update_user_meta( $user_id, 'typee_extra_addresses', $extras );
					if ( function_exists( 'wc_add_notice' ) ) {
						wc_add_notice( 'Đã xóa địa chỉ khỏi sổ địa chỉ của bạn.', 'success' );
					}
				}
			}
			if ( get_user_meta( $user_id, 'typee_default_address', true ) === $target_id ) {
				delete_user_meta( $user_id, 'typee_default_address' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( 'save_extra' === $action ) {
			$edit_id    = isset( $_POST['addr_id'] ) ? sanitize_key( wp_unslash( $_POST['addr_id'] ) ) : '';
			$recipient  = isset( $_POST['addr_recipient'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_recipient'] ) ) ) : '';
			$phone      = isset( $_POST['addr_phone'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_phone'] ) ) ) : '';
			$label_key  = isset( $_POST['addr_label_key'] ) ? sanitize_key( wp_unslash( $_POST['addr_label_key'] ) ) : 'home';
			$company    = isset( $_POST['addr_company'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_company'] ) ) ) : '';
			$address_1  = isset( $_POST['addr_address_1'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_address_1'] ) ) ) : '';
			$address_2  = isset( $_POST['addr_address_2'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_address_2'] ) ) ) : '';
			$city       = isset( $_POST['addr_city'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_city'] ) ) ) : '';
			$state      = isset( $_POST['addr_state'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_state'] ) ) ) : '';
			$postcode   = isset( $_POST['addr_postcode'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['addr_postcode'] ) ) ) : '';
			$note       = isset( $_POST['addr_note'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['addr_note'] ) ) ) : '';
			$is_default = ! empty( $_POST['addr_set_default'] );

			if ( '' === $recipient || '' === $phone || '' === $address_1 || '' === $city ) {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( 'Vui lòng điền đầy đủ Họ tên người nhận, Số điện thoại, Địa chỉ chi tiết và Tỉnh/Thành phố.', 'error' );
				}
				return;
			}

			$label_opts = typee_account_address_label_options();
			if ( ! isset( $label_opts[ $label_key ] ) ) {
				$label_key = 'home';
			}

			$record = array(
				'recipient' => $recipient,
				'phone'     => $phone,
				'label_key' => $label_key,
				'company'   => $company,
				'address_1' => $address_1,
				'address_2' => $address_2,
				'city'      => $city,
				'state'     => $state,
				'postcode'  => $postcode,
				'country'   => 'VN',
				'note'      => $note,
			);

			// Nếu user chưa có địa chỉ `shipping` WooCommerce, lưu trực tiếp vào `shipping` để tương thích 100% Checkout.
			$existing_ship = trim( (string) get_user_meta( $user_id, 'shipping_address_1', true ) );
			if ( '' === $edit_id && '' === $existing_ship ) {
				$parts = preg_split( '/\s+/', $recipient );
				$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
				$first = implode( ' ', $parts );

				update_user_meta( $user_id, 'shipping_first_name', $first ?: $recipient );
				update_user_meta( $user_id, 'shipping_last_name', $last );
				update_user_meta( $user_id, 'shipping_company', $company );
				update_user_meta( $user_id, 'shipping_address_1', $address_1 );
				update_user_meta( $user_id, 'shipping_address_2', $address_2 );
				update_user_meta( $user_id, 'shipping_city', $city );
				update_user_meta( $user_id, 'shipping_state', $state );
				update_user_meta( $user_id, 'shipping_postcode', $postcode );
				update_user_meta( $user_id, 'shipping_country', 'VN' );
				update_user_meta( $user_id, 'shipping_phone', $phone );
				update_user_meta( $user_id, 'typee_address_label_shipping', $label_key );
				update_user_meta( $user_id, 'typee_address_note_shipping', $note );
				if ( $is_default || empty( $addresses ) ) {
					update_user_meta( $user_id, 'typee_default_address', 'shipping' );
				}
			} else {
				$extras = get_user_meta( $user_id, 'typee_extra_addresses', true );
				if ( ! is_array( $extras ) ) {
					$extras = array();
				}
				$new_id            = ( '' !== $edit_id && isset( $extras[ $edit_id ] ) ) ? $edit_id : 'addr_' . substr( md5( $user_id . microtime( true ) . wp_rand() ), 0, 8 );
				$extras[ $new_id ] = $record;
				update_user_meta( $user_id, 'typee_extra_addresses', $extras );

				if ( $is_default || empty( $addresses ) ) {
					update_user_meta( $user_id, 'typee_default_address', $new_id );
					typee_account_sync_default_to_wc_shipping( $user_id, array_merge( $record, array( 'id' => $new_id ) ) );
				}
			}

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( '' !== $edit_id ? 'Đã cập nhật địa chỉ giao hàng.' : 'Đã thêm địa chỉ giao hàng mới vào sổ địa chỉ.', 'success' );
			}
			wp_safe_redirect( $redirect );
			exit;
		}
	},
	10
);

/**
 * Lưu thêm loại địa chỉ, ghi chú vận chuyển và tùy chọn đặt mặc định khi lưu form `form-edit-address.php`.
 */
add_action(
	'woocommerce_customer_save_address',
	function ( $user_id, $load_address ) {
		if ( ! in_array( $load_address, array( 'billing', 'shipping' ), true ) ) {
			return;
		}
		if ( isset( $_POST['typee_address_label'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$label_key = sanitize_key( wp_unslash( $_POST['typee_address_label'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$opts      = typee_account_address_label_options();
			if ( isset( $opts[ $label_key ] ) ) {
				update_user_meta( $user_id, 'typee_address_label_' . $load_address, $label_key );
			}
		}
		if ( isset( $_POST['typee_address_note'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$note = trim( sanitize_textarea_field( wp_unslash( $_POST['typee_address_note'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( $user_id, 'typee_address_note_' . $load_address, $note );
		}
		if ( 'shipping' === $load_address && isset( $_POST['typee_shipping_phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$tel = trim( sanitize_text_field( wp_unslash( $_POST['typee_shipping_phone'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( $user_id, 'shipping_phone', $tel );
		}
		if ( ! empty( $_POST['typee_set_default_address'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( $user_id, 'typee_default_address', $load_address );
		}
	},
	10,
	2
);

/**
 * Việt hóa nhãn & placeholder các trường địa chỉ trong `form-edit-address.php`.
 */
add_filter(
	'woocommerce_address_to_edit',
	function ( $address, $load_address ) {
		$prefix = $load_address . '_';
		$labels = array(
			$prefix . 'first_name' => array( 'Họ & tên đệm', 'Nguyễn Minh' ),
			$prefix . 'last_name'  => array( 'Tên', 'Khoa' ),
			$prefix . 'company'    => array( 'Công ty / Studio (tùy chọn)', 'TYPEE Modding Studio' ),
			$prefix . 'address_1'  => array( 'Số nhà, tên đường, phường/xã', 'Số 42 Triều Khúc, P. Thanh Xuân Nam' ),
			$prefix . 'address_2'  => array( 'Tòa nhà, căn hộ, quận/huyện (tùy chọn)', 'Tầng 4, Tòa nhà Tech Hub, Q. Thanh Xuân' ),
			$prefix . 'city'       => array( 'Tỉnh / Thành phố', 'Hà Nội' ),
			$prefix . 'state'      => array( 'Quận / Huyện / Khu vực', 'Quận Thanh Xuân' ),
			$prefix . 'postcode'   => array( 'Mã bưu chính (tùy chọn)', '100000' ),
			$prefix . 'phone'      => array( 'Số điện thoại nhận hàng', '0912 345 678' ),
			$prefix . 'email'      => array( 'Email nhận thông báo đơn hàng', 'email@domain.com' ),
		);
		foreach ( $labels as $key => $pair ) {
			if ( isset( $address[ $key ] ) ) {
				$address[ $key ]['label']       = $pair[0];
				$address[ $key ]['placeholder'] = $pair[1];
			}
		}
		if ( isset( $address[ $prefix . 'postcode' ] ) ) {
			$address[ $prefix . 'postcode' ]['required'] = false;
		}
		return $address;
	},
	20,
	2
);

/**
 * ============================================================================
 * AJAX: CẤU HÌNH BUILDER ĐÃ LƯU & DANH SÁCH SẢN PHẨM YÊU THÍCH
 * ============================================================================
 */

/**
 * Chuẩn hóa thông tin 1 sản phẩm WooCommerce cho Wishlist & Saved Builds.
 *
 * @param WC_Product $product Sản phẩm WooCommerce.
 * @return array<string, mixed>
 */
function typee_account_serialize_product( $product ) {
	$pid          = $product->get_id();
	$img_id       = $product->get_image_id();
	$img_url      = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : '';
	if ( ! $img_url && function_exists( 'wc_placeholder_img_src' ) ) {
		$img_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	$terms    = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) );
	$cat_name = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : 'Phím cơ Custom';

	$is_variable = $product->is_type( 'variable' );
	if ( $is_variable ) {
		$price   = (float) $product->get_variation_price( 'min', true );
		$regular = (float) $product->get_variation_regular_price( 'min', true );
	} else {
		$price   = (float) $product->get_price();
		$regular = (float) $product->get_regular_price();
	}

	$save_amount = ( $regular > $price && $price > 0 ) ? ( $regular - $price ) : 0;
	$sale_pct    = ( $regular > 0 && $save_amount > 0 ) ? (int) round( ( $save_amount / $regular ) * 100 ) : 0;

	$specs_raw = function_exists( 'typee_product_specs' ) ? typee_product_specs( $product ) : array();
	$specs     = array();
	if ( is_array( $specs_raw ) ) {
		foreach ( $specs_raw as $k => $v ) {
			if ( is_string( $v ) && '' !== trim( $v ) ) {
				$specs[] = trim( $v );
			}
			if ( count( $specs ) >= 3 ) {
				break;
			}
		}
	}

	$in_stock  = $product->is_in_stock();
	$stock_qty = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;

	return array(
		'id'           => $pid,
		'name'         => $product->get_name(),
		'url'          => get_permalink( $pid ),
		'img'          => $img_url,
		'category'     => $cat_name,
		'sku'          => (string) $product->get_sku(),
		'rating'       => (float) $product->get_average_rating(),
		'review_count' => (int) $product->get_review_count(),
		'specs'        => $specs,
		'price'        => $price,
		'regular'      => $regular,
		'price_fmt'    => function_exists( 'typee_money' ) ? typee_money( $price ) : wc_price( $price ),
		'regular_fmt'  => $regular > $price ? ( function_exists( 'typee_money' ) ? typee_money( $regular ) : wc_price( $regular ) ) : '',
		'save_amount'  => $save_amount,
		'save_fmt'     => $save_amount > 0 && function_exists( 'typee_money' ) ? typee_money( $save_amount ) : '',
		'sale_pct'     => $sale_pct,
		'in_stock'     => $in_stock,
		'stock_qty'    => $stock_qty,
		'is_variable'  => $is_variable,
		'purchasable'  => $product->is_purchasable(),
	);
}

/**
 * AJAX: Lấy danh sách sản phẩm Wishlist đầy đủ theo mảng ID từ localStorage + user meta.
 */
add_action(
	'wp_ajax_typee_account_wishlist_products',
	function () {
		check_ajax_referer( 'typee_account_ajax', 'nonce' );

		$user_id = get_current_user_id();
		$raw_ids = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $raw_ids ) ) {
			$decoded = json_decode( $raw_ids, true );
			$raw_ids = is_array( $decoded ) ? $decoded : explode( ',', $raw_ids );
		}

		$ids = array();
		if ( is_array( $raw_ids ) ) {
			foreach ( $raw_ids as $rid ) {
				$pid = absint( $rid );
				if ( $pid > 0 && ! in_array( $pid, $ids, true ) ) {
					$ids[] = $pid;
				}
				if ( count( $ids ) >= 50 ) {
					break;
				}
			}
		}

		// Đồng bộ ngược lại `user_meta` để badge sidebar và đa thiết bị luôn khớp.
		if ( $user_id > 0 && ! empty( $_POST['sync'] ) ) {
			update_user_meta( $user_id, 'typee_wishlist_ids', $ids );
		}

		$items = array();
		if ( function_exists( 'wc_get_product' ) ) {
			foreach ( $ids as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product || 'publish' !== $product->get_status() ) {
					continue;
				}
				$items[] = typee_account_serialize_product( $product );
			}
		}

		wp_send_json_success(
			array(
				'items' => $items,
				'count' => count( $items ),
			)
		);
	}
);

/**
 * AJAX: Đồng bộ nhanh danh sách ID Wishlist lên `user_meta`.
 */
add_action(
	'wp_ajax_typee_account_sync_wishlist',
	function () {
		check_ajax_referer( 'typee_account_ajax', 'nonce' );
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$raw_ids = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $raw_ids ) ) {
			$decoded = json_decode( $raw_ids, true );
			$raw_ids = is_array( $decoded ) ? $decoded : array();
		}
		$clean = array();
		if ( is_array( $raw_ids ) ) {
			foreach ( $raw_ids as $rid ) {
				$pid = absint( $rid );
				if ( $pid > 0 && ! in_array( $pid, $clean, true ) ) {
					$clean[] = $pid;
				}
				if ( count( $clean ) >= 50 ) {
					break;
				}
			}
		}
		update_user_meta( $user_id, 'typee_wishlist_ids', $clean );
		wp_send_json_success( array( 'count' => count( $clean ) ) );
	}
);

/**
 * AJAX: Trả dữ liệu danh mục linh kiện Custom Builder (`typee_builder_catalog`) cho trang Cấu hình đã lưu.
 */
add_action(
	'wp_ajax_typee_account_builder_catalog',
	function () {
		check_ajax_referer( 'typee_account_ajax', 'nonce' );

		$catalog = function_exists( 'typee_builder_catalog' ) ? typee_builder_catalog() : array(
			'kits'     => array(),
			'switches' => array(),
			'keycaps'  => array(),
		);

		$by_id = array();
		foreach ( array( 'kits', 'switches', 'keycaps' ) as $group ) {
			if ( empty( $catalog[ $group ] ) || ! is_array( $catalog[ $group ] ) ) {
				continue;
			}
			foreach ( $catalog[ $group ] as $entry ) {
				if ( empty( $entry['id'] ) ) {
					continue;
				}
				$entry['group']         = $group;
				$by_id[ (int) $entry['id'] ] = $entry;
			}
		}

		// Hỗ trợ tra cứu thêm ID sản phẩm ngoài catalog nếu bản build cũ tham chiếu.
		$extra_ids = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_string( $extra_ids ) ) {
			$decoded   = json_decode( $extra_ids, true );
			$extra_ids = is_array( $decoded ) ? $decoded : array();
		}
		if ( is_array( $extra_ids ) && function_exists( 'wc_get_product' ) ) {
			foreach ( $extra_ids as $rid ) {
				$pid = absint( $rid );
				if ( $pid > 0 && ! isset( $by_id[ $pid ] ) ) {
					$prod = wc_get_product( $pid );
					if ( $prod ) {
						$by_id[ $pid ] = typee_account_serialize_product( $prod );
					}
				}
			}
		}

		wp_send_json_success(
			array(
				'by_id' => $by_id,
			)
		);
	}
);

/**
 * AJAX: Đồng bộ danh sách cấu hình Builder đã lưu (`typee_builds`) lên `user_meta` để lưu bền vững theo tài khoản.
 */
add_action(
	'wp_ajax_typee_account_sync_builds',
	function () {
		check_ajax_referer( 'typee_account_ajax', 'nonce' );
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$raw = isset( $_POST['builds'] ) ? wp_unslash( $_POST['builds'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$arr = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		if ( ! is_array( $arr ) ) {
			$arr = array();
		}

		$clean = array();
		foreach ( array_slice( $arr, 0, 30 ) as $b ) {
			if ( ! is_array( $b ) || empty( $b['id'] ) ) {
				continue;
			}
			$state   = isset( $b['state'] ) && is_array( $b['state'] ) ? $b['state'] : array();
			$parts   = isset( $b['parts'] ) && is_array( $b['parts'] ) ? $b['parts'] : array();
			$clean[] = array(
				'id'       => sanitize_text_field( (string) $b['id'] ),
				'name'     => sanitize_text_field( (string) ( $b['name'] ?? 'Cấu hình bàn phím Custom' ) ),
				'note'     => sanitize_textarea_field( (string) ( $b['note'] ?? '' ) ),
				'layout'   => sanitize_text_field( (string) ( $b['layout'] ?? '' ) ),
				'total'    => (float) ( $b['total'] ?? 0 ),
				'subtotal' => (float) ( $b['subtotal'] ?? 0 ),
				'created'  => sanitize_text_field( (string) ( $b['created'] ?? '' ) ),
				'updated'  => sanitize_text_field( (string) ( $b['updated'] ?? '' ) ),
				'state'    => array(
					'kit'       => absint( $state['kit'] ?? 0 ),
					'kitVar'    => absint( $state['kitVar'] ?? 0 ),
					'sw'        => absint( $state['sw'] ?? 0 ),
					'swVar'     => absint( $state['swVar'] ?? 0 ),
					'packs'     => max( 1, absint( $state['packs'] ?? 1 ) ),
					'keycap'    => absint( $state['keycap'] ?? 0 ),
					'keycapVar' => absint( $state['keycapVar'] ?? 0 ),
				),
				'parts'    => array(
					'kit'    => sanitize_text_field( (string) ( $parts['kit'] ?? '' ) ),
					'sw'     => sanitize_text_field( (string) ( $parts['sw'] ?? '' ) ),
					'keycap' => sanitize_text_field( (string) ( $parts['keycap'] ?? '' ) ),
				),
			);
		}

		update_user_meta( $user_id, 'typee_saved_builds', $clean );
		wp_send_json_success( array( 'count' => count( $clean ) ) );
	}
);
