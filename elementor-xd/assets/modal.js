/* Elementor XD – cửa sổ nổi dùng chung (Cửa sổ nổi XD, Nút video XD). Dùng <dialog> gốc:
   có sẵn khoá focus, phím Esc, lớp nền, hiển thị ở lớp trên cùng (không bị cha overflow/transform cắt).
   - Cửa sổ nổi XD: <dialog class="exd-modal" id="..."> có sẵn trong widget (để tab Style của Elementor áp dụng).
   - Video/ảnh: 1 dialog tạo động, iframe chỉ được tạo khi mở và bị xoá khi đóng. */
(function (w, d) {
	'use strict';
	if (!w.EXD || w.EXD.modal) return;

	var CLOSE = '<button type="button" class="exd-modal__close" aria-label="Đóng"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>';
	var media, lastFocus;

	function videoNode(type, src) {
		var n;
		if (type === 'file') {
			n = d.createElement('video');
			n.src = src; n.controls = true; n.autoplay = true; n.playsInline = true;
		} else {
			n = d.createElement('iframe');
			n.src = src; n.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
			n.allowFullscreen = true; n.title = 'Video';
		}
		return n;
	}

	function onClose(e) {
		var dl = e.target;
		// Dừng video/iframe bên trong.
		[].forEach.call(dl.querySelectorAll('[data-exd-src]'), function (box) { box.innerHTML = ''; });
		if (dl === media) dl.querySelector('.exd-modal__body').innerHTML = '';
		if (!d.querySelector('dialog.exd-modal[open]')) d.documentElement.classList.remove('exd-lock');
		if (lastFocus && lastFocus.focus) { try { lastFocus.focus({ preventScroll: true }); } catch (x) {} }
		d.dispatchEvent(new CustomEvent('exd:modal-close', { detail: { dialog: dl } }));
	}

	/** Mở 1 <dialog class="exd-modal">. */
	function show(dl) {
		if (!dl || dl.open) return;
		lastFocus = d.activeElement;
		if (!dl.__exdBound) { dl.__exdBound = 1; dl.addEventListener('close', onClose); }
		// Video nhúng trong cửa sổ: tạo khi mở.
		[].forEach.call(dl.querySelectorAll('[data-exd-src]'), function (box) {
			if (!box.firstChild) box.appendChild(videoNode(box.getAttribute('data-exd-type'), box.getAttribute('data-exd-src')));
		});
		d.documentElement.classList.add('exd-lock');
		dl.showModal();
		d.dispatchEvent(new CustomEvent('exd:modal-open', { detail: { dialog: dl } }));
	}

	function close(dl) {
		dl = dl || d.querySelector('dialog.exd-modal[open]');
		if (dl && dl.open) dl.close();
	}

	/** Mở video / ảnh trong dialog dùng chung. */
	function openMedia(o) {
		if (!media) {
			media = d.createElement('dialog');
			media.className = 'exd-modal exd-modal--media';
			media.setAttribute('aria-label', 'Video');
			media.innerHTML = '<div class="exd-modal__box">' + CLOSE + '<div class="exd-modal__body"></div></div>';
			d.body.appendChild(media);
		}
		var body = media.querySelector('.exd-modal__body'), el;
		if (o.video) {
			el = d.createElement('div');
			el.className = 'exd-modal__video';
			el.appendChild(videoNode(o.video.type, o.video.src));
		} else {
			el = new Image();
			el.src = o.img.src; el.alt = o.img.alt || ''; el.className = 'exd-modal__img';
		}
		body.innerHTML = '';
		body.appendChild(el);
		show(media);
	}

	// Sự kiện toàn trang: gắn 1 lần.
	d.addEventListener('click', function (e) {
		var t = e.target;
		// Bấm nền tối (khung nội dung phủ kín dialog nên chỉ vùng nền mới có target là chính dialog).
		if (t.nodeName === 'DIALOG' && t.classList.contains('exd-modal')) {
			if (!t.hasAttribute('data-exd-noclose')) close(t);
			return;
		}
		var c = t.closest && t.closest('.exd-modal__close,[data-exd-close]');
		if (c) { close(c.closest('dialog')); return; }

		var a = t.closest && t.closest('[data-exd-video],[data-exd-img],[data-exd-open],a[href^="#exd-"]');
		if (!a) return;
		if (a.hasAttribute('data-exd-video')) {
			e.preventDefault();
			openMedia({ video: { type: a.getAttribute('data-exd-vtype') || 'iframe', src: a.getAttribute('data-exd-video') } });
		} else if (a.hasAttribute('data-exd-img')) {
			e.preventDefault();
			openMedia({ img: { src: a.getAttribute('data-exd-img'), alt: a.getAttribute('data-exd-alt') || '' } });
		} else {
			var id = a.getAttribute('data-exd-open') || a.getAttribute('href').slice(1);
			var dl = d.getElementById(id);
			if (!dl || dl.nodeName !== 'DIALOG') return;
			e.preventDefault();
			show(dl);
		}
	});

	w.EXD.modal = { show: show, close: close, media: openMedia };
})(window, document);
