<?php
/**
 * "For you": a signed-in listener's own shelf.
 *
 * Two lists, from ner-michoel-core's user library (user-library.php,
 * autoplay.php):
 *
 *   - Continue: each series they've been listening to, with the shiur they last
 *     played in it. Play picks up from that shiur and runs through the rest of
 *     the series.
 *   - Picked for you: shiurim by the speakers and in the series they listen to
 *     and like, leaving out what they've heard. With no listening or likes yet
 *     it's simply the newest shiurim.
 *
 * It appears on the Shiurim home in every layout, as a page of its own
 * (?sh_view=foryou, linked from the Shiurim navigation), and on both homepage
 * designs. Nothing is printed for a visitor who isn't signed in, so the public
 * pages are the same as before. Every call into the core plugin is guarded, so a
 * theme running ahead of an older plugin renders as it did.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Whether, where, and for whom.
 * ------------------------------------------------------------------ */

/**
 * True for a signed-in listener, when the core plugin has the library functions.
 */
function ner_michoel_for_you_available() {
	return is_user_logged_in()
		&& post_type_exists( 'shiur' )
		&& function_exists( 'ner_michoel_continue_series_for_user' )
		&& function_exists( 'ner_michoel_get_suggested_for_user' );
}

/**
 * The For you page, on the Shiurim home ('' without a Shiurim archive).
 */
function ner_michoel_for_you_url() {
	$archive = post_type_exists( 'shiur' ) ? get_post_type_archive_link( 'shiur' ) : '';
	return $archive ? add_query_arg( 'sh_view', 'foryou', $archive ) : '';
}

/**
 * True when the request is for the For you page (?sh_view=foryou).
 */
function ner_michoel_is_for_you_view() {
	return isset( $_GET['sh_view'] ) && 'foryou' === $_GET['sh_view']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only display choice.
}

/**
 * Its stylesheet, for any page a signed-in listener might land on or be swapped
 * onto by the page router (nm-router.js), which doesn't load a page's styles.
 * After the layouts' own (priority 21 to 23), so it can adjust them.
 */
function ner_michoel_enqueue_for_you_assets() {
	if ( ! ner_michoel_for_you_available() ) {
		return;
	}
	wp_enqueue_style( 'ner-michoel-for-you', NER_MICHOEL_URI . '/assets/css/for-you.css', array( 'ner-michoel-custom' ), NER_MICHOEL_VERSION );
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_for_you_assets', 24 );

/* ------------------------------------------------------------------
 * The data.
 * ------------------------------------------------------------------ */

/**
 * The name to greet a listener by: the first word of the name they signed up
 * with, or the first two when it starts with a title ("Rabbi Klein").
 */
function ner_michoel_for_you_first_name() {
	$full  = trim( (string) wp_get_current_user()->first_name );
	$words = preg_split( '/\s+/u', $full, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $words ) {
		return '';
	}
	$titles = array( 'rabbi', 'rav', 'harav', 'reb', 'mr', 'mr.', 'mrs', 'mrs.', 'dr', 'dr.' );
	$first  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $words[0], 'UTF-8' ) : strtolower( $words[0] );
	return ( in_array( $first, $titles, true ) && isset( $words[1] ) ) ? $words[0] . ' ' . $words[1] : $words[0];
}

/**
 * Everything the shelf shows, gathered once per request:
 *
 *   name      the listener's first name ('' if they gave none)
 *   personal  true once they've played or liked something
 *   continue  per series: term, shiur (last played), name, link, cover,
 *             left_off (its title), track (the first track to play inside the tap,
 *             may be empty), queue_url (the rest of the series, loaded on press)
 *   picks     shiur posts
 *   url       the For you page
 */
function ner_michoel_for_you_data() {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}

	$data = array(
		'name'     => '',
		'personal' => false,
		'continue' => array(),
		'picks'    => array(),
		'url'      => ner_michoel_for_you_url(),
	);
	if ( ! ner_michoel_for_you_available() ) {
		return $data;
	}

	$user_id      = get_current_user_id();
	$data['name'] = ner_michoel_for_you_first_name();

	foreach ( ner_michoel_continue_series_for_user( $user_id, 6 ) as $item ) {
		$term  = $item['term'];
		$shiur = $item['shiur'];
		$link  = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}

		if ( function_exists( 'ner_michoel_queue_url' ) ) {
			// Its own track now, the rest of its series when the button is pressed.
			// A last-played shiur with no audio of its own (a video) gets no button:
			// the card still links to the series.
			$track     = ner_michoel_build_track_queue( array( $shiur ) );
			$queue_url = $track ? ner_michoel_queue_url(
				array(
					'shiur' => $shiur->ID,
					'mode'  => 'series',
				)
			) : '';
		} else {
			$track     = ner_michoel_build_track_queue( ner_michoel_series_rest_for_shiur( $shiur ) );
			$queue_url = '';
		}

		$data['continue'][] = array(
			'term'      => $term,
			'shiur'     => $shiur,
			'name'      => $term->name,
			'link'      => $link,
			'cover'     => function_exists( 'ner_michoel_get_series_cover_url' ) ? (string) ner_michoel_get_series_cover_url( $term->term_id ) : '',
			'left_off'  => get_the_title( $shiur ),
			'track'     => $track,
			'queue_url' => $queue_url,
		);
	}

	foreach ( ner_michoel_get_suggested_for_user( $user_id, 12 ) as $post ) {
		if ( $post instanceof WP_Post ) {
			$data['picks'][] = $post;
		}
	}

	$has_history      = function_exists( 'ner_michoel_get_user_history' ) && (bool) ner_michoel_get_user_history( $user_id, 1 );
	$has_saved        = function_exists( 'ner_michoel_get_user_saved' ) && (bool) ner_michoel_get_user_saved( $user_id, 'shiur', 1 );
	$data['personal'] = (bool) $data['continue'] || $has_history || $has_saved;

	return $data;
}

/**
 * The shiurim a listener has liked, newest first (published shiurim only).
 */
function ner_michoel_for_you_liked( $limit = 8 ) {
	if ( ! ner_michoel_for_you_available() || ! function_exists( 'ner_michoel_get_user_saved' ) ) {
		return array();
	}
	$out = array();
	foreach ( ner_michoel_get_user_saved( get_current_user_id(), 'shiur', (int) $limit ) as $row ) {
		$post = get_post( (int) $row->post_id );
		if ( $post && 'shiur' === $post->post_type && 'publish' === $post->post_status ) {
			$out[] = $post;
		}
	}
	return $out;
}

/**
 * Up to two initials for a series' tile: the first letters of the first two
 * words ("Bava Kamma" gives "BK").
 */
function ner_michoel_for_you_initials( $text ) {
	$text  = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
	$words = preg_split( '/[\s\-\/·]+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	$out   = '';
	foreach ( array_slice( (array) $words, 0, 2 ) as $word ) {
		$out .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1, 'UTF-8' ) : substr( $word, 0, 1 );
	}
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out, 'UTF-8' ) : strtoupper( $out );
}

/**
 * "Welcome back, Dovid" (or "Welcome, Dovid" before they have any listening).
 */
function ner_michoel_for_you_greeting() {
	$data = ner_michoel_for_you_data();
	if ( $data['personal'] ) {
		/* translators: %s: the listener's first name */
		return $data['name'] ? sprintf( __( 'Welcome back, %s', 'ner-michoel-child' ), $data['name'] ) : __( 'Welcome back', 'ner-michoel-child' );
	}
	/* translators: %s: the listener's first name */
	return $data['name'] ? sprintf( __( 'Welcome, %s', 'ner-michoel-child' ), $data['name'] ) : __( 'Welcome', 'ner-michoel-child' );
}

/**
 * One line under the heading: what the shelf is.
 */
function ner_michoel_for_you_lede() {
	return ner_michoel_for_you_data()['personal']
		? __( 'Pick up where you left off, and find more from the speakers and series you listen to.', 'ner-michoel-child' )
		: __( 'Play or like a few shiurim and this shelf will start to fit you. Until then, here is what is newest.', 'ner-michoel-child' );
}

/**
 * What a pick's Play button needs: its own track now, and the rest of its series
 * when pressed. Nothing for a video, or a shiur with no audio.
 */
function ner_michoel_for_you_play( $post ) {
	$none = array(
		'queue'     => array(),
		'queue_url' => '',
	);
	if ( function_exists( 'ner_michoel_shiur_is_video' ) && ner_michoel_shiur_is_video( $post->ID ) ) {
		return $none;
	}
	$own = ner_michoel_build_track_queue( array( $post ) );
	if ( ! $own ) {
		return $none;
	}
	if ( function_exists( 'ner_michoel_queue_url' ) ) {
		return array(
			'queue'     => $own,
			'queue_url' => ner_michoel_queue_url(
				array(
					'shiur' => $post->ID,
					'mode'  => 'series',
				)
			),
		);
	}
	return array(
		'queue'     => ner_michoel_build_track_queue( ner_michoel_series_rest_for_shiur( $post ) ),
		'queue_url' => '',
	);
}

/* ------------------------------------------------------------------
 * Modern, 24Six, and the pages that use Modern's markup (also Studio's
 * For you page): the same cards as the Series and Recent views.
 * ------------------------------------------------------------------ */

/**
 * A series the listener is part-way through, as a series card.
 */
function ner_michoel_for_you_continue_card( $item ) {
	ner_michoel_render_media_card(
		array(
			'title'     => $item['name'],
			/* translators: %s: title of the shiur the listener last played */
			'subtitle'  => sprintf( __( 'Left off at: %s', 'ner-michoel-child' ), $item['left_off'] ),
			'image'     => $item['cover'],
			'link'      => $item['link'],
			'queue'     => $item['track'],
			'queue_url' => $item['queue_url'],
			'variant'   => 'series',
			'kicker'    => __( 'Continue', 'ner-michoel-child' ),
		)
	);
}

/**
 * A suggested shiur, as the card Recent uses (with its heart).
 */
function ner_michoel_for_you_shiur_card( $post ) {
	$speakers = get_the_terms( $post, 'speaker' );
	ner_michoel_render_media_card(
		array_merge(
			array(
				'title'    => get_the_title( $post ),
				'subtitle' => ( $speakers && ! is_wp_error( $speakers ) ) ? $speakers[0]->name : '',
				'image'    => get_the_post_thumbnail_url( $post, 'medium' ),
				'link'     => get_permalink( $post ),
				'save_id'  => $post->ID,
			),
			ner_michoel_for_you_play( $post )
		)
	);
}

/**
 * The shelf on the Shiurim home in Modern and 24Six: the series to continue,
 * then picks, in one row that scrolls sideways (the same row 24Six uses for
 * its series). A grid would leave a lone card on a second row at some widths;
 * a row fits every screen. "See all" opens the page.
 */
function ner_michoel_render_for_you_shelf() {
	if ( ! ner_michoel_for_you_available() ) {
		return;
	}
	$data = ner_michoel_for_you_data();
	if ( ! $data['continue'] && ! $data['picks'] ) {
		return;
	}

	$continue = array_slice( $data['continue'], 0, 6 );
	$picks    = array_slice( $data['picks'], 0, max( 0, 12 - count( $continue ) ) );
	?>
	<section class="sh-section sh-foryou" id="for-you" aria-labelledby="sh-foryou-title">
		<div class="sh-foryou__bar">
			<div class="sh-foryou__titles">
				<h2 class="sh-section__title" id="sh-foryou-title"><?php esc_html_e( 'For you', 'ner-michoel-child' ); ?></h2>
				<p class="sh-foryou__lede"><?php echo esc_html( ner_michoel_for_you_greeting() . '. ' . ner_michoel_for_you_lede() ); ?></p>
			</div>
			<a class="sh-foryou__more" href="<?php echo esc_url( $data['url'] ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</div>
		<?php ner_michoel_render_card_collection_start( true ); ?>
			<?php foreach ( $continue as $item ) : ?>
				<?php ner_michoel_for_you_continue_card( $item ); ?>
			<?php endforeach; ?>
			<?php foreach ( $picks as $post ) : ?>
				<?php ner_michoel_for_you_shiur_card( $post ); ?>
			<?php endforeach; ?>
		<?php ner_michoel_render_card_collection_end( true ); ?>
	</section>
	<?php
}

/**
 * The For you page (?sh_view=foryou) in Modern, 24Six and Studio: the series to
 * continue, the picks as a track list with Play All, and what they've liked.
 */
function ner_michoel_render_for_you_page( $is_carousel ) {
	if ( ! is_user_logged_in() ) {
		?>
		<p class="sh-empty">
			<?php esc_html_e( 'Sign in to see shiurim picked for you.', 'ner-michoel-child' ); ?>
			<a href="<?php echo esc_url( home_url( '/account/' ) ); ?>"><?php esc_html_e( 'Sign in', 'ner-michoel-child' ); ?></a>
		</p>
		<?php
		return;
	}
	if ( ! ner_michoel_for_you_available() ) {
		echo '<p class="sh-empty">' . esc_html__( 'Nothing here yet.', 'ner-michoel-child' ) . '</p>';
		return;
	}

	$data  = ner_michoel_for_you_data();
	$liked = ner_michoel_for_you_liked( 8 );
	?>
	<p class="sh-foryou__lede sh-foryou__lede--page"><?php echo esc_html( ner_michoel_for_you_greeting() . '. ' . ner_michoel_for_you_lede() ); ?></p>

	<?php if ( $data['continue'] ) : ?>
		<section class="sh-section sh-foryou" aria-labelledby="sh-foryou-continue">
			<h2 class="sh-section__title" id="sh-foryou-continue"><?php esc_html_e( 'Continue your series', 'ner-michoel-child' ); ?></h2>
			<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
				<?php foreach ( $data['continue'] as $item ) : ?>
					<?php ner_michoel_for_you_continue_card( $item ); ?>
				<?php endforeach; ?>
			<?php ner_michoel_render_card_collection_end( $is_carousel ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $data['picks'] ) : ?>
		<?php $queue = ner_michoel_build_track_queue( $data['picks'] ); ?>
		<section class="sh-section sh-foryou" aria-labelledby="sh-foryou-picks">
			<div class="sh-foryou__bar">
				<h2 class="sh-section__title" id="sh-foryou-picks"><?php esc_html_e( 'Picked for you', 'ner-michoel-child' ); ?></h2>
				<?php if ( $queue ) : ?>
					<button type="button" class="sh-play-all" data-play-queue="<?php echo esc_attr( wp_json_encode( $queue ) ); ?>" data-play-index="0"><?php echo ner_michoel_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?> <?php esc_html_e( 'Play All', 'ner-michoel-child' ); ?></button>
				<?php endif; ?>
			</div>
			<?php ner_michoel_render_tracklist( $data['picks'], true ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $liked ) : ?>
		<section class="sh-section sh-foryou" aria-labelledby="sh-foryou-liked">
			<h2 class="sh-section__title" id="sh-foryou-liked"><?php esc_html_e( 'Liked', 'ner-michoel-child' ); ?></h2>
			<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
				<?php foreach ( $liked as $post ) : ?>
					<?php ner_michoel_for_you_shiur_card( $post ); ?>
				<?php endforeach; ?>
			<?php ner_michoel_render_card_collection_end( $is_carousel ); ?>
		</section>
	<?php endif; ?>

	<?php if ( ! $data['continue'] && ! $data['picks'] && ! $liked ) : ?>
		<p class="sh-empty"><?php esc_html_e( 'Nothing here yet. Play or like a shiur and it will show up here.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
	<?php
}

/* ------------------------------------------------------------------
 * Studio.
 * ------------------------------------------------------------------ */

/**
 * A series to continue, as the card data Studio's series cards take (see
 * ner_michoel_studio_series_rows()): the drawn cover when the series has no
 * picture, and "Left off at" in place of the shiur count.
 */
function ner_michoel_for_you_studio_card( $item ) {
	$term  = $item['term'];
	$parts = ner_michoel_studio_name_parts( $term->name );
	$cover = count( $parts ) > 1 ? array_slice( $parts, 1 ) : $parts;
	$main  = $cover ? $cover[0] : html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );

	return array(
		'term'      => $term,
		'title'     => ner_michoel_studio_series_title( $term->name ),
		'kicker'    => __( 'Continue', 'ner-michoel-child' ),
		'count'     => 0,
		/* translators: %s: title of the shiur the listener last played */
		'meta'      => sprintf( __( 'Left off at: %s', 'ner-michoel-child' ), $item['left_off'] ),
		'image'     => $item['cover'],
		'cover'     => $main,
		'cover_sub' => implode( ' · ', array_slice( $cover, 1 ) ),
		'tone'      => ner_michoel_studio_tone( $main ),
		'track'     => $item['track'],
		'queue_url' => $item['queue_url'],
	);
}

/**
 * The panel under Studio's hero: a row of series to continue, then picks as the
 * same rows the New shiurim list uses.
 */
function ner_michoel_render_for_you_studio() {
	if ( ! ner_michoel_for_you_available() || ! function_exists( 'ner_michoel_studio_series_card' ) ) {
		return;
	}
	$data     = ner_michoel_for_you_data();
	$continue = array_slice( $data['continue'], 0, 6 );
	$picks    = array_slice( $data['picks'], 0, 6 );
	if ( ! $continue && ! $picks ) {
		return;
	}
	?>
	<section class="st-foryou" id="for-you" aria-labelledby="st-foryou-title">
		<header class="st-foryou__head">
			<div>
				<p class="st-kicker st-kicker--gold"><?php echo esc_html( ner_michoel_for_you_greeting() ); ?></p>
				<h2 class="st-foryou__title" id="st-foryou-title"><?php esc_html_e( 'For you', 'ner-michoel-child' ); ?></h2>
				<p class="st-foryou__sub"><?php echo esc_html( ner_michoel_for_you_lede() ); ?></p>
			</div>
			<a class="st-more" href="<?php echo esc_url( $data['url'] ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</header>

		<?php if ( $continue ) : ?>
			<div class="st-foryou__group">
				<h3 class="st-foryou__label"><?php esc_html_e( 'Continue your series', 'ner-michoel-child' ); ?></h3>
				<div class="st-foryou__track">
					<?php foreach ( $continue as $item ) : ?>
						<?php ner_michoel_studio_series_card( ner_michoel_for_you_studio_card( $item ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $picks ) : ?>
			<div class="st-foryou__group">
				<h3 class="st-foryou__label"><?php esc_html_e( 'Picked for you', 'ner-michoel-child' ); ?></h3>
				<ul class="st-list st-list--grid">
					<?php foreach ( $picks as $post ) : ?>
						<?php ner_michoel_studio_shiur_row( ner_michoel_studio_shiur( $post ) ); ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/* ------------------------------------------------------------------
 * Classic: plain links, since Classic has no player.
 * ------------------------------------------------------------------ */

/**
 * A short list above the Classic library, or all of it on the For you page
 * ($full).
 */
function ner_michoel_render_for_you_classic( $full = false ) {
	if ( ! ner_michoel_for_you_available() ) {
		return;
	}
	$data     = ner_michoel_for_you_data();
	$continue = array_slice( $data['continue'], 0, $full ? 6 : 3 );
	$picks    = array_slice( $data['picks'], 0, $full ? 12 : 5 );
	if ( ! $continue && ! $picks ) {
		return;
	}
	?>
	<section class="sh-classic-foryou" aria-labelledby="sh-classic-foryou-title">
		<div class="sh-classic-foryou__head">
			<h2 id="sh-classic-foryou-title"><?php esc_html_e( 'For you', 'ner-michoel-child' ); ?></h2>
			<?php if ( ! $full ) : ?>
				<a href="<?php echo esc_url( $data['url'] ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> &rarr;</a>
			<?php endif; ?>
		</div>
		<p class="sh-classic-foryou__lede"><?php echo esc_html( ner_michoel_for_you_greeting() . '. ' . ner_michoel_for_you_lede() ); ?></p>

		<?php if ( $continue ) : ?>
			<h3><?php esc_html_e( 'Continue your series', 'ner-michoel-child' ); ?></h3>
			<ul>
				<?php foreach ( $continue as $item ) : ?>
					<li>
						<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
						&mdash;
						<?php esc_html_e( 'left off at', 'ner-michoel-child' ); ?>
						<a href="<?php echo esc_url( get_permalink( $item['shiur'] ) ); ?>"><?php echo esc_html( $item['left_off'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $picks ) : ?>
			<h3><?php esc_html_e( 'Picked for you', 'ner-michoel-child' ); ?></h3>
			<ul>
				<?php foreach ( $picks as $post ) : ?>
					<?php
					$speakers = get_the_terms( $post, 'speaker' );
					$speaker  = ( $speakers && ! is_wp_error( $speakers ) ) ? $speakers[0]->name : '';
					?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<?php if ( $speaker ) : ?>
							<span class="sh-classic-foryou__meta"><?php echo esc_html( $speaker ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * The For you page in Classic: the whole list, or why there isn't one.
 */
function ner_michoel_render_for_you_classic_page() {
	if ( ! is_user_logged_in() ) {
		?>
		<p class="sh-empty">
			<?php esc_html_e( 'Sign in to see shiurim picked for you.', 'ner-michoel-child' ); ?>
			<a href="<?php echo esc_url( home_url( '/account/' ) ); ?>"><?php esc_html_e( 'Sign in', 'ner-michoel-child' ); ?></a>
		</p>
		<?php
		return;
	}
	$data = ner_michoel_for_you_data();
	if ( ! $data['continue'] && ! $data['picks'] ) {
		echo '<p class="sh-empty">' . esc_html__( 'Nothing here yet. Play or like a shiur and it will show up here.', 'ner-michoel-child' ) . '</p>';
		return;
	}
	ner_michoel_render_for_you_classic( true );
}

/* ------------------------------------------------------------------
 * The homepage.
 * ------------------------------------------------------------------ */

/**
 * The New homepage's panel, between the hero and the newest shiurim: the series
 * to continue as cards with a Continue button, then a few picks as the list's own rows.
 */
function ner_michoel_render_for_you_home_new() {
	if ( ! ner_michoel_for_you_available() ) {
		return;
	}
	$data     = ner_michoel_for_you_data();
	$continue = array_slice( $data['continue'], 0, 3 );
	$picks    = array_slice( $data['picks'], 0, 4 );
	if ( ! $continue && ! $picks ) {
		return;
	}
	$arrow = function_exists( 'ner_michoel_line_icon' ) ? ner_michoel_line_icon( 'arrow-right' ) : '';
	?>
	<section class="hn-panel hn-foryou" aria-labelledby="hn-foryou-title">
		<header class="hn-panel__head">
			<div class="hn-panel__titles">
				<p class="hn-kicker"><?php echo esc_html( ner_michoel_for_you_greeting() ); ?></p>
				<h2 class="hn-panel__title" id="hn-foryou-title"><?php esc_html_e( 'For you', 'ner-michoel-child' ); ?></h2>
			</div>
			<a class="hn-more" href="<?php echo esc_url( $data['url'] ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
		</header>

		<?php if ( $continue ) : ?>
			<ul class="hn-continue">
				<?php foreach ( $continue as $item ) : ?>
					<?php
					$name  = $item['term']->name;
					$parts = function_exists( 'ner_michoel_studio_name_parts' ) ? ner_michoel_studio_name_parts( $name ) : array();
					$short = $parts ? implode( ' · ', count( $parts ) > 1 ? array_slice( $parts, 1 ) : $parts ) : $name;
					$main  = $parts ? ( count( $parts ) > 1 ? $parts[1] : $parts[0] ) : $name;
					?>
					<li class="hn-continue__item">
						<a class="hn-continue__link" href="<?php echo esc_url( $item['link'] ); ?>">
							<span class="hn-continue__art" style="--hue: <?php echo (int) ner_michoel_home_hue( $name ); ?>;">
								<?php if ( $item['cover'] ) : ?>
									<img src="<?php echo esc_url( $item['cover'] ); ?>" alt="" loading="lazy" decoding="async" width="56" height="56" />
								<?php else : ?>
									<span class="hn-continue__initial" aria-hidden="true"><?php echo esc_html( ner_michoel_for_you_initials( $main ) ); ?></span>
								<?php endif; ?>
							</span>
							<span class="hn-continue__text">
								<span class="hn-continue__kicker"><?php esc_html_e( 'Continue series', 'ner-michoel-child' ); ?></span>
								<span class="hn-continue__title"><?php echo esc_html( $short ); ?></span>
								<span class="hn-continue__meta">
									<?php
									/* translators: %s: title of the shiur the listener last played */
									echo esc_html( sprintf( __( 'Left off at: %s', 'ner-michoel-child' ), $item['left_off'] ) );
									?>
								</span>
							</span>
						</a>
						<?php if ( $item['track'] || $item['queue_url'] ) : ?>
							<button
								type="button"
								class="hn-continue__play"
								<?php if ( $item['track'] ) : ?>data-play-queue="<?php echo esc_attr( wp_json_encode( $item['track'] ) ); ?>"<?php endif; ?>
								<?php if ( $item['queue_url'] ) : ?>data-queue-url="<?php echo esc_url( $item['queue_url'] ); ?>"<?php endif; ?>
								data-play-index="0"
							>
								<?php echo ner_michoel_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
								<span><?php esc_html_e( 'Continue', 'ner-michoel-child' ); ?></span>
							</button>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $picks ) : ?>
			<h3 class="hn-foryou__label"><?php esc_html_e( 'Picked for you', 'ner-michoel-child' ); ?></h3>
			<ol class="hn-list">
				<?php foreach ( $picks as $post ) : ?>
					<?php $row = ner_michoel_home_shiur_row( $post ); ?>
					<li class="hn-row">
						<a class="hn-row__link" href="<?php echo esc_url( $row['link'] ); ?>">
							<span class="hn-row__art"<?php echo $row['photo'] ? '' : ' style="--hue: ' . (int) $row['hue'] . '"'; ?>>
								<?php if ( $row['photo'] ) : ?>
									<img src="<?php echo esc_url( $row['photo'] ); ?>" alt="" loading="lazy" decoding="async" width="56" height="56" />
								<?php else : ?>
									<span class="hn-row__initials" aria-hidden="true"><?php echo esc_html( $row['initials'] ); ?></span>
								<?php endif; ?>
								<span class="hn-row__play" aria-hidden="true"><?php echo ner_michoel_icon( $row['is_video'] ? 'video' : 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
							</span>
							<span class="hn-row__body">
								<span class="hn-row__title"><?php echo esc_html( $row['title'] ); ?></span>
								<span class="hn-row__meta">
									<?php if ( $row['speaker'] ) : ?>
										<span><?php echo esc_html( $row['speaker'] ); ?></span>
									<?php endif; ?>
									<?php if ( $row['series'] ) : ?>
										<span><?php echo esc_html( $row['series'] ); ?></span>
									<?php endif; ?>
								</span>
							</span>
							<span class="hn-row__side">
								<?php if ( $row['is_new'] ) : ?>
									<span class="hn-pill hn-pill--new"><?php esc_html_e( 'New', 'ner-michoel-child' ); ?></span>
								<?php elseif ( $row['is_video'] ) : ?>
									<span class="hn-pill"><?php esc_html_e( 'Video', 'ner-michoel-child' ); ?></span>
								<?php endif; ?>
								<span class="hn-row__facts">
									<?php if ( $row['when'] ) : ?>
										<time datetime="<?php echo esc_attr( $row['iso'] ); ?>"><?php echo esc_html( $row['when'] ); ?></time>
									<?php endif; ?>
									<?php if ( $row['length'] ) : ?>
										<span><?php echo esc_html( $row['length'] ); ?></span>
									<?php endif; ?>
								</span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * The Current homepage's section, in its own style: the Account page's Continue
 * cards, then the same cards its "Recent Shiurim" uses.
 */
function ner_michoel_render_for_you_home_current() {
	if ( ! ner_michoel_for_you_available() ) {
		return;
	}
	$data     = ner_michoel_for_you_data();
	$continue = array_slice( $data['continue'], 0, 3 );
	$picks    = array_slice( $data['picks'], 0, 4 );
	if ( ! $continue && ! $picks ) {
		return;
	}
	?>
	<section class="nm-home-section nm-home-foryou" aria-labelledby="nm-foryou-title">
		<div class="nm-home-section__head">
			<h2 id="nm-foryou-title"><?php esc_html_e( 'For you', 'ner-michoel-child' ); ?></h2>
			<a class="nm-home-more" href="<?php echo esc_url( $data['url'] ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> &rarr;</a>
		</div>
		<p class="nm-home-foryou__lede"><?php echo esc_html( ner_michoel_for_you_greeting() . '. ' . ner_michoel_for_you_lede() ); ?></p>
		<?php if ( $continue ) : ?>
			<div class="nm-continue-grid">
				<?php foreach ( $continue as $item ) : ?>
					<?php ner_michoel_render_continue_card( $item['term'], $item['shiur'] ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $picks ) : ?>
			<div class="nm-home-shiur-grid">
				<?php foreach ( $picks as $post ) : ?>
					<?php ner_michoel_render_library_card( $post->ID ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}
