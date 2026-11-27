<?php
/**
 * Template Name: TYPEE – Theo dõi đơn hàng & Bảo hành e-RMA
 * Description: Trang tra cứu tiến trình đơn hàng WooCommerce, kiểm tra hạn bảo hành và gửi yêu cầu e-RMA.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/tracking/hub', null, array( 'initial_tab' => 'lookup' ) );
get_footer();
