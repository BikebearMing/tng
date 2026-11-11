<?php
/**
 * acf/careers-jobs — one row per country (Location parent term) that has open
 * Job posts, with the count. Counts come from a per-term Job query.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers_jobs' ) ) {
	return;
}

$tng_countries = get_terms( array( 'taxonomy' => 'location', 'hide_empty' => false, 'parent' => 0 ) );
$tng_countries = is_wp_error( $tng_countries ) ? array() : $tng_countries;

$tng_rows = array();
foreach ( $tng_countries as $tng_term ) {
	$tng_count = ( new WP_Query(
		array(
			'post_type'      => 'job',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy'         => 'location',
					'terms'            => $tng_term->term_id,
					'include_children' => true,
				),
			),
		)
	) )->found_posts;
	if ( $tng_count > 0 ) {
		$tng_rows[] = array( $tng_term, $tng_count );
	}
}
?>
<section class="careers-jobs" id="job-opportunities" data-section="careers-jobs" data-toggle="show_careers_jobs">
	<p class="subhead careers-jobs__label"><?php echo esc_html( tng_field( 'label', 'See Where You Fit in' ) ); ?></p>
	<h2 class="h2 careers-jobs__heading"><?php echo esc_html( tng_field( 'heading', 'Job Opportunities' ) ); ?></h2>
	<ul class="careers-jobs__list">
		<?php foreach ( $tng_rows as $tng_row ) : list( $tng_term, $tng_count ) = $tng_row; ?>
		<li>
			<a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="careers-jobs__row">
				<span class="h4 careers-jobs__place"><?php echo esc_html( $tng_term->name ); ?></span>
				<span class="subhead careers-jobs__count"><?php echo esc_html( $tng_count . ' ' . ( 1 === $tng_count ? 'Job' : 'Jobs' ) . ' Available' ); ?></span>
				<?php echo tng_arrow_circle( true ); ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
	<a href="<?php echo esc_url( tng_field( 'view_all_url', '#' ) ); ?>" class="button-label link-arrow careers-jobs__link">
		<?php echo esc_html( tng_field( 'view_all_label', 'View all' ) ); ?>
		<?php echo tng_arrow( 1.5 ); ?>
	</a>
</section>
