<?php
/**
 * Cẩm nang Modding & Newbie trên trang chủ (thay khối Soundtest của Figma 1:555 – PRD §1.3 "100% Không Soundtest").
 * Lấy 3 bài viết mới nhất (bỏ danh mục mặc định). Chưa có bài viết thì tự ẩn.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$posts = get_posts(
	array(
		'post_type'        => 'post',
		'post_status'      => 'publish',
		'posts_per_page'   => 3,
		'no_found_rows'    => true,
		'category__not_in' => array( (int) get_option( 'default_category' ) ),
	)
);
if ( ! $posts ) {
	return;
}
?>
<section class="ty-guides-home" data-node="1:555">
	<div class="ty-container">
		<div class="ty-section-head">
			<div class="ty-section-head__main">
				<span class="ty-eyebrow"><?php typee_the_icon( 'handyman', 14 ); ?> THƯ VIỆN KIẾN THỨC DIY</span>
				<h2 class="ty-h2">CẨM NANG MODDING &amp; NEWBIE</h2>
				<p class="ty-section-head__desc">Hướng dẫn chọn linh kiện, cân wire stab, lube switch, tape mod và foam – kèm combo dụng cụ mua nhanh.</p>
			</div>
			<?php if ( typee_url( 'guides' ) ) : ?>
				<a class="ty-more" href="<?php echo esc_url( typee_url( 'guides' ) ); ?>">Xem tất cả cẩm nang <?php typee_the_icon( 'arrow_forward', 12 ); ?></a>
			<?php endif; ?>
		</div>

		<div class="ty-articles">
			<?php foreach ( $posts as $post_item ) : ?>
				<?php get_template_part( 'template-parts/guides/card', null, array( 'post' => $post_item ) ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
