<?php
/**
 * acf/archive-grid — insights / awards grid: active-filter chips, post cards
 * (3 across), count + "View more". Server-rendered from the query string;
 * main.js re-renders it in place from /wp-json/tng/v1/archive when the
 * filter bar changes and appends the next page on "View more".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_archive_grid' ) ) {
	return;
}

$tng_type   = 'award' === tng_field( 'source', 'insight' ) ? 'award' : 'insight';
$tng_size   = max( 3, (int) tng_field( 'page_size', 12 ) );
$tng_params = tng_archive_params( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
$tng_query  = tng_archive_query( $tng_type, $tng_params, 1, $tng_size );
$tng_total  = (int) $tng_query->found_posts;
$tng_base   = get_permalink();
$tng_chips  = tng_archive_chips( $tng_params, $tng_base );
$tng_count  = tng_show( 'show_count' );

$tng_class = 'ins-grid';
if ( ! $tng_chips ) {
	$tng_class .= $tng_count ? ' ins-grid--no-chips' : ' ins-grid--plain';
}
?>
<section class="<?php echo esc_attr( $tng_class ); ?>" id="results" data-section="archive-grid" data-toggle="show_archive_grid" data-type="<?php echo esc_attr( $tng_type ); ?>" data-page-size="<?php echo esc_attr( $tng_size ); ?>" data-total="<?php echo esc_attr( $tng_total ); ?>" data-params="<?php echo esc_attr( wp_json_encode( (object) $tng_params ) ); ?>" data-source="<?php echo esc_url( rest_url( 'tng/v1/archive' ) ); ?>" data-count="<?php echo $tng_count ? '1' : '0'; ?>">
	<?php if ( $tng_chips ) : ?>
	<ul class="ins-chips" aria-label="Active filters">
		<?php foreach ( $tng_chips as $tng_chip ) : ?>
		<li>
			<a href="<?php echo esc_url( $tng_chip[2] ); ?>" class="ins-chip" data-key="<?php echo esc_attr( $tng_chip[3] ); ?>" aria-label="<?php echo esc_attr( 'Remove filter: ' . $tng_chip[0] . ', ' . $tng_chip[1] ); ?>">
				<span class="ins-chip__key"><?php echo esc_html( $tng_chip[0] ); ?></span>
				<span class="ins-chip__value"><?php echo esc_html( $tng_chip[1] ); ?></span>
				<?php echo tng_icon_close(); ?>
			</a>
		</li>
		<?php endforeach; ?>
		<li>
			<a href="<?php echo esc_url( $tng_base . '#results' ); ?>" class="ins-chip ins-chip--reset">
				<span class="ins-chip__value">Reset</span>
				<?php echo tng_icon_close(); ?>
			</a>
		</li>
	</ul>
	<?php endif; ?>

	<?php if ( $tng_total ) : ?>
	<ul class="ins-grid__list">
		<?php echo tng_archive_cards( $tng_query ); // phpcs:ignore ?>
	</ul>
	<?php else : ?>
	<p class="body ins-grid__empty">No results match these filters.</p>
	<?php endif; ?>

	<div class="pro-grid__more ins-grid__more">
		<?php if ( $tng_count ) : ?>
		<p class="pro-grid__count"<?php echo $tng_total ? '' : ' hidden'; ?>>Showing <strong><?php echo esc_html( min( $tng_size, $tng_total ) ); ?></strong> out of <strong><?php echo esc_html( $tng_total ); ?></strong> results</p>
		<?php endif; ?>
		<button type="button" class="button-label link-arrow pro-grid__view-more"<?php echo $tng_total > $tng_size ? '' : ' hidden'; ?>>
			View more
			<?php echo tng_arrow_down(); ?>
		</button>
	</div>
	<p class="visually-hidden" role="status" aria-live="polite" data-status></p>
</section>
