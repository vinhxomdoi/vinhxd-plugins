/* Hộp thông báo XD – nút đóng (chỉ nạp khi bật "Cho phép đóng"). */
(function () {
	'use strict';
	var KEY = 'exd-msg-';
	function hide(el) { (el.closest('.elementor-widget') || el).style.display = 'none'; }
	function store() { try { return window.localStorage; } catch (e) { return null; } }

	EXD.on('exd-message', '[data-exd-dismiss]', function (el) {
		var id = el.getAttribute('data-exd-dismiss'), ls = store();
		var until = ls ? parseInt(ls.getItem(KEY + id) || '0', 10) : 0;
		if (until && until > Date.now() && !document.body.classList.contains('elementor-editor-active')) {
			hide(el);
			return;
		}
		var btn = el.querySelector('.exd-msg__close');
		if (!btn) return;
		btn.addEventListener('click', function () {
			var days = parseInt(el.getAttribute('data-days') || '0', 10);
			if (days > 0 && ls) { try { ls.setItem(KEY + id, String(Date.now() + days * 864e5)); } catch (e) {} }
			el.classList.add('is-closing');
			setTimeout(function () { hide(el); }, EXD.reduced ? 0 : 300);
		});
	});
})();
