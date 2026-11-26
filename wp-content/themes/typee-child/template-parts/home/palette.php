<?php
/**
 * Thanh "Hệ màu Studio" – Figma 1:5. Bỏ khỏi trang chủ bằng filter `typee_home_sections` nếu không cần.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$swatches = array(
	array( '#FF5722', 'Kinetic Accent', 'var(--ty-accent)', true ),
	array( '#F4511E', 'Hover', 'var(--ty-accent-hover)', true ),
	array( '#FFF3E0', 'Nền nhạt', 'var(--ty-accent-light)', true ),
	array( '#0F1117', 'Surface Dark', 'var(--ty-carbon)', false ),
	array( '#1A202C', 'Card tối', 'var(--ty-carbon-card)', false ),
	array( '#FBF8FF', 'Surface Light', 'var(--ty-canvas)', true ),
	array( '#10B981', 'Success', 'var(--ty-success)', false ),
	array( '#3B82F6', 'Studio Info', 'var(--ty-info)', false ),
);
?>
<section class="ty-palette" data-node="1:5">
	<div class="ty-container ty-palette__inner">
		<div class="ty-palette__head">
			<span class="ty-palette__ic"><?php typee_the_icon( 'palette', 18 ); ?></span>
			<div>
				<div class="ty-palette__eyebrow">HỆ MÀU STUDIO</div>
				<div class="ty-palette__name">Kinetic Precision<br>Design Tokens</div>
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
