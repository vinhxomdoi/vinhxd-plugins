/* Scroll To XD – thanh chấm điều hướng (chỉ nạp khi bật). Cuộn mượt & chừa header làm bằng CSS. */
(function (w, d) {
	'use strict';
	var built = false;

	function build() {
		if (built) return;
		var marks = [].slice.call(d.querySelectorAll('.exd-sto[data-exd-bullet]'));
		if (marks.length < 2) return;
		built = true;

		var first = marks[0], cs = w.getComputedStyle(first);
		var nav = d.createElement('nav');
		nav.className = 'exd-sto-nav' + (first.getAttribute('data-side') === 'left' ? ' exd-sto-nav--left' : '');
		nav.setAttribute('aria-label', 'Mục trên trang');
		nav.style.setProperty('--exd-sto-c', cs.getPropertyValue('--exd-sto-c'));
		nav.style.setProperty('--exd-sto-on', cs.getPropertyValue('--exd-sto-on'));

		var links = marks.map(function (m) {
			var a = d.createElement('a');
			a.href = '#' + m.id;
			a.innerHTML = '<span></span>';
			a.firstChild.textContent = m.getAttribute('data-exd-bullet') || m.id;
			a.setAttribute('aria-label', a.firstChild.textContent);
			nav.appendChild(a);
			return a;
		});
		d.body.appendChild(nav);

		// Mục đang xem = mốc cuối cùng đã đi qua vạch 40% chiều cao màn hình (cuối trang → mốc cuối).
		var raf = 0;
		function update() {
			raf = 0;
			var line = w.innerHeight * 0.4, cur = 0;
			marks.forEach(function (m, i) { if (m.getBoundingClientRect().top <= line) cur = i; });
			var h = d.documentElement;
			if (h.scrollTop + w.innerHeight >= h.scrollHeight - 2) cur = marks.length - 1;
			links.forEach(function (a, i) {
				var on = i === cur;
				a.classList.toggle('is-active', on);
				if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
			});
		}
		w.addEventListener('scroll', function () { if (!raf) raf = w.requestAnimationFrame(update); }, { passive: true });
		w.addEventListener('resize', update, { passive: true });
		update();
	}

	EXD.on('exd-scroll-to', '.exd-sto[data-exd-bullet]', function () {
		if (d.body.classList.contains('elementor-editor-active')) return;
		// Chờ mọi mốc có trong DOM rồi mới dựng 1 lần.
		if (d.readyState === 'complete') build(); else w.addEventListener('load', build);
		w.setTimeout(build, 0);
	});
})(window, document);
