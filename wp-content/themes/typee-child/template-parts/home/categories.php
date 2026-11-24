<?php
/**
 * Bento danh mục – Figma 1:173. Chỉ hiển thị danh mục có thật trong DB.
 * Ảnh: ảnh danh mục (nếu có) hoặc ảnh sản phẩm đầu tiên của danh mục.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

// Nội dung tiếp thị mặc định theo Figma; mô tả danh mục trong WP Admin (nếu có) sẽ được ưu tiên.
$defaults = apply_filters(
	'typee_home_category_copy',
	array(
		'pre-built' => array( 'badge' => 'PRE-BUILT', 'title' => 'Bàn Phím Nguyên Chiếc', 'desc' => 'Mở hộp cắm là dùng. Đã cân chỉnh stab, lót tiêu âm poron và switch mượt mà chuẩn out-of-the-box.', 'cta' => 'Khám phá %d+ model', 'accent' => true ),
		'kit'       => array( 'badge' => 'NHÔM CNC GASKET', 'title' => 'Khung Kit Barebone', 'desc' => 'Trọng lượng đầm chắc, âm gõ clack/thock tuỳ chỉnh.', 'cta' => 'Xem danh mục Barebone', 'accent' => false ),
		'switch'    => array( 'badge' => 'PRELUBED', 'title' => 'Switch Cơ Tuyển Chọn', 'desc' => 'Linear êm mượt, Tactile nảy rõ và Silent văn phòng.', 'cta' => '', 'accent' => true ),
		'keycap'    => array( 'badge' => 'PBT 1.6MM', 'title' => 'Keycap PBT Đỉnh Cao', 'desc' => 'Dyesub & Double-shot không bao giờ bóng nhờn.', 'cta' => '', 'accent' => false ),
	)
);

$cards = array();
foreach ( $defaults as $slug => $copy ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term || ! $term->count ) {
		continue;
	}
	$img_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( ! $img_id ) {
		$first = wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'category' => array( $slug ), 'return' => 'ids' ) );
		$img_id = $first ? (int) get_post_thumbnail_id( $first[0] ) : 0;
	}
	$cards[] = array(
		'term'  => $term,
		'copy'  => $copy,
		'img'   => $img_id,
		'price' => typee_category_min_price( $slug ),
		'desc'  => $term->description ? wp_strip_all_tags( $term->description ) : $copy['desc'],
	);
}
if ( ! $cards ) {
	return;
}
$total_cats = count(
	get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'fields'     => 'ids',
		)
	)
);
?>
<section class="ty-cats" data-node="1:173">
	<div class="ty-container">
		<div class="ty-section-head">
			<div class="ty-section-head__main">
				<span class="ty-eyebrow"><?php typee_the_icon( 'category', 14 ); ?> BỘ SƯU TẬP LINH KIỆN CƠ HỌC</span>
				<h2 class="ty-h2">DANH MỤC TUYỂN CHỌN</h2>
				<p class="ty-section-head__desc">Đầy đủ module từ bàn phím build sẵn, Kit nhôm cao cấp đến phụ kiện tinh chỉnh âm thanh.</p>
			</div>
			<?php if ( typee_url( 'shop' ) ) : ?>
				<a class="ty-more" href="<?php echo esc_url( typee_url( 'shop' ) ); ?>"><?php echo esc_html( sprintf( 'Xem tất cả %d danh mục', $total_cats ) ); ?> <?php typee_the_icon( 'arrow_forward', 12 ); ?></a>
			<?php endif; ?>
		</div>

		<div class="ty-bento ty-bento--<?php echo (int) count( $cards ); ?>">
			<?php foreach ( $cards as $i => $card ) : ?>
				<?php
				$big  = $i < 2;
				$link = get_term_link( $card['term'] );
				?>
				<a class="ty-bento__card <?php echo $big ? 'is-big' : 'is-small'; ?>" href="<?php echo esc_url( is_wp_error( $link ) ? '#' : $link ); ?>">
					<?php
					if ( $card['img'] ) {
						echo wp_get_attachment_image( $card['img'], 'large', false, array( 'class' => 'ty-bento__img', 'loading' => 'lazy', 'alt' => '' ) );
					}
					?>
					<span class="ty-bento__shade" aria-hidden="true"></span>
					<div class="ty-bento__top">
						<span class="ty-bento__badge<?php echo $card['copy']['accent'] ? ' is-accent' : ''; ?>"><?php echo esc_html( $card['copy']['badge'] ); ?></span>
						<?php if ( $card['price'] > 0 ) : ?>
							<span class="ty-bento__price"><?php echo esc_html( 'Từ ' . typee_money( $card['price'] ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="ty-bento__bottom">
						<h3 class="ty-bento__title"><?php echo esc_html( $card['copy']['title'] ); ?></h3>
						<p class="ty-bento__desc"><?php echo esc_html( $card['desc'] ); ?></p>
						<?php if ( $big ) : ?>
							<span class="ty-bento__cta">
								<?php
								$cta = $card['copy']['cta'] ? $card['copy']['cta'] : 'Xem danh mục';
								echo esc_html( false !== strpos( $cta, '%d' ) ? sprintf( $cta, $card['term']->count ) : $cta );
								?>
								<?php typee_the_icon( 'arrow_forward', 14 ); ?>
							</span>
						<?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
