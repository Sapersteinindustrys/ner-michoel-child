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

ner_michoel_render_gallery_page( $galleries, '', __( 'All galleries', 'ner-michoel-child' ) );
?>

<?php get_footer(); ?>
