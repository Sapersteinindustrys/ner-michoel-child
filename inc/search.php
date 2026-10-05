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
 * True on a Shiurim search results page (the sidebar's search form sends
 * post_type=shiur with the term).
 */
function ner_michoel_is_shiur_search() {
	return is_search() && 'shiur' === get_query_var( 'post_type' );
}

function ner_michoel_use_shiur_search_template( $template ) {
	if ( ner_michoel_is_shiur_search() ) {
		$shiur_search = locate_template( 'search-shiurim.php' );
		if ( $shiur_search ) {
			return $shiur_search;
		}
	}
	return $template;
}
add_filter( 'template_include', 'ner_michoel_use_shiur_search_template', 99 );

/**
 * "Back" on the search results. Unlike the Shiurim page's own Back button
 * (ner_michoel_render_back_button(), which never leaves the page), this one
 * goes back to the page the search came from. custom.js handles the click:
 * the browser's history if the visitor came from this site, and the link's
 * href (the Shiurim archive) if they didn't, such as from a shared link.
 */
function ner_michoel_render_return_link() {
	?>
	<nav class="sh-back-row" aria-label="<?php esc_attr_e( 'Page navigation', 'ner-michoel-child' ); ?>">
		<a class="sh-back" href="<?php echo esc_url( get_post_type_archive_link( 'shiur' ) ); ?>" data-nm-return>
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
			<span><?php esc_html_e( 'Back', 'ner-michoel-child' ); ?></span>
		</a>
	</nav>
	<?php
}
