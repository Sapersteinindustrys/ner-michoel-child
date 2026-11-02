<?php
/**
 * The Alumni section (/alumni/, ner-michoel-core includes/alumni-section.php):
 * its own page and its own search, only for alumni. Everyone else is told how to
 * get in. The list is drawn here and filtered in the browser as you type
 * (assets/js/alumni.js); none of it is in the public shiur index.
 */

get_header();

$nm_can   = function_exists( 'ner_michoel_alumni_can_view' ) && ner_michoel_alumni_can_view();
$nm_items = ( $nm_can && function_exists( 'ner_michoel_alumni_items' ) ) ? ner_michoel_alumni_items() : array();
// "Rabbi Shurkin's shiurim": the speakers chosen for the section on the Control Panel.
$nm_names = array();
foreach ( function_exists( 'ner_michoel_alumni_speakers' ) ? ner_michoel_alumni_speakers() : array() as $nm_sid ) {
	$nm_term = get_term( $nm_sid, 'speaker' );
	if ( $nm_term && ! is_wp_error( $nm_term ) ) {
		$nm_names[] = $nm_term->name;
	}
}
$nm_kinds = array(
	'a' => __( 'Listen', 'ner-michoel-child' ),
	'v' => __( 'Watch', 'ner-michoel-child' ),
	'w' => __( 'Read', 'ner-michoel-child' ),
);
?>

<div class="shiurim-app nm-alumni">
	<header class="sh-page-header nm-alumni__header">
		<p class="nm-alumni__kicker"><?php esc_html_e( 'For alumni of the yeshiva', 'ner-michoel-child' ); ?></p>
		<h1><?php esc_html_e( 'Alumni', 'ner-michoel-child' ); ?></h1>
	</header>

	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="nm-alumni__gate">
			<p><?php esc_html_e( 'This section is for alumni of the yeshiva. Please log in with your account to see it.', 'ner-michoel-child' ); ?></p>
			<p><a class="nm-contact-form__submit nm-alumni__button" href="<?php echo esc_url( home_url( '/account/' ) ); ?>"><?php esc_html_e( 'Log in or sign up', 'ner-michoel-child' ); ?></a></p>
		</div>
	<?php elseif ( ! $nm_can ) : ?>
		<div class="nm-alumni__gate">
			<p><?php esc_html_e( 'This section is for alumni of the yeshiva, and your account isn’t on our alumni list yet. Please contact the office and they will add you.', 'ner-michoel-child' ); ?></p>
			<p><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact the office', 'ner-michoel-child' ); ?></a></p>
		</div>
	<?php elseif ( ! $nm_items ) : ?>
		<p class="nm-alumni__empty"><?php esc_html_e( 'There are no shiurim here yet.', 'ner-michoel-child' ); ?></p>
	<?php else : ?>
		<?php if ( $nm_names ) : ?>
			<h2 class="nm-alumni__h" dir="auto"><?php echo esc_html( sprintf( /* translators: %s: speaker names */ __( 'Shiurim of %s', 'ner-michoel-child' ), implode( ', ', $nm_names ) ) ); ?></h2>
		<?php endif; ?>
		<div class="nm-alumni__search" data-alumni-search>
			<label class="screen-reader-text" for="nm-alumni-q"><?php esc_html_e( 'Search the alumni shiurim', 'ner-michoel-child' ); ?></label>
			<input type="search" id="nm-alumni-q" class="nm-alumni__query" placeholder="<?php esc_attr_e( 'Search by title, series or year…', 'ner-michoel-child' ); ?>" autocomplete="off" data-alumni-query />
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Kind', 'ner-michoel-child' ); ?></span>
				<select data-alumni-kind>
					<option value=""><?php esc_html_e( 'All', 'ner-michoel-child' ); ?></option>
					<?php foreach ( $nm_kinds as $nm_k => $nm_label ) : ?>
						<option value="<?php echo esc_attr( $nm_k ); ?>"><?php echo esc_html( $nm_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<p class="nm-alumni__count" data-alumni-count aria-live="polite"
			data-one="<?php esc_attr_e( '1 shiur', 'ner-michoel-child' ); ?>"
			data-many="<?php esc_attr_e( '%d shiurim', 'ner-michoel-child' ); ?>"
			data-none="<?php esc_attr_e( 'No shiurim match your search.', 'ner-michoel-child' ); ?>">
			<?php echo esc_html( sprintf( _n( '%d shiur', '%d shiurim', count( $nm_items ), 'ner-michoel-child' ), count( $nm_items ) ) ); ?>
		</p>
		<div class="nm-alumni__wrap">
			<table class="nm-alumni__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'ner-michoel-child' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Series', 'ner-michoel-child' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'ner-michoel-child' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Listen / Watch / Read', 'ner-michoel-child' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $nm_items as $nm_item ) : ?>
					<?php
					$nm_date = wp_date( 'j M Y', $nm_item['time'] );
					$nm_q    = strtolower( $nm_item['title'] . ' ' . $nm_item['series'] . ' ' . $nm_date );
					?>
					<tr data-q="<?php echo esc_attr( $nm_q ); ?>" data-kind="<?php echo esc_attr( $nm_item['kind'] ); ?>">
						<td class="nm-alumni__title"><a href="<?php echo esc_url( $nm_item['url'] ); ?>" dir="auto"><?php echo esc_html( $nm_item['title'] ); ?></a></td>
						<td class="nm-alumni__series" dir="auto"><?php echo esc_html( $nm_item['series'] ); ?></td>
						<td class="nm-alumni__date"><?php echo esc_html( $nm_date ); ?></td>
						<td class="nm-alumni__kind"><a href="<?php echo esc_url( $nm_item['url'] ); ?>"><?php echo esc_html( $nm_kinds[ $nm_item['kind'] ] ); ?></a><?php echo '' !== $nm_item['duration'] ? ' <span class="nm-alumni__dur">' . esc_html( $nm_item['duration'] ) . '</span>' : ''; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
