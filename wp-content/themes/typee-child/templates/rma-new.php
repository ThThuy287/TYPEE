<?php
/**
 * Trang ảo `/rma/new-ticket/` (đăng ký trong inc/routes.php) – mở sẵn Tab Tạo yêu cầu bảo hành e-RMA.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/tracking/hub', null, array( 'initial_tab' => 'rma' ) );
get_footer();
