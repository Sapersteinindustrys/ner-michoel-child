<?php
/**
 * The homepage — per the admin, this should read as friendly/welcoming
 * rather than a dense content dashboard, so it's deliberately its own
 * warm visual style (.nm-home-*): not the dark Modern app, not the
 * plain Classic library, and not toggled — a landing page doesn't
 * benefit from the same Modern/Classic split as browsable content.
 *
 * Pulls a small preview of live content (recent shiurim, news, mazal
 * tov) plus quick-link cards to every section. Written defensively
 * (post_type_exists / function_exists checks) so nothing here fatals
 * before real content or the mazal_tov CPT exist.
 *
 * Only renders if the admin sets a static front page in
 * Settings > Reading — WP falls back to home.php/index.php otherwise.
 */

get_header();

$recent_shiurim = get_posts(
	array(
		'post_type'      => 'shiur',
		'posts_per_page' => 4,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$recent_news = get_posts(
	array(
		'post_type'      => 'post',
		'category_name'  => 'news',
		'posts_per_page' => 2,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$recent_mazal_tovs = post_type_exists( 'mazal_tov' ) ? get_posts(
	array(
		'post_type'      => 'mazal_tov',
		'posts_per_page' => 3,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
) : array();

$recent_galleries = post_type_exists( 'gallery' ) ? get_posts(
	array(
		'post_type'      => 'gallery',
		'posts_per_page' => 4,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
) : array();

$shiurim_url = get_post_type_archive_link( 'shiur' );
$gallery_url = get_post_type_archive_link( 'gallery' );

// Admin-managed homepage slider (Site Control Panel > Homepage
// Slider). Falls back to a static welcome hero when no slides have
// been added yet, so the homepage is never blank on a fresh install.
$hero_slides = function_exists( 'ner_michoel_get_homepage_slider_images' ) ? ner_michoel_get_homepage_slider_images() : array();
?>

<div class="nm-home">

	<?php if ( $hero_slides ) : ?>
	<section class="nm-home-hero nm-home-hero--slider">
		<?php
		$hero_interval_ms = function_exists( 'ner_michoel_get_homepage_slider_interval_seconds' )
			? ner_michoel_get_homepage_slider_interval_seconds() * 1000
			: 6000;
		?>
		<div class="nm-hero-slider" data-interval="<?php echo esc_attr( $hero_interval_ms ); ?>">
			<?php foreach ( $hero_slides as $index => $slide ) : ?>
				<div class="nm-hero-slider__slide<?php echo 0 === $index ? ' is-active' : ''; ?>" style="background-image:url('<?php echo esc_url( $slide['url'] ); ?>');">
					<div class="nm-hero-slider__overlay">
						<div class="nm-home-hero__inner">
							<?php if ( ! empty( $slide['heading'] ) ) : ?>
								<h1><?php echo esc_html( $slide['heading'] ); ?></h1>
							<?php endif; ?>
							<?php if ( ! empty( $slide['subtext'] ) ) : ?>
								<p><?php echo esc_html( $slide['subtext'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $slide['link_url'] ) ) : ?>
								<?php
								$btn_bg     = function_exists( 'ner_michoel_get_homepage_slider_button_background' ) ? ner_michoel_get_homepage_slider_button_background() : '';
								$btn_radius = function_exists( 'ner_michoel_get_homepage_slider_button_radius' ) ? ner_michoel_get_homepage_slider_button_radius() : '';
								$btn_style  = $btn_bg ? sprintf( 'background:%s;border-radius:%s;box-shadow:none;', esc_attr( $btn_bg ), esc_attr( $btn_radius ) ) : '';
								?>
								<a class="nm-home-btn nm-home-btn--primary" style="<?php echo esc_attr( $btn_style ); ?>" href="<?php echo esc_url( $slide['link_url'] ); ?>">
									<?php echo esc_html( ! empty( $slide['link_text'] ) ? $slide['link_text'] : __( 'Learn More', 'ner-michoel-child' ) ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $hero_slides ) > 1 ) : ?>
			<button type="button" class="nm-hero-slider__nav nm-hero-slider__nav--prev" aria-label="<?php esc_attr_e( 'Previous slide', 'ner-michoel-child' ); ?>"><?php echo function_exists( 'ner_michoel_icon' ) ? ner_michoel_icon( 'prev' ) : ''; ?></button>
			<button type="button" class="nm-hero-slider__nav nm-hero-slider__nav--next" aria-label="<?php esc_attr_e( 'Next slide', 'ner-michoel-child' ); ?>"><?php echo function_exists( 'ner_michoel_icon' ) ? ner_michoel_icon( 'next' ) : ''; ?></button>
			<div class="nm-hero-slider__dots">
				<?php foreach ( $hero_slides as $index => $slide ) : ?>
					<button
						type="button"
						class="nm-hero-slider__dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
						data-index="<?php echo esc_attr( $index ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: slide number */ __( 'Slide %d', 'ner-michoel-child' ), $index + 1 ) ); ?>"
					></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php else : ?>
	<section class="nm-home-hero">
		<div class="nm-home-hero__inner">
			<span class="nm-home-hero__kicker"><?php esc_html_e( 'Yeshivas Toras Moshe Alumni Association', 'ner-michoel-child' ); ?></span>
			<h1><?php esc_html_e( 'Welcome to Ner Michoel', 'ner-michoel-child' ); ?></h1>
			<p><?php esc_html_e( 'Thousands of shiurim, community updates, and a way to stay close to the Yeshiva and each other — wherever you are.', 'ner-michoel-child' ); ?></p>
			<div class="nm-home-hero__actions">
				<?php if ( $shiurim_url ) : ?>
					<a class="nm-home-btn nm-home-btn--primary" href="<?php echo esc_url( $shiurim_url ); ?>"><?php esc_html_e( 'Browse Shiurim', 'ner-michoel-child' ); ?></a>
				<?php endif; ?>
				<a class="nm-home-btn nm-home-btn--secondary" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About Ner Michoel', 'ner-michoel-child' ); ?></a>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $recent_galleries ) : ?>
	<section class="nm-home-section nm-home-gallery-section">
		<div class="nm-home-section__head">
			<h2><?php esc_html_e( 'From Our Galleries', 'ner-michoel-child' ); ?></h2>
			<?php if ( $gallery_url ) : ?><a class="nm-home-more" href="<?php echo esc_url( $gallery_url ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> &rarr;</a><?php endif; ?>
		</div>
		<div class="nm-home-gallery-grid">
			<?php foreach ( $recent_galleries as $gallery ) : ?>
				<?php
				$cover   = function_exists( 'ner_michoel_get_gallery_cover_url' ) ? ner_michoel_get_gallery_cover_url( $gallery->ID ) : '';
				$caption = function_exists( 'ner_michoel_gallery_card_subtitle' ) ? ner_michoel_gallery_card_subtitle( $gallery->ID ) : get_the_date( '', $gallery );
				?>
				<a class="nm-home-gallery-card" href="<?php echo esc_url( get_permalink( $gallery ) ); ?>">
					<div class="nm-home-gallery-card__art">
						<?php if ( $cover ) : ?>
							<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span aria-hidden="true">📸</span>
						<?php endif; ?>
					</div>
					<div class="nm-home-gallery-card__title"><?php echo esc_html( get_the_title( $gallery ) ); ?></div>
					<div class="nm-home-gallery-card__meta"><?php echo esc_html( $caption ); ?></div>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<section class="nm-home-links">
		<a class="nm-home-link-card" href="<?php echo esc_url( $shiurim_url ? $shiurim_url : '#' ); ?>">
			<span class="nm-home-link-card__emoji" aria-hidden="true">🎧</span>
			<span class="nm-home-link-card__title"><?php esc_html_e( 'Shiurim', 'ner-michoel-child' ); ?></span>
			<span class="nm-home-link-card__desc"><?php esc_html_e( 'Audio & video lectures', 'ner-michoel-child' ); ?></span>
		</a>
		<a class="nm-home-link-card" href="<?php echo esc_url( $gallery_url ? $gallery_url : '#' ); ?>">
			<span class="nm-home-link-card__emoji" aria-hidden="true">📸</span>
			<span class="nm-home-link-card__title"><?php esc_html_e( 'Galleries', 'ner-michoel-child' ); ?></span>
			<span class="nm-home-link-card__desc"><?php esc_html_e( 'Photos & videos from events', 'ner-michoel-child' ); ?></span>
		</a>
		<a class="nm-home-link-card" href="<?php echo esc_url( home_url( '/news-events/' ) ); ?>">
			<span class="nm-home-link-card__emoji" aria-hidden="true">📰</span>
			<span class="nm-home-link-card__title"><?php esc_html_e( 'News & Events', 'ner-michoel-child' ); ?></span>
			<span class="nm-home-link-card__desc"><?php esc_html_e( 'Updates & Mazal Tovs', 'ner-michoel-child' ); ?></span>
		</a>
		<a class="nm-home-link-card" href="<?php echo esc_url( home_url( '/connections/' ) ); ?>">
			<span class="nm-home-link-card__emoji" aria-hidden="true">📖</span>
			<span class="nm-home-link-card__title"><?php esc_html_e( 'Connections', 'ner-michoel-child' ); ?></span>
			<span class="nm-home-link-card__desc"><?php esc_html_e( 'Magazine archive', 'ner-michoel-child' ); ?></span>
		</a>
		<a class="nm-home-link-card" href="<?php echo esc_url( home_url( '/contribute/' ) ); ?>">
			<span class="nm-home-link-card__emoji" aria-hidden="true">💛</span>
			<span class="nm-home-link-card__title"><?php esc_html_e( 'Contribute', 'ner-michoel-child' ); ?></span>
			<span class="nm-home-link-card__desc"><?php esc_html_e( 'Support the Yeshiva', 'ner-michoel-child' ); ?></span>
		</a>
		<a class="nm-home-link-card" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
			<span class="nm-home-link-card__emoji" aria-hidden="true">✉️</span>
			<span class="nm-home-link-card__title"><?php esc_html_e( 'Contact Us', 'ner-michoel-child' ); ?></span>
			<span class="nm-home-link-card__desc"><?php esc_html_e( 'We\'d love to hear from you', 'ner-michoel-child' ); ?></span>
		</a>
	</section>

	<?php if ( $recent_shiurim ) : ?>
	<section class="nm-home-section">
		<div class="nm-home-section__head">
			<h2><?php esc_html_e( 'Recent Shiurim', 'ner-michoel-child' ); ?></h2>
			<?php if ( $shiurim_url ) : ?><a class="nm-home-more" href="<?php echo esc_url( $shiurim_url ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> &rarr;</a><?php endif; ?>
		</div>
		<div class="nm-home-shiur-grid">
			<?php foreach ( $recent_shiurim as $shiur ) : ?>
				<?php
				$speaker_terms = get_the_terms( $shiur->ID, 'speaker' );
				$speaker_name  = ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) ? $speaker_terms[0]->name : '';
				$cover         = get_the_post_thumbnail_url( $shiur, 'medium' );
				?>
				<a class="nm-home-shiur-card" href="<?php echo esc_url( get_permalink( $shiur ) ); ?>">
					<div class="nm-home-shiur-card__art">
						<?php if ( $cover ) : ?>
							<img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span aria-hidden="true">🎧</span>
						<?php endif; ?>
					</div>
					<div class="nm-home-shiur-card__title"><?php echo esc_html( get_the_title( $shiur ) ); ?><?php ner_michoel_render_shiur_badges( $shiur->ID ); ?></div>
					<?php if ( $speaker_name ) : ?>
						<div class="nm-home-shiur-card__meta"><?php echo esc_html( $speaker_name ); ?></div>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $recent_news || $recent_mazal_tovs ) : ?>
	<section class="nm-home-section nm-home-section--split">
		<?php if ( $recent_news ) : ?>
		<div>
			<div class="nm-home-section__head">
				<h2><?php esc_html_e( 'What\'s New', 'ner-michoel-child' ); ?></h2>
				<a class="nm-home-more" href="<?php echo esc_url( home_url( '/news-events/' ) ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> &rarr;</a>
			</div>
			<div class="nm-home-news-list">
				<?php foreach ( $recent_news as $news_post ) : ?>
					<a class="nm-home-news-item" href="<?php echo esc_url( get_permalink( $news_post ) ); ?>">
						<span class="nm-home-news-item__date"><?php echo esc_html( get_the_date( '', $news_post ) ); ?></span>
						<span class="nm-home-news-item__title"><?php echo esc_html( get_the_title( $news_post ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $recent_mazal_tovs ) : ?>
		<div>
			<div class="nm-home-section__head">
				<h2><?php esc_html_e( 'Mazal Tov! 🎉', 'ner-michoel-child' ); ?></h2>
			</div>
			<div class="nm-home-mazaltov-banner">
				<?php foreach ( $recent_mazal_tovs as $mt ) : ?>
					<?php $relationship = function_exists( 'ner_michoel_get_mazal_tov_relationship' ) ? ner_michoel_get_mazal_tov_relationship( $mt->ID ) : ''; ?>
					<p>
						<?php if ( $relationship ) : ?><?php echo esc_html( $relationship ); ?> <?php endif; ?>
						<strong><?php echo esc_html( get_the_title( $mt ) ); ?></strong>
					</p>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<section class="nm-home-closing">
		<h2><?php esc_html_e( 'Stay Connected', 'ner-michoel-child' ); ?></h2>
		<p><?php esc_html_e( 'Update your contact info, join the alumni WhatsApp group, or reach out any time — we\'d love to hear how you\'re doing.', 'ner-michoel-child' ); ?></p>
		<a class="nm-home-btn nm-home-btn--primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Get in Touch', 'ner-michoel-child' ); ?></a>
	</section>

</div>

<?php get_footer(); ?>
