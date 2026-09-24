<?php
/**
 * Shiurim home. Three layouts, toggled top-right:
 * - Modern (default): Spotify-style grid of Series/Speakers.
 * - Classic: a plain filterable/sortable list of every shiur.
 * - 24Six: the same Series/Speakers browsing, as horizontal-scrolling
 *   carousel rows instead of a wrapping grid.
 */

get_header();

$layout = ner_michoel_get_layout();

if ( 'classic' === $layout ) :
	$classic_query = ner_michoel_get_classic_query();
	?>
	<div class="shiurim-app shiurim-app--classic">
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
?>

<div class="sh-layout-with-sidebar">
	<?php ner_michoel_render_shiurim_sidebar(); ?>

	<div class="shiurim-app sh-main">
		<header class="sh-page-header">
			<h1><?php echo $is_recent ? esc_html__( 'Recent Shiurim', 'ner-michoel-child' ) : post_type_archive_title( '', false ); ?></h1>
		</header>

		<?php if ( $is_recent ) : ?>
			<?php $recent = function_exists( 'ner_michoel_get_recent_shiurim' ) ? ner_michoel_get_recent_shiurim( 24 ) : array(); ?>
			<?php if ( $recent ) : ?>
			<section class="sh-section">
				<?php ner_michoel_render_card_collection_start( $is_carousel ); ?>
					<?php foreach ( $recent as $shiur ) :
						$speaker_terms_for_shiur = get_the_terms( $shiur->ID, 'speaker' );
						$speaker_name            = ( $speaker_terms_for_shiur && ! is_wp_error( $speaker_terms_for_shiur ) ) ? $speaker_terms_for_shiur[0]->name : '';
						ner_michoel_render_media_card(
							array(
								'title'    => get_the_title( $shiur ),
								'subtitle' => $speaker_name,
								'image'    => get_the_post_thumbnail_url( $shiur, 'medium' ),
								'link'     => get_permalink( $shiur ),
								'queue'    => ner_michoel_build_track_queue( array( $shiur ) ),
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
						$shiurim = ner_michoel_get_series_shiurim( $term->term_id );
						ner_michoel_render_media_card(
							array(
								'title'    => $term->name,
								'subtitle' => sprintf(
									/* translators: %d: number of shiurim */
									_n( '%d shiur', '%d shiurim', count( $shiurim ), 'ner-michoel-child' ),
									count( $shiurim )
								),
								'image'    => ner_michoel_get_series_cover_url( $term->term_id ),
								'link'     => get_term_link( $term ),
								'queue'    => ner_michoel_build_track_queue( $shiurim ),
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
						$shiurim = ner_michoel_get_speaker_shiurim( $term->term_id );
						ner_michoel_render_media_card(
							array(
								'title'    => $term->name,
								'subtitle' => __( 'Speaker', 'ner-michoel-child' ),
								'image'    => ner_michoel_get_speaker_photo_url( $term->term_id ),
								'link'     => get_term_link( $term ),
								'queue'    => ner_michoel_build_track_queue( $shiurim ),
								'round'    => true,
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
