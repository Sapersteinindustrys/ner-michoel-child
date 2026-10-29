<?php
/**
 * A series' page: a header with Play All, then its shiurim as a track list.
 */

get_header();

$term = get_queried_object();

$shiurim = ner_michoel_get_series_shiurim( $term->term_id );
$image   = ner_michoel_get_series_cover_url( $term->term_id );
$queue   = ner_michoel_build_track_queue( $shiurim );

// The series directly inside this one that have shiurim in them or below them. (The site's lists skip a series that
// holds nothing itself; on this page a series that only holds other series is what there is to show.)
$children = get_terms(
	array(
		'taxonomy'        => 'series',
		'parent'          => $term->term_id,
		'hide_empty'      => true,
		'nm_with_parents' => true,
	)
);
$children = is_wp_error( $children ) ? array() : $children;
usort(
	$children,
	function ( $a, $b ) {
		return strnatcasecmp( html_entity_decode( $a->name, ENT_QUOTES, 'UTF-8' ), html_entity_decode( $b->name, ENT_QUOTES, 'UTF-8' ) );
	}
);
?>

<div class="shiurim-app">
	<?php ner_michoel_render_return_link(); ?>
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
				if ( $shiurim || ! $children ) {
					echo esc_html(
						sprintf(
							/* translators: %d: number of shiurim */
							_n( '%d shiur', '%d shiurim', count( $shiurim ), 'ner-michoel-child' ),
							count( $shiurim )
						)
					);
				} else {
					// A series that only holds other series.
					echo esc_html(
						sprintf(
							/* translators: %d: number of series inside this one */
							_n( '%d series inside', '%d series inside', count( $children ), 'ner-michoel-child' ),
							count( $children )
						)
					);
				}
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

	<?php if ( $children ) : ?>
	<section class="sh-section" data-series-inside>
		<h2 class="sh-section__title"><?php esc_html_e( 'Series inside', 'ner-michoel-child' ); ?></h2>
		<?php ner_michoel_render_card_collection_start( true ); ?>
			<?php
			foreach ( $children as $child ) :
				ner_michoel_render_media_card(
					array(
						'title'    => html_entity_decode( $child->name, ENT_QUOTES, 'UTF-8' ),
						'subtitle' => $child->count
							/* translators: %d: number of shiurim */
							? sprintf( _n( '%d shiur', '%d shiurim', (int) $child->count, 'ner-michoel-child' ), (int) $child->count )
							: __( 'Series', 'ner-michoel-child' ),
						'image'    => ner_michoel_get_series_cover_url( $child->term_id ),
						'link'     => get_term_link( $child ),
						'variant'  => 'series',
					)
				);
			endforeach;
			?>
		<?php ner_michoel_render_card_collection_end( true ); ?>
	</section>
	<?php endif; ?>

	<?php if ( $shiurim || ! $children ) : ?>
	<section class="sh-section">
		<?php ner_michoel_render_tracklist( $shiurim, true ); ?>
	</section>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
