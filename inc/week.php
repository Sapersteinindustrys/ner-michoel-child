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
 * The section's stylesheet. Loaded site-wide, like the homepage's own, so the router
 * (nm-router.js) has it ready when it swaps a visitor onto the homepage.
 */
function ner_michoel_enqueue_week_assets() {
	wp_enqueue_style( 'ner-michoel-home-week', NER_MICHOEL_URI . '/assets/css/home-week.css', array( 'ner-michoel-home' ), NER_MICHOEL_VERSION );
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
 * What the section shows, in order: the week's parsha, then the Yom Tov in season (one),
 * each only when it has shiurim. Each: type, kicker, prefix, name, when, link, count, rows.
 * An empty array when there is nothing to show.
 */
function ner_michoel_home_week_data() {
	$out = array();

	if ( function_exists( 'ner_michoel_get_week_parsha' ) ) {
		$week = ner_michoel_get_week_parsha( 5 );
		if ( $week ) {
			$rows = array_values( array_filter( array_map( 'ner_michoel_home_week_row', $week['posts'] ) ) );
			if ( $rows ) {
				$out[] = array(
					'type'   => 'parsha',
					'kicker' => __( "This week's parsha", 'ner-michoel-child' ),
					'prefix' => __( 'Parshas', 'ner-michoel-child' ),
					'name'   => $week['name'],
					/* translators: 1: the Shabbos date such as Oct 31, 2: its Hebrew date such as 19 Cheshvan 5787 */
					'when'   => sprintf( __( 'Shabbos %1$s · %2$s', 'ner-michoel-child' ), $week['date'], $week['hebrew'] ),
					'link'   => $week['link'],
					'count'  => $week['count'],
					'rows'   => $rows,
				);
			}
		}
	}

	if ( function_exists( 'ner_michoel_get_active_seasons' ) ) {
		$seasons = ner_michoel_get_active_seasons( 4 );
		if ( $seasons ) {
			$season = $seasons[0];
			$rows   = array_values( array_filter( array_map( 'ner_michoel_home_week_row', $season['posts'] ) ) );
			if ( $rows ) {
				$out[] = array(
					'type'   => 'season',
					'kicker' => __( 'In season', 'ner-michoel-child' ),
					'prefix' => '',
					'name'   => html_entity_decode( $season['name'], ENT_QUOTES, 'UTF-8' ),
					'when'   => $season['label'],
					'link'   => $season['link'],
					'count'  => $season['count'],
					'rows'   => $rows,
				);
			}
		}
	}

	return $out;
}
