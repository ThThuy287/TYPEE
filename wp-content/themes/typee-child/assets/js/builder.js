/**
 * TYPEE Custom Builder (PRD FR-04/05/06; Figma 1:852 & 32:2321).
 * - Dữ liệu linh kiện: JSON do PHP sinh từ WooCommerce (#typee-builder-data)
 * - Tương thích: kiểm tra tức thì phía client + xác nhận bằng API typee-core /typee/v1/validate
 * - Bản build: lưu localStorage (typee_builds), bản nháp (typee_builder_draft), link chia sẻ ?b=
 * - Đặt hàng: thêm từng linh kiện qua ?wc-ajax=add_to_cart kèm tham số is_typee_builder để typee-core kiểm tra
 */
(function ($) {
	'use strict';
	var root = document.querySelector('[data-typee-builder]');
	if (!root) { return; }
	var CFG = JSON.parse(document.getElementById('typee-builder-data').textContent);
	var CAT = CFG.catalog;
	var store = window.TYPEE_STORE || { get: function () { return []; }, set: function () {} };
	var $q = function (sel) { return root.querySelector(sel); };
	var PAGE = 12;

	var STEPS = [
		{ key: 'kit', title: 'Chọn Khung Kit', desc: 'Khung Kit quyết định layout, mạch PCB và kiểu mount. Các bước sau sẽ được kiểm tra tương thích theo Kit bạn chọn.' },
		{ key: 'plate', title: 'Plate định vị', desc: 'Plate giữ switch và ảnh hưởng độ nảy khi gõ. Plate đi theo phiên bản Kit trong kho hiện tại.' },
		{ key: 'switch', title: 'Chọn Switch cơ học', desc: 'Switch không khớp chân với mạch của Kit sẽ bị khóa. Số gói được tự tính theo layout.' },
		{ key: 'keycap', title: 'Chọn bộ Keycap', desc: 'Hệ thống cảnh báo nếu bộ keycap có thể thiếu phím cho layout của Kit.' },
		{ key: 'mod', title: 'Dịch vụ Mod & Lube', desc: 'Gói cân wire stab, lube switch, tape mod.' },
		{ key: 'summary', title: 'Tổng kết & Đặt hàng', desc: 'Kiểm tra lại cấu hình, lưu bản build hoặc đặt trọn bộ combo.' }
	];

	var state = { step: 1, kit: null, kitVar: null, sw: null, swVar: null, packs: 0, keycap: null, keycapVar: null, buildId: '' };
	var ui = { filter: {}, search: '', shown: PAGE, loaded: null };
	var validation = { status: 'idle', errors: [], warnings: [], details: null };

	/* ---------- Tiện ích ---------- */
	function money(n) { return Math.round(n || 0).toLocaleString('vi-VN') + CFG.currency; }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function byId(list, id) { id = +id; for (var i = 0; i < list.length; i++) { if (list[i].id === id) { return list[i]; } } return null; }
	function varOf(item, vid) { return item && vid ? byId(item.vars, vid) : null; }
	function priceOf(item, vid) { var v = varOf(item, vid); return v ? v.price : (item ? item.price : 0); }
	function kitItem() { return byId(CAT.kit, state.kit); }
	function swItem() { return byId(CAT.switch, state.sw); }
	function kcItem() { return byId(CAT.keycap, state.keycap); }
	function kitLayout() { var k = kitItem(); return k ? (k.specs.layout || '') : ''; }
	function minSwitches() { var l = kitLayout(); return l ? (CFG.minSwitches[l] || CFG.minSwitches._default) : 0; }
	function packSize() { var v = varOf(swItem(), state.swVar); return v && v.pack ? v.pack : 35; }
	function has(str, needle) { return String(str || '').toLowerCase().indexOf(needle) > -1; }
	function newId() { return 'TYP-' + Date.now().toString(36).toUpperCase().slice(-5) + Math.random().toString(36).slice(2, 4).toUpperCase(); }

	/* ---------- Tương thích phía client (đồng bộ với TYPEE_Rule_Engine) ---------- */
	function switchBlocked(sw) {
		var k = kitItem();
		if (!k || !sw) { return ''; }
		var kit3 = has(k.specs.pcb, '3-pin'), pin = sw.specs.pin || '';
		if (kit3 && has(pin, '5-pin') && !has(pin, '3-pin')) { return 'Mạch Kit 3-pin không cắm vừa switch 5-pin'; }
		return '';
	}
	function keycapWarning(kc) {
		var l = kitLayout();
		if (!kc || !l) { return ''; }
		var hard = ['65%', '75%', '96%', '98%'], sl = kc.specs.supported_layout || '';
		if (hard.indexOf(l) > -1 && has(sl, '104') && !has(sl, '130') && !has(sl, 'full')) { return 'Có thể thiếu Shift 1.75u / Alt-Fn 1u cho layout ' + l; }
		return '';
	}

	/* ---------- Danh sách theo bước ---------- */
	function listFor(stepKey) {
		var list = stepKey === 'kit' ? CAT.kit : stepKey === 'switch' ? CAT.switch : stepKey === 'keycap' ? CAT.keycap : [];
		var f = ui.filter[stepKey] || {}, q = ui.search.trim().toLowerCase();
		return list.filter(function (it) {
			for (var k in f) { if (f[k] && (it.specs[k] || '').indexOf(f[k]) === -1) { return false; } }
			if (q && (it.name + ' ' + it.sku).toLowerCase().indexOf(q) === -1) { return false; }
			return true;
		});
	}
	var FILTER_KEYS = { kit: [['layout', 'Layout'], ['mount', 'Mounting']], switch: [['switch_type', 'Dạng switch'], ['pin', 'Số chân']], keycap: [['profile', 'Profile'], ['material', 'Chất liệu']] };

	function renderFilters(stepKey) {
		var box = $q('[data-b-filters]'), keys = FILTER_KEYS[stepKey];
		if (!keys) { box.innerHTML = ''; return; }
		var list = stepKey === 'kit' ? CAT.kit : stepKey === 'switch' ? CAT.switch : CAT.keycap;
		var f = ui.filter[stepKey] || {};
		box.innerHTML = keys.map(function (k) {
			var counts = {};
			list.forEach(function (it) { var v = it.specs[k[0]]; if (v) { counts[v] = (counts[v] || 0) + 1; } });
			var vals = Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; }).slice(0, 8);
			if (!vals.length) { return ''; }
			return '<div class="ty-bfilter"><span class="ty-bfilter__label">' + esc(k[1]) + '</span><div class="ty-pills">' +
				'<button type="button" class="ty-pill" data-f="' + k[0] + '" data-v="" aria-pressed="' + (!f[k[0]]) + '">Tất cả</button>' +
				vals.map(function (v) { return '<button type="button" class="ty-pill" data-f="' + k[0] + '" data-v="' + esc(v) + '" aria-pressed="' + (f[k[0]] === v) + '">' + esc(v) + ' <small>' + counts[v] + '</small></button>'; }).join('') +
				'</div></div>';
		}).join('');
	}

	function itemCard(stepKey, it) {
		var selected = (stepKey === 'kit' && state.kit === it.id) || (stepKey === 'switch' && state.sw === it.id) || (stepKey === 'keycap' && state.keycap === it.id);
		var blocked = stepKey === 'switch' ? switchBlocked(it) : '';
		var warn = stepKey === 'keycap' ? keycapWarning(it) : '';
		var chips = Object.keys(it.specs).slice(0, 4).map(function (k) { return '<li>' + esc(it.specs[k]) + '</li>'; }).join('');
		var price = it.vars.length > 1 ? 'Từ ' + money(it.price) : money(it.price);
		if (stepKey === 'switch' && it.vars.length) { price = 'Từ ' + money(it.price) + ' <small>/ gói</small>'; }
		var extra = '';
		if (selected && it.vars.length) {
			var cur = stepKey === 'kit' ? state.kitVar : stepKey === 'switch' ? state.swVar : state.keycapVar;
			extra += '<label class="ty-bitem__varlabel">' + (stepKey === 'switch' ? 'Quy cách gói' : 'Phiên bản') +
				'<select class="ty-select" data-var="' + stepKey + '">' + it.vars.map(function (v) {
					return '<option value="' + v.id + '"' + (v.id === cur ? ' selected' : '') + (v.stock ? '' : ' disabled') + '>' + esc(v.label || ('#' + v.id)) + ' – ' + money(v.price) + (v.stock ? '' : ' (hết hàng)') + '</option>';
				}).join('') + '</select></label>';
		}
		if (selected && stepKey === 'switch') {
			extra += '<div class="ty-bitem__packs"><span>Số gói</span><div class="ty-qty"><button type="button" class="ty-qty__btn" data-pack="-1" aria-label="Bớt 1 gói">−</button><output>' + state.packs + '</output><button type="button" class="ty-qty__btn" data-pack="1" aria-label="Thêm 1 gói">+</button></div>' +
				'<span class="ty-help">' + (state.packs * packSize()) + ' switch' + (minSwitches() ? ' / cần ≥ ' + minSwitches() : '') + '</span></div>';
		}
		return '<article class="ty-bitem' + (selected ? ' is-selected' : '') + (blocked ? ' is-blocked' : '') + '" data-id="' + it.id + '">' +
			'<img class="ty-bitem__img" src="' + esc(it.img) + '" alt="" width="64" height="64" loading="lazy" decoding="async">' +
			'<div class="ty-bitem__body">' +
			'<div class="ty-bitem__meta"><span class="ty-mono">' + esc(it.sku) + '</span>' + (selected ? '<span class="ty-badge ty-badge--accent">' + '✓ ĐÃ CHỌN</span>' : '') + (blocked ? '<span class="ty-badge ty-badge--danger">Không tương thích</span>' : '') + (warn ? '<span class="ty-badge ty-badge--warning">Cảnh báo</span>' : '') + '</div>' +
			'<h3 class="ty-bitem__name"><a href="' + esc(it.url) + '" target="_blank" rel="noopener">' + esc(it.name) + '</a></h3>' +
			'<div class="ty-bitem__price">' + price + '</div>' +
			(chips ? '<ul class="ty-card__specs ty-bitem__specs">' + chips + '</ul>' : '') +
			(blocked ? '<p class="ty-error">' + esc(blocked) + '</p>' : '') + (warn ? '<p class="ty-help">' + esc(warn) + '</p>' : '') +
			extra +
			'<div class="ty-bitem__actions">' + (selected
				? '<button type="button" class="ty-btn ty-btn--soft ty-bitem__btn" data-unpick="' + stepKey + '">Bỏ chọn</button>'
				: '<button type="button" class="ty-btn ty-btn--dark ty-bitem__btn" data-pick="' + it.id + '"' + (blocked || !it.stock ? ' disabled' : '') + '>' + (it.stock ? 'Chọn linh kiện này' : 'Hết hàng') + '</button>') +
			'</div></div></article>';
	}

	function renderList() {
		var s = STEPS[state.step - 1], box = $q('[data-b-list]'), more = $q('[data-b-more]');
		var searchEl = $q('[data-b-search]');
		var listStep = ['kit', 'switch', 'keycap'].indexOf(s.key) > -1;
		searchEl.hidden = !listStep;
		$q('[data-b-filters]').hidden = !listStep;
		more.hidden = true;
		if (listStep) {
			var items = listFor(s.key);
			// Đưa linh kiện đang chọn lên đầu.
			var selId = s.key === 'kit' ? state.kit : s.key === 'switch' ? state.sw : state.keycap;
			items.sort(function (a, b) { return (b.id === selId) - (a.id === selId); });
			$q('[data-b-count]').textContent = items.length + ' linh kiện';
			box.innerHTML = items.length ? items.slice(0, ui.shown).map(function (it) { return itemCard(s.key, it); }).join('')
				: '<div class="ty-empty"><strong>Không có linh kiện phù hợp bộ lọc.</strong><button type="button" class="ty-btn ty-btn--soft" data-clear>Xóa bộ lọc</button></div>';
			more.hidden = items.length <= ui.shown;
			return;
		}
		$q('[data-b-count]').textContent = '';
		if (s.key === 'plate') {
			var k = kitItem(), v = varOf(k, state.kitVar), label = v && v.label ? v.label : '';
			var plate = (label.match(/[^·]*plate[^·]*/i) || [''])[0].trim();
			box.innerHTML = k
				? '<div class="ty-panel ty-binfo"><span class="ty-empty__ic">' + '▦' + '</span><h3>' + (plate ? esc(plate) : 'Plate đi kèm Kit') + '</h3><p>' +
				(plate ? 'Plate được xác định theo phiên bản Kit bạn chọn ở Bước 1.' : esc(k.name) + ' đi kèm plate mặc định của nhà sản xuất. Kho TYPEE hiện chưa bán plate rời – bạn có thể đổi phiên bản Kit ở Bước 1 nếu Kit có tùy chọn plate.') +
				'</p><button type="button" class="ty-btn ty-btn--soft" data-goto="1">Đổi phiên bản Kit</button></div>'
				: '<div class="ty-empty"><strong>Hãy chọn Khung Kit trước.</strong><button type="button" class="ty-btn ty-btn--primary" data-goto="1">Về Bước 1</button></div>';
		} else if (s.key === 'mod') {
			box.innerHTML = '<div class="ty-panel ty-binfo"><h3>Gói Mod &amp; Lube sắp mở bán</h3><p>Dịch vụ cân wire stab, lube switch và tape mod chưa có trong kho sản phẩm. Bạn vẫn có thể hoàn tất cấu hình và đặt hàng linh kiện ngay; công lắp ráp &amp; cân chỉnh cơ bản được miễn phí.</p>' +
				'<button type="button" class="ty-btn ty-btn--soft" data-goto="6">Bỏ qua, xem tổng kết</button></div>';
		} else {
			var missing = [];
			if (!state.kit) { missing.push('<button type="button" class="ty-pill" data-goto="1">Kit</button>'); }
			if (!state.sw) { missing.push('<button type="button" class="ty-pill" data-goto="3">Switch</button>'); }
			if (!state.keycap) { missing.push('<button type="button" class="ty-pill" data-goto="4">Keycap</button>'); }
			box.innerHTML = '<div class="ty-panel ty-binfo">' + (missing.length
				? '<h3>Cấu hình chưa đủ</h3><p>Còn thiếu:</p><div class="ty-pills">' + missing.join('') + '</div>'
				: '<h3>Cấu hình đã sẵn sàng</h3><p>Kiểm tra bảng tóm tắt bên phải rồi bấm <strong>Đặt hàng trọn bộ combo</strong>, hoặc lưu bản build để xin ý kiến bạn bè.</p>') + '</div>';
		}
	}

	/* ---------- Stepper, preview, EAV, tóm tắt ---------- */
	function stepDone(i) { var k = STEPS[i - 1].key; return k === 'kit' ? !!state.kit : k === 'plate' ? !!state.kit : k === 'switch' ? !!state.sw : k === 'keycap' ? !!state.keycap : k === 'mod' ? state.step > 5 : false; }
	function renderStepper() {
		root.querySelectorAll('.ty-stepper__item').forEach(function (b) {
			var i = +b.getAttribute('data-step');
			b.classList.toggle('is-active', i === state.step);
			b.classList.toggle('is-done', i !== state.step && stepDone(i));
			if (i === state.step) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
			b.querySelector('.ty-stepper__kicker').textContent = i === state.step ? 'ĐANG CHỌN' : 'BƯỚC ' + ('0' + i).slice(-2);
		});
		var s = STEPS[state.step - 1];
		$q('[data-b-eyebrow]').textContent = 'CẤU HÌNH BƯỚC ' + ('0' + state.step).slice(-2);
		$q('[data-b-title]').textContent = s.title;
		$q('[data-b-desc]').textContent = s.desc;
	}

	var LAYOUT_ROWS = { '60%': [14, 14, 13, 12, 8], '65%': [15, 15, 14, 14, 9], '75%': [16, 15, 15, 14, 14, 10], 'TKL': [16, 17, 17, 13, 16, 11], '96%': [18, 18, 18, 17, 17, 13], '98%': [18, 18, 18, 17, 17, 13], 'Fullsize': [20, 21, 21, 17, 18, 13] };
	function renderPreview() {
		var k = kitItem(), img = $q('[data-b-preview-img]'), kb = $q('[data-b-kb]'), link = $q('[data-b-preview-link]');
		var layout = kitLayout();
		$q('[data-b-layout-chip]').textContent = 'Layout: ' + (layout || '—');
		if (k) {
			img.src = k.large; img.alt = k.name; img.hidden = false; kb.hidden = true;
			img.onerror = function () { img.hidden = true; kb.hidden = false; };
			link.href = k.url; link.hidden = false;
			var v = varOf(k, state.kitVar);
			$q('[data-b-preview-caption]').textContent = k.name + (v && v.label ? ' · ' + v.label : '');
		} else {
			img.hidden = true; kb.hidden = false; link.hidden = true;
			$q('[data-b-preview-caption]').textContent = 'Chọn Kit để xem trước';
		}
		$q('[data-b-kb-name]').textContent = k ? k.name : 'Chưa chọn Kit';
		var rows = LAYOUT_ROWS[layout] || LAYOUT_ROWS['75%'];
		$q('[data-b-kb-keys]').innerHTML = rows.map(function (n, r) {
			var keys = '';
			for (var i = 0; i < n; i++) {
				var cls = (r === 0 && i === 0) || (r === rows.length - 3 && i === n - 1) ? ' is-accent' : '';
				if (r === rows.length - 1 && i === Math.floor(n / 2) - 1) { cls += ' is-space'; }
				keys += '<span class="ty-kb__key' + cls + '"></span>';
			}
			return '<div class="ty-kb__row">' + keys + '</div>';
		}).join('');
	}

	function renderEav() {
		var k = kitItem(), sw = swItem(), kc = kcItem(), need = minSwitches(), have = state.packs * packSize();
		var rows = [
			['Layout Kit', k ? (k.specs.layout || '—') : '—', ''],
			['Mạch PCB', k ? (k.specs.pcb || '—') : '—', ''],
			['Kiểu mount', k ? (k.specs.mount || '—') : '—', ''],
			['Chân switch', sw ? (sw.specs.pin || '—') : '—', sw ? (switchBlocked(sw) ? 'bad' : 'ok') : ''],
			['Số switch', sw && need ? have + ' / cần ≥ ' + need : (need ? 'cần ≥ ' + need : '—'), sw && need ? (have >= need ? 'ok' : 'bad') : ''],
			['Layout keycap', kc ? (kc.specs.supported_layout || kc.specs.profile || '—') : '—', kc ? (keycapWarning(kc) ? 'warn' : 'ok') : '']
		];
		$q('[data-b-eav]').innerHTML = rows.map(function (r) {
			return '<div class="ty-eav__row' + (r[2] ? ' is-' + r[2] : '') + '"><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>';
		}).join('');
	}

	function totals() {
		var kit = kitItem() ? priceOf(kitItem(), state.kitVar) : 0;
		var sw = swItem() ? priceOf(swItem(), state.swVar) * state.packs : 0;
		var kc = kcItem() ? priceOf(kcItem(), state.keycapVar) : 0;
		var sub = kit + sw + kc, full = !!(state.kit && state.sw && state.keycap);
		var bundle = full ? Math.round(sub * CFG.bundleRate) : 0;
		return { kit: kit, sw: sw, kc: kc, sub: sub, bundle: bundle, total: sub - bundle, full: full };
	}

	function renderSummary() {
		var t = totals(), k = kitItem(), sw = swItem(), kc = kcItem();
		var kv = varOf(k, state.kitVar), sv = varOf(sw, state.swVar);
		var plateLabel = kv && /plate/i.test(kv.label) ? (kv.label.match(/[^·]*plate[^·]*/i) || [''])[0].trim() : (k ? 'Plate đi kèm Kit' : '');
		var rows = [
			[1, 'KHUNG KIT BAREBONE', k ? k.name : '', kv ? kv.label : (k ? k.sku : ''), k ? money(t.kit) : '--'],
			[2, 'PLATE ĐỊNH VỊ', plateLabel, '', k ? 'Kèm Kit' : '--'],
			[3, 'SWITCH', sw ? sw.name : '', sw ? state.packs + ' gói × ' + packSize() + ' = ' + state.packs * packSize() + ' switch' : '', sw ? money(t.sw) : '--'],
			[4, 'BỘ KEYCAP', kc ? kc.name : '', kc ? (kc.specs.profile || kc.sku) : '', kc ? money(t.kc) : '--'],
			[5, 'DỊCH VỤ MODDING', 'Lắp ráp & cân chỉnh cơ bản', '', '0₫']
		];
		$q('[data-b-rows]').innerHTML = rows.map(function (r) {
			var active = r[0] === state.step;
			return '<li class="ty-sumrow' + (active ? ' is-active' : '') + (r[2] ? '' : ' is-empty') + '">' +
				'<button type="button" class="ty-sumrow__btn" data-goto="' + r[0] + '">' +
				'<span class="ty-sumrow__kicker">' + ('0' + r[0]).slice(-2) + '. ' + r[1] + (active ? ' (ĐANG CHỌN)' : '') + '</span>' +
				'<span class="ty-sumrow__name">' + (r[2] ? esc(r[2]) : '[Chưa chọn]') + '</span>' +
				(r[3] ? '<span class="ty-sumrow__sub">' + esc(r[3]) + '</span>' : '') +
				'</button><span class="ty-sumrow__price">' + r[4] + '</span></li>';
		}).join('');
		$q('[data-b-subtotal]').textContent = money(t.sub);
		$q('[data-b-bundle-row]').hidden = !t.full;
		$q('[data-b-bundle]').textContent = '-' + money(t.bundle);
		$q('[data-b-total]').textContent = money(t.total);
		$q('[data-b-build-id]').textContent = state.buildId ? 'Mã build: #' + state.buildId : '';
		var last = state.step === STEPS.length;
		var next = $q('[data-b-next]');
		next.hidden = last;
		if (!last) { next.innerHTML = 'Tiếp tục: Bước ' + (state.step + 1) + ' – ' + esc(STEPS[state.step].title.replace(/^Chọn /, '')) + ' →'; }
		var order = $q('[data-b-order]');
		order.hidden = !(last || t.full);
		order.disabled = !t.full || validation.status === 'bad' || validation.status === 'checking';
	}

	function renderCompat() {
		var box = $q('[data-b-compat]'), title = $q('[data-b-compat-title]'), msg = $q('[data-b-compat-msg]');
		var st = validation.status;
		box.setAttribute('data-state', st);
		if (st === 'idle') { title.textContent = 'Chưa đủ linh kiện để kiểm tra'; msg.textContent = 'Chọn Kit và Switch – hệ thống sẽ kiểm tra chân pin, số switch và layout keycap.'; }
		else if (st === 'checking') { title.textContent = 'Đang kiểm tra tương thích…'; msg.textContent = 'Đối chiếu thông số EAV với Rule Engine.'; }
		else if (st === 'bad') { title.textContent = 'Cấu hình chưa tương thích'; msg.innerHTML = validation.errors.map(esc).join('<br>'); }
		else if (st === 'warn') { title.textContent = 'Tương thích – có lưu ý'; msg.innerHTML = validation.warnings.map(esc).join('<br>'); }
		else if (st === 'error') { title.textContent = 'Không kết nối được Rule Engine'; msg.textContent = 'Kiểm tra phía trình duyệt vẫn hoạt động; hệ thống sẽ kiểm tra lại khi thêm vào giỏ.'; }
		else { title.textContent = 'Tương thích hoàn hảo (100%)'; msg.textContent = 'Chân switch, số lượng và layout keycap đều khớp với ' + (kitItem() ? kitItem().name : 'Kit') + '.'; }
	}

	function render() {
		renderStepper(); renderFilters(STEPS[state.step - 1].key); renderList(); renderPreview(); renderEav(); renderSummary(); renderCompat();
		saveDraft();
	}

	/* ---------- Gọi Rule Engine (typee-core) ---------- */
	var vTimer = null, vSeq = 0;
	function payload() {
		var sv = varOf(swItem(), state.swVar);
		return { kit_id: state.kit || 0, switch_id: state.sw || 0, keycap_id: state.keycap || 0, switch_pack_qty: state.packs || 0, switch_variation_id: state.swVar || 0, switches_per_pack: sv && sv.pack ? sv.pack : 0 };
	}
	function validate() {
		clearTimeout(vTimer);
		if (!state.kit || (!state.sw && !state.keycap)) { validation = { status: 'idle', errors: [], warnings: [] }; renderCompat(); renderSummary(); return; }
		validation.status = 'checking'; renderCompat(); renderSummary();
		vTimer = setTimeout(function () {
			var seq = ++vSeq;
			fetch(CFG.validateUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload()), credentials: 'omit' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (seq !== vSeq) { return; }
					var d = res && res.data ? res.data : null;
					if (!d) { throw new Error('bad'); }
					var errors = d.errors || [], warnings = d.warnings || [];
					validation = { status: d.is_valid ? (warnings.length ? 'warn' : 'ok') : 'bad', errors: errors, warnings: warnings, details: d.details || null };
					renderCompat(); renderSummary();
				})
				.catch(function () { if (seq === vSeq) { validation = { status: 'error', errors: [], warnings: [] }; renderCompat(); renderSummary(); } });
		}, 350);
	}

	/* ---------- Chọn linh kiện ---------- */
	function defaultVar(it) { if (!it || !it.vars.length) { return null; } for (var i = 0; i < it.vars.length; i++) { if (it.vars[i].stock) { return it.vars[i].id; } } return it.vars[0].id; }
	function autoPacks() {
		var need = minSwitches(), size = packSize();
		state.packs = need ? Math.max(1, Math.ceil(need / size)) : 1;
	}
	function pick(stepKey, id) {
		if (stepKey === 'kit') { state.kit = id; state.kitVar = defaultVar(kitItem()); if (state.sw) { autoPacks(); } }
		if (stepKey === 'switch') {
			var it = byId(CAT.switch, id);
			state.sw = id;
			// Ưu tiên gói lớn nhất ≤ số switch cần để giảm số gói phải mua.
			state.swVar = defaultVar(it);
			if (it && it.vars.length && minSwitches()) {
				var best = null;
				it.vars.forEach(function (v) { if (v.stock && v.pack && (!best || Math.abs(v.pack - minSwitches()) < Math.abs(best.pack - minSwitches()))) { best = v; } });
				if (best) { state.swVar = best.id; }
			}
			autoPacks();
		}
		if (stepKey === 'keycap') { state.keycap = id; state.keycapVar = defaultVar(byId(CAT.keycap, id)); }
		render(); validate();
	}
	function unpick(stepKey) {
		if (stepKey === 'kit') { state.kit = state.kitVar = null; }
		if (stepKey === 'switch') { state.sw = state.swVar = null; state.packs = 0; }
		if (stepKey === 'keycap') { state.keycap = state.keycapVar = null; }
		render(); validate();
	}
	function go(step) {
		state.step = Math.max(1, Math.min(STEPS.length, step));
		ui.search = ''; ui.shown = PAGE; $q('[data-b-search]').value = '';
		render();
		var top = root.querySelector('.ty-builder__grid').getBoundingClientRect().top + window.scrollY - 80;
		if (window.scrollY > top) { window.scrollTo({ top: top, behavior: 'smooth' }); }
	}

	/* ---------- Lưu / nạp / chia sẻ (FR-05) ---------- */
	function snapshot() { return { kit: state.kit, kitVar: state.kitVar, sw: state.sw, swVar: state.swVar, packs: state.packs, keycap: state.keycap, keycapVar: state.keycapVar }; }
	function apply(s) {
		if (!s) { return; }
		['kit', 'kitVar', 'sw', 'swVar', 'packs', 'keycap', 'keycapVar'].forEach(function (k) { if (s[k] != null) { state[k] = s[k]; } });
		if (state.kit && !kitItem()) { state.kit = state.kitVar = null; }
		if (state.sw && !swItem()) { state.sw = state.swVar = null; state.packs = 0; }
		if (state.keycap && !kcItem()) { state.keycap = state.keycapVar = null; }
	}
	function saveDraft() { try { localStorage.setItem('typee_builder_draft', JSON.stringify({ s: snapshot(), step: state.step, id: state.buildId })); } catch (e) {} }
	function builds() { return store.get('typee_builds'); }
	function toast(msg) { var t = $q('[data-b-toast]'); t.textContent = msg; t.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(function () { t.hidden = true; }, 4000); }
	function saveBuild(asNew, name, note) {
		var list = builds(), t = totals(), now = new Date().toISOString();
		var entry = { id: asNew || !state.buildId ? newId() : state.buildId, name: name, note: note || '', state: snapshot(), total: t.total, subtotal: t.sub, created: now, updated: now,
			parts: { kit: kitItem() ? kitItem().name : '', sw: swItem() ? swItem().name : '', keycap: kcItem() ? kcItem().name : '' }, layout: kitLayout() };
		var idx = -1;
		list.forEach(function (b, i) { if (b.id === entry.id) { idx = i; } });
		if (idx > -1) { entry.created = list[idx].created; list[idx] = entry; } else { list.unshift(entry); }
		store.set('typee_builds', list);
		state.buildId = entry.id; ui.loaded = entry;
		render(); showLoaded();
		toast(idx > -1 ? 'Đã lưu đè bản build #' + entry.id : 'Đã lưu bản build #' + entry.id);
	}
	function shareUrl() {
		var s = snapshot(), enc = btoa(unescape(encodeURIComponent(JSON.stringify(s)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
		return CFG.builderUrl + '?b=' + enc;
	}
	function decodeShare(str) {
		try { str = str.replace(/-/g, '+').replace(/_/g, '/'); while (str.length % 4) { str += '='; } return JSON.parse(decodeURIComponent(escape(atob(str)))); } catch (e) { return null; }
	}
	function showLoaded() {
		var box = $q('[data-b-loaded]');
		if (!ui.loaded) { box.hidden = true; return; }
		box.hidden = false;
		$q('[data-b-loaded-name]').textContent = ui.loaded.name + ' (#' + ui.loaded.id + ')';
	}

	/* ---------- Đặt hàng trọn bộ ---------- */
	function addItem(productId, qty) {
		var p = payload(), fd = new URLSearchParams();
		fd.append('product_id', productId); fd.append('quantity', qty); fd.append('is_typee_builder', '1');
		Object.keys(p).forEach(function (k) { fd.append(k, p[k]); });
		return fetch(CFG.addToCart, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' } })
			.then(function (r) { return r.json(); })
			.then(function (res) { if (!res || res.error) { throw new Error('add'); } return res; });
	}
	function order() {
		var btn = $q('[data-b-order]'), t = totals();
		if (!t.full) { toast('Cần chọn đủ Kit, Switch và Keycap.'); return; }
		btn.disabled = true; btn.classList.add('is-loading');
		var items = [[state.kitVar || state.kit, 1], [state.swVar || state.sw, state.packs], [state.keycapVar || state.keycap, 1]];
		var last = null;
		items.reduce(function (p, it) { return p.then(function () { return addItem(it[0], it[1]).then(function (r) { last = r; }); }); }, Promise.resolve())
			.then(function () {
				if (last && last.fragments) { $(document.body).trigger('added_to_cart', [last.fragments, last.cart_hash, $(btn)]); }
				if (window.TYPEE_CART && window.TYPEE_CART.open) { window.TYPEE_CART.open(); } else { window.location.href = CFG.cartUrl; }
			})
			.catch(function () { toast('Không thêm được trọn bộ vào giỏ – hệ thống đã chặn cấu hình chưa hợp lệ hoặc hết hàng.'); $(document.body).trigger('wc_fragment_refresh'); })
			.then(function () { btn.disabled = false; btn.classList.remove('is-loading'); });
	}

	/* ---------- Sự kiện ---------- */
	root.addEventListener('click', function (e) {
		var t = e.target.closest('button, a');
		if (!t || !root.contains(t)) { return; }
		if (t.hasAttribute('data-step')) { go(+t.getAttribute('data-step')); }
		else if (t.hasAttribute('data-goto')) { go(+t.getAttribute('data-goto')); }
		else if (t.hasAttribute('data-pick')) { pick(STEPS[state.step - 1].key, +t.getAttribute('data-pick')); }
		else if (t.hasAttribute('data-unpick')) { unpick(t.getAttribute('data-unpick')); }
		else if (t.hasAttribute('data-f')) { var k = STEPS[state.step - 1].key; ui.filter[k] = ui.filter[k] || {}; ui.filter[k][t.getAttribute('data-f')] = t.getAttribute('data-v'); ui.shown = PAGE; renderFilters(k); renderList(); }
		else if (t.hasAttribute('data-clear')) { ui.filter[STEPS[state.step - 1].key] = {}; ui.search = ''; $q('[data-b-search]').value = ''; render(); }
		else if (t.hasAttribute('data-pack')) { state.packs = Math.max(1, state.packs + +t.getAttribute('data-pack')); render(); validate(); }
		else if (t.hasAttribute('data-b-next')) { go(state.step + 1); }
		else if (t.hasAttribute('data-b-more')) { ui.shown += PAGE; renderList(); }
		else if (t.hasAttribute('data-b-order')) { order(); }
		else if (t.hasAttribute('data-b-share')) {
			var url = shareUrl();
			(navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { toast('Đã sao chép link chia sẻ bản build.'); }, function () { window.prompt('Sao chép link chia sẻ:', url); });
		}
		else if (t.hasAttribute('data-b-save-over')) { if (ui.loaded) { saveBuild(false, ui.loaded.name, ui.loaded.note); } }
		else if (t.hasAttribute('data-b-save-new')) { window.TYPEE_UI.open(document.getElementById('b-save-modal'), t); document.getElementById('b-save-modal').setAttribute('data-new', '1'); }
	});
	root.addEventListener('change', function (e) {
		var sel = e.target.closest('[data-var]');
		if (!sel) { return; }
		var k = sel.getAttribute('data-var'), v = +sel.value;
		if (k === 'kit') { state.kitVar = v; }
		if (k === 'switch') { state.swVar = v; autoPacks(); }
		if (k === 'keycap') { state.keycapVar = v; }
		render(); validate();
	});
	var sTimer;
	$q('[data-b-search]').addEventListener('input', function () { var el = this; clearTimeout(sTimer); sTimer = setTimeout(function () { ui.search = el.value; ui.shown = PAGE; renderList(); }, 200); });
	var form = document.querySelector('[data-b-save-form]'), modal = document.getElementById('b-save-modal');
	$(modal).on('typee:open', function () { form.name.value = ui.loaded ? ui.loaded.name + (modal.getAttribute('data-new') ? ' (bản sao)' : '') : ''; form.note.value = ui.loaded ? ui.loaded.note : ''; });
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (!form.name.value.trim()) { form.name.focus(); return; }
		saveBuild(modal.getAttribute('data-new') === '1' || !ui.loaded, form.name.value.trim(), form.note.value.trim());
		modal.removeAttribute('data-new');
		window.TYPEE_UI.close();
	});

	/* ---------- Khởi tạo: ?build_id= (màn 08) › ?b= (link chia sẻ) › bản nháp ---------- */
	var params = new URLSearchParams(window.location.search);
	var bid = params.get('build_id'), shared = params.get('b');
	if (bid) {
		var found = builds().filter(function (b) { return b.id === bid; })[0];
		if (found) { apply(found.state); state.buildId = found.id; ui.loaded = found; state.step = 6; }
		else { setTimeout(function () { toast('Không tìm thấy bản build #' + bid + ' trên trình duyệt này.'); }, 300); }
	} else if (shared) {
		apply(decodeShare(shared)); state.step = 6;
		setTimeout(function () { toast('Đã nạp cấu hình từ link chia sẻ.'); }, 300);
	} else {
		try { var d = JSON.parse(localStorage.getItem('typee_builder_draft') || 'null'); if (d) { apply(d.s); state.step = d.step || 1; state.buildId = d.id || ''; if (state.buildId) { ui.loaded = builds().filter(function (b) { return b.id === state.buildId; })[0] || null; } } } catch (e) {}
	}
	render(); showLoaded(); validate();
})(jQuery);
