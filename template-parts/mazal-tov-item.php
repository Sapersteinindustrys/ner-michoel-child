<?php
/**
 * One Mazal Tov on the News & Events page, the Mazal Tov archive and its single page:
 * its type, then "Name (years)" in bold and what the Mazal Tov is. No date.
 *
 * Pass the announcement as 'post' in the args of get_template_part().
 */

$mt = isset( $args['post'] ) ? get_post( $args['post'] ) : get_post();
if ( ! $mt ) {
	return;
}
$parts     = ner_michoel_theme_mazal_parts( $mt );
$type_name = ner_michoel_theme_mazal_type( $mt );
?>
<div class="sh-mazal-tov-item">
	<?php if ( $type_name ) : ?>
		<span class="sh-mazal-tov-item__type"><?php echo esc_html( $type_name ); ?></span>
	<?php endif; ?>
	<span class="sh-mazal-tov-item__name">
		<strong class="sh-mazal-tov-item__lead"><?php echo esc_html( $parts['lead'] ); ?></strong><?php if ( '' !== $parts['rest'] ) : ?> <span class="sh-mazal-tov-item__rest"><?php echo esc_html( $parts['rest'] ); ?></span><?php endif; ?>
	</span>
</div>
