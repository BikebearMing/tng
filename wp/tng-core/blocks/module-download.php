<?php
/**
 * tng/module-download — download banner. Uses the hero's report file; hidden
 * when there is none. Image falls back to the hero background.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_module_download', $tng_id ) ) {
	return;
}

$tng_report = tng_field( 'module_report', '', $tng_id );
if ( is_array( $tng_report ) ) {
	$tng_report = $tng_report['url'];
}
if ( ! $tng_report ) {
	return;
}

$tng_image = tng_img_url( 'download_image', '', $tng_id );
if ( ! $tng_image ) {
	$tng_image = tng_img_url( 'module_background', tng_asset( 'module-bg.jpg' ), $tng_id );
}
?>
<section class="in-download" data-section="in-download" data-toggle="show_module_download">
	<a href="<?php echo esc_url( $tng_report ); ?>" class="in-download__link" download>
		<span class="h2 dark"><?php echo esc_html( tng_field( 'download_label', 'Download report', $tng_id ) ); ?></span>
		<?php echo tng_arrow_circle( true, 'in-download__icon' ); ?>
	</a>
	<img src="<?php echo esc_url( $tng_image ); ?>" alt="" class="in-download__image" loading="lazy">
</section>
