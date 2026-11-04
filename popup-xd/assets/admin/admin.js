jQuery( function ( $ ) {
	'use strict';

	var cfg   = window.PopupXDAdmin || {};
	var $form = $( '#post' );
	var $box  = $( '.popupxd-postbox' );
	if ( ! $box.length ) {
		return;
	}

	var previewReady = false;
	var refreshOnReturn = false;

	function store( key, val ) {
		try {
			if ( undefined === val ) {
				return window.localStorage.getItem( key );
			}
			window.localStorage.setItem( key, val );
		} catch ( e ) {}
		return null;
	}

	function field( name ) {
		return $form.find( '[name="pxd_' + name + '"]' );
	}
	function checked( name ) {
		return field( name ).filter( ':checked' ).length > 0;
	}
	function radio( name ) {
		return field( name ).filter( ':checked' ).val();
	}
	function num( name, def ) {
		var n = parseFloat( field( name ).val() );
		return isNaN( n ) ? def : n;
	}

	/* ==================================================================
	 * Tabs (remember the last one)
	 * ================================================================ */

	function showTab( target ) {
		var $a = $( '.popupxd-tabs-nav a[href="' + target + '"]' );
		if ( ! $a.length ) {
			return;
		}
		$( '.popupxd-tabs-nav a' ).removeClass( 'nav-tab-active' );
		$a.addClass( 'nav-tab-active' );
		$( '.popupxd-tab-panel' ).hide();
		$( target ).show();
		store( 'popupxd_tab', target );
	}
	$( '.popupxd-tabs-nav a' ).on( 'click', function ( e ) {
		e.preventDefault();
		showTab( $( this ).attr( 'href' ) );
	} );
	var lastTab = store( 'popupxd_tab' );
	if ( lastTab && $( lastTab ).length ) {
		showTab( lastTab );
	}

	/* ==================================================================
	 * Colour pickers
	 * ================================================================ */

	if ( $.fn.wpColorPicker ) {
		$( '.pxd-color-field' ).wpColorPicker( {
			change: function () {
				window.setTimeout( onAnyChange, 0 );
			},
			clear: function () {
				window.setTimeout( onAnyChange, 0 );
			}
		} );
	}

	/* ==================================================================
	 * Show / hide / disable dependent rows
	 * ================================================================ */

	function syncVisibility() {
		var type = radio( 'content_type' );
		$( '.pxd-row-elementor' ).toggle( 'elementor' === type );
		$( '.pxd-row-editor' ).toggle( 'editor' === type );
		// The WordPress editor is only used for "Trình soạn thảo WordPress".
		$( '#postdivrich' ).toggle( 'elementor' !== type );

		$( '.pxd-row-specific' ).toggle( 'specific' === radio( 'display_scope' ) );

		var showClose = checked( 'show_close' );
		$( '.pxd-dep-close' ).toggle( showClose );
		var custom = 'custom' === field( 'close_position' ).val();
		$( '.pxd-close-custom' ).toggle( showClose && custom );
		$( '.pxd-close-gap' ).toggle( ! custom );
		$( '.pxd-outside-tip' ).prop( 'hidden', 0 !== String( field( 'close_position' ).val() ).indexOf( 'outside' ) );

		var pos = field( 'position' ).val();
		$( '.pxd-row-offset' ).toggle( 'center' !== pos );
		$( '.pxd-off' ).hide();
		$( '.pxd-off-' + pos ).show();

		// Inputs that only matter when their checkbox is ticked.
		$( '.pxd-dep[data-dep]' ).each( function () {
			var on = $form.find( '[name="' + $( this ).data( 'dep' ) + '"]' ).is( ':checked' );
			// Dimmed only (not disabled) so the value is still saved and kept.
			$( this ).toggleClass( 'is-off', ! on ).attr( 'aria-disabled', on ? null : 'true' );
		} );

		// Google intrusive-interstitial hint.
		var devices = $form.find( '[name="pxd_target_devices[]"]:checked' ).map( function () {
			return this.value;
		} ).get();
		var risky = checked( 'trigger_time' ) && num( 'time_delay', 3 ) < 5 && ( ! devices.length || -1 !== devices.indexOf( 'mobile' ) );
		$( '#pxd-google-warn' ).prop( 'hidden', ! risky );

		// On/off switch label.
		var $sw = $( '.pxd-switch' );
		var on = $sw.find( 'input' ).is( ':checked' );
		$sw.toggleClass( 'is-off', ! on );
		$sw.find( '.pxd-switch-label' ).text( $sw.find( '.pxd-switch-label' ).data( on ? 'on' : 'off' ) );
	}

	/* ==================================================================
	 * Live summary ("Hiện sau 3 giây · chỉ Desktop · 1 lần/7 ngày")
	 * Same wording as PopupXD_Helpers::summary() in PHP.
	 * ================================================================ */

	function summary() {
		var when = [];
		if ( checked( 'trigger_time' ) ) {
			when.push( 'sau ' + num( 'time_delay', 3 ) + ' giây' );
		}
		if ( checked( 'trigger_scroll' ) ) {
			when.push( 'khi cuộn ' + num( 'scroll_percent', 50 ) + '%' );
		}
		if ( checked( 'trigger_exit' ) ) {
			when.push( 'khi định thoát trang' );
		}
		var sel = $.trim( field( 'click_selector' ).val() || '' );
		if ( checked( 'trigger_click' ) && sel ) {
			when.push( 'khi click ' + sel );
		}
		var text;
		if ( when.length ) {
			text = 'Hiện ' + when.join( ' hoặc ' );
		} else if ( checked( 'trigger_manual' ) ) {
			text = 'Chỉ mở bằng nút / link';
		} else {
			text = 'Không có điều kiện mở';
		}

		var names = { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' };
		var devs = $form.find( '[name="pxd_target_devices[]"]:checked' ).map( function () {
			return names[ this.value ];
		} ).get();
		if ( devs.length && devs.length < 3 ) {
			text += ' · chỉ ' + devs.join( ', ' );
		}

		if ( 'specific' === radio( 'display_scope' ) ) {
			var nPages = $form.find( '[name="pxd_target_pages[]"]:checked' ).length;
			var nTypes = $form.find( '[name="pxd_target_post_types[]"]:checked' ).length;
			text += nPages || nTypes ? ' · ở ' + [ nPages ? nPages + ' trang' : '', nTypes ? nTypes + ' loại nội dung' : '' ].filter( Boolean ).join( ' + ' ) : ' · chưa chọn nơi hiển thị';
		} else {
			text += ' · toàn site';
		}

		if ( when.length && checked( 'cookie_enable' ) ) {
			var days = num( 'cookie_days', 7 );
			text += ' · mỗi người 1 lần/' + ( days > 0 ? days + ' ngày' : 'lượt truy cập' );
		}

		if ( ! $( '.pxd-switch input' ).is( ':checked' ) ) {
			text = 'ĐANG TẮT — ' + text;
		}
		$( '.pxd-summary-text' ).text( text );
		$( '.pxd-summary' ).toggleClass( 'is-off', ! $( '.pxd-switch input' ).is( ':checked' ) );
	}

	/* ==================================================================
	 * Inline validation
	 * ================================================================ */

	var BOX_TOKEN = /^(-?\d*\.?\d+)(px|em|rem|%|vw|vh)?$/i;

	function normalizeSelector( sel ) {
		sel = $.trim( sel || '' );
		if ( sel && ! /^[.#\[]/.test( sel ) && ! /[\s>+~,:]/.test( sel ) && /^-?[a-zA-Z_][\w-]*$/.test( sel ) ) {
			return '.' + sel;
		}
		return sel;
	}

	function validateOne( el ) {
		var $el = $( el );
		var rule = $el.data( 'pxd-validate' );
		var val = $.trim( $el.val() || '' );
		var msg = '';

		if ( val ) {
			if ( 'box' === rule ) {
				var parts = val.split( /\s+/ );
				var okBox = parts.length <= 4 && parts.every( function ( p ) {
					return BOX_TOKEN.test( p );
				} );
				if ( ! okBox ) {
					msg = 'Không hợp lệ. Nhập 1–4 giá trị cách nhau bằng dấu cách, vd: 20px hoặc 20px 24px.';
					if ( $el.data( 'pxd-default' ) ) {
						msg += ' Nếu lưu sẽ dùng mặc định ' + $el.data( 'pxd-default' ) + '.';
					}
				}
			} else if ( 'ids' === rule ) {
				if ( ! /^[\d\s,]+$/.test( val ) ) {
					msg = 'Chỉ nhập số ID, cách nhau bởi dấu phẩy (vd: 12, 45).';
				}
			} else if ( 'selector' === rule ) {
				try {
					document.querySelector( normalizeSelector( val ) );
				} catch ( e ) {
					msg = 'CSS selector không hợp lệ — popup sẽ không mở khi click.';
				}
			}
		}

		var $err = $el.siblings( '.pxd-field-error' );
		if ( msg ) {
			if ( ! $err.length ) {
				$err = $( '<span class="pxd-field-error" role="alert"></span>' ).insertAfter( $el );
			}
			$err.text( msg );
			$el.addClass( 'pxd-invalid' ).attr( 'aria-invalid', 'true' );
		} else {
			$err.remove();
			$el.removeClass( 'pxd-invalid' ).removeAttr( 'aria-invalid' );
		}
		return ! msg;
	}

	$form.on( 'input blur', '[data-pxd-validate]', function () {
		validateOne( this );
	} );
	$form.find( '[data-pxd-validate]' ).each( function () {
		validateOne( this );
	} );

	// Block saving while a field is invalid (capture phase: runs before
	// WordPress disables the buttons and shows its spinner).
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '#publish, #save-post' ) : null;
		if ( ! btn ) {
			return;
		}
		var $bad = $form.find( '[data-pxd-validate]' ).filter( function () {
			return ! this.disabled && ! validateOne( this );
		} );
		if ( ! $bad.length ) {
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		var $panel = $bad.first().closest( '.popupxd-tab-panel' );
		if ( $panel.length ) {
			showTab( '#' + $panel.attr( 'id' ) );
		}
		$bad.first().trigger( 'focus' );
		$bad.first()[0].scrollIntoView( { block: 'center' } );
		var $note = $( '.pxd-save-blocked' );
		if ( ! $note.length ) {
			$note = $( '<div class="notice notice-error inline pxd-save-blocked"><p></p></div>' ).prependTo( $box.find( '.inside' ) );
		}
		$note.find( 'p' ).text( 'Chưa lưu: có ' + $bad.length + ' ô nhập chưa hợp lệ (viền đỏ). Sửa lại rồi bấm Cập nhật.' );
		window.setTimeout( function () {
			$note.fadeOut( 300, function () {
				$note.remove();
			} );
		}, 6000 );
	}, true );

	/* ==================================================================
	 * Page picker: search + "selected only" + counter
	 * ================================================================ */

	var $pick = $( '.pxd-pagepick' );
	function filterPages() {
		var q = $.trim( $pick.find( '.pxd-pagepick-search' ).val() || '' ).toLowerCase();
		var onlySel = $pick.find( '.pxd-pagepick-selected-only' ).is( ':checked' );
		var shown = 0;
		$pick.find( '.pxd-pagepick-list label' ).each( function () {
			var $l = $( this );
			var match = ( ! q || -1 !== String( $l.data( 'title' ) ).indexOf( q ) || -1 !== ( '#' + $l.find( 'input' ).val() ).indexOf( q ) ) &&
				( ! onlySel || $l.find( 'input' ).is( ':checked' ) );
			$l.toggle( match );
			if ( match ) {
				shown++;
			}
		} );
		var n = $pick.find( 'input[type=checkbox][name]:checked' ).length;
		$pick.find( '.pxd-pagepick-count' ).text( 'Đã chọn ' + n + ' trang' + ( q && ! shown ? ' · không tìm thấy trang nào' : '' ) );
	}
	$pick.on( 'input change', 'input', filterPages );
	filterPages();

	/* ==================================================================
	 * Copy buttons (shortcode / link / JS)
	 * ================================================================ */

	function copyText( text, done ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done, function () {
				legacyCopy( text );
				done();
			} );
		} else {
			legacyCopy( text );
			done();
		}
	}
	function legacyCopy( text ) {
		var $t = $( '<textarea readonly style="position:fixed;left:-9999px;top:0;">' ).val( text ).appendTo( document.body );
		$t[0].select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {}
		$t.remove();
	}
	$box.on( 'click', '.pxd-copy-btn', function () {
		var $b = $( this );
		var text = $b.siblings( 'input' ).val();
		copyText( text, function () {
			$b.addClass( 'is-copied' ).find( '.pxd-copy-label' ).text( 'Đã sao chép' );
			window.setTimeout( function () {
				$b.removeClass( 'is-copied' ).find( '.pxd-copy-label' ).text( 'Sao chép' );
			}, 1600 );
		} );
	} );
	// Keep the shortcode in sync with the button text.
	field( 'button_text' ).on( 'input', function () {
		var $sc = $box.find( '.pxd-copy input' ).first();
		$sc.val( '[popupxd id="' + cfg.popupId + '"]' + $( this ).val() + '[/popupxd]' );
		$sc.siblings( '.pxd-copy-btn' ).attr( 'data-copy', $sc.val() );
	} );

	/* ==================================================================
	 * Elementor template: "Sửa mẫu" / "Tạo mẫu mới" (new tab)
	 * ================================================================ */

	var $select  = $( '#pxd-template-select' );
	var $editBtn = $( '#pxd-edit-template' );
	var $newBtn  = $( '#pxd-new-template' );
	var $msg     = $( '.pxd-template-msg' );

	function editUrl( id ) {
		return String( cfg.editUrl || '' ).replace( '__ID__', encodeURIComponent( id ) );
	}
	function syncEditButton() {
		var id = $select.val();
		if ( id ) {
			$editBtn.attr( { href: editUrl( id ), 'aria-disabled': 'false' } ).removeClass( 'disabled' );
		} else {
			$editBtn.attr( { href: '#', 'aria-disabled': 'true' } ).addClass( 'disabled' );
		}
	}
	function message( text, isError ) {
		$msg.text( text || '' ).toggleClass( 'is-error', !! isError ).toggle( !! text );
	}
	$select.on( 'change', syncEditButton );
	syncEditButton();
	message( '' );

	$editBtn.on( 'click', function ( e ) {
		if ( ! $select.val() ) {
			e.preventDefault();
			message( 'Hãy chọn một mẫu trước, hoặc bấm "Tạo mẫu mới".', true );
			return;
		}
		message( '' );
		refreshOnReturn = true;
	} );

	$newBtn.on( 'click', function () {
		if ( $newBtn.prop( 'disabled' ) ) {
			return;
		}
		// Open the tab inside the click so pop-up blockers allow it.
		var win = window.open( '', '_blank' );
		if ( win ) {
			try {
				win.document.title = 'Đang tạo mẫu…';
				win.document.body.innerHTML = '<p style="font:14px/1.5 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#646970;padding:40px;text-align:center;">Đang tạo mẫu Elementor…</p>';
			} catch ( err ) {}
		}
		$newBtn.prop( 'disabled', true );
		message( 'Đang tạo mẫu…' );

		function fail( m ) {
			message( m || 'Không tạo được mẫu, vui lòng thử lại.', true );
			if ( win && ! win.closed ) {
				win.close();
			}
		}

		$.post( cfg.ajaxUrl, {
			action: cfg.createAction,
			nonce: cfg.createNonce,
			popup_id: cfg.popupId,
			title: $( '#title' ).val() || ''
		} ).done( function ( res ) {
			if ( ! res || ! res.success ) {
				fail( res && res.data && res.data.message );
				return;
			}
			var d = res.data;
			$select.find( 'option[value="' + d.id + '"]' ).remove();
			$( '<option>' ).val( d.id ).text( d.title ).appendTo( $select );
			$select.val( String( d.id ) ).trigger( 'change' );
			field( 'content_type' ).filter( '[value="elementor"]' ).prop( 'checked', true ).trigger( 'change' );
			$( '.pxd-no-templates' ).remove();
			message( 'Đã tạo "' + d.title + '" và chọn sẵn. Thiết kế xong trong tab Elementor, quay lại đây và bấm Cập nhật để lưu popup.' );
			refreshOnReturn = true;
			if ( win && ! win.closed ) {
				win.location.href = d.edit_url;
			} else {
				window.open( d.edit_url, '_blank' );
			}
		} ).fail( function ( xhr ) {
			fail( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message );
		} ).always( function () {
			$newBtn.prop( 'disabled', false );
		} );
	} );

	/* ==================================================================
	 * Live preview
	 * ================================================================ */

	var $preview = $( '#pxd-preview' );
	var DEVICES = {
		desktop: { w: 1280, h: 800, label: 'Desktop' },
		tablet:  { w: 820,  h: 1080, label: 'Tablet' },
		mobile:  { w: 390,  h: 780, label: 'Mobile' }
	};
	var device   = DEVICES[ store( 'popupxd_preview_device' ) ] ? store( 'popupxd_preview_device' ) : 'desktop';
	var $frame   = $( '#pxd-preview-frame' );
	var $stage   = $preview.find( '.pxd-preview-stage' );
	var $vp      = $preview.find( '.pxd-preview-viewport' );
	var $meta    = $preview.find( '.pxd-preview-meta' );
	var $loading = $preview.find( '.pxd-preview-loading' );
	var timer    = null;
	var scale    = 1;

	function isSideBySide() {
		return $box.find( '.pxd-layout' ).css( 'grid-template-columns' ) && $box.find( '.pxd-side' ).offset().top < $box.find( '.pxd-settings' ).offset().top + 60;
	}

	function layout() {
		if ( ! $preview.length ) {
			return;
		}
		var d = DEVICES[ device ];
		var avail = $stage.innerWidth();
		scale = Math.min( 1, avail / d.w );
		var maxH = Math.max( 320, Math.round( window.innerHeight * ( isSideBySide() ? 0.68 : 0.75 ) ) );
		var h = Math.min( d.h, Math.round( maxH / scale ) );
		$frame.css( { width: d.w + 'px', height: h + 'px', transform: 'scale(' + scale + ')' } );
		$vp.css( { width: Math.round( d.w * scale ) + 'px', height: Math.round( h * scale ) + 'px' } );
		$preview.find( '.pxd-dev' ).each( function () {
			var on = $( this ).data( 'device' ) === device;
			$( this ).toggleClass( 'is-active', on ).attr( 'aria-pressed', on ? 'true' : 'false' );
		} );
		updateMeta();
	}

	function editorContent() {
		if ( window.tinymce ) {
			var ed = window.tinymce.get( 'content' );
			if ( ed && ! ed.isHidden() ) {
				return ed.getContent();
			}
		}
		return $( '#content' ).val() || '';
	}

	function reload() {
		if ( ! previewReady ) {
			return;
		}
		window.clearTimeout( timer );
		$loading.addClass( 'is-on' );

		var $f = $( '<form>', { method: 'post', action: cfg.previewUrl, target: 'pxd-preview-frame' } ).css( 'display', 'none' );
		function add( name, value ) {
			$( '<input>', { type: 'hidden', name: name, value: value } ).appendTo( $f );
		}
		$form.find( ':input[name^="pxd_"]' ).each( function () {
			var el = this;
			if ( ! el.name ) {
				return;
			}
			if ( ( 'checkbox' === el.type || 'radio' === el.type ) && ! el.checked ) {
				return;
			}
			if ( 'SELECT' === el.tagName && el.multiple ) {
				$( el ).find( 'option:selected' ).each( function () {
					add( el.name, this.value );
				} );
				return;
			}
			add( el.name, $( el ).val() );
		} );
		add( 'popup_id', cfg.popupId );
		add( '_pxdnonce', cfg.previewNonce );
		add( 'post_title', $( '#title' ).val() || '' );
		if ( 'editor' === radio( 'content_type' ) ) {
			add( 'pxd_preview_content', editorContent() );
		}
		$f.appendTo( document.body ).trigger( 'submit' ).remove();
	}

	function schedulePreview() {
		if ( ! previewReady ) {
			return;
		}
		window.clearTimeout( timer );
		timer = window.setTimeout( reload, 600 );
	}

	function updateMeta() {
		var d = DEVICES[ device ];
		var parts = [ d.label + ' ' + d.w + 'px' + ( scale < 1 ? ' (thu nhỏ ' + Math.round( scale * 100 ) + '%)' : '' ) ];
		var maxW = parseInt( field( 'max_width' ).val(), 10 );
		if ( maxW ) {
			parts.push( 'Rộng tối đa ' + maxW + 'px' );
		}
		var box = null;
		try {
			var doc = $frame[0].contentDocument;
			box = doc && doc.querySelector( '.popupxd-overlay .popupxd-box' );
		} catch ( e ) {}
		if ( box ) {
			var r = box.getBoundingClientRect();
			if ( r.width ) {
				parts.push( 'Hiển thị thực tế: ' + Math.round( r.width ) + ' × ' + Math.round( r.height ) + 'px' );
			}
		}
		$meta.text( parts.join( '  ·  ' ) );
	}

	if ( $preview.length && cfg.previewUrl ) {
		previewReady = true;

		$frame.on( 'load', function () {
			$loading.removeClass( 'is-on' );
			window.setTimeout( updateMeta, 450 );
			window.setTimeout( updateMeta, 1500 );
		} );
		$preview.on( 'click', '.pxd-dev', function () {
			device = $( this ).data( 'device' );
			store( 'popupxd_preview_device', device );
			layout();
			window.setTimeout( updateMeta, 450 );
		} );
		$preview.on( 'click', '.pxd-preview-reload', reload );

		$( '#title' ).on( 'input', schedulePreview );
		$( '#content' ).on( 'input', function () {
			if ( 'editor' === radio( 'content_type' ) ) {
				schedulePreview();
			}
		} );
		$( document ).on( 'tinymce-editor-init', function ( e, ed ) {
			if ( ed && 'content' === ed.id ) {
				ed.on( 'change keyup undo redo SetContent', function () {
					if ( 'editor' === radio( 'content_type' ) ) {
						schedulePreview();
					}
				} );
			}
		} );

		// Back from the Elementor tab → show the updated template.
		var onReturn = function () {
			if ( refreshOnReturn ) {
				refreshOnReturn = false;
				reload();
			}
		};
		$( window ).on( 'focus', onReturn );
		document.addEventListener( 'visibilitychange', function () {
			if ( 'visible' === document.visibilityState ) {
				onReturn();
			}
		} );

		$( window ).on( 'resize', function () {
			window.clearTimeout( $preview.data( 'rt' ) );
			$preview.data( 'rt', window.setTimeout( layout, 150 ) );
		} );
	}

	/* ==================================================================
	 * Wiring
	 * ================================================================ */

	function onAnyChange( e ) {
		syncVisibility();
		summary();
		if ( e && e.target && 'pxd_max_width' === e.target.name ) {
			updateMeta();
		}
		// The on/off switch and the page search don't change the design.
		if ( e && e.target && ( 'pxd_enabled' === e.target.name || $( e.target ).is( '.pxd-pagepick-search, .pxd-pagepick-selected-only' ) ) ) {
			return;
		}
		schedulePreview();
	}

	$box.on( 'input change', ':input', onAnyChange );

	syncVisibility();
	summary();
	layout();
	reload();
} );
