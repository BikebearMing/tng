<?php
/**
 * tng/job-intro — the job summary, .h3 centred. Hidden when empty.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_summary = tng_field( 'summary', '', get_the_ID() );
if ( ! $tng_summary ) {
	return;
}
?>
<section class="job-intro" data-section="job-intro">
	<p class="h3 job-intro__text"><?php echo esc_html( $tng_summary ); ?></p>
</section>
