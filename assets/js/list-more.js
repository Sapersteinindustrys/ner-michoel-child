/**
 * "Load more" at the bottom of the long lists on the Shiurim pages: a speaker's, a series'
 * and a topic's shiurim, and grids of cards.
 *
 *   - A list that is all on the page (a speaker's or a series' shiurim) shows the first
 *     STEP lines, and the button shows STEP more each time.
 *   - A list that comes in pages (a topic's, 40 a page) loses its page numbers: the
 *     button fetches the next page and adds its lines under these. Each line's place in
 *     the list's play queue (data-index, data-queue) is moved along, so Play and Next
 *     carry on through the added lines.
 *
 * Without JavaScript every line, and the page numbers, are there as before. Strings come
 * from window.nmListMore (inc/revamp.php). Runs again after the page router (nm-router.js)
 * swaps in a page.
 */
( function () {
	'use strict';

	var STEP      = 30;
	var CARD_STEP = 24;
	var words     = window.nmListMore || {};

	function t( key, fallback ) {
		return words[ key ] || fallback;
	}

	function label( left ) {
		return left > 0 ? t( 'moreN', 'Load more (%s more)' ).replace( '%s', left.toLocaleString() ) : t( 'more', 'Load more' );
	}

	function button() {
		var wrap = document.createElement( 'div' );
		wrap.className = 'nm-more';
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'nm-more__button';
		wrap.appendChild( btn );
		return { wrap: wrap, btn: btn };
	}

	// A list that is all on the page: show STEP at a time.
	function reveal( list, items, step ) {
		if ( items.length <= step + Math.ceil( step / 3 ) ) {
			return; // A few over the step: just show them.
		}
		var shown = step;
		items.forEach( function ( el, i ) {
			if ( i >= shown ) {
				el.hidden = true;
				el.classList.add( 'is-more-hidden' );
			}
		} );
		var b = button();
		b.btn.textContent = label( items.length - shown );
		list.parentNode.insertBefore( b.wrap, list.nextSibling );
		b.btn.addEventListener( 'click', function () {
			var end = Math.min( items.length, shown + step );
			for ( var i = shown; i < end; i++ ) {
				items[ i ].hidden = false;
				items[ i ].classList.remove( 'is-more-hidden' );
			}
			var first = items[ shown ];
			shown = end;
			if ( shown >= items.length ) {
				b.wrap.parentNode.removeChild( b.wrap );
			} else {
				b.btn.textContent = label( items.length - shown );
			}
			// Keyboard users carry on from the first new line.
			var focusable = first && first.querySelector( 'a, button' );
			if ( focusable && document.activeElement === b.btn ) {
				focusable.focus( { preventScroll: true } );
			}
		} );
	}

	function parseQueue( list ) {
		try {
			var q = JSON.parse( list.getAttribute( 'data-queue' ) || '[]' );
			return Array.isArray( q ) ? q : [];
		} catch ( e ) {
			return [];
		}
	}

	// A list that comes in pages: fetch the next one and add its lines.
	function paged( list, nav ) {
		var next = nav.querySelector( 'a.next' );
		if ( ! next || ! window.fetch || ! window.DOMParser ) {
			return;
		}
		var url  = next.href;
		var b    = button();
		var busy = false;

		b.btn.textContent = label( 0 );
		nav.classList.add( 'is-more-hidden' );
		nav.parentNode.insertBefore( b.wrap, nav );

		b.btn.addEventListener( 'click', function () {
			if ( busy || ! url ) {
				return;
			}
			busy = true;
			b.btn.disabled = true;
			b.btn.textContent = t( 'loading', 'Loading…' );
			fetch( url, { credentials: 'same-origin' } )
				.then( function ( res ) {
					if ( ! res.ok ) {
						throw new Error( res.status );
					}
					return res.text();
				} )
				.then( function ( html ) {
					var doc      = new DOMParser().parseFromString( html, 'text/html' );
					var section  = list.closest( '[data-scope-browse], .sh-section' );
					var selector = section && section.hasAttribute( 'data-scope-browse' ) ? '[data-scope-browse] .sh-tracklist' : '.sh-tracklist';
					var other    = doc.querySelector( selector );
					if ( ! other ) {
						throw new Error( 'no list' );
					}
					var queue  = parseQueue( list );
					var offset = queue.length;
					var first  = null;
					Array.prototype.slice.call( other.querySelectorAll( '.sh-track' ) ).forEach( function ( row ) {
						var el = document.importNode( row, true );
						if ( el.hasAttribute( 'data-index' ) ) {
							var index = ( parseInt( el.getAttribute( 'data-index' ), 10 ) || 0 ) + offset;
							el.setAttribute( 'data-index', String( index ) );
							var num = el.querySelector( '.sh-track__index' );
							if ( num ) {
								num.textContent = String( index + 1 );
							}
						}
						list.appendChild( el );
						first = first || el;
					} );
					list.setAttribute( 'data-queue', JSON.stringify( queue.concat( parseQueue( other ) ) ) );

					var otherNext = doc.querySelector( '.sh-classic-pagination a.next' );
					url = otherNext ? otherNext.href : '';
					busy = false;
					b.btn.disabled = false;
					if ( ! url ) {
						b.wrap.parentNode.removeChild( b.wrap );
						nav.parentNode.removeChild( nav );
						return;
					}
					b.btn.textContent = label( 0 );
					var focusable = first && first.querySelector( 'a, button' );
					if ( focusable && document.activeElement === b.btn ) {
						focusable.focus( { preventScroll: true } );
					}
				} )
				.catch( function () {
					// Something went wrong: the page numbers come back.
					busy = false;
					b.wrap.parentNode.removeChild( b.wrap );
					nav.classList.remove( 'is-more-hidden' );
				} );
		} );
	}

	function init() {
		var app = document.querySelector( '.shiurim-app' );
		if ( ! app ) {
			return;
		}
		Array.prototype.slice.call( app.querySelectorAll( '.sh-tracklist' ) ).forEach( function ( list ) {
			if ( list.hasAttribute( 'data-more-done' ) ) {
				return;
			}
			list.setAttribute( 'data-more-done', '' );
			var section = list.parentNode;
			var nav     = section ? section.querySelector( '.sh-classic-pagination' ) : null;
			if ( nav ) {
				paged( list, nav );
			} else {
				reveal( list, Array.prototype.slice.call( list.children ).filter( function ( el ) {
					return el.classList.contains( 'sh-track' );
				} ), STEP );
			}
		} );
		Array.prototype.slice.call( app.querySelectorAll( '.sh-grid' ) ).forEach( function ( grid ) {
			if ( grid.hasAttribute( 'data-more-done' ) ) {
				return;
			}
			grid.setAttribute( 'data-more-done', '' );
			reveal( grid, Array.prototype.slice.call( grid.children ), CARD_STEP );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	document.addEventListener( 'nm:content-swapped', init );
} )();
