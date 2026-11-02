<?php
/**
 * acf/careers-people — label + heading + WYSIWYG text | image.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers_people' ) ) {
	return;
}

$tng_text = tng_field( 'text', '' );
?>
<section class="careers-people" data-section="careers-people" data-toggle="show_careers_people">
	<p class="subhead pro-label"><?php echo esc_html( tng_field( 'label', 'Career Development' ) ); ?></p>
	<h2 class="h2 careers-people__heading"><?php echo esc_html( tng_field( 'heading', 'We value people for who they are.' ) ); ?></h2>

	<div class="careers-people__body">
		<div class="rich-text careers-people__text">
			<?php echo $tng_text ? wp_kses_post( $tng_text ) : '<p>We can only achieve great things together if everyone is welcome.</p>'; ?>
		</div>
		<div class="careers-people__media">
			<img src="<?php echo esc_url( tng_img_url( 'image', 'https://picsum.photos/seed/tng-people/1410/1386' ) ); ?>" alt="" class="careers-people__image" loading="lazy">
		</div>
	</div>
</section>
