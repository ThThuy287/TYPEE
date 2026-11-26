<?php
/**
 * Custom Builder teaser – Figma 1:463 (nội dung giới thiệu tĩnh, dẫn tới công cụ Builder).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$steps = array(
	array( 'mobile', '1. Chọn Khung Kit', 'Layout 65%, 75%, TKL, Alice hoặc Fullsize nhôm CNC.' ),
	array( 'layers', '2. Chọn Plate', 'FR4 nảy âm ấm, PC mềm dẻo hoặc Nhôm/Đồng đanh clack.' ),
	array( 'bubble_chart', '3. Chọn Switch', 'Linear, Tactile, Silent – tự kiểm tra chân 3-pin / 5-pin với mạch.' ),
	array( 'keyboard', '4. Chọn Keycap', 'Phối bảng màu PBT Cherry, OEM hoặc MOA thời thượng.' ),
	array( 'science', '5. Dịch Vụ Mod', 'Tape-mod PCB, cân wire stab thần sầu, dán poron switchpad.' ),
	array( 'verified', '6. Hoàn Tất & Giao', 'Kiểm định KCS 100% phím, gửi ảnh nghiệm thu trước khi ship.' ),
);
?>
<section class="ty-teaser" data-node="1:463">
	<span class="ty-teaser__glow" aria-hidden="true"></span>
	<div class="ty-container">
		<div class="ty-section-head">
			<div class="ty-section-head__main">
				<span class="ty-teaser__pill"><?php typee_the_icon( 'tune', 12 ); ?> QUY TRÌNH TỰ RÁP PHÍM ĐỘC QUYỀN</span>
				<h2 class="ty-h2 ty-teaser__title">CUSTOM BUILDER TOOL ONLINE</h2>
				<p class="ty-teaser__desc">Tự tay phối màu Case, chọn chất liệu Plate, cắm switch yêu thích và phối bộ keycap ưng ý. Hệ thống tự động kiểm tra tương thích chân switch 3-pin / 5-pin và layout phím 100%.</p>
			</div>
			<?php if ( typee_url( 'builder' ) ) : ?>
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">Mở Custom Builder Tool <?php typee_the_icon( 'open_in_new', 14 ); ?></a>
			<?php endif; ?>
		</div>
		<ol class="ty-steps">
			<?php foreach ( $steps as $i => $s ) : ?>
				<li class="ty-steps__item<?php echo 5 === $i ? ' is-final' : ''; ?>">
					<div class="ty-steps__top">
						<span class="ty-steps__num"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
						<?php typee_the_icon( $s[0], 18 ); ?>
					</div>
					<div class="ty-steps__text">
						<h4 class="ty-steps__title"><?php echo esc_html( $s[1] ); ?></h4>
						<p class="ty-steps__desc"><?php echo esc_html( $s[2] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
