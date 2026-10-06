<?php
/**
 * A single written shiur, as a reading view: the title in serif type, the
 * actions (read full screen, download, save, share), and the PDF laid on
 * a desk. Wide screens show the PDF inline (assets/js/written-reader.js);
 * phones get a cover that opens it in their own PDF reader, since most
 * phone browsers can't embed one. Below: the rest of that week, more on
 * the same parsha, and newer/older.
 */

get_header();

while ( have_posts() ) :
	the_post();

	$item    = ner_michoel_written_data( get_the_ID(), true );
	$related = ner_michoel_written_related( get_the_ID() );
	$newer   = get_next_post();
	$older   = get_previous_post();
	$topic   = trim( $item['sefer'] . ( $item['topic'] && $item['topic'] !== $item['sefer'] ? ' · ' . $item['topic'] : '' ) );
	$series  = $item['series'] ? get_term_link( $item['series'] ) : '';
	$speaker = $item['speaker'] ? get_term_link( $item['speaker'] ) : '';
	?>
	<div class="nm-app nm-written-page nm-reading-room nm-reading nm-accent--<?php echo esc_attr( $item['slug'] ); ?><?php echo 'classic' === ner_michoel_get_layout() ? ' nm-app--classic' : ''; ?>">
		<?php ner_michoel_render_back_button(); ?>

		<nav class="nm-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'ner-michoel-child' ); ?>">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'written_shiur' ) ); ?>"><?php esc_html_e( 'Written Shiurim', 'ner-michoel-child' ); ?></a>
			<?php if ( $topic ) : ?>
				<span class="nm-crumbs__sep" aria-hidden="true">/</span>
				<?php if ( $series && ! is_wp_error( $series ) ) : ?>
					<a href="<?php echo esc_url( $series ); ?>"><?php echo esc_html( $topic ); ?></a>
				<?php else : ?>
					<span><?php echo esc_html( $topic ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</nav>

		<header class="nm-doc">
			<?php if ( $item['note'] ) : ?>
				<p class="nm-doc__kicker"><?php echo esc_html( $item['note'] ); ?></p>
			<?php endif; ?>
			<h1 class="nm-doc__title"><?php echo esc_html( $item['main'] ); ?></h1>
			<?php if ( $item['summary'] ) : ?>
				<p class="nm-doc__lede"><?php echo esc_html( $item['summary'] ); ?></p>
			<?php endif; ?>
			<p class="nm-doc__byline">
				<?php if ( $item['speaker'] ) : ?>
					<span class="nm-doc__author">
						<?php esc_html_e( 'by', 'ner-michoel-child' ); ?>
						<?php if ( $speaker && ! is_wp_error( $speaker ) ) : ?>
							<a href="<?php echo esc_url( $speaker ); ?>"><?php echo esc_html( $item['speaker']->name ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['speaker']->name ); ?>
						<?php endif; ?>
					</span>
				<?php endif; ?>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( $item['date'] ); ?></time>
				<?php if ( $item['filesize'] ) : ?>
					<span><?php echo esc_html( sprintf( /* translators: %s: file size, e.g. 175 KB */ __( 'PDF · %s', 'ner-michoel-child' ), size_format( $item['filesize'], 0 ) ) ); ?></span>
				<?php endif; ?>
			</p>

			<div class="nm-doc__actions">
				<?php if ( $item['pdf_url'] ) : ?>
					<?php // A plain link to the PDF until written-reader.js turns it into full screen. ?>
					<a class="nm-btn nm-btn--primary nm-only-wide" href="<?php echo esc_url( $item['pdf_url'] ); ?>" target="_blank" rel="noopener" data-reader-focus>
						<?php echo ner_michoel_line_icon( 'maximize' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
						<?php esc_html_e( 'Read full screen', 'ner-michoel-child' ); ?>
					</a>
					<a class="nm-btn nm-btn--primary nm-only-narrow" href="<?php echo esc_url( $item['pdf_url'] ); ?>" target="_blank" rel="noopener" data-reader-open>
						<?php echo ner_michoel_line_icon( 'book-open' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
						<?php esc_html_e( 'Open & read', 'ner-michoel-child' ); ?>
					</a>
					<?php if ( $item['download_url'] ) : ?>
						<a class="nm-btn" href="<?php echo esc_url( $item['download_url'] ); ?>" data-reader-open>
							<?php echo ner_michoel_line_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
							<?php esc_html_e( 'Download', 'ner-michoel-child' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
				<?php ner_michoel_render_save_button( get_the_ID() ); ?>
				<button type="button" class="nm-btn" data-reader-share data-share-title="<?php echo esc_attr( $item['title'] ); ?>" data-share-copied="<?php esc_attr_e( 'Link copied', 'ner-michoel-child' ); ?>">
					<?php echo ner_michoel_line_icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
					<span data-share-label><?php esc_html_e( 'Share', 'ner-michoel-child' ); ?></span>
				</button>
			</div>
		</header>

		<?php if ( $item['pdf_url'] ) : ?>
			<div class="nm-desk" data-nm-reader data-pdf-src="<?php echo esc_url( $item['pdf_url'] ); ?>" data-post-id="<?php echo esc_attr( $item['id'] ); ?>" data-title="<?php echo esc_attr( $item['title'] ); ?>" role="region" aria-label="<?php esc_attr_e( 'Reading view', 'ner-michoel-child' ); ?>">
				<div class="nm-desk__bar">
					<span class="nm-desk__bar-title"><?php echo esc_html( $item['main'] ); ?></span>
					<?php if ( $item['download_url'] ) : ?>
						<a class="nm-desk__tool" href="<?php echo esc_url( $item['download_url'] ); ?>" aria-label="<?php esc_attr_e( 'Download', 'ner-michoel-child' ); ?>" title="<?php esc_attr_e( 'Download', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_line_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
					<?php endif; ?>
					<a class="nm-desk__tool" href="<?php echo esc_url( $item['pdf_url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Open in a new tab', 'ner-michoel-child' ); ?>" title="<?php esc_attr_e( 'Open in a new tab', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_line_icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
					<button type="button" class="nm-desk__tool nm-desk__close" data-reader-close aria-label="<?php esc_attr_e( 'Close full screen', 'ner-michoel-child' ); ?>" title="<?php esc_attr_e( 'Close (Esc)', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_line_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></button>
				</div>

				<div class="nm-desk__stage">
					<div class="nm-desk__paper" data-reader-frame>
						<p class="nm-desk__loading"><?php esc_html_e( 'Opening the PDF…', 'ner-michoel-child' ); ?></p>
					</div>

					<a class="nm-cover" href="<?php echo esc_url( $item['pdf_url'] ); ?>" target="_blank" rel="noopener" data-reader-open>
						<span class="nm-cover__band"><?php echo esc_html( $topic ? $topic : __( 'Written Shiur', 'ner-michoel-child' ) ); ?></span>
						<span class="nm-cover__title"><?php echo esc_html( $item['main'] ); ?></span>
						<?php if ( $item['note'] ) : ?>
							<span class="nm-cover__note"><?php echo esc_html( $item['note'] ); ?></span>
						<?php endif; ?>
						<?php if ( $item['speaker'] ) : ?>
							<span class="nm-cover__by"><?php echo esc_html( $item['speaker']->name ); ?></span>
						<?php endif; ?>
						<?php if ( $item['summary'] ) : ?>
							<span class="nm-cover__summary"><?php echo esc_html( $item['summary'] ); ?></span>
						<?php else : ?>
							<span class="nm-cover__lines" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
						<?php endif; ?>
						<span class="nm-cover__cta">
							<?php echo ner_michoel_line_icon( 'book-open' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
							<?php esc_html_e( 'Tap to open and read', 'ner-michoel-child' ); ?>
						</span>
					</a>
				</div>
			</div>
		<?php else : ?>
			<p class="sh-empty"><?php esc_html_e( 'The PDF for this shiur hasn\'t been uploaded yet.', 'ner-michoel-child' ); ?></p>
		<?php endif; ?>

		<?php if ( get_the_content() ) : ?>
			<section class="nm-doc__content entry-content">
				<?php the_content(); ?>
			</section>
		<?php endif; ?>

		<?php if ( $related['week'] ) : ?>
			<section class="nm-related">
				<h2 class="nm-related__title"><?php esc_html_e( 'Also this week', 'ner-michoel-child' ); ?></h2>
				<div class="nm-related__sheets">
					<?php
					foreach ( $related['week'] as $week_item ) {
						ner_michoel_render_written_sheet( $week_item );
					}
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $related['series'] ) : ?>
			<section class="nm-related">
				<h2 class="nm-related__title">
					<?php
					/* translators: %s: parsha or topic, e.g. Ki Savo */
					echo esc_html( sprintf( __( 'More on %s', 'ner-michoel-child' ), $item['topic'] ? $item['topic'] : $item['sefer'] ) );
					?>
				</h2>
				<div class="nm-related__sheets">
					<?php
					foreach ( $related['series'] as $series_item ) {
						ner_michoel_render_written_sheet( $series_item );
					}
					?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $newer || $older ) : ?>
			<nav class="nm-pager" aria-label="<?php esc_attr_e( 'More written shiurim', 'ner-michoel-child' ); ?>">
				<?php if ( $newer ) : ?>
					<a class="nm-pager__link nm-pager__link--newer" href="<?php echo esc_url( get_permalink( $newer ) ); ?>">
						<span class="nm-pager__dir"><?php echo ner_michoel_line_icon( 'arrow-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?><?php esc_html_e( 'Newer', 'ner-michoel-child' ); ?></span>
						<span class="nm-pager__title"><?php echo esc_html( get_the_title( $newer ) ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $older ) : ?>
					<a class="nm-pager__link nm-pager__link--older" href="<?php echo esc_url( get_permalink( $older ) ); ?>">
						<span class="nm-pager__dir"><?php esc_html_e( 'Older', 'ner-michoel-child' ); ?><?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
						<span class="nm-pager__title"><?php echo esc_html( get_the_title( $older ) ); ?></span>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
