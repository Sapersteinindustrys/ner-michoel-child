/*
 * Site-wide router: intercepts same-origin link clicks and GET-form submits,
 * fetches the destination, and swaps only Astra's #content wrapper — so
 * nothing outside it (in particular #sh-player and its live <audio> element,
 * both printed on wp_footer, after #content closes) is ever destroyed. That's
 * the whole point: whatever's playing keeps playing while browsing the rest
 * of the site, the way a real page load never could.
 *
 * Every other theme script that binds to specific DOM elements (not
 * delegated on document) listens for the two events this fires around a
 * swap — 'nm:before-content-swap' (teardown: clear intervals, disconnect
 * observers) and 'nm:content-swapped' (init: re-query and re-bind against
 * the new #content) — so they keep working after a swap exactly as they did
 * after a real page load. See custom.js, home.js, account.js,
 * written-library.js, written-reader.js, shiur-live-search.js.
 *
 * Deliberately excluded, always falling through to a real browser
 * navigation: cross-origin/new-tab/download links, #hash-only same-page
 * links, /wp-admin, /wp-login.php, and /admin (ner-michoel-core's standalone
 * front-end CMS dashboard — no get_header()/get_footer(), no #content at
 * all). Anything else that fails for any reason (network error, non-2xx,
 * cross-origin redirect, no #content found in the response) also falls back
 * to a real navigation rather than leaving the visitor stuck.
 */
( function () {
	'use strict';

	if ( ! window.history || ! window.history.pushState || ! window.fetch || ! window.DOMParser ) {
		return;
	}

	var CONTENT_SELECTOR = '#content';
	var EXCLUDED_PATH    = /^\/(wp-admin|wp-login\.php|admin)(\/|$)/;
	var DOWNLOADISH      = /\.(pdf|docx?|xlsx?|pptx?|zip|rar|7z|csv|mp3|mp4|m4a|m4v|mov|wav|jpe?g|png|gif|svg|webp)$/i;

	var inFlight        = null;
	var scrollPositions = {};

	function sameOrigin( url ) {
		return url.origin === window.location.origin;
	}

	function isExcludedUrl( url ) {
		return ! sameOrigin( url ) || EXCLUDED_PATH.test( url.pathname ) || DOWNLOADISH.test( url.pathname );
	}

	function shouldInterceptLink( a ) {
		if ( ! a || ! a.href ) {
			return false;
		}
		if ( a.target && '_self' !== a.target ) {
			return false;
		}
		if ( a.hasAttribute( 'download' ) || a.hasAttribute( 'data-no-pjax' ) ) {
			return false;
		}
		var rel = a.getAttribute( 'rel' ) || '';
		if ( rel.split( /\s+/ ).indexOf( 'external' ) !== -1 ) {
			return false;
		}

		var url;
		try {
			url = new URL( a.href, window.location.href );
		} catch ( e ) {
			return false;
		}
		if ( isExcludedUrl( url ) ) {
			return false;
		}

		// A #hash-only link to the same page: let the browser jump natively.
		if ( url.pathname === window.location.pathname && url.search === window.location.search && url.hash ) {
			return false;
		}

		return true;
	}

	function shouldInterceptForm( form ) {
		if ( ! form || 'FORM' !== form.tagName ) {
			return false;
		}
		if ( form.hasAttribute( 'data-no-pjax' ) ) {
			return false;
		}
		var method = ( form.getAttribute( 'method' ) || 'get' ).toLowerCase();
		if ( 'get' !== method ) {
			return false;
		}
		var url;
		try {
			url = new URL( form.getAttribute( 'action' ) || window.location.pathname, window.location.href );
		} catch ( e ) {
			return false;
		}
		return ! isExcludedUrl( url );
	}

	/**
	 * Replaces #content with the fetched page's #content, updates title and
	 * body classes, pushes (or, on popstate, doesn't re-push) history, moves
	 * focus for a11y, and re-fires the teardown/init events every other
	 * script hooks into. Falls back to a real navigation on any failure.
	 */
	function navigate( url, opts ) {
		opts = opts || {};
		var push = false !== opts.push;

		if ( inFlight ) {
			inFlight.abort();
		}
		var controller = window.AbortController ? new AbortController() : null;
		inFlight = controller;

		if ( push ) {
			scrollPositions[ window.location.href ] = window.scrollY;
		}

		document.dispatchEvent( new CustomEvent( 'nm:before-content-swap' ) );

		fetch( url, {
			signal: controller ? controller.signal : undefined,
			headers: { 'X-Requested-With': 'nm-router' },
			credentials: 'same-origin'
		} )
			.then( function ( res ) {
				if ( ! res.ok ) {
					throw new Error( 'nm-router: bad status ' + res.status );
				}
				if ( res.redirected ) {
					var dest;
					try {
						dest = new URL( res.url );
					} catch ( e ) {
						throw new Error( 'nm-router: unreadable redirect target' );
					}
					if ( ! sameOrigin( dest ) ) {
						throw new Error( 'nm-router: cross-origin redirect' );
					}
				}
				return res.text();
			} )
			.then( function ( html ) {
				var doc        = new DOMParser().parseFromString( html, 'text/html' );
				var newContent = doc.querySelector( CONTENT_SELECTOR );
				var curContent = document.querySelector( CONTENT_SELECTOR );
				if ( ! newContent || ! curContent ) {
					throw new Error( 'nm-router: no ' + CONTENT_SELECTOR + ' in response' );
				}

				curContent.innerHTML = newContent.innerHTML;
				document.title       = doc.title;
				if ( doc.body ) {
					document.body.className = doc.body.className;
				}

				if ( push ) {
					window.history.pushState( { nmRouter: true }, '', url );
				}

				window.scrollTo( 0, opts.restoreScroll ? ( scrollPositions[ url ] || 0 ) : 0 );

				// Minimal a11y: screen readers/keyboard focus land on the new
				// content instead of staying on a now-irrelevant trigger.
				curContent.setAttribute( 'tabindex', '-1' );
				curContent.focus();

				document.dispatchEvent( new CustomEvent( 'nm:content-swapped' ) );
			} )
			.catch( function ( err ) {
				if ( err && 'AbortError' === err.name ) {
					return;
				}
				window.location.href = url;
			} )
			.then( function () {
				if ( inFlight === controller ) {
					inFlight = null;
				}
			} );
	}

	document.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || 0 !== e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}
		var a = e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( ! shouldInterceptLink( a ) ) {
			return;
		}
		e.preventDefault();
		navigate( a.href );
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! shouldInterceptForm( form ) ) {
			return;
		}
		var url = new URL( form.getAttribute( 'action' ) || window.location.pathname, window.location.href );
		url.search = new URLSearchParams( new FormData( form ) ).toString();
		e.preventDefault();
		navigate( url.toString() );
	} );

	window.addEventListener( 'popstate', function () {
		navigate( window.location.href, { push: false, restoreScroll: true } );
	} );

	// So the very first popstate (after one router navigation) has a scroll
	// position to restore back to.
	window.history.replaceState( { nmRouter: true }, '', window.location.href );

	// Exposed so custom.js's layout-toggle can re-fetch the current page
	// through the router (keeping #sh-player alive) instead of
	// window.location.reload() when the visitor switches layout.
	window.nmRouterNavigate = navigate;
} )();
