/**
 * XD Analytics – AI traffic beacon (~1KB).
 * Chạy trên mọi trang, kể cả trang lấy từ cache (LiteSpeed...). Chỉ gửi 1 request khi referrer hoặc
 * utm_source khớp danh sách nguồn AI (vinhxdAI.h = host, vinhxdAI.s = utm). Khách bình thường: 0 request.
 * Server tính lại nguồn từ dữ liệu gửi lên (không tin client), lọc bot/admin, gộp trùng 30 phút.
 */
( function () {
	var cfg = window.vinhxdAI;
	if ( ! cfg || ! cfg.u ) return;

	function hostMatch( refHost, refPath ) {
		for ( var i = 0; i < cfg.h.length; i++ ) {
			var p = cfg.h[ i ], slash = p.indexOf( '/' );
			var ph = slash < 0 ? p : p.slice( 0, slash ), pp = slash < 0 ? '' : p.slice( slash );
			var ok = refHost === ph || refHost.slice( -( ph.length + 1 ) ) === '.' + ph;
			if ( ok && ( ! pp || refPath.indexOf( pp ) === 0 ) ) return true;
		}
		return false;
	}

	var ref = document.referrer || '', hit = false;
	try {
		if ( ref ) {
			var r = new URL( ref );
			var rh = r.hostname.toLowerCase().replace( /^www\./, '' );
			if ( rh !== location.hostname.toLowerCase().replace( /^www\./, '' ) ) hit = hostMatch( rh, r.pathname.toLowerCase() );
		}
		if ( ! hit ) {
			var utm = ( new URLSearchParams( location.search ).get( 'utm_source' ) || '' ).toLowerCase();
			hit = !! utm && cfg.s.indexOf( utm ) > -1;
		}
	} catch ( e ) { return; }
	if ( ! hit ) return;

	// 1 lần / tab / trang (F5 không gửi lại)
	var key = 'vxd_ai_' + location.pathname + location.search;
	try { if ( sessionStorage.getItem( key ) ) return; sessionStorage.setItem( key, '1' ); } catch ( e ) {}

	var body = new FormData();
	body.append( 'action', 'vinhxd_ai_hit' );
	body.append( 'ref', ref );
	body.append( 'url', location.href );
	if ( navigator.sendBeacon && navigator.sendBeacon( cfg.u, body ) ) return;
	if ( window.fetch ) fetch( cfg.u, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' } );
} )();
