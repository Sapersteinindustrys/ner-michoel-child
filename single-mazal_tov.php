<?php
/**
 * One Mazal Tov on its own page. Reads "Name (years)" then what it is, with no date
 * and no author, which is what the general post page would have printed.
 */

get_header();

$layout = ner_michoel_get_layout();
?>

<div class="nm-app<?php echo 'classic' === $layout ? ' nm-app--classic' : ''; ?>">
	<header class="sh-page-header">
		<h1><?php esc_html_e( 'Mazal Tov', 'ner-michoel-child' ); ?></h1>
	</header>

	<section class="sh-section">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<div class="sh-mazal-tov-list">
				<?php get_template_part( 'template-parts/mazal-tov-item', null, array( 'post' => get_post() ) ); ?>
			</div>
			<?php
		endwhile;
		$archive = post_type_exists( 'mazal_tov' ) ? get_post_type_archive_link( 'mazal_tov' ) : '';
		?>
		<?php if ( $archive ) : ?>
			<p class="sh-mazal-tov-back"><a href="<?php echo esc_url( $archive ); ?>"><?php esc_html_e( 'All Mazal Tovs', 'ner-michoel-child' ); ?></a></p>
		<?php endif; ?>
	</section>
</div>

<?php get_footer(); ?>
