<?php
/**
 * acf/experience — the professional page's experience list on a page (the
 * desks): label + WYSIWYG bullet list, capped at 4 with "Read more" (main.js).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_experience' ) ) {
	return;
}

$tng_text = tng_field( 'text', '' );
if ( ! $tng_text ) {
	return;
}
?>
<section class="pro-experience<?php echo tng_block_class( $block ); ?>" id="experience" data-section="desk-experience" data-toggle="show_experience">
	<p class="subhead pro-label"><?php echo esc_html( tng_field( 'label', 'Experience' ) ); ?></p>
	<div class="rich-text pro-experience__list" data-cap="4">
		<?php echo wp_kses_post( $tng_text ); ?>
	</div>
</section>
