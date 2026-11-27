/**
 * TYPEE Child – tương tác giao diện (không gọi API ghi dữ liệu).
 * - Đếm ngược Flash Sale theo ngày kết thúc khuyến mãi trong WooCommerce
 * - Yêu thích / So sánh: lưu tạm trong trình duyệt (localStorage) của từng người xem
 * - Form nhận tin: chỉ báo trạng thái khi chưa gắn backend
 */
(function ($) {
	'use strict';

	var store = {
		get: function (key) {
			try { return JSON.parse(window.localStorage.getItem(key) || '[]'); } catch (e) { return []; }
		},
		set: function (key, val) {
			try { window.localStorage.setItem(key, JSON.stringify(val)); } catch (e) { /* bỏ qua */ }
		}
	};

	/* ---------- Đếm ngược ---------- */
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function initCountdown(el) {
		var end = parseInt(el.getAttribute('data-typee-countdown'), 10) * 1000;
		if (!end) { return; }
		var units = {};
		el.querySelectorAll('[data-u]').forEach(function (b) { units[b.getAttribute('data-u')] = b; });
		function tick() {
			var diff = Math.max(0, end - Date.now());
			var s = Math.floor(diff / 1000);
			units.d.textContent = pad(Math.floor(s / 86400));
			units.h.textContent = pad(Math.floor((s % 86400) / 3600));
			units.m.textContent = pad(Math.floor((s % 3600) / 60));
			units.s.textContent = pad(s % 60);
			if (diff <= 0) { clearInterval(timer); }
		}
		var timer = setInterval(tick, 1000);
		tick();
	}
	document.querySelectorAll('[data-typee-countdown]').forEach(initCountdown);

	/* ---------- Yêu thích & So sánh (per-viewer) ---------- */
	function bindToggle(attr, key, onChange) {
		var list = store.get(key);
		function paint() {
			document.querySelectorAll('[' + attr + ']').forEach(function (btn) {
				var id = String(btn.getAttribute(attr));
				btn.setAttribute('aria-pressed', list.indexOf(id) > -1 ? 'true' : 'false');
			});
			if (onChange) { onChange(list); }
		}
		$(document).on('click', '[' + attr + ']', function (e) {
			e.preventDefault();
			list = store.get(key);
			var id = String(this.getAttribute(attr));
			var i = list.indexOf(id);
			if (i > -1) { list.splice(i, 1); } else { list.push(id); }
			if (key === 'typee_compare' && list.length > 4) { list.shift(); }
			store.set(key, list);
			paint();
		});
		if (key === 'typee_compare') {
			$(document).on('typee:compare-sync', function (_e, nextList) {
				list = Array.isArray(nextList) ? nextList.map(String) : store.get(key);
				paint();
			});
		}
		$(document).on('typee:cards-updated', function () {
			list = store.get(key);
			paint();
		});
		paint();
	}
	bindToggle('data-typee-wishlist', 'typee_wishlist');
	bindToggle('data-typee-compare', 'typee_compare', function (list) {
		document.querySelectorAll('[data-typee-compare-count]').forEach(function (b) {
			b.textContent = list.length;
			b.hidden = list.length === 0;
		});
	});

	/* ---------- Drawer / Modal dùng chung: Esc để đóng, Tab không thoát khỏi hộp (PRD NFR-06) ---------- */
	var overlay = null;
	var openBox = null;
	var lastFocus = null;
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';

	function ensureOverlay() {
		if (!overlay) {
			overlay = document.createElement('div');
			overlay.className = 'ty-overlay';
			overlay.addEventListener('click', function () { closeBox(); });
			document.body.appendChild(overlay);
		}
		return overlay;
	}
	function openBoxEl(box, trigger) {
		if (!box) { return; }
		if (openBox && openBox !== box) { closeBox(true); }
		lastFocus = trigger || document.activeElement;
		ensureOverlay().classList.add('is-open');
		box.classList.add('is-open');
		box.setAttribute('aria-hidden', 'false');
		document.body.classList.add('typee-lock');
		openBox = box;
		var first = box.querySelector('[autofocus]') || box.querySelector(FOCUSABLE);
		window.setTimeout(function () { if (first) { first.focus(); } }, 60);
		$(box).trigger('typee:open');
	}
	function closeBox(keepOverlay) {
		if (!openBox) { return; }
		openBox.classList.remove('is-open');
		openBox.setAttribute('aria-hidden', 'true');
		$(openBox).trigger('typee:close');
		openBox = null;
		if (!keepOverlay && overlay) { overlay.classList.remove('is-open'); }
		document.body.classList.remove('typee-lock');
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}
	window.TYPEE_UI = { open: openBoxEl, close: closeBox };
	window.TYPEE_STORE = store;

	$(document).on('click', '[data-typee-open]', function (e) {
		var box = document.querySelector(this.getAttribute('data-typee-open'));
		if (box) { e.preventDefault(); openBoxEl(box, this); }
	});
	$(document).on('click', '[data-typee-cart-toggle]', function (e) {
		var cartDrawer = document.getElementById('ty-sidecart');
		if (cartDrawer) { e.preventDefault(); openBoxEl(cartDrawer, this); }
	});
	$(document.body).on('added_to_cart', function () {
		var cartDrawer = document.getElementById('ty-sidecart');
		if (cartDrawer && !cartDrawer.classList.contains('is-open')) {
			openBoxEl(cartDrawer);
		}
	});
	$(document).on('click', '[data-typee-close]', function (e) { e.preventDefault(); closeBox(); });
	document.addEventListener('keydown', function (e) {
		if (!openBox) { return; }
		if (e.key === 'Escape') { e.preventDefault(); closeBox(); return; }
		if (e.key === 'Tab') {
			var items = Array.prototype.filter.call(openBox.querySelectorAll(FOCUSABLE), function (el) { return el.offsetParent !== null; });
			if (!items.length) { return; }
			var first = items[0], last = items[items.length - 1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		}
	});

	/* ---------- Tabs (role=tab / tabpanel) ---------- */
	$(document).on('click', '[role="tab"][aria-controls]', function () {
		var list = this.closest('[role="tablist"]');
		list.querySelectorAll('[role="tab"]').forEach(function (t) {
			t.setAttribute('aria-selected', 'false');
			t.tabIndex = -1;
			var p = document.getElementById(t.getAttribute('aria-controls'));
			if (p) { p.hidden = true; }
		});
		this.setAttribute('aria-selected', 'true');
		this.tabIndex = 0;
		var panel = document.getElementById(this.getAttribute('aria-controls'));
		if (panel) { panel.hidden = false; }
	});
	$(document).on('keydown', '[role="tab"]', function (e) {
		if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
		var tabs = Array.prototype.slice.call(this.closest('[role="tablist"]').querySelectorAll('[role="tab"]'));
		var i = tabs.indexOf(this) + (e.key === 'ArrowRight' ? 1 : -1);
		var next = tabs[(i + tabs.length) % tabs.length];
		next.focus(); next.click();
	});

	/* ---------- Form nhận tin (chưa có backend) ---------- */
	$(document).on('submit', '[data-typee-newsletter]', function (e) {
		e.preventDefault();
		var input = this.querySelector('input[type="email"]');
		var msg = this.parentNode.querySelector('.ty-news__msg');
		if (!msg) { return; }
		msg.hidden = false;
		if (!input.value || !input.checkValidity()) {
			msg.textContent = 'Vui lòng nhập email hợp lệ.';
			return;
		}
		msg.textContent = 'Tính năng đăng ký nhận tin sẽ sớm được mở. Cảm ơn bạn đã quan tâm!';
	});
})(jQuery);
