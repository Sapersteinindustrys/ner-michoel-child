<?php
/**
 * Every Mazal Tov, newest first (/mazal-tov/). Each reads "Name (years)" then what it
 * is, with no date. Visitors can share their own from the button at the top; it waits
 * for the admin's approval before it shows here.
 */

get_header();
?>

<div class="nm-app rv-page">
	<?php // The navy hero the homepage and the Shiurim Library open with (revamp.css section 10). ?>
	<header class="rv-ph">
		<div class="rv-ph__copy">
			<p class="rv-ph__eyebrow"><?php esc_html_e( 'Simchas', 'ner-michoel-child' ); ?></p>
			<h1 class="rv-ph__title"><?php esc_html_e( 'Mazal Tov', 'ner-michoel-child' ); ?></h1>
			<p class="rv-ph__lede"><?php esc_html_e( 'Engagements, weddings and births in the Toras Moshe family. Share your own simcha and we will post it here.', 'ner-michoel-child' ); ?></p>
		</div>
		<p class="rv-ph__actions"><?php ner_michoel_mazal_share_button( 'rv-ph__btn' ); ?></p>
	</header>

	<section class="sh-section rv-card">
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
