<?php
/**
 * The site's name, as the theme shows it.
 *
 * The name, the small second name (the tagline) and the logo are WordPress's own settings, changed in the
 * Control Panel (ner-michoel-core: Homepage & Look > Site Name & Logo). The theme only decides how they look:
 * the name large, the second name small under it (assets/css/custom.css), and the logo, if there is one, at
 * the left. Words on the pages that name the site ("Welcome to ...", "Celebrating with the ... family") read
 * the name from here instead of having one written into them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's name as plain text ("Rabbi & Sons", not "Rabbi &amp; Sons"); the template escapes it where it
 * prints it. WordPress keeps the title with its characters escaped for HTML.
 */
function ner_michoel_site_name() {
	return trim( wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) );
}

/**
 * Shows the tagline under the name in the header, when the site has one.
 *
 * Astra hides the tagline unless its own "Site Tagline" switch (Customizer > Header Builder > Site Title & Logo)
 * is on, which nobody would find, and prints an empty line when it is on and there is no tagline. So the
 * switch is on exactly when the site has a tagline, for every screen size.
 *
 * @param array $visible Astra's setting: desktop, tablet and mobile, each 0 or 1.
 * @return array
 */
function ner_michoel_show_header_tagline( $visible ) {
	if ( '' === trim( (string) get_option( 'blogdescription' ) ) ) {
		return $visible;
	}
	return array(
		'desktop' => 1,
		'tablet'  => 1,
		'mobile'  => 1,
	);
}
add_filter( 'astra_get_option_display-site-tagline-responsive', 'ner_michoel_show_header_tagline' );
