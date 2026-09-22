<?php
/**
 * A speaker's page. Two layouts, toggled top-right:
 * - Modern (default): Spotify "Artist" page equivalent.
 * - Classic: a plain filterable/sortable list of this speaker's shiurim.
 */

get_header();

$term = get_queried_object();

if ( 'classic' === ner_michoel_get_layout() ) :
	$classic_query = ner_michoel_get_classic_query( array( 'speaker_term' => $term->term_id ) );
	?>
	<div class="shiurim-app shiurim-app--classic">
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

$series     = ner_michoel_get_speaker_series( $term->term_id );
$standalone = ner_michoel_get_speaker_standalone_shiurim( $term->term_id );
$image      = ner_michoel_get_speaker_photo_url( $term->term_id );
?>

<div class="shiurim-app">
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
		<div class="sh-grid">
			<?php foreach ( $series as $s ) :
				$s_shiurim = ner_michoel_get_series_shiurim( $s->term_id );
				ner_michoel_render_media_card(
					array(
						'title'    => $s->name,
						'subtitle' => sprintf(
							/* translators: %d: number of shiurim */
							_n( '%d shiur', '%d shiurim', count( $s_shiurim ), 'ner-michoel-child' ),
							count( $s_shiurim )
						),
						'image'    => ner_michoel_get_series_cover_url( $s->term_id ),
						'link'     => get_term_link( $s ),
						'queue'    => ner_michoel_build_track_queue( $s_shiurim ),
					)
				);
			endforeach; ?>
		</div>
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
