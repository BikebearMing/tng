<?php
/**
 * acf/about-values — accordion (one item open at a time, main.js) + image.
 * The first item starts open.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_about_values' ) ) {
	return;
}

$tng_items = array_values(
	array_filter(
		(array) tng_field( 'items', array() ),
		static function ( $row ) {
			return ! empty( $row['title'] );
		}
	)
);
if ( ! $tng_items ) {
	return;
}
?>
<section class="about-values" data-section="about-values" data-toggle="show_about_values">
	<div class="about-acc">
		<?php
		foreach ( $tng_items as $tng_i => $tng_item ) :
			$tng_open = 0 === $tng_i;
			$tng_pid  = 'about-acc-' . sanitize_title( $tng_item['title'] ) . '-' . $tng_i;
			?>
		<div class="about-acc__item<?php echo $tng_open ? ' is-open' : ''; ?>">
			<h3 class="about-acc__heading"><button type="button" class="h3 about-acc__btn" aria-expanded="<?php echo $tng_open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $tng_pid ); ?>"><?php echo esc_html( $tng_item['title'] ); ?><span class="about-acc__icon" aria-hidden="true"></span></button></h3>
			<div class="about-acc__panel" id="<?php echo esc_attr( $tng_pid ); ?>">
				<div class="about-acc__inner">
					<div class="rich-text about-acc__text"><?php echo wp_kses_post( isset( $tng_item['text'] ) ? $tng_item['text'] : '' ); ?></div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<img src="<?php echo esc_url( tng_img_url( 'image', tng_bundled_img( 'about-values.jpg' ) ) ); ?>" alt="" class="about-values__image" loading="lazy">
</section>
