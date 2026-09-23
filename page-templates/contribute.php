<?php
/**
 * Template Name: Contribute (Ner Michoel)
 *
 * Donation methods the original site lists: PayPal, phone-based
 * recurring giving (US office), Israeli bank transfer + Nedarim Plus
 * for Israeli tax receipts (real Nedarim Plus link recovered from the
 * live site). The PayPal button itself is account-specific embed
 * code we don't have — that placeholder is intentional, ready for
 * whoever has the actual PayPal business account to drop it in.
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
			<h2><?php esc_html_e( 'Donate Online', 'ner-michoel-child' ); ?></h2>
			<p><?php esc_html_e( 'Donate via PayPal.', 'ner-michoel-child' ); ?></p>
			<!-- TODO: insert the actual PayPal donate button/embed code here once available. -->
		</div>

		<div class="nm-info-card">
			<h2><?php esc_html_e( 'Donate by Phone', 'ner-michoel-child' ); ?></h2>
			<p>
				<?php esc_html_e( 'To arrange a recurring donation or ask a question, contact the American or Israeli office.', 'ner-michoel-child' ); ?>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'ner-michoel-child' ); ?></a>
			</p>
		</div>

		<div class="nm-info-card">
			<h2><?php esc_html_e( 'Donate in Israel', 'ner-michoel-child' ); ?></h2>
			<p><strong><?php esc_html_e( 'Bank Transfer:', 'ner-michoel-child' ); ?></strong> <?php esc_html_e( 'Yeshivas Toras Moshe, Bank 20, Branch 401, Account 584227.', 'ner-michoel-child' ); ?></p>
			<p><strong><?php esc_html_e( 'Credit Card via Nedarim Plus:', 'ner-michoel-child' ); ?></strong> <a href="https://www.matara.pro/nedarimplus/online/?mosad=7007881" target="_blank" rel="noopener"><?php esc_html_e( 'Donate via Nedarim Plus', 'ner-michoel-child' ); ?></a></p>
			<p><?php esc_html_e( 'Both methods provide Israeli tax receipts.', 'ner-michoel-child' ); ?></p>
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
