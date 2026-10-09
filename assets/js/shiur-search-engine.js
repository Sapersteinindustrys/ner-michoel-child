/*
 * The Shiurim search engine: the matching and ranking behind the live search
 * page (shiur-live-search.js) and the search box on a topic page. It touches no
 * page elements and makes no requests: the index goes in, ranked matches come
 * out. That keeps it testable on its own (it also loads as a CommonJS module).
 *
 * The index is the one served by ner-michoel-core (includes/shiur-index.php).
 * Words are matched anywhere in a shiur's title, speaker or series, and a word
 * inside a longer word counts ("shab" finds "Shabbos"). Results, best first:
 *
 *   0  the whole search is in the title
 *   1  every word is in the title
 *   2  every word is in the title, speaker or series
 *   3  close matches, always after all of the above: every word is there, but
 *      one or two letters are off ("Shabbis", "Chanuka") or swapped ("Shabbso")
 *
 * Close matches are ranked by how little they differ, newest first within that.
 * How many letters may be off depends on the word's length (budget()): short
 * words allow fewer, because one wrong letter changes a short word into a
 * different word. They are checked against whole words only, and a short word
 * keeps its first letter (anchored()): "rav" is never "av". Words with a digit
 * must match exactly: a year that is one digit off is a different year. The
 * list stops at CLOSE_MAX, a safety limit, not something a reader should reach.
 * ner-michoel-core's includes/shiur-search.php follows the same rules for the
 * server-ranked search, with the same budget(), anchored() and distance().
 */
( function ( root, factory ) {
	'use strict';

	var api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}
	if ( root ) {
		root.nmShiurSearch = api;
	}
}( 'undefined' !== typeof window ? window : null, function () {
	'use strict';

	var CLOSE_MAX = 1000;

	// What separates words. Letters, marks (accents, niqqud) and digits stay.
	// Built at run time so a browser without Unicode property escapes falls
	// back to a plainer pattern instead of failing to load this whole file.
	var SPLIT;
	try {
		SPLIT = new RegExp( '[^\\p{L}\\p{M}\\p{N}]+', 'u' );
	} catch ( e ) {
		SPLIT = /[\s!-\/:-@\[-`{-~]+/; // whitespace and ASCII punctuation; every other character stays in the word
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

	// The words of folded text. Apostrophes are dropped rather than splitting,
	// so "v'eim" is "veim" and a search for "veim" finds it; hyphens, commas
	// and other punctuation separate words.
	function tokenize( folded ) {
		return folded.replace( /['"]/g, '' ).split( SPLIT ).filter( Boolean );
	}

	// How many letters of this word may be off. 0: the word must match as it is.
	function budget( word ) {
		var length = word.length;
		if ( length < 3 || /\d/.test( word ) ) {
			return 0;
		}
		return length < 6 ? 1 : 2;
	}

	// Whether a close match has to start with the same letter. Short words are
	// the ones that turn into other words ("rav", "av", "bav"), and a slip on
	// the first letter of a short word is rare.
	function anchored( word ) {
		return word.length < 5;
	}

	/*
	 * The number of single-letter changes (a letter added, left out or
	 * different, or two neighbouring letters swapped) between two words, or
	 * max + 1 when it is more than max. The swap counts as one change, which is
	 * why "shabbso" is one change from "shabbos", not two. Stops early once
	 * every route is already over max.
	 */
	function distance( a, b, max ) {
		var m = a.length;
		var n = b.length;
		if ( Math.abs( m - n ) > max ) {
			return max + 1;
		}
		if ( a === b ) {
			return 0;
		}

		var older = null;
		var prev  = new Array( n + 1 );
		var cur   = new Array( n + 1 );
		var i;
		var j;
		for ( j = 0; j <= n; j++ ) {
			prev[ j ] = j;
		}

		for ( i = 1; i <= m; i++ ) {
			var ca     = a.charCodeAt( i - 1 );
			var rowMin = i;
			cur[ 0 ]   = i;
			for ( j = 1; j <= n; j++ ) {
				var cb = b.charCodeAt( j - 1 );
				var v  = prev[ j - 1 ] + ( ca === cb ? 0 : 1 );
				var t  = prev[ j ] + 1;
				if ( t < v ) {
					v = t;
				}
				t = cur[ j - 1 ] + 1;
				if ( t < v ) {
					v = t;
				}
				if ( i > 1 && j > 1 && ca === b.charCodeAt( j - 2 ) && a.charCodeAt( i - 2 ) === cb ) {
					t = older[ j - 2 ] + 1;
					if ( t < v ) {
						v = t;
					}
				}
				cur[ j ] = v;
				if ( v < rowMin ) {
					rowMin = v;
				}
			}
			if ( rowMin > max ) {
				return max + 1;
			}
			var spare = older;
			older     = prev;
			prev      = cur;
			cur       = spare || new Array( n + 1 );
		}

		return prev[ n ] > max ? max + 1 : prev[ n ];
	}

	// t is the post's own date and time read as UTC, so the year is read as UTC
	// to give the same year the rest of the site shows.
	function yearOf( it ) {
		return String( new Date( it.t * 1000 ).getUTCFullYear() );
	}

	/*
	 * Gets a downloaded index ready: folded title and text for every item, its
	 * year, and the list of years. Does nothing the second time.
	 */
	function prepare( data ) {
		if ( data._prepared ) {
			return data;
		}
		data.speakers = data.speakers || {};
		data.series   = data.series || {};
		data.topics   = data.topics || {};

		var years = {};
		data.items.forEach( function ( it ) {
			it._title = fold( it.m );
			it._text  = fold( [ it.m, data.speakers[ it.a ] || '', data.series[ it.s ] || '' ].join( ' ' ) );
			it._year  = yearOf( it );
			years[ it._year ] = true;
		} );
		data.years = Object.keys( years ).sort( function ( a, b ) {
			return b - a;
		} );

		data._prepared = true;
		return data;
	}

	// Every distinct word in the index, with the items it is in. Built the first
	// time a search can have close matches, then kept.
	function vocabulary( data ) {
		if ( data._vocab ) {
			return data._vocab;
		}
		var map   = new Map();
		var items = data.items;
		for ( var i = 0; i < items.length; i++ ) {
			var words = tokenize( items[ i ]._text );
			for ( var w = 0; w < words.length; w++ ) {
				var list = map.get( words[ w ] );
				if ( ! list ) {
					map.set( words[ w ], [ i ] );
				} else if ( list[ list.length - 1 ] !== i ) {
					list.push( i );
				}
			}
		}
		data._vocab = { map: map, words: Array.from( map.keys() ) };
		return data._vocab;
	}

	function allows( it, f ) {
		if ( ( f.speaker && String( it.a ) !== f.speaker ) || ( f.series && String( it.s ) !== f.series ) || ( f.year && it._year !== f.year ) ) {
			return false;
		}
		if ( f.topic ) {
			if ( ! it.g ) {
				return false;
			}
			for ( var k = 0; k < it.g.length; k++ ) {
				if ( String( it.g[ k ] ) === f.topic ) {
					return true;
				}
			}
			return false;
		}
		return true;
	}

	/*
	 * Items of the vocabulary's words within `max` letters of this one: a map
	 * from the item's position in the index to how near the best of its words
	 * is. That is the number of changes plus a fraction under one that is
	 * smaller the more common the word is: with two words equally far from what
	 * was typed, the one that many shiurim use is the one that was meant ("shabbos",
	 * not "shain" for "shabis"). The whole number is the distance; the fraction
	 * only orders the ties.
	 */
	function nearItems( vocab, word, max ) {
		var near   = new Map();
		var list   = vocab.words;
		var first  = anchored( word ) ? word.charAt( 0 ) : '';
		for ( var i = 0; i < list.length; i++ ) {
			var other = list[ i ];
			if ( first && other.charAt( 0 ) !== first ) {
				continue;
			}
			var d = other === word ? 0 : distance( word, other, max );
			if ( d > max ) {
				continue;
			}
			var items = vocab.map.get( other );
			var score = d + 1 / ( 1 + items.length );
			for ( var k = 0; k < items.length; k++ ) {
				var have = near.get( items[ k ] );
				if ( undefined === have || score < have ) {
					near.set( items[ k ], score );
				}
			}
		}
		return near;
	}

	/*
	 * The close matches among `rest` (positions of the items that didn't match
	 * the search as typed): every word is there, exactly or within its budget.
	 */
	function closeMatches( data, query, rest ) {
		var words = [];
		tokenize( query ).forEach( function ( w ) {
			if ( words.indexOf( w ) === -1 ) {
				words.push( w );
			}
		} );

		var reach = 0;
		words.forEach( function ( w ) {
			reach = Math.max( reach, budget( w ) );
		} );
		if ( ! reach || ! rest.length ) {
			return [];
		}

		var vocab = vocabulary( data );
		var near  = words.map( function ( w ) {
			var max = budget( w );
			return max ? nearItems( vocab, w, max ) : null;
		} );

		var out = [];
		for ( var r = 0; r < rest.length; r++ ) {
			var idx   = rest[ r ];
			var it    = data.items[ idx ];
			var total = 0;
			var extra = 0;
			var fits  = true;
			for ( var w = 0; w < words.length; w++ ) {
				if ( -1 !== it._text.indexOf( words[ w ] ) ) {
					continue;
				}
				var score = near[ w ] ? near[ w ].get( idx ) : undefined;
				if ( undefined === score ) {
					fits = false;
					break;
				}
				var d  = Math.floor( score );
				total += d;
				extra += score - d;
			}
			if ( fits ) {
				// extra is at most one half per word, so dividing by the number of words
				// keeps it from ever outweighing a whole change.
				out.push( { it: it, tier: 3, dist: total, rank: total + extra / words.length } );
			}
		}

		out.sort( function ( a, b ) {
			return a.rank - b.rank || b.it.t - a.it.t;
		} );
		return out.length > CLOSE_MAX ? out.slice( 0, CLOSE_MAX ) : out;
	}

	/*
	 * Searches a prepared index. filters: speaker, series and topic (term IDs)
	 * and year, as strings, each '' for any. Returns:
	 *   matches  the results in order, each { it, tier, dist }
	 *   exact    how many come first as full matches (tiers 0 to 2)
	 *   close    how many close matches (tier 3) follow them
	 * With no search words every item that passes the filters is returned in
	 * the index's own order, which is newest first.
	 */
	function search( data, query, filters ) {
		var f      = filters || {};
		var folded = fold( query );
		var words  = folded ? folded.split( ' ' ) : [];
		var phrase = words.join( ' ' );
		var items  = data.items;
		var found  = [];
		var rest   = [];

		for ( var i = 0; i < items.length; i++ ) {
			var it = items[ i ];
			if ( ! allows( it, f ) ) {
				continue;
			}
			if ( ! words.length ) {
				found.push( { it: it, tier: 0, dist: 0 } );
				continue;
			}

			var inText  = true;
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
				rest.push( i );
				continue;
			}
			var tier = -1 !== it._title.indexOf( phrase ) ? 0 : ( inTitle ? 1 : 2 );
			found.push( { it: it, tier: tier, dist: 0 } );
		}

		if ( words.length ) {
			found.sort( function ( a, b ) {
				return a.tier - b.tier || b.it.t - a.it.t;
			} );
		}

		var close = words.length ? closeMatches( data, folded, rest ) : [];
		return { matches: found.concat( close ), exact: found.length, close: close.length };
	}

	return {
		CLOSE_MAX: CLOSE_MAX,
		fold: fold,
		tokenize: tokenize,
		budget: budget,
		anchored: anchored,
		distance: distance,
		prepare: prepare,
		search: search,
		// For a list that isn't the shiur index (the Written Shiurim library): the
		// close matches among some items, which have a folded `_text` and a date `t`.
		closeMatches: closeMatches
	};
} ) );
