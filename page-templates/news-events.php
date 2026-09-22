<?php
/**
 * Template Name: News & Events (Ner Michoel)
 *
 * Combines News & Events (native WP Posts, category "news") with
 * Mazal Tov announcements on one page, matching the original site's
 * /index/news-events-mazal-tov. Two layouts, toggled top-right.
 *
 * Mazal Tov depends on a `mazal_tov` CPT the backend session hasn't
 * shipped yet, so that section is written defensively (post_type_exists
 * / taxonomy_exists / function_exists checks) and simply doesn't render
 * until it lands — no template changes needed once it does.
 */

get_header();

$layout = ner_michoel_get_layout();

$news_query = new WP_Query(
	array(
		'post_type'      => 'post',
		'category_name'  => 'news',
		'posts_per_page' => 10,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$mazal_tovs = array();
if ( post_type_exists( 'mazal_tov' ) ) {
	$mt_query   = new WP_Query(
		array(
			'post_type'      => 'mazal_tov',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$mazal_tovs = $mt_query->posts;
}

$has_mazal_tov_type_tax = taxonomy_exists( 'mazal_tov_type' );
?>

<div class="nm-app<?php echo 'classic' === $layout ? ' nm-app--classic' : ''; ?>">
	<header class="sh-page-header">
		<h1><?php the_title(); ?></h1>
	</header>

	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'News & Events', 'ner-michoel-child' ); ?></h2>
		<?php if ( ! $news_query->have_posts() ) : ?>
			<p class="sh-empty"><?php esc_html_e( 'No news posted yet.', 'ner-michoel-child' ); ?></p>
		<?php else : ?>
			<div class="sh-news-list">
				<?php
				while ( $news_query->have_posts() ) :
					$news_query->the_post();
					?>
					<article class="sh-news-item">
						<div class="sh-news-item__date"><?php echo esc_html( get_the_date() ); ?></div>
						<div class="sh-news-item__body">
							<h3 class="sh-news-item__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<div class="sh-news-item__excerpt"><?php the_excerpt(); ?></div>
						</div>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $mazal_tovs ) : ?>
	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'Mazal Tov', 'ner-michoel-child' ); ?></h2>
		<div class="sh-mazal-tov-list">
			<?php foreach ( $mazal_tovs as $mt ) : ?>
				<?php
				$relationship = function_exists( 'ner_michoel_get_mazal_tov_relationship' ) ? ner_michoel_get_mazal_tov_relationship( $mt->ID ) : '';
				$years        = function_exists( 'ner_michoel_get_mazal_tov_years' ) ? ner_michoel_get_mazal_tov_years( $mt->ID ) : '';
				$type_name    = '';
				if ( $has_mazal_tov_type_tax ) {
					$type_terms = get_the_terms( $mt->ID, 'mazal_tov_type' );
					if ( $type_terms && ! is_wp_error( $type_terms ) ) {
						$type_name = $type_terms[0]->name;
					}
				}
				?>
				<div class="sh-mazal-tov-item">
					<?php if ( $type_name ) : ?>
						<span class="sh-mazal-tov-item__type"><?php echo esc_html( $type_name ); ?></span>
					<?php endif; ?>
					<span class="sh-mazal-tov-item__name">
						<?php if ( $relationship ) : ?>
							<?php echo esc_html( $relationship ); ?>
						<?php endif; ?>
						<?php echo esc_html( get_the_title( $mt ) ); ?>
						<?php if ( $years ) : ?>
							<span class="sh-mazal-tov-item__years">(<?php echo esc_html( $years ); ?>)</span>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
