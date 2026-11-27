/**
 * TYPEE Checkout & Order Received Interactions (Figma 82:1187 & 82:8375)
 * - Áp dụng / gỡ mã giảm giá AJAX chuẩn WooCommerce (không reload trang)
 * - Lưu/tự điền thông tin giao nhận vào trình duyệt khi tích "Lưu thông tin giao hàng"
 * - Bật/tắt khu vực nhập hóa đơn điện tử VAT doanh nghiệp
 * - Đồng bộ trạng thái chọn thẻ phương thức thanh toán & trạng thái Loading khi bấm Đặt hàng
 * - Sao chép nhanh STK / Số tiền / Nội dung VietQR / Mã đơn hàng trên trang Order Received
 */
(function ($) {
	'use strict';

	var PROFILE_KEY = 'typee_saved_receiver_v1';

	/* ---------- 1. Đồng bộ class .is-chosen trên thẻ Radio Thanh toán ---------- */
	function syncPaymentCardSelection() {
		document.querySelectorAll('.ty-co-pay-item').forEach(function (li) {
			var radio = li.querySelector('input.ty-co-pay-radio');
			li.classList.toggle('is-chosen', !!(radio && radio.checked));
		});
	}
	$(document.body).on('change payment_method_selected updated_checkout', '.ty-co-pay-radio, body', syncPaymentCardSelection);
	syncPaymentCardSelection();

	/* ---------- 2. Đóng / mở form xuất hóa đơn điện tử VAT ---------- */
	$(document).on('change', '[data-ty-vat-toggle]', function () {
		var box = document.querySelector('[data-ty-vat-box]');
		if (box) {
			box.hidden = !this.checked;
		}
	});

	/* ---------- 3. Áp dụng & Gỡ mã giảm giá qua WooCommerce AJAX ---------- */
	function getWcAjaxUrl(endpoint) {
		if (window.wc_checkout_params && window.wc_checkout_params.wc_ajax_url) {
			return window.wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', endpoint);
		}
		return '/?wc-ajax=' + endpoint;
	}

	function showCouponMsg(text, isError) {
		var el = document.querySelector('[data-ty-coupon-msg]');
		if (!el) {
			return;
		}
		el.textContent = text;
		el.style.color = isError ? '#b91c1c' : '#047857';
		el.hidden = !text;
	}

	$(document).on('click', '[data-ty-coupon-apply]', function () {
		var input = document.querySelector('[data-ty-coupon-input]');
		var btn = this;
		var code = input ? input.value.trim() : '';
		if (!code) {
			showCouponMsg('Vui lòng nhập mã giảm giá trước khi bấm Áp dụng.', true);
			if (input) { input.focus(); }
			return;
		}

		btn.disabled = true;
		btn.textContent = 'Đang kiểm tra…';
		showCouponMsg('', false);

		$.ajax({
			type: 'POST',
			url: getWcAjaxUrl('apply_coupon'),
			data: {
				coupon_code: code,
				security: (window.wc_checkout_params && window.wc_checkout_params.apply_coupon_nonce) || ''
			},
			dataType: 'html'
		}).done(function (html) {
			var plain = $('<div>').html(html).text().trim();
			var isErr = html.indexOf('woocommerce-error') > -1 || html.indexOf('is-error') > -1;
			if (isErr) {
				showCouponMsg(plain || 'Mã giảm giá không hợp lệ hoặc đã hết hạn.', true);
			} else {
				if (input) { input.value = ''; }
				$(document.body).trigger('applied_coupon_in_checkout', [code]);
				$(document.body).trigger('update_checkout', { update_shipping_method: false });
			}
		}).fail(function () {
			showCouponMsg('Không thể kiểm tra mã giảm giá lúc này. Vui lòng thử lại.', true);
		}).always(function () {
			btn.disabled = false;
			btn.textContent = 'Áp dụng';
		});
	});

	$(document).on('keydown', '[data-ty-coupon-input]', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			var btn = document.querySelector('[data-ty-coupon-apply]');
			if (btn) { btn.click(); }
		}
	});

	$(document).on('click', '[data-ty-coupon-remove]', function (e) {
		e.preventDefault();
		var code = this.getAttribute('data-ty-coupon-remove');
		if (!code) {
			return;
		}
		$.ajax({
			type: 'POST',
			url: getWcAjaxUrl('remove_coupon'),
			data: {
				coupon: code,
				security: (window.wc_checkout_params && window.wc_checkout_params.remove_coupon_nonce) || ''
			},
			dataType: 'html'
		}).always(function () {
			$(document.body).trigger('removed_coupon_in_checkout', [code]);
			$(document.body).trigger('update_checkout', { update_shipping_method: false });
		});
	});

	/* ---------- 4. Lưu thông tin người nhận tiện lợi trên trình duyệt ---------- */
	function restoreSavedReceiver() {
		var form = document.querySelector('form.ty-co-form');
		if (!form) {
			return;
		}
		try {
			var saved = JSON.parse(window.localStorage.getItem(PROFILE_KEY) || 'null');
			if (!saved) {
				return;
			}
			['billing_last_name', 'billing_phone', 'billing_email', 'billing_address_1'].forEach(function (name) {
				var field = form.querySelector('[name="' + name + '"]');
				if (field && !field.value && saved[name]) {
					field.value = saved[name];
				}
			});
		} catch (err) {
			/* ignore */
		}
	}
	restoreSavedReceiver();

	// Tự động nạp danh sách Quận/Huyện/Phường ngay khi vào trang nếu Tỉnh/Thành đã có giá trị mặc định (VD: HOCHIMINH).
	window.setTimeout(function () {
		var $state = $('#billing_state');
		var $city = $('#billing_city');
		if ($state.length && $city.length && $state.val() && $city.find('option').length <= 1) {
			$state.trigger('select2:select');
		}
	}, 180);

	$('form.checkout').on('checkout_place_order', function () {
		var saveChk = document.querySelector('[data-ty-save-receiver]');
		var btn = document.getElementById('place_order');
		if (btn) {
			btn.classList.add('is-loading');
			btn.textContent = 'ĐANG XỬ LÝ ĐƠN HÀNG…';
		}

		try {
			if (saveChk && saveChk.checked) {
				var payload = {};
				['billing_last_name', 'billing_phone', 'billing_email', 'billing_address_1'].forEach(function (name) {
					var f = document.querySelector('[name="' + name + '"]');
					if (f && f.value) {
						payload[name] = f.value.trim();
					}
				});
				window.localStorage.setItem(PROFILE_KEY, JSON.stringify(payload));
			} else if (saveChk && !saveChk.checked) {
				window.localStorage.removeItem(PROFILE_KEY);
			}
		} catch (err) {
			/* ignore */
		}
		return true;
	});

	$(document.body).on('checkout_error', function () {
		var btn = document.getElementById('place_order');
		if (btn) {
			btn.classList.remove('is-loading');
			btn.textContent = btn.getAttribute('data-value') || 'ĐẶT HÀNG NGAY';
		}
	});

	/* ---------- 5. Nút sao chép nhanh trên trang Đặt hàng thành công ---------- */
	$(document).on('click', '[data-ty-copy]', function () {
		var btn = this;
		var text = btn.getAttribute('data-ty-copy') || '';
		if (!text) {
			return;
		}
		var prev = btn.innerHTML;
		function markCopied() {
			btn.textContent = '✓ Đã chép';
			window.setTimeout(function () { btn.innerHTML = prev; }, 1600);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(markCopied);
		} else {
			var ta = document.createElement('textarea');
			ta.value = text;
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			markCopied();
		}
	});
})(jQuery);
