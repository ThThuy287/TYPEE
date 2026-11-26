<?php
/**
 * Trang ảo chưa có template riêng (đang phát triển). Luôn có ít nhất 2 lối thoát (PRD "Zero Dead-Ends").
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();
$key   = typee_current_virtual();
$title = $key ? typee_virtual_pages()[ $key ][2] : 'Đang phát triển';
?>
<section class="ty-page ty-empty-page">
	<div class="ty-container">
		<?php typee_breadcrumb( array( array( $title, '' ) ) ); ?>
		<div class="ty-empty">
			<span class="ty-empty__ic"><?php typee_the_icon( 'construction', 28 ); ?></span>
			<h1 class="ty-empty__title"><?php echo esc_html( $title ); ?></h1>
			<p class="ty-empty__desc">Màn hình này đang được hoàn thiện.</p>
			<div class="ty-empty__actions">
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Xem sản phẩm</a>
				<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( home_url( '/' ) ); ?>">Về trang chủ</a>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
