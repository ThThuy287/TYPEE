<?php
/**
 * Hệ thống Cổng Thanh Toán Miễn Phí cho TYPEE WooCommerce (Figma 82:1187 & 82:8375)
 *
 * Bao gồm 4 phương thức miễn phí hoạt động ngay:
 *  1. `typee_vietqr` – Chuyển khoản QR ngân hàng (VietQR / Napas247 tự sinh QR theo số tiền & mã đơn)
 *  2. `typee_momo`   – Ví điện tử MoMo (Tích hợp sẵn Sandbox Simulator + hỗ trợ MoMo Test/Prod API)
 *  3. `cod`          – Thanh toán khi nhận hàng (COD chuẩn WooCommerce, tự bật mặc định)
 *  4. `typee_vnpay`  – Cổng VNPay-QR / Thẻ ATM & Quốc tế (Tích hợp sẵn Sandbox Simulator + VNPay Sandbox API)
 *
 * @package typee-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TYPEE_Payment_Gateways {

	/**
	 * Khởi tạo hooks (chờ plugins_loaded để chắc chắn WooCommerce và WC_Payment_Gateway đã nạp).
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'register_gateway_classes' ), 20 );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateways' ) );
		add_filter( 'default_option_woocommerce_cod_settings', array( __CLASS__, 'default_cod_settings' ) );
		add_filter( 'option_woocommerce_cod_settings', array( __CLASS__, 'ensure_cod_vietnamese_defaults' ) );
		add_filter( 'default_option_woocommerce_gateway_order', array( __CLASS__, 'default_gateway_order' ) );

		// Khai báo tương thích HPOS (High-Performance Order Storage - wp_wc_orders).
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WP_PLUGIN_DIR . '/typee-core/typee-core.php', true );
				}
			}
		);
	}

	/**
	 * Tự bật COD với tiêu đề/mô tả chuẩn tiếng Việt nếu chưa từng cấu hình trong DB.
	 *
	 * @return array
	 */
	public static function default_cod_settings() {
		return array(
			'enabled'            => 'yes',
			'title'              => 'Thanh toán khi nhận hàng (COD)',
			'description'        => 'Khách hàng được đồng kiểm ngoại quan, kiểm tra seal nguyên vẹn cùng shipper trước khi thanh toán tiền mặt.',
			'instructions'       => 'Vui lòng chuẩn bị đúng số tiền mặt khi nhân viên giao hàng liên hệ.',
			'enable_for_methods' => array(),
			'enable_for_virtual' => 'yes',
		);
	}

	/**
	 * Nếu bảng wp_options có bản ghi cod mặc định nhưng chưa từng được Admin lưu thủ công, bảo đảm COD luôn sẵn sàng.
	 *
	 * @param mixed $val Giá trị option.
	 * @return mixed
	 */
	public static function ensure_cod_vietnamese_defaults( $val ) {
		if ( ! is_array( $val ) ) {
			return self::default_cod_settings();
		}
		if ( empty( $val['title'] ) || 'Cash on delivery' === $val['title'] ) {
			$val['title']       = 'Thanh toán khi nhận hàng (COD)';
			$val['description'] = 'Khách hàng được đồng kiểm ngoại quan, kiểm tra seal nguyên vẹn cùng shipper trước khi thanh toán tiền mặt.';
			if ( ! isset( $val['enabled'] ) ) {
				$val['enabled'] = 'yes';
			}
		}
		return $val;
	}

	/**
	 * Thứ tự hiển thị cổng thanh toán mặc định khớp Figma 82:1385.
	 *
	 * @return array
	 */
	public static function default_gateway_order() {
		return array(
			'typee_vietqr' => 0,
			'typee_momo'   => 1,
			'cod'          => 2,
			'typee_vnpay'  => 3,
		);
	}

	/**
	 * Thêm các gateway vào danh sách WooCommerce.
	 *
	 * @param array $methods Danh sách class gateway.
	 * @return array
	 */
	public static function add_gateways( $methods ) {
		if ( class_exists( 'TYPEE_Gateway_VietQR' ) ) {
			$methods[] = 'TYPEE_Gateway_VietQR';
		}
		if ( class_exists( 'TYPEE_Gateway_MoMo' ) ) {
			$methods[] = 'TYPEE_Gateway_MoMo';
		}
		if ( class_exists( 'TYPEE_Gateway_VNPay' ) ) {
			$methods[] = 'TYPEE_Gateway_VNPay';
		}
		return $methods;
	}

	/**
	 * Secret nội bộ dùng ký HMAC cho luồng Sandbox Simulator (dựa trên wp_salt).
	 *
	 * @return string
	 */
	public static function internal_hmac_secret() {
		return function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'typee_sandbox_secret_key';
	}

	/**
	 * Tạo chữ ký bảo mật cho trang giả lập cổng thanh toán Sandbox nội bộ.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $order_key Order key.
	 * @param string $gateway   Mã cổng.
	 * @return string
	 */
	public static function sign_sandbox_token( $order_id, $order_key, $gateway ) {
		$data = (int) $order_id . '|' . (string) $order_key . '|' . (string) $gateway;
		return hash_hmac( 'sha256', $data, self::internal_hmac_secret() );
	}

	/**
	 * Render giao diện trang Cổng Thanh Toán Thử Nghiệm (Sandbox Simulator) cho MoMo / VNPay.
	 *
	 * @param WC_Order $order        Đơn hàng.
	 * @param string   $brand        'momo' | 'vnpay'.
	 * @param string   $success_url  URL callback thành công.
	 * @param string   $cancel_url   URL callback hủy giao dịch.
	 */
	public static function render_sandbox_simulator( WC_Order $order, $brand, $success_url, $cancel_url ) {
		nocache_headers();
		status_header( 200 );

		$is_momo    = ( 'momo' === $brand );
		$brand_name = $is_momo ? 'Ví điện tử MoMo (Sandbox Test)' : 'Cổng thanh toán VNPay-QR (Sandbox Test)';
		$accent     = $is_momo ? '#a50064' : '#005baa';
		$bg_soft    = $is_momo ? '#fdf2f8' : '#eff6ff';
		$amount_fmt = wp_strip_all_tags( wc_price( (float) $order->get_total() ) );
		$order_code = 'TYPEE-' . $order->get_order_number();
		$qr_payload = rawurlencode( strtoupper( $brand ) . '|ORDER:' . $order_code . '|AMOUNT:' . (int) $order->get_total() );
		$qr_img     = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' . $qr_payload;
		?>
		<!DOCTYPE html>
		<html lang="vi">
		<head>
			<meta charset="utf-8" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html( $brand_name . ' – Đơn #' . $order->get_order_number() ); ?></title>
			<style>
				* { box-sizing: border-box; }
				body {
					margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
					background: #f5f2fb; color: #1b1b21; min-height: 100vh;
					display: flex; align-items: center; justify-content: center; padding: 24px;
				}
				.sb-card {
					width: 100%; max-width: 520px; background: #fff; border-radius: 16px;
					box-shadow: 0 20px 35px -10px rgba(15, 17, 23, 0.14); overflow: hidden; border: 1px solid #e4e1ea;
				}
				.sb-head {
					background: <?php echo esc_attr( $accent ); ?>; color: #fff; padding: 20px 24px;
					display: flex; align-items: center; justify-content: space-between; gap: 12px;
				}
				.sb-head h1 { margin: 0; font-size: 17px; font-weight: 700; }
				.sb-badge {
					background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 999px;
					font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
				}
				.sb-body { padding: 24px; display: flex; flex-direction: column; gap: 18px; }
				.sb-qr-box {
					background: <?php echo esc_attr( $bg_soft ); ?>; border-radius: 12px; padding: 18px;
					display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center;
				}
				.sb-qr-box img { width: 180px; height: 180px; border-radius: 10px; background: #fff; padding: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
				.sb-amount { font-size: 28px; font-weight: 800; color: <?php echo esc_attr( $accent ); ?>; }
				.sb-rows { border: 1px solid #e4e1ea; border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; font-size: 13.5px; }
				.sb-row { display: flex; justify-content: space-between; gap: 12px; }
				.sb-row span:first-child { color: #5f5e63; }
				.sb-row strong { color: #1b1b21; text-align: right; }
				.sb-note { font-size: 12px; line-height: 1.5; color: #5f5e63; background: #f8fafc; padding: 10px 12px; border-radius: 8px; border-left: 3px solid <?php echo esc_attr( $accent ); ?>; }
				.sb-actions { display: flex; flex-direction: column; gap: 10px; }
				.sb-btn {
					display: inline-flex; align-items: center; justify-content: center; min-height: 46px;
					padding: 0 20px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none;
					cursor: pointer; border: 0; transition: opacity 0.15s ease;
				}
				.sb-btn:hover { opacity: 0.92; }
				.sb-btn--ok { background: <?php echo esc_attr( $accent ); ?>; color: #fff; box-shadow: 0 8px 16px -4px rgba(0,0,0,0.18); }
				.sb-btn--cancel { background: #efecf5; color: #1b1b21; font-weight: 600; }
			</style>
		</head>
		<body>
			<main class="sb-card">
				<header class="sb-head">
					<h1><?php echo esc_html( $brand_name ); ?></h1>
					<span class="sb-badge">SANDBOX MODE</span>
				</header>
				<div class="sb-body">
					<div class="sb-qr-box">
						<img src="<?php echo esc_url( $qr_img ); ?>" alt="QR Thanh toán thử nghiệm" width="180" height="180" />
						<div class="sb-amount"><?php echo esc_html( $amount_fmt ); ?></div>
						<div style="font-size:12px;color:#5f5e63">Đơn vị thụ hưởng: <strong>TYPEE CUSTOM KEYBOARD STUDIO</strong></div>
					</div>

					<div class="sb-rows">
						<div class="sb-row"><span>Mã đơn hàng:</span><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></div>
						<div class="sb-row"><span>Nội dung thanh toán:</span><strong><?php echo esc_html( $order_code ); ?></strong></div>
						<div class="sb-row"><span>Người nhận:</span><strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong></div>
						<?php if ( ! $is_momo ) : ?>
							<div class="sb-row"><span>Thẻ test mẫu (NCB):</span><strong>9704198526191432198</strong></div>
						<?php endif; ?>
					</div>

					<div class="sb-note">
						Môi trường kiểm thử tích hợp sẵn (miễn phí 100%). Nhấn <strong>"Xác nhận thanh toán thành công"</strong> bên dưới để giả lập callback có chữ ký số HMAC trả về WooCommerce, tự động cập nhật đơn hàng và chuyển sang trang Hoàn tất.
					</div>

					<div class="sb-actions">
						<a class="sb-btn sb-btn--ok" href="<?php echo esc_url( $success_url ); ?>">
							✓ Xác nhận thanh toán thành công (<?php echo esc_html( $amount_fmt ); ?>)
						</a>
						<a class="sb-btn sb-btn--cancel" href="<?php echo esc_url( $cancel_url ); ?>">
							✕ Giả lập hủy giao dịch / Quay lại Thanh toán
						</a>
					</div>
				</div>
			</main>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Khai báo 3 class Gateway kế thừa WC_Payment_Gateway khi WooCommerce đã sẵn sàng.
	 */
	public static function register_gateway_classes() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		require_once __DIR__ . '/class-typee-gateway-definitions.php';
	}
}
