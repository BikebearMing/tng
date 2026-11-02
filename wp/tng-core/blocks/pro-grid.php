<?php
/**
 * acf/pro-grid — the professionals grid. The first page renders server-side
 * (so reveals fire like the static page); tng-wp.js takes over for search,
 * alphabet and "View more" using the roster endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_pro_grid' ) ) {
	return;
}

$tng_page_size = (int) tng_field( 'page_size', 15 );
if ( $tng_page_size < 1 ) {
	$tng_page_size = 15;
}

$tng_total = (int) wp_count_posts( 'professional' )->publish;
$tng_posts = get_posts(
	array(
		'post_type'      => 'professional',
		'posts_per_page' => $tng_page_size,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>
<section class="pro-grid" data-section="pro-grid" data-toggle="show_pro_grid" data-page-size="<?php echo esc_attr( $tng_page_size ); ?>">
	<ul class="pro-grid__list">
		<?php foreach ( $tng_posts as $tng_p ) { echo tng_pro_card( $tng_p ); } // phpcs:ignore ?>
	</ul>

	<div class="pro-grid__more">
		<p class="pro-grid__count">Showing <strong><?php echo esc_html( min( $tng_page_size, $tng_total ) ); ?></strong> out of <strong><?php echo esc_html( $tng_total ); ?></strong> results</p>
		<button type="button" class="button-label link-arrow pro-grid__view-more"<?php echo $tng_total <= $tng_page_size ? ' hidden' : ''; ?>>
			View more
			<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
		</button>
	</div>
</section>
