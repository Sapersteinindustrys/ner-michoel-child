<?php
/**
 * Written Shiurim archive (/written-shiurim/).
 *
 * Modern and 24Six: the reading room, week by week, the latest week as a
 * feature spread, with instant search and author/Sefer/year filters
 * (inc/written-shiurim.php, assets/js/written-library.js). The first weeks
 * are rendered here; the rest of the library comes as a compact JSON index
 * that the script filters and renders from as you scroll, so a library of
 * thousands stays light. A link with filters in it is filtered here too,
 * the same way the script does it, so it doesn't open on the whole library.
 *
 * Classic: the plain dated list, paginated.
 */

get_header();

$layout = ner_michoel_get_layout();

if ( 'classic' === $layout ) :
	?>
	<div class="nm-app nm-written-page nm-app--classic">
		<?php ner_michoel_render_back_button(); ?>
		<header class="sh-page-header">
			<h1><?php post_type_archive_title(); ?></h1>
		</header>

		<?php ner_michoel_render_written_search_form(); ?>

		<?php if ( have_posts() ) : ?>
			<section class="sh-section">
				<div class="sh-written-list">
					<?php
					while ( have_posts() ) :
						the_post();
						ner_michoel_render_written_row( get_the_ID() );
					endwhile;
					?>
				</div>
			</section>

			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p class="sh-empty"><?php esc_html_e( 'No written shiurim yet.', 'ner-michoel-child' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	get_footer();
	return;
endif;

$written_posts = $GLOBALS['wp_query']->posts;
ner_michoel_written_prime( $written_posts );

$weeks    = ner_michoel_written_weeks( $written_posts );
$authors  = array();
$earliest = '';
foreach ( $weeks as $week ) {
	foreach ( $week['items'] as $item ) {
		if ( $item['speaker'] ) {
			$authors[ $item['speaker']->term_id ] = true;
		}
		if ( $item['year'] && ( '' === $earliest || $item['year'] < $earliest ) ) {
			$earliest = $item['year'];
		}
	}
}

// How many weeks arrive as HTML; the script renders the rest on demand.
$first_weeks = (int) apply_filters( 'ner_michoel_written_first_weeks', 20 );

// A link with filters in it (?q=, ?by=, ?sefer=, ?yr=) arrives filtered.
// The script then takes over from the same URL and finds the same pieces.
$filters   = ner_michoel_written_request_filters();
$filtering = ner_michoel_written_is_filtering( $filters );
$shown     = $filtering ? ner_michoel_written_filter_weeks( $weeks, $filters ) : $weeks;
$matched   = 0;
foreach ( $shown as $week ) {
	$matched += count( $week['items'] );
}
?>

<div class="nm-app nm-written-page nm-reading-room">
	<?php ner_michoel_render_back_button(); ?>

	<header class="nm-masthead">
		<p class="nm-masthead__eyebrow"><?php esc_html_e( 'The Ner Michoel library', 'ner-michoel-child' ); ?></p>
		<h1 class="nm-masthead__title"><?php post_type_archive_title(); ?></h1>
		<p class="nm-masthead__lede"><?php esc_html_e( 'Each week’s divrei Torah and halacha, ready to read, print and share.', 'ner-michoel-child' ); ?></p>
		<?php if ( $written_posts ) : ?>
			<ul class="nm-masthead__stats">
				<li><strong><?php echo esc_html( number_format_i18n( count( $written_posts ) ) ); ?></strong> <?php echo esc_html( _n( 'shiur', 'shiurim', count( $written_posts ), 'ner-michoel-child' ) ); ?></li>
				<li><strong><?php echo esc_html( number_format_i18n( count( $authors ) ) ); ?></strong> <?php echo esc_html( _n( 'author', 'authors', count( $authors ), 'ner-michoel-child' ) ); ?></li>
				<?php if ( $earliest ) : ?>
					<li><?php esc_html_e( 'since', 'ner-michoel-child' ); ?> <strong><?php echo esc_html( $earliest ); ?></strong></li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>
	</header>

	<?php if ( $weeks ) : ?>
		<?php ner_michoel_render_written_filters( $weeks, $filters, $filtering ? $matched : null ); ?>

		<?php // No whitespace inside when nothing matches, so the library is :empty (written-shiurim.css). ?>
		<div class="nm-library<?php echo $filtering ? ' is-filtering' : ''; ?>" data-written-library><?php
		foreach ( array_slice( $shown, 0, $first_weeks ) as $index => $week ) {
			ner_michoel_render_written_week( $week, ! $filtering && 0 === $index );
		}
		?></div>

		<div class="nm-library__more" data-written-more hidden>
			<button type="button" class="nm-btn"><?php esc_html_e( 'Show earlier weeks', 'ner-michoel-child' ); ?></button>
		</div>

		<div class="nm-library__empty" data-written-empty<?php echo $shown ? ' hidden' : ''; ?>>
			<p><?php esc_html_e( 'Nothing matches those filters.', 'ner-michoel-child' ); ?></p>
			<button type="button" class="nm-btn" data-written-clear><?php esc_html_e( 'Show everything', 'ner-michoel-child' ); ?></button>
		</div>

		<?php ner_michoel_render_written_templates(); ?>
		<script type="application/json" data-written-index data-first-weeks="<?php echo esc_attr( $first_weeks ); ?>"><?php echo wp_json_encode( ner_michoel_written_index( $weeks ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
	<?php else : ?>
		<p class="sh-empty"><?php esc_html_e( 'No written shiurim yet.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
