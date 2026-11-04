( function () {
	var conversions = ( typeof vinhxdGA4Conversions !== 'undefined' ) ? vinhxdGA4Conversions : [];
	var internal    = ( typeof vinhxdGA4Internal !== 'undefined' ) ? vinhxdGA4Internal : null;

	// ============================================================
	// 0. Device ID bền qua các lần ghé thăm (localStorage + cookie),
	//    dùng để phát hiện 1 thiết bị đổi nhiều IP (VPN/4G quay số...)
	// ============================================================
	var DEVICE_KEY = 'vinhxd_device_id';
	function getDeviceId() {
		try {
			var id = localStorage.getItem( DEVICE_KEY );
			if ( ! id ) {
				var m = document.cookie.match( '(^|;)\\s*' + DEVICE_KEY + '\\s*=\\s*([^;]+)' );
				id = m ? m.pop() : '';
			}
			if ( ! id ) {
				id = 'dev_' + Math.random().toString( 36 ).substr( 2, 9 ) + '_' + Date.now();
			}
			localStorage.setItem( DEVICE_KEY, id );
			document.cookie = DEVICE_KEY + '=' + id + ';path=/;max-age=31536000';
			return id;
		} catch ( e ) {
			return '';
		}
	}
	var deviceId = getDeviceId();

	// ============================================================
	// 0b. Tín hiệu chống gian lận thu thập ở trình duyệt:
	//     - navigator.webdriver: trình duyệt có đang bị script điều khiển không
	//     - hadInteraction: có cuộn/di chuột/chạm trước khi click không
	//     - time_to_click: click sau bao lâu kể từ khi trang tải xong
	// ============================================================
	var pageLoadTime = Date.now();
	var hadInteraction = false;
	[ 'scroll', 'mousemove', 'touchstart', 'keydown' ].forEach( function ( evt ) {
		window.addEventListener( evt, function () { hadInteraction = true; }, { once: true, passive: true } );
	} );

	function isWebdriver() {
		return !! ( navigator.webdriver === true );
	}

	// ============================================================
	// 1. Bắn event lên GA4 (nếu gtag đã được nhúng qua code snippet)
	// ============================================================
	function fireGA4Event( rule ) {
		if ( typeof gtag !== 'function' ) return;
		gtag( 'event', rule.event_name, {
			event_category: 'vinhxd_conversion',
			event_label: rule.label,
		} );
	}

	// ============================================================
	// 2. Ghi log nội bộ (bảng Nhật ký chuyển đổi trong admin)
	// ============================================================
	function logInternal( type, destination, selector ) {
		if ( ! internal ) return;

		var fd = new FormData();
		fd.append( 'action', 'log_contact_click' );
		fd.append( 'type', type );
		fd.append( 'destination', destination || '' );
		fd.append( 'source_page', window.location.href );
		fd.append( 'selector', selector || '' );
		fd.append( 'device_id', deviceId );
		fd.append( 'webdriver', isWebdriver() ? '1' : '0' );
		fd.append( 'time_to_click', String( Date.now() - pageLoadTime ) );
		fd.append( 'had_interaction', hadInteraction ? '1' : '0' );

		// sendBeacon: trình duyệt vẫn gửi xong request kể cả khi trang đang chuyển sang zalo.me / app gọi điện
		// (fetch thường có thể bị huỷ giữa chừng khi trang unload => mất log). Fallback fetch keepalive.
		try {
			if ( navigator.sendBeacon && navigator.sendBeacon( internal.ajax_url, fd ) ) return;
		} catch ( e ) {}
		try {
			fetch( internal.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true } );
		} catch ( e ) {}
	}

	/** Tìm selector (trong danh sách quy định) khớp với phần tử được click */
	function matchInternalSelector( target ) {
		if ( ! internal || ! internal.rules ) return '';
		for ( var i = 0; i < internal.rules.length; i++ ) {
			var sel = internal.rules[ i ].selector;
			try {
				if ( target.closest && target.closest( sel ) ) return sel;
			} catch ( e ) { /* selector không hợp lệ thì bỏ qua */ }
		}
		return '';
	}

	// ============================================================
	// 3. Bắt sự kiện CLICK
	// ============================================================
	document.addEventListener( 'click', function ( e ) {
		// --- GA4 events theo quy định chuyển đổi ---
		conversions.forEach( function ( rule ) {
			if ( rule.trigger !== 'click' ) return;
			try {
				if ( e.target.closest && e.target.closest( rule.selector ) ) fireGA4Event( rule );
			} catch ( err ) {}
		} );

		// --- Log nội bộ ---
		if ( ! internal ) return;

		var a = e.target.closest( 'a' );
		var href = a ? ( a.getAttribute( 'href' ) || '' ) : '';
		var selector = matchInternalSelector( e.target );

		// Ưu tiên nhận diện theo loại link (gọi điện / Zalo), sau đó mới tới quy định chuyển đổi
		var type = '';
		if ( href.indexOf( 'tel:' ) === 0 ) type = 'call';
		else if ( href.indexOf( 'zalo.me' ) !== -1 ) type = 'zalo';
		else if ( selector ) type = 'click';

		if ( type ) logInternal( type, href, selector );
	}, true );

	// ============================================================
	// 4. Bắt sự kiện SUBMIT (form)
	// ============================================================
	document.addEventListener( 'submit', function ( e ) {
		conversions.forEach( function ( rule ) {
			if ( rule.trigger !== 'submit' ) return;
			try {
				if ( e.target && e.target.matches && e.target.matches( rule.selector ) ) fireGA4Event( rule );
			} catch ( err ) {}
		} );
	}, true );
} )();
