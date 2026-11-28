<?php
/**
 * Login & Register Form – Thiết kế Split-screen TYPEE Studio.
 * Tham chiếu Figma: Node 82:5715 (TYPEE - Đăng Nhập & Đăng Ký Tài Khoản).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 9.9.0
 */

defined( 'ABSPATH' ) || exit;

$active_tab = 'login';
if ( isset( $_POST['register'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$active_tab = 'register';
} elseif ( isset( $_GET['action'] ) && 'register' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$active_tab = 'register';
}

$gen_username    = ( 'yes' === get_option( 'woocommerce_registration_generate_username' ) );
$gen_password    = ( 'yes' === get_option( 'woocommerce_registration_generate_password' ) );
$lost_pw_url     = wp_lostpassword_url();
$home_url        = home_url( '/' );
$auth_bg_url     = TYPEE_CHILD_URI . '/assets/images/auth-studio-bg.png';
$has_google_nsl  = function_exists( 'is_nextend_google_login' ) && is_nextend_google_login();
$has_fb_nsl      = function_exists( 'is_nextend_facebook_login' ) && is_nextend_facebook_login();
$google_auth_url = $has_google_nsl ? add_query_arg( array( 'loginSocial' => 'google' ), wp_login_url() ) : '';
$fb_auth_url     = $has_fb_nsl ? add_query_arg( array( 'loginSocial' => 'facebook' ), wp_login_url() ) : '';
$posted_username = ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? wp_unslash( $_POST['username'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$posted_email    = ( ! empty( $_POST['email'] ) && is_string( $_POST['email'] ) ) ? wp_unslash( $_POST['email'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$posted_name     = ( ! empty( $_POST['typee_reg_name'] ) && is_string( $_POST['typee_reg_name'] ) ) ? wp_unslash( $_POST['typee_reg_name'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$posted_phone    = ( ! empty( $_POST['typee_reg_phone'] ) && is_string( $_POST['typee_reg_phone'] ) ) ? wp_unslash( $_POST['typee_reg_phone'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
$promo_code      = typee_copy( 'promo_code' ) ? typee_copy( 'promo_code' ) : 'TYPEENEW10';

do_action( 'woocommerce_before_customer_login_form' );
?>

<div class="typee-auth-page" data-node="82:5715" data-typee-auth-root>

	<!-- Top Navigation Utility Bar (Figma 82:5718) -->
	<div class="typee-auth-topbar">
		<div class="typee-auth-container typee-auth-topbar__inner">
			<div class="typee-auth-topbar__left">
				<a class="typee-auth-back-pill" href="<?php echo esc_url( $home_url ); ?>">
					<?php typee_the_icon( 'arrow_back', 14 ); ?>
					<span>Quay về Trang chủ</span>
				</a>
				<div class="typee-auth-online-pill">
					<span class="typee-auth-online-pill__dot" aria-hidden="true"></span>
					<span>Studio Online: 1.428 builders đang hoạt động</span>
				</div>
			</div>
			<div class="typee-auth-topbar__brand">
				<span class="typee-auth-topbar__wordmark">TYPEE</span>
				<span class="typee-auth-topbar__tag">STUDIO ID</span>
			</div>
		</div>
	</div>

	<!-- Main Split-Screen Container (Figma 82:5734: 5 cols Left + 7 cols Right) -->
	<div class="typee-auth-container typee-auth-split-wrap" id="customer_login">
		<div class="typee-auth-split">

			<!-- LEFT COLUMN: Brand Showcase & Builder Club Benefits (5 cols) -->
			<aside class="typee-auth-showcase" aria-label="Quyền lợi thành viên TYPEE Club">

				<!-- Visual Card & Brand Manifesto (Figma 82:5736) -->
				<div class="typee-auth-manifesto">
					<img
						class="typee-auth-manifesto__bg"
						src="<?php echo esc_url( $auth_bg_url ); ?>"
						alt=""
						aria-hidden="true"
						loading="eager"
						decoding="async"
					/>
					<div class="typee-auth-manifesto__overlay" aria-hidden="true"></div>

					<div class="typee-auth-manifesto__inner">
						<div class="typee-auth-manifesto__top">
							<span class="typee-auth-manifesto__pill">
								<?php typee_the_icon( 'keyboard', 13, 'typee-auth-manifesto__pill-icon' ); ?>
								<span>BESPOKE KEEB ARCHITECTURE</span>
							</span>
							<span class="typee-auth-manifesto__est ty-mono">EST. 2021</span>
						</div>

						<div class="typee-auth-manifesto__quote">
							<h2 class="typee-auth-manifesto__title">Kiến tạo trải nghiệm gõ phím cơ học đỉnh cao &amp; chuẩn xác.</h2>
							<p class="typee-auth-manifesto__desc">Nơi hội tụ các nhà thiết kế, kỹ sư CNC và những đôi bàn tay tinh tuyển từng lá switch, lube mượt mà và cân wire chuẩn từng nanomet.</p>
						</div>

						<div class="typee-auth-manifesto__stats">
							<div class="typee-auth-manifesto__stats-left">
								<span class="typee-auth-manifesto__stats-icon" aria-hidden="true">
									<?php typee_the_icon( 'keyboard', 20 ); ?>
								</span>
								<div>
									<strong class="typee-auth-manifesto__stats-num">25.000+</strong>
									<span class="typee-auth-manifesto__stats-label">Builder &amp; Người chơi toàn quốc</span>
								</div>
							</div>
							<?php typee_the_icon( 'verified', 22, 'typee-auth-manifesto__stats-badge' ); ?>
						</div>
					</div>
				</div>

				<!-- 3 Exclusive Membership Benefits Card (Figma 82:5766) -->
				<div class="typee-auth-benefits">
					<div class="typee-auth-benefits__head">
						<span class="typee-auth-benefits__title-wrap">
							<?php typee_the_icon( 'verified_user', 18, 'typee-auth-benefits__head-icon' ); ?>
							<h3 class="typee-auth-benefits__title">QUYỀN LỢI THÀNH VIÊN TYPEE CLUB</h3>
						</span>
						<span class="typee-auth-benefits__tag">Free Access</span>
					</div>

					<ul class="typee-auth-benefits__list">
						<li class="typee-auth-benefit">
							<span class="typee-auth-benefit__icon" aria-hidden="true">
								<?php typee_the_icon( 'layers', 16 ); ?>
							</span>
							<div class="typee-auth-benefit__body">
								<strong class="typee-auth-benefit__heading">Lưu cấu hình Custom Builder 6 bước</strong>
								<p class="typee-auth-benefit__desc">Tự do phối kit, plate, switch, keycap và nạp lại cấu hình bất kỳ lúc nào để hoàn thiện hoặc chia sẻ công khai.</p>
							</div>
						</li>

						<li class="typee-auth-benefit">
							<span class="typee-auth-benefit__icon" aria-hidden="true">
								<?php typee_the_icon( 'redeem', 16 ); ?>
							</span>
							<div class="typee-auth-benefit__body">
								<div class="typee-auth-benefit__heading-row">
									<strong class="typee-auth-benefit__heading">Nhận voucher giảm 10%</strong>
									<span class="typee-auth-benefit__code ty-mono"><?php echo esc_html( $promo_code ); ?></span>
								</div>
								<p class="typee-auth-benefit__desc">Kích hoạt tự động cho đơn hàng linh kiện, lube station &amp; switch đầu tiên ngay sau khi hoàn tất đăng ký.</p>
							</div>
						</li>

						<li class="typee-auth-benefit">
							<span class="typee-auth-benefit__icon" aria-hidden="true">
								<?php typee_the_icon( 'shield', 16 ); ?>
							</span>
							<div class="typee-auth-benefit__body">
								<strong class="typee-auth-benefit__heading">Bảo hành e-RMA siêu tốc 48h</strong>
								<p class="typee-auth-benefit__desc">Quản lý serial number (SN), tra cứu tiến trình sửa chữa PCB &amp; cân wire stab trực tiếp qua SĐT không lo thất lạc bill.</p>
							</div>
						</li>
					</ul>

					<!-- Verified Testimonial Inset (Figma 82:5810) -->
					<div class="typee-auth-review">
						<div class="typee-auth-review__top">
							<div class="typee-auth-review__stars" aria-label="Đánh giá 5 trên 5 sao">
								<?php for ( $i = 0; $i < 5; $i++ ) : ?>
									<?php typee_the_icon( 'star', 14, 'typee-auth-review__star' ); ?>
								<?php endfor; ?>
							</div>
							<span class="typee-auth-review__label">BUILDER REVIEW</span>
						</div>
						<p class="typee-auth-review__quote">“Đăng ký thành viên TYPEE giúp mình lưu lại được 5 cấu hình phím khác nhau, săn sale switch HMX cực nhanh và bảo hành mạch PCB rất an tâm.”</p>
						<div class="typee-auth-review__author">
							<strong>Hoàng Minh</strong>
							<span>Enthusiast Member Lv.04 • Hà Nội</span>
						</div>
					</div>
				</div>

			</aside>

			<!-- RIGHT COLUMN: Interactive Auth Panel (7 cols) -->
			<section class="typee-auth-panel" aria-label="Đăng nhập và Đăng ký tài khoản">

				<!-- Studio Header Greeting (Figma 82:5833) -->
				<header class="typee-auth-greeting">
					<div class="typee-auth-greeting__meta">
						<span class="typee-auth-greeting__eyebrow">TÀI KHOẢN &amp; ĐỊNH DANH STUDIO</span>
						<span class="typee-auth-greeting__id ty-mono">ID: TYPEE-AUTH</span>
					</div>
					<h1
						class="typee-auth-greeting__title"
						data-typee-auth-heading
						data-title-login="Chào mừng bạn trở lại với TYPEE Studio"
						data-title-register="Tạo tài khoản TYPEE Studio Club"
					>
						<?php echo 'register' === $active_tab ? 'Tạo tài khoản TYPEE Studio Club' : 'Chào mừng bạn trở lại với TYPEE Studio'; ?>
					</h1>
					<p class="typee-auth-greeting__desc">Khám phá thế giới bàn phím cơ tùy biến, đặt lịch modding tại workshop và quản lý kho switch cá nhân.</p>
				</header>

				<!-- Thông báo lỗi / thành công WooCommerce -->
				<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
					<div class="typee-auth-notices" aria-live="polite">
						<?php wc_print_notices(); ?>
					</div>
				<?php endif; ?>

				<!-- Segmented Tab Switcher (Figma 82:5843) -->
				<div class="typee-auth-tabs" role="tablist" aria-label="Chuyển đổi Đăng nhập và Đăng ký">
					<button
						type="button"
						id="typee-auth-tab-login"
						class="typee-auth-tab<?php echo 'login' === $active_tab ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 'login' === $active_tab ? 'true' : 'false'; ?>"
						aria-controls="typee-auth-panel-login"
						data-typee-auth-tab="login"
					>
						<?php typee_the_icon( 'person', 16, 'typee-auth-tab__icon' ); ?>
						<span>Đăng nhập</span>
						<span class="typee-auth-tab__dot" aria-hidden="true"></span>
					</button>

					<button
						type="button"
						id="typee-auth-tab-register"
						class="typee-auth-tab<?php echo 'register' === $active_tab ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 'register' === $active_tab ? 'true' : 'false'; ?>"
						aria-controls="typee-auth-panel-register"
						data-typee-auth-tab="register"
					>
						<?php typee_the_icon( 'manage_accounts', 16, 'typee-auth-tab__icon' ); ?>
						<span>Đăng ký tài khoản</span>
						<span class="typee-auth-tab__badge">+10% Off</span>
					</button>
				</div>

				<!-- TAB 1: FORM ĐĂNG NHẬP (Figma 82:5858) -->
				<div
					id="typee-auth-panel-login"
					class="typee-auth-card"
					role="tabpanel"
					aria-labelledby="typee-auth-tab-login"
					data-typee-auth-panel="login"
					<?php echo 'login' !== $active_tab ? 'hidden' : ''; ?>
				>
					<form class="woocommerce-form woocommerce-form-login login typee-auth-form" method="post" novalidate>

						<?php do_action( 'woocommerce_login_form_start' ); ?>

						<div class="typee-auth-field">
							<div class="typee-auth-field__head">
								<label class="typee-auth-field__label" for="username">Email hoặc Số điện thoại</label>
								<span class="typee-auth-field__req">Bắt buộc</span>
							</div>
							<div class="typee-auth-input-wrap">
								<span class="typee-auth-input-icon" aria-hidden="true">
									<?php typee_the_icon( 'mail', 17 ); ?>
								</span>
								<input
									type="text"
									class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input"
									name="username"
									id="username"
									autocomplete="username"
									placeholder="ví dụ: builder@typee.vn hoặc 0918 248 899"
									value="<?php echo esc_attr( $posted_username ); ?>"
									required
									aria-required="true"
								/>
							</div>
						</div>

						<div class="typee-auth-field">
							<div class="typee-auth-field__head">
								<label class="typee-auth-field__label" for="password">Mật khẩu</label>
								<a class="typee-auth-forgot-link" href="<?php echo esc_url( $lost_pw_url ); ?>">Quên mật khẩu?</a>
							</div>
							<div class="typee-auth-input-wrap typee-auth-input-wrap--pw">
								<span class="typee-auth-input-icon" aria-hidden="true">
									<?php typee_the_icon( 'lock', 17 ); ?>
								</span>
								<input
									class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input typee-auth-input--pw"
									type="password"
									name="password"
									id="password"
									autocomplete="current-password"
									placeholder="Nhập mật khẩu của bạn"
									required
									aria-required="true"
								/>
								<button
									type="button"
									class="typee-auth-pw-toggle"
									data-typee-pw-toggle="password"
									aria-label="Hiện mật khẩu"
									aria-pressed="false"
								>
									<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--show"><?php echo typee_auth_eye_svg( false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
									<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--hide" hidden><?php echo typee_auth_eye_svg( true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								</button>
							</div>
						</div>

						<?php do_action( 'woocommerce_login_form' ); ?>

						<div class="typee-auth-remember-row">
							<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme typee-auth-checkbox">
								<input class="woocommerce-form__input woocommerce-form__input-checkbox typee-auth-checkbox__input" name="rememberme" type="checkbox" id="rememberme" value="forever" />
								<span>Ghi nhớ đăng nhập trên thiết bị này (30 ngày)</span>
							</label>
							<span class="typee-auth-ssl-note">Bảo mật SSL 256-bit</span>
						</div>

						<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>

						<button
							type="submit"
							class="woocommerce-button button woocommerce-form-login__submit typee-auth-submit"
							name="login"
							value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"
						>
							<span>ĐĂNG NHẬP NGAY</span>
							<?php typee_the_icon( 'arrow_forward', 16 ); ?>
						</button>

						<?php do_action( 'woocommerce_login_form_end' ); ?>

					</form>

					<!-- Divider (Figma 82:5899) -->
					<div class="typee-auth-divider" role="separator">
						<span>HOẶC TIẾP TỤC NHANH VỚI</span>
					</div>

					<!-- Social Buttons (Figma 82:5903) -->
					<div class="typee-auth-socials">
						<?php if ( $has_google_nsl ) : ?>
							<a class="typee-auth-social-btn" href="<?php echo esc_url( $google_auth_url ); ?>" data-plugin="nsl" data-action="connect" data-redirect="current" data-provider="google">
								<svg class="typee-auth-social-btn__svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/><path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.09C3.515 21.3 7.565 24 12.255 24z"/><path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62h-3.98a11.86 11.86 0 0 0 0 10.76l3.98-3.09z"/><path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.69 0-8.74 2.7-10.71 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/></svg>
								<span>Tiếp tục với Google</span>
							</a>
						<?php else : ?>
							<button type="button" class="typee-auth-social-btn" data-typee-social-placeholder="Google">
								<svg class="typee-auth-social-btn__svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/><path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.09C3.515 21.3 7.565 24 12.255 24z"/><path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62h-3.98a11.86 11.86 0 0 0 0 10.76l3.98-3.09z"/><path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.69 0-8.74 2.7-10.71 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/></svg>
								<span>Tiếp tục với Google</span>
							</button>
						<?php endif; ?>

						<?php if ( $has_fb_nsl ) : ?>
							<a class="typee-auth-social-btn" href="<?php echo esc_url( $fb_auth_url ); ?>" data-plugin="nsl" data-action="connect" data-redirect="current" data-provider="facebook">
								<svg class="typee-auth-social-btn__svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
								<span>Tiếp tục với Facebook</span>
							</a>
						<?php else : ?>
							<button type="button" class="typee-auth-social-btn" data-typee-social-placeholder="Facebook">
								<svg class="typee-auth-social-btn__svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
								<span>Tiếp tục với Facebook</span>
							</button>
						<?php endif; ?>
					</div>
					<p class="typee-auth-social-msg" data-typee-social-msg role="status" hidden></p>

					<!-- Footnote Banner (Figma 82:5917) -->
					<div class="typee-auth-footnote">
						<span class="typee-auth-footnote__text">
							<?php typee_the_icon( 'redeem', 16, 'typee-auth-footnote__icon' ); ?>
							<span>Chưa có tài khoản TYPEE Club?</span>
						</span>
						<button type="button" class="typee-auth-footnote__btn" data-typee-switch-tab="register">
							Đăng ký nhận -10% →
						</button>
					</div>
				</div>

				<!-- TAB 2: FORM ĐĂNG KÝ TÀI KHOẢN -->
				<div
					id="typee-auth-panel-register"
					class="typee-auth-card"
					role="tabpanel"
					aria-labelledby="typee-auth-tab-register"
					data-typee-auth-panel="register"
					<?php echo 'register' !== $active_tab ? 'hidden' : ''; ?>
				>
					<form method="post" class="woocommerce-form woocommerce-form-register register typee-auth-form" <?php do_action( 'woocommerce_register_form_tag' ); ?> novalidate>

						<?php do_action( 'woocommerce_register_form_start' ); ?>

						<div class="typee-auth-row-2">
							<div class="typee-auth-field">
								<div class="typee-auth-field__head">
									<label class="typee-auth-field__label" for="typee_reg_name">Họ và tên</label>
									<span class="typee-auth-field__opt">Tùy chọn</span>
								</div>
								<div class="typee-auth-input-wrap">
									<span class="typee-auth-input-icon" aria-hidden="true">
										<?php typee_the_icon( 'person', 17 ); ?>
									</span>
									<input
										type="text"
										class="typee-auth-input"
										name="typee_reg_name"
										id="typee_reg_name"
										autocomplete="name"
										placeholder="Nguyễn Hoàng Minh"
										value="<?php echo esc_attr( $posted_name ); ?>"
									/>
								</div>
							</div>

							<div class="typee-auth-field">
								<div class="typee-auth-field__head">
									<label class="typee-auth-field__label" for="typee_reg_phone">Số điện thoại</label>
									<span class="typee-auth-field__opt">Tra cứu e-RMA</span>
								</div>
								<div class="typee-auth-input-wrap">
									<span class="typee-auth-input-icon" aria-hidden="true">
										<?php typee_the_icon( 'call', 16 ); ?>
									</span>
									<input
										type="tel"
										class="typee-auth-input"
										name="typee_reg_phone"
										id="typee_reg_phone"
										autocomplete="tel"
										placeholder="0918 248 899"
										value="<?php echo esc_attr( $posted_phone ); ?>"
									/>
								</div>
							</div>
						</div>

						<?php if ( ! $gen_username ) : ?>
							<div class="typee-auth-field">
								<div class="typee-auth-field__head">
									<label class="typee-auth-field__label" for="reg_username">Tên đăng nhập</label>
									<span class="typee-auth-field__req">Bắt buộc</span>
								</div>
								<div class="typee-auth-input-wrap">
									<span class="typee-auth-input-icon" aria-hidden="true">
										<?php typee_the_icon( 'account_circle', 17 ); ?>
									</span>
									<input
										type="text"
										class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input"
										name="username"
										id="reg_username"
										autocomplete="username"
										placeholder="vd: builder_minh"
										value="<?php echo esc_attr( $posted_username ); ?>"
										required
										aria-required="true"
									/>
								</div>
							</div>
						<?php endif; ?>

						<div class="typee-auth-field">
							<div class="typee-auth-field__head">
								<label class="typee-auth-field__label" for="reg_email">Địa chỉ Email</label>
								<span class="typee-auth-field__req">Bắt buộc</span>
							</div>
							<div class="typee-auth-input-wrap">
								<span class="typee-auth-input-icon" aria-hidden="true">
									<?php typee_the_icon( 'mail', 17 ); ?>
								</span>
								<input
									type="email"
									class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input"
									name="email"
									id="reg_email"
									autocomplete="email"
									placeholder="ví dụ: builder@typee.vn"
									value="<?php echo esc_attr( $posted_email ); ?>"
									required
									aria-required="true"
								/>
							</div>
						</div>

						<?php if ( ! $gen_password ) : ?>
							<div class="typee-auth-field">
								<div class="typee-auth-field__head">
									<label class="typee-auth-field__label" for="reg_password">Mật khẩu</label>
									<span class="typee-auth-field__req">Tối thiểu 8 ký tự</span>
								</div>
								<div class="typee-auth-input-wrap typee-auth-input-wrap--pw">
									<span class="typee-auth-input-icon" aria-hidden="true">
										<?php typee_the_icon( 'lock', 17 ); ?>
									</span>
									<input
										type="password"
										class="woocommerce-Input woocommerce-Input--text input-text typee-auth-input typee-auth-input--pw"
										name="password"
										id="reg_password"
										autocomplete="new-password"
										placeholder="Tạo mật khẩu bảo mật cho tài khoản"
										data-typee-pw-strength
										required
										aria-required="true"
									/>
									<button
										type="button"
										class="typee-auth-pw-toggle"
										data-typee-pw-toggle="reg_password"
										aria-label="Hiện mật khẩu"
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
						<?php else : ?>
							<div class="typee-auth-info-box">
								<?php typee_the_icon( 'info', 16, 'typee-auth-info-box__icon' ); ?>
								<span>Sau khi đăng ký, hệ thống sẽ gửi liên kết thiết lập mật khẩu an toàn trực tiếp tới địa chỉ email của bạn.</span>
							</div>
						<?php endif; ?>

						<?php do_action( 'woocommerce_register_form' ); ?>

						<div class="typee-auth-voucher-strip">
							<?php typee_the_icon( 'sell', 16, 'typee-auth-voucher-strip__icon' ); ?>
							<span>Ưu đãi thành viên mới: Dùng mã <strong class="ty-mono"><?php echo esc_html( $promo_code ); ?></strong> giảm 10% cho đơn đầu tiên.</span>
						</div>

						<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>

						<button
							type="submit"
							class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit typee-auth-submit"
							name="register"
							value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"
						>
							<span>TẠO TÀI KHOẢN &amp; NHẬN -10%</span>
							<?php typee_the_icon( 'arrow_forward', 16 ); ?>
						</button>

						<?php do_action( 'woocommerce_register_form_end' ); ?>

					</form>

					<div class="typee-auth-footnote">
						<span class="typee-auth-footnote__text">
							<?php typee_the_icon( 'verified_user', 16, 'typee-auth-footnote__icon' ); ?>
							<span>Đã có tài khoản TYPEE Studio?</span>
						</span>
						<button type="button" class="typee-auth-footnote__btn" data-typee-switch-tab="login">
							Đăng nhập ngay →
						</button>
					</div>
				</div>

				<!-- Studio Micro-Specs Utility Bar (Figma 82:5925) -->
				<div class="typee-auth-specs">
					<div class="typee-auth-specs__row">
						<?php typee_the_icon( 'verified_user', 16, 'typee-auth-specs__icon' ); ?>
						<span>Bảo mật hệ thống e-RMA &amp; lịch sử bảo hành theo số serial (SN)</span>
					</div>
					<div class="typee-auth-specs__meta">
						<span>Showroom Hà Nội: <?php echo esc_html( typee_copy( 'address_hn' ) ); ?></span>
						<a class="typee-auth-specs__hotline" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', typee_copy( 'hotline' ) ) ); ?>">
							HOTLINE: <?php echo esc_html( typee_copy( 'hotline' ) ); ?>
						</a>
					</div>
				</div>

			</section>

		</div>
	</div>

</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
