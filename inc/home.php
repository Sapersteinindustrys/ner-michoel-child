<?php
/**
 * The homepage's two designs, and the switch between them.
 *
 * front-page.php holds the current design. template-parts/home-new.php holds
 * the new one (assets/css/home.css, assets/js/home.js): a hero with the shiur
 * search, then the newest shiurim beside the latest Mazal Tovs. A Current /
 * New switch in the homepage's top-right corner flips between them.
 *
 * The choice is a cookie (nm_home), like the Modern / Classic / 24Six layout
 * cookie, so the server renders one design and nothing is sent twice. The
 * host's page cache only answers requests without cookies, so those all get
 * the default design, and anyone who has switched gets theirs. A link with
 * ?home=new or ?home=current shows that design, and home.js remembers it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The homepage design to show: 'new' or 'current'. A ?home= in the link
 * comes first, then the visitor's own choice, then the default: the new
 * design, unless the ner_michoel_home_default_view filter says otherwise.
 */
function ner_michoel_get_home_view() {
	$views = array( 'new', 'current' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only display choice.
	$asked = isset( $_GET['home'] ) && is_string( $_GET['home'] ) ? sanitize_key( wp_unslash( $_GET['home'] ) ) : '';
	if ( in_array( $asked, $views, true ) ) {
		return $asked;
	}

	$saved = isset( $_COOKIE['nm_home'] ) && is_string( $_COOKIE['nm_home'] ) ? sanitize_key( wp_unslash( $_COOKIE['nm_home'] ) ) : '';
	if ( in_array( $saved, $views, true ) ) {
		return $saved;
	}

	$default = apply_filters( 'ner_michoel_home_default_view', 'new' );
	return in_array( $default, $views, true ) ? $default : 'new';
}

/**
 * The Current / New switch: the same dark pill as the layout switch on the
 * Shiurim pages, in the top-right corner of each design's opening section
 * (the new hero, the current slider). It's printed inside that section, so
 * it sits in its corner whatever the header's height or the screen's width,
 * and it stays at the top rather than following the reader down the page.
 */
function ner_michoel_render_home_switch() {
	if ( ! is_front_page() ) {
		return;
	}
	$view    = ner_michoel_get_home_view();
	$options = array(
		'current' => __( 'Current', 'ner-michoel-child' ),
		'new'     => __( 'New', 'ner-michoel-child' ),
	);
	?>
	<div class="nm-home-switch" role="group" aria-label="<?php esc_attr_e( 'Homepage design', 'ner-michoel-child' ); ?>" data-home-switch data-home-view="<?php echo esc_attr( $view ); ?>">
		<?php foreach ( $options as $key => $label ) : ?>
			<button type="button" class="nm-home-switch__option<?php echo $key === $view ? ' is-active' : ''; ?>" data-home-choose="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo $key === $view ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The switch's styles and script on the homepage (both designs), and the
 * serif face the new design's headings use (the same one as the Written
 * Shiurim pages, so a visitor's browser usually has it already).
 */
function ner_michoel_enqueue_home_assets() {
	if ( ! is_front_page() ) {
		return;
	}

	wp_enqueue_style( 'ner-michoel-home', NER_MICHOEL_URI . '/assets/css/home.css', array( 'ner-michoel-custom' ), NER_MICHOEL_VERSION );
	wp_enqueue_script( 'ner-michoel-home', NER_MICHOEL_URI . '/assets/js/home.js', array(), NER_MICHOEL_VERSION, true );

	if ( 'new' === ner_michoel_get_home_view() ) {
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
		'series'   => $serie ? $serie->name : '',
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
			$types     = taxonomy_exists( 'mazal_tov_type' ) ? get_the_terms( $post, 'mazal_tov_type' ) : false;
			$type      = ( $types && ! is_wp_error( $types ) ) ? $types[0] : null;
			$timestamp = (int) get_post_time( 'U', true, $post );
			$photo     = get_the_post_thumbnail_url( $post, 'thumbnail' );

			$data['mazal'][] = array(
				'name'         => get_the_title( $post ),
				'relationship' => function_exists( 'ner_michoel_get_mazal_tov_relationship' ) ? ner_michoel_get_mazal_tov_relationship( $post->ID ) : '',
				'years'        => function_exists( 'ner_michoel_get_mazal_tov_years' ) ? ner_michoel_get_mazal_tov_years( $post->ID ) : '',
				'type'         => $type ? $type->name : '',
				'type_slug'    => $type ? sanitize_html_class( $type->slug ) : '',
				'photo'        => $photo ? $photo : '',
				'when'         => ner_michoel_home_when( $timestamp ),
				'iso'          => $timestamp ? gmdate( 'c', $timestamp ) : '',
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
