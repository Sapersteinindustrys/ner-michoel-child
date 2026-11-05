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
