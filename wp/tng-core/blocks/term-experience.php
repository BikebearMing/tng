<?php
/**
 * tng/term-experience — the professional page's experience block on term
 * pages (label "Representative Matters" on a subpractice). main.js caps it
 * at data-cap items with a "Read more" toggle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'experience' ) ) {
	return;
}

$tng_prefix = tng_term_prefix( $tng_term );
?>
<section class="pro-experience" id="experience" data-section="<?php echo esc_attr( $tng_prefix ); ?>-experience" data-toggle="show_<?php echo esc_attr( $tng_prefix ); ?>_experience">
	<p class="subhead pro-label"><?php echo esc_html( tng_term_experience_label( $tng_term ) ); ?></p>
	<div class="rich-text pro-experience__list" data-cap="4">
		<?php echo wp_kses_post( tng_field( 'experience', '', tng_term_ref( $tng_term ) ) ); ?>
	</div>
</section>
