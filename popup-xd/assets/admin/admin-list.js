jQuery( function ( $ ) {
	'use strict';
	var cfg = window.PopupXDList || {};

	// Bật/Tắt popup ngay trong danh sách.
	$( document ).on( 'click', '.pxd-list-switch', function () {
		var $b = $( this );
		if ( $b.prop( 'disabled' ) ) {
			return;
		}
		var next = 'true' !== $b.attr( 'aria-checked' );
		$b.prop( 'disabled', true );
		$.post( cfg.ajaxUrl, { action: 'popupxd_toggle', nonce: cfg.nonce, id: $b.data( 'id' ), on: next ? '1' : '0' } )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					$b.attr( 'title', ( res && res.data && res.data.message ) || 'Không đổi được trạng thái.' ).find( '.pxd-list-switch-text' ).text( 'Lỗi' );
					return;
				}
				$b.attr( 'aria-checked', res.data.on ? 'true' : 'false' ).find( '.pxd-list-switch-text' ).text( res.data.on ? 'Bật' : 'Tắt' );
				var $row = $b.closest( 'tr' );
				$row.toggleClass( 'pxd-is-off', ! res.data.on );
				$row.find( '.pxd-list-summary' ).text( res.data.summary );
			} )
			.fail( function () {
				$b.attr( 'title', 'Không đổi được trạng thái, vui lòng thử lại.' ).find( '.pxd-list-switch-text' ).text( 'Lỗi' );
			} )
			.always( function () {
				$b.prop( 'disabled', false );
			} );
	} );
} );
