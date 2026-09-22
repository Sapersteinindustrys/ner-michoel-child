<?php
/**
 * Template Name: Contribute (Ner Michoel)
 *
 * Donation methods the original site lists: PayPal, phone-based
 * recurring giving (US office), Israeli bank transfer + Nedarim Plus
 * for Israeli tax receipts. The actual PayPal/Nedarim Plus embed
 * code and account links are real account-specific values we don't
 * have — that content goes in the page editor (the_content) rather
 * than being hardcoded here; this template just supplies structure
 * and the (real, audited) bank transfer details as fixed reference
 * info, same as a footer address.
 */

get_header();
?>

<div class="nm-page nm-page--contribute">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="nm-page__header">
			<h1><?php the_title(); ?></h1>
		</header>

		<div class="nm-page__content entry-content">
			<?php the_content(); ?>
		</div>

		<div class="nm-info-card">
			<h2><?php esc_html_e( 'Israeli Bank Transfer', 'ner-michoel-child' ); ?></h2>
			<p><?php esc_html_e( 'Yeshivas Toras Moshe, Bank 20, Branch 401, Account 584227.', 'ner-michoel-child' ); ?></p>
			<p><?php esc_html_e( 'Israeli tax receipts are also available via Nedarim Plus — contact the Israeli office for a link.', 'ner-michoel-child' ); ?></p>
		</div>

		<p class="nm-security-note">
			<?php esc_html_e( 'Please do not email your credit card information — it is not secure. For help setting up a donation, contact the American or Israeli office.', 'ner-michoel-child' ); ?>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'ner-michoel-child' ); ?></a>
		</p>
		<?php
	endwhile;
	?>
</div>

<?php get_footer(); ?>
