<?php
/**
 * Template Name: Account (Ner Michoel)
 *
 * One URL for the whole feature rather than separate Login/Sign Up/
 * Account pages: logged out, it shows Log In / Sign Up tabs (plus a
 * Forgot Password panel, reached from a link on the Log In tab);
 * logged in, the same URL shows Profile / History / Saved / Suggested
 * tabs instead. Which to render is decided here, server-side, from
 * is_user_logged_in() — the only state account.js keeps is which tab
 * is showing (same generic tab/panel script either way: both sets of
 * tabs use the same .nm-account-tab/.nm-account-panel classes).
 *
 * Backend: ner-michoel-core/includes/accounts.php (REST routes,
 * ordinary WP `subscriber` users — no parallel auth system) and
 * includes/user-library.php (History/Saved/Suggested — two small
 * per-user tables, not post/user meta; see that file for why). Either
 * not being active yet (plugin not updated, or updated to a version
 * before user-library.php shipped) degrades gracefully — an empty
 * "not available yet" page, or a Profile tab with no History/Saved/
 * Suggested tabs — rather than a fatal error, same guard pattern as
 * ner_michoel_live_shiur_is_set() elsewhere in this theme.
 */

get_header();

$account = function_exists( 'ner_michoel_get_current_account' ) ? ner_michoel_get_current_account() : null;
?>

<div class="nm-page nm-page--account">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<header class="nm-page__header">
			<h1><?php the_title(); ?></h1>
		</header>

		<?php if ( ! function_exists( 'ner_michoel_get_current_account' ) ) : ?>

			<p class="nm-empty"><?php esc_html_e( 'Accounts aren’t available yet — check back soon.', 'ner-michoel-child' ); ?></p>

		<?php elseif ( $account ) : ?>

			<?php
			// ---- Logged in: Profile / History / Saved / Suggested ----
			$user_id = get_current_user_id();

			$has_library = function_exists( 'ner_michoel_get_user_history' );
			$history     = $has_library ? ner_michoel_get_user_history( $user_id, 24 ) : array();
			$saved       = $has_library ? ner_michoel_get_user_saved( $user_id ) : array();
			$suggested   = $has_library ? ner_michoel_get_suggested_for_user( $user_id, 12 ) : array();
			?>

			<div class="nm-account-tabs" role="tablist">
				<button type="button" class="nm-account-tab is-active" data-tab="profile" role="tab"><?php esc_html_e( 'Profile', 'ner-michoel-child' ); ?></button>
				<?php if ( $has_library ) : ?>
					<button type="button" class="nm-account-tab" data-tab="history" role="tab"><?php esc_html_e( 'History', 'ner-michoel-child' ); ?></button>
					<button type="button" class="nm-account-tab" data-tab="saved" role="tab"><?php esc_html_e( 'Liked', 'ner-michoel-child' ); ?></button>
					<button type="button" class="nm-account-tab" data-tab="suggested" role="tab"><?php esc_html_e( 'Suggested', 'ner-michoel-child' ); ?></button>
				<?php endif; ?>
			</div>

			<p id="nm-account-notice" class="nm-form-notice" role="status" aria-live="polite" hidden></p>

			<div class="nm-account-panel" data-panel="profile">
				<div class="nm-account-avatar">
					<img id="nm-account-avatar-preview" class="nm-account-avatar__img" src="<?php echo esc_url( $account['avatarUrl'] ); ?>" width="96" height="96" alt="" />
					<label class="nm-account-avatar__upload">
						<?php esc_html_e( 'Change Photo', 'ner-michoel-child' ); ?>
						<input type="file" id="nm-account-avatar-input" accept="image/jpeg,image/png,image/gif,image/webp" hidden />
					</label>
				</div>

				<form id="nm-account-profile-form" method="post" class="nm-contact-form">
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'Name', 'ner-michoel-child' ); ?>
						<input type="text" name="name" value="<?php echo esc_attr( $account['name'] ); ?>" required />
					</label>
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'Email', 'ner-michoel-child' ); ?>
						<input type="email" value="<?php echo esc_attr( $account['email'] ); ?>" disabled />
					</label>
					<button type="submit" class="nm-contact-form__submit"><?php esc_html_e( 'Save Changes', 'ner-michoel-child' ); ?></button>
				</form>

				<h2 class="nm-account-subhead"><?php esc_html_e( 'Change Password', 'ner-michoel-child' ); ?></h2>
				<form id="nm-account-password-form" method="post" class="nm-contact-form">
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'Current Password', 'ner-michoel-child' ); ?>
						<input type="password" name="current_password" autocomplete="current-password" required />
					</label>
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'New Password', 'ner-michoel-child' ); ?>
						<input type="password" name="new_password" autocomplete="new-password" minlength="8" required />
					</label>
					<label class="nm-contact-form__field">
						<?php esc_html_e( 'Confirm New Password', 'ner-michoel-child' ); ?>
						<input type="password" name="new_password_confirm" autocomplete="new-password" minlength="8" required />
					</label>
					<button type="submit" class="nm-contact-form__submit"><?php esc_html_e( 'Update Password', 'ner-michoel-child' ); ?></button>
				</form>

				<?php
				// Computers that skip the emailed log in code (ner-michoel-core's login-codes.php; an older plugin has none).
				if ( function_exists( 'ner_michoel_trusted_count' ) ) :
					$remembered = ner_michoel_trusted_count( $user_id );
					?>
					<h2 class="nm-account-subhead"><?php esc_html_e( 'Remembered Computers', 'ner-michoel-child' ); ?></h2>
					<div class="nm-account-computers" id="nm-account-computers">
						<p class="nm-account-panel__intro" data-computers-text
							data-text-none="<?php echo esc_attr__( 'No computer is remembered. Every log in asks for a code from your email.', 'ner-michoel-child' ); ?>">
							<?php
							if ( $remembered > 0 ) {
								echo esc_html(
									sprintf(
										/* translators: %d: how many computers */
										_n( '%d computer is remembered. It logs in without asking for a code.', '%d computers are remembered. They log in without asking for a code.', $remembered, 'ner-michoel-child' ),
										$remembered
									)
								);
							} else {
								esc_html_e( 'No computer is remembered. Every log in asks for a code from your email.', 'ner-michoel-child' );
							}
							?>
						</p>
						<button type="button" class="nm-contact-form__submit nm-account-computers__forget" id="nm-account-forget-computers"<?php echo $remembered > 0 ? '' : ' hidden'; ?>><?php esc_html_e( 'Forget Remembered Computers', 'ner-michoel-child' ); ?></button>
					</div>
				<?php endif; ?>

				<p class="nm-account-logout">
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/account/' ) ) ); ?>"><?php esc_html_e( 'Log Out', 'ner-michoel-child' ); ?></a>
				</p>
			</div>

			<?php if ( $has_library ) : ?>

				<div class="nm-account-panel" data-panel="history" hidden>
					<?php
					// Series picked up where they were left off, above the plain history.
					$continue = ( $has_library && function_exists( 'ner_michoel_continue_series_for_user' ) ) ? ner_michoel_continue_series_for_user( $user_id ) : array();
					if ( $continue ) :
						?>
						<h3 class="nm-account-subhead"><?php esc_html_e( 'Continue a series', 'ner-michoel-child' ); ?></h3>
						<div class="nm-continue-grid">
							<?php foreach ( $continue as $item ) : ?>
								<?php ner_michoel_render_continue_card( $item['term'], $item['shiur'] ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $history ) : ?>
						<div class="nm-home-shiur-grid">
							<?php foreach ( $history as $row ) : ?>
								<?php ner_michoel_render_library_card( $row->post_id ); ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="nm-empty"><?php esc_html_e( 'Nothing listened to or read yet — your history will show up here.', 'ner-michoel-child' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="nm-account-panel" data-panel="saved" hidden data-saved-list>
					<?php if ( $saved ) : ?>
						<div class="nm-home-shiur-grid">
							<?php foreach ( $saved as $row ) : ?>
								<?php ner_michoel_render_library_card( $row->post_id ); ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="nm-empty"><?php esc_html_e( 'Nothing liked yet — tap the heart on a shiur or written shiur to add it here.', 'ner-michoel-child' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="nm-account-panel" data-panel="suggested" hidden>
					<?php if ( $suggested ) : ?>
						<div class="nm-home-shiur-grid">
							<?php foreach ( $suggested as $suggested_post ) : ?>
								<?php ner_michoel_render_library_card( $suggested_post->ID ); ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="nm-empty"><?php esc_html_e( 'Nothing to suggest yet.', 'ner-michoel-child' ); ?></p>
					<?php endif; ?>
				</div>

			<?php endif; ?>

		<?php else : ?>

			<?php // ---- Logged out: Log In / Sign Up / Forgot Password ---- ?>

			<div class="nm-account-tabs" role="tablist">
				<button type="button" class="nm-account-tab is-active" data-tab="login" role="tab"><?php esc_html_e( 'Log In', 'ner-michoel-child' ); ?></button>
				<button type="button" class="nm-account-tab" data-tab="signup" role="tab"><?php esc_html_e( 'Sign Up', 'ner-michoel-child' ); ?></button>
			</div>

			<p id="nm-account-notice" class="nm-form-notice" role="status" aria-live="polite" hidden></p>

			<form id="nm-account-login-form" method="post" class="nm-contact-form nm-account-panel" data-panel="login">
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Email', 'ner-michoel-child' ); ?>
					<input type="email" name="email" autocomplete="username" required />
				</label>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Password', 'ner-michoel-child' ); ?>
					<input type="password" name="password" autocomplete="current-password" required />
				</label>
				<?php
				// ner-michoel-core's bot check (includes/form-guard.php); account.js sends its fields.
				if ( function_exists( 'ner_michoel_form_guard_fields' ) ) {
					ner_michoel_form_guard_fields( 'login' );
				}
				?>
				<button type="submit" class="nm-contact-form__submit"><?php esc_html_e( 'Log In', 'ner-michoel-child' ); ?></button>
				<a href="#" class="nm-account-link" data-show-panel="forgot"><?php esc_html_e( 'Forgot password?', 'ner-michoel-child' ); ?></a>
			</form>

			<form id="nm-account-signup-form" method="post" class="nm-contact-form nm-account-panel" data-panel="signup" hidden>
				<div class="nm-hp-field" aria-hidden="true">
					<label for="nm-account-website"><?php esc_html_e( 'Leave this field blank', 'ner-michoel-child' ); ?></label>
					<input type="text" id="nm-account-website" name="website" tabindex="-1" autocomplete="off" value="" />
				</div>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Name', 'ner-michoel-child' ); ?>
					<input type="text" name="name" required />
				</label>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Email (this is your username)', 'ner-michoel-child' ); ?>
					<input type="email" name="email" autocomplete="username" required />
					<span class="nm-contact-form__hint"><?php esc_html_e( 'We’ll email you a 6-digit code to confirm it.', 'ner-michoel-child' ); ?></span>
				</label>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Password', 'ner-michoel-child' ); ?>
					<input type="password" name="password" autocomplete="new-password" minlength="8" required />
				</label>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Confirm Password', 'ner-michoel-child' ); ?>
					<input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required />
				</label>
				<?php // Two separate choices, both optional. Stored as user meta by accounts.php. ?>
				<label class="nm-contact-form__check">
					<input type="checkbox" name="pref_updates" value="1" />
					<?php /* translators: %s: the site's name */ ?>
					<span><?php echo esc_html( sprintf( __( 'Send me %s updates', 'ner-michoel-child' ), ner_michoel_site_name() ) ); ?></span>
				</label>
				<label class="nm-contact-form__check">
					<input type="checkbox" name="pref_new_shiur_alerts" value="1" />
					<span><?php esc_html_e( 'Alert me when new shiurim are posted', 'ner-michoel-child' ); ?></span>
				</label>
				<?php
				if ( function_exists( 'ner_michoel_form_guard_fields' ) ) {
					ner_michoel_form_guard_fields( 'signup' );
				}
				?>
				<button type="submit" class="nm-contact-form__submit"><?php esc_html_e( 'Create Account', 'ner-michoel-child' ); ?></button>
			</form>

			<?php // The code step: shown by account.js after sign-up, when a not-yet-confirmed account logs in, and at every log in from a computer that isn't remembered. ?>
			<form id="nm-account-verify-form" method="post" class="nm-contact-form nm-account-panel nm-verify" data-panel="verify" hidden novalidate>
				<h2 class="nm-verify__title"><?php esc_html_e( 'Check your email', 'ner-michoel-child' ); ?></h2>
				<p class="nm-account-panel__intro" data-verify-mode="signup">
					<?php
					printf(
						/* translators: %s: the person's email address */
						esc_html__( 'We sent a 6-digit code to %s. Enter it here to activate your account.', 'ner-michoel-child' ),
						'<strong data-verify-email></strong>'
					);
					?>
				</p>
				<p class="nm-account-panel__intro" data-verify-mode="login" hidden>
					<?php
					printf(
						/* translators: %s: the person's email address, partly hidden */
						esc_html__( 'We sent a 6-digit code to %s to make sure it’s you. Enter it here to log in.', 'ner-michoel-child' ),
						'<strong data-verify-email></strong>'
					);
					?>
				</p>
				<label class="nm-contact-form__field">
					<?php esc_html_e( '6-digit code', 'ner-michoel-child' ); ?>
					<input type="text" name="code" class="nm-verify__code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="12" placeholder="123456" required />
				</label>
				<p class="nm-verify__hint" data-verify-mode="signup">
						<?php
						// Who the email says it is from (ner-michoel-core's ner_michoel_email_sender_name(); an older plugin doesn't have it).
						$verify_sender = function_exists( 'ner_michoel_email_sender_name' ) ? ner_michoel_email_sender_name() : '';
						if ( $verify_sender ) {
							printf(
								/* translators: %s: who the email is from (Yeshivas Toras Moshe) */
								esc_html__( 'Look for an email from %s. Can’t find it? Check your spam folder. The code works for 30 minutes.', 'ner-michoel-child' ),
								'<strong>' . esc_html( $verify_sender ) . '</strong>'
							);
						} else {
							esc_html_e( 'Can’t find it? Check your spam folder. The code works for 30 minutes.', 'ner-michoel-child' );
						}
						?>
					</p>
				<p class="nm-verify__hint" data-verify-mode="login" hidden>
					<?php
					if ( $verify_sender ) {
						printf(
							/* translators: %s: who the email is from (Yeshivas Toras Moshe) */
							esc_html__( 'Look for an email from %s. Can’t find it? Check your spam folder. The code works for 10 minutes.', 'ner-michoel-child' ),
							'<strong>' . esc_html( $verify_sender ) . '</strong>'
						);
					} else {
						esc_html_e( 'Can’t find it? Check your spam folder. The code works for 10 minutes.', 'ner-michoel-child' );
					}
					?>
				</p>
				<?php // Log in only: remember this computer so the next log in here skips the code. account.js fills in the number of days. ?>
				<label class="nm-contact-form__check nm-verify__remember" data-verify-mode="login" hidden>
					<input type="checkbox" name="remember" value="1" checked />
					<span data-verify-remember
						data-text-one="<?php echo esc_attr__( 'Remember this computer for %d day, so I don’t need a code here next time', 'ner-michoel-child' ); ?>"
						data-text-many="<?php echo esc_attr__( 'Remember this computer for %d days, so I don’t need a code here next time', 'ner-michoel-child' ); ?>"><?php esc_html_e( 'Remember this computer, so I don’t need a code here next time', 'ner-michoel-child' ); ?></span>
				</label>
				<button type="submit" class="nm-contact-form__submit"
					data-signup-text="<?php echo esc_attr__( 'Verify & Continue', 'ner-michoel-child' ); ?>"
					data-login-text="<?php echo esc_attr__( 'Log In', 'ner-michoel-child' ); ?>"><?php esc_html_e( 'Verify & Continue', 'ner-michoel-child' ); ?></button>
				<div class="nm-verify__links">
					<button type="button" class="nm-verify__link" data-verify-resend><?php esc_html_e( 'Resend code', 'ner-michoel-child' ); ?></button>
					<span aria-hidden="true">·</span>
					<a href="#" class="nm-account-link" data-verify-restart
						data-signup-text="<?php echo esc_attr__( 'Wrong email? Start over', 'ner-michoel-child' ); ?>"
						data-login-text="<?php echo esc_attr__( 'Back to Log In', 'ner-michoel-child' ); ?>"><?php esc_html_e( 'Wrong email? Start over', 'ner-michoel-child' ); ?></a>
				</div>
				<?php // Only shown when every code for this sign-up has been sent: a reset link confirms the email too. ?>
				<p class="nm-verify__hint" data-verify-forgot-wrap hidden>
					<a href="#" class="nm-account-link" data-verify-forgot data-show-panel="forgot"><?php esc_html_e( 'Use “Forgot password?” instead', 'ner-michoel-child' ); ?></a>
				</p>
			</form>

			<form id="nm-account-forgot-form" method="post" class="nm-contact-form nm-account-panel" data-panel="forgot" hidden>
				<p class="nm-account-panel__intro"><?php esc_html_e( 'Enter your email and we’ll send a link to reset your password.', 'ner-michoel-child' ); ?></p>
				<label class="nm-contact-form__field">
					<?php esc_html_e( 'Email', 'ner-michoel-child' ); ?>
					<input type="email" name="email" required />
				</label>
				<?php
				if ( function_exists( 'ner_michoel_form_guard_fields' ) ) {
					ner_michoel_form_guard_fields( 'forgot' );
				}
				?>
				<button type="submit" class="nm-contact-form__submit"><?php esc_html_e( 'Send Reset Link', 'ner-michoel-child' ); ?></button>
				<a href="#" class="nm-account-link" data-show-panel="login"><?php esc_html_e( 'Back to Log In', 'ner-michoel-child' ); ?></a>
			</form>

		<?php endif; ?>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
