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
 * Loads the live search (assets/js/shiur-live-search.js) on the Shiurim search
 * results page only, and not on the plain search.
 */
function ner_michoel_enqueue_shiur_live_search() {
	if ( ! ner_michoel_is_shiur_search() || ner_michoel_is_plain_shiur_search() ) {
		return;
	}

	wp_enqueue_style( 'ner-michoel-shiur-live-search', NER_MICHOEL_URI . '/assets/css/shiur-live-search.css', array(), NER_MICHOEL_VERSION );
	wp_enqueue_script( 'ner-michoel-shiur-live-search', NER_MICHOEL_URI . '/assets/js/shiur-live-search.js', array(), NER_MICHOEL_VERSION, true );
	wp_localize_script(
		'ner-michoel-shiur-live-search',
		'nmLiveSearch',
		array(
			'loading'  => __( 'Loading shiurim…', 'ner-michoel-child' ),
			'none'     => __( 'No shiurim match these words and filters. Try fewer or different words.', 'ner-michoel-child' ),
			/* translators: 1: shiurim shown so far, 2: shiurim found */
			'showing'  => __( 'Showing %1$s of %2$s shiurim', 'ner-michoel-child' ),
			'foundOne' => __( '1 shiur found.', 'ner-michoel-child' ),
			/* translators: %s: number of shiurim found */
			'found'    => __( '%s shiurim found.', 'ner-michoel-child' ),
			'audio'    => __( 'Audio', 'ner-michoel-child' ),
			'video'    => __( 'Video', 'ner-michoel-child' ),
			'written'  => __( 'Written', 'ner-michoel-child' ),
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
