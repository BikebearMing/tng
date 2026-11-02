<?php
/**
 * tng/under-development — full-height navy placeholder for routes that have
 * no design yet: taxonomy term pages, archives, search, 404, award and job
 * singles, and the About / Services / Insights placeholder pages.
 * The breadcrumb label comes from the route.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_label = 'Page';
if ( is_404() ) {
	$tng_label = 'Page not found';
} elseif ( is_search() ) {
	$tng_label = 'Search';
} elseif ( is_tax() || is_category() || is_tag() ) {
	$tng_obj   = get_queried_object();
	$tng_label = $tng_obj ? $tng_obj->name : 'Archive';
} elseif ( is_post_type_archive() ) {
	$tng_obj   = get_queried_object();
	$tng_label = $tng_obj ? $tng_obj->labels->name : 'Archive';
} elseif ( is_archive() ) {
	$tng_label = 'Archive';
} elseif ( get_the_ID() ) {
	$tng_label = get_the_title();
}

$tng_contact = get_page_by_path( 'contact' );
?>
<section class="page-hero page-hero--ud" data-section="page-hero-ud">
	<?php echo tng_breadcrumbs( $tng_label ); ?>

	<div class="page-hero__content">
		<div class="page-hero__main">
			<h1 class="h1 dark">This page is under development</h1>
			<div class="page-hero__links">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button-label dark link-arrow">
					Back to homepage
					<?php echo tng_arrow( 1.5 ); ?>
				</a>
				<a href="<?php echo esc_url( $tng_contact ? get_permalink( $tng_contact ) : home_url( '/' ) ); ?>" class="button-label dark link-arrow">
					Contact us
					<?php echo tng_arrow( 1.5 ); ?>
				</a>
			</div>
		</div>
	</div>
</section>
