<?php
/**
 * Template Name: Contact (Ner Michoel)
 *
 * Two office blocks (real address/phone/fax from the original site)
 * plus a contact form. The original site's email addresses are
 * Cloudflare-obfuscated in its markup — the real addresses weren't
 * recoverable, so they're intentionally left out here rather than
 * faked; the form is the primary contact path.
 *
 * Submission handling: ner-michoel-core/includes/forms.php. The form
 * posts to admin-post.php?action=nm_contact_submit with a
 * 'nm_contact_submit'-action nonce in 'nm_contact_nonce', and a
 * honeypot field ('nm_contact_hp') that must arrive empty — real
 * visitors never see or fill it (off-screen + aria-hidden), so any
 * value in it means a bot filled every field it found.
 *
 * The handler is expected to redirect back to wp_get_referer() (the
 * nonce field's default referer input covers this) with
 * ?nm_contact=sent on success or ?nm_contact=error on failure, which
 * this template turns into the notice below.
 */

get_header();

$notice = isset( $_GET['nm_contact'] ) ? sanitize_key( wp_unslash( $_GET['nm_contact'] ) ) : '';
?>

<div class="nm-page nm-page--contact">
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

		<div class="nm-office-grid">
			<div class="nm-office-card">
				<h2><?php esc_html_e( 'American Office', 'ner-michoel-child' ); ?></h2>
				<p>1412 East 7th Street<br />Brooklyn, NY 11230</p>
				<p>
					<?php esc_html_e( 'Phone:', 'ner-michoel-child' ); ?> <a href="tel:+17183361770">718-336-1770</a><br />
					<?php esc_html_e( 'Fax:', 'ner-michoel-child' ); ?> 718-336-1799
				</p>
			</div>
			<div class="nm-office-card">
				<h2><?php esc_html_e( 'Israel Office', 'ner-michoel-child' ); ?></h2>
				<p>Rechov Ma'aglei HaRim Levine 20<br />Sanhedria Murchevet, Jerusalem 97707</p>
				<p>PO Box 5322, Jerusalem, Israel 9105202</p>
				<p>
					<?php esc_html_e( 'Phone:', 'ner-michoel-child' ); ?> <a href="tel:+97225826541">02-582-6541</a><br />
					<?php esc_html_e( 'US calling number:', 'ner-michoel-child' ); ?> <a href="tel:+19293231331">929-323-1331</a>
				</p>
			</div>
		</div>

		<div class="nm-contact-form-wrap">
			<h2><?php esc_html_e( 'Send a Message', 'ner-michoel-child' ); ?></h2>

			<?php if ( 'sent' === $notice ) : ?>
				<p class="nm-form-notice nm-form-notice--success"><?php esc_html_e( 'Thanks — your message has been sent. We\'ll be in touch soon.', 'ner-michoel-child' ); ?></p>
			<?php elseif ( 'error' === $notice ) : ?>
				<p class="nm-form-notice nm-form-notice--error"><?php esc_html_e( 'Something went wrong sending your message — please try again, or reach us directly using the details above.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>

			<form class="nm-contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="nm_contact_submit" />
				<?php wp_nonce_field( 'nm_contact_submit', 'nm_contact_nonce' ); ?>

				<div class="nm-hp-field" aria-hidden="true">
					<label for="nm_contact_hp"><?php esc_html_e( 'Leave this field blank', 'ner-michoel-child' ); ?></label>
					<input type="text" id="nm_contact_hp" name="nm_contact_hp" tabindex="-1" autocomplete="off" value="" />
				</div>

				<label class="nm-contact-form__field">
					<?php esc_html_e( 'My Name', 'ner-michoel-child' ); ?>
					<input type="text" name="nm_name" required />
				</label>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'My Email', 'ner-michoel-child' ); ?>
					<input type="email" name="nm_email" required />
				</label>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'My Phone Number', 'ner-michoel-child' ); ?>
					<input type="tel" name="nm_phone" />
				</label>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Message', 'ner-michoel-child' ); ?>
					<textarea name="nm_message" rows="5" required></textarea>
				</label>
				<button type="submit" class="nm-contact-form__submit"><?php esc_html_e( 'Send', 'ner-michoel-child' ); ?></button>
			</form>
		</div>
		<?php
	endwhile;
	?>
</div>

<?php get_footer(); ?>
