<?php
/**
 * acf/home-careers — careers strip with the two-column portrait marquee.
 * Portraits repeater is split alternately into the up/down columns; main.js
 * clones the items for the seamless loop.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers' ) ) {
	return;
}

$tng_portraits = array();
foreach ( (array) tng_field( 'portraits', array() ) as $tng_row ) {
	$tng_src = isset( $tng_row['image'] ) ? $tng_row['image'] : '';
	if ( is_array( $tng_src ) ) {
		$tng_src = $tng_src['url'];
	}
	if ( $tng_src ) {
		$tng_portraits[] = $tng_src;
	}
}
if ( ! $tng_portraits ) {
	$tng_portraits = array( tng_asset( 'guy-1.jpg' ), tng_asset( 'lady-2.jpg' ) );
}
// Alternate into two columns; the down column starts one step later so
// neighbouring rows differ, like the static page.
$tng_col_up   = array();
$tng_col_down = array();
foreach ( $tng_portraits as $tng_i => $tng_src ) {
	if ( $tng_i % 2 ) {
		$tng_col_down[] = $tng_src;
	} else {
		$tng_col_up[] = $tng_src;
	}
}
if ( ! $tng_col_down ) {
	$tng_col_down = array_reverse( $tng_col_up );
}
if ( count( $tng_col_up ) < 2 ) {
	$tng_col_up = array_merge( $tng_col_up, $tng_col_down );
}
if ( count( $tng_col_down ) < 2 ) {
	$tng_col_down = array_merge( $tng_col_down, array_reverse( $tng_col_up ) );
}
?>
<section class="hp-careers" data-section="careers" data-toggle="show_careers">
	<div class="hp-careers__intro">
		<h2 class="h2 dark"><?php echo esc_html( tng_field( 'heading', 'Build your career with Tilleke & Gibbins' ) ); ?></h2>
		<a href="<?php echo esc_url( tng_field( 'cta_url', '#' ) ); ?>" class="button-label dark link-arrow">
			<?php echo esc_html( tng_field( 'cta_label', 'Explore opportunities' ) ); ?>
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
	</div>

	<div class="hp-careers__foot">
		<div class="hp-careers__foot-row">
			<p class="subhead hp-careers__note"><?php echo esc_html( tng_field( 'note', "We're always looking for great people to join our team" ) ); ?></p>
			<span class="hp-careers__rule" aria-hidden="true"></span>
			<img src="<?php echo esc_url( tng_img_url( 'badge_image', tng_asset( 'ALB-Badge-2025-Employer-of-Choice 1.png' ) ) ); ?>" alt="ALB Employer of Choice 2026" class="hp-careers__badge">
		</div>
	</div>

	<div class="hp-careers__marquee" aria-hidden="true">
		<div class="hp-careers__column" data-direction="up">
			<?php foreach ( $tng_col_up as $tng_src ) : ?>
			<img src="<?php echo esc_url( $tng_src ); ?>" alt="">
			<?php endforeach; ?>
		</div>
		<div class="hp-careers__column" data-direction="down">
			<?php foreach ( $tng_col_down as $tng_src ) : ?>
			<img src="<?php echo esc_url( $tng_src ); ?>" alt="">
			<?php endforeach; ?>
		</div>
	</div>
</section>
