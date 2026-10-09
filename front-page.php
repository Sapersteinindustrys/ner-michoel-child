<?php
/**
 * The homepage: template-parts/home-new.php (data in inc/home.php, styles and
 * script in assets/css/home.css and assets/js/home.js). Friendly and welcoming
 * rather than a dense content dashboard, so it's its own light style (.hn):
 * not the dark Modern app, not the plain Classic library, and not toggled, since
 * a landing page doesn't benefit from the Modern / Classic / 24Six / Studio split
 * of browsable content.
 *
 * Only renders if the admin sets a static front page in
 * Settings > Reading — WP falls back to home.php/index.php otherwise.
 *
 * This used to hold a second design, "Current", with a Current / New switch in
 * the page's top-right corner. The new design replaced it for good; the old
 * template is in the git history (child theme v0.2.63 and earlier).
 */

get_header();

get_template_part( 'template-parts/home-new' );

get_footer();
