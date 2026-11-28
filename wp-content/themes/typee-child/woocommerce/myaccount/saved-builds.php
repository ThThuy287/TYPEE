<?php
/**
 * Saved Custom Builds – Cấu hình Builder đã lưu (Figma 32:2950).
 *
 * Nằm trực tiếp bên trong `.typee-account-content` của Layout Shell dùng chung (`my-account.php`).
 * Đồng bộ cấu hình từ `localStorage.typee_builds` (Custom Builder) + `user_meta('typee_saved_builds')`,
 * bổ sung giá & tồn kho thực từ WooCommerce qua AJAX `typee_account_builder_catalog`.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$builder_url = function_exists( 'typee_url' ) ? typee_url( 'builder' ) : home_url( '/custom-builder/' );
$catalog_url = function_exists( 'typee_url' ) ? typee_url( 'catalog' ) : home_url( '/cua-hang/' );
$rma_url     = function_exists( 'typee_url' ) ? typee_url( 'rma_new' ) : home_url( '/gui-yeu-cau-rma/' );
?>

<div class="typee-account-builds" data-typee-builds-root>

	<!-- Khối 1: Header Studio Workspace + Bộ lọc trạng thái & tìm kiếm -->
	<div class="typee-account-card typee-account-builds-head">
		<div class="typee-account-section-head">
			<div>
				<span class="typee-account-eyebrow">STUDIO WORKSPACE • CUSTOM BUILDER</span>
				<div class="typee-account-title-row">
					<h1 class="typee-account-page-title">Cấu hình Builder đã lưu</h1>
					<span class="typee-account-count-pill ty-mono" data-typee-builds-total>0 cấu hình</span>
				</div>
				<p class="typee-account-page-sub">Quản lý các bản phối Kit Barebone, Switch &amp; Keycap đã lưu. Mở lại trong Custom Builder bất cứ lúc nào hoặc thêm trọn bộ vào giỏ với ưu đãi <strong>-5% Custom Bundle</strong>.</p>
			</div>

			<div class="typee-account-builds-head__actions">
				<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $builder_url ); ?>">
					<?php typee_the_icon( 'build_circle', 18 ); ?>
					<span>Tạo bản build mới</span>
				</a>
			</div>
		</div>

		<div class="typee-account-builds-toolbar">
			<div class="typee-account-filter-pills" role="tablist" aria-label="Lọc trạng thái cấu hình">
				<button type="button" class="typee-account-filter-pill is-active" data-typee-builds-filter="all" role="tab" aria-selected="true">
					<span>Tất cả</span>
					<span class="typee-account-filter-pill__count ty-mono" data-typee-builds-count="all">0</span>
				</button>
				<button type="button" class="typee-account-filter-pill" data-typee-builds-filter="complete" role="tab" aria-selected="false">
					<span>Hoàn chỉnh sẵn sàng</span>
					<span class="typee-account-filter-pill__count ty-mono" data-typee-builds-count="complete">0</span>
				</button>
				<button type="button" class="typee-account-filter-pill" data-typee-builds-filter="draft" role="tab" aria-selected="false">
					<span>Bản nháp đang build</span>
					<span class="typee-account-filter-pill__count ty-mono" data-typee-builds-count="draft">0</span>
				</button>
			</div>

			<div class="typee-account-search-box">
				<span class="typee-account-search-box__icon" aria-hidden="true">
					<?php typee_the_icon( 'search', 16 ); ?>
				</span>
				<input
					type="search"
					class="typee-account-search-box__input"
					placeholder="Tìm tên cấu hình, Kit, Switch, Keycap..."
					data-typee-builds-search
					aria-label="Tìm kiếm cấu hình đã lưu"
				>
			</div>
		</div>
	</div>

	<!-- Thông báo thao tác (Copy link / Thêm vào giỏ / Xóa) -->
	<div class="typee-account-inline-toast" data-typee-builds-toast hidden role="status" aria-live="polite"></div>

	<!-- Khối 2: Danh sách thẻ cấu hình (Hydrated bởi account.js) -->
	<div class="typee-account-builds-list" data-typee-builds-list>
		<div class="typee-account-card typee-account-loading-card">
			<span class="typee-account-loading-card__dot" aria-hidden="true"></span>
			<span>Đang đồng bộ danh sách cấu hình Custom Builder...</span>
		</div>
	</div>

	<!-- Khối 3: Trạng thái trống khi không có cấu hình hoặc không khớp bộ lọc -->
	<div class="typee-account-card typee-account-empty-state" data-typee-builds-empty hidden>
		<div class="typee-account-empty-state__icon" aria-hidden="true">
			<?php typee_the_icon( 'keyboard', 32 ); ?>
		</div>
		<h2 class="typee-account-empty-state__title" data-typee-builds-empty-title>Chưa có cấu hình Custom Builder nào được lưu</h2>
		<p class="typee-account-empty-state__desc" data-typee-builds-empty-desc>
			Hãy tự tay phối Kit CNC, Switch và Keycap theo đúng gu âm thanh của bạn trong Custom Keyboard Builder để tự động nhận ưu đãi giảm 5% trọn bộ.
		</p>
		<div class="typee-account-empty-state__actions">
			<a class="ty-btn ty-btn--primary" href="<?php echo esc_url( $builder_url ); ?>">
				<?php typee_the_icon( 'build_circle', 18 ); ?>
				<span>Mở Custom Keyboard Builder</span>
			</a>
			<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( $catalog_url ); ?>">
				<span>Khám phá Kit &amp; Linh kiện</span>
			</a>
		</div>
	</div>

	<!-- Khối 4: Cam kết kỹ thuật Custom Bundle tại TYPEE Studio -->
	<div class="typee-account-card typee-account-builds-perks">
		<div class="typee-account-builds-perks__grid">
			<div class="typee-account-builds-perks__item">
				<span class="typee-account-builds-perks__ico" aria-hidden="true"><?php typee_the_icon( 'verified', 18 ); ?></span>
				<div>
					<strong>Kiểm tra tương thích tự động</strong>
					<span>Đối chiếu chuẩn mạch 5-pin, số lượng switch theo layout và bộ keycap tương ứng.</span>
				</div>
			</div>
			<div class="typee-account-builds-perks__item">
				<span class="typee-account-builds-perks__ico" aria-hidden="true"><?php typee_the_icon( 'handyman', 18 ); ?></span>
				<div>
					<strong>Ưu đãi -5% Custom Bundle</strong>
					<span>Tự động áp dụng khi thêm trọn bộ Kit + Switch + Keycap vào giỏ hàng cùng lúc.</span>
				</div>
			</div>
			<div class="typee-account-builds-perks__item">
				<span class="typee-account-builds-perks__ico" aria-hidden="true"><?php typee_the_icon( 'support_agent', 18 ); ?></span>
				<div>
					<strong>Hỗ trợ lắp ráp &amp; cân Stab</strong>
					<span>Kỹ thuật viên TYPEE Modding Lab hỗ trợ tư vấn âm thanh &amp; bảo hành <a class="typee-account-text-link" href="<?php echo esc_url( $rma_url ); ?>">e-RMA 12 tháng</a>.</span>
				</div>
			</div>
		</div>
	</div>

</div>
