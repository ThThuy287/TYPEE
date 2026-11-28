<?php
/**
 * My Addresses – Sổ địa chỉ giao hàng (Figma 82:6066 / 82:6205).
 *
 * Nằm trực tiếp bên trong `.typee-account-content` của Layout Shell dùng chung (`my-account.php`).
 * Hỗ trợ đầy đủ địa chỉ Giao hàng (`shipping`), Thanh toán (`billing`) chuẩn WooCommerce và nhiều địa chỉ phụ.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$user_id       = get_current_user_id();
$addresses     = function_exists( 'typee_account_get_addresses' ) ? typee_account_get_addresses( $user_id ) : array();
$addr_count    = count( $addresses );
$label_options = function_exists( 'typee_account_address_label_options' ) ? typee_account_address_label_options() : array();
$base_url      = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address' ) : '';
$new_addr_url  = add_query_arg( 'addr_action', 'new', $base_url );
$shipping_url  = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address', 'shipping' ) : '';
$billing_url   = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address', 'billing' ) : '';

$addr_action = isset( $_GET['addr_action'] ) ? sanitize_key( wp_unslash( $_GET['addr_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$edit_id     = isset( $_GET['addr_id'] ) ? sanitize_key( wp_unslash( $_GET['addr_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

$editing_extra = null;
if ( 'edit' === $addr_action && '' !== $edit_id ) {
	foreach ( $addresses as $a ) {
		if ( $a['id'] === $edit_id && 'extra' === $a['wc_type'] ) {
			$editing_extra = $a;
			break;
		}
	}
}

$show_extra_form = ( 'new' === $addr_action || null !== $editing_extra );
$has_shipping    = false;
$has_billing     = false;
foreach ( $addresses as $a ) {
	if ( 'shipping' === $a['id'] ) {
		$has_shipping = true;
	}
	if ( 'billing' === $a['id'] ) {
		$has_billing = true;
	}
}

$current_user = wp_get_current_user();
$default_who  = $current_user ? trim( $current_user->display_name ) : '';
$default_tel  = (string) get_user_meta( $user_id, 'billing_phone', true );
?>

<!-- Khối 1: Header Sổ địa chỉ & Micro-callout -->
<div class="typee-account-card typee-account-addr-header">
	<div class="typee-account-section-head">
		<div>
			<span class="typee-account-eyebrow">QUẢN LÝ GIAO NHẬN &amp; BẢO HÀNH E-RMA</span>
			<div class="typee-account-title-row">
				<h1 class="typee-account-page-title">Sổ địa chỉ giao hàng</h1>
				<span class="typee-account-count-pill ty-mono"><?php echo (int) $addr_count; ?> địa chỉ</span>
			</div>
			<p class="typee-account-page-sub">Lưu nhiều điểm nhận hàng (nhà riêng, văn phòng, studio) để tự động điền khi đặt hàng và gửi/nhận bảo hành e-RMA.</p>
		</div>

		<div class="typee-account-addr-header__actions">
			<?php if ( $show_extra_form ) : ?>
				<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( $base_url ); ?>">
					<?php typee_the_icon( 'arrow_back', 16 ); ?>
					<span>Quay lại danh sách</span>
				</a>
			<?php else : ?>
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $new_addr_url ); ?>">
					<?php typee_the_icon( 'add', 18 ); ?>
					<span>Thêm địa chỉ mới</span>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="typee-account-addr-callout">
		<span class="typee-account-addr-callout__icon" aria-hidden="true">
			<?php typee_the_icon( 'local_shipping', 18 ); ?>
		</span>
		<p class="typee-account-addr-callout__text">
			Địa chỉ được gắn nhãn <strong>Mặc định</strong> sẽ tự động đồng bộ sang trang Thanh toán (Checkout) và dùng làm địa chỉ lấy/trả hàng khi bạn tạo phiếu bảo hành e-RMA.
		</p>
	</div>
</div>

<?php if ( $show_extra_form ) : ?>
	<?php
	$f_recipient = $editing_extra ? $editing_extra['recipient'] : ( isset( $_POST['addr_recipient'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_recipient'] ) ) : $default_who ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_phone     = $editing_extra ? $editing_extra['phone'] : ( isset( $_POST['addr_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_phone'] ) ) : $default_tel ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_label_key = $editing_extra ? $editing_extra['label_key'] : ( isset( $_POST['addr_label_key'] ) ? sanitize_key( wp_unslash( $_POST['addr_label_key'] ) ) : 'home' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_company   = $editing_extra ? $editing_extra['company'] : ( isset( $_POST['addr_company'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_company'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_address_1 = $editing_extra ? $editing_extra['address_1'] : ( isset( $_POST['addr_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_address_1'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_address_2 = $editing_extra ? $editing_extra['address_2'] : ( isset( $_POST['addr_address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_address_2'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_city      = $editing_extra ? $editing_extra['city'] : ( isset( $_POST['addr_city'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_city'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_state     = $editing_extra ? $editing_extra['state'] : ( isset( $_POST['addr_state'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_state'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_postcode  = $editing_extra ? $editing_extra['postcode'] : ( isset( $_POST['addr_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['addr_postcode'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_note      = $editing_extra ? $editing_extra['note'] : ( isset( $_POST['addr_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['addr_note'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$f_default   = $editing_extra ? ! empty( $editing_extra['is_default'] ) : ( 0 === $addr_count );
	?>
	<!-- Form Thêm / Chỉnh sửa địa chỉ bổ sung -->
	<div class="typee-account-card typee-account-addr-form-card">
		<div class="typee-account-card__head">
			<div>
				<h2 class="typee-account-card__title">
					<?php echo $editing_extra ? 'Chỉnh sửa địa chỉ giao hàng' : 'Thêm địa chỉ giao hàng mới'; ?>
				</h2>
				<p class="typee-account-card__sub">Điền chính xác thông tin người nhận và ghi chú giao hàng để shipper liên hệ nhanh chóng.</p>
			</div>
		</div>

		<form method="post" class="typee-account-form">
			<?php wp_nonce_field( 'typee_address_book', 'typee_address_nonce' ); ?>
			<input type="hidden" name="typee_address_book_action" value="save_extra">
			<?php if ( $editing_extra ) : ?>
				<input type="hidden" name="addr_id" value="<?php echo esc_attr( $editing_extra['id'] ); ?>">
			<?php endif; ?>

			<div class="typee-account-form-grid">
				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_recipient">Họ và tên người nhận <span class="typee-account-req">*</span></label>
					<input type="text" id="addr_recipient" name="addr_recipient" class="typee-account-input" value="<?php echo esc_attr( $f_recipient ); ?>" placeholder="VD: Nguyễn Minh Khoa" required>
				</div>

				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_phone">Số điện thoại nhận hàng <span class="typee-account-req">*</span></label>
					<input type="tel" id="addr_phone" name="addr_phone" class="typee-account-input ty-mono" value="<?php echo esc_attr( $f_phone ); ?>" placeholder="VD: 0912 345 678" required>
				</div>

				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_label_key">Phân loại địa chỉ</label>
					<select id="addr_label_key" name="addr_label_key" class="typee-account-select">
						<?php foreach ( $label_options as $key => $opt ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f_label_key, $key ); ?>>
								<?php echo esc_html( $opt['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_company">Tên công ty / Studio (tùy chọn)</label>
					<input type="text" id="addr_company" name="addr_company" class="typee-account-input" value="<?php echo esc_attr( $f_company ); ?>" placeholder="VD: TYPEE Modding Lab">
				</div>

				<div class="typee-account-field typee-account-field--full">
					<label class="typee-account-field__label" for="addr_address_1">Số nhà, tên đường, phường / xã <span class="typee-account-req">*</span></label>
					<input type="text" id="addr_address_1" name="addr_address_1" class="typee-account-input" value="<?php echo esc_attr( $f_address_1 ); ?>" placeholder="VD: Số 42 Triều Khúc, P. Thanh Xuân Nam" required>
				</div>

				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_address_2">Tòa nhà, căn hộ, quận / huyện</label>
					<input type="text" id="addr_address_2" name="addr_address_2" class="typee-account-input" value="<?php echo esc_attr( $f_address_2 ); ?>" placeholder="VD: Tầng 4, Q. Thanh Xuân">
				</div>

				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_city">Tỉnh / Thành phố <span class="typee-account-req">*</span></label>
					<input type="text" id="addr_city" name="addr_city" class="typee-account-input" value="<?php echo esc_attr( $f_city ); ?>" placeholder="VD: Hà Nội" required>
				</div>

				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_state">Khu vực / Quận huyện (tùy chọn)</label>
					<input type="text" id="addr_state" name="addr_state" class="typee-account-input" value="<?php echo esc_attr( $f_state ); ?>" placeholder="VD: Thanh Xuân">
				</div>

				<div class="typee-account-field">
					<label class="typee-account-field__label" for="addr_postcode">Mã bưu chính (tùy chọn)</label>
					<input type="text" id="addr_postcode" name="addr_postcode" class="typee-account-input ty-mono" value="<?php echo esc_attr( $f_postcode ); ?>" placeholder="VD: 100000">
				</div>

				<div class="typee-account-field typee-account-field--full">
					<label class="typee-account-field__label" for="addr_note">Ghi chú vận chuyển</label>
					<textarea id="addr_note" name="addr_note" rows="3" class="typee-account-textarea" placeholder="VD: Giao giờ hành chính (9h - 17h), gọi trước 15 phút, kiểm tra kỹ kiện chống sốc..."><?php echo esc_textarea( $f_note ); ?></textarea>
				</div>
			</div>

			<label class="typee-account-checkbox-row">
				<input type="checkbox" name="addr_set_default" value="1" <?php checked( $f_default ); ?>>
				<span>Đặt làm địa chỉ giao hàng mặc định cho Thanh toán &amp; e-RMA</span>
			</label>

			<div class="typee-account-form-actions">
				<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( $base_url ); ?>">Hủy bỏ</a>
				<button type="submit" class="ty-btn ty-btn--primary">
					<?php typee_the_icon( 'save', 16 ); ?>
					<span><?php echo $editing_extra ? 'Lưu thay đổi địa chỉ' : 'Lưu địa chỉ mới'; ?></span>
				</button>
			</div>
		</form>
	</div>
<?php endif; ?>

<!-- Khối 2: Lưới 2 cột các thẻ địa chỉ đã lưu + Thẻ thêm nhanh -->
<div class="typee-account-addr-grid">
	<?php foreach ( $addresses as $addr ) : ?>
		<?php
		$is_default   = ! empty( $addr['is_default'] );
		$full_address = function_exists( 'typee_account_format_address_line' ) ? typee_account_format_address_line( $addr ) : $addr['address_1'];
		$card_cls     = 'typee-account-addr-card' . ( $is_default ? ' typee-account-addr-card--default' : '' );
		?>
		<article class="<?php echo esc_attr( $card_cls ); ?>">
			<div class="typee-account-addr-card__top">
				<div class="typee-account-addr-card__tags">
					<span class="typee-account-addr-card__type">
						<?php typee_the_icon( $addr['icon'], 15 ); ?>
						<span><?php echo esc_html( $addr['label'] ); ?></span>
					</span>
					<span class="typee-account-addr-card__kind"><?php echo esc_html( $addr['type_badge'] ); ?></span>
				</div>

				<?php if ( $is_default ) : ?>
					<span class="typee-account-badge typee-account-badge--accent">
						<?php typee_the_icon( 'check_circle', 13 ); ?>
						<span>MẶC ĐỊNH</span>
					</span>
				<?php endif; ?>
			</div>

			<div class="typee-account-addr-card__who">
				<h2 class="typee-account-addr-card__name"><?php echo esc_html( $addr['recipient'] ?: $default_who ); ?></h2>
				<?php if ( '' !== $addr['phone'] ) : ?>
					<div class="typee-account-addr-card__phone-row">
						<span class="typee-account-addr-card__phone ty-mono">
							<?php typee_the_icon( 'call', 14 ); ?>
							<span><?php echo esc_html( $addr['phone'] ); ?></span>
						</span>
						<?php if ( ! empty( $addr['verified'] ) ) : ?>
							<span class="typee-account-addr-card__verified">
								<?php typee_the_icon( 'verified', 13 ); ?>
								<span>SĐT tài khoản</span>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="typee-account-addr-card__box">
				<span class="typee-account-addr-card__pin" aria-hidden="true">
					<?php typee_the_icon( 'location_on', 16 ); ?>
				</span>
				<div class="typee-account-addr-card__addr-text">
					<?php if ( '' !== $addr['company'] ) : ?>
						<strong class="typee-account-addr-card__company"><?php echo esc_html( $addr['company'] ); ?></strong>
					<?php endif; ?>
					<p><?php echo esc_html( $full_address ); ?></p>
					<?php if ( '' !== $addr['postcode'] ) : ?>
						<span class="typee-account-addr-card__zip ty-mono">Mã bưu chính: <?php echo esc_html( $addr['postcode'] ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="typee-account-addr-card__note">
				<span class="typee-account-addr-card__note-label">GHI CHÚ VẬN CHUYỂN</span>
				<p class="typee-account-addr-card__note-text">
					<?php
					if ( '' !== $addr['note'] ) {
						echo esc_html( $addr['note'] );
					} else {
						echo 'Chưa có ghi chú riêng — Giao giờ hành chính, kiểm tra niêm phong chống sốc khi nhận.';
					}
					?>
				</p>
			</div>

			<div class="typee-account-addr-card__footer">
				<div class="typee-account-addr-card__btns">
					<a class="typee-account-addr-btn" href="<?php echo esc_url( $addr['edit_url'] ); ?>">
						<?php typee_the_icon( 'edit', 14 ); ?>
						<span>Chỉnh sửa</span>
					</a>

					<form method="post" class="typee-account-inline-form" data-typee-confirm="Bạn chắc chắn muốn xóa địa chỉ này khỏi sổ địa chỉ?">
						<?php wp_nonce_field( 'typee_address_book', 'typee_address_nonce' ); ?>
						<input type="hidden" name="typee_address_book_action" value="delete">
						<input type="hidden" name="addr_id" value="<?php echo esc_attr( $addr['id'] ); ?>">
						<button type="submit" class="typee-account-addr-btn typee-account-addr-btn--danger">
							<?php typee_the_icon( 'delete', 14 ); ?>
							<span>Xóa</span>
						</button>
					</form>
				</div>

				<?php if ( ! $is_default ) : ?>
					<form method="post" class="typee-account-inline-form">
						<?php wp_nonce_field( 'typee_address_book', 'typee_address_nonce' ); ?>
						<input type="hidden" name="typee_address_book_action" value="set_default">
						<input type="hidden" name="addr_id" value="<?php echo esc_attr( $addr['id'] ); ?>">
						<button type="submit" class="typee-account-addr-default-btn">
							<?php typee_the_icon( 'check', 14 ); ?>
							<span>Đặt làm mặc định</span>
						</button>
					</form>
				<?php else : ?>
					<span class="typee-account-addr-active-note">
						<?php typee_the_icon( 'verified', 14 ); ?>
						<span>Đang chọn mặc định</span>
					</span>
				<?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>

	<!-- Thẻ thêm địa chỉ mới nhanh (Quick Add Address Card) -->
	<div class="typee-account-addr-add">
		<div class="typee-account-addr-add__icon" aria-hidden="true">
			<?php typee_the_icon( 'add', 26 ); ?>
		</div>
		<h2 class="typee-account-addr-add__title">
			<?php echo 0 === $addr_count ? 'Thêm địa chỉ giao hàng đầu tiên' : 'Thêm địa chỉ giao hàng khác'; ?>
		</h2>
		<p class="typee-account-addr-add__desc">
			Lưu địa chỉ văn phòng, nhà riêng hoặc người thân để chuyển đổi chỉ với 1 chạm khi thanh toán.
		</p>
		<div class="typee-account-addr-add__actions">
			<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $new_addr_url ); ?>">
				<?php typee_the_icon( 'add', 16 ); ?>
				<span>Thêm địa chỉ mới</span>
			</a>
			<div class="typee-account-addr-add__links">
				<?php if ( ! $has_shipping ) : ?>
					<a class="typee-account-text-link" href="<?php echo esc_url( $shipping_url ); ?>">Thiết lập địa chỉ giao hàng</a>
				<?php endif; ?>
				<?php if ( ! $has_billing ) : ?>
					<a class="typee-account-text-link" href="<?php echo esc_url( $billing_url ); ?>">Thiết lập địa chỉ thanh toán</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<!-- Khối 3: Cam kết Logistics chống sốc & e-RMA toàn quốc (Figma 82:6352) -->
<div class="typee-account-card typee-account-addr-logistics">
	<div class="typee-account-addr-logistics__head">
		<span class="typee-account-addr-logistics__icon" aria-hidden="true">
			<?php typee_the_icon( 'verified_user', 22 ); ?>
		</span>
		<div>
			<h2 class="typee-account-card__title">Cam kết vận chuyển chống sốc &amp; Bảo hành e-RMA tận nơi</h2>
			<p class="typee-account-card__sub">Mọi đơn Kit nhôm CNC, Switch và bàn phím Custom đều được TYPEE bảo hiểm 100% giá trị trong quá trình giao nhận.</p>
		</div>
	</div>

	<div class="typee-account-addr-logistics__grid">
		<div class="typee-account-addr-logistics__item">
			<?php typee_the_icon( 'inventory_2', 18 ); ?>
			<div>
				<strong>Đóng gói chống sốc 3 lớp</strong>
				<span>Foam PE định hình góc hộp, niêm phong tem vỡ KCS từ TYPEE Studio.</span>
			</div>
		</div>
		<div class="typee-account-addr-logistics__item">
			<?php typee_the_icon( 'local_shipping', 18 ); ?>
			<div>
				<strong>Giao hỏa tốc 2H nội thành</strong>
				<span>Hỗ trợ nội thành Hà Nội &amp; TP.HCM, đồng kiểm ngoại quan trước khi nhận.</span>
			</div>
		</div>
		<div class="typee-account-addr-logistics__item">
			<?php typee_the_icon( 'handyman', 18 ); ?>
			<div>
				<strong>Thu hồi e-RMA tại địa chỉ mặc định</strong>
				<span>Shipper lấy hàng tận cửa và hoàn trả sau khi KTV hoàn tất xử lý bảo hành.</span>
			</div>
		</div>
	</div>
</div>
