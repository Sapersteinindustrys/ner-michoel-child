<?php
/**
 * Written Shiurim search results. The ranking is the same as the Shiurim
 * search's (ner-michoel-core/includes/shiur-search.php), run over written
 * shiur titles, with the same grouping: whole-phrase title matches first,
 * then speakers, then series, then titles with some of the words. Routed here
 * by ner_michoel_use_shiur_search_template() in inc/search.php.
 *
 * Modern shows each tier as a row of paper sheets (inc/written-shiurim.php);
 * Classic keeps the dated list.
 */

get_header();

$term       = get_search_query( false );
$labels     = function_exists( 'ner_michoel_shiur_search_labels' ) ? ner_michoel_shiur_search_labels( $term, 'written_shiur' ) : array();
$is_classic = 'classic' === ner_michoel_get_layout();
$tiers      = array();

while ( have_posts() ) {
	the_post();
	$label = isset( $labels[ get_the_ID() ] ) ? $labels[ get_the_ID() ] : '';
	if ( ! isset( $tiers[ $label ] ) ) {
		$tiers[ $label ] = array();
	}
	$tiers[ $label ][] = get_the_ID();
}
$shown = array_sum( array_map( 'count', $tiers ) );
?>

<div class="nm-app nm-written-page<?php echo $is_classic ? ' nm-app--classic' : ' nm-reading-room'; ?>">
	<?php ner_michoel_render_return_link( get_post_type_archive_link( 'written_shiur' ) ); ?>

	<header class="<?php echo $is_classic ? 'sh-page-header' : 'nm-masthead nm-masthead--compact'; ?>">
		<?php if ( ! $is_classic ) : ?>
			<p class="nm-masthead__eyebrow"><?php esc_html_e( 'Search the library', 'ner-michoel-child' ); ?></p>
		<?php endif; ?>
		<h1 class="<?php echo $is_classic ? '' : 'nm-masthead__title'; ?>">
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

	<?php if ( $tiers ) : ?>
		<?php foreach ( $tiers as $label => $ids ) : ?>
			<section class="<?php echo $is_classic ? 'sh-section' : 'nm-related'; ?>">
				<?php if ( '' !== $label ) : ?>
					<h2 class="<?php echo $is_classic ? 'sh-section__title' : 'nm-related__title'; ?>"><?php echo esc_html( $label ); ?></h2>
				<?php endif; ?>
				<div class="<?php echo $is_classic ? 'sh-written-list' : 'nm-related__sheets'; ?>">
					<?php
					foreach ( $ids as $id ) {
						if ( $is_classic ) {
							ner_michoel_render_written_row( $id );
						} else {
							ner_michoel_render_written_sheet( ner_michoel_written_data( $id ) );
						}
					}
					?>
				</div>
			</section>
		<?php endforeach; ?>
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
