/* XD Extra – frontend (không cần jQuery) */
(function () {
    'use strict';

    // Nút lên đầu trang: cuộn passive + requestAnimationFrame
    var btn = document.getElementById('xd-back-to-top');
    if (btn) {
        var ticking = false;
        var update = function () {
            btn.classList.toggle('show', window.pageYOffset > 300);
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
        }, { passive: true });
        update();
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;

        if (t.closest('#xd-back-to-top')) {
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
            return;
        }

        // [xd_uu_dai]: mở JetPopup
        var jp = t.closest('[data-xd-jetpopup]');
        if (jp) {
            var pid = parseInt(jp.getAttribute('data-xd-jetpopup'), 10);
            if (window.JetPopupFrontend && window.JetPopupFrontend.popupManager) {
                window.JetPopupFrontend.popupManager.openPopup(pid);
            } else if (window.jQuery) {
                window.jQuery(document).trigger('jet-popup/open-popup', { id: pid });
            }
            return;
        }

        // [xd_uu_dai]: popup form nội bộ
        var op = t.closest('[data-xd-open]');
        if (op) {
            var box = document.getElementById(op.getAttribute('data-xd-open'));
            if (box) box.style.display = 'flex';
            return;
        }
        if (t.closest('.xd-uudai-close') || t.classList.contains('xd-uudai-overlay')) {
            var ov = t.closest('.xd-uudai-overlay');
            if (ov) ov.style.display = 'none';
        }

        // [xd_lien_he]: tải Google Maps khi khách bấm
        var mb = t.closest('[data-xd-map]');
        if (mb) {
            var wrap = mb.closest('.xd-lh-map');
            if (wrap && !wrap.querySelector('iframe')) {
                var f = document.createElement('iframe');
                f.src = mb.getAttribute('data-xd-map');
                f.title = 'Bản đồ';
                f.loading = 'lazy';
                f.referrerPolicy = 'no-referrer-when-downgrade';
                f.setAttribute('allowfullscreen', '');
                wrap.appendChild(f);
                wrap.classList.add('is-loaded');
            }
        }
    });
})();
