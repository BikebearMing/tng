<?php
/**
 * acf/rows — the Module template's key findings rows on a page, in two styles:
 *  - steps:    numbered from row order (Recruitment Process)
 *  - timeline: a year per row instead of the number, .h2 heading, red tick
 *              on each rule (History)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_rows' ) ) {
	return;
}

$tng_rows = array_values(
	array_filter(
		(array) tng_field( 'rows', array() ),
		static function ( $row ) {
			return ! empty( $row['title'] ) || ! empty( $row['text'] );
		}
	)
);
if ( ! $tng_rows ) {
	return;
}
$tng_tl = 'timeline' === tng_field( 'variant', 'steps' );
$tng_br = array( 'br' => array() );
?>
<?php if ( $tng_tl ) : ?>
<section class="in-findings hist-timeline<?php echo tng_block_class( $block ); ?>" id="timeline" data-section="history-timeline" data-toggle="show_rows">
	<h2 class="h2 hist-timeline__heading"><?php echo esc_html( tng_field( 'heading', 'Timeline' ) ); ?></h2>
	<ol class="in-findings__list hist-timeline__list">
		<?php foreach ( $tng_rows as $tng_r ) : ?>
		<li class="in-findings__item hist-timeline__item">
			<p class="in-findings__num hist-timeline__year"><?php echo esc_html( isset( $tng_r['number'] ) ? $tng_r['number'] : '' ); ?></p>
			<h3 class="h3 in-findings__title"><?php echo wp_kses( $tng_r['title'], $tng_br ); ?></h3>
			<div class="rich-text in-findings__text">
				<?php echo wp_kses_post( $tng_r['text'] ); ?>
			</div>
		</li>
		<?php endforeach; ?>
	</ol>
</section>
<?php else : ?>
<section class="in-findings rec-steps<?php echo tng_block_class( $block ); ?>" data-section="recruitment-steps" data-toggle="show_rows">
	<h2 class="h3 in-findings__heading"><?php echo esc_html( tng_field( 'heading', '' ) ); ?></h2>
	<ol class="in-findings__list">
		<?php foreach ( $tng_rows as $tng_i => $tng_r ) : ?>
		<li class="in-findings__item">
			<span class="in-findings__num" aria-hidden="true"><?php echo esc_html( ! empty( $tng_r['number'] ) ? $tng_r['number'] : sprintf( '%02d', $tng_i + 1 ) ); ?></span>
			<h3 class="h3 in-findings__title"><?php echo wp_kses( $tng_r['title'], $tng_br ); ?></h3>
			<div class="rich-text in-findings__text">
				<?php echo wp_kses_post( $tng_r['text'] ); ?>
			</div>
		</li>
		<?php endforeach; ?>
	</ol>
</section>
<?php endif; ?>
