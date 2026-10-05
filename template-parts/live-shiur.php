<?php
/**
 * Live Shiur block: the Zoom link, meeting ID and schedule set in Site
 * Control Panel > Live Shiur / Zoom. Used on the homepage and the News &
 * Events page.
 *
 * Renders nothing until a Zoom link or a schedule is set, so visitors
 * never see an empty panel. Included with:
 *     get_template_part( 'template-parts/live-shiur' );
 */

if ( ! function_exists( 'ner_michoel_live_shiur_is_set' ) || ! ner_michoel_live_shiur_is_set() ) {
	return;
}

$live = ner_michoel_get_live_shiur();
?>
<section class="nm-live-shiur" aria-labelledby="nm-live-shiur-title">
	<div class="nm-live-shiur__body">
		<span class="nm-live-shiur__kicker"><?php esc_html_e( 'Live Shiur', 'ner-michoel-child' ); ?></span>
		<h2 id="nm-live-shiur-title" class="nm-live-shiur__title"><?php esc_html_e( 'Join us on Zoom', 'ner-michoel-child' ); ?></h2>
		<?php if ( '' !== $live['schedule'] ) : ?>
			<p class="nm-live-shiur__schedule"><?php echo nl2br( esc_html( $live['schedule'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $live['meeting_id'] ) : ?>
			<p class="nm-live-shiur__meeting">
				<?php esc_html_e( 'Meeting ID', 'ner-michoel-child' ); ?>: <strong><?php echo esc_html( $live['meeting_id'] ); ?></strong>
			</p>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $live['zoom_link'] ) : ?>
		<a class="nm-live-shiur__join" href="<?php echo esc_url( $live['zoom_link'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join on Zoom', 'ner-michoel-child' ); ?> &rarr;</a>
	<?php endif; ?>
</section>
