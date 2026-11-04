/*!
 * XD Mega Menu 3.3 – widget "XD Menu"
 * JS thuần (không jQuery). Mọi sự kiện bắt kiểu delegate trên document nên chạy
 * được cả với widget Elementor render lại trong editor.
 */
(function () {
	'use strict';

	var CLOSE_DELAY = 150; // ms – trễ đóng khi rê chuột ra ngoài
	var lastPointer = 'mouse';
	var timers = typeof WeakMap === 'function' ? new WeakMap() : null;
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	/* ---------- Tiện ích ---------- */

	function breakpoint(nav) {
		return parseInt(nav.getAttribute('data-breakpoint'), 10) || 0;
	}

	function isMobile(nav) {
		var bp = breakpoint(nav);
		return bp > 0 && window.matchMedia('(max-width: ' + bp + 'px)').matches;
	}

	function navOf(el) {
		return el && el.closest ? el.closest('.xd-menu-nav') : null;
	}

	function markJs(nav) {
		if (nav && !nav.classList.contains('xd-js')) {
			nav.classList.add('xd-js');
		}
	}

	function child(el, selector) {
		for (var c = el.firstElementChild; c; c = c.nextElementSibling) {
			if (c.matches(selector)) {
				return c;
			}
		}
		return null;
	}

	function panelOf(li) {
		return child(li, '.xd-menu-mega, .xd-sub-menu');
	}

	function toggleOf(li) {
		return child(li, '.xd-submenu-toggle');
	}

	function parentItem(li, nav) {
		var p = li.parentElement ? li.parentElement.closest('.xd-menu-has-submenu') : null;
		return p && nav.contains(p) ? p : null;
	}

	function isVisible(el) {
		if (!el.getClientRects().length) {
			return false;
		}
		return window.getComputedStyle(el).visibility !== 'hidden';
	}

	function focusables(container) {
		return Array.prototype.filter.call(container.querySelectorAll(FOCUSABLE), isVisible);
	}

	function initAll() {
		Array.prototype.forEach.call(document.querySelectorAll('.xd-menu-nav'), markJs);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}

	/* ---------- Desktop: vị trí mega ---------- */

	function positionMega(li) {
		var mega = child(li, '.xd-menu-mega');
		var nav = navOf(li);
		if (!mega || !nav) {
			return;
		}

		var vw = document.documentElement.clientWidth; // không tính thanh cuộn
		var navRect = nav.getBoundingClientRect();
		var liRect = li.getBoundingClientRect();
		var top = navRect.bottom;
		var left = 0;

		mega.style.setProperty('--xd-vw', vw + 'px');
		mega.style.setProperty('--xd-mega-gap', Math.max(0, navRect.bottom - liRect.bottom) + 'px');

		if (!mega.classList.contains('xd-menu-mega-full')) {
			var w = mega.offsetWidth || 0;
			left = liRect.left;
			if (w > 0 && left + w > vw) {
				left = vw - w;
			}
			if (left < 0) {
				left = 0;
			}
		}

		mega.style.setProperty('--xd-mega-top', top + 'px');
		mega.style.setProperty('--xd-mega-left', left + 'px');

		// Bù lệch khi có phần tử cha dùng transform (hiệu ứng Motion/animation của Elementor):
		// lúc đó position:fixed tính theo phần tử cha chứ không theo màn hình.
		var r = mega.getBoundingClientRect();
		var dy = r.top - top;
		var dx = r.left - left;
		if (Math.abs(dy) > 0.5) {
			mega.style.setProperty('--xd-mega-top', (top - dy) + 'px');
		}
		if (Math.abs(dx) > 0.5) {
			mega.style.setProperty('--xd-mega-left', (left - dx) + 'px');
		}
	}

	/* ---------- Desktop: mở / đóng ---------- */

	function clearTimer(li) {
		if (!timers) {
			return;
		}
		var t = timers.get(li);
		if (t) {
			clearTimeout(t);
			timers.delete(li);
		}
	}

	function setOpen(li, open) {
		clearTimer(li);
		li.classList.toggle('xd-menu-open', open);
		var t = toggleOf(li);
		if (t) {
			t.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
		if (!open) {
			Array.prototype.forEach.call(li.querySelectorAll('.xd-menu-open'), function (c) {
				setOpen(c, false);
			});
		}
	}

	function openItem(li) {
		markJs(navOf(li));
		// Đóng các mục cùng cấp đang mở
		for (var s = li.parentElement.firstElementChild; s; s = s.nextElementSibling) {
			if (s !== li && s.classList.contains('xd-menu-open')) {
				setOpen(s, false);
			}
		}
		if (li.classList.contains('xd-menu-has-mega')) {
			positionMega(li);
		}
		setOpen(li, true);
	}

	function closeLater(li) {
		if (!timers) {
			setOpen(li, false);
			return;
		}
		clearTimer(li);
		timers.set(li, setTimeout(function () {
			timers.delete(li);
			setOpen(li, false);
		}, CLOSE_DELAY));
	}

	function closeAllDesktop(except) {
		Array.prototype.forEach.call(document.querySelectorAll('.xd-menu-item.xd-menu-open'), function (li) {
			if (!except || !li.contains(except)) {
				setOpen(li, false);
			}
		});
	}

	/* ---------- Mobile: drawer + panel ---------- */

	function deepestPanel(nav) {
		var list = nav.querySelectorAll('.xd-subpanel-open');
		return list.length ? list[list.length - 1] : null;
	}

	function setBodyLock() {
		var any = !!document.querySelector('.xd-menu-nav.xd-mobile-open');
		document.documentElement.classList.toggle('xd-mobile-menu-open', any);
		document.body.classList.toggle('xd-mobile-menu-open', any);
	}

	function focusLater(el) {
		if (!el) {
			return;
		}
		window.requestAnimationFrame(function () {
			el.focus({ preventScroll: true });
		});
	}

	function openDrawer(nav) {
		markJs(nav);
		closeAllDesktop();
		nav.classList.add('xd-mobile-open');
		setBodyLock();
		var btn = nav.querySelector('.xd-menu-trigger-btn');
		if (btn) {
			btn.setAttribute('aria-expanded', 'true');
			btn.setAttribute('aria-label', 'Đóng menu');
		}
		var menu = nav.querySelector('.xd-menu');
		if (menu) {
			window.requestAnimationFrame(function () {
				var f = focusables(menu);
				if (f.length) {
					f[0].focus({ preventScroll: true });
				}
			});
		}
	}

	function closeDrawer(nav, returnFocus) {
		nav.classList.remove('xd-mobile-open');
		Array.prototype.forEach.call(nav.querySelectorAll('.xd-subpanel-open'), function (p) {
			p.classList.remove('xd-subpanel-open');
		});
		Array.prototype.forEach.call(nav.querySelectorAll('.xd-submenu-toggle[aria-expanded="true"]'), function (t) {
			t.setAttribute('aria-expanded', 'false');
		});
		var menu = nav.querySelector('.xd-menu');
		if (menu) {
			menu.classList.remove('xd-has-open-panel');
		}
		setBodyLock();
		var btn = nav.querySelector('.xd-menu-trigger-btn');
		if (btn) {
			btn.setAttribute('aria-expanded', 'false');
			btn.setAttribute('aria-label', 'Mở menu');
			if (returnFocus) {
				btn.focus({ preventScroll: true });
			}
		}
	}

	function openPanel(li) {
		var panel = panelOf(li);
		var nav = navOf(li);
		if (!panel || !nav) {
			return;
		}
		var menu = nav.querySelector('.xd-menu');
		// Panel cấp 1 định vị theo khung .xd-menu (khung cuộn) → cuộn khung về đầu trước khi mở.
		if (menu && !li.parentElement.closest('.xd-sub-menu, .xd-menu-mega')) {
			menu.scrollTop = 0;
		}
		if (menu) {
			menu.classList.add('xd-has-open-panel');
		}
		panel.classList.add('xd-subpanel-open');
		var inner = child(panel, '.xd-mega-content-inner, .xd-sub-menu-list');
		if (inner) {
			inner.scrollTop = 0;
		}
		var t = toggleOf(li);
		if (t) {
			t.setAttribute('aria-expanded', 'true');
		}
		focusLater(panel.querySelector('.xd-mobile-back-btn'));
	}

	function closePanel(panel) {
		if (!panel) {
			return;
		}
		var nav = navOf(panel);
		panel.classList.remove('xd-subpanel-open');
		Array.prototype.forEach.call(panel.querySelectorAll('.xd-subpanel-open'), function (p) {
			p.classList.remove('xd-subpanel-open');
		});
		var li = panel.parentElement;
		var t = toggleOf(li);
		if (t) {
			t.setAttribute('aria-expanded', 'false');
			focusLater(t);
		}
		if (nav && !nav.querySelector('.xd-subpanel-open')) {
			var menu = nav.querySelector('.xd-menu');
			if (menu) {
				menu.classList.remove('xd-has-open-panel');
			}
		}
	}

	/* ---------- Sự kiện ---------- */

	document.addEventListener('pointerdown', function (e) {
		lastPointer = e.pointerType || 'mouse';
		// Nhấn/chạm ra ngoài → đóng menu desktop đang mở
		closeAllDesktop(e.target);
	}, true);

	// Rê chuột vào: mở (chỉ chuột thật – chạm màn hình xử lý ở click).
	document.addEventListener('pointerover', function (e) {
		if (e.pointerType && e.pointerType !== 'mouse') {
			return;
		}
		var li = e.target.closest ? e.target.closest('.xd-menu-has-submenu') : null;
		var nav = navOf(li);
		if (!li || !nav || isMobile(nav)) {
			return;
		}
		markJs(nav);
		for (var cur = li; cur; cur = parentItem(cur, nav)) {
			clearTimer(cur);
		}
		if (!li.classList.contains('xd-menu-open')) {
			openItem(li);
		}
	});

	// Rê chuột ra: đóng sau CLOSE_DELAY (đủ để đi qua khoảng hở).
	document.addEventListener('pointerout', function (e) {
		if (e.pointerType && e.pointerType !== 'mouse') {
			return;
		}
		var li = e.target.closest ? e.target.closest('.xd-menu-has-submenu') : null;
		var nav = navOf(li);
		if (!li || !nav || isMobile(nav)) {
			return;
		}
		var to = e.relatedTarget;
		for (var cur = li; cur; cur = parentItem(cur, nav)) {
			if (cur.classList.contains('xd-menu-open') && !(to && cur.contains(to))) {
				closeLater(cur);
			}
		}
	});

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t.closest) {
			return;
		}
		var el;

		// Nút mở/đóng drawer
		if ((el = t.closest('.xd-menu-trigger-btn'))) {
			e.preventDefault();
			var nav = navOf(el);
			if (nav.classList.contains('xd-mobile-open')) {
				closeDrawer(nav, true);
			} else {
				openDrawer(nav);
			}
			return;
		}

		// Lớp phủ / nút đóng (cho bàn phím)
		if ((el = t.closest('.xd-menu-overlay, .xd-menu-close-btn'))) {
			e.preventDefault();
			closeDrawer(navOf(el), true);
			return;
		}

		// Nút quay lại trong panel con
		if ((el = t.closest('.xd-mobile-back-btn'))) {
			e.preventDefault();
			closePanel(el.closest('.xd-menu-mega, .xd-sub-menu'));
			return;
		}

		// Nút mũi tên mở menu con
		if ((el = t.closest('.xd-submenu-toggle'))) {
			e.preventDefault();
			var li = el.parentElement;
			if (isMobile(navOf(li))) {
				openPanel(li);
			} else if (li.classList.contains('xd-menu-open')) {
				setOpen(li, false);
			} else {
				openItem(li);
			}
			return;
		}

		// Link của mục có menu con
		if ((el = t.closest('.xd-menu-has-submenu > a'))) {
			var item = el.parentElement;
			var href = el.getAttribute('href') || '';
			var dead = !href || href === '#' || href.indexOf('javascript:') === 0;

			if (isMobile(navOf(item))) {
				// Mobile: chữ là link bình thường; link rỗng thì mở panel con.
				if (dead) {
					e.preventDefault();
					openPanel(item);
				}
				return;
			}

			if (dead) {
				e.preventDefault();
				if (!item.classList.contains('xd-menu-open')) {
					openItem(item);
				} else if (lastPointer !== 'mouse') {
					setOpen(item, false);
				}
				return;
			}

			// Màn hình cảm ứng (iPad ngang…): chạm lần 1 mở menu, lần 2 mới chuyển trang.
			// e.detail === 0 là click do bàn phím (Enter) → luôn chuyển trang.
			if (e.detail !== 0 && lastPointer !== 'mouse' && !item.classList.contains('xd-menu-open')) {
				e.preventDefault();
				openItem(item);
			}
		}
	});

	// Focus rời khỏi mục đang mở (Tab ra ngoài) → đóng.
	document.addEventListener('focusout', function (e) {
		var to = e.relatedTarget;
		if (!to || !e.target.closest) {
			return; // Focus về body (nhấn vào vùng trống trong mega) → giữ nguyên.
		}
		var li = e.target.closest('.xd-menu-open');
		var nav = navOf(li);
		if (!li || !nav) {
			return;
		}
		for (var cur = li; cur; cur = parentItem(cur, nav)) {
			if (cur.classList.contains('xd-menu-open') && !cur.contains(to)) {
				setOpen(cur, false);
			}
		}
	});

	document.addEventListener('keydown', function (e) {
		var openNav = document.querySelector('.xd-menu-nav.xd-mobile-open');

		if (e.key === 'Escape' || e.key === 'Esc') {
			if (openNav) {
				var panel = deepestPanel(openNav);
				if (panel) {
					closePanel(panel);
				} else {
					closeDrawer(openNav, true);
				}
				return;
			}
			var active = document.activeElement;
			var li = active && active.closest ? active.closest('.xd-menu-open') : null;
			if (li) {
				setOpen(li, false);
				var a = child(li, 'a');
				if (a) {
					a.focus();
				}
			} else {
				closeAllDesktop();
			}
			return;
		}

		// Giữ focus trong drawer khi đang mở
		if (e.key === 'Tab' && openNav) {
			var container = deepestPanel(openNav) || openNav.querySelector('.xd-menu');
			var f = container ? focusables(container) : [];
			if (!f.length) {
				return;
			}
			var first = f[0];
			var last = f[f.length - 1];
			var cur = document.activeElement;
			if (!container.contains(cur)) {
				e.preventDefault();
				first.focus();
			} else if (e.shiftKey && cur === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && cur === last) {
				e.preventDefault();
				first.focus();
			}
		}
	});

	// Cuộn trang khi mega đang mở (header sticky): cập nhật vị trí, tối đa 1 lần/khung hình.
	var ticking = false;
	window.addEventListener('scroll', function () {
		if (ticking) {
			return;
		}
		ticking = true;
		window.requestAnimationFrame(function () {
			ticking = false;
			Array.prototype.forEach.call(document.querySelectorAll('.xd-menu-has-mega.xd-menu-open'), positionMega);
		});
	}, { passive: true });

	// Đổi kích thước: đóng drawer khi lên desktop, đóng menu desktop khi xuống mobile.
	var resizeTimer;
	window.addEventListener('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			Array.prototype.forEach.call(document.querySelectorAll('.xd-menu-nav.xd-mobile-open'), function (nav) {
				if (!isMobile(nav)) {
					closeDrawer(nav, false);
				}
			});
			Array.prototype.forEach.call(document.querySelectorAll('.xd-menu-item.xd-menu-open'), function (li) {
				var nav = navOf(li);
				if (nav && isMobile(nav)) {
					setOpen(li, false);
				} else if (li.classList.contains('xd-menu-has-mega')) {
					positionMega(li);
				}
			});
		}, 100);
	}, { passive: true });
})();
