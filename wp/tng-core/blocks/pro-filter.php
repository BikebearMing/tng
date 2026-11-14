<?php
/**
 * acf/pro-filter — search, A–Z, facet dropdowns. main.js opens the facet
 * panels; search, alphabet and facets filter the roster client-side
 * (assets/js/tng-wp.js), with the state in the URL (q, letter, ind, prac,
 * pos, loc, lang).
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
		<?php
		$tng_down = '<span class="pro-filter__chevron" aria-hidden="true"><svg viewBox="0 0 10 10" fill="none"><path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
		foreach ( array(
			'ind'  => array( 'industry', 'Industries', 'All industries' ),
			'prac' => array( 'practice', 'Practices', 'All practices' ),
			'pos'  => array( 'position', 'Positions', 'All positions' ),
			'loc'  => array( 'location', 'Locations', 'All locations' ),
			'lang' => array( 'language', 'Languages', 'All languages' ),
		) as $tng_key => $tng_f ) {
			$tng_opts = tng_filter_term_options( $tng_f[0], 'professional', $tng_f[2], $tng_ids );
			echo tng_filter_dropdown( $tng_key, $tng_f[1], $tng_opts, '', array( 'class' => 'button-label pro-filter__facet', 'icon' => $tng_down, 'ids' => $tng_ids, 'tax' => $tng_f[0] ) ); // phpcs:ignore
		}
		?>
		</div>
	</form>
</section>
