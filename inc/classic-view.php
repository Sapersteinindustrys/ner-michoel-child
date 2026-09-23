<?php
/**
 * "Classic" Shiurim layout — a plain filterable/sortable list, closer
 * to the original site's library browsing than the streaming-app UI
 * in template-tags.php. Toggled via ner_michoel_render_layout_toggle().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared query for the classic list. $args can pin it to a speaker or
 * series term (taxonomy-speaker.php / taxonomy-series.php); on the
 * plain archive both are instead read from ?speaker=&series= so the
 * toolbar dropdowns can filter across everything.
 */
function ner_michoel_get_classic_query( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'speaker_term' => 0,
			'series_term'  => 0,
		)
	);

	$sort = isset( $_GET['sh_sort'] ) ? sanitize_key( $_GET['sh_sort'] ) : 'date';
	if ( ! in_array( $sort, array( 'date', 'title' ), true ) ) {
		$sort = 'date';
	}

	$tax_query = array();

	if ( $args['speaker_term'] ) {
		$tax_query[] = array(
			'taxonomy' => 'speaker',
			'field'    => 'term_id',
			'terms'    => $args['speaker_term'],
		);
	} elseif ( ! empty( $_GET['speaker'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'speaker',
			'field'    => 'slug',
			'terms'    => sanitize_title( wp_unslash( $_GET['speaker'] ) ),
		);
	}

	if ( $args['series_term'] ) {
		$tax_query[] = array(
			'taxonomy' => 'series',
			'field'    => 'term_id',
			'terms'    => $args['series_term'],
		);
	} elseif ( ! empty( $_GET['series'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'series',
			'field'    => 'slug',
			'terms'    => sanitize_title( wp_unslash( $_GET['series'] ) ),
		);
	}

	$query_args = array(
		'post_type'      => 'shiur',
		'posts_per_page' => 25,
		'paged'          => max( 1, (int) get_query_var( 'paged' ) ),
		'orderby'        => 'title' === $sort ? 'title' : 'date',
		'order'          => 'title' === $sort ? 'ASC' : 'DESC',
		'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	);

	if ( ! empty( $_GET['s'] ) ) {
		$query_args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
	}

	return new WP_Query( $query_args );
}

/**
 * Filter dropdowns + sort links for the plain archive. Not shown on
 * the speaker/series pages, since those are already filtered by URL.
 */
function ner_michoel_render_classic_toolbar() {
	$speakers    = get_terms( array( 'taxonomy' => 'speaker', 'hide_empty' => true ) );
	$series      = get_terms( array( 'taxonomy' => 'series', 'hide_empty' => true ) );
	$cur_speaker = isset( $_GET['speaker'] ) ? sanitize_title( wp_unslash( $_GET['speaker'] ) ) : '';
	$cur_series  = isset( $_GET['series'] ) ? sanitize_title( wp_unslash( $_GET['series'] ) ) : '';
	$cur_sort    = isset( $_GET['sh_sort'] ) ? sanitize_key( $_GET['sh_sort'] ) : 'date';
	$cur_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	?>
	<form class="sh-classic-toolbar" method="get">
		<label class="sh-classic-toolbar__field sh-classic-toolbar__field--search">
			<?php esc_html_e( 'Search', 'ner-michoel-child' ); ?>
			<input type="search" name="s" value="<?php echo esc_attr( $cur_search ); ?>" placeholder="<?php esc_attr_e( 'Title…', 'ner-michoel-child' ); ?>" />
		</label>
		<label class="sh-classic-toolbar__field">
			<?php esc_html_e( 'Speaker', 'ner-michoel-child' ); ?>
			<select name="speaker">
				<option value=""><?php esc_html_e( 'All speakers', 'ner-michoel-child' ); ?></option>
				<?php foreach ( $speakers as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $cur_speaker, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="sh-classic-toolbar__field">
			<?php esc_html_e( 'Series', 'ner-michoel-child' ); ?>
			<select name="series">
				<option value=""><?php esc_html_e( 'All series', 'ner-michoel-child' ); ?></option>
				<?php foreach ( $series as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $cur_series, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="sh-classic-toolbar__field">
			<?php esc_html_e( 'Sort by', 'ner-michoel-child' ); ?>
			<select name="sh_sort">
				<option value="date" <?php selected( $cur_sort, 'date' ); ?>><?php esc_html_e( 'Date', 'ner-michoel-child' ); ?></option>
				<option value="title" <?php selected( $cur_sort, 'title' ); ?>><?php esc_html_e( 'Title', 'ner-michoel-child' ); ?></option>
			</select>
		</label>
		<button type="submit" class="sh-classic-toolbar__apply"><?php esc_html_e( 'Apply', 'ner-michoel-child' ); ?></button>
	</form>
	<?php
}

/**
 * The list itself: one row per shiur with title, speaker, series,
 * date, duration, and direct play/download links — no queue/player,
 * just native browser playback, matching the original site's model.
 */
function ner_michoel_render_classic_table( WP_Query $query, $show_speaker = true, $show_series = true ) {
	if ( ! $query->have_posts() ) {
		echo '<p class="sh-empty">' . esc_html__( 'No shiurim found.', 'ner-michoel-child' ) . '</p>';
		return;
	}
	?>
	<table class="sh-classic-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Title', 'ner-michoel-child' ); ?></th>
				<?php if ( $show_speaker ) : ?><th><?php esc_html_e( 'Speaker', 'ner-michoel-child' ); ?></th><?php endif; ?>
				<?php if ( $show_series ) : ?><th><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></th><?php endif; ?>
				<th><?php esc_html_e( 'Date', 'ner-michoel-child' ); ?></th>
				<th><?php esc_html_e( 'Duration', 'ner-michoel-child' ); ?></th>
				<th><?php esc_html_e( 'Listen', 'ner-michoel-child' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$is_video      = ner_michoel_shiur_is_video( get_the_ID() );
				$audio_url     = ner_michoel_get_shiur_audio_url( get_the_ID() );
				$duration      = $is_video ? __( 'Video', 'ner-michoel-child' ) : ner_michoel_get_shiur_duration( get_the_ID() );
				$speaker_terms = get_the_terms( get_the_ID(), 'speaker' );
				$series_terms  = get_the_terms( get_the_ID(), 'series' );
				$download_url  = function_exists( 'ner_michoel_get_shiur_download_url' ) ? ner_michoel_get_shiur_download_url( get_the_ID() ) : $audio_url;
				?>
				<tr>
					<td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php ner_michoel_render_shiur_badges( get_the_ID() ); ?></td>
					<?php if ( $show_speaker ) : ?>
						<td>
							<?php if ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) : ?>
								<a href="<?php echo esc_url( get_term_link( $speaker_terms[0] ) ); ?>"><?php echo esc_html( $speaker_terms[0]->name ); ?></a>
							<?php endif; ?>
						</td>
					<?php endif; ?>
					<?php if ( $show_series ) : ?>
						<td>
							<?php if ( $series_terms && ! is_wp_error( $series_terms ) ) : ?>
								<a href="<?php echo esc_url( get_term_link( $series_terms[0] ) ); ?>"><?php echo esc_html( $series_terms[0]->name ); ?></a>
							<?php endif; ?>
						</td>
					<?php endif; ?>
					<td><?php echo esc_html( get_the_date() ); ?></td>
					<td><?php echo esc_html( $duration ); ?></td>
					<td>
						<?php if ( $is_video ) : ?>
							<a href="<?php the_permalink(); ?>"><?php esc_html_e( 'Watch', 'ner-michoel-child' ); ?></a>
						<?php elseif ( $audio_url ) : ?>
							<a href="<?php echo esc_url( $audio_url ); ?>" target="_blank" rel="noopener" class="sh-classic-play-link" data-shiur-id="<?php echo esc_attr( get_the_ID() ); ?>"><?php esc_html_e( 'Play', 'ner-michoel-child' ); ?></a>
							&middot;
							<a href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'ner-michoel-child' ); ?></a>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</td>
				</tr>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</tbody>
	</table>
	<?php
}

/**
 * Prev/next + page-number links for the classic list.
 */
function ner_michoel_render_classic_pagination( WP_Query $query ) {
	$links = paginate_links(
		array(
			'total'     => $query->max_num_pages,
			'current'   => max( 1, get_query_var( 'paged' ) ?: 1 ),
			'prev_text' => __( '&laquo; Prev', 'ner-michoel-child' ),
			'next_text' => __( 'Next &raquo;', 'ner-michoel-child' ),
			'type'      => 'list',
		)
	);
	if ( $links ) {
		echo '<nav class="sh-classic-pagination">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
