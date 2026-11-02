<?php
/**
 * tng/pro-experience — WYSIWYG bullet list on the light grey band.
 * main.js caps it at data-cap items with a "Read more" toggle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_pro_experience', $tng_id ) ) {
	return;
}

$tng_experience = tng_field( 'experience', '', $tng_id );
if ( ! $tng_experience ) {
	return;
}
?>
<section class="pro-experience" id="experience" data-section="pro-experience" data-toggle="show_pro_experience">
	<p class="subhead pro-label">Experience</p>
	<div class="rich-text pro-experience__list" data-cap="4">
		<?php echo wp_kses_post( $tng_experience ); ?>
	</div>
</section>
