<?php
/**
 * acf/text-groups — the job single's "Position Summary" layout on a page:
 * label + rule, then groups of heading | text between rules. Used for the
 * Internships page's "Internship Programs" and the desks' key contacts (a
 * group with a professional: heading = their name linked to their page,
 * optional position under it). Page modifier (lp-programs, desk-contacts) =
 * the block's Additional CSS class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_text_groups' ) ) {
	return;
}

$tng_groups = array_values(
	array_filter(
		(array) tng_field( 'groups', array() ),
		static function ( $row ) {
			return ! empty( $row['heading'] ) || ! empty( $row['professional'] ) || ! empty( $row['text'] );
		}
	)
);
if ( ! $tng_groups ) {
	return;
}
$tng_people = false !== strpos( tng_block_class( $block ), 'desk-contacts' );
?>
<section class="job-summary<?php echo tng_block_class( $block ); ?>" data-section="<?php echo $tng_people ? 'key-contacts' : 'path-programs'; ?>" data-toggle="show_text_groups">
	<p class="subhead pro-label"><?php echo esc_html( tng_field( 'label', '' ) ); ?></p>
	<?php
	foreach ( $tng_groups as $tng_g ) :
		$tng_pid  = ! empty( $tng_g['professional'] ) ? (int) ( is_object( $tng_g['professional'] ) ? $tng_g['professional']->ID : $tng_g['professional'] ) : 0;
		$tng_pid  = $tng_pid && 'publish' === get_post_status( $tng_pid ) ? $tng_pid : 0;
		$tng_head = ! empty( $tng_g['heading'] ) ? $tng_g['heading'] : ( $tng_pid ? get_the_title( $tng_pid ) : '' );
		$tng_head = $tng_pid ? '<a href="' . esc_url( get_permalink( $tng_pid ) ) . '">' . esc_html( $tng_head ) . '</a>' : esc_html( $tng_head );
		$tng_pos  = isset( $tng_g['position'] ) ? $tng_g['position'] : '';
		?>
	<div class="job-summary__group">
		<?php if ( $tng_pid || $tng_pos ) : ?>
		<div>
			<h3 class="h3 job-summary__heading"><?php echo $tng_head; // phpcs:ignore -- escaped above ?></h3>
			<?php if ( $tng_pos ) : ?>
			<p class="subhead desk-contacts__position"><?php echo esc_html( $tng_pos ); ?></p>
			<?php endif; ?>
		</div>
		<?php else : ?>
		<h3 class="h3 job-summary__heading"><?php echo $tng_head; // phpcs:ignore -- escaped above ?></h3>
		<?php endif; ?>
		<div class="rich-text job-summary__list">
			<?php echo wp_kses_post( isset( $tng_g['text'] ) ? $tng_g['text'] : '' ); ?>
		</div>
	</div>
	<?php endforeach; ?>
</section>
