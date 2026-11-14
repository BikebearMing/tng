<?php
/**
 * tng/module-download — download banner. Uses the hero's report file; hidden
 * when there is none. Image falls back to the hero background. Also closes the
 * Standard template, where imported articles keep their "Download Full
 * Article" PDF in the same field (label default "Download full article").
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

// Two imported articles keep an image here (the old button pointed at a table JPG): no "download" claim for those.
$tng_is_image = (bool) preg_match( '/\.(jpe?g|png|gif|webp)$/i', (string) wp_parse_url( $tng_report, PHP_URL_PATH ) );

// Stored label, else the template's default (read raw). Until 1.3.15 the field's
// default "Download report" was saved on every Standard insight edited in wp-admin:
// on Standard that exact text counts as empty.
$tng_module = 'single-insight-module' === get_page_template_slug( $tng_id );
$tng_label  = (string) get_post_meta( $tng_id, 'download_label', true );
if ( '' === $tng_label || ( ! $tng_module && 'Download report' === $tng_label ) ) {
	$tng_label = $tng_is_image ? 'View full image' : ( $tng_module ? 'Download report' : 'Download full article' );
}

$tng_image = tng_img_url( 'download_image', '', $tng_id );
if ( ! $tng_image ) {
	$tng_image = tng_img_url( 'module_background', tng_asset( 'module-bg.jpg' ), $tng_id );
}
?>
<section class="in-download" data-section="in-download" data-toggle="show_module_download">
	<a href="<?php echo esc_url( $tng_report ); ?>" class="in-download__link"<?php echo $tng_is_image ? '' : ' download'; ?>>
		<span class="h2 dark"><?php echo esc_html( $tng_label ); ?></span>
		<?php echo tng_arrow_circle( true, 'in-download__icon' ); ?>
	</a>
	<img src="<?php echo esc_url( $tng_image ); ?>" alt="" class="in-download__image" loading="lazy">
</section>
