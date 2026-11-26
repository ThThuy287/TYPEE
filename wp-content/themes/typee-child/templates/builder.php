<?php
/**
 * Màn 06 & 08 – Custom Keyboard Builder 6 bước (Figma 1:852, 32:2321; PRD FR-04/05/06).
 * Bước: Kit → Plate → Switch → Keycap → Mod/Lube → Tổng kết.
 * Toàn bộ dữ liệu linh kiện lấy từ WooCommerce; kiểm tra tương thích gọi API typee-core
 * (POST /wp-json/typee/v1/validate). Bản build lưu ở trình duyệt (PRD Giai đoạn 2: client store).
 *
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$catalog = typee_builder_catalog();
$config  = array(
	'catalog'     => $catalog,
	'minSwitches' => typee_min_switches_map(),
	'validateUrl' => esc_url_raw( rest_url( 'typee/v1/validate' ) ),
	'addToCart'   => esc_url_raw( add_query_arg( 'wc-ajax', 'add_to_cart', home_url( '/' ) ) ),
	'cartUrl'     => typee_url( 'cart' ),
	'builderUrl'  => typee_url( 'builder' ),
	'savedUrl'    => typee_url( 'saved_builds' ),
	'bundleRate'  => (float) apply_filters( 'typee_builder_bundle_rate', 0.05 ), // Rule ADP "Auto Discount 5% - Custom Bundle".
	'currency'    => html_entity_decode( get_woocommerce_currency_symbol() ),
);

get_header();
?>
<div class="ty-builder" data-typee-builder>
	<script type="application/json" id="typee-builder-data"><?php echo wp_json_encode( $config ); ?></script>

	<!-- Stepper (Figma 1:855) -->
	<nav class="ty-stepper" aria-label="Các bước ráp phím">
		<ol class="ty-container ty-container--wide ty-stepper__list" data-b-stepper>
			<?php
			$steps = array( 'Khung Kit', 'Plate', 'Switch cơ học', 'Keycap Profile', 'Mod & Lube Studio', 'Tổng kết & Đặt hàng' );
			foreach ( $steps as $i => $label ) :
				?>
				<li>
					<button type="button" class="ty-stepper__item" data-step="<?php echo (int) $i + 1; ?>">
						<span class="ty-stepper__num"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
						<span class="ty-stepper__text">
							<span class="ty-stepper__kicker">BƯỚC <?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="ty-stepper__label" data-step-label><?php echo esc_html( $label ); ?></span>
						</span>
					</button>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<div class="ty-container ty-container--wide ty-builder__wrap">
		<!-- Banner nạp bản build đã lưu (màn 08 – Figma 32:2321) -->
		<div class="ty-builder__loaded" data-b-loaded hidden>
			<span class="ty-builder__loaded-ic"><?php typee_the_icon( 'history', 18 ); ?></span>
			<div class="ty-builder__loaded-text">
				<strong data-b-loaded-name></strong>
				<span>Đã nạp cấu hình từ bản lưu. Chỉnh sửa linh kiện rồi chọn "Lưu đè" hoặc "Lưu bản mới".</span>
			</div>
			<div class="ty-builder__loaded-actions">
				<button type="button" class="ty-btn ty-btn--soft" data-b-save-over>Lưu đè</button>
				<button type="button" class="ty-btn ty-btn--primary" data-b-save-new>Lưu bản mới</button>
			</div>
		</div>

		<div class="ty-builder__grid">
			<!-- Cột 1: Danh mục linh kiện của bước hiện tại -->
			<section class="ty-builder__catalog" aria-labelledby="b-step-title">
				<div class="ty-builder__head">
					<div class="ty-builder__head-top">
						<div>
							<span class="ty-builder__eyebrow" data-b-eyebrow>CẤU HÌNH BƯỚC 01</span>
							<h1 class="ty-builder__title" id="b-step-title" data-b-title>Chọn Khung Kit</h1>
						</div>
						<span class="ty-badge ty-builder__count" data-b-count></span>
					</div>
					<p class="ty-builder__desc" data-b-desc></p>
					<label class="ty-sr" for="b-search">Tìm trong danh sách</label>
					<input id="b-search" type="search" class="ty-input ty-builder__search" placeholder="Tìm theo tên hoặc SKU…" data-b-search>
					<div class="ty-builder__filters" data-b-filters></div>
				</div>
				<div class="ty-builder__list" data-b-list aria-live="polite"></div>
				<button type="button" class="ty-btn ty-btn--soft ty-builder__more" data-b-more hidden>Xem thêm</button>
			</section>

			<!-- Cột 2: Xem trước & thông số EAV -->
			<section class="ty-builder__preview" aria-label="Xem trước cấu hình">
				<div class="ty-preview">
					<div class="ty-preview__bar">
						<span class="ty-preview__live"><span class="ty-dot ty-dot--ok"></span> STUDIO PREVIEW</span>
						<span class="ty-preview__chip" data-b-layout-chip>Layout: —</span>
					</div>
					<div class="ty-preview__stage">
						<img class="ty-preview__img" data-b-preview-img alt="" width="480" height="320" decoding="async" hidden>
						<div class="ty-kb" data-b-kb aria-hidden="true">
							<div class="ty-kb__top"><span data-b-kb-name>Chưa chọn Kit</span><span class="ty-kb__knob"></span></div>
							<div class="ty-kb__keys" data-b-kb-keys></div>
							<div class="ty-kb__glow"></div>
						</div>
					</div>
					<div class="ty-preview__foot">
						<span data-b-preview-caption>Chọn Kit để xem trước</span>
						<a class="ty-preview__link" data-b-preview-link href="#" hidden>Xem trang sản phẩm <?php typee_the_icon( 'open_in_new', 14 ); ?></a>
					</div>
				</div>

				<div class="ty-eav">
					<div class="ty-eav__head">
						<span class="ty-eav__ic"><?php typee_the_icon( 'memory', 18 ); ?></span>
						<div>
							<span class="ty-eav__kicker">EAV COMPATIBILITY ENGINE</span>
							<strong>Thông số tương thích linh kiện</strong>
						</div>
					</div>
					<dl class="ty-eav__rows" data-b-eav></dl>
				</div>
			</section>

			<!-- Cột 3: Trạng thái tương thích & tóm tắt -->
			<aside class="ty-builder__summary" aria-label="Tóm tắt cấu hình">
				<div class="ty-compat" data-b-compat data-state="idle" role="status" aria-live="polite">
					<span class="ty-compat__ic" data-b-compat-ic><?php typee_the_icon( 'help', 20 ); ?></span>
					<div>
						<strong data-b-compat-title>Chưa đủ linh kiện để kiểm tra</strong>
						<p data-b-compat-msg>Chọn Kit, Switch và Keycap – hệ thống sẽ kiểm tra chân pin, số switch và layout keycap.</p>
					</div>
				</div>

				<div class="ty-sumcard">
					<div class="ty-sumcard__head">
						<strong>CẤU HÌNH ĐANG RÁP</strong>
						<span class="ty-mono" data-b-build-id></span>
					</div>
					<ol class="ty-sumcard__rows" data-b-rows></ol>
					<div class="ty-sumcard__calc">
						<div><span>Tạm tính linh kiện:</span><span data-b-subtotal>0₫</span></div>
						<div class="is-accent" data-b-bundle-row hidden><span>Ưu đãi Custom Bundle (-5%)*:</span><span data-b-bundle>0₫</span></div>
						<div class="is-success"><span>Công lắp ráp &amp; cân chỉnh:</span><span>Miễn phí</span></div>
						<div class="ty-sumcard__total"><span>TỔNG CỘNG:</span><strong data-b-total>0₫</strong></div>
						<small>* Ưu đãi bundle được giỏ hàng áp dụng tự động khi đủ Kit + Switch + Keycap.</small>
					</div>
					<button type="button" class="ty-btn ty-btn--primary ty-sumcard__next" data-b-next>Tiếp tục <?php typee_the_icon( 'arrow_forward', 16 ); ?></button>
					<button type="button" class="ty-btn ty-btn--primary ty-sumcard__order" data-b-order hidden><?php typee_the_icon( 'shopping_cart_checkout', 18 ); ?> Đặt hàng trọn bộ combo</button>
					<div class="ty-sumcard__secondary">
						<button type="button" class="ty-btn ty-btn--soft" data-typee-open="#b-save-modal"><?php typee_the_icon( 'bookmark_add', 16 ); ?> Lưu cấu hình</button>
						<button type="button" class="ty-btn ty-btn--soft" data-b-share><?php typee_the_icon( 'share', 16 ); ?> Chia sẻ link</button>
					</div>
					<p class="ty-sumcard__toast" data-b-toast role="status" hidden></p>
					<ul class="ty-sumcard__trust">
						<li><?php typee_the_icon( 'verified', 16 ); ?> Bảo hành chính hãng 12 tháng toàn bộ Kit</li>
						<li><?php typee_the_icon( 'task_alt', 16 ); ?> Kiểm định KCS 100% phím trước khi đóng gói</li>
						<li><?php typee_the_icon( 'support_agent', 16 ); ?> Hỗ trợ kỹ thuật lube/mod trọn đời máy</li>
					</ul>
					<?php if ( typee_url( 'saved_builds' ) ) : ?>
						<a class="ty-sumcard__saved" href="<?php echo esc_url( typee_url( 'saved_builds' ) ); ?>"><?php typee_the_icon( 'bookmark', 14 ); ?> Xem các cấu hình đã lưu</a>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	</div>

	<!-- Modal lưu cấu hình (FR-05) -->
	<div class="ty-modal" id="b-save-modal" role="dialog" aria-modal="true" aria-labelledby="b-save-title" aria-hidden="true">
		<div class="ty-drawer__head">
			<h2 class="ty-drawer__title" id="b-save-title">Lưu cấu hình</h2>
			<button type="button" class="ty-icon-btn" data-typee-close aria-label="Đóng"><?php typee_the_icon( 'close', 20 ); ?></button>
		</div>
		<form class="ty-drawer__body" data-b-save-form>
			<div class="ty-field">
				<label for="b-save-name">Tên bản build</label>
				<input id="b-save-name" class="ty-input" name="name" required maxlength="60" placeholder="VD: QK75N Creamy Dream" autofocus>
			</div>
			<div class="ty-field">
				<label for="b-save-note">Ghi chú linh kiện</label>
				<textarea id="b-save-note" class="ty-textarea" name="note" maxlength="300" placeholder="VD: Lube Krytox 205g0, tape mod 2 lớp…"></textarea>
			</div>
			<p class="ty-help">Bản build được lưu trên trình duyệt này. Dùng "Chia sẻ link" để gửi cho bạn bè.</p>
			<div class="ty-empty__actions" style="justify-content:flex-end">
				<button type="button" class="ty-btn ty-btn--soft" data-typee-close>Hủy</button>
				<button type="submit" class="ty-btn ty-btn--primary">Lưu bản build</button>
			</div>
		</form>
	</div>
</div>
<?php
get_footer();
