<?php
/**
 * tng/module-panels — text cards over the parallax texture. Panels repeater:
 * heading, wysiwyg body, light/dark theme, optional link.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_module_panels', $tng_id ) ) {
	return;
}

$tng_panels = tng_field( 'panels', array(), $tng_id );
if ( ! $tng_panels ) {
	return;
}

$tng_bg = tng_img_url( 'panels_background', tng_asset( 'hp-location-bg.jpg' ), $tng_id );
?>
<section class="in-panels has-parallax" data-section="in-panels" data-toggle="show_module_panels">
	<img src="<?php echo esc_url( $tng_bg ); ?>" alt="" class="parallax-img">

	<div class="in-panels__list">
		<?php
		foreach ( $tng_panels as $tng_panel ) :
			$tng_dark = isset( $tng_panel['theme'] ) && 'dark' === $tng_panel['theme'];
			?>
		<div class="in-panel<?php echo $tng_dark ? ' in-panel--dark' : ''; ?>">
			<h2 class="h2<?php echo $tng_dark ? ' dark' : ''; ?> in-panel__heading"><?php echo wp_kses( nl2br( $tng_panel['heading'] ), array( 'br' => array() ) ); ?></h2>
			<div class="in-panel__body">
				<div class="rich-text">
					<?php echo wp_kses_post( $tng_panel['body'] ); ?>
				</div>
				<?php if ( ! empty( $tng_panel['link_label'] ) ) : ?>
				<a href="<?php echo esc_url( ! empty( $tng_panel['link_url'] ) ? $tng_panel['link_url'] : '#' ); ?>" class="button-label<?php echo $tng_dark ? ' dark' : ''; ?> link-arrow in-panel__link" download>
					<?php echo esc_html( $tng_panel['link_label'] ); ?>
					<?php echo tng_arrow( 1.5 ); ?>
				</a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</section>
