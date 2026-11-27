/**
 * Trang Danh Mục Sản Phẩm (Figma 46:1482):
 * - Dual-thumb price range slider đồng bộ ô nhập tối thiểu/tối đa
 * - Đếm nhanh số sản phẩm ngay trong Off-Canvas Filter Drawer
 * - Lọc, sắp xếp & phân trang AJAX không reload trang + history.pushState()
 * - Chuyển chế độ xem Grid / List lưu localStorage
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-catalog-root]');
	if (!root) { return; }

	var tt         = root.getAttribute('data-tt') || '0';
	var baseUrl    = root.getAttribute('data-base-url') || location.pathname;
	var form       = root.querySelector('[data-typee-filter]');
	var chipsBox   = root.querySelector('[data-catalog-chips]');
	var countBox   = root.querySelector('[data-catalog-count]');
	var heroCount  = root.querySelector('[data-catalog-hero-count]');
	var activeBadge = root.querySelector('[data-catalog-active-count]');
	var resultsBox = root.querySelector('[data-catalog-results]');
	var paginBox   = root.querySelector('[data-catalog-pagination]');
	var skelBox    = root.querySelector('[data-catalog-skeleton]');
	var sortSelect = root.querySelector('[data-catalog-sort]');
	var labelEl    = form ? form.querySelector('[data-filter-label]') : null;
	var orderInput = form ? form.querySelector('[data-filter-orderby]') : null;

	var currentView = 'grid';
	try { currentView = window.localStorage.getItem('typee_catalog_view') || 'grid'; } catch (e) {}

	/* ---------- Grid / List Switcher ---------- */
	function applyViewMode(mode) {
		currentView = mode === 'list' ? 'list' : 'grid';
		try { window.localStorage.setItem('typee_catalog_view', currentView); } catch (e) {}
		var grid = root.querySelector('[data-catalog-grid]');
		if (grid) { grid.classList.toggle('is-list', currentView === 'list'); }
		root.querySelectorAll('[data-view-mode]').forEach(function (btn) {
			var on = btn.getAttribute('data-view-mode') === currentView;
			btn.classList.toggle('is-active', on);
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
	}
	root.querySelectorAll('[data-view-mode]').forEach(function (btn) {
		btn.addEventListener('click', function () { applyViewMode(btn.getAttribute('data-view-mode')); });
	});
	applyViewMode(currentView);

	/* ---------- Dual Price Range Slider ---------- */
	var priceGroup = form ? form.querySelector('[data-price-bounds]') : null;
	var minInput   = priceGroup ? priceGroup.querySelector('[data-price-input="min"]') : null;
	var maxInput   = priceGroup ? priceGroup.querySelector('[data-price-input="max"]') : null;
	var minRange   = priceGroup ? priceGroup.querySelector('[data-price-range="min"]') : null;
	var maxRange   = priceGroup ? priceGroup.querySelector('[data-price-range="max"]') : null;
	var fillBar    = priceGroup ? priceGroup.querySelector('[data-price-fill]') : null;

	function paintSlider() {
		if (!priceGroup || !minRange || !maxRange || !fillBar) { return; }
		var bMin = parseFloat(priceGroup.getAttribute('data-min')) || 0;
		var bMax = parseFloat(priceGroup.getAttribute('data-max')) || 1;
		var span = Math.max(1, bMax - bMin);
		var vMin = parseFloat(minRange.value) || bMin;
		var vMax = parseFloat(maxRange.value) || bMax;
		if (vMin > vMax) { var t = vMin; vMin = vMax; vMax = t; }
		var pLeft  = Math.max(0, Math.min(100, ((vMin - bMin) / span) * 100));
		var pRight = Math.max(0, Math.min(100, 100 - ((vMax - bMin) / span) * 100));
		fillBar.style.left  = pLeft + '%';
		fillBar.style.right = pRight + '%';
	}
	if (minRange && maxRange && minInput && maxInput) {
		minRange.addEventListener('input', function () {
			if (parseFloat(minRange.value) > parseFloat(maxRange.value)) { minRange.value = maxRange.value; }
			minInput.value = minRange.value;
			paintSlider();
			scheduleCount();
		});
		maxRange.addEventListener('input', function () {
			if (parseFloat(maxRange.value) < parseFloat(minRange.value)) { maxRange.value = minRange.value; }
			maxInput.value = maxRange.value;
			paintSlider();
			scheduleCount();
		});
		minInput.addEventListener('input', function () {
			var v = parseFloat(minInput.value);
			if (!isNaN(v)) { minRange.value = v; paintSlider(); }
			scheduleCount();
		});
		maxInput.addEventListener('input', function () {
			var v = parseFloat(maxInput.value);
			if (!isNaN(v)) { maxRange.value = v; paintSlider(); }
			scheduleCount();
		});
		paintSlider();
	}

	/* ---------- Drawer Live Count ---------- */
	var countTimer = null, countSeq = 0, countCtrl = null;

	function syncCheckboxHighlights() {
		if (!form) { return; }
		form.querySelectorAll('.ty-fopt').forEach(function (lbl) {
			var cb = lbl.querySelector('input[type="checkbox"]');
			if (cb) { lbl.classList.toggle('is-checked', cb.checked); }
		});
	}

	function activeCountFromForm() {
		if (!form) { return 0; }
		var n = form.querySelectorAll('input[type="checkbox"]:checked').length;
		if ((minInput && minInput.value) || (maxInput && maxInput.value)) { n++; }
		return n;
	}

	function formParams() {
		var p = new URLSearchParams();
		if (!form) { return p; }
		new FormData(form).forEach(function (v, k) {
			if (v !== '' && v != null) { p.append(k, v); }
		});
		return p;
	}

	function scheduleCount() {
		if (!form || !labelEl || !window.TYPEE) { return; }
		syncCheckboxHighlights();
		clearTimeout(countTimer);
		countTimer = setTimeout(function () {
			var p = formParams();
			var s = ++countSeq;
			var n = activeCountFromForm();
			var baseTxt = n ? ('Áp dụng ' + n + ' bộ lọc') : 'Xem tất cả';
			p.append('action', 'typee_filter_count');
			p.append('tt', tt);
			labelEl.textContent = baseTxt + ' (đang đếm…)';
			if (countCtrl) { countCtrl.abort(); }
			countCtrl = window.AbortController ? new AbortController() : null;
			fetch(window.TYPEE.ajaxUrl + '?' + p.toString(), { credentials: 'same-origin', signal: countCtrl ? countCtrl.signal : undefined })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (s !== countSeq || !res || !res.success) { return; }
					labelEl.textContent = baseTxt + ' (' + res.data.count + ' kết quả)';
				})
				.catch(function (err) {
					if (s === countSeq && (!err || err.name !== 'AbortError')) { labelEl.textContent = baseTxt; }
				});
		}, 200);
	}

	/* ---------- Sync Drawer UI từ URL ---------- */
	function syncFormFromUrl(urlStr) {
		if (!form) { return; }
		var u = new URL(urlStr, location.origin);
		var sp = u.searchParams;
		form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
			var name = cb.name;
			var vals = sp.getAll(name).concat(sp.getAll(name.replace(/\[\]$/, '')));
			cb.checked = vals.indexOf(cb.value) > -1;
		});
		if (minInput) { minInput.value = sp.get('min_price') || ''; }
		if (maxInput) { maxInput.value = sp.get('max_price') || ''; }
		if (priceGroup && minRange && maxRange) {
			minRange.value = minInput && minInput.value ? minInput.value : priceGroup.getAttribute('data-min');
			maxRange.value = maxInput && maxInput.value ? maxInput.value : priceGroup.getAttribute('data-max');
			paintSlider();
		}
		var ob = sp.get('orderby') || 'date';
		if (sortSelect) { sortSelect.value = ob; }
		if (orderInput) { orderInput.value = ob; }
		syncCheckboxHighlights();
	}

	/* ---------- AJAX Load Catalog ---------- */
	var loadCtrl = null;

	function parsePagedFromUrl(u) {
		var m = u.pathname.match(/\/page\/(\d+)\/?/);
		if (m) { return parseInt(m[1], 10) || 1; }
		return parseInt(u.searchParams.get('paged') || '1', 10) || 1;
	}

	function loadCatalogUrl(targetUrl, pushState) {
		if (!window.TYPEE || !window.TYPEE.catalogNonce) {
			location.href = targetUrl;
			return;
		}
		var u = new URL(targetUrl, location.origin);
		var p = new URLSearchParams(u.search);
		p.set('action', 'typee_filter_catalog');
		p.set('nonce', window.TYPEE.catalogNonce);
		p.set('tt', tt);
		p.set('base_url', baseUrl);
		p.set('paged', String(parsePagedFromUrl(u)));

		document.body.classList.add('ty-is-loading');
		if (skelBox && resultsBox) { skelBox.hidden = false; resultsBox.hidden = true; }

		if (loadCtrl) { loadCtrl.abort(); }
		loadCtrl = window.AbortController ? new AbortController() : null;

		fetch(window.TYPEE.ajaxUrl + '?' + p.toString(), { credentials: 'same-origin', signal: loadCtrl ? loadCtrl.signal : undefined })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) { location.href = targetUrl; return; }
				var d = res.data;
				if (resultsBox) { resultsBox.innerHTML = d.gridHtml; resultsBox.hidden = false; }
				if (skelBox) { skelBox.hidden = true; }
				if (paginBox) { paginBox.innerHTML = d.paginationHtml || ''; }
				if (chipsBox) { chipsBox.innerHTML = d.chipsHtml || ''; }
				if (countBox) { countBox.innerHTML = d.countHtml || ''; }
				if (heroCount) { heroCount.textContent = d.total + ' sản phẩm'; }
				if (activeBadge) {
					activeBadge.textContent = d.activeCount;
					activeBadge.hidden = !d.activeCount;
				}
				if (labelEl) {
					labelEl.textContent = d.activeCount
						? ('Áp dụng ' + d.activeCount + ' bộ lọc (' + d.total + ' kết quả)')
						: ('Xem tất cả (' + d.total + ' kết quả)');
				}
				applyViewMode(currentView);
				syncFormFromUrl(u.href);
				if (pushState && window.history && window.history.pushState) {
					window.history.pushState({ catalogUrl: u.href }, '', u.href);
				}
				if (window.jQuery) { window.jQuery(document).trigger('typee:cards-updated'); }
				var tools = root.querySelector('.ty-cattools');
				if (tools && tools.getBoundingClientRect().top < 0) {
					tools.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			})
			.catch(function (err) {
				if (err && err.name === 'AbortError') { return; }
				location.href = targetUrl;
			})
			.finally(function () {
				document.body.classList.remove('ty-is-loading');
				if (skelBox) { skelBox.hidden = true; }
				if (resultsBox) { resultsBox.hidden = false; }
			});
	}

	/* ---------- Event Bindings ---------- */
	if (form) {
		form.addEventListener('change', scheduleCount);

		var resetBtn = form.querySelector('[data-filter-reset]');
		if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				form.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = false; });
				if (minInput) { minInput.value = ''; }
				if (maxInput) { maxInput.value = ''; }
				if (priceGroup && minRange && maxRange) {
					minRange.value = priceGroup.getAttribute('data-min');
					maxRange.value = priceGroup.getAttribute('data-max');
					paintSlider();
				}
				scheduleCount();
			});
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var p = formParams();
			var qs = p.toString();
			var target = qs ? (baseUrl + '?' + qs) : baseUrl;
			if (window.TYPEE_UI) { window.TYPEE_UI.close(); }
			loadCatalogUrl(target, true);
		});
	}

	if (sortSelect) {
		sortSelect.addEventListener('change', function () {
			if (orderInput) { orderInput.value = sortSelect.value; }
			var u = new URL(location.href);
			u.pathname = new URL(baseUrl, location.origin).pathname;
			u.searchParams.delete('paged');
			u.searchParams.set('orderby', sortSelect.value);
			loadCatalogUrl(u.href, true);
		});
	}

	root.addEventListener('click', function (e) {
		var a = e.target.closest('a[data-catalog-link], .woocommerce-pagination a.page-numbers');
		if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') { return; }
		e.preventDefault();
		loadCatalogUrl(a.href, true);
	});

	window.addEventListener('popstate', function () {
		loadCatalogUrl(location.href, false);
	});
})();
