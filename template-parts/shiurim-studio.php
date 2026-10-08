<?php
/**
 * The Shiurim home in the Studio layout (archive-shiur.php). Data from
 * ner_michoel_studio_home_data() (inc/shiurim-studio.php), styles in
 * assets/css/shiurim-studio.css.
 *
 * Every Play button carries its first track and plays inside the tap; the rest
 * of its queue loads from ner-michoel/v1/queue (assets/js/custom.js). The rows
 * of series are .sh-carousel, so custom.js's arrows scroll them.
 */

$data   = ner_michoel_studio_home_data();
$latest = $data['latest'];
$search = home_url( '/' );
?>

<div class="shiurim-app st">
	<?php ner_michoel_render_back_button(); ?>

	<section class="st-hero" aria-labelledby="st-hero-title">
		<div class="st-hero__copy">
			<p class="st-eyebrow"><?php esc_html_e( 'Ner Michoel · Shiurim', 'ner-michoel-child' ); ?></p>
			<h1 class="st-hero__title" id="st-hero-title"><?php esc_html_e( 'The Shiurim Library', 'ner-michoel-child' ); ?></h1>
			<p class="st-hero__lede"><?php esc_html_e( 'Every shiur, by speaker, series and topic. Search the whole library, or start with what was just given.', 'ner-michoel-child' ); ?></p>

			<form class="st-search" method="get" action="<?php echo esc_url( $search ); ?>" role="search">
				<input type="hidden" name="post_type" value="shiur" />
				<label class="screen-reader-text" for="st-search-input"><?php esc_html_e( 'Search shiurim', 'ner-michoel-child' ); ?></label>
				<span class="st-search__icon" aria-hidden="true"><?php echo ner_michoel_icon( 'search' ); ?></span>
				<input class="st-search__input" id="st-search-input" type="search" name="s" placeholder="<?php esc_attr_e( 'Search by title, speaker or series', 'ner-michoel-child' ); ?>" autocomplete="off" />
				<button class="st-search__go" type="submit"><?php esc_html_e( 'Search', 'ner-michoel-child' ); ?></button>
			</form>

			<ul class="st-stats">
				<li><strong><?php echo esc_html( number_format_i18n( $data['shiur_count'] ) ); ?></strong> <?php esc_html_e( 'shiurim', 'ner-michoel-child' ); ?></li>
				<li><strong><?php echo esc_html( number_format_i18n( $data['speaker_count'] ) ); ?></strong> <?php esc_html_e( 'speakers', 'ner-michoel-child' ); ?></li>
				<li><strong><?php echo esc_html( number_format_i18n( $data['series_count'] ) ); ?></strong> <?php esc_html_e( 'series', 'ner-michoel-child' ); ?></li>
				<?php if ( $data['topic_count'] ) : ?>
					<li><strong><?php echo esc_html( number_format_i18n( $data['topic_count'] ) ); ?></strong> <?php esc_html_e( 'topics', 'ner-michoel-child' ); ?></li>
				<?php endif; ?>
			</ul>
		</div>

		<div class="st-hero__side">
			<?php if ( $latest ) : ?>
				<div class="st-feature">
					<p class="st-feature__label"><?php esc_html_e( 'Just added', 'ner-michoel-child' ); ?></p>
					<div class="st-feature__body">
						<div class="st-feature__art">
							<?php ner_michoel_studio_art( $latest['photo'], $latest['initials'], $latest['hue'], 'st-art st-art--feature' ); ?>
						</div>
						<div class="st-feature__text">
							<a class="st-feature__title" href="<?php echo esc_url( $latest['link'] ); ?>"><?php echo esc_html( $latest['title'] ); ?></a>
							<span class="st-feature__meta">
								<?php echo esc_html( implode( ' · ', array_filter( array( $latest['speaker'], $latest['length'] ) ) ) ); ?>
							</span>
						</div>
						<?php ner_michoel_studio_play_button( $latest['track'], $latest['queue_url'], $latest['title'], 'st-play st-play--feature' ); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $data['continue'] ) : ?>
				<?php $resume = $data['continue']['shiur']; ?>
				<div class="st-feature st-feature--quiet">
					<p class="st-feature__label"><?php esc_html_e( 'Pick up where you left off', 'ner-michoel-child' ); ?></p>
					<div class="st-feature__body">
						<div class="st-feature__text">
							<a class="st-feature__title" href="<?php echo esc_url( $resume['link'] ); ?>"><?php echo esc_html( $resume['title'] ); ?></a>
							<span class="st-feature__meta"><?php echo esc_html( $data['continue']['series'] ); ?></span>
						</div>
						<?php ner_michoel_studio_play_button( $resume['track'], $resume['queue_url'], $resume['title'], 'st-play st-play--feature' ); ?>
					</div>
				</div>
			<?php elseif ( $data['seasons'] ) : ?>
				<?php $season = $data['seasons'][0]; ?>
				<a class="st-feature st-feature--quiet st-feature--link" href="<?php echo esc_url( $season['link'] ); ?>">
					<p class="st-feature__label"><?php esc_html_e( 'In season', 'ner-michoel-child' ); ?></p>
					<span class="st-feature__title"><?php echo esc_html( $season['name'] ); ?></span>
					<span class="st-feature__meta"><?php echo esc_html( implode( ' · ', array_filter( array( $season['label'], ner_michoel_studio_count_label( $season['count'] ) ) ) ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</section>

	<?php
	// A signed-in listener's own shelf (inc/for-you.php).
	if ( function_exists( 'ner_michoel_render_for_you_studio' ) ) {
		ner_michoel_render_for_you_studio();
	}
	?>

	<?php foreach ( $data['seasons'] as $season ) : ?>
		<section class="st-season" aria-label="<?php echo esc_attr( $season['name'] ); ?>">
			<header class="st-season__head">
				<div>
					<p class="st-kicker st-kicker--gold"><?php esc_html_e( 'In season', 'ner-michoel-child' ); ?><?php echo $season['label'] ? ' · ' . esc_html( $season['label'] ) : ''; ?></p>
					<h2 class="st-season__title"><?php echo esc_html( $season['name'] ); ?></h2>
				</div>
				<a class="st-more" href="<?php echo esc_url( $season['link'] ); ?>">
					<?php echo esc_html( sprintf( /* translators: %s: number of shiurim, e.g. "12 shiurim" */ __( 'All %s', 'ner-michoel-child' ), ner_michoel_studio_count_label( $season['count'] ) ) ); ?>
					<span aria-hidden="true">&rarr;</span>
				</a>
			</header>
			<ul class="st-list st-list--grid">
				<?php foreach ( $season['posts'] as $season_post ) : ?>
					<?php ner_michoel_studio_shiur_row( ner_michoel_studio_shiur( $season_post ) ); ?>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>

	<div class="st-main">
		<section class="st-panel" aria-labelledby="st-new-title">
			<header class="st-panel__head">
				<div>
					<p class="st-kicker"><?php esc_html_e( 'Latest', 'ner-michoel-child' ); ?></p>
					<h2 class="st-panel__title" id="st-new-title"><?php esc_html_e( 'New shiurim', 'ner-michoel-child' ); ?></h2>
				</div>
				<a class="st-more" href="<?php echo esc_url( add_query_arg( 'sh_view', 'recent', $data['archive'] ) ); ?>"><?php esc_html_e( 'All recent', 'ner-michoel-child' ); ?> <span aria-hidden="true">&rarr;</span></a>
			</header>
			<?php if ( $data['recent'] ) : ?>
				<ul class="st-list">
					<?php foreach ( $data['recent'] as $row ) : ?>
						<?php ner_michoel_studio_shiur_row( $row ); ?>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="st-empty"><?php esc_html_e( 'No shiurim yet.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="st-panel" aria-labelledby="st-speakers-title">
			<header class="st-panel__head">
				<div>
					<p class="st-kicker"><?php esc_html_e( 'Rebbeim', 'ner-michoel-child' ); ?></p>
					<h2 class="st-panel__title" id="st-speakers-title"><?php esc_html_e( 'Speakers', 'ner-michoel-child' ); ?></h2>
				</div>
			</header>
			<?php if ( $data['speakers'] ) : ?>
				<ul class="st-list st-list--people">
					<?php foreach ( $data['speakers'] as $speaker ) : ?>
						<li class="st-row st-row--person">
							<div class="st-row__art">
								<?php ner_michoel_studio_art( $speaker['photo'], $speaker['initials'], $speaker['hue'], 'st-art st-art--person' ); ?>
								<?php ner_michoel_studio_play_button( $speaker['track'], $speaker['queue_url'], $speaker['name'], 'st-play st-play--row' ); ?>
							</div>
							<a class="st-row__link" href="<?php echo esc_url( is_wp_error( $speaker['link'] ) ? '#' : $speaker['link'] ); ?>">
								<span class="st-row__title"><?php echo esc_html( $speaker['name'] ); ?></span>
								<span class="st-row__meta"><span><?php echo esc_html( ner_michoel_studio_count_label( $speaker['count'] ) ); ?></span></span>
							</a>
							<span class="st-row__go" aria-hidden="true">&rarr;</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="st-empty"><?php esc_html_e( 'No speakers yet.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>
		</section>
	</div>

	<?php foreach ( $data['rows'] as $i => $row ) : ?>
		<section class="st-lane" aria-labelledby="st-lane-<?php echo (int) $i; ?>">
			<header class="st-lane__head">
				<h2 class="st-lane__title" id="st-lane-<?php echo (int) $i; ?>"><?php echo esc_html( $row['title'] ); ?></h2>
				<span class="st-lane__count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number of series, 2: number of shiurim, e.g. "120 shiurim" */
							_n( '%1$s series · %2$s', '%1$s series · %2$s', count( $row['cards'] ), 'ner-michoel-child' ),
							number_format_i18n( count( $row['cards'] ) ),
							ner_michoel_studio_count_label( $row['total'] )
						)
					);
					?>
				</span>
			</header>
			<div class="sh-carousel st-lane__rail">
				<button type="button" class="sh-carousel__nav sh-carousel__nav--prev" aria-label="<?php esc_attr_e( 'Scroll left', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'prev' ); ?></button>
				<div class="sh-carousel__track">
					<?php foreach ( $row['cards'] as $card ) : ?>
						<?php ner_michoel_studio_series_card( $card ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="sh-carousel__nav sh-carousel__nav--next" aria-label="<?php esc_attr_e( 'Scroll right', 'ner-michoel-child' ); ?>"><?php echo ner_michoel_icon( 'next' ); ?></button>
			</div>
		</section>
	<?php endforeach; ?>

	<?php if ( $data['topics'] ) : ?>
		<section class="st-topics" aria-labelledby="st-topics-title">
			<header class="st-lane__head">
				<h2 class="st-lane__title" id="st-topics-title"><?php esc_html_e( 'Browse by topic', 'ner-michoel-child' ); ?></h2>
			</header>
			<ul class="st-chips">
				<?php foreach ( $data['topics'] as $topic ) : ?>
					<li>
						<a class="st-chip" href="<?php echo esc_url( get_term_link( $topic ) ); ?>">
							<?php echo esc_html( $topic->name ); ?>
							<span class="st-chip__count"><?php echo esc_html( number_format_i18n( $topic->count ) ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! $latest && ! $data['rows'] ) : ?>
		<p class="st-empty"><?php esc_html_e( 'No shiurim yet. Add a speaker and a series, then upload the first shiur.', 'ner-michoel-child' ); ?></p>
	<?php endif; ?>
</div>
