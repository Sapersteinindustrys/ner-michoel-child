<?php
/**
 * Shiurim home, in Studio's look: the home page itself
 * (template-parts/shiurim-studio.php), and its two other views, "All recent"
 * (?sh_view=recent) and "For you" (?sh_view=foryou), which are the pages below.
 *
 * Cards don't carry their queues. Each play button has its first track, and
 * the full queue loads on click (ner-michoel-core includes/shiur-queue.php). The
 * counts, first tracks and "has audio" checks come in one query per list, not one
 * per card. A card for something with no audio gets no play button.
 */

get_header();

$is_foryou = ner_michoel_is_for_you_view(); // ?sh_view=foryou: the signed-in listener's own page (inc/for-you.php).
$is_recent = isset( $_GET['sh_view'] ) && 'recent' === $_GET['sh_view']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( ! $is_recent && ! $is_foryou ) {
	get_template_part( 'template-parts/shiurim-studio' );
	get_footer();
	return;
}

$series_terms = get_terms( array( 'taxonomy' => 'series', 'hide_empty' => true ) );
$has_series   = ! is_wp_error( $series_terms ) && $series_terms;

// Lazy queues need the core helpers. Without them (an older ner-michoel-core) the
// cards fall back to carrying their queues in the page, as before.
$lazy_queues     = function_exists( 'ner_michoel_queue_url' );
// Which series have any audio to play. Null when that count isn't there (an older
// core): the cards then keep their play button, and the page's fallback covers an empty list.
$series_playable = $lazy_queues && $has_series && function_exists( 'ner_michoel_term_playable_counts' ) ? ner_michoel_term_playable_counts( 'series' ) : null;
?>

<div class="sh-layout-with-sidebar">
	<?php ner_michoel_render_shiurim_sidebar(); ?>

	<div class="shiurim-app sh-main">
			<?php ner_michoel_render_back_button(); ?>
		<header class="sh-page-header">
			<?php
			if ( $is_foryou ) {
				$page_heading = __( 'For you', 'ner-michoel-child' );
			} else {
				$page_heading = __( 'Recent Shiurim', 'ner-michoel-child' );
			}
			?>
			<h1><?php echo esc_html( $page_heading ); ?></h1>
		</header>

		<?php if ( $is_foryou ) : ?>
			<?php ner_michoel_render_for_you_page( false ); ?>
		<?php else : ?>
			<?php $recent = function_exists( 'ner_michoel_get_recent_shiurim' ) ? ner_michoel_get_recent_shiurim( 24 ) : array(); ?>
			<?php
			if ( $lazy_queues && $recent ) {
				ner_michoel_prime_shiur_caches( wp_list_pluck( $recent, 'ID' ) );
			}
			?>
			<?php if ( $recent ) : ?>
			<section class="sh-section">
				<?php ner_michoel_render_card_collection_start( false ); ?>
					<?php foreach ( $recent as $shiur ) :
						$speaker_terms_for_shiur = get_the_terms( $shiur->ID, 'speaker' );
						$speaker_name            = ( $speaker_terms_for_shiur && ! is_wp_error( $speaker_terms_for_shiur ) ) ? $speaker_terms_for_shiur[0]->name : '';
						if ( $lazy_queues ) {
							// Its own queue starts with this shiur. It can play if this shiur has audio, or
							// the rest of its series does.
							$own_track        = ner_michoel_build_track_queue( array( $shiur ) );
							$recent_series    = get_the_terms( $shiur->ID, 'series' );
							$recent_series_id = ( $recent_series && ! is_wp_error( $recent_series ) ) ? (int) $recent_series[0]->term_id : 0;
							$rest_may_play    = null === $series_playable || ( $recent_series_id && ! empty( $series_playable[ $recent_series_id ] ) );
							$queue_args       = array(
								'queue'     => $own_track,
								'queue_url' => ( $own_track || $rest_may_play ) ? ner_michoel_queue_url(
									array(
										'shiur' => $shiur->ID,
										'mode'  => 'series',
									)
								) : '',
							);
						} else {
							$queue_args = array( 'queue' => ner_michoel_build_track_queue( ner_michoel_series_rest_for_shiur( $shiur ) ) ); // a series shiur plays the rest of its series in order
						}
						ner_michoel_render_media_card(
							array_merge(
								array(
									'title'    => get_the_title( $shiur ),
									'subtitle' => $speaker_name,
									'image'    => get_the_post_thumbnail_url( $shiur, 'medium' ),
									'link'     => get_permalink( $shiur ),
									'save_id'  => $shiur->ID,
								),
								$queue_args
							)
						);
					endforeach; ?>
				<?php ner_michoel_render_card_collection_end( false ); ?>
			</section>
			<?php else : ?>
				<p class="sh-empty"><?php esc_html_e( 'No shiurim yet.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
