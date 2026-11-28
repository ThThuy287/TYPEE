<?php
/**
 * My Account page – Bộ khung Layout Shell cố định dùng chung cho MỌI tab Tài khoản TYPEE.
 *
 * Cố định 100% trên tất cả endpoint:
 * - Chiều rộng container (`--ty-container`: 1280px)
 * - Vị trí thanh Breadcrumb
 * - Grid 2 cột cố định: Sidebar trái 300px + Vùng nội dung chính bên phải (`minmax(0, 1fr)`)
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package typee-child
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

$current_endpoint = function_exists( 'typee_account_current_endpoint' ) ? typee_account_current_endpoint() : 'dashboard';
$current_label    = function_exists( 'typee_account_endpoint_breadcrumb_label' ) ? typee_account_endpoint_breadcrumb_label( $current_endpoint ) : 'Tổng quan tài khoản';
$account_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/tai-khoan/' );
?>

<div class="typee-account-shell" data-endpoint="<?php echo esc_attr( $current_endpoint ); ?>">
	<div class="typee-account-container">

		<!-- Breadcrumb cố định vị trí trên mọi tab Tài khoản -->
		<nav class="typee-account-breadcrumb" aria-label="Breadcrumb">
			<ol class="typee-account-breadcrumb__list">
				<li class="typee-account-breadcrumb__item">
					<a class="typee-account-breadcrumb__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">TYPEE Studio</a>
				</li>
				<li class="typee-account-breadcrumb__sep" aria-hidden="true">
					<?php typee_the_icon( 'chevron_right', 14 ); ?>
				</li>
				<li class="typee-account-breadcrumb__item">
					<?php if ( 'dashboard' === $current_endpoint ) : ?>
						<span class="typee-account-breadcrumb__current" aria-current="page">Tài khoản thành viên</span>
					<?php else : ?>
						<a class="typee-account-breadcrumb__link" href="<?php echo esc_url( $account_url ); ?>">Tài khoản thành viên</a>
					<?php endif; ?>
				</li>
				<?php if ( 'dashboard' !== $current_endpoint ) : ?>
					<li class="typee-account-breadcrumb__sep" aria-hidden="true">
						<?php typee_the_icon( 'chevron_right', 14 ); ?>
					</li>
					<li class="typee-account-breadcrumb__item">
						<span class="typee-account-breadcrumb__current" aria-current="page"><?php echo esc_html( $current_label ); ?></span>
					</li>
				<?php endif; ?>
			</ol>
		</nav>

		<!-- Nút mở danh mục tài khoản trên Mobile / Tablet -->
		<div class="typee-account-mobile-bar">
			<button
				type="button"
				class="typee-account-mobile-toggle"
				data-typee-account-nav-toggle
				aria-expanded="false"
				aria-controls="typee-account-sidebar"
			>
				<span class="typee-account-mobile-toggle__left">
					<?php typee_the_icon( 'manage_accounts', 18 ); ?>
					<span>Danh mục tài khoản: <strong><?php echo esc_html( $current_label ); ?></strong></span>
				</span>
				<?php typee_the_icon( 'keyboard_arrow_down', 20, 'typee-account-mobile-toggle__caret' ); ?>
			</button>
		</div>

		<!-- Grid 2 cột cố định: Sidebar trái 300px | Nội dung chính phải -->
		<div class="typee-account-grid">
			<aside class="typee-account-sidebar" id="typee-account-sidebar" aria-label="Điều hướng tài khoản">
				<?php
				/**
				 * My Account navigation.
				 *
				 * @since 2.6.0
				 */
				do_action( 'woocommerce_account_navigation' );
				?>
			</aside>

			<section class="typee-account-main" aria-label="<?php echo esc_attr( $current_label ); ?>">
				<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
					<div class="typee-account-notices">
						<?php wc_print_notices(); ?>
					</div>
				<?php endif; ?>

				<div class="typee-account-content woocommerce-MyAccount-content">
					<?php
					/**
					 * My Account content.
					 *
					 * @since 2.6.0
					 */
					do_action( 'woocommerce_account_content' );
					?>
				</div>
			</section>
		</div>

	</div>
</div>
