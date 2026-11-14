<?php
/**
 * tng/site-header — SCF options page: site header (menu "Site Chrome").
 * Markup mirrors the static header exactly; megamenus are fed by the
 * Industry / Practice / Location taxonomies.
 * Attribute "light": adds .site-header--light (pages that open on a pale hero).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_light = ! empty( $attributes['light'] );

$tng_page_url = static function ( $slug, $fallback = '#' ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : $fallback;
};

// Two wordmarks: "logo" (white, over dark heroes; also the footer's, same
// option) and "logo_dark" (navy, for the white scrolled bar and light pages).
// No navy upload = the bundled navy copy of the white logo.
$tng_logo      = tng_img_url( 'logo', '', 'option' );
$tng_logo_dark = $tng_logo ? tng_img_url( 'logo_dark', tng_asset( 'header-logo-navy.png' ), 'option' ) : '';

$tng_top_links = tng_field( 'top_links', array(), 'option' );
if ( ! $tng_top_links ) {
	$tng_top_links = array(
		array( 'label' => 'About', 'url' => '#' ),
		array( 'label' => 'Careers', 'url' => $tng_page_url( 'careers' ) ),
		array( 'label' => 'Contact', 'url' => $tng_page_url( 'contact' ) ),
	);
}

// Tiny megamenu under a top link: the published child pages of the page it
// points to (menu_order: Pages → Quick Edit → Order, then title). No children, an external
// or non-page link = a plain link.
$tng_top_children = static function ( $url ) {
	$id = $url && '#' !== $url ? url_to_postid( $url ) : 0;
	if ( ! $id || 'page' !== get_post_type( $id ) ) {
		return array();
	}
	return get_pages( array( 'parent' => $id, 'sort_column' => 'menu_order,post_title' ) );
};

$tng_professionals_url =tng_field( 'professionals_url', $tng_page_url( 'professionals' ), 'option' );
$tng_insights_url      = tng_field( 'insights_url', '#', 'option' );
$tng_global_reach_url  = tng_heal_url( tng_field( 'global_reach_url', '#', 'option' ), 'Global Reach' );

$tng_industries = get_terms( array( 'taxonomy' => 'industry', 'hide_empty' => false, 'parent' => 0 ) );
$tng_industries = is_wp_error( $tng_industries ) ? array() : $tng_industries;
$tng_practices  = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false, 'parent' => 0 ) );
$tng_practices  = is_wp_error( $tng_practices ) ? array() : $tng_practices;
$tng_locations  = get_terms( array( 'taxonomy' => 'location', 'hide_empty' => false, 'parent' => 0 ) );
$tng_locations  = is_wp_error( $tng_locations ) ? array() : $tng_locations;
?>
<header class="site-header<?php echo $tng_light ? ' site-header--light' : ''; ?>" data-scrolled-at="40">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-header__logo" aria-label="Tilleke &amp; Gibbins">
		<?php if ( $tng_logo ) : ?>
			<img src="<?php echo esc_url( $tng_logo ); ?>" alt="Tilleke &amp; Gibbins" class="site-header__logo-img site-header__logo-img--on-dark">
			<img src="<?php echo esc_url( $tng_logo_dark ); ?>" alt="" aria-hidden="true" class="site-header__logo-img site-header__logo-img--on-light">
		<?php else : ?>
			<span>Tilleke</span>
			<span><em>&amp;</em>Gibbins</span>
		<?php endif; ?>
	</a>

	<nav class="site-header__nav" id="site-nav" aria-label="Main">
		<ul class="site-header__top">
			<?php foreach ( $tng_top_links as $tng_link ) : ?>
				<?php $tng_kids = $tng_top_children( $tng_link['url'] ); ?>
				<?php if ( $tng_kids ) : ?>
				<li class="has-mega">
					<a href="<?php echo esc_url( $tng_link['url'] ); ?>" class="subhead" aria-haspopup="true"><?php echo esc_html( $tng_link['label'] ); ?></a>
					<div class="site-header__mega site-header__mega--mini">
						<ul class="site-header__mega-links">
						<?php foreach ( $tng_kids as $tng_kid ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $tng_kid ) ); ?>" class="site-header__mega-link"><?php echo esc_html( get_the_title( $tng_kid ) ); ?></a></li>
						<?php endforeach; ?>
						</ul>
					</div>
				</li>
				<?php else : ?>
				<li><a href="<?php echo esc_url( $tng_link['url'] ? $tng_link['url'] : '#' ); ?>" class="subhead"><?php echo esc_html( $tng_link['label'] ); ?></a></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<ul class="site-header__main">
			<li><a href="<?php echo esc_url( $tng_professionals_url ); ?>" class="button-label">Professionals</a></li>
			<li class="has-mega">
				<a href="<?php echo esc_url( $tng_page_url( 'services' ) ); ?>" class="button-label" aria-haspopup="true">Services</a>
				<div class="site-header__mega site-header__mega--services">
					<div class="site-header__mega-col">
						<p class="subhead site-header__mega-label">Industries</p>
						<ul class="site-header__mega-list">
						<?php foreach ( $tng_industries as $tng_i => $tng_term ) : ?>
						<li<?php echo 0 === $tng_i ? ' class="is-active"' : ''; ?>><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="button-label"><?php echo esc_html( $tng_term->name ); ?><?php echo tng_arrow_circle( true ); ?></a></li>
						<?php endforeach; ?>
						</ul>
					</div>
					<div class="site-header__mega-col site-header__mega-col--divided">
						<p class="subhead site-header__mega-label">Practices</p>
						<ul class="site-header__mega-links" style="--rows: <?php echo esc_attr( max( 1, (int) ceil( count( $tng_practices ) / 2 ) ) ); ?>;">
						<?php foreach ( $tng_practices as $tng_term ) : ?>
						<li><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="site-header__mega-link"><?php echo esc_html( $tng_term->name ); ?></a></li>
						<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</li>
			<li><a href="<?php echo esc_url( $tng_insights_url ); ?>" class="button-label">Insights</a></li>
			<li class="has-mega">
				<a href="#" class="button-label" aria-haspopup="true">Locations</a>
				<div class="site-header__mega">
					<ul class="site-header__mega-list">
					<?php foreach ( $tng_locations as $tng_i => $tng_term ) : ?>
					<li<?php echo 0 === $tng_i ? ' class="is-active"' : ''; ?>><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="button-label"><?php echo esc_html( $tng_term->name ); ?><?php echo tng_arrow_circle( true ); ?></a></li>
					<?php endforeach; ?>
					<li><a href="<?php echo esc_url( $tng_global_reach_url ); ?>" class="button-label">Global Reach<?php echo tng_arrow_circle( true ); ?></a></li>
					</ul>
					<div class="site-header__mega-media" aria-hidden="true">
					<?php foreach ( $tng_locations as $tng_i => $tng_term ) : ?>
					<img src="<?php echo esc_url( tng_location_image( $tng_term, 374, 452 ) ); ?>" alt=""<?php echo 0 === $tng_i ? ' class="is-active"' : ''; ?>>
					<?php endforeach; ?>
					<img src="<?php echo esc_url( tng_bundled_img( 'loc-global-menu.jpg' ) ); ?>" alt="">
					</div>
				</div>
			</li>
		</ul>
	</nav>

	<div class="site-header__utils">
		<a href="<?php echo esc_url( tng_search_url() ); ?>" class="subhead site-header__search">
			<?php echo esc_html( tng_field( 'search_label', 'Search', 'option' ) ); ?>
			<svg viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M12 12l4.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
		</a>
		<a href="<?php echo esc_url( tng_field( 'lang_url', '#', 'option' ) ); ?>" class="subhead site-header__lang" aria-label="Language: English">
			<?php echo esc_html( tng_field( 'lang_label', 'ENG', 'option' ) ); ?>
			<svg viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="1.3"/><ellipse cx="9" cy="9" rx="3.2" ry="7.5" stroke="currentColor" stroke-width="1.3"/><path d="M1.5 9h15M2.8 5.2h12.4M2.8 12.8h12.4" stroke="currentColor" stroke-width="1.3"/></svg>
		</a>
	</div>

	<button type="button" class="site-header__toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Menu"><span></span><span></span></button>
</header>
