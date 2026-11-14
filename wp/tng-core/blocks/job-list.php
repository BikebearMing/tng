<?php
/**
 * acf/job-list — every open Job post, A–Z, as the career path rows (title +
 * country pill + arrow), with country filter pills (All + each country with
 * open jobs and its count). The filter is a plain link: ?loc=<country slug>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_job_list' ) ) {
	return;
}

$tng_countries = tng_job_countries();
$tng_base      = get_permalink();
$tng_loc       = isset( $_GET['loc'] ) ? sanitize_title( wp_unslash( $_GET['loc'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$tng_active    = null;
$tng_total     = 0;
foreach ( $tng_countries as $tng_row ) {
	$tng_total += $tng_row[1];
	if ( $tng_row[0]->slug === $tng_loc ) {
		$tng_active = $tng_row[0];
	}
}

$tng_query = array(
	'post_type'      => 'job',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
);
if ( $tng_active ) {
	$tng_query['tax_query'] = array(
		array(
			'taxonomy'         => 'location',
			'terms'            => $tng_active->term_id,
			'include_children' => true,
		),
	);
}
$tng_jobs = get_posts( $tng_query );
?>
<section class="careers-jobs lp-jobs job-list" id="open-positions" data-section="job-list" data-toggle="show_job_list">
	<p class="subhead careers-jobs__label"><?php echo esc_html( tng_field( 'label', 'See Where You Fit in' ) ); ?></p>
	<h2 class="h2 careers-jobs__heading"><?php echo esc_html( tng_field( 'heading', 'Open Positions' ) ); ?></h2>
	<?php if ( count( $tng_countries ) > 1 ) : ?>
	<ul class="job-list__filter" aria-label="Filter by country">
		<li><a href="<?php echo esc_url( $tng_base . '#open-positions' ); ?>" class="job-list__pill"<?php echo $tng_active ? '' : ' aria-current="true"'; ?>>All <span class="job-list__count"><?php echo (int) $tng_total; ?></span></a></li>
		<?php foreach ( $tng_countries as $tng_row ) : list( $tng_term, $tng_count ) = $tng_row; $tng_flag = tng_location_flag( $tng_term ); ?>
		<li><a href="<?php echo esc_url( add_query_arg( 'loc', $tng_term->slug, $tng_base ) . '#open-positions' ); ?>" class="job-list__pill"<?php echo $tng_active && $tng_active->term_id === $tng_term->term_id ? ' aria-current="true"' : ''; ?>><?php if ( $tng_flag ) : ?><img src="<?php echo esc_url( $tng_flag ); ?>" alt=""><?php endif; ?><?php echo esc_html( $tng_term->name ); ?> <span class="job-list__count"><?php echo (int) $tng_count; ?></span></a></li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
	<?php if ( $tng_jobs ) : ?>
	<ul class="careers-jobs__list">
		<?php foreach ( $tng_jobs as $tng_job ) : ?>
		<li>
			<a href="<?php echo esc_url( get_permalink( $tng_job ) ); ?>" class="careers-jobs__row">
				<span class="h4"><?php echo esc_html( get_the_title( $tng_job ) ); ?></span>
				<?php echo tng_country_pill( tng_post_country( $tng_job->ID ), 'job-hero__tag lp-jobs__tag', 'span' ); // phpcs:ignore ?>
				<?php echo tng_arrow_circle( true ); // phpcs:ignore ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php else : ?>
	<p class="body job-list__empty"><?php echo esc_html( tng_field( 'empty_text', 'There are no open positions right now. Please check back soon.' ) ); ?></p>
	<?php endif; ?>
</section>
