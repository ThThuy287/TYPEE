/**
 * Trang Chi Tiết Sản Phẩm (Figma 82:2):
 * - Thư viện ảnh & Lightbox Zoom (hỗ trợ phím Esc / mũi tên)
 * - Đồng bộ Swatches biến thể ↔ WooCommerce `variations_form` (đổi ảnh, giá, SKU, tồn kho, max_qty)
 * - Bộ chọn số lượng (- / +) tôn trọng giới hạn tồn kho
 * - AJAX Thêm vào giỏ hàng & Mua ngay (/checkout) có xác thực Nonce
 */
(function ($) {
	'use strict';

	var root = document.querySelector('[data-pdp-root]');
	if (!root) { return; }

	/* ---------- 1. Gallery & Lightbox ---------- */
	var mainImg    = root.querySelector('[data-pdp-main-img]');
	var stage      = root.querySelector('[data-pdp-stage]');
	var thumbBtns  = Array.prototype.slice.call(root.querySelectorAll('[data-pdp-thumb]'));
	var lightbox   = document.getElementById('ty-pdp-lightbox');
	var lbImg      = lightbox ? lightbox.querySelector('[data-lb-img]') : null;
	var lbCap      = lightbox ? lightbox.querySelector('[data-lb-caption]') : null;
	var currentIdx = 0;

	function setMainImage(largeSrc, fullSrc, altText, thumbIndex) {
		if (!mainImg || !largeSrc) { return; }
		mainImg.src = largeSrc;
		mainImg.setAttribute('data-full', fullSrc || largeSrc);
		if (altText) { mainImg.alt = altText; }
		if (typeof thumbIndex === 'number' && thumbIndex >= 0) {
			currentIdx = thumbIndex;
			thumbBtns.forEach(function (b, i) {
				var on = i === thumbIndex;
				b.classList.toggle('is-active', on);
				b.setAttribute('aria-current', on ? 'true' : 'false');
			});
		}
	}

	thumbBtns.forEach(function (btn, idx) {
		btn.addEventListener('click', function () {
			var img = btn.querySelector('img');
			setMainImage(btn.getAttribute('data-large'), btn.getAttribute('data-full'), img ? img.alt : '', idx);
		});
	});

	function openLightbox(idx) {
		if (!lightbox || !lbImg) { return; }
		var src = mainImg ? (mainImg.getAttribute('data-full') || mainImg.src) : '';
		var alt = mainImg ? mainImg.alt : '';
		if (thumbBtns.length && typeof idx === 'number' && thumbBtns[idx]) {
			currentIdx = idx;
			src = thumbBtns[idx].getAttribute('data-full') || thumbBtns[idx].getAttribute('data-large');
			var im = thumbBtns[idx].querySelector('img');
			alt = im ? im.alt : alt;
		}
		lbImg.src = src;
		lbImg.alt = alt;
		if (lbCap) { lbCap.textContent = alt; }
		lightbox.hidden = false;
		document.body.classList.add('typee-lock');
	}

	function closeLightbox() {
		if (!lightbox || lightbox.hidden) { return; }
		lightbox.hidden = true;
		document.body.classList.remove('typee-lock');
	}

	if (stage) {
		stage.addEventListener('click', function (e) {
			if (e.target.closest('button')) { return; }
			openLightbox(currentIdx);
		});
	}
	var zoomBtn = root.querySelector('[data-pdp-zoom]');
	if (zoomBtn) { zoomBtn.addEventListener('click', function () { openLightbox(currentIdx); }); }

	if (lightbox) {
		lightbox.addEventListener('click', function (e) {
			if (e.target === lightbox || e.target.closest('[data-lb-close]')) { closeLightbox(); }
			if (e.target.closest('[data-lb-prev]') && thumbBtns.length) {
				openLightbox((currentIdx - 1 + thumbBtns.length) % thumbBtns.length);
			}
			if (e.target.closest('[data-lb-next]') && thumbBtns.length) {
				openLightbox((currentIdx + 1) % thumbBtns.length);
			}
		});
		document.addEventListener('keydown', function (e) {
			if (lightbox.hidden) { return; }
			if (e.key === 'Escape') { closeLightbox(); }
			else if (e.key === 'ArrowLeft' && thumbBtns.length) { openLightbox((currentIdx - 1 + thumbBtns.length) % thumbBtns.length); }
			else if (e.key === 'ArrowRight' && thumbBtns.length) { openLightbox((currentIdx + 1) % thumbBtns.length); }
		});
	}

	/* ---------- 2. Quantity Stepper ---------- */
	var qtyInput = root.querySelector('[data-qty-input]');
	var decBtn   = root.querySelector('[data-qty-dec]');
	var incBtn   = root.querySelector('[data-qty-inc]');

	function clampQty(val) {
		if (!qtyInput) { return 1; }
		var min = parseInt(qtyInput.min, 10) || 1;
		var max = parseInt(qtyInput.max, 10) || 9999;
		var n = parseInt(val, 10);
		if (isNaN(n) || n < min) { n = min; }
		if (n > max) { n = max; }
		qtyInput.value = n;
		return n;
	}
	if (decBtn && qtyInput) { decBtn.addEventListener('click', function () { clampQty((parseInt(qtyInput.value, 10) || 1) - 1); }); }
	if (incBtn && qtyInput) { incBtn.addEventListener('click', function () { clampQty((parseInt(qtyInput.value, 10) || 1) + 1); }); }
	if (qtyInput) { qtyInput.addEventListener('change', function () { clampQty(qtyInput.value); }); }

	/* ---------- 3. Variations Sync (Variable Product) ---------- */
	var form          = root.querySelector('[data-pdp-form]');
	var $form         = form ? $(form) : null;
	var alertBox      = root.querySelector('[data-pdp-alert]');
	var addBtn        = root.querySelector('[data-pdp-add]');
	var addBtnText    = root.querySelector('[data-pdp-add-text]');
	var buyNowBtn     = root.querySelector('[data-pdp-buynow]');
	var skuEl         = root.querySelector('[data-pdp-sku]');
	var stockWrap     = root.querySelector('[data-pdp-stock]');
	var stockTextEl   = root.querySelector('[data-pdp-stock-text]');
	var priceHtmlBox  = root.querySelector('[data-pdp-price-html]');
	var stickyPriceEl = root.querySelector('[data-pdp-sticky-price]');
	var defaultPriceHtml = priceHtmlBox ? priceHtmlBox.innerHTML : '';

	function showAlert(msg, type) {
		if (!alertBox) { return; }
		if (!msg) { alertBox.hidden = true; alertBox.textContent = ''; return; }
		alertBox.className = 'ty-pdp-alert ' + (type === 'success' ? 'is-success' : 'is-error');
		alertBox.textContent = msg;
		alertBox.hidden = false;
	}

	function formatVnd(num) {
		var cur = (window.TYPEE && window.TYPEE.currency) ? window.TYPEE.currency : '₫';
		return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + cur;
	}

	if ($form && form.getAttribute('data-pdp-form') === 'variable') {
		function syncSwatchesFromSelects() {
			root.querySelectorAll('.ty-pdp-vgroup').forEach(function (group) {
				var sel = group.querySelector('select');
				var out = group.querySelector('[data-vgroup-selected]');
				if (!sel) { return; }
				var val = sel.value;
				var enabledVals = Array.prototype.map.call(sel.options, function (o) { return o.disabled ? '' : o.value; });
				var activeLabel = '';
				group.querySelectorAll('.ty-pdp-vopt').forEach(function (btn) {
					var v = btn.getAttribute('data-vopt-val');
					var on = val !== '' && v === val;
					btn.classList.toggle('is-active', on);
					btn.setAttribute('aria-pressed', on ? 'true' : 'false');
					btn.classList.toggle('is-unavailable', enabledVals.indexOf(v) === -1);
					if (on) { activeLabel = btn.getAttribute('data-vopt-label') || v; }
				});
				if (out) { out.textContent = activeLabel || 'Chưa chọn'; }
			});
		}

		root.querySelectorAll('.ty-pdp-vopt').forEach(function (btn) {
			btn.addEventListener('click', function () {
				showAlert('');
				var group = btn.closest('.ty-pdp-vgroup');
				var sel   = group ? group.querySelector('select') : null;
				if (!sel) { return; }
				var v = btn.getAttribute('data-vopt-val');
				sel.value = (sel.value === v) ? '' : v;
				$(sel).trigger('change');
				syncSwatchesFromSelects();
			});
		});

		$form.on('woocommerce_update_variation_values', syncSwatchesFromSelects);

		$form.on('found_variation', function (_e, variation) {
			showAlert('');
			syncSwatchesFromSelects();

			// Cập nhật giá
			if (priceHtmlBox && typeof variation.display_price !== 'undefined') {
				var now = parseFloat(variation.display_price) || 0;
				var reg = parseFloat(variation.display_regular_price) || 0;
				var html = '<span class="ty-pdp-pricebox__now">' + formatVnd(now) + '</span>';
				if (reg > now) {
					var pct = Math.round((1 - now / reg) * 100);
					html += '<del class="ty-pdp-pricebox__was">' + formatVnd(reg) + '</del>';
					if (pct > 0) { html += '<span class="ty-pdp-pricebox__pct">-' + pct + '%</span>'; }
				}
				priceHtmlBox.innerHTML = html;
				if (stickyPriceEl) { stickyPriceEl.textContent = formatVnd(now); }
			}

			// Cập nhật SKU
			if (skuEl) {
				skuEl.textContent = variation.sku || skuEl.getAttribute('data-default-sku') || '';
			}

			// Cập nhật ảnh nếu biến thể có ảnh riêng
			if (variation.image && variation.image.src) {
				setMainImage(variation.image.src, variation.image.full_src || variation.image.src, variation.image.alt || '', -1);
			}

			// Cập nhật tồn kho & khóa nút nếu hết hàng
			var canBuy = variation.is_purchasable && variation.is_in_stock;
			if (qtyInput) {
				if (variation.max_qty && variation.max_qty > 0) {
					qtyInput.max = variation.max_qty;
					clampQty(qtyInput.value);
				} else {
					qtyInput.removeAttribute('max');
				}
			}
			if (stockWrap && stockTextEl) {
				stockWrap.classList.remove('is-in', 'is-low', 'is-out', 'is-backorder');
				if (!variation.is_in_stock) {
					stockWrap.classList.add('is-out');
					stockTextEl.textContent = 'Hết hàng';
				} else if (variation.max_qty && variation.max_qty > 0 && variation.max_qty <= 5) {
					stockWrap.classList.add('is-low');
					stockTextEl.textContent = 'Còn ' + variation.max_qty + ' sản phẩm';
				} else {
					stockWrap.classList.add('is-in');
					stockTextEl.textContent = 'Sẵn hàng';
				}
			}
			if (addBtn) {
				addBtn.disabled = !canBuy;
				addBtn.classList.toggle('is-disabled', !canBuy);
			}
			if (addBtnText) { addBtnText.textContent = canBuy ? 'THÊM VÀO GIỎ HÀNG' : 'HẾT HÀNG'; }
			if (buyNowBtn) { buyNowBtn.disabled = !canBuy; }
		});

		$form.on('reset_data', function () {
			syncSwatchesFromSelects();
			if (priceHtmlBox) { priceHtmlBox.innerHTML = defaultPriceHtml; }
			if (skuEl) { skuEl.textContent = skuEl.getAttribute('data-default-sku') || ''; }
			if (stockTextEl) { stockTextEl.textContent = stockTextEl.getAttribute('data-default-stock') || ''; }
			if (mainImg) { setMainImage(mainImg.getAttribute('data-default-src'), mainImg.getAttribute('data-default-full'), mainImg.alt, 0); }
			if (addBtn) { addBtn.disabled = false; addBtn.classList.remove('is-disabled'); }
			if (addBtnText) { addBtnText.textContent = 'THÊM VÀO GIỎ HÀNG'; }
			if (buyNowBtn) { buyNowBtn.disabled = false; }
		});

		// Khởi chạy đồng bộ lần đầu
		setTimeout(syncSwatchesFromSelects, 50);
	}

	/* ---------- 4. AJAX Add to Cart & Buy Now ---------- */
	function submitSingleCart(isBuyNow) {
		if (!form || !window.TYPEE || !window.TYPEE.singleCartNonce) { return; }
		showAlert('');

		var isVar = form.getAttribute('data-pdp-form') === 'variable';
		var varInput = form.querySelector('input.variation_id');
		if (isVar && (!varInput || !parseInt(varInput.value, 10))) {
			showAlert('Vui lòng chọn đầy đủ phiên bản / thuộc tính sản phẩm trước khi thêm vào giỏ.', 'error');
			var firstV = root.querySelector('.ty-pdp-variants');
			if (firstV) { firstV.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
			return;
		}

		var fd = new FormData(form);
		fd.set('action', 'typee_single_add_to_cart');
		fd.set('nonce', window.TYPEE.singleCartNonce);
		fd.set('product_id', form.getAttribute('data-product_id') || fd.get('product_id'));
		fd.set('quantity', String(qtyInput ? clampQty(qtyInput.value) : 1));
		if (isBuyNow) { fd.set('buy_now', '1'); }

		if (addBtn) { addBtn.classList.add('is-loading'); }
		if (buyNowBtn) { buyNowBtn.disabled = true; }
		var prevTxt = addBtnText ? addBtnText.textContent : '';
		if (addBtnText && !isBuyNow) { addBtnText.textContent = 'ĐANG THÊM…'; }

		fetch(window.TYPEE.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) {
					showAlert((res && res.data && res.data.message) ? res.data.message : 'Không thể thêm vào giỏ hàng.', 'error');
					return;
				}
				// Cập nhật fragments Header Cart & Side Cart
				if (res.data.fragments) {
					Object.keys(res.data.fragments).forEach(function (selector) {
						$(selector).replaceWith(res.data.fragments[selector]);
					});
				}
				if (isBuyNow && res.data.redirectUrl) {
					location.href = res.data.redirectUrl;
					return;
				}
				showAlert(res.data.message || 'Đã thêm sản phẩm vào giỏ hàng!', 'success');
				var cartDrawer = document.getElementById('ty-sidecart');
				if (cartDrawer && window.TYPEE_UI) { window.TYPEE_UI.open(cartDrawer); }
			})
			.catch(function () {
				showAlert('Lỗi kết nối tới máy chủ. Vui lòng thử lại.', 'error');
			})
			.finally(function () {
				if (addBtn) { addBtn.classList.remove('is-loading'); }
				if (buyNowBtn) { buyNowBtn.disabled = false; }
				if (addBtnText && !isBuyNow) { addBtnText.textContent = prevTxt; }
			});
	}

	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			submitSingleCart(false);
		});
	}
	if (buyNowBtn) {
		buyNowBtn.addEventListener('click', function () { submitSingleCart(true); });
	}

	/* ---------- 5. Reviews Anchor & Mobile Sticky Bar ---------- */
	var gotoReviews = root.querySelector('[data-pdp-goto-reviews]');
	if (gotoReviews) {
		gotoReviews.addEventListener('click', function (e) {
			e.preventDefault();
			var revBtn = document.getElementById('tab-btn-reviews');
			if (revBtn) { revBtn.click(); }
			var sec = document.getElementById('pdp-details');
			if (sec) { sec.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
		});
	}

	// Nếu URL có #reviews hoặc #comment-..., tự mở tab Đánh giá
	if (location.hash && (location.hash.indexOf('#comment') === 0 || location.hash === '#reviews' || location.hash === '#tab-reviews')) {
		var revTab = document.getElementById('tab-btn-reviews');
		if (revTab) { revTab.click(); }
	}

	var stickyAdd = root.querySelector('[data-pdp-sticky-add]');
	var stickyBuy = root.querySelector('[data-pdp-sticky-buy]');
	if (stickyAdd) { stickyAdd.addEventListener('click', function () { submitSingleCart(false); }); }
	if (stickyBuy) { stickyBuy.addEventListener('click', function () { submitSingleCart(true); }); }
})(window.jQuery);
