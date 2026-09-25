<?php
/**
 * Ner Michoel child theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NER_MICHOEL_VERSION', '0.2.21' );
define( 'NER_MICHOEL_PATH', get_stylesheet_directory() );
define( 'NER_MICHOEL_URI', get_stylesheet_directory_uri() );

/**
 * Load parent Astra styles, then the child's own stylesheet(s) on top.
 */
function ner_michoel_enqueue_assets() {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		NER_MICHOEL_VERSION
	);

	wp_enqueue_style(
		'ner-michoel-child-style',
		get_stylesheet_uri(),
		array( 'astra-parent-style' ),
		NER_MICHOEL_VERSION
	);

	wp_enqueue_style(
		'ner-michoel-custom',
		NER_MICHOEL_URI . '/assets/css/custom.css',
		array( 'ner-michoel-child-style' ),
		NER_MICHOEL_VERSION
	);

	wp_enqueue_script(
		'ner-michoel-custom',
		NER_MICHOEL_URI . '/assets/js/custom.js',
		array(),
		NER_MICHOEL_VERSION,
		true
	);

	// rest_url() resolves correctly even when WP lives in a subdirectory
	// (e.g. /website_xxxx/) — a hardcoded '/wp-json/...' path in JS
	// would 404 there, so the play/completion tracker (custom.js) reads
	// this instead of assuming the REST base is at the site root.
	wp_localize_script(
		'ner-michoel-custom',
		'nerMichoelSettings',
		array(
			'shiurEventUrl' => rest_url( 'ner-michoel/v1/shiur-event' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_assets', 20 );

/**
 * Forces the Customizer's logo uploader into a fixed-box drag/zoom
 * crop (flex-width/flex-height false + explicit dimensions) instead
 * of accepting any aspect ratio — the admin still repositions the
 * crop before it saves, WordPress core's uploader already does that,
 * this just constrains the box it crops into.
 *
 * Astra's Header Builder is active on this site (confirmed via the
 * `ast-hfb-header` body class) and its own Site Identity component
 * already ships a "Logo Width" slider for resizing the *displayed*
 * logo — Customize > Header Builder > the Logo element. That's a
 * separate concern from this crop and needs no theme code; nothing
 * here duplicates it. If that control turns out not to exist in
 * practice once a real logo is uploaded, a custom Customizer "Logo
 * Size" range control would be the fallback — not built preemptively.
 */
function ner_michoel_custom_logo_support() {
	add_theme_support(
		'custom-logo',
		array(
			'width'       => 300,
			'height'      => 100,
			'flex-width'  => false,
			'flex-height' => false,
		)
	);
}
add_action( 'after_setup_theme', 'ner_michoel_custom_logo_support' );

/**
 * Additional includes, split by concern as the rebuild grows
 * (e.g. custom post types, ACF field registration, template tags).
 */
require_once NER_MICHOEL_PATH . '/inc/template-tags.php';
require_once NER_MICHOEL_PATH . '/inc/classic-view.php';
require_once NER_MICHOEL_PATH . '/inc/updates.php';
require_once NER_MICHOEL_PATH . '/inc/appearance.php';
