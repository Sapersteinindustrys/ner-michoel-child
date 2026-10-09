<?php
/**
 * Shiurim home. Four layouts, toggled top-right:
 * - Studio (default): the new homepage's look, with a Shiurim home of its own
 *   (template-parts/shiurim-studio.php).
 * - Modern: Spotify-style grid of Series/Speakers.
 * - Classic: a plain filterable/sortable list of every shiur.
 * - 24Six: the same Series/Speakers browsing, as horizontal-scrolling
 *   carousel rows instead of a wrapping grid.
 *
 * Cards don't carry their queues. Each play button has its first track, and
 * the full queue loads on click (ner-michoel-core includes/shiur-queue.php). The
 * counts, first tracks and "has audio" checks come in one query per list, not one
 * per card. A card for something with no audio gets no play button.
 */

get_header();

$layout    = ner_michoel_get_layout();
$is_foryou = ner_michoel_is_for_you_view(); // ?sh_view=foryou: the signed-in listener's own page (inc/for-you.php).

if ( 'classic' === $layout ) :
	// For you is a page of its own in Classic too: a plain list, not the table.
	if ( $is_foryou ) :
		?>
		<div class="shiurim-app shiurim-app--classic">
			<?php ner_michoel_render_back_button(); ?>
			<header class="sh-page-header">
				<h1><?php esc_html_e( 'For you', 'ner-michoel-child' ); ?></h1>
			</header>
			<?php ner_michoel_render_for_you_classic_page(); ?>
		</div>
		<?php
		get_footer();
		return;
	endif;

	$classic_query = ner_michoel_get_classic_query();
	?>
	<div class="shiurim-app shiurim-app--classic">
			<?php ner_michoel_render_back_button(); ?>
		<header class="sh-page-header">
			<h1><?php post_type_archive_title(); ?></h1>
		</header>
		<?php ner_michoel_render_for_you_classic(); ?>
		<?php ner_michoel_render_classic_toolbar(); ?>
		<?php ner_michoel_render_classic_table( $classic_query ); ?>
		<?php ner_michoel_render_classic_pagination( $classic_query ); ?>
	</div>
	<?php
	get_footer();
	return;
endif;

$series_terms  = get_terms( array( 'taxonomy' => 'series', 'hide_empty' => true ) );
$speaker_terms = get_terms( array( 'taxonomy' => 'speaker', 'hide_empty' => true ) );
$has_series    = ! is_wp_error( $series_terms ) && $series_terms;
$has_speakers  = ! is_wp_error( $speaker_terms ) && $speaker_terms;
$is_carousel   = '24six' === $layout;
$is_recent     = isset( $_GET['sh_view'] ) && 'recent' === $_GET['sh_view']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Studio has a Shiurim home of its own (template-parts/shiurim-studio.php). Its
// "All recent" and "For you" views are the pages below, in Studio's colours.
if ( 'studio' === $layout && ! $is_recent && ! $is_foryou ) {
	get_template_part( 'template-parts/shiurim-studio' );
	get_footer();
	return;
}

// Lazy queues need the core helpers. Without them (an older ner-michoel-core) the
// cards fall back to carrying their queues in the page, as before.
$lazy_queues      = function_exists( 'ner_michoel_queue_url' );
$series_counts    = $lazy_queues && $has_series ? ner_michoel_term_shiur_counts( 'series' ) : array();
$series_first     = $lazy_queues && $has_series ? ner_michoel_first_track_by_term( 'series', wp_list_pluck( $series_terms, 'term_id' ), false ) : array();
$speaker_first    = $lazy_queues && $has_speakers ? ner_michoel_first_track_by_term( 'speaker', wp_list_pluck( $speaker_terms, 'term_id' ), true ) : array();
// Which terms have any audio to play. Null when that count isn't there (an older
// core): the cards then keep their play button, and the page's fallback covers an empty list.
$series_playable  = $lazy_queues && $has_series && function_exists( 'ner_michoel_term_playable_counts' ) ? ner_michoel_term_playable_counts( 'series' ) : null;
$speaker_playable = $lazy_queues && $has_speakers && function_exists( 'ner_michoel_term_playable_counts' ) ? ner_michoel_term_playable_counts( 'speaker' ) : null;
?>

<div class="sh-layout-with-sidebar">
	<?php ner_michoel_render_shiurim_sidebar(); ?>

	<div class="shiurim-app sh-main">
			<?php ner_michoel_render_back_button(); ?>
		<header class="sh-page-header">
			<?php
			if ( $is_foryou ) {
				$page_heading = __( 'For you', 'ner-michoel-child' );
			} elseif ( $is_recent ) {
				$page_heading = __( 'Recent Shiurim', 'ner-michoel-child' );
			} else {
				$page_heading = post_type_archive_title( '', false );
			}
			?>
			<h1><?php echo esc_html( $page_heading ); ?></h1>
		</header>

		<?php if ( $is_foryou ) : ?>
			<?php ner_michoel_render_for_you_page( $is_carousel ); ?>
		<?php elseif ( $is_recent ) : ?>
			<?php $recent = function_exists( 'ner_michoel_get_recent_shiurim' ) ? ner_michoel_get_recent_shiurim( 24 ) : array(); ?>
			<?php
			if ( $lazy_queues && $recent ) {
				ner_michoel_prime_shiur_caches( wp_list_pluck( $recent, 'ID' ) );
			}
			?>
			<?php if ( $recent ) : ?>
			<section class="sh-section">
				<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
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
				<?php ner_michoel_render_card_collection_end( $is_carousel ); ?>
			</section>
			<?php else : ?>
				<p class="sh-empty"><?php esc_html_e( 'No shiurim yet.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>
		<?php else : ?>

			<?php ner_michoel_render_for_you_shelf(); ?>

			<?php if ( $has_series ) : ?>
			<section class="sh-section">
				<h2 class="sh-section__title"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></h2>
				<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
					<?php foreach ( $series_terms as $term ) :
						if ( $lazy_queues ) {
							$series_count     = isset( $series_counts[ $term->term_id ] ) ? $series_counts[ $term->term_id ] : 0;
							$series_has_audio = null === $series_playable || ! empty( $series_playable[ $term->term_id ] );
							$queue_args       = array(
								'queue'     => isset( $series_first[ $term->term_id ] ) ? $series_first[ $term->term_id ] : array(),
								'queue_url' => $series_has_audio ? ner_michoel_queue_url( array( 'series' => $term->term_id ) ) : '',
							);
						} else {
							$shiurim      = ner_michoel_get_series_shiurim( $term->term_id );
							$series_count = count( $shiurim );
							$queue_args   = array( 'queue' => ner_michoel_build_track_queue( $shiurim ) );
						}
						ner_michoel_render_media_card(
							array_merge(
								array(
									'title'    => $term->name,
									'subtitle' => sprintf(
										/* translators: %d: number of shiurim */
										_n( '%d shiur', '%d shiurim', $series_count, 'ner-michoel-child' ),
										$series_count
									),
									'image'    => ner_michoel_get_series_cover_url( $term->term_id ),
									'link'     => get_term_link( $term ),
									'variant'  => 'series',
									'kicker'   => __( 'Series', 'ner-michoel-child' ),
								),
								$queue_args
							)
						);
					endforeach; ?>
				<?php ner_michoel_render_card_collection_end( $is_carousel ); ?>
			</section>
			<?php endif; ?>

			<?php if ( $has_speakers ) : ?>
			<section class="sh-section">
				<h2 class="sh-section__title"><?php esc_html_e( 'Speakers', 'ner-michoel-child' ); ?></h2>
				<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
					<?php foreach ( $speaker_terms as $term ) :
						if ( $lazy_queues ) {
							$speaker_has_audio = null === $speaker_playable || ! empty( $speaker_playable[ $term->term_id ] );
							$queue_args        = array(
								'queue'     => isset( $speaker_first[ $term->term_id ] ) ? $speaker_first[ $term->term_id ] : array(),
								'queue_url' => $speaker_has_audio ? ner_michoel_queue_url( array( 'speaker' => $term->term_id ) ) : '',
							);
						} else {
							$queue_args = array( 'queue' => ner_michoel_build_track_queue( ner_michoel_get_speaker_shiurim( $term->term_id ) ) );
						}
						ner_michoel_render_media_card(
							array_merge(
								array(
									'title'    => $term->name,
									'subtitle' => __( 'Speaker', 'ner-michoel-child' ),
									'image'    => ner_michoel_get_speaker_photo_url( $term->term_id ),
									'link'     => get_term_link( $term ),
									'round'    => true,
								),
								$queue_args
							)
						);
					endforeach; ?>
				<?php ner_michoel_render_card_collection_end( $is_carousel ); ?>
			</section>
			<?php endif; ?>

			<?php if ( ! $has_series && ! $has_speakers ) : ?>
				<p class="sh-empty"><?php esc_html_e( 'No shiurim yet — add a Speaker and Series, then upload the first shiur.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>

		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
