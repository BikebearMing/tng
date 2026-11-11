<?php
/**
 * tng/job-hero — job single hero (light gradient, navy header): back link to
 * the careers job list, title, country pill, department, apply card.
 * Apply card text comes from Jobs → Job page settings; the job's own apply
 * email overrides the default.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! $tng_id || 'job' !== get_post_type( $tng_id ) ) {
	return;
}

$tng_country = tng_post_country( $tng_id );
$tng_dept    = tng_field( 'department', '', $tng_id );
$tng_email   = tng_field( 'apply_email', '', $tng_id );
$tng_email   = $tng_email ? $tng_email : tng_field( 'apply_email', 'recruit@tilleke.com', 'option' );
?>
<section class="job-hero" data-section="job-hero">
	<a href="<?php echo esc_url( tng_page_url( 'careers' ) . '#job-opportunities' ); ?>" class="link-arrow job-hero__back">
		<?php echo tng_arrow_left(); ?>
		Back to Job Opportunities
	</a>

	<h1 class="h1 job-hero__title"><?php echo esc_html( get_the_title( $tng_id ) ); ?></h1>
	<?php echo tng_country_pill( $tng_country ); ?>

	<?php if ( $tng_dept ) : ?>
	<dl class="job-hero__meta">
		<dt class="job-hero__label">Department</dt>
		<dd class="job-hero__value"><?php echo esc_html( $tng_dept ); ?></dd>
	</dl>
	<?php endif; ?>

	<aside class="job-apply" aria-label="How to apply">
		<h2 class="h3 job-apply__title"><?php echo esc_html( tng_field( 'apply_heading', 'Ready to join us?', 'option' ) ); ?></h2>
		<p class="job-apply__text"><?php echo esc_html( tng_field( 'apply_text', 'Please send the CV to:', 'option' ) ); ?><br><a href="mailto:<?php echo esc_attr( $tng_email ); ?>" class="job-apply__email"><?php echo esc_html( $tng_email ); ?></a></p>
	</aside>
</section>
