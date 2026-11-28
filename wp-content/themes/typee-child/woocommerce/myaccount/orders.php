<?php
/**
 * Orders – Danh sách Đơn hàng của tôi bên trong Layout Shell Tài khoản TYPEE.
 * Tham chiếu Figma: Node 82:7120 (Đơn hàng của tôi) & Node 46:5741 (Lịch sử đơn hàng & Tiến trình 5 bước).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 9.5.0
 */

defined( 'ABSPATH' ) || exit;

$user_id       = get_current_user_id();
$filter_groups = function_exists( 'typee_account_order_filter_groups' ) ? typee_account_order_filter_groups() : array( 'all' => 'Tất cả' );
$active_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! isset( $filter_groups[ $active_status ] ) ) {
	$active_status = 'all';
}
$search_q     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$active_range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! in_array( $active_range, array( 'all', '30d', '90d', 'year' ), true ) ) {
	$active_range = 'all';
}

$page_num = max( 1, isset( $current_page ) ? (int) $current_page : 1 );
if ( isset( $_GET['paged_orders'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$page_num = max( 1, absint( $_GET['paged_orders'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
}

$result = typee_account_query_orders(
	$user_id,
	array(
		'status'   => $active_status,
		'q'        => $search_q,
		'range'    => $active_range,
		'page'     => $page_num,
		'per_page' => 5,
	)
);

$orders_list   = $result['orders'];
$total_matched = (int) $result['total'];
$total_all     = (int) $result['total_all'];
$max_pages     = (int) $result['max_pages'];
$curr_page     = (int) $result['page'];
$per_page      = (int) $result['per_page'];
$counts        = $result['counts'];
$has_any_order = $total_all > 0;
$has_filtered  = ! empty( $orders_list );
$is_filtering  = ( 'all' !== $active_status || '' !== $search_q || 'all' !== $active_range );
$user_phone    = trim( (string) get_user_meta( $user_id, 'billing_phone', true ) );
$orders_base   = wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) );
$progress_lbls = typee_account_order_progress_steps();

$build_orders_url = function ( $overrides = array() ) use ( $active_status, $search_q, $active_range ) {
	$page   = isset( $overrides['page'] ) ? max( 1, (int) $overrides['page'] ) : 1;
	$status = isset( $overrides['status'] ) ? $overrides['status'] : $active_status;
	$q      = array_key_exists( 'q', $overrides ) ? $overrides['q'] : $search_q;
	$range  = isset( $overrides['range'] ) ? $overrides['range'] : $active_range;

	$base = wc_get_endpoint_url( 'orders', $page > 1 ? $page : '', wc_get_page_permalink( 'myaccount' ) );
	$args = array();
	if ( 'all' !== $status ) {
		$args['status'] = $status;
	}
	if ( '' !== $q ) {
		$args['q'] = $q;
	}
	if ( 'all' !== $range ) {
		$args['range'] = $range;
	}
	return $args ? add_query_arg( $args, $base ) : $base;
};

do_action( 'woocommerce_before_account_orders', $has_any_order );
?>

<!-- Khối 1: Header Banner Đơn hàng của tôi (Figma 82:7247) -->
<header class="typee-account-card typee-account-orders-head" data-node="82:7247">
	<div class="typee-account-orders-head__top">
		<div class="typee-account-orders-head__title-row">
			<h1 class="typee-account-page-title">Đơn hàng của tôi</h1>
			<span class="typee-account-count-pill ty-mono"><?php echo (int) $total_all; ?> đơn hàng</span>
		</div>

		<?php if ( '' !== $user_phone ) : ?>
			<div class="typee-account-rma-sync-pill">
				<?php typee_the_icon( 'verified_user', 16, 'typee-account-rma-sync-pill__icon' ); ?>
				<span>Đồng bộ e-RMA theo SĐT: <strong class="ty-mono"><?php echo esc_html( $user_phone ); ?></strong></span>
			</div>
		<?php else : ?>
			<a class="typee-account-rma-sync-pill typee-account-rma-sync-pill--link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">
				<?php typee_the_icon( 'verified_user', 16, 'typee-account-rma-sync-pill__icon' ); ?>
				<span>Cập nhật SĐT để đồng bộ e-RMA</span>
			</a>
		<?php endif; ?>
	</div>
	<p class="typee-account-page-desc">Theo dõi tiến độ gia công, đóng gói túi khí 3 lớp, hành trình vận chuyển và kích hoạt bảo hành e-RMA linh kiện.</p>
</header>

<!-- Khối 2: Segmented Filter Tabs theo trạng thái (Figma 82:7261 & 46:6000) -->
<nav class="typee-account-orders-tabs" aria-label="Lọc đơn hàng theo trạng thái">
	<?php foreach ( $filter_groups as $status_key => $status_label ) : ?>
		<?php
		$is_tab_active = ( $active_status === $status_key );
		$tab_count     = isset( $counts[ $status_key ] ) ? (int) $counts[ $status_key ] : 0;
		$tab_url       = $build_orders_url(
			array(
				'status' => $status_key,
				'page'   => 1,
			)
		);
		?>
		<a
			class="typee-account-orders-tab<?php echo $is_tab_active ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( $tab_url ); ?>"
			<?php echo $is_tab_active ? 'aria-current="true"' : ''; ?>
		>
			<span><?php echo esc_html( $status_label ); ?></span>
			<span class="typee-account-orders-tab__count<?php echo ( ! $is_tab_active && in_array( $status_key, array( 'processing', 'shipping' ), true ) && $tab_count > 0 ) ? ' is-accent' : ''; ?>">
				<?php echo (int) $tab_count; ?>
			</span>
		</a>
	<?php endforeach; ?>
</nav>

<!-- Khối 3: Thanh tìm kiếm & lọc khoảng thời gian (Figma 82:7292) -->
<form class="typee-account-orders-filter" method="get" action="<?php echo esc_url( $orders_base ); ?>" data-typee-orders-filter>
	<?php if ( 'all' !== $active_status ) : ?>
		<input type="hidden" name="status" value="<?php echo esc_attr( $active_status ); ?>" />
	<?php endif; ?>

	<div class="typee-account-orders-search">
		<span class="typee-account-orders-search__icon" aria-hidden="true">
			<?php typee_the_icon( 'search', 17 ); ?>
		</span>
		<label class="ty-sr" for="typee-orders-q">Tìm kiếm đơn hàng</label>
		<input
			type="search"
			id="typee-orders-q"
			name="q"
			class="typee-account-orders-search__input"
			placeholder="Tìm nhanh theo Mã đơn hàng (VD: #TY-2026) hoặc tên Kit, Switch, Keycap..."
			value="<?php echo esc_attr( $search_q ); ?>"
		/>
		<?php if ( '' !== $search_q ) : ?>
			<a
				class="typee-account-orders-search__clear"
				href="<?php echo esc_url( $build_orders_url( array( 'q' => '', 'page' => 1 ) ) ); ?>"
				aria-label="Xóa từ khóa tìm kiếm"
			>
				<?php typee_the_icon( 'close', 15 ); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="typee-account-orders-range">
		<span class="typee-account-orders-range__icon" aria-hidden="true">
			<?php typee_the_icon( 'schedule', 16 ); ?>
		</span>
		<label class="ty-sr" for="typee-orders-range">Lọc theo thời gian</label>
		<select id="typee-orders-range" name="range" class="typee-account-orders-range__select" data-typee-autosubmit>
			<option value="all" <?php selected( $active_range, 'all' ); ?>>Tất cả thời gian</option>
			<option value="30d" <?php selected( $active_range, '30d' ); ?>>30 ngày gần nhất</option>
			<option value="90d" <?php selected( $active_range, '90d' ); ?>>90 ngày gần nhất</option>
			<option value="year" <?php selected( $active_range, 'year' ); ?>>12 tháng gần nhất</option>
		</select>
		<span class="typee-account-orders-range__caret" aria-hidden="true">
			<?php typee_the_icon( 'keyboard_arrow_down', 18 ); ?>
		</span>
	</div>
</form>

<!-- Khối 4: Danh sách thẻ Đơn hàng (Figma 82:7305 & 46:6040) -->
<?php if ( $has_filtered ) : ?>

	<div class="typee-account-orders-list">
		<?php foreach ( $orders_list as $order ) : ?>
			<?php
			$order_id        = $order->get_id();
			$order_number    = $order->get_order_number();
			$order_date      = $order->get_date_created();
			$view_url        = $order->get_view_order_url();
			$status_info     = typee_account_order_display_status( $order );
			$items           = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
			$items_array     = array_values( $items );
			$items_total_cnt = count( $items_array );
			$preview_items   = array_slice( $items_array, 0, 3 );
			$hidden_items    = max( 0, $items_total_cnt - count( $preview_items ) );
			$discount_total  = (float) $order->get_discount_total();
			$payment_title   = $order->get_payment_method_title();
			$order_again_url = typee_account_order_again_url( $order );
			$tracking_url    = add_query_arg( 'order_id', $order_number, typee_url( 'tracking' ) );
			$rma_url         = add_query_arg( 'order_id', $order_number, typee_url( 'rma_new' ) );
			$wc_actions      = wc_get_account_orders_actions( $order );
			$is_in_progress  = ( $status_info['step'] >= 1 && $status_info['step'] <= 4 );
			$is_completed    = ( 'completed' === $status_info['group'] );
			?>

			<article class="typee-account-order-card typee-account-order-card--<?php echo esc_attr( $status_info['tone'] ); ?>">

				<!-- Header thẻ đơn hàng -->
				<div class="typee-account-order-card__head">
					<div class="typee-account-order-card__meta">
						<div class="typee-account-order-card__id-wrap">
							<span class="typee-account-order-card__id-label">MÃ ĐƠN:</span>
							<a class="typee-account-order-card__id ty-mono" href="<?php echo esc_url( $view_url ); ?>">
								#TY-<?php echo esc_html( $order_number ); ?>
							</a>
						</div>

						<?php if ( $order_date ) : ?>
							<span class="typee-account-order-card__sep" aria-hidden="true">|</span>
							<span class="typee-account-order-card__date">
								Ngày đặt:
								<time datetime="<?php echo esc_attr( $order_date->date( 'c' ) ); ?>">
									<?php echo esc_html( $order_date->date_i18n( 'd/m/Y H:i' ) ); ?>
								</time>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $status_info['carrier'] || '' !== $status_info['tracking_code'] ) : ?>
							<span class="typee-account-order-card__sep" aria-hidden="true">|</span>
							<span class="typee-account-order-card__carrier">
								<?php typee_the_icon( 'local_shipping', 14 ); ?>
								<span>
									<?php echo esc_html( $status_info['carrier'] ? $status_info['carrier'] : 'Vận chuyển' ); ?>
									<?php if ( '' !== $status_info['tracking_code'] ) : ?>
										: <strong class="ty-mono"><?php echo esc_html( $status_info['tracking_code'] ); ?></strong>
									<?php endif; ?>
								</span>
							</span>
						<?php endif; ?>
					</div>

					<span class="typee-account-status-pill typee-account-status-pill--<?php echo esc_attr( $status_info['tone'] ); ?>">
						<?php if ( in_array( $status_info['tone'], array( 'info', 'accent' ), true ) ) : ?>
							<span class="typee-account-status-pill__pulse" aria-hidden="true"></span>
						<?php elseif ( 'success' === $status_info['tone'] ) : ?>
							<?php typee_the_icon( 'check_circle', 14 ); ?>
						<?php elseif ( 'warning' === $status_info['tone'] ) : ?>
							<?php typee_the_icon( 'package_2', 14 ); ?>
						<?php elseif ( 'danger' === $status_info['tone'] ) : ?>
							<?php typee_the_icon( 'cancel', 14 ); ?>
						<?php endif; ?>
						<span><?php echo esc_html( $status_info['label'] ); ?></span>
					</span>
				</div>

				<!-- Body thẻ đơn hàng -->
				<div class="typee-account-order-card__body">

					<?php if ( $is_in_progress ) : ?>
						<!-- Thanh tiến trình 5 bước (Figma 46:6059) -->
						<div class="typee-account-order-stepper" aria-label="Tiến độ xử lý đơn hàng">
							<div class="typee-account-order-stepper__top">
								<span class="typee-account-order-stepper__eyebrow">
									<?php typee_the_icon( 'handyman', 14 ); ?>
									<span>TIẾN ĐỘ KỸ THUẬT &amp; GIAO NHẬN TYPEE</span>
								</span>
								<span class="typee-account-order-stepper__note">
									Bước <strong><?php echo (int) $status_info['step']; ?>/5</strong>
								</span>
							</div>
							<ol class="typee-account-order-stepper__bars">
								<?php foreach ( $progress_lbls as $step_num => $step_text ) : ?>
									<?php
									$step_cls = 'is-upcoming';
									if ( $step_num < $status_info['step'] ) {
										$step_cls = 'is-done';
									} elseif ( $step_num === $status_info['step'] ) {
										$step_cls = 'is-current';
									}
									?>
									<li class="typee-account-order-stepper__step <?php echo esc_attr( $step_cls ); ?>">
										<span class="typee-account-order-stepper__bar" aria-hidden="true"></span>
										<span class="typee-account-order-stepper__label"><?php echo esc_html( $step_text ); ?></span>
									</li>
								<?php endforeach; ?>
							</ol>
						</div>
					<?php endif; ?>

					<?php if ( $discount_total > 0 ) : ?>
						<div class="typee-account-order-ribbon">
							<?php typee_the_icon( 'sell', 14 ); ?>
							<span>ĐÃ ÁP DỤNG ƯU ĐÃI ĐƠN HÀNG • TIẾT KIỆM <?php echo esc_html( typee_money( $discount_total ) ); ?></span>
						</div>
					<?php endif; ?>

					<!-- Danh sách sản phẩm tóm tắt -->
					<ul class="typee-account-order-items">
						<?php foreach ( $preview_items as $item ) : ?>
							<?php
							$product    = method_exists( $item, 'get_product' ) ? $item->get_product() : false;
							$prod_url   = ( $product && $product->is_visible() ) ? $product->get_permalink( $item ) : '';
							$thumb_html = $product ? $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'typee-account-order-item__img', 'loading' => 'lazy' ) ) : '';
							$qty        = (int) $item->get_quantity();
							$line_total = (float) $order->get_line_total( $item, true, true );
							$meta_chips = array();
							if ( method_exists( $item, 'get_formatted_meta_data' ) ) {
								foreach ( $item->get_formatted_meta_data( '_', true ) as $meta_obj ) {
									$meta_chips[] = wp_strip_all_tags( $meta_obj->display_key . ': ' . $meta_obj->display_value );
								}
							}
							?>
							<li class="typee-account-order-item">
								<div class="typee-account-order-item__left">
									<div class="typee-account-order-item__thumb">
										<?php if ( $thumb_html ) : ?>
											<?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
										<?php else : ?>
											<?php typee_the_icon( 'keyboard', 24, 'typee-account-order-item__fallback' ); ?>
										<?php endif; ?>
									</div>
									<div class="typee-account-order-item__info">
										<h3 class="typee-account-order-item__name">
											<?php if ( $prod_url ) : ?>
												<a href="<?php echo esc_url( $prod_url ); ?>"><?php echo esc_html( $item->get_name() ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $item->get_name() ); ?>
											<?php endif; ?>
										</h3>
										<div class="typee-account-order-item__meta">
											<?php foreach ( array_slice( $meta_chips, 0, 3 ) as $chip ) : ?>
												<span class="typee-account-order-item__chip"><?php echo esc_html( $chip ); ?></span>
											<?php endforeach; ?>
											<span class="typee-account-order-item__qty">SL: <strong><?php echo (int) $qty; ?></strong></span>
										</div>
									</div>
								</div>
								<div class="typee-account-order-item__price ty-mono">
									<?php echo esc_html( typee_money( $line_total ) ); ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( $hidden_items > 0 ) : ?>
						<p class="typee-account-order-more">
							<a href="<?php echo esc_url( $view_url ); ?>">
								+ <?php echo (int) $hidden_items; ?> sản phẩm khác trong đơn hàng này — Xem toàn bộ chi tiết
							</a>
						</p>
					<?php endif; ?>

					<?php if ( $is_completed ) : ?>
						<div class="typee-account-order-rma-strip">
							<?php typee_the_icon( 'verified_user', 16, 'typee-account-order-rma-strip__icon' ); ?>
							<span><strong>e-RMA Active:</strong> Bảo hành điện tử chính hãng đã được kích hoạt cho đơn hàng #TY-<?php echo esc_html( $order_number ); ?>.</span>
						</div>
					<?php endif; ?>
				</div>

				<!-- Footer thẻ đơn hàng: Trạng thái vận chuyển + Tổng tiền + Nút hành động -->
				<div class="typee-account-order-card__foot">
					<div class="typee-account-order-card__status-note">
						<?php typee_the_icon( 'info', 15 ); ?>
						<span><?php echo esc_html( $status_info['note'] ); ?></span>
					</div>

					<div class="typee-account-order-card__bottom">
						<div class="typee-account-order-card__summary">
							<?php if ( $payment_title ) : ?>
								<span class="typee-account-order-card__pay-method">
									Thanh toán: <strong><?php echo esc_html( wp_strip_all_tags( $payment_title ) ); ?></strong>
								</span>
							<?php endif; ?>
							<div class="typee-account-order-card__total-row">
								<span class="typee-account-order-card__total-label">Tổng thanh toán:</span>
								<strong class="typee-account-order-card__total-val ty-mono">
									<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
								</strong>
							</div>
						</div>

						<div class="typee-account-order-card__actions">
							<?php if ( isset( $wc_actions['pay'] ) ) : ?>
								<a class="typee-account-btn typee-account-btn--accent" href="<?php echo esc_url( $wc_actions['pay']['url'] ); ?>">
									<?php typee_the_icon( 'payments', 15 ); ?>
									<span>Thanh toán ngay</span>
								</a>
							<?php endif; ?>

							<?php if ( isset( $wc_actions['cancel'] ) ) : ?>
								<a class="typee-account-btn typee-account-btn--danger" href="<?php echo esc_url( $wc_actions['cancel']['url'] ); ?>">
									<?php typee_the_icon( 'close', 15 ); ?>
									<span>Hủy đơn</span>
								</a>
							<?php endif; ?>

							<?php if ( '' !== $order_again_url ) : ?>
								<a class="typee-account-btn typee-account-btn--soft" href="<?php echo esc_url( $order_again_url ); ?>">
									<?php typee_the_icon( 'autorenew', 15 ); ?>
									<span>Mua lại</span>
								</a>
							<?php endif; ?>

							<?php if ( $is_in_progress ) : ?>
								<a class="typee-account-btn typee-account-btn--soft" href="<?php echo esc_url( $tracking_url ); ?>">
									<?php typee_the_icon( 'local_shipping', 15 ); ?>
									<span>Tra cứu lộ trình</span>
								</a>
							<?php endif; ?>

							<?php if ( $is_completed ) : ?>
								<a class="typee-account-btn typee-account-btn--warm" href="<?php echo esc_url( $rma_url ); ?>">
									<?php typee_the_icon( 'verified_user', 15 ); ?>
									<span>Yêu cầu bảo hành e-RMA</span>
								</a>
							<?php endif; ?>

							<a class="typee-account-btn typee-account-btn--dark" href="<?php echo esc_url( $view_url ); ?>">
								<span>Xem chi tiết đơn</span>
								<?php typee_the_icon( 'arrow_forward', 14 ); ?>
							</a>
						</div>
					</div>
				</div>

			</article>
		<?php endforeach; ?>
	</div>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<!-- Khối 5: Phân trang giữ nguyên trạng thái lọc & tab active (Figma 82:7594) -->
	<?php
	$from_idx = ( ( $curr_page - 1 ) * $per_page ) + 1;
	$to_idx   = min( $total_matched, $curr_page * $per_page );
	?>
	<div class="typee-account-pagination">
		<p class="typee-account-pagination__info">
			Hiển thị <strong><?php echo (int) $from_idx; ?>–<?php echo (int) $to_idx; ?></strong> trong <strong><?php echo (int) $total_matched; ?></strong> đơn hàng
		</p>

		<?php if ( $max_pages > 1 ) : ?>
			<nav class="typee-account-pagination__controls" aria-label="Phân trang đơn hàng">
				<?php if ( $curr_page > 1 ) : ?>
					<a class="typee-account-page-btn" href="<?php echo esc_url( $build_orders_url( array( 'page' => $curr_page - 1 ) ) ); ?>">
						<?php typee_the_icon( 'chevron_left', 16 ); ?>
						<span>Trước</span>
					</a>
				<?php else : ?>
					<span class="typee-account-page-btn is-disabled" aria-disabled="true">
						<?php typee_the_icon( 'chevron_left', 16 ); ?>
						<span>Trước</span>
					</span>
				<?php endif; ?>

				<?php for ( $p = 1; $p <= $max_pages; $p++ ) : ?>
					<?php if ( $p === $curr_page ) : ?>
						<span class="typee-account-page-num is-active" aria-current="page"><?php echo (int) $p; ?></span>
					<?php else : ?>
						<a class="typee-account-page-num" href="<?php echo esc_url( $build_orders_url( array( 'page' => $p ) ) ); ?>"><?php echo (int) $p; ?></a>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ( $curr_page < $max_pages ) : ?>
					<a class="typee-account-page-btn" href="<?php echo esc_url( $build_orders_url( array( 'page' => $curr_page + 1 ) ) ); ?>">
						<span>Sau</span>
						<?php typee_the_icon( 'chevron_right', 16 ); ?>
					</a>
				<?php else : ?>
					<span class="typee-account-page-btn is-disabled" aria-disabled="true">
						<span>Sau</span>
						<?php typee_the_icon( 'chevron_right', 16 ); ?>
					</span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>

<?php elseif ( $is_filtering ) : ?>

	<div class="typee-account-card typee-account-empty">
		<div class="typee-account-empty__icon"><?php typee_the_icon( 'search', 28 ); ?></div>
		<h2 class="typee-account-empty__title">Không tìm thấy đơn hàng phù hợp</h2>
		<p class="typee-account-empty__desc">Không có đơn hàng nào khớp với bộ lọc trạng thái hoặc từ khóa bạn vừa chọn.</p>
		<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $orders_base ); ?>">Xóa bộ lọc &amp; Xem tất cả</a>
	</div>

<?php else : ?>

	<div class="typee-account-card typee-account-empty">
		<div class="typee-account-empty__icon"><?php typee_the_icon( 'receipt_long', 28 ); ?></div>
		<h2 class="typee-account-empty__title">Bạn chưa có đơn hàng nào</h2>
		<p class="typee-account-empty__desc">Khám phá các mẫu bàn phím cơ Custom CNC, Switch, Keycap chính hãng hoặc tự ráp cấu hình 6 bước tại TYPEE Studio.</p>
		<div class="typee-account-empty__actions">
			<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Khám phá sản phẩm</a>
			<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">Mở Custom Builder</a>
		</div>
	</div>

<?php endif; ?>

<!-- Khối 6: Cam kết bảo vệ đơn hàng & vận chuyển (Figma 82:7612) -->
<div class="typee-account-card typee-account-guarantee">
	<div class="typee-account-guarantee__icon" aria-hidden="true">
		<?php typee_the_icon( 'verified_user', 24 ); ?>
	</div>
	<div class="typee-account-guarantee__body">
		<h2 class="typee-account-guarantee__title">Cam kết bảo vệ đơn hàng &amp; Vận chuyển linh kiện TYPEE Studio</h2>
		<p class="typee-account-guarantee__desc">100% switch và thanh wire stab được bọc đệm khí chuyên dụng chống cong vênh chân tiếp xúc. Mọi đơn kit barebone nhôm CNC đều được dán tem niêm phong e-RMA kiểm định trước khi xuất kho giao tận tay khách hàng.</p>
	</div>
</div>

<?php do_action( 'woocommerce_after_account_orders', $has_any_order ); ?>
