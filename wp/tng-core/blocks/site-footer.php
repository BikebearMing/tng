<?php
/**
 * tng/site-footer — SCF options page: site footer (menu "Site Chrome").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_page_url = static function ( $slug, $fallback = '#' ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : $fallback;
};

$tng_logo = tng_img_url( 'logo', '', 'option' );

$tng_social_paths = array(
	'facebook' => '<path d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H8v3h2.5V21h3z"/>',
	'youtube'  => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8zM10 15V9l5.2 3L10 15z"/>',
	'linkedin' => '<path d="M6.9 8.7H3.6V20h3.3V8.7zM5.3 3.5a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 13.1c0-3.2-1.7-4.7-4-4.7a3.4 3.4 0 0 0-3.1 1.7V8.7H10V20h3.3v-5.6c0-1.5.3-2.9 2.1-2.9 1.8 0 1.8 1.7 1.8 3V20h3.3v-6.9z"/>',
);

$tng_social = tng_field( 'social_links', array(), 'option' );
if ( ! $tng_social ) {
	$tng_social = array(
		array( 'network' => 'facebook', 'url' => '#' ),
		array( 'network' => 'youtube', 'url' => '#' ),
		array( 'network' => 'linkedin', 'url' => '#' ),
	);
}

$tng_networks = tng_field( 'networks', array(), 'option' );
if ( ! $tng_networks ) {
	$tng_networks = array(
		array( 'name' => 'Lex Mundi Member', 'style' => 'lexmundi', 'image' => '', 'url' => '#' ),
		array( 'name' => 'Multilaw', 'style' => 'multilaw', 'image' => '', 'url' => '#' ),
		array( 'name' => 'Drew Network Asia', 'style' => 'dna', 'image' => '', 'url' => '#' ),
	);
}

$tng_quick_1 = tng_field( 'quick_links_1', array(), 'option' );
if ( ! $tng_quick_1 ) {
	$tng_quick_1 = array(
		array( 'label' => 'Professionals', 'url' => $tng_page_url( 'professionals' ) ),
		array( 'label' => 'Services', 'url' => '#' ),
		array( 'label' => 'Insights', 'url' => '#' ),
		array( 'label' => 'Locations', 'url' => '#' ),
	);
}
$tng_quick_2 = tng_field( 'quick_links_2', array(), 'option' );
if ( ! $tng_quick_2 ) {
	$tng_quick_2 = array(
		array( 'label' => 'About', 'url' => '#' ),
		array( 'label' => 'Careers', 'url' => $tng_page_url( 'careers' ) ),
		array( 'label' => 'Contact', 'url' => $tng_page_url( 'contact' ) ),
	);
}

$tng_legal = tng_field( 'legal_links', array(), 'option' );
if ( ! $tng_legal ) {
	$tng_legal = array(
		array( 'label' => 'Privacy Policy', 'url' => '#' ),
		array( 'label' => 'Fraudulent Communications', 'url' => '#' ),
		array( 'label' => 'Legal Notice', 'url' => '#' ),
	);
}
?>
<footer id="footer" class="hp-footer" data-section="footer">
	<div class="hp-footer__top">

		<div class="hp-footer__brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hp-footer__logo" aria-label="Tilleke &amp; Gibbins">
				<?php if ( $tng_logo ) : ?>
					<img src="<?php echo esc_url( $tng_logo ); ?>" alt="Tilleke &amp; Gibbins">
				<?php else : ?>
					<span>Tilleke</span>
					<span><em>&amp;</em>Gibbins</span>
				<?php endif; ?>
			</a>

			<ul class="hp-footer__social">
				<?php foreach ( $tng_social as $tng_row ) :
					$tng_net = isset( $tng_social_paths[ $tng_row['network'] ] ) ? $tng_row['network'] : 'facebook';
					?>
				<li>
					<a href="<?php echo esc_url( $tng_row['url'] ? $tng_row['url'] : '#' ); ?>" aria-label="<?php echo esc_attr( ucfirst( $tng_net ) ); ?>">
						<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?php echo $tng_social_paths[ $tng_net ]; // phpcs:ignore ?></svg>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>

			<p class="button-label dark hp-footer__newsletter-heading"><?php echo esc_html( tng_field( 'newsletter_heading', 'Subscribe to our newsletter', 'option' ) ); ?></p>
			<form class="hp-footer__form" action="<?php echo esc_url( tng_field( 'newsletter_action', '#', 'option' ) ); ?>" method="post">
				<label class="visually-hidden" for="footer-email">Your email</label>
				<input type="email" id="footer-email" name="email" placeholder="Your email" required>
				<button type="submit">
					Subscribe
					<?php echo tng_arrow( 1.5 ); ?>
				</button>
			</form>
		</div>

		<div class="hp-footer__nav">
			<p class="subhead hp-footer__label">Networks</p>
			<ul class="hp-footer__networks">
				<?php foreach ( $tng_networks as $tng_row ) :
					$tng_style = ! empty( $tng_row['style'] ) ? $tng_row['style'] : 'lexmundi';
					$tng_src   = ! empty( $tng_row['image'] ) ? $tng_row['image'] : tng_asset( $tng_style . '.png' );
					if ( is_array( $tng_src ) ) {
						$tng_src = $tng_src['url'];
					}
					?>
				<li><a href="<?php echo esc_url( $tng_row['url'] ? $tng_row['url'] : '#' ); ?>"><img src="<?php echo esc_url( $tng_src ); ?>" alt="<?php echo esc_attr( $tng_row['name'] ); ?>" class="hp-footer__network hp-footer__network--<?php echo esc_attr( $tng_style ); ?>"></a></li>
				<?php endforeach; ?>
			</ul>

			<p class="subhead hp-footer__label hp-footer__label--links">Quick links</p>
			<div class="hp-footer__quick">
				<?php foreach ( array( $tng_quick_1, $tng_quick_2 ) as $tng_col ) : ?>
				<ul class="hp-footer__quick-col">
					<?php foreach ( $tng_col as $tng_row ) : ?>
					<li><a href="<?php echo esc_url( $tng_row['url'] ? $tng_row['url'] : '#' ); ?>" class="button-label dark"><?php echo esc_html( $tng_row['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php endforeach; ?>
			</div>
		</div>

	</div>

	<div class="hp-footer__bar">
		<p class="subhead hp-footer__copyright"><?php echo esc_html( tng_field( 'copyright', '© 2026 Tilleke & Gibbins International Ltd. All rights reserved.', 'option' ) ); ?></p>
		<ul class="hp-footer__legal">
			<?php foreach ( $tng_legal as $tng_row ) : ?>
			<li><a href="<?php echo esc_url( $tng_row['url'] ? $tng_row['url'] : '#' ); ?>" class="subhead dark"><?php echo esc_html( $tng_row['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</footer>
