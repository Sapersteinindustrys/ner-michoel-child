<?php
/**
 * Every Mazal Tov, newest first (/mazal-tov/). Each reads "Name (years)" then what it
 * is, with no date. Visitors can share their own from the button at the top; it waits
 * for the admin's approval before it shows here.
 */

get_header();
?>

<div class="nm-app">
	<header class="sh-page-header">
		<h1><?php esc_html_e( 'Mazal Tov', 'ner-michoel-child' ); ?></h1>
	</header>

	<section class="sh-section">
		<p class="sh-mazal-tov-section__actions"><?php ner_michoel_mazal_share_button( 'sh-mazal-tov-share-btn' ); ?></p>

		<?php if ( ! have_posts() ) : ?>
			<p class="sh-empty"><?php esc_html_e( 'No Mazal Tovs posted yet.', 'ner-michoel-child' ); ?></p>
		<?php else : ?>
			<div class="sh-mazal-tov-list">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/mazal-tov-item', null, array( 'post' => get_post() ) );
				endwhile;
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php endif; ?>
	</section>

	<?php get_template_part( 'template-parts/mazal-tov-share' ); ?>
</div>

<?php get_footer(); ?>
