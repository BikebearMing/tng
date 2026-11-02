<?php
/**
 * acf/careers-cta — grey CTA panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers_cta' ) ) {
	return;
}
?>
<section class="careers-cta" data-section="careers-cta" data-toggle="show_careers_cta">
	<div class="careers-cta__panel">
		<h2 class="h2 careers-cta__heading"><?php echo esc_html( tng_field( 'heading', 'Curious about our recruitment process?' ) ); ?></h2>
		<a href="<?php echo esc_url( tng_field( 'link_url', '#' ) ); ?>" class="button-label link-arrow careers-cta__link">
			<?php echo esc_html( tng_field( 'link_label', 'Learn more' ) ); ?>
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
	</div>
</section>
