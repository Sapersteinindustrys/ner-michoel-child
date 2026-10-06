<?php
/**
 * Shiurim home. Three layouts, toggled top-right:
 * - Modern (default): Spotify-style grid of Series/Speakers.
 * - Classic: a plain filterable/sortable list of every shiur.
 * - 24Six: the same Series/Speakers browsing, as horizontal-scrolling
 *   carousel rows instead of a wrapping grid.
 *
 * Cards don't carry their queues. Each play button has its first track, and
 * the full queue loads on click (ner-michoel-core includes/shiur-queue.php). The
 * counts and first tracks come in one query per list, not one per card.
 */

get_header();

$layout = ner_michoel_get_layout();

if ( 'classic' === $layout ) :
	$classic_query = ner_michoel_get_classic_query();
	?>
	<div class="shiurim-app shiurim-app--classic">
			<?php ner_michoel_render_back_button(); ?>
		<header class="sh-page-header">
			<h1><?php post_type_archive_title(); ?></h1>
		</header>
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

// Lazy queues need the core helpers. Without them (an older ner-michoel-core) the
// cards fall back to carrying their queues in the page, as before.
$lazy_queues   = function_exists( 'ner_michoel_queue_url' );
$series_counts = $lazy_queues && $has_series ? ner_michoel_term_shiur_counts( 'series' ) : array();
$series_first  = $lazy_queues && $has_series ? ner_michoel_first_track_by_term( 'series', wp_list_pluck( $series_terms, 'term_id' ), false ) : array();
$speaker_first = $lazy_queues && $has_speakers ? ner_michoel_first_track_by_term( 'speaker', wp_list_pluck( $speaker_terms, 'term_id' ), true ) : array();
?>

<div class="sh-layout-with-sidebar">
	<?php ner_michoel_render_shiurim_sidebar(); ?>

	<div class="shiurim-app sh-main">
			<?php ner_michoel_render_back_button(); ?>
		<header class="sh-page-header">
			<h1><?php echo $is_recent ? esc_html__( 'Recent Shiurim', 'ner-michoel-child' ) : post_type_archive_title( '', false ); ?></h1>
		</header>

		<?php if ( $is_recent ) : ?>
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
						// Its own queue starts with this shiur, so the first track is this shiur.
						$queue_args = $lazy_queues
							? array(
								'queue'     => ner_michoel_build_track_queue( array( $shiur ) ),
								'queue_url' => ner_michoel_queue_url(
									array(
										'shiur' => $shiur->ID,
										'mode'  => 'series',
									)
								),
							)
							: array( 'queue' => ner_michoel_build_track_queue( ner_michoel_series_rest_for_shiur( $shiur ) ) ); // a series shiur plays the rest of its series in order
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

			<?php if ( $has_series ) : ?>
			<section class="sh-section">
				<h2 class="sh-section__title"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></h2>
				<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
					<?php foreach ( $series_terms as $term ) :
						if ( $lazy_queues ) {
							$series_count = isset( $series_counts[ $term->term_id ] ) ? $series_counts[ $term->term_id ] : 0;
							$queue_args   = array(
								'queue'     => isset( $series_first[ $term->term_id ] ) ? $series_first[ $term->term_id ] : array(),
								'queue_url' => ner_michoel_queue_url( array( 'series' => $term->term_id ) ),
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
							$queue_args = array(
								'queue'     => isset( $speaker_first[ $term->term_id ] ) ? $speaker_first[ $term->term_id ] : array(),
								'queue_url' => ner_michoel_queue_url( array( 'speaker' => $term->term_id ) ),
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
