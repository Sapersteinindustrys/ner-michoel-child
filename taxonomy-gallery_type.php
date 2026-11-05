<?php
/**
 * One gallery-type section — this is what the nav actually links to
 * (/galleries/photo/, /galleries/video/, /galleries/videoshiurim/),
 * not the generic post-type archive.
 */

get_header();

$term = get_queried_object();

$labels = array(
	'photo'        => __( 'Photo Galleries', 'ner-michoel-child' ),
	'video'        => __( 'Video Gallery', 'ner-michoel-child' ),
	'videoshiurim' => __( 'Shiurim Video Gallery', 'ner-michoel-child' ),
);
$title = isset( $labels[ $term->slug ] ) ? $labels[ $term->slug ] : $term->name;

$galleries = new WP_Query(
	array(
		'post_type'      => 'gallery',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'gallery_type',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			),
		),
	)
);
ner_michoel_render_gallery_page( $galleries, $term->slug, $title );
?>

<?php get_footer(); ?>
