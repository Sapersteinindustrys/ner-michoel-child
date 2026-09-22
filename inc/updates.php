<?php
/**
 * Self-hosted auto-updates via GitHub — so a new version can be
 * pushed and tagged without manually re-uploading a zip through
 * wp-admin. Uses the "Plugin Update Checker" library (MIT, vendored
 * in vendor/plugin-update-checker/ — same copy ner-michoel-core
 * uses, each package needs its own), pointed at a public release
 * repo dedicated to this theme. The library auto-detects "theme" vs
 * "plugin" from whether the given path is a directory or a file, so
 * passing the theme directory (not a specific .php file) is what
 * makes it behave as a theme checker.
 *
 * How to ship an update:
 * 1. Bump the `Version:` header in style.css (and NER_MICHOEL_VERSION
 *    in functions.php).
 * 2. Push the theme's files to https://github.com/Sapersteinindustrys/ner-michoel-child
 * 3. Tag the commit with the same version number (e.g. `v0.2.0`) and push the tag,
 *    then `gh release create v0.2.0`.
 * WordPress then shows "Update Available" (Dashboard > Updates) within
 * ~12 hours on its own, or immediately if you click "Check Again" —
 * no manual zip upload needed either way.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs immediately (not deferred to a hook) — PUC registers its own
 * hooks into WordPress' update-check lifecycle internally, and needs
 * to do that as early as the theme loads, the same way its own
 * documented usage pattern does.
 */
function ner_michoel_child_init_update_checker() {
	$library = NER_MICHOEL_PATH . '/vendor/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $library ) ) {
		return;
	}
	require_once $library;

	if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}

	\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/Sapersteinindustrys/ner-michoel-child/',
		NER_MICHOEL_PATH,
		'ner-michoel-child'
	);
}
ner_michoel_child_init_update_checker();
