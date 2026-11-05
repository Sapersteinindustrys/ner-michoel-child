<?php
/**
 * A topic's page: every shiur tagged with it (ner-michoel-core includes/topics.php).
 * A header with Play All, the topic's shiurim as a track list (40 a page, newest
 * first: some topics have over a thousand), then on the first page its written
 * shiurim, as cards.
 *
 * Under the header is a search box for this topic (ner_michoel_render_topic_search(),
 * inc/search.php), which looks through all of the topic's shiurim, not just the
 * page shown. While something is typed it takes the place of the lists marked
 * data-topic-browse.
 */

get_header();

global $wp_query;
$term = get_queried_object();

$shiurim     = $wp_query->posts;
if ( $shiurim && function_exists( 'ner_michoel_prime_shiur_caches' ) ) {
	// The track list builds a track for each shiur: load their data in a few queries first.
	ner_michoel_prime_shiur_caches( wp_list_pluck( $shiurim, 'ID' ) );
}
$is_first    = max( 1, (int) get_query_var( 'paged' ) ) === 1;
// Swipeable rows (the written shiurim cards).
$is_carousel = true;
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
			<?php
			// A parsha's own page: the Shabbos it is read, in English and Hebrew dates (ner-michoel-core includes/parsha.php).
			$parsha_numbers = function_exists( 'ner_michoel_parsha_match_name' ) ? ner_michoel_parsha_match_name( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ) : array();
			$parsha_read    = ( $parsha_numbers && function_exists( 'ner_michoel_parsha_next_reading' ) ) ? ner_michoel_parsha_next_reading( $parsha_numbers ) : null;
			?>
			<?php if ( $parsha_read ) : ?>
				<p class="sh-hero__parsha-date">
					<span class="sh-hero__parsha-when">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: "This Shabbos" or "Read on Shabbos", 2: a date such as Nov 7, 3: its Hebrew date such as 27 Cheshvan 5787 */
								__( '%1$s %2$s · %3$s', 'ner-michoel-child' ),
								$parsha_read['this_week'] ? __( 'This Shabbos,', 'ner-michoel-child' ) : __( 'Next read on Shabbos', 'ner-michoel-child' ),
								$parsha_read['date'],
								$parsha_read['hebrew']
							)
						);
						?>
					</span>
					<span class="sh-hero__parsha-he" lang="he" dir="rtl"><?php echo esc_html( $parsha_read['hebrew_he'] ); ?></span>
				</p>
			<?php endif; ?>
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

	<?php
	// The search bar with filters, set to this topic (inc/revamp.php); the older topic-only box without it.
	if ( function_exists( 'ner_michoel_render_scoped_search' ) ) {
		/* translators: %s: topic name */
		ner_michoel_render_scoped_search( array( 'scope' => 'topic', 'term' => $term, 'label' => sprintf( __( 'Search within %s…', 'ner-michoel-child' ), $term->name ) ) );
	} else {
		ner_michoel_render_topic_search( $term );
	}
	?>

	<?php if ( $shiurim ) : ?>
		<section class="sh-section" data-topic-browse data-scope-browse>
			<?php ner_michoel_render_tracklist( $shiurim, true ); ?>
			<?php ner_michoel_render_classic_pagination( $wp_query ); ?>
		</section>
	<?php endif; ?>

	<?php if ( $written ) : ?>
		<section class="sh-section" data-topic-browse data-scope-browse>
			<h2 class="sh-section__title"><?php esc_html_e( 'Written Shiurim', 'ner-michoel-child' ); ?></h2>
			<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
				<?php foreach ( $written as $item ) :
					$speaker_terms = get_the_terms( $item->ID, 'speaker' );
					ner_michoel_render_media_card(
						array(
							'title'    => get_the_title( $item ),
							'subtitle' => ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) ? $speaker_terms[0]->name : '',
							'image'    => ner_michoel_shiur_art( $item, 'medium' ),
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
		<p class="sh-empty" data-topic-browse data-scope-browse><?php esc_html_e( 'No shiurim with this topic yet.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
