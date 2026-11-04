/**
 * Theme XD - Main JavaScript (vanilla, không cần jQuery, nạp defer)
 * Chỉ được enqueue trên trang bài viết / archive có sidebar do theme render.
 * @package ThemeXD
 */
(function () {
	'use strict';

	var d = document;

	/* ============================================================
	   COPY LINK BUTTON
	============================================================ */
	d.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.txd-share-btn.copy-link') : null;
		if (!btn || btn.classList.contains('copied')) return;
		e.preventDefault();

		var url = btn.getAttribute('data-url') || location.href;
		var done = function () {
			var orig = btn.innerHTML;
			btn.classList.add('copied');
			btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg> ' +
				(btn.getAttribute('data-copied') || 'Đã sao chép!');
			setTimeout(function () { btn.classList.remove('copied'); btn.innerHTML = orig; }, 2000);
		};
		var fallback = function () {
			var el = d.createElement('textarea');
			el.value = url;
			el.setAttribute('readonly', '');
			el.style.cssText = 'position:fixed;left:-9999px';
			d.body.appendChild(el);
			el.select();
			try { d.execCommand('copy'); done(); } catch (err) {}
			d.body.removeChild(el);
		};

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(url).then(done, fallback);
		} else {
			fallback();
		}
	});

	/* ============================================================
	   STICKY SIDEBAR: tắt sticky nếu sidebar cao hơn màn hình
	   (nếu không, phần dưới sidebar sẽ không bao giờ xem được)
	============================================================ */
	var sb = d.querySelector('.txd-sidebar');
	if (sb) {
		var checkSidebar = function () {
			var top = parseFloat(getComputedStyle(sb).top) || 0;
			sb.classList.toggle('is-static', sb.offsetHeight > window.innerHeight - top - 16);
		};
		if ('ResizeObserver' in window) new ResizeObserver(checkSidebar).observe(sb);
		window.addEventListener('resize', checkSidebar, { passive: true });
		checkSidebar();
	}

	/* ============================================================
	   READING PROGRESS BAR
	   Đo vị trí bài 1 lần (và khi kích thước đổi), scroll chỉ cập nhật transform qua rAF
	============================================================ */
	var art = d.querySelector('.txd-post-article');
	if (art) {
		var bar = d.createElement('div');
		bar.id = 'txd-progress';
		bar.setAttribute('aria-hidden', 'true');
		d.body.appendChild(bar);

		var start = 0, end = 1, ticking = false;
		var measure = function () {
			var r = art.getBoundingClientRect();
			start = r.top + window.pageYOffset;
			end = start + art.offsetHeight - window.innerHeight;
		};
		var update = function () {
			ticking = false;
			var p = (window.pageYOffset - start) / Math.max(1, end - start);
			bar.style.transform = 'scaleX(' + Math.min(1, Math.max(0, p)) + ')';
		};
		var onScroll = function () {
			if (!ticking) { ticking = true; requestAnimationFrame(update); }
		};

		measure(); update();
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', function () { measure(); onScroll(); }, { passive: true });
		if ('ResizeObserver' in window) new ResizeObserver(function () { measure(); onScroll(); }).observe(art);
		else window.addEventListener('load', function () { measure(); update(); });
	}
})();
