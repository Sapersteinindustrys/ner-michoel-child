<?php
/**
 * Template Name: Contact (Ner Michoel)
 *
 * Two office blocks (real address/phone/fax/email from the original
 * site, the emails decoded from the live site's Cloudflare-obfuscated
 * markup) plus a contact form.
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

// "Email a Magid Shiur" — moved here from its own page. Same handler
// (forms.php, nm_email_magid_submit), so its redirect and notices are unchanged.
$magid_notice = isset( $_GET['nm_magid'] ) ? sanitize_key( wp_unslash( $_GET['nm_magid'] ) ) : '';
$speakers     = get_terms( array( 'taxonomy' => 'speaker', 'hide_empty' => false ) );
$has_speakers = ! is_wp_error( $speakers ) && $speakers;
$all_shiurim  = get_posts(
	array(
		'post_type'      => 'shiur',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>

<div class="nm-page nm-page--contact">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<div class="nm-office-grid">
			<div class="nm-office-card">
				<h2><?php esc_html_e( 'American Office', 'ner-michoel-child' ); ?></h2>
				<p><?php esc_html_e( 'American Friends of Yeshivas Toras Moshe', 'ner-michoel-child' ); ?></p>
				<p>1412 East 7th Street<br />Brooklyn, NY 11230</p>
				<p>
					<?php esc_html_e( 'Phone:', 'ner-michoel-child' ); ?> <a href="tel:+17183361770">718-336-1770</a><br />
					<?php esc_html_e( 'Fax:', 'ner-michoel-child' ); ?> 718-336-1799
				</p>
				<p>
					<?php esc_html_e( 'Toras Moshe:', 'ner-michoel-child' ); ?> <a href="mailto:americanfriends@torasmoshe.org">americanfriends@torasmoshe.org</a><br />
					<?php esc_html_e( 'Ner Michoel:', 'ner-michoel-child' ); ?> <a href="mailto:nermichoel@torasmoshe.org">nermichoel@torasmoshe.org</a>
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
				<p>
					<?php esc_html_e( 'Email:', 'ner-michoel-child' ); ?> <a href="mailto:admin@torasmoshe.org">admin@torasmoshe.org</a>
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
		<div class="nm-contact-form-wrap" id="email-magid-shiur">
			<h2><?php esc_html_e( 'Email a Magid Shiur', 'ner-michoel-child' ); ?></h2>
			<p class="nm-page__intro"><?php esc_html_e( 'Send a question or message directly to one of the Magidei Shiur.', 'ner-michoel-child' ); ?></p>

			<?php if ( ! $has_speakers ) : ?>
				<p class="sh-empty"><?php esc_html_e( 'No speakers set up yet.', 'ner-michoel-child' ); ?></p>
			<?php else : ?>

				<?php if ( 'sent' === $magid_notice ) : ?>
					<p class="nm-form-notice nm-form-notice--success"><?php esc_html_e( 'Thanks — your message has been sent.', 'ner-michoel-child' ); ?></p>
				<?php elseif ( 'error' === $magid_notice ) : ?>
					<p class="nm-form-notice nm-form-notice--error"><?php esc_html_e( 'Something went wrong sending your message — please try again.', 'ner-michoel-child' ); ?></p>
				<?php endif; ?>

				<form class="nm-contact-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="nm_email_magid_submit" />
					<?php wp_nonce_field( 'nm_email_magid_submit', 'nm_email_magid_nonce' ); ?>

					<div class="nm-hp-field" aria-hidden="true">
						<label for="nm_magid_hp"><?php esc_html_e( 'Leave this field blank', 'ner-michoel-child' ); ?></label>
						<input type="text" id="nm_magid_hp" name="nm_magid_hp" tabindex="-1" autocomplete="off" value="" />
					</div>

					<label class="nm-contact-form__field">
						<?php esc_html_e( 'Magid Shiur', 'ner-michoel-child' ); ?>
						<select name="nm_speaker_id" required>
							<option value=""><?php esc_html_e( 'Choose a speaker…', 'ner-michoel-child' ); ?></option>
							<?php foreach ( $speakers as $speaker_term ) : ?>
								<option value="<?php echo esc_attr( $speaker_term->term_id ); ?>"><?php echo esc_html( $speaker_term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'Which shiur is this about? (optional)', 'ner-michoel-child' ); ?>
						<select name="nm_shiur_id">
							<option value=""><?php esc_html_e( '— Not about a specific shiur —', 'ner-michoel-child' ); ?></option>
							<?php foreach ( $all_shiurim as $shiur ) : ?>
								<option value="<?php echo esc_attr( $shiur->ID ); ?>"><?php echo esc_html( $shiur->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'My Name', 'ner-michoel-child' ); ?>
						<input type="text" name="nm_name" required />
					</label>
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'My Email', 'ner-michoel-child' ); ?>
						<input type="email" name="nm_email" required />
					</label>
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'Message', 'ner-michoel-child' ); ?>
						<textarea name="nm_message" rows="5" required></textarea>
					</label>
					<button type="submit" class="nm-contact-form__submit"><?php esc_html_e( 'Send', 'ner-michoel-child' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	endwhile;
	?>
</div>

<?php get_footer(); ?>
