/**
 * TYPEE Child – Tương tác khu vực Tài khoản & Xác thực.
 *
 * - Chuyển đổi tab Đăng nhập / Đăng ký (hỗ trợ bàn phím, URL hash, nút footnote).
 * - Nút ẩn/hiện mật khẩu cho toàn bộ input mật khẩu.
 * - Đánh giá độ mạnh mật khẩu & kiểm tra khớp mật khẩu xác nhận.
 * - Nút xóa nhanh nội dung ô email trên trang Khôi phục mật khẩu.
 * - Đếm ngược gửi lại email xác nhận khôi phục mật khẩu.
 * - Mở/đóng sidebar Tài khoản trên mobile & đồng bộ badge Wishlist từ localStorage.
 */
(function () {
	'use strict';

	/* ---------- 1. Chuyển tab Đăng nhập / Đăng ký ---------- */
	var authRoot = document.querySelector('[data-typee-auth-root]');
	if (authRoot) {
		var tabs = Array.prototype.slice.call(authRoot.querySelectorAll('[data-typee-auth-tab]'));
		var panels = Array.prototype.slice.call(authRoot.querySelectorAll('[data-typee-auth-panel]'));
		var heading = authRoot.querySelector('[data-typee-auth-heading]');

		var activateTab = function (target, moveFocus) {
			tabs.forEach(function (btn) {
				var isCurrent = btn.getAttribute('data-typee-auth-tab') === target;
				btn.classList.toggle('is-active', isCurrent);
				btn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
				btn.tabIndex = isCurrent ? 0 : -1;
				if (isCurrent && moveFocus) {
					btn.focus();
				}
			});

			panels.forEach(function (panel) {
				var isCurrent = panel.getAttribute('data-typee-auth-panel') === target;
				panel.hidden = !isCurrent;
			});

			if (heading) {
				var nextTitle = heading.getAttribute(
					target === 'register' ? 'data-title-register' : 'data-title-login'
				);
				if (nextTitle) {
					heading.textContent = nextTitle;
				}
			}
		};

		tabs.forEach(function (btn) {
			btn.addEventListener('click', function () {
				activateTab(btn.getAttribute('data-typee-auth-tab'), false);
			});

			btn.addEventListener('keydown', function (e) {
				if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
					return;
				}
				e.preventDefault();
				var idx = tabs.indexOf(btn);
				var next = tabs[(idx + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
				activateTab(next.getAttribute('data-typee-auth-tab'), true);
			});
		});

		authRoot.querySelectorAll('[data-typee-switch-tab]').forEach(function (trigger) {
			trigger.addEventListener('click', function () {
				var target = trigger.getAttribute('data-typee-switch-tab');
				activateTab(target, false);
				var panel = authRoot.querySelector('[data-typee-auth-panel="' + target + '"]');
				if (panel) {
					var firstInput = panel.querySelector('input:not([type="hidden"])');
					if (firstInput) {
						firstInput.focus();
					}
				}
			});
		});

		if (window.location.hash === '#register') {
			activateTab('register', false);
		} else if (window.location.hash === '#login') {
			activateTab('login', false);
		}

		// Phản hồi nhẹ cho nút Social khi chưa cấu hình plugin OAuth.
		var socialMsg = authRoot.querySelector('[data-typee-social-msg]');
		authRoot.querySelectorAll('[data-typee-social-placeholder]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!socialMsg) {
					return;
				}
				var provider = btn.getAttribute('data-typee-social-placeholder') || 'mạng xã hội';
				socialMsg.textContent = 'Đăng nhập nhanh bằng ' + provider + ' đang được kết nối OAuth. Vui lòng đăng nhập bằng Email / SĐT ở biểu mẫu phía trên.';
				socialMsg.hidden = false;
			});
		});
	}

	/* ---------- 2. Nút ẩn / hiện mật khẩu ---------- */
	document.querySelectorAll('[data-typee-pw-toggle]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var inputId = btn.getAttribute('data-typee-pw-toggle');
			var input = inputId ? document.getElementById(inputId) : null;
			if (!input) {
				return;
			}
			var showing = input.type === 'text';
			input.type = showing ? 'password' : 'text';
			btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
			btn.setAttribute('aria-label', showing ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');

			var eyeShow = btn.querySelector('.typee-auth-pw-toggle__eye--show');
			var eyeHide = btn.querySelector('.typee-auth-pw-toggle__eye--hide');
			if (eyeShow) {
				eyeShow.hidden = !showing;
			}
			if (eyeHide) {
				eyeHide.hidden = showing;
			}
		});
	});

	/* ---------- 3. Độ mạnh mật khẩu & xác nhận khớp mật khẩu ---------- */
	document.querySelectorAll('[data-typee-pw-strength]').forEach(function (input) {
		var field = input.closest('.typee-auth-field');
		var meter = field ? field.querySelector('[data-typee-strength-meter]') : null;
		var bar = meter ? meter.querySelector('[data-typee-strength-bar]') : null;
		var label = meter ? meter.querySelector('[data-typee-strength-label]') : null;
		if (!meter || !bar || !label) {
			return;
		}

		input.addEventListener('input', function () {
			var val = input.value || '';
			if (!val.length) {
				meter.hidden = true;
				return;
			}
			meter.hidden = false;
			var score = 0;
			if (val.length >= 8) { score++; }
			if (/[A-Z]/.test(val) && /[a-z]/.test(val)) { score++; }
			if (/\d/.test(val)) { score++; }
			if (/[^A-Za-z0-9]/.test(val)) { score++; }

			bar.classList.remove('is-weak', 'is-medium', 'is-strong');
			if (score <= 1) {
				bar.classList.add('is-weak');
				label.textContent = 'Mật khẩu yếu';
			} else if (score <= 2) {
				bar.classList.add('is-medium');
				label.textContent = 'Trung bình';
			} else {
				bar.classList.add('is-strong');
				label.textContent = 'Mật khẩu mạnh';
			}
		});
	});

	document.querySelectorAll('[data-typee-pw-confirm]').forEach(function (confirmInput) {
		var sourceId = confirmInput.getAttribute('data-typee-pw-confirm');
		var sourceInput = sourceId ? document.getElementById(sourceId) : null;
		var field = confirmInput.closest('.typee-auth-field');
		var msg = field ? field.querySelector('[data-typee-match-msg]') : null;
		if (!sourceInput || !msg) {
			return;
		}

		var checkMatch = function () {
			var a = sourceInput.value || '';
			var b = confirmInput.value || '';
			if (!b.length) {
				msg.hidden = true;
				return;
			}
			msg.hidden = false;
			if (a === b) {
				msg.textContent = 'Mật khẩu xác nhận khớp.';
				msg.classList.remove('is-err');
				msg.classList.add('is-ok');
			} else {
				msg.textContent = 'Mật khẩu xác nhận chưa trùng khớp.';
				msg.classList.remove('is-ok');
				msg.classList.add('is-err');
			}
		};

		confirmInput.addEventListener('input', checkMatch);
		sourceInput.addEventListener('input', checkMatch);
	});

	/* ---------- 4. Nút xóa nhanh input email khôi phục mật khẩu ---------- */
	document.querySelectorAll('[data-typee-clear-input]').forEach(function (btn) {
		var inputId = btn.getAttribute('data-typee-clear-input');
		var input = inputId ? document.getElementById(inputId) : null;
		if (!input) {
			return;
		}
		var syncVisibility = function () {
			btn.hidden = !(input.value && input.value.length > 0);
		};
		input.addEventListener('input', syncVisibility);
		btn.addEventListener('click', function () {
			input.value = '';
			syncVisibility();
			input.focus();
		});
		syncVisibility();
	});

	/* ---------- 5. Đếm ngược gửi lại email khôi phục (lost-password-confirmation) ---------- */
	document.querySelectorAll('[data-typee-resend-countdown]').forEach(function (link) {
		var sec = parseInt(link.getAttribute('data-typee-resend-countdown'), 10);
		var secEl = link.querySelector('[data-typee-resend-sec]');
		if (!sec || sec <= 0 || !secEl) {
			return;
		}
		link.addEventListener('click', function (e) {
			if (link.classList.contains('is-waiting')) {
				e.preventDefault();
			}
		});
		var timer = window.setInterval(function () {
			sec -= 1;
			if (sec <= 0) {
				window.clearInterval(timer);
				link.classList.remove('is-waiting');
				link.removeAttribute('aria-disabled');
				link.textContent = 'Gửi lại liên kết';
				return;
			}
			secEl.textContent = String(sec);
		}, 1000);
	});

	/* ---------- 6. Toggle Sidebar Tài khoản trên Mobile / Tablet ---------- */
	var navToggle = document.querySelector('[data-typee-account-nav-toggle]');
	var sidebar = document.getElementById('typee-account-sidebar');
	if (navToggle && sidebar) {
		navToggle.addEventListener('click', function () {
			var open = sidebar.classList.toggle('is-open');
			navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* ---------- 7. Đồng bộ badge Wishlist & Cấu hình Builder từ localStorage + Server ---------- */
	var cfg = window.TYPEE_ACCOUNT || {};

	var readStore = function (key, fallback) {
		try {
			var raw = window.localStorage.getItem(key);
			if (!raw) {
				return fallback;
			}
			var parsed = JSON.parse(raw);
			return parsed !== null && parsed !== undefined ? parsed : fallback;
		} catch (e) {
			return fallback;
		}
	};

	var writeStore = function (key, value) {
		try {
			window.localStorage.setItem(key, JSON.stringify(value));
		} catch (e) {
			/* bỏ qua nếu trình duyệt chặn localStorage */
		}
	};

	var escHtml = function (str) {
		return String(str === null || str === undefined ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	};

	var fmtVnd = function (num) {
		var n = Math.round(Number(num) || 0);
		return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '₫';
	};

	var fmtDateVi = function (iso) {
		if (!iso) {
			return 'Gần đây';
		}
		var d = new Date(iso);
		if (isNaN(d.getTime())) {
			return 'Gần đây';
		}
		var dd = String(d.getDate()).padStart(2, '0');
		var mm = String(d.getMonth() + 1).padStart(2, '0');
		return dd + '/' + mm + '/' + d.getFullYear();
	};

	var getMergedWishlistIds = function () {
		var localList = readStore('typee_wishlist', []);
		if (!Array.isArray(localList)) {
			localList = [];
		}
		var serverList = Array.isArray(cfg.serverWishlist) ? cfg.serverWishlist : [];
		var seen = {};
		var merged = [];
		localList.concat(serverList).forEach(function (item) {
			var id = String(parseInt(item, 10) || 0);
			if (id !== '0' && !seen[id]) {
				seen[id] = true;
				merged.push(id);
			}
		});
		return merged;
	};

	var getMergedBuilds = function () {
		var localList = readStore('typee_builds', []);
		if (!Array.isArray(localList)) {
			localList = [];
		}
		var serverList = Array.isArray(cfg.serverBuilds) ? cfg.serverBuilds : [];
		var map = {};
		var order = [];

		localList.concat(serverList).forEach(function (b) {
			if (!b || !b.id) {
				return;
			}
			var id = String(b.id);
			if (!map[id]) {
				map[id] = b;
				order.push(id);
			} else {
				var curT = new Date(map[id].updated || map[id].created || 0).getTime() || 0;
				var newT = new Date(b.updated || b.created || 0).getTime() || 0;
				if (newT > curT) {
					map[id] = b;
				}
			}
		});

		return order.map(function (id) {
			return map[id];
		});
	};

	var updateSidebarWishlistBadge = function (count) {
		document.querySelectorAll('[data-typee-account-wishlist-count]').forEach(function (el) {
			if (count > 0) {
				el.textContent = String(count);
				el.hidden = false;
			} else {
				el.textContent = '';
				el.hidden = true;
			}
		});
		document.querySelectorAll('[data-typee-wishlist-count]').forEach(function (el) {
			el.textContent = String(count);
			el.hidden = count <= 0;
		});
	};

	var updateSidebarBuildsBadge = function (count) {
		document.querySelectorAll('[data-typee-account-builds-count]').forEach(function (el) {
			if (count > 0) {
				el.textContent = count + ' build';
				el.hidden = false;
			} else {
				el.textContent = '';
				el.hidden = true;
			}
		});
	};

	updateSidebarWishlistBadge(getMergedWishlistIds().length);
	updateSidebarBuildsBadge(getMergedBuilds().length);

	/* ---------- Helper AJAX & WooCommerce Cart ---------- */
	var postAjax = function (action, payload) {
		if (!cfg.ajaxUrl) {
			return Promise.resolve({ success: false });
		}
		var fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', cfg.nonce || '');
		Object.keys(payload || {}).forEach(function (k) {
			var v = payload[k];
			fd.append(k, typeof v === 'object' ? JSON.stringify(v) : String(v));
		});
		return window.fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: fd
		}).then(function (r) {
			return r.json();
		}).catch(function () {
			return { success: false };
		});
	};

	var addToWcCart = function (params) {
		var url = cfg.addToCartUrl || '/?wc-ajax=add_to_cart';
		var body = new URLSearchParams();
		Object.keys(params || {}).forEach(function (k) {
			if (params[k] !== undefined && params[k] !== null && params[k] !== '') {
				body.append(k, String(params[k]));
			}
		});
		return window.fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) {
			return r.json();
		}).catch(function () {
			return { error: true };
		});
	};

	var triggerCartRefresh = function (data) {
		if (window.jQuery) {
			if (data && data.fragments) {
				window.jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash || '']);
			} else {
				window.jQuery(document.body).trigger('wc_fragment_refresh');
			}
		}
	};

	var showInlineToast = function (el, msg, tone) {
		if (!el) {
			return;
		}
		el.textContent = msg;
		el.classList.remove('is-error', 'is-info');
		if (tone === 'error') {
			el.classList.add('is-error');
		} else if (tone === 'info') {
			el.classList.add('is-info');
		}
		el.hidden = false;
		if (el._typeeTimer) {
			window.clearTimeout(el._typeeTimer);
		}
		el._typeeTimer = window.setTimeout(function () {
			el.hidden = true;
		}, 4500);
	};

	/* ---------- Xác nhận xóa trên Sổ địa chỉ ---------- */
	document.querySelectorAll('form[data-typee-confirm]').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			var message = form.getAttribute('data-typee-confirm') || 'Bạn chắc chắn muốn thực hiện thao tác này?';
			if (!window.confirm(message)) {
				e.preventDefault();
			}
		});
	});

	/* ---------- 8. Đơn hàng: tự lọc khi đổi khoảng thời gian & nút In đơn ---------- */
	document.querySelectorAll('[data-typee-autosubmit]').forEach(function (select) {
		select.addEventListener('change', function () {
			var form = select.closest('form');
			if (form) {
				form.submit();
			}
		});
	});

	document.querySelectorAll('[data-typee-print-order]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			window.print();
		});
	});

	/* ---------- 9. Hồ sơ cá nhân: Xem trước ảnh đại diện, đồng bộ Họ tên, Gauge 4 mức ---------- */
	var avatarInput = document.querySelector('[data-typee-avatar-input]');
	var avatarPreview = document.querySelector('[data-typee-avatar-preview]');
	var avatarHint = document.querySelector('[data-typee-avatar-hint]');
	if (avatarInput) {
		document.querySelectorAll('[data-typee-avatar-trigger]').forEach(function (trigger) {
			trigger.addEventListener('click', function () {
				avatarInput.click();
			});
		});

		avatarInput.addEventListener('change', function () {
			var file = avatarInput.files && avatarInput.files[0];
			if (!file) {
				return;
			}
			if (file.size > 5 * 1024 * 1024) {
				if (avatarHint) {
					avatarHint.textContent = 'Ảnh vượt quá 5MB, vui lòng chọn tệp nhỏ hơn.';
				}
				avatarInput.value = '';
				return;
			}
			if (avatarHint) {
				avatarHint.textContent = 'Đã chọn: ' + file.name + ' (bấm "Lưu thay đổi" để cập nhật)';
			}
			if (avatarPreview && window.FileReader) {
				var reader = new FileReader();
				reader.onload = function (ev) {
					var img = avatarPreview.querySelector('img');
					if (img && ev.target && ev.target.result) {
						img.src = ev.target.result;
						img.removeAttribute('srcset');
					}
				};
				reader.readAsDataURL(file);
			}
		});
	}

	var fullNameInput = document.querySelector('[data-typee-fullname-sync]');
	if (fullNameInput) {
		var firstHidden = document.getElementById('account_first_name');
		var lastHidden = document.getElementById('account_last_name');
		var displayHidden = document.getElementById('account_display_name');
		var syncName = function () {
			var val = (fullNameInput.value || '').trim();
			if (!val) {
				return;
			}
			var parts = val.split(/\s+/);
			var last = parts.length > 1 ? parts.pop() : '';
			var first = parts.join(' ') || val;
			if (firstHidden) { firstHidden.value = first; }
			if (lastHidden) { lastHidden.value = last; }
			if (displayHidden) { displayHidden.value = val; }
		};
		fullNameInput.addEventListener('input', syncName);
	}

	document.querySelectorAll('[data-typee-focus-input]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var targetId = btn.getAttribute('data-typee-focus-input');
			var target = targetId ? document.getElementById(targetId) : null;
			if (target) {
				target.focus();
				if (target.select) {
					target.select();
				}
			}
		});
	});

	var profilePw = document.querySelector('[data-typee-profile-pw]');
	var pwGauge = document.querySelector('[data-typee-pw-gauge]');
	if (profilePw && pwGauge) {
		var gaugeLabel = pwGauge.querySelector('[data-typee-pw-gauge-label]');
		var segs = Array.prototype.slice.call(pwGauge.querySelectorAll('[data-typee-pw-seg]'));
		var ruleLen = pwGauge.querySelector('[data-typee-pw-rule="len"]');
		var ruleNum = pwGauge.querySelector('[data-typee-pw-rule="num"]');
		var ruleSym = pwGauge.querySelector('[data-typee-pw-rule="sym"]');

		var paintGauge = function () {
			var v = profilePw.value || '';
			var hasLen = v.length >= 8;
			var hasNum = /\d/.test(v);
			var hasSym = /[^A-Za-z0-9]/.test(v);
			var hasCase = /[A-Z]/.test(v) && /[a-z]/.test(v);

			if (ruleLen) { ruleLen.classList.toggle('is-met', hasLen); }
			if (ruleNum) { ruleNum.classList.toggle('is-met', hasNum); }
			if (ruleSym) { ruleSym.classList.toggle('is-met', hasSym); }

			var score = 0;
			if (hasLen) { score++; }
			if (hasNum) { score++; }
			if (hasSym) { score++; }
			if (hasCase) { score++; }

			var tone = '';
			var text = 'Chưa nhập mật khẩu mới';
			if (v.length > 0) {
				if (score <= 1) {
					tone = 'is-weak';
					text = 'Yếu (' + score + '/4 yêu cầu)';
				} else if (score <= 2) {
					tone = 'is-medium';
					text = 'Trung bình (' + score + '/4 yêu cầu)';
				} else if (score === 3) {
					tone = 'is-strong';
					text = 'Mạnh (3/4 yêu cầu)';
				} else {
					tone = 'is-strong';
					text = 'Rất mạnh (4/4 yêu cầu)';
				}
			}

			if (gaugeLabel) {
				gaugeLabel.textContent = text;
				gaugeLabel.classList.remove('is-weak', 'is-medium', 'is-strong');
				if (tone) {
					gaugeLabel.classList.add(tone);
				}
			}

			segs.forEach(function (seg, idx) {
				seg.classList.remove('is-weak', 'is-medium', 'is-strong');
				if (v.length > 0 && idx < Math.max(1, score)) {
					seg.classList.add(tone);
				}
			});
		};

		profilePw.addEventListener('input', paintGauge);
	}

	var twofaCheckbox = document.querySelector('[data-typee-2fa-checkbox]');
	var twofaBadge = document.querySelector('[data-typee-2fa-badge]');
	var twofaLabel = document.querySelector('[data-typee-2fa-label]');
	if (twofaCheckbox) {
		twofaCheckbox.addEventListener('change', function () {
			var on = twofaCheckbox.checked;
			if (twofaBadge) {
				twofaBadge.textContent = on ? 'ĐANG BẬT' : 'ĐANG TẮT';
				twofaBadge.classList.toggle('typee-account-badge--success', on);
				twofaBadge.classList.toggle('typee-account-badge--neutral', !on);
			}
			if (twofaLabel) {
				twofaLabel.textContent = on ? 'Tắt 2FA' : 'Bật 2FA';
			}
		});
	}

	/* ---------- 10. Cấu hình Builder đã lưu (saved-builds.php – Figma 32:2950) ---------- */
	var buildsRoot = document.querySelector('[data-typee-builds-root]');
	if (buildsRoot) {
		var buildsListEl = buildsRoot.querySelector('[data-typee-builds-list]');
		var buildsEmptyEl = buildsRoot.querySelector('[data-typee-builds-empty]');
		var buildsEmptyTitle = buildsRoot.querySelector('[data-typee-builds-empty-title]');
		var buildsEmptyDesc = buildsRoot.querySelector('[data-typee-builds-empty-desc]');
		var buildsTotalEl = buildsRoot.querySelector('[data-typee-builds-total]');
		var buildsToastEl = buildsRoot.querySelector('[data-typee-builds-toast]');
		var buildsSearchEl = buildsRoot.querySelector('[data-typee-builds-search]');
		var buildsFilterBtns = Array.prototype.slice.call(buildsRoot.querySelectorAll('[data-typee-builds-filter]'));

		var buildsState = {
			items: getMergedBuilds(),
			catalog: {},
			filter: 'all',
			query: ''
		};

		var bundleRate = Number(cfg.bundleRate) || 0.05;

		var resolveProduct = function (id) {
			if (!id) {
				return null;
			}
			return buildsState.catalog[String(id)] || null;
		};

		var resolveVariation = function (prod, varId) {
			if (!prod || !varId || !Array.isArray(prod.variations)) {
				return null;
			}
			for (var i = 0; i < prod.variations.length; i++) {
				if (String(prod.variations[i].id) === String(varId)) {
					return prod.variations[i];
				}
			}
			return null;
		};

		var computeBuildMeta = function (b) {
			var st = b.state || {};
			var parts = b.parts || {};
			var kit = resolveProduct(st.kit);
			var sw = resolveProduct(st.sw);
			var kc = resolveProduct(st.keycap);
			var kitVar = resolveVariation(kit, st.kitVar);
			var swVar = resolveVariation(sw, st.swVar);
			var kcVar = resolveVariation(kc, st.keycapVar);

			var hasKit = !!(st.kit || parts.kit);
			var hasSw = !!(st.sw || parts.sw);
			var hasKc = !!(st.keycap || parts.keycap);
			var doneCount = (hasKit ? 1 : 0) + (hasSw ? 1 : 0) + (hasKc ? 1 : 0);
			var isComplete = hasKit && hasSw && hasKc;

			var packs = Math.max(1, parseInt(st.packs, 10) || 1);
			var kitPrice = kitVar ? Number(kitVar.price || 0) : (kit ? Number(kit.price || 0) : 0);
			var swUnitPrice = swVar ? Number(swVar.price || 0) : (sw ? Number(sw.price || 0) : 0);
			var swPrice = swUnitPrice * packs;
			var kcPrice = kcVar ? Number(kcVar.price || 0) : (kc ? Number(kc.price || 0) : 0);

			var liveSubtotal = kitPrice + swPrice + kcPrice;
			var subtotal = liveSubtotal > 0 ? liveSubtotal : (Number(b.subtotal) || Number(b.total) || 0);
			var discount = isComplete ? Math.round(subtotal * bundleRate) : 0;
			var total = liveSubtotal > 0 ? Math.max(0, subtotal - discount) : (Number(b.total) || subtotal);

			var allInStock = true;
			[kit, sw, kc].forEach(function (p) {
				if (p && p.in_stock === false) {
					allInStock = false;
				}
			});

			return {
				kit: kit,
				sw: sw,
				kc: kc,
				kitVar: kitVar,
				swVar: swVar,
				kcVar: kcVar,
				hasKit: hasKit,
				hasSw: hasSw,
				hasKc: hasKc,
				doneCount: doneCount,
				isComplete: isComplete,
				packs: packs,
				kitPrice: kitPrice,
				swPrice: swPrice,
				kcPrice: kcPrice,
				subtotal: subtotal,
				discount: discount,
				total: total,
				allInStock: allInStock,
				img: (kitVar && kitVar.img) || (kit && kit.img) || (kc && kc.img) || (sw && sw.img) || '',
				layout: b.layout || (kit && kit.layout) || 'Custom',
				mount: (kit && kit.mount) || ''
			};
		};

		var persistBuilds = function () {
			writeStore('typee_builds', buildsState.items);
			updateSidebarBuildsBadge(buildsState.items.length);
			if (cfg.isLoggedIn) {
				postAjax('typee_account_sync_builds', { builds: buildsState.items });
			}
		};

		var renderBuilds = function () {
			var allItems = buildsState.items;
			var countAll = allItems.length;
			var countComplete = 0;
			var countDraft = 0;

			allItems.forEach(function (b) {
				if (computeBuildMeta(b).isComplete) {
					countComplete += 1;
				} else {
					countDraft += 1;
				}
			});

			if (buildsTotalEl) {
				buildsTotalEl.textContent = countAll + ' cấu hình';
			}
			var cAll = buildsRoot.querySelector('[data-typee-builds-count="all"]');
			var cComp = buildsRoot.querySelector('[data-typee-builds-count="complete"]');
			var cDraft = buildsRoot.querySelector('[data-typee-builds-count="draft"]');
			if (cAll) { cAll.textContent = String(countAll); }
			if (cComp) { cComp.textContent = String(countComplete); }
			if (cDraft) { cDraft.textContent = String(countDraft); }

			var q = (buildsState.query || '').trim().toLowerCase();
			var visible = allItems.filter(function (b) {
				var m = computeBuildMeta(b);
				if (buildsState.filter === 'complete' && !m.isComplete) {
					return false;
				}
				if (buildsState.filter === 'draft' && m.isComplete) {
					return false;
				}
				if (q) {
					var hay = [
						b.id || '',
						b.name || '',
						b.note || '',
						b.layout || '',
						(b.parts && b.parts.kit) || '',
						(b.parts && b.parts.sw) || '',
						(b.parts && b.parts.keycap) || '',
						(m.kit && m.kit.name) || '',
						(m.sw && m.sw.name) || '',
						(m.kc && m.kc.name) || ''
					].join(' ').toLowerCase();
					if (hay.indexOf(q) === -1) {
						return false;
					}
				}
				return true;
			});

			if (!visible.length) {
				if (buildsListEl) {
					buildsListEl.innerHTML = '';
					buildsListEl.hidden = true;
				}
				if (buildsEmptyEl) {
					buildsEmptyEl.hidden = false;
					if (countAll > 0) {
						if (buildsEmptyTitle) { buildsEmptyTitle.textContent = 'Không tìm thấy cấu hình phù hợp'; }
						if (buildsEmptyDesc) { buildsEmptyDesc.textContent = 'Thử xóa từ khóa tìm kiếm hoặc chuyển sang bộ lọc "Tất cả" để xem lại toàn bộ cấu hình đã lưu.'; }
					} else {
						if (buildsEmptyTitle) { buildsEmptyTitle.textContent = 'Chưa có cấu hình Custom Builder nào được lưu'; }
						if (buildsEmptyDesc) { buildsEmptyDesc.textContent = 'Hãy tự tay phối Kit CNC, Switch và Keycap theo đúng gu âm thanh của bạn trong Custom Keyboard Builder để tự động nhận ưu đãi giảm 5% trọn bộ.'; }
					}
				}
				return;
			}

			if (buildsEmptyEl) { buildsEmptyEl.hidden = true; }
			if (!buildsListEl) { return; }
			buildsListEl.hidden = false;

			var builderBase = cfg.builderUrl || '/custom-builder/';
			var sep = builderBase.indexOf('?') === -1 ? '?' : '&';

			buildsListEl.innerHTML = visible.map(function (b) {
				var m = computeBuildMeta(b);
				var editUrl = builderBase + sep + 'build_id=' + encodeURIComponent(b.id);
				var pct = Math.round((m.doneCount / 3) * 100);
				var kitName = (m.kit && m.kit.name) || (b.parts && b.parts.kit) || '';
				var swName = (m.sw && m.sw.name) || (b.parts && b.parts.sw) || '';
				var kcName = (m.kc && m.kc.name) || (b.parts && b.parts.keycap) || '';
				var kitSub = (m.kitVar && m.kitVar.label) || (m.kit && m.kit.mount) || (m.layout || 'Barebone Kit');
				var swSub = ((m.swVar && m.swVar.label) ? m.swVar.label + ' • ' : '') + m.packs + ' pack';
				var kcSub = (m.kcVar && m.kcVar.label) || (m.kc && m.kc.profile) || 'Bộ Keycap';

				var statusBadge = m.isComplete
					? '<span class="typee-account-badge typee-account-badge--success">Hoàn chỉnh sẵn sàng</span>'
					: '<span class="typee-account-badge typee-account-badge--warning">Bản nháp đang build (' + m.doneCount + '/3 bước)</span>';

				var compatBadge = m.isComplete
					? '<span class="typee-account-badge typee-account-badge--neutral">Tương thích 100% • Bundle -5%</span>'
					: '<span class="typee-account-badge typee-account-badge--neutral">Còn ' + (3 - m.doneCount) + ' bước để nhận ưu đãi -5%</span>';

				var thumbHtml = m.img
					? '<img class="typee-account-build-card__img" src="' + escHtml(m.img) + '" alt="' + escHtml(b.name || 'Custom Build') + '" loading="lazy">'
					: '<div class="typee-account-build-card__placeholder"><span>TYPEE STUDIO</span><span>' + escHtml(m.layout) + '</span></div>';

				var step1 = m.hasKit
					? '<div class="typee-account-build-step"><span class="typee-account-build-step__num">1</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">KIT BAREBONE</span><span class="typee-account-build-step__name">' + escHtml(kitName) + '</span><div class="typee-account-build-step__bottom"><span class="typee-account-build-step__meta">' + escHtml(kitSub) + '</span>' + (m.kitPrice > 0 ? '<span class="typee-account-build-step__price ty-mono">' + fmtVnd(m.kitPrice) + '</span>' : '') + '</div></div></div>'
					: '<div class="typee-account-build-step typee-account-build-step--pending"><span class="typee-account-build-step__num">1</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">CHƯA CHỌN KIT</span><span class="typee-account-build-step__name">Đang chờ chọn khung Kit...</span><div class="typee-account-build-step__bottom"><a class="typee-account-text-link" href="' + escHtml(editUrl) + '">+ Chọn Kit ngay</a></div></div></div>';

				var step2 = m.hasSw
					? '<div class="typee-account-build-step"><span class="typee-account-build-step__num">2</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">SWITCH CƠ HỌC</span><span class="typee-account-build-step__name">' + escHtml(swName) + '</span><div class="typee-account-build-step__bottom"><span class="typee-account-build-step__meta">' + escHtml(swSub) + '</span>' + (m.swPrice > 0 ? '<span class="typee-account-build-step__price ty-mono">' + fmtVnd(m.swPrice) + '</span>' : '') + '</div></div></div>'
					: '<div class="typee-account-build-step typee-account-build-step--pending"><span class="typee-account-build-step__num">2</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">CHƯA CHỌN SWITCH</span><span class="typee-account-build-step__name">Chọn Linear, Tactile hoặc Silent...</span><div class="typee-account-build-step__bottom"><a class="typee-account-text-link" href="' + escHtml(editUrl) + '">+ Chọn Switch ngay</a></div></div></div>';

				var step3 = m.hasKc
					? '<div class="typee-account-build-step"><span class="typee-account-build-step__num">3</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">BỘ KEYCAP</span><span class="typee-account-build-step__name">' + escHtml(kcName) + '</span><div class="typee-account-build-step__bottom"><span class="typee-account-build-step__meta">' + escHtml(kcSub) + '</span>' + (m.kcPrice > 0 ? '<span class="typee-account-build-step__price ty-mono">' + fmtVnd(m.kcPrice) + '</span>' : '') + '</div></div></div>'
					: '<div class="typee-account-build-step typee-account-build-step--pending"><span class="typee-account-build-step__num">3</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">CHƯA CHỌN KEYCAP</span><span class="typee-account-build-step__name">Chọn profile Cherry, MOA hoặc SA...</span><div class="typee-account-build-step__bottom"><a class="typee-account-text-link" href="' + escHtml(editUrl) + '">+ Chọn Keycap ngay</a></div></div></div>';

				var step4 = m.isComplete
					? '<div class="typee-account-build-step"><span class="typee-account-build-step__num">✓</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">ƯU ĐÃI CUSTOM BUNDLE</span><span class="typee-account-build-step__name">Giảm trực tiếp 5% trọn bộ</span><div class="typee-account-build-step__bottom"><span class="typee-account-build-step__meta">Miễn phí kiểm định KCS</span>' + (m.discount > 0 ? '<span class="typee-account-build-step__price ty-mono">-' + fmtVnd(m.discount) + '</span>' : '') + '</div></div></div>'
					: '<div class="typee-account-build-step typee-account-build-step--pending"><span class="typee-account-build-step__num">%</span><div class="typee-account-build-step__info"><span class="typee-account-build-step__kicker">ƯU ĐÃI CUSTOM BUNDLE</span><span class="typee-account-build-step__name">Hoàn tất đủ 3 nhóm linh kiện</span><div class="typee-account-build-step__bottom"><span class="typee-account-build-step__meta">Tự động giảm 5% khi thêm giỏ</span></div></div></div>';

				var primaryAction = m.isComplete
					? '<button type="button" class="ty-btn ty-btn--primary" data-typee-build-atc="' + escHtml(b.id) + '">' + (m.allInStock ? 'Thêm trọn bộ vào giỏ' : 'Thêm vào giỏ (kiểm tra kho)') + '</button>'
					: '<a class="ty-btn ty-btn--primary" href="' + escHtml(editUrl) + '">Tiếp tục build trong Studio</a>';

				return (
					'<article class="typee-account-build-card' + (m.isComplete ? '' : ' typee-account-build-card--draft') + '">' +
						'<div class="typee-account-build-card__head">' +
							'<div class="typee-account-build-card__badges">' + statusBadge + compatBadge + '</div>' +
							'<span class="typee-account-build-card__date ty-mono">Lưu ngày ' + escHtml(fmtDateVi(b.updated || b.created)) + '</span>' +
						'</div>' +
						'<div class="typee-account-build-card__body">' +
							'<div class="typee-account-build-card__preview">' +
								'<div class="typee-account-build-card__thumb">' +
									thumbHtml +
									'<span class="typee-account-build-card__tag">' + escHtml(m.layout) + '</span>' +
									(m.mount ? '<span class="typee-account-build-card__mount">' + escHtml(m.mount) + '</span>' : '') +
								'</div>' +
								'<div class="typee-account-build-card__progress">' +
									'<div class="typee-account-build-card__progress-top"><span>Tiến độ linh kiện</span><span class="ty-mono">' + m.doneCount + '/3</span></div>' +
									'<div class="typee-account-build-card__bar"><span class="typee-account-build-card__fill" style="width:' + pct + '%;"></span></div>' +
								'</div>' +
								(b.note ? '<p class="typee-account-build-card__note">Ghi chú: ' + escHtml(b.note) + '</p>' : '') +
							'</div>' +
							'<div class="typee-account-build-card__bom">' +
								'<div>' +
									'<span class="typee-account-build-card__code ty-mono">CẤU HÌNH #' + escHtml(b.id) + '</span>' +
									'<h3 class="typee-account-build-card__title">' + escHtml(b.name || 'Bàn phím Custom TYPEE') + '</h3>' +
								'</div>' +
								'<div class="typee-account-build-card__steps">' + step1 + step2 + step3 + step4 + '</div>' +
							'</div>' +
						'</div>' +
						'<div class="typee-account-build-card__foot">' +
							'<div class="typee-account-build-card__summary">' +
								'<div class="typee-account-build-card__stock' + (m.allInStock ? '' : ' is-warn') + '">' +
									'<span>' + (m.isComplete ? (m.allInStock ? '● Kho TYPEE: Sẵn sàng lắp ráp' : '● Một số linh kiện cần kiểm tra tồn kho') : '● Tạm tính cho ' + m.doneCount + '/3 linh kiện đã chọn') + '</span>' +
									(m.discount > 0 ? '<span class="typee-account-build-card__save">Giảm ' + fmtVnd(m.discount) + ' (-5%)</span>' : '') +
								'</div>' +
								'<div class="typee-account-build-card__totals">' +
									'<span class="typee-account-build-card__total-label">Tổng chi phí dự kiến:</span>' +
									'<span class="typee-account-build-card__total ty-mono">' + fmtVnd(m.total) + '</span>' +
									(m.discount > 0 && m.subtotal > m.total ? '<span class="typee-account-build-card__strike ty-mono">' + fmtVnd(m.subtotal) + '</span>' : '') +
								'</div>' +
							'</div>' +
							'<div class="typee-account-build-card__actions">' +
								'<button type="button" class="typee-account-build-btn" data-typee-build-share="' + escHtml(b.id) + '">Chia sẻ</button>' +
								'<a class="typee-account-build-btn" href="' + escHtml(editUrl) + '">Mở lại Builder</a>' +
								'<button type="button" class="typee-account-build-btn typee-account-build-btn--danger" data-typee-build-delete="' + escHtml(b.id) + '" aria-label="Xóa cấu hình">Xóa</button>' +
								primaryAction +
							'</div>' +
						'</div>' +
					'</article>'
				);
			}).join('');
		};

		buildsFilterBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				buildsState.filter = btn.getAttribute('data-typee-builds-filter') || 'all';
				buildsFilterBtns.forEach(function (b) {
					var active = b === btn;
					b.classList.toggle('is-active', active);
					b.setAttribute('aria-selected', active ? 'true' : 'false');
				});
				renderBuilds();
			});
		});

		if (buildsSearchEl) {
			buildsSearchEl.addEventListener('input', function () {
				buildsState.query = buildsSearchEl.value || '';
				renderBuilds();
			});
		}

		if (buildsListEl) {
			buildsListEl.addEventListener('click', function (e) {
				var delBtn = e.target.closest('[data-typee-build-delete]');
				if (delBtn) {
					var delId = delBtn.getAttribute('data-typee-build-delete');
					if (!window.confirm('Bạn chắc chắn muốn xóa cấu hình này khỏi tài khoản?')) {
						return;
					}
					buildsState.items = buildsState.items.filter(function (item) {
						return String(item.id) !== String(delId);
					});
					persistBuilds();
					renderBuilds();
					showInlineToast(buildsToastEl, 'Đã xóa cấu hình khỏi danh sách đã lưu.', 'info');
					return;
				}

				var shareBtn = e.target.closest('[data-typee-build-share]');
				if (shareBtn) {
					var shareId = shareBtn.getAttribute('data-typee-build-share');
					var target = buildsState.items.find(function (item) {
						return String(item.id) === String(shareId);
					});
					if (target) {
						var base = cfg.builderUrl || '/custom-builder/';
						var sep = base.indexOf('?') === -1 ? '?' : '&';
						var link = base + sep + 'build_id=' + encodeURIComponent(target.id);
						try {
							if (target.state) {
								link = base + sep + 'b=' + encodeURIComponent(window.btoa(JSON.stringify(target.state)));
							}
						} catch (err) {
							/* giữ fallback build_id */
						}
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(link).then(function () {
								showInlineToast(buildsToastEl, 'Đã sao chép liên kết chia sẻ cấu hình "' + (target.name || target.id) + '"!', 'success');
							}).catch(function () {
								showInlineToast(buildsToastEl, 'Liên kết chia sẻ: ' + link, 'info');
							});
						} else {
							showInlineToast(buildsToastEl, 'Liên kết chia sẻ: ' + link, 'info');
						}
					}
					return;
				}

				var atcBtn = e.target.closest('[data-typee-build-atc]');
				if (atcBtn) {
					var atcId = atcBtn.getAttribute('data-typee-build-atc');
					var build = buildsState.items.find(function (item) {
						return String(item.id) === String(atcId);
					});
					if (!build || !build.state) {
						return;
					}
					var st = build.state;
					var swProd = resolveProduct(st.sw);
					var perPack = (swProd && swProd.switches_per_pack) ? swProd.switches_per_pack : 35;
					var packs = Math.max(1, parseInt(st.packs, 10) || 1);
					var shared = {
						is_typee_builder: 1,
						kit_id: st.kit || '',
						switch_id: st.sw || '',
						keycap_id: st.keycap || '',
						switch_pack_qty: packs,
						switch_variation_id: st.swVar || '',
						switches_per_pack: perPack
					};
					var queue = [];
					if (st.kit) {
						queue.push(Object.assign({ product_id: st.kitVar || st.kit, quantity: 1 }, shared));
					}
					if (st.sw) {
						queue.push(Object.assign({ product_id: st.swVar || st.sw, quantity: packs }, shared));
					}
					if (st.keycap) {
						queue.push(Object.assign({ product_id: st.keycapVar || st.keycap, quantity: 1 }, shared));
					}
					if (!queue.length) {
						return;
					}

					var origLabel = atcBtn.textContent;
					atcBtn.disabled = true;
					atcBtn.textContent = 'Đang thêm trọn bộ...';

					var lastResp = null;
					var failed = false;
					queue.reduce(function (promise, item) {
						return promise.then(function () {
							return addToWcCart(item).then(function (resp) {
								lastResp = resp;
								if (resp && resp.error) {
									failed = true;
								}
							});
						});
					}, Promise.resolve()).then(function () {
						atcBtn.disabled = false;
						atcBtn.textContent = origLabel;
						triggerCartRefresh(lastResp);
						if (failed) {
							showInlineToast(buildsToastEl, 'Một số linh kiện trong cấu hình cần chọn phiên bản hoặc đã hết hàng. Vui lòng mở lại trong Custom Builder để kiểm tra.', 'error');
						} else {
							showInlineToast(buildsToastEl, 'Đã thêm trọn bộ "' + (build.name || build.id) + '" vào giỏ hàng (áp dụng ưu đãi -5% Custom Bundle)!', 'success');
						}
					});
				}
			});
		}

		// Đồng bộ localStorage + tải bảng giá live từ WooCommerce.
		var neededIds = [];
		buildsState.items.forEach(function (b) {
			if (b && b.state) {
				[b.state.kit, b.state.sw, b.state.keycap].forEach(function (id) {
					if (id && neededIds.indexOf(id) === -1) {
						neededIds.push(id);
					}
				});
			}
		});

		if (buildsState.items.length > 0) {
			persistBuilds();
			postAjax('typee_account_builder_catalog', { ids: neededIds }).then(function (res) {
				if (res && res.success && res.data && res.data.by_id) {
					buildsState.catalog = res.data.by_id;
				}
				renderBuilds();
			});
		} else {
			renderBuilds();
		}
	}

	/* ---------- 11. Danh sách sản phẩm yêu thích (wishlist.php – Figma 82:7799) ---------- */
	var wlRoot = document.querySelector('[data-typee-wishlist-root]');
	if (wlRoot) {
		var wlGridEl = wlRoot.querySelector('[data-typee-wl-grid]');
		var wlEmptyEl = wlRoot.querySelector('[data-typee-wl-empty]');
		var wlTotalEl = wlRoot.querySelector('[data-typee-wl-total]');
		var wlToastEl = wlRoot.querySelector('[data-typee-wl-toast]');
		var wlSortEl = wlRoot.querySelector('[data-typee-wl-sort]');
		var wlClearBtn = wlRoot.querySelector('[data-typee-wl-clear-all]');
		var wlAddAllBtn = wlRoot.querySelector('[data-typee-wl-add-all]');

		var wlState = {
			ids: getMergedWishlistIds(),
			items: [],
			sort: 'recent'
		};

		var getCompareIds = function () {
			var arr = readStore('typee_compare', []);
			return Array.isArray(arr) ? arr.map(String) : [];
		};

		var persistWishlist = function () {
			writeStore('typee_wishlist', wlState.ids);
			updateSidebarWishlistBadge(wlState.ids.length);
			if (cfg.isLoggedIn) {
				postAjax('typee_account_sync_wishlist', { ids: wlState.ids });
			}
		};

		var getSortedWishlist = function () {
			var arr = wlState.items.slice();
			if (wlState.sort === 'price_asc') {
				arr.sort(function (a, b) { return (a.price || 0) - (b.price || 0); });
			} else if (wlState.sort === 'price_desc') {
				arr.sort(function (a, b) { return (b.price || 0) - (a.price || 0); });
			} else if (wlState.sort === 'in_stock') {
				arr.sort(function (a, b) { return (b.in_stock ? 1 : 0) - (a.in_stock ? 1 : 0); });
			}
			return arr;
		};

		var renderWishlist = function () {
			var items = getSortedWishlist();
			var count = items.length;
			var compareIds = getCompareIds();

			if (wlTotalEl) {
				wlTotalEl.textContent = count + ' sản phẩm';
			}
			if (wlClearBtn) {
				wlClearBtn.disabled = count === 0;
			}
			if (wlAddAllBtn) {
				var addable = items.filter(function (p) { return p.in_stock && !p.is_variable && p.purchasable; });
				wlAddAllBtn.disabled = addable.length === 0;
			}

			if (count === 0) {
				if (wlGridEl) {
					wlGridEl.innerHTML = '';
					wlGridEl.hidden = true;
				}
				if (wlEmptyEl) {
					wlEmptyEl.hidden = false;
				}
				return;
			}

			if (wlEmptyEl) { wlEmptyEl.hidden = true; }
			if (!wlGridEl) { return; }
			wlGridEl.hidden = false;

			wlGridEl.innerHTML = items.map(function (p) {
				var stockCls = !p.in_stock
					? 'typee-account-wl-card__stock--out'
					: (p.stock_qty !== null && p.stock_qty <= 5 ? 'typee-account-wl-card__stock--low' : 'typee-account-wl-card__stock--in');
				var stockText = !p.in_stock
					? 'Tạm hết hàng'
					: (p.stock_qty !== null && p.stock_qty <= 5 ? 'Chỉ còn ' + p.stock_qty : 'Sẵn hàng');

				var specsHtml = Array.isArray(p.specs) && p.specs.length
					? '<div class="typee-account-wl-card__specs">' + p.specs.map(function (s) {
						return '<span class="typee-account-wl-card__chip">' + escHtml(s) + '</span>';
					}).join('') + '</div>'
					: '';

				var ratingHtml = p.rating > 0
					? '<div class="typee-account-wl-card__rating"><span class="typee-account-wl-card__star">★</span><strong>' + escHtml(p.rating.toFixed(1)) + '</strong><span>(' + escHtml(p.review_count) + ')</span></div>'
					: '<div class="typee-account-wl-card__rating"><span class="typee-account-wl-card__star">★</span><span>Chính hãng TYPEE</span></div>';

				var ctaHtml = '';
				if (!p.in_stock) {
					ctaHtml = '<a class="ty-btn ty-btn--soft typee-account-wl-card__atc" href="' + escHtml(p.url) + '">Xem chi tiết</a>';
				} else if (p.is_variable) {
					ctaHtml = '<a class="ty-btn ty-btn--primary typee-account-wl-card__atc" href="' + escHtml(p.url) + '">Chọn phiên bản</a>';
				} else {
					ctaHtml = '<button type="button" class="ty-btn ty-btn--primary typee-account-wl-card__atc" data-typee-wl-atc="' + escHtml(p.id) + '">Thêm vào giỏ</button>';
				}

				var cmpActive = compareIds.indexOf(String(p.id)) !== -1;

				return (
					'<article class="typee-account-wl-card" data-product-id="' + escHtml(p.id) + '">' +
						'<div class="typee-account-wl-card__media">' +
							'<a class="typee-account-wl-card__img-link" href="' + escHtml(p.url) + '">' +
								'<img class="typee-account-wl-card__img" src="' + escHtml(p.img) + '" alt="' + escHtml(p.name) + '" loading="lazy">' +
							'</a>' +
							'<div class="typee-account-wl-card__badges">' +
								'<span class="typee-account-wl-card__cat">' + escHtml(p.category) + '</span>' +
								(p.sale_pct > 0 ? '<span class="typee-account-wl-card__sale">-' + escHtml(p.sale_pct) + '%</span>' : '') +
							'</div>' +
							'<button type="button" class="typee-account-wl-card__remove" data-typee-wl-remove="' + escHtml(p.id) + '" aria-label="Xóa khỏi yêu thích" title="Xóa khỏi yêu thích">✕</button>' +
							'<span class="typee-account-wl-card__stock ' + stockCls + '"><span class="typee-account-wl-card__stock-dot" aria-hidden="true"></span><span>' + escHtml(stockText) + '</span></span>' +
						'</div>' +
						'<div class="typee-account-wl-card__body">' +
							ratingHtml +
							'<h2 class="typee-account-wl-card__name"><a href="' + escHtml(p.url) + '">' + escHtml(p.name) + '</a></h2>' +
							specsHtml +
							'<div class="typee-account-wl-card__price-box">' +
								'<div class="typee-account-wl-card__price-row">' +
									'<span class="typee-account-wl-card__price ty-mono">' + escHtml(p.price_fmt || fmtVnd(p.price)) + '</span>' +
									(p.regular_fmt ? '<span class="typee-account-wl-card__regular ty-mono">' + escHtml(p.regular_fmt) + '</span>' : '') +
								'</div>' +
								(p.save_fmt ? '<span class="typee-account-wl-card__save">Tiết kiệm ' + escHtml(p.save_fmt) + '</span>' : '') +
							'</div>' +
							'<div class="typee-account-wl-card__foot">' +
								ctaHtml +
								'<button type="button" class="typee-account-wl-card__cmp' + (cmpActive ? ' is-active' : '') + '" data-typee-wl-compare="' + escHtml(p.id) + '" title="' + (cmpActive ? 'Đã thêm vào so sánh' : 'Thêm vào so sánh') + '" aria-label="So sánh sản phẩm">⇄</button>' +
							'</div>' +
						'</div>' +
					'</article>'
				);
			}).join('');
		};

		if (wlSortEl) {
			wlSortEl.addEventListener('change', function () {
				wlState.sort = wlSortEl.value || 'recent';
				renderWishlist();
			});
		}

		if (wlClearBtn) {
			wlClearBtn.addEventListener('click', function () {
				if (!wlState.items.length) {
					return;
				}
				if (!window.confirm('Bạn chắc chắn muốn xóa toàn bộ sản phẩm khỏi danh sách yêu thích?')) {
					return;
				}
				wlState.ids = [];
				wlState.items = [];
				persistWishlist();
				renderWishlist();
				showInlineToast(wlToastEl, 'Đã xóa toàn bộ sản phẩm yêu thích.', 'info');
			});
		}

		if (wlAddAllBtn) {
			wlAddAllBtn.addEventListener('click', function () {
				var addable = wlState.items.filter(function (p) { return p.in_stock && !p.is_variable && p.purchasable; });
				if (!addable.length) {
					return;
				}
				var origText = wlAddAllBtn.textContent;
				wlAddAllBtn.disabled = true;
				wlAddAllBtn.textContent = 'Đang thêm vào giỏ...';

				var added = 0;
				var lastResp = null;
				addable.reduce(function (promise, p) {
					return promise.then(function () {
						return addToWcCart({ product_id: p.id, quantity: 1 }).then(function (resp) {
							lastResp = resp;
							if (!resp || !resp.error) {
								added += 1;
							}
						});
					});
				}, Promise.resolve()).then(function () {
					wlAddAllBtn.disabled = false;
					wlAddAllBtn.textContent = origText;
					triggerCartRefresh(lastResp);
					var skipped = wlState.items.length - added;
					var msg = 'Đã thêm ' + added + ' sản phẩm vào giỏ hàng!';
					if (skipped > 0) {
						msg += ' (' + skipped + ' sản phẩm cần chọn phiên bản hoặc tạm hết hàng)';
					}
					showInlineToast(wlToastEl, msg, 'success');
				});
			});
		}

		if (wlGridEl) {
			wlGridEl.addEventListener('click', function (e) {
				var rmBtn = e.target.closest('[data-typee-wl-remove]');
				if (rmBtn) {
					var rmId = String(rmBtn.getAttribute('data-typee-wl-remove'));
					wlState.ids = wlState.ids.filter(function (id) { return String(id) !== rmId; });
					wlState.items = wlState.items.filter(function (p) { return String(p.id) !== rmId; });
					persistWishlist();
					renderWishlist();
					showInlineToast(wlToastEl, 'Đã xóa sản phẩm khỏi danh sách yêu thích.', 'info');
					return;
				}

				var atcBtn = e.target.closest('[data-typee-wl-atc]');
				if (atcBtn) {
					var pid = atcBtn.getAttribute('data-typee-wl-atc');
					var orig = atcBtn.textContent;
					atcBtn.disabled = true;
					atcBtn.textContent = 'Đang thêm...';
					addToWcCart({ product_id: pid, quantity: 1 }).then(function (resp) {
						atcBtn.disabled = false;
						atcBtn.textContent = orig;
						if (resp && resp.error) {
							showInlineToast(wlToastEl, 'Không thể thêm sản phẩm này vào giỏ hàng.', 'error');
						} else {
							triggerCartRefresh(resp);
							showInlineToast(wlToastEl, 'Đã thêm sản phẩm vào giỏ hàng!', 'success');
						}
					});
					return;
				}

				var cmpBtn = e.target.closest('[data-typee-wl-compare]');
				if (cmpBtn) {
					var cid = String(cmpBtn.getAttribute('data-typee-wl-compare'));
					var cur = getCompareIds();
					var idx = cur.indexOf(cid);
					if (idx !== -1) {
						cur.splice(idx, 1);
						writeStore('typee_compare', cur);
						cmpBtn.classList.remove('is-active');
						showInlineToast(wlToastEl, 'Đã gỡ sản phẩm khỏi danh sách so sánh.', 'info');
					} else {
						if (cur.length >= 4) {
							showInlineToast(wlToastEl, 'Chỉ so sánh tối đa 4 sản phẩm cùng lúc.', 'error');
							return;
						}
						cur.push(cid);
						writeStore('typee_compare', cur);
						cmpBtn.classList.add('is-active');
						showInlineToast(wlToastEl, 'Đã thêm vào bảng so sánh (' + cur.length + '/4).', 'success');
					}
				}
			});
		}

		// Tải thông tin sản phẩm thực từ WooCommerce.
		if (wlState.ids.length > 0) {
			postAjax('typee_account_wishlist_products', { ids: wlState.ids, sync: 1 }).then(function (res) {
				if (res && res.success && res.data && Array.isArray(res.data.items)) {
					wlState.items = res.data.items;
					wlState.ids = wlState.items.map(function (p) { return String(p.id); });
					writeStore('typee_wishlist', wlState.ids);
					updateSidebarWishlistBadge(wlState.ids.length);
				}
				renderWishlist();
			});
		} else {
			renderWishlist();
		}
	}
})();
