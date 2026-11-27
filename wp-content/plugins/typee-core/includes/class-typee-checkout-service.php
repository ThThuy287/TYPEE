<?php
/**
 * Nghiệp vụ Trang Thanh Toán WooCommerce cho TYPEE (Figma 82:1187)
 *
 * - Tự động chuyển trang Checkout từ Block (`wp:woocommerce/checkout`) sang Classic Shortcode
 *   trong bộ nhớ RAM để không cần sửa tay dữ liệu DB của nhóm.
 * - Ép duy nhất 1 phương thức giao hàng Miễn Phí (`Free Shipping - 0₫`), tự chọn mặc định.
 * - Chuẩn hóa trường nhập liệu, kiểm tra hợp lệ (Họ tên, SĐT VN, Email, Tỉnh/Thành, Quận/Huyện,
 *   Địa chỉ chi tiết, thông tin VAT, điều khoản e-RMA) và lưu vào `wc_orders` (tương thích HPOS).
 * - Hỗ trợ ưu đãi tự động `Custom Bundle (-5%)` và mã giảm giá quảng bá `TYPEENEW10`.
 *
 * @package typee-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TYPEE_Checkout_Service {

	/**
	 * ID phương thức giao hàng miễn phí duy nhất.
	 */
	const FREE_SHIP_RATE_ID = 'free_shipping:1';

	/**
	 * Khởi tạo toàn bộ hooks nghiệp vụ Checkout.
	 */
	public static function init() {
		// 1. Chuyển Block Checkout sang Classic Shortcode và ép dùng template Checkout TYPEE.
		add_filter( 'the_posts', array( __CLASS__, 'force_classic_checkout_in_memory' ), 10, 2 );
		add_filter( 'the_content', array( __CLASS__, 'ensure_classic_checkout_shortcode' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'use_typee_checkout_layout' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'ensure_classic_checkout_scripts' ), 30 );

		// 2. Duy nhất 1 phương thức giao hàng miễn phí (Free Shipping - 0đ) & tự chọn mặc định.
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'enforce_single_free_shipping' ), 99999, 2 );
		add_filter( 'woocommerce_shipping_chosen_method', array( __CLASS__, 'default_free_shipping_method' ), 99999, 3 );
		add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false' );

		// 3. Chuẩn hóa trường nhập liệu Checkout khớp Figma 82:1242 & woo-vietnam-checkout.
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'customize_checkout_fields' ), 1000000 );
		add_filter( 'default_checkout_billing_country', array( __CLASS__, 'default_country_vn' ), 10000 );
		add_filter( 'default_checkout_billing_state', array( __CLASS__, 'default_state_vn' ), 10000 );

		// 4. Validation dữ liệu đầu vào & Lưu meta đơn hàng (chuẩn HPOS).
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout_submission' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_custom_order_meta' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_admin_vat_info' ), 15, 1 );

		// 5. Cập nhật động số món & tổng tiền trên nút ĐẶT HÀNG NGAY khi áp/gỡ mã giảm giá.
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'add_checkout_fragments' ) );

		// 6. Khuyến mãi đồng bộ với Header & Custom Builder: Custom Bundle (-5%) + mã TYPEENEW10.
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_custom_bundle_discount' ), 20 );
		add_filter( 'woocommerce_get_shop_coupon_data', array( __CLASS__, 'provide_promo_coupon_fallback' ), 10, 2 );
		add_filter( 'woocommerce_coupon_get_discount_amount', array( __CLASS__, 'cap_typeenew10_discount' ), 10, 5 );
	}

	/**
	 * Thay nội dung Gutenberg Block Checkout sang `[woocommerce_checkout]` trong bộ nhớ khi truy vấn trang Thanh toán.
	 *
	 * @param WP_Post[] $posts Danh sách bài viết/trang.
	 * @param WP_Query  $query Đối tượng truy vấn.
	 * @return WP_Post[]
	 */
	public static function force_classic_checkout_in_memory( $posts, $query ) {
		if ( is_admin() || empty( $posts ) || ! function_exists( 'wc_get_page_id' ) ) {
			return $posts;
		}
		$checkout_id = (int) get_option( 'woocommerce_checkout_page_id' );
		if ( $checkout_id <= 0 ) {
			return $posts;
		}
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && (int) $post->ID === $checkout_id ) {
				if ( false !== strpos( (string) $post->post_content, 'wp:woocommerce/checkout' ) ) {
					$post->post_content = '[woocommerce_checkout]';
				}
			}
		}
		return $posts;
	}

	/**
	 * Dự phòng ở tầng `the_content` để chắc chắn render `[woocommerce_checkout]`.
	 *
	 * @param string $content Nội dung trang.
	 * @return string
	 */
	public static function ensure_classic_checkout_shortcode( $content ) {
		if ( function_exists( 'is_checkout' ) && is_checkout() && false !== strpos( (string) $content, 'wp:woocommerce/checkout' ) ) {
			return '[woocommerce_checkout]';
		}
		return $content;
	}

	/**
	 * Bảo đảm các script chuẩn của Classic Checkout (`wc-checkout`, `selectWoo`) luôn được nạp đầy đủ.
	 */
	public static function ensure_classic_checkout_scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}
		wp_enqueue_script( 'wc-country-select' );
		wp_enqueue_script( 'wc-address-i18n' );
		wp_enqueue_script( 'wc-checkout' );
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_style( 'select2' );
	}

	/**
	 * Ép trang Checkout sử dụng layout Figma của theme `typee-child` thay vì page.php mặc định.
	 *
	 * @param string $template Đường dẫn template hiện tại.
	 * @return string
	 */
	public static function use_typee_checkout_layout( $template ) {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			$custom = get_stylesheet_directory() . '/woocommerce/checkout/layouts/checkout.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	/**
	 * Ép duy nhất 1 phương thức vận chuyển Miễn Phí (0₫) cho mọi khu vực.
	 *
	 * @param array $rates   Danh sách WC_Shipping_Rate hiện tại.
	 * @param array $package Gói hàng.
	 * @return array
	 */
	public static function enforce_single_free_shipping( $rates, $package ) {
		if ( ! class_exists( 'WC_Shipping_Rate' ) ) {
			return $rates;
		}

		// Giữ instance_id của free_shipping nếu đã có trong DB, ngược lại khởi tạo rate 0₫ chuẩn.
		foreach ( (array) $rates as $rate_id => $rate ) {
			if ( $rate instanceof WC_Shipping_Rate && 'free_shipping' === $rate->get_method_id() ) {
				$rate->set_cost( 0 );
				$rate->set_taxes( array() );
				$rate->set_label( 'Miễn phí vận chuyển chống sốc Studio' );
				return array( $rate_id => $rate );
			}
		}

		$single_rate = new WC_Shipping_Rate(
			self::FREE_SHIP_RATE_ID,
			'Miễn phí vận chuyển chống sốc Studio',
			0,
			array(),
			'free_shipping',
			1
		);

		return array( self::FREE_SHIP_RATE_ID => $single_rate );
	}

	/**
	 * Luôn tự động chọn sẵn phương thức Free Shipping duy nhất.
	 *
	 * @param string $default        Rate mặc định.
	 * @param array  $rates          Danh sách rates.
	 * @param string $chosen_method  Phương thức đang chọn.
	 * @return string
	 */
	public static function default_free_shipping_method( $default, $rates, $chosen_method ) {
		if ( ! empty( $rates ) && is_array( $rates ) ) {
			$keys = array_keys( $rates );
			return reset( $keys );
		}
		return self::FREE_SHIP_RATE_ID;
	}

	public static function default_country_vn() {
		return 'VN';
	}

	public static function default_state_vn( $state ) {
		return $state ? $state : '';
	}

	/**
	 * Chuẩn hóa nhãn, placeholder, độ ưu tiên và class bố cục 2 cột của các trường nhập liệu (Figma 82:1242).
	 *
	 * @param array $fields Mảng trường checkout của WooCommerce.
	 * @return array
	 */
	public static function customize_checkout_fields( $fields ) {
		// Nếu plugin woo-vietnam-checkout đang tắt, gộp first_name vào last_name để giữ đúng 1 ô Họ và tên.
		if ( isset( $fields['billing']['billing_first_name'] ) && ! class_exists( 'Woo_Address_Selectbox_Class' ) ) {
			unset( $fields['billing']['billing_first_name'] );
		}
		unset( $fields['billing']['billing_company'] );
		unset( $fields['billing']['billing_postcode'] );

		$fields['billing']['billing_last_name'] = array_merge(
			isset( $fields['billing']['billing_last_name'] ) ? $fields['billing']['billing_last_name'] : array(),
			array(
				'label'        => 'Họ và tên người nhận',
				'placeholder'  => 'VD: Nguyễn Hoàng Minh',
				'required'     => true,
				'class'        => array( 'form-row-wide', 'ty-co-field', 'ty-co-field--icon-person' ),
				'autocomplete' => 'name',
				'priority'     => 10,
			)
		);

		$fields['billing']['billing_phone'] = array_merge(
			isset( $fields['billing']['billing_phone'] ) ? $fields['billing']['billing_phone'] : array(),
			array(
				'label'        => 'Số điện thoại liên hệ',
				'placeholder'  => 'VD: 0918 248 899',
				'required'     => true,
				'type'         => 'tel',
				'class'        => array( 'form-row-first', 'ty-co-field', 'ty-co-field--icon-phone' ),
				'autocomplete' => 'tel',
				'priority'     => 20,
			)
		);

		$fields['billing']['billing_email'] = array_merge(
			isset( $fields['billing']['billing_email'] ) ? $fields['billing']['billing_email'] : array(),
			array(
				'label'        => 'Email nhận hóa đơn & e-RMA',
				'placeholder'  => 'VD: minh.nguyen@gmail.com',
				'required'     => true,
				'type'         => 'email',
				'class'        => array( 'form-row-last', 'ty-co-field', 'ty-co-field--icon-mail' ),
				'autocomplete' => 'email',
				'priority'     => 21,
			)
		);

		if ( isset( $fields['billing']['billing_state'] ) ) {
			$fields['billing']['billing_state']['label']       = 'Tỉnh / Thành phố';
			$fields['billing']['billing_state']['placeholder'] = 'Chọn Tỉnh / Thành phố';
			$fields['billing']['billing_state']['required']    = true;
			$fields['billing']['billing_state']['class']       = array( 'form-row-first', 'address-field', 'update_totals_on_change', 'ty-co-field', 'ty-co-field--icon-pin' );
			$fields['billing']['billing_state']['priority']    = 30;
		}

		if ( isset( $fields['billing']['billing_city'] ) ) {
			$fields['billing']['billing_city']['label']       = class_exists( 'Woo_Address_Selectbox_Class' ) ? 'Quận / Huyện / Phường' : 'Quận / Huyện';
			$fields['billing']['billing_city']['placeholder'] = 'Chọn Quận / Huyện';
			$fields['billing']['billing_city']['required']    = true;
			$fields['billing']['billing_city']['class']       = array( 'form-row-last', 'address-field', 'ty-co-field', 'ty-co-field--icon-map' );
			$fields['billing']['billing_city']['priority']    = 40;
		}

		if ( isset( $fields['billing']['billing_address_2'] ) ) {
			$fields['billing']['billing_address_2']['label']    = 'Phường / Xã';
			$fields['billing']['billing_address_2']['priority'] = 45;
		}

		$fields['billing']['billing_address_1'] = array_merge(
			isset( $fields['billing']['billing_address_1'] ) ? $fields['billing']['billing_address_1'] : array(),
			array(
				'label'        => 'Địa chỉ chi tiết',
				'placeholder'  => 'Số nhà, tên đường, tòa nhà / căn hộ...',
				'required'     => true,
				'class'        => array( 'form-row-wide', 'address-field', 'ty-co-field', 'ty-co-field--icon-home' ),
				'autocomplete' => 'street-address',
				'priority'     => 60,
			)
		);

		$fields['order']['order_comments'] = array(
			'type'        => 'textarea',
			'label'       => 'Ghi chú đơn hàng & Yêu cầu kỹ thuật Modding',
			'placeholder' => 'VD: Nhờ kỹ thuật viên TYPEE Studio cân wire stab phím Spacebar và Enter, hoặc ghi chú giờ nhận hàng...',
			'required'    => false,
			'class'       => array( 'form-row-wide', 'notes', 'ty-co-field', 'ty-co-field--notes' ),
			'custom_attributes' => array(
				'rows' => 3,
			),
		);

		return $fields;
	}

	/**
	 * Kiểm tra hợp lệ dữ liệu đầu vào bằng tiếng Việt rõ ràng khi khách bấm Đặt hàng.
	 *
	 * @param array    $data   Dữ liệu đã chuẩn hóa của WooCommerce.
	 * @param WP_Error $errors Đối tượng lỗi.
	 */
	public static function validate_checkout_submission( $data, $errors ) {
		// Xóa các thông báo mặc định tiếng Anh nếu trùng để trả thông báo tiếng Việt thân thiện.
		$full_name = isset( $data['billing_last_name'] ) ? trim( (string) $data['billing_last_name'] ) : '';
		if ( mb_strlen( $full_name, 'UTF-8' ) < 2 ) {
			$errors->remove( 'billing_last_name_required' );
			$errors->add( 'typee_name_invalid', 'Vui lòng nhập đầy đủ <strong>Họ và tên người nhận</strong> (tối thiểu 2 ký tự).' );
		}

		$raw_phone   = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';
		$clean_phone = preg_replace( '/[\s\.\-\(\)]+/', '', $raw_phone );
		if ( ! preg_match( '/^(0|\+84)[0-9]{8,10}$/', $clean_phone ) ) {
			$errors->remove( 'billing_phone_required' );
			$errors->remove( 'billing_phone_validation' );
			$errors->add( 'typee_phone_invalid', '<strong>Số điện thoại liên hệ</strong> chưa đúng định dạng di động Việt Nam (VD: 0918248899).' );
		}

		$email = isset( $data['billing_email'] ) ? trim( (string) $data['billing_email'] ) : '';
		if ( ! is_email( $email ) ) {
			$errors->remove( 'billing_email_required' );
			$errors->remove( 'billing_email_validation' );
			$errors->add( 'typee_email_invalid', 'Vui lòng nhập địa chỉ <strong>Email</strong> hợp lệ để nhận hóa đơn và kích hoạt bảo hành điện tử e-RMA.' );
		}

		if ( empty( $data['billing_state'] ) ) {
			$errors->remove( 'billing_state_required' );
			$errors->add( 'typee_state_required', 'Vui lòng chọn <strong>Tỉnh / Thành phố</strong> nhận hàng.' );
		}

		if ( empty( $data['billing_city'] ) ) {
			$errors->remove( 'billing_city_required' );
			$errors->add( 'typee_city_required', 'Vui lòng chọn <strong>Quận / Huyện / Phường</strong> nhận hàng.' );
		}

		$addr = isset( $data['billing_address_1'] ) ? trim( (string) $data['billing_address_1'] ) : '';
		if ( mb_strlen( $addr, 'UTF-8' ) < 4 ) {
			$errors->remove( 'billing_address_1_required' );
			$errors->add( 'typee_address_required', 'Vui lòng nhập <strong>Địa chỉ chi tiết</strong> (số nhà, tên đường/tòa nhà).' );
		}

		// Kiểm tra thông tin hóa đơn VAT nếu khách tích chọn.
		if ( ! empty( $_POST['typee_require_vat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Đã được WooCommerce xác thực nonce process_checkout.
			$vat_company = isset( $_POST['typee_vat_company'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['typee_vat_company'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$vat_tax     = isset( $_POST['typee_vat_tax_code'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['typee_vat_tax_code'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $vat_company || '' === $vat_tax ) {
				$errors->add( 'typee_vat_incomplete', 'Bạn đã chọn xuất hóa đơn VAT doanh nghiệp: vui lòng nhập đủ <strong>Tên công ty</strong> và <strong>Mã số thuế</strong>.' );
			}
		}

		// Kiểm tra xác nhận điều khoản & chính sách bảo hành điện tử e-RMA.
		if ( empty( $_POST['typee_terms_accepted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$errors->add( 'typee_terms_required', 'Vui lòng tích xác nhận đồng ý với <strong>Điều khoản dịch vụ &amp; Chính sách bảo hành điện tử e-RMA</strong> trước khi đặt hàng.' );
		}
	}

	/**
	 * Lưu thông tin mở rộng (hóa đơn VAT, số điện thoại chuẩn hóa) vào WC_Order theo chuẩn HPOS.
	 *
	 * @param WC_Order $order Đối tượng đơn hàng.
	 * @param array    $data  Dữ liệu checkout.
	 */
	public static function save_custom_order_meta( $order, $data ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		// Gán mặc định quốc gia VN nếu form ẩn trường quốc gia.
		if ( ! $order->get_billing_country() ) {
			$order->set_billing_country( 'VN' );
		}

		if ( ! empty( $_POST['typee_require_vat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$company = isset( $_POST['typee_vat_company'] ) ? sanitize_text_field( wp_unslash( $_POST['typee_vat_company'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$tax     = isset( $_POST['typee_vat_tax_code'] ) ? sanitize_text_field( wp_unslash( $_POST['typee_vat_tax_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$v_addr  = isset( $_POST['typee_vat_address'] ) ? sanitize_text_field( wp_unslash( $_POST['typee_vat_address'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$order->update_meta_data( '_typee_require_vat', 'yes' );
			$order->update_meta_data( '_typee_vat_company', $company );
			$order->update_meta_data( '_typee_vat_tax_code', $tax );
			$order->update_meta_data( '_typee_vat_address', $v_addr );
			if ( $company ) {
				$order->set_billing_company( $company );
			}
		}

		$order->update_meta_data( '_typee_erma_activated', 'yes' );
	}

	/**
	 * Hiển thị thông tin xuất hóa đơn VAT trong màn quản trị đơn hàng WP-Admin.
	 *
	 * @param WC_Order $order Đơn hàng.
	 */
	public static function render_admin_vat_info( $order ) {
		if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_typee_require_vat' ) ) {
			return;
		}
		echo '<div class="address" style="margin-top:12px;padding:10px;background:#fff8f5;border-left:3px solid #ff5722;">';
		echo '<p><strong>Yêu cầu xuất hóa đơn VAT:</strong><br/>';
		echo 'Công ty: ' . esc_html( $order->get_meta( '_typee_vat_company' ) ) . '<br/>';
		echo 'Mã số thuế: ' . esc_html( $order->get_meta( '_typee_vat_tax_code' ) ) . '<br/>';
		if ( $order->get_meta( '_typee_vat_address' ) ) {
			echo 'Địa chỉ ĐKKD: ' . esc_html( $order->get_meta( '_typee_vat_address' ) );
		}
		echo '</p></div>';
	}

	/**
	 * Định dạng nút ĐẶT HÀNG NGAY kèm tổng thanh toán hiện tại của giỏ hàng.
	 *
	 * @return string
	 */
	public static function place_order_button_label() {
		$total_plain = '';
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$total_plain = html_entity_decode( wp_strip_all_tags( wc_price( (float) WC()->cart->get_total( 'edit' ) ) ), ENT_QUOTES, 'UTF-8' );
		}
		return $total_plain ? 'ĐẶT HÀNG NGAY • ' . $total_plain : 'ĐẶT HÀNG NGAY';
	}

	/**
	 * Trả thêm fragments để khi áp mã giảm giá AJAX, số lượng món và giá trên nút Đặt hàng cập nhật ngay.
	 *
	 * @param array $fragments Fragments của WooCommerce.
	 * @return array
	 */
	public static function add_checkout_fragments( $fragments ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $fragments;
		}
		$count = (int) WC()->cart->get_cart_contents_count();
		$label = self::place_order_button_label();

		$fragments['.ty-co-summary__count'] = '<span class="ty-co-summary__count">' . esc_html( $count . ' món' ) . '</span>';
		$fragments['#ty-co-submit-box']     = self::render_submit_button_box( $label );
		return $fragments;
	}

	/**
	 * Render hộp nút ĐẶT HÀNG NGAY (tách icon bên ngoài #place_order để wc-checkout.js không xóa icon khi đổi cổng).
	 *
	 * @param string $label Nhãn nút.
	 * @return string
	 */
	public static function render_submit_button_box( $label = '' ) {
		if ( '' === $label ) {
			$label = self::place_order_button_label();
		}
		ob_start();
		?>
		<div id="ty-co-submit-box" class="ty-co-submit-box">
			<span class="ty-co-submit-ic ty-co-submit-ic--left" aria-hidden="true">
				<?php if ( function_exists( 'typee_the_icon' ) ) { typee_the_icon( 'lock', 18 ); } ?>
			</span>
			<button
				type="submit"
				class="button alt ty-co-submit-btn"
				name="woocommerce_checkout_place_order"
				id="place_order"
				value="<?php echo esc_attr( $label ); ?>"
				data-value="<?php echo esc_attr( $label ); ?>"
			><?php echo esc_html( $label ); ?></button>
			<span class="ty-co-submit-ic ty-co-submit-ic--right" aria-hidden="true">
				<?php if ( function_exists( 'typee_the_icon' ) ) { typee_the_icon( 'arrow_forward', 18 ); } ?>
			</span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Tự động áp dụng "Ưu đãi Custom Bundle (-5%)" khi giỏ hàng có đủ cả 3 nhóm linh kiện: Kit + Switch + Keycap.
	 *
	 * @param WC_Cart $cart Giỏ hàng.
	 */
	public static function apply_custom_bundle_discount( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}

		$found_cats = array();
		$bundle_sub = 0.0;

		foreach ( $cart->get_cart() as $item ) {
			$prod = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $prod instanceof WC_Product ) {
				continue;
			}
			$pid   = $prod->get_parent_id() ? $prod->get_parent_id() : $prod->get_id();
			$slugs = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
			if ( is_wp_error( $slugs ) ) {
				continue;
			}
			foreach ( array( 'kit', 'switch', 'keycap' ) as $target ) {
				if ( in_array( $target, $slugs, true ) ) {
					$found_cats[ $target ] = true;
					$bundle_sub           += (float) $item['line_subtotal'];
					break;
				}
			}
		}

		if ( isset( $found_cats['kit'], $found_cats['switch'], $found_cats['keycap'] ) && $bundle_sub > 0 ) {
			$rate     = (float) apply_filters( 'typee_builder_bundle_rate', 0.05 );
			$discount = round( $bundle_sub * $rate );
			if ( $discount > 0 ) {
				$cart->add_fee( 'Ưu đãi Custom Bundle (-5%)', -$discount, false );
			}
		}
	}

	/**
	 * Hỗ trợ mã giảm giá quảng bá trên Header `TYPEENEW10` (Giảm 10% tối đa 100.000₫) nếu trong DB chưa tạo tay.
	 * Nếu Admin đã tạo coupon thật cùng tên trong WP-Admin thì ưu tiên dùng coupon thật trong DB.
	 *
	 * @param mixed  $data Dữ liệu coupon ảo.
	 * @param string $code Mã coupon khách nhập.
	 * @return mixed
	 */
	public static function provide_promo_coupon_fallback( $data, $code ) {
		if ( $data || 'typeeenew10' !== strtolower( trim( (string) $code ) ) ) {
			return $data;
		}
		if ( function_exists( 'wc_get_coupon_id_by_code' ) ) {
			remove_filter( 'woocommerce_get_shop_coupon_data', array( __CLASS__, __FUNCTION__ ), 10 );
			$real_id = wc_get_coupon_id_by_code( $code );
			add_filter( 'woocommerce_get_shop_coupon_data', array( __CLASS__, __FUNCTION__ ), 10, 2 );
			if ( $real_id > 0 ) {
				return $data;
			}
		}
		return array(
			'id'                         => 9999901,
			'amount'                     => 10,
			'discount_type'              => 'percent',
			'description'                => 'Giảm 10% tối đa 100.000₫ đơn hàng mới tại TYPEE Studio',
			'individual_use'             => false,
			'product_ids'                => array(),
			'excluded_product_ids'       => array(),
			'usage_limit'                => 0,
			'usage_limit_per_user'       => 0,
			'limit_usage_to_x_items'     => null,
			'free_shipping'              => true,
			'product_categories'         => array(),
			'excluded_product_categories'=> array(),
			'exclude_sale_items'         => false,
			'minimum_amount'             => 0,
			'maximum_amount'             => 0,
		);
	}

	/**
	 * Giới hạn mức giảm tối đa 100.000₫ cho mã `TYPEENEW10` theo đúng nội dung thanh Topbar.
	 *
	 * @param float      $discount           Số tiền giảm dự kiến.
	 * @param float      $discounting_amount Giá trị tính giảm.
	 * @param array|null $cart_item          Dòng sản phẩm.
	 * @param bool       $single             Đơn lẻ hay tổng.
	 * @param WC_Coupon  $coupon             Đối tượng coupon.
	 * @return float
	 */
	public static function cap_typeenew10_discount( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
		if ( ! $coupon instanceof WC_Coupon || 'typeeenew10' !== strtolower( $coupon->get_code() ) ) {
			return $discount;
		}
		$max_cap = 100000.0;
		$sub     = ( function_exists( 'WC' ) && WC()->cart ) ? (float) WC()->cart->get_subtotal() : 0.0;
		if ( $sub > 0 && ( $sub * 0.10 ) > $max_cap ) {
			$ratio = $discounting_amount / $sub;
			return round( $max_cap * $ratio );
		}
		return min( $discount, $max_cap );
	}
}
