<?php
/**
 * A series' page. Two layouts, toggled top-right:
 * - Modern (default): Spotify "Album" page equivalent.
 * - Classic: a plain filterable/sortable list of this series' shiurim.
 */

get_header();

$term = get_queried_object();

if ( 'classic' === ner_michoel_get_layout() ) :
	$classic_query = ner_michoel_get_classic_query( array( 'series_term' => $term->term_id ) );
	?>
	<div class="shiurim-app shiurim-app--classic">
		<header class="sh-page-header">
			<h1><?php echo esc_html( $term->name ); ?></h1>
		</header>
		<?php ner_michoel_render_classic_table( $classic_query, true, false ); ?>
		<?php ner_michoel_render_classic_pagination( $classic_query ); ?>
	</div>
	<?php
	get_footer();
	return;
endif;

$shiurim = ner_michoel_get_series_shiurim( $term->term_id );
$image   = ner_michoel_get_series_cover_url( $term->term_id );
$queue   = ner_michoel_build_track_queue( $shiurim );
?>

<div class="shiurim-app">
	<header class="sh-hero">
		<div class="sh-hero__art sh-hero__art--square">
			<?php if ( $image ) : ?>
				<img src="<?php echo esc_url( $image ); ?>" alt="" />
			<?php else : ?>
				<?php echo ner_michoel_placeholder_art( $term->name ); ?>
			<?php endif; ?>
		</div>
		<div class="sh-hero__info">
			<span class="sh-hero__kicker"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></span>
			<h1><?php echo esc_html( $term->name ); ?></h1>
			<?php if ( $term->description ) : ?>
				<p class="sh-hero__desc"><?php echo esc_html( $term->description ); ?></p>
			<?php endif; ?>
			<p class="sh-hero__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of shiurim */
						_n( '%d shiur', '%d shiurim', count( $shiurim ), 'ner-michoel-child' ),
						count( $shiurim )
					)
				);
				?>
			</p>
			<?php if ( $queue ) : ?>
				<button
					type="button"
					class="sh-play-all"
					data-play-queue="<?php echo esc_attr( wp_json_encode( $queue ) ); ?>"
					data-play-index="0"
				><?php echo ner_michoel_icon( 'play' ); ?> <?php esc_html_e( 'Play All', 'ner-michoel-child' ); ?></button>
			<?php endif; ?>
		</div>
	</header>

	<section class="sh-section">
		<?php ner_michoel_render_tracklist( $shiurim, true ); ?>
	</section>
</div>

<?php get_footer(); ?>
