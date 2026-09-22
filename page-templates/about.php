<?php
/**
 * Template Name: About (Ner Michoel)
 *
 * Static content ported from the original site's About page — yeshiva
 * history, the Rabbi Michoel Weiner memorial section, and Ner
 * Michoel's own founding. No layout toggle; a static page doesn't
 * benefit from a Modern/Classic split the way browsable content does.
 */

get_header();
?>

<div class="nm-page nm-page--about">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="nm-page__header">
			<h1><?php the_title(); ?></h1>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="nm-page__figure">
				<?php the_post_thumbnail( 'large' ); ?>
			</figure>
		<?php endif; ?>

		<div class="nm-page__content entry-content">
			<?php the_content(); ?>
		</div>
		<?php
	endwhile;
	?>
</div>

<?php get_footer(); ?>
