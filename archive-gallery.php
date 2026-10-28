<?php
/**
 * Galleries home — every gallery across all three types (photo,
 * video, shiurim-video) in one combined list, as a card grid.
 *
 * The real, type-specific nav links (/galleries/photo/, /video/,
 * /videoshiurim/) go to taxonomy-gallery_type.php instead — this page
 * is a fallback for anyone landing on the plain CPT archive URL.
 */

get_header();

$galleries = new WP_Query(
	array(
		'post_type'      => 'gallery',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

?>

<div class="nm-app">
	<header class="sh-page-header">
		<h1><?php post_type_archive_title(); ?></h1>
	</header>

	<?php if ( ! $galleries->have_posts() ) : ?>
		<p class="sh-empty"><?php esc_html_e( 'No galleries yet.', 'ner-michoel-child' ); ?></p>
	<?php else : ?>
		<div class="sh-grid">
			<?php
			while ( $galleries->have_posts() ) :
				$galleries->the_post();
				ner_michoel_render_media_card(
					array(
						'title'    => get_the_title(),
						'subtitle' => ner_michoel_gallery_card_subtitle( get_the_ID() ),
						'image'    => ner_michoel_get_gallery_cover_url( get_the_ID() ),
						'link'     => get_permalink(),
					)
				);
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
