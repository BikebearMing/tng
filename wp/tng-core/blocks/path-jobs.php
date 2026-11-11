<?php
/**
 * acf/path-jobs — "Opportunities for <career path>": the careers job rows,
 * one per open Job whose "Career path page" is this page (title + country
 * pill + arrow). Hidden when there are none.
 * Open question (2026-11-03): the user thinks this list may need to be
 * grouped / filtered by location; it is one flat list for now.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_path_jobs' ) ) {
	return;
}

$tng_jobs = get_posts(
	array(
		'post_type'      => 'job',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'   => 'career_path',
				'value' => (string) get_the_ID(),
			),
		),
	)
);
if ( ! $tng_jobs ) {
	return;
}
?>
<section class="careers-jobs lp-jobs" data-section="path-jobs" data-toggle="show_path_jobs">
	<p class="subhead careers-jobs__label"><?php echo esc_html( tng_field( 'label', 'Opportunities for' ) ); ?></p>
	<h2 class="h2 careers-jobs__heading"><?php echo esc_html( tng_field( 'heading', get_the_title() ) ); ?></h2>
	<ul class="careers-jobs__list">
		<?php foreach ( $tng_jobs as $tng_job ) : ?>
		<li>
			<a href="<?php echo esc_url( get_permalink( $tng_job ) ); ?>" class="careers-jobs__row">
				<span class="h4"><?php echo esc_html( get_the_title( $tng_job ) ); ?></span>
				<?php echo tng_country_pill( tng_post_country( $tng_job->ID ), 'job-hero__tag lp-jobs__tag', 'span' ); ?>
				<?php echo tng_arrow_circle( true ); ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
