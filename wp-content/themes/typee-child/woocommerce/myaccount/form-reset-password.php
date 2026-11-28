<?php
/**
 * Reset password form – Thiết lập mật khẩu mới đồng bộ khung Card 480px (Figma 82:5941).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 9.2.0
 */

defined( 'ABSPATH' ) || exit;

$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/tai-khoan/' );

do_action( 'woocommerce_before_reset_password_form' );
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
				<div class="typee-auth-recovery-emblem" aria-hidden="true">
					<?php typee_the_icon( 'lock', 24, 'typee-auth-recovery-emblem__main' ); ?>
					<span class="typee-auth-recovery-emblem__sub">
						<?php typee_the_icon( 'check', 12 ); ?>
					</span>
				</div>
				<div class="typee-auth-recovery-proto">
					<span class="typee-auth-recovery-proto__tag ty-mono">RMA PROTOCOL V2.4</span>
					<span class="typee-auth-recovery-proto__code ty-mono">CODE: SEC-PW-RESET</span>
				</div>
			</div>

			<div class="typee-auth-recovery-intro">
				<div class="typee-auth-recovery-intro__kicker">
					<span class="typee-auth-recovery-intro__eyebrow">BẢO MẬT &amp; ĐỊNH DANH TÀI KHOẢN</span>
					<span class="typee-auth-recovery-intro__slash ty-mono">// NEW CREDENTIAL</span>
				</div>
				<h1 class="typee-auth-recovery-intro__title">Thiết lập mật khẩu mới</h1>
				<p class="typee-auth-recovery-intro__desc">
					<?php
					echo wp_kses_post(
						apply_filters(
							'woocommerce_reset_password_message',
							'Vui lòng nhập mật khẩu mới cho tài khoản TYPEE Studio của bạn. Nên dùng tối thiểu 8 ký tự gồm chữ hoa, chữ thường và số.'
						)
					);
					?>
				</p>
			</div>

			<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
				<div class="typee-auth-notices" aria-live="polite">
					<?php wc_print_notices(); ?>
				</div>
			<?php endif; ?>

			<form method="post" class="woocommerce-ResetPassword lost_reset_password typee-auth-form" data-typee-reset-form>

				<div class="typee-auth-field">
					<div class="typee-auth-field__head">
						<label class="typee-auth-field__label" for="password_1">Mật khẩu mới</label>
						<span class="typee-auth-field__req typee-auth-field__req--upper">BẮT BUỘC</span>
					</div>
					<div class="typee-auth-input-wrap typee-auth-input-wrap--pw">
						<span class="typee-auth-input-icon" aria-hidden="true">
							<?php typee_the_icon( 'lock', 17 ); ?>
						</span>
						<input
							type="password"
							class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input typee-auth-input--white typee-auth-input--pw"
							name="password_1"
							id="password_1"
							autocomplete="new-password"
							placeholder="Nhập mật khẩu mới"
							data-typee-pw-strength
							required
							aria-required="true"
						/>
						<button
							type="button"
							class="typee-auth-pw-toggle"
							data-typee-pw-toggle="password_1"
							aria-label="Hiện mật khẩu mới"
							aria-pressed="false"
						>
							<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--show"><?php echo typee_auth_eye_svg( false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--hide" hidden><?php echo typee_auth_eye_svg( true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</button>
					</div>
					<div class="typee-auth-strength" data-typee-strength-meter hidden>
						<div class="typee-auth-strength__track">
							<span class="typee-auth-strength__bar" data-typee-strength-bar></span>
						</div>
						<span class="typee-auth-strength__label" data-typee-strength-label></span>
					</div>
				</div>

				<div class="typee-auth-field">
					<div class="typee-auth-field__head">
						<label class="typee-auth-field__label" for="password_2">Xác nhận mật khẩu mới</label>
						<span class="typee-auth-field__req typee-auth-field__req--upper">BẮT BUỘC</span>
					</div>
					<div class="typee-auth-input-wrap typee-auth-input-wrap--pw">
						<span class="typee-auth-input-icon" aria-hidden="true">
							<?php typee_the_icon( 'lock', 17 ); ?>
						</span>
						<input
							type="password"
							class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input typee-auth-input--white typee-auth-input--pw"
							name="password_2"
							id="password_2"
							autocomplete="new-password"
							placeholder="Nhập lại mật khẩu mới"
							data-typee-pw-confirm="password_1"
							required
							aria-required="true"
						/>
						<button
							type="button"
							class="typee-auth-pw-toggle"
							data-typee-pw-toggle="password_2"
							aria-label="Hiện mật khẩu xác nhận"
							aria-pressed="false"
						>
							<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--show"><?php echo typee_auth_eye_svg( false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--hide" hidden><?php echo typee_auth_eye_svg( true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</button>
					</div>
					<p class="typee-auth-match-msg" data-typee-match-msg hidden></p>
				</div>

				<input type="hidden" name="reset_key" value="<?php echo esc_attr( $args['key'] ); ?>" />
				<input type="hidden" name="reset_login" value="<?php echo esc_attr( $args['login'] ); ?>" />

				<?php do_action( 'woocommerce_resetpassword_form' ); ?>

				<input type="hidden" name="wc_reset_password" value="true" />
				<?php wp_nonce_field( 'reset_password', 'woocommerce-reset-password-nonce' ); ?>

				<button
					type="submit"
					class="woocommerce-Button button typee-auth-submit typee-auth-submit--recovery"
					value="<?php esc_attr_e( 'Save', 'woocommerce' ); ?>"
				>
					<span>Lưu mật khẩu mới</span>
					<?php typee_the_icon( 'arrow_forward', 16 ); ?>
				</button>

			</form>

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

		<div class="typee-auth-recovery-status">
			<span class="typee-auth-recovery-status__left">
				<span class="typee-auth-recovery-status__dot" aria-hidden="true"></span>
				<span>Hệ thống TYPEE Auth: Hoạt động bình thường</span>
			</span>
			<span class="typee-auth-recovery-status__ver ty-mono">v<?php echo esc_html( TYPEE_CHILD_VERSION ); ?></span>
		</div>

	</div>
</div>

<?php do_action( 'woocommerce_after_reset_password_form' ); ?>
