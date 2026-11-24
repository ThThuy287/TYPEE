<?php
/**
 * Đăng ký nhận tin – Figma 1:637.
 * Backend nhận email chưa có: nếu nhóm gắn form (vd. WPForms) qua filter `typee_newsletter_form`
 * thì form đó được dùng; mặc định chỉ hiển thị giao diện và báo "sắp mở".
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$custom_form = apply_filters( 'typee_newsletter_form', '' );
?>
<section class="ty-news" data-node="1:637">
	<div class="ty-container">
		<div class="ty-news__box">
			<div class="ty-news__copy">
				<span class="ty-news__pill"><?php typee_the_icon( 'mail', 14 ); ?> CỘNG ĐỒNG DÂN CHƠI PHÍM TYPEE</span>
				<h3 class="ty-news__title">Nhận mã giảm giá 10% cho đơn hàng ráp phím đầu tiên</h3>
				<p class="ty-news__desc">Đăng ký để nhận sớm thông tin mở Group Buy (GB) các mẫu Kit giới hạn, thông báo switch hot về kho và lịch Workshop mod phím offline tại Hà Nội &amp; Sài Gòn.</p>
			</div>
			<div class="ty-news__form-wrap">
				<?php if ( $custom_form ) : ?>
					<?php echo do_shortcode( $custom_form ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php else : ?>
					<form class="ty-news__form" data-typee-newsletter novalidate>
						<label class="ty-sr" for="ty-news-email">Email</label>
						<input id="ty-news-email" type="email" name="email" placeholder="Nhập email của bạn..." required />
						<button type="submit" class="ty-btn ty-btn--primary">Đăng Ký</button>
					</form>
					<p class="ty-news__msg" role="status" hidden></p>
				<?php endif; ?>
				<div class="ty-news__notes">
					<span><?php typee_the_icon( 'check_circle', 14 ); ?> Không spam</span>
					<span><?php typee_the_icon( 'check_circle', 14 ); ?> Hủy bất cứ lúc nào</span>
				</div>
			</div>
		</div>
	</div>
</section>
