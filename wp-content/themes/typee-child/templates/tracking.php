<?php
/**
 * Trang ảo `/tracking-rma/` (đăng ký trong inc/routes.php).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/tracking/hub', null, array( 'initial_tab' => 'lookup' ) );
get_footer();
