/*
 * Written shiur reading view (single-written_shiur.php).
 *
 * - Wide screens: the PDF goes on the desk in an iframe. It's added by script
 *   so a phone never starts downloading a PDF it can't show; a phone turned
 *   to landscape gets it too. Narrow screens keep the cover, which opens the
 *   PDF in the device's own reader.
 * - Full screen: the desk fills the window. Esc or × closes it.
 * - Share: the system share sheet where there is one, otherwise copy link.
 * - Opening the PDF records History (logged in, nerMichoelRecordHistory in
 *   custom.js) and marks the shiur "Read" in this browser for the library.
 */
( function () {
	'use strict';

	var READ_KEY = 'nmWrittenRead';
	var desk     = document.querySelector( '[data-nm-reader]' );
	var opened   = false;

	function readIds() {
		try {
			var list = JSON.parse( window.localStorage.getItem( READ_KEY ) || '[]' );
			return Array.isArray( list ) ? list : [];
		} catch ( e ) {
			return [];
		}
	}

	// Related sheets below the reader get their "Read" marks too.
	var read = readIds();
	document.querySelectorAll( '.nm-sheet[data-written-id]' ).forEach( function ( sheet ) {
		if ( read.indexOf( sheet.getAttribute( 'data-written-id' ) ) !== -1 ) {
			sheet.classList.add( 'is-read' );
		}
	} );

	function markOpened() {
		if ( opened || ! desk ) {
			return;
		}
		opened = true;
		var id = desk.getAttribute( 'data-post-id' );
		try {
			var list = readIds().filter( function ( other ) {
				return other !== id;
			} );
			list.unshift( id );
			window.localStorage.setItem( READ_KEY, JSON.stringify( list.slice( 0, 500 ) ) );
		} catch ( e ) {
			// Private mode or storage blocked: the marker is a nicety.
		}
		if ( window.nerMichoelSettings && window.nerMichoelSettings.isLoggedIn && 'function' === typeof window.nerMichoelRecordHistory ) {
			window.nerMichoelRecordHistory( id );
		}
	}

	if ( desk ) {
		var paper   = desk.querySelector( '[data-reader-frame]' );
		var src     = desk.getAttribute( 'data-pdf-src' );
		var closer  = desk.querySelector( '[data-reader-close]' );
		var wide    = window.matchMedia ? window.matchMedia( '(min-width: 761px)' ) : null;
		var trigger = null;

		var showPdf = function () {
			if ( ! paper || ! src || paper.querySelector( 'iframe' ) ) {
				return;
			}
			var frame   = document.createElement( 'iframe' );
			frame.src   = src + '#view=FitH';
			frame.title = desk.getAttribute( 'data-title' ) || '';
			frame.addEventListener( 'load', function () {
				paper.classList.add( 'is-loaded' );
			} );
			paper.appendChild( frame );
			desk.classList.add( 'is-enhanced' );
			markOpened();
		};

		if ( ! wide || wide.matches ) {
			showPdf();
		}
		if ( wide ) {
			var onChange = function ( e ) {
				if ( e.matches ) {
					showPdf();
				}
			};
			if ( wide.addEventListener ) {
				wide.addEventListener( 'change', onChange );
			} else if ( wide.addListener ) {
				wide.addListener( onChange );
			}
		}

		var openFocus = function ( from ) {
			showPdf();
			trigger = from || document.activeElement;
			desk.classList.add( 'is-focus' );
			document.documentElement.classList.add( 'nm-reader-open' );
			if ( closer ) {
				closer.focus();
			}
		};

		var closeFocus = function () {
			if ( ! desk.classList.contains( 'is-focus' ) ) {
				return;
			}
			desk.classList.remove( 'is-focus' );
			document.documentElement.classList.remove( 'nm-reader-open' );
			if ( trigger && trigger.focus ) {
				trigger.focus();
			}
		};

		document.querySelectorAll( '[data-reader-focus]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				openFocus( btn );
			} );
		} );

		if ( closer ) {
			closer.addEventListener( 'click', closeFocus );
		}

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				closeFocus();
			}
		} );

		// Keep keyboard focus inside the full-screen reader while it's open.
		document.addEventListener( 'focusin', function ( e ) {
			if ( desk.classList.contains( 'is-focus' ) && ! desk.contains( e.target ) && closer ) {
				closer.focus();
			}
		} );

		// The cover, Open and Download all count as opening it.
		document.querySelectorAll( '[data-reader-open]' ).forEach( function ( link ) {
			link.addEventListener( 'click', markOpened );
		} );
	}

	// Share --------------------------------------------------------------------
	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var field = document.createElement( 'textarea' );
			field.value = text;
			field.setAttribute( 'readonly', '' );
			field.style.position = 'fixed';
			field.style.opacity = '0';
			document.body.appendChild( field );
			field.select();
			var ok = false;
			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}
			document.body.removeChild( field );
			if ( ok ) {
				resolve();
			} else {
				reject();
			}
		} );
	}

	document.querySelectorAll( '[data-reader-share]' ).forEach( function ( btn ) {
		var label    = btn.querySelector( '[data-share-label]' );
		var original = label ? label.textContent : '';

		btn.addEventListener( 'click', function () {
			var url   = window.location.href.split( '#' )[ 0 ];
			var title = btn.getAttribute( 'data-share-title' ) || document.title;

			if ( navigator.share ) {
				navigator.share( { title: title, url: url } ).catch( function () {
					// Closing the share sheet isn't an error worth showing.
				} );
				return;
			}

			copyText( url ).then( function () {
				if ( label ) {
					label.textContent = btn.getAttribute( 'data-share-copied' ) || original;
				}
				btn.classList.add( 'is-done' );
				window.setTimeout( function () {
					if ( label ) {
						label.textContent = original;
					}
					btn.classList.remove( 'is-done' );
				}, 2200 );
			} ).catch( function () {
				window.location.href = 'mailto:?subject=' + encodeURIComponent( title ) + '&body=' + encodeURIComponent( url );
			} );
		} );
	} );
} )();
