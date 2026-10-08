<?php
/**
 * Written Shiurim "reading room": the archive's week-by-week library, the
 * paper-sheet cards, and the single page's reading view.
 *
 * Presentation only. The post type, the PDF accessors and the download
 * route live in ner-michoel-core (includes/written-shiurim.php,
 * includes/downloads.php). Every core call here is guarded, so the pages
 * still render if the plugin is behind.
 *
 * The content has a weekly rhythm (each week's parsha essay and halacha
 * piece), so the archive groups by calendar week rather than by series:
 * a week can hold a Moadim piece and a parsha piece, and the same parsha
 * comes round again every year.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sefer accent for a series name: the first " / " segment picks the
 * colour family, the rest is the topic. "Sefer Devarim / Ki Savo" gives
 * Sefer Devarim, devarim, Ki Savo.
 */
function ner_michoel_written_series_parts( $series_name ) {
	$parts = array_values( array_filter( array_map( 'trim', explode( '/', (string) $series_name ) ), 'strlen' ) );
	if ( ! $parts ) {
		return array(
			'sefer' => '',
			'slug'  => 'other',
			'topic' => '',
		);
	}

	$sefer = $parts[0];
	$topic = count( $parts ) > 1 ? implode( ' · ', array_slice( $parts, 1 ) ) : $parts[0];
	$key   = strtolower( preg_replace( '/^Sefer\s+/i', '', $sefer ) );
	$known = array( 'bereishis', 'shemos', 'vayikra', 'bamidbar', 'devarim', 'moadim', 'gemara' );

	return array(
		'sefer' => $sefer,
		'slug'  => in_array( $key, $known, true ) ? $key : 'other',
		'topic' => $topic,
	);
}

/**
 * Splits the parsha/year tail off a title so it can sit on its own line:
 * "Bizayon Av v'Eim (Ki Savo 5786)" becomes "Bizayon Av v'Eim" with the
 * note "Ki Savo 5786", and "Parshas Haazinu 5787" becomes "Parshas
 * Haazinu" with the note "5787".
 */
function ner_michoel_written_title_parts( $title ) {
	$title = trim( (string) $title );
	if ( preg_match( '/^(.*\S)\s*\(([^()]+)\)\s*$/u', $title, $m ) ) {
		return array(
			'main' => $m[1],
			'note' => trim( $m[2] ),
		);
	}
	if ( preg_match( '/^(.*\S)\s+((?:5[78]|20)\d\d)$/u', $title, $m ) ) {
		return array(
			'main' => $m[1],
			'note' => $m[2],
		);
	}
	return array(
		'main' => $title,
		'note' => '',
	);
}

/**
 * The Hebrew year a title mentions ("Parshas Haazinu 5787"), or ''.
 */
function ner_michoel_written_year( $title ) {
	return preg_match( '/\b(5[78]\d\d)\b/', (string) $title, $m ) ? $m[1] : '';
}

/**
 * Everything a card or the reading view shows for one written shiur.
 *
 * $full: also the PDF's own URL and file size, which only the reading view
 * shows. Cards skip them because the library builds data for every written
 * shiur at once.
 */
function ner_michoel_written_data( $post, $full = false ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}

	$speakers = get_the_terms( $post->ID, 'speaker' );
	$speaker  = ( $speakers && ! is_wp_error( $speakers ) ) ? $speakers[0] : null;
	$series   = get_the_terms( $post->ID, 'series' );
	$serie    = ( $series && ! is_wp_error( $series ) ) ? $series[0] : null;
	$parts    = ner_michoel_written_series_parts( $serie ? $serie->name : '' );
	$title    = get_the_title( $post );
	$split    = ner_michoel_written_title_parts( $title );
	$year     = ner_michoel_written_year( $title );
	// The note line under the title: the split-off tail, or else the year
	// when the title doesn't already show it.
	$note = $split['note'];
	if ( '' === $note && '' !== $year && false === strpos( $split['main'], $year ) ) {
		$note = $year;
	}

	$pdf_url  = '';
	$filesize = 0;
	if ( $full && function_exists( 'ner_michoel_get_written_shiur_pdf_id' ) ) {
		$pdf_id = ner_michoel_get_written_shiur_pdf_id( $post->ID );
		if ( $pdf_id ) {
			$pdf_url  = function_exists( 'ner_michoel_get_written_shiur_pdf_url' ) ? ner_michoel_get_written_shiur_pdf_url( $post->ID ) : '';
			$meta     = wp_get_attachment_metadata( $pdf_id );
			$filesize = ( is_array( $meta ) && ! empty( $meta['filesize'] ) ) ? (int) $meta['filesize'] : 0;
		}
	}

	return array(
		'id'           => $post->ID,
		'title'        => $title,
		'main'         => $split['main'],
		'note'         => $note,
		'year'         => $year,
		'summary'      => function_exists( 'ner_michoel_written_first_line' ) ? ner_michoel_written_first_line( $post ) : '',
		'link'         => get_permalink( $post ),
		'timestamp'    => (int) get_post_time( 'U', true, $post ),
		'date'         => get_the_date( '', $post ),
		'date_short'   => get_the_date( 'M j, Y', $post ),
		'speaker'      => $speaker,
		'series'       => $serie,
		'sefer'        => $parts['sefer'],
		'slug'         => $parts['slug'],
		'topic'        => $parts['topic'],
		'pdf_url'      => $pdf_url,
		'download_url' => function_exists( 'ner_michoel_get_written_shiur_download_url' ) ? ner_michoel_get_written_shiur_download_url( $post->ID ) : '',
		'filesize'     => $filesize,
	);
}

/**
 * Loads every attached PDF's attachment row in one query, so checking each
 * written shiur's PDF (its MIME type, for the Download link) doesn't cost a
 * query apiece across the whole library.
 */
function ner_michoel_written_prime( $posts ) {
	if ( ! function_exists( '_prime_post_caches' ) ) {
		return;
	}
	$ids = array();
	foreach ( $posts as $post ) {
		$pdf_id = (int) get_post_meta( $post->ID, '_written_pdf_id', true );
		if ( $pdf_id ) {
			$ids[] = $pdf_id;
		}
	}
	if ( $ids ) {
		_prime_post_caches( array_unique( $ids ), false, false );
	}
}

/**
 * Groups written shiurim (newest first) by the calendar week, Sunday to
 * Shabbos, they were posted in. A week is named for its parsha: a
 * "Sefer ..." series in the week if there is one, otherwise the first
 * piece's topic.
 */
function ner_michoel_written_weeks( $posts ) {
	$tz    = wp_timezone();
	$weeks = array();

	foreach ( $posts as $post ) {
		$item = ner_michoel_written_data( $post );
		if ( ! $item ) {
			continue;
		}

		$day   = ( new DateTimeImmutable( '@' . $item['timestamp'] ) )->setTimezone( $tz )->setTime( 0, 0 );
		$start = $day->modify( '-' . (int) $day->format( 'w' ) . ' days' );
		$key   = $start->format( 'Y-m-d' );

		if ( ! isset( $weeks[ $key ] ) ) {
			$weeks[ $key ] = array(
				'start' => $start,
				'end'   => $start->modify( '+6 days' ),
				'items' => array(),
			);
		}
		$weeks[ $key ]['items'][] = $item;
	}

	foreach ( $weeks as $key => $week ) {
		$lead = $week['items'][0];
		foreach ( $week['items'] as $item ) {
			if ( 0 === strpos( $item['sefer'], 'Sefer' ) ) {
				$lead = $item;
				break;
			}
		}
		// The parsha piece's own year: a Rosh Hashanah piece in the same week
		// can already carry the next year.
		$year = $lead['year'];
		foreach ( $week['items'] as $item ) {
			if ( '' !== $year ) {
				break;
			}
			$year = $item['year'];
		}
		$weeks[ $key ]['label'] = $lead['topic'] ? $lead['topic'] : $lead['main'];
		$weeks[ $key ]['sefer'] = $lead['sefer'];
		$weeks[ $key ]['slug']  = $lead['slug'];
		$weeks[ $key ]['year']  = $year;
		$weeks[ $key ]['range'] = ner_michoel_written_week_range( $week['start'], $week['end'] );
	}

	return array_values( $weeks );
}

/**
 * "Sep 13 – 19, 2026", "Aug 30 – Sep 5, 2026" or "Dec 29, 2025 – Jan 4, 2026".
 */
function ner_michoel_written_week_range( $start, $end ) {
	if ( $start->format( 'Y' ) !== $end->format( 'Y' ) ) {
		return wp_date( 'M j, Y', $start->getTimestamp() ) . ' – ' . wp_date( 'M j, Y', $end->getTimestamp() );
	}
	if ( $start->format( 'm' ) !== $end->format( 'm' ) ) {
		return wp_date( 'M j', $start->getTimestamp() ) . ' – ' . wp_date( 'M j, Y', $end->getTimestamp() );
	}
	return wp_date( 'M j', $start->getTimestamp() ) . ' – ' . wp_date( 'j, Y', $end->getTimestamp() );
}

/**
 * IDs of written shiurim the logged-in visitor has opened (History), so
 * their cards can say "Read". Visitors who aren't logged in get the same
 * from their own browser (written-library.js).
 */
function ner_michoel_written_read_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = array();
	if ( is_user_logged_in() && function_exists( 'ner_michoel_get_user_history' ) ) {
		foreach ( ner_michoel_get_user_history( get_current_user_id(), 500 ) as $row ) {
			if ( 'written_shiur' === $row->post_type ) {
				$ids[ (int) $row->post_id ] = true;
			}
		}
	}
	return $ids;
}

/**
 * IDs of written shiurim the logged-in visitor has saved, fetched once per
 * page rather than once per card.
 */
function ner_michoel_written_saved_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = array();
	if ( is_user_logged_in() && function_exists( 'ner_michoel_get_user_saved' ) ) {
		foreach ( ner_michoel_get_user_saved( get_current_user_id(), 'written_shiur' ) as $row ) {
			$ids[ (int) $row->post_id ] = true;
		}
	}
	return $ids;
}

/**
 * One written shiur as a sheet of paper: a Sefer band, the title in
 * serif type, its opening line, the author, date, and Save/Download. The
 * whole sheet is a link; the two actions sit above it.
 *
 * The data-slot hooks let written-library.js build the same markup from a
 * <template> rendered by this function (ner_michoel_render_written_templates()),
 * so sheets added while scrolling or filtering match these exactly.
 *
 * $variant: '' or 'feature' (the latest week's larger sheets).
 */
function ner_michoel_render_written_sheet( $item, $variant = '' ) {
	if ( ! $item ) {
		return;
	}

	$is_new  = $item['timestamp'] > ( time() - 10 * DAY_IN_SECONDS );
	$read    = ner_michoel_written_read_ids();
	$classes = array( 'nm-sheet', 'nm-accent--' . $item['slug'] );
	if ( $variant ) {
		$classes[] = 'nm-sheet--' . $variant;
	}
	if ( $is_new ) {
		$classes[] = 'is-new';
	}
	if ( isset( $read[ $item['id'] ] ) ) {
		$classes[] = 'is-read';
	}
	if ( $item['summary'] ) {
		$classes[] = 'has-summary';
	}

	$topic     = ( $item['topic'] && $item['topic'] !== $item['sefer'] ) ? $item['topic'] : '';
	$can_save  = is_user_logged_in() && function_exists( 'ner_michoel_get_user_saved' );
	$saved_ids = ner_michoel_written_saved_ids();
	$is_saved  = isset( $saved_ids[ $item['id'] ] );
	?>
	<article
		class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		data-written-id="<?php echo esc_attr( $item['id'] ); ?>"
		data-speaker="<?php echo esc_attr( $item['speaker'] ? $item['speaker']->term_id : '' ); ?>"
		data-sefer="<?php echo esc_attr( $item['slug'] ); ?>"
		data-year="<?php echo esc_attr( $item['year'] ); ?>"
	>
		<div class="nm-sheet__paper">
			<div class="nm-sheet__band">
				<span class="nm-sheet__sefer" data-slot="sefer"><?php echo esc_html( $item['sefer'] ? $item['sefer'] : __( 'Written Shiur', 'ner-michoel-child' ) ); ?></span>
				<?php if ( $topic ) : ?>
					<span class="nm-sheet__topic" data-slot="topic"><?php echo esc_html( $topic ); ?></span>
				<?php endif; ?>
			</div>

			<div class="nm-sheet__body">
				<?php if ( $is_new ) : ?>
					<span class="nm-sheet__new" data-slot="new"><?php esc_html_e( 'New', 'ner-michoel-child' ); ?></span>
				<?php endif; ?>
				<h3 class="nm-sheet__title">
					<a class="nm-sheet__link" href="<?php echo esc_url( $item['link'] ); ?>" data-slot="title"><?php echo esc_html( $item['main'] ); ?></a>
				</h3>
				<?php if ( $item['note'] ) : ?>
					<p class="nm-sheet__note" data-slot="note"><?php echo esc_html( $item['note'] ); ?></p>
				<?php endif; ?>
				<?php if ( $item['summary'] ) : ?>
					<p class="nm-sheet__summary" data-slot="summary"><?php echo esc_html( $item['summary'] ); ?></p>
				<?php endif; ?>
				<?php if ( $item['speaker'] ) : ?>
					<p class="nm-sheet__by" data-slot="by"><?php echo esc_html( $item['speaker']->name ); ?></p>
				<?php endif; ?>
			</div>

			<footer class="nm-sheet__foot">
				<time class="nm-sheet__date" data-slot="date" datetime="<?php echo esc_attr( gmdate( 'c', $item['timestamp'] ) ); ?>"><?php echo esc_html( $item['date_short'] ); ?></time>
				<span class="nm-sheet__read"><?php echo ner_michoel_line_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?><?php esc_html_e( 'Read', 'ner-michoel-child' ); ?></span>
				<span class="nm-sheet__actions">
					<?php if ( $can_save ) : ?>
						<button type="button" class="sh-save nm-sheet__icon<?php echo $is_saved ? ' is-saved' : ''; ?>" data-slot="save" data-save-id="<?php echo esc_attr( $item['id'] ); ?>" aria-pressed="<?php echo $is_saved ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Save', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></button>
					<?php endif; ?>
					<?php if ( $item['download_url'] ) : ?>
						<?php
						/* translators: %s: written shiur title */
						$download_label = __( 'Download %s', 'ner-michoel-child' );
						// Title and note as the card shows them; buildSheet() in written-library.js matches.
						$download_name = $item['main'] . ( $item['note'] ? ' (' . $item['note'] . ')' : '' );
						?>
						<a class="nm-sheet__icon" data-slot="download" data-label="<?php echo esc_attr( $download_label ); ?>" href="<?php echo esc_url( $item['download_url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( $download_label, $download_name ) ); ?>"><?php echo ner_michoel_line_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
					<?php endif; ?>
				</span>
			</footer>
		</div>
	</article>
	<?php
}

/**
 * The library filters asked for in the URL: ?q= (search), ?by= (author
 * term ID), ?sefer= (Sefer slug) and ?yr= (year). written-library.js keeps
 * them there as you filter; reading them here too means a shared link
 * arrives already filtered rather than showing the whole library until the
 * script catches up. Not "author" or "year": WordPress claims those.
 *
 * @return array{q: string, speaker: string, sefer: string, year: string}
 */
function ner_michoel_written_request_filters() {
	$filters = array();
	foreach ( array(
		'q'       => 'q',
		'speaker' => 'by',
		'sefer'   => 'sefer',
		'year'    => 'yr',
	) as $key => $param ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filters.
		$value           = isset( $_GET[ $param ] ) && is_string( $_GET[ $param ] ) ? wp_unslash( $_GET[ $param ] ) : '';
		$filters[ $key ] = sanitize_text_field( $value );
	}
	return $filters;
}

/**
 * Lowercase, straight quotes, single spaces: "V’Eim" matches "v'eim".
 * fold() in written-library.js does the same, so both find the same pieces.
 */
function ner_michoel_written_fold( $text ) {
	$text = (string) $text;
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$text = str_replace( array( "\u{2018}", "\u{2019}", "\u{02BC}", '`' ), "'", $text );
	$text = str_replace( array( "\u{201C}", "\u{201D}" ), '"', $text );
	return trim( (string) preg_replace( '/[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', ' ', $text ) );
}

/**
 * Whether any filter is set. Mirrors isFiltering() in written-library.js.
 */
function ner_michoel_written_is_filtering( $filters ) {
	return '' !== ner_michoel_written_fold( $filters['q'] )
		|| '' !== $filters['speaker']
		|| '' !== $filters['sefer']
		|| '' !== $filters['year'];
}

/**
 * What the search box looks through for one piece: the same fields, in the
 * same form, as its row in ner_michoel_written_index(), which the script
 * searches.
 */
function ner_michoel_written_search_text( $item ) {
	$parts = array(
		$item['main'],
		$item['note'] ? $item['note'] : '',
		$item['speaker'] ? $item['speaker']->name : '',
		$item['sefer'] ? $item['sefer'] : __( 'Written Shiur', 'ner-michoel-child' ),
		$item['topic'] !== $item['sefer'] ? $item['topic'] : '',
		$item['year'],
		$item['summary'] ? $item['summary'] : '',
	);
	return ner_michoel_written_fold( html_entity_decode( implode( ' ', $parts ), ENT_QUOTES, 'UTF-8' ) );
}

/**
 * The weeks cut down to the pieces that match the filters, leaving out
 * weeks with none. Mirrors matchingWeeks() and matches() in
 * written-library.js: every search word must appear, and the author, Sefer
 * and year must be the ones picked.
 */
function ner_michoel_written_filter_weeks( $weeks, $filters ) {
	$words = array_values( array_filter( explode( ' ', ner_michoel_written_fold( $filters['q'] ) ), 'strlen' ) );
	$out   = array();

	foreach ( $weeks as $week ) {
		$items = array();
		foreach ( $week['items'] as $item ) {
			$speaker = $item['speaker'] ? (string) $item['speaker']->term_id : '0';
			if ( ( '' !== $filters['speaker'] && $speaker !== $filters['speaker'] )
				|| ( '' !== $filters['sefer'] && $item['slug'] !== $filters['sefer'] )
				|| ( '' !== $filters['year'] && (string) $item['year'] !== $filters['year'] ) ) {
				continue;
			}
			if ( $words ) {
				$text = ner_michoel_written_search_text( $item );
				foreach ( $words as $word ) {
					if ( false === strpos( $text, $word ) ) {
						continue 2;
					}
				}
			}
			$items[] = $item;
		}
		if ( $items ) {
			$week['items'] = $items;
			$out[]         = $week;
		}
	}

	return $out;
}

/**
 * The archive's filter bar: an instant search box and chips for author
 * and Sefer, with counts. Filtering happens in the page
 * (written-library.js); without script the search box still submits to
 * the server search.
 *
 * @param array    $weeks   The whole library, ner_michoel_written_weeks().
 * @param array    $filters The filters in force, ner_michoel_written_request_filters().
 * @param int|null $matched How many pieces match them, when filtering.
 */
function ner_michoel_render_written_filters( $weeks, $filters = array(), $matched = null ) {
	$filters   = array_merge(
		array(
			'q'       => '',
			'speaker' => '',
			'sefer'   => '',
			'year'    => '',
		),
		$filters
	);
	$filtering = ner_michoel_written_is_filtering( $filters );
	$is_on     = function ( $key, $value ) use ( $filters ) {
		return (string) $value === $filters[ $key ];
	};
	$active    = count( array_filter( array( $filters['speaker'], $filters['sefer'], $filters['year'] ), 'strlen' ) );

	$speakers = array();
	$sefarim  = array();
	$years    = array();
	$total    = 0;

	foreach ( $weeks as $week ) {
		foreach ( $week['items'] as $item ) {
			++$total;
			if ( $item['year'] ) {
				$years[ $item['year'] ] = true;
			}
			if ( $item['speaker'] ) {
				$id = $item['speaker']->term_id;
				if ( ! isset( $speakers[ $id ] ) ) {
					$speakers[ $id ] = array(
						'name'  => $item['speaker']->name,
						'count' => 0,
					);
				}
				++$speakers[ $id ]['count'];
			}
			if ( ! isset( $sefarim[ $item['slug'] ] ) ) {
				$sefarim[ $item['slug'] ] = array(
					'name'  => 'other' === $item['slug'] ? __( 'Other', 'ner-michoel-child' ) : preg_replace( '/^Sefer\s+/i', '', $item['sefer'] ),
					'count' => 0,
				);
			}
			++$sefarim[ $item['slug'] ]['count'];
		}
	}

	uasort(
		$speakers,
		function ( $a, $b ) {
			return $b['count'] - $a['count'];
		}
	);

	krsort( $years );

	$order   = array_flip( array( 'bereishis', 'shemos', 'vayikra', 'bamidbar', 'devarim', 'moadim', 'gemara', 'other' ) );
	uksort(
		$sefarim,
		function ( $a, $b ) use ( $order ) {
			return ( isset( $order[ $a ] ) ? $order[ $a ] : 99 ) - ( isset( $order[ $b ] ) ? $order[ $b ] : 99 );
		}
	);
	?>
	<div class="nm-filters__sentinel" data-written-sentinel aria-hidden="true"></div>
	<div class="nm-filters" data-written-filters>
		<div class="nm-filters__top">
			<form class="nm-filters__search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search" data-written-search-form>
				<input type="hidden" name="post_type" value="written_shiur" />
				<?php echo ner_michoel_line_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
				<label class="screen-reader-text" for="nm-written-filter"><?php esc_html_e( 'Search written shiurim', 'ner-michoel-child' ); ?></label>
				<input type="search" id="nm-written-filter" name="s" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php esc_attr_e( 'Search by title, parsha or author…', 'ner-michoel-child' ); ?>" autocomplete="off" data-written-query />
			</form>
			<?php // Shown once the bar is stuck to the top and its chip rows fold away (written-library.js). ?>
			<button type="button" class="nm-chip nm-filters__toggle" aria-expanded="false" aria-controls="nm-written-filter-rows" data-written-toggle hidden>
				<?php esc_html_e( 'Filters', 'ner-michoel-child' ); ?>
				<span class="nm-filters__badge" data-written-badge<?php echo $active ? '' : ' hidden'; ?>><?php echo $active ? esc_html( $active ) : ''; ?></span>
			</button>
		</div>

		<div class="nm-filters__rows" id="nm-written-filter-rows">

			<div class="nm-filters__row" role="group" aria-label="<?php esc_attr_e( 'Author', 'ner-michoel-child' ); ?>">
				<button type="button" class="nm-chip<?php echo $is_on( 'speaker', '' ) ? ' is-on' : ''; ?>" data-filter="speaker" data-value="" aria-pressed="<?php echo $is_on( 'speaker', '' ) ? 'true' : 'false'; ?>"><?php esc_html_e( 'All authors', 'ner-michoel-child' ); ?></button>
				<?php foreach ( $speakers as $id => $speaker ) : ?>
					<button type="button" class="nm-chip<?php echo $is_on( 'speaker', $id ) ? ' is-on' : ''; ?>" data-filter="speaker" data-value="<?php echo esc_attr( $id ); ?>" aria-pressed="<?php echo $is_on( 'speaker', $id ) ? 'true' : 'false'; ?>"><?php echo esc_html( $speaker['name'] ); ?> <span class="nm-chip__count"><?php echo esc_html( $speaker['count'] ); ?></span></button>
				<?php endforeach; ?>
			</div>

			<div class="nm-filters__row" role="group" aria-label="<?php esc_attr_e( 'Sefer', 'ner-michoel-child' ); ?>">
				<button type="button" class="nm-chip<?php echo $is_on( 'sefer', '' ) ? ' is-on' : ''; ?>" data-filter="sefer" data-value="" aria-pressed="<?php echo $is_on( 'sefer', '' ) ? 'true' : 'false'; ?>"><?php esc_html_e( 'All topics', 'ner-michoel-child' ); ?></button>
				<?php foreach ( $sefarim as $slug => $sefer ) : ?>
					<button type="button" class="nm-chip nm-accent--<?php echo esc_attr( $slug ); ?><?php echo $is_on( 'sefer', $slug ) ? ' is-on' : ''; ?>" data-filter="sefer" data-value="<?php echo esc_attr( $slug ); ?>" aria-pressed="<?php echo $is_on( 'sefer', $slug ) ? 'true' : 'false'; ?>"><span class="nm-chip__dot" aria-hidden="true"></span><?php echo esc_html( $sefer['name'] ); ?> <span class="nm-chip__count"><?php echo esc_html( $sefer['count'] ); ?></span></button>
				<?php endforeach; ?>
			</div>

			<?php
			/* translators: %s: number of written shiurim */
			$all_text = sprintf( _n( '%s written shiur', '%s written shiurim', $total, 'ner-michoel-child' ), number_format_i18n( $total ) );
			/* translators: 1: shown count, 2: total count */
			$showing = __( 'Showing %1$s of %2$s', 'ner-michoel-child' );
			?>
			<p class="nm-filters__status" aria-live="polite">
				<span data-written-count
					data-template="<?php echo esc_attr( $showing ); ?>"
					data-total="<?php echo esc_attr( $total ); ?>"
					data-all="<?php echo esc_attr( $all_text ); ?>"
				><?php echo esc_html( $filtering ? sprintf( $showing, (int) $matched, $total ) : $all_text ); ?></span>
				<button type="button" class="nm-filters__clear" data-written-clear<?php echo $filtering ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear filters', 'ner-michoel-child' ); ?></button>
				<?php if ( count( $years ) > 1 ) : ?>
					<span class="nm-filters__years" role="group" aria-label="<?php esc_attr_e( 'Year', 'ner-michoel-child' ); ?>">
						<button type="button" class="nm-chip nm-chip--small<?php echo $is_on( 'year', '' ) ? ' is-on' : ''; ?>" data-filter="year" data-value="" aria-pressed="<?php echo $is_on( 'year', '' ) ? 'true' : 'false'; ?>"><?php esc_html_e( 'Any year', 'ner-michoel-child' ); ?></button>
						<?php foreach ( array_keys( $years ) as $year ) : ?>
							<button type="button" class="nm-chip nm-chip--small<?php echo $is_on( 'year', $year ) ? ' is-on' : ''; ?>" data-filter="year" data-value="<?php echo esc_attr( $year ); ?>" aria-pressed="<?php echo $is_on( 'year', $year ) ? 'true' : 'false'; ?>"><?php echo esc_html( $year ); ?></button>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</p>
		</div>
	</div>
	<?php
}

/**
 * One week of the library. The first (latest) week renders as the
 * feature spread.
 */
function ner_michoel_render_written_week( $week, $is_feature = false ) {
	$count = count( $week['items'] );
	?>
	<section class="nm-week nm-accent--<?php echo esc_attr( $week['slug'] ); ?><?php echo $is_feature ? ' nm-week--feature' : ''; ?>" data-written-week>
		<header class="nm-week__head">
			<?php if ( $is_feature ) : ?>
				<span class="nm-week__eyebrow" data-slot="eyebrow"><?php esc_html_e( 'Latest', 'ner-michoel-child' ); ?></span>
			<?php endif; ?>
			<?php if ( $week['sefer'] ) : ?>
				<span class="nm-week__sefer" data-slot="sefer"><?php echo esc_html( $week['sefer'] ); ?></span>
			<?php endif; ?>
			<h2 class="nm-week__title">
				<span data-slot="label"><?php echo esc_html( $week['label'] ); ?></span>
				<?php if ( $week['year'] ) : ?>
					<span class="nm-week__year" data-slot="year"><?php echo esc_html( $week['year'] ); ?></span>
				<?php endif; ?>
			</h2>
			<p class="nm-week__dates">
				<span data-slot="range"><?php echo esc_html( $week['range'] ); ?></span>
				<?php if ( $is_feature ) : ?>
					<?php
					/* translators: %s: number of pieces that week */
					$one = _n( '%s piece', '%s pieces', 1, 'ner-michoel-child' );
					/* translators: %s: number of pieces that week */
					$other = _n( '%s piece', '%s pieces', 2, 'ner-michoel-child' );
					?>
					<span class="nm-week__count" data-slot="count" data-one="<?php echo esc_attr( $one ); ?>" data-other="<?php echo esc_attr( $other ); ?>">&middot; <?php echo esc_html( sprintf( 1 === $count ? $one : $other, number_format_i18n( $count ) ) ); ?></span>
				<?php endif; ?>
			</p>
		</header>
		<div class="nm-week__sheets" data-slot="sheets">
			<?php
			foreach ( $week['items'] as $item ) {
				ner_michoel_render_written_sheet( $item, $is_feature ? 'feature' : '' );
			}
			?>
		</div>
	</section>
	<?php
}

/**
 * The <template>s written-library.js fills in for weeks and sheets it adds
 * while scrolling or filtering. Rendered by the same functions as the
 * server-side ones, with every optional part present, so the markup can't
 * drift apart.
 */
function ner_michoel_render_written_templates() {
	$speaker = (object) array(
		'term_id' => 0,
		'name'    => '',
	);
	// Placeholders only need to be non-empty and distinct, so every optional
	// part is rendered (the topic only shows when it differs from the Sefer).
	$item    = array(
		'id'           => 0,
		'title'        => '',
		'main'         => '',
		'note'         => 'N',
		'year'         => '',
		'summary'      => 'X',
		'link'         => '#',
		'timestamp'    => time(),
		'date'         => '',
		'date_short'   => '',
		'speaker'      => $speaker,
		'series'       => null,
		'sefer'        => 'S',
		'slug'         => 'other',
		'topic'        => 'T',
		'pdf_url'      => '',
		'download_url' => '#',
		'filesize'     => 0,
	);
	$week    = array(
		'items' => array(),
		'label' => '',
		'sefer' => 'S',
		'slug'  => 'other',
		'year'  => 'Y',
		'range' => '',
	);
	?>
	<template data-written-template="sheet"><?php ner_michoel_render_written_sheet( $item ); ?></template>
	<template data-written-template="week"><?php ner_michoel_render_written_week( $week, true ); ?></template>
	<?php
}

/**
 * The whole library as compact JSON, for filtering and for rendering past
 * the first weeks (written-library.js). Speakers and series are listed once
 * and referred to by ID; links are site-relative.
 */
function ner_michoel_written_index( $weeks ) {
	$read      = ner_michoel_written_read_ids();
	$saved     = ner_michoel_written_saved_ids();
	$new_after = time() - 10 * DAY_IN_SECONDS;
	$speakers  = array();
	$items     = array();
	$out_weeks = array();
	// Titles and term names arrive HTML-encoded (&#8217;, &amp;); the script
	// sets them as text, so they go into the JSON as plain characters.
	$plain = function ( $text ) {
		return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
	};

	foreach ( $weeks as $week ) {
		$ids = array();
		foreach ( $week['items'] as $item ) {
			$ids[] = $item['id'];
			if ( $item['speaker'] ) {
				$speakers[ $item['speaker']->term_id ] = $plain( $item['speaker']->name );
			}
			$flags = ( $item['timestamp'] > $new_after ? 1 : 0 ) | ( isset( $read[ $item['id'] ] ) ? 2 : 0 ) | ( isset( $saved[ $item['id'] ] ) ? 4 : 0 );
			$row   = array(
				'i' => $item['id'],
				'm' => $plain( $item['main'] ),
				'u' => wp_make_link_relative( $item['link'] ),
				'd' => $item['date_short'],
				't' => $item['timestamp'],
				'a' => $item['speaker'] ? $item['speaker']->term_id : 0,
				'k' => $item['slug'],
				's' => $plain( $item['sefer'] ? $item['sefer'] : __( 'Written Shiur', 'ner-michoel-child' ) ),
				'p' => $item['topic'] !== $item['sefer'] ? $plain( $item['topic'] ) : '',
				'y' => $item['year'],
			);
			if ( $item['note'] ) {
				$row['n'] = $plain( $item['note'] );
			}
			if ( $item['summary'] ) {
				$row['x'] = $plain( $item['summary'] );
			}
			if ( $item['download_url'] ) {
				$row['w'] = wp_make_link_relative( $item['download_url'] );
			}
			if ( $flags ) {
				$row['f'] = $flags;
			}
			$items[] = $row;
		}
		$out_weeks[] = array(
			'l' => $plain( $week['label'] ),
			's' => $plain( $week['sefer'] ),
			'k' => $week['slug'],
			'y' => $week['year'],
			'r' => $week['range'],
			'i' => $ids,
		);
	}

	return array(
		'speakers' => $speakers,
		'items'    => $items,
		'weeks'    => $out_weeks,
	);
}

/**
 * Other written shiurim to read next, for the single page: the rest of
 * the same week, more on the same series from other years, and the
 * newer/older neighbours.
 */
function ner_michoel_written_related( $post_id ) {
	$item = ner_michoel_written_data( $post_id );
	$out  = array(
		'week'   => array(),
		'series' => array(),
	);
	if ( ! $item ) {
		return $out;
	}

	$tz    = wp_timezone();
	$day   = ( new DateTimeImmutable( '@' . $item['timestamp'] ) )->setTimezone( $tz )->setTime( 0, 0 );
	$start = $day->modify( '-' . (int) $day->format( 'w' ) . ' days' );
	$end   = $start->modify( '+6 days' )->setTime( 23, 59, 59 );

	$week_posts = get_posts(
		array(
			'post_type'      => 'written_shiur',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'post__not_in'   => array( $post_id ),
			'no_found_rows'  => true,
			'date_query'     => array(
				array(
					'after'     => $start->format( 'Y-m-d H:i:s' ),
					'before'    => $end->format( 'Y-m-d H:i:s' ),
					'inclusive' => true,
				),
			),
		)
	);
	$skip = array( $post_id );
	foreach ( $week_posts as $p ) {
		$out['week'][] = ner_michoel_written_data( $p );
		$skip[]        = $p->ID;
	}

	if ( $item['series'] ) {
		$series_posts = get_posts(
			array(
				'post_type'      => 'written_shiur',
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'post__not_in'   => $skip,
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'series',
						'field'    => 'term_id',
						'terms'    => (int) $item['series']->term_id,
					),
				),
			)
		);
		foreach ( $series_posts as $p ) {
			$out['series'][] = ner_michoel_written_data( $p );
		}
	}

	return $out;
}

/**
 * The reading room needs the whole library in one query: it renders the
 * first weeks and indexes the rest for the script. Classic keeps
 * WordPress's own pagination, and the server search (search-written.php)
 * its own ranking and limit.
 */
function ner_michoel_written_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || $query->is_search() || ! $query->is_post_type_archive( 'written_shiur' ) ) {
		return;
	}
	if ( 'classic' === ner_michoel_get_layout() ) {
		return;
	}
	$query->set( 'posts_per_page', -1 );
	$query->set( 'no_found_rows', true );
}
add_action( 'pre_get_posts', 'ner_michoel_written_archive_query' );

/**
 * The reading room's serif face (written pages only) and its two scripts
 * (site-wide, not just written pages, so the router (nm-router.js) always
 * has them ready when it swaps a visitor onto a written-shiur page without
 * a real page load; both scripts no-op harmlessly where their elements
 * don't exist).
 */
function ner_michoel_enqueue_written_assets() {
	$is_search  = function_exists( 'ner_michoel_is_written_search' ) && ner_michoel_is_written_search();
	$is_archive = is_post_type_archive( 'written_shiur' ) && ! is_search();
	$is_single  = is_singular( 'written_shiur' );

	if ( $is_archive || $is_search || $is_single ) {
		wp_enqueue_style(
			'ner-michoel-written-serif',
			'https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL carries its own versioning.
		);
	}

	wp_enqueue_script( 'ner-michoel-written-library', NER_MICHOEL_URI . '/assets/js/written-library.js', array(), NER_MICHOEL_VERSION, true );
	wp_enqueue_script( 'ner-michoel-written-reader', NER_MICHOEL_URI . '/assets/js/written-reader.js', array( 'ner-michoel-custom' ), NER_MICHOEL_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_written_assets', 22 );
