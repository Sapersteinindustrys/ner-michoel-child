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

	var slides = slider.querySelectorAll( '.nm-hero-slider__slide' );
	var dots   = document.querySelectorAll( '.nm-hero-slider__dot' );
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

	function applySpeed( speed ) {
		currentSpeed = speed;
		audio.playbackRate = speed;
		elSpeed.textContent = speed + 'x';
		elSpeed.classList.toggle( 'is-active', speed !== 1 );
		try {
			window.localStorage.setItem( SPEED_STORAGE_KEY, speed );
		} catch ( e ) {
			// Storage unavailable — speed still applies this session.
		}
	}

	elSpeed.addEventListener( 'click', function () {
		var next = SPEEDS[ ( SPEEDS.indexOf( currentSpeed ) + 1 ) % SPEEDS.length ];
		applySpeed( next );
	} );

	elSpeed.textContent = currentSpeed + 'x';
	elSpeed.classList.toggle( 'is-active', currentSpeed !== 1 );

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
			row.classList.remove( 'is-active' );
		} );
		if ( ! track ) {
			return;
		}
		document.querySelectorAll( '.sh-track[data-id="' + track.id + '"]' ).forEach( function ( row ) {
			row.classList.add( 'is-active' );
		} );
	}

	function updateMeta() {
		var track = currentTrack();
		if ( ! track ) {
			return;
		}
		elTitle.textContent   = track.title || '';
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
		var track = currentTrack();
		if ( track && ! reportedPlay[ track.id ] ) {
			reportedPlay[ track.id ] = true;
			nerMichoelSendShiurEvent( track.id, 'play' );
		}
	} );

	audio.addEventListener( 'pause', function () {
		setToggleIcon( false );
		persist( false );
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
