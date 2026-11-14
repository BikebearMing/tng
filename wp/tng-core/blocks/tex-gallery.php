<?php
/**
 * acf/tex-gallery — Textile Collection gallery: label + rule, 3-column grid of
 * square images, social circles centred under it. No images uploaded = the
 * nine bundled Figma photos; a social row is left out when its URL is empty.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_textile_gallery' ) ) {
	return;
}

$tng_images = array();
foreach ( (array) tng_field( 'images', array() ) as $tng_img ) {
	if ( is_array( $tng_img ) ) {
		$tng_img = isset( $tng_img['url'] ) ? $tng_img['url'] : '';
	} elseif ( is_numeric( $tng_img ) ) {
		$tng_img = wp_get_attachment_image_url( (int) $tng_img, 'large' );
	}
	if ( $tng_img ) {
		$tng_images[] = $tng_img;
	}
}
if ( ! $tng_images ) {
	for ( $tng_i = 1; $tng_i <= 9; $tng_i++ ) {
		$tng_images[] = tng_asset( 'img/about-textile-' . $tng_i . '.jpg' );
	}
}

$tng_icons = array(
	'instagram' => array( 'Instagram', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>' ),
	'facebook'  => array( 'Facebook', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H8v3h2.5V21h3z"/></svg>' ),
	'x'         => array( 'X', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.2-8.3L1.8 3h6.4l4.4 5.8L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z"/></svg>' ),
	'linkedin'  => array( 'LinkedIn', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.9 8.7H3.6V20h3.3V8.7zM5.3 3.5a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 13.1c0-3.2-1.7-4.7-4-4.7a3.4 3.4 0 0 0-3.1 1.7V8.7H10V20h3.3v-5.6c0-1.5.3-2.9 2.1-2.9 1.8 0 1.8 1.7 1.8 3V20h3.3v-6.9z"/></svg>' ),
	'youtube'   => array( 'YouTube', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8zM10 15V9l5.2 3L10 15z"/></svg>' ),
);
$tng_socials = array_values(
	array_filter(
		(array) tng_field( 'socials', array() ),
		static function ( $row ) use ( $tng_icons ) {
			return ! empty( $row['url'] ) && isset( $row['network'], $tng_icons[ $row['network'] ] );
		}
	)
);
// A row left on "#": the collection's own accounts (old site's Textile page)
$tng_tex_urls = array(
	'instagram' => 'https://www.instagram.com/southeast_asian_textile/',
	'facebook'  => 'https://www.facebook.com/Tgtextilecollection',
	'x'         => 'https://twitter.com/tillekegibbins',
	'linkedin'  => 'https://www.linkedin.com/company/tilleke-gibbins/',
);
?>
<section class="tex-gallery" id="gallery" data-section="textile-gallery" data-toggle="show_textile_gallery">
	<p class="subhead pro-label tex-gallery__label"><?php echo esc_html( tng_field( 'label', 'Tilleke & Gibbins’ Textile Collection' ) ); ?></p>
	<ul class="tex-gallery__grid">
		<?php foreach ( $tng_images as $tng_img ) : ?>
		<li><img src="<?php echo esc_url( $tng_img ); ?>" alt="" class="tex-gallery__image" loading="lazy"></li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $tng_socials ) : ?>
	<ul class="tex-gallery__social">
		<?php foreach ( $tng_socials as $tng_row ) : $tng_icon = $tng_icons[ $tng_row['network'] ]; ?>
		<li><a href="<?php echo esc_url( tng_link_or( $tng_row['url'], isset( $tng_tex_urls[ $tng_row['network'] ] ) ? $tng_tex_urls[ $tng_row['network'] ] : tng_default_social( $tng_row['network'] ) ) ); ?>" aria-label="<?php echo esc_attr( $tng_icon[0] ); ?>"><?php echo $tng_icon[1]; // phpcs:ignore ?></a></li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
</section>
