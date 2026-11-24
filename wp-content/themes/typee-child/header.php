<?php
/**
 * Header – Figma node 1:746 (Header).
 * Giữ nguyên các action của Flatsome/WordPress, chỉ thay phần markup header.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$suggest = array();
if ( function_exists( 'wc_get_products' ) ) {
	$suggest = wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => 4,
			'orderby' => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
			'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}
$nav = array(
	array( 'key' => 'home', 'text' => 'Trang chủ' ),
	array( 'key' => 'builder', 'text' => 'Custom Builder', 'badge' => 'HOT' ),
	array( 'key' => 'prebuilt', 'text' => 'Bàn phím nguyên chiếc' ),
	array( 'key' => 'kit', 'text' => 'Khung Kit (Barebone)' ),
	array( 'key' => 'switch', 'text' => 'Switch' ),
	array( 'key' => 'keycap', 'text' => 'Keycap & Phụ kiện' ),
	array( 'key' => 'blog', 'text' => 'Cẩm nang & Soundtest' ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="<?php flatsome_html_classes(); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="http://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<?php do_action( 'flatsome_after_body_open' ); ?>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'flatsome' ); ?></a>

<div id="wrapper">

	<?php do_action( 'flatsome_before_header' ); ?>

	<header id="header" class="ty-header" data-node="1:746">
		<!-- Thanh trên cùng -->
		<div class="ty-topbar">
			<div class="ty-container ty-topbar__inner">
				<div class="ty-topbar__promo">
					<span class="ty-topbar__badge"><?php echo esc_html( typee_copy( 'promo_label' ) ); ?></span>
					<p class="ty-topbar__text">
						<?php echo esc_html( typee_copy( 'promo_text' ) ); ?><strong><?php echo esc_html( typee_copy( 'promo_code' ) ); ?></strong><?php echo esc_html( typee_copy( 'promo_suffix' ) ); ?>
					</p>
				</div>
				<nav class="ty-topbar__links" aria-label="Hỗ trợ">
					<?php typee_link( 'faq', 'Hướng dẫn Newbie (FAQ)', 'ty-topbar__link' ); ?>
					<?php typee_link( 'warranty', 'Chính sách Bảo hành & RMA', 'ty-topbar__link' ); ?>
					<?php typee_link( 'tracking', 'Tra cứu đơn hàng', 'ty-topbar__link' ); ?>
					<a class="ty-topbar__hotline" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', typee_copy( 'hotline' ) ) ); ?>">
						<?php typee_the_icon( 'call', 12 ); ?>
						<span><?php echo esc_html( typee_copy( 'hotline' ) ); ?></span>
					</a>
				</nav>
			</div>
		</div>

		<!-- Thanh chính -->
		<div class="ty-mainbar">
			<div class="ty-container ty-mainbar__inner">
				<a class="ty-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php
					if ( has_custom_logo() ) {
						$logo_id = get_theme_mod( 'custom_logo' );
						echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'ty-brand__logo', 'alt' => get_bloginfo( 'name' ) ) );
					}
					?>
					<span class="ty-brand__text">
						<span class="ty-brand__name">TYPEE</span>
						<span class="ty-brand__tag"><?php echo esc_html( typee_copy( 'brand_tagline' ) ); ?></span>
					</span>
				</a>

				<div class="ty-search">
					<form role="search" method="get" class="ty-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
						<label class="ty-sr" for="ty-search-input">Tìm kiếm sản phẩm</label>
						<?php typee_the_icon( 'search', 16, 'ty-search__icon' ); ?>
						<input id="ty-search-input" class="ty-search__input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Tìm tên Kit, Switch, Keycap hoặc mã SKU..." autocomplete="off" />
						<input type="hidden" name="post_type" value="product" />
						<button class="ty-search__btn" type="submit">Tìm kiếm</button>
					</form>
					<?php if ( $suggest ) : ?>
						<div class="ty-search__suggest">
							<span class="ty-search__suggest-label">Gợi ý:</span>
							<?php foreach ( $suggest as $i => $sp ) : ?>
								<?php if ( $i ) : ?><span class="ty-search__dot" aria-hidden="true">•</span><?php endif; ?>
								<a href="<?php echo esc_url( $sp->get_permalink() ); ?>"><?php echo esc_html( wp_trim_words( $sp->get_name(), 3, '' ) ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="ty-actions">
					<a class="ty-account" href="<?php echo esc_url( typee_url( 'account' ) ); ?>">
						<span class="ty-account__avatar"><?php typee_the_icon( 'person', 14 ); ?></span>
						<span class="ty-account__text">
							<span class="ty-account__hello">Xin chào,</span>
							<span class="ty-account__name">
								<?php
								if ( is_user_logged_in() ) {
									echo esc_html( wp_get_current_user()->display_name );
								} else {
									echo 'Đăng nhập / Đăng ký';
								}
								?>
							</span>
						</span>
					</a>
					<a class="ty-compare-link" href="<?php echo esc_url( typee_url( 'compare' ) ); ?>" aria-label="So sánh sản phẩm">
						<?php typee_the_icon( 'compare_arrows', 20 ); ?>
						<span class="ty-compare-link__count" data-typee-compare-count hidden>0</span>
					</a>
					<?php echo typee_header_cart_button(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
		</div>

		<!-- Thanh điều hướng -->
		<div class="ty-navbar">
			<div class="ty-container ty-navbar__inner">
				<nav class="ty-nav" aria-label="Danh mục chính">
					<?php
					if ( has_nav_menu( 'typee_primary' ) ) {
						wp_nav_menu(
							array(
								'theme_location' => 'typee_primary',
								'container'      => false,
								'menu_class'     => 'ty-nav__list',
								'depth'          => 1,
							)
						);
					} else {
						echo '<ul class="ty-nav__list">';
						foreach ( $nav as $item ) {
							$badge = ! empty( $item['badge'] ) ? '<span class="ty-nav__badge">' . esc_html( $item['badge'] ) . '</span>' : '';
							echo '<li>';
							typee_link( $item['key'], $item['text'], 'ty-nav__link', $badge );
							echo '</li>';
						}
						echo '</ul>';
					}
					?>
				</nav>
				<div class="ty-navbar__status">
					<span class="ty-navbar__dot" aria-hidden="true"></span>
					<span><?php echo esc_html( typee_copy( 'showroom' ) ); ?></span>
				</div>
			</div>
		</div>
	</header>

	<?php do_action( 'flatsome_after_header' ); ?>

	<main id="main" class="<?php flatsome_main_classes(); ?>">
