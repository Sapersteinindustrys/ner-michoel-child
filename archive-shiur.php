<?php
/**
 * Shiurim home. Two layouts, toggled top-right:
 * - Modern (default): Spotify-style grid of Series/Speakers.
 * - Classic: a plain filterable/sortable list of every shiur.
 */

get_header();

if ( 'classic' === ner_michoel_get_layout() ) :
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
?>

<div class="shiurim-app">
	<header class="sh-page-header">
		<h1><?php post_type_archive_title(); ?></h1>
	</header>

	<?php if ( $has_series ) : ?>
	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></h2>
		<div class="sh-grid">
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
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $has_speakers ) : ?>
	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'Speakers', 'ner-michoel-child' ); ?></h2>
		<div class="sh-grid">
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
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! $has_series && ! $has_speakers ) : ?>
		<p class="sh-empty"><?php esc_html_e( 'No shiurim yet — add a Speaker and Series, then upload the first shiur.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
