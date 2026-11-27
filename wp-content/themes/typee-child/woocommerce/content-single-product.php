<?php
/**
 * Nội dung Trang Chi Tiết Sản Phẩm – Figma 82:2 (Single Product PDP).
 * Hỗ trợ đầy đủ Simple Product & Variable Product, Gallery + Lightbox,
 * Tabs (Thông số EAV, Mô tả, Đánh giá thật, Bảo hành) & Sản phẩm liên quan.
 *
 * @package typee-child
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product || ! is_a( $product, WC_Product::class ) ) {
	$product = wc_get_product( get_the_ID() );
}
if ( ! $product ) {
	return;
}

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

$prod_id      = $product->get_id();
$is_variable  = $product->is_type( 'variable' );
$gallery      = typee_single_gallery_items( $product );
$specs_rows   = typee_single_specs_rows( $product );
$stock        = typee_stock_label( $product );
$is_out       = 'out' === $stock['state'];
$discount     = typee_discount_percent( $product );
$sku          = $product->get_sku();
$rating_avg   = (float) $product->get_average_rating();
$rating_count = (int) $product->get_review_count();
$sold_count   = (int) $product->get_total_sales();
$max_qty      = $product->get_max_purchase_quantity();
$max_attr     = ( $max_qty > 0 ) ? $max_qty : '';

// Danh mục chính cho Breadcrumb & Kicker.
$cat_terms   = wp_get_post_terms( $prod_id, 'product_cat' );
$primary_cat = ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) ? $cat_terms[0] : null;

// Giá hiển thị ban đầu.
$price_now     = (float) $product->get_price();
$price_regular = 0;
$price_max     = 0;
if ( $is_variable ) {
	$price_now = (float) $product->get_variation_price( 'min', true );
	$price_max = (float) $product->get_variation_price( 'max', true );
	if ( $product->is_on_sale() ) {
		$price_regular = (float) $product->get_variation_regular_price( 'min', true );
	}
} elseif ( $product->is_on_sale() ) {
	$price_regular = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
	$price_now     = (float) wc_get_price_to_display( $product );
} else {
	$price_now = (float) wc_get_price_to_display( $product );
}
$savings = ( $price_regular > $price_now ) ? ( $price_regular - $price_now ) : 0;

// Dữ liệu biến thể (chuẩn WooCommerce variations_form).
$available_variations = array();
$variation_attributes = array();
$default_attributes   = array();
if ( $is_variable ) {
	$available_variations = $product->get_available_variations();
	$variation_attributes = $product->get_variation_attributes();
	$default_attributes   = $product->get_default_attributes();
}

// Sản phẩm liên quan từ WooCommerce.
$related_ids = wc_get_related_products( $prod_id, 4 );
$related     = $related_ids ? typee_products_by_ids( $related_ids ) : array();

do_action( 'woocommerce_before_single_product' );
?>
<div id="product-<?php echo esc_attr( $prod_id ); ?>" <?php wc_product_class( 'ty-pdp', $product ); ?> data-pdp-root data-node="82:2">

	<!-- 1. BREADCRUMB & METADATA BAR (Figma 82:5) -->
	<section class="ty-pdp-bar">
		<div class="ty-container ty-pdp-bar__inner">
			<nav class="ty-pdp-bc" aria-label="Breadcrumb">
				<ol>
					<li>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
							<?php typee_the_icon( 'home_pin', 14 ); ?>
							<span>Trang chủ</span>
						</a>
					</li>
					<li class="ty-pdp-bc__sep" aria-hidden="true">/</li>
					<li><a href="<?php echo esc_url( typee_url( 'catalog' ) ); ?>">Cửa hàng</a></li>
					<?php if ( $primary_cat ) : ?>
						<li class="ty-pdp-bc__sep" aria-hidden="true">/</li>
						<li><a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>"><?php echo esc_html( $primary_cat->name ); ?></a></li>
					<?php endif; ?>
					<li class="ty-pdp-bc__sep" aria-hidden="true">/</li>
					<li aria-current="page"><?php echo esc_html( wp_trim_words( $product->get_name(), 8, '…' ) ); ?></li>
				</ol>
			</nav>

			<div class="ty-pdp-bar__meta">
				<?php if ( $is_out ) : ?>
					<span class="ty-pdp-pill is-out"><span class="ty-pdp-pill__dot"></span> TẠM HẾT HÀNG</span>
				<?php elseif ( $product->is_featured() || $sold_count >= 10 ) : ?>
					<span class="ty-pdp-pill"><span class="ty-pdp-pill__dot"></span> ĐANG BÁN CHẠY</span>
				<?php else : ?>
					<span class="ty-pdp-pill is-in"><span class="ty-pdp-pill__dot"></span> SẴN HÀNG KHO LAB</span>
				<?php endif; ?>
				<?php if ( $sku ) : ?>
					<span class="ty-pdp-bar__dot" aria-hidden="true">•</span>
					<span class="ty-pdp-bar__sku">SKU: <strong data-pdp-sku data-default-sku="<?php echo esc_attr( $sku ); ?>"><?php echo esc_html( $sku ); ?></strong></span>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- 2. MAIN PRODUCT SECTION: 12-COL DUAL GRID (Figma 82:28) -->
	<section class="ty-pdp-main">
		<div class="ty-container">
			<?php wc_print_notices(); ?>

			<div class="ty-pdp-grid">
				<!-- LEFT COLUMN: PRODUCT MEDIA GALLERY (7 COLS – Figma 82:30) -->
				<div class="ty-pdp-media">
					<div class="ty-pdp-stage" data-pdp-stage>
						<img
							class="ty-pdp-stage__img"
							src="<?php echo esc_url( $gallery[0]['large'] ); ?>"
							data-full="<?php echo esc_url( $gallery[0]['full'] ); ?>"
							data-default-src="<?php echo esc_url( $gallery[0]['large'] ); ?>"
							data-default-full="<?php echo esc_url( $gallery[0]['full'] ); ?>"
							alt="<?php echo esc_attr( $gallery[0]['alt'] ); ?>"
							loading="eager"
							fetchpriority="high"
							data-pdp-main-img
						/>

						<div class="ty-pdp-stage__badges">
							<?php if ( $discount > 0 && ! $is_out ) : ?>
								<span class="ty-pdp-badge ty-pdp-badge--sale" data-pdp-discount-badge><?php echo esc_html( sprintf( '-%d%% GIẢM', $discount ) ); ?></span>
							<?php endif; ?>
							<?php if ( ! $is_out ) : ?>
								<span class="ty-pdp-badge ty-pdp-badge--in">
									<?php typee_the_icon( 'verified', 13 ); ?>
									<span>SẴN HÀNG LAB</span>
								</span>
							<?php else : ?>
								<span class="ty-pdp-badge ty-pdp-badge--out">HẾT HÀNG</span>
							<?php endif; ?>
						</div>

						<span class="ty-pdp-stage__tag">
							<?php typee_the_icon( 'photo_camera', 13 ); ?>
							<span>Ảnh thực tế sản phẩm tại TYPEE</span>
						</span>

						<button type="button" class="ty-pdp-zoom-btn" data-pdp-zoom aria-label="Phóng to ảnh sản phẩm">
							<?php typee_the_icon( 'search', 18 ); ?>
						</button>
					</div>

					<?php if ( count( $gallery ) > 1 ) : ?>
						<div class="ty-pdp-thumbs" role="list" aria-label="Thư viện ảnh sản phẩm">
							<?php foreach ( $gallery as $idx => $img ) : ?>
								<button
									type="button"
									class="ty-pdp-thumb<?php echo 0 === $idx ? ' is-active' : ''; ?>"
									data-pdp-thumb
									data-index="<?php echo (int) $idx; ?>"
									data-large="<?php echo esc_url( $img['large'] ); ?>"
									data-full="<?php echo esc_url( $img['full'] ); ?>"
									aria-label="<?php echo esc_attr( sprintf( 'Xem ảnh %d', $idx + 1 ) ); ?>"
									aria-current="<?php echo 0 === $idx ? 'true' : 'false'; ?>"
								>
									<img src="<?php echo esc_url( $img['thumb'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ); ?>" loading="lazy" decoding="async" />
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<!-- Quick Lab Commitments Panel (Figma 82:59) -->
					<div class="ty-pdp-commit">
						<div class="ty-pdp-commit__item">
							<?php typee_the_icon( 'verified_user', 20, 'ty-pdp-commit__ic' ); ?>
							<div>
								<strong>Bảo hành 12T</strong>
								<span>Chính hãng 1-đổi-1</span>
							</div>
						</div>
						<div class="ty-pdp-commit__item">
							<?php typee_the_icon( 'task_alt', 20, 'ty-pdp-commit__ic' ); ?>
							<div>
								<strong>Kiểm định KCS</strong>
								<span>Quy trình 4 bước</span>
							</div>
						</div>
						<div class="ty-pdp-commit__item">
							<?php typee_the_icon( 'handyman', 20, 'ty-pdp-commit__ic' ); ?>
							<div>
								<strong>Cân Wire Stab</strong>
								<span>Hỗ trợ tại Studio</span>
							</div>
						</div>
						<div class="ty-pdp-commit__item">
							<?php typee_the_icon( 'local_shipping', 20, 'ty-pdp-commit__ic' ); ?>
							<div>
								<strong>Freeship 0₫</strong>
								<span>Giao nhanh toàn quốc</span>
							</div>
						</div>
					</div>
				</div>

				<!-- RIGHT COLUMN: PRODUCT INFORMATION & PURCHASE MATRIX (5 COLS – Figma 82:92) -->
				<div class="ty-pdp-info">
					<div class="ty-pdp-kicker">
						<div class="ty-pdp-kicker__brand">
							<span class="is-accent">TYPEE STUDIO</span>
							<?php if ( $primary_cat ) : ?>
								<span class="is-sep">×</span>
								<a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>"><?php echo esc_html( mb_strtoupper( $primary_cat->name, 'UTF-8' ) ); ?></a>
							<?php endif; ?>
						</div>
						<span class="ty-pdp-kicker__tag"><?php echo $is_variable ? 'Nhiều tùy chọn' : 'Chính hãng'; ?></span>
					</div>

					<h1 class="ty-pdp-title"><?php echo esc_html( $product->get_name() ); ?></h1>

					<div class="ty-pdp-metarow">
						<a class="ty-pdp-rating" href="#tab-reviews" data-pdp-goto-reviews>
							<?php typee_the_icon( 'star', 15, 'ty-pdp-rating__star' ); ?>
							<strong><?php echo esc_html( $rating_count > 0 ? number_format( $rating_avg, 1 ) : '5.0' ); ?></strong>
							<span>(<?php echo esc_html( $rating_count > 0 ? $rating_count . ' đánh giá' : 'Chưa có đánh giá' ); ?>)</span>
						</a>
						<span class="ty-pdp-metarow__sep" aria-hidden="true">|</span>
						<span class="ty-pdp-stock is-<?php echo esc_attr( $stock['state'] ); ?>" data-pdp-stock>
							<span class="ty-pdp-stock__dot" aria-hidden="true"></span>
							<span data-pdp-stock-text data-default-stock="<?php echo esc_attr( $stock['text'] ); ?>"><?php echo esc_html( $stock['text'] ); ?></span>
						</span>
						<?php if ( $sold_count > 0 ) : ?>
							<span class="ty-pdp-metarow__sep" aria-hidden="true">|</span>
							<span class="ty-pdp-sold">Đã bán <strong><?php echo esc_html( number_format_i18n( $sold_count ) ); ?>+</strong></span>
						<?php endif; ?>
					</div>

					<!-- Pricing Block (Figma 82:123) -->
					<div class="ty-pdp-pricebox" data-pdp-pricebox>
						<div class="ty-pdp-pricebox__row" data-pdp-price-html>
							<?php if ( $price_now > 0 ) : ?>
								<span class="ty-pdp-pricebox__now">
									<?php
									if ( $is_variable && $price_max > $price_now ) {
										echo esc_html( typee_money( $price_now ) . ' – ' . typee_money( $price_max ) );
									} else {
										echo esc_html( typee_money( $price_now ) );
									}
									?>
								</span>
								<?php if ( $price_regular > $price_now ) : ?>
									<del class="ty-pdp-pricebox__was"><?php echo esc_html( typee_money( $price_regular ) ); ?></del>
								<?php endif; ?>
								<?php if ( $discount > 0 ) : ?>
									<span class="ty-pdp-pricebox__pct"><?php echo esc_html( sprintf( '-%d%%', $discount ) ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="ty-pdp-pricebox__now">Liên hệ</span>
							<?php endif; ?>
						</div>
						<?php if ( $savings > 0 ) : ?>
							<div class="ty-pdp-pricebox__save" data-pdp-savings>
								<?php typee_the_icon( 'sell', 14 ); ?>
								<span>Tiết kiệm <strong><?php echo esc_html( typee_money( $savings ) ); ?></strong> so với giá niêm yết</span>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $product->get_short_description() ) : ?>
						<div class="ty-pdp-excerpt">
							<?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?>
						</div>
					<?php endif; ?>

					<!-- PURCHASE FORM (Simple & Variable Products) -->
					<?php if ( $is_variable ) : ?>
						<form
							class="variations_form cart ty-pdp-form"
							action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
							method="post"
							enctype="multipart/form-data"
							data-product_id="<?php echo absint( $prod_id ); ?>"
							data-product_variations="<?php echo wc_esc_json( wp_json_encode( $available_variations ) ); ?>"
							data-pdp-form="variable"
						>
							<?php do_action( 'woocommerce_before_variations_form' ); ?>

							<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
								<p class="ty-pdp-alert is-error">Sản phẩm này hiện tạm hết hàng ở tất cả các phiên bản.</p>
							<?php else : ?>
								<div class="ty-pdp-variants variations">
									<?php foreach ( $variation_attributes as $attribute_name => $options ) : ?>
										<?php
										$attr_id     = sanitize_title( $attribute_name );
										$attr_label  = wc_attribute_label( $attribute_name, $product );
										$selected    = isset( $_REQUEST[ 'attribute_' . $attr_id ] ) ? wc_clean( wp_unslash( $_REQUEST[ 'attribute_' . $attr_id ] ) ) : ( isset( $default_attributes[ $attr_id ] ) ? $default_attributes[ $attr_id ] : '' ); // phpcs:ignore WordPress.Security.NonceVerification
										?>
										<div class="ty-pdp-vgroup" data-vgroup="<?php echo esc_attr( 'attribute_' . $attr_id ); ?>">
											<div class="ty-pdp-vgroup__head">
												<span class="ty-pdp-vgroup__label"><?php echo esc_html( mb_strtoupper( $attr_label, 'UTF-8' ) ); ?>:</span>
												<span class="ty-pdp-vgroup__selected" data-vgroup-selected><?php echo $selected ? esc_html( $selected ) : 'Chưa chọn'; ?></span>
											</div>

											<div class="ty-pdp-vopts" role="radiogroup" aria-label="<?php echo esc_attr( $attr_label ); ?>">
												<?php
												foreach ( $options as $opt ) :
													$opt_label = $opt;
													if ( taxonomy_exists( $attribute_name ) ) {
														$t = get_term_by( 'slug', $opt, $attribute_name );
														if ( $t && ! is_wp_error( $t ) ) {
															$opt_label = $t->name;
														}
													}
													$hex     = typee_guess_swatch_color( $opt_label );
													$is_sel  = sanitize_title( $selected ) === sanitize_title( $opt ) || (string) $selected === (string) $opt;
													?>
													<button
														type="button"
														class="ty-pdp-vopt<?php echo $is_sel ? ' is-active' : ''; ?>"
														data-vopt-attr="<?php echo esc_attr( 'attribute_' . $attr_id ); ?>"
														data-vopt-val="<?php echo esc_attr( $opt ); ?>"
														data-vopt-label="<?php echo esc_attr( $opt_label ); ?>"
														aria-pressed="<?php echo $is_sel ? 'true' : 'false'; ?>"
													>
														<?php if ( $hex ) : ?>
															<span class="ty-pdp-vopt__swatch" style="background:<?php echo esc_attr( $hex ); ?>" aria-hidden="true"></span>
														<?php endif; ?>
														<span><?php echo esc_html( $opt_label ); ?></span>
													</button>
												<?php endforeach; ?>
											</div>

											<!-- Native WooCommerce Select (ẩn trực quan, giữ đồng bộ 100% với wc-add-to-cart-variation) -->
											<div class="ty-pdp-vselect-wrap">
												<?php
												wc_dropdown_variation_attribute_options(
													array(
														'options'   => $options,
														'attribute' => $attribute_name,
														'product'   => $product,
														'selected'  => $selected,
													)
												);
												?>
											</div>
										</div>
									<?php endforeach; ?>

									<a class="reset_variations ty-pdp-reset-var" href="#" style="display:none">Xóa lựa chọn</a>
								</div>

								<div class="single_variation_wrap">
									<div class="woocommerce-variation single_variation ty-pdp-var-summary"></div>
									<div class="woocommerce-variation-add-to-cart variations_button">
										<div class="ty-pdp-alert" data-pdp-alert role="alert" hidden></div>

										<div class="ty-pdp-cta-row">
											<div class="ty-pdp-qty" data-pdp-qty>
												<button type="button" class="ty-pdp-qty__btn" data-qty-dec aria-label="Giảm số lượng">
													<?php typee_the_icon( 'remove', 14 ); ?>
												</button>
												<input
													type="number"
													class="ty-pdp-qty__input qty"
													name="quantity"
													value="1"
													min="1"
													<?php echo $max_attr ? 'max="' . esc_attr( $max_attr ) . '"' : ''; ?>
													step="1"
													inputmode="numeric"
													aria-label="Số lượng"
													data-qty-input
												/>
												<button type="button" class="ty-pdp-qty__btn" data-qty-inc aria-label="Tăng số lượng">
													<?php typee_the_icon( 'add', 14 ); ?>
												</button>
											</div>

											<button type="submit" class="single_add_to_cart_button ty-pdp-add" data-pdp-add>
												<?php typee_the_icon( 'add_shopping_cart', 18 ); ?>
												<span data-pdp-add-text>THÊM VÀO GIỎ HÀNG</span>
											</button>

											<button type="button" class="ty-pdp-icbtn" data-typee-wishlist="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="Yêu thích">
												<?php typee_the_icon( 'favorite', 18 ); ?>
											</button>
											<button type="button" class="ty-pdp-icbtn" data-typee-compare="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="So sánh">
												<?php typee_the_icon( 'compare_arrows', 18 ); ?>
											</button>
										</div>

										<button type="button" class="ty-pdp-buynow" data-pdp-buynow>
											<?php typee_the_icon( 'bolt', 16 ); ?>
											<span>MUA NGAY (GIAO NHANH MIỄN PHÍ 0₫)</span>
										</button>

										<input type="hidden" name="add-to-cart" value="<?php echo absint( $prod_id ); ?>" />
										<input type="hidden" name="product_id" value="<?php echo absint( $prod_id ); ?>" />
										<input type="hidden" name="variation_id" class="variation_id" value="0" />
									</div>
								</div>
							<?php endif; ?>

							<?php do_action( 'woocommerce_after_variations_form' ); ?>
						</form>
					<?php else : ?>
						<form
							class="cart ty-pdp-form"
							action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
							method="post"
							enctype="multipart/form-data"
							data-product_id="<?php echo absint( $prod_id ); ?>"
							data-pdp-form="simple"
						>
							<div class="ty-pdp-alert" data-pdp-alert role="alert" hidden></div>

							<?php if ( $is_out || ! $product->is_purchasable() ) : ?>
								<div class="ty-pdp-alert is-error">Sản phẩm này hiện tạm hết hàng. Vui lòng quay lại sau hoặc liên hệ Hotline <?php echo esc_html( typee_copy( 'hotline' ) ); ?>.</div>
								<div class="ty-pdp-cta-row">
									<button type="button" class="ty-pdp-add is-disabled" disabled>TẠM HẾT HÀNG</button>
									<button type="button" class="ty-pdp-icbtn" data-typee-wishlist="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="Yêu thích">
										<?php typee_the_icon( 'favorite', 18 ); ?>
									</button>
									<button type="button" class="ty-pdp-icbtn" data-typee-compare="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="So sánh">
										<?php typee_the_icon( 'compare_arrows', 18 ); ?>
									</button>
								</div>
							<?php else : ?>
								<div class="ty-pdp-cta-row">
									<div class="ty-pdp-qty" data-pdp-qty>
										<button type="button" class="ty-pdp-qty__btn" data-qty-dec aria-label="Giảm số lượng">
											<?php typee_the_icon( 'remove', 14 ); ?>
										</button>
										<input
											type="number"
											class="ty-pdp-qty__input qty"
											name="quantity"
											value="1"
											min="1"
											<?php echo $max_attr ? 'max="' . esc_attr( $max_attr ) . '"' : ''; ?>
											step="1"
											inputmode="numeric"
											aria-label="Số lượng"
											data-qty-input
										/>
										<button type="button" class="ty-pdp-qty__btn" data-qty-inc aria-label="Tăng số lượng">
											<?php typee_the_icon( 'add', 14 ); ?>
										</button>
									</div>

									<button type="submit" name="add-to-cart" value="<?php echo absint( $prod_id ); ?>" class="single_add_to_cart_button ty-pdp-add" data-pdp-add>
										<?php typee_the_icon( 'add_shopping_cart', 18 ); ?>
										<span data-pdp-add-text>THÊM VÀO GIỎ HÀNG</span>
									</button>

									<button type="button" class="ty-pdp-icbtn" data-typee-wishlist="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="Yêu thích">
										<?php typee_the_icon( 'favorite', 18 ); ?>
									</button>
									<button type="button" class="ty-pdp-icbtn" data-typee-compare="<?php echo esc_attr( $prod_id ); ?>" aria-pressed="false" aria-label="So sánh">
										<?php typee_the_icon( 'compare_arrows', 18 ); ?>
									</button>
								</div>

								<button type="button" class="ty-pdp-buynow" data-pdp-buynow>
									<?php typee_the_icon( 'bolt', 16 ); ?>
									<span>MUA NGAY (GIAO NHANH MIỄN PHÍ 0₫)</span>
								</button>

								<input type="hidden" name="product_id" value="<?php echo absint( $prod_id ); ?>" />
							<?php endif; ?>
						</form>
					<?php endif; ?>

					<!-- Custom Builder Direct Launch Card (Figma 82:262) -->
					<div class="ty-pdp-builder-card">
						<div class="ty-pdp-builder-card__left">
							<span class="ty-pdp-builder-card__ic"><?php typee_the_icon( 'build_circle', 20 ); ?></span>
							<div>
								<strong>Muốn tự phối Switch hoặc Keycap?</strong>
								<span>Mở Custom Builder 6 bước để kiểm tra tương thích ngay</span>
							</div>
						</div>
						<a class="ty-pdp-builder-card__btn" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">
							<span>Nạp vào Builder</span>
							<?php typee_the_icon( 'arrow_forward', 14 ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- 3. DEEP-DIVE TECHNICAL SPECIFICATIONS & TABS (Figma 82:278) -->
	<section class="ty-pdp-tabs-sec" id="pdp-details">
		<div class="ty-container">
			<div class="ty-pdp-tablist" role="tablist" aria-label="Chi tiết sản phẩm">
				<button type="button" class="ty-pdp-tab" role="tab" id="tab-btn-specs" aria-controls="tab-specs" aria-selected="true" tabindex="0">
					<?php typee_the_icon( 'memory', 16 ); ?>
					<span>Thông Số Kỹ Thuật (EAV Specs)</span>
				</button>
				<button type="button" class="ty-pdp-tab" role="tab" id="tab-btn-desc" aria-controls="tab-desc" aria-selected="false" tabindex="-1">
					<?php typee_the_icon( 'article', 16 ); ?>
					<span>Mô Tả Chi Tiết</span>
				</button>
				<button type="button" class="ty-pdp-tab" role="tab" id="tab-btn-reviews" aria-controls="tab-reviews" aria-selected="false" tabindex="-1">
					<?php typee_the_icon( 'star', 16 ); ?>
					<span>Đánh Giá Khách Hàng (<?php echo (int) $rating_count; ?>)</span>
				</button>
				<button type="button" class="ty-pdp-tab" role="tab" id="tab-btn-warranty" aria-controls="tab-warranty" aria-selected="false" tabindex="-1">
					<?php typee_the_icon( 'verified_user', 16 ); ?>
					<span>Bảo Hành &amp; Hỗ Trợ</span>
				</button>
			</div>

			<!-- TAB 1: EAV SPECS TABLE -->
			<div class="ty-pdp-tabpanel ty-tabpanel" role="tabpanel" id="tab-specs" aria-labelledby="tab-btn-specs">
				<div class="ty-pdp-panel-head">
					<div>
						<h2>Bảng Chi Tiết Cấu Hình Kỹ Thuật</h2>
						<p>Thông số kỹ thuật chuẩn được đồng bộ trực tiếp từ cơ sở dữ liệu sản phẩm TYPEE.</p>
					</div>
					<span class="ty-pdp-panel-pill">Tương thích Windows / macOS / Linux</span>
				</div>

				<?php if ( ! empty( $specs_rows ) ) : ?>
					<div class="ty-pdp-specs-wrap">
						<table class="ty-pdp-specs">
							<tbody>
								<?php foreach ( $specs_rows as $row ) : ?>
									<tr>
										<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
										<td><?php echo esc_html( $row['value'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p class="ty-pdp-empty-note">Thông số kỹ thuật chi tiết của sản phẩm này đang được kỹ thuật viên TYPEE cập nhật.</p>
				<?php endif; ?>
			</div>

			<!-- TAB 2: DESCRIPTION -->
			<div class="ty-pdp-tabpanel ty-tabpanel" role="tabpanel" id="tab-desc" aria-labelledby="tab-btn-desc" hidden>
				<div class="ty-pdp-panel-head">
					<div>
						<h2>Mô Tả &amp; Đánh Giá Chuyên Sâu</h2>
						<p>Thông tin chi tiết về thiết kế, trải nghiệm gõ và phụ kiện đi kèm.</p>
					</div>
				</div>
				<div class="ty-pdp-prose">
					<?php
					$content = apply_filters( 'the_content', $product->get_description() );
					if ( trim( wp_strip_all_tags( (string) $content ) ) !== '' ) {
						echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
					} elseif ( $product->get_short_description() ) {
						echo wp_kses_post( wpautop( $product->get_short_description() ) );
					} else {
						echo '<p class="ty-pdp-empty-note">Chưa có mô tả chi tiết cho sản phẩm này.</p>';
					}
					?>
				</div>
			</div>

			<!-- TAB 3: REVIEWS (WooCommerce Real Reviews) -->
			<div class="ty-pdp-tabpanel ty-tabpanel" role="tabpanel" id="tab-reviews" aria-labelledby="tab-btn-reviews" hidden>
				<div class="ty-pdp-panel-head">
					<div>
						<h2>Đánh Giá Thực Tế Từ Khách Hàng</h2>
						<p>Chia sẻ trải nghiệm gõ thực tế sau khi sử dụng sản phẩm tại TYPEE.</p>
					</div>
					<span class="ty-pdp-panel-pill">
						<?php echo esc_html( $rating_count > 0 ? sprintf( '★ %.1f / 5 (%d lượt)', $rating_avg, $rating_count ) : 'Chưa có đánh giá' ); ?>
					</span>
				</div>
				<div class="ty-pdp-reviews-wrap">
					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					} else {
						echo '<p class="ty-pdp-empty-note">Tính năng đánh giá đang tạm đóng cho sản phẩm này.</p>';
					}
					?>
				</div>
			</div>

			<!-- TAB 4: WARRANTY & SUPPORT -->
			<div class="ty-pdp-tabpanel ty-tabpanel" role="tabpanel" id="tab-warranty" aria-labelledby="tab-btn-warranty" hidden>
				<div class="ty-pdp-panel-head">
					<div>
						<h2>Chính Sách Bảo Hành e-RMA &amp; Hỗ Trợ Kỹ Thuật</h2>
						<p>An tâm sử dụng với quy trình kiểm định KCS và bảo hành điện tử theo số điện thoại.</p>
					</div>
				</div>
				<div class="ty-pdp-warranty-grid">
					<div class="ty-pdp-wcard">
						<strong>Bảo hành 12 tháng chính hãng</strong>
						<p>Áp dụng cho khung Kit CNC, mạch PCB hotswap và pin. Đổi mới 1-đổi-1 trong 7 ngày đầu nếu phát sinh lỗi từ nhà sản xuất.</p>
					</div>
					<div class="ty-pdp-wcard">
						<strong>Kích hoạt e-RMA tự động</strong>
						<p>Không cần giữ giấy bảo hành — hệ thống tự lưu theo SĐT đặt hàng và mã SKU. Tra cứu nhanh tại trang <a href="<?php echo esc_url( typee_url( 'tracking' ) ); ?>">Tra cứu đơn hàng / RMA</a>.</p>
					</div>
					<div class="ty-pdp-wcard">
						<strong>Hỗ trợ QMK/VIA &amp; Modding Lab</strong>
						<p>Kỹ thuật viên TYPEE hỗ trợ cài đặt keymap JSON/VIA, hướng dẫn lube switch và cân wire stab miễn phí qua Hotline <?php echo esc_html( typee_copy( 'hotline' ) ); ?>.</p>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- 4. RELATED PRODUCTS (Figma 82:459) -->
	<?php if ( ! empty( $related ) ) : ?>
		<section class="ty-pdp-related">
			<div class="ty-container">
				<div class="ty-pdp-sec-head">
					<h2>Sản Phẩm Liên Quan</h2>
					<a class="ty-pdp-sec-link" href="<?php echo esc_url( $primary_cat ? get_term_link( $primary_cat ) : typee_url( 'catalog' ) ); ?>">
						<span>Xem tất cả</span>
						<?php typee_the_icon( 'arrow_forward', 15 ); ?>
					</a>
				</div>
				<div class="ty-grid-4">
					<?php foreach ( $related as $rel_prod ) : ?>
						<?php get_template_part( 'template-parts/product/card', null, array( 'product' => $rel_prod ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- 5. CUSTOM BUILDER PROMO CALLOUT BANNER (Figma 82:521) -->
	<section class="ty-pdp-promo">
		<div class="ty-container">
			<div class="ty-pdp-promo__card">
				<div class="ty-pdp-promo__left">
					<span class="ty-pdp-promo__ic"><?php typee_the_icon( 'keyboard', 28 ); ?></span>
					<div>
						<span class="ty-pdp-promo__kicker">TYPEE CUSTOM STUDIO</span>
						<h2>Chưa chắc chắn về cấu hình? Thử tự phối phím trong Custom Builder 6 bước!</h2>
						<p>Trực quan hóa phối màu keycap, chọn loại switch, kiểm tra tương thích socket và dự toán chi phí real-time.</p>
					</div>
				</div>
				<a class="ty-btn ty-btn--primary ty-pdp-promo__btn" href="<?php echo esc_url( typee_url( 'builder' ) ); ?>">
					<?php typee_the_icon( 'tune', 18 ); ?>
					<span>Mở Custom Builder Ngay</span>
				</a>
			</div>
		</div>
	</section>

	<!-- LIGHTBOX MODAL (Phóng to ảnh sản phẩm) -->
	<div class="ty-pdp-lightbox" id="ty-pdp-lightbox" role="dialog" aria-modal="true" aria-label="Xem ảnh phóng to" hidden>
		<button type="button" class="ty-pdp-lb__close" data-lb-close aria-label="Đóng ảnh"><?php typee_the_icon( 'close', 22 ); ?></button>
		<?php if ( count( $gallery ) > 1 ) : ?>
			<button type="button" class="ty-pdp-lb__nav is-prev" data-lb-prev aria-label="Ảnh trước"><?php typee_the_icon( 'chevron_left', 26 ); ?></button>
			<button type="button" class="ty-pdp-lb__nav is-next" data-lb-next aria-label="Ảnh kế tiếp"><?php typee_the_icon( 'chevron_right', 26 ); ?></button>
		<?php endif; ?>
		<figure class="ty-pdp-lb__figure">
			<img src="" alt="" data-lb-img />
			<figcaption data-lb-caption></figcaption>
		</figure>
	</div>

	<!-- MOBILE STICKY BUY BAR (<= 767px) -->
	<?php if ( ! $is_out ) : ?>
		<div class="ty-pdp-sticky" data-pdp-sticky>
			<div class="ty-pdp-sticky__price">
				<span>Giá bán</span>
				<strong data-pdp-sticky-price><?php echo esc_html( $price_now > 0 ? typee_money( $price_now ) : 'Liên hệ' ); ?></strong>
			</div>
			<div class="ty-pdp-sticky__actions">
				<button type="button" class="ty-pdp-sticky__add" data-pdp-sticky-add>Thêm vào giỏ</button>
				<button type="button" class="ty-pdp-sticky__buy" data-pdp-sticky-buy>Mua ngay</button>
			</div>
		</div>
	<?php endif; ?>
</div>
<?php
do_action( 'woocommerce_after_single_product' );
