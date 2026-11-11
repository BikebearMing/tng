<?php
/**
 * acf/careers-people — label + heading + WYSIWYG text | image.
 * Variant "path" (career path pages): 99 top, narrower text (.lp-people).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers_people' ) ) {
	return;
}

$tng_text = tng_field( 'text', '' );
$tng_path = 'path' === tng_field( 'variant', 'default' );
?>
<section class="careers-people<?php echo $tng_path ? ' lp-people' : ''; ?>" data-section="careers-people" data-toggle="show_careers_people">
	<p class="subhead pro-label"><?php echo esc_html( tng_field( 'label', 'Career Development' ) ); ?></p>
	<h2 class="h2 careers-people__heading"><?php echo wp_kses( tng_field( 'heading', 'We value people for who they are.' ), array( 'br' => array() ) ); ?></h2>

	<div class="careers-people__body">
		<div class="rich-text careers-people__text">
			<?php echo $tng_text ? wp_kses_post( $tng_text ) : '<p>We can only achieve great things together if everyone is welcome.</p>'; ?>
		</div>
		<div class="careers-people__media">
			<img src="<?php echo esc_url( tng_img_url( 'image', tng_bundled_img( 'careers-people.jpg' ) ) ); ?>" alt="" class="careers-people__image" loading="lazy">
		</div>
	</div>
</section>
