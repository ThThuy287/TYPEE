/**
 * Trang Theo Dõi Đơn Hàng & Bảo Hành Điện Tử e-RMA (Figma 82:3293).
 * - Chuyển tab mượt giữa Tra cứu đơn hàng & Tạo yêu cầu e-RMA
 * - Tra cứu đơn hàng thật qua AJAX `typee_lookup_order` (có Nonce)
 * - Tự điền nhanh đơn hàng gần đây của tài khoản đã đăng nhập
 * - Xác thực đơn hàng và gửi yêu cầu bảo hành thật lưu vào CPT `typee_warranty`
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-tracking-root]');
	if (!root || !window.TYPEE || !window.TYPEE.trackingNonce) { return; }

	function esc(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/* ---------- 1. Tab Switching ---------- */
	var tabBtns   = root.querySelectorAll('[data-tracking-tab]');
	var panelLook = document.getElementById('tracking-panel-lookup');
	var panelRma  = document.getElementById('tracking-panel-rma');

	function switchTab(name) {
		var isRma = name === 'rma';
		tabBtns.forEach(function (b) {
			var on = b.getAttribute('data-tracking-tab') === (isRma ? 'rma' : 'lookup');
			b.classList.toggle('is-active', on);
			b.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		if (panelLook) { panelLook.hidden = isRma; }
		if (panelRma) { panelRma.hidden = !isRma; }
	}

	tabBtns.forEach(function (b) {
		b.addEventListener('click', function () { switchTab(b.getAttribute('data-tracking-tab')); });
	});
	root.querySelectorAll('[data-switch-to-rma]').forEach(function (b) {
		b.addEventListener('click', function () { switchTab('rma'); root.scrollIntoView({ behavior: 'smooth' }); });
	});
	root.querySelectorAll('[data-switch-to-lookup]').forEach(function (b) {
		b.addEventListener('click', function () { switchTab('lookup'); root.scrollIntoView({ behavior: 'smooth' }); });
	});

	/* ---------- 2. Order Tracking & Warranty Lookup ---------- */
	var lookupForm    = root.querySelector('[data-tracking-lookup-form]');
	var lookupAlert   = root.querySelector('[data-lookup-alert]');
	var lookupBtn     = root.querySelector('[data-lookup-btn]');
	var lookupBtnText = root.querySelector('[data-lookup-btn-text]');
	var resultBox     = root.querySelector('[data-tracking-result]');
	var lastOrderData = null;
	var lastContact   = '';

	function setAlert(box, msg, type) {
		if (!box) { return; }
		if (!msg) { box.hidden = true; box.textContent = ''; return; }
		box.className = 'typee-tracking-alert ' + (type === 'success' ? 'is-success' : 'is-error');
		box.textContent = msg;
		box.hidden = false;
	}

	function renderOrderResult(d) {
		if (!resultBox) { return; }
		var step = parseInt(d.statusStep, 10) || 0;
		var steps = [
			{ n: 1, title: '1. Đã đặt hàng', sub: d.createdAt || 'Hệ thống đã ghi nhận' },
			{ n: 2, title: '2. Xử lý & KCS Lab', sub: 'Kiểm định & đóng gói chống sốc' },
			{ n: 3, title: '3. Đang giao hàng', sub: d.shippingMethod || 'Đơn vị vận chuyển' },
			{ n: 4, title: '4. Giao thành công', sub: 'Hoàn tất & kích hoạt e-Warranty' }
		];

		var stepsHtml = steps.map(function (st) {
			var cls = 'is-pending';
			if (step === 0) { cls = 'is-pending'; }
			else if (step > st.n || (step === 4 && st.n === 4)) { cls = 'is-done'; }
			else if (step === st.n) { cls = 'is-active'; }
			return (
				'<div class="typee-tracking-step ' + cls + '">' +
					'<span class="typee-tracking-step__dot">' + (cls === 'is-done' ? '✓' : st.n) + '</span>' +
					'<h4>' + esc(st.title) + '</h4>' +
					'<p>' + esc(st.sub) + '</p>' +
				'</div>'
			);
		}).join('');

		var itemsHtml = (d.items || []).map(function (it) {
			var w = it.warranty || {};
			var wCls = w.isActive ? 'is-active' : 'is-expired';
			var reqBtn = w.isActive
				? '<button type="button" class="typee-warranty-reqbtn" data-req-rma-prod="' + esc(it.productId) + '">Yêu cầu bảo hành</button>'
				: '';
			var nameEl = it.url
				? '<a class="typee-tracking-item__name" href="' + esc(it.url) + '">' + esc(it.name) + '</a>'
				: '<span class="typee-tracking-item__name">' + esc(it.name) + '</span>';
			return (
				'<div class="typee-tracking-item">' +
					'<div class="typee-tracking-item__left">' +
						'<img class="typee-tracking-item__thumb" src="' + esc(it.thumb) + '" alt="" loading="lazy" />' +
						'<div class="typee-tracking-item__info">' +
							nameEl +
							'<span class="typee-tracking-item__sku">' + (it.sku ? ('SKU: ' + esc(it.sku) + ' | ') : '') + 'SL: ' + esc(it.qty) + '</span>' +
							'<span class="typee-warranty-pill ' + wCls + '">' + esc(w.label || '') + '</span>' +
						'</div>' +
					'</div>' +
					'<div class="typee-tracking-item__right">' +
						'<span class="typee-tracking-item__price">' + esc(it.total) + '</span>' +
						reqBtn +
					'</div>' +
				'</div>'
			);
		}).join('');

		var ticketsHtml = '';
		if (d.tickets && d.tickets.length) {
			ticketsHtml =
				'<div class="typee-warranty-tickets">' +
					'<div class="typee-tracking-subhead"><h4>Phiếu bảo hành e-RMA đã gửi (' + d.tickets.length + ')</h4></div>' +
					d.tickets.map(function (t) {
						return (
							'<div class="typee-warranty-ticket">' +
								'<div>' +
									'<strong>' + esc(t.code) + '</strong> — ' + esc(t.productName) +
									'<div style="font-size:12px;color:#5b4039;margin-top:2px">' + esc(t.issueType) + ' • ' + esc(t.createdAt) + (t.adminNote ? ('<br/>Phản hồi Lab: ' + esc(t.adminNote)) : '') + '</div>' +
								'</div>' +
								'<span class="typee-tracking-status is-' + esc(t.statusTone || 'warning') + '">' + esc(t.statusLabel) + '</span>' +
							'</div>'
						);
					}).join('') +
				'</div>';
		}

		var discountRow = d.discountTotal
			? '<div class="typee-tracking-summary__row"><span>Giảm giá Voucher</span><strong>' + esc(d.discountTotal) + '</strong></div>'
			: '';

		resultBox.innerHTML =
			'<div class="typee-tracking-rescard">' +
				'<div class="typee-tracking-reshead">' +
					'<div class="typee-tracking-reshead__left">' +
						'<span class="typee-tracking-reshead__code">' + esc(d.orderCode) + '</span>' +
						'<span class="typee-tracking-status is-' + esc(d.statusTone || 'accent') + '">' + esc(d.statusLabel) + '</span>' +
					'</div>' +
					'<div class="typee-tracking-reshead__meta">' +
						'<span>Đặt lúc: <strong>' + esc(d.createdAt) + '</strong></span>' +
						'<span>Vận chuyển: <strong>' + esc(d.shippingMethod) + '</strong></span>' +
					'</div>' +
				'</div>' +
				'<div class="typee-tracking-resbody">' +
					'<div>' +
						'<h3 class="typee-tracking-stepper-title">Lộ trình xử lý đơn hàng</h3>' +
						'<div class="typee-tracking-steps">' + stepsHtml + '</div>' +
					'</div>' +
					'<div class="typee-tracking-detgrid">' +
						'<div class="typee-tracking-receiver">' +
							'<div class="typee-tracking-subhead"><h4>Thông tin người nhận</h4><span>' + esc(d.shippingTotal) + '</span></div>' +
							'<div class="typee-tracking-kv">' +
								'<div class="typee-tracking-kv__row"><span>Họ tên:</span><strong>' + esc(d.customerName) + '</strong></div>' +
								'<div class="typee-tracking-kv__row"><span>Số điện thoại:</span><strong>' + esc(d.phone || '—') + '</strong></div>' +
								'<div class="typee-tracking-kv__row"><span>Email:</span><strong>' + esc(d.email || '—') + '</strong></div>' +
							'</div>' +
							'<div class="typee-tracking-addr"><strong>Địa chỉ nhận hàng:</strong><br/>' + esc(d.address || 'Chưa cập nhật') + (d.note ? ('<br/><em>Ghi chú: ' + esc(d.note) + '</em>') : '') + '</div>' +
							'<div class="typee-tracking-paybox">' +
								'<div><strong>' + esc(d.paymentMethod) + '</strong><small>' + (d.isPaid ? 'Đã thanh toán' : 'Chưa thanh toán / COD') + '</small></div>' +
								'<span class="typee-tracking-paybox__total">' + esc(d.total) + '</span>' +
							'</div>' +
						'</div>' +
						'<div class="typee-tracking-items">' +
							'<div class="typee-tracking-subhead"><h4>Sản phẩm &amp; Thời hạn bảo hành (' + esc(d.itemCount) + ' món)</h4><span>Tự động theo ngày đặt</span></div>' +
							'<div class="typee-tracking-itemlist">' + itemsHtml + '</div>' +
							'<div class="typee-tracking-summary">' +
								'<div class="typee-tracking-summary__row"><span>Tạm tính</span><strong>' + esc(d.subtotal) + '</strong></div>' +
								discountRow +
								'<div class="typee-tracking-summary__row"><span>Phí vận chuyển</span><strong>' + esc(d.shippingTotal) + '</strong></div>' +
								'<div class="typee-tracking-summary__row is-grand"><span>Tổng thanh toán</span><strong>' + esc(d.total) + '</strong></div>' +
							'</div>' +
							ticketsHtml +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		resultBox.hidden = false;
		resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

		// Nút 1-click "Yêu cầu bảo hành" trên từng sản phẩm → chuyển sang Tab 2 và điền sẵn thông tin!
		resultBox.querySelectorAll('[data-req-rma-prod]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var pid = btn.getAttribute('data-req-rma-prod');
				prefillRmaForm(d, lastContact, pid);
				switchTab('rma');
				root.scrollIntoView({ behavior: 'smooth' });
			});
		});
	}

	function runLookup(orderCode, contact) {
		setAlert(lookupAlert, '');
		if (lookupBtn) { lookupBtn.classList.add('is-loading'); }
		if (lookupBtnText) { lookupBtnText.textContent = 'Đang tra cứu…'; }

		var fd = new FormData();
		fd.append('action', 'typee_lookup_order');
		fd.append('nonce', window.TYPEE.trackingNonce);
		fd.append('order_code', orderCode);
		fd.append('contact', contact);

		return fetch(window.TYPEE.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) {
					if (resultBox) { resultBox.hidden = true; }
					setAlert(lookupAlert, (res && res.data && res.data.message) ? res.data.message : 'Không tìm thấy đơn hàng.', 'error');
					return null;
				}
				lastOrderData = res.data;
				lastContact = contact || res.data.phone || res.data.email || '';
				renderOrderResult(res.data);
				return res.data;
			})
			.catch(function () {
				setAlert(lookupAlert, 'Lỗi kết nối tới máy chủ. Vui lòng thử lại.', 'error');
				return null;
			})
			.finally(function () {
				if (lookupBtn) { lookupBtn.classList.remove('is-loading'); }
				if (lookupBtnText) { lookupBtnText.textContent = 'Tra cứu ngay'; }
			});
	}

	if (lookupForm) {
		lookupForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var code = lookupForm.order_code.value.trim();
			var ct   = lookupForm.contact.value.trim();
			if (!code || !ct) {
				setAlert(lookupAlert, 'Vui lòng nhập đầy đủ Mã đơn hàng và SĐT/Email.', 'error');
				return;
			}
			runLookup(code, ct);
		});
	}

	// Bấm nhanh đơn hàng gần đây của user đã đăng nhập
	root.querySelectorAll('[data-quick-order]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var oid = btn.getAttribute('data-quick-order');
			var ct  = btn.getAttribute('data-quick-contact') || '';
			if (lookupForm) {
				lookupForm.order_code.value = '#TY-' + oid;
				lookupForm.contact.value = ct;
			}
			runLookup(oid, ct);
		});
	});

	/* ---------- 3. Tab 2: Warranty e-RMA Submission ---------- */
	var rmaForm       = root.querySelector('[data-warranty-form]');
	var rmaOrderInput = root.querySelector('[data-rma-order]');
	var rmaContInput  = root.querySelector('[data-rma-contact]');
	var rmaVerifyBtn  = root.querySelector('[data-rma-verify]');
	var rmaVerifyHint = root.querySelector('[data-rma-verify-hint]');
	var rmaFieldsBox  = root.querySelector('[data-rma-fields]');
	var rmaProdSelect = root.querySelector('[data-rma-product]');
	var rmaSubmitBtn  = root.querySelector('[data-rma-submit]');
	var rmaSubmitText = root.querySelector('[data-rma-submit-text]');
	var rmaAlert      = root.querySelector('[data-rma-alert]');

	function prefillRmaForm(orderData, contact, selectedProdId) {
		if (!orderData || !rmaOrderInput || !rmaContInput || !rmaProdSelect || !rmaFieldsBox) { return; }
		rmaOrderInput.value = orderData.orderCode;
		rmaContInput.value  = contact || orderData.phone || orderData.email || '';
		var opts = (orderData.items || []).map(function (it) {
			var w = it.warranty || {};
			var dis = w.isActive ? '' : ' disabled';
			var sel = (selectedProdId && String(it.productId) === String(selectedProdId)) ? ' selected' : '';
			return '<option value="' + esc(it.productId) + '"' + dis + sel + '>' + esc(it.name) + ' — ' + esc(w.label || '') + '</option>';
		}).join('');
		rmaProdSelect.innerHTML = opts;
		rmaFieldsBox.hidden = false;
		if (rmaVerifyHint) { rmaVerifyHint.textContent = 'Đã xác thực đơn ' + orderData.orderCode + ' (' + (orderData.items || []).length + ' sản phẩm).'; }
		setAlert(rmaAlert, '');
	}

	if (rmaVerifyBtn && rmaOrderInput && rmaContInput) {
		rmaVerifyBtn.addEventListener('click', function () {
			setAlert(rmaAlert, '');
			var code = rmaOrderInput.value.trim();
			var ct   = rmaContInput.value.trim();
			if (!code || !ct) {
				setAlert(rmaAlert, 'Vui lòng nhập Mã đơn hàng và SĐT/Email để xác thực.', 'error');
				return;
			}
			rmaVerifyBtn.disabled = true;
			var fd = new FormData();
			fd.append('action', 'typee_lookup_order');
			fd.append('nonce', window.TYPEE.trackingNonce);
			fd.append('order_code', code);
			fd.append('contact', ct);

			fetch(window.TYPEE.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) {
						if (rmaFieldsBox) { rmaFieldsBox.hidden = true; }
						setAlert(rmaAlert, (res && res.data && res.data.message) ? res.data.message : 'Không thể xác thực đơn hàng.', 'error');
						return;
					}
					prefillRmaForm(res.data, ct, '');
				})
				.catch(function () {
					setAlert(rmaAlert, 'Lỗi kết nối. Vui lòng thử lại.', 'error');
				})
				.finally(function () { rmaVerifyBtn.disabled = false; });
		});
	}

	if (rmaForm) {
		rmaForm.addEventListener('submit', function (e) {
			e.preventDefault();
			setAlert(rmaAlert, '');
			if (rmaSubmitBtn) { rmaSubmitBtn.classList.add('is-loading'); }
			if (rmaSubmitText) { rmaSubmitText.textContent = 'Đang gửi yêu cầu…'; }

			var fd = new FormData(rmaForm);
			fd.set('action', 'typee_submit_warranty');
			fd.set('nonce', window.TYPEE.trackingNonce);

			fetch(window.TYPEE.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) {
						setAlert(rmaAlert, (res && res.data && res.data.message) ? res.data.message : 'Không thể gửi yêu cầu bảo hành.', 'error');
						return;
					}
					setAlert(rmaAlert, res.data.message, 'success');
					rmaForm.description.value = '';
				})
				.catch(function () {
					setAlert(rmaAlert, 'Lỗi kết nối tới máy chủ. Vui lòng thử lại.', 'error');
				})
				.finally(function () {
					if (rmaSubmitBtn) { rmaSubmitBtn.classList.remove('is-loading'); }
					if (rmaSubmitText) { rmaSubmitText.textContent = 'Gửi yêu cầu bảo hành e-RMA'; }
				});
		});
	}
})();
