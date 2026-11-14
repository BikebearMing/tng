<?php
/**
 * acf/archive-filter — the insights / awards filter bar (the compact
 * professionals filter). A GET form, so it works without JS: "Search by
 * Name" (q) and "Search by Professionals" (pro) reload the page with the
 * filter, the other filters ride along as hidden inputs. With JS (main.js):
 * the professional field is a type-ahead over the roster, Date (year) and the
 * facets open panels, and every change swaps the grid below in place through
 * /tng/v1/archive with the state in the URL (inc/archive.php lists the keys).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_archive_filter' ) ) {
	return;
}

$tng_params = tng_archive_params( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
$tng_type   = tng_archive_page_type();
$tng_cur    = function ( $k ) use ( $tng_params ) {
	return isset( $tng_params[ $k ] ) ? $tng_params[ $k ] : '';
};
$tng_search = '<svg viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.8"/><path d="M12 12l4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$tng_down   = '<span class="pro-filter__chevron" aria-hidden="true"><svg viewBox="0 0 10 10" fill="none"><path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
$tng_cal    = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 1h2v2h6V1h2v2h2.5A1.5 1.5 0 0 1 19 4.5v13a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 1 17.5v-13A1.5 1.5 0 0 1 2.5 3H5V1zm-2 7v9h14V8H3z"/></svg>';

// A ?pro= slug shows the person's name; the type-ahead keeps the slug
$tng_pro_name = '' !== $tng_cur( 'pro' ) ? tng_archive_pro_label( $tng_cur( 'pro' ) ) : '';
$tng_pro_slug = $tng_pro_name !== $tng_cur( 'pro' ) ? $tng_cur( 'pro' ) : '';

$tng_years = array( '' => 'Any year' );
foreach ( tng_filter_years( $tng_type ) as $tng_y ) {
	$tng_years[ (string) $tng_y ] = (string) $tng_y;
}

// Awards have no insight category, so their bar has no Resources facet (4 columns)
$tng_facets = array();
if ( 'insight' === $tng_type ) {
	$tng_facets['res'] = array( 'Resources', tng_filter_term_options( 'insight_category', $tng_type, 'All resources' ), '' );
}
$tng_facets['sort'] = array( 'Sort by Relevance', array( '' => 'Relevance', 'newest' => 'Newest', 'oldest' => 'Oldest' ), 'Sort by ' );
$tng_facets['ind']  = array( 'Industries', tng_filter_term_options( 'industry', $tng_type, 'All industries' ), '' );
$tng_facets['prac'] = array( 'Practices', tng_filter_term_options( 'practice', $tng_type, 'All practices' ), '' );
$tng_facets['loc']  = array( 'Locations', tng_filter_term_options( 'location', $tng_type, 'All locations' ), '' );
?>
<section class="pro-filter ins-filter" data-section="archive-filter" data-toggle="show_archive_filter">
	<form class="pro-filter__form" role="search" method="get" action="<?php echo esc_url( get_permalink() . '#results' ); ?>">
		<?php
		// keep the other filters when searching without JS
		foreach ( array( 'date', 'res', 'ind', 'prac', 'loc', 'lang', 'sort' ) as $tng_keep ) :
			if ( '' !== $tng_cur( $tng_keep ) ) :
				?>
		<input type="hidden" name="<?php echo esc_attr( $tng_keep ); ?>" value="<?php echo esc_attr( $tng_cur( $tng_keep ) ); ?>">
				<?php
			endif;
		endforeach;
		?>
		<div class="ins-filter__bar">
			<div class="pro-filter__search ins-filter__field">
				<label class="visually-hidden" for="ins-search">Search by name</label>
				<input type="search" id="ins-search" name="q" placeholder="Search by Name" value="<?php echo esc_attr( $tng_cur( 'q' ) ); ?>">
				<button type="submit" class="pro-filter__submit" aria-label="Search"><?php echo $tng_search; // phpcs:ignore ?></button>
			</div>
			<div class="pro-filter__search ins-filter__field">
				<label class="visually-hidden" for="ins-search-pro">Search by professional</label>
				<input type="search" id="ins-search-pro" name="pro" placeholder="Search by Professionals" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="flt-pro" data-roster="<?php echo esc_url( rest_url( 'tng/v1/roster' ) ); ?>" data-slug="<?php echo esc_attr( $tng_pro_slug ); ?>" data-name="<?php echo esc_attr( $tng_pro_name ); ?>" value="<?php echo esc_attr( $tng_pro_name ); ?>">
				<button type="submit" class="pro-filter__submit" aria-label="Search"><?php echo $tng_search; // phpcs:ignore ?></button>
				<ul class="flt-panel flt-panel--pros" id="flt-pro" role="listbox" aria-label="Professionals" data-lenis-prevent hidden></ul>
			</div>
			<?php echo tng_filter_dropdown( 'date', 'Date', $tng_years, $tng_cur( 'date' ), array( 'class' => 'ins-filter__date', 'panel_class' => 'flt-panel--years', 'icon' => $tng_cal ) ); // phpcs:ignore ?>
		</div>

		<div class="pro-filter__facets<?php echo count( $tng_facets ) < 5 ? ' pro-filter__facets--four' : ''; ?>">
			<?php
			foreach ( $tng_facets as $tng_key => $tng_f ) {
				echo tng_filter_dropdown( $tng_key, $tng_f[0], $tng_f[1], $tng_cur( $tng_key ), array( 'prefix' => $tng_f[2], 'icon' => $tng_down ) ); // phpcs:ignore
			}
			?>
		</div>
	</form>
</section>
