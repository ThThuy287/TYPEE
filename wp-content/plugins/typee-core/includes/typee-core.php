<?php
/**
 * Plugin Name: TYPEE Core
 * Description: Hệ thống nghiệp vụ cốt lõi cho TYPEE (Compatibility Rule Engine, Custom Builder API, WooCommerce Hooks).
 * Version: 1.0.0
 * Author: Nhóm TYPEE - UIT
 */

// Chặn truy cập trực tiếp vào file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Nạp Core Logic Rule Engine (Thủy)
$rule_engine_file = plugin_dir_path( __FILE__ ) . 'includes/class-typee-rule-engine.php';
if ( file_exists( $rule_engine_file ) ) {
    require_once $rule_engine_file;
}