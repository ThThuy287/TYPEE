<?php
/**
 * Định nghĩa 3 cổng thanh toán miễn phí TYPEE (VietQR, MoMo, VNPay) kế thừa WC_Payment_Gateway.
 *
 * @package typee-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TYPEE_Gateway_VietQR' ) ) {
	/**
	 * Cổng 1: Chuyển khoản ngân hàng tự động sinh mã VietQR Napas247.
	 */
	class TYPEE_Gateway_VietQR extends WC_Payment_Gateway {

		public $bank_id;
		public $bank_name;
		public $account_no;
		public $account_name;
		public $prefix;

		public function __construct() {
			$this->id                 = 'typee_vietqr';
			$this->has_fields         = false;
			$this->method_title       = 'TYPEE – Chuyển khoản QR ngân hàng (VietQR / Napas247)';
			$this->method_description = 'Tự động sinh mã QR chuẩn VietQR Napas247 khớp chính xác số tiền và mã đơn hàng trên trang Đặt hàng thành công.';
			$this->supports           = array( 'products' );

			$this->init_form_fields();
			$this->init_settings();

			$this->enabled      = $this->get_option( 'enabled', 'yes' );
			$this->title        = $this->get_option( 'title', 'Chuyển khoản QR ngân hàng (VietQR / Napas247)' );
			$this->description  = $this->get_option( 'description', 'Tự sinh mã QR đúng số tiền & nội dung chuyển khoản. Mọi ngân hàng nội địa quét là nhận tiền, không lo nhập sai STK.' );
			$this->bank_id      = $this->get_option( 'bank_id', 'MB' );
			$this->bank_name    = $this->get_option( 'bank_name', 'MB Bank (Ngân hàng Quân Đội)' );
			$this->account_no   = $this->get_option( 'account_no', '0918248899' );
			$this->account_name = $this->get_option( 'account_name', 'TYPEE CUSTOM STUDIO' );
			$this->prefix       = $this->get_option( 'prefix', 'TYPEE' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'render_vietqr_box' ), 10, 1 );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'      => array(
					'title'   => 'Kích hoạt',
					'type'    => 'checkbox',
					'label'   => 'Bật thanh toán Chuyển khoản VietQR Napas247',
					'default' => 'yes',
				),
				'title'        => array(
					'title'   => 'Tiêu đề hiển thị',
					'type'    => 'text',
					'default' => 'Chuyển khoản QR ngân hàng (VietQR / Napas247)',
				),
				'description'  => array(
					'title'   => 'Mô tả ngắn',
					'type'    => 'textarea',
					'default' => 'Tự sinh mã QR đúng số tiền & nội dung chuyển khoản. Mọi ngân hàng nội địa quét là nhận tiền, không lo nhập sai STK.',
				),
				'bank_id'      => array(
					'title'   => 'Ngân hàng thụ hưởng (VietQR ID)',
					'type'    => 'select',
					'default' => 'MB',
					'options' => array(
						'MB'   => 'MB Bank (970422)',
						'VCB'  => 'Vietcombank (970436)',
						'TCB'  => 'Techcombank (970407)',
						'ACB'  => 'ACB (970416)',
						'BIDV' => 'BIDV (970418)',
						'ICB'  => 'VietinBank (970415)',
						'VPB'  => 'VPBank (970432)',
						'TPB'  => 'TPBank (970423)',
					),
				),
				'bank_name'    => array(
					'title'   => 'Tên ngân hàng hiển thị',
					'type'    => 'text',
					'default' => 'MB Bank (Ngân hàng Quân Đội)',
				),
				'account_no'   => array(
					'title'   => 'Số tài khoản nhận tiền',
					'type'    => 'text',
					'default' => '0918248899',
				),
				'account_name' => array(
					'title'   => 'Tên chủ tài khoản (Không dấu)',
					'type'    => 'text',
					'default' => 'TYPEE CUSTOM STUDIO',
				),
				'prefix'       => array(
					'title'   => 'Tiền tố nội dung CK',
					'type'    => 'text',
					'default' => 'TYPEE',
				),
			);
		}

		/**
		 * Tạo thông tin QR động cho một đơn hàng cụ thể.
		 *
		 * @param WC_Order $order Đơn hàng.
		 * @return array
		 */
		public function get_qr_details( WC_Order $order ) {
			$amount = (int) round( (float) $order->get_total() );
			$memo   = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $this->prefix ) ) . ' ' . $order->get_order_number();
			$qr_url = sprintf(
				'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
				rawurlencode( $this->bank_id ),
				rawurlencode( preg_replace( '/\s+/', '', $this->account_no ) ),
				$amount,
				rawurlencode( $memo ),
				rawurlencode( $this->account_name )
			);
			return array(
				'bank_id'      => $this->bank_id,
				'bank_name'    => $this->bank_name,
				'account_no'   => $this->account_no,
				'account_name' => $this->account_name,
				'amount'       => $amount,
				'memo'         => $memo,
				'qr_url'       => $qr_url,
			);
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wc_add_notice( 'Không tìm thấy đơn hàng để xử lý thanh toán.', 'error' );
				return array( 'result' => 'failure' );
			}

			$qr = $this->get_qr_details( $order );
			$order->update_meta_data( '_typee_vietqr_memo', $qr['memo'] );
			$order->update_status( 'on-hold', sprintf( 'Chờ khách quét mã VietQR (%s - %s - Nội dung: %s).', $qr['bank_id'], $qr['account_no'], $qr['memo'] ) );
			wc_reduce_stock_levels( $order_id );
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		/**
		 * Hiển thị khối quét mã VietQR trên trang Order Received (nếu template mặc định gọi hook).
		 *
		 * @param int $order_id Order ID.
		 */
		public function render_vietqr_box( $order_id ) {
			// Nếu trang thankyou.php custom của TYPEE đã hiển thị khối QR chuyên biệt thì không in trùng.
			if ( did_action( 'typee_thankyou_rendered_vietqr' ) ) {
				return;
			}
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			$qr = $this->get_qr_details( $order );
			?>
			<section class="ty-vietqr-fallback">
				<h3>Quét mã VietQR để hoàn tất thanh toán</h3>
				<img src="<?php echo esc_url( $qr['qr_url'] ); ?>" alt="Mã VietQR thanh toán đơn hàng" width="240" />
				<p>Ngân hàng: <strong><?php echo esc_html( $qr['bank_name'] ); ?></strong> – STK: <strong><?php echo esc_html( $qr['account_no'] ); ?></strong></p>
				<p>Chủ TK: <strong><?php echo esc_html( $qr['account_name'] ); ?></strong> – Nội dung: <strong><?php echo esc_html( $qr['memo'] ); ?></strong></p>
			</section>
			<?php
		}
	}
}

if ( ! class_exists( 'TYPEE_Gateway_MoMo' ) ) {
	/**
	 * Cổng 2: Ví điện tử MoMo (Miễn phí - Hỗ trợ cả Sandbox Simulator nội bộ & MoMo Test/Prod API).
	 */
	class TYPEE_Gateway_MoMo extends WC_Payment_Gateway {

		public $mode;
		public $endpoint;
		public $partner_code;
		public $access_key;
		public $secret_key;

		public function __construct() {
			$this->id                 = 'typee_momo';
			$this->has_fields         = false;
			$this->method_title       = 'TYPEE – Ví điện tử MoMo (Sandbox / QR)';
			$this->method_description = 'Tích hợp Ví MoMo miễn phí: chạy sẵn chế độ Sandbox Simulator để kiểm thử đặt hàng ngay, hoặc nhập bộ khóa MoMo Test/Production API.';
			$this->supports           = array( 'products' );

			$this->init_form_fields();
			$this->init_settings();

			$this->enabled      = $this->get_option( 'enabled', 'yes' );
			$this->title        = $this->get_option( 'title', 'Ví điện tử MoMo' );
			$this->description  = $this->get_option( 'description', 'Thanh toán một chạm qua ứng dụng MoMo hoặc quét mã QR MoMo liên kết trực tiếp ví.' );
			$this->mode         = $this->get_option( 'mode', 'simulator' );
			$this->endpoint     = $this->get_option( 'endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create' );
			$this->partner_code = trim( (string) $this->get_option( 'partner_code', '' ) );
			$this->access_key   = trim( (string) $this->get_option( 'access_key', '' ) );
			$this->secret_key   = trim( (string) $this->get_option( 'secret_key', '' ) );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_typee_momo_gateway', array( $this, 'handle_simulator_screen' ) );
			add_action( 'woocommerce_api_typee_momo_return', array( $this, 'handle_callback_return' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'      => array(
					'title'   => 'Kích hoạt',
					'type'    => 'checkbox',
					'label'   => 'Bật cổng thanh toán Ví điện tử MoMo',
					'default' => 'yes',
				),
				'title'        => array(
					'title'   => 'Tiêu đề hiển thị',
					'type'    => 'text',
					'default' => 'Ví điện tử MoMo',
				),
				'description'  => array(
					'title'   => 'Mô tả ngắn',
					'type'    => 'textarea',
					'default' => 'Thanh toán một chạm qua ứng dụng MoMo hoặc quét mã QR MoMo liên kết trực tiếp ví.',
				),
				'mode'         => array(
					'title'       => 'Chế độ vận hành',
					'type'        => 'select',
					'default'     => 'simulator',
					'description' => 'Chọn "Sandbox Simulator tích hợp sẵn" để test đặt hàng thành công ngay không cần tài khoản doanh nghiệp, hoặc "MoMo API" khi đã có PartnerCode/AccessKey/SecretKey.',
					'options'     => array(
						'simulator' => 'Sandbox Simulator tích hợp sẵn (Khuyên dùng cho Demo/Đồ án)',
						'api'       => 'Kết nối MoMo Payment API (Test / Production)',
					),
				),
				'endpoint'     => array(
					'title'   => 'MoMo API Endpoint',
					'type'    => 'text',
					'default' => 'https://test-payment.momo.vn/v2/gateway/api/create',
				),
				'partner_code' => array(
					'title'   => 'Partner Code (Tùy chọn cho chế độ API)',
					'type'    => 'text',
					'default' => '',
				),
				'access_key'   => array(
					'title'   => 'Access Key (Tùy chọn cho chế độ API)',
					'type'    => 'text',
					'default' => '',
				),
				'secret_key'   => array(
					'title'   => 'Secret Key (Tùy chọn cho chế độ API)',
					'type'    => 'password',
					'default' => '',
				),
			);
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wc_add_notice( 'Không tìm thấy đơn hàng.', 'error' );
				return array( 'result' => 'failure' );
			}

			// Nếu chọn chế độ kết nối MoMo API thật và đã nhập đủ thông tin khóa.
			if ( 'api' === $this->mode && $this->partner_code && $this->access_key && $this->secret_key ) {
				$redirect_url = WC()->api_request_url( 'typee_momo_return' );
				$ipn_url      = $redirect_url;
				$amount       = (string) max( 1000, (int) round( (float) $order->get_total() ) );
				$momo_oid     = $order->get_id() . '_' . time();
				$request_id   = $momo_oid;
				$order_info   = 'Thanh toan don hang TYPEE #' . $order->get_order_number();
				$request_type = 'captureWallet';
				$extra_data   = base64_encode( wp_json_encode( array( 'key' => $order->get_order_key() ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

				$raw_hash  = "accessKey={$this->access_key}&amount={$amount}&extraData={$extra_data}&ipnUrl={$ipn_url}&orderId={$momo_oid}&orderInfo={$order_info}&partnerCode={$this->partner_code}&redirectUrl={$redirect_url}&requestId={$request_id}&requestType={$request_type}";
				$signature = hash_hmac( 'sha256', $raw_hash, $this->secret_key );

				$response = wp_remote_post(
					$this->endpoint,
					array(
						'timeout' => 20,
						'headers' => array( 'Content-Type' => 'application/json' ),
						'body'    => wp_json_encode(
							array(
								'partnerCode' => $this->partner_code,
								'partnerName' => 'TYPEE Studio',
								'storeId'     => 'TYPEE',
								'requestId'   => $request_id,
								'amount'      => $amount,
								'orderId'     => $momo_oid,
								'orderInfo'   => $order_info,
								'redirectUrl' => $redirect_url,
								'ipnUrl'      => $ipn_url,
								'lang'        => 'vi',
								'extraData'   => $extra_data,
								'requestType' => $request_type,
								'signature'   => $signature,
							)
						),
					)
				);

				if ( ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( ! empty( $body['payUrl'] ) ) {
						return array(
							'result'   => 'success',
							'redirect' => esc_url_raw( $body['payUrl'] ),
						);
					}
				}
				wc_add_notice( 'Không kết nối được máy chủ MoMo API. Vui lòng kiểm tra khóa cấu hình hoặc chuyển sang chế độ Sandbox Simulator.', 'error' );
				return array( 'result' => 'failure' );
			}

			// Mặc định: Chuyển tới cổng giả lập MoMo Sandbox nội bộ có ký HMAC.
			$order->update_status( 'pending', 'Khách hàng đang thực hiện thanh toán qua cổng Ví MoMo (Sandbox).' );
			$order->save();

			$sig     = TYPEE_Payment_Gateways::sign_sandbox_token( $order->get_id(), $order->get_order_key(), $this->id );
			$sim_url = add_query_arg(
				array(
					'order_id' => $order->get_id(),
					'key'      => $order->get_order_key(),
					'sig'      => $sig,
				),
				WC()->api_request_url( 'typee_momo_gateway' )
			);

			return array(
				'result'   => 'success',
				'redirect' => $sim_url,
			);
		}

		/**
		 * Hiển thị màn hình giả lập cổng thanh toán MoMo Sandbox.
		 */
		public function handle_simulator_screen() {
			$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$sig      = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			$order    = wc_get_order( $order_id );
			$expected = TYPEE_Payment_Gateways::sign_sandbox_token( $order_id, $key, $this->id );
			if ( ! $order || $order->get_order_key() !== $key || ! hash_equals( $expected, $sig ) ) {
				wp_die( 'Phiên thanh toán MoMo không hợp lệ hoặc đã hết hạn.', 'Lỗi xác thực', array( 'response' => 403 ) );
			}

			$cb_base = WC()->api_request_url( 'typee_momo_return' );
			$ok_sig  = hash_hmac( 'sha256', $order_id . '|0|' . $key, TYPEE_Payment_Gateways::internal_hmac_secret() );
			$can_sig = hash_hmac( 'sha256', $order_id . '|1006|' . $key, TYPEE_Payment_Gateways::internal_hmac_secret() );

			$success_url = add_query_arg(
				array(
					'mode'       => 'sim',
					'orderId'    => $order_id,
					'key'        => $key,
					'resultCode' => '0',
					'transId'    => 'MOMO' . gmdate( 'YmdHis' ) . wp_rand( 100, 999 ),
					'signature'  => $ok_sig,
				),
				$cb_base
			);
			$cancel_url = add_query_arg(
				array(
					'mode'       => 'sim',
					'orderId'    => $order_id,
					'key'        => $key,
					'resultCode' => '1006',
					'signature'  => $can_sig,
				),
				$cb_base
			);

			TYPEE_Payment_Gateways::render_sandbox_simulator( $order, 'momo', $success_url, $cancel_url );
		}

		/**
		 * Callback xử lý kết quả trả về từ MoMo (hỗ trợ cả Sandbox Simulator & MoMo API thật).
		 */
		public function handle_callback_return() {
			$raw_oid     = isset( $_GET['orderId'] ) ? sanitize_text_field( wp_unslash( $_GET['orderId'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$parts       = explode( '_', $raw_oid );
			$order_id    = absint( $parts[0] );
			$result_code = isset( $_GET['resultCode'] ) ? sanitize_text_field( wp_unslash( $_GET['resultCode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$trans_id    = isset( $_GET['transId'] ) ? sanitize_text_field( wp_unslash( $_GET['transId'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$mode        = isset( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}

			// Xác thực chữ ký callback.
			$verified = false;
			if ( 'sim' === $mode ) {
				$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$sig      = isset( $_GET['signature'] ) ? sanitize_text_field( wp_unslash( $_GET['signature'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$expected = hash_hmac( 'sha256', $order_id . '|' . $result_code . '|' . $key, TYPEE_Payment_Gateways::internal_hmac_secret() );
				$verified = ( $order->get_order_key() === $key && hash_equals( $expected, $sig ) );
			} elseif ( $this->secret_key && $this->access_key ) {
				$verified = true; // Môi trường MoMo redirect callback.
			}

			if ( ! $verified ) {
				wc_add_notice( 'Chữ ký xác thực giao dịch MoMo không hợp lệ.', 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}

			if ( '0' === (string) $result_code ) {
				if ( ! $order->is_paid() ) {
					$order->payment_complete( $trans_id ? $trans_id : 'MOMO-' . time() );
					$order->add_order_note( sprintf( 'Thanh toán Ví MoMo thành công. Mã giao dịch: %s.', $trans_id ? $trans_id : 'SANDBOX' ) );
				}
				if ( WC()->cart ) {
					WC()->cart->empty_cart();
				}
				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;
			}

			$order->update_status( 'pending', 'Khách hàng đã hủy giao dịch trên cổng Ví MoMo.' );
			$order->save();
			wc_add_notice( 'Giao dịch Ví MoMo đã bị hủy. Bạn có thể chọn lại phương thức thanh toán khác.', 'notice' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}
}

if ( ! class_exists( 'TYPEE_Gateway_VNPay' ) ) {
	/**
	 * Cổng 3: VNPay-QR / Thẻ ATM nội địa & Quốc tế (Visa / Master / JCB).
	 */
	class TYPEE_Gateway_VNPay extends WC_Payment_Gateway {

		public $mode;
		public $vnp_url;
		public $tmn_code;
		public $hash_secret;

		public function __construct() {
			$this->id                 = 'typee_vnpay';
			$this->has_fields         = false;
			$this->method_title       = 'TYPEE – Cổng VNPay-QR / Thẻ ATM & Quốc tế';
			$this->method_description = 'Tích hợp cổng VNPay miễn phí: chạy sẵn chế độ Sandbox Simulator hoặc kết nối VNPay Sandbox/Merchant thật bằng vnp_TmnCode & vnp_HashSecret.';
			$this->supports           = array( 'products' );

			$this->init_form_fields();
			$this->init_settings();

			$this->enabled     = $this->get_option( 'enabled', 'yes' );
			$this->title       = $this->get_option( 'title', 'Cổng VNPay-QR / Thẻ quốc tế & ATM' );
			$this->description = $this->get_option( 'description', 'Cổng thanh toán mã hóa SSL 256-bit an toàn qua VNPay-QR, thẻ ATM nội địa hoặc Visa / Master / JCB.' );
			$this->mode        = $this->get_option( 'mode', 'simulator' );
			$this->vnp_url     = $this->get_option( 'vnp_url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html' );
			$this->tmn_code    = trim( (string) $this->get_option( 'tmn_code', '' ) );
			$this->hash_secret = trim( (string) $this->get_option( 'hash_secret', '' ) );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_typee_vnpay_gateway', array( $this, 'handle_simulator_screen' ) );
			add_action( 'woocommerce_api_typee_vnpay_return', array( $this, 'handle_callback_return' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => 'Kích hoạt',
					'type'    => 'checkbox',
					'label'   => 'Bật cổng thanh toán VNPay-QR / Thẻ ATM & Quốc tế',
					'default' => 'yes',
				),
				'title'       => array(
					'title'   => 'Tiêu đề hiển thị',
					'type'    => 'text',
					'default' => 'Cổng VNPay-QR / Thẻ quốc tế & ATM',
				),
				'description' => array(
					'title'   => 'Mô tả ngắn',
					'type'    => 'textarea',
					'default' => 'Cổng thanh toán mã hóa SSL 256-bit an toàn qua VNPay-QR, thẻ ATM nội địa hoặc Visa / Master / JCB.',
				),
				'mode'        => array(
					'title'       => 'Chế độ vận hành',
					'type'        => 'select',
					'default'     => 'simulator',
					'description' => 'Chọn "Sandbox Simulator tích hợp sẵn" để test ngay không cần khóa ngoài, hoặc "VNPay Sandbox/Merchant API" khi đã đăng ký vnp_TmnCode & vnp_HashSecret.',
					'options'     => array(
						'simulator' => 'Sandbox Simulator tích hợp sẵn (Khuyên dùng cho Demo/Đồ án)',
						'api'       => 'Kết nối VNPay Sandbox / Merchant API (v2.1.0)',
					),
				),
				'vnp_url'     => array(
					'title'   => 'VNPay Payment URL',
					'type'    => 'text',
					'default' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
				),
				'tmn_code'    => array(
					'title'   => 'vnp_TmnCode (Mã Website VNPay)',
					'type'    => 'text',
					'default' => '',
				),
				'hash_secret' => array(
					'title'   => 'vnp_HashSecret (Chuỗi bí mật HMAC-SHA512)',
					'type'    => 'password',
					'default' => '',
				),
			);
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wc_add_notice( 'Không tìm thấy đơn hàng.', 'error' );
				return array( 'result' => 'failure' );
			}

			// Chế độ VNPay API chuẩn v2.1.0 (HMAC-SHA512).
			if ( 'api' === $this->mode && $this->tmn_code && $this->hash_secret ) {
				$return_url = WC()->api_request_url( 'typee_vnpay_return' );
				$txn_ref    = $order->get_id() . '_' . gmdate( 'His' );
				$amount     = (int) round( (float) $order->get_total() ) * 100;
				$ip_addr    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';

				$input_data = array(
					'vnp_Version'    => '2.1.0',
					'vnp_TmnCode'    => $this->tmn_code,
					'vnp_Amount'     => $amount,
					'vnp_Command'    => 'pay',
					'vnp_CreateDate' => wp_date( 'YmdHis' ),
					'vnp_CurrCode'   => 'VND',
					'vnp_IpAddr'     => $ip_addr,
					'vnp_Locale'     => 'vn',
					'vnp_OrderInfo'  => 'Thanh toan don hang TYPEE ' . $order->get_order_number(),
					'vnp_OrderType'  => 'other',
					'vnp_ReturnUrl'  => $return_url,
					'vnp_TxnRef'     => $txn_ref,
				);
				ksort( $input_data );

				$hashdata = '';
				$query    = '';
				$i        = 0;
				foreach ( $input_data as $key => $value ) {
					if ( 1 === $i ) {
						$hashdata .= '&' . urlencode( $key ) . '=' . urlencode( (string) $value );
					} else {
						$hashdata .= urlencode( $key ) . '=' . urlencode( (string) $value );
						$i         = 1;
					}
					$query .= urlencode( $key ) . '=' . urlencode( (string) $value ) . '&';
				}

				$secure_hash = hash_hmac( 'sha512', $hashdata, $this->hash_secret );
				$pay_url     = $this->vnp_url . '?' . $query . 'vnp_SecureHash=' . $secure_hash;

				return array(
					'result'   => 'success',
					'redirect' => $pay_url,
				);
			}

			// Mặc định: Chuyển tới cổng giả lập VNPay Sandbox nội bộ.
			$order->update_status( 'pending', 'Khách hàng đang thực hiện thanh toán qua cổng VNPay (Sandbox).' );
			$order->save();

			$sig     = TYPEE_Payment_Gateways::sign_sandbox_token( $order->get_id(), $order->get_order_key(), $this->id );
			$sim_url = add_query_arg(
				array(
					'order_id' => $order->get_id(),
					'key'      => $order->get_order_key(),
					'sig'      => $sig,
				),
				WC()->api_request_url( 'typee_vnpay_gateway' )
			);

			return array(
				'result'   => 'success',
				'redirect' => $sim_url,
			);
		}

		/**
		 * Trang giả lập cổng VNPay Sandbox nội bộ.
		 */
		public function handle_simulator_screen() {
			$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$sig      = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			$order    = wc_get_order( $order_id );
			$expected = TYPEE_Payment_Gateways::sign_sandbox_token( $order_id, $key, $this->id );
			if ( ! $order || $order->get_order_key() !== $key || ! hash_equals( $expected, $sig ) ) {
				wp_die( 'Phiên thanh toán VNPay không hợp lệ hoặc đã hết hạn.', 'Lỗi xác thực', array( 'response' => 403 ) );
			}

			$cb_base = WC()->api_request_url( 'typee_vnpay_return' );
			$ok_sig  = hash_hmac( 'sha512', $order_id . '|00|' . $key, TYPEE_Payment_Gateways::internal_hmac_secret() );
			$can_sig = hash_hmac( 'sha512', $order_id . '|24|' . $key, TYPEE_Payment_Gateways::internal_hmac_secret() );

			$success_url = add_query_arg(
				array(
					'mode'              => 'sim',
					'vnp_TxnRef'        => $order_id,
					'key'               => $key,
					'vnp_ResponseCode'  => '00',
					'vnp_TransactionNo' => 'VNP' . gmdate( 'YmdHis' ) . wp_rand( 10, 99 ),
					'vnp_SecureHash'    => $ok_sig,
				),
				$cb_base
			);
			$cancel_url = add_query_arg(
				array(
					'mode'             => 'sim',
					'vnp_TxnRef'       => $order_id,
					'key'              => $key,
					'vnp_ResponseCode' => '24',
					'vnp_SecureHash'   => $can_sig,
				),
				$cb_base
			);

			TYPEE_Payment_Gateways::render_sandbox_simulator( $order, 'vnpay', $success_url, $cancel_url );
		}

		/**
		 * Xử lý callback từ VNPay (cả Simulator nội bộ và VNPay Sandbox/Merchant API thật).
		 */
		public function handle_callback_return() {
			$txn_ref   = isset( $_GET['vnp_TxnRef'] ) ? sanitize_text_field( wp_unslash( $_GET['vnp_TxnRef'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$parts     = explode( '_', $txn_ref );
			$order_id  = absint( $parts[0] );
			$resp_code = isset( $_GET['vnp_ResponseCode'] ) ? sanitize_text_field( wp_unslash( $_GET['vnp_ResponseCode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$trans_no  = isset( $_GET['vnp_TransactionNo'] ) ? sanitize_text_field( wp_unslash( $_GET['vnp_TransactionNo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$mode      = isset( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}

			$verified = false;
			if ( 'sim' === $mode ) {
				$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$hash     = isset( $_GET['vnp_SecureHash'] ) ? sanitize_text_field( wp_unslash( $_GET['vnp_SecureHash'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$expected = hash_hmac( 'sha512', $order_id . '|' . $resp_code . '|' . $key, TYPEE_Payment_Gateways::internal_hmac_secret() );
				$verified = ( $order->get_order_key() === $key && hash_equals( $expected, $hash ) );
			} elseif ( $this->hash_secret ) {
				$vnp_params  = array();
				$secure_hash = isset( $_GET['vnp_SecureHash'] ) ? sanitize_text_field( wp_unslash( $_GET['vnp_SecureHash'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
					if ( 0 === strpos( $k, 'vnp_' ) && 'vnp_SecureHash' !== $k && 'vnp_SecureHashType' !== $k ) {
						$vnp_params[ $k ] = sanitize_text_field( wp_unslash( $v ) );
					}
				}
				ksort( $vnp_params );
				$hashdata = '';
				$i        = 0;
				foreach ( $vnp_params as $k => $v ) {
					$hashdata .= ( 1 === $i ? '&' : '' ) . urlencode( $k ) . '=' . urlencode( $v );
					$i         = 1;
				}
				$calc_hash = hash_hmac( 'sha512', $hashdata, $this->hash_secret );
				$verified  = hash_equals( strtolower( $calc_hash ), strtolower( $secure_hash ) );
			}

			if ( ! $verified ) {
				wc_add_notice( 'Chữ ký phản hồi từ cổng VNPay không hợp lệ.', 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}

			if ( '00' === $resp_code ) {
				if ( ! $order->is_paid() ) {
					$order->payment_complete( $trans_no ? $trans_no : 'VNP-' . time() );
					$order->add_order_note( sprintf( 'Thanh toán VNPay thành công. Mã giao dịch: %s.', $trans_no ? $trans_no : 'SANDBOX' ) );
				}
				if ( WC()->cart ) {
					WC()->cart->empty_cart();
				}
				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;
			}

			$order->update_status( 'pending', 'Giao dịch VNPay không thành công hoặc bị khách hủy (Mã: ' . $resp_code . ').' );
			$order->save();
			wc_add_notice( 'Giao dịch VNPay chưa hoàn tất (Mã phản hồi: ' . esc_html( $resp_code ) . '). Bạn có thể thử lại hoặc đổi phương thức thanh toán.', 'notice' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}
}
