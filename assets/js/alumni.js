/**
 * The Alumni page's own search (alumni.php): hides the rows that don't have
 * every typed word, and keeps the count up to date.
 */
( function () {
	'use strict';
	var box = document.querySelector( '[data-alumni-search]' );
	if ( ! box ) {
		return;
	}
	var query = box.querySelector( '[data-alumni-query]' );
	var kind = box.querySelector( '[data-alumni-kind]' );
	var count = document.querySelector( '[data-alumni-count]' );
	var rows = Array.prototype.slice.call( document.querySelectorAll( '.nm-alumni__table tbody tr' ) );

	function words( text ) {
		return String( text || '' ).toLowerCase().replace( /[‘’'"]/g, '' ).split( /\s+/ ).filter( Boolean );
	}

	function run() {
		var want = words( query.value );
		var k = kind ? kind.value : '';
		var shown = 0;
		rows.forEach( function ( row ) {
			var q = row.getAttribute( 'data-q' ).replace( /[‘’'"]/g, '' );
			var ok = ( ! k || row.getAttribute( 'data-kind' ) === k ) && want.every( function ( w ) {
				return q.indexOf( w ) !== -1;
			} );
			row.hidden = ! ok;
			if ( ok ) {
				shown++;
			}
		} );
		if ( count ) {
			count.textContent = 0 === shown ? count.getAttribute( 'data-none' ) : ( 1 === shown ? count.getAttribute( 'data-one' ) : count.getAttribute( 'data-many' ).replace( '%d', shown ) );
		}
	}

	query.addEventListener( 'input', run );
	if ( kind ) {
		kind.addEventListener( 'change', run );
	}
	if ( query.value ) {
		run();
	}
}() );
