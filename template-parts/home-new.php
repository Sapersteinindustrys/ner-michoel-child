<?php
/**
 * The new homepage design (front-page.php shows it when inc/home.php says
 * so): a hero with the shiur search and the homepage slider's photos, then
 * the newest shiurim beside the latest Mazal Tovs, then the rest of the site.
 *
 * Light and self-contained (.hn-*, assets/css/home.css), like the current
 * homepage: it doesn't use the dark Shiurim app styles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home   = ner_michoel_home_new_data();
$urls   = $home['urls'];
$counts = $home['counts'];
$newest = $home['shiurim'] ? $home['shiurim'][0] : null;

$sparkle = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2.5l1.9 5.6 5.6 1.9-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.9L12 2.5z"/><path d="M19 15.5l.8 2.2 2.2.8-2.2.8L19 21.5l-.8-2.2-2.2-.8 2.2-.8.8-2.2z" opacity=".7"/></svg>';
?>

<div class="hn">

	<section class="hn-hero" aria-labelledby="hn-hero-title">
		<?php ner_michoel_render_home_switch(); ?>
		<div class="hn-hero__copy">
			<p class="hn-eyebrow"><?php esc_html_e( 'Yeshivas Toras Moshe Alumni Association', 'ner-michoel-child' ); ?></p>
			<h1 class="hn-hero__title" id="hn-hero-title"><?php esc_html_e( 'Welcome to Ner Michoel', 'ner-michoel-child' ); ?></h1>
			<p class="hn-hero__lede"><?php esc_html_e( 'Thousands of shiurim, community updates, and a way to stay close to the Yeshiva and each other — wherever you are.', 'ner-michoel-child' ); ?></p>

			<?php if ( $urls['shiurim'] ) : ?>
				<form class="hn-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<input type="hidden" name="post_type" value="shiur" />
					<label class="screen-reader-text" for="hn-search-input"><?php esc_html_e( 'Search shiurim', 'ner-michoel-child' ); ?></label>
					<span class="hn-search__icon" aria-hidden="true"><?php echo ner_michoel_line_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
					<input class="hn-search__input" id="hn-search-input" type="search" name="s" placeholder="<?php esc_attr_e( 'Search shiurim, speakers or series…', 'ner-michoel-child' ); ?>" autocomplete="off" />
					<button class="hn-search__go" type="submit"><?php esc_html_e( 'Search', 'ner-michoel-child' ); ?></button>
				</form>
			<?php endif; ?>

			<?php
			$stats = array_filter(
				array(
					array( $counts['shiur'], _n( 'shiur', 'shiurim', $counts['shiur'], 'ner-michoel-child' ) ),
					array( $counts['written_shiur'], __( 'written', 'ner-michoel-child' ) ),
					array( $counts['speaker'], _n( 'speaker', 'speakers', $counts['speaker'], 'ner-michoel-child' ) ),
				),
				function ( $stat ) {
					return $stat[0] > 0;
				}
			);
			?>
			<?php if ( $stats ) : ?>
				<ul class="hn-stats">
					<?php foreach ( $stats as $stat ) : ?>
						<li><strong><?php echo esc_html( number_format_i18n( $stat[0] ) ); ?></strong> <?php echo esc_html( $stat[1] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="hn-hero__media" data-hn-photos data-interval="<?php echo esc_attr( $home['interval'] ); ?>">
			<?php if ( $home['slides'] ) : ?>
				<?php
				// The slide's own URL only, as the current slider uses it. Its srcset
				// lists the uploads folder's copies, and with the media on the CDN
				// those are gone, so a browser that picked one showed a broken image.
				foreach ( $home['slides'] as $index => $slide ) :
					?>
					<?php if ( 0 === $index ) : ?>
						<img class="hn-hero__photo is-active" src="<?php echo esc_url( $slide['url'] ); ?>" alt="" decoding="async" fetchpriority="high" />
					<?php else : ?>
						<?php // Fetched by home.js just before it fades in, so the page doesn't load every photo up front. ?>
						<img class="hn-hero__photo" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="<?php echo esc_url( $slide['url'] ); ?>" alt="" decoding="async" />
					<?php endif; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="hn-hero__art" aria-hidden="true"><span lang="he" dir="rtl">נר מיכאל</span></div>
			<?php endif; ?>

			<?php if ( $newest ) : ?>
				<a class="hn-now" href="<?php echo esc_url( $newest['link'] ); ?>">
					<span class="hn-now__play" aria-hidden="true"><?php echo ner_michoel_icon( $newest['is_video'] ? 'video' : 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
					<span class="hn-now__text">
						<span class="hn-now__label"><?php esc_html_e( 'Newest shiur', 'ner-michoel-child' ); ?></span>
						<span class="hn-now__title"><?php echo esc_html( $newest['title'] ); ?></span>
						<?php if ( $newest['speaker'] ) : ?>
							<span class="hn-now__meta"><?php echo esc_html( $newest['speaker'] ); ?></span>
						<?php endif; ?>
					</span>
				</a>
			<?php endif; ?>
		</div>
	</section>

	<?php get_template_part( 'template-parts/live-shiur' ); ?>

	<?php
	// A signed-in listener's own shelf (inc/for-you.php).
	if ( function_exists( 'ner_michoel_render_for_you_home_new' ) ) {
		ner_michoel_render_for_you_home_new();
	}
	?>

	<div class="hn-main">

		<section class="hn-panel hn-feed" aria-labelledby="hn-feed-title">
			<header class="hn-panel__head">
				<div class="hn-panel__titles">
					<p class="hn-kicker"><?php esc_html_e( 'Just added', 'ner-michoel-child' ); ?></p>
					<h2 class="hn-panel__title" id="hn-feed-title"><?php esc_html_e( 'New Shiurim', 'ner-michoel-child' ); ?></h2>
				</div>
				<?php if ( $home['shiurim'] && $home['written'] ) : ?>
					<div class="hn-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Which shiurim', 'ner-michoel-child' ); ?>" data-hn-tabs>
						<button type="button" class="hn-tab is-active" role="tab" id="hn-tab-listen" aria-selected="true" aria-controls="hn-list-listen"><?php esc_html_e( 'Audio & video', 'ner-michoel-child' ); ?></button>
						<button type="button" class="hn-tab" role="tab" id="hn-tab-read" aria-selected="false" aria-controls="hn-list-read" tabindex="-1"><?php esc_html_e( 'Written', 'ner-michoel-child' ); ?></button>
					</div>
				<?php endif; ?>
			</header>

			<?php if ( $home['shiurim'] ) : ?>
				<ol class="hn-list" id="hn-list-listen"<?php echo $home['written'] ? ' role="tabpanel" aria-labelledby="hn-tab-listen"' : ''; ?>>
					<?php foreach ( $home['shiurim'] as $row ) : ?>
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
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<?php if ( $home['written'] ) : ?>
				<ol class="hn-list hn-list--written" id="hn-list-read"<?php echo $home['shiurim'] ? ' role="tabpanel" aria-labelledby="hn-tab-read" hidden' : ''; ?>>
					<?php foreach ( $home['written'] as $row ) : ?>
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
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<?php if ( ! $home['shiurim'] && ! $home['written'] ) : ?>
				<p class="hn-empty"><?php esc_html_e( 'New shiurim will appear here as they are added.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>

			<footer class="hn-panel__foot">
				<?php if ( $urls['shiurim'] ) : ?>
					<a class="hn-more" href="<?php echo esc_url( $urls['shiurim'] ); ?>" data-hn-more-listen><?php esc_html_e( 'Browse all shiurim', 'ner-michoel-child' ); ?> <?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
				<?php endif; ?>
				<?php if ( $urls['written'] && $home['written'] ) : ?>
					<a class="hn-more" href="<?php echo esc_url( $urls['written'] ); ?>" data-hn-more-read<?php echo $home['shiurim'] ? ' hidden' : ''; ?>><?php esc_html_e( 'All written shiurim', 'ner-michoel-child' ); ?> <?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
				<?php endif; ?>
			</footer>
		</section>

		<aside class="hn-panel hn-mazal" aria-labelledby="hn-mazal-title">
			<header class="hn-mazal__head">
				<span class="hn-mazal__mark" aria-hidden="true"><?php echo $sparkle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
				<p class="hn-kicker hn-kicker--gold"><?php esc_html_e( 'Simchas', 'ner-michoel-child' ); ?></p>
				<h2 class="hn-panel__title" id="hn-mazal-title"><?php esc_html_e( 'Mazal Tov', 'ner-michoel-child' ); ?></h2>
				<p class="hn-mazal__lede"><?php esc_html_e( 'Celebrating with the Ner Michoel family.', 'ner-michoel-child' ); ?></p>
			</header>

			<?php if ( $home['mazal'] ) : ?>
				<ul class="hn-mazal__list">
					<?php foreach ( $home['mazal'] as $mazal ) : ?>
						<li class="hn-mt<?php echo $mazal['type_slug'] ? ' hn-mt--' . esc_attr( $mazal['type_slug'] ) : ''; ?>">
							<span class="hn-mt__badge" aria-hidden="true">
								<?php if ( $mazal['photo'] ) : ?>
									<img src="<?php echo esc_url( $mazal['photo'] ); ?>" alt="" loading="lazy" decoding="async" width="48" height="48" />
								<?php else : ?>
									<?php echo $sparkle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
								<?php endif; ?>
							</span>
							<div class="hn-mt__body">
								<?php if ( $mazal['type'] ) : ?>
									<p class="hn-mt__type"><?php echo esc_html( $mazal['type'] ); ?></p>
								<?php endif; ?>
								<p class="hn-mt__name">
									<?php if ( $mazal['relationship'] ) : ?>
										<span class="hn-mt__rel"><?php echo esc_html( $mazal['relationship'] ); ?></span>
									<?php endif; ?>
									<?php echo esc_html( $mazal['name'] ); ?>
								</p>
								<?php if ( $mazal['years'] || $mazal['when'] ) : ?>
									<p class="hn-mt__meta">
										<?php if ( $mazal['years'] ) : ?>
											<span><?php echo esc_html( $mazal['years'] ); ?></span>
										<?php endif; ?>
										<?php if ( $mazal['when'] ) : ?>
											<time datetime="<?php echo esc_attr( $mazal['iso'] ); ?>"><?php echo esc_html( $mazal['when'] ); ?></time>
										<?php endif; ?>
									</p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="hn-mazal__empty"><?php esc_html_e( 'Engagements, weddings, births and bar mitzvahs from our alumni families will be shared here.', 'ner-michoel-child' ); ?></p>
			<?php endif; ?>

			<footer class="hn-mazal__foot">
				<p class="hn-mazal__ask"><?php esc_html_e( 'Have a simcha to share?', 'ner-michoel-child' ); ?></p>
				<div class="hn-mazal__actions">
					<a class="hn-btn hn-btn--gold" href="<?php echo esc_url( $urls['contact'] ); ?>"><?php esc_html_e( 'Send us your news', 'ner-michoel-child' ); ?></a>
					<?php if ( $home['mazal'] && $urls['mazal'] ) : ?>
						<a class="hn-more" href="<?php echo esc_url( $urls['mazal'] ); ?>"><?php esc_html_e( 'All announcements', 'ner-michoel-child' ); ?> <?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
					<?php endif; ?>
				</div>
				<?php if ( $urls['post_mazal'] ) : ?>
					<a class="hn-mazal__admin" href="<?php echo esc_url( $urls['post_mazal'] ); ?>">+ <?php esc_html_e( 'Post a Mazal Tov', 'ner-michoel-child' ); ?></a>
				<?php endif; ?>
			</footer>
		</aside>

	</div>

	<?php
	$tiles = array(
		array( 'headphones', __( 'Shiurim', 'ner-michoel-child' ), __( 'Audio and video lectures', 'ner-michoel-child' ), $urls['shiurim'] ),
		array( 'book-open', __( 'Written Shiurim', 'ner-michoel-child' ), __( 'Divrei Torah to read and print', 'ner-michoel-child' ), $urls['written'] ),
		array( 'image', __( 'Galleries', 'ner-michoel-child' ), __( 'Photos and videos from events', 'ner-michoel-child' ), $urls['galleries'] ),
		array( 'newspaper', __( 'Mazal Tov', 'ner-michoel-child' ), __( 'Simchas from our alumni families', 'ner-michoel-child' ), $urls['mazal'] ),
		array( 'heart', __( 'Contribute', 'ner-michoel-child' ), __( 'Support the Yeshiva', 'ner-michoel-child' ), $urls['contribute'] ),
		array( 'mail', __( 'Contact', 'ner-michoel-child' ), __( 'We would love to hear from you', 'ner-michoel-child' ), $urls['contact'] ),
	);
	?>
	<nav class="hn-explore" aria-label="<?php esc_attr_e( 'Explore the site', 'ner-michoel-child' ); ?>">
		<?php foreach ( $tiles as $tile ) : ?>
			<?php
			if ( ! $tile[3] ) {
				continue;
			}
			?>
			<a class="hn-tile" href="<?php echo esc_url( $tile[3] ); ?>">
				<span class="hn-tile__icon" aria-hidden="true"><?php echo ner_michoel_line_icon( $tile[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
				<span class="hn-tile__text">
					<span class="hn-tile__title"><?php echo esc_html( $tile[1] ); ?></span>
					<span class="hn-tile__desc"><?php echo esc_html( $tile[2] ); ?></span>
				</span>
				<span class="hn-tile__go" aria-hidden="true"><?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $home['galleries'] ) : ?>
		<section class="hn-gallery" aria-labelledby="hn-gallery-title">
			<header class="hn-section-head">
				<div>
					<p class="hn-kicker"><?php esc_html_e( 'Galleries', 'ner-michoel-child' ); ?></p>
					<h2 class="hn-section-head__title" id="hn-gallery-title"><?php esc_html_e( 'From our events', 'ner-michoel-child' ); ?></h2>
				</div>
				<?php if ( $urls['galleries'] ) : ?>
					<a class="hn-more" href="<?php echo esc_url( $urls['galleries'] ); ?>"><?php esc_html_e( 'See all', 'ner-michoel-child' ); ?> <?php echo ner_michoel_line_icon( 'arrow-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?></a>
				<?php endif; ?>
			</header>
			<div class="hn-gallery__grid">
				<?php foreach ( $home['galleries'] as $gallery ) : ?>
					<a class="hn-shot" href="<?php echo esc_url( $gallery['link'] ); ?>">
						<?php if ( $gallery['cover'] ) : ?>
							<img src="<?php echo esc_url( $gallery['cover'] ); ?>" alt="" loading="lazy" decoding="async" />
						<?php else : ?>
							<?php echo ner_michoel_art_placeholder( 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
						<?php endif; ?>
						<span class="hn-shot__caption">
							<span class="hn-shot__title"><?php echo esc_html( $gallery['title'] ); ?></span>
							<?php if ( $gallery['meta'] ) : ?>
								<span class="hn-shot__meta"><?php echo esc_html( $gallery['meta'] ); ?></span>
							<?php endif; ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="hn-cta" aria-labelledby="hn-cta-title">
		<div class="hn-cta__copy">
			<h2 class="hn-cta__title" id="hn-cta-title"><?php esc_html_e( 'Stay Connected', 'ner-michoel-child' ); ?></h2>
			<p><?php esc_html_e( 'Update your contact info, join the alumni WhatsApp group, or reach out any time — we\'d love to hear how you\'re doing.', 'ner-michoel-child' ); ?></p>
		</div>
		<div class="hn-cta__actions">
			<a class="hn-btn hn-btn--light" href="<?php echo esc_url( $urls['contact'] ); ?>"><?php esc_html_e( 'Get in Touch', 'ner-michoel-child' ); ?></a>
			<a class="hn-btn hn-btn--ghost" href="<?php echo esc_url( $urls['contribute'] ); ?>"><?php esc_html_e( 'Contribute', 'ner-michoel-child' ); ?></a>
		</div>
	</section>

</div>
