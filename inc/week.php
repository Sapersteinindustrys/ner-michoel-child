<?php
/**
 * The homepage's "This week" section: this week's parsha with its shiurim, and the
 * Yom Tov that is in season with its shiurim (template-parts/home-week.php,
 * assets/css/home-week.css).
 *
 * What the week's parsha is and which shiurim belong to it is ner-michoel-core's
 * (includes/parsha.php, includes/topics.php); this only lays it out. Every call into
 * the plugin is guarded, so the page still renders when the plugin is behind.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The section's stylesheet and the script behind its Chutz La'aretz / Eretz Yisroel buttons
 * (assets/js/home-week.js). Loaded site-wide, like the homepage's own, so the router
 * (nm-router.js) has them ready when it swaps a visitor onto the homepage.
 */
function ner_michoel_enqueue_week_assets() {
	wp_enqueue_style( 'ner-michoel-home-week', NER_MICHOEL_URI . '/assets/css/home-week.css', array( 'ner-michoel-home' ), NER_MICHOEL_VERSION );
	wp_enqueue_script( 'ner-michoel-home-week', NER_MICHOEL_URI . '/assets/js/home-week.js', array(), NER_MICHOEL_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_week_assets', 23 );

/**
 * One shiur or written shiur as the section lists it: the homepage's own row for a
 * shiur, and the same facts the written list shows for a written one.
 */
function ner_michoel_home_week_row( $post ) {
	if ( 'written_shiur' === $post->post_type ) {
		$item = function_exists( 'ner_michoel_written_data' ) ? ner_michoel_written_data( $post ) : array();
		if ( ! $item ) {
			return null;
		}
		return array(
			'kind'    => 'written',
			'id'      => $item['id'],
			'title'   => $item['main'],
			'note'    => $item['note'],
			'link'    => $item['link'],
			'speaker' => $item['speaker'] ? $item['speaker']->name : '',
			'sefer'   => $item['sefer'],
			'slug'    => $item['slug'],
			'is_new'  => $item['timestamp'] && ( time() - $item['timestamp'] ) <= 14 * DAY_IN_SECONDS,
			'when'    => ner_michoel_home_when( $item['timestamp'] ),
			'iso'     => $item['timestamp'] ? gmdate( 'c', $item['timestamp'] ) : '',
		);
	}
	$row         = ner_michoel_home_shiur_row( $post );
	$row['kind'] = 'shiur';
	return $row;
}

/**
 * "62 shiurim" / "1 shiur".
 */
function ner_michoel_home_week_count( $count ) {
	/* translators: %s: number of shiurim */
	return sprintf( _n( '%s shiur', '%s shiurim', (int) $count, 'ner-michoel-child' ), number_format_i18n( (int) $count ) );
}

/**
 * The week's parsha as the plugin gives it for Chutz La'aretz and for Eretz Yisroel: default
 * (the one shown first), toggle (true in the weeks the two differ and the buttons are on),
 * diaspora, israel. Read once per page. An older plugin knows one parsha only.
 */
function ner_michoel_home_week_regions() {
	static $regions = null;
	if ( null === $regions ) {
		if ( function_exists( 'ner_michoel_get_week_regions' ) ) {
			$regions = ner_michoel_get_week_regions( 5 );
		} else {
			$week    = function_exists( 'ner_michoel_get_week_parsha' ) ? ner_michoel_get_week_parsha( 5 ) : null;
			$regions = array(
				'default'  => 'diaspora',
				'toggle'   => false,
				'diaspora' => $week,
				'israel'   => $week,
			);
		}
	}
	return $regions;
}

/**
 * The two places' names, as the buttons say them.
 */
function ner_michoel_home_week_region_labels() {
	return array(
		'diaspora' => __( "Chutz La'aretz", 'ner-michoel-child' ),
		'israel'   => __( 'Eretz Yisroel', 'ner-michoel-child' ),
	);
}

/**
 * One parsha's panel: type, region, kicker, prefix, name, when, link, count, rows. Null when
 * there is no parsha, or none of its shiurim can be listed.
 */
function ner_michoel_home_week_parsha_panel( $week, $region ) {
	if ( ! $week ) {
		return null;
	}
	$rows = array_values( array_filter( array_map( 'ner_michoel_home_week_row', $week['posts'] ) ) );
	if ( ! $rows ) {
		return null;
	}
	return array(
		'type'   => 'parsha',
		'region' => $region,
		'kicker' => __( "This week's parsha", 'ner-michoel-child' ),
		'prefix' => __( 'Parshas', 'ner-michoel-child' ),
		'name'   => $week['name'],
		/* translators: 1: the Shabbos date such as Oct 31, 2: its Hebrew date such as 19 Cheshvan 5787 */
		'when'   => $week['date'] ? sprintf( __( 'Shabbos %1$s · %2$s', 'ner-michoel-child' ), $week['date'], $week['hebrew'] ) : '',
		'link'   => $week['link'],
		'count'  => $week['count'],
		'rows'   => $rows,
	);
}

/**
 * The parsha panels, by place ('diaspora', 'israel') in the weeks the two differ, else under
 * the one key ''. A place with no panel is null. Built once per page.
 */
function ner_michoel_home_week_parsha_panels() {
	static $panels = null;
	if ( null === $panels ) {
		$regions = ner_michoel_home_week_regions();
		$panels  = array();
		if ( ! empty( $regions['toggle'] ) ) {
			foreach ( array( 'diaspora', 'israel' ) as $region ) {
				$panels[ $region ] = ner_michoel_home_week_parsha_panel( $regions[ $region ], $region );
			}
		} else {
			$panels[''] = ner_michoel_home_week_parsha_panel( $regions[ $regions['default'] ], '' );
		}
	}
	return $panels;
}

/**
 * What the section shows, in order: the week's parsha (one panel, or one for Chutz La'aretz
 * and one for Eretz Yisroel in the weeks they differ, the one not shown first hidden), then
 * the second box: the Yom Tov in season, or what an editor chose (one), each only when it has
 * shiurim. Each: type, region, hidden, kicker, prefix, name, when, link, count, rows. An empty
 * array when there is nothing to show.
 */
function ner_michoel_home_week_data() {
	$out     = array();
	$regions = ner_michoel_home_week_regions();

	foreach ( ner_michoel_home_week_parsha_panels() as $region => $panel ) {
		if ( $panel ) {
			$panel['hidden'] = '' !== $region && $region !== $regions['default'];
			$out[]           = $panel;
		}
	}

	if ( function_exists( 'ner_michoel_get_week_season' ) ) {
		$season = ner_michoel_get_week_season( 4 );
	} elseif ( function_exists( 'ner_michoel_get_active_seasons' ) ) {
		$seasons = ner_michoel_get_active_seasons( 4 );
		$season  = $seasons ? $seasons[0] : null;
	} else {
		$season = null;
	}
	if ( $season ) {
		$rows = array_values( array_filter( array_map( 'ner_michoel_home_week_row', $season['posts'] ) ) );
		if ( $rows ) {
			$out[] = array(
				'type'   => 'season',
				'region' => '',
				'hidden' => false,
				// Something an editor put up that isn't a Yom Tov is "Featured".
				'kicker' => ( isset( $season['kind'] ) && 'featured' === $season['kind'] ) ? __( 'Featured', 'ner-michoel-child' ) : __( 'In season', 'ner-michoel-child' ),
				'prefix' => '',
				'name'   => html_entity_decode( $season['name'], ENT_QUOTES, 'UTF-8' ),
				'when'   => $season['label'],
				'link'   => $season['link'],
				'count'  => $season['count'],
				'rows'   => $rows,
			);
		}
	}

	return $out;
}

/**
 * The buttons between Chutz La'aretz and Eretz Yisroel, for the weeks the two read different
 * parshiyos: default (the place shown first), labels, and a note for each place that has no
 * parsha box this week (Yom Tov there, or no shiurim yet). Null when there is nothing to
 * switch. assets/js/home-week.js shows the buttons and remembers the visitor's choice.
 */
function ner_michoel_home_week_switch() {
	$regions = ner_michoel_home_week_regions();
	if ( empty( $regions['toggle'] ) ) {
		return null;
	}
	$panels = ner_michoel_home_week_parsha_panels();
	if ( ! array_filter( $panels ) ) {
		return null;
	}

	$labels = ner_michoel_home_week_region_labels();
	$notes  = array();
	foreach ( array( 'diaspora', 'israel' ) as $region ) {
		if ( $panels[ $region ] ) {
			continue;
		}
		if ( empty( $regions[ $region ] ) ) {
			/* translators: %s: Chutz La'aretz or Eretz Yisroel */
			$notes[ $region ] = sprintf( __( 'This Shabbos is Yom Tov or Chol Hamoed in %s, so there is no parsha.', 'ner-michoel-child' ), $labels[ $region ] );
		} else {
			/* translators: %s: a parsha such as Noach */
			$notes[ $region ] = sprintf( __( 'No shiurim yet for Parshas %s.', 'ner-michoel-child' ), $regions[ $region ]['name'] );
		}
	}

	return array(
		'default' => $regions['default'],
		'labels'  => $labels,
		'notes'   => $notes,
	);
}
