<?php
/**
 * acf/about-vision — big tab buttons (Vision / Mission) swapping text + image.
 * ARIA tabs, driven by main.js's shared tabs handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_about_vision' ) ) {
	return;
}

$tng_items = array_values(
	array_filter(
		(array) tng_field( 'items', array() ),
		static function ( $row ) {
			return ! empty( $row['label'] );
		}
	)
);
if ( ! $tng_items ) {
	return;
}
$tng_slug = static function ( $item, $i ) {
	return sanitize_title( $item['label'] ) . '-' . $i;
};
?>
<section class="about-vision" id="vision" data-section="about-vision" data-toggle="show_about_vision">
	<ul class="about-vision__tabs" role="tablist" aria-label="Vision and mission">
		<?php foreach ( $tng_items as $tng_i => $tng_item ) : $tng_s = $tng_slug( $tng_item, $tng_i ); ?>
		<li><button type="button" class="h1 about-vision__tab<?php echo 0 === $tng_i ? ' is-active' : ''; ?>" role="tab" id="about-vision-tab-<?php echo esc_attr( $tng_s ); ?>" aria-controls="about-vision-<?php echo esc_attr( $tng_s ); ?>" aria-selected="<?php echo 0 === $tng_i ? 'true' : 'false'; ?>"<?php echo 0 === $tng_i ? '' : ' tabindex="-1"'; ?>><?php echo esc_html( $tng_item['label'] ); ?><?php echo tng_arrow_circle( true ); ?></button></li>
		<?php endforeach; ?>
	</ul>
	<div class="about-vision__panels">
		<?php foreach ( $tng_items as $tng_i => $tng_item ) : $tng_s = $tng_slug( $tng_item, $tng_i ); ?>
		<div class="about-vision__panel" id="about-vision-<?php echo esc_attr( $tng_s ); ?>" role="tabpanel" aria-labelledby="about-vision-tab-<?php echo esc_attr( $tng_s ); ?>"<?php echo 0 === $tng_i ? '' : ' hidden'; ?>>
			<p class="h3 about-vision__text"><?php echo esc_html( isset( $tng_item['text'] ) ? $tng_item['text'] : '' ); ?></p>
			<img src="<?php echo esc_url( tng_img_value( isset( $tng_item['image'] ) ? $tng_item['image'] : '', tng_bundled_img( array( 'about-' . $tng_s . '.jpg', 'about-vision.jpg' ) ) ) ); ?>" alt="" class="about-vision__image" loading="lazy">
		</div>
		<?php endforeach; ?>
	</div>
</section>
