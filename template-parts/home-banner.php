<?php
/**
 * The homepage's spot under the welcome: the small Zoom line, and the banner and the box
 * asking alumni to log in when they are placed here. All three are set in the Control Panel
 * under Announcements > Homepage Banner (ner-michoel-core includes/home-banner.php). The
 * banner and the alumni box can be placed elsewhere instead (above the footer, in the
 * footer, or as a popup); inc/revamp.php prints them there. assets/css/revamp.css lays
 * them out.
 *
 * With an older plugin that has no banner yet, the old Zoom box shows as before.
 * The alumni box is for visitors who are not signed in. Logged-in pages are not cached,
 * and a cached page is always a signed-out one, so the box is printed here, not by script.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ner_michoel_home_banner_public' ) ) {
	get_template_part( 'template-parts/live-shiur' );
	return;
}

$nm_notices = ner_michoel_home_notices();

echo ner_michoel_zoom_line_html( $nm_notices['zoom'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
echo ner_michoel_notices_html( 'home' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
