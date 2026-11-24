<?php
/**
 * Thanh "Hệ màu Studio" – Figma 1:5. Bỏ khỏi trang chủ bằng filter `typee_home_sections` nếu không cần.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$swatches = array(
	array( '#FF5722', 'Accent', 'var(--ty-accent)', true ),
	array( '#E64A19', 'Hover', 'var(--ty-accent-strong)', true ),
	array( '#1E1E24', 'Tiêu đề', 'var(--ty-ink)', false ),
	array( '#4A4A55', 'Nội dung', 'var(--ty-muted)', false ),
	array( '#E2E4E9', 'Khung viền', 'var(--ty-border)', false ),
	array( '#F5F5F7', 'Bề mặt', 'var(--ty-chip)', false ),
	array( '#FFFFFF', 'Nền Canvas', '#fff', true ),
	array( '#141418', 'Carbon Tối', 'var(--ty-carbon)', false ),
);
?>
<section class="ty-palette" data-node="1:5">
	<div class="ty-container ty-palette__inner">
		<div class="ty-palette__head">
			<span class="ty-palette__ic"><?php typee_the_icon( 'palette', 18 ); ?></span>
			<div>
				<div class="ty-palette__eyebrow">HỆ MÀU STUDIO</div>
				<div class="ty-palette__name">Blocksy Mechanical<br>Palette v3.0</div>
			</div>
		</div>
		<ul class="ty-palette__list">
			<?php foreach ( $swatches as $s ) : ?>
				<li>
					<span class="ty-palette__dot<?php echo $s[3] ? ' has-ring' : ''; ?>" style="background:<?php echo esc_attr( $s[2] ); ?>"></span>
					<strong><?php echo esc_html( $s[0] ); ?></strong>
					<span><?php echo esc_html( $s[1] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
