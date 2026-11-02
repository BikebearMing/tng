<?php
/**
 * acf/careers-paths — three link cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_career_paths' ) ) {
	return;
}

$tng_paths = tng_field( 'paths', array() );
if ( ! $tng_paths ) {
	$tng_default_text = 'Lawyers at Tilleke are the benchmark of world-class legal practice in Southeast Asia.';
	$tng_paths        = array(
		array( 'title' => 'Legal Professionals', 'text' => $tng_default_text, 'url' => '#' ),
		array( 'title' => 'Operations Professionals', 'text' => $tng_default_text, 'url' => '#' ),
		array( 'title' => 'Internship', 'text' => $tng_default_text, 'url' => '#' ),
	);
}
?>
<section class="career-paths" data-section="career-paths" data-toggle="show_career_paths">
	<ul class="career-paths__list">
		<?php foreach ( $tng_paths as $tng_row ) : ?>
		<li>
			<a href="<?php echo esc_url( ! empty( $tng_row['url'] ) ? $tng_row['url'] : '#' ); ?>" class="career-card">
				<h2 class="h3 career-card__title"><?php echo esc_html( $tng_row['title'] ); ?></h2>
				<?php echo tng_arrow_circle( true ); ?>
				<p class="body career-card__text"><?php echo esc_html( $tng_row['text'] ); ?></p>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
