<?php
/**
 * Reads admin-set appearance options (once the Site Control Panel
 * grows color/font pickers — see dev-notes.md) and turns them into a
 * small CSS override block, so the dark app system (Shiurim,
 * Galleries, News & Events, the player bar) can be recolored/refonted
 * without touching custom.css.
 *
 * Each option is independent and optional — only the ones an admin
 * has actually set get output, everything else keeps its default
 * from custom.css's body.is-nm-app rule. Safe to load even if none of
 * these options exist yet (nothing renders, no error).
 *
 * Option names (get_option), matching the --sh-* custom property
 * each one overrides on body.is-nm-app:
 * - nm_color_bg      -> --sh-bg
 * - nm_color_surface -> --sh-surface
 * - nm_color_text    -> --sh-text
 * - nm_color_accent  -> --sh-accent
 * - nm_font_family   -> --sh-font
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ner_michoel_render_appearance_overrides() {
	$map = array(
		'nm_color_bg'      => '--sh-bg',
		'nm_color_surface' => '--sh-surface',
		'nm_color_text'    => '--sh-text',
		'nm_color_accent'  => '--sh-accent',
		'nm_font_family'   => '--sh-font',
	);

	$declarations = array();
	foreach ( $map as $option => $css_var ) {
		$value = get_option( $option, '' );
		if ( '' === $value ) {
			continue;
		}
		// Colors get sanitized as hex; the font option is free text
		// (a CSS font-family value/stack), so it only gets basic
		// tag-stripping rather than a color-shaped sanitizer.
		$is_color = '--sh-font' !== $css_var;
		$safe     = $is_color ? sanitize_hex_color( $value ) : wp_strip_all_tags( $value );
		if ( '' === $safe || null === $safe ) {
			continue;
		}
		$declarations[] = $css_var . ': ' . $safe . ';';
	}

	if ( empty( $declarations ) ) {
		return;
	}
	?>
	<style id="ner-michoel-appearance-overrides">
		body.is-nm-app { <?php echo implode( ' ', $declarations ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value passed through sanitize_hex_color()/wp_strip_all_tags() above. ?> }
	</style>
	<?php
}
add_action( 'wp_head', 'ner_michoel_render_appearance_overrides', 20 );
