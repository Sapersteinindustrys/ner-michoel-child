<?php
/**
 * The player's sheet: the autoplay switch and the queue. It slides up from
 * the player bar when the bar is pressed (or its up-arrow button). Behaviour
 * is in assets/js/custom.js (the player block), and styles are in
 * assets/css/player-sheet.css.
 *
 * It's printed with the bar (ner_michoel_render_player_bar() in
 * inc/template-tags.php), so it appears on exactly the pages the bar does.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The sheet's markup. Its now-playing details and the queue are filled in by
 * custom.js, so the server sends only the structure.
 *
 * $layout is the bar's: the sheet and its backdrop take the same look
 * (sh-sheet--stream and so on, assets/css/player-sheet.css).
 */
function ner_michoel_render_player_sheet( $layout = '' ) {
	$layout = sanitize_key( $layout );
	?>
	<div class="sh-sheet-backdrop<?php echo $layout ? ' sh-sheet-backdrop--' . esc_attr( $layout ) : ''; ?>" id="sh-sheet-backdrop" aria-hidden="true"></div>
	<div class="sh-sheet<?php echo $layout ? ' sh-sheet--' . esc_attr( $layout ) : ''; ?>" id="sh-sheet" role="dialog" aria-labelledby="sh-sheet-heading" aria-hidden="true">
		<div class="sh-sheet__grip" aria-hidden="true"></div>

		<div class="sh-sheet__head">
			<div class="sh-sheet__now">
				<div class="sh-sheet__cover" id="sh-sheet-cover" aria-hidden="true"></div>
				<div class="sh-sheet__now-text">
					<div class="sh-sheet__eyebrow" id="sh-sheet-heading"><?php esc_html_e( 'Now playing', 'ner-michoel-child' ); ?></div>
					<div class="sh-sheet__now-title" id="sh-sheet-now-title"></div>
					<div class="sh-sheet__now-speaker" id="sh-sheet-now-speaker"></div>
				</div>
			</div>
			<button type="button" class="sh-sheet__close" id="sh-sheet-close" aria-label="<?php esc_attr_e( 'Close', 'ner-michoel-child' ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
		</div>

		<label class="sh-sheet__autoplay">
			<span class="sh-sheet__autoplay-text">
				<strong><?php esc_html_e( 'Autoplay', 'ner-michoel-child' ); ?></strong>
				<span><?php esc_html_e( 'Play the next shiur in the queue when this one ends', 'ner-michoel-child' ); ?></span>
			</span>
			<input type="checkbox" class="sh-sheet__switch-input" id="sh-autoplay" role="switch" checked />
			<span class="sh-sheet__switch" aria-hidden="true"></span>
		</label>

		<h3 class="sh-sheet__queue-title"><?php esc_html_e( 'Queue', 'ner-michoel-child' ); ?></h3>
		<ol class="sh-sheet__queue" id="sh-sheet-queue"></ol>
	</div>
	<?php
}

/**
 * Stylesheet for the sheet. Loaded on every page; it's only ever shown where
 * the player bar is.
 */
function ner_michoel_enqueue_player_sheet_styles() {
	wp_enqueue_style(
		'ner-michoel-player-sheet',
		NER_MICHOEL_URI . '/assets/css/player-sheet.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_player_sheet_styles', 21 );
