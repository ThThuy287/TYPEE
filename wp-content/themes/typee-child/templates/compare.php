<?php
/**
 * Màn 05 – So Sánh Linh Kiện & Bàn Phím Cơ 4 Cột Chuẩn EAV (Figma 82:2526).
 *
 * Toàn bộ dữ liệu sản phẩm & thông số EAV được tải động từ Backend REST API
 * (`/wp-json/typee/v1/compare` & `/wp-json/typee/v1/compare/products`), đồng bộ
 * hai chiều với `localStorage.typee_compare` và tham số chia sẻ `?ids=`.
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$cmp_config = typee_compare_client_config();
$hotline    = preg_replace( '/\s+/', '', typee_copy( 'hotline' ) );

get_header();
?>
<div class="ty-cmp" data-typee-compare-page data-node="82:2526">
	<script type="application/json" id="typee-compare-config"><?php echo wp_json_encode( $cmp_config ); ?></script>

	<!-- Mẫu icon nội bộ để JS tái sử dụng không cần hardcode SVG path -->
	<template id="typee-compare-icons">
		<span data-ic="close"><?php typee_the_icon( 'close', 14 ); ?></span>
		<span data-ic="cart"><?php typee_the_icon( 'add_shopping_cart', 16 ); ?></span>
		<span data-ic="add"><?php typee_the_icon( 'add', 22 ); ?></span>
		<span data-ic="layers"><?php typee_the_icon( 'layers', 16 ); ?></span>
		<span data-ic="handyman"><?php typee_the_icon( 'handyman', 16 ); ?></span>
		<span data-ic="memory"><?php typee_the_icon( 'memory', 16 ); ?></span>
		<span data-ic="bolt"><?php typee_the_icon( 'bolt', 16 ); ?></span>
		<span data-ic="verified_user"><?php typee_the_icon( 'verified_user', 16 ); ?></span>
		<span data-ic="verified"><?php typee_the_icon( 'verified', 14 ); ?></span>
		<span data-ic="check"><?php typee_the_icon( 'check_circle', 14 ); ?></span>
		<span data-ic="none"><?php typee_the_icon( 'cancel', 14 ); ?></span>
		<span data-ic="chevron"><?php typee_the_icon( 'keyboard_arrow_down', 18 ); ?></span>
	</template>

	<div class="ty-container ty-container--wide ty-cmp__inner">
		<!-- 1. BREADCRUMB NAVIGATION (Figma 82:2530) -->
		<nav class="ty-cmp__breadcrumb" aria-label="Breadcrumb">
			<ol>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang chủ</a></li>
				<li class="ty-cmp__bc-sep" aria-hidden="true"><?php typee_the_icon( 'chevron_right', 14 ); ?></li>
				<li><a href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Công cụ so sánh</a></li>
				<li class="ty-cmp__bc-sep" aria-hidden="true"><?php typee_the_icon( 'chevron_right', 14 ); ?></li>
				<li aria-current="page">So sánh linh kiện &amp; Bàn phím cơ</li>
			</ol>
		</nav>

		<!-- 2. PAGE HEADER & TECHNICAL UTILITY TOOLBAR (Figma 82:2541) -->
		<header class="ty-cmp__header">
			<div class="ty-cmp__heading-wrap">
				<div class="ty-cmp__title-row">
					<h1 class="ty-cmp__title">So sánh linh kiện &amp; Bàn phím cơ</h1>
					<span class="ty-cmp__limit-badge">
						<?php typee_the_icon( 'compare_arrows', 14, 'ty-cmp__limit-ic' ); ?>
						<span>Tối đa 4 sản phẩm cùng lúc</span>
					</span>
				</div>
				<p class="ty-cmp__subtitle">
					Đối chiếu trực tiếp chi tiết thông số cơ khí, kiểu mount, mạch PCB và khả năng custom giúp bạn chọn đúng linh kiện hoàn hảo cho góc làm việc.
				</p>
			</div>

			<div class="ty-cmp__toolbar" role="region" aria-label="Công cụ bảng đối chiếu">
				<div class="ty-cmp__toolbar-top">
					<button
						type="button"
						class="ty-cmp__diff-toggle"
						role="switch"
						aria-checked="false"
						data-cmp-diff-toggle
					>
						<span class="ty-cmp__switch-track" aria-hidden="true">
							<span class="ty-cmp__switch-thumb"></span>
						</span>
						<span class="ty-cmp__switch-label">Chỉ hiện điểm khác biệt</span>
						<span class="ty-cmp__diff-badge" data-cmp-diff-count>0 mục lệch</span>
					</button>
				</div>

				<div class="ty-cmp__toolbar-actions">
					<button type="button" class="ty-cmp__tool-btn ty-cmp__tool-btn--add" data-cmp-open-picker>
						<?php typee_the_icon( 'add', 16 ); ?>
						<span>Thêm sản phẩm</span>
					</button>
					<button type="button" class="ty-cmp__tool-btn" data-cmp-clear-all>
						<?php typee_the_icon( 'autorenew', 15 ); ?>
						<span>Xóa tất cả</span>
					</button>
					<button type="button" class="ty-cmp__tool-btn" data-cmp-share>
						<?php typee_the_icon( 'share', 15 ); ?>
						<span>Chia sẻ</span>
					</button>
				</div>
			</div>
		</header>

		<!-- Thông báo nhanh (Toast cho Chia sẻ / Thêm giỏ hàng) -->
		<div class="ty-cmp__toast" data-cmp-toast role="status" aria-live="polite" hidden></div>

		<!-- TRẠNG THÁI ĐANG TẢI (Loading Skeleton) -->
		<section class="ty-cmp__loading" data-cmp-loading aria-label="Đang tải dữ liệu đối chiếu" hidden>
			<div class="ty-cmp__skeleton-grid">
				<div class="ty-cmp__sk-card ty-cmp__sk-card--label"></div>
				<div class="ty-cmp__sk-card"></div>
				<div class="ty-cmp__sk-card"></div>
				<div class="ty-cmp__sk-card"></div>
			</div>
			<div class="ty-cmp__sk-rows">
				<div class="ty-cmp__sk-row"></div>
				<div class="ty-cmp__sk-row"></div>
				<div class="ty-cmp__sk-row"></div>
				<div class="ty-cmp__sk-row"></div>
			</div>
		</section>

		<!-- TRẠNG THÁI LỖI (Error State) -->
		<section class="ty-panel ty-empty ty-cmp__error" data-cmp-error hidden>
			<span class="ty-empty__ic"><?php typee_the_icon( 'error', 28 ); ?></span>
			<h2 class="ty-empty__title">Không thể tải dữ liệu đối chiếu</h2>
			<p class="ty-empty__desc" data-cmp-error-msg>Đã xảy ra sự cố kết nối tới máy chủ. Vui lòng thử lại.</p>
			<div class="ty-empty__actions">
				<button type="button" class="ty-btn ty-btn--primary" data-cmp-retry>
					<?php typee_the_icon( 'autorenew', 16 ); ?>
					<span>Thử tải lại</span>
				</button>
				<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Về kho sản phẩm</a>
			</div>
		</section>

		<!-- TRẠNG THÁI TRỐNG (Empty State - 0 sản phẩm) -->
		<section class="ty-panel ty-empty ty-cmp__empty" data-cmp-empty hidden>
			<img
				class="ty-empty__mascot"
				src="<?php echo esc_url( TYPEE_CHILD_URI . '/assets/images/logo-mascot.png' ); ?>"
				alt="TYPEE Mascot"
				width="84"
				height="96"
				loading="lazy"
				decoding="async"
			/>
			<h2 class="ty-empty__title">Chưa có linh kiện nào trong bảng đối chiếu</h2>
			<p class="ty-empty__desc">
				Chọn tối đa 4 mẫu Bàn phím cơ, Khung Kit, Switch hoặc Keycap để đặt lên bàn cân đối chiếu trực quan từng thông số EAV.
			</p>
			<div class="ty-empty__actions">
				<button type="button" class="ty-btn ty-btn--primary" data-cmp-open-picker>
					<?php typee_the_icon( 'add', 18 ); ?>
					<span>Chọn sản phẩm để so sánh</span>
				</button>
				<a class="ty-btn ty-btn--soft" href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Khám phá Kho sản phẩm</a>
			</div>
			<?php if ( ! empty( $cmp_config['presets'] ) ) : ?>
				<div class="ty-cmp__presets">
					<span class="ty-cmp__presets-label">Hoặc nạp nhanh từ kho hiện tại:</span>
					<div class="ty-pills ty-cmp__presets-list">
						<?php foreach ( $cmp_config['presets'] as $preset ) : ?>
							<button
								type="button"
								class="ty-pill"
								data-cmp-preset="<?php echo esc_attr( implode( ',', $preset['ids'] ) ); ?>"
							>
								<?php typee_the_icon( 'bolt', 14 ); ?>
								<span><?php echo esc_html( $preset['label'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>

		<!-- 3. MATRIX TABLE: 4-COLUMN ENTHUSIAST COMPARISON (Figma 82:2572) -->
		<section class="ty-cmp__matrix" data-cmp-matrix aria-label="Bảng đối chiếu thông số EAV" hidden>
			<div class="ty-cmp__sentinel" data-cmp-sentinel aria-hidden="true"></div>

			<div class="ty-cmp__scroll">
				<!-- STICKY TOP HEADER ROW (PRODUCTS) (Figma 82:2575) -->
				<div class="ty-cmp__sticky-head" data-cmp-sticky-head>
					<div class="ty-cmp__grid ty-cmp__head-grid" data-cmp-head-grid>
						<!-- Render động bởi compare.js từ API dữ liệu thật -->
					</div>
				</div>

				<!-- SECTION ACCORDIONS / SPECIFICATION ROWS (Figma 82:2661) -->
				<div class="ty-cmp__groups" data-cmp-groups>
					<!-- Render động 5 nhóm tiêu chí EAV bởi compare.js -->
				</div>
			</div>
		</section>

		<!-- 4. BOTTOM ADVISORY BANNER & CTA STRIP (Figma 82:3082) -->
		<section class="ty-cmp__advisory" aria-labelledby="ty-cmp-adv-title">
			<div class="ty-cmp__advisory-main">
				<div class="ty-cmp__advisory-ic" aria-hidden="true">
					<?php typee_the_icon( 'tune', 26 ); ?>
				</div>
				<div class="ty-cmp__advisory-copy">
					<h2 class="ty-cmp__advisory-title" id="ty-cmp-adv-title">
						Chưa biết chọn linh kiện nào hợp tay gõ &amp; sở thích âm thanh?
					</h2>
					<p class="ty-cmp__advisory-desc">
						Sử dụng công cụ phối ghép trực quan hoặc trò chuyện trực tiếp cùng kỹ thuật viên mod phím cơ tại Lab để được đo test lực nhấn.
					</p>
				</div>
			</div>
			<div class="ty-cmp__advisory-actions">
				<a class="ty-cmp__cta ty-cmp__cta--dark" href="tel:<?php echo esc_attr( $hotline ? $hotline : '19008899' ); ?>">
					<?php typee_the_icon( 'support_agent', 18 ); ?>
					<span>Chat tư vấn cùng Kỹ thuật viên Lab</span>
				</a>
				<a class="ty-cmp__cta ty-cmp__cta--primary" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">
					<?php typee_the_icon( 'build_circle', 18 ); ?>
					<span>Mở Custom Keyboard Builder (6 bước)</span>
				</a>
			</div>
		</section>
	</div>

	<!-- MODAL CHỌN NHANH SẢN PHẨM ĐỂ ĐỐI CHIẾU -->
	<div
		class="ty-modal ty-cmp-modal"
		id="ty-cmp-picker"
		role="dialog"
		aria-modal="true"
		aria-labelledby="ty-cmp-picker-title"
		aria-hidden="true"
	>
		<div class="ty-drawer__head">
			<div>
				<h2 class="ty-drawer__title" id="ty-cmp-picker-title">Thêm linh kiện vào bảng so sánh</h2>
				<p class="ty-help" data-cmp-picker-status>Đã chọn 0 / 4 khe so sánh</p>
			</div>
			<button type="button" class="ty-icon-btn" data-typee-close aria-label="Đóng hộp thoại">
				<?php typee_the_icon( 'close', 20 ); ?>
			</button>
		</div>

		<div class="ty-drawer__body ty-cmp-modal__body">
			<div class="ty-cmp-modal__controls">
				<div class="ty-cmp-modal__search-wrap">
					<?php typee_the_icon( 'search', 16, 'ty-cmp-modal__search-ic' ); ?>
					<label class="ty-sr" for="ty-cmp-search">Tìm linh kiện theo tên hoặc mã SKU</label>
					<input
						id="ty-cmp-search"
						type="search"
						class="ty-input ty-cmp-modal__search"
						placeholder="Tìm tên Kit, Bàn phím, Switch, Keycap hoặc mã SKU..."
						autocomplete="off"
						data-cmp-picker-search
					/>
				</div>
				<div class="ty-pills ty-cmp-modal__cats" role="group" aria-label="Lọc theo danh mục" data-cmp-picker-cats>
					<button type="button" class="ty-pill" data-cat="all" aria-pressed="true">Tất cả</button>
					<button type="button" class="ty-pill" data-cat="pre-built" aria-pressed="false">Pre-built</button>
					<button type="button" class="ty-pill" data-cat="kit" aria-pressed="false">Khung Kit</button>
					<button type="button" class="ty-pill" data-cat="switch" aria-pressed="false">Switch</button>
					<button type="button" class="ty-pill" data-cat="keycap" aria-pressed="false">Keycap</button>
				</div>
			</div>

			<div class="ty-cmp-modal__results" data-cmp-picker-list aria-live="polite"></div>
		</div>

		<div class="ty-drawer__foot ty-cmp-modal__foot">
			<span class="ty-help">Mẹo: Nên so sánh các sản phẩm cùng nhóm (VD: giữa các mẫu Kit/Pre-built) để có nhiều tiêu chí tương đồng nhất.</span>
			<button type="button" class="ty-btn ty-btn--primary" data-typee-close>Hoàn tất</button>
		</div>
	</div>
</div>
<?php
get_footer();
