<?php
/**
 * acf/info-hero — About child page hero (Pro Bono, CSR, Museum of Counterfeit
 * Goods): the about hero's frame, title on the bottom, then a contact block —
 * grey label (or the museum's two-line intro), rule, optional name, then icon
 * rows for hours / phone / email (a row is left out when its field is empty).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_info_hero' ) ) {
	return;
}

$tng_label = tng_field( 'label', '' );
$tng_name  = tng_field( 'contact_name', '' );
$tng_hours = tng_field( 'hours', '' );
$tng_phone = tng_field( 'phone', '' );
$tng_email = tng_field( 'email', '' );
// Further named contacts (desks): name + phones (one per line) + email
$tng_more = array_values(
	array_filter(
		(array) tng_field( 'contacts', array() ),
		static function ( $row ) {
			return ! empty( $row['contact_name'] );
		}
	)
);

// Bundled hero per page (slug), else the Pro Bono one
$tng_heroes   = array(
	'pro-bono'                    => 'img/about-pro-bono-hero.jpg',
	'csr'                         => 'img/about-csr-hero.jpg',
	'museum-of-counterfeit-goods' => 'img/about-museum-hero.jpg',
	'textile-collection'          => 'img/about-textile-collection-hero.jpg',
	'japan-desk'                  => 'img/loc-thailand-hero.jpg',
	'china-desk'                  => 'img/loc-thailand-hero.jpg',
);
$tng_slug     = (string) get_post_field( 'post_name', get_the_ID() );
$tng_fallback = tng_asset( isset( $tng_heroes[ $tng_slug ] ) ? $tng_heroes[ $tng_slug ] : $tng_heroes['pro-bono'] );

$tng_icon_hours = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6 1a1 1 0 0 1 1 1v1h6V2a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h1V2a1 1 0 0 1 1-1zM4 8v8h12V8H4z"/></svg>';
$tng_icon_phone = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.6 2.2a1.5 1.5 0 0 0-1.9-.6L3 2.4A2.3 2.3 0 0 0 1.7 5c.9 5.7 5.6 10.4 11.3 11.3a2.3 2.3 0 0 0 2.6-1.3l.8-1.7a1.5 1.5 0 0 0-.6-1.9l-2.3-1.4a1.5 1.5 0 0 0-1.9.3l-.8.9a9.5 9.5 0 0 1-4.7-4.7l.9-.8a1.5 1.5 0 0 0 .3-1.9L6.6 2.2z"/></svg>';
$tng_icon_email = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M1 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v.4l-9 5.4-9-5.4V5zm0 2.7V15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.7l-8.5 5.1a1 1 0 0 1-1 0L1 7.7z"/></svg>';
?>
<section class="in-hero info-hero<?php echo tng_block_class( $block ); ?>" data-section="info-hero" data-toggle="show_info_hero">
	<?php echo tng_breadcrumbs( get_the_title(), tng_page_trail() ); // phpcs:ignore ?>

	<div class="info-hero__content">
		<h1 class="h1 dark info-hero__title"><?php echo esc_html( tng_field( 'title', get_the_title() ) ); ?></h1>
		<?php if ( $tng_label || $tng_name || $tng_hours || $tng_phone || $tng_email || $tng_more ) : ?>
		<div class="info-hero__contact">
			<?php if ( $tng_label ) : ?>
			<p class="subhead info-hero__label"><?php echo esc_html( $tng_label ); ?></p>
			<?php endif; ?>
			<?php if ( $tng_name ) : ?>
			<p class="h4 dark info-hero__name"><?php echo esc_html( $tng_name ); ?></p>
			<?php endif; ?>
			<?php if ( $tng_hours || $tng_phone || $tng_email ) : ?>
			<ul class="info-hero__lines">
				<?php if ( $tng_hours ) : ?>
				<li><span class="loc-hero__icon"><?php echo $tng_icon_hours; // phpcs:ignore ?></span><span><?php echo nl2br( esc_html( trim( $tng_hours ) ), false ); ?></span></li>
				<?php endif; ?>
				<?php if ( $tng_phone ) : ?>
				<li><span class="loc-hero__icon"><?php echo $tng_icon_phone; // phpcs:ignore ?></span><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $tng_phone ) ); ?>"><?php echo esc_html( $tng_phone ); ?></a></li>
				<?php endif; ?>
				<?php if ( $tng_email ) : ?>
				<li><span class="loc-hero__icon"><?php echo $tng_icon_email; // phpcs:ignore ?></span><a href="mailto:<?php echo esc_attr( $tng_email ); ?>"><?php echo esc_html( $tng_email ); ?></a></li>
				<?php endif; ?>
			</ul>
			<?php endif; ?>
			<?php foreach ( $tng_more as $tng_c ) : ?>
			<p class="h4 dark info-hero__name"><?php echo esc_html( $tng_c['contact_name'] ); ?></p>
			<ul class="info-hero__lines">
				<?php foreach ( array_filter( array_map( 'trim', preg_split( '/\R/', (string) ( isset( $tng_c['phones'] ) ? $tng_c['phones'] : '' ) ) ) ) as $tng_ph ) : ?>
				<li><span class="loc-hero__icon"><?php echo $tng_icon_phone; // phpcs:ignore ?></span><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', preg_replace( '/\(.*\)/', '', $tng_ph ) ) ); ?>"><?php echo esc_html( $tng_ph ); ?></a></li>
				<?php endforeach; ?>
				<?php if ( ! empty( $tng_c['email'] ) ) : ?>
				<li><span class="loc-hero__icon"><?php echo $tng_icon_email; // phpcs:ignore ?></span><a href="mailto:<?php echo esc_attr( $tng_c['email'] ); ?>"><?php echo esc_html( $tng_c['email'] ); ?></a></li>
				<?php endif; ?>
			</ul>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>

	<img src="<?php echo esc_url( tng_img_url( 'image', $tng_fallback ) ); ?>" alt="" class="in-hero__image">
</section>
