/*
 * Live search for the Shiurim search page (search-shiurim.php) and for the search
 * box on a topic page (taxonomy-topic.php). Both load the shiur index once
 * (ner-michoel/v1/shiur-index, built in ner-michoel-core includes/shiur-index.php),
 * then filter it in the browser as the reader types. Nothing goes to the server
 * per keystroke. The matching and ranking are shiur-search-engine.js's: full
 * matches first, then close matches (a letter or two off, or swapped) after them.
 *
 * - Search page: also changes with the speaker, series, topic and year filters,
 *   and keeps them in the URL. If the index can't be loaded, the page reloads as
 *   the plain search (?nm_plain=1), which the server ranks.
 * - Topic page: searches only that topic's shiurim and written shiurim, across
 *   all of its pages, in place of the page's own lists while there is something
 *   typed (the elements marked data-topic-browse). The search is kept in the URL
 *   as #q=. The index is fetched only when the box is used (clicked into, or the
 *   page was opened with #q=), since most visitors to a topic never search it. If
 *   it can't be loaded the box says so and the page's own lists stay.
 *
 * Titles, speakers and series are set as text, never as HTML. Strings come from
 * wp_localize_script() (nmLiveSearch).
 *
 * Re-entrant: nm-router.js (site-wide) can swap #content without a real page
 * load, so init() re-runs on its 'nm:content-swapped' event every time one of
 * these pages is routed to. Every element it binds lives inside #content, so a
 * previous visit's listeners are destroyed along with that old markup — no
 * teardown needed, just re-querying. The index is kept between visits, so a
 * second visit doesn't download or prepare it again.
 */
( function ( document, window ) {
	'use strict';

	var PAGE_SIZE = 60;
	var DEBOUNCE  = 140;

	var text = window.nmLiveSearch || {};
	function t( key, fallback ) {
		return text[ key ] || fallback;
	}

	function format( template, values ) {
		var i = 0;
		return template.replace( /%(\d+\$)?s/g, function ( match, position ) {
			var n = position ? parseInt( position, 10 ) - 1 : i++;
			return values[ n ] !== undefined ? values[ n ] : '';
		} );
	}

	// The shiur index by address, as a promise of the prepared index. The address
	// carries the index's version, so a new index is a new entry.
	var indexes = {};

	function loadIndex( url ) {
		var engine = window.nmShiurSearch;
		if ( ! engine || ! window.fetch ) {
			return Promise.reject( new Error( 'Search not available' ) );
		}
		if ( ! indexes[ url ] ) {
			indexes[ url ] = fetch( url, { credentials: 'same-origin' } )
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
					return engine.prepare( data );
				} );
			indexes[ url ].catch( function () {
				delete indexes[ url ];
			} );
		}
		return indexes[ url ];
	}

	// The dates are shown in UTC: the index holds the post's own date and time
	// read as UTC, so this gives the same date the rest of the site shows.
	function formatDate( ts ) {
		return new Date( ts * 1000 ).toLocaleDateString( undefined, { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' } );
	}

	/*
	 * A list of results, a few at a time: the rows, the "Show more" button and the
	 * count line. Shared by the search page and the topic box. Close matches come
	 * after the full ones, under a heading that says how many there are.
	 */
	function resultsList( data, list, moreBtn, countEl ) {
		var KIND = { a: t( 'audio', 'Audio' ), v: t( 'video', 'Video' ), w: t( 'written', 'Written' ) };
		var found = { matches: [], exact: 0, close: 0 };
		var shown = 0;

		function row( it ) {
			var li       = document.createElement( 'li' );
			li.className = 'nm-live-results__item';

			var link         = document.createElement( 'a' );
			link.className   = 'nm-live-results__title';
			link.href        = it.u;
			link.textContent = it.m;
			li.appendChild( link );

			var meta         = document.createElement( 'span' );
			meta.className   = 'nm-live-results__meta';
			meta.textContent = [
				data.speakers[ it.a ] || '',
				data.series[ it.s ] || '',
				formatDate( it.t ),
				KIND[ it.c ] || '',
				it.d || ''
			].filter( Boolean ).join( ' · ' );
			li.appendChild( meta );

			return li;
		}

		function closeHeading() {
			var li       = document.createElement( 'li' );
			li.className = 'nm-live-results__divider';
			li.textContent = 1 === found.close
				? t( 'closeOne', '1 close match · spelled a little differently' )
				: format( t( 'closeMany', '%s close matches · spelled a little differently' ), [ found.close.toLocaleString() ] );
			return li;
		}

		function updateCount( none ) {
			var total = found.matches.length;
			if ( 0 === total ) {
				countEl.textContent = none;
			} else if ( shown < total ) {
				countEl.textContent = format( t( 'showing', 'Showing %1$s of %2$s shiurim' ), [ shown.toLocaleString(), total.toLocaleString() ] );
			} else if ( 1 === total ) {
				countEl.textContent = t( 'foundOne', '1 shiur found.' );
			} else {
				countEl.textContent = format( t( 'found', '%s shiurim found.' ), [ total.toLocaleString() ] );
			}
		}

		var none = '';

		function showMore() {
			var end      = Math.min( found.matches.length, shown + PAGE_SIZE );
			var fragment = document.createDocumentFragment();
			for ( var i = shown; i < end; i++ ) {
				if ( i === found.exact && found.close ) {
					fragment.appendChild( closeHeading() );
				}
				fragment.appendChild( row( found.matches[ i ].it ) );
			}
			list.appendChild( fragment );
			shown          = end;
			moreBtn.hidden = shown >= found.matches.length;
			updateCount( none );
		}

		moreBtn.addEventListener( 'click', showMore );

		return {
			// Shows a new set of results from the top. `message` is the count line when there are none.
			show: function ( result, message ) {
				found            = result;
				none             = message;
				list.textContent = '';
				shown            = 0;
				showMore();
			},
			clear: function () {
				found            = { matches: [], exact: 0, close: 0 };
				list.textContent = '';
				shown            = 0;
				moreBtn.hidden   = true;
				countEl.textContent = '';
			}
		};
	}

	/* ---------------------------------------------------------------------
	 * The Shiurim search page
	 * ------------------------------------------------------------------ */
	function initSearchPage() {
		var root = document.querySelector( '[data-live-search]' );
		if ( ! root ) {
			return;
		}

		var engine = window.nmShiurSearch;

		var input     = root.querySelector( '[data-live-query]' );
		var speakerEl = root.querySelector( '[data-live-speaker]' );
		var seriesEl  = root.querySelector( '[data-live-series]' );
		var topicEl   = root.querySelector( '[data-live-topic]' );
		var yearEl    = root.querySelector( '[data-live-year]' );
		var countEl   = root.querySelector( '[data-live-count]' );
		var list      = root.querySelector( '[data-live-results]' );
		var moreBtn   = root.querySelector( '[data-live-more]' );
		var titleEl   = document.querySelector( '[data-live-title]' );

		var typing = null;
		var data   = null;
		var view   = null;

		// The plain search, ranked on the server: used when the index can't be loaded.
		function goPlain() {
			var params = new URLSearchParams( window.location.search );
			params.set( 'nm_plain', '1' );
			window.location.replace( window.location.pathname + '?' + params.toString() );
		}

		if ( ! window.fetch || ! window.URLSearchParams || ! engine ) {
			goPlain();
			return;
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

		function fillFilters() {
			addOptions( speakerEl, sortedNames( data.speakers ) );
			addOptions( seriesEl, sortedNames( data.series ) );
			// Topics come with core v0.9.19. With an older index there are none, so the filter stays hidden.
			if ( topicEl ) {
				addOptions( topicEl, sortedNames( data.topics ) );
				var topicLabel = topicEl.closest( 'label' );
				if ( topicLabel ) {
					topicLabel.hidden = ! Object.keys( data.topics ).length;
				}
			}
			addOptions(
				yearEl,
				data.years.map( function ( y ) {
					return { id: y, name: y };
				} )
			);
		}

		/*
		 * The filters and search text live in the URL, so a link or Back returns to
		 * the same results. tp (topic) is named the same way: topic is the topic taxonomy's.
		 * The names are by, sr and yr on purpose: speaker and series
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
			if ( topicEl ) {
				topicEl.value = params.get( 'tp' ) || '';
			}
			yearEl.value    = params.get( 'yr' ) || '';
			// An option that isn't in the index (an old link) falls back to "All".
			[ speakerEl, seriesEl, topicEl, yearEl ].forEach( function ( select ) {
				if ( select && select.selectedIndex < 0 ) {
					select.value = '';
				}
			} );
		}

		function writeUrl() {
			var params = new URLSearchParams( window.location.search );
			params.set( 's', input.value );
			params.set( 'post_type', 'shiur' );
			params.delete( 'nm_plain' );
			[ [ 'by', speakerEl.value ], [ 'sr', seriesEl.value ], [ 'tp', topicEl ? topicEl.value : '' ], [ 'yr', yearEl.value ] ].forEach( function ( pair ) {
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

		function run() {
			writeUrl();
			updateTitle();

			// The index is newest first, so with no search words that order is kept.
			var result = engine.search( data, input.value, {
				speaker: speakerEl.value,
				series: seriesEl.value,
				topic: topicEl ? topicEl.value : '',
				year: yearEl.value
			} );
			view.show( result, t( 'none', 'No shiurim match these words and filters. Try fewer or different words.' ) );
		}

		function schedule() {
			window.clearTimeout( typing );
			typing = window.setTimeout( run, DEBOUNCE );
		}

		root.hidden = false;
		countEl.textContent = t( 'loading', 'Loading shiurim…' );

		loadIndex( root.getAttribute( 'data-index-url' ) )
			.then( function ( prepared ) {
				data = prepared;
				view = resultsList( data, list, moreBtn, countEl );
				fillFilters();
				readUrl();
				run();

				input.addEventListener( 'input', schedule );
				speakerEl.addEventListener( 'change', run );
				seriesEl.addEventListener( 'change', run );
				if ( topicEl ) {
					topicEl.addEventListener( 'change', run );
				}
				yearEl.addEventListener( 'change', run );
			} )
			.catch( goPlain );
	}

	/* ---------------------------------------------------------------------
	 * The search box on a topic page
	 * ------------------------------------------------------------------ */
	function initTopicSearch() {
		var root = document.querySelector( '[data-topic-search]' );
		if ( ! root ) {
			return;
		}

		var engine  = window.nmShiurSearch;
		var topicId = root.getAttribute( 'data-topic-id' );
		var input   = root.querySelector( '[data-topic-query]' );
		var countEl = root.querySelector( '[data-topic-count]' );
		var list    = root.querySelector( '[data-topic-results]' );
		var moreBtn = root.querySelector( '[data-topic-more]' );
		var allEl   = root.querySelector( '[data-topic-all]' );
		var allLink = allEl ? allEl.querySelector( 'a' ) : null;
		// The page's own lists (the track list and written shiurim, or the Classic table),
		// which the search takes the place of while there is something typed.
		var browse  = Array.prototype.slice.call( document.querySelectorAll( '[data-topic-browse]' ) );

		// Without what it needs the box stays hidden (it starts hidden) and the page is as it was.
		if ( ! engine || ! window.fetch || ! window.URLSearchParams || ! input || ! topicId ) {
			return;
		}

		var typing  = null;
		var data    = null;
		var view    = null;
		var ready   = null;
		var waiting = false;

		function showBrowse( on ) {
			browse.forEach( function ( el ) {
				el.hidden = ! on;
			} );
		}

		/*
		 * The index (a few hundred KB, and growing with the library) is only fetched
		 * when the box is used: when it is clicked into, or when the page was opened
		 * with a search in its address. Most visitors to a topic never search it.
		 * Once fetched the browser keeps it, so the next topic page has it at once.
		 */
		function start() {
			if ( ! ready ) {
				ready = loadIndex( root.getAttribute( 'data-index-url' ) ).then( function ( prepared ) {
					// An index from before topics has none, and one not rebuilt yet since this
					// topic got its first shiur doesn't have it.
					if ( ! prepared.topics || ! prepared.topics[ topicId ] ) {
						throw new Error( 'No topic in the index' );
					}
					data = prepared;
					view = resultsList( data, list, moreBtn, countEl );
					return data;
				} );
				ready.catch( function () {
					ready = null; // the next try starts again
				} );
			}
			return ready;
		}

		/*
		 * The search is kept with this page's entry in the browser's history, not in the
		 * address. So it comes back after Back (from a result, say) or a layout switch,
		 * which the page router does by swapping the page in and so keeps the entry. A
		 * link to the page, a shared one, or a reload (the router starts a loaded page's
		 * entry afresh) opens it empty. Nothing about it is in the URL, which keeps it out
		 * of cached copies of the page and the page statistics, and out of the page-number
		 * links of the topic's own list (WordPress copies ?q=… into those, so Next would have carried
		 * an old search to the next page). A #q=… would have made Astra's anchor
		 * scrolling throw: it takes the address's # to be an element to scroll to.
		 */
		function savedSearch() {
			var state = window.history.state;
			return state && 'object' === typeof state && 'string' === typeof state.nmTopicSearch ? state.nmTopicSearch : '';
		}

		function saveSearch( query ) {
			var state = window.history.state && 'object' === typeof window.history.state ? Object.assign( {}, window.history.state ) : {};
			if ( query ) {
				state.nmTopicSearch = query;
			} else {
				delete state.nmTopicSearch;
			}
			window.history.replaceState( state, '', window.location.href );
		}


		function clearResults() {
			if ( view ) {
				view.clear();
			} else {
				countEl.textContent = '';
			}
			root.classList.remove( 'is-searching' );
			if ( allEl ) {
				allEl.hidden = true;
			}
			showBrowse( true );
		}

		// The search can't be done: say so, and leave the page's own lists showing.
		function unavailable() {
			waiting = false;
			root.classList.remove( 'is-searching' );
			showBrowse( true );
			countEl.textContent = t( 'unavailable', 'Search isn’t working right now. Please try again in a moment.' );
		}

		function run() {
			var query = input.value.trim();
			saveSearch( query );

			if ( ! query ) {
				clearResults();
				return;
			}

			// The first time: fetch the index, then come back here. The page's own lists stay
			// until there is something to put in their place.
			if ( ! data ) {
				countEl.textContent = t( 'loading', 'Loading shiurim…' );
				if ( ! waiting ) {
					waiting = true;
					start().then( function () {
						waiting = false;
						run();
					}, unavailable );
				}
				return;
			}

			root.classList.add( 'is-searching' );
			showBrowse( false );

			var result = engine.search( data, query, { topic: topicId } );
			view.show( result, format( root.getAttribute( 'data-none' ) || 'No shiurim here match “%s”.', [ query ] ) );

			// A way out when this topic doesn't have it: the same search across every shiur.
			if ( allEl && allLink ) {
				var url = new URL( root.getAttribute( 'data-all-url' ) || '/', window.location.href );
				url.searchParams.set( 's', query );
				url.searchParams.set( 'post_type', 'shiur' );
				allLink.href        = url.toString();
				allLink.textContent = format( root.getAttribute( 'data-all-label' ) || 'Search all shiurim for “%s”', [ query ] );
				allEl.hidden        = false;
			}
		}

		function schedule() {
			window.clearTimeout( typing );
			typing = window.setTimeout( run, DEBOUNCE );
		}

		input.addEventListener( 'input', schedule );
		// Getting ready as the box is clicked into: the index is on its way before the first letter.
		input.addEventListener( 'focus', function () {
			start().catch( function () {} );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && input.value ) {
				input.value = '';
				window.clearTimeout( typing );
				run();
			} else if ( 'Enter' === e.key ) {
				// The results are already showing: Enter just puts the phone keyboard away.
				window.clearTimeout( typing );
				run();
				input.blur();
			}
		} );

		root.hidden = false;

		// Back to this page, or a layout switch: the search it had comes back.
		var first = savedSearch();
		if ( first || input.value ) {
			input.value = input.value || first;
			run();
		}
	}

	function init() {
		initSearchPage();
		initTopicSearch();
	}

	init();
	document.addEventListener( 'nm:content-swapped', init );
} )( document, window );
