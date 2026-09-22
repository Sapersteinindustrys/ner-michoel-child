<?php
/**
 * Ner Michoel child theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NER_MICHOEL_VERSION', '0.2.0' );
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
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_assets', 20 );

/**
 * Additional includes, split by concern as the rebuild grows
 * (e.g. custom post types, ACF field registration, template tags).
 */
require_once NER_MICHOEL_PATH . '/inc/template-tags.php';
require_once NER_MICHOEL_PATH . '/inc/classic-view.php';
require_once NER_MICHOEL_PATH . '/inc/updates.php';
