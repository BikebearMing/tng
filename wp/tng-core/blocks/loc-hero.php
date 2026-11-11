<?php
/**
 * tng/loc-hero — location (country) page hero: the insight hero's frame,
 * title + the country's office block (first city office under the country:
 * phone, email, address, "View on map").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term ) {
	return;
}

$tng_ref    = tng_term_ref( $tng_term );
$tng_office = tng_country_office( $tng_term );
$tng_phone_icon = '<span class="loc-hero__icon"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.6 2.2a1.5 1.5 0 0 0-1.9-.6L3 2.4A2.3 2.3 0 0 0 1.7 5c.9 5.7 5.6 10.4 11.3 11.3a2.3 2.3 0 0 0 2.6-1.3l.8-1.7a1.5 1.5 0 0 0-.6-1.9l-2.3-1.4a1.5 1.5 0 0 0-1.9.3l-.8.9a9.5 9.5 0 0 1-4.7-4.7l.9-.8a1.5 1.5 0 0 0 .3-1.9L6.6 2.2z"/></svg></span>';
$tng_mail_icon  = '<span class="loc-hero__icon"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M1 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v.4l-9 5.4-9-5.4V5zm0 2.7V15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.7l-8.5 5.1a1 1 0 0 1-1 0L1 7.7z"/></svg></span>';
?>
<section class="in-hero loc-hero" data-section="loc-hero">
	<?php echo tng_breadcrumbs( $tng_term->name, array( array( 'Locations', tng_page_url( 'contact' ) ) ) ); ?>

	<h1 class="h1 dark loc-hero__title"><?php echo esc_html( $tng_term->name ); ?></h1>

	<?php
	if ( $tng_office ) :
		$tng_oref    = 'location_' . $tng_office->term_id;
		$tng_phone   = tng_field( 'phone', '', $tng_oref );
		$tng_email   = tng_field( 'email', '', $tng_oref );
		$tng_address = tng_field( 'address', '', $tng_oref );
		$tng_query   = tng_field( 'map_query', '', $tng_oref );
		?>
	<div class="loc-hero__office">
		<p class="body dark loc-hero__office-name"><?php echo esc_html( $tng_office->name . ' Office' ); ?></p>
		<div class="loc-hero__office-body">
			<ul class="loc-hero__lines">
				<?php if ( $tng_phone ) : ?>
				<li><?php echo $tng_phone_icon; // phpcs:ignore ?><a href="<?php echo esc_attr( tng_tel( $tng_phone ) ); ?>"><?php echo esc_html( $tng_phone ); ?></a></li>
				<?php endif; ?>
				<?php if ( $tng_email ) : ?>
				<li><?php echo $tng_mail_icon; // phpcs:ignore ?><a href="mailto:<?php echo esc_attr( $tng_email ); ?>"><?php echo esc_html( $tng_email ); ?></a></li>
				<?php endif; ?>
			</ul>
			<div class="loc-hero__address">
				<p class="body dark"><?php echo wp_kses( $tng_address, array( 'br' => array() ) ); ?></p>
				<?php if ( $tng_query ) : ?>
				<a href="<?php echo esc_url( 'https://www.google.com/maps?q=' . rawurlencode( $tng_query ) ); ?>" class="loc-hero__map" target="_blank" rel="noopener">View on map</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<img src="<?php echo esc_url( tng_img_url( 'hero_image', tng_bundled_img( 'loc-' . $tng_term->slug . '-hero.jpg', tng_default_hero() ), $tng_ref ) ); ?>" alt="" class="in-hero__image">
</section>
