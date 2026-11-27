<?php
/**
 * Trang Đặt Hàng Thành Công – Order Received (Figma 82:8375)
 *
 * Hiển thị:
 *  - Hero xác nhận đơn hàng, mã đơn hàng chính thức (có nút sao chép), kích hoạt e-RMA theo SĐT
 *  - Nếu phương thức là VietQR (`typee_vietqr`): hiển thị trực tiếp ảnh mã QR Napas247 khớp đúng
 *    Tổng tiền & Nội dung chuyển khoản cùng nút sao chép nhanh STK / Số tiền / Nội dung
 *  - Nếu phương thức là MoMo / VNPay / COD: hiển thị trạng thái thanh toán & mã giao dịch thực tế
 *  - Chi tiết người nhận, địa chỉ giao hàng (Miễn phí vận chuyển 0₫) và danh sách linh kiện đã đặt
 *
 * @var WC_Order $order
 * @package typee-child
 * @version 8.1.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order ty-ty">
	<?php if ( $order ) : ?>
		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>
			<section class="ty-panel ty-empty">
				<span class="ty-empty__ic"><?php typee_the_icon( 'error', 28 ); ?></span>
				<h1 class="ty-empty__title">Giao dịch thanh toán chưa thành công</h1>
				<p class="ty-empty__desc">Ngân hàng hoặc ví điện tử đã từ chối giao dịch. Bạn có thể thực hiện thanh toán lại cho đơn hàng này.</p>
				<div class="ty-empty__actions">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="ty-btn ty-btn--primary">Thanh toán lại</a>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ty-btn ty-btn--soft">Về trang chủ</a>
				</div>
			</section>
		<?php else : ?>
			<?php
			$order_number = $order->get_order_number();
			$order_code   = '#TY-' . $order_number;
			$pay_method   = $order->get_payment_method();
			$is_paid      = $order->is_paid();
			$created_dt   = $order->get_date_created();
			$eta_ts       = $created_dt ? ( $created_dt->getTimestamp() + 2 * DAY_IN_SECONDS ) : ( time() + 2 * DAY_IN_SECONDS );
			$phone        = $order->get_billing_phone();

			// Lấy cấu hình VietQR nếu đơn chọn chuyển khoản VietQR.
			$vietqr_data = null;
			if ( 'typee_vietqr' === $pay_method && WC()->payment_gateways() ) {
				$gateways = WC()->payment_gateways()->payment_gateways();
				if ( isset( $gateways['typee_vietqr'] ) && method_exists( $gateways['typee_vietqr'], 'get_qr_details' ) ) {
					$vietqr_data = $gateways['typee_vietqr']->get_qr_details( $order );
				}
			}
			?>

			<!-- 1. HERO SUCCESS BANNER (Figma 82:8397) -->
			<section class="ty-ty-hero">
				<div class="ty-ty-hero__bar" aria-hidden="true"></div>
				<div class="ty-ty-hero__inner">
					<div class="ty-ty-hero__left">
						<div class="ty-ty-hero__emblem" aria-hidden="true">
							<?php typee_the_icon( 'check_circle', 34 ); ?>
						</div>
						<div class="ty-ty-hero__copy">
							<div class="ty-ty-hero__title-row">
								<h1 class="ty-ty-hero__title">Đặt hàng thành công!</h1>
								<span class="ty-ty-hero__badge">
									<?php echo $is_paid ? 'ĐÃ THANH TOÁN' : 'HỆ THỐNG ĐÃ GHI NHẬN'; ?>
								</span>
							</div>
							<p class="ty-ty-hero__desc">
								Cảm ơn bạn đã tin tưởng lựa chọn <strong>TYPEE Studio</strong>. Đơn hàng của bạn đã được chuyển tới bộ phận kỹ thuật viên Lab để kiểm định KCS và đóng gói chống sốc.
							</p>
							<div class="ty-ty-hero__meta-pills">
								<span class="ty-ty-meta-item">
									<?php typee_the_icon( 'local_shipping', 15 ); ?>
									<span>Dự kiến giao: <strong><?php echo esc_html( wp_date( 'd/m/Y', $eta_ts ) ); ?></strong> (Miễn phí vận chuyển 0₫)</span>
								</span>
								<?php if ( $phone ) : ?>
									<span class="ty-ty-meta-item ty-ty-meta-item--erma">
										<?php typee_the_icon( 'verified_user', 15 ); ?>
										<span>Đã kích hoạt <strong>e-RMA 12 tháng</strong> theo SĐT: <?php echo esc_html( $phone ); ?></span>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<div class="ty-ty-hero__right">
						<img
							class="ty-ty-code__brand"
							src="<?php echo esc_url( TYPEE_CHILD_URI . '/assets/images/logo-wordmark.png' ); ?>"
							alt="TYPEE"
							width="96"
							height="22"
							loading="lazy"
							decoding="async"
						/>
						<span class="ty-ty-code__kicker">MÃ ĐƠN HÀNG CHÍNH THỨC</span>
						<div class="ty-ty-code__row">
							<strong class="ty-mono ty-ty-code__val"><?php echo esc_html( $order_code ); ?></strong>
							<button type="button" class="ty-ty-copy-btn" data-ty-copy="<?php echo esc_attr( $order_number ); ?>">
								<?php typee_the_icon( 'content_copy', 14 ); ?>
								<span>Sao chép</span>
							</button>
						</div>
						<?php if ( $created_dt ) : ?>
							<span class="ty-ty-code__time">Khởi tạo: <?php echo esc_html( $created_dt->date_i18n( 'H:i:s - d/m/Y' ) ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<!-- 2. DUAL-COLUMN ORDER DETAILS & VIETQR / PAYMENT AUDIT (Figma 82:8444) -->
			<div class="ty-co-grid ty-ty-grid">
				<div class="ty-co-main">
					<?php if ( $vietqr_data && ! $is_paid ) : ?>
						<?php do_action( 'typee_thankyou_rendered_vietqr' ); ?>
						<section class="ty-co-card ty-ty-vietqr" aria-labelledby="ty-vietqr-heading">
							<header class="ty-co-card__head">
								<div class="ty-co-card__head-left">
									<span class="ty-co-num"><?php typee_the_icon( 'qr_code', 18 ); ?></span>
									<div>
										<h2 class="ty-co-card__title" id="ty-vietqr-heading">Quét mã VietQR để hoàn tất thanh toán</h2>
										<p class="ty-co-card__sub">Mở ứng dụng Ngân hàng bất kỳ và quét mã – số tiền và nội dung đã được điền tự động</p>
									</div>
								</div>
								<span class="ty-co-free-pill">Napas247 24/7</span>
							</header>

							<div class="ty-ty-vietqr__body">
								<div class="ty-ty-vietqr__qr">
									<img src="<?php echo esc_url( $vietqr_data['qr_url'] ); ?>" alt="Mã QR chuyển khoản VietQR đơn hàng" width="220" height="260" />
								</div>
								<div class="ty-ty-vietqr__info">
									<div class="ty-ty-bank-row">
										<span>Ngân hàng thụ hưởng</span>
										<strong><?php echo esc_html( $vietqr_data['bank_name'] ); ?></strong>
									</div>
									<div class="ty-ty-bank-row">
										<span>Chủ tài khoản</span>
										<strong><?php echo esc_html( $vietqr_data['account_name'] ); ?></strong>
									</div>
									<div class="ty-ty-bank-row">
										<span>Số tài khoản</span>
										<div class="ty-ty-bank-copy">
											<strong class="ty-mono"><?php echo esc_html( $vietqr_data['account_no'] ); ?></strong>
											<button type="button" class="ty-ty-copy-btn" data-ty-copy="<?php echo esc_attr( $vietqr_data['account_no'] ); ?>">Sao chép</button>
										</div>
									</div>
									<div class="ty-ty-bank-row">
										<span>Số tiền chính xác</span>
										<div class="ty-ty-bank-copy">
											<strong class="is-accent"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
											<button type="button" class="ty-ty-copy-btn" data-ty-copy="<?php echo esc_attr( (string) $vietqr_data['amount'] ); ?>">Sao chép</button>
										</div>
									</div>
									<div class="ty-ty-bank-row">
										<span>Nội dung chuyển khoản</span>
										<div class="ty-ty-bank-copy">
											<strong class="ty-mono is-accent"><?php echo esc_html( $vietqr_data['memo'] ); ?></strong>
											<button type="button" class="ty-ty-copy-btn" data-ty-copy="<?php echo esc_attr( $vietqr_data['memo'] ); ?>">Sao chép</button>
										</div>
									</div>
								</div>
							</div>
						</section>
					<?php endif; ?>

					<!-- Thông tin giao hàng & Người nhận (Figma 82:8446) -->
					<section class="ty-co-card" aria-labelledby="ty-ty-ship-title">
						<header class="ty-co-card__head">
							<div class="ty-co-card__head-left">
								<span class="ty-co-num">1</span>
								<h2 class="ty-co-card__title" id="ty-ty-ship-title">Thông tin giao hàng &amp; Người nhận</h2>
							</div>
							<span class="ty-co-secure-pill">Miễn phí vận chuyển • 0₫</span>
						</header>

						<div class="ty-ty-receiver-grid">
							<div class="ty-ty-box">
								<span class="ty-ty-box__label">NGƯỜI NHẬN KIỆN HÀNG</span>
								<strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong>
								<span><?php echo esc_html( $phone ); ?> <?php echo $order->get_billing_email() ? '• ' . esc_html( $order->get_billing_email() ) : ''; ?></span>
							</div>
							<div class="ty-ty-box">
								<span class="ty-ty-box__label">PHƯƠNG THỨC THANH TOÁN</span>
								<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
								<span>
									<?php
									if ( $is_paid ) {
										echo 'Đã thanh toán' . ( $order->get_transaction_id() ? ' • Mã GD: ' . esc_html( $order->get_transaction_id() ) : '' );
									} elseif ( 'cod' === $pay_method ) {
										echo 'Thanh toán tiền mặt khi nhận hàng (Đồng kiểm)';
									} else {
										echo 'Đang chờ xác nhận chuyển khoản';
									}
									?>
								</span>
							</div>
						</div>

						<div class="ty-ty-box" style="margin-top:12px">
							<span class="ty-ty-box__label">ĐỊA CHỈ BÀN GIAO</span>
							<strong><?php echo wp_kses_post( str_replace( '<br/>', ', ', $order->get_formatted_billing_address() ) ); ?></strong>
							<?php if ( $order->get_customer_note() ) : ?>
								<span class="ty-ty-note">Ghi chú kỹ thuật / giao nhận: <?php echo esc_html( $order->get_customer_note() ); ?></span>
							<?php endif; ?>
						</div>
					</section>

					<?php
					do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
					?>
				</div>

				<!-- Cột phải: Tóm tắt sản phẩm đã đặt -->
				<aside class="ty-co-aside">
					<div class="ty-co-summary">
						<header class="ty-co-summary__head">
							<div class="ty-co-summary__head-left">
								<?php typee_the_icon( 'receipt_long', 20, 'ty-co-summary__bag-ic' ); ?>
								<h2 class="ty-co-summary__title">Chi tiết đơn hàng</h2>
							</div>
							<span class="ty-co-summary__count"><?php echo esc_html( $order->get_item_count() . ' món' ); ?></span>
						</header>

						<div class="ty-co-items">
							<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
								<?php
								$prod    = $item->get_product();
								$img_id  = $prod ? $prod->get_image_id() : 0;
								$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
								?>
								<article class="ty-co-item">
									<div class="ty-co-item__thumb">
										<img src="<?php echo esc_url( $img_url ); ?>" alt="" width="64" height="64" />
										<span class="ty-co-item__qty">x<?php echo esc_html( $item->get_quantity() ); ?></span>
									</div>
									<div class="ty-co-item__body">
										<div class="ty-co-item__top">
											<h3 class="ty-co-item__name"><?php echo esc_html( $item->get_name() ); ?></h3>
											<span class="ty-co-item__price"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></span>
										</div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>

						<div class="ty-co-calc">
							<div class="ty-co-calc__row">
								<span>Tạm tính</span>
								<strong><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></strong>
							</div>
							<?php foreach ( $order->get_fees() as $fee ) : ?>
								<div class="ty-co-calc__row <?php echo (float) $fee->get_total() < 0 ? 'is-discount' : ''; ?>">
									<span><?php echo esc_html( $fee->get_name() ); ?></span>
									<strong><?php echo wp_kses_post( wc_price( $fee->get_total() ) ); ?></strong>
								</div>
							<?php endforeach; ?>
							<?php if ( (float) $order->get_discount_total() > 0 ) : ?>
								<div class="ty-co-calc__row is-discount">
									<span>Giảm giá Voucher</span>
									<strong>-<?php echo wp_kses_post( wc_price( $order->get_discount_total() ) ); ?></strong>
								</div>
							<?php endif; ?>
							<div class="ty-co-calc__row">
								<span class="ty-co-calc__ship-label">Phí vận chuyển <em class="ty-co-calc__freeship">FREESHIP</em></span>
								<strong class="is-emerald">0₫</strong>
							</div>
							<div class="ty-co-grand">
								<div class="ty-co-grand__left">
									<strong>Tổng thanh toán</strong>
									<span>Đã bao gồm VAT</span>
								</div>
								<div class="ty-co-grand__right">
									<div class="ty-co-grand__amount"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></div>
								</div>
							</div>
						</div>

						<div class="ty-ty-actions">
							<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Tiếp tục mua sắm</a>
							<?php if ( typee_url( 'tracking' ) ) : ?>
								<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'tracking' ) ); ?>">Tra cứu tiến độ đơn hàng</a>
							<?php endif; ?>
						</div>
					</div>
				</aside>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<section class="ty-panel ty-empty">
			<span class="ty-empty__ic"><?php typee_the_icon( 'check_circle', 28 ); ?></span>
			<h1 class="ty-empty__title">Cảm ơn bạn. Đơn hàng đã được hệ thống ghi nhận!</h1>
			<div class="ty-empty__actions">
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">Về trang chủ</a>
			</div>
		</section>
	<?php endif; ?>
</div>
