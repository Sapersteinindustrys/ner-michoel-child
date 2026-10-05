<?php
/**
 * Written Shiurim archive (/written-shiurim/): the PDF lectures, newest
 * first. In the app look (ner_michoel_is_written_context()), with the
 * layout toggle, but one list in both layouts: there's no audio queue to
 * browse, so Modern/Classic doesn't change what's shown.
 */

get_header();

$layout = ner_michoel_get_layout();
?>

<div class="nm-app<?php echo 'classic' === $layout ? ' nm-app--classic' : ''; ?>">
	<?php ner_michoel_render_back_button( home_url( '/' ) ); ?>
	<header class="sh-page-header">
		<h1><?php post_type_archive_title(); ?></h1>
	</header>

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

<?php get_footer(); ?>
