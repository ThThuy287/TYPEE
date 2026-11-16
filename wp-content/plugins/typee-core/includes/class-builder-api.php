<?php
/**
 * Class TYPEE_Builder_API
 * Đăng ký REST API và WooCommerce Hooks cho Custom Builder (Task 6.3 - Lele)
 */
class TYPEE_Builder_API {

    public static function init() {
        // Đăng ký API Endpoint
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        
        // Bắt sự kiện Add to Cart của WooCommerce để chặn hàng lỗi
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 10, 3);
    }

    public static function register_routes() {
        register_rest_route('typee/v1', '/validate', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'handle_validation_request'],
            'permission_callback' => '__return_true', // Cho phép client gọi không cần xác thực
        ]);
    }

    /**
     * Hàm xử lý request từ Frontend gửi lên API
     */
    public static function handle_validation_request(WP_REST_Request $request) {
        $params = $request->get_json_params();

        $kit_id = isset($params['kit_id']) ? intval($params['kit_id']) : 0;
        $switch_id = isset($params['switch_id']) ? intval($params['switch_id']) : 0;
        $keycap_id = isset($params['keycap_id']) ? intval($params['keycap_id']) : 0;
        $pack_qty = isset($params['switch_pack_qty']) ? intval($params['switch_pack_qty']) : 0;

        // Gọi Core Logic của Thủy
        if (class_exists('TYPEE_Rule_Engine')) {
            $result = TYPEE_Rule_Engine::validate_configuration($kit_id, $switch_id, $keycap_id, $pack_qty);
            
            return rest_ensure_response([
                'success' => true,
                'data'    => $result
            ]);
        }

        return new WP_Error('missing_core', 'Hệ thống Core Logic chưa sẵn sàng.', ['status' => 500]);
    }

    /**
     * Hàm chặn WooCommerce thêm vào giỏ hàng nếu cấu hình bị lỗi
     */
    public static function validate_add_to_cart($passed, $product_id, $quantity) {
        // Lấy data từ request khi người dùng bấm nút mua (cần Frontend truyền data)
        $is_builder_request = isset($_POST['is_typee_builder']) ? sanitize_text_field($_POST['is_typee_builder']) : false;

        if ($is_builder_request && class_exists('TYPEE_Rule_Engine')) {
            $kit_id = isset($_POST['kit_id']) ? intval($_POST['kit_id']) : 0;
            $switch_id = isset($_POST['switch_id']) ? intval($_POST['switch_id']) : 0;
            $keycap_id = isset($_POST['keycap_id']) ? intval($_POST['keycap_id']) : 0;
            $pack_qty = isset($_POST['switch_pack_qty']) ? intval($_POST['switch_pack_qty']) : 0;

            $result = TYPEE_Rule_Engine::validate_configuration($kit_id, $switch_id, $keycap_id, $pack_qty);

            if (!$result['is_valid']) {
                $error_msg = implode('<br>', $result['errors']);
                wc_add_notice('Lỗi Cấu Hình: ' . $error_msg, 'error');
                return false; // Chặn không cho đưa vào giỏ
            }
        }

        return $passed;
    }
}
