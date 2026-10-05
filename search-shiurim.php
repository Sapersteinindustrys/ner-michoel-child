<?php
/**
 * Shiurim search results. The ranking is in ner-michoel-core
 * (includes/shiur-search.php), and it gives the results in order. Results
 * are grouped under a heading per tier: first the titles that contain the
 * whole search, then titles with the most words of it, with fewer words
 * after. Routed here by ner_michoel_use_shiur_search_template() in
 * inc/search.php.
 */

get_header();

$term        = get_search_query( false );
$labels      = function_exists( 'ner_michoel_shiur_search_labels' ) ? ner_michoel_shiur_search_labels( $term ) : array();
$is_carousel = '24six' === ner_michoel_get_layout();
$current     = null;
$shown       = 0;
?>

<div class="sh-layout-with-sidebar">
	<?php ner_michoel_render_shiurim_sidebar(); ?>

	<div class="shiurim-app sh-main">
		<?php ner_michoel_render_return_link(); ?>

		<header class="sh-page-header">
			<h1>
				<?php
				printf(
					/* translators: %s: the search as typed */
					esc_html__( 'Results for “%s”', 'ner-michoel-child' ),
					esc_html( $term )
				);
				?>
			</h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				$shiur = get_post();
				$label = isset( $labels[ $shiur->ID ] ) ? $labels[ $shiur->ID ] : '';

				// A new tier: close the previous group and open this one's heading.
				if ( $label !== $current ) {
					if ( null !== $current ) {
						ner_michoel_render_card_collection_end( $is_carousel );
						echo '</section>';
					}
					echo '<section class="sh-section">';
					echo '<h2 class="sh-section__title">' . esc_html( $label ) . '</h2>';
					ner_michoel_render_card_collection_start( $is_carousel );
					$current = $label;
				}

				$speaker_terms = get_the_terms( $shiur->ID, 'speaker' );
				$speaker_name  = ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) ? $speaker_terms[0]->name : '';

				ner_michoel_render_media_card(
					array(
						'title'    => get_the_title( $shiur ),
						'subtitle' => $speaker_name,
						'image'    => get_the_post_thumbnail_url( $shiur, 'medium' ),
						'link'     => get_permalink( $shiur ),
						'queue'    => ner_michoel_build_track_queue( ner_michoel_series_rest_for_shiur( $shiur ) ), // a series shiur plays the rest of its series in order
						'save_id'  => $shiur->ID,
					)
				);
				++$shown;
			endwhile;

			if ( null !== $current ) {
				ner_michoel_render_card_collection_end( $is_carousel );
				echo '</section>';
			}
			?>
			<p class="sh-empty">
				<?php
				printf(
					/* translators: %d: number of shiurim found */
					esc_html( _n( '%d shiur found.', '%d shiurim found.', $shown, 'ner-michoel-child' ) ),
					(int) $shown
				);
				?>
			</p>
		<?php else : ?>
			<p class="sh-empty">
				<?php esc_html_e( 'Nothing matches these words in shiur titles, speaker names, or series names. Try fewer or different words.', 'ner-michoel-child' ); ?>
			</p>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
