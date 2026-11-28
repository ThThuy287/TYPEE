<?php
/**
 * Lost password confirmation – Trạng thái đã gửi email khôi phục thành công.
 * Tham chiếu Figma: Node 82:5941 / 82:5985 (Banner xanh ngọc + đếm ngược gửi lại).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 3.9.0
 */

defined( 'ABSPATH' ) || exit;

$account_url   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/tai-khoan/' );
$lost_pw_url   = wp_lostpassword_url();
$target_email  = '';

if ( function_exists( 'WC' ) && WC()->session ) {
	$saved_email = WC()->session->get( 'typee_lost_pw_email' );
	if ( is_string( $saved_email ) && '' !== $saved_email ) {
		$target_email = $saved_email;
	}
}
?>

<div class="typee-auth-recovery-page" data-node="82:5941">
	<div class="typee-auth-recovery-glow typee-auth-recovery-glow--tr" aria-hidden="true"></div>
	<div class="typee-auth-recovery-glow typee-auth-recovery-glow--bl" aria-hidden="true"></div>

	<div class="typee-auth-recovery-wrap">

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

		<div class="typee-auth-recovery-card">

			<div class="typee-auth-recovery-card__top">
				<div class="typee-auth-recovery-emblem typee-auth-recovery-emblem--success" aria-hidden="true">
					<?php typee_the_icon( 'check_circle', 26, 'typee-auth-recovery-emblem__main' ); ?>
				</div>
				<div class="typee-auth-recovery-proto">
					<span class="typee-auth-recovery-proto__tag ty-mono">RMA PROTOCOL V2.4</span>
					<span class="typee-auth-recovery-proto__code ty-mono">CODE: SEC-PW-SENT</span>
				</div>
			</div>

			<div class="typee-auth-recovery-intro">
				<div class="typee-auth-recovery-intro__kicker">
					<span class="typee-auth-recovery-intro__eyebrow">BẢO MẬT &amp; ĐỊNH DANH TÀI KHOẢN</span>
					<span class="typee-auth-recovery-intro__slash ty-mono">// BUILDER ACCESS</span>
				</div>
				<h1 class="typee-auth-recovery-intro__title">Khôi phục mật khẩu</h1>
			</div>

			<?php do_action( 'woocommerce_before_lost_password_confirmation_message' ); ?>

			<!-- Khối xác nhận màu xanh ngọc (Figma 82:5985) -->
			<div class="typee-auth-confirm" role="status" aria-live="polite">
				<span class="typee-auth-confirm__icon" aria-hidden="true">
					<?php typee_the_icon( 'check_circle', 20 ); ?>
				</span>
				<div class="typee-auth-confirm__body">
					<h2 class="typee-auth-confirm__title">Đã gửi email khôi phục thành công!</h2>
					<p class="typee-auth-confirm__desc">
						Vui lòng kiểm tra hòm thư đến hoặc thư rác
						<?php if ( '' !== $target_email ) : ?>
							tại <strong><?php echo esc_html( $target_email ); ?></strong>.
						<?php else : ?>
							của email đã đăng ký tài khoản.
						<?php endif; ?>
						Liên kết bảo mật có hiệu lực trong 15 phút.
					</p>

					<div class="typee-auth-confirm__strip">
						<div class="typee-auth-confirm__resend-row">
							<span>Chưa nhận được mã?</span>
							<a
								class="typee-auth-confirm__resend is-waiting"
								href="<?php echo esc_url( $lost_pw_url ); ?>"
								data-typee-resend-countdown="60"
								aria-disabled="true"
							>
								Gửi lại sau <strong data-typee-resend-sec>60</strong>s
							</a>
						</div>
						<div class="typee-auth-confirm__hotline">
							Hotline Kỹ thuật: <strong><?php echo esc_html( typee_copy( 'hotline' ) ); ?></strong>
						</div>
					</div>
				</div>
			</div>

			<?php do_action( 'woocommerce_after_lost_password_confirmation_message' ); ?>

			<a class="typee-auth-submit typee-auth-submit--recovery" href="<?php echo esc_url( $account_url ); ?>">
				<span>Quay lại Đăng nhập ngay</span>
				<?php typee_the_icon( 'arrow_forward', 16 ); ?>
			</a>

			<div class="typee-auth-recovery-foot">
				<a class="typee-auth-recovery-foot__link" href="<?php echo esc_url( $lost_pw_url ); ?>">
					<?php typee_the_icon( 'arrow_back', 14 ); ?>
					<span>Thử lại với địa chỉ email khác</span>
				</a>
				<p class="typee-auth-recovery-foot__ssl">
					<?php typee_the_icon( 'shield', 13, 'typee-auth-recovery-foot__ssl-icon' ); ?>
					<span>Bảo mật SSL 256-bit • Mã hóa tài khoản Builder e-RMA</span>
				</p>
			</div>

		</div>

		<div class="typee-auth-recovery-status">
			<span class="typee-auth-recovery-status__left">
				<span class="typee-auth-recovery-status__dot" aria-hidden="true"></span>
				<span>Hệ thống TYPEE Auth: Hoạt động bình thường</span>
			</span>
			<span class="typee-auth-recovery-status__ver ty-mono">v<?php echo esc_html( TYPEE_CHILD_VERSION ); ?></span>
		</div>

	</div>
</div>
