<?php
/**
 * Thẻ Radio Từng Phương Thức Thanh Toán (Figma 82:1386 - 82:1467)
 *
 * @var WC_Payment_Gateway $gateway
 * @package typee-child
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gid = $gateway->id;

// Ánh xạ icon, màu sắc & nhãn badge cho từng phương thức thanh toán theo Figma.
$visual = array(
	'icon'       => 'payments',
	'icon_tone'  => 'muted',
	'badge'      => '',
	'badge_tone' => 'neutral',
	'banks'      => array(),
);

if ( 'typee_vietqr' === $gid || 'bacs' === $gid ) {
	$visual = array(
		'icon'       => 'qr_code',
		'icon_tone'  => 'accent',
		'badge'      => 'Khuyên dùng • Tự động 10s',
		'badge_tone' => 'accent',
		'banks'      => array( 'MB Bank', 'Vietcombank', 'Techcombank', 'Napas247' ),
	);
} elseif ( false !== strpos( $gid, 'momo' ) ) {
	$visual = array(
		'icon'       => 'mobile',
		'icon_tone'  => 'momo',
		'badge'      => 'QR / Ứng dụng MoMo',
		'badge_tone' => 'soft',
		'banks'      => array(),
	);
} elseif ( 'cod' === $gid ) {
	$visual = array(
		'icon'       => 'local_shipping',
		'icon_tone'  => 'muted',
		'badge'      => 'Đồng kiểm ngoại quan',
		'badge_tone' => 'soft',
		'banks'      => array(),
	);
} elseif ( false !== strpos( $gid, 'vnpay' ) ) {
	$visual = array(
		'icon'       => 'credit_card',
		'icon_tone'  => 'vnpay',
		'badge'      => 'VNPay-QR • Visa / Master / ATM',
		'badge_tone' => 'neutral',
		'banks'      => array(),
	);
}
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gid ); ?> ty-co-pay-item <?php echo $gateway->chosen ? 'is-chosen' : ''; ?>">
	<input
		id="payment_method_<?php echo esc_attr( $gid ); ?>"
		type="radio"
		class="input-radio ty-co-pay-radio"
		name="payment_method"
		value="<?php echo esc_attr( $gid ); ?>"
		<?php checked( $gateway->chosen, true ); ?>
	/>

	<label class="ty-co-pay-label" for="payment_method_<?php echo esc_attr( $gid ); ?>">
		<span class="ty-co-pay-ic is-<?php echo esc_attr( $visual['icon_tone'] ); ?>">
			<?php typee_the_icon( $visual['icon'], 22 ); ?>
		</span>

		<span class="ty-co-pay-content">
			<span class="ty-co-pay-title-row">
				<strong class="ty-co-pay-title"><?php echo wp_kses_post( $gateway->get_title() ); ?></strong>
				<?php if ( $visual['badge'] ) : ?>
					<span class="ty-co-pay-badge is-<?php echo esc_attr( $visual['badge_tone'] ); ?>">
						<?php echo esc_html( $visual['badge'] ); ?>
					</span>
				<?php endif; ?>
			</span>
			<?php if ( $gateway->get_description() ) : ?>
				<span class="ty-co-pay-desc"><?php echo wp_kses_post( wp_strip_all_tags( $gateway->get_description() ) ); ?></span>
			<?php endif; ?>
		</span>

		<span class="ty-co-pay-indicator" aria-hidden="true">
			<?php typee_the_icon( 'check', 12 ); ?>
		</span>
	</label>

	<?php if ( 'typee_vietqr' === $gid ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gid ); ?> ty-co-pay-box" <?php if ( ! $gateway->chosen ) : ?>style="display:none;"<?php endif; ?>>
			<div class="ty-co-vietqr-hint">
				<div class="ty-co-vietqr-hint__left">
					<span class="ty-co-vietqr-hint__ic"><?php typee_the_icon( 'qr_code', 22 ); ?></span>
					<div>
						<strong>Hệ thống sinh mã VietQR tự động</strong>
						<span>Mã QR đúng số tiền &amp; mã đơn hiển thị ngay sau khi bấm "Đặt Hàng Ngay".</span>
					</div>
				</div>
				<div class="ty-co-vietqr-hint__banks">
					<?php foreach ( $visual['banks'] as $b_name ) : ?>
						<span><?php echo esc_html( $b_name ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php elseif ( $gateway->has_fields() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gid ); ?> ty-co-pay-box" <?php if ( ! $gateway->chosen ) : ?>style="display:none;"<?php endif; ?>>
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
