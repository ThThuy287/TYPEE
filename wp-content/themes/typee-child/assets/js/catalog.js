/**
 * Bộ lọc Off-Canvas (PRD FR-01): đếm số kết quả trước khi áp dụng, đặt lại, không gửi tham số rỗng.
 */
(function () {
	'use strict';
	var form = document.querySelector('[data-typee-filter]');
	if (!form) { return; }
	var label = form.querySelector('[data-filter-label]');
	var tt = form.closest('[data-tt]').getAttribute('data-tt');
	var timer = null, seq = 0, ctrl = null;
	function base() { var n = activeCount(); return n ? 'Áp dụng ' + n + ' bộ lọc' : 'Xem tất cả'; }

	function params() {
		var p = new URLSearchParams();
		new FormData(form).forEach(function (v, k) { if (v !== '' && v != null) { p.append(k, v); } });
		return p;
	}
	function activeCount() {
		var n = form.querySelectorAll('input[type="checkbox"]:checked').length;
		if (form.min_price.value || form.max_price.value) { n++; }
		return n;
	}
	function update() {
		clearTimeout(timer);
		timer = setTimeout(function () {
			var p = params(), s = ++seq;
			p.append('action', 'typee_filter_count'); p.append('tt', tt);
			// Khi xem trước theme (chưa kích hoạt), admin-ajax cần cùng tham số xem trước để chạy code của theme con.
			new URLSearchParams(location.search).forEach(function (v, k) { if (k.indexOf('customize_') === 0) { p.append(k, v); } });
			// Nút vẫn bấm được trong lúc đếm; số kết quả hiện khi máy chủ trả về.
			label.textContent = base() + ' (đang đếm…)';
			if (ctrl) { ctrl.abort(); }
			ctrl = window.AbortController ? new AbortController() : null;
			fetch(window.TYPEE.ajaxUrl + '?' + p.toString(), { credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (s !== seq || !res || !res.success) { return; }
					label.textContent = base() + ' (' + res.data.count + ' kết quả)';
				})
				.catch(function (e) { if (s === seq && (!e || e.name !== 'AbortError')) { label.textContent = base(); } });
		}, 250);
	}
	form.addEventListener('change', update);
	form.addEventListener('input', function (e) { if (e.target.type === 'number') { update(); } });
	form.querySelector('[data-filter-reset]').addEventListener('click', function () {
		form.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = false; });
		form.min_price.value = ''; form.max_price.value = '';
		update();
	});
	// Không gửi ô giá trống lên URL.
	form.addEventListener('submit', function () {
		[form.min_price, form.max_price].forEach(function (i) { if (!i.value) { i.disabled = true; } });
	});
	// Sắp xếp: đổi là gửi ngay (giữ hành vi WooCommerce, không cần nút).
	var order = document.querySelector('.woocommerce-ordering select');
	if (order) { order.addEventListener('change', function () { document.body.classList.add('ty-is-loading'); this.form.submit(); }); }
})();

/**
 * Phản hồi tức thì khi đổi bộ lọc/sắp xếp/trang: hiện thanh tiến trình và làm mờ lưới
 * để người dùng biết hệ thống đang tải, không tưởng trang bị treo.
 */
(function () {
	'use strict';
	var cat = document.querySelector('.ty-catalog');
	if (!cat) { return; }
	function busy() { document.body.classList.add('ty-is-loading'); }
	cat.addEventListener('click', function (e) {
		var a = e.target.closest('a[href]');
		if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') { return; }
		if (a.closest('.ty-pill, .ty-chip, .ty-cattools__clear, .page-numbers, .ty-empty__actions')) { busy(); }
	});
	cat.addEventListener('submit', busy);
	window.addEventListener('pageshow', function () { document.body.classList.remove('ty-is-loading'); });
})();
