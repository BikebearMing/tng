<?php
/**
 * tng/job-cta — "Ready to join us?" panel (teal, parallax) with three cards:
 * the recruitment process (settings link), the job's career path page, and
 * the job's country page. A card without a target is left out.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! $tng_id || 'job' !== get_post_type( $tng_id ) ) {
	return;
}

$tng_email = tng_field( 'apply_email', '', $tng_id );
$tng_email = $tng_email ? $tng_email : tng_field( 'apply_email', 'recruit@tilleke.com', 'option' );

$tng_cards = array(
	array(
		tng_field( 'process_title', 'Curious about our<br />Recruitment Process?', 'option' ),
		tng_field( 'process_label', 'Learn More', 'option' ),
		tng_field( 'process_url', '#', 'option' ),
	),
);

$tng_path = (int) tng_field( 'career_path', 0, $tng_id );
if ( $tng_path && 'publish' === get_post_status( $tng_path ) ) {
	$tng_cards[] = array(
		tng_field( 'role_title', 'Learn more<br />about your role', 'option' ),
		get_the_title( $tng_path ),
		get_permalink( $tng_path ),
	);
}

$tng_country = tng_post_country( $tng_id );
if ( $tng_country ) {
	$tng_cards[] = array(
		str_replace( '%s', esc_html( $tng_country->name ), tng_field( 'location_title', 'Learn more<br />about %s', 'option' ) ),
		$tng_country->name . ' Office',
		tng_term_link( $tng_country ),
	);
}
?>
<section class="job-cta has-parallax" data-section="job-cta">
	<img src="<?php echo esc_url( tng_img_url( 'cta_background', tng_texture_url(), 'option' ) ); ?>" alt="" class="parallax-img">
	<h2 class="h2 dark job-cta__heading"><?php echo esc_html( tng_field( 'cta_heading', 'Ready to join us?', 'option' ) ); ?></h2>
	<p class="body dark job-cta__text"><?php echo esc_html( tng_field( 'apply_text', 'Please send the CV to:', 'option' ) ); ?> <a href="mailto:<?php echo esc_attr( $tng_email ); ?>" class="job-cta__email"><?php echo esc_html( $tng_email ); ?></a></p>
	<ul class="job-cta__list">
		<?php foreach ( $tng_cards as $tng_card ) : ?>
		<li>
			<a href="<?php echo esc_url( $tng_card[2] ? $tng_card[2] : '#' ); ?>" class="job-cta__card">
				<h3 class="h3 job-cta__title"><?php echo wp_kses( $tng_card[0], array( 'br' => array() ) ); ?></h3>
				<span class="link-arrow job-cta__link">
					<?php echo esc_html( $tng_card[1] ); ?>
					<?php echo tng_arrow( 1.5 ); ?>
				</span>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
