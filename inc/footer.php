<?php
/**
 * The site footer: the title, links to the pages, the Contribute button and the
 * Amazon Smile link that an administrator sets in the Control Panel (Homepage &
 * Look > Footer; ner-michoel-core, includes/footer-settings.php). Styles in
 * assets/css/footer.css.
 *
 * It is printed on every page, just before Astra's own footer (#colophon), and
 * shown by CSS from the body class: the page router (nm-router.js) swaps only
 * #content, so something printed outside it can't be added or taken away when a
 * visitor clicks to another page. Where it shows (the home page only, or every page
 * except the Shiurim pages) is a class on the footer itself, chosen on the screen,
 * and it takes Astra's footer's place there. Everywhere else Astra's footer stays.
 *
 * Needs ner-michoel-core's ner_michoel_get_footer(): without it (the plugin is off or
 * behind) nothing is printed and Astra's footer stays, so either package can be
 * updated first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The footer as the plugin hands it over, or null when it should not be printed
 * (the plugin has no footer yet, or an administrator switched it off). Worked out
 * once per page.
 */
function ner_michoel_site_footer_data() {
	static $data = false;
	if ( false === $data ) {
		$footer = function_exists( 'ner_michoel_get_footer' ) ? ner_michoel_get_footer() : null;
		$data   = ( is_array( $footer ) && ! empty( $footer['on'] ) ) ? $footer : null;
	}
	return $data;
}

function ner_michoel_enqueue_footer_assets() {
	if ( ner_michoel_site_footer_data() ) {
		wp_enqueue_style( 'ner-michoel-footer', NER_MICHOEL_URI . '/assets/css/footer.css', array( 'ner-michoel-custom' ), NER_MICHOEL_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_footer_assets', 23 );

/**
 * One link of the footer. An address that leaves the site opens in a new tab.
 *
 * @param array  $link  label, url, external.
 * @param string $class The link's CSS class(es).
 */
function ner_michoel_footer_link_html( $link, $class ) {
	if ( ! is_array( $link ) ) {
		return '';
	}
	$label = isset( $link['label'] ) ? (string) $link['label'] : '';
	// An address WordPress won't allow in a link (javascript:, say) comes back empty: no link at all.
	$href = esc_url( isset( $link['url'] ) ? (string) $link['url'] : '' );
	if ( '' === $label || '' === $href ) {
		return '';
	}
	$external = ! empty( $link['external'] );

	return sprintf(
		'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
		esc_attr( $class ),
		$href,
		$external ? ' target="_blank" rel="noopener noreferrer"' : '',
		esc_html( $label )
	);
}

function ner_michoel_render_site_footer() {
	$footer = ner_michoel_site_footer_data();
	if ( ! $footer ) {
		return;
	}

	$scope = ( isset( $footer['scope'] ) && 'all' === $footer['scope'] ) ? 'all' : 'home';

	$buttons = '';
	if ( ! empty( $footer['contribute'] ) ) {
		$buttons .= ner_michoel_footer_link_html( $footer['contribute'], 'nm-footer__btn nm-footer__btn--primary' );
	}
	if ( ! empty( $footer['smile'] ) ) {
		$buttons .= ner_michoel_footer_link_html( $footer['smile'], 'nm-footer__btn' );
	}

	$links = '';
	if ( ! empty( $footer['links'] ) && is_array( $footer['links'] ) ) {
		foreach ( $footer['links'] as $link ) {
			$html = ner_michoel_footer_link_html( $link, 'nm-footer__link' );
			if ( '' !== $html ) {
				$links .= '<li>' . $html . '</li>';
			}
		}
	}

	$title = isset( $footer['title'] ) ? (string) $footer['title'] : '';
	$note  = isset( $footer['note'] ) ? (string) $footer['note'] : '';

	// Nothing to say (everything emptied or switched off): print no shell, and Astra's own footer stays.
	if ( '' === $title && '' === $buttons && '' === $links && '' === $note ) {
		return;
	}
	?>
	<footer class="nm-footer nm-footer--<?php echo esc_attr( $scope ); ?>" id="nm-footer">
		<div class="nm-footer__inner">
			<?php if ( '' !== $title || '' !== $buttons ) : ?>
				<div class="nm-footer__top">
					<?php if ( '' !== $title ) : ?>
						<p class="nm-footer__title"><?php echo esc_html( $title ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $buttons ) : ?>
						<div class="nm-footer__actions"><?php echo $buttons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each link is built and escaped in ner_michoel_footer_link_html(). ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $links ) : ?>
				<nav class="nm-footer__nav" aria-label="<?php esc_attr_e( 'Footer', 'ner-michoel-child' ); ?>">
					<ul class="nm-footer__links"><?php echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each link is built and escaped in ner_michoel_footer_link_html(). ?></ul>
				</nav>
			<?php endif; ?>
			<?php if ( '' !== $note ) : ?>
				<p class="nm-footer__note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
		</div>
	</footer>
	<?php
}
add_action( 'astra_footer_before', 'ner_michoel_render_site_footer' );
