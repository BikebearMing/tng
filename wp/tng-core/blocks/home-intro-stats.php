<?php
/**
 * acf/home-intro-stats — intro + counters, with the regional presence panel
 * nested inside the same section (its own toggle), exactly like the static DOM.
 * Location cards come from the Location taxonomy's country terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_intro_stats' ) ) {
	return;
}

$tng_bg       = tng_img_url( 'background_image', tng_asset( 'hp-location-bg.jpg' ) );
$tng_heading  = tng_field( 'heading', "Southeast Asia's Leading Law Firm" );
$tng_body     = tng_field( 'body', 'Tilleke & Gibbins is a leading full-service Southeast Asian law firm proudly serving international and local businesses across six countries. We are committed to helping our clients achieve sustainable success in the region, working with them to provide unparalleled legal expertise, rooted in practical commercial insight.' );
$tng_counters = tng_field( 'counters', array() );
if ( ! $tng_counters ) {
	$tng_counters = array(
		array( 'number' => '250+', 'label' => 'Lawyers' ),
		array( 'number' => '135', 'label' => 'Years of Service' ),
		array( 'number' => '50', 'label' => 'Firm of the Year Awards (2025)' ),
		array( 'number' => '7', 'label' => 'Offices' ),
	);
}

$tng_locations = get_terms( array( 'taxonomy' => 'location', 'hide_empty' => false, 'parent' => 0 ) );
$tng_locations = is_wp_error( $tng_locations ) ? array() : $tng_locations;
?>
<section class="hp-intro has-parallax" data-section="intro-stats" data-toggle="show_intro_stats">
	<img src="<?php echo esc_url( $tng_bg ); ?>" alt="" class="parallax-img">

	<div class="hp-intro__top">
		<div class="hp-intro__text">
			<h2 class="h2 dark"><?php echo esc_html( $tng_heading ); ?></h2>
			<p class="body dark"><?php echo esc_html( $tng_body ); ?></p>
			<a href="<?php echo esc_url( tng_link_or( tng_field( 'cta_url', '#' ), tng_page_url( 'about' ) ) ); ?>" class="button-label dark link-arrow hp-intro__link">
				<?php echo esc_html( tng_field( 'cta_label', 'Explore' ) ); ?>
				<?php echo tng_arrow( 1.5 ); ?>
			</a>
		</div>

		<ul class="hp-intro__counters">
			<?php foreach ( $tng_counters as $tng_row ) : ?>
			<li>
				<p class="counter dark"><?php echo esc_html( $tng_row['number'] ); ?></p>
				<p class="counter-label dark"><?php echo esc_html( $tng_row['label'] ); ?></p>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php if ( tng_show( 'show_regional_presence' ) ) : ?>
	<div class="hp-intro__panel" data-section="regional-presence" data-toggle="show_regional_presence">
		<img src="<?php echo esc_url( tng_img_url( 'rp_map_image', tng_asset( 'regional-presence.jpg' ) ) ); ?>" alt="" class="hp-intro__map">
		<h2 class="h2"><?php echo esc_html( tng_field( 'rp_heading', 'Our Regional Presence' ) ); ?></h2>

		<ul class="hp-intro__locations">
			<?php foreach ( $tng_locations as $tng_term ) : ?>
			<li class="hp-intro__location">
				<a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>">
					<img src="<?php echo esc_url( tng_location_image( $tng_term, 604, 368 ) ); ?>" alt="">
					<span class="hp-intro__location-name">
						<span class="h3"><?php echo esc_html( $tng_term->name ); ?></span>
						<span class="hp-featured-banner__icon" aria-hidden="true"><?php echo tng_arrow( 2 ); ?></span>
					</span>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
</section>
