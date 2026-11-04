/* Slider XD – điều khiển cho track scroll-snap (mũi tên, chấm, tự chạy, mờ dần). Không jQuery. */
(function (w, d) {
	'use strict';

	function Slider(root) {
		var conf = {};
		try { conf = JSON.parse(root.getAttribute('data-exd-sl') || '{}'); } catch (e) {}
		var track = root.querySelector('.exd-sl__track');
		var slides = [].slice.call(track.children);
		var dots = [].slice.call(root.querySelectorAll('.exd-sl__dot'));
		var prev = root.querySelector('.exd-sl__prev'), next = root.querySelector('.exd-sl__next');
		var fade = !!conf.fade, loop = !!conf.loop, idx = 0, pages = slides.length, timer = 0;
		var paused = false, visible = true, raf = 0;
		if (slides.length < 2) return;

		function step() {
			var a = slides[0], b = slides[1];
			return b ? b.offsetLeft - a.offsetLeft : a.offsetWidth;
		}
		function perView() {
			if (fade) return 1;
			var s = step();
			return s ? Math.max(1, Math.round((track.clientWidth + 1) / s)) : 1;
		}
		function layout() {
			pages = Math.max(1, slides.length - perView() + 1);
			dots.forEach(function (dt, i) { dt.hidden = i >= pages; });
			if (idx > pages - 1) idx = pages - 1;
			paint();
		}
		function paint() {
			dots.forEach(function (dt, i) {
				var on = i === idx;
				dt.classList.toggle('is-active', on);
				if (on) dt.setAttribute('aria-current', 'true'); else dt.removeAttribute('aria-current');
			});
			if (fade) {
				slides.forEach(function (s, i) {
					s.classList.toggle('is-active', i === idx);
					if (i === idx) s.removeAttribute('inert'); else s.setAttribute('inert', '');
				});
			}
			if (!loop) {
				if (prev) prev.disabled = idx <= 0;
				if (next) next.disabled = idx >= pages - 1;
			}
		}
		function go(i, user) {
			if (loop) i = (i + pages) % pages;
			else i = Math.max(0, Math.min(pages - 1, i));
			idx = i;
			if (!fade) track.scrollTo({ left: i * step(), behavior: EXD.reduced ? 'auto' : 'smooth' });
			paint();
			if (user) restart();
		}

		// Đồng bộ chấm khi người dùng vuốt.
		if (!fade) {
			track.addEventListener('scroll', function () {
				if (raf) return;
				raf = w.requestAnimationFrame(function () {
					raf = 0;
					var s = step();
					if (!s) return;
					var i = Math.round(track.scrollLeft / s);
					if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 2) i = pages - 1;
					if (i !== idx) { idx = Math.max(0, Math.min(pages - 1, i)); paint(); }
				});
			}, { passive: true });
		}

		if (prev) prev.addEventListener('click', function () { go(idx - 1, true); });
		if (next) next.addEventListener('click', function () { go(idx + 1, true); });
		dots.forEach(function (dt, i) { dt.addEventListener('click', function () { go(i, true); }); });
		track.addEventListener('keydown', function (e) {
			if (!fade) return; // trượt: phím mũi tên cuộn gốc
			if (e.key === 'ArrowLeft') { go(idx - 1, true); e.preventDefault(); }
			if (e.key === 'ArrowRight') { go(idx + 1, true); e.preventDefault(); }
		});

		// Tự chạy: dừng khi rê chuột/focus, tab ẩn, slider ra khỏi màn hình, hoặc người dùng giảm chuyển động.
		function tick() {
			stop();
			if (!conf.auto || EXD.reduced || paused || !visible || d.hidden) return;
			timer = w.setTimeout(function () {
				if (!loop && idx >= pages - 1) go(0); else go(idx + 1);
				tick();
			}, conf.auto);
		}
		function stop() { if (timer) { w.clearTimeout(timer); timer = 0; } }
		function restart() { tick(); }
		if (conf.auto) {
			if (conf.pause) {
				root.addEventListener('mouseenter', function () { paused = true; stop(); });
				root.addEventListener('mouseleave', function () { paused = false; tick(); });
			}
			root.addEventListener('focusin', function () { paused = true; stop(); });
			root.addEventListener('focusout', function () { paused = false; tick(); });
			track.addEventListener('touchstart', function () { paused = true; stop(); }, { passive: true });
			track.addEventListener('touchend', function () { paused = false; tick(); }, { passive: true });
			d.addEventListener('visibilitychange', tick);
			if ('IntersectionObserver' in w) {
				new IntersectionObserver(function (en) { visible = en[0].isIntersecting; tick(); }, { threshold: 0.25 }).observe(root);
			}
		}

		if ('ResizeObserver' in w) new ResizeObserver(function () { layout(); }).observe(track);
		layout();
		tick();
	}

	EXD.on('exd-slider', '.exd-sl', Slider);
	EXD.on('exd-posts', '.exd-sl', Slider); // Bài viết XD kiểu slider dùng chung
})(window, document);
