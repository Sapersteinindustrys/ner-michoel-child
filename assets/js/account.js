/**
 * Account page (page-templates/account.php): tab switching — Log In /
 * Sign Up / Forgot Password logged out, or Profile / History / Saved /
 * Suggested logged in, whichever set of .nm-account-tab/.nm-account-panel
 * elements the server actually rendered — and the REST calls behind
 * every form on the page.
 *
 * Server truth, not client state: a successful login or sign-up just
 * reloads the page — functions.php/template-tags.php already decide
 * what to render from is_user_logged_in(), so there's no separate
 * client-side "am I logged in" state to keep in sync with it. Same
 * reasoning is why showPanel() below is generic rather than hardcoding
 * either tab set: it works off whichever .nm-account-tab/.nm-account-panel
 * elements exist in the DOM, however many that turns out to be.
 *
 * The History/Saved/Suggested grids' own Save-button clicks are handled
 * in assets/js/custom.js (shared with the standalone Save button on
 * single-shiur.php/single-written_shiur.php), not here.
 *
 * Re-entrant: nm-router.js (site-wide) can swap #content without a real page
 * load, so init() re-runs on its 'nm:content-swapped' event every time this
 * page is routed to. Every element it binds lives inside #content, so a
 * previous visit's listeners are destroyed along with that old markup — no
 * teardown needed, just re-querying.
 */
( function () {
	'use strict';

	var settings = window.nerMichoelAccount || {};

	function notice( el, message, isError ) {
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.hidden = false;
		el.classList.toggle( 'nm-form-notice--error', !! isError );
		el.classList.toggle( 'nm-form-notice--success', ! isError );
	}

	/**
	 * POSTs to one of accounts.php's routes. `body` is FormData for the
	 * avatar upload (needs multipart), otherwise a plain object sent as
	 * JSON. The X-WP-Nonce header is required by update-profile/avatar
	 * only, but sending it always is harmless — public routes ignore it.
	 */
	function postAccount( path, body ) {
		var isFormData = body instanceof FormData;
		return fetch( settings.root + '/' + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: isFormData
				? { 'X-WP-Nonce': settings.nonce }
				: { 'Content-Type': 'application/json', 'X-WP-Nonce': settings.nonce },
			body: isFormData ? body : JSON.stringify( body )
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				return { ok: res.ok, data: data };
			} );
		} );
	}

	function init() {

	/* ---- Logged-out: tabs ---- */

	var tabs = document.querySelectorAll( '.nm-account-tab' );
	var panels = document.querySelectorAll( '.nm-account-panel' );

	function showPanel( name ) {
		panels.forEach( function ( panel ) {
			panel.hidden = panel.dataset.panel !== name;
		} );
		tabs.forEach( function ( tab ) {
			tab.classList.toggle( 'is-active', tab.dataset.tab === name );
		} );
	}

	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			showPanel( tab.dataset.tab );
		} );
	} );

	document.querySelectorAll( '[data-show-panel]' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			showPanel( link.dataset.showPanel );
		} );
	} );

	/* ---- Log in ---- */

	var loginForm = document.getElementById( 'nm-account-login-form' );
	if ( loginForm ) {
		loginForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var noticeEl = document.getElementById( 'nm-account-notice' );
			postAccount( 'account-login', {
				email: loginForm.email.value,
				password: loginForm.password.value
			} ).then( function ( result ) {
				if ( result.ok && result.data.success ) {
					window.location.reload();
					return;
				}
				notice( noticeEl, result.data.message || 'Something went wrong.', true );
			} );
		} );
	}

	/* ---- Sign up ---- */

	var signupForm = document.getElementById( 'nm-account-signup-form' );
	if ( signupForm ) {
		signupForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var noticeEl = document.getElementById( 'nm-account-notice' );

			if ( signupForm.password.value !== signupForm.password_confirm.value ) {
				notice( noticeEl, 'Passwords don’t match.', true );
				return;
			}

			postAccount( 'account-register', {
				name: signupForm.name.value,
				email: signupForm.email.value,
				password: signupForm.password.value,
				pref_updates: signupForm.pref_updates.checked,
				pref_new_shiur_alerts: signupForm.pref_new_shiur_alerts.checked,
				website: signupForm.website.value // honeypot — always blank for real visitors
			} ).then( function ( result ) {
				if ( result.ok && result.data.success ) {
					window.location.reload();
					return;
				}
				notice( noticeEl, result.data.message || 'Something went wrong.', true );
			} );
		} );
	}

	/* ---- Forgot password ---- */

	var forgotForm = document.getElementById( 'nm-account-forgot-form' );
	if ( forgotForm ) {
		forgotForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var noticeEl = document.getElementById( 'nm-account-notice' );
			postAccount( 'account-forgot-password', { email: forgotForm.email.value } ).then( function ( result ) {
				notice( noticeEl, result.data.message, ! result.ok );
				if ( result.ok ) {
					forgotForm.reset();
				}
			} );
		} );
	}

	/* ---- Logged-in: profile (name) ---- */

	var profileForm = document.getElementById( 'nm-account-profile-form' );
	if ( profileForm ) {
		profileForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var noticeEl = document.getElementById( 'nm-account-notice' );
			postAccount( 'account-update-profile', { name: profileForm.name.value } ).then( function ( result ) {
				notice( noticeEl, result.ok ? 'Saved.' : ( result.data.message || 'Something went wrong.' ), ! result.ok );
			} );
		} );
	}

	/* ---- Logged-in: change password ---- */

	var passwordForm = document.getElementById( 'nm-account-password-form' );
	if ( passwordForm ) {
		passwordForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var noticeEl = document.getElementById( 'nm-account-notice' );

			if ( passwordForm.new_password.value !== passwordForm.new_password_confirm.value ) {
				notice( noticeEl, 'New passwords don’t match.', true );
				return;
			}

			postAccount( 'account-update-profile', {
				current_password: passwordForm.current_password.value,
				new_password: passwordForm.new_password.value
			} ).then( function ( result ) {
				notice( noticeEl, result.ok ? 'Password updated.' : ( result.data.message || 'Something went wrong.' ), ! result.ok );
				if ( result.ok ) {
					passwordForm.reset();
				}
			} );
		} );
	}

	/* ---- Logged-in: avatar ---- */

	var avatarInput = document.getElementById( 'nm-account-avatar-input' );
	if ( avatarInput ) {
		avatarInput.addEventListener( 'change', function () {
			var file = avatarInput.files[ 0 ];
			if ( ! file ) {
				return;
			}
			var noticeEl = document.getElementById( 'nm-account-notice' );
			var preview = document.getElementById( 'nm-account-avatar-preview' );

			var formData = new FormData();
			formData.append( 'photo', file );

			postAccount( 'account-avatar', formData ).then( function ( result ) {
				if ( result.ok && result.data.success ) {
					if ( preview ) {
						preview.src = result.data.avatarUrl;
					}
					notice( noticeEl, 'Photo updated.', false );
				} else {
					notice( noticeEl, result.data.message || 'Something went wrong.', true );
				}
			} );
		} );
	}

	}

	init();
	document.addEventListener( 'nm:content-swapped', init );
} )();
