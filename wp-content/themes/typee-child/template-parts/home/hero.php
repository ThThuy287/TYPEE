<?php
/**
 * Hero – Figma 1:65. Ảnh và 4 thẻ thông số lấy từ một sản phẩm thật
 * (ưu tiên sản phẩm "Nổi bật", sau đó là bàn phím Pre-built mới nhất có ảnh).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$hero = null;
$pick = typee_products_by_ids(
	typee_cached_ids(
		'hero',
		function () {
			$ids = wc_get_products( array( 'status' => 'publish', 'featured' => true, 'limit' => 1, 'return' => 'ids' ) );
			if ( ! $ids ) {
				$ids = wc_get_products(
					array(
						'status'   => 'publish',
						'limit'    => 1,
						'category' => array( 'pre-built' ),
						'orderby'  => 'date',
						'order'    => 'DESC',
						'return'   => 'ids',
					)
				);
			}
			return $ids;
		}
	)
);
$hero  = $pick ? $pick[0] : null;
$specs = $hero ? array_slice( typee_product_specs( $hero ), 0, 4 ) : array();
$count = (int) wp_count_posts( 'product' )->publish;

$tag_icons = array( 'keyboard', 'hub', 'layers', 'shield' );
$tag_pos   = array( 'is-tl', 'is-tr', 'is-bl', 'is-br' );
?>
<section class="ty-hero" data-node="1:65">
	<span class="ty-hero__glow ty-hero__glow--a" aria-hidden="true"></span>
	<span class="ty-hero__glow ty-hero__glow--b" aria-hidden="true"></span>
	<div class="ty-container ty-hero__grid">
		<div class="ty-hero__copy">
			<div class="ty-hero__pill">
				<span class="ty-dot"></span>
				<span class="ty-hero__pill-strong">TYPEE STUDIO EDITION V3.4</span>
				<span class="ty-hero__pill-sep" aria-hidden="true">|</span>
				<span>Xưởng cơ khí số – Digital Crafting Lab</span>
			</div>
			<h1 class="ty-hero__title">
				STUDIO BÀN PHÍM CƠ <span class="ty-grad">&amp;</span><br>
				<span class="ty-grad">TỰ RÁP</span> THEO CHẤT RIÊNG
			</h1>
			<p class="ty-hero__lead">
				<?php
				printf(
					/* translators: %s: số sản phẩm */
					esc_html__( 'Khám phá hơn %s mẫu Kit nhôm CNC nguyên khối, Switch cơ học đã lube sẵn bằng mỡ Krytox 205g0 và Keycap PBT chuẩn profile cherry cao cấp nhất thị trường.', 'typee-child' ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</p>
			<div class="ty-hero__actions">
				<?php if ( typee_url( 'builder' ) ) : ?>
					<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">
						<?php typee_the_icon( 'tune', 15 ); ?> Ráp phím ngay (Custom Builder) <?php typee_the_icon( 'arrow_forward', 12 ); ?>
					</a>
				<?php endif; ?>
				<a class="ty-btn ty-btn--glass" href="<?php echo esc_url( typee_url( 'compare' ) ); ?>">
					<?php typee_the_icon( 'compare_arrows', 18 ); ?> So sánh linh kiện <span class="ty-hero__khz">4 cột</span>
				</a>
			</div>
			<ul class="ty-hero__trust">
				<li><span class="ty-hero__trust-ic"><?php typee_the_icon( 'handyman', 14 ); ?></span>Cân Lube Stab<br>Free</li>
				<li><span class="ty-hero__trust-ic"><?php typee_the_icon( 'memory', 14 ); ?></span>Hotswap 5-Pin<br>Chuẩn</li>
				<li><span class="ty-hero__trust-ic"><?php typee_the_icon( 'bolt', 14 ); ?></span>Giao Hoả Tốc 2H</li>
				<li><span class="ty-hero__trust-ic"><?php typee_the_icon( 'verified_user', 14 ); ?></span>Bảo Hành 12<br>Tháng</li>
			</ul>
		</div>

		<?php if ( $hero ) : ?>
			<div class="ty-hero__card">
				<a class="ty-hero__media" href="<?php echo esc_url( $hero->get_permalink() ); ?>">
					<?php echo typee_product_image( $hero, 'large', array( 'class' => 'ty-hero__img', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php foreach ( $specs as $i => $spec ) : ?>
						<span class="ty-hero__tag <?php echo esc_attr( $tag_pos[ $i ] ); ?>">
							<?php if ( 0 === $i ) : ?>
								<span class="ty-dot"></span>
							<?php else : ?>
								<?php typee_the_icon( $tag_icons[ $i ], 12 ); ?>
							<?php endif; ?>
							<?php echo esc_html( $spec['label'] . ': ' . $spec['value'] ); ?>
						</span>
					<?php endforeach; ?>
				</a>
				<div class="ty-hero__now">
					<div class="ty-hero__now-main">
						<span class="ty-hero__now-ic"><?php typee_the_icon( 'keyboard', 16 ); ?></span>
						<div>
							<a class="ty-hero__now-title" href="<?php echo esc_url( $hero->get_permalink() ); ?>"><?php echo esc_html( $hero->get_name() ); ?></a>
							<div class="ty-hero__now-sub">
								<?php
								$sub = array();
								if ( $hero->get_sku() ) {
									$sub[] = 'SKU ' . $hero->get_sku();
								}
								if ( (float) $hero->get_price() > 0 ) {
									$sub[] = typee_money( wc_get_price_to_display( $hero ) );
								}
								echo esc_html( implode( ' • ', $sub ) );
								?>
							</div>
						</div>
					</div>
					<a class="ty-hero__now-cta" href="<?php echo esc_url( $hero->get_permalink() ); ?>">Xem chi tiết <?php typee_the_icon( 'arrow_forward', 14 ); ?></a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
