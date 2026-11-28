<?php
/**
 * Edit address form – Chỉnh sửa địa chỉ Giao hàng / Thanh toán chuẩn WooCommerce.
 *
 * Nằm trực tiếp bên trong `.typee-account-content` của Layout Shell dùng chung (`my-account.php`),
 * giữ nguyên Sidebar trái với mục "Sổ địa chỉ giao hàng" đang active.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$page_title    = ( 'billing' === $load_address ) ? 'Chỉnh sửa địa chỉ thanh toán' : 'Chỉnh sửa địa chỉ giao hàng';
$back_url      = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-address' ) : '';
$user_id       = get_current_user_id();
$label_options = function_exists( 'typee_account_address_label_options' ) ? typee_account_address_label_options() : array();
$saved_label   = sanitize_key( (string) get_user_meta( $user_id, 'typee_address_label_' . $load_address, true ) );
if ( ! isset( $label_options[ $saved_label ] ) ) {
	$saved_label = ( 'billing' === $load_address ) ? 'office' : 'home';
}
$saved_note     = (string) get_user_meta( $user_id, 'typee_address_note_' . $load_address, true );
$shipping_phone = (string) get_user_meta( $user_id, 'shipping_phone', true );
if ( '' === $shipping_phone ) {
	$shipping_phone = (string) get_user_meta( $user_id, 'billing_phone', true );
}
$default_key = sanitize_key( (string) get_user_meta( $user_id, 'typee_default_address', true ) );
$is_default  = ( $default_key === $load_address ) || ( '' === $default_key && 'shipping' === $load_address );

do_action( 'woocommerce_before_edit_account_address_form' );
?>

<?php if ( ! $load_address ) : ?>
	<?php wc_get_template( 'myaccount/my-address.php' ); ?>
<?php else : ?>

	<div class="typee-account-card typee-account-edit-addr">
		<div class="typee-account-edit-addr__top">
			<a class="typee-account-back-link" href="<?php echo esc_url( $back_url ); ?>">
				<?php typee_the_icon( 'arrow_back', 16 ); ?>
				<span>Quay lại Sổ địa chỉ</span>
			</a>
			<span class="typee-account-badge typee-account-badge--neutral">
				<?php echo 'billing' === $load_address ? 'ĐỊA CHỈ THANH TOÁN' : 'ĐỊA CHỈ GIAO HÀNG'; ?>
			</span>
		</div>

		<div class="typee-account-card__head">
			<div>
				<h1 class="typee-account-page-title"><?php echo esc_html( apply_filters( 'woocommerce_my_account_edit_address_title', $page_title, $load_address ) ); ?></h1>
				<p class="typee-account-page-sub">Thông tin này được sử dụng cho hóa đơn, giao nhận thiết bị bàn phím custom và bảo hành điện tử e-RMA.</p>
			</div>
		</div>

		<form method="post" class="typee-account-form typee-account-wc-addr-form" novalidate="novalidate">
			<div class="woocommerce-address-fields">
				<?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>

				<div class="typee-account-form-grid typee-account-form-grid--extra">
					<div class="typee-account-field">
						<label class="typee-account-field__label" for="typee_address_label">Phân loại địa chỉ</label>
						<select id="typee_address_label" name="typee_address_label" class="typee-account-select">
							<?php foreach ( $label_options as $key => $opt ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_label, $key ); ?>>
									<?php echo esc_html( $opt['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<?php if ( 'shipping' === $load_address ) : ?>
						<div class="typee-account-field">
							<label class="typee-account-field__label" for="typee_shipping_phone">Số điện thoại nhận hàng</label>
							<input type="tel" id="typee_shipping_phone" name="typee_shipping_phone" class="typee-account-input ty-mono" value="<?php echo esc_attr( $shipping_phone ); ?>" placeholder="VD: 0912 345 678">
						</div>
					<?php endif; ?>
				</div>

				<div class="woocommerce-address-fields__field-wrapper typee-account-wc-fields">
					<?php
					foreach ( $address as $key => $field ) {
						woocommerce_form_field( $key, $field, wc_get_post_data_by_key( $key, $field['value'] ) );
					}
					?>
				</div>

				<div class="typee-account-field typee-account-field--full">
					<label class="typee-account-field__label" for="typee_address_note">Ghi chú vận chuyển</label>
					<textarea id="typee_address_note" name="typee_address_note" rows="3" class="typee-account-textarea" placeholder="VD: Giao giờ hành chính, gọi trước 15 phút, kiểm tra niêm phong..."><?php echo esc_textarea( $saved_note ); ?></textarea>
				</div>

				<label class="typee-account-checkbox-row">
					<input type="checkbox" name="typee_set_default_address" value="1" <?php checked( $is_default ); ?>>
					<span>Đặt làm địa chỉ mặc định hiển thị đầu tiên trong Sổ địa chỉ</span>
				</label>

				<?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>

				<div class="typee-account-form-actions">
					<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( $back_url ); ?>">Hủy bỏ</a>
					<button type="submit" class="ty-btn ty-btn--primary button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_address" value="<?php esc_attr_e( 'Save address', 'woocommerce' ); ?>">
						<?php typee_the_icon( 'save', 16 ); ?>
						<span>Lưu thay đổi địa chỉ</span>
					</button>
					<?php wp_nonce_field( 'woocommerce-edit_address', 'woocommerce-edit-address-nonce' ); ?>
					<input type="hidden" name="action" value="edit_address" />
				</div>
			</div>
		</form>
	</div>

<?php endif; ?>

<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>
