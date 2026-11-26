<?php
/**
 * Thẻ bài viết cẩm nang (dùng ở trang chủ, /modding-guides/, tìm kiếm).
 *
 * @var array $args { post: WP_Post }
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$post_item = isset( $args['post'] ) ? get_post( $args['post'] ) : null;
if ( ! $post_item ) {
	return;
}
$cats   = get_the_category( $post_item->ID );
$cat    = $cats ? $cats[0]->name : '';
$tags   = get_the_tags( $post_item->ID );
$kicker = $tags ? $tags[0]->name : $cat;
$link   = get_permalink( $post_item );
$mins   = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( $post_item->post_content ) ) / 200 ) );
?>
<article class="ty-article">
	<a class="ty-article__media" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
		<?php
		if ( has_post_thumbnail( $post_item ) ) {
			echo get_the_post_thumbnail( $post_item, 'medium_large', array( 'class' => 'ty-article__img', 'loading' => 'lazy', 'decoding' => 'async' ) );
		}
		?>
		<?php if ( $cat ) : ?><span class="ty-article__badge"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
	</a>
	<div class="ty-article__body">
		<?php if ( $kicker ) : ?><span class="ty-article__kicker"><?php echo esc_html( mb_strtoupper( $kicker ) ); ?></span><?php endif; ?>
		<h3 class="ty-article__title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( get_the_title( $post_item ) ); ?></a></h3>
		<p class="ty-article__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_item ), 24 ) ); ?></p>
	</div>
	<div class="ty-article__foot">
		<a class="ty-article__link" href="<?php echo esc_url( $link ); ?>">Đọc hướng dẫn chi tiết <?php typee_the_icon( 'arrow_forward', 12 ); ?></a>
		<span class="ty-article__time"><?php echo esc_html( sprintf( '%d phút đọc', $mins ) ); ?></span>
	</div>
</article>
