<?php
/**
 * acf/archive-filter — the compact professionals filter. A GET form: "Search
 * by Name" (q) and "Search by Professionals" (pro) reload the page with the
 * filter (the grid block below reads the query string). Date + facets are
 * closed buttons until their open-state design exists.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_archive_filter' ) ) {
	return;
}

$tng_params = tng_archive_params( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
$tng_search = '<svg viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.8"/><path d="M12 12l4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$tng_down   = '<span class="pro-filter__chevron" aria-hidden="true"><svg viewBox="0 0 10 10" fill="none"><path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
?>
<section class="pro-filter ins-filter" data-section="archive-filter" data-toggle="show_archive_filter">
	<form class="pro-filter__form" role="search" method="get" action="<?php echo esc_url( get_permalink() . '#results' ); ?>">
		<?php
		// keep the taxonomy filters (from "View all" links) when searching
		foreach ( array( 'res', 'ind', 'prac', 'loc' ) as $tng_keep ) :
			if ( ! empty( $tng_params[ $tng_keep ] ) ) :
				?>
		<input type="hidden" name="<?php echo esc_attr( $tng_keep ); ?>" value="<?php echo esc_attr( $tng_params[ $tng_keep ] ); ?>">
				<?php
			endif;
		endforeach;
		?>
		<div class="ins-filter__bar">
			<div class="pro-filter__search ins-filter__field">
				<label class="visually-hidden" for="ins-search">Search by name</label>
				<input type="search" id="ins-search" name="q" placeholder="Search by Name" value="<?php echo esc_attr( isset( $tng_params['q'] ) ? $tng_params['q'] : '' ); ?>">
				<button type="submit" class="pro-filter__submit" aria-label="Search"><?php echo $tng_search; // phpcs:ignore ?></button>
			</div>
			<div class="pro-filter__search ins-filter__field">
				<label class="visually-hidden" for="ins-search-pro">Search by professional</label>
				<input type="search" id="ins-search-pro" name="pro" placeholder="Search by Professionals" value="<?php echo esc_attr( isset( $tng_params['pro'] ) ? $tng_params['pro'] : '' ); ?>">
				<button type="submit" class="pro-filter__submit" aria-label="Search"><?php echo $tng_search; // phpcs:ignore ?></button>
			</div>
			<button type="button" class="ins-filter__date" aria-expanded="false">
				<span>Date</span>
				<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 1h2v2h6V1h2v2h2.5A1.5 1.5 0 0 1 19 4.5v13a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 1 17.5v-13A1.5 1.5 0 0 1 2.5 3H5V1zm-2 7v9h14V8H3z"/></svg>
			</button>
		</div>

		<div class="pro-filter__facets">
			<?php foreach ( array( 'Resources', 'Sort by Relevance', 'Industries', 'Practices', 'Locations' ) as $tng_facet ) : ?>
			<button type="button" class="pro-filter__facet" aria-expanded="false">
				<span><?php echo esc_html( $tng_facet ); ?></span>
				<?php echo $tng_down; // phpcs:ignore ?>
			</button>
			<?php endforeach; ?>
		</div>
	</form>
</section>
