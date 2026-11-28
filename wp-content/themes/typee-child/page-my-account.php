<?php
/**
 * Template name: WooCommerce - My Account
 *
 * Ghi đè template `page-my-account.php` của Flatsome để không lặp header/sidebar cũ
 * và để toàn bộ khu vực Tài khoản dùng duy nhất Layout Shell trong `woocommerce/myaccount/my-account.php`.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="typee-account-page-wrap" role="main">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>
<?php
get_footer();
