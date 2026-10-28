<?php
/**
 * A speaker's page: the speaker's series as swipeable rows of cards, then
 * the shiurim that are in no series as a track list.
 *
 * The series come in two groups (ner_michoel_get_speaker_series_groups(),
 * inc/speaker-series.php), each in the old site's category order: the series
 * that are all this speaker's, then "Series with other speakers", whose cards
 * say how many shiurim are this speaker's and who else is in the series.
 */

get_header();

$term = get_queried_object();

$groups      = ner_michoel_get_speaker_series_groups( $term->term_id );
$standalone  = ner_michoel_get_speaker_standalone_shiurim( $term->term_id );
$image       = ner_michoel_get_speaker_photo_url( $term->term_id );
// Swipeable rows of series cards.
$is_carousel = true;

// The series, in two groups: the ones that are all this speaker's, then the ones that also
// hold other speakers' shiurim (inc/speaker-series.php).
$all_series    = array_merge( $groups['own'], $groups['shared'] );
$series_groups = array(
	array(
		'key'     => 'own',
		'title'   => __( 'Series', 'ner-michoel-child' ),
		'note'    => '',
		'entries' => $groups['own'],
	),
	array(
		'key'     => 'shared',
		'title'   => __( 'Series with other speakers', 'ner-michoel-child' ),
		/* translators: %s: the speaker's name */
		'note'    => sprintf( __( 'These series also have shiurim by other speakers. Each card shows how many are by %s.', 'ner-michoel-child' ), html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ),
		'entries' => $groups['shared'],
	),
);

// The series cards: each one's first track comes in one query for all of them, and its
// full queue loads when its play button is pressed (see archive-shiur.php). A card for a
// series with no audio gets no play button. A card's counts come with its group.
$lazy_queues      = function_exists( 'ner_michoel_queue_url' );
$series_first     = $lazy_queues && $all_series ? ner_michoel_first_track_by_term( 'series', wp_list_pluck( wp_list_pluck( $all_series, 'term' ), 'term_id' ), false ) : array();
$series_playable  = $lazy_queues && $all_series && function_exists( 'ner_michoel_term_playable_counts' ) ? ner_michoel_term_playable_counts( 'series' ) : null;
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

	<?php foreach ( $series_groups as $series_group ) : ?>
	<?php if ( $series_group['entries'] ) : ?>
	<section class="sh-section" data-series-group="<?php echo esc_attr( $series_group['key'] ); ?>">
		<h2 class="sh-section__title"><?php echo esc_html( $series_group['title'] ); ?></h2>
		<?php if ( $series_group['note'] ) : ?>
			<p class="sh-section__note"><?php echo esc_html( $series_group['note'] ); ?></p>
		<?php endif; ?>
		<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
			<?php foreach ( $series_group['entries'] as $entry ) :
				$s = $entry['term'];
				if ( $lazy_queues ) {
					$s_has_audio = null === $series_playable || ! empty( $series_playable[ $s->term_id ] );
					$queue_args  = array(
						'queue'     => isset( $series_first[ $s->term_id ] ) ? $series_first[ $s->term_id ] : array(),
						'queue_url' => $s_has_audio ? ner_michoel_queue_url( array( 'series' => $s->term_id ) ) : '',
					);
				} else {
					$queue_args = array( 'queue' => ner_michoel_build_track_queue( ner_michoel_get_series_shiurim( $s->term_id ) ) );
				}
				ner_michoel_render_media_card(
					array_merge(
						array(
							'title'    => $s->name,
							'subtitle' => ner_michoel_speaker_series_count_label( $entry ),
							'tags'     => ner_michoel_speaker_series_tags( $entry ),
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
	<?php endforeach; ?>

	<?php if ( $standalone ) : ?>
	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'Shiurim', 'ner-michoel-child' ); ?></h2>
		<?php ner_michoel_render_tracklist( $standalone, false ); ?>
	</section>
	<?php endif; ?>

	<?php if ( ! $all_series && ! $standalone ) : ?>
		<p class="sh-empty"><?php esc_html_e( 'No shiurim from this speaker yet.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
