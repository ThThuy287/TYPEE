<?php
/**
 * Footer – Figma node 1:663 (Footer).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$cats = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
	)
);
?>

</main>

<footer id="footer" class="ty-footer" data-node="1:663">
	<div class="ty-container">
		<div class="ty-footer__grid">
			<div class="ty-footer__col ty-footer__about">
				<div class="ty-footer__brand">TYPEE</div>
				<p class="ty-footer__desc">Studio cơ học chuyên phân phối bàn phím custom cao cấp, kit nhôm CNC nguyên khối, switch mượt mà và keycap chính hãng.</p>
				<ul class="ty-footer__contact">
					<li><strong>Hà Nội:</strong> <?php echo esc_html( typee_copy( 'address_hn' ) ); ?></li>
					<li><strong>TP.HCM:</strong> <?php echo esc_html( typee_copy( 'address_hcm' ) ); ?></li>
					<li><strong>Hotline:</strong> <?php echo esc_html( typee_copy( 'hotline_note' ) ); ?></li>
				</ul>
			</div>

			<div class="ty-footer__col">
				<h3 class="ty-footer__title">DANH MỤC SẢN PHẨM</h3>
				<ul class="ty-footer__list">
					<?php if ( ! is_wp_error( $cats ) ) : ?>
						<?php foreach ( $cats as $cat ) : ?>
							<li><a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></li>
						<?php endforeach; ?>
					<?php endif; ?>
					<li><?php typee_link( 'builder', 'Công cụ Custom Builder online' ); ?></li>
				</ul>
			</div>

			<div class="ty-footer__col">
				<h3 class="ty-footer__title">HỖ TRỢ KHÁCH HÀNG</h3>
				<ul class="ty-footer__list">
					<li><?php typee_link( 'tracking', 'Tra cứu đơn hàng tự động' ); ?></li>
					<li><?php typee_link( 'faq', 'Hướng dẫn chọn linh kiện cho Newbie' ); ?></li>
					<li><?php typee_link( 'warranty', 'Gửi yêu cầu Bảo hành & Đổi trả (RMA)' ); ?></li>
					<li><?php typee_link( 'blog', 'Soundtest Center' ); ?></li>
					<li><?php typee_link( 'blog', 'Quy trình Mod & Lube switch tiêu chuẩn' ); ?></li>
				</ul>
			</div>

			<div class="ty-footer__col">
				<h3 class="ty-footer__title">CHÍNH SÁCH &amp; KẾT NỐI</h3>
				<ul class="ty-footer__policy">
					<li><?php typee_the_icon( 'verified', 16 ); ?> Bảo hành 12 tháng Kit / 6 tháng PCB</li>
					<li><?php typee_the_icon( 'autorenew', 14 ); ?> Đổi trả 7 ngày linh hoạt</li>
					<li><?php typee_the_icon( 'local_shipping', 16 ); ?> Vận chuyển hoả tốc GHTK toàn quốc</li>
				</ul>
				<?php
				// Tên các phương thức thanh toán đang bật trong WooCommerce (không hardcode như bản Figma).
				$gateways = ( function_exists( 'WC' ) && WC()->payment_gateways() ) ? WC()->payment_gateways()->payment_gateways() : array();
				$gateways = array_filter( $gateways, function ( $gw ) {
					return 'yes' === $gw->enabled;
				} );
				if ( $gateways ) :
					?>
					<div class="ty-footer__pay">
						<div class="ty-footer__pay-title">CỔNG THANH TOÁN AN TOÀN</div>
						<div class="ty-footer__pay-list">
							<?php foreach ( $gateways as $gw ) : ?>
								<span class="ty-footer__pay-chip"><?php echo esc_html( wp_strip_all_tags( $gw->get_title() ) ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="ty-footer__bottom">
			<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> TYPEE Mechanical Keyboard Studio. All rights reserved.</p>
			<nav class="ty-footer__legal" aria-label="Pháp lý">
				<?php typee_link( 'terms', 'Điều khoản dịch vụ' ); ?>
				<?php typee_link( 'privacy', 'Chính sách bảo mật' ); ?>
				<a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>">Sitemap</a>
			</nav>
		</div>
	</div>
</footer>

</div><!-- #wrapper -->

<?php wp_footer(); ?>

</body>
</html>
