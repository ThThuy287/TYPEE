<?php
/**
 * My Account Dashboard – Tổng quan tài khoản bên trong Layout Shell Tài khoản TYPEE.
 * Tham chiếu Figma: Node 46:5741 (Profile Hero & 4 Operational Metrics + Recent Orders).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 4.4.0
 */

defined( 'ABSPATH' ) || exit;

$user_id      = get_current_user_id();
$summary      = function_exists( 'typee_account_user_summary' ) ? typee_account_user_summary( $user_id ) : array();
$user_phone   = trim( (string) get_user_meta( $user_id, 'billing_phone', true ) );
$recent_query = typee_account_query_orders(
	$user_id,
	array(
		'per_page' => 3,
		'page'     => 1,
	)
);
$recent_orders = $recent_query['orders'];

$edit_account_url = wc_get_account_endpoint_url( 'edit-account' );
$orders_url       = wc_get_account_endpoint_url( 'orders' );
$address_url      = wc_get_account_endpoint_url( 'edit-address' );
$builds_url       = wc_get_account_endpoint_url( 'saved-builds' );
$rma_url          = wc_get_account_endpoint_url( 'rma-tickets' );
$remain_amount    = isset( $summary['tier_target'], $summary['total_spent'] ) ? max( 0, (float) $summary['tier_target'] - (float) $summary['total_spent'] ) : 0;
?>

<!-- Khối 1: Profile & Loyalty Hero Card + 4 Chỉ số vận hành (Figma 46:5766) -->
<section class="typee-account-card typee-account-dash-hero" data-node="46:5766">
	<div class="typee-account-dash-hero__top">
		<div class="typee-account-dash-hero__user">
			<div class="typee-account-dash-hero__avatar">
				<?php
				echo get_avatar(
					$user_id,
					160,
					'',
					isset( $summary['display_name'] ) ? $summary['display_name'] : '',
					array( 'class' => 'typee-account-dash-hero__img' )
				);
				?>
				<span class="typee-account-dash-hero__vip">MEMBER</span>
			</div>

			<div class="typee-account-dash-hero__identity">
				<div class="typee-account-dash-hero__name-row">
					<h1 class="typee-account-dash-hero__name">
						Xin chào, <?php echo esc_html( isset( $summary['display_name'] ) ? $summary['display_name'] : $current_user->display_name ); ?>
					</h1>
					<?php if ( ! empty( $summary['tier_name'] ) ) : ?>
						<span class="typee-account-dash-hero__rank">
							<?php typee_the_icon( 'star', 13 ); ?>
							<span><?php echo esc_html( $summary['tier_name'] ); ?></span>
						</span>
					<?php endif; ?>
				</div>

				<div class="typee-account-dash-hero__contacts">
					<span>
						<?php typee_the_icon( 'mail', 14 ); ?>
						<span><?php echo esc_html( isset( $summary['email'] ) ? $summary['email'] : $current_user->user_email ); ?></span>
					</span>
					<?php if ( '' !== $user_phone ) : ?>
						<span>
							<?php typee_the_icon( 'call', 14 ); ?>
							<span class="ty-mono"><?php echo esc_html( $user_phone ); ?></span>
						</span>
					<?php endif; ?>
					<?php if ( ! empty( $summary['member_code'] ) ) : ?>
						<span class="typee-account-dash-hero__id ty-mono">
							<?php typee_the_icon( 'verified_user', 14 ); ?>
							<span>ID: <?php echo esc_html( $summary['member_code'] ); ?></span>
						</span>
					<?php endif; ?>
				</div>

				<div class="typee-account-dash-hero__btns">
					<a class="typee-account-btn typee-account-btn--soft" href="<?php echo esc_url( $edit_account_url ); ?>">
						<?php typee_the_icon( 'edit', 14 ); ?>
						<span>Chỉnh sửa hồ sơ</span>
					</a>
					<a class="typee-account-btn typee-account-btn--soft" href="<?php echo esc_url( $edit_account_url . '#security' ); ?>">
						<?php typee_the_icon( 'lock', 14 ); ?>
						<span>Đổi mật khẩu &amp; 2FA</span>
					</a>
				</div>
			</div>
		</div>

		<!-- Cột phải: Tiến độ chi tiêu & đặc quyền thành viên -->
		<?php if ( ! empty( $summary ) ) : ?>
			<div class="typee-account-dash-loyalty">
				<div class="typee-account-dash-loyalty__head">
					<div>
						<span class="typee-account-dash-loyalty__eyebrow">TỔNG CHI TIÊU TÍCH LŨY</span>
						<strong class="typee-account-dash-loyalty__amount ty-mono">
							<?php echo esc_html( typee_money( $summary['total_spent'] ) ); ?>
						</strong>
					</div>
					<span class="typee-account-dash-loyalty__pct ty-mono"><?php echo (int) $summary['tier_pct']; ?>%</span>
				</div>
				<div class="typee-account-tier__bar" role="progressbar" aria-valuenow="<?php echo (int) $summary['tier_pct']; ?>" aria-valuemin="0" aria-valuemax="100">
					<span class="typee-account-tier__fill" style="width: <?php echo (int) $summary['tier_pct']; ?>%;"></span>
				</div>
				<p class="typee-account-dash-loyalty__desc">
					<?php if ( $remain_amount > 0 ) : ?>
						Chi tiêu thêm <strong><?php echo esc_html( typee_money( $remain_amount ) ); ?></strong> để đạt mốc <strong><?php echo esc_html( $summary['tier_next'] ); ?></strong>.
					<?php else : ?>
						<?php echo esc_html( $summary['tier_next'] ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<!-- 4 Chỉ số vận hành nhanh (Figma 46:5840) -->
	<div class="typee-account-metrics">
		<a class="typee-account-metric" href="<?php echo esc_url( add_query_arg( 'status', 'completed', $orders_url ) ); ?>">
			<span class="typee-account-metric__icon typee-account-metric__icon--success">
				<?php typee_the_icon( 'check_circle', 20 ); ?>
			</span>
			<div>
				<strong class="typee-account-metric__num ty-mono"><?php echo esc_html( sprintf( '%02d', isset( $summary['orders_completed'] ) ? (int) $summary['orders_completed'] : 0 ) ); ?></strong>
				<span class="typee-account-metric__label">Đơn hoàn tất</span>
			</div>
		</a>

		<a class="typee-account-metric" href="<?php echo esc_url( add_query_arg( 'status', 'processing', $orders_url ) ); ?>">
			<span class="typee-account-metric__icon typee-account-metric__icon--accent">
				<?php typee_the_icon( 'local_shipping', 20 ); ?>
			</span>
			<div>
				<strong class="typee-account-metric__num typee-account-metric__num--accent ty-mono"><?php echo esc_html( sprintf( '%02d', isset( $summary['orders_active'] ) ? (int) $summary['orders_active'] : 0 ) ); ?></strong>
				<span class="typee-account-metric__label">Đơn đang xử lý</span>
			</div>
		</a>

		<a class="typee-account-metric" href="<?php echo esc_url( $rma_url ); ?>">
			<span class="typee-account-metric__icon typee-account-metric__icon--info">
				<?php typee_the_icon( 'verified_user', 20 ); ?>
			</span>
			<div>
				<strong class="typee-account-metric__num ty-mono"><?php echo esc_html( sprintf( '%02d', isset( $summary['rma_active'] ) ? (int) $summary['rma_active'] : 0 ) ); ?></strong>
				<span class="typee-account-metric__label">Phiếu e-RMA mở</span>
			</div>
		</a>

		<a class="typee-account-metric" href="<?php echo esc_url( $builds_url ); ?>">
			<span class="typee-account-metric__icon typee-account-metric__icon--warning">
				<?php typee_the_icon( 'keyboard', 20 ); ?>
			</span>
			<div>
				<strong class="typee-account-metric__num ty-mono"><?php echo esc_html( sprintf( '%02d', isset( $summary['saved_builds_count'] ) ? (int) $summary['saved_builds_count'] : 0 ) ); ?></strong>
				<span class="typee-account-metric__label">Cấu hình đã lưu</span>
			</div>
		</a>
	</div>
</section>

<!-- Khối 2: Đơn hàng gần đây -->
<section class="typee-account-card typee-account-dash-recent">
	<div class="typee-account-section-head">
		<div class="typee-account-section-head__left">
			<span class="typee-account-section-bar" aria-hidden="true"></span>
			<h2 class="typee-account-section-title typee-account-section-title--sm">Đơn hàng gần đây</h2>
		</div>
		<a class="typee-account-more-link" href="<?php echo esc_url( $orders_url ); ?>">
			<span>Xem tất cả đơn hàng</span>
			<?php typee_the_icon( 'arrow_forward', 14 ); ?>
		</a>
	</div>

	<?php if ( ! empty( $recent_orders ) ) : ?>
		<ul class="typee-account-recent-list">
			<?php foreach ( $recent_orders as $order ) : ?>
				<?php
				$status_info = typee_account_order_display_status( $order );
				$order_date  = $order->get_date_created();
				$items_cnt   = (int) $order->get_item_count();
				?>
				<li class="typee-account-recent-item">
					<div class="typee-account-recent-item__main">
						<a class="typee-account-recent-item__id ty-mono" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
							#TY-<?php echo esc_html( $order->get_order_number() ); ?>
						</a>
						<span class="typee-account-recent-item__meta">
							<?php echo $order_date ? esc_html( $order_date->date_i18n( 'd/m/Y H:i' ) ) : ''; ?> • <?php echo (int) $items_cnt; ?> sản phẩm
						</span>
					</div>

					<span class="typee-account-status-pill typee-account-status-pill--<?php echo esc_attr( $status_info['tone'] ); ?>">
						<?php echo esc_html( $status_info['label'] ); ?>
					</span>

					<div class="typee-account-recent-item__right">
						<strong class="typee-account-recent-item__total ty-mono"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
						<a class="typee-account-btn typee-account-btn--soft" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">Chi tiết</a>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<div class="typee-account-dash-empty">
			<p>Bạn chưa có đơn hàng nào gần đây. Bắt đầu khám phá danh mục Kit CNC, Switch và Keycap tại TYPEE Studio.</p>
			<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Khám phá sản phẩm</a>
		</div>
	<?php endif; ?>
</section>

<!-- Khối 3: Lối tắt quản lý tài khoản -->
<div class="typee-account-shortcuts">
	<a class="typee-account-shortcut" href="<?php echo esc_url( $edit_account_url ); ?>">
		<span class="typee-account-shortcut__icon"><?php typee_the_icon( 'manage_accounts', 20 ); ?></span>
		<div>
			<strong>Hồ sơ cá nhân &amp; Bảo mật</strong>
			<span>Cập nhật họ tên, SĐT e-RMA, ngày sinh và đổi mật khẩu</span>
		</div>
	</a>

	<a class="typee-account-shortcut" href="<?php echo esc_url( $address_url ); ?>">
		<span class="typee-account-shortcut__icon"><?php typee_the_icon( 'location_on', 20 ); ?></span>
		<div>
			<strong>Sổ địa chỉ giao hàng</strong>
			<span>Quản lý địa chỉ thanh toán và địa chỉ nhận hàng nhanh</span>
		</div>
	</a>

	<a class="typee-account-shortcut" href="<?php echo esc_url( $builds_url ); ?>">
		<span class="typee-account-shortcut__icon"><?php typee_the_icon( 'keyboard', 20 ); ?></span>
		<div>
			<strong>Cấu hình Custom Builder</strong>
			<span>Xem lại hoặc tiếp tục chỉnh sửa các bản build 6 bước</span>
		</div>
	</a>

	<a class="typee-account-shortcut" href="<?php echo esc_url( typee_url( 'tracking' ) ); ?>">
		<span class="typee-account-shortcut__icon"><?php typee_the_icon( 'verified_user', 20 ); ?></span>
		<div>
			<strong>Tra cứu đơn &amp; Bảo hành e-RMA</strong>
			<span>Kiểm tra vận đơn và tiến trình xử lý bảo hành linh kiện</span>
		</div>
	</a>
</div>

<?php
do_action( 'woocommerce_account_dashboard' );
do_action( 'woocommerce_before_my_account' );
do_action( 'woocommerce_after_my_account' );
