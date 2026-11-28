<?php
/**
 * My Account navigation – Sidebar điều hướng cố định dùng chung cho toàn bộ trang Tài khoản TYPEE.
 * Tham chiếu Figma: Node 82:7135 (User Profile Card + Vertical Menu + Specialist Card).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$summary    = function_exists( 'typee_account_user_summary' ) ? typee_account_user_summary() : array();
$user_id    = get_current_user_id();
$menu_items = function_exists( 'wc_get_account_menu_items' ) ? wc_get_account_menu_items() : array();
$logout_url = function_exists( 'wc_logout_url' ) ? wc_logout_url() : wp_logout_url( home_url( '/' ) );

do_action( 'woocommerce_before_account_navigation' );
?>

<div class="typee-account-sidebar-stack">

	<!-- Khối 1: Thẻ hồ sơ thành viên & tiến độ hạng tích lũy -->
	<div class="typee-account-profile">
		<div class="typee-account-profile__head">
			<div class="typee-account-profile__avatar">
				<?php
				echo get_avatar(
					$user_id,
					112,
					'',
					isset( $summary['display_name'] ) ? $summary['display_name'] : '',
					array( 'class' => 'typee-account-profile__avatar-img' )
				);
				?>
				<span class="typee-account-profile__status-dot" title="Thành viên đang hoạt động" aria-hidden="true"></span>
			</div>

			<div class="typee-account-profile__meta">
				<h2 class="typee-account-profile__name">
					<?php echo esc_html( isset( $summary['display_name'] ) ? $summary['display_name'] : '' ); ?>
				</h2>
				<p class="typee-account-profile__email" title="<?php echo esc_attr( isset( $summary['email'] ) ? $summary['email'] : '' ); ?>">
					<?php echo esc_html( isset( $summary['email'] ) ? $summary['email'] : '' ); ?>
				</p>
				<span class="typee-account-profile__code ty-mono">
					<?php echo esc_html( isset( $summary['member_code'] ) ? $summary['member_code'] : '' ); ?>
				</span>
			</div>
		</div>

		<?php if ( ! empty( $summary ) ) : ?>
			<div class="typee-account-tier">
				<div class="typee-account-tier__top">
					<span class="typee-account-tier__name">
						<?php typee_the_icon( 'verified', 15, 'typee-account-tier__icon' ); ?>
						<span><?php echo esc_html( $summary['tier_name'] ); ?></span>
					</span>
					<span class="typee-account-tier__pct ty-mono"><?php echo (int) $summary['tier_pct']; ?>%</span>
				</div>
				<div
					class="typee-account-tier__bar"
					role="progressbar"
					aria-valuenow="<?php echo (int) $summary['tier_pct']; ?>"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-label="Tiến độ tích lũy hạng thành viên"
				>
					<span class="typee-account-tier__fill" style="width: <?php echo (int) $summary['tier_pct']; ?>%;"></span>
				</div>
				<p class="typee-account-tier__note">
					<?php if ( (int) $summary['tier_pct'] >= 100 ) : ?>
						<strong><?php echo esc_html( typee_money( $summary['total_spent'] ) ); ?></strong> — <?php echo esc_html( $summary['tier_next'] ); ?>
					<?php else : ?>
						<strong><?php echo esc_html( typee_money( $summary['total_spent'] ) ); ?></strong>
						<span> / <?php echo esc_html( typee_money( $summary['tier_target'] ) ); ?> tới <?php echo esc_html( $summary['tier_next'] ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Khối 2: Menu điều hướng dọc cố định -->
	<nav class="woocommerce-MyAccount-navigation typee-account-nav" aria-label="<?php esc_attr_e( 'Account pages', 'woocommerce' ); ?>">
		<ul class="typee-account-nav__list">
			<?php foreach ( $menu_items as $endpoint => $label ) : ?>
				<?php
				if ( 'customer-logout' === $endpoint ) {
					continue;
				}
				$is_active = function_exists( 'typee_account_is_endpoint_active' ) ? typee_account_is_endpoint_active( $endpoint ) : wc_is_current_account_menu_item( $endpoint );
				$icon_name = function_exists( 'typee_account_endpoint_icon' ) ? typee_account_endpoint_icon( $endpoint ) : 'chevron_right';
				$badge     = function_exists( 'typee_account_menu_badge' ) ? typee_account_menu_badge( $endpoint, $summary ) : array( 'text' => '', 'tone' => '', 'attr' => '' );
				$item_cls  = trim( wc_get_account_menu_item_classes( $endpoint ) . ' typee-account-nav__item' . ( $is_active ? ' is-active typee-account-nav__item--active' : '' ) );
				$url       = wc_get_account_endpoint_url( $endpoint );
				?>
				<li class="<?php echo esc_attr( $item_cls ); ?>">
					<a
						class="typee-account-nav__link"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; ?>
					>
						<span class="typee-account-nav__label-group">
							<?php typee_the_icon( $icon_name, 18, 'typee-account-nav__icon' ); ?>
							<span class="typee-account-nav__text"><?php echo esc_html( $label ); ?></span>
						</span>

						<?php if ( '' !== $badge['text'] || '' !== $badge['attr'] ) : ?>
							<span
								class="typee-account-nav__badge<?php echo 'accent' === $badge['tone'] ? ' typee-account-nav__badge--accent' : ''; ?>"
								<?php echo $badge['attr']; // phpcs:ignore WordPress.Security.EscapeOutput -- hằng số data-attr nội bộ. ?>
								<?php echo ( '' === $badge['text'] && '' !== $badge['attr'] ) ? 'hidden' : ''; ?>
							>
								<?php echo esc_html( $badge['text'] ); ?>
							</span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<!-- Nút Đăng xuất cố định ở cuối danh sách điều hướng -->
		<div class="typee-account-nav__footer">
			<a class="typee-account-nav__logout" href="<?php echo esc_url( $logout_url ); ?>">
				<?php typee_the_icon( 'logout', 18, 'typee-account-nav__logout-icon' ); ?>
				<span>Đăng xuất tài khoản</span>
			</a>
		</div>
	</nav>

	<!-- Khối 3: Thẻ Hỗ trợ Kỹ thuật & Modding Workshop (Figma 82:7222) -->
	<div class="typee-account-support">
		<div class="typee-account-support__head">
			<span class="typee-account-support__eyebrow">KỸ THUẬT VIÊN PHỤ TRÁCH</span>
			<span class="typee-account-support__dot" aria-hidden="true"></span>
		</div>
		<div class="typee-account-support__body">
			<div class="typee-account-support__avatar" aria-hidden="true">
				<?php typee_the_icon( 'support_agent', 22 ); ?>
			</div>
			<div class="typee-account-support__info">
				<h3 class="typee-account-support__name">TYPEE Modding Lab</h3>
				<p class="typee-account-support__role">Master Modding &amp; Lube Studio</p>
				<a class="typee-account-support__phone ty-mono" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', typee_copy( 'hotline' ) ) ); ?>">
					<?php echo esc_html( typee_copy( 'hotline' ) ); ?> (#402)
				</a>
			</div>
		</div>
		<div class="typee-account-support__actions">
			<a class="typee-account-support__btn" href="<?php echo esc_url( typee_url( 'tracking' ) ); ?>">
				<?php typee_the_icon( 'search', 14 ); ?>
				<span>Tra cứu RMA</span>
			</a>
			<a class="typee-account-support__btn" href="<?php echo esc_url( typee_url( 'rma_new' ) ); ?>">
				<?php typee_the_icon( 'handyman', 14 ); ?>
				<span>Gửi e-RMA</span>
			</a>
		</div>
	</div>

</div>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
