<?php
/**
 * tng/module-share — centred share row (buttons wired in main.js).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_module_share', $tng_id ) ) {
	return;
}
?>
<section class="in-share-row" data-section="in-share-row" data-toggle="show_module_share">
	<h2 class="h3 in-share-row__heading"><?php echo esc_html( tng_field( 'share_heading', 'Share the insight', $tng_id ) ); ?></h2>
	<div class="in-share">
		<?php echo tng_share_buttons(); // phpcs:ignore ?>
	</div>
</section>
