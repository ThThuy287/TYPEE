<?php
/**
 * View Order – Chi tiết đơn hàng bên trong Layout Shell Tài khoản TYPEE.
 * Giữ nguyên 100% khung ngoài & trạng thái active mục "Đơn hàng của tôi" ở Sidebar.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$order_number    = $order->get_order_number();
$order_date      = $order->get_date_created();
$status_info     = typee_account_order_display_status( $order );
$orders_url      = wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) );
$order_items     = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$item_totals     = $order->get_order_item_totals();
$notes           = $order->get_customer_order_notes();
$order_again_url = typee_account_order_again_url( $order );
$tracking_url    = add_query_arg( 'order_id', $order_number, typee_url( 'tracking' ) );
$rma_url         = add_query_arg( 'order_id', $order_number, typee_url( 'rma_new' ) );
$wc_actions      = wc_get_account_orders_actions( $order );
$progress_lbls   = typee_account_order_progress_steps();
$shipping_addr   = $order->get_formatted_shipping_address();
$billing_addr    = $order->get_formatted_billing_address();
$customer_note   = $order->get_customer_note();
?>

<!-- Khối 1: Header Chi tiết đơn hàng + Tiến trình 5 bước -->
<section class="typee-account-card typee-account-order-detail-head">
	<div class="typee-account-order-detail-head__nav">
		<a class="typee-account-back-link" href="<?php echo esc_url( $orders_url ); ?>">
			<?php typee_the_icon( 'arrow_back', 16 ); ?>
			<span>Quay lại Đơn hàng của tôi</span>
		</a>

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

	<div class="typee-account-order-detail-head__main">
		<div>
			<h1 class="typee-account-page-title">
				Chi tiết đơn hàng <span class="ty-mono">#TY-<?php echo esc_html( $order_number ); ?></span>
			</h1>
			<p class="typee-account-page-desc">
				<?php if ( $order_date ) : ?>
					Đặt lúc <strong><?php echo esc_html( $order_date->date_i18n( 'd/m/Y H:i' ) ); ?></strong> •
				<?php endif; ?>
				<?php echo esc_html( $status_info['note'] ); ?>
			</p>
		</div>

		<div class="typee-account-order-detail-head__actions">
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
				<a class="typee-account-btn typee-account-btn--accent" href="<?php echo esc_url( $order_again_url ); ?>">
					<?php typee_the_icon( 'autorenew', 15 ); ?>
					<span>Mua lại đơn này</span>
				</a>
			<?php endif; ?>

			<?php if ( 'completed' === $status_info['group'] ) : ?>
				<a class="typee-account-btn typee-account-btn--warm" href="<?php echo esc_url( $rma_url ); ?>">
					<?php typee_the_icon( 'verified_user', 15 ); ?>
					<span>Yêu cầu e-RMA</span>
				</a>
			<?php elseif ( $status_info['step'] > 0 ) : ?>
				<a class="typee-account-btn typee-account-btn--soft" href="<?php echo esc_url( $tracking_url ); ?>">
					<?php typee_the_icon( 'local_shipping', 15 ); ?>
					<span>Tra cứu vận đơn</span>
				</a>
			<?php endif; ?>

			<button type="button" class="typee-account-btn typee-account-btn--soft" data-typee-print-order>
				<?php typee_the_icon( 'receipt_long', 15 ); ?>
				<span>In đơn</span>
			</button>
		</div>
	</div>

	<?php if ( $status_info['step'] > 0 ) : ?>
		<div class="typee-account-order-stepper" aria-label="Tiến độ xử lý đơn hàng">
			<div class="typee-account-order-stepper__top">
				<span class="typee-account-order-stepper__eyebrow">
					<?php typee_the_icon( 'timeline', 15 ); ?>
					<span>TIẾN TRÌNH XỬ LÝ &amp; VẬN CHUYỂN</span>
				</span>
				<span class="typee-account-order-stepper__note">
					Hoàn thành bước <strong><?php echo (int) $status_info['step']; ?>/5</strong>
				</span>
			</div>
			<ol class="typee-account-order-stepper__bars">
				<?php foreach ( $progress_lbls as $step_num => $step_text ) : ?>
					<?php
					$step_cls = 'is-upcoming';
					if ( $step_num < $status_info['step'] || ( 5 === $status_info['step'] && $step_num <= 5 ) ) {
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
</section>

<!-- Khối 2: Danh sách sản phẩm & Tổng kết thanh toán -->
<section class="typee-account-card typee-account-order-detail-items">
	<?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

	<div class="typee-account-section-head">
		<div class="typee-account-section-head__left">
			<span class="typee-account-section-bar" aria-hidden="true"></span>
			<h2 class="typee-account-section-title">Sản phẩm trong đơn hàng (<?php echo (int) $order->get_item_count(); ?>)</h2>
		</div>
		<?php if ( '' !== $status_info['tracking_code'] ) : ?>
			<span class="typee-account-count-pill ty-mono">Vận đơn: <?php echo esc_html( $status_info['tracking_code'] ); ?></span>
		<?php endif; ?>
	</div>

	<ul class="typee-account-order-items typee-account-order-items--detail">
		<?php
		do_action( 'woocommerce_order_details_before_order_table_items', $order );

		foreach ( $order_items as $item_id => $item ) :
			$product    = method_exists( $item, 'get_product' ) ? $item->get_product() : false;
			$prod_url   = ( $product && $product->is_visible() ) ? $product->get_permalink( $item ) : '';
			$sku        = $product ? $product->get_sku() : '';
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
							<?php if ( '' !== $sku ) : ?>
								<span class="typee-account-order-item__chip typee-account-order-item__chip--sku ty-mono">SKU: <?php echo esc_html( $sku ); ?></span>
							<?php endif; ?>
							<?php foreach ( $meta_chips as $chip ) : ?>
								<span class="typee-account-order-item__chip"><?php echo esc_html( $chip ); ?></span>
							<?php endforeach; ?>
							<span class="typee-account-order-item__qty">Số lượng: <strong>×<?php echo (int) $qty; ?></strong></span>
						</div>
					</div>
				</div>
				<div class="typee-account-order-item__price ty-mono">
					<?php echo esc_html( typee_money( $line_total ) ); ?>
				</div>
			</li>
			<?php
		endforeach;

		do_action( 'woocommerce_order_details_after_order_table_items', $order );
		?>
	</ul>

	<!-- Bảng tổng kết tài chính -->
	<div class="typee-account-order-totals">
		<?php foreach ( $item_totals as $key => $total ) : ?>
			<div class="typee-account-order-totals__row<?php echo 'order_total' === $key ? ' is-grand' : ''; ?>">
				<span class="typee-account-order-totals__label"><?php echo esc_html( wp_strip_all_tags( $total['label'] ) ); ?></span>
				<span class="typee-account-order-totals__val<?php echo 'order_total' === $key ? ' ty-mono' : ''; ?>"><?php echo wp_kses_post( $total['value'] ); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if ( $customer_note ) : ?>
			<div class="typee-account-order-totals__note">
				<strong>Ghi chú đơn hàng:</strong>
				<span><?php echo wp_kses( nl2br( esc_html( $customer_note ) ), array( 'br' => array() ) ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>

<!-- Khối 3: Thông tin giao nhận & Thanh toán (2 cột) -->
<div class="typee-account-detail-grid-2">
	<section class="typee-account-card typee-account-address-card">
		<div class="typee-account-section-head">
			<div class="typee-account-section-head__left">
				<?php typee_the_icon( 'local_shipping', 18, 'typee-account-address-card__icon' ); ?>
				<h2 class="typee-account-section-title typee-account-section-title--sm">Địa chỉ giao hàng</h2>
			</div>
		</div>
		<address class="typee-account-address-card__body">
			<?php echo wp_kses_post( $shipping_addr ? $shipping_addr : ( $billing_addr ? $billing_addr : 'Chưa có địa chỉ giao hàng.' ) ); ?>
		</address>
		<?php if ( $order->get_billing_phone() ) : ?>
			<p class="typee-account-address-card__meta">
				<?php typee_the_icon( 'call', 14 ); ?>
				<span class="ty-mono"><?php echo esc_html( $order->get_billing_phone() ); ?></span>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $status_info['carrier'] ) : ?>
			<p class="typee-account-address-card__meta">
				<?php typee_the_icon( 'package_2', 14 ); ?>
				<span>Đơn vị vận chuyển: <strong><?php echo esc_html( $status_info['carrier'] ); ?></strong></span>
			</p>
		<?php endif; ?>
	</section>

	<section class="typee-account-card typee-account-address-card">
		<div class="typee-account-section-head">
			<div class="typee-account-section-head__left">
				<?php typee_the_icon( 'payments', 18, 'typee-account-address-card__icon' ); ?>
				<h2 class="typee-account-section-title typee-account-section-title--sm">Thanh toán &amp; Bảo hành</h2>
			</div>
		</div>
		<address class="typee-account-address-card__body">
			<?php echo wp_kses_post( $billing_addr ? $billing_addr : 'Theo thông tin tài khoản.' ); ?>
		</address>
		<?php if ( $order->get_billing_email() ) : ?>
			<p class="typee-account-address-card__meta">
				<?php typee_the_icon( 'mail', 14 ); ?>
				<span><?php echo esc_html( $order->get_billing_email() ); ?></span>
			</p>
		<?php endif; ?>
		<?php if ( $order->get_payment_method_title() ) : ?>
			<p class="typee-account-address-card__meta">
				<?php typee_the_icon( 'credit_card', 14 ); ?>
				<span>Phương thức: <strong><?php echo esc_html( wp_strip_all_tags( $order->get_payment_method_title() ) ); ?></strong></span>
			</p>
		<?php endif; ?>
	</section>
</div>

<!-- Khối 4: Nhật ký cập nhật từ TYPEE Studio (nếu có) -->
<?php if ( ! empty( $notes ) ) : ?>
	<section class="typee-account-card typee-account-order-notes">
		<div class="typee-account-section-head">
			<div class="typee-account-section-head__left">
				<span class="typee-account-section-bar" aria-hidden="true"></span>
				<h2 class="typee-account-section-title typee-account-section-title--sm">Cập nhật đơn hàng từ TYPEE Studio</h2>
			</div>
		</div>
		<ol class="typee-account-notes-list">
			<?php foreach ( $notes as $note ) : ?>
				<li class="typee-account-notes-item">
					<span class="typee-account-notes-item__dot" aria-hidden="true"></span>
					<div class="typee-account-notes-item__body">
						<time class="typee-account-notes-item__time ty-mono">
							<?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $note->comment_date ) ) ); ?>
						</time>
						<div class="typee-account-notes-item__text">
							<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</section>
<?php endif; ?>

<?php
do_action( 'woocommerce_after_order_details', $order );
do_action( 'woocommerce_view_order', $order_id );
?>
