<?php
/**
 * Edit account form – Hồ sơ cá nhân & Bảo mật bên trong Layout Shell Tài khoản TYPEE.
 * Tham chiếu Figma: Node 82:6569 / 82:6710 (Hồ sơ cá nhân & Đổi mật khẩu).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

$user_id   = (int) $user->ID;
$summary   = function_exists( 'typee_account_user_summary' ) ? typee_account_user_summary( $user_id ) : array();
$full_name = trim( $user->first_name . ' ' . $user->last_name );
if ( '' === $full_name ) {
	$full_name = $user->display_name;
}

$phone       = trim( (string) get_user_meta( $user_id, 'billing_phone', true ) );
$dob_raw     = trim( (string) get_user_meta( $user_id, 'typee_dob', true ) );
$dob_day     = 0;
$dob_month   = 0;
$dob_year    = 0;
if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $dob_raw, $m ) ) {
	$dob_year  = (int) $m[1];
	$dob_month = (int) $m[2];
	$dob_day   = (int) $m[3];
}

$gender      = (string) get_user_meta( $user_id, 'typee_gender', true );
$fav_layout  = (string) get_user_meta( $user_id, 'typee_fav_layout', true );
if ( '' === $fav_layout ) {
	$fav_layout = '75';
}
$twofa_pref  = get_user_meta( $user_id, 'typee_2fa_sms_pref', true );
$twofa_on    = ( 'no' !== $twofa_pref );
$updated_ts  = (int) get_user_meta( $user_id, 'typee_profile_updated', true );
$updated_str = $updated_ts ? date_i18n( 'd/m/Y', $updated_ts ) : date_i18n( 'd/m/Y', strtotime( $user->user_registered ) );
$joined_str  = date_i18n( 'm/Y', strtotime( $user->user_registered ) );
$layouts     = function_exists( 'typee_account_keyboard_layouts' ) ? typee_account_keyboard_layouts() : array();
$lost_pw_url = wp_lostpassword_url();
$curr_year   = (int) gmdate( 'Y' );

do_action( 'woocommerce_before_edit_account_form' );
?>

<!-- Khối 1: Page Header Unit (Figma 82:6711) -->
<header class="typee-account-card typee-account-profile-head" data-node="82:6711">
	<div class="typee-account-profile-head__main">
		<h1 class="typee-account-page-title">Hồ sơ cá nhân &amp; Bảo mật</h1>
		<p class="typee-account-page-desc">Quản lý thông tin định danh cá nhân, dữ liệu tài khoản và cài đặt bảo mật cho tài khoản TYPEE Studio của bạn.</p>
	</div>
	<?php if ( '' !== $phone ) : ?>
		<span class="typee-account-verified-pill">
			<?php typee_the_icon( 'verified_user', 16 ); ?>
			<span>e-RMA Verified ID</span>
		</span>
	<?php else : ?>
		<span class="typee-account-verified-pill typee-account-verified-pill--pending">
			<?php typee_the_icon( 'info', 16 ); ?>
			<span>Chưa liên kết SĐT e-RMA</span>
		</span>
	<?php endif; ?>
</header>

<form
	class="woocommerce-EditAccountForm edit-account typee-account-profile-form"
	action=""
	method="post"
	<?php do_action( 'woocommerce_edit_account_form_tag' ); ?>
>
	<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

	<!-- Trường đồng bộ chuẩn WooCommerce -->
	<input type="hidden" name="account_first_name" id="account_first_name" value="<?php echo esc_attr( $user->first_name ? $user->first_name : $full_name ); ?>" />
	<input type="hidden" name="account_last_name" id="account_last_name" value="<?php echo esc_attr( $user->last_name ); ?>" />
	<input type="hidden" name="account_display_name" id="account_display_name" value="<?php echo esc_attr( $user->display_name ); ?>" />

	<!-- SECTION 1: Personal Profile & Avatar (Figma 82:6723) -->
	<section class="typee-account-card typee-account-profile-section" id="profile-info">
		<div class="typee-account-section-head">
			<div class="typee-account-section-head__left">
				<span class="typee-account-section-bar" aria-hidden="true"></span>
				<h2 class="typee-account-section-title">Thông tin tài khoản</h2>
			</div>
			<span class="typee-account-section-meta">Cập nhật lần cuối: <?php echo esc_html( $updated_str ); ?></span>
		</div>

		<!-- Avatar & Identity Strip (Figma 82:6731) -->
		<div class="typee-account-avatar-strip">
			<div class="typee-account-avatar-strip__media">
				<div class="typee-account-avatar-strip__frame" data-typee-avatar-preview>
					<?php
					echo get_avatar(
						$user_id,
						192,
						'',
						$full_name,
						array( 'class' => 'typee-account-avatar-strip__img' )
					);
					?>
				</div>
				<button
					type="button"
					class="typee-account-avatar-strip__cam"
					data-typee-avatar-trigger
					aria-label="Đổi ảnh đại diện"
				>
					<?php typee_the_icon( 'photo_camera', 16 ); ?>
				</button>
			</div>

			<div class="typee-account-avatar-strip__info">
				<div class="typee-account-avatar-strip__name-row">
					<strong class="typee-account-avatar-strip__name"><?php echo esc_html( $full_name ); ?></strong>
					<?php if ( ! empty( $summary['tier_name'] ) ) : ?>
						<span class="typee-account-tier-pill"><?php echo esc_html( strtoupper( $summary['tier_name'] ) ); ?></span>
					<?php endif; ?>
				</div>
				<p class="typee-account-avatar-strip__bio">
					Thành viên chính thức gia nhập từ <strong>Tháng <?php echo esc_html( $joined_str ); ?></strong>. Được bảo chứng bảo hành linh kiện chính hãng TYPEE e-RMA.
				</p>
				<div class="typee-account-avatar-strip__actions">
					<input
						type="file"
						name="typee_avatar_file"
						id="typee_avatar_file"
						accept="image/jpeg,image/png,image/webp"
						class="ty-sr"
						data-typee-avatar-input
					/>
					<button type="button" class="typee-account-avatar-strip__btn" data-typee-avatar-trigger>
						Đổi ảnh đại diện
					</button>
					<span class="typee-account-avatar-strip__hint" data-typee-avatar-hint>
						Định dạng JPG, PNG dung lượng &lt; 5MB
					</span>
				</div>
			</div>
		</div>

		<!-- Profile Input Grid 2 cột (Figma 82:6751) -->
		<div class="typee-account-form-grid">

			<!-- 1. Họ và tên đầy đủ -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<label class="typee-account-field__label" for="typee_full_name">Họ và tên đầy đủ</label>
					<span class="typee-account-field__tag">Bắt buộc</span>
				</div>
				<input
					type="text"
					class="typee-account-input"
					name="typee_full_name"
					id="typee_full_name"
					autocomplete="name"
					placeholder="Nhập họ và tên đầy đủ"
					value="<?php echo esc_attr( $full_name ); ?>"
					data-typee-fullname-sync
					required
					aria-required="true"
				/>
				<span class="typee-account-field__help">Hiển thị trên hóa đơn đỏ VAT và phiếu kiểm định bàn phím.</span>
			</div>

			<!-- 2. Địa chỉ Email -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<label class="typee-account-field__label" for="account_email">Địa chỉ Email</label>
					<span class="typee-account-badge typee-account-badge--success">
						<?php typee_the_icon( 'check', 12 ); ?>
						<span>Đã xác thực</span>
					</span>
				</div>
				<div class="typee-account-input-wrap">
					<input
						type="email"
						class="woocommerce-Input woocommerce-Input--email input-text typee-account-input typee-account-input--muted typee-account-input--has-right"
						name="account_email"
						id="account_email"
						autocomplete="email"
						value="<?php echo esc_attr( $user->user_email ); ?>"
						required
						aria-required="true"
					/>
					<span class="typee-account-input-right-icon" aria-hidden="true">
						<?php typee_the_icon( 'lock', 15 ); ?>
					</span>
				</div>
				<span class="typee-account-field__help">Email dùng để nhận hóa đơn điện tử &amp; file firmware QMK/VIA.</span>
			</div>

			<!-- 3. Số điện thoại liên hệ -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<label class="typee-account-field__label" for="typee_account_phone">Số điện thoại liên hệ</label>
					<span class="typee-account-badge typee-account-badge--warm">
						<?php echo '' !== $phone ? 'Đã liên kết e-RMA' : 'Chưa liên kết'; ?>
					</span>
				</div>
				<div class="typee-account-input-wrap">
					<input
						type="tel"
						class="typee-account-input typee-account-input--has-btn ty-mono"
						name="typee_account_phone"
						id="typee_account_phone"
						autocomplete="tel"
						placeholder="0918 248 899"
						value="<?php echo esc_attr( $phone ); ?>"
					/>
					<button type="button" class="typee-account-input-action" data-typee-focus-input="typee_account_phone">
						Thay đổi
					</button>
				</div>
				<span class="typee-account-field__help">Dùng để tra cứu hồ sơ sửa chữa, cân wire stabs &amp; nhận OTP.</span>
			</div>

			<!-- 4. Ngày sinh (3 ô select Ngày / Tháng / Năm) -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<span class="typee-account-field__label" id="typee-dob-label">Ngày sinh của bạn</span>
					<span class="typee-account-field__promo">Tặng Voucher 15%</span>
				</div>
				<div class="typee-account-dob-grid" role="group" aria-labelledby="typee-dob-label">
					<select name="typee_dob_day" class="typee-account-select" aria-label="Ngày sinh">
						<option value="">Ngày</option>
						<?php for ( $d = 1; $d <= 31; $d++ ) : ?>
							<option value="<?php echo (int) $d; ?>" <?php selected( $dob_day, $d ); ?>>
								Ngày <?php echo esc_html( sprintf( '%02d', $d ) ); ?>
							</option>
						<?php endfor; ?>
					</select>

					<select name="typee_dob_month" class="typee-account-select" aria-label="Tháng sinh">
						<option value="">Tháng</option>
						<?php for ( $mo = 1; $mo <= 12; $mo++ ) : ?>
							<option value="<?php echo (int) $mo; ?>" <?php selected( $dob_month, $mo ); ?>>
								Tháng <?php echo esc_html( sprintf( '%02d', $mo ) ); ?>
							</option>
						<?php endfor; ?>
					</select>

					<select name="typee_dob_year" class="typee-account-select" aria-label="Năm sinh">
						<option value="">Năm</option>
						<?php for ( $y = $curr_year - 12; $y >= 1950; $y-- ) : ?>
							<option value="<?php echo (int) $y; ?>" <?php selected( $dob_year, $y ); ?>>
								Năm <?php echo (int) $y; ?>
							</option>
						<?php endfor; ?>
					</select>
				</div>
				<span class="typee-account-field__help">Nhận đặc quyền voucher giảm 15% linh kiện modding vào tháng sinh nhật.</span>
			</div>

			<!-- 5. Giới tính (Segmented Pills, Figma 82:6811) -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<span class="typee-account-field__label" id="typee-gender-label">Giới tính</span>
				</div>
				<div class="typee-account-gender-pills" role="radiogroup" aria-labelledby="typee-gender-label">
					<?php
					$gender_opts = array(
						'male'   => 'Nam',
						'female' => 'Nữ',
						'other'  => 'Khác',
					);
					foreach ( $gender_opts as $g_val => $g_label ) :
						?>
						<label class="typee-account-gender-pill">
							<input
								type="radio"
								name="typee_gender"
								value="<?php echo esc_attr( $g_val ); ?>"
								<?php checked( $gender ? $gender : 'male', $g_val ); ?>
							/>
							<span><?php echo esc_html( $g_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<span class="typee-account-field__help">Tối ưu gợi ý kích thước keycap &amp; wrist-rest phù hợp vóc dáng tay.</span>
			</div>

			<!-- 6. Layout phím quen thuộc nhất (Figma 82:6826) -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<label class="typee-account-field__label" for="typee_fav_layout">Layout phím quen thuộc nhất</label>
				</div>
				<select name="typee_fav_layout" id="typee_fav_layout" class="typee-account-select">
					<?php foreach ( $layouts as $l_key => $l_label ) : ?>
						<option value="<?php echo esc_attr( $l_key ); ?>" <?php selected( $fav_layout, $l_key ); ?>>
							<?php echo esc_html( $l_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="typee-account-field__help">Studio tự động gợi ý plate và switch pinout tương thích với layout này.</span>
			</div>

		</div>

		<?php do_action( 'woocommerce_edit_account_form_fields' ); ?>

		<!-- Action Bar Section 1 (Figma 82:6834) -->
		<div class="typee-account-profile-actions">
			<span class="typee-account-profile-actions__note">
				<?php typee_the_icon( 'verified_user', 16, 'typee-account-profile-actions__icon' ); ?>
				<span>Mọi thay đổi dữ liệu được mã hóa chuẩn e-RMA 256-bit</span>
			</span>
			<div class="typee-account-profile-actions__btns">
				<button type="reset" class="typee-account-btn typee-account-btn--soft">Hủy bỏ</button>
				<button
					type="submit"
					class="woocommerce-Button button typee-account-btn typee-account-btn--accent typee-account-btn--lg"
					name="save_account_details"
					value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"
				>
					<?php typee_the_icon( 'save', 16 ); ?>
					<span>Lưu thay đổi</span>
				</button>
			</div>
		</div>
	</section>

	<!-- SECTION 2: Security & Password Management (Figma 82:6848) -->
	<section class="typee-account-card typee-account-security-section" id="security">
		<div class="typee-account-section-head">
			<div class="typee-account-section-head__left">
				<span class="typee-account-section-bar" aria-hidden="true"></span>
				<h2 class="typee-account-section-title">Đổi mật khẩu tài khoản</h2>
			</div>
			<span class="typee-account-section-meta">
				<?php typee_the_icon( 'lock', 13 ); ?>
				<span>Bảo mật 2FA</span>
			</span>
		</div>

		<p class="typee-account-page-desc">Để bảo mật tài khoản và các cấu hình phím cơ học đã lưu trong Studio, vui lòng không chia sẻ mật khẩu cho người khác. Mật khẩu nên kết hợp chữ hoa, chữ thường, số và ký tự đặc biệt.</p>

		<div class="typee-account-pw-stack">
			<!-- Mật khẩu hiện tại -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<label class="typee-account-field__label" for="password_current">Mật khẩu hiện tại</label>
					<a class="typee-account-forgot-link" href="<?php echo esc_url( $lost_pw_url ); ?>">Quên mật khẩu?</a>
				</div>
				<div class="typee-account-input-wrap">
					<input
						type="password"
						class="woocommerce-Input woocommerce-Input--password input-text typee-account-input typee-account-input--has-right"
						name="password_current"
						id="password_current"
						autocomplete="current-password"
						placeholder="Nhập mật khẩu đang sử dụng (để trống nếu không đổi)"
					/>
					<button
						type="button"
						class="typee-auth-pw-toggle"
						data-typee-pw-toggle="password_current"
						aria-label="Hiện mật khẩu hiện tại"
						aria-pressed="false"
					>
						<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--show"><?php echo typee_auth_eye_svg( false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<span class="typee-auth-pw-toggle__eye typee-auth-pw-toggle__eye--hide" hidden><?php echo typee_auth_eye_svg( true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</button>
				</div>
			</div>

			<!-- Mật khẩu mới + 4-Segment Strength Gauge Strip (Figma 82:6873) -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<label class="typee-account-field__label" for="password_1">Mật khẩu mới</label>
				</div>
				<div class="typee-account-input-wrap">
					<input
						type="password"
						class="woocommerce-Input woocommerce-Input--password input-text typee-account-input typee-account-input--has-right"
						name="password_1"
						id="password_1"
						autocomplete="new-password"
						placeholder="Nhập mật khẩu mới (để trống nếu không đổi)"
						data-typee-profile-pw
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

				<!-- Password Strength Gauge 4 thanh (Figma 82:6882) -->
				<div class="typee-account-pw-gauge" data-typee-pw-gauge>
					<div class="typee-account-pw-gauge__top">
						<span>Độ mạnh mật khẩu:</span>
						<strong class="typee-account-pw-gauge__status" data-typee-pw-gauge-label>Chưa nhập mật khẩu mới</strong>
					</div>
					<div class="typee-account-pw-gauge__bars" aria-hidden="true">
						<span class="typee-account-pw-gauge__seg" data-typee-pw-seg="1"></span>
						<span class="typee-account-pw-gauge__seg" data-typee-pw-seg="2"></span>
						<span class="typee-account-pw-gauge__seg" data-typee-pw-seg="3"></span>
						<span class="typee-account-pw-gauge__seg" data-typee-pw-seg="4"></span>
					</div>
					<ul class="typee-account-pw-criteria">
						<li class="typee-account-pw-criteria__item" data-typee-pw-rule="len">
							<?php typee_the_icon( 'check_circle', 13 ); ?>
							<span>Tối thiểu 8 ký tự</span>
						</li>
						<li class="typee-account-pw-criteria__item" data-typee-pw-rule="num">
							<?php typee_the_icon( 'check_circle', 13 ); ?>
							<span>Ít nhất 1 số (0-9)</span>
						</li>
						<li class="typee-account-pw-criteria__item" data-typee-pw-rule="sym">
							<?php typee_the_icon( 'check_circle', 13 ); ?>
							<span>Ký tự đặc biệt (@, #, !)</span>
						</li>
					</ul>
				</div>
			</div>

			<!-- Xác nhận mật khẩu mới -->
			<div class="typee-account-field">
				<div class="typee-account-field__head">
					<label class="typee-account-field__label" for="password_2">Xác nhận mật khẩu mới</label>
				</div>
				<div class="typee-account-input-wrap">
					<input
						type="password"
						class="woocommerce-Input woocommerce-Input--password input-text typee-account-input typee-account-input--has-right"
						name="password_2"
						id="password_2"
						autocomplete="new-password"
						placeholder="Nhập lại mật khẩu mới"
						data-typee-pw-confirm="password_1"
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

			<?php do_action( 'woocommerce_edit_account_form' ); ?>

			<div class="typee-account-pw-submit-wrap">
				<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
				<input type="hidden" name="action" value="save_account_details" />
				<button
					type="submit"
					class="typee-account-btn typee-account-btn--dark typee-account-btn--lg"
					name="save_account_details"
					value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"
				>
					<?php typee_the_icon( 'lock', 16 ); ?>
					<span>Cập nhật mật khẩu mới</span>
				</button>
			</div>
		</div>

		<!-- Two-Factor Authentication (2FA) Banner (Figma 82:6925) -->
		<div class="typee-account-2fa-banner">
			<input type="hidden" name="typee_2fa_present" value="1" />
			<div class="typee-account-2fa-banner__left">
				<span class="typee-account-2fa-banner__icon" aria-hidden="true">
					<?php typee_the_icon( 'shield', 20 ); ?>
				</span>
				<div class="typee-account-2fa-banner__info">
					<div class="typee-account-2fa-banner__title-row">
						<strong>Xác thực 2 bước (2FA qua SMS OTP)</strong>
						<span
							class="typee-account-badge <?php echo $twofa_on ? 'typee-account-badge--success' : 'typee-account-badge--neutral'; ?>"
							data-typee-2fa-badge
						>
							<?php echo $twofa_on ? 'ĐANG BẬT' : 'ĐANG TẮT'; ?>
						</span>
					</div>
					<p class="typee-account-2fa-banner__desc">Tự động yêu cầu mã OTP khi thanh toán các đơn hàng kit phím nhôm CNC nguyên khối trên 5.000.000₫.</p>
				</div>
			</div>
			<label class="typee-account-2fa-toggle">
				<input
					type="checkbox"
					name="typee_2fa_sms_pref"
					value="yes"
					<?php checked( $twofa_on ); ?>
					data-typee-2fa-checkbox
				/>
				<span class="typee-account-2fa-toggle__btn" data-typee-2fa-label>
					<?php echo $twofa_on ? 'Tắt 2FA' : 'Bật 2FA'; ?>
				</span>
			</label>
		</div>
	</section>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
