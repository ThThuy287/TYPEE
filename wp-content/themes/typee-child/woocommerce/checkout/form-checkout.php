<?php
/**
 * Checkout Form – One-Page Checkout 2 Cột (Figma 82:1187)
 *
 * Cột trái (7/12):
 *  - Mục 1: Thông tin người nhận & Giao nhận + Ghi chú kỹ thuật Modding + VAT
 *  - Mục 2: Phương thức vận chuyển (Duy nhất 1 phương thức Miễn phí 0₫ mặc định)
 *  - Mục 3: Phương thức thanh toán (VietQR, Ví MoMo, COD, VNPay)
 * Cột phải Sticky (5/12):
 *  - Đơn hàng của bạn (Sản phẩm thật từ giỏ hàng + Voucher + Bảng tính tiền + Cam kết e-RMA + Nút ĐẶT HÀNG NGAY)
 *
 * @package typee-child
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Dự phòng nếu site chọn checkout_layout khác mặc định của Flatsome.
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo '<div class="ty-panel ty-empty"><p>' . esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', 'Vui lòng đăng nhập tài khoản để tiến hành đặt hàng.' ) ) . '</p>';
	echo '<div class="ty-empty__actions"><a class="ty-btn ty-btn--primary" href="' . esc_url( typee_url( 'account' ) ) . '">Đăng nhập ngay</a></div></div>';
	return;
}

$cart_count   = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
$hotline_raw  = preg_replace( '/\s+/', '', typee_copy( 'hotline' ) );
$warranty_url = typee_url( 'warranty' ) ? typee_url( 'warranty' ) : home_url( '/chinh-sach-bao-hanh/' );
$terms_url    = typee_url( 'terms' ) ? typee_url( 'terms' ) : $warranty_url;
?>

<form
	name="checkout"
	method="post"
	class="checkout woocommerce-checkout ty-co-form"
	action="<?php echo esc_url( wc_get_checkout_url() ); ?>"
	enctype="multipart/form-data"
	aria-label="Thanh toán đơn hàng"
>
	<!-- Kênh hiển thị thông báo lỗi / trạng thái khi submit AJAX -->
	<div class="ty-co-notices-anchor"></div>

	<div class="ty-co-grid">
		<!-- ==================== LEFT COLUMN (7/12) ==================== -->
		<div class="ty-co-main">
			<?php if ( $checkout->get_checkout_fields() ) : ?>
				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<!-- SECTION 1: Thông tin người nhận & Giao nhận (Figma 82:1228) -->
				<section class="ty-co-card" id="customer_details" aria-labelledby="ty-co-sec1-title">
					<header class="ty-co-card__head">
						<div class="ty-co-card__head-left">
							<span class="ty-co-num">1</span>
							<div>
								<h2 class="ty-co-card__title" id="ty-co-sec1-title">Thông tin người nhận &amp; Giao nhận</h2>
								<p class="ty-co-card__sub">Chứng từ bảo hành điện tử e-RMA sẽ liên kết trực tiếp qua SĐT và Email này</p>
							</div>
						</div>
						<span class="ty-co-secure-pill">
							<?php typee_the_icon( 'lock', 13 ); ?>
							<span>Bảo mật 100%</span>
						</span>
					</header>

					<div class="ty-co-fields-wrap">
						<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

						<div class="woocommerce-billing-fields__field-wrapper ty-co-billing-grid">
							<?php
							$billing_fields = $checkout->get_checkout_fields( 'billing' );
							foreach ( $billing_fields as $key => $field ) {
								woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
							}
							?>
						</div>

						<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>

						<!-- Ghi chú đơn hàng & Yêu cầu kỹ thuật Modding (Figma 82:1307) -->
						<div class="ty-co-notes-block">
							<div class="ty-co-notes-head">
								<label for="order_comments" class="ty-co-notes-label">
									<?php typee_the_icon( 'handyman', 15, 'ty-co-notes-ic' ); ?>
									<span>Ghi chú đơn hàng &amp; Yêu cầu kỹ thuật Modding</span>
								</label>
								<span class="ty-co-notes-free">Tùy chọn kỹ thuật miễn phí</span>
							</div>
							<?php
							$order_fields = $checkout->get_checkout_fields( 'order' );
							if ( isset( $order_fields['order_comments'] ) ) {
								$note_field          = $order_fields['order_comments'];
								$note_field['label'] = '';
								woocommerce_form_field( 'order_comments', $note_field, $checkout->get_value( 'order_comments' ) );
							}
							?>
						</div>

						<!-- Tùy chọn nhanh: Lưu thông tin & Xuất hóa đơn VAT (Figma 82:1318) -->
						<div class="ty-co-fast-checks">
							<label class="ty-co-check">
								<input type="checkbox" name="typee_save_receiver" value="1" checked data-ty-save-receiver />
								<span>Lưu thông tin giao hàng cho lần mua tiếp theo</span>
							</label>
							<label class="ty-co-check">
								<input type="checkbox" name="typee_require_vat" value="1" data-ty-vat-toggle />
								<span>Xuất hóa đơn điện tử VAT doanh nghiệp</span>
							</label>
						</div>

						<div class="ty-co-vat-box" data-ty-vat-box hidden>
							<div class="ty-co-vat-grid">
								<p class="form-row form-row-first ty-co-field">
									<label for="typee_vat_company">Tên công ty / Đơn vị <abbr class="required" title="bắt buộc">*</abbr></label>
									<span class="woocommerce-input-wrapper">
										<input type="text" class="input-text" id="typee_vat_company" name="typee_vat_company" placeholder="VD: Công ty TNHH Công Nghệ TYPEE" />
									</span>
								</p>
								<p class="form-row form-row-last ty-co-field">
									<label for="typee_vat_tax_code">Mã số thuế <abbr class="required" title="bắt buộc">*</abbr></label>
									<span class="woocommerce-input-wrapper">
										<input type="text" class="input-text" id="typee_vat_tax_code" name="typee_vat_tax_code" placeholder="VD: 0316889900" />
									</span>
								</p>
								<p class="form-row form-row-wide ty-co-field">
									<label for="typee_vat_address">Địa chỉ đăng ký kinh doanh</label>
									<span class="woocommerce-input-wrapper">
										<input type="text" class="input-text" id="typee_vat_address" name="typee_vat_address" placeholder="Nhập địa chỉ ghi trên hóa đơn đỏ..." />
									</span>
								</p>
							</div>
						</div>
					</div>
				</section>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
			<?php endif; ?>

			<!-- SECTION 2: Phương thức vận chuyển – Duy nhất 1 phương thức Free Shipping 0₫ tự động chọn (Figma 82:1327) -->
			<section class="ty-co-card" aria-labelledby="ty-co-sec2-title">
				<header class="ty-co-card__head">
					<div class="ty-co-card__head-left">
						<span class="ty-co-num">2</span>
						<div>
							<h2 class="ty-co-card__title" id="ty-co-sec2-title">Phương thức vận chuyển</h2>
							<p class="ty-co-card__sub">Đơn hàng custom được đóng gói chống sốc 3 lớp cùng tem niêm phong Studio</p>
						</div>
					</div>
				</header>

				<div class="ty-co-ship-single" role="group" aria-label="Vận chuyển miễn phí mặc định">
					<input type="hidden" name="shipping_method[0]" value="<?php echo esc_attr( class_exists( 'TYPEE_Checkout_Service' ) ? TYPEE_Checkout_Service::FREE_SHIP_RATE_ID : 'free_shipping:1' ); ?>" />
					<div class="ty-co-ship-card is-selected">
						<div class="ty-co-ship-card__top">
							<div class="ty-co-ship-card__info">
								<span class="ty-co-ship-card__ic"><?php typee_the_icon( 'local_shipping', 20 ); ?></span>
								<div>
									<strong class="ty-co-ship-card__name">Giao hàng tiêu chuẩn &amp; Chống sốc toàn quốc</strong>
									<span class="ty-co-ship-card__desc">Tự động áp dụng mặc định • Bảo hiểm 100% giá trị linh kiện CNC</span>
								</div>
							</div>
							<span class="ty-co-radio-checked" aria-hidden="true"><?php typee_the_icon( 'check', 13 ); ?></span>
						</div>
						<div class="ty-co-ship-card__foot">
							<span class="ty-co-free-pill">MIỄN PHÍ VẬN CHUYỂN</span>
							<div class="ty-co-ship-card__price">
								<del>35.000₫</del>
								<strong>0₫</strong>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- SECTION 3: Phương thức thanh toán (Figma 82:1375) -->
			<section class="ty-co-card" aria-labelledby="ty-co-sec3-title">
				<header class="ty-co-card__head">
					<div class="ty-co-card__head-left">
						<span class="ty-co-num">3</span>
						<div>
							<h2 class="ty-co-card__title" id="ty-co-sec3-title">Phương thức thanh toán</h2>
							<p class="ty-co-card__sub">Hệ thống xử lý mã đơn hàng và tự động kích hoạt tiến trình đóng gói ngay tức thì</p>
						</div>
					</div>
				</header>

				<?php woocommerce_checkout_payment(); ?>
			</section>
		</div>

		<!-- ==================== RIGHT COLUMN: Sticky Order Summary (5/12) ==================== -->
		<aside class="ty-co-aside" aria-label="Tóm tắt đơn hàng">
			<div class="ty-co-summary">
				<header class="ty-co-summary__head">
					<div class="ty-co-summary__head-left">
						<?php typee_the_icon( 'shopping_bag', 20, 'ty-co-summary__bag-ic' ); ?>
						<h2 class="ty-co-summary__title" id="order_review_heading">Đơn hàng của bạn</h2>
						<span class="ty-co-summary__count"><?php echo esc_html( $cart_count . ' món' ); ?></span>
					</div>
					<a class="ty-co-summary__edit" href="<?php echo esc_url( typee_url( 'cart' ) ); ?>">Chỉnh sửa</a>
				</header>

				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php woocommerce_order_review(); ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

				<!-- Cam kết Điều khoản & Chính sách bảo hành e-RMA (Figma 82:1582) -->
				<label class="ty-co-legal">
					<input type="checkbox" name="typee_terms_accepted" value="1" checked required />
					<span>
						Tôi xác nhận đã kiểm tra các thông số sản phẩm và đồng ý với
						<a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener">Điều khoản dịch vụ</a>
						&amp;
						<a href="<?php echo esc_url( $warranty_url ); ?>" target="_blank" rel="noopener">Chính sách bảo hành điện tử e-RMA</a>
						của TYPEE Studio.
					</span>
				</label>

				<!-- Nút ĐẶT HÀNG NGAY • {TOTAL} (Figma 82:1588) -->
				<?php
				if ( class_exists( 'TYPEE_Checkout_Service' ) ) {
					echo TYPEE_Checkout_Service::render_submit_button_box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo '<button type="submit" class="button alt ty-co-submit-btn" name="woocommerce_checkout_place_order" id="place_order" value="ĐẶT HÀNG NGAY">ĐẶT HÀNG NGAY</button>';
				}
				?>

				<!-- Micro Assurance Trust Badges (Figma 82:1596) -->
				<ul class="ty-co-trust" aria-label="Cam kết dịch vụ">
					<li><?php typee_the_icon( 'shield', 15 ); ?><span>Bảo mật SSL 256-bit</span></li>
					<li><?php typee_the_icon( 'verified_user', 15 ); ?><span>Chính hãng 100% Fullbox</span></li>
					<li><?php typee_the_icon( 'autorenew', 15 ); ?><span>1 đổi 1 trong 7 ngày</span></li>
					<li><?php typee_the_icon( 'task_alt', 15 ); ?><span>KCS kiểm tra 4 bước</span></li>
				</ul>
			</div>

			<!-- Technical Studio Support Callout (Figma 82:1617) -->
			<div class="ty-co-support">
				<div class="ty-co-support__left">
					<span class="ty-co-support__ic"><?php typee_the_icon( 'support_agent', 20 ); ?></span>
					<div>
						<strong>Cần tư vấn phối Switch hay Plate?</strong>
						<span>Kỹ thuật viên túc trực hỗ trợ chọn layout</span>
					</div>
				</div>
				<a class="ty-co-support__btn" href="tel:<?php echo esc_attr( $hotline_raw ? $hotline_raw : '19008899' ); ?>">Chat ngay</a>
			</div>
		</aside>
	</div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
