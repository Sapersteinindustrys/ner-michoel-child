<?php
/**
 * The November 2026 revamp list, the parts that are the theme's own:
 *
 *   - assets/css/revamp.css: the homepage banner and alumni box, the search page, the search
 *     bars on the shiurim pages, the gallery pages, and videos that fit the screen.
 *   - "Audio Shiurim" under Shiurim in the header menu (Shiurim itself still opens the audio).
 *   - One entry per shiur: a shiur that comes as audio and video (or written) is listed once,
 *     by its audio, with the formats it comes in shown on it (ner_michoel_one_per_shiur()).
 *   - The search bar with filters on the shiurim pages (ner_michoel_render_scoped_search()):
 *     a speaker's page searches that speaker, a series page that series, and so on.
 *   - The old News & Events page is gone: its address goes to the homepage.
 *   - "Load more" under the long lists (assets/js/list-more.js), and a Sort choice on the search bars.
 *   - Where the homepage banner and the alumni box show, and the small Zoom line.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The revamp's stylesheet. Site-wide, like the other component sheets, so the page router
 * (nm-router.js) has it ready on whichever page it swaps in.
 */
function ner_michoel_enqueue_revamp_styles() {
	wp_enqueue_style( 'ner-michoel-revamp', NER_MICHOEL_URI . '/assets/css/revamp.css', array( 'ner-michoel-custom' ), NER_MICHOEL_VERSION );
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_revamp_styles', 30 );

/**
 * "Load more" under the long lists on the Shiurim pages (assets/js/list-more.js). Site-wide,
 * for the page router, and small.
 */
function ner_michoel_enqueue_list_more() {
	wp_enqueue_script( 'ner-michoel-list-more', NER_MICHOEL_URI . '/assets/js/list-more.js', array(), NER_MICHOEL_VERSION, true );
	wp_add_inline_script(
		'ner-michoel-list-more',
		'window.nmListMore = ' . wp_json_encode(
			array(
				/* translators: %s: how many more shiurim there are */
				'moreN'   => __( 'Load more (%s more)', 'ner-michoel-child' ),
				'more'    => __( 'Load more', 'ner-michoel-child' ),
				'loading' => __( 'Loading…', 'ner-michoel-child' ),
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_list_more', 30 );

/* ------------------------------------------------------------------
 * News & Events is gone.
 * ------------------------------------------------------------------ */

/**
 * The News & Events page was taken off the site. Anyone with an old link lands on the
 * homepage (302, so the address can be used again later).
 */
function ner_michoel_retire_news_page() {
	if ( is_page_template( 'page-templates/news-events.php' ) ) {
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}
}
add_action( 'template_redirect', 'ner_michoel_retire_news_page' );

/* ------------------------------------------------------------------
 * "Audio Shiurim" under Shiurim.
 * ------------------------------------------------------------------ */

add_filter( 'wp_nav_menu_objects', 'ner_michoel_nav_add_audio_under_shiurim', 11, 2 );

/**
 * "Audio Shiurim" as the first item under Shiurim in the header menu. Shiurim itself still
 * opens the shiurim (audio) page, so audio stays the default; the sub-item sits beside
 * "Written Shiurim" so both kinds are named. Added as the menu is printed, like the other
 * items in inc/navigation.php, and skipped when the menu already has one by hand.
 */
function ner_michoel_nav_add_audio_under_shiurim( $items, $args ) {
	if ( ! post_type_exists( 'shiur' ) || ! function_exists( 'ner_michoel_nav_is_header_menu' ) || ! ner_michoel_nav_is_header_menu( $args ) ) {
		return $items;
	}

	$shiurim_url = untrailingslashit( (string) get_post_type_archive_link( 'shiur' ) );
	$shiurim     = null;
	$position    = 0;
	$i           = 0;
	foreach ( $items as $item ) {
		if ( in_array( 'nm-menu-audio', (array) $item->classes, true ) || 0 === strcasecmp( trim( wp_strip_all_tags( $item->title ) ), 'Audio Shiurim' ) ) {
			return $items;
		}
		if ( null === $shiurim && empty( $item->menu_item_parent ) && ( ( '' !== $shiurim_url && untrailingslashit( (string) $item->url ) === $shiurim_url ) || 0 === strcasecmp( trim( $item->title ), 'Shiurim' ) ) ) {
			$shiurim  = $item;
			$position = $i;
		}
		++$i;
	}
	if ( null === $shiurim ) {
		return $items;
	}

	$is_current = is_post_type_archive( 'shiur' ) && empty( $_GET['sh_view'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.

	$child                        = clone $shiurim;
	$child->ID                    = -3;
	$child->db_id                 = -3;
	$child->menu_item_parent      = (string) $shiurim->db_id;
	$child->title                 = __( 'Audio Shiurim', 'ner-michoel-child' );
	$child->url                   = get_post_type_archive_link( 'shiur' );
	$child->object                = 'custom';
	$child->object_id             = 0;
	$child->type                  = 'custom';
	$child->type_label            = __( 'Custom Link', 'ner-michoel-child' );
	$child->menu_order            = (int) $shiurim->menu_order;
	$child->target                = '';
	$child->attr_title            = '';
	$child->description           = '';
	$child->xfn                   = '';
	$child->post_parent           = 0;
	$child->ancestors             = array();
	$child->classes               = array( 'menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'nm-menu-audio' );
	$child->current               = $is_current;
	$child->current_item_ancestor = false;
	$child->current_item_parent   = false;
	if ( $is_current ) {
		$child->classes[] = 'current-menu-item';
	}

	// Right after Shiurim, so it comes before Written Shiurim under it.
	$items = array_values( $items );
	array_splice( $items, $position + 1, 0, array( $child ) );

	// Shiurim now has something under it: give it the class Astra opens the dropdown with.
	if ( ! in_array( 'menu-item-has-children', (array) $shiurim->classes, true ) ) {
		$shiurim->classes[] = 'menu-item-has-children';
	}

	return $items;
}

/* ------------------------------------------------------------------
 * One entry per shiur.
 * ------------------------------------------------------------------ */

/**
 * A list of shiurim (and written shiurim) with each shiur listed once, however many formats
 * it comes in. Of the formats in the list, the one kept is the audio, else the video, else the
 * written, so a shiur opens on its audio by default; it is kept where the first of its formats
 * came in the list. Its page has the Listen / Watch / Read switch, and the list shows the
 * formats it comes in (ner_michoel_render_format_chips()).
 *
 * $exclude: post IDs whose whole shiur is left out (the shiur whose page this is, for "More
 * from this Speaker").
 *
 * @param WP_Post[] $posts
 * @param int[]     $exclude
 * @return WP_Post[]
 */
function ner_michoel_one_per_shiur( array $posts, array $exclude = array() ) {
	if ( ! $posts || ! function_exists( 'ner_michoel_get_shiur_formats' ) ) {
		return $posts;
	}

	$order = array( 'a' => 0, 'v' => 1, 'w' => 2 );

	// The shiur each post belongs to: the smallest ID among its formats (any member names the same group).
	$group_of = function ( $id ) {
		$formats = ner_michoel_get_shiur_formats( $id );
		return $formats ? min( array_map( 'intval', $formats ) ) : (int) $id;
	};

	$skip = array();
	foreach ( $exclude as $id ) {
		$skip[ $group_of( $id ) ] = true;
	}

	// Pass one: per shiur, the best format in the list.
	$best = array();
	foreach ( $posts as $post ) {
		$group = $group_of( $post->ID );
		$kind  = function_exists( 'ner_michoel_format_kind_of' ) ? ner_michoel_format_kind_of( $post->ID ) : 'a';
		$rank  = isset( $order[ $kind ] ) ? $order[ $kind ] : 3;
		if ( ! isset( $best[ $group ] ) || $rank < $best[ $group ][0] ) {
			$best[ $group ] = array( $rank, $post );
		}
	}

	// Pass two: in the list's order, each shiur once, as its best format.
	$out  = array();
	$seen = array();
	foreach ( $posts as $post ) {
		$group = $group_of( $post->ID );
		if ( isset( $skip[ $group ] ) || isset( $seen[ $group ] ) ) {
			continue;
		}
		$seen[ $group ] = true;
		$out[]          = $best[ $group ][1];
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Search bars on the shiurim pages.
 * ------------------------------------------------------------------ */

/**
 * The search bar with filters, for the shiurim pages: the Shiurim page, Recent, a speaker's
 * page, a series page, a topic page. It searches the cached shiur index as you type
 * (assets/js/shiur-live-search.js), and its filters (speaker, series, topic, year, audio /
 * video / written) can be used with words or on their own.
 *
 * On a speaker, series or topic page the search starts filtered to it: the speaker's filter
 * is set to that speaker (and can be changed), and so on. A series page searches the series
 * and the series inside it. While something is typed or a filter is changed, the results take
 * the place of the page's own lists (the elements marked data-scope-browse); clearing it brings
 * them back.
 *
 * $args:
 *   scope      'all', 'speaker', 'series' or 'topic'
 *   term       the WP_Term for a speaker, series or topic
 *   label      the words in the box ("Search Rabbi Klein's shiurim…")
 *   start_open true to show every shiur (newest first) at once, for the Recent page
 *
 * The search is never printed into the page (pages are cached): it is kept with the page's
 * entry in the browser's history.
 */
function ner_michoel_render_scoped_search( array $args = array() ) {
	$args  = array_merge(
		array(
			'scope'      => 'all',
			'term'       => null,
			'label'      => __( 'Search shiurim…', 'ner-michoel-child' ),
			'start_open' => false,
		),
		$args
	);
	$scope = in_array( $args['scope'], array( 'speaker', 'series', 'topic' ), true ) && $args['term'] ? $args['scope'] : 'all';

	$ids = array();
	if ( 'all' !== $scope ) {
		$ids[] = (int) $args['term']->term_id;
		if ( 'series' === $scope ) {
			$children = get_term_children( (int) $args['term']->term_id, 'series' );
			if ( ! is_wp_error( $children ) ) {
				$ids = array_merge( $ids, array_map( 'intval', $children ) );
			}
		}
	}

	$index_url = function_exists( 'ner_michoel_shiur_index_url' ) ? ner_michoel_shiur_index_url() : rest_url( 'ner-michoel/v1/shiur-index' );
	$uid       = 'nm-scope-' . wp_unique_id();
	?>
	<div class="nm-scope-search" data-scope-search
		data-scope="<?php echo esc_attr( $scope ); ?>"
		data-scope-ids="<?php echo esc_attr( implode( ',', $ids ) ); ?>"
		data-index-url="<?php echo esc_url( $index_url ); ?>"
		data-all-url="<?php echo esc_url( home_url( '/' ) ); ?>"
		<?php echo $args['start_open'] ? 'data-start-open' : ''; ?>
		hidden>
		<div class="nm-scope-search__bar">
			<span class="nm-scope-search__icon" aria-hidden="true"><?php echo ner_michoel_line_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
			<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
			<input type="search" id="<?php echo esc_attr( $uid ); ?>" class="nm-scope-search__query" placeholder="<?php echo esc_attr( $args['label'] ); ?>" autocomplete="off" enterkeyhint="search" data-scope-query />
			<button type="button" class="nm-scope-search__toggle" data-scope-toggle aria-expanded="true"><?php echo ner_michoel_line_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?> <span><?php esc_html_e( 'Filters', 'ner-michoel-child' ); ?></span></button>
		</div>
		<div class="nm-scope-search__filters" data-scope-filters>
			<label class="nm-scope-search__filter">
				<span><?php esc_html_e( 'Speaker', 'ner-michoel-child' ); ?></span>
				<select data-scope-speaker><option value=""><?php esc_html_e( 'All speakers', 'ner-michoel-child' ); ?></option></select>
			</label>
			<label class="nm-scope-search__filter">
				<span><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></span>
				<select data-scope-series><option value=""><?php esc_html_e( 'All series', 'ner-michoel-child' ); ?></option></select>
			</label>
			<label class="nm-scope-search__filter" hidden>
				<span><?php esc_html_e( 'Topic', 'ner-michoel-child' ); ?></span>
				<select data-scope-topic><option value=""><?php esc_html_e( 'All topics', 'ner-michoel-child' ); ?></option></select>
			</label>
			<label class="nm-scope-search__filter">
				<span><?php esc_html_e( 'Year', 'ner-michoel-child' ); ?></span>
				<select data-scope-year><option value=""><?php esc_html_e( 'All years', 'ner-michoel-child' ); ?></option></select>
			</label>
			<label class="nm-scope-search__filter">
				<span><?php esc_html_e( 'Kind', 'ner-michoel-child' ); ?></span>
				<select data-scope-kind>
					<option value=""><?php esc_html_e( 'All kinds', 'ner-michoel-child' ); ?></option>
					<option value="a"><?php esc_html_e( 'Audio', 'ner-michoel-child' ); ?></option>
					<option value="v"><?php esc_html_e( 'Video', 'ner-michoel-child' ); ?></option>
					<option value="w"><?php esc_html_e( 'Written', 'ner-michoel-child' ); ?></option>
				</select>
			</label>
			<label class="nm-scope-search__filter">
				<span><?php esc_html_e( 'Sort', 'ner-michoel-child' ); ?></span>
				<select data-scope-sort>
					<option value=""><?php esc_html_e( 'Best match', 'ner-michoel-child' ); ?></option>
					<option value="new"><?php esc_html_e( 'Newest first', 'ner-michoel-child' ); ?></option>
					<option value="old"><?php esc_html_e( 'Oldest first', 'ner-michoel-child' ); ?></option>
					<option value="speaker"><?php esc_html_e( 'By speaker', 'ner-michoel-child' ); ?></option>
				</select>
			</label>
			<button type="button" class="nm-scope-search__reset" data-scope-reset hidden><?php esc_html_e( 'Clear', 'ner-michoel-child' ); ?></button>
		</div>
		<div class="nm-scope-search__status">
			<p class="nm-scope-search__count" data-scope-count aria-live="polite"></p>
			<p class="nm-scope-search__all" data-scope-all hidden><a href="<?php echo esc_url( home_url( '/' ) ); ?>"></a></p>
		</div>
		<ul class="nm-live-results nm-scope-search__results" data-scope-results></ul>
		<button type="button" class="nm-live-search__more" data-scope-more hidden><?php esc_html_e( 'Show more', 'ner-michoel-child' ); ?></button>
	</div>
	<?php
}

/* ------------------------------------------------------------------
 * "For you" is by account only.
 * ------------------------------------------------------------------ */

/**
 * "For you" comes only from the signed-in account's own History and Saved list
 * (inc/for-you.php, core user-library.php): nothing about the visitor's internet
 * address or computer goes into it. A signed-in visitor's pages are also marked
 * private, so no cache between the site and the browser (an office or school
 * network's, the host's) can keep one person's "For you" and hand it to someone
 * else on the same connection.
 */
function ner_michoel_private_pages_for_accounts() {
	if ( is_user_logged_in() && ! is_admin() && ! headers_sent() ) {
		header( 'Cache-Control: private, no-cache, max-age=0' );
		header( 'Vary: Cookie', false );
	}
}
add_action( 'template_redirect', 'ner_michoel_private_pages_for_accounts', 0 );

/* ------------------------------------------------------------------
 * The gallery pages, in the homepage's look.
 * ------------------------------------------------------------------ */

function ner_michoel_is_gallery_context() {
	return is_post_type_archive( 'gallery' ) || is_tax( 'gallery_type' ) || is_singular( 'gallery' );
}

/** The light look and the serif headings on the gallery pages (body.is-gallery in revamp.css). */
function ner_michoel_gallery_body_class( $classes ) {
	if ( ner_michoel_is_gallery_context() ) {
		$classes[] = 'is-gallery';
	}
	return $classes;
}
add_filter( 'body_class', 'ner_michoel_gallery_body_class' );

function ner_michoel_gallery_fonts() {
	if ( ner_michoel_is_gallery_context() ) {
		wp_enqueue_style( 'ner-michoel-written-serif', 'https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL carries its own versioning.
	}
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_gallery_fonts', 31 );

/** The three gallery sections, with their names and how many galleries each has. */
function ner_michoel_gallery_sections() {
	$names = array(
		'photo'        => __( 'Photos', 'ner-michoel-child' ),
		'video'        => __( 'Videos', 'ner-michoel-child' ),
		'videoshiurim' => __( 'Shiurim videos', 'ner-michoel-child' ),
	);
	$out   = array();
	$terms = get_terms(
		array(
			'taxonomy'   => 'gallery_type',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return $out;
	}
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$out[ $term->slug ] = array(
			'term'  => $term,
			'name'  => isset( $names[ $term->slug ] ) ? $names[ $term->slug ] : $term->name,
			'count' => (int) $term->count,
			'link'  => $link,
		);
	}
	// Photos, Videos, Shiurim videos first, in that order; any other section after.
	uksort(
		$out,
		function ( $a, $b ) use ( $names ) {
			$order = array_flip( array_keys( $names ) );
			return ( isset( $order[ $a ] ) ? $order[ $a ] : 9 ) <=> ( isset( $order[ $b ] ) ? $order[ $b ] : 9 );
		}
	);
	return $out;
}

/** A YouTube video's picture, '' for anything else (Vimeo's needs a request, so it gets the play mark). */
function ner_michoel_gallery_video_thumb( $url ) {
	if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $url, $m ) ) {
		return 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
	}
	return '';
}

/** What a gallery card shows: picture, kind, count line. */
function ner_michoel_gallery_card_data( $post_id ) {
	$type     = function_exists( 'ner_michoel_get_gallery_type' ) ? ner_michoel_get_gallery_type( $post_id ) : '';
	$is_video = in_array( $type, array( 'video', 'videoshiurim' ), true );
	$image    = function_exists( 'ner_michoel_get_gallery_cover_url' ) ? ner_michoel_get_gallery_cover_url( $post_id, 'medium_large' ) : '';
	$count    = 0;
	if ( $is_video ) {
		$videos = function_exists( 'ner_michoel_get_gallery_videos' ) ? ner_michoel_get_gallery_videos( $post_id ) : array();
		$count  = count( $videos );
		if ( '' === $image && $videos ) {
			$image = ner_michoel_gallery_video_thumb( $videos[0] );
		}
		/* translators: %s: number of videos */
		$what = sprintf( _n( '%s video', '%s videos', $count, 'ner-michoel-child' ), number_format_i18n( $count ) );
	} else {
		$count = function_exists( 'ner_michoel_get_gallery_image_count' ) ? ner_michoel_get_gallery_image_count( $post_id ) : 0;
		/* translators: %s: number of photos */
		$what = sprintf( _n( '%s photo', '%s photos', $count, 'ner-michoel-child' ), number_format_i18n( $count ) );
	}
	return array(
		'is_video' => $is_video,
		'image'    => $image,
		'what'     => $what,
		'date'     => get_the_date( '', $post_id ),
		'iso'      => get_the_date( 'c', $post_id ),
	);
}

/**
 * The Galleries pages (archive-gallery.php: all of them; taxonomy-gallery_type.php: one
 * section): a navy hero with the sections as tabs, then the galleries as picture cards.
 *
 * @param WP_Query $galleries
 * @param string   $current   the section's slug, '' for all
 * @param string   $title
 */
function ner_michoel_render_gallery_page( WP_Query $galleries, $current, $title ) {
	$sections = ner_michoel_gallery_sections();
	$all_url  = get_post_type_archive_link( 'gallery' );
	$total    = (int) wp_count_posts( 'gallery' )->publish;
	$ledes    = array(
		'photo'        => __( 'Pictures from the Yeshiva: events, simchas and the year together.', 'ner-michoel-child' ),
		'video'        => __( 'Videos from the Yeshiva’s events.', 'ner-michoel-child' ),
		'videoshiurim' => __( 'Shiurim to watch, from the Yeshiva’s rebbeim.', 'ner-michoel-child' ),
	);
	$lede     = isset( $ledes[ $current ] ) ? $ledes[ $current ] : __( 'Pictures and videos from the Yeshiva.', 'ner-michoel-child' );
	?>
	<div class="nm-app nm-gx">
		<header class="nm-gx-hero">
			<p class="nm-gx-hero__kicker"><?php esc_html_e( 'Galleries', 'ner-michoel-child' ); ?></p>
			<h1 class="nm-gx-hero__title"><?php echo esc_html( $title ); ?></h1>
			<p class="nm-gx-hero__lede"><?php echo esc_html( $lede ); ?></p>
			<?php if ( $sections ) : ?>
				<nav class="nm-gx-tabs" aria-label="<?php esc_attr_e( 'Gallery sections', 'ner-michoel-child' ); ?>">
					<?php if ( $all_url ) : ?>
						<a class="nm-gx-tabs__tab<?php echo '' === $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $all_url ); ?>"<?php echo '' === $current ? ' aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'All', 'ner-michoel-child' ); ?> <span class="nm-gx-tabs__count"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
						</a>
					<?php endif; ?>
					<?php foreach ( $sections as $slug => $section ) : ?>
						<a class="nm-gx-tabs__tab<?php echo $slug === $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $section['link'] ); ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>>
							<?php echo esc_html( $section['name'] ); ?> <span class="nm-gx-tabs__count"><?php echo esc_html( number_format_i18n( $section['count'] ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</header>

		<?php if ( ! $galleries->have_posts() ) : ?>
			<div class="nm-gx-empty">
				<span class="nm-gx-empty__icon" aria-hidden="true"><?php echo ner_michoel_line_icon( 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
				<h2 class="nm-gx-empty__title"><?php esc_html_e( 'No galleries here yet', 'ner-michoel-child' ); ?></h2>
				<p class="nm-gx-empty__text"><?php esc_html_e( 'New pictures and videos will show here as soon as they are added.', 'ner-michoel-child' ); ?></p>
				<?php if ( '' !== $current && $all_url ) : ?>
					<a class="nm-gx-empty__link" href="<?php echo esc_url( $all_url ); ?>"><?php esc_html_e( 'See all galleries', 'ner-michoel-child' ); ?></a>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<ul class="nm-gx-grid">
				<?php
				while ( $galleries->have_posts() ) :
					$galleries->the_post();
					$card = ner_michoel_gallery_card_data( get_the_ID() );
					?>
					<li class="nm-gx-card<?php echo $card['is_video'] ? ' nm-gx-card--video' : ''; ?>">
						<a class="nm-gx-card__link" href="<?php the_permalink(); ?>">
							<span class="nm-gx-card__art">
								<?php if ( $card['image'] ) : ?>
									<img src="<?php echo esc_url( $card['image'] ); ?>" alt="" loading="lazy" decoding="async" />
								<?php else : ?>
									<span class="nm-gx-card__blank" aria-hidden="true"><?php echo ner_michoel_line_icon( 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
								<?php endif; ?>
								<?php if ( $card['is_video'] ) : ?>
									<span class="nm-gx-card__play" aria-hidden="true"><?php echo ner_michoel_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
								<?php endif; ?>
								<span class="nm-gx-card__badge"><?php echo esc_html( $card['what'] ); ?></span>
							</span>
							<span class="nm-gx-card__body">
								<span class="nm-gx-card__title"><?php the_title(); ?></span>
								<time class="nm-gx-card__date" datetime="<?php echo esc_attr( $card['iso'] ); ?>"><?php echo esc_html( $card['date'] ); ?></time>
							</span>
						</a>
					</li>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}

/* ------------------------------------------------------------------
 * The homepage banner, the alumni invitation and the Zoom line, and where they show.
 * Set in the Control Panel under Announcements > Homepage Banner (ner-michoel-core
 * includes/home-banner.php). The banner and the alumni box each have a place: under the
 * welcome on the homepage (template-parts/home-banner.php), above the footer on every page,
 * inside the footer, or a popup that opens once on each computer and again when its words
 * change. Everything outside the homepage is printed outside #content, so the page router
 * (nm-router.js) leaves it alone as visitors move between pages.
 * ------------------------------------------------------------------ */

/**
 * The banner, the alumni box (null for signed-in visitors) and the Zoom line, worked out
 * once per page. All null with an older plugin.
 */
function ner_michoel_home_notices() {
	static $notices = null;
	if ( null === $notices ) {
		$notices = array(
			'banner' => null,
			'alumni' => null,
			'zoom'   => null,
		);
		if ( function_exists( 'ner_michoel_home_banner_public' ) ) {
			$notices = array_merge( $notices, ner_michoel_home_banner_public() );
			if ( is_user_logged_in() ) {
				$notices['alumni'] = null;
			}
		}
	}
	return $notices;
}

/** The banner's markup. $uid keeps the heading's id unique when two copies are on a page. */
function ner_michoel_notice_banner_html( array $banner, $uid = '' ) {
	$title_id = 'hn-banner-title' . $uid;
	$bg       = $banner['background'] ? ' style="--hn-banner-bg: url(\'' . esc_url( $banner['background'] ) . '\')"' : '';
	$lines    = array_filter( array_map( 'trim', explode( "\n", $banner['text'] ) ), 'strlen' );

	$html = '<section class="hn-banner hn-banner--' . esc_attr( $banner['style'] ) . '"' . $bg . ( '' !== $banner['title'] ? ' aria-labelledby="' . esc_attr( $title_id ) . '"' : '' ) . '>';
	if ( $banner['logo'] ) {
		$html .= '<span class="hn-banner__logo"><img src="' . esc_url( $banner['logo'] ) . '" alt="" loading="lazy" decoding="async" /></span>';
	}
	$html .= '<div class="hn-banner__body">';
	if ( '' !== $banner['kicker'] ) {
		$html .= '<p class="hn-banner__kicker">' . esc_html( $banner['kicker'] ) . '</p>';
	}
	if ( '' !== $banner['title'] ) {
		$html .= '<h2 class="hn-banner__title" id="' . esc_attr( $title_id ) . '">' . esc_html( $banner['title'] ) . '</h2>';
	}
	foreach ( $lines as $line ) {
		$html .= '<p class="hn-banner__text">' . esc_html( $line ) . '</p>';
	}
	$html .= '</div>';
	if ( '' !== $banner['button_label'] && '' !== $banner['button_url'] ) {
		$external = 0 !== strpos( $banner['button_url'], home_url() );
		$html    .= '<a class="hn-banner__button" href="' . esc_url( $banner['button_url'] ) . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $banner['button_label'] ) . ' ' . ner_michoel_line_icon( 'arrow-right' ) . '</a>';
	}
	return $html . '</section>';
}

/** The alumni box's markup. */
function ner_michoel_notice_alumni_html( array $alumni, $uid = '' ) {
	$title_id = 'hn-alumni-title' . $uid;
	return '<section class="hn-alumni-invite" aria-labelledby="' . esc_attr( $title_id ) . '">'
		. '<span class="hn-alumni-invite__icon" aria-hidden="true">' . ner_michoel_line_icon( 'user' ) . '</span>'
		. '<div class="hn-alumni-invite__body">'
		. '<h2 class="hn-alumni-invite__title" id="' . esc_attr( $title_id ) . '">' . esc_html( $alumni['title'] ) . '</h2>'
		. '<p class="hn-alumni-invite__text">' . esc_html( $alumni['text'] ) . '</p>'
		. '</div>'
		. '<a class="hn-alumni-invite__button" href="' . esc_url( $alumni['url'] ) . '" data-no-pjax>' . esc_html( $alumni['button'] ) . '</a>'
		. '</section>';
}

/**
 * The banner and the alumni box placed in $place, side by side when both are there.
 * '' when neither is.
 *
 * @param string $place home, above_footer, footer or popup.
 * @return string
 */
function ner_michoel_notices_html( $place ) {
	$notices = ner_michoel_home_notices();
	$uid     = 'home' === $place ? '' : '-' . str_replace( '_', '-', $place );
	$banner  = ( $notices['banner'] && $place === $notices['banner']['place'] ) ? ner_michoel_notice_banner_html( $notices['banner'], $uid ) : '';
	$alumni  = ( $notices['alumni'] && $place === $notices['alumni']['place'] ) ? ner_michoel_notice_alumni_html( $notices['alumni'], $uid ) : '';
	if ( '' === $banner && '' === $alumni ) {
		return '';
	}
	return '<div class="hn-notices' . ( ( '' !== $banner && '' !== $alumni ) ? ' hn-notices--both' : '' ) . '">' . $banner . $alumni . '</div>';
}

/** The small Zoom line under the welcome: a live dot, the schedule, the meeting ID and a button. */
function ner_michoel_zoom_line_html( $zoom ) {
	if ( ! $zoom ) {
		return '';
	}
	$schedule = trim( preg_replace( '/\s*\n\s*/', ' · ', (string) $zoom['schedule'] ) );
	$html     = '<div class="hn-zoom" role="region" aria-label="' . esc_attr__( 'Live Shiur on Zoom', 'ner-michoel-child' ) . '">';
	$html    .= '<span class="hn-zoom__live" aria-hidden="true"></span>';
	$html    .= '<p class="hn-zoom__words"><strong>' . esc_html__( 'Live Shiur on Zoom', 'ner-michoel-child' ) . '</strong>';
	if ( '' !== $schedule ) {
		$html .= '<span class="hn-zoom__sep" aria-hidden="true">·</span><span>' . esc_html( $schedule ) . '</span>';
	}
	if ( '' !== $zoom['meeting_id'] ) {
		$html .= '<span class="hn-zoom__sep" aria-hidden="true">·</span><span class="hn-zoom__id">' . esc_html__( 'Meeting ID', 'ner-michoel-child' ) . ' ' . esc_html( $zoom['meeting_id'] ) . '</span>';
	}
	$html .= '</p>';
	if ( '' !== $zoom['zoom_link'] ) {
		$html .= '<a class="hn-zoom__join" href="' . esc_url( $zoom['zoom_link'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Join on Zoom', 'ner-michoel-child' ) . ' ' . ner_michoel_line_icon( 'external' ) . '</a>';
	}
	return $html . '</div>';
}

/** Above the footer, on every page, just before the site footer. */
function ner_michoel_print_notices_above_footer() {
	$html = ner_michoel_notices_html( 'above_footer' );
	if ( '' !== $html ) {
		echo '<div class="nm-notes nm-notes--above-footer">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
	}
}
add_action( 'astra_footer_before', 'ner_michoel_print_notices_above_footer', 5 );

/** Inside the site footer (inc/footer.php calls this hook between its title and its links). */
function ner_michoel_print_notices_in_footer() {
	$GLOBALS['ner_michoel_footer_notes_done'] = true;
	$html = ner_michoel_notices_html( 'footer' );
	if ( '' !== $html ) {
		echo '<div class="nm-notes nm-notes--footer">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
	}
}
add_action( 'ner_michoel_footer_notices', 'ner_michoel_print_notices_in_footer' );

/** When the site footer is switched off, "inside the footer" shows just above Astra's footer. */
function ner_michoel_print_footer_notices_fallback() {
	if ( empty( $GLOBALS['ner_michoel_footer_notes_done'] ) ) {
		$html = ner_michoel_notices_html( 'footer' );
		if ( '' !== $html ) {
			echo '<div class="nm-notes nm-notes--above-footer">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
		}
	}
}
add_action( 'astra_footer_before', 'ner_michoel_print_footer_notices_fallback', 20 );

/**
 * The popup: printed hidden on every page, opened by the script below once on each computer.
 * The browser remembers each message by a short hash of its words, so changing the words
 * shows it again. A browser that keeps nothing (private windows, blocked storage) is asked
 * once per visit instead.
 */
function ner_michoel_print_notices_popup() {
	$notices = ner_michoel_home_notices();
	$keys    = array();
	foreach ( array( 'banner', 'alumni' ) as $part ) {
		if ( $notices[ $part ] && 'popup' === $notices[ $part ]['place'] ) {
			$keys[] = $notices[ $part ]['key'];
		}
	}
	$html = ner_michoel_notices_html( 'popup' );
	if ( '' === $html ) {
		return;
	}
	?>
	<div class="nm-notes nm-notes--popup" id="nm-popup" data-nm-popup="<?php echo esc_attr( implode( '-', $keys ) ); ?>" hidden>
		<div class="nm-popup__backdrop" data-nm-popup-close></div>
		<div class="nm-popup__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Announcement', 'ner-michoel-child' ); ?>" tabindex="-1">
			<button type="button" class="nm-popup__close" data-nm-popup-close aria-label="<?php esc_attr_e( 'Close', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_line_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></button>
			<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built. ?>
		</div>
	</div>
	<script>
	(function () {
		var box = document.getElementById('nm-popup');
		if (!box) { return; }
		var key = 'nmPopupSeen', id = box.getAttribute('data-nm-popup'), seen = '';
		function read() {
			try { return window.localStorage.getItem(key) || ''; } catch (e) {}
			try { return window.sessionStorage.getItem(key) || ''; } catch (e) {}
			return '';
		}
		function remember() {
			try { window.localStorage.setItem(key, id); return; } catch (e) {}
			try { window.sessionStorage.setItem(key, id); } catch (e) {}
		}
		seen = read();
		if (seen === id) { return; }
		var before = null;
		function close() {
			box.hidden = true;
			document.documentElement.classList.remove('nm-popup-open');
			document.removeEventListener('keydown', onKey);
			if (before && before.focus) { before.focus(); }
		}
		function onKey(e) { if (e.key === 'Escape') { close(); } }
		box.addEventListener('click', function (e) {
			if (e.target.closest('[data-nm-popup-close]')) { close(); return; }
			if (e.target.closest('a')) { remember(); box.hidden = true; document.documentElement.classList.remove('nm-popup-open'); }
		});
		window.setTimeout(function () {
			before = document.activeElement;
			box.hidden = false;
			document.documentElement.classList.add('nm-popup-open');
			document.addEventListener('keydown', onKey);
			remember();
			var dialog = box.querySelector('.nm-popup__dialog');
			if (dialog) { dialog.focus(); }
		}, 1200);
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'ner_michoel_print_notices_popup', 20 );
