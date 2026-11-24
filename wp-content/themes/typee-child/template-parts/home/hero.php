<?php
/**
 * Hero – Figma 1:65. Ảnh và 4 thẻ thông số lấy từ một sản phẩm thật
 * (ưu tiên sản phẩm "Nổi bật", sau đó là bàn phím Pre-built mới nhất có ảnh).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$hero = null;
$pick = wc_get_products( array( 'status' => 'publish', 'featured' => true, 'limit' => 1 ) );
if ( ! $pick ) {
	$pick = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 1,
			'category' => array( 'pre-built' ),
			'orderby'  => 'date',
			'order'    => 'DESC',
		)
	);
}
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
				<span>Âm thanh Thocky &amp; Clacky Đỉnh Cao</span>
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
				<?php if ( typee_url( 'blog' ) ) : ?>
					<a class="ty-btn ty-btn--glass" href="<?php echo esc_url( typee_url( 'blog' ) ); ?>">
						<?php typee_the_icon( 'play_circle', 18 ); ?> Nghe Soundtest mẫu <span class="ty-hero__khz">96kHz</span>
					</a>
				<?php endif; ?>
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
						<span class="ty-hero__now-ic"><?php typee_the_icon( 'graphic_eq', 16 ); ?></span>
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
					<div class="ty-bars" aria-hidden="true">
						<?php foreach ( array( 12, 24, 16, 20, 8, 16, 24, 12 ) as $h ) : ?>
							<span style="height:<?php echo (int) $h; ?>px"></span>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
