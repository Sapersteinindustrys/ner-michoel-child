<?php
/**
 * Shiurim search: the results page and its way back.
 *
 * The ranking itself lives in ner-michoel-core (includes/shiur-search.php).
 * This file only decides which template shows the results, and provides
 * the Back link on that page.
 *
 * Other searches (site-wide, or a plain search with no Shiurim scope) keep
 * WordPress's and Astra's search template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True on the plain Shiurim search: the server-ranked results, for browsers
 * without JavaScript, or when the live search's index can't be loaded (the
 * script then reloads with nm_plain=1). Everywhere else the page is the live
 * search, and the server doesn't rank at all.
 */
function ner_michoel_is_plain_shiur_search() {
	return ! empty( $_GET['nm_plain'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display switch.
}

/**
 * Turns core's server ranking off on the live search page (see
 * ner_michoel_shiur_search_pre_get_posts() in ner-michoel-core). Only for
 * Shiurim searches; the Written Shiurim search keeps it.
 */
function ner_michoel_skip_server_ranking( $run, $query ) {
	if ( ner_michoel_is_plain_shiur_search() || 'shiur' !== $query->get( 'post_type' ) ) {
		return $run;
	}
	return false;
}
add_filter( 'ner_michoel_shiur_search_server_ranking', 'ner_michoel_skip_server_ranking', 10, 2 );

/**
 * Loads the live search (assets/js/shiur-live-search.js) site-wide, not just
 * on the Shiurim search results page, so the router (nm-router.js) always
 * has it ready when it swaps a visitor onto that page without a real page
 * load. The script itself no-ops where its root element doesn't exist —
 * which is also still true on the plain search page.
 */
function ner_michoel_enqueue_shiur_live_search() {
	wp_enqueue_style( 'ner-michoel-shiur-live-search', NER_MICHOEL_URI . '/assets/css/shiur-live-search.css', array(), NER_MICHOEL_VERSION );
	// The matching and ranking, on their own so they can be tested; the live search and the topic page's search box use it.
	wp_enqueue_script( 'ner-michoel-shiur-search-engine', NER_MICHOEL_URI . '/assets/js/shiur-search-engine.js', array(), NER_MICHOEL_VERSION, true );
	wp_enqueue_script( 'ner-michoel-shiur-live-search', NER_MICHOEL_URI . '/assets/js/shiur-live-search.js', array( 'ner-michoel-shiur-search-engine' ), NER_MICHOEL_VERSION, true );
	wp_localize_script(
		'ner-michoel-shiur-live-search',
		'nmLiveSearch',
		array(
			'loading'   => __( 'Loading shiurim…', 'ner-michoel-child' ),
			'none'      => __( 'No shiurim match these words and filters. Try fewer or different words.', 'ner-michoel-child' ),
			/* translators: 1: shiurim shown so far, 2: shiurim found */
			'showing'   => __( 'Showing %1$s of %2$s shiurim', 'ner-michoel-child' ),
			'foundOne'  => __( '1 shiur found.', 'ner-michoel-child' ),
			/* translators: %s: number of shiurim found */
			'found'     => __( '%s shiurim found.', 'ner-michoel-child' ),
			'audio'     => __( 'Audio', 'ner-michoel-child' ),
			'video'     => __( 'Video', 'ner-michoel-child' ),
			'written'   => __( 'Written', 'ner-michoel-child' ),
			// The heading above the results that match with a letter or two off or swapped.
			'closeOne'  => __( '1 close match · spelled a little differently', 'ner-michoel-child' ),
			/* translators: %s: number of close matches */
			'closeMany' => __( '%s close matches · spelled a little differently', 'ner-michoel-child' ),
			// A topic page's search box, when the search can't load.
			'unavailable' => __( 'Search isn’t working right now. Please try again in a moment.', 'ner-michoel-child' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_shiur_live_search', 22 );

/**
 * True on a Shiurim search results page (the sidebar's search form sends
 * post_type=shiur with the term).
 */
function ner_michoel_is_shiur_search() {
	return is_search() && 'shiur' === get_query_var( 'post_type' );
}

/**
 * True on a Written Shiurim search results page (its own search form sends
 * post_type=written_shiur with the term).
 */
function ner_michoel_is_written_search() {
	return is_search() && 'written_shiur' === get_query_var( 'post_type' );
}

function ner_michoel_use_shiur_search_template( $template ) {
	if ( ner_michoel_is_shiur_search() ) {
		$shiur_search = locate_template( 'search-shiurim.php' );
		if ( $shiur_search ) {
			return $shiur_search;
		}
	}
	if ( ner_michoel_is_written_search() ) {
		$written_search = locate_template( 'search-written.php' );
		if ( $written_search ) {
			return $written_search;
		}
	}
	return $template;
}
add_filter( 'template_include', 'ner_michoel_use_shiur_search_template', 99 );

/**
 * The search box for Written Shiurim, used on the written archive and on its
 * results page. Searches titles, speakers and series, like the Shiurim search.
 */
function ner_michoel_render_written_search_form() {
	$term = get_search_query( false );
	?>
	<form class="sh-written-search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search">
		<input type="hidden" name="post_type" value="written_shiur" />
		<label class="screen-reader-text" for="sh-written-search-input"><?php esc_html_e( 'Search written shiurim', 'ner-michoel-child' ); ?></label>
		<input type="search" id="sh-written-search-input" name="s" value="<?php echo esc_attr( $term ); ?>" placeholder="<?php esc_attr_e( 'Search written shiurim…', 'ner-michoel-child' ); ?>" />
		<button type="submit"><?php esc_html_e( 'Search', 'ner-michoel-child' ); ?></button>
	</form>
	<?php
}

/**
 * The search box on a topic page (taxonomy-topic.php): finds that topic's shiurim
 * and written shiurim as you type, across all of the topic's pages
 * (assets/js/shiur-live-search.js, from the same cached index as the Shiurim
 * search). Close spellings are found too, after the full matches.
 *
 * It starts hidden and the script shows it, so a visitor without script just
 * sees the page's own lists. The index is only fetched when the box is used, not
 * with every topic page. Those lists carry data-topic-browse; the search takes
 * their place while something is typed. The search is kept in the address as #q=
 * (after the #, so the server never sees it) and put back into the box by the
 * script, never printed here: topic pages can be cached, and one visitor's search
 * must not end up in the page the next one gets.
 *
 * @param WP_Term $term The topic.
 */
function ner_michoel_render_topic_search( $term ) {
	if ( ! $term || empty( $term->term_id ) || ! $term->count ) {
		return;
	}

	$index_url = function_exists( 'ner_michoel_shiur_index_url' ) ? ner_michoel_shiur_index_url() : rest_url( 'ner-michoel/v1/shiur-index' );
	/* translators: %s: topic name */
	$label = sprintf( __( 'Search within %s', 'ner-michoel-child' ), $term->name );
	?>
	<div class="nm-live-search nm-live-search--topic"
		data-topic-search
		data-topic-id="<?php echo esc_attr( $term->term_id ); ?>"
		data-index-url="<?php echo esc_url( $index_url ); ?>"
		data-all-url="<?php echo esc_url( home_url( '/' ) ); ?>"
		data-none="<?php echo esc_attr( __( 'No shiurim in this topic match “%s”.', 'ner-michoel-child' ) ); ?>"
		data-all-label="<?php echo esc_attr( __( 'Search all shiurim for “%s”', 'ner-michoel-child' ) ); ?>"
		hidden>
		<label class="screen-reader-text" for="nm-topic-query"><?php echo esc_html( $label ); ?></label>
		<input type="search" id="nm-topic-query" class="nm-live-search__query" placeholder="<?php echo esc_attr( $label . '…' ); ?>" autocomplete="off" enterkeyhint="search" data-topic-query />
		<div class="nm-live-search__status">
			<p class="nm-live-search__count" data-topic-count aria-live="polite"></p>
			<p class="nm-live-search__all" data-topic-all hidden><a href="<?php echo esc_url( home_url( '/' ) ); ?>"></a></p>
		</div>
		<ul class="nm-live-results" data-topic-results></ul>
		<button type="button" class="nm-live-search__more" data-topic-more hidden><?php esc_html_e( 'Show more', 'ner-michoel-child' ); ?></button>
	</div>
	<?php
}

/**
 * "Back" on the search results. Unlike the Shiurim page's own Back button
 * (ner_michoel_render_back_button(), which never leaves the page), this one
 * goes back to the page the search came from. custom.js handles the click:
 * the browser's history if the visitor came from this site, and the link's
 * href (the Shiurim archive) if they didn't, such as from a shared link.
 */
function ner_michoel_render_return_link( $fallback = '' ) {
	$fallback = $fallback ? $fallback : get_post_type_archive_link( 'shiur' );
	?>
	<nav class="sh-back-row" aria-label="<?php esc_attr_e( 'Page navigation', 'ner-michoel-child' ); ?>">
		<a class="sh-back" href="<?php echo esc_url( $fallback ); ?>" data-nm-return>
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
			<span><?php esc_html_e( 'Back', 'ner-michoel-child' ); ?></span>
		</a>
	</nav>
	<?php
}
