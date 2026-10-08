<?php
/**
 * Custom template tags and hook callbacks for Astra's action hooks
 * (astra_header, astra_content_top, astra_footer, etc.), plus the
 * markup helpers for the Shiurim (streaming-style) templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True on the Shiurim archive, a speaker/series term page, or a single
 * shiur. Kept separate from ner_michoel_is_app_context() below because
 * only Shiurim pages get the audio player bar chrome.
 */
function ner_michoel_is_shiurim_context() {
	return is_post_type_archive( 'shiur' ) || is_tax( array( 'speaker', 'series', 'topic' ) ) || is_singular( 'shiur' )
		|| ( function_exists( 'ner_michoel_is_shiur_search' ) && ner_michoel_is_shiur_search() );
}

/**
 * Written shiurim (PDF lectures): their own archive and single page, but
 * not a Shiurim page — no player bar, no audio queue. They sit in the
 * app look and show the layout toggle, like Galleries do.
 */
function ner_michoel_is_written_context() {
	return is_post_type_archive( 'written_shiur' ) || is_singular( 'written_shiur' );
}

/**
 * A shiur's topics (ner-michoel-core includes/topics.php) as links to their pages.
 * Nothing when it has none, or when the core plugin is older than topics.
 */
function ner_michoel_render_topic_chips( $post_id ) {
	if ( ! taxonomy_exists( 'topic' ) ) {
		return;
	}
	$topics = get_the_terms( $post_id, 'topic' );
	if ( ! $topics || is_wp_error( $topics ) ) {
		return;
	}
	?>
	<ul class="sh-topic-chips" aria-label="<?php esc_attr_e( 'Topics', 'ner-michoel-child' ); ?>">
		<?php foreach ( $topics as $topic ) : ?>
			<li><a class="sh-topic-chip" href="<?php echo esc_url( get_term_link( $topic ) ); ?>"><?php echo esc_html( $topic->name ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * One row in a written-shiurim list: date, title, speaker, and a Read PDF
 * link when a PDF is attached. Expects to be called inside the loop, or
 * with an explicit post ID.
 */
function ner_michoel_render_written_row( $post_id ) {
	$speaker_terms = get_the_terms( $post_id, 'speaker' );
	$speaker       = ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) ? $speaker_terms[0]->name : '';
	$pdf_url       = function_exists( 'ner_michoel_get_written_shiur_download_url' ) ? ner_michoel_get_written_shiur_download_url( $post_id ) : '';
	?>
	<article class="sh-written-row">
		<div class="sh-written-row__date"><?php echo esc_html( get_the_date( '', $post_id ) ); ?></div>
		<div class="sh-written-row__body">
			<h3 class="sh-written-row__title"><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
			<?php if ( $speaker ) : ?>
				<p class="sh-written-row__meta"><?php echo esc_html( $speaker ); ?></p>
			<?php endif; ?>
			<?php $first_line = ner_michoel_written_first_line( $post_id ); ?>
			<?php if ( $first_line ) : ?>
				<p class="sh-written-row__summary"><?php echo esc_html( $first_line ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $pdf_url ) : ?>
			<a class="sh-download-link sh-written-row__pdf" href="<?php echo esc_url( $pdf_url ); ?>"><?php esc_html_e( 'Download PDF', 'ner-michoel-child' ); ?></a>
		<?php endif; ?>
	</article>
	<?php
}

/**
 * True on any page that carries the Modern/Classic layout toggle:
 * Shiurim, Written Shiurim, Galleries, or the News & Events page. Mazal
 * Tov has no context of its own — it's a section inside the News & Events
 * page, not a separate archive.
 */
function ner_michoel_is_app_context() {
	return ner_michoel_is_shiurim_context()
		|| ner_michoel_is_written_context()
		|| is_post_type_archive( 'gallery' ) || is_tax( 'gallery_type' ) || is_singular( 'gallery' )
		|| is_page_template( 'page-templates/news-events.php' );
}

/**
 * Which layout to render:
 * - 'stream'  — the Spotify/app-style visual language (grid browsing)
 * - 'classic' — the plain filterable/sortable library style
 * - 'studio'  — the new homepage's light look on the Shiurim pages, with
 *   Modern's lists and 24Six's swipeable rows (inc/shiurim-studio.php,
 *   assets/css/shiurim-studio.css). The Shiurim home has its own page;
 *   the other pages keep their markup and take the look from the CSS.
 * - '24six'   — carousel/swimlane browsing (horizontal-scrolling rows
 *   of Series/Speakers instead of a wrapping grid), inspired by
 *   24six.app's layout specifically, not its color scheme — reuses
 *   the same dark palette as 'stream'. Shiurim-only: archive-shiur.php
 *   and taxonomy-speaker.php are the only templates that branch on
 *   this value; taxonomy-series.php and single-shiur.php show one
 *   collection's contents rather than browsing multiple collections,
 *   so there's nothing meaningful to turn into a carousel there —
 *   they render the same as 'stream' for any non-'classic' value.
 * Shared site-wide across Shiurim, Galleries, and News & Events so
 * the top-right toggle is one global choice, not a per-section
 * setting (Galleries/News & Events don't have a '24six'-specific
 * layout of their own yet — they just render their 'stream' markup
 * for that value, same as taxonomy-series.php/single-shiur.php do).
 * Persisted in a cookie so the server can render the right templates
 * directly instead of shipping all of them to the client.
 */
function ner_michoel_get_layout() {
	$layout = isset( $_COOKIE['nm_layout'] ) ? sanitize_key( wp_unslash( $_COOKIE['nm_layout'] ) ) : 'stream';
	return in_array( $layout, array( 'classic', '24six', 'studio' ), true ) ? $layout : 'stream';
}

/**
 * Adds `is-nm-app` plus the active layout as a body class on every
 * toggle-carrying template (and `is-shiurim` specifically for the
 * Shiurim ones), so custom.css can scope each layout's styles without
 * touching the rest of the (Astra-styled) site.
 */
function ner_michoel_app_body_class( $classes ) {
	if ( ner_michoel_is_app_context() ) {
		$classes[] = 'is-nm-app';
		$classes[] = 'sh-layout-' . ner_michoel_get_layout();
		if ( ner_michoel_is_shiurim_context() ) {
			$classes[] = 'is-shiurim';
		}
	}
	return $classes;
}
add_filter( 'body_class', 'ner_michoel_app_body_class' );

/**
 * Top-right switch between the four layouts. Flipping it sets the
 * layout cookie and reloads, since each layout is a genuinely
 * different template (not a client-side CSS skin) — see the branches
 * at the top of each app-context template.
 */
function ner_michoel_render_layout_toggle() {
	if ( ! ner_michoel_is_app_context() ) {
		return;
	}
	$layout   = ner_michoel_get_layout();
	$position = function_exists( 'ner_michoel_get_layout_toggle_position' ) ? ner_michoel_get_layout_toggle_position() : 'top-right';
	$opacity  = function_exists( 'ner_michoel_get_layout_toggle_opacity' ) ? ner_michoel_get_layout_toggle_opacity() : 100;
	?>
	<div class="sh-layout-toggle sh-layout-toggle--<?php echo esc_attr( $position ); ?>" style="--nm-toggle-bg-alpha: <?php echo esc_attr( $opacity / 100 ); ?>;" role="group" aria-label="<?php esc_attr_e( 'Layout', 'ner-michoel-child' ); ?>">
		<button type="button" class="sh-layout-toggle__option<?php echo 'stream' === $layout ? ' is-active' : ''; ?>" data-layout="stream"><?php esc_html_e( 'Modern', 'ner-michoel-child' ); ?></button>
		<button type="button" class="sh-layout-toggle__option<?php echo 'classic' === $layout ? ' is-active' : ''; ?>" data-layout="classic"><?php esc_html_e( 'Classic', 'ner-michoel-child' ); ?></button>
		<button type="button" class="sh-layout-toggle__option<?php echo '24six' === $layout ? ' is-active' : ''; ?>" data-layout="24six"><?php esc_html_e( '24Six', 'ner-michoel-child' ); ?></button>
		<button type="button" class="sh-layout-toggle__option<?php echo 'studio' === $layout ? ' is-active' : ''; ?>" data-layout="studio"><?php esc_html_e( 'Studio', 'ner-michoel-child' ); ?></button>
	</div>
	<?php
}
add_action( 'wp_body_open', 'ner_michoel_render_layout_toggle' );

/**
 * Small inline icon set for the player controls — kept as literal SVG
 * (not an icon font/dependency) so it's swapped by JS with zero
 * extra requests.
 */
function ner_michoel_icon( $name ) {
	$icons = array(
		'play'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>',
		'pause'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>',
		'prev'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M6 6h2v12H6zM20 6v12l-10-6z"/></svg>',
		'next'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M16 6h2v12h-2zM4 6v12l10-6z"/></svg>',
		'volume'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3a4.5 4.5 0 0 0-2.5-4v8a4.5 4.5 0 0 0 2.5-4z"/></svg>',
		'download' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 3v10.17l3.59-3.58L17 11l-5 5-5-5 1.41-1.41L11 13.17V3h1zM5 19h14v2H5z"/></svg>',
		'video'    => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/></svg>',
		'search'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/></svg>',
		// One path serves both states of the Save button: filled via the
		// default fill="currentColor" when saved, or outline-only via a
		// CSS override (.sh-save:not(.is-saved) svg) when not — see
		// ner_michoel_render_save_button().
		'heart'    => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
	);
	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

/**
 * Outline icons for the light, content-facing pages (homepage section
 * index, placeholders) — one consistent 24px, 1.75-stroke set in place
 * of emoji, which render differently on every OS and read as a
 * generated-template look. Inherit color via currentColor.
 *
 * Paths are from Lucide (https://lucide.dev) — ISC License,
 * Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022
 * as part of Feather (MIT); all other copyright (c) Lucide Contributors.
 */
function ner_michoel_line_icon( $name ) {
	$paths = array(
		'headphones' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
		'image'      => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
		'newspaper'  => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/>',
		'book-open'  => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
		'heart'      => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
		'mail'       => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'check'       => '<path d="M20 6 9 17l-5-5"/>',
		'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		'search'      => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'maximize'    => '<path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/>',
		'close'       => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'share'       => '<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/>',
		'external'    => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'arrow-left'  => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
		'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'map-pin'     => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
		'send'        => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="nm-icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Placeholder artwork for a homepage card with no image: a dark tile
 * with a line motif — an audio waveform for shiurim, a picture frame
 * for galleries — instead of a stock gradient plus an emoji.
 */
function ner_michoel_art_placeholder( $kind = 'audio' ) {
	if ( 'audio' === $kind ) {
		// Bar heights tuned by eye to read as a waveform, not a bar chart.
		$heights = array( 6, 12, 20, 11, 24, 15, 8, 18, 22, 10, 14, 6 );
		$bars    = '';
		foreach ( $heights as $i => $h ) {
			$x     = 4 + $i * 6;
			$bars .= sprintf( '<line x1="%1$d" y1="%2$s" x2="%1$d" y2="%3$s"/>', $x, 16 - $h / 2, 16 + $h / 2 );
		}
		$art = '<svg class="nm-art-placeholder__wave" viewBox="0 0 74 32" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" focusable="false">' . $bars . '</svg>';
	} else {
		$art = ner_michoel_line_icon( 'image' );
	}
	return '<span class="nm-art-placeholder nm-art-placeholder--' . esc_attr( $kind ) . '" aria-hidden="true">' . $art . '</span>';
}

/**
 * Fallback "cover art" for a speaker/series/shiur with no image set —
 * a tile showing its first letter, styled entirely in CSS. --hue gives
 * each name its own tint where a layout uses it (Modern, in
 * shiurim-modern.css); the same name always gets the same one, matching
 * the homepage's initials (ner_michoel_home_hue()).
 */
function ner_michoel_placeholder_art( $label = '' ) {
	$initial = $label ? mb_substr( trim( $label ), 0, 1 ) : '♪';
	$hue     = abs( crc32( (string) $label ) ) % 360;
	return '<div class="sh-placeholder-art" style="--hue: ' . (int) $hue . ';" aria-hidden="true">' . esc_html( $initial ) . '</div>';
}

/**
 * "N photos" for a photo gallery, otherwise just the date — a photo
 * count would be wrong/misleading for a video or shiurim-video
 * gallery, which has no images array to count. Shared by
 * archive-gallery.php and taxonomy-gallery_type.php.
 */
function ner_michoel_gallery_card_subtitle( $post_id ) {
	if ( 'photo' === ner_michoel_get_gallery_type( $post_id ) ) {
		$count = ner_michoel_get_gallery_image_count( $post_id );
		return sprintf(
			/* translators: %d: number of photos */
			_n( '%d photo', '%d photos', $count, 'ner-michoel-child' ),
			$count
		);
	}
	return get_the_date( '', $post_id );
}

/**
 * "Back" button at the top of a Shiurim-section page, in every layout.
 * It never leaves the page. It steps back through the layouts already
 * tried on this page, and stops at the layout the page opened with. The
 * trail is kept by custom.js. The button starts disabled, and the script
 * enables it once there's a layout to go back to.
 */
function ner_michoel_render_back_button( $label = '' ) {
	if ( '' === $label ) {
		$label = __( 'Back', 'ner-michoel-child' );
	}
	?>
	<nav class="sh-back-row" aria-label="<?php esc_attr_e( 'Page navigation', 'ner-michoel-child' ); ?>">
		<button type="button" class="sh-back" data-nm-back disabled>
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
			<span><?php echo esc_html( $label ); ?></span>
		</button>
		<a class="sh-back sh-home" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/></svg>
			<span><?php esc_html_e( 'Home', 'ner-michoel-child' ); ?></span>
		</a>
	</nav>
	<?php
}

/**
 * Opens a collection of media cards as either the wrapping grid
 * (Modern/'stream') or a horizontal-scrolling carousel row ('24six')
 * — the cards themselves (ner_michoel_render_media_card()) are
 * identical either way, only the container differs. Pair with
 * ner_michoel_render_card_collection_end(). $is_carousel is passed in
 * rather than read from ner_michoel_get_layout() here, since a page
 * may want the grid even in carousel mode (not currently the case,
 * but keeps this a dumb layout primitive rather than baking in
 * "24six" as a concept it needs to know about).
 */
function ner_michoel_render_card_collection_start( $is_carousel ) {
	if ( $is_carousel ) {
		?>
		<div class="sh-carousel">
			<button type="button" class="sh-carousel__nav sh-carousel__nav--prev" aria-label="<?php esc_attr_e( 'Scroll left', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'prev' ); ?></button>
			<div class="sh-carousel__track">
		<?php
	} else {
		echo '<div class="sh-grid">';
	}
}

/**
 * Closes what ner_michoel_render_card_collection_start() opened.
 */
function ner_michoel_render_card_collection_end( $is_carousel ) {
	if ( $is_carousel ) {
		?>
			</div>
			<button type="button" class="sh-carousel__nav sh-carousel__nav--next" aria-label="<?php esc_attr_e( 'Scroll right', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'next' ); ?></button>
		</div>
		<?php
	} else {
		echo '</div>';
	}
}

/**
 * "New" is pure date math (published within the last 14 days) — no
 * backend needed. "Trending" defers to ner_michoel_is_shiur_trending()
 * if the backend ever ships one (based on real play-count data none
 * of our accessors expose yet); until then it's silently omitted
 * rather than guessed at with a fake signal.
 */
function ner_michoel_get_shiur_badges( $post_id ) {
	$badges = array();

	$published = get_post_time( 'U', true, $post_id );
	if ( $published && ( time() - $published ) <= 14 * DAY_IN_SECONDS ) {
		$badges[] = array(
			'key'   => 'new',
			'label' => __( 'New', 'ner-michoel-child' ),
		);
	}

	if ( function_exists( 'ner_michoel_is_shiur_trending' ) && ner_michoel_is_shiur_trending( $post_id ) ) {
		$badges[] = array(
			'key'   => 'trending',
			'label' => __( 'Trending', 'ner-michoel-child' ),
		);
	}

	return $badges;
}

/**
 * Renders whatever ner_michoel_get_shiur_badges() returns as small
 * pill labels. Shared by tracklist rows, the classic table, the
 * single-shiur hero, and the homepage's recent-shiurim cards, so a
 * new badge type only needs adding in one place.
 */
function ner_michoel_render_shiur_badges( $post_id ) {
	$badges = ner_michoel_get_shiur_badges( $post_id );
	if ( ! $badges ) {
		return;
	}
	echo '<span class="sh-badges">';
	foreach ( $badges as $badge ) {
		echo '<span class="sh-badge sh-badge--' . esc_attr( $badge['key'] ) . '">' . esc_html( $badge['label'] ) . '</span>';
	}
	echo '</span>';
}

/**
 * A toggleable Save button for a shiur or written shiur — adds/removes
 * it from the visitor's Saved list (ner-michoel-core's user-library.php;
 * Account page > Saved tab, once ner-michoel-child's account.php grows
 * one). Logged-out visitors never see it: there's no saved list to add
 * to without an account, and a button that only bounces you to a
 * login page on click is worse than no button, not better. The actual
 * toggle (POST savedToggleUrl, assets/js/custom.js) needs
 * is_user_logged_in() true at request time regardless — this is also
 * just the honest reflection of that on the page.
 */
function ner_michoel_render_save_button( $post_id ) {
	if ( ! is_user_logged_in() || ! function_exists( 'ner_michoel_is_post_saved_by_user' ) ) {
		return;
	}

	$is_saved = ner_michoel_is_post_saved_by_user( $post_id );
	$label    = $is_saved ? __( 'Saved', 'ner-michoel-child' ) : __( 'Save', 'ner-michoel-child' );
	?>
	<button
		type="button"
		class="sh-save<?php echo $is_saved ? ' is-saved' : ''; ?>"
		data-save-id="<?php echo esc_attr( $post_id ); ?>"
		data-label-saved="<?php esc_attr_e( 'Saved', 'ner-michoel-child' ); ?>"
		data-label-unsaved="<?php esc_attr_e( 'Save', 'ner-michoel-child' ); ?>"
		aria-pressed="<?php echo $is_saved ? 'true' : 'false'; ?>"
	><?php echo ner_michoel_icon( 'heart' ); ?> <span class="sh-save__text"><?php echo esc_html( $label ); ?></span></button>
	<?php
}

/**
 * A clickable card for a speaker, series, shiur, or written shiur —
 * cover art, title, subtitle, and (if a track queue is supplied) a
 * hover play button that starts playback without leaving the grid.
 *
 * save_id (optional, default 0): a shiur/written_shiur post ID to
 * overlay a Save toggle on, for callers that render actual content
 * cards rather than speaker/series cards (those have nothing to
 * save). Logged-out visitors never see it, same as everywhere else
 * Save appears — nothing extra for a caller to check first.
 */
function ner_michoel_render_media_card( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'    => '',
			'subtitle' => '',
			'image'    => '',
			'link'     => '',
			'queue'     => array(),
			'queue_url' => '', // The full queue, loaded when the play button is clicked (ner-michoel/v1/queue).
			'round'    => false,
			'save_id'  => 0,
			'variant'  => '', // 'series' gives a series card its own look (see shiurim-cards.css).
			'kicker'   => '', // Small label above the title, e.g. "Series".
		)
	);

	$show_save = $args['save_id'] && is_user_logged_in() && function_exists( 'ner_michoel_is_post_saved_by_user' );
	$is_saved  = $show_save && ner_michoel_is_post_saved_by_user( $args['save_id'] );
	$classes   = 'sh-card' . ( $args['round'] ? ' sh-card--round' : '' ) . ( 'series' === $args['variant'] ? ' sh-card--series' : '' );
	?>
	<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $args['link'] ); ?>">
		<div class="sh-card__art">
			<?php if ( $args['image'] ) : ?>
				<img src="<?php echo esc_url( $args['image'] ); ?>" alt="" loading="lazy" />
			<?php else : ?>
				<?php echo ner_michoel_placeholder_art( $args['title'] ); ?>
			<?php endif; ?>
			<?php if ( ! empty( $args['queue'] ) || $args['queue_url'] ) : ?>
				<button
					type="button"
					class="sh-card__play"
					aria-label="<?php esc_attr_e( 'Play', 'ner-michoel-child' ); ?>"
					<?php if ( ! empty( $args['queue'] ) ) : ?>
					data-play-queue="<?php echo esc_attr( wp_json_encode( $args['queue'] ) ); ?>"
					<?php endif; ?>
					<?php if ( $args['queue_url'] ) : ?>
					data-queue-url="<?php echo esc_url( $args['queue_url'] ); ?>"
					<?php endif; ?>
					data-play-index="0"
				><?php echo ner_michoel_icon( 'play' ); ?></button>
			<?php endif; ?>
			<?php if ( $show_save ) : ?>
				<button
					type="button"
					class="sh-save sh-card__save<?php echo $is_saved ? ' is-saved' : ''; ?>"
					data-save-id="<?php echo esc_attr( $args['save_id'] ); ?>"
					aria-pressed="<?php echo $is_saved ? 'true' : 'false'; ?>"
					aria-label="<?php esc_attr_e( 'Save', 'ner-michoel-child' ); ?>"
				><?php echo ner_michoel_icon( 'heart' ); ?></button>
			<?php endif; ?>
		</div>
		<?php if ( $args['kicker'] ) : ?>
			<div class="sh-card__kicker"><?php echo esc_html( $args['kicker'] ); ?></div>
		<?php endif; ?>
		<div class="sh-card__title"><?php echo esc_html( $args['title'] ); ?></div>
		<?php if ( $args['subtitle'] ) : ?>
			<div class="sh-card__subtitle"><?php echo esc_html( $args['subtitle'] ); ?></div>
		<?php endif; ?>
	</a>
	<?php
}

/**
 * A shiur or written-shiur card for the Account page's History /
 * Saved / Suggested tabs (page-templates/account.php). Deliberately
 * not ner_michoel_render_media_card() above: that card (.sh-card)
 * depends on the --sh-* custom properties that only exist under
 * body.is-nm-app's dark app shell, and the Account page is the plain
 * light .nm-page template (login/profile), not that shell — used
 * there it would render with invisible/default text, the same bug
 * class as the documented .is-nm-app text-color incident elsewhere in
 * this file's history. Reuses the homepage's light-themed "Recent
 * Shiurim" card instead (front-page.php's .nm-home-shiur-card),
 * extended to also cover written_shiur and an optional Save toggle —
 * kept as its own function rather than factored out of a page that
 * was already live and working, so this is new, isolated surface
 * rather than a refactor of it.
 */
function ner_michoel_render_library_card( $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	$is_written    = 'written_shiur' === $post->post_type;
	$speaker_terms = get_the_terms( $post_id, 'speaker' );
	$speaker       = ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) ? $speaker_terms[0] : null;

	$cover = get_the_post_thumbnail_url( $post_id, 'medium' );
	if ( ! $cover && $speaker && function_exists( 'ner_michoel_get_speaker_photo_url' ) ) {
		$cover = ner_michoel_get_speaker_photo_url( $speaker->term_id );
	}

	$show_save = is_user_logged_in() && function_exists( 'ner_michoel_is_post_saved_by_user' );
	$is_saved  = $show_save && ner_michoel_is_post_saved_by_user( $post_id );
	?>
	<a class="nm-home-shiur-card" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<div class="nm-home-shiur-card__art">
			<?php if ( $cover ) : ?>
				<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy" />
			<?php else : ?>
				<?php echo ner_michoel_art_placeholder( $is_written ? 'image' : 'audio' ); ?>
			<?php endif; ?>
			<?php if ( $show_save ) : ?>
				<button
					type="button"
					class="sh-save nm-home-shiur-card__save<?php echo $is_saved ? ' is-saved' : ''; ?>"
					data-save-id="<?php echo esc_attr( $post_id ); ?>"
					aria-pressed="<?php echo $is_saved ? 'true' : 'false'; ?>"
					aria-label="<?php esc_attr_e( 'Save', 'ner-michoel-child' ); ?>"
				><?php echo ner_michoel_icon( 'heart' ); ?></button>
			<?php endif; ?>
		</div>
		<div class="nm-home-shiur-card__title">
			<?php echo esc_html( get_the_title( $post_id ) ); ?>
			<?php
			if ( ! $is_written ) {
				ner_michoel_render_shiur_badges( $post_id );
			}
			?>
		</div>
		<?php if ( $speaker ) : ?>
			<div class="nm-home-shiur-card__meta"><?php echo esc_html( $speaker->name ); ?></div>
		<?php endif; ?>
	</a>
	<?php
}

/**
 * Persistent left-hand nav for the Shiurim archive (Modern layout
 * only — Classic already has its own flat toolbar, and 24Six's
 * horizontal-carousel rows don't leave room for a sidebar without
 * redesigning the carousel itself). Search + a "Recent" view (the one
 * thing here without its own existing page) + every Series/Speaker,
 * which already have real archive pages (taxonomy-series.php /
 * taxonomy-speaker.php) to link straight to.
 */
function ner_michoel_render_shiurim_sidebar() {
	$series_terms  = get_terms( array( 'taxonomy' => 'series', 'hide_empty' => true ) );
	$speaker_terms = get_terms( array( 'taxonomy' => 'speaker', 'hide_empty' => true ) );
	$shiurim_url   = get_post_type_archive_link( 'shiur' );
	$is_recent     = isset( $_GET['sh_view'] ) && 'recent' === $_GET['sh_view']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$is_foryou     = function_exists( 'ner_michoel_is_for_you_view' ) && ner_michoel_is_for_you_view();
	$show_foryou   = function_exists( 'ner_michoel_for_you_available' ) && ner_michoel_for_you_available();
	$is_all        = is_post_type_archive( 'shiur' ) && ! $is_recent && ! $is_foryou;
	?>
	<aside class="sh-sidebar">
		<form class="sh-sidebar__search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo ner_michoel_icon( 'search' ); ?>
			<input type="hidden" name="post_type" value="shiur" />
			<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search shiurim…', 'ner-michoel-child' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" />
		</form>

		<nav class="sh-sidebar__nav">
			<a href="<?php echo esc_url( $shiurim_url ); ?>" class="sh-sidebar__link<?php echo $is_all ? ' is-active' : ''; ?>"><?php esc_html_e( 'All Shiurim', 'ner-michoel-child' ); ?></a>
			<?php if ( $show_foryou ) : ?>
				<a href="<?php echo esc_url( ner_michoel_for_you_url() ); ?>" class="sh-sidebar__link<?php echo $is_foryou ? ' is-active' : ''; ?>"><?php esc_html_e( 'For you', 'ner-michoel-child' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( add_query_arg( 'sh_view', 'recent', $shiurim_url ) ); ?>" class="sh-sidebar__link<?php echo $is_recent ? ' is-active' : ''; ?>"><?php esc_html_e( 'Recent', 'ner-michoel-child' ); ?></a>
			<?php if ( post_type_exists( 'written_shiur' ) ) : ?>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'written_shiur' ) ); ?>" class="sh-sidebar__link<?php echo is_post_type_archive( 'written_shiur' ) ? ' is-active' : ''; ?>"><?php esc_html_e( 'Written Shiurim', 'ner-michoel-child' ); ?></a>
			<?php endif; ?>
		</nav>

		<?php if ( $series_terms && ! is_wp_error( $series_terms ) ) : ?>
			<div class="sh-sidebar__group">
				<h3 class="sh-sidebar__group-title"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></h3>
				<nav class="sh-sidebar__nav sh-sidebar__nav--scroll">
					<?php foreach ( $series_terms as $term ) : ?>
						<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="sh-sidebar__link<?php echo is_tax( 'series', $term->term_id ) ? ' is-active' : ''; ?>"><?php echo esc_html( $term->name ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
		<?php endif; ?>

		<?php if ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) : ?>
			<div class="sh-sidebar__group">
				<h3 class="sh-sidebar__group-title"><?php esc_html_e( 'Speakers', 'ner-michoel-child' ); ?></h3>
				<nav class="sh-sidebar__nav sh-sidebar__nav--scroll">
					<?php foreach ( $speaker_terms as $term ) : ?>
						<a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="sh-sidebar__link<?php echo is_tax( 'speaker', $term->term_id ) ? ' is-active' : ''; ?>"><?php echo esc_html( $term->name ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
		<?php endif; ?>
	</aside>
	<?php
}

/**
 * True if a shiur is a video (not audio) — checked defensively since
 * ner_michoel_get_shiur_media_type() may not exist yet on the backend
 * (falls back to 'audio', matching every shiur before video support
 * was added).
 */
function ner_michoel_shiur_is_video( $post_id ) {
	if ( ! function_exists( 'ner_michoel_get_shiur_media_type' ) ) {
		return false;
	}
	$type = ner_michoel_get_shiur_media_type( $post_id );
	if ( 'video' === $type ) {
		return function_exists( 'ner_michoel_get_shiur_video_url' ) && (bool) ner_michoel_get_shiur_video_url( $post_id );
	}
	if ( 'video-embed' === $type ) {
		return function_exists( 'ner_michoel_get_shiur_vimeo_id' ) && (bool) ner_michoel_get_shiur_vimeo_id( $post_id );
	}
	return false;
}

/**
 * One row inside a tracklist. A video shiur renders as a plain link
 * to its single page instead — it isn't part of the audio queue
 * (see ner_michoel_render_tracklist()), so there's no `data-index`
 * for the player JS to act on; native navigation handles it. Silently
 * skips a shiur with neither audio nor video — nothing to play.
 */
function ner_michoel_render_track_row( $shiur, $index, $show_speaker = true ) {
	$speaker_terms = get_the_terms( $shiur, 'speaker' );
	$speaker_name  = ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) ? $speaker_terms[0]->name : '';
	$cover         = get_the_post_thumbnail_url( $shiur, 'thumbnail' );

	if ( ner_michoel_shiur_is_video( $shiur->ID ) ) {
		?>
		<a class="sh-track sh-track--video" href="<?php echo esc_url( get_permalink( $shiur ) ); ?>" data-id="<?php echo esc_attr( $shiur->ID ); ?>">
			<div class="sh-track__num"><?php echo ner_michoel_icon( 'video' ); ?></div>
			<div class="sh-track__art">
				<?php if ( $cover ) : ?>
					<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy" />
				<?php else : ?>
					<?php echo ner_michoel_placeholder_art( get_the_title( $shiur ) ); ?>
				<?php endif; ?>
			</div>
			<div class="sh-track__info">
				<div class="sh-track__title"><?php echo esc_html( get_the_title( $shiur ) ); ?><?php ner_michoel_render_shiur_badges( $shiur->ID ); ?></div>
				<?php if ( $show_speaker && $speaker_name ) : ?>
					<div class="sh-track__speaker"><?php echo esc_html( $speaker_name ); ?></div>
				<?php endif; ?>
			</div>
			<div class="sh-track__duration"><?php esc_html_e( 'Watch', 'ner-michoel-child' ); ?></div>
		</a>
		<?php
		return;
	}
	?>
	<div class="sh-track" data-id="<?php echo esc_attr( $shiur->ID ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
		<div class="sh-track__num">
			<span class="sh-track__index"><?php echo esc_html( $index + 1 ); ?></span>
			<span class="sh-track__eq" aria-hidden="true"><span></span><span></span><span></span></span>
			<button type="button" class="sh-track__play" aria-label="<?php esc_attr_e( 'Play', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'play' ); ?></button>
		</div>
		<div class="sh-track__art">
			<?php if ( $cover ) : ?>
				<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy" />
			<?php else : ?>
				<?php echo ner_michoel_placeholder_art( get_the_title( $shiur ) ); ?>
			<?php endif; ?>
		</div>
		<div class="sh-track__info">
			<div class="sh-track__title"><?php echo esc_html( get_the_title( $shiur ) ); ?><?php ner_michoel_render_shiur_badges( $shiur->ID ); ?></div>
			<?php if ( $show_speaker && $speaker_name ) : ?>
				<div class="sh-track__speaker"><?php echo esc_html( $speaker_name ); ?></div>
			<?php endif; ?>
		</div>
		<div class="sh-track__duration"><?php echo esc_html( ner_michoel_get_shiur_duration( $shiur->ID ) ); ?><?php
			// Like this shiur from its row. The heart sits in the duration cell so the
			// row's grid stays as it is. Logged-in visitors only, like every heart.
			if ( is_user_logged_in() && function_exists( 'ner_michoel_is_post_saved_by_user' ) ) :
				$row_saved = ner_michoel_is_post_saved_by_user( $shiur->ID );
				?>
				<button type="button" class="sh-save sh-track__save<?php echo $row_saved ? ' is-saved' : ''; ?>" data-save-id="<?php echo esc_attr( $shiur->ID ); ?>" aria-pressed="<?php echo $row_saved ? 'true' : 'false'; ?>" aria-label="<?php esc_attr_e( 'Like', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'heart' ); ?></button>
			<?php endif; ?></div>
		<?php
		$download_url = function_exists( 'ner_michoel_get_shiur_download_url' ) ? ner_michoel_get_shiur_download_url( $shiur->ID ) : '';
		if ( $download_url ) :
			?>
			<a class="sh-track__download" href="<?php echo esc_url( $download_url ); ?>" aria-label="<?php esc_attr_e( 'Download', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'download' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * A full tracklist. Audio shiurim are queue-driven (the queue is
 * serialized once on the wrapper, and each row just carries its
 * index into it, so the player JS doesn't need to re-derive anything
 * from the DOM); video shiurim sit in the same list, in the same
 * order, but as plain links — they're excluded from the queue
 * entirely, not just skipped from playback, so a video row's
 * position never has to line up with an index in `data-queue`.
 */
function ner_michoel_render_tracklist( $shiurim, $show_speaker = true ) {
	$visible = array_values(
		array_filter(
			$shiurim,
			function( $shiur ) {
				return (bool) ner_michoel_get_shiur_audio_url( $shiur->ID ) || ner_michoel_shiur_is_video( $shiur->ID );
			}
		)
	);

	if ( empty( $visible ) ) {
		echo '<p class="sh-empty">' . esc_html__( 'Nothing uploaded yet.', 'ner-michoel-child' ) . '</p>';
		return;
	}

	$audio_only = array_values(
		array_filter(
			$visible,
			function( $shiur ) {
				return ! ner_michoel_shiur_is_video( $shiur->ID );
			}
		)
	);
	$queue           = ner_michoel_build_track_queue( $audio_only );
	$audio_index_map = array_flip( wp_list_pluck( $audio_only, 'ID' ) );
	?>
	<div class="sh-tracklist" data-queue="<?php echo esc_attr( wp_json_encode( $queue ) ); ?>">
		<?php foreach ( $visible as $shiur ) : ?>
			<?php ner_michoel_render_track_row( $shiur, isset( $audio_index_map[ $shiur->ID ] ) ? $audio_index_map[ $shiur->ID ] : 0, $show_speaker ); ?>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Persistent bottom player bar — printed once in the footer on any
 * Shiurim-related page so playback state can survive navigating
 * between speaker/series/archive pages (see assets/js/custom.js).
 *
 * Shown in Modern ('stream') and 24Six. Every play control (cards, Play
 * All, tracklist rows) is wired to this bar, and custom.js exits early
 * without it, so 24Six had no playback at all until this covered it too.
 * Classic doesn't get it: its audio is plain links.
 */
function ner_michoel_render_player_bar() {
	if ( 'classic' === ner_michoel_get_layout() ) {
		return;
	}
	?>
	<div class="sh-player" id="sh-player" hidden>
		<audio id="sh-audio" preload="metadata"></audio>

		<?php // Full-width hairline scrubber pinned to the bar's top edge. ?>
		<input type="range" class="sh-player__seek" id="sh-player-seek" min="0" max="1000" value="0" aria-label="<?php esc_attr_e( 'Seek', 'ner-michoel-child' ); ?>" />

		<div class="sh-player__body">
			<div class="sh-player__now">
				<div class="sh-player__cover" id="sh-player-cover"></div>
				<div class="sh-player__meta">
					<a class="sh-player__title" id="sh-player-title" href="#"></a>
					<div class="sh-player__speaker" id="sh-player-speaker"></div>
				</div>
			</div>

			<div class="sh-player__buttons">
				<button type="button" class="sh-player__prev" id="sh-player-prev" aria-label="<?php esc_attr_e( 'Previous', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'prev' ); ?></button>
				<button type="button" class="sh-player__toggle" id="sh-player-toggle" aria-label="<?php esc_attr_e( 'Play', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'play' ); ?></button>
				<button type="button" class="sh-player__next" id="sh-player-next" aria-label="<?php esc_attr_e( 'Next', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'next' ); ?></button>
			</div>

			<div class="sh-player__end">
				<button type="button" class="sh-player__open" id="sh-player-open" aria-expanded="false" aria-controls="sh-sheet" aria-label="<?php esc_attr_e( 'Autoplay and queue', 'ner-michoel-child' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
				</button>
				<span class="sh-player__times">
					<span class="sh-player__time" id="sh-player-current">0:00</span>
					<span class="sh-player__time-sep">/</span>
					<span class="sh-player__time sh-player__time--total" id="sh-player-duration">0:00</span>
				</span>
				<div class="sh-player__speed-wrap">
					<button type="button" class="sh-player__speed" id="sh-player-speed" aria-haspopup="menu" aria-expanded="false" aria-controls="sh-player-speed-menu" aria-label="<?php esc_attr_e( 'Playback speed', 'ner-michoel-child' ); ?>">
						<span class="sh-player__speed-label" id="sh-player-speed-label">1&times;</span>
						<svg class="sh-player__speed-caret" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
					</button>
					<?php // Options are built by custom.js from its SPEEDS list, so the list lives in one place. ?>
					<div class="sh-speed-menu" id="sh-player-speed-menu" role="menu" aria-label="<?php esc_attr_e( 'Playback speed', 'ner-michoel-child' ); ?>" hidden></div>
				</div>
				<span class="sh-player__volume">
					<?php echo ner_michoel_icon( 'volume' ); ?>
					<input type="range" class="sh-player__volume-range" id="sh-player-volume" min="0" max="1" step="0.01" value="1" aria-label="<?php esc_attr_e( 'Volume', 'ner-michoel-child' ); ?>" />
				</span>
			</div>
		</div>
	</div>
	<?php
	// Autoplay switch and queue, slid up from the bar (inc/player-sheet.php).
	ner_michoel_render_player_sheet();
}
add_action( 'wp_footer', 'ner_michoel_render_player_bar' );

/**
 * Stylesheet for the series-card look (archive-shiur.php's Series row).
 */
function ner_michoel_enqueue_shiurim_card_styles() {
	wp_enqueue_style(
		'ner-michoel-shiurim-cards',
		NER_MICHOEL_URI . '/assets/css/shiurim-cards.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_shiurim_card_styles', 21 );

/**
 * Modern's look on the Shiurim pages (assets/css/shiurim-modern.css): the
 * same markup and navigation, in a light theme. After the card, navigation
 * and player-sheet styles, which it adjusts (and after the live search's,
 * which is queued earlier at priority 22 on the search page).
 */
function ner_michoel_enqueue_modern_styles() {
	if ( 'stream' !== ner_michoel_get_layout() || ! ner_michoel_is_shiurim_context() ) {
		return;
	}
	wp_enqueue_style(
		'ner-michoel-shiurim-modern',
		NER_MICHOEL_URI . '/assets/css/shiurim-modern.css',
		array( 'ner-michoel-shiurim-cards', 'ner-michoel-shiurim-navigation', 'ner-michoel-player-sheet' ),
		NER_MICHOEL_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_modern_styles', 23 );

/**
 * "Continue series" card for the Account page. It shows the series and the
 * shiur the listener last played in it. Play queues from that shiur through the
 * rest of the series (ner_michoel_series_rest_for_shiur()), then autoplay goes on.
 */
function ner_michoel_render_continue_card( $term, $shiur ) {
	// Its own track now; the rest of its series loads when Continue is pressed. An older
	// ner-michoel-core (no queue endpoint) gets the whole rest of the series in the page.
	if ( function_exists( 'ner_michoel_queue_url' ) ) {
		$queue     = ner_michoel_build_track_queue( array( $shiur ) );
		$queue_url = $queue ? ner_michoel_queue_url(
			array(
				'shiur' => $shiur->ID,
				'mode'  => 'series',
			)
		) : '';
	} else {
		$queue     = ner_michoel_build_track_queue( ner_michoel_series_rest_for_shiur( $shiur ) );
		$queue_url = '';
	}
	$cover  = ner_michoel_get_series_cover_url( $term->term_id );
	$link   = get_term_link( $term );
	?>
	<div class="nm-continue-card">
		<a class="nm-continue-card__link" href="<?php echo esc_url( is_wp_error( $link ) ? '#' : $link ); ?>">
			<span class="nm-continue-card__art">
				<?php if ( $cover ) : ?>
					<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy" />
				<?php else : ?>
					<?php echo ner_michoel_placeholder_art( $term->name ); ?>
				<?php endif; ?>
			</span>
			<span class="nm-continue-card__text">
				<span class="nm-continue-card__kicker"><?php esc_html_e( 'Continue series', 'ner-michoel-child' ); ?></span>
				<span class="nm-continue-card__title"><?php echo esc_html( $term->name ); ?></span>
				<span class="nm-continue-card__meta">
					<?php
					/* translators: %s: title of the shiur the listener last played */
					echo esc_html( sprintf( __( 'Left off at: %s', 'ner-michoel-child' ), get_the_title( $shiur ) ) );
					?>
				</span>
			</span>
		</a>
		<?php if ( $queue ) : ?>
			<button type="button" class="nm-continue-card__play" data-play-queue="<?php echo esc_attr( wp_json_encode( $queue ) ); ?>"<?php if ( $queue_url ) : ?> data-queue-url="<?php echo esc_url( $queue_url ); ?>"<?php endif; ?> data-play-index="0">
				<?php echo ner_michoel_icon( 'play' ); ?>
				<span><?php esc_html_e( 'Continue', 'ner-michoel-child' ); ?></span>
			</button>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The first line of a written shiur, for its homepage card. That's the
 * excerpt if there is one, otherwise the first non-empty line of the
 * description, trimmed to about 18 words. '' when there's no text at all.
 * Works on Hebrew and other non-Latin text: words are split on spaces.
 */
function ner_michoel_written_first_line( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}

	$text = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );

	foreach ( preg_split( '/\R/u', $text ) as $line ) {
		$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
		if ( '' !== $line ) {
			// Always ends with an ellipsis, so the card reads as the start of a longer
			// text. Skip adding a second one when the line already ends with one itself
			// (a hand-written excerpt, say). '…' is a fixed 3-byte UTF-8 sequence, so a
			// plain byte-wise substr() compares it correctly without needing mbstring.
			$trimmed = rtrim( wp_trim_words( $line, 18, '' ), " \t.,;:" );
			return ( '…' === substr( $trimmed, -3 ) ) ? $trimmed : $trimmed . '…';
		}
	}

	return '';
}
