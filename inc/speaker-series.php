<?php
/**
 * A speaker's series, sorted and split for the speaker page (taxonomy-speaker.php).
 *
 * The page used to list every series that holds even one of the speaker's shiurim, A to Z by
 * name, each card with the whole series' count. On the live library that is a jumble: of the 52
 * cards on Rabbi Abramowitz's page, 51 are series made up of other speakers' shiurim ("Moadim /
 * Purim", 48 shiurim, 2 of them his), and A to Z puts Sefer Bamidbar before Sefer Bereishis and
 * Perek 10 before Perek 2.
 *
 * Now the page has two groups: the series that are all the speaker's, and under them the series
 * that also hold other speakers' shiurim, each card saying how many are the speaker's and who
 * else is in it. Both are in the order the old site listed its categories (Gemara, Halacha,
 * Moadim, the Chumashim; masechtos in Shas order, holidays through the year, parshiyos in Torah
 * order, perakim by number), not A to Z.
 *
 * Reads the plugin's data model (the speaker and series taxonomies) with plain WordPress
 * queries, so nothing here needs a newer ner-michoel-core.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Order.
 * ------------------------------------------------------------------ */

/**
 * A name reduced for matching: entities decoded, lower case, letters and digits only. "Asara
 * B'Teves" and "Asara BTeves" match, and "Tisha Bav &amp; 3 Weeks" is "tishabav3weeks".
 */
function ner_michoel_series_norm( $text ) {
	$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	return (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', $text );
}

/**
 * The orders, as position by normalised name. The category list, the masechtos, the holidays and
 * the halachos are the old site's own (its Shiurim filters); the parshiyos are Studio's list.
 *
 * @return array[] 'category', 'gemara', 'moadim', 'halacha', 'parsha' => name => position.
 */
function ner_michoel_series_ranks() {
	static $ranks = null;
	if ( null !== $ranks ) {
		return $ranks;
	}

	$lists = array(
		'category' => array(
			'Gemara', 'Mishna Yomis', 'Halacha', 'Moadim', 'Vaadim', 'Weekly Parsha',
			'Sefer Bereishis', 'Sefer Shemos', 'Sefer Vayikra', 'Sefer Bamidbar', 'Sefer Devarim',
			'Shiur Klali', 'Sefer Hamitzvos', 'Yarchei Kallah', 'Ner Michoel CD Projects',
			'Hespedim of HaRav Mosheh Twersky zt"l', 'Other Shiurim', 'Written Shiurim',
		),
		// Shas order. The old site's filter lists the ones it has in this order too.
		'gemara'   => array(
			'Berochos', 'Shabbos', 'Eiruvin', 'Pesachim', 'Shekalim', 'Yoma', 'Sukkah', 'Beitzah', 'Rosh Hashanah', 'Taanis', 'Megillah', 'Moed Katan', 'Chagigah',
			'Yevamos', 'Kesubos', 'Nedarim', 'Nazir', 'Sotah', 'Gittin', 'Kiddushin',
			'Bava Kamma', 'Bava Metzia', 'Bava Basra', 'Sanhedrin', 'Makkos', 'Shevuos', 'Avodah Zarah', 'Horayos',
			'Zevachim', 'Menachos', 'Chullin', 'Bechoros', 'Arachin', 'Temurah', 'Kerisus', 'Meilah', 'Tamid', 'Kodshim',
			'Niddah',
		),
		// Through the year from Tishrei.
		'moadim'   => array(
			'Rosh Hashanah', 'Yom Kippur', 'Sukkos', 'Chanukah', "Asara B'Teves", 'Purim', 'Pesach', 'Sefiras Haomer', 'Shavuos', 'Tisha Bav & 3 Weeks',
		),
		// Kitzur Shulchan Aruch.
		'halacha'  => array(
			'Hilchos Tzitzis', 'Hilchos Krias Shema', 'Hilchos Tefillah', 'Hilchos Krias HaTorah', 'Hilchos Brochos', 'Hilchos Shabbos',
			'Hilchos Rosh Hashanah-Yom Kippur', 'Hilchos Sukkah & Arba Minim', 'Hilchos Chanukah', 'Hilchos Purim', 'Hilchos Pesach',
			'Hilchos Sefiras Haomer', 'Hilchos Shavuos', 'Hilchos Tisha Bav', 'Hilchos Chol Hamoed', 'Hilchos Kibbud Av VeEim',
			'Hilchos Kashrus', 'Hilchos Shemitta', "Yoreh Deah: Issur V'Heter", 'Yoreh Deah: Hilchos Niddah', 'The Six Constant Mitzvos',
		),
		'parsha'   => function_exists( 'ner_michoel_studio_parsha_order' ) ? ner_michoel_studio_parsha_order() : array(),
	);

	$ranks = array();
	foreach ( $lists as $key => $names ) {
		$ranks[ $key ] = array();
		foreach ( array_values( $names ) as $position => $name ) {
			$norm = ner_michoel_series_norm( $name );
			if ( '' !== $norm && ! isset( $ranks[ $key ][ $norm ] ) ) {
				$ranks[ $key ][ $norm ] = $position;
			}
		}
	}

	// Spellings the old site uses that Studio's parsha list spells another way.
	foreach ( array( 'miketz' => 'mikeitz', 'vezoshaberachah' => 'vezoshaberacha' ) as $alias => $same ) {
		if ( isset( $ranks['parsha'][ $same ] ) && ! isset( $ranks['parsha'][ $alias ] ) ) {
			$ranks['parsha'][ $alias ] = $ranks['parsha'][ $same ];
		}
	}

	return $ranks;
}

/**
 * A series name as a sort key: one [ rank, text ] pair for each part of its path ("Gemara / Bava
 * Kamma / Perek 2"). The first part ranks by the category list. The second ranks by the masechtos
 * under Gemara, the holidays under Moadim, the halachos under Halacha, the parshiyos under a Sefer.
 * Everything else, and anything the lists don't know, has no rank and goes by name with numbers
 * in numeric order (Perek 2 before Perek 10), after what is ranked.
 */
function ner_michoel_series_order_key( $name ) {
	$ranks = ner_michoel_series_ranks();
	$parts = preg_split( '#\s+/\s+#u', html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
	$parts = array_values( array_filter( array_map( 'trim', (array) $parts ), 'strlen' ) );

	$sefarim = array( 'seferbereishis', 'sefershemos', 'sefervayikra', 'seferbamidbar', 'seferdevarim' );
	$key     = array();
	$group   = '';
	foreach ( $parts as $i => $part ) {
		$norm = ner_michoel_series_norm( $part );
		$rank = 0;
		if ( 0 === $i ) {
			$rank = isset( $ranks['category'][ $norm ] ) ? $ranks['category'][ $norm ] : 1000;
			if ( in_array( $norm, array( 'gemara', 'moadim', 'halacha' ), true ) ) {
				$group = $norm;
			} elseif ( in_array( $norm, $sefarim, true ) ) {
				$group = 'parsha';
			}
		} elseif ( 1 === $i && '' !== $group ) {
			$rank = isset( $ranks[ $group ][ $norm ] ) ? $ranks[ $group ][ $norm ] : 1000;
		}
		$key[] = array( $rank, $part );
	}
	return $key;
}

/**
 * -1, 0 or 1 for two sort keys (ner_michoel_series_order_key()). A path that continues another
 * ("... / Perek 2 / Moadim / Chanukah") comes right after it.
 */
function ner_michoel_series_compare_keys( $a, $b ) {
	$shared = min( count( $a ), count( $b ) );
	for ( $i = 0; $i < $shared; $i++ ) {
		if ( $a[ $i ][0] !== $b[ $i ][0] ) {
			return $a[ $i ][0] < $b[ $i ][0] ? -1 : 1;
		}
		$by_text = strnatcasecmp( $a[ $i ][1], $b[ $i ][1] );
		if ( 0 !== $by_text ) {
			return $by_text < 0 ? -1 : 1;
		}
	}
	return count( $a ) <=> count( $b );
}

/**
 * Items in series order. $name_of gives the series name of an item (a term's name, say). Items
 * with the same name keep the order they came in.
 *
 * @param array    $items   Anything.
 * @param callable $name_of Item => series name.
 * @return array
 */
function ner_michoel_sort_by_series_order( array $items, $name_of ) {
	$keyed = array();
	foreach ( array_values( $items ) as $i => $item ) {
		$keyed[] = array( ner_michoel_series_order_key( call_user_func( $name_of, $item ) ), $i, $item );
	}
	usort(
		$keyed,
		function ( $a, $b ) {
			$by_key = ner_michoel_series_compare_keys( $a[0], $b[0] );
			return 0 !== $by_key ? $by_key : $a[1] <=> $b[1];
		}
	);
	return array_column( $keyed, 2 );
}

/* ------------------------------------------------------------------
 * Who is in which series.
 * ------------------------------------------------------------------ */

/**
 * Published shiurim in every series, and how many of them are each speaker's, in two queries
 * for the whole library (about 4,000 shiurim at the last count). Written shiurim, drafts and
 * trashed shiurim are left out, as everywhere else.
 *
 * 'total'    series term ID => shiurim in it, whoever they are by (also those with no speaker).
 * 'speakers' series term ID => ( speaker term ID => that speaker's shiurim in it ). A shiur given
 *            by two speakers counts for both.
 *
 * @return array[]
 */
function ner_michoel_series_speaker_counts() {
	static $counts = null;
	if ( null !== $counts ) {
		return $counts;
	}

	// Kept until the library changes, when the plugin has that cache (ner-michoel-core 0.9.27+):
	// every speaker page asks for the same two whole-library counts.
	if ( function_exists( 'ner_michoel_library_cache' ) ) {
		$counts = ner_michoel_library_cache( 'series-speaker-counts', 'ner_michoel_series_speaker_counts_query' );
		return $counts;
	}

	$counts = ner_michoel_series_speaker_counts_query();
	return $counts;
}

/**
 * The two queries behind ner_michoel_series_speaker_counts().
 *
 * @return array[]
 */
function ner_michoel_series_speaker_counts_query() {
	global $wpdb;

	$counts = array(
		'total'    => array(),
		'speakers' => array(),
	);

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed strings and table names only, no input.
	$totals = $wpdb->get_results(
		"SELECT tt.term_id AS series_id, COUNT(DISTINCT p.ID) AS n
		FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'series'
		INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'shiur' AND p.post_status = 'publish'
		GROUP BY tt.term_id"
	);
	$pairs  = $wpdb->get_results(
		"SELECT se.term_id AS series_id, sp.term_id AS speaker_id, COUNT(DISTINCT p.ID) AS n
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->term_relationships} tr_se ON tr_se.object_id = p.ID
		INNER JOIN {$wpdb->term_taxonomy} se ON se.term_taxonomy_id = tr_se.term_taxonomy_id AND se.taxonomy = 'series'
		INNER JOIN {$wpdb->term_relationships} tr_sp ON tr_sp.object_id = p.ID
		INNER JOIN {$wpdb->term_taxonomy} sp ON sp.term_taxonomy_id = tr_sp.term_taxonomy_id AND sp.taxonomy = 'speaker'
		WHERE p.post_type = 'shiur' AND p.post_status = 'publish'
		GROUP BY se.term_id, sp.term_id"
	);
	// phpcs:enable

	foreach ( (array) $totals as $row ) {
		$counts['total'][ (int) $row->series_id ] = (int) $row->n;
	}
	foreach ( (array) $pairs as $row ) {
		$counts['speakers'][ (int) $row->series_id ][ (int) $row->speaker_id ] = (int) $row->n;
	}

	return $counts;
}

/**
 * A speaker's series for their page, sorted (ner_michoel_series_order_key()) and split:
 *
 * 'own'    the series made up only of this speaker's shiurim.
 * 'shared' the series that also hold other speakers' shiurim (or shiurim with no speaker).
 *
 * Each entry: term (WP_Term), mine (this speaker's shiurim in it), total (all of its shiurim),
 * and others: the other speakers in it, most shiurim first, each [ name, count ].
 *
 * @param int $speaker_term_id The speaker's term ID.
 * @return array[] 'own' and 'shared'.
 */
function ner_michoel_get_speaker_series_groups( $speaker_term_id ) {
	$speaker_term_id = (int) $speaker_term_id;
	$groups          = array(
		'own'    => array(),
		'shared' => array(),
	);

	$counts = ner_michoel_series_speaker_counts();
	$mine   = array();
	foreach ( $counts['speakers'] as $series_id => $by_speaker ) {
		if ( ! empty( $by_speaker[ $speaker_term_id ] ) ) {
			$mine[ $series_id ] = $by_speaker[ $speaker_term_id ];
		}
	}
	if ( ! $mine ) {
		return $groups;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'series',
			'include'    => array_keys( $mine ),
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || ! $terms ) {
		return $groups;
	}

	// The other speakers' names, in one lookup.
	$other_ids = array();
	foreach ( $terms as $term ) {
		foreach ( array_keys( $counts['speakers'][ $term->term_id ] ) as $speaker_id ) {
			if ( $speaker_id !== $speaker_term_id ) {
				$other_ids[ $speaker_id ] = $speaker_id;
			}
		}
	}
	$names = array();
	if ( $other_ids ) {
		$speaker_terms = get_terms(
			array(
				'taxonomy'   => 'speaker',
				'include'    => array_values( $other_ids ),
				'hide_empty' => false,
			)
		);
		foreach ( is_wp_error( $speaker_terms ) ? array() : (array) $speaker_terms as $speaker_term ) {
			$names[ (int) $speaker_term->term_id ] = html_entity_decode( $speaker_term->name, ENT_QUOTES, 'UTF-8' );
		}
	}

	$terms = ner_michoel_sort_by_series_order(
		$terms,
		function ( $term ) {
			return $term->name;
		}
	);

	foreach ( $terms as $term ) {
		$id     = (int) $term->term_id;
		$total  = isset( $counts['total'][ $id ] ) ? $counts['total'][ $id ] : $mine[ $id ];
		$others = array();
		foreach ( $counts['speakers'][ $id ] as $speaker_id => $n ) {
			if ( $speaker_id !== $speaker_term_id && isset( $names[ $speaker_id ] ) ) {
				$others[] = array(
					'name'  => $names[ $speaker_id ],
					'count' => $n,
				);
			}
		}
		usort(
			$others,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'] ? $b['count'] <=> $a['count'] : strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		$groups[ $total > $mine[ $id ] ? 'shared' : 'own' ][] = array(
			'term'   => $term,
			'mine'   => $mine[ $id ],
			'total'  => max( $total, $mine[ $id ] ),
			'others' => $others,
		);
	}

	return $groups;
}

/* ------------------------------------------------------------------
 * What the cards say.
 * ------------------------------------------------------------------ */

/**
 * "12 shiurim" for a series that is all the speaker's, "2 of 61 shiurim" for one that is not.
 */
function ner_michoel_speaker_series_count_label( $entry ) {
	if ( $entry['total'] > $entry['mine'] ) {
		/* translators: 1: the speaker's shiurim in the series, 2: all of its shiurim */
		return sprintf( _n( '%1$d of %2$d shiur', '%1$d of %2$d shiurim', $entry['total'], 'ner-michoel-child' ), $entry['mine'], $entry['total'] );
	}
	/* translators: %d: number of shiurim */
	return sprintf( _n( '%d shiur', '%d shiurim', $entry['total'], 'ner-michoel-child' ), $entry['total'] );
}

/**
 * The tag on a shared series' card: who else is in it ("with Rosh HaYeshiva", "with Rabbi Klein and
 * Rosh HaYeshiva", or for three or more "with 6 other speakers", since a series every speaker gave a
 * shiur in would otherwise name two of them by chance), and everyone with their counts on hover. For
 * ner_michoel_render_media_card()'s `tags`. Empty when no other speaker is named (the shiurim not by
 * this speaker have none).
 */
function ner_michoel_speaker_series_tags( $entry ) {
	$others = $entry['others'];
	if ( ! $others ) {
		return array();
	}

	$names = wp_list_pluck( $others, 'name' );
	if ( 1 === count( $names ) ) {
		/* translators: %s: a speaker's name */
		$label = sprintf( __( 'with %s', 'ner-michoel-child' ), $names[0] );
	} elseif ( 2 === count( $names ) ) {
		/* translators: 1, 2: speakers' names */
		$label = sprintf( __( 'with %1$s and %2$s', 'ner-michoel-child' ), $names[0], $names[1] );
	} else {
		/* translators: %d: how many other speakers are in the series (3 or more) */
		$label = sprintf( __( 'with %d other speakers', 'ner-michoel-child' ), count( $names ) );
	}

	$list = array();
	foreach ( $others as $other ) {
		$list[] = $other['name'] . ' (' . number_format_i18n( $other['count'] ) . ')';
	}

	return array(
		array(
			'label' => $label,
			/* translators: %s: speakers with how many shiurim each, e.g. "Rosh HaYeshiva (12), Rabbi Klein (5)" */
			'title' => sprintf( __( 'Also in this series: %s', 'ner-michoel-child' ), implode( ', ', $list ) ),
		),
	);
}
