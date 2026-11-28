<?php
/**
 * Lost password form – Card 480px chuẩn TYPEE Secure Auth.
 * Tham chiếu Figma: Node 82:5941 (TYPEE - Khôi phục Mật khẩu).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 9.2.0
 */

defined( 'ABSPATH' ) || exit;

$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/tai-khoan/' );
$posted_val  = ( ! empty( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ) ? wp_unslash( $_POST['user_login'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

do_action( 'woocommerce_before_lost_password_form' );
?>

<div class="typee-auth-recovery-page" data-node="82:5941">
	<div class="typee-auth-recovery-glow typee-auth-recovery-glow--tr" aria-hidden="true"></div>
	<div class="typee-auth-recovery-glow typee-auth-recovery-glow--bl" aria-hidden="true"></div>

	<div class="typee-auth-recovery-wrap">

		<!-- Header bar 480px (Figma 82:5947) -->
		<div class="typee-auth-recovery-head">
			<a class="typee-auth-recovery-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php typee_the_icon( 'arrow_back', 14 ); ?>
				<span>Quay về Trang chủ</span>
			</a>
			<span class="typee-auth-recovery-badge">
				<span class="typee-auth-recovery-badge__dot" aria-hidden="true"></span>
				<span>TYPEE ID // SECURE AUTH</span>
			</span>
		</div>

		<!-- Card 480px chính (Figma 82:5958) -->
		<div class="typee-auth-recovery-card">

			<!-- Top Icon + Protocol Meta (Figma 82:5961) -->
			<div class="typee-auth-recovery-card__top">
				<div class="typee-auth-recovery-emblem" aria-hidden="true">
					<?php typee_the_icon( 'lock', 24, 'typee-auth-recovery-emblem__main' ); ?>
					<span class="typee-auth-recovery-emblem__sub">
						<?php typee_the_icon( 'verified_user', 12 ); ?>
					</span>
				</div>
				<div class="typee-auth-recovery-proto">
					<span class="typee-auth-recovery-proto__tag ty-mono">RMA PROTOCOL V2.4</span>
					<span class="typee-auth-recovery-proto__code ty-mono">CODE: SEC-PW-RECOVERY</span>
				</div>
			</div>

			<!-- Heading & Description (Figma 82:5974) -->
			<div class="typee-auth-recovery-intro">
				<div class="typee-auth-recovery-intro__kicker">
					<span class="typee-auth-recovery-intro__eyebrow">BẢO MẬT &amp; ĐỊNH DANH TÀI KHOẢN</span>
					<span class="typee-auth-recovery-intro__slash ty-mono">// BUILDER ACCESS</span>
				</div>
				<h1 class="typee-auth-recovery-intro__title">Khôi phục mật khẩu</h1>
				<p class="typee-auth-recovery-intro__desc">
					<?php
					echo wp_kses_post(
						apply_filters(
							'woocommerce_lost_password_message',
							'Nhập địa chỉ email đã liên kết với tài khoản TYPEE Studio của bạn. Chúng tôi sẽ gửi một liên kết an toàn để bạn thiết lập mật khẩu mới trong 15 phút.'
						)
					);
					?>
				</p>
			</div>

			<!-- Thông báo lỗi / trạng thái WooCommerce -->
			<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
				<div class="typee-auth-notices" aria-live="polite">
					<?php wc_print_notices(); ?>
				</div>
			<?php endif; ?>

			<!-- Form yêu cầu đặt lại mật khẩu (Figma 82:6006) -->
			<form method="post" class="woocommerce-ResetPassword lost_reset_password typee-auth-form">

				<div class="typee-auth-field">
					<div class="typee-auth-field__head">
						<label class="typee-auth-field__label" for="user_login">Địa chỉ Email đã đăng ký</label>
						<span class="typee-auth-field__req typee-auth-field__req--upper">BẮT BUỘC</span>
					</div>

					<div class="typee-auth-input-wrap typee-auth-input-wrap--clearable">
						<span class="typee-auth-input-icon" aria-hidden="true">
							<?php typee_the_icon( 'mail', 17 ); ?>
						</span>
						<input
							class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input typee-auth-input--white typee-auth-input--pw"
							type="text"
							name="user_login"
							id="user_login"
							autocomplete="username"
							placeholder="builder@typee.vn"
							value="<?php echo esc_attr( $posted_val ); ?>"
							required
							aria-required="true"
						/>
						<button
							type="button"
							class="typee-auth-clear-btn"
							data-typee-clear-input="user_login"
							aria-label="Xóa nội dung đã nhập"
							<?php echo '' === $posted_val ? 'hidden' : ''; ?>
						>
							<?php typee_the_icon( 'close', 14 ); ?>
						</button>
					</div>

					<p class="typee-auth-field__hint">
						<?php typee_the_icon( 'info', 13 ); ?>
						<span>Hệ thống sẽ gửi mã xác thực token một lần (Single-Use Actuation Token).</span>
					</p>
				</div>

				<?php do_action( 'woocommerce_lostpassword_form' ); ?>

				<input type="hidden" name="wc_reset_password" value="true" />
				<?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>

				<button
					type="submit"
					class="woocommerce-Button button typee-auth-submit typee-auth-submit--recovery"
					value="<?php esc_attr_e( 'Reset password', 'woocommerce' ); ?>"
				>
					<span>Gửi liên kết đặt lại mật khẩu</span>
					<?php typee_the_icon( 'arrow_forward', 16 ); ?>
				</button>

			</form>

			<!-- Bottom Inset Navigation & SSL Strip (Figma 82:6033) -->
			<div class="typee-auth-recovery-foot">
				<a class="typee-auth-recovery-foot__link" href="<?php echo esc_url( $account_url ); ?>">
					<?php typee_the_icon( 'arrow_back', 14 ); ?>
					<span>Quay lại trang Đăng nhập &amp; Đăng ký</span>
				</a>
				<p class="typee-auth-recovery-foot__ssl">
					<?php typee_the_icon( 'shield', 13, 'typee-auth-recovery-foot__ssl-icon' ); ?>
					<span>Bảo mật SSL 256-bit • Mã hóa tài khoản Builder e-RMA</span>
				</p>
			</div>

		</div>

		<!-- System Auth Status Strip (Figma 82:6044) -->
		<div class="typee-auth-recovery-status">
			<span class="typee-auth-recovery-status__left">
				<span class="typee-auth-recovery-status__dot" aria-hidden="true"></span>
				<span>Hệ thống TYPEE Auth: Hoạt động bình thường</span>
			</span>
			<span class="typee-auth-recovery-status__ver ty-mono">v<?php echo esc_html( TYPEE_CHILD_VERSION ); ?></span>
		</div>

	</div>
</div>

<?php do_action( 'woocommerce_after_lost_password_form' ); ?>
