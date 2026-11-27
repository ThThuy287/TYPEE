/**
 * TYPEE Live Search Dropdown & Search Results Page Interactions (Figma 82:1823).
 * Gắn động dropdown vào `.ty-search__form` hiện có (không sửa DOM của Header).
 */
(function () {
	'use strict';

	/* ---------- 1. LIVE SEARCH DROPDOWN (HEADER INPUT) ---------- */
	var input = document.getElementById('ty-search-input');
	var form  = input ? input.closest('.ty-search__form') : null;

	if (input && form && window.TYPEE && window.TYPEE.searchNonce) {
		var dropdown = document.createElement('div');
		dropdown.className = 'typee-search-dropdown';
		dropdown.setAttribute('role', 'region');
		dropdown.setAttribute('aria-label', 'Gợi ý tìm kiếm sản phẩm');
		dropdown.hidden = true;
		form.appendChild(dropdown);

		var timer = null;
		var ctrl  = null;
		var seq   = 0;
		var lastQ = null;

		function escHtml(s) {
			return String(s || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		function highlight(text, kw) {
			var safe = escHtml(text);
			if (!kw) { return safe; }
			var escapedKw = kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			return safe.replace(new RegExp('(' + escapedKw + ')', 'ig'), '<mark>$1</mark>');
		}

		function openDd() { dropdown.hidden = false; }
		function closeDd() { dropdown.hidden = true; }

		function renderLoading() {
			dropdown.innerHTML =
				'<div class="typee-search-dd__head">' +
					'<span class="typee-search-dd__title">Đang tìm kiếm…</span>' +
					'<span class="typee-search-dd__hint">SKU Indexing Instant</span>' +
				'</div>' +
				'<div class="typee-search-dd__state">Đang tra cứu sản phẩm trong kho TYPEE…</div>';
			openDd();
		}

		function renderResponse(data) {
			var kw = data.keyword || '';
			var items = Array.isArray(data.items) ? data.items : [];
			var total = parseInt(data.total, 10) || 0;
			var popular = Array.isArray(data.popular) ? data.popular : [];
			var searchBase = (window.TYPEE && window.TYPEE.searchUrl) ? window.TYPEE.searchUrl : '/search/';

			if (!kw) {
				var tags = popular.map(function (t) {
					return '<a class="typee-search-dd__poptag" href="' + escHtml(searchBase + '?q=' + encodeURIComponent(t)) + '">' + escHtml(t) + '</a>';
				}).join('');
				dropdown.innerHTML =
					'<div class="typee-search-dd__head">' +
						'<span class="typee-search-dd__title">Từ khóa tìm kiếm phổ biến</span>' +
						'<span class="typee-search-dd__hint">Chọn nhanh</span>' +
					'</div>' +
					'<div class="typee-search-dd__popular">' + tags + '</div>';
				openDd();
				return;
			}

			if (!items.length) {
				dropdown.innerHTML =
					'<div class="typee-search-dd__head">' +
						'<span class="typee-search-dd__title">Gợi ý tìm kiếm</span>' +
						'<span class="typee-search-dd__hint">0 kết quả</span>' +
					'</div>' +
					'<div class="typee-search-dd__state">Không tìm thấy sản phẩm khớp với <strong>&ldquo;' + escHtml(kw) + '&rdquo;</strong>.</div>' +
					'<a class="typee-search-dd__foot" href="' + escHtml(data.allUrl || (searchBase + '?q=' + encodeURIComponent(kw))) + '">Mở trang tìm kiếm đầy đủ →</a>';
				openDd();
				return;
			}

			var rowsHtml = items.map(function (it) {
				var skuHtml = it.sku ? '<span class="typee-search-dd__sku">SKU: ' + escHtml(it.sku) + '</span>' : '';
				var regHtml = it.regularText ? '<del>' + escHtml(it.regularText) + '</del>' : '';
				return (
					'<li class="typee-search-dd__item">' +
						'<a class="typee-search-dd__link" href="' + escHtml(it.url) + '">' +
							'<span class="typee-search-dd__thumb"><img src="' + escHtml(it.thumb) + '" alt="" loading="lazy" /></span>' +
							'<span class="typee-search-dd__info">' +
								'<span class="typee-search-dd__meta">' +
									'<span class="typee-search-dd__cat">' + escHtml(it.category + (it.stockText ? ' • ' + it.stockText : '')) + '</span>' +
									skuHtml +
								'</span>' +
								'<span class="typee-search-dd__name">' + highlight(it.name, kw) + '</span>' +
								'<span class="typee-search-dd__price"><strong>' + escHtml(it.priceText) + '</strong>' + regHtml + '</span>' +
							'</span>' +
							'<span class="typee-search-dd__arrow" aria-hidden="true">›</span>' +
						'</a>' +
					'</li>'
				);
			}).join('');

			dropdown.innerHTML =
				'<div class="typee-search-dd__head">' +
					'<span class="typee-search-dd__title">Gợi ý thông minh (' + items.length + ' sản phẩm phù hợp nhất)</span>' +
					'<span class="typee-search-dd__hint">SKU Indexing Instant</span>' +
				'</div>' +
				'<ul class="typee-search-dd__list">' + rowsHtml + '</ul>' +
				'<a class="typee-search-dd__foot" href="' + escHtml(data.allUrl) + '">Xem tất cả ' + total + ' kết quả cho từ khóa &ldquo;' + escHtml(kw) + '&rdquo; →</a>';
			openDd();
		}

		function fetchSuggestions(kw) {
			var mySeq = ++seq;
			lastQ = kw;
			if (kw) { renderLoading(); }
			if (ctrl) { ctrl.abort(); }
			ctrl = window.AbortController ? new AbortController() : null;

			var p = new URLSearchParams();
			p.set('action', 'typee_live_search');
			p.set('nonce', window.TYPEE.searchNonce);
			p.set('q', kw);

			fetch(window.TYPEE.ajaxUrl + '?' + p.toString(), { credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (mySeq !== seq || !res || !res.success) { return; }
					renderResponse(res.data);
				})
				.catch(function (err) {
					if (err && err.name === 'AbortError') { return; }
					closeDd();
				});
		}

		input.addEventListener('input', function () {
			clearTimeout(timer);
			var val = input.value.trim();
			timer = setTimeout(function () { fetchSuggestions(val); }, 300);
		});

		input.addEventListener('focus', function () {
			var val = input.value.trim();
			if (lastQ === val && dropdown.innerHTML) {
				openDd();
			} else {
				fetchSuggestions(val);
			}
		});

		document.addEventListener('click', function (e) {
			if (!form.contains(e.target)) { closeDd(); }
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !dropdown.hidden) { closeDd(); }
		});
	}

	/* ---------- 2. SEARCH RESULTS PAGE CONTROLS ---------- */
	var page = document.querySelector('[data-typee-search-page]');
	if (!page) { return; }

	page.querySelectorAll('[data-typee-search-autosubmit]').forEach(function (sel) {
		sel.addEventListener('change', function () {
			if (sel.form) { sel.form.submit(); }
		});
	});

	var grid = page.querySelector('[data-typee-search-grid]');
	var viewBtns = page.querySelectorAll('[data-search-view]');
	if (grid && viewBtns.length) {
		var savedView = 'grid';
		try { savedView = window.localStorage.getItem('typee_search_view') || 'grid'; } catch (e) {}

		function setView(mode) {
			var isList = mode === 'list';
			grid.classList.toggle('is-list', isList);
			try { window.localStorage.setItem('typee_search_view', isList ? 'list' : 'grid'); } catch (e) {}
			viewBtns.forEach(function (b) {
				var on = b.getAttribute('data-search-view') === (isList ? 'list' : 'grid');
				b.classList.toggle('is-active', on);
				b.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
		}
		viewBtns.forEach(function (b) {
			b.addEventListener('click', function () { setView(b.getAttribute('data-search-view')); });
		});
		setView(savedView);
	}
})();
