<?php
/**
 * Khung trang Checkout & Order Received (ghi đè Flatsome checkout/layouts/checkout.php)
 * Thiết kế tham chiếu: Figma 82:1187 (Checkout) & 82:8375 (Order Received).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$is_received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );

get_header();
?>
<div class="ty-co-page" data-node="<?php echo $is_received ? '82:8375' : '82:1187'; ?>">
	<!-- Top Progress & Breadcrumb Ribbon (Figma 82:1190) -->
	<section class="ty-co-ribbon" aria-label="Tiến trình đặt hàng">
		<div class="ty-container ty-co-ribbon__inner">
			<div class="ty-co-ribbon__left">
				<a class="ty-co-ribbon__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="TYPEE – Trang chủ">
					<img
						src="<?php echo esc_url( TYPEE_CHILD_URI . '/assets/images/logo-wordmark.png' ); ?>"
						alt="TYPEE"
						width="88"
						height="20"
						loading="eager"
						decoding="async"
					/>
				</a>
				<span class="ty-co-ribbon__div" aria-hidden="true"></span>
				<nav class="ty-co-bc" aria-label="Breadcrumb">
					<ol>
						<li>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
								<?php typee_the_icon( 'home_pin', 14 ); ?>
								<span>Trang chủ</span>
							</a>
						</li>
						<li class="ty-co-bc__sep" aria-hidden="true">/</li>
						<li><a href="<?php echo esc_url( typee_url( 'cart' ) ); ?>">Giỏ hàng</a></li>
						<li class="ty-co-bc__sep" aria-hidden="true">/</li>
						<?php if ( $is_received ) : ?>
							<li><a href="<?php echo esc_url( typee_url( 'checkout' ) ); ?>">Thanh toán</a></li>
							<li class="ty-co-bc__sep" aria-hidden="true">/</li>
							<li aria-current="page">Đặt hàng thành công</li>
						<?php else : ?>
							<li aria-current="page">Thanh toán đơn hàng</li>
						<?php endif; ?>
					</ol>
				</nav>
			</div>

			<ol class="ty-co-steps" aria-label="Các bước thanh toán">
				<li class="ty-co-step is-done">
					<a class="ty-co-step__link" href="<?php echo esc_url( typee_url( 'cart' ) ); ?>">
						<span class="ty-co-step__dot"><?php typee_the_icon( 'check', 13 ); ?></span>
						<span class="ty-co-step__label">1. Giỏ hàng</span>
					</a>
				</li>
				<li class="ty-co-step__line is-done" aria-hidden="true"></li>
				<li class="ty-co-step <?php echo $is_received ? 'is-done' : 'is-active'; ?>">
					<span class="ty-co-step__dot">
						<?php if ( $is_received ) : ?>
							<?php typee_the_icon( 'check', 13 ); ?>
						<?php else : ?>
							2
						<?php endif; ?>
					</span>
					<span class="ty-co-step__label">2. Thông tin &amp; Thanh toán</span>
				</li>
				<li class="ty-co-step__line <?php echo $is_received ? 'is-done' : ''; ?>" aria-hidden="true"></li>
				<li class="ty-co-step <?php echo $is_received ? 'is-active' : ''; ?>">
					<span class="ty-co-step__dot">3</span>
					<span class="ty-co-step__label">3. Hoàn tất</span>
				</li>
			</ol>
		</div>
	</section>

	<div class="ty-container ty-co-shell">
		<?php
		while ( have_posts() ) :
			the_post();
			wc_print_notices();
			// Luôn chạy qua shortcode chuẩn của WooCommerce để hiển thị form-checkout.php / thankyou.php đồng nhất.
			echo do_shortcode( '[woocommerce_checkout]' );
		endwhile;
		?>
	</div>
</div>
<?php
get_footer();
