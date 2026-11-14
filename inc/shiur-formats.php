<?php
/**
 * One shiur in more than one format: audio, video and written (PDF).
 *
 * ner-michoel-core (includes/shiur-formats.php) finds the shiurim that are
 * the same shiur in different formats. This file shows it:
 * - on a list or a card, a small "Audio · Written" label, so a reader can see
 *   at a glance which shiurim come more than one way (the card itself still
 *   opens the page it always did);
 * - on the shiur's own page, a Listen / Watch / Read switch with one option
 *   per format. The page you are on is marked; the others are links to those
 *   pages. The player bar lives outside the page content, so choosing Read
 *   while the audio plays keeps it playing.
 *
 * A shiur that comes one way shows nothing new, and every call into core is
 * guarded, so the pages are unchanged where core is behind.
 *
 * Icons are from Lucide (https://lucide.dev), ISC License, as in
 * ner_michoel_line_icon().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What each kind is called, in the order audio, video, written.
 * 'noun' labels a list, 'verb' is the option on the shiur's page.
 */
function ner_michoel_format_kinds() {
	return array(
		'a' => array(
			'noun'  => __( 'Audio', 'ner-michoel-child' ),
			'verb'  => __( 'Listen', 'ner-michoel-child' ),
			'title' => __( 'Listen to this shiur', 'ner-michoel-child' ),
		),
		'v' => array(
			'noun'  => __( 'Video', 'ner-michoel-child' ),
			'verb'  => __( 'Watch', 'ner-michoel-child' ),
			'title' => __( 'Watch this shiur', 'ner-michoel-child' ),
		),
		'w' => array(
			'noun'  => __( 'Written', 'ner-michoel-child' ),
			'verb'  => __( 'Read', 'ner-michoel-child' ),
			'title' => __( 'Read this shiur', 'ner-michoel-child' ),
		),
	);
}

/**
 * A small outline icon for a kind (14px, inherits the text colour).
 */
function ner_michoel_format_icon( $kind ) {
	$paths = array(
		'a' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
		'v' => '<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
		'w' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
	);
	if ( ! isset( $paths[ $kind ] ) ) {
		return '';
	}
	return '<svg class="nm-fmt__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $kind ] . '</svg>';
}

/**
 * The formats a shiur comes in, as array( kind => post ID ), itself included,
 * or array() when it comes one way (or core can't say).
 *
 * $verify is for the shiur's own page: it leaves out a format whose post is
 * no longer published.
 */
function ner_michoel_get_shiur_formats( $post_id, $verify = false ) {
	if ( ! function_exists( 'ner_michoel_shiur_formats' ) ) {
		return array();
	}
	$formats = ner_michoel_shiur_formats( $post_id, $verify );
	return is_array( $formats ) ? $formats : array();
}

/**
 * The kinds a shiur comes in as one string in the order audio, video,
 * written ("aw"), or '' when it comes one way. What the lists print and the
 * written library's data carries.
 */
function ner_michoel_get_shiur_format_kinds( $post_id ) {
	return implode( '', array_keys( ner_michoel_get_shiur_formats( $post_id ) ) );
}

/**
 * The kind this page's own shiur is: 'w' for a written shiur, 'v' for a
 * video, otherwise 'a'.
 */
function ner_michoel_format_kind_of( $post_id ) {
	if ( 'written_shiur' === get_post_type( $post_id ) ) {
		return 'w';
	}
	return ( function_exists( 'ner_michoel_shiur_is_video' ) && ner_michoel_shiur_is_video( $post_id ) ) ? 'v' : 'a';
}

/**
 * The label for a list or card: one chip per format. $kinds is a string such
 * as "aw". Returns '' for fewer than two kinds, so a caller can print it
 * without checking. $for_slot marks the group for written-library.js, which
 * fills the same markup for sheets it builds in the browser (it removes the
 * chips a sheet doesn't have). Already escaped.
 */
function ner_michoel_format_chips_markup( $kinds, $for_slot = false ) {
	$info  = ner_michoel_format_kinds();
	$kinds = array_values(
		array_filter(
			str_split( (string) $kinds ),
			function ( $kind ) use ( $info ) {
				return isset( $info[ $kind ] );
			}
		)
	);
	if ( count( $kinds ) < 2 ) {
		return '';
	}

	$html = '<span class="nm-fmts"' . ( $for_slot ? ' data-slot="formats"' : '' ) . '>';
	foreach ( $kinds as $kind ) {
		$html .= '<span class="nm-fmt nm-fmt--' . esc_attr( $kind ) . '" data-fmt="' . esc_attr( $kind ) . '">' . ner_michoel_format_icon( $kind ) . '<span class="nm-fmt__name">' . esc_html( $info[ $kind ]['noun'] ) . '</span></span>';
	}
	return $html . '</span>';
}

/**
 * Prints the label for a shiur or written shiur (nothing when it comes one way).
 */
function ner_michoel_render_format_chips( $post_id ) {
	echo ner_michoel_format_chips_markup( ner_michoel_get_shiur_format_kinds( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in ner_michoel_format_chips_markup().
}

/**
 * The Listen / Watch / Read switch on a shiur's own page: one option for each
 * format it comes in, this page's own marked, the others links. Prints nothing
 * when the shiur comes one way. $class adds a modifier for the page it sits in.
 */
function ner_michoel_render_format_switch( $post_id, $class = '' ) {
	$formats = ner_michoel_get_shiur_formats( $post_id, true );
	if ( count( $formats ) < 2 ) {
		return;
	}

	$info = ner_michoel_format_kinds();
	$own  = ner_michoel_format_kind_of( $post_id );
	?>
	<nav class="nm-formats<?php echo $class ? ' ' . esc_attr( $class ) : ''; ?>" aria-label="<?php esc_attr_e( 'Ways to take this shiur', 'ner-michoel-child' ); ?>">
		<?php foreach ( $formats as $kind => $id ) : ?>
			<?php if ( (int) $id === (int) $post_id && $kind !== $own ) : // the same post has this format too (audio + video on one shiur): jump to it on the page ?>
				<a class="nm-formats__opt nm-formats__opt--<?php echo esc_attr( $kind ); ?>" href="#nm-watch" title="<?php echo esc_attr( $info[ $kind ]['title'] ); ?>" data-no-pjax><?php echo ner_michoel_format_icon( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?><span><?php echo esc_html( $info[ $kind ]['verb'] ); ?></span></a>
			<?php elseif ( $kind === $own ) : ?>
				<span class="nm-formats__opt nm-formats__opt--<?php echo esc_attr( $kind ); ?> is-current" aria-current="page"><?php echo ner_michoel_format_icon( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?><span><?php echo esc_html( $info[ $kind ]['verb'] ); ?></span></span>
			<?php else : ?>
				<a class="nm-formats__opt nm-formats__opt--<?php echo esc_attr( $kind ); ?>" href="<?php echo esc_url( get_permalink( $id ) ); ?>" title="<?php echo esc_attr( $info[ $kind ]['title'] ); ?>"><?php echo ner_michoel_format_icon( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?><span><?php echo esc_html( $info[ $kind ]['verb'] ); ?></span></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * The stylesheet. Site-wide like the other component styles, so the router
 * has it ready when it swaps a visitor onto a page that uses it.
 */
function ner_michoel_enqueue_format_styles() {
	wp_enqueue_style(
		'ner-michoel-shiur-formats',
		NER_MICHOEL_URI . '/assets/css/shiur-formats.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_format_styles', 21 );

/* ------------------------------------------------------------------
 * The same row everywhere (Yoni, 2026-11-13): every shiur row in a list
 * (series, speaker and topic pages, the homepage, the search table) shows
 * its speaker and series as links, the buttons that do each format right
 * there (Listen plays in the bar, Watch opens the video, Read opens the
 * written shiur) in place of the Audio / Video / Written chips, and an
 * arrow to the shiur's own page. The search table builds the same markup
 * in the browser (assets/js/shiur-live-search.js).
 * ------------------------------------------------------------------ */

/**
 * The formats a row offers: array( kind => post ID ), at least the shiur itself.
 */
function ner_michoel_row_formats( $post_id ) {
	$formats = ner_michoel_get_shiur_formats( $post_id );
	if ( ! $formats ) {
		$formats = array( ner_michoel_format_kind_of( $post_id ) => (int) $post_id );
	}
	return $formats;
}

/**
 * The speaker and series of a shiur as links: "Rabbi X · Gemara / Bava Kamma". Escaped.
 *
 * @param int  $post_id      The shiur.
 * @param bool $show_speaker False on a list where every row is the same speaker's.
 */
function ner_michoel_row_byline_markup( $post_id, $show_speaker = true ) {
	$parts = array();
	foreach ( $show_speaker ? array( 'speaker', 'series' ) : array( 'series' ) as $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		$term = $terms[0];
		$url  = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}
		$name    = 'series' === $taxonomy && function_exists( 'ner_michoel_series_full_name' ) ? ner_michoel_series_full_name( $term ) : html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
		$parts[] = '<a class="nm-by__' . esc_attr( $taxonomy ) . '" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
	}
	return $parts ? '<span class="nm-by">' . implode( '<span class="nm-by__sep" aria-hidden="true"> · </span>', $parts ) . '</span>' : '';
}

/**
 * The row's buttons: one per format (Listen / Watch / Read) and the arrow to the shiur's page. Escaped.
 *
 * @param int    $post_id The shiur the row is.
 * @param string $listen  How Listen plays: 'row' when the row itself plays (a track list: the row's
 *                        click handler in custom.js takes it), 'queue' to carry its own track
 *                        (data-play-queue, played by the card handler in custom.js).
 */
function ner_michoel_row_actions_markup( $post_id, $listen = 'queue' ) {
	$info    = ner_michoel_format_kinds();
	$formats = ner_michoel_row_formats( $post_id );
	$html    = '<span class="nm-acts">';
	foreach ( array( 'a', 'v', 'w' ) as $kind ) {
		if ( empty( $formats[ $kind ] ) ) {
			continue;
		}
		$id    = (int) $formats[ $kind ];
		$label = ner_michoel_format_icon( $kind ) . '<span>' . esc_html( $info[ $kind ]['verb'] ) . '</span>';
		$title = ' title="' . esc_attr( $info[ $kind ]['title'] ) . '"';
		if ( 'a' === $kind ) {
			$attrs = '';
			if ( 'row' !== $listen || $id !== (int) $post_id ) {
				$post  = get_post( $id );
				$track = ( $post && function_exists( 'ner_michoel_build_track_queue' ) ) ? ner_michoel_build_track_queue( array( $post ) ) : array();
				if ( ! $track ) {
					continue;
				}
				$attrs = ' data-play-queue="' . esc_attr( wp_json_encode( $track ) ) . '" data-play-index="0"';
			}
			$html .= '<button type="button" class="nm-act nm-act--a"' . $title . $attrs . '>' . $label . '</button>';
		} else {
			// The video of an audio shiur that has its Vimeo link on the same post is on that post's page.
			$url   = get_permalink( $id ) . ( ( 'v' === $kind && ! empty( $formats['a'] ) && (int) $formats['a'] === $id ) ? '#nm-watch' : '' );
			$html .= '<a class="nm-act nm-act--' . esc_attr( $kind ) . '" href="' . esc_url( $url ) . '"' . $title . '>' . $label . '</a>';
		}
	}
	$html .= '<a class="nm-go" href="' . esc_url( get_permalink( $post_id ) ) . '" aria-label="' . esc_attr__( 'Open the shiur\'s page', 'ner-michoel-child' ) . '" title="' . esc_attr__( 'Open the shiur\'s page', 'ner-michoel-child' ) . '">';
	$html .= '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>';
	return $html . '</span>';
}
