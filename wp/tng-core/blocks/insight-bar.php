<?php
/**
 * tng/insight-bar — sticky reading bar (template chrome). Must stay a direct
 * child of <main> so position: sticky works.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
?>
<div class="in-bar" data-section="in-bar">
	<p class="h4 in-bar__title"><?php echo esc_html( get_the_title( $tng_id ) ); ?></p>
	<p class="subhead in-bar__date"><time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d', $tng_id ) ); ?>"><?php echo esc_html( tng_hero_date( $tng_id ) ); ?></time></p>
	<div class="in-bar__progress" aria-hidden="true"><span></span></div>
</div>
