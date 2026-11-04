/* Cửa sổ nổi XD – tự mở (chỉ nạp khi bật "Tự mở"). */
(function (w, d) {
	'use strict';
	function ls() { try { return w.localStorage; } catch (e) { return null; } }
	function ss() { try { return w.sessionStorage; } catch (e) { return null; } }

	EXD.on('exd-lightbox', 'dialog[data-exd-auto]', function (dl) {
		if (d.body.classList.contains('elementor-editor-active') || /[?&]elementor-preview=/.test(location.search)) return;
		var key = 'exd-lb-seen-' + dl.id, freq = dl.getAttribute('data-exd-freq') || 'always';
		var on = dl.getAttribute('data-exd-on') || 'time', delay = parseFloat(dl.getAttribute('data-exd-auto') || '0') * 1000;

		if (freq === 'session' && ss() && ss().getItem(key)) return;
		if (freq.indexOf('days:') === 0 && ls() && parseInt(ls().getItem(key) || '0', 10) > Date.now()) return;

		var done = false;
		function open() {
			if (done || d.querySelector('dialog.exd-modal[open]')) return;
			done = true;
			if (freq === 'session' && ss()) ss().setItem(key, '1');
			if (freq.indexOf('days:') === 0 && ls()) ls().setItem(key, String(Date.now() + parseInt(freq.slice(5), 10) * 864e5));
			EXD.modal.show(dl);
		}

		if (on === 'scroll') {
			var onScroll = function () {
				var h = d.documentElement;
				if ((h.scrollTop + w.innerHeight) / h.scrollHeight >= 0.5) { w.removeEventListener('scroll', onScroll); open(); }
			};
			w.addEventListener('scroll', onScroll, { passive: true });
		} else if (on === 'exit' && w.matchMedia('(hover:hover)').matches) {
			d.addEventListener('mouseout', function h(e) {
				if (!e.relatedTarget && e.clientY <= 0) { d.removeEventListener('mouseout', h); open(); }
			});
		} else {
			w.setTimeout(open, delay);
		}
	});
})(window, document);
