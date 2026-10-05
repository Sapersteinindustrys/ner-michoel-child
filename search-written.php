<?php
/**
 * Written Shiurim search results. The ranking is the same as the Shiurim
 * search's (ner-michoel-core/includes/shiur-search.php), run over written
 * shiur titles, with the same grouping: whole-phrase title matches first,
 * then speakers, then series, then titles with some of the words. Routed here
 * by ner_michoel_use_shiur_search_template() in inc/search.php.
 */

get_header();

$term    = get_search_query( false );
$labels  = function_exists( 'ner_michoel_shiur_search_labels' ) ? ner_michoel_shiur_search_labels( $term, 'written_shiur' ) : array();
$current = null;
$shown   = 0;
?>

<div class="nm-app nm-written-page">
	<?php ner_michoel_render_return_link( get_post_type_archive_link( 'written_shiur' ) ); ?>

	<header class="sh-page-header">
		<h1>
			<?php
			printf(
				/* translators: %s: the search as typed */
				esc_html__( 'Written shiurim for “%s”', 'ner-michoel-child' ),
				esc_html( $term )
			);
			?>
		</h1>
	</header>

	<?php ner_michoel_render_written_search_form(); ?>

	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			$label = isset( $labels[ get_the_ID() ] ) ? $labels[ get_the_ID() ] : '';

			// A new tier: close the previous group and open this one's heading.
			if ( $label !== $current ) {
				if ( null !== $current ) {
					echo '</div></section>';
				}
				echo '<section class="sh-section">';
				echo '<h2 class="sh-section__title">' . esc_html( $label ) . '</h2>';
				echo '<div class="sh-written-list">';
				$current = $label;
			}

			ner_michoel_render_written_row( get_the_ID() );
			++$shown;
		endwhile;

		if ( null !== $current ) {
			echo '</div></section>';
		}
		?>
		<p class="sh-empty">
			<?php
			printf(
				/* translators: %d: number of written shiurim found */
				esc_html( _n( '%d written shiur found.', '%d written shiurim found.', $shown, 'ner-michoel-child' ) ),
				(int) $shown
			);
			?>
		</p>
	<?php else : ?>
		<p class="sh-empty">
			<?php esc_html_e( 'Nothing matches these words in written shiur titles, speaker names, or series names. Try fewer or different words.', 'ner-michoel-child' ); ?>
		</p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
