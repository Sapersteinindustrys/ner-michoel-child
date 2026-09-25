/**
 * Ner Michoel - front-end behavior.
 *
 * Shiurim player: a small queue-based audio engine driving the
 * persistent bottom bar (see ner_michoel_render_player_bar() in
 * inc/template-tags.php) plus every `[data-play-queue]` card and
 * `.sh-tracklist` row on the Shiurim templates.
 *
 * State (current track + position) is snapshotted to localStorage so
 * the bar can redisplay "now playing" after navigating to a different
 * speaker/series page. Browsers block autoplay without a user
 * gesture, so playback itself doesn't resume automatically — the bar
 * just reappears paused at the right spot until the user hits play.
 */

/**
 * Reports a shiur play/completion event to the backend's stats
 * endpoint (POST {post_id, event}) — best-effort, fire-and-forget, so
 * a failed request just means a stat doesn't get counted and never
 * blocks playback. Used by both the audio queue engine and the
 * native <video> player below, hence a plain top-level function
 * rather than scoped inside either one's IIFE.
 */
function nerMichoelSendShiurEvent( postId, event ) {
	if ( ! postId || ! window.nerMichoelSettings || ! window.nerMichoelSettings.shiurEventUrl ) {
		return;
	}
	fetch( window.nerMichoelSettings.shiurEventUrl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( { post_id: parseInt( postId, 10 ), event: event } )
	} ).catch( function () {
		// Ignore — stats are best-effort, not critical path.
	} );
}

( function () {
	'use strict';

	// Classic layout has no JS-controlled player (its "Play" is a
	// plain link opening native browser playback), so 'play' is
	// reported on click rather than on an actual `play` event —
	// there's no completion signal to fire from a click, so Classic
	// intentionally never reports 'complete' (an approximated one
	// would be worse than an honest gap). "Download" links are
	// deliberately not tracked here — a download isn't a listen.
	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( '.sh-classic-play-link' );
		if ( ! link ) {
			return;
		}
		nerMichoelSendShiurEvent( link.getAttribute( 'data-shiur-id' ), 'play' );
	} );
} )();

( function () {
	'use strict';

	// 24Six carousel rows (archive-shiur.php, taxonomy-speaker.php) —
	// prev/next buttons are progressive enhancement on top of native
	// scroll-snap; touch/trackpad swiping already works without this.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.sh-carousel__nav' );
		if ( ! btn ) {
			return;
		}
		var track = btn.parentElement.querySelector( '.sh-carousel__track' );
		if ( ! track ) {
			return;
		}
		var amount = Math.round( track.clientWidth * 0.8 );
		track.scrollBy( { left: btn.classList.contains( 'sh-carousel__nav--prev' ) ? -amount : amount, behavior: 'smooth' } );
	} );
} )();

( function () {
	'use strict';

	// Site-wide layout toggle (Shiurim, Galleries, News & Events) —
	// sets a cookie the server reads (see ner_michoel_get_layout() in
	// inc/template-tags.php) and reloads, since Modern/Classic are
	// separate PHP templates, not a CSS-only skin swap.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.sh-layout-toggle__option' );
		if ( ! btn || btn.classList.contains( 'is-active' ) ) {
			return;
		}
		document.cookie = 'nm_layout=' + btn.getAttribute( 'data-layout' ) + ';path=/;max-age=31536000';
		window.location.reload();
	} );
} )();

( function () {
	'use strict';

	// Gallery slider (Modern layout, single-gallery.php) — stage image
	// + thumbnail strip, no external library. Each .sh-gallery-slider
	// carries its full image list as JSON so this doesn't need to walk
	// the DOM to know what comes next/previous.
	document.querySelectorAll( '.sh-gallery-slider' ).forEach( function ( slider ) {
		var images;
		try {
			images = JSON.parse( slider.getAttribute( 'data-images' ) );
		} catch ( e ) {
			return;
		}
		if ( ! images || ! images.length ) {
			return;
		}

		var stageImg = slider.querySelector( '.sh-gallery-slider__image' );
		var caption  = slider.querySelector( '.sh-gallery-slider__caption' );
		var thumbs   = slider.querySelectorAll( '.sh-gallery-slider__thumb' );
		var prevBtn  = slider.querySelector( '.sh-gallery-slider__nav--prev' );
		var nextBtn  = slider.querySelector( '.sh-gallery-slider__nav--next' );
		var index    = 0;

		function show( i ) {
			index = ( i + images.length ) % images.length;
			var img = images[ index ];
			stageImg.src    = img.url;
			stageImg.width  = img.width;
			stageImg.height = img.height;
			stageImg.alt    = img.alt || '';
			if ( caption ) {
				caption.textContent = img.caption || '';
			}
			thumbs.forEach( function ( thumb, i2 ) {
				thumb.classList.toggle( 'is-active', i2 === index );
			} );
		}

		thumbs.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				show( parseInt( thumb.getAttribute( 'data-index' ), 10 ) || 0 );
			} );
		} );

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				show( index - 1 );
			} );
		}
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				show( index + 1 );
			} );
		}

		slider.setAttribute( 'tabindex', '0' );
		slider.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowLeft' === e.key ) {
				show( index - 1 );
			} else if ( 'ArrowRight' === e.key ) {
				show( index + 1 );
			}
		} );
	} );
} )();

( function () {
	'use strict';

	// Homepage hero slider (front-page.php, admin-managed via the Site
	// Control Panel) — auto-advances, pauses on hover, dots jump
	// directly to a slide. No external library, same pattern as the
	// gallery slider above.
	var slider = document.querySelector( '.nm-hero-slider' );
	if ( ! slider ) {
		return;
	}

	var slides   = slider.querySelectorAll( '.nm-hero-slider__slide' );
	var dots     = document.querySelectorAll( '.nm-hero-slider__dot' );
	var navPrev  = document.querySelector( '.nm-hero-slider__nav--prev' );
	var navNext  = document.querySelector( '.nm-hero-slider__nav--next' );
	if ( slides.length < 2 ) {
		return;
	}

	var interval = parseInt( slider.getAttribute( 'data-interval' ), 10 ) || 6000;
	var index    = 0;
	var timer    = null;

	function show( i ) {
		index = ( i + slides.length ) % slides.length;
		slides.forEach( function ( slide, i2 ) {
			slide.classList.toggle( 'is-active', i2 === index );
		} );
		dots.forEach( function ( dot, i2 ) {
			dot.classList.toggle( 'is-active', i2 === index );
		} );
	}

	function start() {
		stop();
		timer = window.setInterval( function () {
			show( index + 1 );
		}, interval );
	}

	function stop() {
		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	dots.forEach( function ( dot ) {
		dot.addEventListener( 'click', function () {
			show( parseInt( dot.getAttribute( 'data-index' ), 10 ) || 0 );
			start();
		} );
	} );

	if ( navPrev ) {
		navPrev.addEventListener( 'click', function () {
			show( index - 1 );
			start();
		} );
	}

	if ( navNext ) {
		navNext.addEventListener( 'click', function () {
			show( index + 1 );
			start();
		} );
	}

	slider.addEventListener( 'mouseenter', stop );
	slider.addEventListener( 'mouseleave', start );

	start();
} )();

( function () {
	'use strict';

	var player = document.getElementById( 'sh-player' );
	if ( ! player ) {
		return;
	}

	var audio       = document.getElementById( 'sh-audio' );
	var elCover     = document.getElementById( 'sh-player-cover' );
	var elTitle     = document.getElementById( 'sh-player-title' );
	var elSpeaker   = document.getElementById( 'sh-player-speaker' );
	var elToggle    = document.getElementById( 'sh-player-toggle' );
	var elPrev      = document.getElementById( 'sh-player-prev' );
	var elNext      = document.getElementById( 'sh-player-next' );
	var elSeek      = document.getElementById( 'sh-player-seek' );
	var elCurrent   = document.getElementById( 'sh-player-current' );
	var elDuration  = document.getElementById( 'sh-player-duration' );
	var elVolume    = document.getElementById( 'sh-player-volume' );
	var elSpeed     = document.getElementById( 'sh-player-speed' );

	var ICON_PLAY  = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
	var ICON_PAUSE = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>';

	var STORAGE_KEY       = 'nerMichoelPlayer';
	var SPEED_STORAGE_KEY = 'nerMichoelPlayerSpeed';
	var SPEEDS            = [ 0.75, 1, 1.25, 1.5, 1.75, 2 ];

	var state = {
		queue: [],
		index: -1
	};

	// Tracks which track IDs already reported a 'play'/'complete' event
	// this page load, so resuming from pause or seeking around doesn't
	// re-report the same milestone.
	var reportedPlay     = {};
	var reportedComplete = {};
	var COMPLETE_THRESHOLD = 0.9;

	var currentSpeed = 1;
	try {
		var savedSpeed = parseFloat( window.localStorage.getItem( SPEED_STORAGE_KEY ) );
		if ( SPEEDS.indexOf( savedSpeed ) !== -1 ) {
			currentSpeed = savedSpeed;
		}
	} catch ( e ) {
		// Storage unavailable — default speed.
	}

	// Playback speed: a trigger that opens a menu listing every speed.
	// Replaces a button that cycled one step per click, which meant
	// reaching a slower speed took clicking through every faster one,
	// and the options weren't visible until you landed on them.
	var elSpeedLabel = document.getElementById( 'sh-player-speed-label' );
	var elSpeedMenu  = document.getElementById( 'sh-player-speed-menu' );
	var elSpeedWrap  = elSpeed.closest( '.sh-player__speed-wrap' );
	var speedOptions = [];

	var ICON_CHECK = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

	function formatSpeed( speed ) {
		return speed + '\u00d7'; // multiplication sign, escaped so it survives any served charset
	}

	function renderSpeed() {
		elSpeedLabel.textContent = formatSpeed( currentSpeed );
		elSpeed.classList.toggle( 'is-active', currentSpeed !== 1 );
		elSpeed.setAttribute( 'aria-label', 'Playback speed: ' + formatSpeed( currentSpeed ) );
		speedOptions.forEach( function ( option ) {
			var isCurrent = parseFloat( option.getAttribute( 'data-speed' ) ) === currentSpeed;
			option.setAttribute( 'aria-checked', isCurrent ? 'true' : 'false' );
		} );
	}

	// Browsers reset playbackRate to defaultPlaybackRate every time a new
	// src loads, so setting both means the chosen speed carries across
	// track changes on its own instead of relying on each load path to
	// remember to re-apply it.
	function syncAudioRate() {
		audio.defaultPlaybackRate = currentSpeed;
		audio.playbackRate        = currentSpeed;
	}

	function applySpeed( speed ) {
		currentSpeed = speed;
		syncAudioRate();
		renderSpeed();
		try {
			window.localStorage.setItem( SPEED_STORAGE_KEY, speed );
		} catch ( e ) {
			// Storage unavailable — speed still applies this session.
		}
	}

	// One step up/down the list, stopping at either end (no wrap — a
	// wrap from 2× straight to 0.75× on a keyboard shortcut would be
	// jarring mid-listen). Used by the < and > shortcuts below.
	function stepSpeed( direction ) {
		var i    = SPEEDS.indexOf( currentSpeed );
		var next = SPEEDS[ Math.min( SPEEDS.length - 1, Math.max( 0, i + direction ) ) ];
		if ( next !== currentSpeed ) {
			applySpeed( next );
		}
	}

	function isSpeedMenuOpen() {
		return ! elSpeedMenu.hidden;
	}

	function openSpeedMenu() {
		elSpeedMenu.hidden = false;
		elSpeed.setAttribute( 'aria-expanded', 'true' );
		var current = speedOptions.filter( function ( option ) {
			return 'true' === option.getAttribute( 'aria-checked' );
		} )[ 0 ];
		( current || speedOptions[ 0 ] ).focus();
	}

	function closeSpeedMenu( returnFocus ) {
		if ( ! isSpeedMenuOpen() ) {
			return;
		}
		elSpeedMenu.hidden = true;
		elSpeed.setAttribute( 'aria-expanded', 'false' );
		if ( returnFocus ) {
			elSpeed.focus();
		}
	}

	var speedTitle = document.createElement( 'div' );
	speedTitle.className = 'sh-speed-menu__title';
	speedTitle.setAttribute( 'aria-hidden', 'true' ); // the menu itself already carries the label
	speedTitle.textContent = 'Playback speed';
	elSpeedMenu.appendChild( speedTitle );

	SPEEDS.forEach( function ( speed ) {
		var option = document.createElement( 'button' );
		option.type = 'button';
		option.className = 'sh-speed-menu__option';
		option.setAttribute( 'role', 'menuitemradio' );
		option.setAttribute( 'tabindex', '-1' );
		option.setAttribute( 'data-speed', String( speed ) );
		option.innerHTML =
			'<span class="sh-speed-menu__check">' + ICON_CHECK + '</span>' +
			'<span class="sh-speed-menu__value">' + formatSpeed( speed ) + '</span>' +
			( 1 === speed ? '<span class="sh-speed-menu__hint">Normal</span>' : '' );

		option.addEventListener( 'click', function () {
			applySpeed( speed );
			closeSpeedMenu( true );
		} );

		// Hover moves focus, so there's only ever one highlighted row —
		// otherwise the keyboard-focused row and the hovered row would
		// both light up at once.
		option.addEventListener( 'mouseenter', function () {
			option.focus();
		} );

		elSpeedMenu.appendChild( option );
		speedOptions.push( option );
	} );

	elSpeed.addEventListener( 'click', function () {
		if ( isSpeedMenuOpen() ) {
			closeSpeedMenu( true );
		} else {
			openSpeedMenu();
		}
	} );

	elSpeed.addEventListener( 'keydown', function ( e ) {
		if ( 'ArrowUp' === e.key || 'ArrowDown' === e.key ) {
			e.preventDefault();
			openSpeedMenu();
		}
	} );

	elSpeedMenu.addEventListener( 'keydown', function ( e ) {
		var count = speedOptions.length;
		var i     = speedOptions.indexOf( document.activeElement );
		switch ( e.key ) {
			case 'ArrowDown':
				e.preventDefault();
				speedOptions[ i < 0 ? 0 : ( i + 1 ) % count ].focus();
				break;
			case 'ArrowUp':
				e.preventDefault();
				speedOptions[ i < 0 ? count - 1 : ( i - 1 + count ) % count ].focus();
				break;
			case 'Home':
				e.preventDefault();
				speedOptions[ 0 ].focus();
				break;
			case 'End':
				e.preventDefault();
				speedOptions[ count - 1 ].focus();
				break;
			case 'Escape':
				e.preventDefault();
				closeSpeedMenu( true );
				break;
			case 'Tab':
				closeSpeedMenu( false );
				break;
		}
	} );

	// Click/tap anywhere outside the control closes the menu.
	document.addEventListener( 'pointerdown', function ( e ) {
		if ( isSpeedMenuOpen() && ! elSpeedWrap.contains( e.target ) ) {
			closeSpeedMenu( false );
		}
	} );

	syncAudioRate();
	renderSpeed();

	// Drives the seek/volume rails' filled-progress look — see the
	// --fill custom property consumed in custom.css. Set on both
	// sliders any time their value changes for real.
	function setRangeFill( el ) {
		var min = parseFloat( el.min ) || 0;
		var max = parseFloat( el.max ) || 1;
		var val = parseFloat( el.value ) || 0;
		var pct = max > min ? ( ( val - min ) / ( max - min ) ) * 100 : 0;
		el.style.setProperty( '--fill', pct + '%' );
	}

	function formatTime( seconds ) {
		if ( ! isFinite( seconds ) || seconds < 0 ) {
			return '0:00';
		}
		var m = Math.floor( seconds / 60 );
		var s = Math.floor( seconds % 60 );
		return m + ':' + ( s < 10 ? '0' : '' ) + s;
	}

	function currentTrack() {
		return state.queue[ state.index ] || null;
	}

	function updateActiveRowHighlight() {
		var track = currentTrack();
		document.querySelectorAll( '.sh-track.is-active' ).forEach( function ( row ) {
			row.classList.remove( 'is-active', 'is-playing' );
		} );
		if ( ! track ) {
			return;
		}
		document.querySelectorAll( '.sh-track[data-id="' + track.id + '"]' ).forEach( function ( row ) {
			row.classList.add( 'is-active' );
			// The animated equalizer bars (sh-track__eq, see custom.css)
			// only make sense while actually playing — a paused-but-
			// selected row shows the plain track number instead.
			row.classList.toggle( 'is-playing', ! audio.paused );
		} );
	}

	function updateMeta() {
		var track = currentTrack();
		if ( ! track ) {
			return;
		}
		elTitle.textContent   = track.title || '';
		elTitle.href          = track.url || '#';
		elSpeaker.textContent = track.speaker || '';
		if ( track.cover ) {
			elCover.innerHTML = '<img src="' + track.cover + '" alt="" />';
		} else {
			elCover.innerHTML = '';
		}
		updateActiveRowHighlight();
	}

	function persist( playing ) {
		var track = currentTrack();
		if ( ! track ) {
			return;
		}
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( {
				track: track,
				position: audio.currentTime || 0,
				playing: !! playing
			} ) );
		} catch ( e ) {
			// Storage unavailable (private mode, quota) — playback still works, just no resume.
		}
	}

	function setToggleIcon( playing ) {
		elToggle.innerHTML = playing ? ICON_PAUSE : ICON_PLAY;
		elToggle.setAttribute( 'aria-label', playing ? 'Pause' : 'Play' );
		// Mirrors the state onto the bar itself so CSS can tell the two
		// glyphs apart — the play triangle needs an optical nudge right
		// of center, the pause bars don't (see .sh-player__toggle svg).
		player.classList.toggle( 'is-playing', playing );
	}

	// Buffering indicator (spinner over the cover art, see
	// .sh-player.is-loading in custom.css) — 'waiting' fires whenever
	// playback stalls for more data, 'playing'/'canplay' clear it.
	function setLoading( isLoading ) {
		player.classList.toggle( 'is-loading', isLoading );
	}

	function loadTrack( autoplay ) {
		var track = currentTrack();
		if ( ! track ) {
			return;
		}
		audio.src = track.src;
		audio.playbackRate = currentSpeed;
		updateMeta();
		player.hidden = false;
		elPrev.disabled = state.index <= 0;
		elNext.disabled = state.index >= state.queue.length - 1;
		elSeek.value = 0;
		setRangeFill( elSeek );
		if ( autoplay ) {
			audio.play();
		}
	}

	function playQueue( queue, index ) {
		if ( ! queue || ! queue.length ) {
			return;
		}
		state.queue = queue;
		state.index = index || 0;
		loadTrack( true );
	}

	function playIndex( index ) {
		if ( index < 0 || index >= state.queue.length ) {
			return;
		}
		state.index = index;
		loadTrack( true );
	}

	function parseQueue( el ) {
		try {
			return JSON.parse( el.getAttribute( 'data-play-queue' ) );
		} catch ( e ) {
			return null;
		}
	}

	// Cards and "Play All" buttons.
	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-play-queue]' );
		if ( ! trigger ) {
			return;
		}
		e.preventDefault();
		var queue = parseQueue( trigger );
		var index = parseInt( trigger.getAttribute( 'data-play-index' ), 10 ) || 0;
		playQueue( queue, index );
	} );

	// Tracklist rows.
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.sh-track__download' ) ) {
			return;
		}
		var row = e.target.closest( '.sh-track' );
		if ( ! row ) {
			return;
		}
		var list = row.closest( '.sh-tracklist' );
		if ( ! list ) {
			return;
		}
		var queue = parseQueue( list );
		var index = parseInt( row.getAttribute( 'data-index' ), 10 ) || 0;
		if ( ! queue ) {
			return;
		}
		playQueue( queue, index );
	} );

	elToggle.addEventListener( 'click', function () {
		if ( ! currentTrack() ) {
			return;
		}
		if ( audio.paused ) {
			audio.play();
		} else {
			audio.pause();
		}
	} );

	elPrev.addEventListener( 'click', function () {
		playIndex( state.index - 1 );
	} );

	elNext.addEventListener( 'click', function () {
		playIndex( state.index + 1 );
	} );

	audio.addEventListener( 'play', function () {
		setToggleIcon( true );
		persist( true );
		updateActiveRowHighlight();
		setLoading( false );
		var track = currentTrack();
		if ( track && ! reportedPlay[ track.id ] ) {
			reportedPlay[ track.id ] = true;
			nerMichoelSendShiurEvent( track.id, 'play' );
		}
	} );

	audio.addEventListener( 'pause', function () {
		setToggleIcon( false );
		persist( false );
		updateActiveRowHighlight();
	} );

	audio.addEventListener( 'waiting', function () {
		setLoading( true );
	} );

	audio.addEventListener( 'playing', function () {
		setLoading( false );
	} );

	audio.addEventListener( 'canplay', function () {
		setLoading( false );
	} );

	audio.addEventListener( 'loadedmetadata', function () {
		elDuration.textContent = formatTime( audio.duration );
		elSeek.max = Math.floor( audio.duration ) || 0;
	} );

	audio.addEventListener( 'timeupdate', function () {
		elCurrent.textContent = formatTime( audio.currentTime );
		if ( ! elSeek.matches( ':active' ) ) {
			elSeek.value = Math.floor( audio.currentTime );
			setRangeFill( elSeek );
		}
		var track = currentTrack();
		if ( track && ! reportedComplete[ track.id ] && audio.duration && ( audio.currentTime / audio.duration ) >= COMPLETE_THRESHOLD ) {
			reportedComplete[ track.id ] = true;
			nerMichoelSendShiurEvent( track.id, 'complete' );
		}
	} );

	audio.addEventListener( 'ended', function () {
		if ( state.index < state.queue.length - 1 ) {
			playIndex( state.index + 1 );
		} else {
			setToggleIcon( false );
			persist( false );
		}
	} );

	elSeek.addEventListener( 'input', function () {
		audio.currentTime = parseFloat( elSeek.value );
		setRangeFill( elSeek );
	} );

	elVolume.addEventListener( 'input', function () {
		audio.volume = parseFloat( elVolume.value );
		setRangeFill( elVolume );
	} );

	// Keyboard shortcuts (space/arrows), same set every major player
	// uses — skipped whenever focus is on a form control, so typing
	// in the contact form or a search box isn't hijacked, and skipped
	// on any modifier combo so browser/OS shortcuts stay intact.
	document.addEventListener( 'keydown', function ( e ) {
		if ( ! currentTrack() ) {
			return;
		}
		if ( e.ctrlKey || e.altKey || e.metaKey ) {
			return;
		}
		var tag = e.target.tagName;
		if ( 'INPUT' === tag || 'TEXTAREA' === tag || 'SELECT' === tag || e.target.isContentEditable ) {
			return;
		}
		// The speed control handles its own keys. Without this, Space on
		// a focused menu option would toggle playback here AND its
		// preventDefault would cancel the option's own click, and the
		// arrows would change volume instead of moving through the list.
		if ( elSpeedWrap.contains( e.target ) ) {
			return;
		}
		switch ( e.key ) {
			// < and > step the speed — same keys YouTube uses (Shift+,/.).
			case '<':
				e.preventDefault();
				stepSpeed( -1 );
				break;
			case '>':
				e.preventDefault();
				stepSpeed( 1 );
				break;
			case ' ':
				e.preventDefault();
				if ( audio.paused ) {
					audio.play();
				} else {
					audio.pause();
				}
				break;
			case 'ArrowLeft':
				e.preventDefault();
				audio.currentTime = Math.max( 0, audio.currentTime - 10 );
				break;
			case 'ArrowRight':
				e.preventDefault();
				audio.currentTime = Math.min( audio.duration || audio.currentTime + 10, audio.currentTime + 10 );
				break;
			case 'ArrowUp':
				e.preventDefault();
				audio.volume = Math.min( 1, audio.volume + 0.1 );
				elVolume.value = audio.volume;
				setRangeFill( elVolume );
				break;
			case 'ArrowDown':
				e.preventDefault();
				audio.volume = Math.max( 0, audio.volume - 0.1 );
				elVolume.value = audio.volume;
				setRangeFill( elVolume );
				break;
		}
	} );

	// Volume starts at 1 (100%) per its markup default — fill it in
	// immediately rather than waiting for the first drag.
	setRangeFill( elVolume );

	// Restore the last "now playing" snapshot (paused) after navigating
	// to a different Shiurim page — no autoplay, browsers block it
	// without a fresh user gesture anyway.
	try {
		var saved = window.localStorage.getItem( STORAGE_KEY );
		if ( saved ) {
			var snapshot = JSON.parse( saved );
			if ( snapshot && snapshot.track ) {
				state.queue = [ snapshot.track ];
				state.index = 0;
				audio.src = snapshot.track.src;
				audio.playbackRate = currentSpeed;
				updateMeta();
				player.hidden = false;
				elPrev.disabled = true;
				elNext.disabled = true;
				setToggleIcon( false );
				var resumeAt = snapshot.position || 0;
				audio.addEventListener( 'loadedmetadata', function onMeta() {
					audio.currentTime = resumeAt;
					elSeek.value = Math.floor( resumeAt );
					setRangeFill( elSeek );
					audio.removeEventListener( 'loadedmetadata', onMeta );
				} );
			}
		}
	} catch ( e ) {
		// Ignore malformed/unavailable storage.
	}
} )();

( function () {
	'use strict';

	// Video shiur play/completion tracking — single-shiur.php's native
	// <video controls> element isn't part of the audio queue engine
	// above, so it gets its own (much simpler) play-once/complete-once
	// listeners rather than sharing that engine's state.
	var COMPLETE_THRESHOLD = 0.9;

	document.querySelectorAll( '.sh-video-player' ).forEach( function ( wrap ) {
		var video = wrap.querySelector( 'video' );
		if ( ! video ) {
			return;
		}
		var postId        = wrap.getAttribute( 'data-post-id' );
		var reportedPlay     = false;
		var reportedComplete = false;

		video.addEventListener( 'play', function () {
			if ( ! reportedPlay ) {
				reportedPlay = true;
				nerMichoelSendShiurEvent( postId, 'play' );
			}
		} );

		video.addEventListener( 'timeupdate', function () {
			if ( ! reportedComplete && video.duration && ( video.currentTime / video.duration ) >= COMPLETE_THRESHOLD ) {
				reportedComplete = true;
				nerMichoelSendShiurEvent( postId, 'complete' );
			}
		} );
	} );
} )();
