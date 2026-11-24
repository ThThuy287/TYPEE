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
			var id = String(this.getAttribute(attr));
			var i = list.indexOf(id);
			if (i > -1) { list.splice(i, 1); } else { list.push(id); }
			if (key === 'typee_compare' && list.length > 4) { list.shift(); }
			store.set(key, list);
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
