<?php
/**
 * Ner Michoel child theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NER_MICHOEL_VERSION', '0.2.63' );
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
	//
	// nonce: WordPress's own REST cookie-auth middleware
	// (rest_cookie_check_errors(), always active) rejects the entire
	// request — not just anything requiring a login — whenever the
	// visitor's browser sends a valid logged-in auth cookie without a
	// matching X-WP-Nonce header, as a CSRF guard. shiur-event's own
	// permission_callback is public (works with no login at all), but
	// that middleware runs first: a logged-in visitor's browser sends
	// their session cookie on this same-origin fetch whether the
	// endpoint needs it or not, so without this nonce every play/
	// complete ping from a logged-in visitor was silently rejected —
	// invisible until there were real logged-in visitors to notice.
	// historyUrl/savedToggleUrl: the Account page's History/Saved tabs
	// (ner-michoel-core's user-library.php) — written shiurim record
	// history through historyUrl on "Read here" (shiur history rides
	// the existing shiur-event ping instead, see shiur-stats.php), and
	// the Save button on single shiur/written-shiur pages uses
	// savedToggleUrl.
	wp_localize_script(
		'ner-michoel-custom',
		'nerMichoelSettings',
		array(
			'shiurEventUrl'   => rest_url( 'ner-michoel/v1/shiur-event' ),
			'nextUpUrl'       => rest_url( 'ner-michoel/v1/next-up' ),
			'historyUrl'      => rest_url( 'ner-michoel/v1/history-record' ),
			'savedToggleUrl'  => rest_url( 'ner-michoel/v1/saved-toggle' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn'      => is_user_logged_in(),
		)
	);

	// Site-wide router (nm-router.js): swaps #content on same-origin
	// navigation instead of a real page load, so whatever's playing in
	// #sh-player keeps playing. No dependency array entry needed on the
	// other theme scripts below — they listen for its events at parse time,
	// which only matters before the first navigation, and everything here
	// is in_footer on every page regardless of relative enqueue order.
	wp_enqueue_script(
		'ner-michoel-router',
		NER_MICHOEL_URI . '/assets/js/nm-router.js',
		array(),
		NER_MICHOEL_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_assets', 20 );

/**
 * Component stylesheets, kept out of custom.css so each feature's styles
 * stay in one file. Both depend on custom.css for the --nm-* / --sh-*
 * tokens they use.
 */
function ner_michoel_enqueue_component_styles() {
	wp_enqueue_style(
		'ner-michoel-live-shiur',
		NER_MICHOEL_URI . '/assets/css/live-shiur.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);

	wp_enqueue_style(
		'ner-michoel-written-shiurim',
		NER_MICHOEL_URI . '/assets/css/written-shiurim.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_component_styles', 21 );

/**
 * Account page (login/sign up/profile) assets. Loaded site-wide (not
 * gated to the one template that needs it) so the router (nm-router.js)
 * always has them ready when it swaps a visitor into the Account page
 * without a real page load; account.js no-ops harmlessly where its
 * elements don't exist.
 */
function ner_michoel_enqueue_account_assets() {
	wp_enqueue_style(
		'ner-michoel-account',
		NER_MICHOEL_URI . '/assets/css/account.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);

	wp_enqueue_script(
		'ner-michoel-account',
		NER_MICHOEL_URI . '/assets/js/account.js',
		array(),
		NER_MICHOEL_VERSION,
		true
	);

	// X-WP-Nonce for the two routes that require being logged in
	// (update-profile, avatar) — WordPress's own REST cookie-auth
	// middleware checks this against the request automatically; a
	// missing/stale nonce here is why an otherwise-correct authenticated
	// request would get rejected.
	wp_localize_script(
		'ner-michoel-account',
		'nerMichoelAccount',
		array(
			'root'  => esc_url_raw( rest_url( 'ner-michoel/v1' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_account_assets', 21 );

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
 * Tells ner-michoel-core that the forms render its bot check
 * (ner_michoel_form_guard_fields(), in contact.php and account.php), so it
 * can require the check on what they send. Until a theme says this, the
 * plugin doesn't require it, so the two can be updated in either order.
 */
function ner_michoel_form_guard_support() {
	add_theme_support( 'nm-form-guard' );
}
add_action( 'after_setup_theme', 'ner_michoel_form_guard_support' );

/**
 * Tells ner-michoel-core that the Account page has the "enter your code" step
 * (page-templates/account.php, assets/js/account.js), so a new sign-up is
 * confirmed by an emailed 6-digit code before the account works. Until a theme
 * says this the plugin signs new accounts straight in, so the two can be
 * updated in either order.
 */
function ner_michoel_email_verification_support() {
	add_theme_support( 'nm-email-verification' );
}
add_action( 'after_setup_theme', 'ner_michoel_email_verification_support' );

/**
 * Additional includes, split by concern as the rebuild grows
 * (e.g. custom post types, ACF field registration, template tags).
 */
require_once NER_MICHOEL_PATH . '/inc/template-tags.php';
require_once NER_MICHOEL_PATH . '/inc/classic-view.php';
require_once NER_MICHOEL_PATH . '/inc/updates.php';
require_once NER_MICHOEL_PATH . '/inc/appearance.php';
require_once NER_MICHOEL_PATH . '/inc/navigation.php';
require_once NER_MICHOEL_PATH . '/inc/search.php';
require_once NER_MICHOEL_PATH . '/inc/player-sheet.php';
require_once NER_MICHOEL_PATH . '/inc/written-shiurim.php';
require_once NER_MICHOEL_PATH . '/inc/home.php';
require_once NER_MICHOEL_PATH . '/inc/shiurim-studio.php';
require_once NER_MICHOEL_PATH . '/inc/for-you.php';
