<?php
/**
 * The homepage banner under the welcome (it was the Zoom box), and the box asking alumni to
 * log in. Both are set in the Control Panel under Announcements > Homepage Banner
 * (ner-michoel-core includes/home-banner.php); assets/css/home-extras.css lays them out.
 *
 * With an older plugin that has no banner yet, the old Zoom box shows as before.
 * The alumni box is for visitors who are not signed in. Logged-in pages are not cached,
 * and a cached page is always a signed-out one, so the box is printed here, not by script.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ner_michoel_home_banner_public' ) ) {
	get_template_part( 'template-parts/live-shiur' );
	return;
}

$nm_banner_parts = ner_michoel_home_banner_public();
$nm_banner       = $nm_banner_parts['banner'];
$nm_alumni       = is_user_logged_in() ? null : $nm_banner_parts['alumni'];

if ( ! $nm_banner && ! $nm_alumni ) {
	return;
}
?>
<div class="hn-notices<?php echo ( $nm_banner && $nm_alumni ) ? ' hn-notices--both' : ''; ?>">
	<?php if ( $nm_banner ) : ?>
		<?php
		$nm_style = 'hn-banner--' . $nm_banner['style'];
		$nm_bg    = $nm_banner['background'] ? ' style="--hn-banner-bg: url(\'' . esc_url( $nm_banner['background'] ) . '\')"' : '';
		$nm_lines = array_filter( array_map( 'trim', explode( "\n", $nm_banner['text'] ) ), 'strlen' );
		?>
		<section class="hn-banner <?php echo esc_attr( $nm_style ); ?>"<?php echo $nm_bg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> aria-labelledby="hn-banner-title">
			<?php if ( $nm_banner['logo'] ) : ?>
				<span class="hn-banner__logo"><img src="<?php echo esc_url( $nm_banner['logo'] ); ?>" alt="" loading="lazy" decoding="async" /></span>
			<?php endif; ?>
			<div class="hn-banner__body">
				<?php if ( '' !== $nm_banner['kicker'] ) : ?>
					<p class="hn-banner__kicker"><?php echo esc_html( $nm_banner['kicker'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $nm_banner['title'] ) : ?>
					<h2 class="hn-banner__title" id="hn-banner-title"><?php echo esc_html( $nm_banner['title'] ); ?></h2>
				<?php endif; ?>
				<?php foreach ( $nm_lines as $nm_line ) : ?>
					<p class="hn-banner__text"><?php echo esc_html( $nm_line ); ?></p>
				<?php endforeach; ?>
			</div>
			<?php if ( '' !== $nm_banner['button_label'] && '' !== $nm_banner['button_url'] ) : ?>
				<?php $nm_external = 0 !== strpos( $nm_banner['button_url'], home_url() ); ?>
				<a class="hn-banner__button" href="<?php echo esc_url( $nm_banner['button_url'] ); ?>"<?php echo $nm_external ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $nm_banner['button_label'] ); ?> <?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( $nm_alumni ) : ?>
		<section class="hn-alumni-invite" aria-labelledby="hn-alumni-title">
			<span class="hn-alumni-invite__icon" aria-hidden="true"><?php echo ner_michoel_line_icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
			<div class="hn-alumni-invite__body">
				<h2 class="hn-alumni-invite__title" id="hn-alumni-title"><?php echo esc_html( $nm_alumni['title'] ); ?></h2>
				<p class="hn-alumni-invite__text"><?php echo esc_html( $nm_alumni['text'] ); ?></p>
			</div>
			<a class="hn-alumni-invite__button" href="<?php echo esc_url( $nm_alumni['url'] ); ?>"><?php echo esc_html( $nm_alumni['button'] ); ?></a>
		</section>
	<?php endif; ?>
</div>
