<?php
/**
 * Template Name: Connections (Ner Michoel)
 *
 * The original site's "Connections" page is a magazine archive — a
 * grid of issue cover thumbnails, each linking to a PDF. No CPT for
 * this (out of scope per the static-page split) — the admin builds
 * the grid as a WP Gallery block (or plain image+link pairs) in the
 * page editor; this template just gives it a nice wrapper and grid
 * spacing to sit in.
 */

get_header();
?>

<div class="nm-page nm-page--connections">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="nm-page__header">
			<h1><?php the_title(); ?></h1>
			<p class="nm-page__intro"><?php esc_html_e( 'Archived issues of Connections magazine.', 'ner-michoel-child' ); ?></p>
		</header>

		<div class="nm-page__content entry-content nm-connections-grid">
			<?php the_content(); ?>
		</div>
		<?php
	endwhile;
	?>
</div>

<?php get_footer(); ?>
