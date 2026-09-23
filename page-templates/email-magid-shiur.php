<?php
/**
 * Template Name: Email a Magid Shiur (Ner Michoel)
 *
 * A real section from the original site's nav (/contact/email-a-
 * magid-shiur) that hadn't been rebuilt yet — a way to reach a
 * specific rebbi rather than the general Contact form.
 *
 * Needs a backend piece that doesn't exist yet: a way to know which
 * email address a "speaker" term should forward to. Written
 * defensively (function_exists check on ner_michoel_get_speaker_email)
 * so the page still renders and the form still submits in the
 * meantime — it just can't target a specific rebbi's inbox until
 * that accessor exists. See dev-notes.md for the exact spec handed
 * to the backend session.
 *
 * Submission handling: ner-michoel-core/includes/forms.php, same
 * pattern as the Contact form — posts to
 * admin-post.php?action=nm_email_magid_submit with a
 * 'nm_email_magid_submit'-action nonce in 'nm_email_magid_nonce', a
 * honeypot ('nm_magid_hp'), and expects a redirect back with
 * ?nm_magid=sent / ?nm_magid=error.
 */

get_header();

$speakers = get_terms( array( 'taxonomy' => 'speaker', 'hide_empty' => false ) );
$has_speakers = ! is_wp_error( $speakers ) && $speakers;
$notice   = isset( $_GET['nm_magid'] ) ? sanitize_key( wp_unslash( $_GET['nm_magid'] ) ) : '';

// Optional — lets the sender point to a specific shiur (audio or
// video, no media-type filter) rather than just a general question.
$all_shiurim = get_posts(
	array(
		'post_type'      => 'shiur',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>

<div class="nm-page nm-page--email-magid">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="nm-page__header">
			<h1><?php the_title(); ?></h1>
			<p class="nm-page__intro"><?php esc_html_e( 'Send a question or message directly to one of the Magidei Shiur.', 'ner-michoel-child' ); ?></p>
		</header>

		<div class="nm-page__content entry-content">
			<?php the_content(); ?>
		</div>

		<?php if ( ! $has_speakers ) : ?>
			<p class="sh-empty"><?php esc_html_e( 'No speakers set up yet.', 'ner-michoel-child' ); ?></p>
		<?php else : ?>

			<?php if ( 'sent' === $notice ) : ?>
				<p class="nm-form-notice nm-form-notice--success"><?php esc_html_e( 'Thanks — your message has been sent.', 'ner-michoel-child' ); ?></p>
			<?php elseif ( 'error' === $notice ) : ?>
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
		<?php
	endwhile;
	?>
</div>

<?php get_footer(); ?>
