<?php
/**
 * Soundtest Center & Cẩm nang – Figma 1:555. Lấy 3 bài viết mới nhất trong WordPress.
 * Chưa có bài viết thì section tự ẩn.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$posts = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'no_found_rows'  => true,
		// Bỏ bài nằm trong danh mục mặc định (vd. bài mẫu "Chào tất cả mọi người!").
		'category__not_in' => array( (int) get_option( 'default_category' ) ),
	)
);
if ( ! $posts ) {
	return;
}
?>
<section class="ty-sound" data-node="1:555">
	<div class="ty-container">
		<div class="ty-section-head">
			<div class="ty-section-head__main">
				<span class="ty-eyebrow"><?php typee_the_icon( 'hearing', 14 ); ?> PHÒNG THU ÂM HỌC CƠ HỌC</span>
				<h2 class="ty-h2">SOUNDTEST CENTER &amp; CẨM NANG MOD PHÍM</h2>
				<p class="ty-section-head__desc">Nghe trực tiếp âm thanh thực tế thu bằng micro Shure SM7B trong phòng cách âm chuyên dụng của TYPEE.</p>
			</div>
			<?php if ( typee_url( 'blog' ) ) : ?>
				<a class="ty-more" href="<?php echo esc_url( typee_url( 'blog' ) ); ?>">Khám phá thêm bài test âm <?php typee_the_icon( 'arrow_forward', 12 ); ?></a>
			<?php endif; ?>
		</div>

		<div class="ty-articles">
			<?php foreach ( $posts as $post_item ) : ?>
				<?php
				$cats  = get_the_category( $post_item->ID );
				$cat   = $cats ? $cats[0]->name : '';
				$tags  = get_the_tags( $post_item->ID );
				$kicker = $tags ? $tags[0]->name : $cat;
				?>
				<article class="ty-article">
					<a class="ty-article__media" href="<?php echo esc_url( get_permalink( $post_item ) ); ?>" tabindex="-1" aria-hidden="true">
						<?php
						if ( has_post_thumbnail( $post_item ) ) {
							echo get_the_post_thumbnail( $post_item, 'medium_large', array( 'class' => 'ty-article__img', 'loading' => 'lazy' ) );
						}
						?>
						<?php if ( $cat ) : ?><span class="ty-article__badge"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
					</a>
					<div class="ty-article__body">
						<?php if ( $kicker ) : ?><span class="ty-article__kicker"><?php echo esc_html( mb_strtoupper( $kicker ) ); ?></span><?php endif; ?>
						<h3 class="ty-article__title"><a href="<?php echo esc_url( get_permalink( $post_item ) ); ?>"><?php echo esc_html( get_the_title( $post_item ) ); ?></a></h3>
						<p class="ty-article__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_item ), 24 ) ); ?></p>
					</div>
					<div class="ty-article__foot">
						<a class="ty-article__link" href="<?php echo esc_url( get_permalink( $post_item ) ); ?>">Đọc bài hướng dẫn chi tiết <?php typee_the_icon( 'arrow_forward', 12 ); ?></a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
