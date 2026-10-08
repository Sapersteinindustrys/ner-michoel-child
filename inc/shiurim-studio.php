<?php
/**
 * Studio: the fourth Shiurim layout ('studio' in ner_michoel_get_layout()).
 *
 * The new homepage's look (template-parts/home-new.php, assets/css/home.css)
 * brought to the Shiurim pages: light panels, a navy and gold hero, serif
 * headings. From Modern it keeps the clean grid and lists; from 24Six the
 * swipeable rows. The Shiurim home (archive-shiur.php) gets its own page
 * (template-parts/shiurim-studio.php): a hero with search, the newest shiurim
 * and the speakers side by side, what's in season, the series in rows by
 * category, and the topics. The other Shiurim pages keep their markup and
 * take the look from assets/css/shiurim-studio.css. The player bar stays dark.
 *
 * Every call into ner-michoel-core is guarded, so the page still renders when
 * the plugin is behind.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True on a Shiurim page in the Studio layout.
 */
function ner_michoel_is_studio() {
	return 'studio' === ner_michoel_get_layout() && ner_michoel_is_shiurim_context();
}

/**
 * Studio's stylesheet and the serif face (the homepage's and the Written
 * Shiurim pages', so a visitor's browser usually has it already). After the
 * card and search styles, which it adjusts.
 */
function ner_michoel_enqueue_studio_assets() {
	if ( ! ner_michoel_is_studio() ) {
		return;
	}

	wp_enqueue_style(
		'ner-michoel-written-serif',
		'https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL carries its own versioning.
	);
	wp_enqueue_style( 'ner-michoel-shiurim-studio', NER_MICHOEL_URI . '/assets/css/shiurim-studio.css', array( 'ner-michoel-shiurim-cards' ), NER_MICHOEL_VERSION );
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_studio_assets', 23 );

/* ------------------------------------------------------------------
 * Small helpers. The homepage's (inc/home.php) when they're there, so a
 * speaker gets the same initials and colour on both.
 * ------------------------------------------------------------------ */

function ner_michoel_studio_initials( $name ) {
	if ( function_exists( 'ner_michoel_home_initials' ) ) {
		return ner_michoel_home_initials( $name );
	}
	$name = trim( html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' ) );
	return '' === $name ? '' : strtoupper( function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1, 'UTF-8' ) : substr( $name, 0, 1 ) );
}

function ner_michoel_studio_hue( $name ) {
	return function_exists( 'ner_michoel_home_hue' ) ? ner_michoel_home_hue( $name ) : abs( crc32( (string) $name ) ) % 360;
}

function ner_michoel_studio_when( $timestamp ) {
	return function_exists( 'ner_michoel_home_when' ) ? ner_michoel_home_when( $timestamp ) : wp_date( get_option( 'date_format' ), (int) $timestamp );
}

/**
 * One of the six cover colours (shiurim-studio.css, .st-tone-0 to 5) for a name:
 * navy, green, gold, burgundy, teal, plum. The same name always gets the same one.
 */
function ner_michoel_studio_tone( $name ) {
	return abs( crc32( strtolower( trim( (string) $name ) ) ) ) % 6;
}

function ner_michoel_studio_length( $duration ) {
	return function_exists( 'ner_michoel_home_length' ) ? ner_michoel_home_length( $duration ) : (string) $duration;
}

/**
 * "1 shiur" / "12 shiurim", with the number formatted for the site's locale.
 */
function ner_michoel_studio_count_label( $count ) {
	/* translators: %s: number of shiurim */
	return sprintf( _n( '%s shiur', '%s shiurim', (int) $count, 'ner-michoel-child' ), number_format_i18n( (int) $count ) );
}

/**
 * One shiur as Studio lists it: what its row shows, and what its Play button
 * needs (its own track in the page, the rest of its autoplay list by URL).
 */
function ner_michoel_studio_shiur( $post ) {
	$speakers = get_the_terms( $post, 'speaker' );
	$speaker  = ( $speakers && ! is_wp_error( $speakers ) ) ? $speakers[0] : null;
	$series   = get_the_terms( $post, 'series' );
	$serie    = ( $series && ! is_wp_error( $series ) ) ? $series[0] : null;

	// The shiur's own picture, then its speaker's photo, then initials.
	$photo = get_the_post_thumbnail_url( $post, 'thumbnail' );
	if ( ! $photo && $speaker && function_exists( 'ner_michoel_get_speaker_photo_url' ) ) {
		$photo = ner_michoel_get_speaker_photo_url( $speaker->term_id );
	}

	$name      = $speaker ? $speaker->name : get_the_title( $post );
	$timestamp = (int) get_post_time( 'U', true, $post );
	$is_video  = function_exists( 'ner_michoel_shiur_is_video' ) && ner_michoel_shiur_is_video( $post->ID );
	$track     = $is_video ? array() : ner_michoel_build_track_queue( array( $post ) );

	return array(
		'id'        => $post->ID,
		'title'     => get_the_title( $post ),
		'link'      => get_permalink( $post ),
		'speaker'   => $speaker ? $speaker->name : '',
		'series'    => $serie ? ner_michoel_studio_series_title( $serie->name ) : '',
		'photo'     => $photo ? $photo : '',
		'initials'  => ner_michoel_studio_initials( $name ),
		'hue'       => ner_michoel_studio_hue( $name ),
		'is_video'  => $is_video,
		'is_written' => 'written_shiur' === $post->post_type,
		'is_new'    => $timestamp && ( time() - $timestamp ) <= 14 * DAY_IN_SECONDS,
		'when'      => ner_michoel_studio_when( $timestamp ),
		'iso'       => $timestamp ? gmdate( 'c', $timestamp ) : '',
		'length'    => function_exists( 'ner_michoel_get_shiur_duration' ) ? ner_michoel_studio_length( ner_michoel_get_shiur_duration( $post->ID ) ) : '',
		'track'     => $track,
		'queue_url' => ( $track && function_exists( 'ner_michoel_queue_url' ) ) ? ner_michoel_queue_url(
			array(
				'shiur' => $post->ID,
				'mode'  => 'autoplay',
			)
		) : '',
	);
}

/**
 * A series name the way a card shows it. The old site's names carry their whole
 * category path ("Gemara / Shabbos / Perek 2"); the row already says
 * "Gemara", so the card reads "Shabbos · Perek 2".
 */
function ner_michoel_studio_series_title( $name ) {
	$parts = ner_michoel_studio_name_parts( $name );
	if ( count( $parts ) > 1 ) {
		array_shift( $parts );
	}
	return implode( ' · ', $parts );
}

function ner_michoel_studio_name_parts( $name ) {
	$name = html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' );
	return array_values( array_filter( array_map( 'trim', preg_split( '#\s+/\s+#u', $name ) ), 'strlen' ) );
}

/**
 * The parshiyos in order, for sorting the Parsha row. Names as the old site
 * spells them; joined parshiyos are listed under their first.
 */
function ner_michoel_studio_parsha_order() {
	return array(
		'bereishis', 'noach', 'lech lecha', 'vayeira', 'chayei sarah', 'toldos', 'vayeitzei', 'vayishlach', 'vayeishev', 'mikeitz', 'vayigash', 'vayechi',
		'shemos', 'vaeira', 'bo', 'beshalach', 'yisro', 'mishpatim', 'terumah', 'tetzaveh', 'ki sisa', 'vayakhel', 'pekudei',
		'vayikra', 'tzav', 'shemini', 'tazria', 'tazria-metzora', 'metzora', 'acharei mos', 'kedoshim', 'emor', 'behar', 'bechukosai',
		'bamidbar', 'naso', 'behaaloscha', 'shlach', 'korach', 'chukas', 'balak', 'pinchas', 'mattos', 'mattos-massei', 'massei',
		'devarim', 'vaeschanan', 'eikev', "re'eh", 'shoftim', 'ki seitzei', 'ki savo', 'nitzavim', 'vayelech', 'haazinu', 'vezos haberacha',
	);
}

/**
 * The series in rows by category, for Studio's Shiurim home. A series' category
 * is the first part of its name; "Sefer Bereishis / Vayeira" and the other
 * Chumashim go together in a Parsha row, in Torah order. A category with a single
 * series joins "More series" at the end. Rows are biggest first.
 *
 * Each row: title, total (shiurim), cards. Each card: term, title, kicker,
 * count, image, cover / cover_sub / tone (the drawn cover when there's no
 * image), track (first track, may be empty), queue_url
 * ('' when there's nothing to play).
 */
function ner_michoel_studio_series_rows( $terms ) {
	if ( ! $terms ) {
		return array();
	}

	$ids      = wp_list_pluck( $terms, 'term_id' );
	$counts   = function_exists( 'ner_michoel_term_shiur_counts' ) ? ner_michoel_term_shiur_counts( 'series' ) : array();
	$first    = function_exists( 'ner_michoel_first_track_by_term' ) ? ner_michoel_first_track_by_term( 'series', $ids, false ) : array();
	$playable = function_exists( 'ner_michoel_term_playable_counts' ) ? ner_michoel_term_playable_counts( 'series' ) : null;
	$order    = array_flip( ner_michoel_studio_parsha_order() );
	$parsha   = __( 'Parsha', 'ner-michoel-child' );

	$rows = array();
	foreach ( $terms as $term ) {
		$parts = ner_michoel_studio_name_parts( $term->name );
		if ( ! $parts ) {
			continue;
		}
		$head   = $parts[0];
		$rest   = array_slice( $parts, 1 );
		$kicker = '';
		$sort   = '';

		if ( $rest && preg_match( '/^sefer\s+(.+)$/i', $head, $sefer ) ) {
			// "Sefer Bereishis / Vayeira": the Parsha row, card "Vayeira", kicker "Bereishis".
			$row_key = $parsha;
			$kicker  = $sefer[1];
			$key     = strtolower( $rest[0] );
			$sort    = sprintf( '%03d', isset( $order[ $key ] ) ? $order[ $key ] : 999 ) . ' ' . implode( ' ', $rest );
		} else {
			$row_key = $head;
		}

		$title = $rest ? implode( ' · ', $rest ) : $head;
		// The cover: the series' own name large, the rest of its path small under it
		// ("Bava Kamma" over "Perek 3"), so a masechta's perakim don't all look alike.
		$cover_parts = $rest ? $rest : array( $head );
		$count = isset( $counts[ $term->term_id ] ) ? (int) $counts[ $term->term_id ] : (int) $term->count;
		$image = function_exists( 'ner_michoel_get_series_cover_url' ) ? ner_michoel_get_series_cover_url( $term->term_id ) : '';
		$audio = null === $playable || ! empty( $playable[ $term->term_id ] );

		if ( ! isset( $rows[ $row_key ] ) ) {
			$rows[ $row_key ] = array(
				'title' => $row_key,
				'total' => 0,
				'cards' => array(),
			);
		}
		$rows[ $row_key ]['total']  += $count;
		$rows[ $row_key ]['cards'][] = array(
			'term'      => $term,
			'title'     => $title,
			'kicker'    => $kicker,
			'count'     => $count,
			'image'     => $image ? $image : '',
			'cover'     => $cover_parts[0],
			'cover_sub' => implode( ' · ', array_slice( $cover_parts, 1 ) ),
			'tone'      => ner_michoel_studio_tone( $cover_parts[0] ),
			'track'     => isset( $first[ $term->term_id ] ) ? $first[ $term->term_id ] : array(),
			'queue_url' => ( $audio && function_exists( 'ner_michoel_queue_url' ) ) ? ner_michoel_queue_url( array( 'series' => $term->term_id ) ) : '',
			'sort'      => '' !== $sort ? $sort : $title,
		);
	}

	// One-series categories gather at the end, under "More series".
	$more = array(
		'title' => __( 'More series', 'ner-michoel-child' ),
		'total' => 0,
		'cards' => array(),
	);
	foreach ( $rows as $key => $row ) {
		if ( count( $row['cards'] ) < 2 ) {
			foreach ( $row['cards'] as $card ) {
				$card['title']   = html_entity_decode( $card['term']->name, ENT_QUOTES, 'UTF-8' );
				$parts           = ner_michoel_studio_name_parts( $card['term']->name );
				$card['cover']     = $parts ? $parts[ count( $parts ) - 1 ] : $card['title'];
				$card['cover_sub'] = count( $parts ) > 1 ? implode( ' · ', array_slice( $parts, 0, -1 ) ) : '';
				$card['tone']      = ner_michoel_studio_tone( $card['cover'] );
				$card['sort']    = $card['title'];
				$more['cards'][] = $card;
			}
			$more['total'] += $row['total'];
			unset( $rows[ $key ] );
		}
	}

	uasort(
		$rows,
		function ( $a, $b ) {
			return $b['total'] <=> $a['total'];
		}
	);
	if ( $more['cards'] ) {
		$rows[] = $more;
	}

	foreach ( $rows as &$row ) {
		usort(
			$row['cards'],
			function ( $a, $b ) {
				return strnatcasecmp( $a['sort'], $b['sort'] );
			}
		);
	}
	unset( $row );

	return array_values( $rows );
}

/**
 * Everything Studio's Shiurim home shows, so the template only lays it out.
 */
function ner_michoel_studio_home_data() {
	$counts = wp_count_posts( 'shiur' );

	// The newest shiurim: the first is the hero's, the next eight the list's.
	$recent = get_posts(
		array(
			'post_type'           => 'shiur',
			'post_status'         => 'publish',
			'posts_per_page'      => 9,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	if ( $recent && function_exists( 'ner_michoel_prime_shiur_caches' ) ) {
		ner_michoel_prime_shiur_caches( wp_list_pluck( $recent, 'ID' ) );
	}
	$recent = array_map( 'ner_michoel_studio_shiur', $recent );

	// Speakers, most shiurim first.
	$speaker_terms = get_terms(
		array(
			'taxonomy'   => 'speaker',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	$speaker_terms = is_wp_error( $speaker_terms ) ? array() : $speaker_terms;
	$sp_first      = ( $speaker_terms && function_exists( 'ner_michoel_first_track_by_term' ) ) ? ner_michoel_first_track_by_term( 'speaker', wp_list_pluck( $speaker_terms, 'term_id' ), true ) : array();
	$sp_counts     = function_exists( 'ner_michoel_term_shiur_counts' ) ? ner_michoel_term_shiur_counts( 'speaker' ) : array();
	$sp_playable   = function_exists( 'ner_michoel_term_playable_counts' ) ? ner_michoel_term_playable_counts( 'speaker' ) : null;
	$speakers      = array();
	foreach ( $speaker_terms as $term ) {
		$audio      = null === $sp_playable || ! empty( $sp_playable[ $term->term_id ] );
		$photo      = function_exists( 'ner_michoel_get_speaker_photo_url' ) ? ner_michoel_get_speaker_photo_url( $term->term_id ) : '';
		$speakers[] = array(
			'name'      => $term->name,
			'link'      => get_term_link( $term ),
			'count'     => isset( $sp_counts[ $term->term_id ] ) ? (int) $sp_counts[ $term->term_id ] : (int) $term->count,
			'photo'     => $photo ? $photo : '',
			'initials'  => ner_michoel_studio_initials( $term->name ),
			'hue'       => ner_michoel_studio_hue( $term->name ),
			'track'     => isset( $sp_first[ $term->term_id ] ) ? $sp_first[ $term->term_id ] : array(),
			'queue_url' => ( $audio && function_exists( 'ner_michoel_queue_url' ) ) ? ner_michoel_queue_url( array( 'speaker' => $term->term_id ) ) : '',
		);
	}

	$series_terms = get_terms(
		array(
			'taxonomy'   => 'series',
			'hide_empty' => true,
		)
	);
	$series_terms = is_wp_error( $series_terms ) ? array() : $series_terms;

	// Topics, most shiurim first (ner-michoel-core v0.9.19+).
	$topics = array();
	if ( taxonomy_exists( 'topic' ) ) {
		$topic_terms = get_terms(
			array(
				'taxonomy'   => 'topic',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 36,
			)
		);
		$topics = is_wp_error( $topic_terms ) ? array() : $topic_terms;
	}

	$seasons = array();
	if ( function_exists( 'ner_michoel_get_active_seasons' ) ) {
		foreach ( ner_michoel_get_active_seasons( 6 ) as $season ) {
			// Seasons only here: the "always" topics are the homepage's to feature.
			if ( 'season' === $season['mode'] ) {
				$seasons[] = $season;
			}
		}
		$seasons = array_slice( $seasons, 0, 2 );
	}

	// Pick up where you left off, for a listener who's signed in.
	$continue = null;
	if ( is_user_logged_in() && function_exists( 'ner_michoel_continue_series_for_user' ) ) {
		$items = ner_michoel_continue_series_for_user( get_current_user_id(), 1 );
		if ( $items ) {
			$continue = array(
				'series' => ner_michoel_studio_series_title( $items[0]['term']->name ),
				'shiur'  => ner_michoel_studio_shiur( $items[0]['shiur'] ),
			);
		}
	}

	return array(
		'shiur_count'   => $counts ? (int) $counts->publish : 0,
		'speaker_count' => count( $speaker_terms ),
		'series_count'  => count( $series_terms ),
		'topic_count'   => taxonomy_exists( 'topic' ) ? (int) wp_count_terms( array( 'taxonomy' => 'topic', 'hide_empty' => true ) ) : 0,
		'latest'        => $recent ? $recent[0] : null,
		'recent'        => array_slice( $recent, 1 ),
		'speakers'      => $speakers,
		'rows'          => ner_michoel_studio_series_rows( $series_terms ),
		'topics'        => $topics,
		'seasons'       => $seasons,
		'continue'      => $continue,
		'archive'       => get_post_type_archive_link( 'shiur' ),
	);
}

/* ------------------------------------------------------------------
 * Markup pieces.
 * ------------------------------------------------------------------ */

/**
 * A Play button for a list or card. Carries the first track (it plays inside the
 * tap) and the full queue's URL (custom.js swaps it in). Nothing when there's
 * nothing to play.
 */
function ner_michoel_studio_play_button( $track, $queue_url, $label, $class = 'st-play' ) {
	if ( ! $track && ! $queue_url ) {
		return;
	}
	?>
	<button
		type="button"
		class="<?php echo esc_attr( $class ); ?>"
		aria-label="<?php echo esc_attr( sprintf( /* translators: %s: what plays */ __( 'Play %s', 'ner-michoel-child' ), $label ) ); ?>"
		<?php if ( $track ) : ?>data-play-queue="<?php echo esc_attr( wp_json_encode( $track ) ); ?>"<?php endif; ?>
		<?php if ( $queue_url ) : ?>data-queue-url="<?php echo esc_url( $queue_url ); ?>"<?php endif; ?>
		data-play-index="0"
	><?php echo ner_michoel_icon( 'play' ); ?></button>
	<?php
}

/**
 * Artwork: the picture, or initials on the name's own colour.
 */
function ner_michoel_studio_art( $photo, $initials, $hue, $class ) {
	?>
	<span class="<?php echo esc_attr( $class ); ?>" style="--hue: <?php echo (int) $hue; ?>;">
		<?php if ( $photo ) : ?>
			<img src="<?php echo esc_url( $photo ); ?>" alt="" loading="lazy" decoding="async" />
		<?php else : ?>
			<span class="st-initials" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
		<?php endif; ?>
	</span>
	<?php
}

/**
 * One shiur in a list: artwork with its Play button, title and meta (a link to
 * its page), and when / how long on the right.
 */
function ner_michoel_studio_shiur_row( $row ) {
	?>
	<li class="st-row">
		<div class="st-row__art">
			<?php ner_michoel_studio_art( $row['photo'], $row['initials'], $row['hue'], 'st-art st-art--row' ); ?>
			<?php ner_michoel_studio_play_button( $row['track'], $row['queue_url'], $row['title'], 'st-play st-play--row' ); ?>
		</div>
		<a class="st-row__link" href="<?php echo esc_url( $row['link'] ); ?>">
			<span class="st-row__title"><?php echo esc_html( $row['title'] ); ?></span>
			<span class="st-row__meta">
				<?php if ( $row['speaker'] ) : ?>
					<span><?php echo esc_html( $row['speaker'] ); ?></span>
				<?php endif; ?>
				<?php if ( $row['series'] ) : ?>
					<span><?php echo esc_html( $row['series'] ); ?></span>
				<?php endif; ?>
			</span>
		</a>
		<span class="st-row__side">
			<?php if ( $row['is_new'] ) : ?>
				<span class="st-pill st-pill--new"><?php esc_html_e( 'New', 'ner-michoel-child' ); ?></span>
			<?php elseif ( $row['is_written'] ) : ?>
				<span class="st-pill st-pill--written"><?php esc_html_e( 'Written', 'ner-michoel-child' ); ?></span>
			<?php elseif ( $row['is_video'] ) : ?>
				<span class="st-pill"><?php esc_html_e( 'Video', 'ner-michoel-child' ); ?></span>
			<?php endif; ?>
			<span class="st-row__facts">
				<?php if ( $row['when'] ) : ?>
					<time datetime="<?php echo esc_attr( $row['iso'] ); ?>"><?php echo esc_html( $row['when'] ); ?></time>
				<?php endif; ?>
				<?php if ( $row['length'] ) : ?>
					<span><?php echo esc_html( $row['length'] ); ?></span>
				<?php endif; ?>
			</span>
		</span>
	</li>
	<?php
}

/**
 * A card in a row of series: artwork with Play, then the name and count. The
 * whole card links to the series (a stretched link), with Play on top of it.
 */
function ner_michoel_studio_series_card( $card ) {
	$link = get_term_link( $card['term'] );
	?>
	<article class="st-card">
		<div class="st-card__art">
			<?php if ( $card['image'] ) : ?>
				<span class="st-art st-art--card"><img src="<?php echo esc_url( $card['image'] ); ?>" alt="" loading="lazy" decoding="async" /></span>
			<?php else : ?>
				<span class="st-art st-art--card st-cover st-tone-<?php echo (int) $card['tone']; ?>" aria-hidden="true">
					<span class="st-cover__title"><?php echo esc_html( $card['cover'] ); ?></span>
					<?php if ( $card['cover_sub'] ) : ?>
						<span class="st-cover__sub"><?php echo esc_html( $card['cover_sub'] ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<?php ner_michoel_studio_play_button( $card['track'], $card['queue_url'], $card['title'], 'st-play st-play--card' ); ?>
		</div>
		<?php if ( $card['kicker'] ) : ?>
			<p class="st-card__kicker"><?php echo esc_html( $card['kicker'] ); ?></p>
		<?php endif; ?>
		<h3 class="st-card__title"><a class="st-card__link" href="<?php echo esc_url( is_wp_error( $link ) ? '#' : $link ); ?>"><?php echo esc_html( $card['title'] ); ?></a></h3>
		<p class="st-card__meta"><?php echo esc_html( ner_michoel_studio_count_label( $card['count'] ) ); ?></p>
	</article>
	<?php
}
