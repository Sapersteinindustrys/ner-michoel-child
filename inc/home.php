<?php
/**
 * The homepage's data and assets.
 *
 * front-page.php shows template-parts/home-new.php (assets/css/home.css,
 * assets/js/home.js): a hero with the shiur search, then the newest shiurim
 * beside the latest Mazal Tovs. It is the only homepage design. There used to
 * be a second, "Current" one, with a Current / New switch in the page's
 * top-right corner (a cookie, nm_home, and a ?home= link); both are gone, and
 * a leftover cookie or ?home= in a link is ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The homepage's styles and script, and the serif face its headings use (the
 * same one as the Written Shiurim pages, so a visitor's browser usually has it
 * already). Loaded site-wide, not just on the homepage, so the router
 * (nm-router.js) always has them ready when it swaps a visitor onto the
 * homepage without a real page load; home.js no-ops harmlessly on pages
 * without its elements.
 */
function ner_michoel_enqueue_home_assets() {
	wp_enqueue_style( 'ner-michoel-home', NER_MICHOEL_URI . '/assets/css/home.css', array( 'ner-michoel-custom' ), NER_MICHOEL_VERSION );
	wp_enqueue_script( 'ner-michoel-home', NER_MICHOEL_URI . '/assets/js/home.js', array(), NER_MICHOEL_VERSION, true );

	if ( is_front_page() ) {
		wp_enqueue_style(
			'ner-michoel-written-serif',
			'https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@400;500;700&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL carries its own versioning.
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_home_assets', 22 );

/**
 * The homepage's own words: the small line above the welcome, the welcome title
 * and paragraph, and the title and text of the banner at the bottom. An
 * administrator can change them in the Control Panel (Homepage & Look >
 * Homepage; ner-michoel-core, includes/homepage-settings.php). A blank box, or a
 * plugin that does not have the setting yet, gives the words below. The welcome names the site
 * (Homepage & Look > Site Name & Logo); the small line above it is the second, smaller name.
 *
 * @param string $key eyebrow, title, lede, cta_title or cta_text.
 */
function ner_michoel_home_text( $key ) {
	$defaults = array(
		'eyebrow'   => __( 'Ner Michoel Alumni Association', 'ner-michoel-child' ),
		/* translators: %s: the site's name */
		'title'     => sprintf( __( 'Welcome to %s', 'ner-michoel-child' ), ner_michoel_site_name() ),
		'lede'      => __( 'Thousands of shiurim, community updates, and a way to stay close to the Yeshiva and each other — wherever you are.', 'ner-michoel-child' ),
		'cta_title' => __( 'Stay Connected', 'ner-michoel-child' ),
		'cta_text'  => __( 'Update your contact info, join the alumni WhatsApp group, or reach out any time — we\'d love to hear how you\'re doing.', 'ner-michoel-child' ),
	);

	$saved = function_exists( 'ner_michoel_get_homepage_text' ) ? ner_michoel_get_homepage_text( $key ) : '';
	if ( '' !== $saved ) {
		return $saved;
	}
	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/**
 * The welcome title, as HTML ready to print. Words an administrator saved are printed as they are. The
 * site's own words, "Welcome to <name>", put the name in a span of its own, so the name stays together on
 * the line under "Welcome to" (and, when it is too long for a phone, breaks between its own words), never
 * "Welcome to Yeshivas / Toras Moshe".
 */
function ner_michoel_home_title_html() {
	$saved = function_exists( 'ner_michoel_get_homepage_text' ) ? ner_michoel_get_homepage_text( 'title' ) : '';
	if ( '' !== $saved ) {
		return esc_html( $saved );
	}
	return sprintf(
		/* translators: %s: the site's name, in an element of its own */
		esc_html__( 'Welcome to %s', 'ner-michoel-child' ),
		'<span class="hn-hero__name">' . esc_html( ner_michoel_site_name() ) . '</span>'
	);
}

/**
 * The icon in the Mazal Tov box's gold badge, and in the round badge of an announcement that has no photo:
 * the one chosen in the Control Panel (ner-michoel-core, Homepage & Look > Homepage), or a kiddush cup until
 * one is chosen. The theme keeps its own copy of the cup for a plugin that is behind.
 *
 * @return array {kind: "svg" or "image", svg: the drawing for kind svg, url: the picture for kind image}
 */
function ner_michoel_mazal_icon() {
	if ( function_exists( 'ner_michoel_get_mazal_icon' ) ) {
		$icon = ner_michoel_get_mazal_icon();
		if ( is_array( $icon ) && isset( $icon['kind'] ) && ( ( 'image' === $icon['kind'] && ! empty( $icon['url'] ) ) || ( 'svg' === $icon['kind'] && ! empty( $icon['svg'] ) ) ) ) {
			return $icon;
		}
	}
	return array(
		'kind' => 'svg',
		'svg'  => '<svg class="nm-mazal-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 3h10l-.8 6.6a4.2 4.2 0 0 1-8.4 0z"/><path d="M7.6 7h8.8"/><path d="M12 13.8v5.7"/><path d="M8 21h8"/></svg>',
		'url'  => '',
	);
}

/**
 * "Today", "Yesterday", "3 days ago", then a date: "Sep 17", or
 * "Sep 17, 2025" from another year. Days are counted in the site's timezone.
 */
function ner_michoel_home_when( $timestamp ) {
	$timestamp = (int) $timestamp;
	if ( ! $timestamp ) {
		return '';
	}
	$tz    = wp_timezone();
	$today = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0 );
	$day   = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $tz )->setTime( 0, 0 );
	// Rounded, so a day of 23 or 25 hours (clocks changing) still counts as one.
	$days = (int) round( ( $today->getTimestamp() - $day->getTimestamp() ) / DAY_IN_SECONDS );

	if ( $days <= 0 ) {
		return __( 'Today', 'ner-michoel-child' );
	}
	if ( 1 === $days ) {
		return __( 'Yesterday', 'ner-michoel-child' );
	}
	if ( $days < 7 ) {
		/* translators: %s: number of days */
		return sprintf( _n( '%s day ago', '%s days ago', $days, 'ner-michoel-child' ), number_format_i18n( $days ) );
	}
	return wp_date( $day->format( 'Y' ) === $today->format( 'Y' ) ? 'M j' : 'M j, Y', $timestamp );
}

/**
 * A shiur's stored length ("42:13", "1:02:45") as "42 min" or "1 hr 3 min".
 * Anything else is shown as it was entered.
 */
function ner_michoel_home_length( $duration ) {
	$duration = trim( (string) $duration );
	if ( ! preg_match( '/^(?:(\d+):)?(\d{1,3}):(\d{2})$/', $duration, $parts ) ) {
		return $duration;
	}
	$minutes = (int) $parts[1] * 60 + (int) $parts[2] + ( (int) $parts[3] >= 30 ? 1 : 0 );
	$minutes = max( 1, $minutes );
	if ( $minutes < 60 ) {
		/* translators: %s: minutes */
		return sprintf( __( '%s min', 'ner-michoel-child' ), number_format_i18n( $minutes ) );
	}
	$hours = (int) floor( $minutes / 60 );
	$rest  = $minutes % 60;
	if ( ! $rest ) {
		/* translators: %s: hours */
		return sprintf( __( '%s hr', 'ner-michoel-child' ), number_format_i18n( $hours ) );
	}
	/* translators: 1: hours, 2: minutes */
	return sprintf( __( '%1$s hr %2$s min', 'ner-michoel-child' ), number_format_i18n( $hours ), number_format_i18n( $rest ) );
}

/**
 * Up to two initials for a name, skipping titles: "Rabbi Avrohom Meiselman"
 * gives "AM", "Rabbi Klein" gives "K", "Rosh HaYeshiva" gives "RH".
 */
function ner_michoel_home_initials( $name ) {
	$name  = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
	$words = preg_split( '/[\s\-]+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
	$skip  = array( 'rabbi', 'rav', 'harav', 'hagaon', 'reb', "r'", 'r.', 'mr.', 'mrs.', 'mr', 'mrs', 'dr.', 'dr', '&', 'and' );
	$kept  = array();
	foreach ( $words as $word ) {
		if ( ! in_array( function_exists( 'mb_strtolower' ) ? mb_strtolower( $word, 'UTF-8' ) : strtolower( $word ), $skip, true ) ) {
			$kept[] = $word;
		}
	}
	if ( ! $kept ) {
		$kept = $words;
	}
	if ( ! $kept ) {
		return '';
	}
	$first = function ( $word ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1, 'UTF-8' ) : substr( $word, 0, 1 );
	};
	$initials = $first( $kept[0] );
	if ( count( $kept ) > 1 ) {
		$initials .= $first( $kept[ count( $kept ) - 1 ] );
	}
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials, 'UTF-8' ) : strtoupper( $initials );
}

/**
 * A steady colour for a name (the same speaker always gets the same one),
 * as a hue for home.css's initials badges.
 */
function ner_michoel_home_hue( $name ) {
	return abs( crc32( (string) $name ) ) % 360;
}

/**
 * One shiur as the new homepage lists it.
 */
function ner_michoel_home_shiur_row( $post ) {
	$speakers = get_the_terms( $post, 'speaker' );
	$speaker  = ( $speakers && ! is_wp_error( $speakers ) ) ? $speakers[0] : null;
	$series   = get_the_terms( $post, 'series' );
	$serie    = ( $series && ! is_wp_error( $series ) ) ? $series[0] : null;

	// The shiur's own picture, then its speaker's photo, then initials.
	$photo = get_the_post_thumbnail_url( $post, 'thumbnail' );
	if ( ! $photo && $speaker && function_exists( 'ner_michoel_get_speaker_photo_url' ) ) {
		$photo = ner_michoel_get_speaker_photo_url( $speaker->term_id );
	}

	$timestamp = (int) get_post_time( 'U', true, $post );
	$name      = $speaker ? $speaker->name : get_the_title( $post );

	return array(
		'id'       => $post->ID,
		'title'    => get_the_title( $post ),
		'link'     => get_permalink( $post ),
		'speaker'  => $speaker ? $speaker->name : '',
		'series'   => $serie ? ner_michoel_series_full_name( $serie ) : '',
		'photo'    => $photo ? $photo : '',
		'initials' => ner_michoel_home_initials( $name ),
		'hue'      => ner_michoel_home_hue( $name ),
		'is_video' => function_exists( 'ner_michoel_shiur_is_video' ) && ner_michoel_shiur_is_video( $post->ID ),
		'is_new'   => $timestamp && ( time() - $timestamp ) <= 14 * DAY_IN_SECONDS,
		'when'     => ner_michoel_home_when( $timestamp ),
		'iso'      => $timestamp ? gmdate( 'c', $timestamp ) : '',
		'length'   => function_exists( 'ner_michoel_get_shiur_duration' ) ? ner_michoel_home_length( ner_michoel_get_shiur_duration( $post->ID ) ) : '',
	);
}

/**
 * Everything the new homepage shows, gathered in one place so the template
 * (template-parts/home-new.php) only lays it out. Every query is small, and
 * every call into ner-michoel-core is guarded, so the page still renders
 * when the plugin is behind.
 */
function ner_michoel_home_new_data() {
	$data = array(
		'shiurim'   => array(),
		'written'   => array(),
		'mazal'     => array(),
		'galleries' => array(),
		'slides'    => function_exists( 'ner_michoel_get_homepage_slider_images' ) ? ner_michoel_get_homepage_slider_images() : array(),
		'interval'  => function_exists( 'ner_michoel_get_homepage_slider_interval_seconds' ) ? max( 3, (int) ner_michoel_get_homepage_slider_interval_seconds() ) * 1000 : 6000,
		'counts'    => array(),
		'urls'      => array(
			'shiurim'    => post_type_exists( 'shiur' ) ? get_post_type_archive_link( 'shiur' ) : '',
			'written'    => post_type_exists( 'written_shiur' ) ? get_post_type_archive_link( 'written_shiur' ) : '',
			'galleries'  => post_type_exists( 'gallery' ) ? get_post_type_archive_link( 'gallery' ) : '',
			'mazal'      => post_type_exists( 'mazal_tov' ) ? get_post_type_archive_link( 'mazal_tov' ) : '',
			'contribute' => home_url( '/contribute/' ),
			'contact'    => home_url( '/contact/' ),
			'about'      => home_url( '/about/' ),
			'post_mazal' => current_user_can( 'edit_posts' ) && post_type_exists( 'mazal_tov' ) ? admin_url( 'admin.php?page=nm-mazal-tov-quick-add' ) : '',
		),
	);

	if ( post_type_exists( 'shiur' ) ) {
		$shiurim = get_posts(
			array(
				'post_type'      => 'shiur',
				'posts_per_page' => 8,
				'no_found_rows'  => true,
			)
		);
		foreach ( $shiurim as $post ) {
			$data['shiurim'][] = ner_michoel_home_shiur_row( $post );
		}
	}

	if ( post_type_exists( 'written_shiur' ) && function_exists( 'ner_michoel_written_data' ) ) {
		$written = get_posts(
			array(
				'post_type'      => 'written_shiur',
				'posts_per_page' => 8,
				'no_found_rows'  => true,
			)
		);
		foreach ( $written as $post ) {
			$item = ner_michoel_written_data( $post );
			if ( ! $item ) {
				continue;
			}
			$data['written'][] = array(
				'id'      => $item['id'],
				'title'   => $item['main'],
				'note'    => $item['note'],
				'link'    => $item['link'],
				'speaker' => $item['speaker'] ? $item['speaker']->name : '',
				'sefer'   => $item['sefer'],
				'slug'    => $item['slug'],
				'is_new'  => $item['timestamp'] && ( time() - $item['timestamp'] ) <= 14 * DAY_IN_SECONDS,
				'when'    => ner_michoel_home_when( $item['timestamp'] ),
				'iso'     => $item['timestamp'] ? gmdate( 'c', $item['timestamp'] ) : '',
			);
		}
	}

	if ( post_type_exists( 'mazal_tov' ) ) {
		$mazal = get_posts(
			array(
				'post_type'      => 'mazal_tov',
				'posts_per_page' => 6,
				'no_found_rows'  => true,
			)
		);
		foreach ( $mazal as $post ) {
			$types = taxonomy_exists( 'mazal_tov_type' ) ? get_the_terms( $post, 'mazal_tov_type' ) : false;
			$type  = ( $types && ! is_wp_error( $types ) ) ? $types[0] : null;
			$photo = get_the_post_thumbnail_url( $post, 'thumbnail' );
			$parts = ner_michoel_theme_mazal_parts( $post );

			// The words before the name, "Name (years)", and what the Mazal Tov is. No date: an announcement is not shown with when it was posted.
			$data['mazal'][] = array(
				'before'    => $parts['before'],
				'lead'      => $parts['lead'],
				'after'     => $parts['after'],
				'type'      => $type ? $type->name : '',
				'type_slug' => $type ? sanitize_html_class( $type->slug ) : '',
				'photo'     => $photo ? $photo : '',
			);
		}
	}

	if ( post_type_exists( 'gallery' ) ) {
		$galleries = get_posts(
			array(
				'post_type'      => 'gallery',
				'posts_per_page' => 4,
				'no_found_rows'  => true,
			)
		);
		foreach ( $galleries as $post ) {
			$data['galleries'][] = array(
				'title' => get_the_title( $post ),
				'link'  => get_permalink( $post ),
				'cover' => function_exists( 'ner_michoel_get_gallery_cover_url' ) ? ner_michoel_get_gallery_cover_url( $post->ID ) : '',
				'meta'  => function_exists( 'ner_michoel_gallery_card_subtitle' ) ? ner_michoel_gallery_card_subtitle( $post->ID ) : get_the_date( '', $post ),
			);
		}
	}

	foreach ( array( 'shiur', 'written_shiur' ) as $type ) {
		$count                   = post_type_exists( $type ) ? wp_count_posts( $type ) : null;
		$data['counts'][ $type ] = ( $count && isset( $count->publish ) ) ? (int) $count->publish : 0;
	}
	$speakers                  = taxonomy_exists( 'speaker' ) ? wp_count_terms( array( 'taxonomy' => 'speaker', 'hide_empty' => true ) ) : 0;
	$data['counts']['speaker'] = is_wp_error( $speakers ) ? 0 : (int) $speakers;

	return $data;
}
