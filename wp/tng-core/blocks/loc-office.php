<?php
/**
 * tng/loc-office — navy office strip + Google Maps embed (keyless) for the
 * country's first city office.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'office' ) ) {
	return;
}

$tng_office  = tng_country_office( $tng_term );
$tng_oref    = 'location_' . $tng_office->term_id;
$tng_phone   = tng_field( 'phone', '', $tng_oref );
$tng_email   = tng_field( 'email', '', $tng_oref );
$tng_address = tng_field( 'address', '', $tng_oref );
$tng_query   = tng_field( 'map_query', 'Tilleke & Gibbins, ' . $tng_office->name, $tng_oref );
?>
<section class="loc-office" data-section="loc-office" data-toggle="show_loc_office">
	<div class="loc-office__info">
		<p class="subhead dark pro-label loc-office__label">Office</p>
		<div class="loc-office__body">
			<h2 class="h2 dark loc-office__city"><?php echo esc_html( $tng_office->name ); ?></h2>
			<div class="loc-office__details">
				<ul class="loc-hero__lines loc-office__lines">
					<?php if ( $tng_phone ) : ?>
					<li><span class="loc-hero__icon"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.6 2.2a1.5 1.5 0 0 0-1.9-.6L3 2.4A2.3 2.3 0 0 0 1.7 5c.9 5.7 5.6 10.4 11.3 11.3a2.3 2.3 0 0 0 2.6-1.3l.8-1.7a1.5 1.5 0 0 0-.6-1.9l-2.3-1.4a1.5 1.5 0 0 0-1.9.3l-.8.9a9.5 9.5 0 0 1-4.7-4.7l.9-.8a1.5 1.5 0 0 0 .3-1.9L6.6 2.2z"/></svg></span><a href="<?php echo esc_attr( tng_tel( $tng_phone ) ); ?>"><?php echo esc_html( $tng_phone ); ?></a></li>
					<?php endif; ?>
					<?php if ( $tng_email ) : ?>
					<li><span class="loc-hero__icon"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M1 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v.4l-9 5.4-9-5.4V5zm0 2.7V15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.7l-8.5 5.1a1 1 0 0 1-1 0L1 7.7z"/></svg></span><a href="mailto:<?php echo esc_attr( $tng_email ); ?>"><?php echo esc_html( $tng_email ); ?></a></li>
					<?php endif; ?>
				</ul>
				<p class="body dark loc-office__address"><?php echo wp_kses( $tng_address, array( 'br' => array() ) ); ?></p>
			</div>
		</div>
	</div>
	<iframe class="loc-office__map" src="<?php echo esc_url( 'https://www.google.com/maps?q=' . rawurlencode( $tng_query ) . '&z=14&output=embed' ); ?>" title="<?php echo esc_attr( 'Map: ' . $tng_office->name . ' office' ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
</section>
