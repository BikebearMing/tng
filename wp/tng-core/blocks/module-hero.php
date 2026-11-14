<?php
/**
 * tng/module-hero — Module template hero: cover image, lead, report card.
 * No reading bar on this template (main.js skips the progress when .in-bar
 * is absent).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_in_hero_module', $tng_id ) ) {
	return;
}

$tng_bg = tng_img_url( 'module_background', '', $tng_id );
if ( ! $tng_bg ) {
	$tng_bg = get_the_post_thumbnail_url( $tng_id, 'full' );
}
if ( ! $tng_bg ) {
	$tng_bg = tng_asset( 'module-bg.jpg' );
}

$tng_lead   = tng_field( 'module_lead', '', $tng_id );
$tng_report = tng_field( 'module_report', '', $tng_id );
if ( is_array( $tng_report ) ) {
	$tng_report = $tng_report['url'];
}
?>
<section class="in-hero in-hero--module" data-section="in-hero-module" data-toggle="show_in_hero_module">
	<img src="<?php echo esc_url( $tng_bg ); ?>" alt="" class="in-hero__bg">

	<?php echo tng_breadcrumbs( get_the_title( $tng_id ), array( array( 'Insights', tng_page_url( 'insights' ) ) ) ); ?>

	<div class="in-hero__intro">
		<h1 class="h1 dark"><?php echo esc_html( get_the_title( $tng_id ) ); ?></h1>
		<p class="subhead in-hero__date"><time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d', $tng_id ) ); ?>"><?php echo esc_html( tng_hero_date( $tng_id ) ); ?></time></p>
	</div>

	<?php if ( $tng_lead ) : ?>
	<p class="h3 dark in-hero__lead"><?php echo esc_html( $tng_lead ); ?></p>
	<?php endif; ?>

	<?php if ( $tng_report ) : ?>
	<a href="<?php echo esc_url( $tng_report ); ?>" class="in-hero__report" download>
		<span class="h3"><?php echo esc_html( tng_field( 'module_report_label', 'Download report', $tng_id ) ); ?></span>
		<?php echo tng_arrow_circle( true ); ?>
	</a>
	<?php endif; ?>
</section>
