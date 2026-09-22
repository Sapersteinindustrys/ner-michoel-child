<?php
/**
 * A single shiur — audio or video (ner_michoel_shiur_is_video(), see
 * inc/template-tags.php). Two layouts, toggled top-right:
 * - Modern (default): Spotify "track" page equivalent for audio; a
 *   real <video> player in place of the hero art for video.
 * - Classic: a plain content page — native play/download links for
 *   audio, a plain <video controls> element for video.
 */

get_header();

while ( have_posts() ) :
	the_post();

	$is_video      = ner_michoel_shiur_is_video( get_the_ID() );
	$video_url     = ( $is_video && function_exists( 'ner_michoel_get_shiur_video_url' ) ) ? ner_michoel_get_shiur_video_url( get_the_ID() ) : '';
	$audio_url     = ner_michoel_get_shiur_audio_url( get_the_ID() );
	$speaker_terms = get_the_terms( get_the_ID(), 'speaker' );
	$series_terms  = get_the_terms( get_the_ID(), 'series' );
	$poster        = get_the_post_thumbnail_url( get_the_ID(), 'large' );
	$download_url  = function_exists( 'ner_michoel_get_shiur_download_url' )
		? ner_michoel_get_shiur_download_url( get_the_ID() )
		: ( $is_video ? $video_url : $audio_url );
	$dedication    = function_exists( 'ner_michoel_get_shiur_dedication' ) ? ner_michoel_get_shiur_dedication( get_the_ID() ) : '';

	// "More from this Speaker" — same speaker, current shiur excluded,
	// capped at 6. No backend support needed: reuses the existing
	// ner_michoel_get_speaker_shiurim() accessor.
	$related = array();
	if ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) {
		$current_id = get_the_ID();
		$related    = array_slice(
			array_values(
				array_filter(
					ner_michoel_get_speaker_shiurim( $speaker_terms[0]->term_id ),
					function ( $s ) use ( $current_id ) {
						return $s->ID !== $current_id;
					}
				)
			),
			0,
			6
		);
	}

	if ( 'classic' === ner_michoel_get_layout() ) :
		?>
		<div class="shiurim-app shiurim-app--classic">
			<header class="sh-page-header">
				<h1><?php the_title(); ?></h1>
				<p class="sh-classic-meta">
					<?php if ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) : ?>
						<a href="<?php echo esc_url( get_term_link( $speaker_terms[0] ) ); ?>"><?php echo esc_html( $speaker_terms[0]->name ); ?></a>
					<?php endif; ?>
					<?php if ( $series_terms && ! is_wp_error( $series_terms ) ) : ?>
						&middot; <a href="<?php echo esc_url( get_term_link( $series_terms[0] ) ); ?>"><?php echo esc_html( $series_terms[0]->name ); ?></a>
					<?php endif; ?>
					&middot; <?php echo esc_html( get_the_date() ); ?>
				</p>
				<?php if ( $is_video && $video_url ) : ?>
					<div class="sh-video-player">
						<video controls preload="metadata"<?php echo $poster ? ' poster="' . esc_url( $poster ) . '"' : ''; ?>>
							<source src="<?php echo esc_url( $video_url ); ?>" />
						</video>
					</div>
					<?php if ( $download_url ) : ?>
						<p><a href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'ner-michoel-child' ); ?></a></p>
					<?php endif; ?>
				<?php elseif ( $audio_url ) : ?>
					<p>
						<a href="<?php echo esc_url( $audio_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Play', 'ner-michoel-child' ); ?></a>
						&middot;
						<a href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'ner-michoel-child' ); ?></a>
					</p>
				<?php else : ?>
					<p class="sh-empty"><?php esc_html_e( 'Nothing uploaded yet.', 'ner-michoel-child' ); ?></p>
				<?php endif; ?>
			</header>
			<?php if ( get_the_content() ) : ?>
				<section class="sh-content"><?php the_content(); ?></section>
			<?php endif; ?>
			<?php if ( $dedication ) : ?>
				<p class="sh-dedication"><?php echo nl2br( esc_html( $dedication ) ); ?></p>
			<?php endif; ?>
			<?php if ( $related ) : ?>
				<section class="sh-section">
					<h2 class="sh-section__title"><?php esc_html_e( 'More from this Speaker', 'ner-michoel-child' ); ?></h2>
					<ul class="sh-classic-related-list">
						<?php foreach ( $related as $r ) : ?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $r ) ); ?>"><?php echo esc_html( get_the_title( $r ) ); ?></a>
								<span class="sh-classic-meta"><?php echo esc_html( get_the_date( '', $r ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>
		<?php
		continue;
	endif;

	$queue = $is_video ? array() : ner_michoel_build_track_queue( array( get_post() ) );
	?>

	<div class="shiurim-app">
		<?php if ( $is_video && $video_url ) : ?>
			<header class="sh-page-header">
				<h1><?php the_title(); ?></h1>
				<p class="sh-hero__meta">
					<?php if ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) : ?>
						<a href="<?php echo esc_url( get_term_link( $speaker_terms[0] ) ); ?>"><?php echo esc_html( $speaker_terms[0]->name ); ?></a>
					<?php endif; ?>
					<?php if ( $series_terms && ! is_wp_error( $series_terms ) ) : ?>
						&middot; <a href="<?php echo esc_url( get_term_link( $series_terms[0] ) ); ?>"><?php echo esc_html( $series_terms[0]->name ); ?></a>
					<?php endif; ?>
				</p>
			</header>
			<div class="sh-video-player">
				<video controls preload="metadata"<?php echo $poster ? ' poster="' . esc_url( $poster ) . '"' : ''; ?>>
					<source src="<?php echo esc_url( $video_url ); ?>" />
				</video>
			</div>
			<?php if ( $download_url ) : ?>
				<p class="sh-hero__actions"><a class="sh-download-link" href="<?php echo esc_url( $download_url ); ?>"><?php echo ner_michoel_icon( 'download' ); ?> <?php esc_html_e( 'Download', 'ner-michoel-child' ); ?></a></p>
			<?php endif; ?>
		<?php else : ?>
			<header class="sh-hero">
				<div class="sh-hero__art sh-hero__art--square">
					<?php if ( $poster ) : ?>
						<img src="<?php echo esc_url( $poster ); ?>" alt="" />
					<?php else : ?>
						<?php echo ner_michoel_placeholder_art( get_the_title() ); ?>
					<?php endif; ?>
				</div>
				<div class="sh-hero__info">
					<span class="sh-hero__kicker"><?php esc_html_e( 'Shiur', 'ner-michoel-child' ); ?></span>
					<h1><?php the_title(); ?></h1>
					<p class="sh-hero__meta">
						<?php if ( $speaker_terms && ! is_wp_error( $speaker_terms ) ) : ?>
							<a href="<?php echo esc_url( get_term_link( $speaker_terms[0] ) ); ?>"><?php echo esc_html( $speaker_terms[0]->name ); ?></a>
						<?php endif; ?>
						<?php if ( $series_terms && ! is_wp_error( $series_terms ) ) : ?>
							&middot; <a href="<?php echo esc_url( get_term_link( $series_terms[0] ) ); ?>"><?php echo esc_html( $series_terms[0]->name ); ?></a>
						<?php endif; ?>
					</p>
					<?php if ( $queue ) : ?>
						<div class="sh-hero__actions">
							<button
								type="button"
								class="sh-play-all"
								data-play-queue="<?php echo esc_attr( wp_json_encode( $queue ) ); ?>"
								data-play-index="0"
							><?php echo ner_michoel_icon( 'play' ); ?> <?php esc_html_e( 'Play', 'ner-michoel-child' ); ?></button>
							<?php if ( $download_url ) : ?>
								<a class="sh-download-link" href="<?php echo esc_url( $download_url ); ?>"><?php echo ner_michoel_icon( 'download' ); ?> <?php esc_html_e( 'Download', 'ner-michoel-child' ); ?></a>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<p class="sh-empty"><?php esc_html_e( 'No audio uploaded yet.', 'ner-michoel-child' ); ?></p>
					<?php endif; ?>
				</div>
			</header>
		<?php endif; ?>

		<?php if ( get_the_content() ) : ?>
			<section class="sh-section sh-content">
				<?php the_content(); ?>
			</section>
		<?php endif; ?>

		<?php if ( $dedication ) : ?>
			<p class="sh-dedication sh-section"><?php echo nl2br( esc_html( $dedication ) ); ?></p>
		<?php endif; ?>

		<?php if ( $related ) : ?>
			<section class="sh-section">
				<h2 class="sh-section__title"><?php esc_html_e( 'More from this Speaker', 'ner-michoel-child' ); ?></h2>
				<?php ner_michoel_render_tracklist( $related, false ); ?>
			</section>
		<?php endif; ?>
	</div>

	<?php
endwhile;

get_footer();
