/* Elementor XD – lõi JS (~1KB): khởi tạo widget 1 lần, chạy cả ngoài trang lẫn trong Elementor editor. */
(function (w, d) {
	'use strict';
	if (w.EXD) return;
	var list = [], hooked = false;

	function run(item, root) {
		var els = root.matches && root.matches(item.sel) ? [root] : [];
		els = els.concat([].slice.call(root.querySelectorAll(item.sel)));
		els.forEach(function (el) {
			if (el.__exd && el.__exd[item.sel]) return;
			(el.__exd = el.__exd || {})[item.sel] = 1;
			try { item.fn(el); } catch (e) { if (w.console) console.error('[EXD]', e); }
		});
	}

	function hookElementor() {
		if (hooked || !w.elementorFrontend || !w.elementorFrontend.hooks) return;
		hooked = true;
		list.forEach(addHook);
	}
	function addHook(item) {
		w.elementorFrontend.hooks.addAction('frontend/element_ready/' + item.name + '.default', function ($scope) {
			run(item, $scope[0] || $scope);
		});
	}

	w.EXD = {
		/** EXD.on('exd-slider', '.exd-slider', function (el) {...}) */
		on: function (name, sel, fn) {
			var item = { name: name, sel: sel, fn: fn };
			list.push(item);
			if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', function () { run(item, d); });
			else run(item, d);
			if (hooked) addHook(item);
		},
		reduced: w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches
	};

	hookElementor();
	if (w.jQuery) w.jQuery(w).on('elementor/frontend/init', hookElementor);
})(window, document);
