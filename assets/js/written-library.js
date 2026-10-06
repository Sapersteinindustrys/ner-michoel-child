/*
 * Written Shiurim library (archive-written_shiur.php, search-written.php).
 *
 * - Marks sheets the visitor has opened before ("Read"), from this browser.
 * - The archive arrives with its first weeks rendered and the whole library
 *   as a JSON index ([data-written-index], ner_michoel_written_index()).
 *   Search and the author/Sefer/year chips filter that index; matching weeks
 *   are rendered from <template>s (ner_michoel_render_written_templates()),
 *   more as you scroll, so the page stays light however big the library is.
 * - Filters live in the URL (?q=, ?by=, ?sefer=, ?yr=), so a filtered view
 *   can be shared and survives Back. Not "author" or "year": those are
 *   WordPress's own query vars and would filter the page's query itself.
 *   The server filters such a link the same way before this runs
 *   (ner_michoel_written_filter_weeks()); keep matches() and fold() in step
 *   with it. "/" focuses the search.
 * - While filtering, the matches lay out as one grid (written-shiurim.css).
 * - The filter bar folds to one row once it sticks to the top.
 */
( function () {
	'use strict';

	var READ_KEY = 'nmWrittenRead';

	function readIds() {
		try {
			var list = JSON.parse( window.localStorage.getItem( READ_KEY ) || '[]' );
			return Array.isArray( list ) ? list.map( String ) : [];
		} catch ( e ) {
			return [];
		}
	}

	var readList = readIds();
	if ( readList.length ) {
		document.querySelectorAll( '.nm-sheet[data-written-id]' ).forEach( function ( sheet ) {
			if ( readList.indexOf( sheet.getAttribute( 'data-written-id' ) ) !== -1 ) {
				sheet.classList.add( 'is-read' );
			}
		} );
	}

	var bar     = document.querySelector( '[data-written-filters]' );
	var library = document.querySelector( '[data-written-library]' );
	var indexEl = document.querySelector( '[data-written-index]' );
	if ( ! bar || ! library || ! indexEl ) {
		return;
	}

	var index;
	try {
		index = JSON.parse( indexEl.textContent );
	} catch ( e ) {
		return;
	}

	var sheetTpl = document.querySelector( '[data-written-template="sheet"]' );
	var weekTpl  = document.querySelector( '[data-written-template="week"]' );
	if ( ! sheetTpl || ! weekTpl || ! index.weeks || ! index.items ) {
		return;
	}

	var FIRST    = parseInt( indexEl.getAttribute( 'data-first-weeks' ), 10 ) || 20;
	var CHUNK    = 16;
	var input    = bar.querySelector( '[data-written-query]' );
	var form     = bar.querySelector( '[data-written-search-form]' );
	var countEl  = bar.querySelector( '[data-written-count]' );
	var chips    = Array.prototype.slice.call( bar.querySelectorAll( '[data-filter]' ) );
	var clears   = Array.prototype.slice.call( document.querySelectorAll( '[data-written-clear]' ) );
	var empty    = document.querySelector( '[data-written-empty]' );
	var more     = document.querySelector( '[data-written-more]' );
	var toggle   = bar.querySelector( '[data-written-toggle]' );
	var badge    = bar.querySelector( '[data-written-badge]' );
	var sentinel = document.querySelector( '[data-written-sentinel]' );
	// A filtered link arrives showing "Showing 3 of 180"; data-all is the
	// unfiltered line to go back to.
	var allText  = countEl ? ( countEl.getAttribute( 'data-all' ) || countEl.textContent ) : '';
	var speakers = index.speakers || {};
	var state    = { q: '', speaker: '', sefer: '', year: '' };

	// Lowercase, straight quotes, single spaces: "V’Eim" matches "v'eim".
	function fold( text ) {
		return String( text || '' )
			.toLowerCase()
			.replace( /[‘’ʼ`]/g, "'" )
			.replace( /[“”]/g, '"' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	var byId = {};
	index.items.forEach( function ( it ) {
		byId[ it.i ] = it;
		it._text = fold( [ it.m, it.n, speakers[ it.a ], it.s, it.p, it.y, it.x ].join( ' ' ) );
	} );

	function isFiltering() {
		return !! ( fold( state.q ) || state.speaker || state.sefer || state.year );
	}

	function matches( it, words ) {
		if ( state.speaker && String( it.a ) !== state.speaker ) {
			return false;
		}
		if ( state.sefer && it.k !== state.sefer ) {
			return false;
		}
		if ( state.year && it.y !== state.year ) {
			return false;
		}
		for ( var i = 0; i < words.length; i++ ) {
			if ( it._text.indexOf( words[ i ] ) === -1 ) {
				return false;
			}
		}
		return true;
	}

	// Each week of the index, cut down to its matching pieces.
	function matchingWeeks() {
		var words = fold( state.q ).split( ' ' ).filter( Boolean );
		var out   = [];
		index.weeks.forEach( function ( week ) {
			var items = [];
			week.i.forEach( function ( id ) {
				var it = byId[ id ];
				if ( it && matches( it, words ) ) {
					items.push( it );
				}
			} );
			if ( items.length ) {
				out.push( { week: week, items: items } );
			}
		} );
		return out;
	}

	// Building from the templates -------------------------------------------
	function slot( root, name ) {
		return root.querySelector( '[data-slot="' + name + '"]' );
	}

	function fill( root, name, text ) {
		var el = slot( root, name );
		if ( ! el ) {
			return;
		}
		if ( text ) {
			el.textContent = text;
		} else {
			el.parentNode.removeChild( el );
		}
	}

	function drop( root, name ) {
		var el = slot( root, name );
		if ( el ) {
			el.parentNode.removeChild( el );
		}
	}

	function buildSheet( it, feature ) {
		var el    = sheetTpl.content.firstElementChild.cloneNode( true );
		var flags = it.f || 0;
		var cls   = [ 'nm-sheet', 'nm-accent--' + it.k ];
		if ( feature ) {
			cls.push( 'nm-sheet--feature' );
		}
		if ( flags & 1 ) {
			cls.push( 'is-new' );
		}
		if ( ( flags & 2 ) || readList.indexOf( String( it.i ) ) !== -1 ) {
			cls.push( 'is-read' );
		}
		if ( it.x ) {
			cls.push( 'has-summary' );
		}
		el.className = cls.join( ' ' );
		el.setAttribute( 'data-written-id', it.i );
		el.setAttribute( 'data-speaker', it.a || '' );
		el.setAttribute( 'data-sefer', it.k );
		el.setAttribute( 'data-year', it.y || '' );

		fill( el, 'sefer', it.s );
		fill( el, 'topic', it.p );
		if ( ! ( flags & 1 ) ) {
			drop( el, 'new' );
		}
		var title = slot( el, 'title' );
		title.textContent = it.m;
		title.setAttribute( 'href', it.u );
		fill( el, 'note', it.n );
		fill( el, 'summary', it.x );
		fill( el, 'by', speakers[ it.a ] );

		var date = slot( el, 'date' );
		date.textContent = it.d;
		date.setAttribute( 'datetime', new Date( it.t * 1000 ).toISOString() );

		var save = slot( el, 'save' );
		if ( save ) {
			var saved = !! ( flags & 4 );
			save.setAttribute( 'data-save-id', it.i );
			save.classList.toggle( 'is-saved', saved );
			save.setAttribute( 'aria-pressed', saved ? 'true' : 'false' );
		}

		var download = slot( el, 'download' );
		if ( download ) {
			if ( it.w ) {
				download.setAttribute( 'href', it.w );
				download.setAttribute( 'aria-label', ( download.getAttribute( 'data-label' ) || '%s' ).replace( '%s', it.m + ( it.n ? ' (' + it.n + ')' : '' ) ) );
			} else {
				download.parentNode.removeChild( download );
			}
		}
		return el;
	}

	function buildWeek( entry, feature ) {
		var el   = weekTpl.content.firstElementChild.cloneNode( true );
		var week = entry.week;
		el.className = 'nm-week nm-accent--' + week.k + ( feature ? ' nm-week--feature' : '' );

		if ( feature ) {
			var count = slot( el, 'count' );
			var n     = entry.items.length;
			if ( count ) {
				count.textContent = '· ' + ( 1 === n ? count.getAttribute( 'data-one' ) : count.getAttribute( 'data-other' ) ).replace( '%s', n );
			}
		} else {
			drop( el, 'eyebrow' );
			drop( el, 'count' );
		}
		fill( el, 'sefer', week.s );
		slot( el, 'label' ).textContent = week.l;
		fill( el, 'year', week.y );
		slot( el, 'range' ).textContent = week.r;

		var box = slot( el, 'sheets' );
		entry.items.forEach( function ( it ) {
			box.appendChild( buildSheet( it, feature ) );
		} );
		return el;
	}

	// Rendering, a chunk at a time -------------------------------------------
	var matched   = index.weeks.map( function ( week ) {
		return {
			week: week,
			items: week.i.map( function ( id ) {
				return byId[ id ];
			} ).filter( Boolean )
		};
	} );
	var shown     = Math.min( FIRST, matched.length );
	var filtering = false;

	function renderMore( count, animate ) {
		var end  = Math.min( matched.length, shown + count );
		var frag = document.createDocumentFragment();
		var nth  = 0;
		for ( var i = shown; i < end; i++ ) {
			var feature = ! filtering && 0 === i;
			var week    = buildWeek( matched[ i ], feature );
			// A short cascade for the first sheets of a new result. The feature
			// spread keeps still: its sheets are already turned at an angle.
			if ( animate && ! feature ) {
				week.querySelectorAll( '.nm-sheet' ).forEach( function ( sheet ) {
					if ( nth < 12 ) {
						var paper = sheet.querySelector( '.nm-sheet__paper' );
						sheet.classList.add( 'is-entering' );
						paper.style.animationDelay = ( nth * 30 ) + 'ms';
						// Hand the sheet back to its normal hover lift afterwards.
						paper.addEventListener( 'animationend', function () {
							sheet.classList.remove( 'is-entering' );
							paper.style.animationDelay = '';
						}, { once: true } );
					}
					nth++;
				} );
			}
			frag.appendChild( week );
		}
		library.appendChild( frag );
		shown = end;
		if ( more ) {
			more.hidden = shown >= matched.length;
		}
	}

	// Keep going while the end of the list is close to the screen.
	function fillScreen() {
		var guard = 0;
		while ( more && ! more.hidden && more.getBoundingClientRect().top < window.innerHeight + 900 && guard < 20 ) {
			renderMore( CHUNK, false );
			guard++;
		}
	}

	function rerender( animate ) {
		readList = readIds();
		matched  = matchingWeeks();
		library.textContent = '';
		shown = 0;
		renderMore( FIRST, animate );
	}

	function updateUi() {
		var total = 0;
		matched.forEach( function ( entry ) {
			total += entry.items.length;
		} );

		library.classList.toggle( 'is-filtering', filtering );
		if ( empty ) {
			empty.hidden = total > 0;
		}
		clears.forEach( function ( btn ) {
			btn.hidden = ! filtering && btn.closest( '[data-written-filters]' ) !== null;
		} );
		if ( countEl ) {
			countEl.textContent = filtering
				? countEl.getAttribute( 'data-template' ).replace( '%1$s', total ).replace( '%2$s', countEl.getAttribute( 'data-total' ) )
				: allText;
		}
		chips.forEach( function ( chip ) {
			var on = ( state[ chip.getAttribute( 'data-filter' ) ] || '' ) === chip.getAttribute( 'data-value' );
			chip.classList.toggle( 'is-on', on );
			chip.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
		if ( badge ) {
			var active = [ state.speaker, state.sefer, state.year ].filter( Boolean ).length;
			badge.textContent = active;
			badge.hidden = ! active;
		}
	}

	function syncUrl() {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		var params = new URLSearchParams( window.location.search );
		[ [ 'q', state.q ], [ 'by', state.speaker ], [ 'sefer', state.sefer ], [ 'yr', state.year ] ].forEach( function ( pair ) {
			if ( pair[ 1 ] ) {
				params.set( pair[ 0 ], pair[ 1 ] );
			} else {
				params.delete( pair[ 0 ] );
			}
		} );
		var query = params.toString();
		window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash );
	}

	// After a change made far down the page, bring the results back into view.
	function revealResults() {
		var top = library.getBoundingClientRect().top;
		if ( top >= 0 ) {
			return;
		}
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		window.scrollTo( {
			top: window.pageYOffset + top - bar.offsetHeight - 24,
			behavior: reduce ? 'auto' : 'smooth'
		} );
	}

	function update() {
		filtering = isFiltering();
		rerender( true );
		updateUi();
		syncUrl();
		revealResults();
		fillScreen();
	}

	// Start from the URL, so shared and Back-button views come back filtered.
	var initial = new URLSearchParams( window.location.search );
	state.q       = initial.get( 'q' ) || '';
	state.speaker = initial.get( 'by' ) || '';
	state.sefer   = initial.get( 'sefer' ) || '';
	state.year    = initial.get( 'yr' ) || '';
	if ( input ) {
		input.value = state.q;
	}
	filtering = isFiltering();
	if ( filtering ) {
		rerender( false );
	} else if ( more ) {
		more.hidden = shown >= matched.length;
	}
	updateUi();

	// Saving a sheet (custom.js) changes its button; keep the index in step
	// so sheets built later show it saved.
	if ( window.MutationObserver ) {
		new window.MutationObserver( function ( records ) {
			records.forEach( function ( record ) {
				var btn = record.target;
				if ( btn.matches && btn.matches( '.sh-save[data-save-id]' ) ) {
					var it = byId[ btn.getAttribute( 'data-save-id' ) ];
					if ( it ) {
						it.f = ( ( it.f || 0 ) & ~4 ) | ( btn.classList.contains( 'is-saved' ) ? 4 : 0 );
					}
				}
			} );
		} ).observe( library, { subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
	}

	// "/" jumps to the search box, as on most sites with search.
	document.addEventListener( 'keydown', function ( e ) {
		var tag = ( e.target && e.target.tagName ) || '';
		if ( '/' !== e.key || ! input || e.ctrlKey || e.metaKey || e.altKey || /^(INPUT|TEXTAREA|SELECT)$/.test( tag ) || e.target.isContentEditable ) {
			return;
		}
		e.preventDefault();
		input.focus();
	} );

	var typing;
	if ( input ) {
		input.addEventListener( 'input', function () {
			window.clearTimeout( typing );
			typing = window.setTimeout( function () {
				state.q = input.value;
				update();
			}, 140 );
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && input.value ) {
				input.value = '';
				state.q     = '';
				update();
			}
		} );
	}

	// Results are already on the page, so Enter just keeps them.
	if ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			window.clearTimeout( typing );
			state.q = input ? input.value : '';
			update();
		} );
	}

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			var key   = chip.getAttribute( 'data-filter' );
			var value = chip.getAttribute( 'data-value' );
			state[ key ] = state[ key ] === value ? '' : value;
			update();
		} );
	} );

	clears.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state = { q: '', speaker: '', sefer: '', year: '' };
			if ( input ) {
				input.value = '';
			}
			update();
			bar.scrollIntoView( { block: 'nearest' } );
		} );
	} );

	// More weeks: on scroll, and from the button (keyboard and no-observer).
	if ( more ) {
		var moreBtn = more.querySelector( 'button' );
		if ( moreBtn ) {
			moreBtn.addEventListener( 'click', function () {
				renderMore( CHUNK, false );
			} );
		}
		if ( window.IntersectionObserver ) {
			new window.IntersectionObserver( function ( entries ) {
				if ( entries[ 0 ].isIntersecting ) {
					fillScreen();
				}
			}, { rootMargin: '0px 0px 900px 0px' } ).observe( more );
		}
	}

	// The week headings stick just below the filter bar, whatever its height.
	// A sticky bar keeps its space in the page, so while it's folded its
	// bottom margin makes up the difference and nothing below jumps.
	var baseMargin = parseFloat( window.getComputedStyle( bar ).marginBottom ) || 0;
	var fullHeight = bar.offsetHeight;
	function measure() {
		var height = bar.offsetHeight;
		if ( bar.classList.contains( 'is-stuck' ) ) {
			bar.style.marginBottom = ( baseMargin + Math.max( 0, fullHeight - height ) ) + 'px';
		} else {
			bar.style.marginBottom = '';
			baseMargin = parseFloat( window.getComputedStyle( bar ).marginBottom ) || 0;
			fullHeight = height;
		}
		library.style.setProperty( '--nm-filters-height', height + 'px' );
	}
	measure();
	if ( window.ResizeObserver ) {
		new window.ResizeObserver( measure ).observe( bar );
	} else {
		window.addEventListener( 'resize', measure );
	}

	// Fold the bar to one row once it sticks to the top of the window.
	function setOpen( open ) {
		bar.classList.toggle( 'is-open', open );
		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			setOpen( ! bar.classList.contains( 'is-open' ) );
			measure();
		} );
	}

	if ( sentinel && toggle && window.IntersectionObserver ) {
		var adminBar = parseInt( window.getComputedStyle( document.documentElement ).getPropertyValue( '--wp-admin--admin-bar--height' ), 10 ) || 0;
		new window.IntersectionObserver( function ( entries ) {
			var entry = entries[ 0 ];
			var stuck = ! entry.isIntersecting && entry.boundingClientRect.top < ( entry.rootBounds ? entry.rootBounds.top : 0 ) + 1;
			bar.classList.toggle( 'is-stuck', stuck );
			toggle.hidden = ! stuck;
			if ( ! stuck ) {
				setOpen( false );
			}
			measure();
		}, { rootMargin: '-' + adminBar + 'px 0px 0px 0px', threshold: 0 } ).observe( sentinel );
	}
} )();
