<?php
/**
 * Trang ảo `/search/?q=<keyword>` (đăng ký trong inc/routes.php).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/search/results' );
get_footer();
