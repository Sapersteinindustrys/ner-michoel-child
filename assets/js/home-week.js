/*
 * The homepage's "This week" section (inc/week.php, template-parts/home-week.php): the
 * buttons between Chutz La'aretz and Eretz Yisroel.
 *
 * In the few weeks a year the two read different parshiyos, the page holds a parsha box
 * for each place (data-hn-region-panel), one of them hidden, and these buttons
 * (data-hn-region) swap them. The buttons start hidden, so without script a visitor sees the
 * place the site shows first and nothing that doesn't work. The visitor's choice is
 * remembered in this browser (localStorage), so the next visit starts on the same place.
 *
 * The page is served from the host's page cache to everybody, so the choice can only be made
 * here, not on the server.
 *
 * Re-entrant, like home.js: nm-router.js can swap #content without a page load, so init()
 * runs again on 'nm:content-swapped'. The click handler is delegated and bound once.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'nmWeekRegion';

	function remembered() {
		try {
			var value = window.localStorage.getItem( STORAGE_KEY );
			return 'israel' === value || 'diaspora' === value ? value : '';
		} catch ( e ) {
			return '';
		}
	}

	function remember( region ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, region );
		} catch ( e ) {
			// Private window or blocked storage: the choice just lasts until the page changes.
		}
	}

	/**
	 * Shows one place's parsha box and note, and marks its button.
	 */
	function show( bar, region ) {
		var week = bar.closest( '.hn-week' ) || document;

		Array.prototype.forEach.call( week.querySelectorAll( '[data-hn-region-panel]' ), function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-hn-region-panel' ) !== region;
		} );

		Array.prototype.forEach.call( bar.querySelectorAll( '[data-hn-region-note]' ), function ( note ) {
			note.hidden = note.getAttribute( 'data-hn-region-note' ) !== region;
		} );

		Array.prototype.forEach.call( bar.querySelectorAll( '[data-hn-region-set]' ), function ( button ) {
			var on = button.getAttribute( 'data-hn-region-set' ) === region;
			button.classList.toggle( 'is-active', on );
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
	}

	function init() {
		var bar = document.querySelector( '[data-hn-region]' );
		if ( ! bar ) {
			return;
		}
		bar.hidden = false;
		show( bar, remembered() || bar.getAttribute( 'data-default' ) || 'diaspora' );
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target && e.target.closest ? e.target.closest( '[data-hn-region-set]' ) : null;
		if ( ! button ) {
			return;
		}
		var bar = button.closest( '[data-hn-region]' );
		if ( ! bar ) {
			return;
		}
		var region = button.getAttribute( 'data-hn-region-set' );
		remember( region );
		show( bar, region );
	} );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	document.addEventListener( 'nm:content-swapped', init );
} )();
