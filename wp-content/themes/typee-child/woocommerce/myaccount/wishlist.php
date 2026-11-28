<?php
/**
 * Wishlist – Danh sách sản phẩm yêu thích (Figma 82:7799 / 82:7801).
 *
 * Nằm trực tiếp bên trong `.typee-account-content` của Layout Shell dùng chung (`my-account.php`),
 * giữ nguyên 100% Sidebar điều hướng trái và hệ khung cố định với toàn bộ 6 mục Tài khoản.
 * Đồng bộ danh sách ID từ `localStorage.typee_wishlist` + `user_meta('typee_wishlist_ids')`
 * và tải dữ liệu sản phẩm WooCommerce thực tế qua AJAX `typee_account_wishlist_products`.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$catalog_url = function_exists( 'typee_url' ) ? typee_url( 'catalog' ) : home_url( '/cua-hang/' );
$builder_url = function_exists( 'typee_url' ) ? typee_url( 'builder' ) : home_url( '/custom-builder/' );
?>

<div class="typee-account-wishlist" data-typee-wishlist-root>

	<!-- Khối 1: Header & Thanh hành động Wishlist -->
	<div class="typee-account-card typee-account-wl-head">
		<div class="typee-account-section-head">
			<div>
				<span class="typee-account-eyebrow">DANH SÁCH QUAN TÂM • WISHLIST</span>
				<div class="typee-account-title-row">
					<h1 class="typee-account-page-title">Sản phẩm yêu thích</h1>
					<span class="typee-account-count-pill ty-mono" data-typee-wl-total>0 sản phẩm</span>
				</div>
				<p class="typee-account-page-sub">Theo dõi giá, tình trạng tồn kho các mẫu bàn phím, Kit CNC, Switch và Keycap bạn đã đánh dấu yêu thích trên TYPEE Studio.</p>
			</div>
		</div>

		<div class="typee-account-wl-toolbar" data-typee-wl-toolbar>
			<div class="typee-account-wl-sort">
				<label class="typee-account-wl-sort__label" for="typee-wl-sort-select">
					<?php typee_the_icon( 'sort', 16 ); ?>
					<span>Sắp xếp:</span>
				</label>
				<select id="typee-wl-sort-select" class="typee-account-select typee-account-select--compact" data-typee-wl-sort>
					<option value="recent">Mới thêm gần đây</option>
					<option value="price_asc">Giá: Thấp đến cao</option>
					<option value="price_desc">Giá: Cao đến thấp</option>
					<option value="in_stock">Ưu tiên còn hàng</option>
				</select>
			</div>

			<div class="typee-account-wl-bulk">
				<button type="button" class="ty-btn ty-btn--soft typee-account-wl-clear-btn" data-typee-wl-clear-all disabled>
					<?php typee_the_icon( 'delete', 16 ); ?>
					<span>Xóa tất cả</span>
				</button>
				<button type="button" class="ty-btn ty-btn--primary" data-typee-wl-add-all disabled>
					<?php typee_the_icon( 'add_shopping_cart', 16 ); ?>
					<span>Thêm tất cả vào giỏ</span>
				</button>
			</div>
		</div>
	</div>

	<!-- Thông báo phản hồi thao tác Wishlist -->
	<div class="typee-account-inline-toast" data-typee-wl-toast hidden role="status" aria-live="polite"></div>

	<!-- Khối 2: Lưới sản phẩm yêu thích (3 cột desktop / 2 cột tablet / 1 cột mobile) -->
	<div class="typee-account-wl-grid" data-typee-wl-grid>
		<div class="typee-account-card typee-account-loading-card typee-account-wl-grid__full">
			<span class="typee-account-loading-card__dot" aria-hidden="true"></span>
			<span>Đang tải danh sách sản phẩm yêu thích của bạn...</span>
		</div>
	</div>

	<!-- Khối 3: Trạng thái trống Wishlist -->
	<div class="typee-account-card typee-account-empty-state" data-typee-wl-empty hidden>
		<div class="typee-account-empty-state__icon" aria-hidden="true">
			<?php typee_the_icon( 'favorite', 32 ); ?>
		</div>
		<h2 class="typee-account-empty-state__title">Danh sách yêu thích của bạn đang trống</h2>
		<p class="typee-account-empty-state__desc">
			Bấm vào biểu tượng trái tim trên bất kỳ mẫu bàn phím cơ, Kit CNC, Switch hoặc Keycap nào để lưu lại theo dõi giá và mua nhanh sau này.
		</p>
		<div class="typee-account-empty-state__actions">
			<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $catalog_url ); ?>">
				<?php typee_the_icon( 'shopping_bag', 18 ); ?>
				<span>Khám phá danh mục sản phẩm</span>
			</a>
			<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( $builder_url ); ?>">
				<?php typee_the_icon( 'build_circle', 18 ); ?>
				<span>Tự phối phím với Custom Builder</span>
			</a>
		</div>
	</div>

	<!-- Khối 4: Cam kết TYPEE Precision Commitments (Figma 82:8150) -->
	<div class="typee-account-wl-trust">
		<div class="typee-account-wl-trust__item">
			<span class="typee-account-wl-trust__icon" aria-hidden="true"><?php typee_the_icon( 'verified_user', 20 ); ?></span>
			<div>
				<strong>Bảo hành 12 tháng e-RMA</strong>
				<span>Kích hoạt điện tử theo Serial sản phẩm</span>
			</div>
		</div>
		<div class="typee-account-wl-trust__item">
			<span class="typee-account-wl-trust__icon" aria-hidden="true"><?php typee_the_icon( 'inventory_2', 20 ); ?></span>
			<div>
				<strong>Đóng gói chống sốc 3 lớp</strong>
				<span>An toàn tuyệt đối cho Kit CNC &amp; Keycap</span>
			</div>
		</div>
		<div class="typee-account-wl-trust__item">
			<span class="typee-account-wl-trust__icon" aria-hidden="true"><?php typee_the_icon( 'local_shipping', 20 ); ?></span>
			<div>
				<strong>Giao hỏa tốc &amp; COD toàn quốc</strong>
				<span>Đồng kiểm ngoại quan trước thanh toán</span>
			</div>
		</div>
		<div class="typee-account-wl-trust__item">
			<span class="typee-account-wl-trust__icon" aria-hidden="true"><?php typee_the_icon( 'handyman', 20 ); ?></span>
			<div>
				<strong>Kỹ thuật viên Modding 24/7</strong>
				<span>Tư vấn chọn Switch, Plate &amp; cân Stab</span>
			</div>
		</div>
	</div>

</div>
