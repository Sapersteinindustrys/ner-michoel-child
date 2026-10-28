<?php
/**
 * Template Name: News & Events (Ner Michoel)
 *
 * Combines News & Events (native WP Posts, category "news") with
 * Mazal Tov announcements on one page, matching the original site's
 * /index/news-events-mazal-tov.
 *
 * Mazal Tov depends on a `mazal_tov` CPT the backend session hasn't
 * shipped yet, so that section is written defensively (post_type_exists
 * / taxonomy_exists / function_exists checks) and simply doesn't render
 * until it lands — no template changes needed once it does.
 */

get_header();

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
?>

<div class="nm-app">
	<header class="sh-page-header">
		<h1><?php the_title(); ?></h1>
	</header>

	<?php get_template_part( 'template-parts/live-shiur' ); ?>

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

	<?php if ( $mazal_tovs || ner_michoel_mazal_share_ready() ) : ?>
	<section class="sh-section">
		<h2 class="sh-section__title"><?php esc_html_e( 'Mazal Tov', 'ner-michoel-child' ); ?></h2>
		<?php if ( ner_michoel_mazal_share_ready() ) : ?>
			<p class="sh-mazal-tov-section__actions"><?php ner_michoel_mazal_share_button( 'sh-mazal-tov-share-btn' ); ?></p>
		<?php endif; ?>
		<div class="sh-mazal-tov-list">
			<?php foreach ( $mazal_tovs as $mt ) : ?>
				<?php get_template_part( 'template-parts/mazal-tov-item', null, array( 'post' => $mt ) ); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php get_template_part( 'template-parts/mazal-tov-share' ); ?>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
