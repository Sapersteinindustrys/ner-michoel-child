<?php
/**
 * Shiurim search results. Routed here by ner_michoel_use_shiur_search_template()
 * in inc/search.php. Two modes:
 *
 * - Live (the default): assets/js/shiur-live-search.js loads the cached shiur
 *   index (ner-michoel-core includes/shiur-index.php) and filters it in the
 *   browser as the reader types or picks a speaker, series or year. The server
 *   doesn't rank anything (ner_michoel_skip_server_ranking()), so the page comes
 *   back fast.
 * - Plain (?nm_plain=1): the server-ranked results from core's
 *   includes/shiur-search.php, grouped under a heading per tier. For browsers
 *   without JavaScript (the <noscript> link), and the script reloads with it
 *   when the index can't be loaded.
 */

get_header();

$term     = get_search_query( false );
$is_plain = ner_michoel_is_plain_shiur_search();
/* translators: %s: the search as typed */
$title_template = __( 'Results for “%s”', 'ner-michoel-child' );
$title_empty    = __( 'Search shiurim', 'ner-michoel-child' );
?>

<div class="sh-layout-with-sidebar">
	<?php ner_michoel_render_shiurim_sidebar(); ?>

	<div class="shiurim-app sh-main">
		<?php ner_michoel_render_return_link(); ?>

		<header class="sh-page-header">
			<h1 data-live-title data-title-template="<?php echo esc_attr( $title_template ); ?>" data-title-empty="<?php echo esc_attr( $title_empty ); ?>">
				<?php echo esc_html( '' !== trim( $term ) ? sprintf( $title_template, $term ) : $title_empty ); ?>
			</h1>
		</header>

		<?php if ( ! $is_plain ) : ?>

			<?php
			// Shown by the script as soon as it runs. index-url carries the index version,
			// so the browser can keep the file until the next rebuild.
			$index_url = function_exists( 'ner_michoel_shiur_index_url' ) ? ner_michoel_shiur_index_url() : rest_url( 'ner-michoel/v1/shiur-index' );
			?>
			<div class="nm-live-search" data-live-search data-index-url="<?php echo esc_url( $index_url ); ?>" hidden>
				<label class="screen-reader-text" for="nm-live-query"><?php esc_html_e( 'Search shiurim', 'ner-michoel-child' ); ?></label>
				<input type="search" id="nm-live-query" class="nm-live-search__query" value="<?php echo esc_attr( $term ); ?>" placeholder="<?php esc_attr_e( 'Search by title, speaker or series…', 'ner-michoel-child' ); ?>" autocomplete="off" data-live-query />
				<div class="nm-live-search__filters">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Speaker', 'ner-michoel-child' ); ?></span>
						<select data-live-speaker><option value=""><?php esc_html_e( 'All speakers', 'ner-michoel-child' ); ?></option></select>
					</label>
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></span>
						<select data-live-series><option value=""><?php esc_html_e( 'All series', 'ner-michoel-child' ); ?></option></select>
					</label>
					<label hidden>
						<span class="screen-reader-text"><?php esc_html_e( 'Topic', 'ner-michoel-child' ); ?></span>
						<select data-live-topic><option value=""><?php esc_html_e( 'All topics', 'ner-michoel-child' ); ?></option></select>
					</label>
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Year', 'ner-michoel-child' ); ?></span>
						<select data-live-year><option value=""><?php esc_html_e( 'All years', 'ner-michoel-child' ); ?></option></select>
					</label>
				</div>
				<p class="nm-live-search__count" data-live-count aria-live="polite"></p>
				<ul class="nm-live-results" data-live-results></ul>
				<button type="button" class="nm-live-search__more" data-live-more hidden><?php esc_html_e( 'Show more', 'ner-michoel-child' ); ?></button>
			</div>

			<noscript>
				<p class="sh-empty">
					<a href="<?php echo esc_url( add_query_arg( 'nm_plain', '1' ) ); ?>"><?php esc_html_e( 'Show the results', 'ner-michoel-child' ); ?></a>
				</p>
			</noscript>

		<?php else : ?>

			<?php
			$labels      = function_exists( 'ner_michoel_shiur_search_labels' ) ? ner_michoel_shiur_search_labels( $term ) : array();
			$is_carousel = '24six' === ner_michoel_get_layout();
			$current     = null;
			$shown       = 0;
			?>

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

		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
