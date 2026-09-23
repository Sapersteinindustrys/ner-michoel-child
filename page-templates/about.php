<?php
/**
 * Template Name: About (Ner Michoel)
 *
 * Real content ported from the original site's About page — yeshiva
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
			<h2><?php esc_html_e( 'Yeshivas Toras Moshe', 'ner-michoel-child' ); ?></h2>
			<p><?php esc_html_e( 'Yeshivas Toras Moshe was founded in Jerusalem in 1982 by Rav Moshe Meiselman shlita, as a memorial to Rav Moshe Soloveitchik zt"l. The Yeshiva has experienced tremendous growth. With 130 bochurim and 50 avreichim in the kollel, enrollment is at an all-time high.', 'ner-michoel-child' ); ?></p>

			<h2><?php esc_html_e( 'The Ner Michoel Alumni Association', 'ner-michoel-child' ); ?></h2>
			<p><?php esc_html_e( 'The Ner Michoel Alumni Association of Yeshivas Toras Moshe was founded in September 2012 to maintain the connections between the Yeshiva and its alumni around the world. Since then, it has produced educational materials and hosted events internationally.', 'ner-michoel-child' ); ?></p>
			<p><?php esc_html_e( 'The Association is named for Rabbi Michoel Weiner zt"l, a contemporary and close friend of Rav Meiselman — the two grew up in Boston together and maintained a mutual feeling of respect and appreciation throughout their lives.', 'ner-michoel-child' ); ?></p>

			<h2><?php esc_html_e( 'Rabbi Michoel Weiner zt"l', 'ner-michoel-child' ); ?></h2>
			<p><?php esc_html_e( 'Reb Michoel studied under some of the most prominent Torah scholars of his generation and was known for his meticulous scholarship. His diligence in learning was legendary; he would sit for long hours, toiling in the understanding of Torah with complete focus. His commentaries on Talmudic texts are noted for their halakhic precision.', 'ner-michoel-child' ); ?></p>
			<p><?php esc_html_e( 'Alongside his intensity in learning, Reb Michoel possessed an extraordinary sense of humor, and maintained a joyful home centered on spiritual service.', 'ner-michoel-child' ); ?></p>

			<?php if ( get_the_content() ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>
		</div>
		<?php
	endwhile;
	?>
</div>

<?php get_footer(); ?>
