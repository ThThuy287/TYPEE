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

// 1. Nạp Core Logic Rule Engine (Thủy)
$rule_engine_file = plugin_dir_path( __FILE__ ) . 'includes/class-typee-rule-engine.php';
if ( file_exists( $rule_engine_file ) ) {
    require_once $rule_engine_file;
}

// 2. Nạp REST API & WooCommerce Hooks (Lele)
$builder_api_file = plugin_dir_path( __FILE__ ) . 'includes/class-builder-api.php';
if ( file_exists( $builder_api_file ) ) {
    require_once $builder_api_file;
    if ( class_exists( 'TYPEE_Builder_API' ) ) {
        TYPEE_Builder_API::init(); 
    }
}

// 3. Nạp REST API & Ma trận đối chiếu EAV cho trang So sánh (Figma 82:2526)
$compare_api_file = plugin_dir_path( __FILE__ ) . 'includes/class-compare-api.php';
if ( file_exists( $compare_api_file ) ) {
    require_once $compare_api_file;
    if ( class_exists( 'TYPEE_Compare_API' ) ) {
        TYPEE_Compare_API::init();
    }
}

// 4. Nạp Cổng Thanh Toán Miễn Phí & Nghiệp Vụ Checkout WooCommerce (Figma 82:1187 & 82:8375)
$gateways_file = plugin_dir_path( __FILE__ ) . 'includes/class-typee-payment-gateways.php';
if ( file_exists( $gateways_file ) ) {
    require_once $gateways_file;
    if ( class_exists( 'TYPEE_Payment_Gateways' ) ) {
        TYPEE_Payment_Gateways::init();
    }
}

$checkout_service_file = plugin_dir_path( __FILE__ ) . 'includes/class-typee-checkout-service.php';
if ( file_exists( $checkout_service_file ) ) {
    require_once $checkout_service_file;
    if ( class_exists( 'TYPEE_Checkout_Service' ) ) {
        TYPEE_Checkout_Service::init();
    }
}
