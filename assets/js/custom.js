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
	} );

	elVolume.addEventListener( 'input', function () {
		audio.volume = parseFloat( elVolume.value );
	} );

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
					audio.removeEventListener( 'loadedmetadata', onMeta );
				} );
			}
		}
	} catch ( e ) {
		// Ignore malformed/unavailable storage.
	}
} )();
