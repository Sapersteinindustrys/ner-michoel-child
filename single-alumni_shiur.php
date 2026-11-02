<?php
/**
 * One shiur in the Alumni section (ner-michoel-core includes/alumni-section.php
 * only lets alumni this far). Kept simple and separate from the public shiur
 * page: no links to public speaker or series pages, no public queue.
 */

get_header();

while ( have_posts() ) :
	the_post();
	$nm_id     = get_the_ID();
	$nm_kind   = function_exists( 'ner_michoel_alumni_kind' ) ? ner_michoel_alumni_kind( $nm_id ) : 'a';
	$nm_series = get_the_terms( $nm_id, 'series' );
	$nm_series = ( $nm_series && ! is_wp_error( $nm_series ) ) ? ( function_exists( 'ner_michoel_series_label' ) ? ner_michoel_series_label( $nm_series[0] ) : $nm_series[0]->name ) : '';
	$nm_vimeo  = function_exists( 'ner_michoel_get_shiur_vimeo_id' ) ? ner_michoel_get_shiur_vimeo_id( $nm_id ) : '';
	$nm_audio  = function_exists( 'ner_michoel_get_shiur_audio_url' ) ? ner_michoel_get_shiur_audio_url( $nm_id ) : '';
	$nm_video  = function_exists( 'ner_michoel_get_shiur_video_url' ) ? ner_michoel_get_shiur_video_url( $nm_id ) : '';
	$nm_pdf_id = (int) get_post_meta( $nm_id, '_written_pdf_id', true );
	$nm_pdf    = $nm_pdf_id ? wp_get_attachment_url( $nm_pdf_id ) : '';
	?>
	<div class="shiurim-app nm-alumni nm-alumni--single">
		<p class="nm-alumni__back"><a href="<?php echo esc_url( function_exists( 'ner_michoel_alumni_url' ) ? ner_michoel_alumni_url() : home_url( '/alumni/' ) ); ?>">&larr; <?php esc_html_e( 'Alumni', 'ner-michoel-child' ); ?></a></p>
		<header class="sh-page-header">
			<h1 dir="auto"><?php the_title(); ?></h1>
			<p class="nm-alumni__meta">
				<?php echo esc_html( get_the_date( 'j M Y' ) ); ?>
				<?php if ( '' !== $nm_series ) : ?> · <span dir="auto"><?php echo esc_html( $nm_series ); ?></span><?php endif; ?>
			</p>
		</header>

		<div class="nm-alumni__player">
			<?php if ( $nm_vimeo ) : ?>
				<div class="nm-alumni__video"><iframe src="<?php echo esc_url( 'https://player.vimeo.com/video/' . rawurlencode( $nm_vimeo ) ); ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="<?php echo esc_attr( get_the_title() ); ?>"></iframe></div>
			<?php elseif ( $nm_video ) : ?>
				<video class="nm-alumni__video-file" src="<?php echo esc_url( $nm_video ); ?>" controls preload="metadata"></video>
			<?php elseif ( $nm_audio ) : ?>
				<audio class="nm-alumni__audio" src="<?php echo esc_url( $nm_audio ); ?>" controls preload="metadata"></audio>
			<?php elseif ( $nm_pdf ) : ?>
				<p><a class="nm-contact-form__submit nm-alumni__button" href="<?php echo esc_url( $nm_pdf ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the PDF', 'ner-michoel-child' ); ?></a></p>
			<?php else : ?>
				<p><?php esc_html_e( 'This shiur has no file yet.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( '' !== trim( get_the_content() ) ) : ?>
			<div class="nm-alumni__content"><?php the_content(); ?></div>
		<?php endif; ?>
	</div>
	<?php
endwhile;

get_footer();
