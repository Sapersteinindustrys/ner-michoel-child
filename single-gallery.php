<?php
/**
 * A single gallery. Branches on gallery_type (photo vs. video/
 * shiurim-video — see ner_michoel_get_gallery_type()). Two layouts,
 * toggled top-right, both branches:
 * - Modern (default): photo = slider (stage + thumbnail strip, see
 *   .sh-gallery-slider in assets/js/custom.js); video = stacked
 *   oEmbed players.
 * - Classic: photo = plain thumbnail grid; video = same stacked
 *   oEmbed players (there's no meaningfully different "plain" video
 *   list — the embed itself is the content either way).
 */

get_header();

while ( have_posts() ) :
	the_post();

	$type   = ner_michoel_get_gallery_type( get_the_ID() );
	$layout = ner_michoel_get_layout();
	$is_video = in_array( $type, array( 'video', 'videoshiurim' ), true );
	?>

	<div class="nm-app<?php echo 'classic' === $layout ? ' nm-app--classic' : ''; ?>">
		<header class="sh-page-header">
			<h1><?php the_title(); ?></h1>
			<p class="<?php echo 'classic' === $layout ? 'sh-classic-meta' : 'sh-hero__meta'; ?>">
				<?php echo esc_html( get_the_date() ); ?>
				<?php if ( ! $is_video ) : ?>
					<?php $count = ner_michoel_get_gallery_image_count( get_the_ID() ); ?>
					&middot;
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of photos */
							_n( '%d photo', '%d photos', $count, 'ner-michoel-child' ),
							$count
						)
					);
					?>
				<?php endif; ?>
			</p>
		</header>

		<?php if ( get_the_content() ) : ?>
			<section class="sh-content" style="margin-bottom: 24px;"><?php the_content(); ?></section>
		<?php endif; ?>

		<?php if ( $is_video ) : ?>
			<?php
			$videos = ner_michoel_get_gallery_videos( get_the_ID() );
			if ( ! $videos ) :
				?>
				<p class="sh-empty"><?php esc_html_e( 'No videos added yet.', 'ner-michoel-child' ); ?></p>
				<?php
			else :
				?>
				<div class="sh-video-list">
					<?php foreach ( $videos as $video_url ) : ?>
						<div class="sh-video-list__item">
							<?php
							$embed = wp_oembed_get( $video_url );
							if ( $embed ) {
								echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_oembed_get() sanitizes its output.
							} else {
								echo '<p class="sh-empty">' . esc_html( $video_url ) . '</p>';
							}
							?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php
			endif;
			?>
		<?php else : ?>
			<?php
			$images = ner_michoel_get_gallery_images( get_the_ID() );
			$count  = count( $images );
			if ( ! $images ) :
				?>
				<p class="sh-empty"><?php esc_html_e( 'No photos uploaded yet.', 'ner-michoel-child' ); ?></p>
				<?php
			elseif ( 'classic' === $layout ) :
				?>
				<div class="sh-classic-photo-grid">
					<?php foreach ( $images as $image ) : ?>
						<a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank" rel="noopener">
							<img src="<?php echo esc_url( $image['thumb'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" />
						</a>
					<?php endforeach; ?>
				</div>
				<?php
			else :
				?>
				<div class="sh-gallery-slider" data-images="<?php echo esc_attr( wp_json_encode( $images ) ); ?>">
					<div class="sh-gallery-slider__stage">
						<?php if ( $count > 1 ) : ?>
							<button type="button" class="sh-gallery-slider__nav sh-gallery-slider__nav--prev" aria-label="<?php esc_attr_e( 'Previous', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'prev' ); ?></button>
						<?php endif; ?>
						<img
							class="sh-gallery-slider__image"
							src="<?php echo esc_url( $images[0]['url'] ); ?>"
							width="<?php echo esc_attr( $images[0]['width'] ); ?>"
							height="<?php echo esc_attr( $images[0]['height'] ); ?>"
							alt="<?php echo esc_attr( $images[0]['alt'] ); ?>"
						/>
						<?php if ( $count > 1 ) : ?>
							<button type="button" class="sh-gallery-slider__nav sh-gallery-slider__nav--next" aria-label="<?php esc_attr_e( 'Next', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'next' ); ?></button>
						<?php endif; ?>
					</div>
					<?php if ( $images[0]['caption'] ) : ?>
						<div class="sh-gallery-slider__caption"><?php echo esc_html( $images[0]['caption'] ); ?></div>
					<?php else : ?>
						<div class="sh-gallery-slider__caption"></div>
					<?php endif; ?>
					<?php if ( $count > 1 ) : ?>
						<div class="sh-gallery-slider__thumbs">
							<?php foreach ( $images as $index => $image ) : ?>
								<button type="button" class="sh-gallery-slider__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>" data-index="<?php echo esc_attr( $index ); ?>">
									<img src="<?php echo esc_url( $image['thumb'] ); ?>" alt="" loading="lazy" />
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php
			endif;
			?>
		<?php endif; ?>
	</div>

	<?php
endwhile;

get_footer();
