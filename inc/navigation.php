<?php
/**
 * "Written Shiurim" under the Shiurim item of the site header menu, when
 * the admin has it switched on (Site Control Panel > Site Settings > Menu).
 *
 * The menu itself is built in Appearance > Menus, and this never writes to
 * it. The entry is added to the list as WordPress renders it, so switching
 * the option off removes it at once. It's skipped when the menu already has
 * a Written Shiurim item (added by hand), or has no Shiurim item to nest
 * under, so it can't duplicate or land somewhere odd.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_nav_menu_objects', 'ner_michoel_nav_add_written_under_shiurim', 10, 2 );

function ner_michoel_nav_add_written_under_shiurim( $items, $args ) {
	if ( ! function_exists( 'ner_michoel_written_in_shiurim_menu_enabled' ) || ! ner_michoel_written_in_shiurim_menu_enabled() ) {
		return $items;
	}
	if ( ! post_type_exists( 'written_shiur' ) || ! ner_michoel_nav_is_header_menu( $args ) ) {
		return $items;
	}

	$written_url = untrailingslashit( (string) get_post_type_archive_link( 'written_shiur' ) );
	$shiurim_url = untrailingslashit( (string) get_post_type_archive_link( 'shiur' ) );

	$shiurim       = null;
	$highest_order = 0;
	foreach ( $items as $item ) {
		$url = untrailingslashit( (string) $item->url );

		if ( '' !== $written_url && $url === $written_url ) {
			return $items; // Already in the menu, added by hand.
		}
		if ( null === $shiurim && ( ( '' !== $shiurim_url && $url === $shiurim_url ) || 0 === strcasecmp( trim( $item->title ), 'Shiurim' ) ) ) {
			$shiurim = $item;
		}

		$highest_order = max( $highest_order, (int) $item->menu_order );
	}

	if ( null === $shiurim ) {
		return $items;
	}

	// Cloned from the Shiurim item so every property the walker reads
	// exists, then overridden. The negative ID can't collide with a real
	// menu item's database ID.
	$is_current = is_post_type_archive( 'written_shiur' ) || is_singular( 'written_shiur' );

	$child                        = clone $shiurim;
	$child->ID                    = -1;
	$child->db_id                 = -1;
	$child->menu_item_parent      = (string) $shiurim->db_id;
	$child->title                 = __( 'Written Shiurim', 'ner-michoel-child' );
	$child->url                   = get_post_type_archive_link( 'written_shiur' );
	$child->object                = 'written_shiur';
	$child->object_id             = 0;
	$child->type                  = 'custom';
	$child->type_label            = __( 'Custom Link', 'ner-michoel-child' );
	$child->menu_order            = $highest_order + 1;
	$child->target                = '';
	$child->attr_title            = '';
	$child->description           = '';
	$child->xfn                   = '';
	$child->post_parent           = 0;
	$child->ancestors             = array();
	$child->classes               = array( 'menu-item', 'menu-item-type-custom', 'menu-item-object-written_shiur', 'nm-menu-written' );
	$child->current               = $is_current;
	$child->current_item_ancestor = false;
	$child->current_item_parent   = false;

	if ( $is_current ) {
		$child->classes[] = 'current-menu-item';
	}

	$items[] = $child;

	return $items;
}

/**
 * The site's header menu, on desktop and mobile. Astra renders it with the
 * main-header-menu class (the class the header CSS in custom.css targets),
 * and it also uses the primary location. Either one counts. Other menus,
 * such as a footer, are left alone.
 */
function ner_michoel_nav_is_header_menu( $args ) {
	$location   = isset( $args->theme_location ) ? (string) $args->theme_location : '';
	$menu_class = isset( $args->menu_class ) ? (string) $args->menu_class : '';

	return 'primary' === $location || false !== strpos( $menu_class, 'main-header-menu' );
}

/**
 * "Log In" (or the visitor's first name, once logged in) as the last
 * item in the site header menu, linking to /account/ — added at render
 * time, same reasoning and mechanics as the Written Shiurim item
 * above (never writes to the saved menu, skipped if already there by
 * hand). Shows on every page since there's no setting to turn it off:
 * unlike Written Shiurim, there's no backend feature this depends on
 * that might not be installed — accounts.php not being active just
 * means the link 404s like any other page would with no content yet.
 */
add_filter( 'wp_nav_menu_objects', 'ner_michoel_nav_add_account_link', 10, 2 );

function ner_michoel_nav_add_account_link( $items, $args ) {
	if ( ! ner_michoel_nav_is_header_menu( $args ) ) {
		return $items;
	}

	$account_url = untrailingslashit( home_url( '/account/' ) );

	// Cloned from the first item this loop sees, the same reasoning as
	// the Written Shiurim item above: every property the walker reads
	// exists, without hardcoding which ones that build of Astra happens
	// to read. $items[0] specifically would be wrong here — nothing
	// guarantees these keys are contiguous from 0 (an upstream filter
	// — Astra's own, a caching/minification plugin, anything hooked
	// earlier on wp_nav_menu_objects — can remove an item without
	// reindexing), and this crashed the entire site the first time it
	// shipped: $items[0] was unset, so $template was null, and
	// `clone null` is a fatal TypeError, uncaught, on every page the
	// header renders — i.e. all of them. Caught by capturing the
	// template inside the loop instead of indexing after it.
	$template      = null;
	$highest_order = 0;
	foreach ( $items as $item ) {
		if ( untrailingslashit( (string) $item->url ) === $account_url ) {
			return $items; // Already in the menu, added by hand.
		}
		if ( null === $template ) {
			$template = $item;
		}
		$highest_order = max( $highest_order, (int) $item->menu_order );
	}

	if ( null === $template ) {
		return $items;
	}

	$is_logged_in = is_user_logged_in();
	$label        = $is_logged_in ? wp_get_current_user()->first_name : '';
	if ( '' === $label ) {
		$label = $is_logged_in ? __( 'My Account', 'ner-michoel-child' ) : __( 'Log In', 'ner-michoel-child' );
	}

	$child                        = clone $template;
	$child->ID                    = -2;
	$child->db_id                 = -2;
	$child->menu_item_parent      = '0';
	$child->title                 = $label;
	$child->url                   = home_url( '/account/' );
	$child->object                = 'custom';
	$child->object_id             = 0;
	$child->type                  = 'custom';
	$child->type_label            = __( 'Custom Link', 'ner-michoel-child' );
	$child->menu_order            = $highest_order + 1;
	$child->target                = '';
	$child->attr_title            = '';
	$child->description           = '';
	$child->xfn                   = '';
	$child->post_parent           = 0;
	$child->ancestors             = array();
	$child->classes               = array( 'menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'nm-menu-account' );
	$child->current               = is_page_template( 'page-templates/account.php' );
	$child->current_item_ancestor = false;
	$child->current_item_parent   = false;

	if ( $child->current ) {
		$child->classes[] = 'current-menu-item';
	}

	$items[] = $child;

	return $items;
}

/**
 * Styles for the Back link on Shiurim-section pages, and the layout
 * toggle's position under 24Six. See assets/css/shiurim-navigation.css.
 */
function ner_michoel_enqueue_shiurim_navigation_styles() {
	wp_enqueue_style(
		'ner-michoel-shiurim-navigation',
		NER_MICHOEL_URI . '/assets/css/shiurim-navigation.css',
		array( 'ner-michoel-custom' ),
		NER_MICHOEL_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'ner_michoel_enqueue_shiurim_navigation_styles', 21 );
