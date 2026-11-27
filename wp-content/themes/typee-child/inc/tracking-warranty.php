<?php
/**
 * Theo Dõi Đơn Hàng & Tra Cứu / Yêu Cầu Bảo Hành e-RMA – Figma 82:3293.
 *
 * - Đăng ký Custom Post Type `typee_warranty` quản lý trực tiếp trong WP-Admin.
 * - AJAX `typee_lookup_order`: tra cứu đơn hàng WooCommerce thật (xác thực Order ID + Email/SĐT)
 *   hoặc tra cứu mã ticket `RMA-*`, tự tính hạn bảo hành theo từng sản phẩm.
 * - AJAX `typee_submit_warranty`: gửi yêu cầu bảo hành e-RMA thật, lưu vào CPT `typee_warranty`.
 * - Shortcode `[typee_tracking_warranty]` để nhúng linh hoạt vào bất kỳ trang WP nào.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Danh sách trạng thái xử lý yêu cầu bảo hành e-RMA.
 */
function typee_warranty_statuses() {
	return array(
		'pending'    => array( 'label' => 'Đã tiếp nhận', 'tone' => 'warning' ),
		'inspecting' => array( 'label' => 'Đang kiểm định KCS', 'tone' => 'info' ),
		'repairing'  => array( 'label' => 'Đang sửa chữa / Đổi mới', 'tone' => 'accent' ),
		'completed'  => array( 'label' => 'Hoàn tất & Đã trả hàng', 'tone' => 'success' ),
		'rejected'   => array( 'label' => 'Từ chối bảo hành', 'tone' => 'danger' ),
	);
}

/**
 * 1. Đăng ký Custom Post Type `typee_warranty` hiển thị trong WP-Admin.
 */
add_action( 'init', function () {
	register_post_type(
		'typee_warranty',
		array(
			'labels'          => array(
				'name'               => 'Bảo hành e-RMA',
				'singular_name'      => 'Yêu cầu bảo hành',
				'menu_name'          => 'Bảo hành e-RMA',
				'all_items'          => 'Tất cả yêu cầu e-RMA',
				'add_new'            => 'Thêm yêu cầu mới',
				'add_new_item'       => 'Thêm yêu cầu bảo hành e-RMA',
				'edit_item'          => 'Chi tiết yêu cầu e-RMA',
				'view_item'          => 'Xem yêu cầu e-RMA',
				'search_items'       => 'Tìm yêu cầu e-RMA',
				'not_found'          => 'Chưa có yêu cầu bảo hành nào.',
				'not_found_in_trash' => 'Không có yêu cầu nào trong thùng rác.',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 56,
			'menu_icon'       => 'dashicons-shield-alt',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);
} );

/**
 * Cột hiển thị tùy chỉnh trong danh sách `typee_warranty` ở WP-Admin.
 */
add_filter( 'manage_typee_warranty_posts_columns', function ( $columns ) {
	return array(
		'cb'           => $columns['cb'],
		'title'        => 'Mã e-RMA & Tiêu đề',
		'rma_order'    => 'Mã đơn hàng',
		'rma_product'  => 'Sản phẩm',
		'rma_customer' => 'Khách hàng / Liên hệ',
		'rma_issue'    => 'Phân loại lỗi',
		'rma_status'   => 'Trạng thái xử lý',
		'date'         => 'Ngày gửi',
	);
} );

add_action( 'manage_typee_warranty_posts_custom_column', function ( $column, $post_id ) {
	$statuses = typee_warranty_statuses();
	switch ( $column ) {
		case 'rma_order':
			$oid = (int) get_post_meta( $post_id, '_rma_order_id', true );
			if ( $oid ) {
				$edit_url = admin_url( 'post.php?post=' . $oid . '&action=edit' );
				printf( '<a href="%s"><strong>#TY-%d</strong></a>', esc_url( $edit_url ), $oid );
			}
			break;
		case 'rma_product':
			echo esc_html( get_post_meta( $post_id, '_rma_product_name', true ) );
			break;
		case 'rma_customer':
			echo '<strong>' . esc_html( get_post_meta( $post_id, '_rma_customer_name', true ) ) . '</strong><br />';
			echo '<small>' . esc_html( get_post_meta( $post_id, '_rma_contact', true ) ) . '</small>';
			break;
		case 'rma_issue':
			echo esc_html( get_post_meta( $post_id, '_rma_issue_type', true ) );
			break;
		case 'rma_status':
			$st   = get_post_meta( $post_id, '_rma_status', true );
			$info = isset( $statuses[ $st ] ) ? $statuses[ $st ] : $statuses['pending'];
			echo '<span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#efecf5;font-weight:600;font-size:12px;">' . esc_html( $info['label'] ) . '</span>';
			break;
	}
}, 10, 2 );

/**
 * Metabox quản lý chi tiết & cập nhật trạng thái trong WP-Admin.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'typee_warranty_details',
		'Thông tin chi tiết yêu cầu bảo hành e-RMA',
		'typee_warranty_render_metabox',
		'typee_warranty',
		'normal',
		'high'
	);
} );

function typee_warranty_render_metabox( $post ) {
	wp_nonce_field( 'typee_warranty_admin_save', 'typee_warranty_admin_nonce' );
	$code     = get_post_meta( $post->ID, '_rma_code', true );
	$oid      = (int) get_post_meta( $post->ID, '_rma_order_id', true );
	$prod     = get_post_meta( $post->ID, '_rma_product_name', true );
	$cust     = get_post_meta( $post->ID, '_rma_customer_name', true );
	$contact  = get_post_meta( $post->ID, '_rma_contact', true );
	$issue    = get_post_meta( $post->ID, '_rma_issue_type', true );
	$status   = get_post_meta( $post->ID, '_rma_status', true );
	$note     = get_post_meta( $post->ID, '_rma_admin_note', true );
	$statuses = typee_warranty_statuses();
	?>
	<table class="form-table">
		<tr><th>Mã Ticket e-RMA</th><td><strong><?php echo esc_html( $code ? $code : 'RMA-' . $post->ID ); ?></strong></td></tr>
		<tr><th>Mã đơn hàng</th><td><strong>#TY-<?php echo (int) $oid; ?></strong></td></tr>
		<tr><th>Sản phẩm</th><td><?php echo esc_html( $prod ); ?></td></tr>
		<tr><th>Khách hàng</th><td><?php echo esc_html( $cust ); ?> — <?php echo esc_html( $contact ); ?></td></tr>
		<tr><th>Phân loại lỗi</th><td><?php echo esc_html( $issue ); ?></td></tr>
		<tr>
			<th><label for="typee_rma_status">Trạng thái xử lý</label></th>
			<td>
				<select id="typee_rma_status" name="typee_rma_status">
					<?php foreach ( $statuses as $k => $info ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status ? $status : 'pending', $k ); ?>><?php echo esc_html( $info['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="typee_rma_admin_note">Phản hồi từ KTV Lab</label></th>
			<td><textarea id="typee_rma_admin_note" name="typee_rma_admin_note" rows="3" class="large-text"><?php echo esc_textarea( $note ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

add_action( 'save_post_typee_warranty', function ( $post_id ) {
	if ( ! isset( $_POST['typee_warranty_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['typee_warranty_admin_nonce'] ) ), 'typee_warranty_admin_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['typee_rma_status'] ) ) {
		$st = sanitize_key( wp_unslash( $_POST['typee_rma_status'] ) );
		if ( isset( typee_warranty_statuses()[ $st ] ) ) {
			update_post_meta( $post_id, '_rma_status', $st );
		}
	}
	if ( isset( $_POST['typee_rma_admin_note'] ) ) {
		update_post_meta( $post_id, '_rma_admin_note', sanitize_textarea_field( wp_unslash( $_POST['typee_rma_admin_note'] ) ) );
	}
} );

/**
 * 2. Helpers xác thực đơn hàng & tính toán thời hạn bảo hành thật.
 */
function typee_tracking_normalize_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( 0 === strpos( $digits, '84' ) && strlen( $digits ) >= 10 ) {
		$digits = '0' . substr( $digits, 2 );
	}
	return $digits;
}

function typee_tracking_parse_order_id( $raw ) {
	$clean = strtoupper( trim( (string) $raw ) );
	$clean = preg_replace( '/^(#?TY-|#?DH-|#)/', '', $clean );
	return absint( preg_replace( '/\D+/', '', $clean ) );
}

function typee_tracking_verify_order_contact( $order, $contact ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	// Nếu user đã đăng nhập và là chủ đơn hàng → hợp lệ ngay.
	if ( is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ) {
		return true;
	}

	$contact = trim( (string) $contact );
	if ( '' === $contact ) {
		return false;
	}

	$order_email = strtolower( trim( (string) $order->get_billing_email() ) );
	if ( '' !== $order_email && strtolower( $contact ) === $order_email ) {
		return true;
	}

	$order_phone = typee_tracking_normalize_phone( $order->get_billing_phone() );
	$input_phone = typee_tracking_normalize_phone( $contact );
	if ( '' !== $order_phone && '' !== $input_phone && $order_phone === $input_phone ) {
		return true;
	}

	return false;
}

/**
 * Tính toán hạn bảo hành cho một sản phẩm trong đơn hàng.
 *
 * @param WC_Order        $order   Đơn hàng.
 * @param WC_Product|null $product Sản phẩm.
 * @return array{months:int,startDate:string,expiryDate:string,isActive:bool,daysLeft:int,label:string}
 */
function typee_tracking_calc_warranty( $order, $product = null ) {
	$months = 12;
	if ( $product instanceof WC_Product ) {
		$pid    = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$custom = (int) get_post_meta( $pid, '_typee_warranty_months', true );
		if ( ! $custom ) {
			$custom = (int) get_post_meta( $pid, 'warranty_months', true );
		}
		if ( $custom > 0 ) {
			$months = $custom;
		}
	}

	$base_dt  = $order->get_date_completed() ? $order->get_date_completed() : $order->get_date_created();
	$start_ts = $base_dt ? $base_dt->getTimestamp() : time();
	$exp_ts   = strtotime( '+' . $months . ' months', $start_ts );
	$now      = time();
	$status   = $order->get_status();
	$invalid  = in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true );

	$days_left = (int) ceil( ( $exp_ts - $now ) / DAY_IN_SECONDS );
	$is_active = ! $invalid && $days_left > 0;

	if ( $invalid ) {
		$label = 'Không áp dụng (Đơn đã hủy)';
	} elseif ( $is_active ) {
		$label = sprintf( 'Còn bảo hành (%d ngày • đến %s)', $days_left, wp_date( 'd/m/Y', $exp_ts ) );
	} else {
		$label = sprintf( 'Hết hạn bảo hành (%s)', wp_date( 'd/m/Y', $exp_ts ) );
	}

	return array(
		'months'     => $months,
		'startDate'  => wp_date( 'd/m/Y', $start_ts ),
		'expiryDate' => wp_date( 'd/m/Y', $exp_ts ),
		'isActive'   => $is_active,
		'daysLeft'   => max( 0, $days_left ),
		'label'      => $label,
	);
}

/**
 * Chuẩn hóa dữ liệu đơn hàng WC_Order sang payload JSON sạch cho frontend.
 */
function typee_tracking_serialize_order( $order ) {
	$status = $order->get_status();

	$status_map = array(
		'pending'    => array( 'label' => 'Chờ xác nhận', 'step' => 1, 'tone' => 'warning' ),
		'on-hold'    => array( 'label' => 'Chờ xác nhận thanh toán', 'step' => 1, 'tone' => 'warning' ),
		'processing' => array( 'label' => 'Đang xử lý & Kiểm định KCS', 'step' => 2, 'tone' => 'accent' ),
		'shipping'   => array( 'label' => 'Đang giao hàng', 'step' => 3, 'tone' => 'accent' ),
		'completed'  => array( 'label' => 'Hoàn thành', 'step' => 4, 'tone' => 'success' ),
		'cancelled'  => array( 'label' => 'Đã hủy', 'step' => 0, 'tone' => 'danger' ),
		'failed'     => array( 'label' => 'Thanh toán thất bại', 'step' => 0, 'tone' => 'danger' ),
		'refunded'   => array( 'label' => 'Đã hoàn tiền', 'step' => 0, 'tone' => 'danger' ),
	);
	$st_info = isset( $status_map[ $status ] ) ? $status_map[ $status ] : array( 'label' => wc_get_order_status_name( $status ), 'step' => 2, 'tone' => 'accent' );

	$created_dt = $order->get_date_created();
	$items_out  = array();
	foreach ( $order->get_items() as $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			continue;
		}
		$prod    = $item->get_product();
		$pid     = $item->get_product_id();
		$img_id  = $prod ? $prod->get_image_id() : 0;
		$thumb   = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
		$warr    = typee_tracking_calc_warranty( $order, $prod );

		$items_out[] = array(
			'productId' => (int) $pid,
			'name'      => $item->get_name(),
			'sku'       => $prod ? $prod->get_sku() : '',
			'qty'       => (int) $item->get_quantity(),
			'thumb'     => $thumb,
			'total'     => wp_strip_all_tags( $order->get_formatted_line_subtotal( $item ) ),
			'url'       => ( $prod && $prod->is_visible() ) ? $prod->get_permalink() : '',
			'warranty'  => $warr,
		);
	}

	// Các ticket e-RMA đã tạo cho đơn hàng này.
	$rma_posts = get_posts(
		array(
			'post_type'      => 'typee_warranty',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'meta_key'       => '_rma_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $order->get_id(), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$statuses  = typee_warranty_statuses();
	$tickets   = array();
	foreach ( $rma_posts as $rp ) {
		$st_key   = get_post_meta( $rp->ID, '_rma_status', true );
		$st_meta  = isset( $statuses[ $st_key ] ) ? $statuses[ $st_key ] : $statuses['pending'];
		$tickets[] = array(
			'code'        => get_post_meta( $rp->ID, '_rma_code', true ) ?: ( 'RMA-' . $rp->ID ),
			'productName' => get_post_meta( $rp->ID, '_rma_product_name', true ),
			'issueType'   => get_post_meta( $rp->ID, '_rma_issue_type', true ),
			'description' => wp_strip_all_tags( $rp->post_content ),
			'statusLabel' => $st_meta['label'],
			'statusTone'  => $st_meta['tone'],
			'adminNote'   => get_post_meta( $rp->ID, '_rma_admin_note', true ),
			'createdAt'   => get_the_date( 'H:i • d/m/Y', $rp ),
		);
	}

	$ship_total = (float) $order->get_shipping_total();

	return array(
		'type'            => 'order',
		'orderId'         => $order->get_id(),
		'orderCode'       => '#TY-' . $order->get_order_number(),
		'status'          => $status,
		'statusLabel'     => $st_info['label'],
		'statusStep'      => $st_info['step'],
		'statusTone'      => $st_info['tone'],
		'createdAt'       => $created_dt ? $created_dt->date_i18n( 'H:i • d/m/Y' ) : '',
		'customerName'    => $order->get_formatted_billing_full_name(),
		'phone'           => $order->get_billing_phone(),
		'email'           => $order->get_billing_email(),
		'address'         => wp_strip_all_tags( str_replace( '<br/>', ', ', $order->get_formatted_billing_address() ) ),
		'note'            => $order->get_customer_note(),
		'paymentMethod'   => wp_strip_all_tags( $order->get_payment_method_title() ),
		'isPaid'          => $order->is_paid(),
		'shippingMethod'  => $order->get_shipping_method() ?: 'Miễn phí vận chuyển (0₫)',
		'shippingTotal'   => $ship_total > 0 ? typee_money( $ship_total ) : '0₫ (Freeship)',
		'subtotal'        => wp_strip_all_tags( $order->get_subtotal_to_display() ),
		'discountTotal'   => (float) $order->get_discount_total() > 0 ? '-' . typee_money( (float) $order->get_discount_total() ) : '',
		'total'           => wp_strip_all_tags( $order->get_formatted_order_total() ),
		'itemCount'       => (int) $order->get_item_count(),
		'items'           => $items_out,
		'tickets'         => $tickets,
	);
}

/**
 * 3. AJAX: Tra cứu đơn hàng & Hạn bảo hành (hoặc mã ticket RMA-*).
 */
function typee_ajax_lookup_order() {
	check_ajax_referer( 'typee_tracking_nonce', 'nonce' );

	$code_raw = isset( $_POST['order_code'] ) ? sanitize_text_field( wp_unslash( $_POST['order_code'] ) ) : '';
	$contact  = isset( $_POST['contact'] ) ? sanitize_text_field( wp_unslash( $_POST['contact'] ) ) : '';
	$code_up  = strtoupper( trim( $code_raw ) );

	if ( '' === $code_up ) {
		wp_send_json_error( array( 'message' => 'Vui lòng nhập Mã đơn hàng hoặc Mã e-RMA.' ), 400 );
	}

	// Nếu người dùng nhập mã Ticket `RMA-...` → tìm trực tiếp trong CPT `typee_warranty`.
	if ( 0 === strpos( $code_up, 'RMA-' ) ) {
		$found = get_posts(
			array(
				'post_type'      => 'typee_warranty',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_rma_code', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $code_up, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		if ( empty( $found ) ) {
			wp_send_json_error( array( 'message' => 'Không tìm thấy phiếu bảo hành ' . $code_up . '. Vui lòng kiểm tra lại mã.' ), 404 );
		}
		$rp  = $found[0];
		$oid = (int) get_post_meta( $rp->ID, '_rma_order_id', true );
		$ord = $oid ? wc_get_order( $oid ) : null;
		if ( $ord && ! typee_tracking_verify_order_contact( $ord, $contact ) ) {
			wp_send_json_error( array( 'message' => 'Email hoặc Số điện thoại không khớp với phiếu bảo hành này.' ), 403 );
		}
		if ( $ord ) {
			wp_send_json_success( typee_tracking_serialize_order( $ord ) );
		}
	}

	// Tra cứu theo Mã đơn hàng WooCommerce.
	$order_id = typee_tracking_parse_order_id( $code_raw );
	if ( ! $order_id ) {
		wp_send_json_error( array( 'message' => 'Mã đơn hàng không hợp lệ. Ví dụ: 4352 hoặc #TY-4352.' ), 400 );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => 'Không tìm thấy đơn hàng #' . $order_id . ' trong hệ thống.' ), 404 );
	}

	if ( ! typee_tracking_verify_order_contact( $order, $contact ) ) {
		wp_send_json_error( array( 'message' => 'Thông tin Email hoặc Số điện thoại không khớp với đơn hàng #' . $order_id . '.' ), 403 );
	}

	wp_send_json_success( typee_tracking_serialize_order( $order ) );
}
add_action( 'wp_ajax_typee_lookup_order', 'typee_ajax_lookup_order' );
add_action( 'wp_ajax_nopriv_typee_lookup_order', 'typee_ajax_lookup_order' );

/**
 * 4. AJAX: Gửi yêu cầu bảo hành e-RMA → lưu vào CPT `typee_warranty` trong WP-Admin.
 */
function typee_ajax_submit_warranty() {
	check_ajax_referer( 'typee_tracking_nonce', 'nonce' );

	$code_raw    = isset( $_POST['order_code'] ) ? sanitize_text_field( wp_unslash( $_POST['order_code'] ) ) : '';
	$contact     = isset( $_POST['contact'] ) ? sanitize_text_field( wp_unslash( $_POST['contact'] ) ) : '';
	$product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$issue_type  = isset( $_POST['issue_type'] ) ? sanitize_text_field( wp_unslash( $_POST['issue_type'] ) ) : '';
	$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

	$order_id = typee_tracking_parse_order_id( $code_raw );
	$order    = $order_id ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		wp_send_json_error( array( 'message' => 'Không tìm thấy đơn hàng hợp lệ. Vui lòng xác thực mã đơn trước.' ), 404 );
	}
	if ( ! typee_tracking_verify_order_contact( $order, $contact ) ) {
		wp_send_json_error( array( 'message' => 'Email hoặc Số điện thoại không khớp với đơn hàng #' . $order_id . '.' ), 403 );
	}
	if ( in_array( $order->get_status(), array( 'cancelled', 'failed', 'refunded' ), true ) ) {
		wp_send_json_error( array( 'message' => 'Đơn hàng đã hủy hoặc không hợp lệ nên không thể tạo yêu cầu bảo hành.' ), 400 );
	}

	// Xác minh sản phẩm thuộc đơn hàng & còn hạn bảo hành.
	$matched_name = '';
	$matched_prod = null;
	foreach ( $order->get_items() as $item ) {
		if ( $item instanceof WC_Order_Item_Product && (int) $item->get_product_id() === $product_id ) {
			$matched_name = $item->get_name();
			$matched_prod = $item->get_product();
			break;
		}
	}
	if ( '' === $matched_name ) {
		wp_send_json_error( array( 'message' => 'Vui lòng chọn đúng sản phẩm thuộc đơn hàng #' . $order_id . '.' ), 400 );
	}

	$warr = typee_tracking_calc_warranty( $order, $matched_prod );
	if ( ! $warr['isActive'] ) {
		wp_send_json_error( array( 'message' => 'Sản phẩm này đã hết thời hạn bảo hành (' . $warr['expiryDate'] . ').' ), 400 );
	}

	if ( mb_strlen( trim( $description ), 'UTF-8' ) < 10 ) {
		wp_send_json_error( array( 'message' => 'Vui lòng mô tả chi tiết tình trạng lỗi (tối thiểu 10 ký tự).' ), 400 );
	}

	$rma_code      = 'RMA-' . wp_date( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 4, false, false ) );
	$customer_name = $order->get_formatted_billing_full_name() ?: 'Khách hàng';
	$contact_info  = $contact ?: ( $order->get_billing_phone() ?: $order->get_billing_email() );

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'typee_warranty',
			'post_status'  => 'publish',
			'post_title'   => sprintf( '%s – Đơn #TY-%d (%s)', $rma_code, $order_id, $matched_name ),
			'post_content' => $description,
			'post_author'  => is_user_logged_in() ? get_current_user_id() : 0,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Không thể lưu yêu cầu bảo hành. Vui lòng thử lại.' ), 500 );
	}

	update_post_meta( $post_id, '_rma_code', $rma_code );
	update_post_meta( $post_id, '_rma_order_id', $order_id );
	update_post_meta( $post_id, '_rma_product_id', $product_id );
	update_post_meta( $post_id, '_rma_product_name', $matched_name );
	update_post_meta( $post_id, '_rma_customer_name', $customer_name );
	update_post_meta( $post_id, '_rma_contact', $contact_info );
	update_post_meta( $post_id, '_rma_issue_type', $issue_type ?: 'Lỗi kỹ thuật chung' );
	update_post_meta( $post_id, '_rma_status', 'pending' );

	wp_send_json_success(
		array(
			'ticketId'    => $post_id,
			'rmaCode'     => $rma_code,
			'orderCode'   => '#TY-' . $order->get_order_number(),
			'productName' => $matched_name,
			'statusLabel' => 'Đã tiếp nhận',
			'message'     => sprintf( 'Đã tiếp nhận yêu cầu bảo hành %s! Kỹ thuật viên TYPEE Lab sẽ liên hệ trong vòng 2 giờ làm việc.', $rma_code ),
		)
	);
}
add_action( 'wp_ajax_typee_submit_warranty', 'typee_ajax_submit_warranty' );
add_action( 'wp_ajax_nopriv_typee_submit_warranty', 'typee_ajax_submit_warranty' );

/**
 * 5. Shortcode `[typee_tracking_warranty]` để nhúng vào Page bất kỳ.
 */
add_shortcode( 'typee_tracking_warranty', function ( $atts ) {
	$atts = shortcode_atts( array( 'tab' => 'lookup' ), $atts );
	ob_start();
	get_template_part( 'template-parts/tracking/hub', null, array( 'initial_tab' => $atts['tab'] ) );
	return ob_get_clean();
} );
