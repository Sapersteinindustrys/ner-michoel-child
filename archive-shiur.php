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

<div class="shiurim-app st sh-recent-page">
	<?php ner_michoel_render_back_button(); ?>

	<?php
	// The same navy hero as the Shiurim Library (template-parts/shiurim-studio.php), with
	// the other ways into the library on its right, where the old sidebar's links were.
	$shiurim_url = get_post_type_archive_link( 'shiur' );
	if ( $is_foryou ) {
		$page_heading = __( 'For you', 'ner-michoel-child' );
		$page_lede    = __( 'New shiurim from the speakers and series you follow, and what you were listening to.', 'ner-michoel-child' );
	} else {
		$page_heading = __( 'New Shiurim', 'ner-michoel-child' );
		$page_lede    = __( 'Every shiur, newest first. Search them, or narrow them by speaker, series, topic or year.', 'ner-michoel-child' );
	}
	$hero_links = array(
		array( __( 'The Shiurim Library', 'ner-michoel-child' ), __( 'Browse all shiurim', 'ner-michoel-child' ), $shiurim_url ),
	);
	if ( $is_foryou ) {
		$hero_links[] = array( __( 'Latest', 'ner-michoel-child' ), __( 'New shiurim', 'ner-michoel-child' ), add_query_arg( 'sh_view', 'recent', $shiurim_url ) );
	}
	if ( post_type_exists( 'written_shiur' ) ) {
		$hero_links[] = array( __( 'To read', 'ner-michoel-child' ), __( 'Written shiurim', 'ner-michoel-child' ), get_post_type_archive_link( 'written_shiur' ) );
	}
	$hero_links[] = array( __( 'Every shiur in rows', 'ner-michoel-child' ), __( 'Shiur table', 'ner-michoel-child' ), add_query_arg( array( 'post_type' => 'shiur', 's' => '', 'vw' => 'table' ), home_url( '/' ) ) );
	?>
	<section class="st-hero" aria-labelledby="st-hero-title">
		<div class="st-hero__copy">
			<?php /* translators: %s: the site's name */ ?>
			<p class="st-eyebrow"><?php echo esc_html( sprintf( __( '%s · Shiurim', 'ner-michoel-child' ), ner_michoel_site_name() ) ); ?></p>
			<h1 class="st-hero__title" id="st-hero-title"><?php echo esc_html( $page_heading ); ?></h1>
			<p class="st-hero__lede"><?php echo esc_html( $page_lede ); ?></p>
		</div>
		<div class="st-hero__side sh-recent-page__links">
			<?php foreach ( $hero_links as $hero_link ) : ?>
				<a class="st-feature st-feature--quiet st-feature--link" href="<?php echo esc_url( $hero_link[2] ); ?>">
					<p class="st-feature__label"><?php echo esc_html( $hero_link[0] ); ?></p>
					<span class="st-feature__title"><?php echo esc_html( $hero_link[1] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>

	<div class="sh-recent-page__body">
		<?php if ( $is_foryou ) : ?>
			<?php ner_michoel_render_for_you_page( false ); ?>
		<?php else : ?>
			<?php
			// Every shiur, newest first, one line each with its dates and kinds, and the search bar's
			// filters to narrow it (inc/revamp.php). The cards below are for a browser without script.
			if ( function_exists( 'ner_michoel_render_scoped_search' ) ) {
				ner_michoel_render_scoped_search(
					array(
						'label'      => __( 'Search the new shiurim…', 'ner-michoel-child' ),
						'start_open' => true,
					)
				);
			}
			?>
			<?php $recent = function_exists( 'ner_michoel_get_recent_shiurim' ) ? ner_michoel_get_recent_shiurim( 24 ) : array(); ?>
			<?php $recent = function_exists( 'ner_michoel_one_per_shiur' ) ? ner_michoel_one_per_shiur( $recent ) : $recent; ?>
			<?php
			if ( $lazy_queues && $recent ) {
				ner_michoel_prime_shiur_caches( wp_list_pluck( $recent, 'ID' ) );
			}
			?>
			<?php if ( $recent ) : ?>
			<section class="sh-section" data-scope-browse>
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
									'image'    => ner_michoel_shiur_art( $shiur, 'medium' ),
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
				<p class="sh-empty" data-scope-browse><?php esc_html_e( 'No shiurim yet.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
