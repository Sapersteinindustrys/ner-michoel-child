/*
 * Homepage (inc/home.php).
 *
 * - The Current / New switch in the top-right corner. The choice is a cookie
 *   (nm_home) the server reads, so switching loads the page again with the
 *   other design. A link with ?home=new or ?home=current shows that design;
 *   it is remembered here and tidied out of the address.
 * - On the new design (template-parts/home-new.php): the hero's photos fade
 *   from one to the next, each fetched just before its turn, and the New
 *   Shiurim tabs switch between audio/video and written.
 */
( function () {
	'use strict';

	var YEAR = 60 * 60 * 24 * 365;

	function remember( view ) {
		document.cookie = 'nm_home=' + view + ';path=/;max-age=' + YEAR + ';SameSite=Lax';
	}

	function remembered() {
		var match = document.cookie.match( /(?:^|;\s*)nm_home=([a-z]+)/ );
		return match ? match[ 1 ] : '';
	}

	function storage( action, key ) {
		try {
			if ( 'get' === action ) {
				return window.sessionStorage.getItem( key );
			}
			if ( 'set' === action ) {
				window.sessionStorage.setItem( key, '1' );
			} else {
				window.sessionStorage.removeItem( key );
			}
		} catch ( e ) {
			// Storage blocked (private mode): the check below just doesn't run.
		}
		return null;
	}

	// The switch -------------------------------------------------------------
	var params = new URLSearchParams( window.location.search );
	var asked  = params.get( 'home' );

	if ( 'new' === asked || 'current' === asked ) {
		remember( asked );
		params.delete( 'home' );
		if ( window.history && window.history.replaceState ) {
			var query = params.toString();
			window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash );
		}
	}

	var switcher = document.querySelector( '[data-home-switch]' );
	if ( switcher ) {
		var shown = switcher.getAttribute( 'data-home-view' );
		var mine  = remembered();

		// The browser may keep a copy of the homepage for a while (the site
		// allows two hours). If that copy shows the other design than the one
		// chosen, fetch the page again, once.
		if ( ! asked && mine && mine !== shown && ! storage( 'get', 'nmHomeRefetched' ) ) {
			storage( 'set', 'nmHomeRefetched' );
			window.location.reload();
			return;
		}
		if ( mine === shown ) {
			storage( 'remove', 'nmHomeRefetched' );
		}

		switcher.addEventListener( 'click', function ( e ) {
			var button = e.target.closest ? e.target.closest( '[data-home-choose]' ) : null;
			if ( ! button ) {
				return;
			}
			var view = button.getAttribute( 'data-home-choose' );
			if ( view === shown ) {
				return;
			}
			remember( view );
			switcher.classList.add( 'is-switching' );
			Array.prototype.forEach.call( switcher.querySelectorAll( '[data-home-choose]' ), function ( option ) {
				var on = option === button;
				option.classList.toggle( 'is-active', on );
				option.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			// ?home= as well as the cookie, so it works even where cookies are
			// blocked, and the address never offers a stale copy.
			var next = new URLSearchParams( window.location.search );
			next.set( 'home', view );
			window.location.replace( window.location.pathname + '?' + next.toString() + window.location.hash );
		} );
	}

	// The hero's photos ---------------------------------------------------
	var media = document.querySelector( '[data-hn-photos]' );
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	if ( media && ! reduceMotion ) {
		var photos = Array.prototype.slice.call( media.querySelectorAll( '.hn-hero__photo' ) );
		if ( photos.length > 1 ) {
			var interval = parseInt( media.getAttribute( 'data-interval' ), 10 ) || 6000;
			var current  = 0;

			var load = function ( img ) {
				var src = img.getAttribute( 'data-src' );
				if ( ! src ) {
					return;
				}
				var srcset = img.getAttribute( 'data-srcset' );
				if ( srcset ) {
					img.setAttribute( 'srcset', srcset );
					img.removeAttribute( 'data-srcset' );
				}
				img.setAttribute( 'src', src );
				img.removeAttribute( 'data-src' );
			};

			// The next photo is ready before its turn comes.
			load( photos[ 1 ] );

			window.setInterval( function () {
				if ( document.hidden ) {
					return;
				}
				var next = ( current + 1 ) % photos.length;
				var img  = photos[ next ];
				load( img );
				if ( ! img.complete || ! img.naturalWidth ) {
					return; // Still loading: try again on the next turn.
				}
				photos[ current ].classList.remove( 'is-active' );
				img.classList.add( 'is-active' );
				current = next;
				load( photos[ ( next + 1 ) % photos.length ] );
			}, interval );
		}
	}

	// New Shiurim tabs ---------------------------------------------------
	var tabList = document.querySelector( '[data-hn-tabs]' );
	if ( tabList ) {
		var tabs       = Array.prototype.slice.call( tabList.querySelectorAll( '[role="tab"]' ) );
		var moreListen = document.querySelector( '[data-hn-more-listen]' );
		var moreRead   = document.querySelector( '[data-hn-more-read]' );

		var select = function ( tab, focus ) {
			tabs.forEach( function ( other ) {
				var on    = other === tab;
				var panel = document.getElementById( other.getAttribute( 'aria-controls' ) );
				other.classList.toggle( 'is-active', on );
				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.tabIndex = on ? 0 : -1;
				if ( panel ) {
					panel.hidden = ! on;
				}
			} );
			// The link under the list goes to whichever archive is showing.
			var reading = 'hn-tab-read' === tab.id;
			if ( moreListen ) {
				moreListen.hidden = reading && !! moreRead;
			}
			if ( moreRead ) {
				moreRead.hidden = ! reading;
			}
			if ( focus ) {
				tab.focus();
			}
		};

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );
			// Arrow keys move between tabs, as tabs do everywhere else.
			tab.addEventListener( 'keydown', function ( e ) {
				var target = null;
				if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) {
					target = tabs[ ( i + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) {
					target = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === e.key ) {
					target = tabs[ 0 ];
				} else if ( 'End' === e.key ) {
					target = tabs[ tabs.length - 1 ];
				}
				if ( target ) {
					e.preventDefault();
					select( target, true );
				}
			} );
		} );
	}
} )();
