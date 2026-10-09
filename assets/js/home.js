/*
 * Homepage (inc/home.php, template-parts/home-new.php).
 *
 * - The hero's photos fade from one to the next, each fetched just before its
 *   turn.
 * - The New Shiurim tabs switch between audio/video and written.
 *
 * Re-entrant: nm-router.js (site-wide) can swap #content without a real page
 * load, so init() re-runs on its 'nm:content-swapped' event every time this
 * page is routed to. teardown() (on 'nm:before-content-swap') clears the
 * hero's crossfade interval first, so leaving the homepage doesn't leave it
 * running against detached photos.
 */
( function () {
	'use strict';

	var photoInterval = null;

	function teardown() {
		if ( photoInterval ) {
			window.clearInterval( photoInterval );
			photoInterval = null;
		}
	}

	function init() {
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

				photoInterval = window.setInterval( function () {
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
	}

	init();
	document.addEventListener( 'nm:before-content-swap', teardown );
	document.addEventListener( 'nm:content-swapped', init );
} )();
