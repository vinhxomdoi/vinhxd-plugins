/*!
 * Popup XD — frontend
 */
(function () {
	'use strict';

	var BASE_Z = 100000;
	var HIDE_DELAY = 350;
	var FOCUSABLE = 'a[href],area[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),iframe,object,embed,[contenteditable="true"],[tabindex]:not([tabindex="-1"])';

	// Same breakpoints as the "Theo thiết bị" setting.
	var DEVICE_QUERIES = {
		mobile: '(max-width: 767px)',
		tablet: '(min-width: 768px) and (max-width: 1024px)',
		desktop: '(min-width: 1025px)'
	};

	var openStack = []; // currently open overlays, last = top-most
	var queue = [];     // auto-triggered overlays waiting for the screen to be free
	var scrollLocked = false;
	var savedBodyPadding = '';

	/* ------------------------------------------------------------------
	 * Utilities
	 * ---------------------------------------------------------------- */

	function attr( el, name ) {
		return el.getAttribute( name ) || '';
	}

	function getCookie( name ) {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + name + '=([^;]*)' ) );
		return match ? decodeURIComponent( match[1] ) : null;
	}

	function setCookie( name, value, days ) {
		var expires = '';
		var n = parseInt( days, 10 );
		if ( n > 0 ) {
			var d = new Date();
			d.setTime( d.getTime() + n * 864e5 );
			expires = '; expires=' + d.toUTCString();
		}
		document.cookie = name + '=' + encodeURIComponent( value ) + expires + '; path=/; SameSite=Lax';
	}

	function cookieName( overlay ) {
		return 'popupxd_seen_' + attr( overlay, 'data-popupxd-id' );
	}

	function emit( name, overlay ) {
		var detail = { id: attr( overlay, 'data-popupxd-id' ), overlay: overlay };
		var ev;
		try {
			ev = new CustomEvent( name, { detail: detail } );
		} catch ( e ) {
			ev = document.createEvent( 'CustomEvent' );
			ev.initCustomEvent( name, false, false, detail );
		}
		document.dispatchEvent( ev );
	}

	function fireResize() {
		var ev;
		try {
			ev = new Event( 'resize' );
		} catch ( e ) {
			ev = document.createEvent( 'UIEvents' );
			ev.initUIEvent( 'resize', true, false, window, 0 );
		}
		window.dispatchEvent( ev );
	}

	function elementFrom( target ) {
		// Clicks can land on text nodes in some browsers.
		while ( target && 1 !== target.nodeType ) {
			target = target.parentNode;
		}
		return target && target.closest ? target : null;
	}

	/**
	 * Admins often type a bare Elementor class name ("baogiaxd") instead of a
	 * selector (".baogiaxd"); add the dot for them.
	 */
	function normalizeSelector( sel ) {
		sel = ( sel || '' ).trim();
		if ( ! sel ) {
			return sel;
		}
		if ( /^[.#\[]/.test( sel ) || /[\s>+~,:]/.test( sel ) ) {
			return sel;
		}
		if ( /^-?[a-zA-Z_][\w-]*$/.test( sel ) ) {
			return '.' + sel;
		}
		return sel;
	}

	/** Returns a usable selector, or '' if it is invalid (never throws). */
	function safeSelector( raw, id ) {
		var sel = normalizeSelector( raw );
		if ( ! sel ) {
			return '';
		}
		try {
			document.querySelector( sel );
			return sel;
		} catch ( e ) {
			if ( window.console ) {
				window.console.warn( '[Popup XD] Popup #' + id + ': CSS selector không hợp lệ: "' + raw + '"' );
			}
			return '';
		}
	}

	function deviceAllowed( overlay ) {
		if ( ! window.matchMedia ) {
			return true;
		}
		for ( var device in DEVICE_QUERIES ) {
			if ( window.matchMedia( DEVICE_QUERIES[ device ] ).matches ) {
				return overlay.classList.contains( 'pxd-dev-' + device );
			}
		}
		return true;
	}

	function isOpen( overlay ) {
		return overlay.classList.contains( 'popupxd-active' );
	}

	function doneCookieName( overlay ) {
		return 'popupxd_done_' + attr( overlay, 'data-popupxd-id' );
	}

	/** Visitor already submitted the form inside this popup. */
	function formDone( overlay ) {
		return null !== getCookie( doneCookieName( overlay ) );
	}

	/** Blocks automatic triggers (manual buttons / click selector ignore it). */
	function alreadySeen( overlay ) {
		if ( formDone( overlay ) ) {
			return true;
		}
		return '1' === attr( overlay, 'data-cookie' ) && null !== getCookie( cookieName( overlay ) );
	}

	function markSeen( overlay ) {
		if ( '1' === attr( overlay, 'data-cookie' ) ) {
			setCookie( cookieName( overlay ), '1', attr( overlay, 'data-cookie-days' ) || '0' );
		}
	}

	/* ------------------------------------------------------------------
	 * Analytics: events for Google Tag Manager (dataLayer)
	 *   popupxd_open, popupxd_close, popupxd_click, popupxd_form_submit
	 * ---------------------------------------------------------------- */

	function track( name, overlay, extra ) {
		if ( window.PopupXDPreview ) {
			return; // admin preview frame
		}
		var data = {
			event: 'popupxd_' + name,
			popupxd_id: attr( overlay, 'data-popupxd-id' ),
			popupxd_title: attr( overlay, 'data-popupxd-title' )
		};
		for ( var k in extra ) {
			if ( Object.prototype.hasOwnProperty.call( extra, k ) ) {
				data[ k ] = extra[ k ];
			}
		}
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( data );
	}

	function isBuilderPreview() {
		return document.body.classList.contains( 'elementor-editor-active' ) ||
			!! ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() );
	}

	/* ------------------------------------------------------------------
	 * Scroll lock — only while at least one open popup asks for it.
	 * Compensates the scrollbar width so the page doesn't jump sideways.
	 * ---------------------------------------------------------------- */

	function updateScrollLock() {
		var need = openStack.some( function ( o ) {
			return '1' === attr( o, 'data-disable-scroll' );
		} );
		var html = document.documentElement;
		var body = document.body;

		if ( need && ! scrollLocked ) {
			var gap = window.innerWidth - html.clientWidth;
			savedBodyPadding = body.style.paddingRight;
			if ( gap > 0 ) {
				var current = parseFloat( window.getComputedStyle( body ).paddingRight ) || 0;
				body.style.paddingRight = ( current + gap ) + 'px';
			}
			html.classList.add( 'popupxd-noscroll' );
			body.classList.add( 'popupxd-noscroll' );
			scrollLocked = true;
		} else if ( ! need && scrollLocked ) {
			html.classList.remove( 'popupxd-noscroll' );
			body.classList.remove( 'popupxd-noscroll' );
			body.style.paddingRight = savedBodyPadding;
			scrollLocked = false;
		}
	}

	/* ------------------------------------------------------------------
	 * Open / close
	 * ---------------------------------------------------------------- */

	/**
	 * @param {Element} overlay
	 * @param {Object}  opts  manual: opened by an explicit user action
	 *                        (button / link / click selector) — may stack on
	 *                        top of another popup and ignores the device rule.
	 */
	function openPopup( overlay, opts ) {
		opts = opts || {};
		if ( ! overlay || isOpen( overlay ) ) {
			return false;
		}
		if ( ! opts.manual ) {
			if ( ! deviceAllowed( overlay ) ) {
				return false;
			}
			// One auto popup at a time: wait until the screen is free.
			if ( openStack.length ) {
				if ( -1 === queue.indexOf( overlay ) ) {
					overlay._pxdQueuedTrigger = opts.trigger;
					queue.push( overlay );
				}
				return false;
			}
		}

		window.clearTimeout( overlay._pxdHideTimer );
		window.clearTimeout( overlay._pxdReopenTimer );

		var active = document.activeElement;
		overlay._pxdReturnFocus = active && active !== document.body ? active : null;

		overlay.style.zIndex = String( BASE_Z + openStack.length );
		overlay.style.display = 'flex';
		void overlay.offsetWidth; // reflow so the entrance transition runs
		overlay.classList.add( 'popupxd-active' );
		overlay.setAttribute( 'aria-hidden', 'false' );

		openStack.push( overlay );
		updateScrollLock();

		// "Tính là đã xem khi: popup hiện ra" → set the cookie right away.
		if ( 'show' === attr( overlay, 'data-count-mode' ) ) {
			markSeen( overlay );
		}

		var box = overlay.querySelector( '.popupxd-box' );
		if ( box && ! opts.noFocus ) {
			try {
				box.focus( { preventScroll: true } );
			} catch ( e ) {
				box.focus();
			}
		}

		// Sliders / carousels (Elementor, Swiper…) initialised while the popup
		// was display:none measured 0px — make them re-measure.
		fireResize();
		window.setTimeout( fireResize, HIDE_DELAY );

		emit( 'popupxd:open', overlay );
		track( 'open', overlay, { popupxd_trigger: opts.trigger || 'api' } );
		return true;
	}

	function closePopup( overlay, method ) {
		if ( ! overlay || ! isOpen( overlay ) ) {
			return;
		}
		method = 'string' === typeof method ? method : 'api';
		window.clearTimeout( overlay._pxdFormTimer );
		overlay.classList.remove( 'popupxd-active' );
		overlay.setAttribute( 'aria-hidden', 'true' );
		markSeen( overlay );

		var idx = openStack.indexOf( overlay );
		if ( idx > -1 ) {
			openStack.splice( idx, 1 );
		}
		updateScrollLock();

		overlay._pxdHideTimer = window.setTimeout( function () {
			if ( ! isOpen( overlay ) ) {
				overlay.style.display = 'none';
			}
		}, HIDE_DELAY );

		var back = overlay._pxdReturnFocus;
		overlay._pxdReturnFocus = null;
		if ( back && document.contains( back ) && back.focus ) {
			try {
				back.focus( { preventScroll: true } );
			} catch ( e ) {
				back.focus();
			}
		}

		// "Tự động hiện lại sau khi đóng" (opt-in, ignores the cookie on purpose).
		if ( '1' === attr( overlay, 'data-reopen' ) && 'form' !== method && ! formDone( overlay ) ) {
			var secs = parseFloat( attr( overlay, 'data-reopen-seconds' ) || '0' );
			if ( secs > 0 ) {
				overlay._pxdReopenTimer = window.setTimeout( function () {
					openPopup( overlay, { trigger: 'reopen' } );
				}, secs * 1000 );
			}
		}

		emit( 'popupxd:close', overlay );
		track( 'close', overlay, { popupxd_close_method: method } );

		if ( ! openStack.length ) {
			window.setTimeout( flushQueue, HIDE_DELAY + 50 );
		}
	}

	function flushQueue() {
		while ( queue.length && ! openStack.length ) {
			var next = queue.shift();
			if ( ! isOpen( next ) && ! alreadySeen( next ) && openPopup( next, { trigger: next._pxdQueuedTrigger } ) ) {
				return;
			}
		}
	}

	function topPopup() {
		return openStack.length ? openStack[ openStack.length - 1 ] : null;
	}

	function findOverlay( id ) {
		return document.getElementById( 'popupxd-' + String( id ).replace( /[^0-9]/g, '' ) );
	}

	/* ------------------------------------------------------------------
	 * Triggers
	 * ---------------------------------------------------------------- */

	function initOverlay( overlay ) {
		var id = attr( overlay, 'data-popupxd-id' );

		var closeBtn = overlay.querySelector( '.popupxd-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				closePopup( overlay, 'button' );
			} );
		}

		if ( '1' === attr( overlay, 'data-close-outside' ) ) {
			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					closePopup( overlay, 'overlay' );
				}
			} );
		}

		// Clicks on links / buttons inside the popup (for GTM).
		overlay.addEventListener( 'click', function ( e ) {
			var t = elementFrom( e.target );
			var c = t && t.closest( 'a[href], button, [role="button"], input[type="submit"]' );
			if ( ! c || ! overlay.contains( c ) || c.classList.contains( 'popupxd-close' ) ) {
				return;
			}
			track( 'click', overlay, {
				popupxd_click_text: ( c.innerText || c.value || c.getAttribute( 'aria-label' ) || '' ).trim().slice( 0, 100 ),
				popupxd_click_url: c.getAttribute( 'href' ) || ''
			} );
		} );

		// Click selector = explicit user action → works even after the
		// "don't show again" cookie is set. Respects device targeting: on a
		// device where the popup is off, the element keeps its normal behaviour.
		if ( 'yes' === attr( overlay, 'data-trigger-click' ) ) {
			var selector = safeSelector( attr( overlay, 'data-click-selector' ), id );
			if ( selector ) {
				document.addEventListener( 'click', function ( e ) {
					var target = elementFrom( e.target );
					if ( ! target || overlay.contains( target ) || ! deviceAllowed( overlay ) ) {
						return;
					}
					if ( target.closest( selector ) ) {
						e.preventDefault();
						openPopup( overlay, { manual: true, trigger: 'click_selector' } );
					}
				} );
			}
		}

		if ( alreadySeen( overlay ) || isBuilderPreview() ) {
			return;
		}

		// Time
		if ( 'yes' === attr( overlay, 'data-trigger-time' ) ) {
			var delay = Math.max( 0, parseFloat( attr( overlay, 'data-time-delay' ) || '0' ) ) * 1000;
			window.setTimeout( function () {
				if ( ! alreadySeen( overlay ) ) {
					openPopup( overlay, { trigger: 'time' } );
				}
			}, delay );
		}

		// Scroll — rAF-throttled, listener removed once fired.
		if ( 'yes' === attr( overlay, 'data-trigger-scroll' ) ) {
			var targetPercent = parseFloat( attr( overlay, 'data-scroll-percent' ) || '50' );
			var ticking = false;
			var onScroll = function () {
				if ( ticking ) {
					return;
				}
				ticking = true;
				window.requestAnimationFrame( function () {
					ticking = false;
					var doc = document.documentElement;
					var height = Math.max( doc.scrollHeight, document.body.scrollHeight ) - window.innerHeight;
					if ( height <= 0 ) {
						return;
					}
					var top = window.pageYOffset || doc.scrollTop;
					if ( ( top / height ) * 100 >= targetPercent ) {
						window.removeEventListener( 'scroll', onScroll );
						if ( ! alreadySeen( overlay ) ) {
							openPopup( overlay, { trigger: 'scroll' } );
						}
					}
				} );
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
		}

		// Exit intent (pointer leaves through the top of the window — desktop only).
		if ( 'yes' === attr( overlay, 'data-trigger-exit' ) && window.matchMedia && window.matchMedia( '(hover: hover)' ).matches ) {
			var onLeave = function ( e ) {
				if ( e.clientY <= 0 && ! e.relatedTarget ) {
					document.removeEventListener( 'mouseout', onLeave );
					if ( ! alreadySeen( overlay ) ) {
						openPopup( overlay, { trigger: 'exit_intent' } );
					}
				}
			};
			document.addEventListener( 'mouseout', onLeave );
		}
	}

	function initGlobalHandlers() {
		// Shortcode buttons ([data-popupxd-open="ID"]) and plain links to
		// "#popupxd-ID" (handy for Elementor Button widgets).
		document.addEventListener( 'click', function ( e ) {
			var target = elementFrom( e.target );
			if ( ! target ) {
				return;
			}
			var trigger = target.closest( '[data-popupxd-open], a[href^="#popupxd-"]' );
			if ( ! trigger ) {
				return;
			}
			var id = trigger.hasAttribute( 'data-popupxd-open' )
				? attr( trigger, 'data-popupxd-open' )
				: attr( trigger, 'href' ).replace( '#popupxd-', '' );
			var overlay = findOverlay( id );
			if ( overlay ) {
				e.preventDefault();
				openPopup( overlay, { manual: true, trigger: trigger.hasAttribute( 'data-popupxd-open' ) ? 'button' : 'link' } );
			}
		} );

		initFormHandlers();

		document.addEventListener( 'keydown', function ( e ) {
			var top = topPopup();
			if ( ! top ) {
				return;
			}
			if ( 'Escape' === e.key || 'Esc' === e.key ) {
				closePopup( top, 'esc' );
				return;
			}
			// Keep keyboard focus inside the top-most popup.
			if ( 'Tab' === e.key ) {
				var box = top.querySelector( '.popupxd-box' );
				var items = Array.prototype.filter.call( box.querySelectorAll( FOCUSABLE ), function ( el ) {
					return el.offsetWidth || el.offsetHeight || el.getClientRects().length;
				} );
				if ( ! items.length ) {
					e.preventDefault();
					box.focus();
					return;
				}
				var first = items[0];
				var last = items[ items.length - 1 ];
				if ( e.shiftKey && ( document.activeElement === first || document.activeElement === box ) ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Form submitted successfully inside a popup
	 * (Elementor Pro Forms, Contact Form 7, Fluent Forms)
	 * ---------------------------------------------------------------- */

	function onFormSuccess( target, source ) {
		var el = elementFrom( target );
		var overlay = el && el.closest( '.popupxd-overlay' );
		if ( ! overlay ) {
			return;
		}
		track( 'form_submit', overlay, { popupxd_form: source } );
		emit( 'popupxd:form_success', overlay );

		// Don't auto-show again to someone who already converted.
		var hideDays = parseInt( attr( overlay, 'data-form-hide-days' ), 10 );
		if ( hideDays >= 0 ) {
			setCookie( doneCookieName( overlay ), '1', hideDays );
		}
		window.clearTimeout( overlay._pxdReopenTimer );

		// Close after N seconds (time to read the thank-you message).
		var delay = parseFloat( attr( overlay, 'data-form-close' ) );
		if ( delay >= 0 ) {
			window.clearTimeout( overlay._pxdFormTimer );
			overlay._pxdFormTimer = window.setTimeout( function () {
				closePopup( overlay, 'form' );
			}, delay * 1000 );
		}
	}

	function initFormHandlers() {
		// Contact Form 7 (native DOM event on the .wpcf7 wrapper).
		document.addEventListener( 'wpcf7mailsent', function ( e ) {
			onFormSuccess( e.target, 'cf7' );
		} );
		// Elementor Pro Forms + Fluent Forms fire jQuery events on the <form>.
		var $ = window.jQuery;
		if ( $ ) {
			$( document ).on( 'submit_success', function ( e ) {
				onFormSuccess( e.target, 'elementor' );
			} );
			$( document ).on( 'fluentform_submission_success', function ( e, data ) {
				var form = data && data.form && data.form[0] ? data.form[0] : e.target;
				onFormSuccess( form, 'fluentform' );
			} );
		}
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '.popupxd-overlay' ), initOverlay );
		initGlobalHandlers();

		// Small public API: PopupXD.open(123), PopupXD.close(123), PopupXD.closeAll()
		window.PopupXD = {
			open: function ( id, o ) {
				return openPopup( findOverlay( id ), { manual: true, noFocus: !! ( o && o.noFocus ) } );
			},
			close: function ( id ) {
				closePopup( findOverlay( id ), 'api' );
			},
			closeAll: function () {
				openStack.slice().forEach( function ( o ) {
					closePopup( o, 'api' );
				} );
			}
		};
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
