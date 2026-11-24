<?php
/**
 * Class TYPEE_Rule_Engine
 * Xử lý logic kiểm tra tương thích giữa Kit, Switch và Keycap cho Custom Builder
 */
class TYPEE_Rule_Engine {

    /**
     * Kiểm tra tính hợp lệ của cấu hình Custom Builder
     *
     * @param int $kit_id
     * @param int $switch_id
     * @param int $keycap_id
     * @param int $switch_pack_qty Số lượng gói switch khách chọn
     * @param array $extra_params Các tham số bổ sung (switch_variation_id, switches_per_pack, v.v.)
     * @return array
     */
    public static function validate_configuration($kit_id, $switch_id, $keycap_id, $switch_pack_qty = 0, $extra_params = []) {
        $is_valid = true;
        $errors = [];
        $warnings = [];
        $total_price = 0;

        $switch_variation_id = isset($extra_params['switch_variation_id']) ? intval($extra_params['switch_variation_id']) : 0;
        $switches_per_pack_param = isset($extra_params['switches_per_pack']) ? intval($extra_params['switches_per_pack']) : 0;

        // 1. Tạo Cache Key
        $cache_key = md5("typee_builder_{$kit_id}_{$switch_id}_{$keycap_id}_{$switch_pack_qty}_{$switch_variation_id}_{$switches_per_pack_param}");
        
        // 2. Đọc từ Cache (Redis/WP Object Cache nếu có)
        $cached_result = wp_cache_get($cache_key, 'typee_builder');
        if (false !== $cached_result) {
            return $cached_result;
        }

        // Lấy thông tin sản phẩm từ WooCommerce
        $kit_product    = wc_get_product($kit_id);
        $switch_product = wc_get_product($switch_id);
        $keycap_product = wc_get_product($keycap_id);

        if (!$kit_product) {
            return [
                'is_valid'    => false,
                'total_price' => 0,
                'errors'      => ['Vui lòng chọn Kit bàn phím.'],
                'warnings'    => []
            ];
        }

        // Nếu có truyền variation riêng cho switch, ưu tiên lấy product variation
        $actual_switch_product = $switch_product;
        if ($switch_variation_id > 0) {
            $switch_var_obj = wc_get_product($switch_variation_id);
            if ($switch_var_obj) {
                $actual_switch_product = $switch_var_obj;
            }
        }

        // Cập nhật tổng giá
        $total_price += (float) $kit_product->get_price();
        if ($actual_switch_product) {
            $total_price += ((float) $actual_switch_product->get_price() * $switch_pack_qty);
        }
        if ($keycap_product) {
            $total_price += (float) $keycap_product->get_price();
        }

        // 3. Lấy thông tin thuộc tính (hỗ trợ cả WooCommerce Attribute, Postmeta cục bộ và Taxonomy)
        $kit_layout = self::get_attribute_value($kit_product, ['layout', 'attribute_layout', 'pa_layout']);
        $kit_pcb    = self::get_attribute_value($kit_product, ['pcb', 'attribute_pcb', 'tuy-chon-pcb', 'pa_pcb']);

        // --- RULE 1: KIỂM TRA CHÂN PIN CỦA SWITCH VÀ MẠCH KIT ---
        if ($actual_switch_product) {
            $switch_pin = self::get_attribute_value($actual_switch_product, ['pin', 'attribute_pin', 'so-chan-pin', 'attribute_so-chan-pin', 'pa_pin']);

            // Kiểm tra trường hợp mạch Kit là 3-pin nhưng Switch là 5-pin
            $kit_is_3pin    = (stripos($kit_pcb, '3-pin') !== false || stripos($kit_pcb, '3 pin') !== false);
            $switch_is_5pin = (stripos($switch_pin, '5-pin') !== false || stripos($switch_pin, '5 pin') !== false);
            $switch_is_3pin = (stripos($switch_pin, '3-pin') !== false || stripos($switch_pin, '3 pin') !== false);

            if ($kit_is_3pin && $switch_is_5pin && !$switch_is_3pin) {
                $is_valid = false;
                $errors[] = "Kit mạch 3-pin không cắm vừa Switch 5-pin. Vui lòng chọn Switch 3-pin hoặc Kit hỗ trợ mạch 5-pin.";
            }
        }

        // --- RULE 2: KIỂM TRA SỐ LƯỢNG SWITCH (HỖ TRỢ GÓI 10 / 35 / 70 / 90) ---
        $switches_per_pack = 35;
        $total_switches = 0;
        $min_switches_needed = 0;

        if ($actual_switch_product && $kit_layout) {
            $min_switches_needed = self::get_min_switches_needed($kit_layout);
            $switches_per_pack   = self::get_switches_per_pack($actual_switch_product, $extra_params);
            $total_switches      = $switch_pack_qty * $switches_per_pack;

            if ($total_switches < $min_switches_needed) {
                $is_valid = false;
                $errors[] = "Số lượng switch không đủ. Layout {$kit_layout} cần tối thiểu {$min_switches_needed} switch (Hiện có {$total_switches} switch: {$switch_pack_qty} gói x {$switches_per_pack} switch).";
            }
        }

        // --- RULE 3: KIỂM TRA LAYOUT KEYCAP ---
        if ($keycap_product && $kit_layout) {
            $keycap_layout = self::get_attribute_value($keycap_product, ['supported_layout', 'attribute_supported_layout', 'supported-layout', 'pa_supported_layout']);

            $hard_layouts = ['65%', '75%', '96%', '98%'];
            // Nếu là layout đặc thù và keycap chỉ ghi chuẩn 104 phím (không phải Full Kit 130+ keys)
            if (in_array($kit_layout, $hard_layouts) && stripos($keycap_layout, '104') !== false && stripos($keycap_layout, '130') === false && stripos($keycap_layout, 'Full') === false) {
                $warnings[] = "Cảnh báo: Bộ keycap này có thể thiếu nút Shift ngắn 1.75u hoặc phím Alt/Fn 1u cho layout {$kit_layout}.";
            }
        }

        // 4. Chuẩn bị kết quả trả về
        $result = [
            'is_valid'    => $is_valid,
            'total_price' => $total_price,
            'errors'      => $errors,
            'warnings'    => $warnings,
            'details'     => [
                'kit_layout'          => $kit_layout,
                'kit_pcb'             => $kit_pcb,
                'switches_per_pack'   => $switches_per_pack,
                'total_switches'      => $total_switches,
                'min_switches_needed' => $min_switches_needed
            ]
        ];

        // 5. Lưu vào Cache (Cache 1 giờ)
        wp_cache_set($cache_key, $result, 'typee_builder', HOUR_IN_SECONDS);

        return $result;
    }

    /**
     * Lấy giá trị thuộc tính linh hoạt (hỗ trợ WC Attributes, Postmeta trực tiếp và Taxonomy)
     *
     * @param WC_Product|int $product_or_id
     * @param array|string $attr_names Danh sách các key có thể có
     * @return string
     */
    public static function get_attribute_value($product_or_id, $attr_names) {
        if (empty($product_or_id)) {
            return '';
        }

        $product = is_numeric($product_or_id) ? wc_get_product($product_or_id) : $product_or_id;
        if (!$product) {
            return '';
        }

        $product_id = $product->get_id();
        $attr_names = (array) $attr_names;

        // 1. Thử lấy qua WooCommerce get_attribute()
        foreach ($attr_names as $attr) {
            $clean_slug = str_replace(['attribute_', 'pa_'], '', $attr);
            $val = $product->get_attribute($clean_slug);
            if (!empty($val)) {
                return trim($val);
            }
        }

        // 2. Thử lấy qua postmeta trực tiếp (bảng wp_postmeta)
        foreach ($attr_names as $attr) {
            $meta_val = get_post_meta($product_id, $attr, true);
            if (!empty($meta_val)) {
                return is_array($meta_val) ? implode(', ', $meta_val) : trim($meta_val);
            }

            if (strpos($attr, 'attribute_') !== 0) {
                $meta_val = get_post_meta($product_id, 'attribute_' . $attr, true);
                if (!empty($meta_val)) {
                    return is_array($meta_val) ? implode(', ', $meta_val) : trim($meta_val);
                }
            }
        }

        // 3. Thử lấy qua taxonomy terms (bảng wp_term_taxonomy nếu có)
        foreach ($attr_names as $attr) {
            $tax = (strpos($attr, 'pa_') === 0) ? $attr : 'pa_' . str_replace(['attribute_', '-'], ['', '_'], $attr);
            if (taxonomy_exists($tax)) {
                $terms = wp_get_post_terms($product_id, $tax, ['fields' => 'names']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    return trim($terms[0]);
                }
            }
        }

        // 4. Nếu đây là sản phẩm con (variation), fallback lên sản phẩm cha (parent product)
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                return self::get_attribute_value($parent_id, $attr_names);
            }
        }

        return '';
    }

    /**
     * Xác định số lượng switch trong 1 gói (hỗ trợ gói 10, 35, 70, 90)
     *
     * @param WC_Product $switch_product
     * @param array $extra_params
     * @return int
     */
    public static function get_switches_per_pack($switch_product, $extra_params = []) {
        // 1. Nếu caller truyền trực tiếp switches_per_pack
        if (!empty($extra_params['switches_per_pack'])) {
            return intval($extra_params['switches_per_pack']);
        }

        // 2. Đọc từ thuộc tính số lượng (attribute_so-luong / so-luong)
        $qty_attr = self::get_attribute_value($switch_product, ['so-luong', 'attribute_so-luong', 'quantity', 'so_luong']);
        if (!empty($qty_attr) && preg_match('/(\d+)/', $qty_attr, $matches)) {
            return intval($matches[1]);
        }

        // 3. Đọc từ tên sản phẩm / biến thể (ví dụ: "... - 70 Switch")
        $product_name = $switch_product->get_name();
        if (preg_match('/(\d+)\s*(?:switch|phím|gói|pack)/i', $product_name, $matches)) {
            return intval($matches[1]);
        }

        // 4. Nếu là sản phẩm cha variable, đọc từ default attributes
        if ($switch_product->is_type('variable')) {
            $default_attributes = $switch_product->get_default_attributes();
            if (!empty($default_attributes['so-luong']) && preg_match('/(\d+)/', $default_attributes['so-luong'], $matches)) {
                return intval($matches[1]);
            }
        }

        // Mặc định là 35 switch/gói
        return 35;
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
