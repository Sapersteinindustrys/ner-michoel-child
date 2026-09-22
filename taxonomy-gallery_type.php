<?php
/**
 * One gallery-type section — this is what the nav actually links to
 * (/galleries/photo/, /galleries/video/, /galleries/videoshiurim/),
 * not the generic post-type archive. Two layouts, toggled top-right.
 */

get_header();

$term = get_queried_object();

$labels = array(
	'photo'        => __( 'Photo Galleries', 'ner-michoel-child' ),
	'video'        => __( 'Video Gallery', 'ner-michoel-child' ),
	'videoshiurim' => __( 'Shiurim Video Gallery', 'ner-michoel-child' ),
);
$title = isset( $labels[ $term->slug ] ) ? $labels[ $term->slug ] : $term->name;

$layout    = ner_michoel_get_layout();
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

<div class="nm-app<?php echo 'classic' === $layout ? ' nm-app--classic' : ''; ?>">
	<header class="sh-page-header">
		<h1><?php echo esc_html( $title ); ?></h1>
	</header>

	<?php if ( ! $galleries->have_posts() ) : ?>
		<p class="sh-empty"><?php esc_html_e( 'No galleries yet.', 'ner-michoel-child' ); ?></p>
	<?php elseif ( 'classic' === $layout ) : ?>
		<div class="sh-classic-gallery-grid">
			<?php
			while ( $galleries->have_posts() ) :
				$galleries->the_post();
				$cover = ner_michoel_get_gallery_cover_url( get_the_ID() );
				?>
				<a class="sh-classic-gallery-card" href="<?php the_permalink(); ?>">
					<div class="sh-classic-gallery-card__art">
						<?php if ( $cover ) : ?>
							<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<?php echo ner_michoel_placeholder_art( get_the_title() ); ?>
						<?php endif; ?>
					</div>
					<div class="sh-classic-gallery-card__title"><?php the_title(); ?></div>
					<div class="sh-classic-gallery-card__meta"><?php echo esc_html( ner_michoel_gallery_card_subtitle( get_the_ID() ) ); ?></div>
				</a>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
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
