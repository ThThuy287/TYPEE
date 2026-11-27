<?php
/**
 * Trang Chi Tiết Sản Phẩm (Single Product Page – Figma 82:2).
 *
 * - Tổng hợp bảng thông số kỹ thuật đầy đủ (ACF `attribute_*` + WooCommerce `get_attributes()` + trọng lượng/kích thước).
 * - Endpoint AJAX `typee_single_add_to_cart` có xác thực Nonce, hỗ trợ cả Simple & Variable Product,
 *   trả về WooCommerce fragments (`.ty-hcart`, `.ty-sidecart__content`) và hỗ trợ chế độ `buy_now` chuyển thẳng `/checkout/`.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tổng hợp toàn bộ ảnh sản phẩm (Featured + Gallery) dưới dạng mảng chuẩn cho Gallery & Lightbox.
 *
 * @param WC_Product $product Sản phẩm.
 * @return array<int, array{id:int,full:string,large:string,thumb:string,alt:string}>
 */
function typee_single_gallery_items( $product ) {
	$ids   = array();
	$main  = (int) $product->get_image_id();
	if ( $main ) {
		$ids[] = $main;
	}
	foreach ( $product->get_gallery_image_ids() as $gid ) {
		$gid = (int) $gid;
		if ( $gid && ! in_array( $gid, $ids, true ) ) {
			$ids[] = $gid;
		}
	}

	$items = array();
	$title = $product->get_name();
	foreach ( $ids as $id ) {
		$full  = wp_get_attachment_image_url( $id, 'full' );
		$large = wp_get_attachment_image_url( $id, 'woocommerce_single' );
		$thumb = wp_get_attachment_image_url( $id, 'woocommerce_gallery_thumbnail' );
		if ( ! $full && ! $large ) {
			continue;
		}
		$alt     = get_post_meta( $id, '_wp_attachment_image_alt', true );
		$items[] = array(
			'id'    => $id,
			'full'  => $full ? $full : $large,
			'large' => $large ? $large : $full,
			'thumb' => $thumb ? $thumb : ( $large ? $large : $full ),
			'alt'   => $alt ? $alt : $title,
		);
	}

	if ( empty( $items ) ) {
		$ph      = wc_placeholder_img_src( 'woocommerce_single' );
		$items[] = array(
			'id'    => 0,
			'full'  => $ph,
			'large' => $ph,
			'thumb' => $ph,
			'alt'   => $title,
		);
	}

	return $items;
}

/**
 * Tổng hợp toàn bộ bảng thông số kỹ thuật từ DB (ACF `attribute_*`, WooCommerce Product Attributes, SKU, Trọng lượng, Kích thước).
 *
 * @param WC_Product $product Sản phẩm.
 * @return array<int, array{label:string,value:string}>
 */
function typee_single_specs_rows( $product ) {
	$rows = array();
	$seen = array();

	$add_row = function ( $label, $value ) use ( &$rows, &$seen ) {
		$label = trim( (string) $label );
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $label || '' === $value ) {
			return;
		}
		$key = mb_strtolower( $label, 'UTF-8' );
		if ( isset( $seen[ $key ] ) ) {
			return;
		}
		$seen[ $key ] = true;
		$rows[]       = array(
			'label' => $label,
			'value' => $value,
		);
	};

	// 1. Thông số EAV theo nhóm sản phẩm (ACF postmeta `attribute_*`).
	foreach ( typee_product_specs( $product ) as $spec ) {
		$add_row( $spec['label'], $spec['value'] );
	}

	// 2. Các thuộc tính WooCommerce chuẩn (Product Attributes / `pa_*` và Custom Attributes).
	foreach ( $product->get_attributes() as $attr ) {
		if ( ! $attr instanceof WC_Product_Attribute ) {
			continue;
		}
		$label = wc_attribute_label( $attr->get_name(), $product );
		if ( $attr->is_taxonomy() ) {
			$vals = wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
			$val  = is_array( $vals ) ? implode( ', ', $vals ) : '';
		} else {
			$val = implode( ', ', $attr->get_options() );
		}
		$add_row( $label, $val );
	}

	// 3. Thông tin cơ bản từ WC_Product.
	if ( $product->get_sku() ) {
		$add_row( 'Mã sản phẩm (SKU)', $product->get_sku() );
	}
	$cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
	if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
		$add_row( 'Danh mục', implode( ', ', $cats ) );
	}
	if ( $product->has_weight() ) {
		$add_row( 'Trọng lượng', wc_format_weight( $product->get_weight() ) );
	}
	if ( $product->has_dimensions() ) {
		$add_row( 'Kích thước', wc_format_dimensions( $product->get_dimensions( false ) ) );
	}

	return $rows;
}

/**
 * Nhận diện mã màu swatch trực quan nếu tên tùy chọn là màu sắc phổ biến (tiếng Việt & tiếng Anh).
 *
 * @param string $label Tên tùy chọn (vd: "Đen (Black)", "Silver", "Vàng Gold").
 * @return string Hex color hoặc '' nếu không phải màu.
 */
function typee_guess_swatch_color( $label ) {
	$s   = mb_strtolower( $label, 'UTF-8' );
	$map = array(
		'đen'      => '#18181b',
		'black'    => '#18181b',
		'trắng'    => '#f8fafc',
		'white'    => '#f8fafc',
		'bạc'      => '#cbd5e1',
		'silver'   => '#cbd5e1',
		'xám'      => '#64748b',
		'gray'     => '#64748b',
		'grey'     => '#64748b',
		'vàng'     => '#eab308',
		'gold'     => '#d97706',
		'yellow'   => '#eab308',
		'cam'      => '#ff5722',
		'orange'   => '#ff5722',
		'đỏ'       => '#dc2626',
		'red'      => '#dc2626',
		'hồng'     => '#ec4899',
		'pink'     => '#ec4899',
		'tím'      => '#7c3aed',
		'purple'   => '#7c3aed',
		'violet'   => '#7c3aed',
		'xanh dương' => '#2563eb',
		'navy'     => '#0f172a',
		'midnight' => '#082f49',
		'blue'     => '#2563eb',
		'xanh lá'  => '#16a34a',
		'green'    => '#16a34a',
		'kem'      => '#f5f0e1',
		'cream'    => '#f5f0e1',
		'nâu'      => '#78350f',
		'brown'    => '#78350f',
	);
	foreach ( $map as $kw => $hex ) {
		if ( false !== mb_strpos( $s, $kw, 0, 'UTF-8' ) ) {
			return $hex;
		}
	}
	return '';
}

/**
 * AJAX: Thêm vào giỏ hàng từ trang Chi tiết sản phẩm (hỗ trợ cả Simple & Variable + chế độ Mua ngay).
 */
function typee_ajax_single_add_to_cart() {
	check_ajax_referer( 'typee_single_cart_nonce', 'nonce' );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => 'Giỏ hàng hiện chưa sẵn sàng.' ), 400 );
	}

	$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
	$quantity     = isset( $_POST['quantity'] ) ? max( 1, wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) ) : 1; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$buy_now      = ! empty( $_POST['buy_now'] );

	$product = wc_get_product( $product_id );
	if ( ! $product || 'publish' !== $product->get_status() ) {
		wp_send_json_error( array( 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.' ), 404 );
	}

	$variations = array();
	foreach ( $_POST as $k => $v ) {
		if ( 0 === strpos( (string) $k, 'attribute_' ) ) {
			$variations[ sanitize_text_field( wp_unslash( $k ) ) ] = sanitize_text_field( wp_unslash( $v ) );
		}
	}

	if ( $product->is_type( 'variable' ) ) {
		if ( ! $variation_id ) {
			wp_send_json_error( array( 'message' => 'Vui lòng chọn đầy đủ phiên bản / thuộc tính sản phẩm trước khi mua.' ), 400 );
		}
		$var_obj = wc_get_product( $variation_id );
		if ( ! $var_obj || (int) $var_obj->get_parent_id() !== $product_id ) {
			wp_send_json_error( array( 'message' => 'Biến thể không hợp lệ. Vui lòng chọn lại.' ), 400 );
		}
		if ( ! $var_obj->is_purchasable() || ! $var_obj->is_in_stock() ) {
			wp_send_json_error( array( 'message' => 'Phiên bản bạn chọn hiện đã hết hàng.' ), 400 );
		}
	} elseif ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		wp_send_json_error( array( 'message' => 'Sản phẩm hiện đang hết hàng.' ), 400 );
	}

	$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );
	if ( ! $passed ) {
		$notices = wc_get_notices( 'error' );
		wc_clear_notices();
		$msg = ! empty( $notices[0]['notice'] ) ? wp_strip_all_tags( $notices[0]['notice'] ) : 'Không thể thêm sản phẩm vào giỏ hàng.';
		wp_send_json_error( array( 'message' => $msg ), 400 );
	}

	$added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
	if ( ! $added ) {
		$notices = wc_get_notices( 'error' );
		wc_clear_notices();
		$msg = ! empty( $notices[0]['notice'] ) ? wp_strip_all_tags( $notices[0]['notice'] ) : 'Số lượng vượt quá tồn kho hiện có hoặc sản phẩm không thể mua.';
		wp_send_json_error( array( 'message' => $msg ), 400 );
	}

	do_action( 'woocommerce_ajax_added_to_cart', $product_id );
	WC()->cart->calculate_totals();

	$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

	wp_send_json_success(
		array(
			'message'     => 'Đã thêm sản phẩm vào giỏ hàng!',
			'cartCount'   => (int) WC()->cart->get_cart_contents_count(),
			'fragments'   => $fragments,
			'redirectUrl' => $buy_now ? wc_get_checkout_url() : '',
		)
	);
}
add_action( 'wp_ajax_typee_single_add_to_cart', 'typee_ajax_single_add_to_cart' );
add_action( 'wp_ajax_nopriv_typee_single_add_to_cart', 'typee_ajax_single_add_to_cart' );

/**
 * Bỏ các thanh tiêu đề / breadcrumb mặc định của Flatsome trên trang Chi tiết sản phẩm.
 */
add_action( 'wp', function () {
	if ( function_exists( 'is_product' ) && is_product() ) {
		remove_action( 'flatsome_after_header', 'flatsome_product_header' );
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	}
} );

/**
 * Đảm bảo mọi trang Chi tiết sản phẩm luôn nạp template single-product.php của theme con TYPEE
 * (tránh Flatsome chặn trước bằng layout mặc định).
 */
add_filter( 'template_include', function ( $template ) {
	if ( function_exists( 'is_product' ) && is_product() && ! typee_current_virtual() ) {
		$own = TYPEE_CHILD_DIR . '/woocommerce/single-product.php';
		if ( file_exists( $own ) ) {
			return $own;
		}
	}
	return $template;
}, 50 );
