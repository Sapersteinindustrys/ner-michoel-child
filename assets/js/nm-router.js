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
 *
 * History: the router owns it, so it also keeps, in every entry it adds, the
 * page the visitor came from, which a page can't otherwise know. The Back link
 * on Shiurim-section pages is built on that (see "Back" below).
 */
( function () {
	'use strict';

	if ( ! window.history || ! window.history.pushState || ! window.fetch || ! window.DOMParser ) {
		return;
	}

	var CONTENT_SELECTOR = '#content';
	var EXCLUDED_PATH    = /^\/(wp-admin|wp-login\.php|admin)(\/|$)/;
	var DOWNLOADISH      = /\.(pdf|docx?|xlsx?|pptx?|zip|rar|7z|csv|mp3|mp4|m4a|m4v|mov|wav|jpe?g|png|gif|svg|webp)$/i;
	// EXCLUDED_PATH only matches WordPress at the root of the domain. This also finds
	// the dashboard and login when it sits in a folder, as on the staging site.
	var ADMIN_ANYWHERE   = /\/(wp-admin|wp-login\.php)(\/|$)/;

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
		// Never a password in a web address: it would end up in the browser's
		// history, the server's logs and the site's own page-view statistics.
		if ( form.querySelector( 'input[type="password"]' ) ) {
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
	 * Adds the stylesheets the destination page has that this page doesn't yet (a layout's own,
	 * the live search's), each in the destination's order, and waits for them so the new content
	 * never shows unstyled. A slow or failed stylesheet doesn't hold the visitor for more than a
	 * few seconds.
	 */
	function loadMissingStyles( doc ) {
		var absolute = function ( link ) {
			return new URL( link.getAttribute( 'href' ), window.location.href ).href;
		};
		var existing = {};
		Array.prototype.forEach.call( document.querySelectorAll( 'link[rel="stylesheet"]' ), function ( link ) {
			existing[ link.href ] = link;
		} );

		var wanted  = Array.prototype.slice.call( doc.querySelectorAll( 'link[rel="stylesheet"][href]' ) );
		var waiting = [];

		wanted.forEach( function ( link, i ) {
			var href = absolute( link );
			if ( existing[ href ] ) {
				return;
			}

			var el  = document.createElement( 'link' );
			el.rel  = 'stylesheet';
			el.href = href;
			if ( link.media ) {
				el.media = link.media;
			}

			// Before the next of the destination's stylesheets this page already has, so the
			// cascade runs in the same order as on a direct load.
			var before = null;
			for ( var j = i + 1; j < wanted.length && ! before; j++ ) {
				before = existing[ absolute( wanted[ j ] ) ] || null;
			}

			waiting.push(
				new Promise( function ( resolve ) {
					el.onload  = resolve;
					el.onerror = resolve;
				} )
			);
			if ( before ) {
				before.parentNode.insertBefore( el, before );
			} else {
				document.head.appendChild( el );
			}
			existing[ href ] = el;
		} );

		if ( ! waiting.length ) {
			return Promise.resolve();
		}
		return Promise.race( [
			Promise.all( waiting ),
			new Promise( function ( resolve ) {
				setTimeout( resolve, 4000 );
			} )
		] );
	}

	/**
	 * What every history entry remembers, for the Back link (see "Back"
	 * below). The browser doesn't tell a page what's behind it, so the router
	 * keeps it in the entry's state:
	 *   prev      the address of the page of this site the visitor came from, or ''
	 *   prevHome  true if that page was the home page
	 *   first     true if nothing comes before this entry in the tab's history
	 * A page that was really loaded starts with what the browser knows: the
	 * referrer, and whether the tab has any history.
	 */
	function freshEntryState() {
		var prev = '';
		try {
			var referrer = new URL( document.referrer );
			prev = sameOrigin( referrer ) ? referrer.href : '';
		} catch ( e ) {
			prev = ''; // No referrer.
		}
		return { nmRouter: true, prev: prev, prevHome: false, first: window.history.length <= 1 };
	}

	/**
	 * This entry's Back memory, for when the entry is taken over rather than a
	 * new one added: a reload, or Back going up a level. Whatever else a page
	 * keeps in the state (a topic page's search) is dropped, as it always was.
	 */
	function keepEntryState() {
		var state = window.history.state;
		if ( ! state || ! state.nmRouter ) {
			return freshEntryState();
		}
		return { nmRouter: true, prev: state.prev || '', prevHome: !! state.prevHome, first: !! state.first };
	}

	/**
	 * Replaces #content with the fetched page's #content, updates title and
	 * body classes, pushes (or, on popstate, doesn't re-push; or, with
	 * opts.replace, takes over the current entry instead of adding one)
	 * history, moves focus for a11y, and re-fires the teardown/init events
	 * every other script hooks into. Falls back to a real navigation on any
	 * failure.
	 */
	function navigate( url, opts ) {
		opts = opts || {};
		var replace = true === opts.replace;
		var push    = false !== opts.push && ! replace;

		// The page being left, as it is now: the entry for the next one remembers it.
		var from        = window.location.href;
		var leavingHome = document.body.classList.contains( 'home' );

		if ( inFlight ) {
			inFlight.abort();
		}
		var controller = window.AbortController ? new AbortController() : null;
		inFlight = controller;

		if ( push || replace ) {
			scrollPositions[ from ] = window.scrollY;
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
				var next = new DOMParser().parseFromString( html, 'text/html' );
				return loadMissingStyles( next ).then( function () {
					// A newer click has taken over while the styles loaded: drop this one quietly.
					if ( controller && inFlight !== controller ) {
						var superseded = new Error( 'nm-router: superseded' );
						superseded.name = 'AbortError';
						throw superseded;
					}
					return html;
				} );
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
					window.history.pushState( { nmRouter: true, prev: from, prevHome: leavingHome, first: false }, '', url );
				} else if ( replace ) {
					window.history.replaceState( keepEntryState(), '', url );
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
				if ( replace ) {
					// Keeps to the one entry, as the router would have.
					window.location.replace( url );
				} else {
					window.location.href = url;
				}
			} )
			.then( function () {
				if ( inFlight === controller ) {
					inFlight = null;
				}
			} );
	}

	/*
	 * Back
	 * ----
	 * The Back link on Shiurim-section pages (ner_michoel_render_back_button(),
	 * inc/template-tags.php) is <a data-nm-back href="PARENT" data-nm-home="HOME">,
	 * with data-nm-up-first on a shiur. A click goes to the first of these that applies:
	 *
	 *  1. The page the visitor came from, if that was a page of this site (not the
	 *     dashboard or the login) and isn't this page. When it's the entry right behind
	 *     this one, history.back() takes it, so the history and the scroll position come
	 *     back as they were; with no entry behind (a tab opened from that page) it's
	 *     loaded in this entry's place.
	 *     Not for data-nm-up-first (a shiur) when that page was the home page: a shiur
	 *     opened straight from the home page backs out through its series first.
	 *  2. PARENT, the link's own href (the shiur's series, or the home page): loaded in
	 *     this entry's place, not added after it, so Back never leaves a trail it would
	 *     walk back into. If PARENT is the entry right behind, history.back() takes it.
	 *
	 * Opened from the home page, a shiur goes: Back -> its series, Back -> the home page.
	 * After home -> Shiurim -> a series -> a shiur: Back -> the series, the Shiurim page,
	 * the home page. From a shared link: the series, then the home page. A modified click
	 * (new tab and so on) opens PARENT the way a browser does, and without JavaScript
	 * it's just that link.
	 */

	function cleanPath( path ) {
		return path.replace( /\/+$/, '' );
	}

	// The same page: same place and query. A #hash doesn't make another.
	function sameUrl( a, b ) {
		try {
			var x = new URL( a, window.location.href );
			var y = new URL( b, window.location.href );
			return x.origin === y.origin && cleanPath( x.pathname ) === cleanPath( y.pathname ) && x.search === y.search;
		} catch ( e ) {
			return false;
		}
	}

	// The home page. Its only query is the homepage design switch (?home=).
	function isHomeUrl( href, home ) {
		if ( ! home ) {
			return false;
		}
		try {
			var x = new URL( href, window.location.href );
			var y = new URL( home, window.location.href );
			if ( x.origin !== y.origin || cleanPath( x.pathname ) !== cleanPath( y.pathname ) ) {
				return false;
			}
			return ! Array.from( x.searchParams.keys() ).some( function ( key ) {
				return 'home' !== key;
			} );
		} catch ( e ) {
			return false;
		}
	}

	/**
	 * Where a click on Back goes. Everything it needs is passed in.
	 *
	 * @param {string}  parent  The link's href.
	 * @param {string}  home    The home page's address (data-nm-home).
	 * @param {boolean} upFirst data-nm-up-first.
	 * @param {Object}  state   The current history entry's state.
	 * @param {string}  here    The current address.
	 * @return {{url: string, pop: boolean}} pop: the target is the entry right
	 *         behind this one, so take history.back() instead of loading url.
	 */
	function planBack( parent, home, upFirst, state, here ) {
		var entry  = state && state.nmRouter ? state : null;
		var prev   = entry && entry.prev ? entry.prev : '';
		var canPop = '' !== prev && ! entry.first;

		var usable = '' !== prev && ! sameUrl( prev, here );
		if ( usable ) {
			try {
				var prevUrl = new URL( prev, here );
				usable      = ! isExcludedUrl( prevUrl ) && ! ADMIN_ANYWHERE.test( prevUrl.pathname );
			} catch ( e ) {
				usable = false;
			}
		}
		if ( usable && upFirst && ( entry.prevHome || isHomeUrl( prev, home ) ) ) {
			usable = false;
		}

		if ( usable ) {
			return { url: prev, pop: canPop };
		}
		return { url: parent, pop: canPop && sameUrl( parent, prev ) };
	}

	function goBack( link ) {
		var plan = planBack(
			new URL( link.getAttribute( 'href' ), window.location.href ).href,
			link.getAttribute( 'data-nm-home' ) || '',
			link.hasAttribute( 'data-nm-up-first' ),
			window.history.state,
			window.location.href
		);
		// history.length can overcount (a tab's first page still counts the blank page
		// the tab started from), and a Back that pops nothing would be a dead click. Where
		// the browser can say whether anything is behind this entry, ask it.
		var nav = window.navigation;
		if ( plan.pop && nav && 'boolean' === typeof nav.canGoBack && ! nav.canGoBack ) {
			plan.pop = false;
		}
		if ( plan.pop ) {
			window.history.back();
		} else {
			navigate( plan.url, { push: false, replace: true, restoreScroll: true } );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || 0 !== e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}
		var a = e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( a && a.hasAttribute( 'data-nm-back' ) ) {
			e.preventDefault();
			goBack( a );
			return;
		}
		if ( ! shouldInterceptLink( a ) ) {
			return;
		}
		e.preventDefault();
		navigate( a.href );
	} );

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		// A form the page's own script has already taken over (the Account page
		// sends its forms through the REST API) isn't a navigation.
		if ( e.defaultPrevented || ! shouldInterceptForm( form ) ) {
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
	// position to restore back to, and this entry has its Back memory (kept
	// across a reload, otherwise taken from the referrer).
	window.history.replaceState( keepEntryState(), '', window.location.href );

	// Exposed so custom.js's layout-toggle can re-fetch the current page
	// through the router (keeping #sh-player alive) instead of
	// window.location.reload() when the visitor switches layout.
	window.nmRouterNavigate = navigate;

	// Exposed for the tests: where Back would go, for the inputs given.
	window.nmRouterPlanBack = planBack;
} )();
