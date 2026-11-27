<?php
/**
 * Template chuẩn WordPress/WooCommerce cho `/?s=<keyword>&post_type=product`.
 * Dùng chung giao diện Kết quả tìm kiếm chuẩn Figma 82:1823.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/search/results' );
get_footer();
