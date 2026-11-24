<?php
/**
 * Trang chủ – Figma frame 1:2 "TYPEE - Trang chủ & Hệ thống nhận diện Gadgets".
 * Thứ tự section có thể đổi/bỏ qua filter `typee_home_sections`.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sections = apply_filters(
	'typee_home_sections',
	array( 'hero', 'palette', 'categories', 'deals', 'builder', 'soundtest', 'newsletter' )
);
?>
<div class="ty-home">
	<?php
	foreach ( $sections as $section ) {
		get_template_part( 'template-parts/home/' . sanitize_file_name( $section ) );
	}
	?>
</div>
<?php
get_footer();
