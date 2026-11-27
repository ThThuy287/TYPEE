<?php
/**
 * Trung tâm Tra Cứu Đơn Hàng & Bảo Hành Điện Tử e-RMA – Figma 82:3293.
 * Toàn bộ selector mang tiền tố `.typee-tracking-*` / `.typee-warranty-*`.
 *
 * @var array $args { initial_tab?: 'lookup'|'rma' }
 * @package typee-child
 */

defined( 'ABSPATH' ) || exit;

$initial_tab = ( isset( $args['initial_tab'] ) && 'rma' === $args['initial_tab'] ) ? 'rma' : 'lookup';
$hotline     = typee_copy( 'hotline' );

// Nếu khách đã đăng nhập → lấy tối đa 5 đơn gần đây để bấm xem nhanh.
$user_orders = array();
if ( is_user_logged_in() && function_exists( 'wc_get_orders' ) ) {
	$user_orders = wc_get_orders(
		array(
			'customer_id' => get_current_user_id(),
			'limit'       => 5,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);
}
?>
<div class="typee-tracking-page" data-tracking-root data-initial-tab="<?php echo esc_attr( $initial_tab ); ?>" data-node="82:3293">
	<div class="ty-container typee-tracking-wrap">

		<!-- 1. BREADCRUMB (Figma 82:3296) -->
		<nav class="typee-tracking-bc" aria-label="Breadcrumb">
			<ol>
				<li>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php typee_the_icon( 'home_pin', 14 ); ?>
						<span>Trang chủ</span>
					</a>
				</li>
				<li class="typee-tracking-bc__sep" aria-hidden="true">/</li>
				<li><span>Dịch vụ khách hàng</span></li>
				<li class="typee-tracking-bc__sep" aria-hidden="true">/</li>
				<li aria-current="page">Tra cứu đơn hàng &amp; Bảo hành e-RMA</li>
			</ol>
		</nav>

		<!-- 2. PAGE HEADER (Figma 82:3309) -->
		<header class="typee-tracking-hero">
			<div class="typee-tracking-hero__glow" aria-hidden="true"></div>
			<div class="typee-tracking-hero__left">
				<span class="typee-tracking-hero__kicker">
					<span class="typee-tracking-hero__dot" aria-hidden="true"></span>
					<span>TYPEE LAB ENGINEERING SUPPORT</span>
				</span>
				<h1 class="typee-tracking-hero__title">Trung tâm Tra cứu &amp; Bảo hành Điện tử e-RMA</h1>
				<p class="typee-tracking-hero__desc">
					Tra cứu tiến trình xử lý đơn hàng, kiểm tra thời hạn bảo hành điện tử theo từng sản phẩm hoặc gửi yêu cầu bảo hành trực tiếp tới kỹ thuật viên TYPEE Studio.
				</p>
			</div>

			<div class="typee-tracking-hotline">
				<span class="typee-tracking-hotline__ic"><?php typee_the_icon( 'support_agent', 24 ); ?></span>
				<div>
					<span class="typee-tracking-hotline__label">HOTLINE KỸ THUẬT LAB (NHÁNH 2)</span>
					<a class="typee-tracking-hotline__num" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $hotline ) ); ?>"><?php echo esc_html( $hotline ); ?></a>
					<span class="typee-tracking-hotline__sub">09:00 - 21:00 hàng ngày (Kể cả T7 &amp; CN)</span>
				</div>
			</div>
		</header>

		<!-- 3. SEGMENTED CONTROL TABS (Figma 82:3331) -->
		<div class="typee-tracking-tabs" role="tablist" aria-label="Chức năng tra cứu và bảo hành">
			<button
				type="button"
				class="typee-tracking-tab<?php echo 'lookup' === $initial_tab ? ' is-active' : ''; ?>"
				role="tab"
				id="tracking-tab-lookup"
				aria-controls="tracking-panel-lookup"
				aria-selected="<?php echo 'lookup' === $initial_tab ? 'true' : 'false'; ?>"
				data-tracking-tab="lookup"
			>
				<?php typee_the_icon( 'local_shipping', 18 ); ?>
				<span>Tra cứu đơn hàng &amp; Hạn bảo hành</span>
			</button>
			<button
				type="button"
				class="typee-tracking-tab<?php echo 'rma' === $initial_tab ? ' is-active' : ''; ?>"
				role="tab"
				id="tracking-tab-rma"
				aria-controls="tracking-panel-rma"
				aria-selected="<?php echo 'rma' === $initial_tab ? 'true' : 'false'; ?>"
				data-tracking-tab="rma"
			>
				<?php typee_the_icon( 'verified_user', 18 ); ?>
				<span>Tạo yêu cầu bảo hành e-RMA mới</span>
			</button>
		</div>

		<!-- 4. TAB 1: ORDER TRACKING & WARRANTY LOOKUP (Figma 82:3341) -->
		<section
			class="typee-tracking-panel"
			role="tabpanel"
			id="tracking-panel-lookup"
			aria-labelledby="tracking-tab-lookup"
			<?php echo 'lookup' !== $initial_tab ? 'hidden' : ''; ?>
		>
			<?php if ( ! empty( $user_orders ) ) : ?>
				<div class="typee-tracking-recent">
					<span class="typee-tracking-recent__label">Đơn hàng gần đây của bạn (bấm để xem nhanh):</span>
					<div class="typee-tracking-recent__list">
						<?php foreach ( $user_orders as $uo ) : ?>
							<button
								type="button"
								class="typee-tracking-recent__btn"
								data-quick-order="<?php echo esc_attr( $uo->get_id() ); ?>"
								data-quick-contact="<?php echo esc_attr( $uo->get_billing_phone() ?: $uo->get_billing_email() ); ?>"
							>
								<strong>#TY-<?php echo esc_html( $uo->get_order_number() ); ?></strong>
								<span><?php echo esc_html( wc_get_order_status_name( $uo->get_status() ) ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="typee-tracking-card">
				<div class="typee-tracking-card__head">
					<?php typee_the_icon( 'search', 20, 'typee-tracking-card__ic' ); ?>
					<h2>Nhập thông tin xác thực đơn hàng</h2>
				</div>

				<form class="typee-tracking-form" data-tracking-lookup-form>
					<div class="typee-tracking-form__grid">
						<div class="typee-tracking-field">
							<label for="tracking-order-code">Mã đơn hàng / Mã e-RMA <span class="is-req">*</span></label>
							<div class="typee-tracking-inputwrap">
								<span class="typee-tracking-inputic"><?php typee_the_icon( 'package_2', 16 ); ?></span>
								<input id="tracking-order-code" name="order_code" type="text" required placeholder="VD: 4352, #TY-4352 hoặc RMA-2026..." autocomplete="off" />
							</div>
						</div>

						<div class="typee-tracking-field">
							<label for="tracking-contact">Số điện thoại hoặc Email đặt hàng <span class="is-req">*</span></label>
							<div class="typee-tracking-inputwrap">
								<span class="typee-tracking-inputic"><?php typee_the_icon( 'call', 16 ); ?></span>
								<input id="tracking-contact" name="contact" type="text" required placeholder="VD: 0918248899 hoặc email@domain.com" autocomplete="off" />
							</div>
						</div>
					</div>

					<div class="typee-tracking-form__foot">
						<p class="typee-tracking-form__hint">
							<?php typee_the_icon( 'lock', 14 ); ?>
							<span>Hệ thống đối chiếu trực tiếp Mã đơn hàng và SĐT/Email để bảo mật thông tin người nhận.</span>
						</p>
						<button type="submit" class="ty-btn ty-btn--primary typee-tracking-submit" data-lookup-btn>
							<?php typee_the_icon( 'search', 16 ); ?>
							<span data-lookup-btn-text>Tra cứu ngay</span>
						</button>
					</div>

					<div class="typee-tracking-alert" data-lookup-alert role="alert" hidden></div>
				</form>
			</div>

			<!-- Vùng hiển thị kết quả tra cứu thật từ WooCommerce -->
			<div class="typee-tracking-result" data-tracking-result hidden></div>
		</section>

		<!-- 5. TAB 2: CREATE e-RMA WARRANTY REQUEST -->
		<section
			class="typee-tracking-panel"
			role="tabpanel"
			id="tracking-panel-rma"
			aria-labelledby="tracking-tab-rma"
			<?php echo 'rma' !== $initial_tab ? 'hidden' : ''; ?>
		>
			<div class="typee-tracking-card typee-warranty-card">
				<div class="typee-tracking-card__head">
					<?php typee_the_icon( 'handyman', 20, 'typee-tracking-card__ic' ); ?>
					<h2>Gửi yêu cầu bảo hành điện tử e-RMA</h2>
				</div>

				<form class="typee-warranty-form" data-warranty-form>
					<div class="typee-tracking-form__grid">
						<div class="typee-tracking-field">
							<label for="rma-order-code">Mã đơn hàng đã mua <span class="is-req">*</span></label>
							<div class="typee-tracking-inputwrap">
								<span class="typee-tracking-inputic"><?php typee_the_icon( 'package_2', 16 ); ?></span>
								<input id="rma-order-code" name="order_code" type="text" required placeholder="VD: 4352 hoặc #TY-4352" autocomplete="off" data-rma-order />
							</div>
						</div>

						<div class="typee-tracking-field">
							<label for="rma-contact">SĐT hoặc Email đặt hàng <span class="is-req">*</span></label>
							<div class="typee-tracking-inputwrap">
								<span class="typee-tracking-inputic"><?php typee_the_icon( 'call', 16 ); ?></span>
								<input id="rma-contact" name="contact" type="text" required placeholder="Nhập SĐT hoặc Email khớp đơn hàng" autocomplete="off" data-rma-contact />
							</div>
						</div>
					</div>

					<div class="typee-warranty-verify-row">
						<button type="button" class="ty-btn ty-btn--dark" data-rma-verify>
							<?php typee_the_icon( 'verified', 16 ); ?>
							<span>Xác thực đơn &amp; Tải danh sách sản phẩm</span>
						</button>
						<span class="typee-warranty-verify-hint" data-rma-verify-hint>Xác thực đơn hàng để chọn đúng sản phẩm còn hạn bảo hành.</span>
					</div>

					<div class="typee-warranty-fields" data-rma-fields hidden>
						<div class="typee-tracking-form__grid">
							<div class="typee-tracking-field">
								<label for="rma-product">Chọn sản phẩm cần bảo hành <span class="is-req">*</span></label>
								<select id="rma-product" name="product_id" class="ty-select" required data-rma-product></select>
							</div>

							<div class="typee-tracking-field">
								<label for="rma-issue-type">Nhóm lỗi gặp phải <span class="is-req">*</span></label>
								<select id="rma-issue-type" name="issue_type" class="ty-select" required>
									<option value="Lỗi mạch PCB / Socket Hotswap">Lỗi mạch PCB / Socket Hotswap</option>
									<option value="Lỗi Switch (Double-click / Không nhận phím)">Lỗi Switch (Double-click / Không nhận phím)</option>
									<option value="Lỗi Pin / Kết nối Wireless 2.4G / Bluetooth">Lỗi Pin / Kết nối Wireless 2.4G / Bluetooth</option>
									<option value="Lỗi LED RGB / Màn hình TFT">Lỗi LED RGB / Màn hình TFT</option>
									<option value="Lỗi vỏ Case / Stabilizer / Phụ kiện">Lỗi vỏ Case / Stabilizer / Phụ kiện</option>
									<option value="Yêu cầu kỹ thuật khác">Yêu cầu kỹ thuật khác</option>
								</select>
							</div>
						</div>

						<div class="typee-tracking-field">
							<label for="rma-desc">Mô tả chi tiết tình trạng lỗi <span class="is-req">*</span></label>
							<textarea id="rma-desc" name="description" rows="4" class="ty-textarea" required minlength="10" placeholder="Mô tả cụ thể phím bị lỗi, thời điểm phát sinh và tình trạng kết nối để kỹ thuật viên chẩn đoán nhanh nhất..."></textarea>
						</div>

						<div class="typee-tracking-form__foot">
							<span class="typee-tracking-form__hint">
								<?php typee_the_icon( 'verified_user', 14 ); ?>
								<span>Yêu cầu sẽ được tạo mã Ticket e-RMA chính thức và chuyển ngay tới Kỹ thuật viên TYPEE Lab.</span>
							</span>
							<button type="submit" class="ty-btn ty-btn--primary typee-tracking-submit" data-rma-submit>
								<?php typee_the_icon( 'check_circle', 16 ); ?>
								<span data-rma-submit-text>Gửi yêu cầu bảo hành e-RMA</span>
							</button>
						</div>
					</div>

					<div class="typee-tracking-alert" data-rma-alert role="alert" hidden></div>
				</form>
			</div>
		</section>

		<!-- 6. BOTTOM 3 WARRANTY COMMITMENT CARDS (Figma 82:3597) -->
		<section class="typee-tracking-features" aria-label="Cam kết bảo hành TYPEE">
			<article class="typee-tracking-fcard">
				<span class="typee-tracking-fcard__ic"><?php typee_the_icon( 'autorenew', 22 ); ?></span>
				<h3>Chính sách đổi mới 1-đổi-1</h3>
				<p>Đổi mới ngay lập tức sản phẩm tương đương trong 7 ngày đầu tiên nếu phát sinh lỗi từ nhà sản xuất (PCB, chip controller, sạc không vào điện).</p>
				<a class="typee-tracking-fcard__link" href="<?php echo esc_url( typee_url( 'warranty' ) ?: home_url( '/' ) ); ?>">
					<span>Chi tiết chính sách đổi trả</span>
					<?php typee_the_icon( 'arrow_forward', 14 ); ?>
				</a>
			</article>

			<article class="typee-tracking-fcard">
				<span class="typee-tracking-fcard__ic"><?php typee_the_icon( 'keyboard', 22 ); ?></span>
				<h3>Hỗ trợ mượn phím sơ cua</h3>
				<p>Khách hàng gửi phím custom để bảo hành mạch PCB hoặc sửa chữa chuyên sâu tại Lab sẽ được hỗ trợ bàn phím văn phòng sơ cua miễn phí.</p>
				<button type="button" class="typee-tracking-fcard__link" data-switch-to-rma>
					<span>Tạo phiếu bảo hành ngay</span>
					<?php typee_the_icon( 'arrow_forward', 14 ); ?>
				</button>
			</article>

			<article class="typee-tracking-fcard">
				<span class="typee-tracking-fcard__ic"><?php typee_the_icon( 'history', 22 ); ?></span>
				<h3>Số hóa lịch sử bảo hành 12T</h3>
				<p>Mọi sản phẩm bán ra tại TYPEE đều tự động kích hoạt e-Warranty theo Mã đơn hàng &amp; SĐT, không cần giữ phiếu giấy.</p>
				<button type="button" class="typee-tracking-fcard__link" data-switch-to-lookup>
					<span>Kiểm tra hạn bảo hành</span>
					<?php typee_the_icon( 'arrow_forward', 14 ); ?>
				</button>
			</article>
		</section>

	</div>
</div>
