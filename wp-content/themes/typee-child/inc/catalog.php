<?php
/**
 * Màn 02–03: Trang Danh Mục Sản Phẩm / Cửa Hàng + Bộ lọc Off-Canvas (Figma 46:1482).
 *
 * - Hỗ trợ đồng thời:
 *   1. Danh mục sản phẩm (`product_cat` qua tham số `cat[]`)
 *   2. Thuộc tính kỹ thuật ACF/postmeta (`attribute_*`: Layout, Kiểu mount, Chuẩn mạch PCB, Kết nối, Loại switch, Số chân switch, Profile keycap, Chất liệu keycap)
 *   3. Thuộc tính chuẩn WooCommerce (`pa_*` tự động nhận diện từ `wc_get_attribute_taxonomies()`)
 *   4. Khoảng giá (`min_price`, `max_price`) & Tình trạng kho hàng (`instock=1`)
 *   5. Sắp xếp chuẩn WooCommerce (`orderby`) & Phân trang giữ nguyên bộ lọc
 *   6. AJAX lọc + đếm kết quả trực tiếp có xác thực Nonce (`typee_catalog_nonce`)
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Các nhóm lọc kỹ thuật: tham số URL => [ key, nhãn, compare, source('meta'|'tax') ].
 */
function typee_filter_groups() {
	static $groups = null;
	if ( null !== $groups ) {
		return $groups;
	}

	$base = array(
		'layout'          => array( 'attribute_layout', 'Layout bàn phím', '=', 'meta' ),
		'mount'           => array( 'attribute_mount', 'Kiểu mount', '=', 'meta' ),
		'pcb'             => array( 'attribute_pcb', 'Chuẩn mạch (PCB)', '=', 'meta' ),
		'connection'      => array( 'attribute_connection', 'Kết nối', '=', 'meta' ),
		'switch_type'     => array( 'attribute_switch_type', 'Loại switch', '=', 'meta' ),
		'pin'             => array( 'attribute_pin', 'Số chân switch', 'LIKE', 'meta' ),
		'profile'         => array( 'attribute_profile', 'Profile keycap', '=', 'meta' ),
		'material'        => array( 'attribute_material', 'Chất liệu keycap', '=', 'meta' ),
		'keycap_material' => array( 'attribute_keycap_material', 'Keycap đi kèm', '=', 'meta' ),
	);

	// Tự động nạp thêm các Global Attributes chuẩn WooCommerce (pa_*) nếu quản trị viên tạo trong WP-Admin.
	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		foreach ( wc_get_attribute_taxonomies() as $attr ) {
			$tax = wc_attribute_taxonomy_name( $attr->attribute_name );
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$param            = 'filter_' . $attr->attribute_name;
			$base[ $param ]   = array( $tax, $attr->attribute_label, 'IN', 'tax' );
		}
	}

	$groups = apply_filters( 'typee_filter_groups', $base );
	return $groups;
}

/**
 * Danh sách danh mục sản phẩm có sản phẩm thực tế (dùng cho bộ lọc danh mục).
 *
 * @return WP_Term[]
 */
function typee_catalog_categories() {
	$default_cat = (int) get_option( 'default_product_cat' );
	$terms       = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'exclude'    => $default_cat ? array( $default_cat ) : array(),
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Đọc nguồn tham số (mặc định $_GET hoặc mảng truyền vào cho AJAX).
 */
function typee_catalog_input( $source = null ) {
	return is_array( $source ) ? $source : $_GET; // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * Danh mục đang chọn thêm từ bộ lọc `cat[]`.
 *
 * @param array|null $source Nguồn tham số.
 * @return string[]
 */
function typee_active_cats( $source = null ) {
	$in = typee_catalog_input( $source );
	if ( empty( $in['cat'] ) ) {
		return array();
	}
	$raw = wp_unslash( $in['cat'] );
	$arr = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
	return array_values( array_unique( array_filter( array_map( 'sanitize_title', $arr ) ) ) );
}

/**
 * Giá trị lọc kỹ thuật đang chọn: [ 'layout' => ['75%','65%'], ... ].
 *
 * @param array|null $source Nguồn tham số.
 * @return array<string, string[]>
 */
function typee_active_filters( $source = null ) {
	$in  = typee_catalog_input( $source );
	$out = array();
	foreach ( array_keys( typee_filter_groups() ) as $key ) {
		if ( empty( $in[ $key ] ) ) {
			continue;
		}
		$raw  = wp_unslash( $in[ $key ] );
		$vals = array_filter( array_map( 'sanitize_text_field', (array) $raw ) );
		if ( $vals ) {
			$out[ $key ] = array_values( array_unique( $vals ) );
		}
	}
	return $out;
}

/**
 * Xây meta_query cho các nhóm lọc dạng postmeta (`attribute_*`).
 */
function typee_filter_meta_query( $active ) {
	$groups = typee_filter_groups();
	$mq     = array( 'relation' => 'AND' );
	foreach ( $active as $key => $vals ) {
		if ( empty( $groups[ $key ] ) || 'meta' !== $groups[ $key ][3] ) {
			continue;
		}
		list( $meta, , $cmp ) = $groups[ $key ];
		if ( 'LIKE' === $cmp ) {
			$or = array( 'relation' => 'OR' );
			foreach ( $vals as $v ) {
				$or[] = array( 'key' => $meta, 'value' => $v, 'compare' => 'LIKE' );
			}
			$mq[] = $or;
		} else {
			$mq[] = array( 'key' => $meta, 'value' => $vals, 'compare' => 'IN' );
		}
	}
	return count( $mq ) > 1 ? $mq : array();
}

/**
 * Xây tax_query cho các nhóm lọc dạng taxonomy (`pa_*` và `cat[]`).
 */
function typee_filter_tax_query( $active, $cats = array() ) {
	$groups = typee_filter_groups();
	$tq     = array();
	if ( ! empty( $cats ) ) {
		$tq[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $cats,
			'operator' => 'IN',
		);
	}
	foreach ( $active as $key => $vals ) {
		if ( empty( $groups[ $key ] ) || 'tax' !== $groups[ $key ][3] ) {
			continue;
		}
		$tq[] = array(
			'taxonomy' => $groups[ $key ][0],
			'field'    => 'slug',
			'terms'    => $vals,
			'operator' => 'IN',
		);
	}
	return $tq;
}

function typee_is_catalog() {
	return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) );
}

/**
 * Áp bộ lọc vào truy vấn danh mục chính của WooCommerce (SSR).
 */
add_action( 'woocommerce_product_query', function ( $q ) {
	$active = typee_active_filters();
	$cats   = typee_active_cats();

	$mq = typee_filter_meta_query( $active );
	if ( $mq ) {
		$existing = (array) $q->get( 'meta_query' );
		$q->set( 'meta_query', array_merge( $existing, array( $mq ) ) );
	}

	if ( ! empty( $_GET['instock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$existing   = (array) $q->get( 'meta_query' );
		$existing[] = array( 'key' => '_stock_status', 'value' => 'instock' );
		$q->set( 'meta_query', $existing );
	}

	$tq = typee_filter_tax_query( $active, $cats );
	if ( $tq ) {
		$existing_tq = (array) $q->get( 'tax_query' );
		$q->set( 'tax_query', array_merge( $existing_tq, $tq ) );
	}
} );

add_filter( 'loop_shop_per_page', function () {
	return 16;
}, 30 );
add_filter( 'loop_shop_columns', function () {
	return 4;
}, 30 );

/**
 * Tùy chỉnh danh sách sắp xếp tiếng Việt chuẩn Figma 46:1560.
 */
add_filter( 'woocommerce_catalog_orderby', function () {
	return array(
		'date'       => 'Sắp xếp: Mới nhất',
		'popularity' => 'Sắp xếp: Phổ biến nhất',
		'price'      => 'Sắp xếp: Giá thấp → cao',
		'price-desc' => 'Sắp xếp: Giá cao → thấp',
		'menu_order' => 'Sắp xếp: Mặc định',
	);
}, 20 );

/**
 * Đếm số sản phẩm theo từng giá trị thông số trong phạm vi danh mục hiện tại (facet), hỗ trợ cả meta và pa_*.
 *
 * @param int $term_taxonomy_id 0 = toàn cửa hàng.
 * @return array<string, array<string, array{label:string,count:int}>>
 */
function typee_facet_counts( $term_taxonomy_id = 0 ) {
	$key    = 'facets_v2_' . (int) $term_taxonomy_id . '_v' . (int) wp_cache_get( 'ver', 'typee' );
	$cached = wp_cache_get( $key, 'typee' );
	if ( false !== $cached ) {
		return $cached;
	}
	global $wpdb;
	$groups    = typee_filter_groups();
	$meta_map  = array();
	$tax_map   = array();
	foreach ( $groups as $param => $g ) {
		if ( 'tax' === $g[3] ) {
			$tax_map[ $param ] = $g[0];
		} else {
			$meta_map[ $param ] = $g[0];
		}
	}

	$out = array();
	if ( $meta_map ) {
		$metas = array_values( $meta_map );
		$in    = implode( ',', array_fill( 0, count( $metas ), '%s' ) );
		$join  = $term_taxonomy_id ? "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID AND tr.term_taxonomy_id = " . (int) $term_taxonomy_id : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_key, pm.meta_value, COUNT(DISTINCT p.ID) c FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID {$join} WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value <> '' AND pm.meta_key IN ($in) GROUP BY pm.meta_key, pm.meta_value", $metas ) );
		foreach ( $rows as $r ) {
			$param = array_search( $r->meta_key, $meta_map, true );
			if ( false === $param ) {
				continue;
			}
			if ( 'pin' === $param ) {
				foreach ( array_map( 'trim', explode( ',', $r->meta_value ) ) as $pin ) {
					if ( '' === $pin ) {
						continue;
					}
					$prev                     = isset( $out['pin'][ $pin ]['count'] ) ? $out['pin'][ $pin ]['count'] : 0;
					$out['pin'][ $pin ]       = array( 'label' => $pin, 'count' => $prev + (int) $r->c );
				}
				continue;
			}
			$out[ $param ][ $r->meta_value ] = array( 'label' => $r->meta_value, 'count' => (int) $r->c );
		}
	}

	// Đếm các Global Attributes WooCommerce (`pa_*`).
	foreach ( $tax_map as $param => $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}
		foreach ( $terms as $t ) {
			$out[ $param ][ $t->slug ] = array( 'label' => $t->name, 'count' => (int) $t->count );
		}
	}

	foreach ( $out as &$vals ) {
		uasort( $vals, function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		} );
	}
	unset( $vals );

	wp_cache_set( $key, $out, 'typee', HOUR_IN_SECONDS );
	return $out;
}

/**
 * term_taxonomy_id của danh mục đang xem (0 nếu là trang cửa hàng tổng).
 */
function typee_current_cat_ttid() {
	$obj = get_queried_object();
	return ( $obj instanceof WP_Term && 'product_cat' === $obj->taxonomy ) ? (int) $obj->term_taxonomy_id : 0;
}

/**
 * URL cơ sở sạch (không query string, không /page/N/).
 */
function typee_catalog_base_url( $base = '' ) {
	if ( ! $base ) {
		$base = strtok( add_query_arg( null, null ), '?' );
	}
	return preg_replace( '#/page/\d+/?#', '/', $base );
}

/**
 * URL hiện tại sau khi thay đổi một tham số lọc (giữ các tham số khác, bỏ phân trang).
 */
function typee_filter_url( $changes, $base_override = '' ) {
	$base = $base_override ? $base_override : add_query_arg( null, null );
	$base = remove_query_arg( array( 'paged', 'product-page' ), $base );
	$base = preg_replace( '#/page/\d+/?#', '/', $base );
	foreach ( $changes as $k => $v ) {
		$base = remove_query_arg( $k, $base );
		if ( null !== $v && array() !== $v && '' !== $v ) {
			$base = add_query_arg( $k, is_array( $v ) ? array_map( 'rawurlencode', array_values( $v ) ) : rawurlencode( (string) $v ), $base );
		}
	}
	return $base;
}

/**
 * Xây WP_Query args chuẩn cho bộ lọc danh mục (dùng chung cho AJAX đếm và AJAX render danh sách).
 */
function typee_build_catalog_query_args( $source, $tt = 0, $paged = 1, $per_page = 16 ) {
	$active  = typee_active_filters( $source );
	$cats    = typee_active_cats( $source );
	$orderby = isset( $source['orderby'] ) ? sanitize_text_field( wp_unslash( $source['orderby'] ) ) : 'date';

	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => max( 1, (int) $paged ),
		'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		),
	);

	if ( $tt ) {
		$term = get_term_by( 'term_taxonomy_id', $tt );
		if ( $term && ! is_wp_error( $term ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => $term->taxonomy,
				'field'    => 'term_id',
				'terms'    => (int) $term->term_id,
			);
		}
	}

	$tq = typee_filter_tax_query( $active, $cats );
	if ( $tq ) {
		$args['tax_query'] = array_merge( $args['tax_query'], $tq ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	$mq = typee_filter_meta_query( $active );
	if ( $mq ) {
		$args['meta_query'][] = $mq;
	}

	if ( ! empty( $source['instock'] ) ) {
		$args['meta_query'][] = array( 'key' => '_stock_status', 'value' => 'instock' );
	}

	$min = isset( $source['min_price'] ) && '' !== $source['min_price'] ? max( 0, (float) $source['min_price'] ) : 0;
	$max = isset( $source['max_price'] ) && '' !== $source['max_price'] ? max( 0, (float) $source['max_price'] ) : 0;
	if ( $min || $max ) {
		$args['meta_query'][] = array(
			'key'     => '_price',
			'value'   => array( $min, $max ? $max : 999999999 ),
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		);
	}

	switch ( $orderby ) {
		case 'popularity':
			$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
			break;
		case 'price':
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'ASC', 'date' => 'DESC' );
			break;
		case 'price-desc':
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
			break;
		case 'menu_order':
			$args['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
			break;
		case 'date':
		default:
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			break;
	}

	return $args;
}

/**
 * Render danh sách chip bộ lọc đang áp dụng (dùng chung cho SSR & AJAX).
 */
function typee_render_active_chips( $source, $base_url ) {
	$groups = typee_filter_groups();
	$active = typee_active_filters( $source );
	$cats   = typee_active_cats( $source );
	$min_p  = isset( $source['min_price'] ) && '' !== $source['min_price'] ? (float) $source['min_price'] : 0;
	$max_p  = isset( $source['max_price'] ) && '' !== $source['max_price'] ? (float) $source['max_price'] : 0;
	$instk  = ! empty( $source['instock'] );
	$n_act  = array_sum( array_map( 'count', $active ) ) + count( $cats ) + ( $instk ? 1 : 0 ) + ( ( $min_p || $max_p ) ? 1 : 0 );

	$current_url = $base_url;
	foreach ( $source as $k => $v ) {
		if ( in_array( $k, array( 'action', 'nonce', 'tt', 'base_url', 'paged' ), true ) || '' === $v || array() === $v ) {
			continue;
		}
		$current_url = add_query_arg( $k, is_array( $v ) ? array_map( 'rawurlencode', array_values( $v ) ) : rawurlencode( (string) $v ), $current_url );
	}

	ob_start();
	foreach ( $cats as $slug ) :
		$term_obj = get_term_by( 'slug', $slug, 'product_cat' );
		$label    = $term_obj && ! is_wp_error( $term_obj ) ? $term_obj->name : $slug;
		$next     = array_values( array_diff( $cats, array( $slug ) ) );
		?>
		<a class="ty-chip" href="<?php echo esc_url( typee_filter_url( array( 'cat' => $next ), $current_url ) ); ?>" data-catalog-link aria-label="<?php echo esc_attr( 'Bỏ lọc danh mục: ' . $label ); ?>">
			Danh mục: <strong><?php echo esc_html( $label ); ?></strong> <?php typee_the_icon( 'close', 12 ); ?>
		</a>
	<?php endforeach; ?>
	<?php foreach ( $active as $key => $vals ) : ?>
		<?php foreach ( $vals as $v ) : ?>
			<?php
			$disp = $v;
			if ( isset( $groups[ $key ] ) && 'tax' === $groups[ $key ][3] ) {
				$t = get_term_by( 'slug', $v, $groups[ $key ][0] );
				if ( $t && ! is_wp_error( $t ) ) {
					$disp = $t->name;
				}
			}
			$next = array_values( array_diff( $vals, array( $v ) ) );
			?>
			<a class="ty-chip" href="<?php echo esc_url( typee_filter_url( array( $key => $next ), $current_url ) ); ?>" data-catalog-link aria-label="<?php echo esc_attr( 'Bỏ lọc ' . $groups[ $key ][1] . ': ' . $disp ); ?>">
				<?php echo esc_html( $groups[ $key ][1] . ': ' ); ?><strong><?php echo esc_html( $disp ); ?></strong> <?php typee_the_icon( 'close', 12 ); ?>
			</a>
		<?php endforeach; ?>
	<?php endforeach; ?>
	<?php if ( $instk ) : ?>
		<a class="ty-chip" href="<?php echo esc_url( typee_filter_url( array( 'instock' => null ), $current_url ) ); ?>" data-catalog-link>
			Kho: <strong>Có sẵn (Giao 2H)</strong> <?php typee_the_icon( 'close', 12 ); ?>
		</a>
	<?php endif; ?>
	<?php if ( $min_p || $max_p ) : ?>
		<a class="ty-chip" href="<?php echo esc_url( typee_filter_url( array( 'min_price' => null, 'max_price' => null ), $current_url ) ); ?>" data-catalog-link>
			Giá: <strong><?php echo esc_html( ( $min_p ? typee_money( $min_p ) : '0₫' ) . ' – ' . ( $max_p ? typee_money( $max_p ) : '∞' ) ); ?></strong> <?php typee_the_icon( 'close', 12 ); ?>
		</a>
	<?php endif; ?>
	<?php if ( $n_act ) : ?>
		<a class="ty-cattools__clear" href="<?php echo esc_url( $base_url ); ?>" data-catalog-link>Xóa tất cả</a>
	<?php endif; ?>
	<?php
	return array(
		'html'  => ob_get_clean(),
		'count' => $n_act,
	);
}

/**
 * Render chuỗi đếm kết quả chuẩn Figma 46:1559 ("Hiển thị 1 - 16 trong 48 sản phẩm").
 */
function typee_render_result_count( $total, $paged, $per_page ) {
	if ( $total <= 0 ) {
		return 'Không tìm thấy sản phẩm nào';
	}
	$from = ( ( max( 1, (int) $paged ) - 1 ) * $per_page ) + 1;
	$to   = min( $total, $paged * $per_page );
	return sprintf(
		'Hiển thị <strong>%1$d - %2$d</strong> trong <strong>%3$d</strong> sản phẩm',
		$from,
		$to,
		$total
	);
}

/**
 * Render phân trang chuẩn WooCommerce giữ nguyên mọi tham số lọc.
 */
function typee_render_pagination( $max_pages, $current_page, $base_url, $query_args = array() ) {
	if ( $max_pages <= 1 ) {
		return '';
	}
	$clean_args = array();
	foreach ( $query_args as $k => $v ) {
		if ( in_array( $k, array( 'action', 'nonce', 'tt', 'base_url', 'paged', 'product-page' ), true ) || '' === $v || array() === $v ) {
			continue;
		}
		$clean_args[ $k ] = $v;
	}

	$links = paginate_links(
		array(
			'base'      => trailingslashit( $base_url ) . 'page/%#%/',
			'format'    => '',
			'add_args'  => $clean_args,
			'current'   => max( 1, (int) $current_page ),
			'total'     => (int) $max_pages,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'type'      => 'list',
			'end_size'  => 2,
			'mid_size'  => 2,
		)
	);
	if ( ! $links ) {
		return '';
	}
	return '<nav class="woocommerce-pagination" aria-label="Phân trang sản phẩm">' . $links . '</nav>';
}

/**
 * AJAX: Đếm nhanh số kết quả trong Filter Drawer trước khi bấm Áp dụng.
 */
function typee_ajax_filter_count() {
	$tt   = isset( $_GET['tt'] ) ? absint( $_GET['tt'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$args = typee_build_catalog_query_args( $_GET, $tt, 1, 1 ); // phpcs:ignore WordPress.Security.NonceVerification
	$args['fields']        = 'ids';
	$args['no_found_rows'] = false;

	$ck    = 'count_v2_' . md5( wp_json_encode( $args ) ) . '_v' . (int) wp_cache_get( 'ver', 'typee' );
	$count = wp_cache_get( $ck, 'typee' );
	if ( false === $count ) {
		$q     = new WP_Query( $args );
		$count = (int) $q->found_posts;
		wp_cache_set( $ck, $count, 'typee', 10 * MINUTE_IN_SECONDS );
	}
	wp_send_json_success( array( 'count' => (int) $count ) );
}
add_action( 'wp_ajax_typee_filter_count', 'typee_ajax_filter_count' );
add_action( 'wp_ajax_nopriv_typee_filter_count', 'typee_ajax_filter_count' );

/**
 * AJAX: Lọc, Sắp xếp & Phân trang danh mục sản phẩm trực tiếp (không cần reload trang, có Nonce bảo mật).
 */
function typee_ajax_filter_catalog() {
	check_ajax_referer( 'typee_catalog_nonce', 'nonce' );

	$tt       = isset( $_GET['tt'] ) ? absint( $_GET['tt'] ) : 0;
	$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page = (int) apply_filters( 'loop_shop_per_page', 16 );
	$raw_base = isset( $_GET['base_url'] ) ? esc_url_raw( wp_unslash( $_GET['base_url'] ) ) : '';
	$base_url = $raw_base ? typee_catalog_base_url( $raw_base ) : home_url( '/catalog/' );

	$args  = typee_build_catalog_query_args( $_GET, $tt, $paged, $per_page );
	$query = new WP_Query( $args );
	$total = (int) $query->found_posts;

	ob_start();
	if ( $query->have_posts() ) {
		echo '<div class="ty-grid-4 ty-catalog__grid" data-catalog-grid>';
		while ( $query->have_posts() ) {
			$query->the_post();
			get_template_part( 'template-parts/product/card', null, array( 'product' => get_the_ID() ) );
		}
		echo '</div>';
		wp_reset_postdata();
	} else {
		?>
		<div class="ty-panel ty-empty">
			<img
				class="ty-empty__mascot"
				src="<?php echo esc_url( TYPEE_CHILD_URI . '/assets/images/logo-mascot.png' ); ?>"
				alt="TYPEE Mascot"
				width="84"
				height="96"
				loading="lazy"
				decoding="async"
			/>
			<h2 class="ty-empty__title">Không tìm thấy sản phẩm phù hợp bộ lọc</h2>
			<p class="ty-empty__desc">Thử bỏ bớt điều kiện lọc, nới rộng khoảng giá hoặc xem toàn bộ cửa hàng.</p>
			<div class="ty-empty__actions">
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $base_url ); ?>" data-catalog-link>Xóa tất cả bộ lọc</a>
				<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">Mở Custom Builder</a>
			</div>
		</div>
		<?php
	}
	$grid_html = ob_get_clean();

	$chips = typee_render_active_chips( $_GET, $base_url );
	$pagin = typee_render_pagination( (int) $query->max_num_pages, $paged, $base_url, $_GET );

	wp_send_json_success(
		array(
			'total'          => $total,
			'activeCount'    => $chips['count'],
			'chipsHtml'      => $chips['html'],
			'countHtml'      => typee_render_result_count( $total, $paged, $per_page ),
			'gridHtml'       => $grid_html,
			'paginationHtml' => $pagin,
		)
	);
}
add_action( 'wp_ajax_typee_filter_catalog', 'typee_ajax_filter_catalog' );
add_action( 'wp_ajax_nopriv_typee_filter_catalog', 'typee_ajax_filter_catalog' );

/**
 * Khoảng giá của danh mục hiện tại (cho ô giá tối thiểu/tối đa và dual slider).
 */
function typee_price_bounds( $tt = 0 ) {
	global $wpdb;
	$join = $tt ? "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = l.product_id AND tr.term_taxonomy_id = " . (int) $tt : '';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
	$row = $wpdb->get_row( "SELECT MIN(l.min_price) mn, MAX(l.max_price) mx FROM {$wpdb->wc_product_meta_lookup} l {$join} WHERE l.min_price > 0" );
	return array(
		'min' => $row ? (float) $row->mn : 0,
		'max' => $row ? (float) $row->mx : 10000000,
	);
}

/**
 * Bỏ thanh tiêu đề danh mục mặc định của Flatsome & các khối đếm/sắp xếp/phân trang trùng lặp của WooCommerce
 * (vì archive-product.php của TYPEE đã render sẵn trong thanh công cụ và vùng AJAX riêng).
 */
add_action( 'wp', function () {
	if ( typee_is_catalog() ) {
		remove_action( 'flatsome_after_header', 'flatsome_category_header' );
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
		remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
	}
} );

/**
 * Mọi trang danh mục (cửa hàng, danh mục, thẻ, thuộc tính) dùng chung giao diện danh mục của TYPEE.
 */
add_filter( 'template_include', function ( $template ) {
	if ( typee_is_catalog() && ! typee_current_virtual() ) {
		$own = TYPEE_CHILD_DIR . '/woocommerce/archive-product.php';
		if ( file_exists( $own ) ) {
			return $own;
		}
	}
	return $template;
}, 50 );
