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
	return is_post_type_archive( 'shiur' ) || is_tax( array( 'speaker', 'series' ) ) || is_singular( 'shiur' );
}

/**
 * True on any page that carries the Modern/Classic layout toggle:
 * Shiurim, Galleries, or the News & Events page. Mazal Tov has no
 * context of its own — it's a section inside the News & Events page,
 * not a separate archive.
 */
function ner_michoel_is_app_context() {
	return ner_michoel_is_shiurim_context()
		|| is_post_type_archive( 'gallery' ) || is_tax( 'gallery_type' ) || is_singular( 'gallery' )
		|| is_page_template( 'page-templates/news-events.php' );
}

/**
 * Which layout to render:
 * - 'stream'  — the Spotify/app-style visual language (grid browsing)
 * - 'classic' — the plain filterable/sortable library style
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
	return in_array( $layout, array( 'classic', '24six' ), true ) ? $layout : 'stream';
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
 * Top-right switch between the three layouts. Flipping it sets the
 * layout cookie and reloads, since each layout is a genuinely
 * different template (not a client-side CSS skin) — see the branches
 * at the top of each app-context template.
 */
function ner_michoel_render_layout_toggle() {
	if ( ! ner_michoel_is_app_context() ) {
		return;
	}
	$layout = ner_michoel_get_layout();
	?>
	<div class="sh-layout-toggle" role="group" aria-label="<?php esc_attr_e( 'Layout', 'ner-michoel-child' ); ?>">
		<button type="button" class="sh-layout-toggle__option<?php echo 'stream' === $layout ? ' is-active' : ''; ?>" data-layout="stream"><?php esc_html_e( 'Modern', 'ner-michoel-child' ); ?></button>
		<button type="button" class="sh-layout-toggle__option<?php echo 'classic' === $layout ? ' is-active' : ''; ?>" data-layout="classic"><?php esc_html_e( 'Classic', 'ner-michoel-child' ); ?></button>
		<button type="button" class="sh-layout-toggle__option<?php echo '24six' === $layout ? ' is-active' : ''; ?>" data-layout="24six"><?php esc_html_e( '24Six', 'ner-michoel-child' ); ?></button>
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
	);
	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

/**
 * Fallback "cover art" for a speaker/series/shiur with no image set —
 * a tile showing its first letter, styled entirely in CSS.
 */
function ner_michoel_placeholder_art( $label = '' ) {
	$initial = $label ? mb_substr( trim( $label ), 0, 1 ) : '♪';
	return '<div class="sh-placeholder-art" aria-hidden="true">' . esc_html( $initial ) . '</div>';
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
 * A clickable card for a speaker or series — cover art, title,
 * subtitle, and (if a track queue is supplied) a hover play button
 * that starts playback without leaving the grid.
 */
function ner_michoel_render_media_card( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'    => '',
			'subtitle' => '',
			'image'    => '',
			'link'     => '',
			'queue'    => array(),
			'round'    => false,
		)
	);
	?>
	<a class="sh-card<?php echo $args['round'] ? ' sh-card--round' : ''; ?>" href="<?php echo esc_url( $args['link'] ); ?>">
		<div class="sh-card__art">
			<?php if ( $args['image'] ) : ?>
				<img src="<?php echo esc_url( $args['image'] ); ?>" alt="" loading="lazy" />
			<?php else : ?>
				<?php echo ner_michoel_placeholder_art( $args['title'] ); ?>
			<?php endif; ?>
			<?php if ( ! empty( $args['queue'] ) ) : ?>
				<button
					type="button"
					class="sh-card__play"
					aria-label="<?php esc_attr_e( 'Play', 'ner-michoel-child' ); ?>"
					data-play-queue="<?php echo esc_attr( wp_json_encode( $args['queue'] ) ); ?>"
					data-play-index="0"
				><?php echo ner_michoel_icon( 'play' ); ?></button>
			<?php endif; ?>
		</div>
		<div class="sh-card__title"><?php echo esc_html( $args['title'] ); ?></div>
		<?php if ( $args['subtitle'] ) : ?>
			<div class="sh-card__subtitle"><?php echo esc_html( $args['subtitle'] ); ?></div>
		<?php endif; ?>
	</a>
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
				<div class="sh-track__title"><?php echo esc_html( get_the_title( $shiur ) ); ?></div>
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
			<div class="sh-track__title"><?php echo esc_html( get_the_title( $shiur ) ); ?></div>
			<?php if ( $show_speaker && $speaker_name ) : ?>
				<div class="sh-track__speaker"><?php echo esc_html( $speaker_name ); ?></div>
			<?php endif; ?>
		</div>
		<div class="sh-track__duration"><?php echo esc_html( ner_michoel_get_shiur_duration( $shiur->ID ) ); ?></div>
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
 */
function ner_michoel_render_player_bar() {
	if ( ! ner_michoel_is_shiurim_context() || 'stream' !== ner_michoel_get_layout() ) {
		return;
	}
	?>
	<div class="sh-player" id="sh-player" hidden>
		<audio id="sh-audio" preload="metadata"></audio>
		<div class="sh-player__now">
			<div class="sh-player__cover" id="sh-player-cover"></div>
			<div class="sh-player__meta">
				<div class="sh-player__title" id="sh-player-title"></div>
				<div class="sh-player__speaker" id="sh-player-speaker"></div>
			</div>
		</div>
		<div class="sh-player__center">
			<div class="sh-player__buttons">
				<button type="button" class="sh-player__prev" id="sh-player-prev" aria-label="<?php esc_attr_e( 'Previous', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'prev' ); ?></button>
				<button type="button" class="sh-player__toggle" id="sh-player-toggle" aria-label="<?php esc_attr_e( 'Play', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'play' ); ?></button>
				<button type="button" class="sh-player__next" id="sh-player-next" aria-label="<?php esc_attr_e( 'Next', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'next' ); ?></button>
			</div>
			<div class="sh-player__progress">
				<span class="sh-player__time" id="sh-player-current">0:00</span>
				<input type="range" class="sh-player__seek" id="sh-player-seek" min="0" max="1000" value="0" aria-label="<?php esc_attr_e( 'Seek', 'ner-michoel-child' ); ?>" />
				<span class="sh-player__time" id="sh-player-duration">0:00</span>
			</div>
		</div>
		<div class="sh-player__volume">
			<button type="button" class="sh-player__speed" id="sh-player-speed" aria-label="<?php esc_attr_e( 'Playback speed', 'ner-michoel-child' ); ?>">1x</button>
			<?php echo ner_michoel_icon( 'volume' ); ?>
			<input type="range" class="sh-player__volume-range" id="sh-player-volume" min="0" max="1" step="0.01" value="1" aria-label="<?php esc_attr_e( 'Volume', 'ner-michoel-child' ); ?>" />
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'ner_michoel_render_player_bar' );
