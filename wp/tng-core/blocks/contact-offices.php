<?php
/**
 * acf/contact-offices — city tabs + details + lazy Google map per office.
 * Offices are Location city terms (children of a country) that have an
 * office_name; ordered by name. Term fields: office_name, phone, email,
 * address, map_query.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_contact_offices' ) ) {
	return;
}

$tng_cities = get_terms(
	array(
		'taxonomy'   => 'location',
		'hide_empty' => false,
	)
);
$tng_cities = is_wp_error( $tng_cities ) ? array() : $tng_cities;
$tng_cities = array_values(
	array_filter(
		$tng_cities,
		static function ( $t ) {
			return $t->parent && tng_field( 'office_name', '', 'location_' . $t->term_id );
		}
	)
);
usort(
	$tng_cities,
	static function ( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	}
);

if ( ! $tng_cities ) {
	return;
}

$tng_phone_icon = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.6 2.2a1.5 1.5 0 0 0-1.9-.6L3 2.4A2.3 2.3 0 0 0 1.7 5c.9 5.7 5.6 10.4 11.3 11.3a2.3 2.3 0 0 0 2.6-1.3l.8-1.7a1.5 1.5 0 0 0-.6-1.9l-2.3-1.4a1.5 1.5 0 0 0-1.9.3l-.8.9a9.5 9.5 0 0 1-4.7-4.7l.9-.8a1.5 1.5 0 0 0 .3-1.9L6.6 2.2z"/></svg>';
$tng_mail_icon  = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M1 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v.4l-9 5.4-9-5.4V5zm0 2.7V15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.7l-8.5 5.1a1 1 0 0 1-1 0L1 7.7z"/></svg>';
?>
<section class="contact-offices" data-section="contact-offices" data-toggle="show_contact_offices">
	<ul class="contact-offices__tabs" role="tablist" aria-label="Offices">
		<?php foreach ( $tng_cities as $tng_i => $tng_city ) : ?>
			<li role="presentation"><button type="button" class="h2 contact-offices__tab<?php echo 0 === $tng_i ? ' is-active' : ''; ?>" role="tab" id="office-tab-<?php echo esc_attr( $tng_city->slug ); ?>" aria-controls="office-<?php echo esc_attr( $tng_city->slug ); ?>" aria-selected="<?php echo 0 === $tng_i ? 'true' : 'false'; ?>"<?php echo 0 === $tng_i ? '' : ' tabindex="-1"'; ?>><?php echo esc_html( $tng_city->name ); ?><?php echo tng_arrow_circle( true ); ?></button></li>
		<?php endforeach; ?>
	</ul>

	<div class="contact-offices__panels">
		<?php
		foreach ( $tng_cities as $tng_i => $tng_city ) :
			$tng_tid     = 'location_' . $tng_city->term_id;
			$tng_phone   = tng_field( 'phone', '', $tng_tid );
			$tng_email   = tng_field( 'email', '', $tng_tid );
			$tng_address = tng_field( 'address', '', $tng_tid );
			$tng_query   = tng_field( 'map_query', 'Tilleke & Gibbins, ' . $tng_city->name, $tng_tid );
			?>
		<div class="contact-office<?php echo 0 === $tng_i ? ' is-active' : ''; ?>" id="office-<?php echo esc_attr( $tng_city->slug ); ?>" role="tabpanel" aria-labelledby="office-tab-<?php echo esc_attr( $tng_city->slug ); ?>"<?php echo 0 === $tng_i ? '' : ' hidden'; ?>>
			<div class="contact-office__info">
				<h2 class="h4 contact-office__name"><?php echo esc_html( tng_field( 'office_name', 'Tilleke & Gibbins International Ltd.', $tng_tid ) ); ?></h2>
				<ul class="contact-office__lines">
					<?php if ( $tng_phone ) : ?>
					<li><span class="contact-office__icon"><?php echo $tng_phone_icon; // phpcs:ignore ?></span><a href="tel:<?php echo esc_attr( preg_replace( '/[^+\d]/', '', $tng_phone ) ); ?>"><?php echo esc_html( $tng_phone ); ?></a></li>
					<?php endif; ?>
					<?php if ( $tng_email ) : ?>
					<li><span class="contact-office__icon"><?php echo $tng_mail_icon; // phpcs:ignore ?></span><a href="mailto:<?php echo esc_attr( $tng_email ); ?>"><?php echo esc_html( $tng_email ); ?></a></li>
					<?php endif; ?>
				</ul>
				<?php if ( $tng_address ) : ?>
				<div class="rich-text contact-office__address">
					<p><?php echo wp_kses( $tng_address, array( 'br' => array() ) ); ?></p>
				</div>
				<?php endif; ?>
			</div>
			<iframe class="contact-office__map" src="https://www.google.com/maps?q=<?php echo esc_attr( rawurlencode( $tng_query ) ); ?>&amp;output=embed" title="Map: <?php echo esc_attr( $tng_city->name ); ?> office" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
		</div>
		<?php endforeach; ?>
	</div>
</section>
