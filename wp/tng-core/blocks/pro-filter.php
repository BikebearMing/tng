<?php
/**
 * acf/pro-filter — search, A–Z, facet dropdowns (closed state; the open panel
 * has no design yet). Search + alphabet filter the roster client-side
 * (assets/js/tng-wp.js).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_pro_filter' ) ) {
	return;
}
?>
<section class="pro-filter" data-section="pro-filter" data-toggle="show_pro_filter">
	<form class="pro-filter__form" role="search" action="#">
		<div class="pro-filter__bar">
			<div class="pro-filter__search">
				<label class="visually-hidden" for="pro-search">Search professionals</label>
				<input type="search" class="button-label" id="pro-search" name="q" placeholder="<?php echo esc_attr( tng_field( 'search_placeholder', 'Search by name, expertise or industry' ) ); ?>">
				<button type="submit" class="pro-filter__submit" aria-label="Search">
					<svg viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.8"/><path d="M12 12l4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
				</button>
			</div>
			<ul class="pro-filter__alpha" aria-label="Filter by surname">
				<?php foreach ( range( 'A', 'Z' ) as $tng_letter ) : ?>
				<li><button type="button" aria-pressed="false"><?php echo esc_html( $tng_letter ); ?></button></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="pro-filter__facets">
		<?php foreach ( array( 'Industries', 'Practices', 'Positions', 'Locations', 'Languages' ) as $tng_facet ) : ?>
		<button type="button" class="button-label pro-filter__facet" aria-expanded="false">
			<span><?php echo esc_html( $tng_facet ); ?></span>
			<span class="pro-filter__chevron" aria-hidden="true"><svg viewBox="0 0 10 10" fill="none"><path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
		</button>
		<?php endforeach; ?>
		</div>
	</form>
</section>
