/*
 * Live search for the Shiurim search page (search-shiurim.php). The page loads
 * the shiur index once (ner-michoel/v1/shiur-index, built in ner-michoel-core
 * includes/shiur-index.php), then filters it in the browser as the reader types
 * or changes a speaker, series or year. Nothing goes to the server per keystroke.
 *
 * If the index can't be loaded, the page reloads as the plain search
 * (?nm_plain=1), which the server ranks. Titles, speakers and series are set as
 * text, never as HTML. Strings come from wp_localize_script() (nmLiveSearch).
 */
( function ( document, window ) {
	'use strict';

	var root = document.querySelector( '[data-live-search]' );
	if ( ! root ) {
		return;
	}

	var PAGE_SIZE = 60;
	var DEBOUNCE  = 140;

	var text = window.nmLiveSearch || {};
	function t( key, fallback ) {
		return text[ key ] || fallback;
	}

	var input     = root.querySelector( '[data-live-query]' );
	var speakerEl = root.querySelector( '[data-live-speaker]' );
	var seriesEl  = root.querySelector( '[data-live-series]' );
	var yearEl    = root.querySelector( '[data-live-year]' );
	var countEl   = root.querySelector( '[data-live-count]' );
	var list      = root.querySelector( '[data-live-results]' );
	var moreBtn   = root.querySelector( '[data-live-more]' );
	var titleEl   = document.querySelector( '[data-live-title]' );

	var KIND = { a: t( 'audio', 'Audio' ), v: t( 'video', 'Video' ), w: t( 'written', 'Written' ) };

	var index   = null;
	var matches = [];
	var shown   = 0;
	var typing  = null;

	// The plain search, ranked on the server: used when the index can't be loaded.
	function goPlain() {
		var params = new URLSearchParams( window.location.search );
		params.set( 'nm_plain', '1' );
		window.location.replace( window.location.pathname + '?' + params.toString() );
	}

	if ( ! window.fetch || ! window.URLSearchParams ) {
		goPlain();
		return;
	}

	// Lowercase, straight quotes, single spaces, so "V’Eim" matches "v'eim".
	function fold( value ) {
		return String( value || '' )
			.toLowerCase()
			.replace( /[‘’ʼ`]/g, "'" )
			.replace( /[“”]/g, '"' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function format( template, values ) {
		var i = 0;
		return template.replace( /%(\d+\$)?s/g, function ( match, position ) {
			var n = position ? parseInt( position, 10 ) - 1 : i++;
			return values[ n ] !== undefined ? values[ n ] : '';
		} );
	}

	// t is the post's own date and time read as UTC, so it's shown in UTC to give
	// the same date the rest of the site shows.
	function yearOf( it ) {
		return new Date( it.t * 1000 ).getUTCFullYear();
	}

	function formatDate( ts ) {
		return new Date( ts * 1000 ).toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' } );
	}

	function addOptions( select, options ) {
		options.forEach( function ( opt ) {
			var el         = document.createElement( 'option' );
			el.value       = opt.id;
			el.textContent = opt.name;
			select.appendChild( el );
		} );
	}

	function sortedNames( names ) {
		return Object.keys( names )
			.map( function ( id ) {
				return { id: id, name: names[ id ] };
			} )
			.sort( function ( a, b ) {
				return a.name.localeCompare( b.name );
			} );
	}

	function prepare( data ) {
		index          = data;
		index.speakers = index.speakers || {};
		index.series   = index.series || {};

		var years = {};
		index.items.forEach( function ( it ) {
			it._title = fold( it.m );
			it._text  = fold( [ it.m, index.speakers[ it.a ] || '', index.series[ it.s ] || '' ].join( ' ' ) );
			it._year  = String( yearOf( it ) );
			years[ it._year ] = true;
		} );

		addOptions( speakerEl, sortedNames( index.speakers ) );
		addOptions( seriesEl, sortedNames( index.series ) );
		addOptions(
			yearEl,
			Object.keys( years )
				.sort( function ( a, b ) {
					return b - a;
				} )
				.map( function ( y ) {
					return { id: y, name: y };
				} )
		);
	}

	/*
	 * The filters and search text live in the URL, so a link or Back returns to
	 * the same results. The names are by, sr and yr on purpose: speaker and series
	 * are WordPress query vars for the taxonomies and year is the date archive's,
	 * so using those would filter or redirect the page on the server. s is always
	 * kept, even empty: without it, ?post_type=shiur is the Shiurim archive, not
	 * this page.
	 */
	function readUrl() {
		var params = new URLSearchParams( window.location.search );
		input.value     = params.get( 's' ) || '';
		speakerEl.value = params.get( 'by' ) || '';
		seriesEl.value  = params.get( 'sr' ) || '';
		yearEl.value    = params.get( 'yr' ) || '';
		// An option that isn't in the index (an old link) falls back to "All".
		[ speakerEl, seriesEl, yearEl ].forEach( function ( select ) {
			if ( select.selectedIndex < 0 ) {
				select.value = '';
			}
		} );
	}

	function writeUrl() {
		var params = new URLSearchParams( window.location.search );
		params.set( 's', input.value );
		params.set( 'post_type', 'shiur' );
		params.delete( 'nm_plain' );
		[ [ 'by', speakerEl.value ], [ 'sr', seriesEl.value ], [ 'yr', yearEl.value ] ].forEach( function ( pair ) {
			if ( pair[ 1 ] ) {
				params.set( pair[ 0 ], pair[ 1 ] );
			} else {
				params.delete( pair[ 0 ] );
			}
		} );
		window.history.replaceState( window.history.state, '', window.location.pathname + '?' + params.toString() );
	}

	function updateTitle() {
		if ( ! titleEl ) {
			return;
		}
		var query = input.value.trim();
		titleEl.textContent = query
			? format( titleEl.getAttribute( 'data-title-template' ) || '%s', [ query ] )
			: ( titleEl.getAttribute( 'data-title-empty' ) || '' );
	}

	function row( it ) {
		var li       = document.createElement( 'li' );
		li.className = 'nm-live-results__item';

		var link         = document.createElement( 'a' );
		link.className   = 'nm-live-results__title';
		link.href        = it.u;
		link.textContent = it.m;
		li.appendChild( link );

		var meta       = document.createElement( 'span' );
		meta.className = 'nm-live-results__meta';
		meta.textContent = [
			index.speakers[ it.a ] || '',
			index.series[ it.s ] || '',
			formatDate( it.t ),
			KIND[ it.c ] || '',
			it.d || ''
		].filter( Boolean ).join( ' · ' );
		li.appendChild( meta );

		return li;
	}

	function updateCount() {
		var total = matches.length;
		if ( 0 === total ) {
			countEl.textContent = t( 'none', 'No shiurim match these words and filters. Try fewer or different words.' );
		} else if ( shown < total ) {
			countEl.textContent = format( t( 'showing', 'Showing %1$s of %2$s shiurim' ), [ shown.toLocaleString(), total.toLocaleString() ] );
		} else if ( 1 === total ) {
			countEl.textContent = t( 'foundOne', '1 shiur found.' );
		} else {
			countEl.textContent = format( t( 'found', '%s shiurim found.' ), [ total.toLocaleString() ] );
		}
	}

	function showMore() {
		var end      = Math.min( matches.length, shown + PAGE_SIZE );
		var fragment = document.createDocumentFragment();
		for ( var i = shown; i < end; i++ ) {
			fragment.appendChild( row( matches[ i ].it ) );
		}
		list.appendChild( fragment );
		shown          = end;
		moreBtn.hidden = shown >= matches.length;
		updateCount();
	}

	function run() {
		writeUrl();
		updateTitle();

		var words   = fold( input.value ).split( ' ' ).filter( Boolean );
		var phrase  = words.join( ' ' );
		var speaker = speakerEl.value;
		var series  = seriesEl.value;
		var year    = yearEl.value;

		matches = [];
		var items = index.items;
		for ( var i = 0; i < items.length; i++ ) {
			var it = items[ i ];
			if ( ( speaker && String( it.a ) !== speaker ) || ( series && String( it.s ) !== series ) || ( year && it._year !== year ) ) {
				continue;
			}

			var tier = 0;
			if ( words.length ) {
				var inText = true;
				var inTitle = true;
				for ( var w = 0; w < words.length; w++ ) {
					if ( -1 === it._text.indexOf( words[ w ] ) ) {
						inText = false;
						break;
					}
					if ( -1 === it._title.indexOf( words[ w ] ) ) {
						inTitle = false;
					}
				}
				if ( ! inText ) {
					continue;
				}
				// 0: the whole search is in the title. 1: every word is in the title.
				// 2: the words are spread over the title, speaker and series.
				if ( -1 !== it._title.indexOf( phrase ) ) {
					tier = 0;
				} else {
					tier = inTitle ? 1 : 2;
				}
			}
			matches.push( { it: it, tier: tier } );
		}

		// The index is newest first, so with no search words that order is kept.
		if ( words.length ) {
			matches.sort( function ( a, b ) {
				return a.tier - b.tier || b.it.t - a.it.t;
			} );
		}

		list.textContent = '';
		shown = 0;
		showMore();
	}

	function schedule() {
		window.clearTimeout( typing );
		typing = window.setTimeout( run, DEBOUNCE );
	}

	root.hidden = false;
	countEl.textContent = t( 'loading', 'Loading shiurim…' );

	fetch( root.getAttribute( 'data-index-url' ), { credentials: 'same-origin' } )
		.then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'Index not available' );
			}
			return response.json();
		} )
		.then( function ( data ) {
			if ( ! data || ! Array.isArray( data.items ) ) {
				throw new Error( 'Index not readable' );
			}
			prepare( data );
			readUrl();
			run();

			input.addEventListener( 'input', schedule );
			speakerEl.addEventListener( 'change', run );
			seriesEl.addEventListener( 'change', run );
			yearEl.addEventListener( 'change', run );
			moreBtn.addEventListener( 'click', showMore );
		} )
		.catch( goPlain );
} )( document, window );
