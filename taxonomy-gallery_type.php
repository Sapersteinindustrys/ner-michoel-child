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
?>

<div class="nm-app">
	<header class="sh-page-header">
		<h1><?php echo esc_html( $title ); ?></h1>
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
