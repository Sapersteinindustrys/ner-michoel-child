<?php
/**
 * Mazal Tov announcements on the public pages.
 *
 * An announcement reads "Name ('95) on his engagement to Sarah Levy": the person's
 * name with his years in brackets, then what the Mazal Tov is. It is never shown with
 * the date it was posted. The pieces come from ner-michoel-core
 * (ner_michoel_mazal_tov_parts); a plugin that is behind gets the older reading
 * (the title, then its years in brackets), so the two can be updated in either order.
 *
 * Visitors share a Mazal Tov from a form that opens over the page
 * (template-parts/mazal-tov-share.php). The core plugin keeps what they send until the
 * site's admin approves it; nothing they type is shown before that.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pieces of an announcement to print, in reading order: 'before' (words ahead of the
 * name, like "Mazel tov to Rabbi & Mrs."), 'lead' (the name with his years, shown bold) and
 * 'after' (what the Mazal Tov is). Plain text: escape it where it is printed.
 */
function ner_michoel_theme_mazal_parts( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array( 'before' => '', 'lead' => '', 'after' => '' );
	}
	if ( function_exists( 'ner_michoel_mazal_tov_parts' ) ) {
		$parts = ner_michoel_mazal_tov_parts( $post );
		// A plugin from before the name could sit in the middle only knows lead and rest.
		return array(
			'before' => isset( $parts['before'] ) ? $parts['before'] : '',
			'lead'   => $parts['lead'],
			'after'  => isset( $parts['after'] ) ? $parts['after'] : ( isset( $parts['rest'] ) ? $parts['rest'] : '' ),
		);
	}
	$relationship = function_exists( 'ner_michoel_get_mazal_tov_relationship' ) ? ner_michoel_get_mazal_tov_relationship( $post->ID ) : '';
	$years        = function_exists( 'ner_michoel_get_mazal_tov_years' ) ? ner_michoel_get_mazal_tov_years( $post->ID ) : '';
	$lead         = trim( $relationship . ' ' . get_the_title( $post ) );
	if ( '' !== $years ) {
		$lead .= ' (' . $years . ')';
	}
	return array( 'before' => '', 'lead' => $lead, 'after' => '' );
}

/**
 * The kinds of simcha a visitor can pick on the share form: the admin's presets, as id => name.
 * Empty when there are none (or the plugin is behind), and the form then asks for the words itself.
 */
function ner_michoel_mazal_share_choices() {
	return function_exists( 'ner_michoel_mazal_tov_share_choices' ) ? ner_michoel_mazal_tov_share_choices() : array();
}

/** The name of an announcement's type ("Birth"), '' when it has none. */
function ner_michoel_theme_mazal_type( $post ) {
	$terms = taxonomy_exists( 'mazal_tov_type' ) ? get_the_terms( $post, 'mazal_tov_type' ) : false;
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
}

/**
 * True when the plugin can take a shared Mazal Tov. The share button falls back to the
 * Contact page when it can't (a plugin that is behind), so it never leads to a form
 * that fails.
 */
function ner_michoel_mazal_share_ready() {
	return post_type_exists( 'mazal_tov' ) && has_action( 'admin_post_nopriv_nm_mazal_tov_share' );
}

/**
 * The button that opens the share form. A plain link to the Contact page until the
 * script opens the form instead (and for anyone the script doesn't reach).
 */
function ner_michoel_mazal_share_button( $classes = '', $label = '' ) {
	if ( '' === $label ) {
		$label = __( 'Share a Mazal Tov', 'ner-michoel-child' );
	}
	if ( ! ner_michoel_mazal_share_ready() ) {
		printf(
			'<a class="%1$s" href="%2$s">%3$s</a>',
			esc_attr( $classes ),
			esc_url( home_url( '/contact/' ) ),
			esc_html__( 'Send us your news', 'ner-michoel-child' )
		);
		return;
	}
	printf(
		'<a class="%1$s" href="%2$s" data-nm-mt-open data-no-pjax aria-haspopup="dialog">%3$s</a>',
		esc_attr( $classes ),
		esc_url( home_url( '/contact/' ) ),
		esc_html( $label )
	);
}

/** Share-form script and style: on every page, because the page router swaps content in without loading a page's scripts. */
function ner_michoel_enqueue_mazal_share_assets() {
	wp_enqueue_style(
		'ner-michoel-mazal-share',
		NER_MICHOEL_URI . '/assets/css/mazal-tov-share.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);
	wp_enqueue_script(
		'ner-michoel-mazal-share',
		NER_MICHOEL_URI . '/assets/js/mazal-tov-share.js',
		array(),
		NER_MICHOEL_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_mazal_share_assets', 21 );

/** More announcements per page on the Mazal Tov archive. */
function ner_michoel_mazal_archive_query( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'mazal_tov' ) ) {
		$query->set( 'posts_per_page', 30 );
	}
}
add_action( 'pre_get_posts', 'ner_michoel_mazal_archive_query' );
