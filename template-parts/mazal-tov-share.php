<?php
/**
 * The "Share a Mazal Tov" form, in a dialog that the buttons marked data-nm-mt-open
 * open (assets/js/mazal-tov-share.js).
 *
 * It posts to ner-michoel-core (includes/mazal-tov-share.php), which keeps what is sent
 * as a Mazal Tov waiting for the admin: nothing shows on the site until it is approved.
 * Nobody types the years; the plugin looks them up from the name. The handler sends the
 * visitor back with ?nm_mazal_tov=sent | error | verify | slow, and the dialog opens by
 * itself to say which. The same bot checks as the Contact form.
 *
 * Print it once on a page, anywhere inside the page's content.
 */

if ( ! ner_michoel_mazal_share_ready() ) {
	return;
}

$notice = isset( $_GET['nm_mazal_tov'] ) ? sanitize_key( wp_unslash( $_GET['nm_mazal_tov'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which message to show.
if ( ! in_array( $notice, array( 'sent', 'error', 'verify', 'slow' ), true ) ) {
	$notice = '';
}
$types = taxonomy_exists( 'mazal_tov_type' ) ? get_terms( array( 'taxonomy' => 'mazal_tov_type', 'hide_empty' => false ) ) : array();
$types = is_wp_error( $types ) ? array() : $types;
?>
<dialog class="nm-mt-share" id="nm-mt-share" aria-labelledby="nm-mt-share-title"<?php echo $notice ? ' data-nm-mt-autoopen' : ''; ?>>
	<div class="nm-mt-share__panel">
		<button type="button" class="nm-mt-share__close" data-nm-mt-close aria-label="<?php esc_attr_e( 'Close', 'ner-michoel-child' ); ?>">&times;</button>
		<h2 class="nm-mt-share__title" id="nm-mt-share-title"><?php esc_html_e( 'Share a Mazal Tov', 'ner-michoel-child' ); ?></h2>

		<?php if ( 'sent' === $notice ) : ?>
			<p class="nm-form-notice nm-form-notice--success nm-mt-share__thanks" role="status"><?php esc_html_e( 'Thank you! We have it. It will be looked over and posted soon.', 'ner-michoel-child' ); ?></p>
			<p class="nm-mt-share__actions"><button type="button" class="nm-mt-share__submit" data-nm-mt-close><?php esc_html_e( 'Close', 'ner-michoel-child' ); ?></button></p>
		<?php else : ?>
			<p class="nm-mt-share__intro"><?php esc_html_e( 'Tell us about a simcha. We look it over before it goes on the site.', 'ner-michoel-child' ); ?></p>

			<?php if ( 'error' === $notice ) : ?>
				<p class="nm-form-notice nm-form-notice--error" role="alert"><?php esc_html_e( 'Something was missing, so it was not sent. Please fill in every box and try again.', 'ner-michoel-child' ); ?></p>
			<?php elseif ( 'verify' === $notice ) : ?>
				<p class="nm-form-notice nm-form-notice--error" role="alert"><?php esc_html_e( 'We couldn’t confirm this came from a person, so it wasn’t sent. Please try again.', 'ner-michoel-child' ); ?></p>
			<?php elseif ( 'slow' === $notice ) : ?>
				<p class="nm-form-notice nm-form-notice--error" role="alert"><?php esc_html_e( 'Several have been sent from your network in the last hour, so this one wasn’t sent. Please try again later.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>

			<form class="nm-mt-share__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-no-pjax>
				<input type="hidden" name="action" value="nm_mazal_tov_share" />
				<?php wp_nonce_field( 'nm_mazal_tov_share', 'nm_mazal_tov_share_nonce' ); ?>

				<div class="nm-hp-field" aria-hidden="true">
					<label for="nm_mt_hp"><?php esc_html_e( 'Leave this field blank', 'ner-michoel-child' ); ?></label>
					<input type="text" id="nm_mt_hp" name="nm_mt_hp" tabindex="-1" autocomplete="off" value="" />
				</div>

				<label class="nm-mt-share__field">
					<span><?php esc_html_e( 'Who is the Mazal Tov for?', 'ner-michoel-child' ); ?></span>
					<input type="text" name="nm_mt_name" maxlength="120" required placeholder="<?php esc_attr_e( 'e.g. Moshe Cohen', 'ner-michoel-child' ); ?>" />
					<small><?php esc_html_e( 'His years in yeshiva are added for you.', 'ner-michoel-child' ); ?></small>
				</label>

				<?php if ( $types ) : ?>
					<label class="nm-mt-share__field">
						<span><?php esc_html_e( 'What kind of simcha?', 'ner-michoel-child' ); ?></span>
						<select name="nm_mt_type">
							<option value=""><?php esc_html_e( 'Choose…', 'ner-michoel-child' ); ?></option>
							<?php foreach ( $types as $term ) : ?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>

				<label class="nm-mt-share__field">
					<span><?php esc_html_e( 'What is the Mazal Tov?', 'ner-michoel-child' ); ?></span>
					<input type="text" name="nm_mt_what" maxlength="200" required placeholder="<?php esc_attr_e( 'e.g. on his engagement to Sarah Levy', 'ner-michoel-child' ); ?>" />
					<small><?php esc_html_e( 'Write it to follow the name, like “on the birth of a baby boy”.', 'ner-michoel-child' ); ?></small>
				</label>

				<fieldset class="nm-mt-share__you">
					<legend><?php esc_html_e( 'Your details (so we can reach you; they are not shown on the site)', 'ner-michoel-child' ); ?></legend>
					<label class="nm-mt-share__field">
						<span><?php esc_html_e( 'Your name', 'ner-michoel-child' ); ?></span>
						<input type="text" name="nm_mt_from_name" maxlength="120" autocomplete="name" required />
					</label>
					<label class="nm-mt-share__field">
						<span><?php esc_html_e( 'Your email', 'ner-michoel-child' ); ?></span>
						<input type="email" name="nm_mt_from_email" maxlength="190" autocomplete="email" required />
					</label>
				</fieldset>

				<?php
				if ( function_exists( 'ner_michoel_form_guard_fields' ) ) {
					ner_michoel_form_guard_fields( 'mazaltov' );
				}
				?>
				<p class="nm-mt-share__actions">
					<button type="submit" class="nm-mt-share__submit"><?php esc_html_e( 'Send for approval', 'ner-michoel-child' ); ?></button>
					<button type="button" class="nm-mt-share__cancel" data-nm-mt-close><?php esc_html_e( 'Cancel', 'ner-michoel-child' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>
</dialog>
