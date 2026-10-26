<?php
/**
 * The homepage's "This week" section (inc/week.php): this week's parsha and the Yom Tov
 * in season, each a panel with a title block on the left and its newest shiurim as rows
 * (the same rows as New Shiurim). Prints nothing when neither has shiurim.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$occasions = function_exists( 'ner_michoel_home_week_data' ) ? ner_michoel_home_week_data() : array();
if ( ! $occasions ) {
	return;
}
?>

<div class="hn-week">
	<?php foreach ( $occasions as $index => $occasion ) : ?>
		<?php $title_id = 'hn-week-title-' . (int) $index; ?>
		<section class="hn-panel hn-occasion hn-occasion--<?php echo esc_attr( $occasion['type'] ); ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="hn-occasion__intro">
				<p class="hn-occasion__kicker"><?php echo esc_html( $occasion['kicker'] ); ?></p>
				<h2 class="hn-occasion__title" id="<?php echo esc_attr( $title_id ); ?>">
					<?php if ( $occasion['prefix'] ) : ?>
						<span class="hn-occasion__prefix"><?php echo esc_html( $occasion['prefix'] ); ?></span>
					<?php endif; ?>
					<?php echo esc_html( $occasion['name'] ); ?>
				</h2>
				<?php if ( $occasion['when'] ) : ?>
					<p class="hn-occasion__when"><?php echo esc_html( $occasion['when'] ); ?></p>
				<?php endif; ?>
				<?php if ( $occasion['link'] ) : ?>
					<a class="hn-occasion__all" href="<?php echo esc_url( $occasion['link'] ); ?>">
						<?php /* translators: %s: number of shiurim, such as "62 shiurim" */ ?>
						<?php echo esc_html( sprintf( __( 'All %s', 'ner-michoel-child' ), ner_michoel_home_week_count( $occasion['count'] ) ) ); ?>
						<?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
					</a>
				<?php endif; ?>
			</div>

			<ol class="hn-list hn-occasion__list">
				<?php foreach ( $occasion['rows'] as $row ) : ?>
					<?php if ( 'written' === $row['kind'] ) : ?>
						<li class="hn-row nm-accent--<?php echo esc_attr( $row['slug'] ); ?>">
							<a class="hn-row__link" href="<?php echo esc_url( $row['link'] ); ?>">
								<span class="hn-row__art hn-row__art--sheet" aria-hidden="true"><?php echo ner_michoel_line_icon( 'book-open' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
								<span class="hn-row__body">
									<span class="hn-row__title"><?php echo esc_html( $row['title'] ); ?><?php if ( $row['note'] ) : ?> <span class="hn-row__note"><?php echo esc_html( $row['note'] ); ?></span><?php endif; ?></span>
									<span class="hn-row__meta">
										<?php if ( $row['speaker'] ) : ?>
											<span><?php echo esc_html( $row['speaker'] ); ?></span>
										<?php endif; ?>
										<?php if ( $row['sefer'] ) : ?>
											<span class="hn-row__sefer"><?php echo esc_html( $row['sefer'] ); ?></span>
										<?php endif; ?>
									</span>
								</span>
								<span class="hn-row__side">
									<?php if ( $row['is_new'] ) : ?>
										<span class="hn-pill hn-pill--new"><?php esc_html_e( 'New', 'ner-michoel-child' ); ?></span>
									<?php else : ?>
										<span class="hn-pill"><?php esc_html_e( 'PDF', 'ner-michoel-child' ); ?></span>
									<?php endif; ?>
									<?php if ( $row['when'] ) : ?>
										<span class="hn-row__facts"><time datetime="<?php echo esc_attr( $row['iso'] ); ?>"><?php echo esc_html( $row['when'] ); ?></time></span>
									<?php endif; ?>
								</span>
							</a>
						</li>
					<?php else : ?>
						<li class="hn-row">
							<a class="hn-row__link" href="<?php echo esc_url( $row['link'] ); ?>">
								<span class="hn-row__art"<?php echo $row['photo'] ? '' : ' style="--hue: ' . (int) $row['hue'] . '"'; ?>>
									<?php if ( $row['photo'] ) : ?>
										<img src="<?php echo esc_url( $row['photo'] ); ?>" alt="" loading="lazy" decoding="async" width="56" height="56" />
									<?php else : ?>
										<span class="hn-row__initials" aria-hidden="true"><?php echo esc_html( $row['initials'] ); ?></span>
									<?php endif; ?>
									<span class="hn-row__play" aria-hidden="true"><?php echo ner_michoel_icon( $row['is_video'] ? 'video' : 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
								</span>
								<span class="hn-row__body">
									<span class="hn-row__title"><?php echo esc_html( $row['title'] ); ?></span>
									<span class="hn-row__meta">
										<?php if ( $row['speaker'] ) : ?>
											<span><?php echo esc_html( $row['speaker'] ); ?></span>
										<?php endif; ?>
										<?php if ( $row['series'] ) : ?>
											<span><?php echo esc_html( $row['series'] ); ?></span>
										<?php endif; ?>
									</span>
								</span>
								<span class="hn-row__side">
									<?php if ( $row['is_new'] ) : ?>
										<span class="hn-pill hn-pill--new"><?php esc_html_e( 'New', 'ner-michoel-child' ); ?></span>
									<?php elseif ( $row['is_video'] ) : ?>
										<span class="hn-pill"><?php esc_html_e( 'Video', 'ner-michoel-child' ); ?></span>
									<?php endif; ?>
									<span class="hn-row__facts">
										<?php if ( $row['when'] ) : ?>
											<time datetime="<?php echo esc_attr( $row['iso'] ); ?>"><?php echo esc_html( $row['when'] ); ?></time>
										<?php endif; ?>
										<?php if ( $row['length'] ) : ?>
											<span><?php echo esc_html( $row['length'] ); ?></span>
										<?php endif; ?>
									</span>
								</span>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>
</div>
