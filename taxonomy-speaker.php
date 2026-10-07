<?php
/**
 * A speaker's page. Three layouts, toggled top-right:
 * - Modern (default): Spotify "Artist" page equivalent.
 * - Classic: a plain filterable/sortable list of this speaker's shiurim.
 * - 24Six: same page, the Series section becomes a horizontal-
 *   scrolling carousel row instead of a wrapping grid.
 */

get_header();

$term = get_queried_object();

if ( 'classic' === ner_michoel_get_layout() ) :
	$classic_query = ner_michoel_get_classic_query( array( 'speaker_term' => $term->term_id ) );
	?>
	<div class="shiurim-app shiurim-app--classic">
			<?php ner_michoel_render_return_link(); ?>
		<header class="sh-page-header">
			<h1><?php echo esc_html( $term->name ); ?></h1>
		</header>
		<?php ner_michoel_render_classic_table( $classic_query, false, true ); ?>
		<?php ner_michoel_render_classic_pagination( $classic_query ); ?>
	</div>
	<?php
	get_footer();
	return;
endif;

$series      = ner_michoel_get_speaker_series( $term->term_id );
$standalone  = ner_michoel_get_speaker_standalone_shiurim( $term->term_id );
$image       = ner_michoel_get_speaker_photo_url( $term->term_id );
$is_carousel = '24six' === ner_michoel_get_layout();

// The series cards: each one's count and first track come in one query for all of them,
// and its full queue loads when its play button is pressed (see archive-shiur.php). A card
// for a series with no audio gets no play button.
$lazy_queues      = function_exists( 'ner_michoel_queue_url' );
$series_counts    = $lazy_queues && $series ? ner_michoel_term_shiur_counts( 'series' ) : array();
$series_first     = $lazy_queues && $series ? ner_michoel_first_track_by_term( 'series', wp_list_pluck( $series, 'term_id' ), false ) : array();
$series_playable  = $lazy_queues && $series && function_exists( 'ner_michoel_term_playable_counts' ) ? ner_michoel_term_playable_counts( 'series' ) : null;
?>

<div class="shiurim-app">
	<?php ner_michoel_render_return_link(); ?>
	<header class="sh-hero sh-hero--round">
		<div class="sh-hero__art">
			<?php if ( $image ) : ?>
				<img src="<?php echo esc_url( $image ); ?>" alt="" />
			<?php else : ?>
				<?php echo ner_michoel_placeholder_art( $term->name ); ?>
			<?php endif; ?>
		</div>
		<div class="sh-hero__info">
			<span class="sh-hero__kicker"><?php esc_html_e( 'Speaker', 'ner-michoel-child' ); ?></span>
			<h1><?php echo esc_html( $term->name ); ?></h1>
			<?php if ( $term->description ) : ?>
				<p class="sh-hero__desc"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $series ) : ?>
	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></h2>
		<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
			<?php foreach ( $series as $s ) :
				if ( $lazy_queues ) {
					$s_count          = isset( $series_counts[ $s->term_id ] ) ? $series_counts[ $s->term_id ] : 0;
					$s_has_audio      = null === $series_playable || ! empty( $series_playable[ $s->term_id ] );
					$queue_args       = array(
						'queue'     => isset( $series_first[ $s->term_id ] ) ? $series_first[ $s->term_id ] : array(),
						'queue_url' => $s_has_audio ? ner_michoel_queue_url( array( 'series' => $s->term_id ) ) : '',
					);
				} else {
					$s_shiurim  = ner_michoel_get_series_shiurim( $s->term_id );
					$s_count    = count( $s_shiurim );
					$queue_args = array( 'queue' => ner_michoel_build_track_queue( $s_shiurim ) );
				}
				ner_michoel_render_media_card(
					array_merge(
						array(
							'title'    => $s->name,
							'subtitle' => sprintf(
								/* translators: %d: number of shiurim */
								_n( '%d shiur', '%d shiurim', $s_count, 'ner-michoel-child' ),
								$s_count
							),
							'image'    => ner_michoel_get_series_cover_url( $s->term_id ),
							'link'     => get_term_link( $s ),
						),
						$queue_args
					)
				);
			endforeach; ?>
		<?php ner_michoel_render_card_collection_end( $is_carousel ); ?>
	</section>
	<?php endif; ?>

	<?php if ( $standalone ) : ?>
	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'Shiurim', 'ner-michoel-child' ); ?></h2>
		<?php ner_michoel_render_tracklist( $standalone, false ); ?>
	</section>
	<?php endif; ?>

	<?php if ( ! $series && ! $standalone ) : ?>
		<p class="sh-empty"><?php esc_html_e( 'No shiurim from this speaker yet.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
