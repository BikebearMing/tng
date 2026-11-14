<?php
/**
 * acf/careers-cta — grey CTA panel: optional label over the heading,
 * optional line of text under it, then a link-arrow. Page modifiers
 * (lp-apply, rec-cta, desk-about) come from the block's Additional CSS class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers_cta' ) ) {
	return;
}

$tng_heading = tng_field( 'heading', 'Curious about our recruitment process?' );
$tng_label   = tng_field( 'label', '' );
$tng_text    = tng_field( 'text', '' );
$tng_url     = tng_field( 'link_url', '#' );
// Seeded on "#" before the Recruitment Process page existed
if ( '#' === $tng_url && false !== stripos( $tng_heading, 'recruitment' ) ) {
	$tng_careers = get_page_by_path( 'careers' );
	$tng_url     = tng_heal_url( $tng_url, 'Recruitment Process', $tng_careers ? $tng_careers->ID : 0 );
}
?>
<section class="careers-cta<?php echo tng_block_class( $block ); ?>" data-section="careers-cta" data-toggle="show_careers_cta">
	<div class="careers-cta__panel">
		<?php if ( $tng_label ) : ?>
		<p class="subhead"><?php echo esc_html( $tng_label ); ?></p>
		<?php endif; ?>
		<h2 class="h2 careers-cta__heading<?php echo $tng_label ? ' desk-about__heading' : ''; ?>"><?php echo esc_html( $tng_heading ); ?></h2>
		<?php if ( $tng_text ) : ?>
		<p class="body lp-apply__text"><?php echo esc_html( $tng_text ); ?></p>
		<?php endif; ?>
		<a href="<?php echo esc_url( $tng_url ); ?>" class="button-label link-arrow careers-cta__link">
			<?php echo esc_html( tng_field( 'link_label', 'Learn more' ) ); ?>
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
	</div>
</section>
