<?php
/**
 * Class TYPEE_Compare_API
 *
 * REST API & Data Normalization Engine cho trang So Sánh Linh Kiện & Bàn Phím Cơ (4 Cột Chuẩn EAV)
 * Thiết kế tham chiếu: Figma frame 82:2526.
 *
 * Endpoints:
 *  - GET /wp-json/typee/v1/compare?ids=1,2,3,4
 *  - GET /wp-json/typee/v1/compare/products?q=...&category=...&limit=16
 *
 * Chỉ đọc dữ liệu thật từ WooCommerce + ACF postmeta (`attribute_*`) + post_content,
 * kết hợp Redis/WP Object Cache. Tuyệt đối không ghi/sửa dữ liệu DB.
 *
 * @package typee-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TYPEE_Compare_API {

	/**
	 * Số lượng sản phẩm tối đa được phép đối chiếu cùng lúc.
	 */
	const MAX_SLOTS = 4;

	/**
	 * Cache group cho Object Cache / Redis.
	 */
	const CACHE_GROUP = 'typee_compare';

	/**
	 * Đăng ký hooks cho REST API và xóa cache khi sản phẩm thay đổi.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'bump_cache_version' ) );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'bump_cache_version' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'maybe_bump_on_delete' ) );
	}

	/**
	 * Tăng version cache khi sản phẩm cập nhật.
	 */
	public static function bump_cache_version() {
		$ver = (int) wp_cache_get( 'ver', self::CACHE_GROUP );
		wp_cache_set( 'ver', $ver + 1, self::CACHE_GROUP );
	}

	/**
	 * Tăng version cache nếu xóa post thuộc loại product.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function maybe_bump_on_delete( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::bump_cache_version();
		}
	}

	/**
	 * Lấy số phiên bản cache hiện tại (kết hợp cả group 'typee' của theme nếu có).
	 *
	 * @return string
	 */
	private static function cache_version() {
		$v1 = (int) wp_cache_get( 'ver', self::CACHE_GROUP );
		$v2 = (int) wp_cache_get( 'ver', 'typee' );
		return $v1 . '_' . $v2;
	}

	/**
	 * Đăng ký REST API endpoints dưới namespace `typee/v1`.
	 */
	public static function register_routes() {
		register_rest_route(
			'typee/v1',
			'/compare',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_compare_matrix' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'description'       => 'Danh sách ID sản phẩm phân cách bởi dấu phẩy (tối đa 4 ID).',
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'typee/v1',
			'/compare/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_search_products' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'        => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_key',
					),
					'limit'    => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 16,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Xử lý GET /wp-json/typee/v1/compare?ids=...
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_compare_matrix( WP_REST_Request $request ) {
		try {
			if ( ! function_exists( 'wc_get_product' ) ) {
				return new WP_Error(
					'woocommerce_unavailable',
					'Hệ thống WooCommerce chưa sẵn sàng.',
					array( 'status' => 503 )
				);
			}

			$raw_ids = $request->get_param( 'ids' );
			if ( is_array( $raw_ids ) ) {
				$tokens = $raw_ids;
			} else {
				$raw_str = trim( (string) $raw_ids );
				$tokens  = '' === $raw_str ? array() : explode( ',', $raw_str );
			}

			if ( empty( $tokens ) ) {
				return rest_ensure_response(
					array(
						'success' => true,
						'data'    => array(
							'products'   => array(),
							'groups'     => array(),
							'diff_count' => 0,
							'max_slots'  => self::MAX_SLOTS,
						),
					)
				);
			}

			$clean_ids = array();
			foreach ( $tokens as $token ) {
				$token = trim( (string) $token );
				if ( '' === $token ) {
					continue;
				}
				if ( ! ctype_digit( $token ) || (int) $token <= 0 ) {
					return new WP_Error(
						'invalid_product_id',
						'Tham số ids chứa mã sản phẩm không hợp lệ.',
						array( 'status' => 400 )
					);
				}
				$id = (int) $token;
				if ( ! in_array( $id, $clean_ids, true ) ) {
					$clean_ids[] = $id;
				}
			}

			if ( count( $clean_ids ) > self::MAX_SLOTS ) {
				return new WP_Error(
					'max_slots_exceeded',
					sprintf( 'Chỉ hỗ trợ so sánh tối đa %d sản phẩm cùng lúc.', self::MAX_SLOTS ),
					array( 'status' => 400 )
				);
			}

			$payload = self::get_comparison_data( $clean_ids );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $payload,
				)
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'compare_internal_error',
				'Đã xảy ra lỗi khi tải bảng đối chiếu thông số: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Xử lý GET /wp-json/typee/v1/compare/products (Modal chọn nhanh sản phẩm).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_search_products( WP_REST_Request $request ) {
		try {
			if ( ! function_exists( 'wc_get_products' ) ) {
				return new WP_Error(
					'woocommerce_unavailable',
					'Hệ thống WooCommerce chưa sẵn sàng.',
					array( 'status' => 503 )
				);
			}

			$q        = trim( (string) $request->get_param( 'q' ) );
			$category = sanitize_key( (string) $request->get_param( 'category' ) );
			$limit    = min( 40, max( 1, (int) $request->get_param( 'limit' ) ) );

			$allowed_cats = array( 'all', 'pre-built', 'kit', 'switch', 'keycap' );
			if ( ! in_array( $category, $allowed_cats, true ) ) {
				return new WP_Error(
					'invalid_category',
					'Danh mục lọc không hợp lệ.',
					array( 'status' => 400 )
				);
			}

			$cache_key = 'search_' . md5( $q . '|' . $category . '|' . $limit . '|' . self::cache_version() );
			$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return rest_ensure_response(
					array(
						'success' => true,
						'data'    => $cached,
					)
				);
			}

			$ids = self::query_product_ids( $q, $category, $limit );
			if ( $ids && function_exists( '_prime_post_caches' ) ) {
				_prime_post_caches( $ids, true, true );
			}

			$items = array();
			foreach ( $ids as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product || ! $product->is_visible() ) {
					continue;
				}
				$items[] = self::format_picker_item( $product );
			}

			$result = array(
				'items' => $items,
				'total' => count( $items ),
			);

			wp_cache_set( $cache_key, $result, self::CACHE_GROUP, 10 * MINUTE_IN_SECONDS );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $result,
				)
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'compare_search_error',
				'Không thể tìm kiếm danh sách linh kiện: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Truy vấn danh sách ID sản phẩm theo từ khóa tên / mã SKU và danh mục.
	 *
	 * @param string $q        Từ khóa.
	 * @param string $category Slug danh mục ('all' hoặc slug cụ thể).
	 * @param int    $limit    Số lượng tối đa.
	 * @return int[]
	 */
	private static function query_product_ids( $q, $category, $limit ) {
		global $wpdb;

		$tax_query = array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		);

		if ( 'all' !== $category && '' !== $category ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array( $category ),
			);
		}

		if ( '' === $q ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'orderby'        => array(
						'menu_order' => 'ASC',
						'date'       => 'DESC',
					),
					'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			return array_map( 'intval', (array) $query->posts );
		}

		// Tìm đồng thời theo tiêu đề sản phẩm và mã _sku trong wp_postmeta.
		$like     = '%' . $wpdb->esc_like( $q ) . '%';
		$sku_pids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku'
				 WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value LIKE %s
				 LIMIT %d",
				$like,
				$limit
			)
		);

		$title_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				's'              => $q,
				'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);

		$merged = array_values(
			array_unique(
				array_merge(
					array_map( 'intval', (array) $sku_pids ),
					array_map( 'intval', (array) $title_query->posts )
				)
			)
		);

		if ( 'all' !== $category && ! empty( $merged ) ) {
			$filtered = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'post__in'       => $merged,
					'orderby'        => 'post__in',
					'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				)
			);
			return array_map( 'intval', (array) $filtered->posts );
		}

		return array_slice( $merged, 0, $limit );
	}

	/**
	 * Tạo dữ liệu đối chiếu đầy đủ cho mảng ID sản phẩm (dùng chung cho REST API và SSR).
	 *
	 * @param int[] $ids Danh sách ID sản phẩm.
	 * @return array
	 */
	public static function get_comparison_data( array $ids ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, self::MAX_SLOTS );
		if ( empty( $ids ) ) {
			return array(
				'products'   => array(),
				'groups'     => array(),
				'diff_count' => 0,
				'max_slots'  => self::MAX_SLOTS,
			);
		}

		$cache_key = 'matrix_' . implode( '_', $ids ) . '_v' . self::cache_version();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, true, true );
		}

		$products     = array();
		$product_objs = array();
		$seen_parents = array();

		foreach ( $ids as $raw_id ) {
			$p = wc_get_product( $raw_id );
			if ( ! $p ) {
				continue;
			}
			// Nếu truyền ID biến thể thì lấy sản phẩm cha để đọc đủ thông số EAV ACF.
			if ( $p->is_type( 'variation' ) && $p->get_parent_id() ) {
				$parent = wc_get_product( $p->get_parent_id() );
				if ( $parent ) {
					$p = $parent;
				}
			}
			$pid = (int) $p->get_id();
			if ( 'publish' !== $p->get_status() || isset( $seen_parents[ $pid ] ) ) {
				continue;
			}
			$seen_parents[ $pid ] = true;
			$product_objs[]       = $p;
			$products[]           = self::format_product_card( $p );
		}

		list( $groups, $diff_count ) = self::build_eav_groups( $product_objs );

		$data = array(
			'products'   => $products,
			'groups'     => $groups,
			'diff_count' => $diff_count,
			'max_slots'  => self::MAX_SLOTS,
		);

		wp_cache_set( $cache_key, $data, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Chuẩn hóa dữ liệu gọn cho mỗi sản phẩm trong Modal chọn nhanh.
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @return array
	 */
	private static function format_picker_item( WC_Product $product ) {
		$cat_info  = self::resolve_category_info( $product );
		$price_obj = self::resolve_price_info( $product, $cat_info['slug'] );
		$img_id    = $product->get_image_id();
		$img_url   = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );

		$chips = array();
		foreach ( array( 'attribute_layout', 'attribute_mount', 'attribute_pcb', 'attribute_connection', 'attribute_switch_type', 'attribute_pin', 'attribute_profile', 'attribute_material' ) as $mk ) {
			$v = self::get_eav_meta( $product, array( $mk ) );
			if ( '' !== $v ) {
				$chips[] = $v;
			}
			if ( count( $chips ) >= 3 ) {
				break;
			}
		}

		return array(
			'id'              => (int) $product->get_id(),
			'name'            => $product->get_name(),
			'sku'             => (string) $product->get_sku(),
			'url'             => $product->get_permalink(),
			'image'           => (string) $img_url,
			'category_slug'   => $cat_info['slug'],
			'category_badge'  => $cat_info['badge'],
			'price_formatted' => $price_obj['formatted'],
			'in_stock'        => (bool) $product->is_in_stock(),
			'specs_summary'   => $chips,
		);
	}

	/**
	 * Chuẩn hóa dữ liệu thẻ sản phẩm ở Sticky Header của bảng đối chiếu (Figma 82:2586).
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @return array
	 */
	private static function format_product_card( WC_Product $product ) {
		$id        = (int) $product->get_id();
		$cat_info  = self::resolve_category_info( $product );
		$price_obj = self::resolve_price_info( $product, $cat_info['slug'] );
		$stock_obj = self::resolve_stock_info( $product );
		$cart_obj  = self::resolve_cart_info( $product );
		$img_id    = $product->get_image_id();
		$img_url   = $img_id ? wp_get_attachment_image_url( $img_id, 'medium_large' ) : '';
		if ( ! $img_url ) {
			$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
		}

		// Badge trạng thái nổi bật phía trái (Figma #82:2592 / #82:2617 / #82:2642).
		$discount = $price_obj['discount_percent'];
		if ( $discount > 0 ) {
			$highlight = array(
				'text' => sprintf( 'GIẢM %d%%', $discount ),
				'tone' => 'peach',
			);
		} elseif ( (int) $product->get_total_sales() >= 3 || $product->is_featured() ) {
			$highlight = array(
				'text' => 'ĐANG BÁN CHẠY',
				'tone' => 'peach',
			);
		} elseif ( 'kit' === $cat_info['slug'] ) {
			$highlight = array(
				'text' => 'ENTHUSIAST KIT',
				'tone' => 'neutral',
			);
		} elseif ( 'pre-built' === $cat_info['slug'] ) {
			$highlight = array(
				'text' => 'SẴN SÀNG GÕ',
				'tone' => 'peach',
			);
		} else {
			$highlight = array(
				'text' => mb_strtoupper( $cat_info['badge'], 'UTF-8' ),
				'tone' => 'neutral',
			);
		}

		// Chip phụ phía phải (layout hoặc phân loại - Figma #82:2594).
		$layout     = self::get_eav_meta( $product, array( 'attribute_layout', 'layout' ) );
		$sub_badge  = $cat_info['badge'];
		if ( 'kit' === $cat_info['slug'] && $layout ) {
			$sub_badge = 'Kit ' . $layout;
		} elseif ( 'switch' === $cat_info['slug'] ) {
			$sw_type   = self::get_eav_meta( $product, array( 'attribute_switch_type' ) );
			$sub_badge = $sw_type ? $sw_type . ' Switch' : 'Switch';
		} elseif ( 'keycap' === $cat_info['slug'] ) {
			$prof      = self::get_eav_meta( $product, array( 'attribute_profile' ) );
			$sub_badge = $prof ? $prof . ' Profile' : 'Keycap';
		}

		return array(
			'id'                      => $id,
			'name'                    => $product->get_name(),
			'sku'                     => (string) $product->get_sku(),
			'url'                     => $product->get_permalink(),
			'image'                   => (string) $img_url,
			'category_slug'           => $cat_info['slug'],
			'category_badge'          => $sub_badge,
			'highlight_badge'         => $highlight,
			'price_current'           => $price_obj['current'],
			'price_formatted'         => $price_obj['formatted'],
			'price_regular'           => $price_obj['regular'],
			'price_regular_formatted' => $price_obj['regular_formatted'],
			'price_note'              => $price_obj['note'],
			'in_stock'                => $stock_obj['in_stock'],
			'stock_text'              => $stock_obj['text'],
			'stock_state'             => $stock_obj['state'],
			'cart'                    => $cart_obj,
		);
	}

	/**
	 * Xác định danh mục chính của sản phẩm (pre-built / kit / switch / keycap).
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @return array{slug:string,badge:string,type_label:string}
	 */
	private static function resolve_category_info( WC_Product $product ) {
		$pid   = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$terms = wp_get_post_terms( $pid, 'product_cat' );
		$map   = array(
			'pre-built' => array(
				'badge'      => 'Pre-built',
				'type_label' => 'Pre-built (Đủ Switch + Keycap)',
			),
			'kit'       => array(
				'badge'      => 'Barebone Kit',
				'type_label' => 'Kit Barebone (Tự build)',
			),
			'switch'    => array(
				'badge'      => 'Switch',
				'type_label' => 'Switch cơ học rời',
			),
			'keycap'    => array(
				'badge'      => 'Keycap',
				'type_label' => 'Bộ Keycap rời',
			),
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( isset( $map[ $term->slug ] ) ) {
					return array(
						'slug'       => $term->slug,
						'badge'      => $map[ $term->slug ]['badge'],
						'type_label' => $map[ $term->slug ]['type_label'],
					);
				}
			}
			$first = reset( $terms );
			return array(
				'slug'       => $first->slug,
				'badge'      => $first->name,
				'type_label' => $first->name,
			);
		}

		return array(
			'slug'       => 'other',
			'badge'      => 'Linh kiện',
			'type_label' => 'Linh kiện bàn phím cơ',
		);
	}

	/**
	 * Định dạng tiền tệ chuẩn WooCommerce sang chuỗi văn bản thuần.
	 *
	 * @param float $amount Số tiền.
	 * @return string
	 */
	private static function money_plain( $amount ) {
		if ( $amount <= 0 ) {
			return '';
		}
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
		}
		return number_format_i18n( (float) $amount ) . '₫';
	}

	/**
	 * Tính giá hiển thị, giá gốc gạch ngang, % giảm và ghi chú phụ (VD: "Kit chưa switch").
	 *
	 * @param WC_Product $product  Sản phẩm.
	 * @param string     $cat_slug Slug danh mục.
	 * @return array
	 */
	private static function resolve_price_info( WC_Product $product, $cat_slug ) {
		$price_now     = 0.0;
		$price_regular = 0.0;
		$prefix        = '';
		$discount      = 0;

		if ( $product->is_type( 'variable' ) ) {
			$price_now = (float) $product->get_variation_price( 'min', true );
			$max_price = (float) $product->get_variation_price( 'max', true );
			if ( $max_price > $price_now && $price_now > 0 ) {
				$prefix = 'Từ ';
			}
			if ( $product->is_on_sale() ) {
				$reg_min = (float) $product->get_variation_regular_price( 'min', true );
				if ( $reg_min > $price_now ) {
					$price_regular = $reg_min;
					$discount      = (int) round( ( 1 - $price_now / $reg_min ) * 100 );
				}
			}
		} else {
			$price_now = (float) wc_get_price_to_display( $product );
			if ( $product->is_on_sale() ) {
				$reg = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
				if ( $reg > $price_now && $price_now > 0 ) {
					$price_regular = $reg;
					$discount      = (int) round( ( 1 - $price_now / $reg ) * 100 );
				}
			}
		}

		$note = '';
		if ( $price_regular <= 0 ) {
			if ( 'kit' === $cat_slug ) {
				$note = 'Kit chưa switch';
			} elseif ( 'switch' === $cat_slug ) {
				$note = 'Giá theo gói';
			} elseif ( 'keycap' === $cat_slug ) {
				$note = 'Bộ nút phím';
			}
		}

		return array(
			'current'           => $price_now,
			'formatted'         => $price_now > 0 ? $prefix . self::money_plain( $price_now ) : 'Liên hệ',
			'regular'           => $price_regular,
			'regular_formatted' => $price_regular > 0 ? self::money_plain( $price_regular ) : '',
			'discount_percent'  => $discount,
			'note'              => $note,
		);
	}

	/**
	 * Thông tin tồn kho thực tế.
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @return array
	 */
	private static function resolve_stock_info( WC_Product $product ) {
		$status = $product->get_stock_status();
		if ( 'outofstock' === $status || ! $product->is_in_stock() ) {
			return array(
				'in_stock' => false,
				'text'     => 'Hết hàng',
				'state'    => 'out',
			);
		}
		if ( 'onbackorder' === $status ) {
			return array(
				'in_stock' => true,
				'text'     => 'Đặt trước (Pre-order)',
				'state'    => 'backorder',
			);
		}
		if ( $product->managing_stock() ) {
			$qty = (int) $product->get_stock_quantity();
			if ( $qty > 0 && $qty <= 5 ) {
				return array(
					'in_stock' => true,
					'text'     => sprintf( 'Chỉ còn %d sản phẩm', $qty ),
					'state'    => 'low',
				);
			}
		}
		return array(
			'in_stock' => true,
			'text'     => 'Sẵn hàng tại Studio',
			'state'    => 'in',
		);
	}

	/**
	 * Chuẩn bị thông tin giỏ hàng (hỗ trợ AJAX Add-to-Cart cho cả Simple & Variable Product).
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @return array
	 */
	private static function resolve_cart_info( WC_Product $product ) {
		$id = (int) $product->get_id();

		if ( $product->is_type( 'variable' ) ) {
			$variations  = array();
			$default_var = null;

			foreach ( $product->get_children() as $child_id ) {
				$var = wc_get_product( $child_id );
				if ( ! $var || ! $var->is_purchasable() ) {
					continue;
				}
				$attrs = array();
				$parts = array();
				foreach ( $var->get_variation_attributes( true ) as $attr_key => $attr_val ) {
					if ( '' === $attr_val ) {
						continue;
					}
					$attrs[ $attr_key ] = $attr_val;
					$parts[]            = $attr_val;
				}
				$v_in_stock = $var->is_in_stock();
				$v_price    = (float) wc_get_price_to_display( $var );
				$entry      = array(
					'id'              => (int) $var->get_id(),
					'label'           => $parts ? implode( ' / ', $parts ) : $var->get_name(),
					'price'           => $v_price,
					'price_formatted' => self::money_plain( $v_price ),
					'in_stock'        => $v_in_stock,
					'attrs'           => $attrs,
				);
				$variations[] = $entry;
				if ( null === $default_var && $v_in_stock ) {
					$default_var = $entry;
				}
			}

			if ( null === $default_var && ! empty( $variations ) ) {
				$default_var = $variations[0];
			}

			if ( $default_var && $default_var['in_stock'] ) {
				return array(
					'can_ajax'     => true,
					'is_variable'  => true,
					'product_id'   => $default_var['id'],
					'parent_id'    => $id,
					'default_label'=> $default_var['label'],
					'variations'   => $variations,
					'button_text'  => 'Thêm vào giỏ hàng',
					'disabled'     => false,
					'fallback_url' => $product->get_permalink(),
				);
			}

			return array(
				'can_ajax'     => false,
				'is_variable'  => true,
				'product_id'   => $id,
				'parent_id'    => $id,
				'default_label'=> '',
				'variations'   => $variations,
				'button_text'  => 'Hết hàng',
				'disabled'     => true,
				'fallback_url' => $product->get_permalink(),
			);
		}

		$purchasable = $product->is_purchasable() && $product->is_in_stock();
		return array(
			'can_ajax'     => $purchasable,
			'is_variable'  => false,
			'product_id'   => $id,
			'parent_id'    => $id,
			'default_label'=> '',
			'variations'   => array(),
			'button_text'  => $purchasable ? 'Thêm vào giỏ hàng' : 'Hết hàng',
			'disabled'     => ! $purchasable,
			'fallback_url' => $product->get_permalink(),
		);
	}

	/**
	 * Đọc giá trị thuộc tính EAV từ postmeta / WC Attribute / Taxonomy.
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @param string[]   $keys    Danh sách tên meta hoặc slug thuộc tính.
	 * @return string
	 */
	private static function get_eav_meta( WC_Product $product, array $keys ) {
		if ( class_exists( 'TYPEE_Rule_Engine' ) && method_exists( 'TYPEE_Rule_Engine', 'get_attribute_value' ) ) {
			$val = TYPEE_Rule_Engine::get_attribute_value( $product, $keys );
			if ( '' !== $val ) {
				return $val;
			}
		}

		$pid = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		foreach ( $keys as $k ) {
			$raw = get_post_meta( $pid, $k, true );
			if ( is_array( $raw ) ) {
				$raw = implode( ', ', array_filter( array_map( 'strval', $raw ) ) );
			}
			$raw = trim( (string) $raw );
			if ( '' !== $raw ) {
				return $raw;
			}
		}
		return '';
	}

	/**
	 * Đọc tùy chọn thuộc tính cục bộ WooCommerce (`_product_attributes`) theo từ khóa nhãn/slug.
	 *
	 * @param WC_Product $product  Sản phẩm.
	 * @param string[]   $keywords Danh sách từ khóa cần khớp (không phân biệt hoa thường).
	 * @return string[]
	 */
	private static function get_wc_attribute_options( WC_Product $product, array $keywords ) {
		$attrs = $product->get_attributes();
		if ( empty( $attrs ) ) {
			return array();
		}

		foreach ( $attrs as $slug => $attr ) {
			if ( ! is_object( $attr ) ) {
				continue;
			}
			$label    = wc_attribute_label( $attr->get_name(), $product );
			$haystack = mb_strtolower( $slug . ' ' . $attr->get_name() . ' ' . $label, 'UTF-8' );

			foreach ( $keywords as $kw ) {
				if ( false !== mb_strpos( $haystack, mb_strtolower( $kw, 'UTF-8' ), 0, 'UTF-8' ) ) {
					if ( $attr->is_taxonomy() ) {
						$terms = wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
						return is_wp_error( $terms ) ? array() : array_values( array_filter( array_map( 'trim', $terms ) ) );
					}
					$opts = $attr->get_options();
					return array_values( array_filter( array_map( 'trim', (array) $opts ) ) );
				}
			}
		}
		return array();
	}

	/**
	 * Bóc tách thông số kỹ thuật từ nội dung mô tả HTML thực tế trong `wp_posts.post_content`
	 * (dữ liệu sản phẩm TYPEE có các dòng `▪️Nhãn: Giá trị`, `<li><strong>Nhãn:</strong> Giá trị</li>`, hoặc bảng `<tr><th>Nhãn</th><td>Giá trị</td></tr>`).
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @return array<string,string> Bản đồ nhãn chữ thường → giá trị sạch.
	 */
	private static function parse_description_specs( WC_Product $product ) {
		static $memo = array();
		$pid = (int) $product->get_id();
		if ( isset( $memo[ $pid ] ) ) {
			return $memo[ $pid ];
		}

		$raw_html = (string) $product->get_description() . "\n" . (string) $product->get_short_description();
		$pairs    = array();

		// 1. Bóc tách từ bảng <tr><th>...</th><td>...</td></tr> (dùng nhiều ở Keycap).
		if ( preg_match_all( '#<tr[^>]*>\s*<(?:th|td)[^>]*>(.*?)</(?:th|td)>\s*<td[^>]*>(.*?)</td>\s*</tr>#isu', $raw_html, $tr_matches, PREG_SET_ORDER ) ) {
			foreach ( $tr_matches as $m ) {
				$k = mb_strtolower( trim( wp_strip_all_tags( $m[1] ) ), 'UTF-8' );
				$v = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $m[2] ) ) );
				$k = rtrim( $k, ':： ' );
				if ( '' !== $k && '' !== $v && mb_strlen( $k, 'UTF-8' ) <= 50 ) {
					$pairs[ $k ] = $v;
				}
			}
		}

		// 2. Chuyển các thẻ ngắt dòng và ký hiệu bullet `▪️` thành dòng văn bản để quét `Nhãn: Giá trị`.
		$normalized = preg_replace( '#</?(?:li|p|br|div|ul|ol|h[1-6])[^>]*>#iu', "\n", $raw_html );
		$normalized = str_replace( array( '▪️', '▪', '•', '●', '💥', '💎', '🖇', '📌', '🔥' ), "\n", $normalized );
		$plain      = html_entity_decode( wp_strip_all_tags( $normalized ), ENT_QUOTES, 'UTF-8' );

		foreach ( preg_split( '/\r\n|\r|\n/u', $plain ) as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( '' === $line || mb_strlen( $line, 'UTF-8' ) > 200 ) {
				continue;
			}
			if ( preg_match( '/^([^:：]{2,42})\s*[:：]\s*(.+)$/u', $line, $m ) ) {
				$k = mb_strtolower( trim( $m[1] ), 'UTF-8' );
				$v = trim( $m[2] );
				// Loại bỏ URL nếu có dính trong dòng mô tả.
				$v = trim( preg_replace( '#https?://\S+#i', '', $v ) );
				$v = rtrim( $v, ' -–—:;' );
				if ( '' !== $k && '' !== $v && ! isset( $pairs[ $k ] ) ) {
					$pairs[ $k ] = $v;
				}
			}
		}

		$pairs['__full_plain'] = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $plain ) ), 'UTF-8' );
		$memo[ $pid ]          = $pairs;
		return $pairs;
	}

	/**
	 * Tra cứu một giá trị đã bóc tách từ mô tả theo danh sách từ khóa nhãn.
	 *
	 * @param array    $parsed   Kết quả từ parse_description_specs().
	 * @param string[] $keywords Danh sách từ khóa.
	 * @return string
	 */
	private static function find_parsed_spec( array $parsed, array $keywords ) {
		foreach ( $keywords as $kw ) {
			$kw_low = mb_strtolower( $kw, 'UTF-8' );
			if ( isset( $parsed[ $kw_low ] ) && '' !== $parsed[ $kw_low ] ) {
				return $parsed[ $kw_low ];
			}
			foreach ( $parsed as $k => $v ) {
				if ( '__full_plain' === $k ) {
					continue;
				}
				if ( false !== mb_strpos( $k, $kw_low, 0, 'UTF-8' ) && '' !== $v ) {
					return $v;
				}
			}
		}
		return '';
	}

	/**
	 * Cắt gọn chuỗi thông số để vừa vặn ô ma trận nhưng vẫn giữ nguyên ý nghĩa kỹ thuật.
	 *
	 * @param string $text Văn bản.
	 * @param int    $max  Độ dài tối đa.
	 * @return string
	 */
	private static function clip_spec( $text, $max = 56 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( '' === $text ) {
			return '—';
		}
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, $max - 1, 'UTF-8' ) ) . '…';
	}

	/**
	 * Xây dựng toàn bộ 5 nhóm thông số EAV (Figma 82:2661) và đếm số tiêu chí khác biệt.
	 *
	 * @param WC_Product[] $products Danh sách đối tượng sản phẩm.
	 * @return array{0:array,1:int}
	 */
	private static function build_eav_groups( array $products ) {
		if ( empty( $products ) ) {
			return array( array(), 0 );
		}

		$group_defs = array(
			array(
				'id'    => 'basic',
				'title' => 'NHÓM 1: THÔNG TIN CƠ BẢN & PHÂN LOẠI',
				'icon'  => 'layers',
				'rows'  => array(
					array( 'key' => 'product_type', 'label' => 'Loại sản phẩm', 'sublabel' => 'Phân khúc đóng gói' ),
					array( 'key' => 'layout', 'label' => 'Layout phím', 'sublabel' => 'Số phím & Kích thước' ),
					array( 'key' => 'weight', 'label' => 'Trọng lượng hoàn thiện', 'sublabel' => 'Khối lượng thực tế' ),
					array( 'key' => 'color_options', 'label' => 'Tùy chọn màu vỏ / Phiên bản', 'sublabel' => 'Số phối màu & quy cách hãng cung cấp' ),
				),
			),
			array(
				'id'    => 'mechanical',
				'title' => 'NHÓM 2: CẤU TRÚC CƠ KHÍ & VẬT LIỆU',
				'icon'  => 'handyman',
				'rows'  => array(
					array( 'key' => 'mounting', 'label' => 'Kiểu Mounting', 'sublabel' => 'Cơ chế tiêu âm & độ nhún' ),
					array( 'key' => 'case_material', 'label' => 'Chất liệu vỏ Case / Linh kiện', 'sublabel' => 'Độ hoàn thiện vật liệu' ),
					array( 'key' => 'surface_finish', 'label' => 'Xử lý bề mặt', 'sublabel' => 'Công nghệ phủ màu & ký tự' ),
					array( 'key' => 'weight_bar', 'label' => 'Tạ đáy (Weight Bar)', 'sublabel' => 'Vật liệu & hoàn thiện đối trọng' ),
					array( 'key' => 'typing_angle_dim', 'label' => 'Độ dốc gõ & Kích thước', 'sublabel' => 'Thông số công thái học' ),
				),
			),
			array(
				'id'    => 'pcb_electronics',
				'title' => 'NHÓM 3: MẠCH PCB & ĐIỆN TỬ',
				'icon'  => 'memory',
				'rows'  => array(
					array( 'key' => 'hotswap_socket', 'label' => 'Hot-swap Socket / Loại trục', 'sublabel' => 'Thay switch nóng & phân loại trục' ),
					array( 'key' => 'switch_spec', 'label' => 'Switch & Chân Pin / Lực nhấn', 'sublabel' => 'Tương thích trục cơ học' ),
					array( 'key' => 'plate_profile', 'label' => 'Plate định vị / Profile Keycap', 'sublabel' => 'Độ cứng & biên dạng phím' ),
					array( 'key' => 'rgb_led', 'label' => 'Đèn LED RGB từng phím', 'sublabel' => 'Hiệu ứng ánh sáng nền' ),
					array( 'key' => 'pcb_flex_foam', 'label' => 'Cắt rãnh mạch & Tiêu âm', 'sublabel' => 'Độ dày PCB & hệ thống Foam' ),
				),
			),
			array(
				'id'    => 'connectivity_power',
				'title' => 'NHÓM 4: KẾT NỐI & NĂNG LƯỢNG',
				'icon'  => 'bolt',
				'rows'  => array(
					array( 'key' => 'connection', 'label' => 'Phương thức kết nối', 'sublabel' => 'Khả năng đa thiết bị' ),
					array( 'key' => 'battery', 'label' => 'Dung lượng Pin tích hợp', 'sublabel' => 'Thời lượng hoạt động không dây' ),
					array( 'key' => 'polling_travel', 'label' => 'Tần số quét / Hành trình phím', 'sublabel' => 'Độ trễ tín hiệu & độ nhạy' ),
					array( 'key' => 'port_stock', 'label' => 'Cổng kết nối & Tồn kho', 'sublabel' => 'Chuẩn giao tiếp vật lý & kho Studio' ),
				),
			),
			array(
				'id'    => 'software_warranty',
				'title' => 'NHÓM 5: PHẦN MỀM & DỊCH VỤ BẢO HÀNH',
				'icon'  => 'verified_user',
				'rows'  => array(
					array( 'key' => 'keymap', 'label' => 'Khả năng Remap phím', 'sublabel' => 'Macro & tùy biến phím ảo' ),
					array( 'key' => 'os_layout_compat', 'label' => 'Tương thích HĐH & Layout', 'sublabel' => 'Chuyển đổi Windows / macOS' ),
					array( 'key' => 'warranty', 'label' => 'Bảo hành chính hãng e-RMA', 'sublabel' => 'TYPEE Studio bảo hành điện tử' ),
					array( 'key' => 'lab_service', 'label' => 'Phụ kiện & Dịch vụ Mod Lab', 'sublabel' => 'Đóng gói tiêu chuẩn & căn chỉnh' ),
				),
			),
		);

		$out_groups = array();
		$diff_total = 0;
		$prod_count = count( $products );

		foreach ( $group_defs as $gdef ) {
			$out_rows = array();

			foreach ( $gdef['rows'] as $rdef ) {
				$values         = array();
				$norm_set       = array();
				$has_real_value = false;

				foreach ( $products as $p ) {
					$cell   = self::extract_criterion_cell( $p, $rdef['key'] );
					$main   = isset( $cell['main'] ) && '' !== trim( (string) $cell['main'] ) ? self::clip_spec( $cell['main'], 58 ) : '—';
					$sub    = isset( $cell['sub'] ) && '' !== trim( (string) $cell['sub'] ) ? self::clip_spec( $cell['sub'], 60 ) : '';
					$accent = ! empty( $cell['accent'] );
					$icon   = isset( $cell['icon'] ) ? (string) $cell['icon'] : '';

					if ( '—' !== $main ) {
						$has_real_value = true;
					}

					$norm = mb_strtolower( preg_replace( '/\s+/u', ' ', trim( $main ) ), 'UTF-8' );
					$norm_set[ $norm ] = true;

					$values[] = array(
						'product_id' => (int) $p->get_id(),
						'main'       => $main,
						'sub'        => $sub,
						'accent'     => $accent,
						'icon'       => $icon,
					);
				}

				// Ẩn dòng nếu tất cả sản phẩm đang chọn đều không áp dụng tiêu chí này ('—').
				if ( ! $has_real_value ) {
					continue;
				}

				$is_diff = ( $prod_count >= 2 ) && ( count( $norm_set ) > 1 );
				if ( $is_diff ) {
					++$diff_total;
				}

				$out_rows[] = array(
					'key'      => $rdef['key'],
					'label'    => $rdef['label'],
					'sublabel' => $rdef['sublabel'],
					'is_diff'  => $is_diff,
					'values'   => $values,
				);
			}

			if ( ! empty( $out_rows ) ) {
				$out_groups[] = array(
					'id'              => $gdef['id'],
					'title'           => $gdef['title'],
					'icon'            => $gdef['icon'],
					'criteria_count'  => count( $out_rows ),
					'rows'            => $out_rows,
				);
			}
		}

		return array( $out_groups, $diff_total );
	}

	/**
	 * Trích xuất giá trị của 1 tiêu chí cụ thể cho 1 sản phẩm từ dữ liệu thật trong DB.
	 *
	 * @param WC_Product $product Sản phẩm.
	 * @param string     $key     Mã tiêu chí.
	 * @return array{main:string,sub?:string,accent?:bool,icon?:string}
	 */
	private static function extract_criterion_cell( WC_Product $product, $key ) {
		$cat    = self::resolve_category_info( $product );
		$slug   = $cat['slug'];
		$parsed = self::parse_description_specs( $product );
		$plain  = isset( $parsed['__full_plain'] ) ? $parsed['__full_plain'] : '';

		switch ( $key ) {
			case 'product_type':
				return array(
					'main'   => $cat['type_label'],
					'accent' => ( 'pre-built' === $slug ),
				);

			case 'layout':
				$layout = self::get_eav_meta( $product, array( 'attribute_layout', 'layout' ) );
				if ( '' === $layout && 'keycap' === $slug ) {
					$sup = self::get_eav_meta( $product, array( 'attribute_supported_layout' ) );
					return array(
						'main' => $sup ? $sup : 'ANSI tiêu chuẩn',
						'sub'  => 'Tương thích bộ nút Keycap',
					);
				}
				if ( '' === $layout ) {
					$layout = self::find_parsed_spec( $parsed, array( 'layout', 'số nút', 'số phím' ) );
				}
				if ( '' === $layout ) {
					return array( 'main' => '—' );
				}
				$sub_notes = array(
					'60%'      => 'Nhỏ gọn ~61-64 phím',
					'65%'      => 'Gọn gàng ~67-68 phím có cụm điều hướng',
					'75%'      => 'Nhỏ gọn ~81-84 phím, giữ hàng F-row',
					'TKL'      => 'Chuẩn Tenkeyless ~87 phím không Numpad',
					'96%'      => 'Đầy đủ cụm phím số dạng thu gọn',
					'98%'      => '1800 Compact có cụm Numpad rời nhẹ',
					'Fullsize' => 'Đầy đủ 104-108 phím có Numpad',
					'Alice'    => 'Bố cục công thái học chia hai nửa',
				);
				$sub = isset( $sub_notes[ $layout ] ) ? $sub_notes[ $layout ] : '';
				if ( preg_match( '/(màn hình|lcd|tft|núm xoay|knob)/iu', $plain ) ) {
					$sub = 'Có Màn hình mini / Núm xoay tùy biến';
				}
				return array(
					'main' => $layout,
					'sub'  => $sub,
				);

			case 'weight':
				$w_kg = (float) $product->get_weight();
				if ( $w_kg > 0 ) {
					$grams = (int) round( $w_kg * 1000 );
					if ( $grams >= 1000 ) {
						$label = '~' . number_format_i18n( $grams ) . 'g';
						if ( $grams >= 2250 ) {
							$label .= ' (Rất đầm)';
						} elseif ( $grams >= 1800 ) {
							$label .= ' (Đầm tay)';
						}
					} else {
						$label = $grams . 'g' . ( 'switch' === $slug ? ' / gói' : '' );
					}
					return array( 'main' => $label );
				}
				$parsed_w = self::find_parsed_spec( $parsed, array( 'trọng lượng', 'khối lượng', 'weight' ) );
				if ( '' !== $parsed_w ) {
					return array( 'main' => $parsed_w );
				}
				return array( 'main' => '—' );

			case 'color_options':
				$colors = self::get_wc_attribute_options( $product, array( 'màu sắc', 'mau-sac', 'color', 'phân loại' ) );
				$weights_opt = self::get_wc_attribute_options( $product, array( 'đối trọng', 'doi-trong', 'weight' ) );
				if ( ! empty( $colors ) ) {
					$main = count( $colors ) . ' tùy chọn màu';
					if ( ! empty( $weights_opt ) ) {
						$main .= ' + ' . count( $weights_opt ) . ' màu tạ';
					}
					return array(
						'main' => $main,
						'sub'  => implode( ', ', array_slice( $colors, 0, 4 ) ) . ( count( $colors ) > 4 ? '…' : '' ),
					);
				}
				$packs = self::get_wc_attribute_options( $product, array( 'số lượng', 'so-luong', 'pack' ) );
				if ( ! empty( $packs ) ) {
					return array(
						'main' => implode( ' / ', $packs ),
						'sub'  => 'Quy cách đóng gói',
					);
				}
				$parsed_c = self::find_parsed_spec( $parsed, array( 'màu sắc', 'phối màu' ) );
				return array( 'main' => $parsed_c ? $parsed_c : 'Bản tiêu chuẩn' );

			case 'mounting':
				$mount = self::get_eav_meta( $product, array( 'attribute_mount', 'mount' ) );
				if ( '' === $mount ) {
					$mount = self::find_parsed_spec( $parsed, array( 'mounting', 'cấu trúc gắn', 'kết cấu' ) );
				}
				if ( '' === $mount && 'switch' === $slug ) {
					$pin = self::get_eav_meta( $product, array( 'attribute_pin' ) );
					if ( $pin ) {
						return array(
							'main' => false !== stripos( $pin, '5' ) ? 'PCB Mount (5-pin) / Plate (3-pin)' : 'Plate Mount (3-pin)',
							'sub'  => 'Chuẩn chân gắn Switch: ' . $pin,
						);
					}
				}
				if ( '' === $mount && 'keycap' === $slug ) {
					return array(
						'main' => 'Stem chữ thập MX (+)',
						'sub'  => 'Tương thích Switch cơ học chuẩn MX',
					);
				}
				if ( '' === $mount ) {
					return array( 'main' => '—' );
				}
				$m_sub = '';
				if ( false !== stripos( $mount, 'gasket' ) ) {
					$m_sub = 'Nhún êm, âm gõ trầm ấm';
				} elseif ( false !== stripos( $mount, 'top' ) ) {
					$m_sub = 'Phản hồi gõ chắc tay, đồng đều';
				} elseif ( false !== stripos( $mount, 'leaf' ) ) {
					$m_sub = 'Độ nhún đàn hồi cao cấp';
				} elseif ( false !== stripos( $mount, 'o-ring' ) ) {
					$m_sub = 'Đệm vòng cao su êm ái';
				}
				if ( preg_match( '/(catchball|ball-catch|không cần bắt ốc|không cần vít)/iu', $plain ) ) {
					$m_sub = 'Tháo lắp nhanh không ốc vít';
				}
				return array(
					'main'   => $mount,
					'sub'    => $m_sub,
					'accent' => ( false !== stripos( $mount, 'leaf' ) || false !== stripos( $mount, 'gasket' ) ),
				);

			case 'case_material':
				if ( 'keycap' === $slug ) {
					$mat = self::get_eav_meta( $product, array( 'attribute_material' ) );
					$thk = self::find_parsed_spec( $parsed, array( 'độ dày' ) );
					return array(
						'main' => $mat ? 'Nhựa ' . $mat . ' cao cấp' : self::find_parsed_spec( $parsed, array( 'chất liệu' ) ),
						'sub'  => $thk ? 'Độ dày thành: ' . $thk : '',
					);
				}
				$mat = self::get_eav_meta( $product, array( 'attribute_case_material' ) );
				if ( '' === $mat ) {
					$mat = self::find_parsed_spec( $parsed, array( 'chất liệu vỏ', 'chất liệu' ) );
				}
				if ( '' === $mat && preg_match( '/(nhôm\s*(?:6063)?\s*cnc|nhôm nguyên khối|acrylic|nhựa abs|polycarbonate)/iu', $plain, $mm ) ) {
					$mat = ucfirst( $mm[1] );
				}
				if ( '' === $mat && 'switch' === $slug ) {
					$mat = self::find_parsed_spec( $parsed, array( 'housing', 'trục (stem)', 'trục', 'vỏ' ) );
				}
				if ( '' === $mat && 'pre-built' === $slug ) {
					$kc_mat = self::get_eav_meta( $product, array( 'attribute_keycap_material' ) );
					if ( $kc_mat ) {
						$mat = 'Keycap ' . $kc_mat;
					}
				}
				return array( 'main' => $mat ? $mat : '—' );

			case 'surface_finish':
				$fin = self::get_eav_meta( $product, array( 'attribute_surface_finish' ) );
				if ( '' === $fin ) {
					$fin = self::find_parsed_spec( $parsed, array( 'hoàn thiện bề mặt', 'xử lý bề mặt', 'công nghệ in', 'ký tự' ) );
				}
				if ( '' === $fin ) {
					$tags = array();
					if ( preg_match( '/anod(?:ized|e)?/iu', $plain ) ) {
						$tags[] = 'Anodized';
					}
					if ( preg_match( '/e-?coat(?:ing)?|electrophoresis|điện di/iu', $plain ) ) {
						$tags[] = 'E-Coating';
					}
					if ( preg_match( '/spray-?coat/iu', $plain ) ) {
						$tags[] = 'Spray-coated';
					}
					if ( preg_match( '/double-?shot/iu', $plain ) ) {
						$tags[] = 'Double-shot';
					}
					if ( preg_match( '/dye-?sub/iu', $plain ) ) {
						$tags[] = 'Dye-sub';
					}
					if ( ! empty( $tags ) ) {
						$fin = implode( ' / ', $tags );
					}
				}
				return array( 'main' => $fin ? $fin : '—' );

			case 'weight_bar':
				$w_bar = self::get_eav_meta( $product, array( 'attribute_weight_bar' ) );
				if ( '' === $w_bar ) {
					$w_opts = self::get_wc_attribute_options( $product, array( 'đối trọng', 'doi-trong' ) );
					if ( ! empty( $w_opts ) ) {
						$w_bar = 'Tạ đáy tùy chọn: ' . implode( ', ', $w_opts );
					}
				}
				if ( '' === $w_bar ) {
					$w_bar = self::find_parsed_spec( $parsed, array( 'tạ đối trọng', 'đối trọng', 'weight', 'tạ' ) );
				}
				if ( '' === $w_bar && preg_match( '/(mirror pvd|tạ gương|tạ đồng|tạ hoa văn|pvd)/iu', $plain, $wm ) ) {
					$w_bar = $wm[1];
				}
				if ( '' === $w_bar ) {
					return array( 'main' => '—' );
				}
				$is_premium = (bool) preg_match( '/(pvd|brass|copper|đồng|gương)/iu', $w_bar );
				return array(
					'main'   => $w_bar,
					'accent' => $is_premium,
					'icon'   => $is_premium ? 'verified' : '',
				);

			case 'typing_angle_dim':
				$angle = self::get_eav_meta( $product, array( 'attribute_typing_angle' ) );
				if ( '' === $angle ) {
					$angle = self::find_parsed_spec( $parsed, array( 'góc nghiêng', 'typing angle', 'độ dốc' ) );
				}
				$dims  = array_filter(
					array(
						$product->get_length(),
						$product->get_width(),
						$product->get_height(),
					)
				);
				$dim_str = ! empty( $dims ) ? implode( ' × ', $dims ) . ' cm' : self::find_parsed_spec( $parsed, array( 'kích thước' ) );
				if ( $angle && $dim_str ) {
					return array(
						'main' => $angle,
						'sub'  => 'Kích thước: ' . $dim_str,
					);
				}
				if ( $angle ) {
					return array( 'main' => $angle );
				}
				if ( $dim_str ) {
					return array( 'main' => $dim_str );
				}
				return array( 'main' => '—' );

			case 'hotswap_socket':
				if ( 'switch' === $slug ) {
					$st = self::get_eav_meta( $product, array( 'attribute_switch_type' ) );
					return array(
						'main'   => $st ? 'Switch ' . $st : 'Switch cơ học',
						'accent' => true,
					);
				}
				$pcb = self::get_eav_meta( $product, array( 'attribute_pcb', 'pcb' ) );
				if ( '' === $pcb ) {
					$pcb = self::find_parsed_spec( $parsed, array( 'hotswap', 'pcb', 'bo mạch' ) );
				}
				if ( '' === $pcb && false !== mb_strpos( $plain, 'hotswap', 0, 'UTF-8' ) ) {
					$pcb = 'Hotswap Socket (Thay nóng switch)';
				}
				return array( 'main' => $pcb ? $pcb : '—' );

			case 'switch_spec':
				if ( 'pre-built' === $slug ) {
					$sw_inc = self::get_eav_meta( $product, array( 'attribute_switch_included' ) );
					if ( '' === $sw_inc ) {
						$sw_inc = self::find_parsed_spec( $parsed, array( 'switch' ) );
					}
					return array(
						'main'   => $sw_inc ? $sw_inc : 'Switch lắp sẵn từ hãng',
						'sub'    => 'Đi kèm sẵn trên phím',
						'accent' => ( '' !== $sw_inc ),
					);
				}
				if ( 'kit' === $slug ) {
					$pcb = self::get_eav_meta( $product, array( 'attribute_pcb' ) );
					return array(
						'main' => 'Chưa kèm Switch (Barebone)',
						'sub'  => $pcb ? 'Tương thích mạch ' . $pcb : 'Tự chọn Switch theo gu âm',
					);
				}
				if ( 'switch' === $slug ) {
					$force = self::get_eav_meta( $product, array( 'attribute_bottom_out' ) );
					$pin   = self::get_eav_meta( $product, array( 'attribute_pin' ) );
					$act   = self::find_parsed_spec( $parsed, array( 'lực kích hoạt' ) );
					return array(
						'main'   => $force ? 'Bottom-out ' . $force . ( $pin ? ' · ' . $pin : '' ) : ( $pin ? $pin : '—' ),
						'sub'    => $act ? 'Lực kích hoạt: ' . $act : '',
						'accent' => true,
					);
				}
				if ( 'keycap' === $slug ) {
					return array(
						'main' => 'Tương thích chân Switch (+)',
						'sub'  => 'Cherry MX / Gateron / Kailh / HMX…',
					);
				}
				return array( 'main' => '—' );

			case 'plate_profile':
				if ( 'keycap' === $slug ) {
					$prof = self::get_eav_meta( $product, array( 'attribute_profile' ) );
					return array(
						'main'   => $prof ? $prof . ' Profile' : '—',
						'accent' => ( '' !== $prof ),
					);
				}
				$plate = self::get_eav_meta( $product, array( 'attribute_plate' ) );
				if ( '' === $plate ) {
					$p_opts = self::get_wc_attribute_options( $product, array( 'plate' ) );
					if ( ! empty( $p_opts ) ) {
						$plate = implode( ' / ', $p_opts );
					}
				}
				if ( '' === $plate ) {
					$plate = self::find_parsed_spec( $parsed, array( 'plate', 'tấm định vị' ) );
				}
				$kc_mat = 'pre-built' === $slug ? self::get_eav_meta( $product, array( 'attribute_keycap_material' ) ) : '';
				if ( $plate ) {
					return array(
						'main' => 'Plate ' . preg_replace( '/^plate\s+/iu', '', $plate ),
						'sub'  => $kc_mat ? 'Keycap đi kèm: ' . $kc_mat : '',
					);
				}
				if ( $kc_mat ) {
					return array( 'main' => 'Keycap ' . $kc_mat );
				}
				return array( 'main' => '—' );

			case 'rgb_led':
				$led = self::get_eav_meta( $product, array( 'attribute_led' ) );
				if ( '' === $led ) {
					$led = self::find_parsed_spec( $parsed, array( 'led', 'đèn nền' ) );
				}
				if ( '' === $led ) {
					if ( preg_match( '/không\s*led/iu', $plain ) ) {
						$led = 'Không LED (Tối ưu pin & âm gõ)';
					} elseif ( preg_match( '/rgb/iu', $plain ) ) {
						$led = 'LED RGB đa hiệu ứng';
					}
				}
				if ( '' === $led ) {
					return array( 'main' => '—' );
				}
				$no_led = (bool) preg_match( '/không\s*led|no\s*led/iu', $led );
				return array(
					'main' => $led,
					'icon' => $no_led ? 'none' : 'check',
				);

			case 'pcb_flex_foam':
				$pcb_detail = self::find_parsed_spec( $parsed, array( 'pcb', 'foam tiêu âm', 'foam' ) );
				$parts      = array();
				if ( preg_match( '/(1\.[26]\s*mm)/iu', $plain, $pm ) ) {
					$parts[] = str_replace( ' ', '', $pm[1] );
				}
				if ( preg_match( '/non-?flex/iu', $plain ) ) {
					$parts[] = 'Non-flex (Âm giòn clacky)';
				} elseif ( preg_match( '/flex-?cut/iu', $plain ) ) {
					$parts[] = 'Flex-cut (Độ nhún dẻo)';
				}
				$foam = self::find_parsed_spec( $parsed, array( 'foam tiêu âm', 'foam' ) );
				if ( '' === $foam && false !== mb_strpos( $plain, 'foam tiêu âm', 0, 'UTF-8' ) ) {
					$foam = 'Full bộ Foam tiêu âm PCB & Case';
				}
				if ( ! empty( $parts ) ) {
					return array(
						'main' => implode( ' · ', $parts ),
						'sub'  => $foam,
					);
				}
				if ( $foam ) {
					return array( 'main' => $foam );
				}
				if ( $pcb_detail ) {
					return array( 'main' => $pcb_detail );
				}
				return array( 'main' => '—' );

			case 'connection':
				$conn = self::get_eav_meta( $product, array( 'attribute_connection', 'connection' ) );
				if ( '' === $conn ) {
					$conn = self::find_parsed_spec( $parsed, array( 'chế độ kết nối', 'các chế độ kết nối', 'kết nối' ) );
				}
				if ( '' === $conn && ( 'kit' === $slug || 'pre-built' === $slug ) ) {
					if ( preg_match( '/(tri-?mode|3\s*chế độ|2\.4\s*ghz)/iu', $plain ) ) {
						$conn = '3 Mode (USB-C / Bluetooth / 2.4GHz)';
					} elseif ( preg_match( '/(wired|cắm dây|usb-c)/iu', $plain ) ) {
						$conn = 'Có dây (USB Type-C)';
					}
				}
				return array( 'main' => $conn ? $conn : '—' );

			case 'battery':
				$bat = self::get_eav_meta( $product, array( 'attribute_battery' ) );
				if ( '' === $bat ) {
					$bat = self::find_parsed_spec( $parsed, array( 'dung lượng pin', 'pin' ) );
				}
				if ( '' === $bat && preg_match( '/(\d[\d\.,]*\s*mah)/iu', $plain, $bm ) ) {
					$bat = $bm[1];
				}
				if ( '' === $bat ) {
					$conn = self::get_eav_meta( $product, array( 'attribute_connection' ) );
					if ( $conn && false !== mb_strpos( mb_strtolower( $conn, 'UTF-8' ), 'có dây', 0, 'UTF-8' ) && false === mb_strpos( mb_strtolower( $conn, 'UTF-8' ), 'bluetooth', 0, 'UTF-8' ) && false === mb_strpos( mb_strtolower( $conn, 'UTF-8' ), '3 mode', 0, 'UTF-8' ) ) {
						return array(
							'main' => 'Không dùng pin',
							'sub'  => 'Cấp nguồn trực tiếp qua USB-C',
						);
					}
					return array( 'main' => '—' );
				}
				$mah_num = (int) preg_replace( '/[^\d]/', '', $bat );
				return array(
					'main'   => $bat,
					'sub'    => $mah_num >= 6000 ? 'Pin dung lượng lớn' : ( $mah_num > 0 ? 'Pin sạc tích hợp trong case' : '' ),
					'accent' => ( $mah_num >= 6000 ),
				);

			case 'polling_travel':
				if ( 'switch' === $slug ) {
					$act_t = self::find_parsed_spec( $parsed, array( 'hành trình kích hoạt' ) );
					$tot_t = self::find_parsed_spec( $parsed, array( 'tổng hành trình' ) );
					if ( $act_t || $tot_t ) {
						return array(
							'main' => ( $act_t ? 'Kích hoạt ' . $act_t : '' ) . ( $act_t && $tot_t ? ' / ' : '' ) . ( $tot_t ? 'Tổng ' . $tot_t : '' ),
							'sub'  => 'Hành trình trục cơ học',
						);
					}
				}
				if ( preg_match( '/(8k|8\.?000\s*hz)/iu', $plain ) ) {
					return array(
						'main'   => '8.000Hz Ultra-Polling',
						'sub'    => 'Độ trễ siêu thấp chuẩn Esports',
						'accent' => true,
					);
				}
				if ( preg_match( '/(1\.?000\s*hz)/iu', $plain ) ) {
					return array( 'main' => '1.000Hz (USB-C / 2.4GHz)' );
				}
				if ( 'kit' === $slug || 'pre-built' === $slug ) {
					return array(
						'main' => '1.000Hz tiêu chuẩn (USB-C)',
						'sub'  => 'Chuẩn giao tiếp HID độ trễ thấp',
					);
				}
				return array( 'main' => '—' );

			case 'port_stock':
				$stock = self::resolve_stock_info( $product );
				$port  = '';
				if ( preg_match( '/daughterboard/iu', $plain ) ) {
					$port = 'USB Type-C Daughterboard rời';
				} elseif ( 'kit' === $slug || 'pre-built' === $slug ) {
					$port = 'Cổng USB Type-C tháo rời';
				} else {
					$sku  = $product->get_sku();
					$port = $sku ? 'SKU: ' . $sku : $cat['badge'];
				}
				return array(
					'main'   => $port,
					'sub'    => 'Kho: ' . $stock['text'],
					'accent' => ( 'low' === $stock['state'] ),
				);

			case 'keymap':
				$km = self::get_eav_meta( $product, array( 'attribute_keymap' ) );
				if ( '' === $km ) {
					$km = self::find_parsed_spec( $parsed, array( 'keymap', 'phần mềm', 'firmware' ) );
				}
				if ( '' === $km ) {
					$tags = array();
					if ( preg_match( '/\bqmk\b/iu', $plain ) ) {
						$tags[] = 'QMK';
					}
					if ( preg_match( '/\bvia\b/iu', $plain ) ) {
						$tags[] = 'VIA';
					}
					if ( preg_match( '/\bvial\b/iu', $plain ) ) {
						$tags[] = 'VIAL';
					}
					if ( ! empty( $tags ) ) {
						$km = implode( ' / ', $tags );
					}
				}
				if ( '' === $km ) {
					return array( 'main' => '—' );
				}
				return array(
					'main'   => $km,
					'sub'    => 'Tùy biến keymap & macro trực quan',
					'accent' => ( false !== stripos( $km, 'via' ) || false !== stripos( $km, 'qmk' ) ),
				);

			case 'os_layout_compat':
				$sup_layout = self::get_eav_meta( $product, array( 'attribute_supported_layout' ) );
				if ( '' !== $sup_layout ) {
					return array(
						'main' => $sup_layout,
						'sub'  => 'Độ phủ nút Keycap',
					);
				}
				if ( 'kit' === $slug || 'pre-built' === $slug ) {
					return array(
						'main' => 'Windows, macOS, Linux',
						'sub'  => 'Hỗ trợ chuyển đổi layout Win / Mac',
					);
				}
				return array( 'main' => '—' );

			case 'warranty':
				$war = self::get_eav_meta( $product, array( 'attribute_warranty' ) );
				if ( '' === $war ) {
					$war = self::find_parsed_spec( $parsed, array( 'bảo hành' ) );
				}
				if ( '' === $war ) {
					if ( 'kit' === $slug || 'pre-built' === $slug ) {
						$war = '12 tháng Kit / 6 tháng Mạch PCB';
					} else {
						$war = 'Đổi trả 7 ngày lỗi NSX tại TYPEE';
					}
				}
				return array( 'main' => $war );

			case 'lab_service':
				if ( 'pre-built' === $slug ) {
					return array(
						'main'   => 'Lắp ráp hoàn chỉnh (Sẵn sàng gõ)',
						'sub'    => 'Hỗ trợ căn chỉnh wire stab & mod âm tại Lab',
						'accent' => true,
					);
				}
				if ( 'kit' === $slug ) {
					$acc = array();
					if ( false !== mb_strpos( $plain, 'carrying case', 0, 'UTF-8' ) || false !== mb_strpos( $plain, 'túi đựng', 0, 'UTF-8' ) ) {
						$acc[] = 'Túi Carrying Case';
					}
					if ( false !== mb_strpos( $plain, 'stabilizer', 0, 'UTF-8' ) || false !== mb_strpos( $plain, 'stab', 0, 'UTF-8' ) ) {
						$acc[] = 'Bộ Stabilizer';
					}
					if ( false !== mb_strpos( $plain, 'foam', 0, 'UTF-8' ) ) {
						$acc[] = 'Full Foam';
					}
					return array(
						'main' => ! empty( $acc ) ? 'Kèm ' . implode( ', ', $acc ) : 'Kèm phụ kiện tiêu chuẩn hãng',
						'sub'  => 'Miễn phí công lắp khi build trọn bộ tại TYPEE',
					);
				}
				if ( 'switch' === $slug ) {
					return array(
						'main' => 'Hỗ trợ dịch vụ Lube & Film tại Lab',
						'sub'  => 'Tùy chọn mỡ Krytox 205g0 chuẩn Studio',
					);
				}
				if ( 'keycap' === $slug ) {
					return array(
						'main' => 'Đóng hộp khay bảo vệ nút phím',
						'sub'  => 'Hỗ trợ tư vấn phối màu hợp vỏ Kit',
					);
				}
				return array( 'main' => '—' );
		}

		return array( 'main' => '—' );
	}
}
