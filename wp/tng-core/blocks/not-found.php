<?php
/**
 * tng/not-found — the 404 page (tng-404 template): the under-development
 * hero with a short note, the site search field (posts to the search results
 * page) and links to the main sections. No fields: template copy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_links = array(
	array( 'Back to homepage', home_url( '/' ) ),
	array( 'Find a professional', tng_page_url( 'professionals', '' ) ),
	array( 'Services', tng_page_url( 'services', '' ) ),
	array( 'Insights', tng_page_url( 'insights', '' ) ),
	array( 'Contact us', tng_page_url( 'contact', '' ) ),
);
?>
<section class="page-hero page-hero--ud nf-hero" data-section="not-found">
	<?php echo tng_breadcrumbs( 'Page not found' ); // phpcs:ignore ?>

	<div class="page-hero__content">
		<div class="page-hero__main">
			<h1 class="h1 dark">This page could not be found</h1>
			<p class="body dark nf-hero__text">The link may be out of date, or the page may have moved. Try a search, or start from one of the pages below.</p>
			<form class="hp-footer__form nf-hero__form" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
				<label class="visually-hidden" for="nf-q">Search the site</label>
				<input type="search" id="nf-q" name="s" placeholder="Search the site">
				<button type="submit">
					Search
					<?php echo tng_arrow( 1.5 ); // phpcs:ignore ?>
				</button>
			</form>
			<div class="page-hero__links">
				<?php foreach ( $tng_links as $tng_l ) : if ( ! $tng_l[1] ) { continue; } ?>
				<a href="<?php echo esc_url( $tng_l[1] ); ?>" class="button-label dark link-arrow">
					<?php echo esc_html( $tng_l[0] ); ?>
					<?php echo tng_arrow( 1.5 ); // phpcs:ignore ?>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
