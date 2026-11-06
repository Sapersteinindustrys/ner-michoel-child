/*
 * Live search for the Shiurim search page (search-shiurim.php) and for the search
 * box on a topic page (taxonomy-topic.php). Both load the shiur index once
 * (ner-michoel/v1/shiur-index, built in ner-michoel-core includes/shiur-index.php),
 * then filter it in the browser as the reader types. Nothing goes to the server
 * per keystroke. The matching and ranking are shiur-search-engine.js's: full
 * matches first, then close matches (a letter or two off, or swapped) after them.
 *
 * - Search page: also changes with the speaker, series, topic, year and kind
 *   (audio / video / written) filters, and keeps them in the URL. The results
 *   show as a list or as a table (Title, Magid Shiur, Date, Listen / Watch /
 *   Read), like the shiur table on torasmoshe.org: the table on a computer and
 *   the list on a phone unless the reader picks the other one (kept in the URL
 *   as vw= and remembered in the browser). If the index can't be loaded, the page reloads as
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

	// The same day on the Hebrew calendar, "15 Cheshvan 5787", with the month
	// spelled as the lists on the page do (ner_michoel_hebrew_date_label() in
	// ner-michoel-core). Browsers have the calendar built in; one without it
	// shows the English date alone.
	var HEBREW_MONTHS = { Tishri: 'Tishrei', Heshvan: 'Cheshvan', Tevet: 'Teves', Tamuz: 'Tammuz' };
	var hebrewFormat;
	function formatHebrewDate( ts ) {
		try {
			hebrewFormat = hebrewFormat || new Intl.DateTimeFormat( 'en-u-ca-hebrew', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );
			var part = {};
			hebrewFormat.formatToParts( new Date( ts * 1000 ) ).forEach( function ( p ) {
				part[ p.type ] = p.value;
			} );
			if ( ! part.day || ! part.month || ! part.year ) {
				return '';
			}
			return part.day + ' ' + ( HEBREW_MONTHS[ part.month ] || part.month ) + ' ' + part.year;
		} catch ( e ) {
			return '';
		}
	}

	/*
	 * A list of results, a few at a time: the rows, the "Show more" button and the
	 * count line. Shared by the search page and the topic box. Close matches come
	 * after the full ones, under a heading that says how many there are.
	 */
	function resultsList( data, list, moreBtn, countEl, table ) {
		var KIND = { a: t( 'audio', 'Audio' ), v: t( 'video', 'Video' ), w: t( 'written', 'Written' ) };
		var VERB = { a: t( 'listen', 'Listen' ), v: t( 'watch', 'Watch' ), w: t( 'read', 'Read' ) };
		// table: { body: <tbody>, wrap: its box, on: function () { true while the table shows } }.
		var asTable = function () {
			return !! ( table && table.on() );
		};
		var found = { matches: [], exact: 0, close: 0 };
		var shown = 0;

		// "Audio", or "Audio + Written" for a shiur that comes in more than one format (the index's f key).
		function kindLabel( it ) {
			if ( it.f && it.f.length > 1 ) {
				return it.f.split( '' ).map( function ( kind ) {
					return KIND[ kind ] || '';
				} ).filter( Boolean ).join( ' + ' );
			}
			return KIND[ it.c ] || '';
		}

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
				formatHebrewDate( it.t ),
				kindLabel( it ),
				it.d || ''
			].filter( Boolean ).join( ' · ' );
			li.appendChild( meta );

			return li;
		}

		// One table row: the title (with its series under it), the speaker, the date
		// (English, Hebrew under it) and the kinds it comes in, each with its icon.
		function tableRow( it ) {
			var tr = document.createElement( 'tr' );

			var title         = document.createElement( 'td' );
			title.className   = 'nm-live-table__title';
			var link          = document.createElement( 'a' );
			link.href         = it.u;
			link.textContent  = it.m;
			title.appendChild( link );
			if ( data.series[ it.s ] ) {
				var series         = document.createElement( 'span' );
				series.className   = 'nm-live-table__sub';
				series.textContent = data.series[ it.s ];
				title.appendChild( series );
			}
			tr.appendChild( title );

			var speaker         = document.createElement( 'td' );
			speaker.textContent = data.speakers[ it.a ] || '';
			tr.appendChild( speaker );

			var date         = document.createElement( 'td' );
			date.className   = 'nm-live-table__date';
			date.textContent = formatDate( it.t );
			var hebrew       = formatHebrewDate( it.t );
			if ( hebrew ) {
				var heb         = document.createElement( 'span' );
				heb.className   = 'nm-live-table__sub';
				heb.textContent = hebrew;
				date.appendChild( heb );
			}
			tr.appendChild( date );

			var kinds       = document.createElement( 'td' );
			kinds.className = 'nm-live-table__kinds';
			( it.f && it.f.length > 1 ? it.f : it.c ).split( '' ).forEach( function ( kind ) {
				if ( ! VERB[ kind ] ) {
					return;
				}
				var tag         = document.createElement( 'span' );
				tag.className   = 'nm-live-table__kind nm-live-table__kind--' + kind;
				tag.textContent = VERB[ kind ];
				tag.title       = KIND[ kind ];
				kinds.appendChild( tag );
			} );
			if ( it.d ) {
				var length         = document.createElement( 'span' );
				length.className   = 'nm-live-table__length';
				length.textContent = it.d;
				kinds.appendChild( length );
			}
			tr.appendChild( kinds );

			return tr;
		}

		function closeHeading() {
			if ( asTable() ) {
				var tr          = document.createElement( 'tr' );
				tr.className    = 'nm-live-table__divider';
				var cell        = document.createElement( 'td' );
				cell.colSpan    = 4;
				cell.textContent = closeText();
				tr.appendChild( cell );
				return tr;
			}
			var li       = document.createElement( 'li' );
			li.className = 'nm-live-results__divider';
			li.textContent = closeText();
			return li;
		}

		function closeText() {
			return 1 === found.close
				? t( 'closeOne', '1 close match · spelled a little differently' )
				: format( t( 'closeMany', '%s close matches · spelled a little differently' ), [ found.close.toLocaleString() ] );
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
				fragment.appendChild( asTable() ? tableRow( found.matches[ i ].it ) : row( found.matches[ i ].it ) );
			}
			( asTable() ? table.body : list ).appendChild( fragment );
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
				if ( table ) {
					table.body.textContent = '';
					table.wrap.hidden      = ! asTable() || ! result.matches.length;
					list.hidden            = asTable();
				}
				shown            = 0;
				showMore();
			},
			// Shows the same results again, from the top (after a switch between list and table).
			redraw: function () {
				this.show( found, none );
			},
			clear: function () {
				found            = { matches: [], exact: 0, close: 0 };
				list.textContent = '';
				if ( table ) {
					table.body.textContent = '';
				}
				shown            = 0;
				moreBtn.hidden   = true;
				countEl.textContent = '';
			}
		};
	}

	/*
	 * One entry per shiur: a shiur that comes as audio and video, or with a written sheet, is
	 * in the index once per format. Each is shown once, by its audio if that matched, else its
	 * video, else its written sheet, where the first of them came. The index's x key names the
	 * shiur (core v0.9.45); an older index is grouped by title and speaker, the rule core uses.
	 */
	var KIND_RANK = { a: 0, v: 1, w: 2 };
	function shiurKey( it ) {
		if ( it.x ) {
			return 'x' + it.x;
		}
		if ( it.f && it.f.length > 1 && window.nmShiurSearch ) {
			return 't' + window.nmShiurSearch.fold( it.m ) + '|' + it.a;
		}
		return 'i' + it.i;
	}
	/*
	 * The results in the order the visitor picked: '' keeps the search's own order (best
	 * match first, or newest first with no words), 'new' newest first, 'old' oldest first,
	 * 'speaker' by the speaker's name (then newest first). The full matches stay ahead of
	 * the close ones, each group sorted on its own.
	 */
	function sortMatches( data, result, how ) {
		if ( ! how ) {
			return result;
		}
		var cmp;
		if ( 'old' === how ) {
			cmp = function ( a, b ) {
				return a.it.t - b.it.t;
			};
		} else if ( 'speaker' === how ) {
			cmp = function ( a, b ) {
				var na = ( data.speakers && data.speakers[ a.it.a ] ) || '\uffff';
				var nb = ( data.speakers && data.speakers[ b.it.a ] ) || '\uffff';
				return na.localeCompare( nb ) || b.it.t - a.it.t;
			};
		} else {
			cmp = function ( a, b ) {
				return b.it.t - a.it.t;
			};
		}
		var exact = result.matches.slice( 0, result.exact ).sort( cmp );
		var close = result.matches.slice( result.exact ).sort( cmp );
		return { matches: exact.concat( close ), exact: result.exact, close: result.close };
	}

	function onePerShiur( result ) {
		var best  = {};
		var order = [];
		result.matches.forEach( function ( match, i ) {
			var key  = shiurKey( match.it );
			var rank = KIND_RANK[ match.it.c ] !== undefined ? KIND_RANK[ match.it.c ] : 3;
			if ( ! best[ key ] ) {
				best[ key ] = { match: match, rank: rank, exact: i < result.exact };
				order.push( key );
			} else if ( rank < best[ key ].rank ) {
				best[ key ].match = match;
				best[ key ].rank  = rank;
			}
		} );
		var exact = 0;
		var keep  = order.map( function ( key ) {
			if ( best[ key ].exact ) {
				exact++;
			}
			return best[ key ].match;
		} );
		return { matches: keep, exact: exact, close: keep.length - exact };
	}

	// Only the shiurim that come in this kind (their own, or one of their formats).
	function onlyKindOf( result, kind ) {
		if ( ! kind ) {
			return result;
		}
		var exact = 0;
		var keep  = result.matches.filter( function ( match, i ) {
			var ok = -1 !== ( match.it.f || match.it.c ).indexOf( kind );
			if ( ok && i < result.exact ) {
				exact++;
			}
			return ok;
		} );
		return { matches: keep, exact: exact, close: keep.length - exact };
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
		var kindEl    = root.querySelector( '[data-live-kind]' );
		var sortEl    = root.querySelector( '[data-live-sort]' );
		var tableBody = root.querySelector( '[data-live-table]' );
		var tableWrap = root.querySelector( '[data-live-table-wrap]' );
		var viewBtns  = Array.prototype.slice.call( root.querySelectorAll( '[data-live-view]' ) );
		var viewMode  = 'list';
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
			if ( kindEl ) {
				kindEl.value = params.get( 'ty' ) || '';
			}
			if ( sortEl ) {
				sortEl.value = params.get( 'so' ) || '';
			}
			setView( params.get( 'vw' ) || savedView() || ( window.matchMedia && window.matchMedia( '(min-width: 760px)' ).matches ? 'table' : 'list' ) );
			// An option that isn't in the index (an old link) falls back to "All".
			[ speakerEl, seriesEl, topicEl, yearEl, kindEl, sortEl ].forEach( function ( select ) {
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
			[ [ 'by', speakerEl.value ], [ 'sr', seriesEl.value ], [ 'tp', topicEl ? topicEl.value : '' ], [ 'yr', yearEl.value ], [ 'ty', kindEl ? kindEl.value : '' ], [ 'so', sortEl ? sortEl.value : '' ], [ 'vw', tableBody ? viewMode : '' ] ].forEach( function ( pair ) {
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

		// List or table. Only where the page has the table (an older page doesn't).
		function setView( mode ) {
			viewMode = tableBody && 'table' === mode ? 'table' : 'list';
			viewBtns.forEach( function ( btn ) {
				btn.setAttribute( 'aria-pressed', btn.getAttribute( 'data-live-view' ) === viewMode ? 'true' : 'false' );
			} );
		}

		function savedView() {
			try {
				return window.localStorage.getItem( 'nmShiurView' ) || '';
			} catch ( e ) {
				return '';
			}
		}

		function saveView() {
			try {
				window.localStorage.setItem( 'nmShiurView', viewMode );
			} catch ( e ) {}
		}

		// Only the shiurim that come in this kind (its own, or one of its formats).
		function onlyKind( result, kind ) {
			if ( ! kind ) {
				return result;
			}
			var exact = 0;
			var keep  = result.matches.filter( function ( match, i ) {
				var ok = -1 !== ( match.it.f || match.it.c ).indexOf( kind );
				if ( ok && i < result.exact ) {
					exact++;
				}
				return ok;
			} );
			return { matches: keep, exact: exact, close: keep.length - exact };
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
			view.show( sortMatches( data, onePerShiur( onlyKind( result, kindEl ? kindEl.value : '' ) ), sortEl ? sortEl.value : '' ), t( 'none', 'No shiurim match these words and filters. Try fewer or different words.' ) );
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
				view = resultsList(
					data,
					list,
					moreBtn,
					countEl,
					tableBody && tableWrap ? {
						body: tableBody,
						wrap: tableWrap,
						on: function () {
							return 'table' === viewMode;
						}
					} : null
				);
				fillFilters();
				readUrl();
				run();

				if ( kindEl ) {
					kindEl.addEventListener( 'change', run );
				}
				if ( sortEl ) {
					sortEl.addEventListener( 'change', run );
				}
				viewBtns.forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						setView( btn.getAttribute( 'data-live-view' ) );
						saveView();
						writeUrl();
						view.redraw();
					} );
				} );

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
			view.show( onePerShiur( result ), format( root.getAttribute( 'data-none' ) || 'No shiurim here match “%s”.', [ query ] ) );

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

	/* ---------------------------------------------------------------------
	 * The search bar with filters on the shiurim pages
	 * (ner_michoel_render_scoped_search(), inc/revamp.php)
	 * ------------------------------------------------------------------ */
	function initScopeSearch() {
		var root = document.querySelector( '[data-scope-search]' );
		if ( ! root ) {
			return;
		}

		var engine = window.nmShiurSearch;
		if ( ! engine || ! window.fetch || ! window.URLSearchParams ) {
			return; // The box stays hidden and the page is as it was.
		}

		var scope     = root.getAttribute( 'data-scope' ) || 'all';
		var scopeIds  = ( root.getAttribute( 'data-scope-ids' ) || '' ).split( ',' ).filter( Boolean );
		var startOpen = root.hasAttribute( 'data-start-open' );
		var input     = root.querySelector( '[data-scope-query]' );
		var speakerEl = root.querySelector( '[data-scope-speaker]' );
		var seriesEl  = root.querySelector( '[data-scope-series]' );
		var topicEl   = root.querySelector( '[data-scope-topic]' );
		var yearEl    = root.querySelector( '[data-scope-year]' );
		var kindEl    = root.querySelector( '[data-scope-kind]' );
		var sortEl    = root.querySelector( '[data-scope-sort]' );
		var resetBtn  = root.querySelector( '[data-scope-reset]' );
		var toggleBtn = root.querySelector( '[data-scope-toggle]' );
		var filtersEl = root.querySelector( '[data-scope-filters]' );
		var countEl   = root.querySelector( '[data-scope-count]' );
		var list      = root.querySelector( '[data-scope-results]' );
		var moreBtn   = root.querySelector( '[data-scope-more]' );
		var allEl     = root.querySelector( '[data-scope-all]' );
		var allLink   = allEl ? allEl.querySelector( 'a' ) : null;
		var browse    = Array.prototype.slice.call( document.querySelectorAll( '[data-scope-browse]' ) );
		var selects   = [ speakerEl, seriesEl, topicEl, yearEl, kindEl, sortEl ];

		// The value each filter starts with on this page: the page's own speaker, series or topic.
		var SCOPE_SERIES = 'scope';
		var defaults     = { speaker: '', series: '', topic: '', year: '', kind: '' };
		if ( 'speaker' === scope && scopeIds[ 0 ] ) {
			defaults.speaker = scopeIds[ 0 ];
		} else if ( 'series' === scope && scopeIds.length ) {
			defaults.series = SCOPE_SERIES;
		} else if ( 'topic' === scope && scopeIds[ 0 ] ) {
			defaults.topic = scopeIds[ 0 ];
		}

		var typing = null;
		var data   = null;
		var view   = null;
		var ready  = null;

		function showBrowse( on ) {
			browse.forEach( function ( el ) {
				el.hidden = ! on;
			} );
		}

		function addOption( select, value, label ) {
			var el         = document.createElement( 'option' );
			el.value       = value;
			el.textContent = label;
			select.appendChild( el );
		}

		// Each filter offers what this page's shiurim have: a speaker's page lists that
		// speaker's series and years, a series page its speakers.
		function fillFilters() {
			var inScope = scopeTest();
			var seen    = { a: {}, s: {}, g: {}, y: {} };
			data.items.forEach( function ( it ) {
				if ( inScope && ! inScope( it ) ) {
					return;
				}
				seen.a[ it.a ] = true;
				seen.s[ it.s ] = true;
				seen.y[ it._year ] = true;
				( it.g || [] ).forEach( function ( g ) {
					seen.g[ g ] = true;
				} );
			} );
			var names = function ( map, have ) {
				return Object.keys( map ).filter( function ( id ) {
					return !! have[ id ];
				} ).map( function ( id ) {
					return { id: id, name: map[ id ] };
				} ).sort( function ( a, b ) {
					return a.name.localeCompare( b.name );
				} );
			};
			// A speaker's page still offers every speaker, so the filter can be widened.
			var speakers = 'speaker' === scope ? names( data.speakers, data.speakers ) : names( data.speakers, seen.a );
			speakers.forEach( function ( o ) {
				addOption( speakerEl, o.id, o.name );
			} );
			if ( 'series' === scope ) {
				addOption( seriesEl, SCOPE_SERIES, t( 'thisSeries', 'This series (all of it)' ) );
			}
			names( data.series, seen.s ).forEach( function ( o ) {
				addOption( seriesEl, o.id, o.name );
			} );
			if ( topicEl ) {
				var topics = names( data.topics || {}, 'topic' === scope ? ( data.topics || {} ) : seen.g );
				topics.forEach( function ( o ) {
					addOption( topicEl, o.id, o.name );
				} );
				var topicLabel = topicEl.closest( 'label' );
				if ( topicLabel ) {
					topicLabel.hidden = ! topics.length;
				}
			}
			data.years.filter( function ( y ) {
				return seen.y[ y ];
			} ).forEach( function ( y ) {
				addOption( yearEl, y, y );
			} );
		}

		// On a series page, "This series" is the series and every series inside it.
		function scopeTest() {
			if ( 'series' !== scope || ! scopeIds.length ) {
				if ( 'speaker' === scope && scopeIds[ 0 ] ) {
					return function ( it ) {
						return String( it.a ) === scopeIds[ 0 ];
					};
				}
				if ( 'topic' === scope && scopeIds[ 0 ] ) {
					return function ( it ) {
						return !! it.g && it.g.map( String ).indexOf( scopeIds[ 0 ] ) !== -1;
					};
				}
				return null;
			}
			var set = {};
			scopeIds.forEach( function ( id ) {
				set[ id ] = true;
			} );
			return function ( it ) {
				return !! set[ String( it.s ) ];
			};
		}

		function current() {
			return {
				q: input.value.trim(),
				speaker: speakerEl.value,
				series: seriesEl.value,
				topic: topicEl ? topicEl.value : '',
				year: yearEl.value,
				kind: kindEl ? kindEl.value : '',
				sort: sortEl ? sortEl.value : ''
			};
		}

		// Something to search: words typed, a filter moved off where this page starts it, or an order
		// picked (the order covers all of the page's shiurim, from the index, not just the ones shown).
		function isActive( c ) {
			return startOpen || !! c.q || c.speaker !== defaults.speaker || c.series !== defaults.series || c.topic !== defaults.topic || !! c.year || !! c.kind || !! c.sort;
		}

		function setValues( c ) {
			input.value     = c.q || '';
			speakerEl.value = c.speaker;
			seriesEl.value  = c.series;
			if ( topicEl ) {
				topicEl.value = c.topic;
			}
			yearEl.value = c.year;
			if ( kindEl ) {
				kindEl.value = c.kind;
			}
			if ( sortEl ) {
				sortEl.value = c.sort || '';
			}
			selects.forEach( function ( select ) {
				if ( select && select.selectedIndex < 0 ) {
					select.selectedIndex = 0;
				}
			} );
		}

		// Kept with this page's entry in the browser's history (never in the address: pages are cached).
		function saved() {
			var state = window.history.state;
			return state && 'object' === typeof state && state.nmScope && 'object' === typeof state.nmScope ? state.nmScope : null;
		}

		function save( c ) {
			var state = window.history.state && 'object' === typeof window.history.state ? Object.assign( {}, window.history.state ) : {};
			if ( isActive( c ) && ! startOpen ) {
				state.nmScope = c;
			} else {
				delete state.nmScope;
			}
			window.history.replaceState( state, '', window.location.href );
		}

		function start() {
			if ( ! ready ) {
				ready = loadIndex( root.getAttribute( 'data-index-url' ) ).then( function ( prepared ) {
					data = prepared;
					view = resultsList( data, list, moreBtn, countEl );
					fillFilters();
					// Until now each list had only its "All" line: put this page's own filter on.
					speakerEl.value = defaults.speaker;
					seriesEl.value  = defaults.series;
					if ( topicEl ) {
						topicEl.value = defaults.topic;
					}
					selects.forEach( function ( select ) {
						if ( select && select.selectedIndex < 0 ) {
							select.selectedIndex = 0;
						}
					} );
					return data;
				} );
				ready.catch( function () {
					ready = null;
				} );
			}
			return ready;
		}

		function run() {
			var c = current();
			save( c );
			if ( resetBtn ) {
				resetBtn.hidden = ! isActive( c ) || ( startOpen && ! c.q && ! c.speaker && ! c.series && ! c.topic && ! c.year && ! c.kind && ! c.sort );
			}

			if ( ! isActive( c ) ) {
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
				return;
			}

			if ( ! data ) {
				countEl.textContent = t( 'loading', 'Loading shiurim…' );
				start().then( run, function () {
					root.classList.remove( 'is-searching' );
					showBrowse( true );
					countEl.textContent = t( 'unavailable', 'Search isn’t working right now. Please try again in a moment.' );
				} );
				return;
			}

			root.classList.add( 'is-searching' );
			showBrowse( false );

			var only   = SCOPE_SERIES === c.series ? scopeTest() : null;
			var result = engine.search( data, c.q, {
				speaker: c.speaker,
				series: SCOPE_SERIES === c.series ? '' : c.series,
				topic: c.topic,
				year: c.year,
				only: only
			} );
			view.show( sortMatches( data, onePerShiur( onlyKindOf( result, c.kind ) ), c.sort ), t( 'none', 'No shiurim match these words and filters. Try fewer or different words.' ) );

			// A way out: the same words across every shiur, on the full search page.
			if ( allEl && allLink ) {
				if ( c.q && 'all' !== scope ) {
					var url = new URL( root.getAttribute( 'data-all-url' ) || '/', window.location.href );
					url.searchParams.set( 's', c.q );
					url.searchParams.set( 'post_type', 'shiur' );
					allLink.href        = url.toString();
					allLink.textContent = format( t( 'searchAll', 'Search all shiurim for “%s”' ), [ c.q ] );
					allEl.hidden        = false;
				} else {
					allEl.hidden = true;
				}
			}
		}

		function schedule() {
			window.clearTimeout( typing );
			typing = window.setTimeout( run, DEBOUNCE );
		}

		input.addEventListener( 'input', schedule );
		input.addEventListener( 'focus', function () {
			start().catch( function () {} );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && input.value ) {
				input.value = '';
				window.clearTimeout( typing );
				run();
			} else if ( 'Enter' === e.key ) {
				window.clearTimeout( typing );
				run();
				input.blur();
			}
		} );
		selects.forEach( function ( select ) {
			if ( select ) {
				select.addEventListener( 'change', run );
				// The lists fill from the index: get it on the way as soon as a filter is touched.
				select.addEventListener( 'focus', function () {
					start().catch( function () {} );
				} );
				select.addEventListener( 'mousedown', function () {
					start().catch( function () {} );
				} );
			}
		} );
		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				setValues( { q: '', speaker: defaults.speaker, series: defaults.series, topic: defaults.topic, year: '', kind: '', sort: '' } );
				run();
				input.focus();
			} );
		}

		// Filters fold away on a phone, behind the Filters button.
		function setOpen( open ) {
			if ( ! toggleBtn || ! filtersEl ) {
				return;
			}
			filtersEl.hidden = ! open;
			toggleBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
		if ( toggleBtn ) {
			toggleBtn.addEventListener( 'click', function () {
				setOpen( filtersEl.hidden );
				if ( ! filtersEl.hidden ) {
					start().catch( function () {} );
				}
			} );
			setOpen( ! ( window.matchMedia && window.matchMedia( '(max-width: 700px)' ).matches ) );
		}

		root.hidden = false;

		var first = saved();
		if ( first || startOpen ) {
			start().then( function () {
				setValues( first || defaults );
				run();
			}, function () {
				countEl.textContent = t( 'unavailable', 'Search isn’t working right now. Please try again in a moment.' );
			} );
		} else {
			// The filters' lists come from the index: fetched once the page has settled (the
			// browser keeps it, so the next shiurim page has it at once), and at the latest when
			// the box or a filter is touched.
			var settle = window.requestIdleCallback || function ( fn ) {
				return window.setTimeout( fn, 1200 );
			};
			settle( function () {
				start().catch( function () {} );
			}, { timeout: 4000 } );
		}
	}

	function init() {
		initSearchPage();
		initTopicSearch();
		initScopeSearch();
	}

	init();
	document.addEventListener( 'nm:content-swapped', init );
} )( document, window );
