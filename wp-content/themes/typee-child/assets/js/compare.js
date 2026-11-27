/**
 * TYPEE Compare Matrix (Figma 82:2526)
 * - Đọc dữ liệu thật từ Backend REST API `/wp-json/typee/v1/compare` & `/compare/products`
 * - Đồng bộ hai chiều với `localStorage.typee_compare` và URL `?ids=...`
 * - Hỗ trợ lọc "Chỉ hiện điểm khác biệt", thu gọn nhóm EAV, chọn biến thể & AJAX Add-to-Cart
 */
(function ($) {
	'use strict';

	var root = document.querySelector('[data-typee-compare-page]');
	if (!root) {
		return;
	}

	var cfgEl = document.getElementById('typee-compare-config');
	var CFG = cfgEl ? JSON.parse(cfgEl.textContent) : {};
	var MAX_SLOTS = CFG.maxSlots || 4;
	var STORAGE_KEY = 'typee_compare';
	var store = window.TYPEE_STORE || {
		get: function (k) {
			try { return JSON.parse(window.localStorage.getItem(k) || '[]'); } catch (e) { return []; }
		},
		set: function (k, v) {
			try { window.localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* ignore */ }
		}
	};

	/* ---------- Bản đồ Icon SVG nội bộ ---------- */
	var ICONS = {};
	var tpl = document.getElementById('typee-compare-icons');
	if (tpl && tpl.content) {
		tpl.content.querySelectorAll('[data-ic]').forEach(function (el) {
			ICONS[el.getAttribute('data-ic')] = el.innerHTML;
		});
	}
	function icon(name) {
		return ICONS[name] || '';
	}

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	/* ---------- Trạng thái trang ---------- */
	var state = {
		ids: [],
		data: null,
		onlyDiff: false,
		collapsedGroups: {},
		selectedVars: {},
		picker: {
			q: '',
			category: 'all',
			items: [],
			loading: false
		}
	};

	var els = {
		diffToggle: root.querySelector('[data-cmp-diff-toggle]'),
		diffCount: root.querySelector('[data-cmp-diff-count]'),
		clearBtn: root.querySelector('[data-cmp-clear-all]'),
		shareBtn: root.querySelector('[data-cmp-share]'),
		addBtns: root.querySelectorAll('[data-cmp-open-picker]'),
		toast: root.querySelector('[data-cmp-toast]'),
		loading: root.querySelector('[data-cmp-loading]'),
		error: root.querySelector('[data-cmp-error]'),
		errorMsg: root.querySelector('[data-cmp-error-msg]'),
		empty: root.querySelector('[data-cmp-empty]'),
		matrix: root.querySelector('[data-cmp-matrix]'),
		stickyHead: root.querySelector('[data-cmp-sticky-head]'),
		sentinel: root.querySelector('[data-cmp-sentinel]'),
		headGrid: root.querySelector('[data-cmp-head-grid]'),
		groups: root.querySelector('[data-cmp-groups]'),
		pickerModal: document.getElementById('ty-cmp-picker'),
		pickerStatus: document.querySelector('[data-cmp-picker-status]'),
		pickerSearch: document.querySelector('[data-cmp-picker-search]'),
		pickerCats: document.querySelector('[data-cmp-picker-cats]'),
		pickerList: document.querySelector('[data-cmp-picker-list]')
	};

	var toastTimer = null;
	function showToast(msg) {
		if (!els.toast) {
			return;
		}
		els.toast.textContent = msg;
		els.toast.hidden = false;
		if (toastTimer) {
			window.clearTimeout(toastTimer);
		}
		toastTimer = window.setTimeout(function () {
			els.toast.hidden = true;
		}, 3200);
	}

	/* ---------- Đồng bộ ID với localStorage, Header Badge & URL ---------- */
	function sanitizeIds(raw) {
		var out = [];
		(raw || []).forEach(function (v) {
			var n = parseInt(v, 10);
			if (n > 0 && out.indexOf(n) === -1 && out.length < MAX_SLOTS) {
				out.push(n);
			}
		});
		return out;
	}

	function syncStorageAndUrl(ids) {
		state.ids = sanitizeIds(ids);
		var strIds = state.ids.map(String);
		store.set(STORAGE_KEY, strIds);

		// Đồng bộ với main.js & badge trên Header
		$(document).trigger('typee:compare-sync', [strIds]);
		document.querySelectorAll('[data-typee-compare-count]').forEach(function (b) {
			b.textContent = strIds.length;
			b.hidden = strIds.length === 0;
		});

		// Cập nhật URL để F5 hoặc copy link luôn giữ nguyên danh sách đang đối chiếu
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			if (state.ids.length > 0) {
				url.searchParams.set('ids', state.ids.join(','));
			} else {
				url.searchParams.delete('ids');
			}
			window.history.replaceState({}, '', url.toString());
		}

		updateToolbarState();
	}

	function updateToolbarState() {
		var hasItems = state.ids.length > 0;
		var canDiff = state.ids.length >= 2;
		if (els.clearBtn) {
			els.clearBtn.disabled = !hasItems;
		}
		if (els.shareBtn) {
			els.shareBtn.disabled = !hasItems;
		}
		if (els.diffToggle) {
			els.diffToggle.disabled = !canDiff;
			els.diffToggle.setAttribute('aria-checked', state.onlyDiff && canDiff ? 'true' : 'false');
		}
		if (els.pickerStatus) {
			els.pickerStatus.textContent = 'Đã chọn ' + state.ids.length + ' / ' + MAX_SLOTS + ' khe so sánh';
		}
	}

	/* ---------- Gọi Backend REST API lấy dữ liệu đối chiếu ---------- */
	var activeFetchId = 0;

	function loadMatrix(ids, preloadedData) {
		syncStorageAndUrl(ids);

		if (state.ids.length === 0) {
			state.data = null;
			els.loading.hidden = true;
			els.error.hidden = true;
			els.matrix.hidden = true;
			els.empty.hidden = false;
			els.diffCount.textContent = '0 mục lệch';
			return;
		}

		if (preloadedData && Array.isArray(preloadedData.products)) {
			applyMatrixData(preloadedData);
			return;
		}

		var reqId = ++activeFetchId;
		els.empty.hidden = true;
		els.error.hidden = true;
		if (!state.data) {
			els.matrix.hidden = true;
			els.loading.hidden = false;
		}

		var sep = CFG.compareApiUrl.indexOf('?') > -1 ? '&' : '?';
		var endpoint = CFG.compareApiUrl + sep + 'ids=' + encodeURIComponent(state.ids.join(','));

		window.fetch(endpoint, { credentials: 'same-origin' })
			.then(function (res) {
				return res.json().then(function (body) {
					if (!res.ok || !body || !body.success) {
						var msg = (body && body.message) ? body.message : 'Không thể tải dữ liệu đối chiếu (HTTP ' + res.status + ').';
						throw new Error(msg);
					}
					return body.data;
				});
			})
			.then(function (data) {
				if (reqId !== activeFetchId) {
					return;
				}
				applyMatrixData(data);
			})
			.catch(function (err) {
				if (reqId !== activeFetchId) {
					return;
				}
				els.loading.hidden = true;
				els.matrix.hidden = true;
				els.empty.hidden = true;
				els.errorMsg.textContent = err && err.message ? err.message : 'Đã xảy ra lỗi kết nối máy chủ.';
				els.error.hidden = false;
			});
	}

	function applyMatrixData(data) {
		state.data = data;
		els.loading.hidden = true;
		els.error.hidden = true;

		var products = data.products || [];
		if (!products.length) {
			// Các ID cũ trong localStorage không còn tồn tại trong DB
			syncStorageAndUrl([]);
			els.matrix.hidden = true;
			els.empty.hidden = false;
			return;
		}

		// Đồng bộ lại thứ tự ID hợp lệ từ Backend
		var validIds = products.map(function (p) { return p.id; });
		syncStorageAndUrl(validIds);

		els.empty.hidden = true;
		els.matrix.hidden = false;
		renderMatrix();
	}

	/* ---------- Render Sticky Header & 5 Nhóm Thông Số EAV ---------- */
	function slotCountFor(prodCount) {
		return Math.min(MAX_SLOTS, Math.max(2, prodCount + (prodCount < MAX_SLOTS ? 1 : 0)));
	}

	function renderProductCard(p) {
		var hb = p.highlight_badge || { text: '', tone: 'neutral' };
		var tagCls = hb.tone === 'peach' ? 'ty-cmp-card__tag--peach' : 'ty-cmp-card__tag--neutral';
		var cart = p.cart || {};
		var vars = Array.isArray(cart.variations) ? cart.variations : [];
		var chosenVarId = state.selectedVars[p.id] || cart.product_id;

		var activePrice = p.price_formatted;
		var activeStock = p.in_stock;
		if (cart.is_variable && vars.length > 0) {
			for (var i = 0; i < vars.length; i++) {
				if (vars[i].id === +chosenVarId) {
					if (state.selectedVars[p.id]) {
						activePrice = vars[i].price_formatted || activePrice;
					}
					activeStock = vars[i].in_stock;
					break;
				}
			}
		}

		var varHtml = '';
		if (cart.is_variable && vars.length > 1) {
			varHtml = '<div class="ty-cmp-card__var">' +
				'<label class="ty-sr" for="cmp-var-' + p.id + '">Chọn phiên bản ' + esc(p.name) + '</label>' +
				'<select id="cmp-var-' + p.id + '" class="ty-select ty-cmp-card__var-select" data-cmp-var="' + p.id + '">' +
				vars.map(function (v) {
					return '<option value="' + v.id + '"' + (+chosenVarId === v.id ? ' selected' : '') + (v.in_stock ? '' : ' disabled') + '>' +
						esc(v.label) + ' – ' + esc(v.price_formatted) + (v.in_stock ? '' : ' (Hết)') +
						'</option>';
				}).join('') +
				'</select></div>';
		}

		var secondaryPriceHtml = '';
		if (p.price_regular_formatted) {
			secondaryPriceHtml = '<span class="ty-cmp-card__was">' + esc(p.price_regular_formatted) + '</span>';
		} else if (p.price_note) {
			secondaryPriceHtml = '<span class="ty-cmp-card__note">' + esc(p.price_note) + '</span>';
		}

		var btnDisabled = cart.disabled || !activeStock;
		var btnText = btnDisabled ? 'Hết hàng' : (cart.button_text || 'Thêm vào giỏ hàng');

		return '<article class="ty-cmp-card" data-product-id="' + p.id + '">' +
			'<button type="button" class="ty-cmp-card__remove" data-cmp-remove="' + p.id + '" aria-label="Bỏ ' + esc(p.name) + ' khỏi bảng so sánh">' +
				icon('close') +
			'</button>' +
			'<div class="ty-cmp-card__top">' +
				'<div class="ty-cmp-card__badges">' +
					(hb.text ? '<span class="ty-cmp-card__tag ' + tagCls + '">' + esc(hb.text) + '</span>' : '') +
					(p.category_badge ? '<span class="ty-cmp-card__cat">' + esc(p.category_badge) + '</span>' : '') +
				'</div>' +
				'<a class="ty-cmp-card__media" href="' + esc(p.url) + '" tabindex="-1" aria-hidden="true">' +
					'<img class="ty-cmp-card__img" src="' + esc(p.image) + '" alt="" loading="lazy" decoding="async" />' +
				'</a>' +
				'<h2 class="ty-cmp-card__name"><a href="' + esc(p.url) + '">' + esc(p.name) + '</a></h2>' +
				'<div class="ty-cmp-card__price-row">' +
					'<span class="ty-cmp-card__price">' + esc(activePrice) + '</span>' +
					secondaryPriceHtml +
				'</div>' +
				varHtml +
			'</div>' +
			'<div class="ty-cmp-card__bottom">' +
				'<button type="button" class="ty-cmp-card__cart" data-cmp-add-cart="' + p.id + '"' + (btnDisabled ? ' disabled' : '') + '>' +
					icon('cart') +
					'<span>' + esc(btnText) + '</span>' +
				'</button>' +
				'<a class="ty-cmp-card__detail" href="' + esc(p.url) + '">Xem chi tiết sản phẩm →</a>' +
			'</div>' +
		'</article>';
	}

	function renderEmptySlotCard(slotIndex) {
		return '<div class="ty-cmp-slot">' +
			'<div class="ty-cmp-slot__body">' +
				'<span class="ty-cmp-slot__ic">' + icon('add') + '</span>' +
				'<span class="ty-cmp-slot__kicker">CỘT SO SÁNH TRỐNG</span>' +
				'<h3 class="ty-cmp-slot__title">Thêm sản phẩm thứ ' + slotIndex + '</h3>' +
				'<p class="ty-cmp-slot__desc">Chọn từ kho Bàn phím, Kit, Switch hoặc Keycap để đối chiếu trực tiếp.</p>' +
			'</div>' +
			'<button type="button" class="ty-cmp-slot__btn" data-cmp-open-picker>' +
				'<span>+ Chọn nhanh sản phẩm</span>' +
			'</button>' +
		'</div>';
	}

	function renderMatrix() {
		if (!state.data) {
			return;
		}
		var products = state.data.products || [];
		var groups = state.data.groups || [];
		var diffCount = state.data.diff_count || 0;
		var slots = slotCountFor(products.length);
		var emptySlots = Math.max(0, slots - products.length);
		var filterDiff = state.onlyDiff && products.length >= 2;

		els.diffCount.textContent = diffCount + ' mục lệch';
		updateToolbarState();

		// 1. Render Sticky Header Grid
		els.headGrid.style.setProperty('--cmp-slots', String(slots));
		var introHtml = '<div class="ty-cmp__col-intro">' +
			'<span class="ty-cmp__intro-kicker">BẢNG ĐỐI CHIẾU THÔNG SỐ EAV</span>' +
			'<h2 class="ty-cmp__intro-title">Linh kiện đang chọn</h2>' +
			'<p class="ty-cmp__intro-meta">Đang hiển thị ' + products.length + ' / ' + MAX_SLOTS + ' khe so sánh khả dụng.</p>' +
			'<div class="ty-cmp__legend"><span class="ty-cmp__legend-swatch"></span><span>Ô màu cam nhạt: Thông số có sự khác biệt</span></div>' +
		'</div>';

		var cardsHtml = products.map(renderProductCard).join('');
		var slotsHtml = '';
		for (var s = 0; s < emptySlots; s++) {
			slotsHtml += renderEmptySlotCard(products.length + s + 1);
		}
		els.headGrid.innerHTML = introHtml + cardsHtml + slotsHtml;

		// 2. Render 5 Nhóm Thông Số EAV
		var visibleGroupHtml = [];
		groups.forEach(function (g) {
			var rows = (g.rows || []).filter(function (r) {
				return !filterDiff || r.is_diff;
			});
			if (!rows.length) {
				return;
			}
			var isCollapsed = !!state.collapsedGroups[g.id];
			var countLabel = filterDiff ? (rows.length + ' / ' + g.criteria_count + ' tiêu chí') : (rows.length + ' tiêu chí');

			var rowsHtml = rows.map(function (r) {
				var cellsHtml = (r.values || []).map(function (v) {
					var cellCls = 'ty-cmp-cell' + (v.accent ? ' is-accent' : '');
					var icHtml = '';
					if (v.icon === 'verified') {
						icHtml = '<span class="ty-cmp-cell__ic--verified">' + icon('verified') + '</span>';
					} else if (v.icon === 'check') {
						icHtml = '<span class="ty-cmp-cell__ic--check">' + icon('check') + '</span>';
					} else if (v.icon === 'none') {
						icHtml = '<span class="ty-cmp-cell__ic--none">' + icon('none') + '</span>';
					}
					return '<div class="' + cellCls + '">' +
						'<span class="ty-cmp-cell__main">' + icHtml + '<span>' + esc(v.main) + '</span></span>' +
						(v.sub ? '<span class="ty-cmp-cell__sub">' + esc(v.sub) + '</span>' : '') +
					'</div>';
				}).join('');

				var placeholderCells = '';
				for (var e = 0; e < emptySlots; e++) {
					placeholderCells += '<div class="ty-cmp-cell ty-cmp-cell--slot" aria-hidden="true">—</div>';
				}

				return '<div class="ty-cmp__grid ty-cmp-row' + (r.is_diff ? ' is-diff' : '') + '" style="--cmp-slots:' + slots + '">' +
					'<div class="ty-cmp-row__label">' +
						'<span class="ty-cmp-row__name">' + esc(r.label) + '</span>' +
						'<span class="ty-cmp-row__sub">' + esc(r.sublabel) + '</span>' +
					'</div>' +
					cellsHtml +
					placeholderCells +
				'</div>';
			}).join('');

			visibleGroupHtml.push(
				'<section class="ty-cmp-group' + (isCollapsed ? ' is-collapsed' : '') + '" data-group-id="' + esc(g.id) + '">' +
					'<button type="button" class="ty-cmp-group__head" data-cmp-group-toggle="' + esc(g.id) + '" aria-expanded="' + (!isCollapsed) + '">' +
						'<span class="ty-cmp-group__head-left">' +
							'<span class="ty-cmp-group__ic">' + icon(g.icon || 'layers') + '</span>' +
							'<span class="ty-cmp-group__title">' + esc(g.title) + '</span>' +
						'</span>' +
						'<span class="ty-cmp-group__head-right">' +
							'<span>' + esc(countLabel) + '</span>' +
							'<span class="ty-cmp-group__chev">' + icon('chevron') + '</span>' +
						'</span>' +
					'</button>' +
					'<div class="ty-cmp-group__body">' + rowsHtml + '</div>' +
				'</section>'
			);
		});

		if (!visibleGroupHtml.length && filterDiff) {
			els.groups.innerHTML = '<div class="ty-panel ty-cmp__no-diff">' +
				'<h3>Các sản phẩm đang chọn có thông số tương đồng nhau</h3>' +
				'<p class="ty-help">Tắt bộ lọc điểm khác biệt để xem đầy đủ toàn bộ bảng thông số kỹ thuật.</p>' +
				'<div class="ty-empty__actions"><button type="button" class="ty-btn ty-btn--soft" data-cmp-reset-diff>Hiện tất cả tiêu chí</button></div>' +
			'</div>';
		} else {
			els.groups.innerHTML = visibleGroupHtml.join('');
		}
	}

	/* ---------- Modal Chọn Nhanh Sản Phẩm (Live Search từ Backend) ---------- */
	var searchTimer = null;

	function fetchPickerProducts() {
		if (!els.pickerList) {
			return;
		}
		state.picker.loading = true;
		els.pickerList.innerHTML = '<p class="ty-help">Đang tải danh sách sản phẩm từ kho…</p>';

		var sep = CFG.searchApiUrl.indexOf('?') > -1 ? '&' : '?';
		var url = CFG.searchApiUrl + sep +
			'q=' + encodeURIComponent(state.picker.q) +
			'&category=' + encodeURIComponent(state.picker.category) +
			'&limit=16';

		window.fetch(url, { credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (body) {
				state.picker.loading = false;
				if (!body || !body.success || !body.data) {
					els.pickerList.innerHTML = '<p class="ty-error">Không thể tải danh sách sản phẩm.</p>';
					return;
				}
				state.picker.items = body.data.items || [];
				renderPickerList();
			})
			.catch(function () {
				state.picker.loading = false;
				els.pickerList.innerHTML = '<p class="ty-error">Lỗi kết nối khi tìm kiếm sản phẩm.</p>';
			});
	}

	function renderPickerList() {
		if (!els.pickerList) {
			return;
		}
		var items = state.picker.items;
		if (!items.length) {
			els.pickerList.innerHTML = '<div class="ty-empty" style="grid-column:1/-1;padding:24px">' +
				'<strong>Không tìm thấy linh kiện phù hợp.</strong>' +
				'<p class="ty-help">Thử từ khóa khác hoặc chuyển danh mục về "Tất cả".</p>' +
			'</div>';
			return;
		}

		var isFull = state.ids.length >= MAX_SLOTS;
		els.pickerList.innerHTML = items.map(function (it) {
			var picked = state.ids.indexOf(it.id) > -1;
			var specs = (it.specs_summary || []).join(' · ');
			var btnHtml = picked
				? '<button type="button" class="ty-btn ty-btn--soft ty-cmp-pick__btn" data-cmp-pick-toggle="' + it.id + '">✓ Đang chọn</button>'
				: '<button type="button" class="ty-btn ty-btn--primary ty-cmp-pick__btn" data-cmp-pick-toggle="' + it.id + '"' + (isFull ? ' disabled title="Đã đủ 4 sản phẩm"' : '') + '>' + (isFull ? 'Đủ 4/4' : '+ So sánh') + '</button>';

			return '<article class="ty-cmp-pick' + (picked ? ' is-picked' : '') + '">' +
				'<img class="ty-cmp-pick__img" src="' + esc(it.image) + '" alt="" width="56" height="56" loading="lazy" />' +
				'<div class="ty-cmp-pick__info">' +
					'<div class="ty-cmp-pick__meta">' +
						'<span class="ty-badge ty-badge--soft">' + esc(it.category_badge) + '</span>' +
						(it.sku ? '<span class="ty-mono">' + esc(it.sku) + '</span>' : '') +
					'</div>' +
					'<div class="ty-cmp-pick__name" title="' + esc(it.name) + '">' + esc(it.name) + '</div>' +
					'<div class="ty-cmp-pick__price">' + esc(it.price_formatted) + '</div>' +
					(specs ? '<div class="ty-cmp-pick__specs">' + esc(specs) + '</div>' : '') +
				'</div>' +
				btnHtml +
			'</article>';
		}).join('');
	}

	function openPicker(triggerEl) {
		if (!els.pickerModal) {
			return;
		}
		updateToolbarState();
		if (window.TYPEE_UI && window.TYPEE_UI.open) {
			window.TYPEE_UI.open(els.pickerModal, triggerEl);
		} else {
			els.pickerModal.classList.add('is-open');
			els.pickerModal.setAttribute('aria-hidden', 'false');
		}
		if (!state.picker.items.length && !state.picker.loading) {
			fetchPickerProducts();
		} else {
			renderPickerList();
		}
	}

	/* ---------- AJAX Add-to-Cart chuẩn WooCommerce ---------- */
	function handleAddToCart(btn, parentId) {
		if (!state.data || !state.data.products) {
			return;
		}
		var prod = null;
		for (var i = 0; i < state.data.products.length; i++) {
			if (state.data.products[i].id === +parentId) {
				prod = state.data.products[i];
				break;
			}
		}
		if (!prod || !prod.cart) {
			return;
		}

		var targetId = state.selectedVars[prod.id] || prod.cart.product_id;
		if (!prod.cart.can_ajax || !targetId) {
			window.location.href = prod.cart.fallback_url || prod.url;
			return;
		}

		var originalHtml = btn.innerHTML;
		btn.disabled = true;
		btn.querySelector('span').textContent = 'Đang thêm…';

		var formData = new window.FormData();
		formData.append('product_id', String(targetId));
		formData.append('quantity', '1');

		window.fetch(CFG.addToCartUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.error && res.product_url) {
					window.location.href = res.product_url;
					return;
				}
				// Cập nhật giỏ hàng trên Header qua WooCommerce fragments
				if (res && res.fragments) {
					Object.keys(res.fragments).forEach(function (selector) {
						var target = document.querySelector(selector);
						if (target) {
							target.outerHTML = res.fragments[selector];
						}
					});
				}
				$(document.body).trigger('added_to_cart', [res ? res.fragments : {}, res ? res.cart_hash : '', $(btn)]);
				btn.querySelector('span').textContent = '✓ Đã thêm vào giỏ';
				showToast('Đã thêm "' + prod.name + '" vào giỏ hàng!');
				window.setTimeout(function () {
					btn.disabled = false;
					btn.innerHTML = originalHtml;
				}, 1400);
			})
			.catch(function () {
				btn.disabled = false;
				btn.innerHTML = originalHtml;
				showToast('Không thể thêm vào giỏ hàng. Đang chuyển tới trang chi tiết…');
				window.setTimeout(function () {
					window.location.href = prod.url;
				}, 900);
			});
	}

	/* ---------- Gắn sự kiện tương tác ---------- */
	// 1. Bật/tắt "Chỉ hiện điểm khác biệt"
	if (els.diffToggle) {
		els.diffToggle.addEventListener('click', function () {
			if (state.ids.length < 2) {
				return;
			}
			state.onlyDiff = !state.onlyDiff;
			renderMatrix();
		});
	}

	// 2. Xóa tất cả
	if (els.clearBtn) {
		els.clearBtn.addEventListener('click', function () {
			if (!state.ids.length) {
				return;
			}
			state.onlyDiff = false;
			state.selectedVars = {};
			loadMatrix([]);
			renderPickerList();
			showToast('Đã xóa toàn bộ danh sách so sánh.');
		});
	}

	// 3. Chia sẻ liên kết đối chiếu
	if (els.shareBtn) {
		els.shareBtn.addEventListener('click', function () {
			if (!state.ids.length) {
				return;
			}
			var shareUrl = CFG.comparePageUrl + (CFG.comparePageUrl.indexOf('?') > -1 ? '&' : '?') + 'ids=' + state.ids.join(',');
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(shareUrl).then(function () {
					showToast('Đã sao chép liên kết so sánh vào bộ nhớ tạm!');
				});
			} else {
				var ta = document.createElement('textarea');
				ta.value = shareUrl;
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				document.body.removeChild(ta);
				showToast('Đã sao chép liên kết so sánh!');
			}
		});
	}

	// 4. Sự kiện ủy quyền trong trang Compare
	$(root).on('click', '[data-cmp-open-picker]', function (e) {
		e.preventDefault();
		openPicker(this);
	});

	$(root).on('click', '[data-cmp-remove]', function (e) {
		e.preventDefault();
		var rmId = parseInt(this.getAttribute('data-cmp-remove'), 10);
		var next = state.ids.filter(function (id) { return id !== rmId; });
		loadMatrix(next);
		renderPickerList();
	});

	$(root).on('change', '[data-cmp-var]', function () {
		var pid = parseInt(this.getAttribute('data-cmp-var'), 10);
		state.selectedVars[pid] = parseInt(this.value, 10);
		renderMatrix();
	});

	$(root).on('click', '[data-cmp-add-cart]', function (e) {
		e.preventDefault();
		handleAddToCart(this, parseInt(this.getAttribute('data-cmp-add-cart'), 10));
	});

	$(root).on('click', '[data-cmp-group-toggle]', function () {
		var gid = this.getAttribute('data-cmp-group-toggle');
		state.collapsedGroups[gid] = !state.collapsedGroups[gid];
		renderMatrix();
	});

	$(root).on('click', '[data-cmp-reset-diff]', function () {
		state.onlyDiff = false;
		renderMatrix();
	});

	$(root).on('click', '[data-cmp-retry]', function () {
		state.data = null;
		loadMatrix(state.ids);
	});

	$(root).on('click', '[data-cmp-preset]', function () {
		var raw = (this.getAttribute('data-cmp-preset') || '').split(',');
		loadMatrix(raw);
	});

	// 5. Sự kiện trong Modal Chọn nhanh
	if (els.pickerSearch) {
		els.pickerSearch.addEventListener('input', function () {
			var val = this.value.trim();
			if (searchTimer) {
				window.clearTimeout(searchTimer);
			}
			searchTimer = window.setTimeout(function () {
				state.picker.q = val;
				fetchPickerProducts();
			}, 260);
		});
	}

	if (els.pickerCats) {
		$(els.pickerCats).on('click', '[data-cat]', function () {
			var cat = this.getAttribute('data-cat');
			state.picker.category = cat;
			els.pickerCats.querySelectorAll('[data-cat]').forEach(function (btn) {
				btn.setAttribute('aria-pressed', btn.getAttribute('data-cat') === cat ? 'true' : 'false');
			});
			fetchPickerProducts();
		});
	}

	if (els.pickerList) {
		$(els.pickerList).on('click', '[data-cmp-pick-toggle]', function () {
			var pid = parseInt(this.getAttribute('data-cmp-pick-toggle'), 10);
			var idx = state.ids.indexOf(pid);
			var next = state.ids.slice();
			if (idx > -1) {
				next.splice(idx, 1);
			} else {
				if (next.length >= MAX_SLOTS) {
					showToast('Chỉ được chọn tối đa ' + MAX_SLOTS + ' sản phẩm cùng lúc.');
					return;
				}
				next.push(pid);
			}
			state.data = null;
			loadMatrix(next);
			renderPickerList();
		});
	}

	// 6. Sticky Sentinel thu gọn ảnh thẻ sản phẩm khi cuộn sâu
	if ('IntersectionObserver' in window && els.sentinel && els.stickyHead) {
		var io = new window.IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				els.stickyHead.classList.toggle('is-stuck', !entry.isIntersecting && entry.boundingClientRect.top < 0);
			});
		}, { threshold: 0 });
		io.observe(els.sentinel);
	}

	/* ---------- Khởi chạy ban đầu ---------- */
	var urlIds = Array.isArray(CFG.urlIds) && CFG.urlIds.length ? CFG.urlIds : null;
	var storedIds = sanitizeIds(store.get(STORAGE_KEY));
	var bootIds = urlIds || storedIds;

	loadMatrix(bootIds, urlIds && CFG.initialData ? CFG.initialData : null);
})(jQuery);
