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
	 * The sign-up that's waiting for its code: { email, token, resendAt }. Kept
	 * in sessionStorage (this tab only, gone when it closes), so reloading the
	 * page doesn't lose the code step, with a copy in memory for browsers that
	 * won't let a page use storage.
	 */
	var PENDING_KEY = 'nmAccountPending';
	var WELCOME_KEY = 'nmAccountWelcome';
	var pendingInMemory = null;
	var resendTimer = null;

	function readPending() {
		if ( pendingInMemory ) {
			return pendingInMemory;
		}
		try {
			var raw = window.sessionStorage.getItem( PENDING_KEY );
			return raw ? JSON.parse( raw ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function writePending( state ) {
		pendingInMemory = state;
		try {
			if ( state ) {
				window.sessionStorage.setItem( PENDING_KEY, JSON.stringify( state ) );
			} else {
				window.sessionStorage.removeItem( PENDING_KEY );
			}
		} catch ( e ) {
			// Storage is off: the copy in memory is enough for this page.
		}
	}

	/** Shown once on the account page, right after the code was accepted. */
	function takeWelcome() {
		try {
			var name = window.sessionStorage.getItem( WELCOME_KEY );
			window.sessionStorage.removeItem( WELCOME_KEY );
			return name;
		} catch ( e ) {
			return null;
		}
	}

	function hideNotice( el ) {
		if ( el ) {
			el.hidden = true;
			el.textContent = '';
		}
	}

	/** Disables a form's send button while its request is out, so a double click can't send twice. */
	function setFormBusy( form, busy ) {
		form.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		form.querySelectorAll( 'button[type="submit"]' ).forEach( function ( button ) {
			button.disabled = busy;
		} );
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
		} ).catch( function () {
			// No answer (offline, or a reply that isn't JSON): say so, instead of leaving the form looking dead.
			return { ok: false, data: { message: 'We couldn’t reach the server. Please check your connection and try again.' } };
		} );
	}

	/**
	 * The bot check's fields (ner-michoel-core assets/form-guard.js) added to
	 * what a logged-out form sends. Nothing to add when the check isn't on
	 * the page (an older plugin).
	 */
	function withGuard( form, data ) {
		var extra = window.nmFormGuard ? window.nmFormGuard.fields( form ) : {};
		Object.keys( extra ).forEach( function ( key ) {
			data[ key ] = extra[ key ];
		} );
		return data;
	}

	/** Each check works once: get a new one ready for the next try. */
	function renewGuard( form ) {
		if ( window.nmFormGuard ) {
			window.nmFormGuard.reset( form );
		}
	}

	function init() {

	/* ---- Logged-out: tabs ---- */

	var tabs = document.querySelectorAll( '.nm-account-tab' );
	var panels = document.querySelectorAll( '.nm-account-panel' );

	var tabStrip = document.querySelector( '.nm-account-tabs' );

	function showPanel( name ) {
		panels.forEach( function ( panel ) {
			panel.hidden = panel.dataset.panel !== name;
		} );
		tabs.forEach( function ( tab ) {
			tab.classList.toggle( 'is-active', tab.dataset.tab === name );
		} );
		// While a code is being entered, the tabs would only lead away from it.
		if ( tabStrip ) {
			tabStrip.hidden = 'verify' === name;
		}
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
			postAccount( 'account-login', withGuard( loginForm, {
				email: loginForm.email.value,
				password: loginForm.password.value
			} ) ).then( function ( result ) {
				if ( result.ok && result.data.success ) {
					window.location.reload();
					return;
				}
				renewGuard( loginForm );
				if ( result.data.verify ) {
					// The right password, but the email isn't confirmed yet: on to the code step.
					loginForm.password.value = '';
					startVerify( result.data );
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

			setFormBusy( signupForm, true );
			postAccount( 'account-register', withGuard( signupForm, {
				name: signupForm.name.value,
				email: signupForm.email.value,
				password: signupForm.password.value,
				pref_updates: signupForm.pref_updates.checked,
				pref_new_shiur_alerts: signupForm.pref_new_shiur_alerts.checked,
				website: signupForm.website.value // honeypot — always blank for real visitors
			} ) ).then( function ( result ) {
				if ( result.ok && result.data.success && ! result.data.verify ) {
					window.location.reload();
					return;
				}
				setFormBusy( signupForm, false );
				renewGuard( signupForm );
				if ( result.ok && result.data.success ) {
					// Account made but not confirmed yet: on to the code step (and the password out of the page).
					signupForm.password.value = '';
					signupForm.password_confirm.value = '';
					startVerify( result.data );
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
			postAccount( 'account-forgot-password', withGuard( forgotForm, { email: forgotForm.email.value } ) ).then( function ( result ) {
				renewGuard( forgotForm );
				notice( noticeEl, result.data.message, ! result.ok );
				if ( result.ok ) {
					forgotForm.reset();
				}
			} );
		} );
	}

	/* ---- Email verification: enter the code ---- */

	var noticeBox = document.getElementById( 'nm-account-notice' );
	var verifyForm = document.getElementById( 'nm-account-verify-form' );
	var pending = null;

	function digitsOnly( text ) {
		return String( text || '' ).replace( /\D/g, '' );
	}

	/** The Resend button: a countdown while a new code can't be asked for yet. */
	function renderResend() {
		var button = verifyForm && verifyForm.querySelector( '[data-verify-resend]' );
		if ( ! button || ! document.body.contains( button ) ) {
			// This page was swapped out: its timer has nothing left to update.
			window.clearInterval( resendTimer );
			resendTimer = null;
			return;
		}
		if ( pending && pending.noResend ) {
			// Every code this sign-up gets has been sent; the message says what to do instead.
			button.disabled = true;
			button.textContent = 'No more codes for this sign-up';
			verifyForm.querySelector( '[data-verify-forgot-wrap]' ).hidden = false;
			window.clearInterval( resendTimer );
			resendTimer = null;
			return;
		}
		var left = pending ? Math.ceil( ( pending.resendAt - Date.now() ) / 1000 ) : 0;
		if ( left > 0 ) {
			button.disabled = true;
			button.textContent = 'Resend code in ' + left + 's';
		} else {
			button.disabled = false;
			button.textContent = 'Resend code';
			window.clearInterval( resendTimer );
			resendTimer = null;
		}
	}

	function waitToResend( seconds ) {
		if ( ! pending ) {
			return;
		}
		pending.resendAt = Date.now() + Math.max( 0, Number( seconds ) || 0 ) * 1000;
		writePending( pending );
		window.clearInterval( resendTimer );
		resendTimer = null;
		renderResend();
		if ( pending.resendAt > Date.now() ) {
			resendTimer = window.setInterval( renderResend, 500 );
		}
	}

	function setVerifyBusy( busy ) {
		verifyForm.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		verifyForm.querySelector( 'button[type="submit"]' ).disabled = busy;
		verifyForm.code.readOnly = busy;
	}

	/**
	 * Shows the code step for the sign-up in `data` (the server's reply: the
	 * email, the token for this browser, and how long until another code can be
	 * asked for), with the server's message above it.
	 */
	function startVerify( data ) {
		pending = { email: data.email, token: data.token, resendAt: 0, noResend: 'exhausted' === data.status };
		showVerify( data.message, 'failed' === data.status || 'capped' === data.status || 'exhausted' === data.status );
		waitToResend( data.resend_in );
	}

	function showVerify( message, isError ) {
		verifyForm.querySelector( '[data-verify-email]' ).textContent = pending.email;
		verifyForm.code.value = '';
		setVerifyBusy( false );
		showPanel( 'verify' );
		if ( message ) {
			notice( noticeBox, message, !! isError );
		} else {
			hideNotice( noticeBox );
		}
		renderResend();
		verifyForm.code.focus();
	}

	/** The sign-up can't be continued from here (another tab or a new code replaced it): back to Log In, which picks it up again. */
	function verifySessionEnded( message ) {
		var email = pending ? pending.email : '';
		writePending( null );
		pending = null;
		window.clearInterval( resendTimer );
		resendTimer = null;
		showPanel( 'login' );
		notice( noticeBox, message, true );
		if ( loginForm ) {
			loginForm.password.value = '';
			if ( email ) {
				loginForm.email.value = email; // Only the password is left to type.
				loginForm.password.focus();
			} else {
				loginForm.email.focus();
			}
		}
	}

	if ( verifyForm ) {
		verifyForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! pending ) {
				showPanel( 'login' );
				return;
			}
			var code = digitsOnly( verifyForm.code.value );
			if ( 6 !== code.length ) {
				notice( noticeBox, 'Please enter the 6-digit code from the email.', true );
				verifyForm.code.focus();
				return;
			}

			setVerifyBusy( true );
			postAccount( 'account-verify', { email: pending.email, token: pending.token, code: code } ).then( function ( result ) {
				var data = result.data || {};
				if ( result.ok && data.success ) {
					try {
						window.sessionStorage.setItem( WELCOME_KEY, data.name || '1' );
					} catch ( err ) {
						// No storage: they just won't see the welcome line.
					}
					writePending( null );
					notice( noticeBox, 'Email confirmed! Taking you to your account…', false );
					window.setTimeout( function () {
						window.location.reload();
					}, 600 );
					return;
				}
				if ( 'invalid_session' === data.code ) {
					verifySessionEnded( data.message );
					return;
				}
				setVerifyBusy( false );
				notice( noticeBox, data.message || 'Something went wrong.', true );
				verifyForm.code.value = '';
				verifyForm.code.focus();
				if ( 'number' === typeof data.resend_in ) {
					waitToResend( data.resend_in );
				}
			} );
		} );

		// Only the digits count: a pasted "123 456" works, and the sixth digit sends it.
		verifyForm.code.addEventListener( 'input', function () {
			var digits = digitsOnly( verifyForm.code.value ).slice( 0, 6 );
			if ( verifyForm.code.value !== digits ) {
				verifyForm.code.value = digits;
			}
			if ( 6 === digits.length && ! verifyForm.code.readOnly ) {
				if ( 'function' === typeof verifyForm.requestSubmit ) {
					verifyForm.requestSubmit();
				} else {
					verifyForm.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
				}
			}
		} );

		verifyForm.querySelector( '[data-verify-resend]' ).addEventListener( 'click', function () {
			if ( ! pending ) {
				return;
			}
			var button = this;
			button.disabled = true;
			button.textContent = 'Sending…';
			postAccount( 'account-resend-code', { email: pending.email, token: pending.token } ).then( function ( result ) {
				var data = result.data || {};
				if ( 'invalid_session' === data.code ) {
					verifySessionEnded( data.message );
					return;
				}
				notice( noticeBox, data.message || 'Something went wrong.', ! ( result.ok && data.success ) );
				if ( 'exhausted' === data.code ) {
					pending.noResend = true;
				}
				waitToResend( 'number' === typeof data.resend_in ? data.resend_in : 10 );
				verifyForm.code.focus();
			} );
		} );

		verifyForm.querySelector( '[data-verify-forgot]' ).addEventListener( 'click', function () {
			if ( pending && forgotForm ) {
				forgotForm.email.value = pending.email;
			}
		} );

		verifyForm.querySelector( '[data-verify-restart]' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			writePending( null );
			pending = null;
			window.clearInterval( resendTimer );
			resendTimer = null;
			hideNotice( noticeBox );
			showPanel( 'signup' );
			if ( signupForm ) {
				signupForm.email.focus();
			}
		} );

		// Reloaded, or back on this page, while a code is waiting: pick up where they were.
		var resumed = readPending();
		if ( resumed && resumed.email && resumed.token ) {
			pending = resumed;
			showVerify( '', false );
			waitToResend( Math.ceil( ( resumed.resendAt - Date.now() ) / 1000 ) );
		}
	}

	/* ---- Right after the code was accepted ---- */

	if ( document.getElementById( 'nm-account-profile-form' ) ) {
		var welcomeName = takeWelcome();
		if ( welcomeName ) {
			notice( noticeBox, 'Your email is confirmed. Welcome' + ( '1' !== welcomeName ? ', ' + welcomeName : '' ) + '!', false );
		}
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
