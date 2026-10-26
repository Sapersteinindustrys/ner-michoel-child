/**
 * The "Share a Mazal Tov" form (template-parts/mazal-tov-share.php): the buttons marked
 * data-nm-mt-open open it as a dialog over the page; Close, Cancel, Escape and a click
 * outside close it. After the form is sent the page reloads with ?nm_mazal_tov=...
 * and the dialog opens by itself to say how it went.
 *
 * Everything is attached to the document, so it keeps working after the page router
 * (nm-router.js) swaps a new page's content in.
 */
( function () {
	'use strict';

	function dialog() {
		return document.getElementById( 'nm-mt-share' );
	}

	function open( dlg ) {
		if ( dlg.open ) {
			return;
		}
		if ( typeof dlg.showModal === 'function' ) {
			dlg.showModal();
		} else {
			dlg.setAttribute( 'open', '' );
		}
		var first = dlg.querySelector( 'input[name="nm_mt_name"], .nm-mt-share__submit' );
		if ( first ) {
			first.focus();
		}
	}

	function close( dlg ) {
		if ( typeof dlg.close === 'function' ) {
			dlg.close();
		} else {
			dlg.removeAttribute( 'open' );
		}
	}

	// Capture phase, so the router (which also listens on the document) sees the click as handled.
	document.addEventListener(
		'click',
		function ( e ) {
			var target = e.target;
			if ( ! target || ! target.closest ) {
				return;
			}
			var opener = target.closest( '[data-nm-mt-open]' );
			if ( opener ) {
				var dlg = dialog();
				if ( dlg ) {
					e.preventDefault();
					open( dlg );
				}
				return;
			}
			var closer = target.closest( '[data-nm-mt-close]' );
			if ( closer ) {
				var d = dialog();
				if ( d ) {
					e.preventDefault();
					close( d );
				}
				return;
			}
			// A click on the dimmed area (the dialog element itself, not its panel).
			if ( target.id === 'nm-mt-share' ) {
				close( target );
			}
		},
		true
	);

	// Once closed, take the result word out of the address so a reload doesn't show it again.
	document.addEventListener(
		'close',
		function ( e ) {
			if ( ! e.target || e.target.id !== 'nm-mt-share' ) {
				return;
			}
			try {
				var url = new URL( window.location.href );
				if ( url.searchParams.has( 'nm_mazal_tov' ) ) {
					url.searchParams.delete( 'nm_mazal_tov' );
					window.history.replaceState( window.history.state, '', url.toString() );
				}
			} catch ( err ) {}
		},
		true
	);

	function init() {
		var dlg = dialog();
		if ( dlg && dlg.hasAttribute( 'data-nm-mt-autoopen' ) ) {
			dlg.removeAttribute( 'data-nm-mt-autoopen' );
			open( dlg );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	document.addEventListener( 'nm:content-swapped', init );
}() );
