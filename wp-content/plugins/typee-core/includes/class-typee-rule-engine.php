<?php
/**
 * Class TYPEE_Rule_Engine
 * Xử lý logic kiểm tra tương thích giữa Kit, Switch và Keycap
 */
class TYPEE_Rule_Engine {

    /**
     * Kiểm tra tính hợp lệ của cấu hình
     *
     * @param int $kit_id
     * @param int $switch_id
     * @param int $keycap_id
     * @param int $switch_pack_qty Số lượng pack switch khách chọn
     * @return array
     */
    public static function validate_configuration($kit_id, $switch_id, $keycap_id, $switch_pack_qty = 0) {
        $is_valid = true;
        $errors = [];
        $warnings = [];
        $total_price = 0;

        // 1. Tạo Cache Key
        $cache_key = md5("typee_builder_{$kit_id}_{$switch_id}_{$keycap_id}_{$switch_pack_qty}");
        
        // 2. Đọc từ Redis Cache (nếu có)
        $cached_result = wp_cache_get($cache_key, 'typee_builder');
        if (false !== $cached_result) {
            return $cached_result;
        }

        // Lấy thông tin giá (Đảm bảo sản phẩm tồn tại)
        $kit_product = wc_get_product($kit_id);
        $switch_product = wc_get_product($switch_id);
        $keycap_product = wc_get_product($keycap_id);

        if (!$kit_product) {
            return ['is_valid' => false, 'errors' => ['Vui lòng chọn Kit bàn phím.']];
        }

        // Cấp nhật tổng giá tạm thời
        $total_price += (float) $kit_product->get_price();
        if ($switch_product) $total_price += ((float) $switch_product->get_price() * $switch_pack_qty);
        if ($keycap_product) $total_price += (float) $keycap_product->get_price();

        // 3. Lấy thông tin Taxonomy (EAV) thay vì ACF Meta
        $kit_layout_terms = wp_get_post_terms($kit_id, 'pa_layout', ['fields' => 'names']);
        $kit_pcb_terms    = wp_get_post_terms($kit_id, 'pa_pcb', ['fields' => 'names']);
        
        $kit_layout = !empty($kit_layout_terms) ? $kit_layout_terms[0] : '';
        $kit_pcb    = !empty($kit_pcb_terms) ? $kit_pcb_terms[0] : '';

        // --- RULE 1: KIỂM TRA PIN CỦA SWITCH VÀ MẠCH KIT ---
        if ($switch_product) {
            $switch_pin_terms = wp_get_post_terms($switch_id, 'pa_pin', ['fields' => 'names']);
            $switch_pin = !empty($switch_pin_terms) ? $switch_pin_terms[0] : '';

            // Nếu mạch Kit là 3-pin mà cắm switch 5-pin -> BLOCK
            if (strpos($kit_pcb, '3-pin') !== false && strpos($switch_pin, '5-pin') !== false && strpos($switch_pin, '3-pin') === false) {
                $is_valid = false;
                $errors[] = "Kit mạch 3-pin không cắm vừa Switch 5-pin. Vui lòng chọn Switch 3-pin.";
            }
        }

        // --- RULE 2: KIỂM TRA SỐ LƯỢNG SWITCH (PACK = 35) ---
        if ($switch_product && $kit_layout) {
            $min_switches_needed = self::get_min_switches_needed($kit_layout);
            $total_switches = $switch_pack_qty * 35; // Giả sử 1 pack có 35 switch

            if ($total_switches < $min_switches_needed) {
                $is_valid = false;
                $errors[] = "Số lượng switch không đủ. Layout {$kit_layout} cần tối thiểu {$min_switches_needed} switch.";
            }
        }

        // --- RULE 3: KIỂM TRA LAYOUT KEYCAP ---
        if ($keycap_product && $kit_layout) {
            $keycap_layout_terms = wp_get_post_terms($keycap_id, 'pa_supported_layout', ['fields' => 'names']);
            $keycap_layout = !empty($keycap_layout_terms) ? $keycap_layout_terms[0] : '';

            $hard_layouts = ['65%', '75%', '96%', '98%'];
            if (in_array($kit_layout, $hard_layouts) && strpos($keycap_layout, '104 keys') !== false) {
                // Ràng buộc mềm -> Cảnh báo
                $warnings[] = "Cảnh báo: Bộ keycap này có thể thiếu nút Shift ngắn 1.75u hoặc phím Alt/Fn 1u cho layout {$kit_layout}.";
            }
        }

        // 4. Chuẩn bị kết quả
        $result = [
            'is_valid'    => $is_valid,
            'total_price' => $total_price,
            'errors'      => $errors,
            'warnings'    => $warnings
        ];

        // 5. Lưu vào Redis Cache (Cache 1 giờ)
        wp_cache_set($cache_key, $result, 'typee_builder', HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Lấy số lượng switch tối thiểu theo layout
     */
    private static function get_min_switches_needed($layout) {
        switch ($layout) {
            case '60%': return 64;
            case '65%': return 68;
            case '75%': return 84;
            case 'TKL': return 87;
            case '96%': 
            case '98%': return 100;
            case 'Fullsize': return 108;
            default: return 110;
        }
    }
}
// Load Core Logic Rule Engine (Thủy)
require_once plugin_dir_path( __FILE__ ) . 'includes/class-typee-rule-engine.php';

// Load REST API & WooCommerce Hooks (Lele - mở comment khi Lele tạo file xong)
// require_once plugin_dir_path( __FILE__ ) . 'includes/class-builder-api.php';