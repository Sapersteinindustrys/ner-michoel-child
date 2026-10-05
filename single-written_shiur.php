<?php
/**
 * A single written shiur: title, speaker, series, date, the PDF, and the
 * description. The PDF is shown inline on wide screens. Phones often can't
 * embed a PDF, so the open and download links sit above the viewer, and
 * the viewer is hidden on narrow screens (custom CSS).
 */

get_header();

while ( have_posts() ) :
	the_post();

	$post_id       = get_the_ID();
	$speaker_terms = get_the_terms( $post_id, 'speaker' );
	$series_terms  = get_the_terms( $post_id, 'series' );
	$pdf_url       = function_exists( 'ner_michoel_get_written_shiur_pdf_url' ) ? ner_michoel_get_written_shiur_pdf_url( $post_id ) : '';
	$download_url  = function_exists( 'ner_michoel_get_written_shiur_download_url' ) ? ner_michoel_get_written_shiur_download_url( $post_id ) : '';
	?>
	<div class="nm-app nm-written-page<?php echo 'classic' === ner_michoel_get_layout() ? ' nm-app--classic' : ''; ?>">
		<?php ner_michoel_render_back_button(); ?>
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
			<?php ner_michoel_render_save_button( $post_id ); ?>
		</header>

		<?php if ( $pdf_url ) : ?>
			<p class="sh-written-actions">
				<a class="sh-download-link" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open PDF', 'ner-michoel-child' ); ?></a>
				<?php if ( $download_url ) : ?>
					<a class="sh-download-link" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download PDF', 'ner-michoel-child' ); ?></a>
				<?php endif; ?>
			</p>
			<?php
			// The viewer loads by itself on wide screens (assets/js/custom.js). Phones
			// get the links above only: many can't embed a PDF, and the viewer is hidden
			// there. data-post-id lets the script record History for this written shiur.
			?>
			<div class="sh-pdf-viewer" data-pdf-viewer data-pdf-src="<?php echo esc_url( $pdf_url ); ?>" data-pdf-title="<?php echo esc_attr( get_the_title() ); ?>" data-post-id="<?php echo esc_attr( $post_id ); ?>"></div>
		<?php else : ?>
			<p class="sh-empty"><?php esc_html_e( 'The PDF for this shiur hasn\'t been uploaded yet.', 'ner-michoel-child' ); ?></p>
		<?php endif; ?>

		<?php if ( get_the_content() ) : ?>
			<section class="sh-section sh-content">
				<?php the_content(); ?>
			</section>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
