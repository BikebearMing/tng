<?php
/**
 * tng/job-summary — "Position Summary": groups of heading + bullet list.
 * Falls back to the post content (as one group) for jobs without groups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id     = get_the_ID();
$tng_groups = tng_field( 'responsibilities', array(), $tng_id );
$tng_groups = array_values(
	array_filter(
		is_array( $tng_groups ) ? $tng_groups : array(),
		static function ( $row ) {
			return ! empty( $row['heading'] ) || ! empty( $row['list'] );
		}
	)
);

if ( ! $tng_groups ) {
	$tng_content = trim( (string) get_post_field( 'post_content', $tng_id ) );
	if ( '' === $tng_content ) {
		return;
	}
	$tng_groups = array(
		array(
			'heading' => '',
			'list'    => apply_filters( 'the_content', $tng_content ),
		),
	);
}
?>
<section class="job-summary" data-section="job-summary">
	<p class="subhead pro-label">Position Summary</p>
	<?php foreach ( $tng_groups as $tng_group ) : ?>
	<div class="job-summary__group">
		<h2 class="h3 job-summary__heading"><?php echo esc_html( $tng_group['heading'] ); ?></h2>
		<div class="rich-text job-summary__list">
			<?php echo wp_kses_post( $tng_group['list'] ); ?>
		</div>
	</div>
	<?php endforeach; ?>
</section>
