<?php
/**
 * A topic's page: every shiur tagged with it (ner-michoel-core includes/topics.php).
 * Layouts as on the series page:
 * - Modern / 24Six: a header with Play All, the topic's shiurim as a track list
 *   (40 a page, newest first: some topics have over a thousand), then on the
 *   first page its written shiurim, as cards.
 * - Classic: the plain table, paged the same way.
 */

get_header();

global $wp_query;
$term = get_queried_object();

if ( 'classic' === ner_michoel_get_layout() ) :
	?>
	<div class="shiurim-app shiurim-app--classic">
		<?php ner_michoel_render_return_link(); ?>
		<header class="sh-page-header">
			<h1><?php echo esc_html( $term->name ); ?></h1>
		</header>
		<?php ner_michoel_render_classic_table( $wp_query, true, true ); ?>
		<?php ner_michoel_render_classic_pagination( $wp_query ); ?>
	</div>
	<?php
	get_footer();
	return;
endif;

$shiurim     = $wp_query->posts;
if ( $shiurim && function_exists( 'ner_michoel_prime_shiur_caches' ) ) {
	// The track list builds a track for each shiur: load their data in a few queries first.
	ner_michoel_prime_shiur_caches( wp_list_pluck( $shiurim, 'ID' ) );
}
$is_first    = max( 1, (int) get_query_var( 'paged' ) ) === 1;
$is_carousel = '24six' === ner_michoel_get_layout();
$label       = function_exists( 'ner_michoel_topic_season_label' ) ? ner_michoel_topic_season_label( $term->term_id ) : '';
$found       = (int) $wp_query->found_posts;

// Play All: the first shiur on the page with audio plays at once, and the topic's
// newest shiurim follow from the queue endpoint.
$first_track = array();
foreach ( $shiurim as $shiur ) {
	$first_track = ner_michoel_build_track_queue( array( $shiur ) );
	if ( $first_track ) {
		break;
	}
}
$queue_url = ( $first_track && function_exists( 'ner_michoel_queue_url' ) ) ? ner_michoel_queue_url( array( 'topic' => $term->term_id ) ) : '';

$written = $is_first ? get_posts(
	array(
		'post_type'      => 'written_shiur',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'topic',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			),
		),
	)
) : array();
?>

<div class="shiurim-app">
	<?php ner_michoel_render_return_link(); ?>
	<header class="sh-hero">
		<div class="sh-hero__art sh-hero__art--square">
			<?php echo ner_michoel_placeholder_art( $term->name ); ?>
		</div>
		<div class="sh-hero__info">
			<span class="sh-hero__kicker"><?php esc_html_e( 'Topic', 'ner-michoel-child' ); ?></span>
			<h1><?php echo esc_html( $term->name ); ?></h1>
			<?php if ( $term->description ) : ?>
				<p class="sh-hero__desc"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>
			<p class="sh-hero__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of shiurim */
						_n( '%d shiur', '%d shiurim', $found, 'ner-michoel-child' ),
						$found
					)
				);
				if ( $label ) {
					echo ' &middot; ' . esc_html(
						sprintf(
							/* translators: %s: Hebrew dates, such as 11–23 Tishrei */
							__( 'On the homepage %s', 'ner-michoel-child' ),
							$label
						)
					);
				}
				?>
			</p>
			<?php if ( $first_track ) : ?>
				<button
					type="button"
					class="sh-play-all"
					data-play-queue="<?php echo esc_attr( wp_json_encode( $first_track ) ); ?>"
					<?php if ( $queue_url ) : ?>data-queue-url="<?php echo esc_url( $queue_url ); ?>"<?php endif; ?>
					data-play-index="0"
				><?php echo ner_michoel_icon( 'play' ); ?> <?php esc_html_e( 'Play All', 'ner-michoel-child' ); ?></button>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $shiurim ) : ?>
		<section class="sh-section">
			<?php ner_michoel_render_tracklist( $shiurim, true ); ?>
			<?php ner_michoel_render_classic_pagination( $wp_query ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $written ) : ?>
		<section class="sh-section">
			<h2 class="sh-section__title"><?php esc_html_e( 'Written Shiurim', 'ner-michoel-child' ); ?></h2>
			<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
				<?php foreach ( $written as $item ) :
					$speaker_terms = get_the_terms( $item->ID, 'speaker' );
					ner_michoel_render_media_card(
						array(
							'title'    => get_the_title( $item ),
							'subtitle' => ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) ? $speaker_terms[0]->name : '',
							'image'    => get_the_post_thumbnail_url( $item, 'medium' ),
							'link'     => get_permalink( $item ),
							'kicker'   => __( 'Written', 'ner-michoel-child' ),
							'save_id'  => $item->ID,
						)
					);
				endforeach; ?>
			<?php ner_michoel_render_card_collection_end( $is_carousel ); ?>
		</section>
	<?php endif; ?>

	<?php if ( ! $shiurim && ! $written ) : ?>
		<p class="sh-empty"><?php esc_html_e( 'No shiurim with this topic yet.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
