<?php
/**
 * tng/module-findings — numbered key findings rows. Numbers come from row
 * order (01, 02, …).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_module_findings', $tng_id ) ) {
	return;
}

$tng_findings = tng_field( 'findings', array(), $tng_id );
if ( ! $tng_findings ) {
	return;
}
?>
<section class="in-findings" data-section="in-findings" data-toggle="show_module_findings">
	<h2 class="h3 in-findings__heading"><?php echo esc_html( tng_field( 'findings_heading', 'Key findings', $tng_id ) ); ?></h2>
	<ol class="in-findings__list">
		<?php foreach ( $tng_findings as $tng_i => $tng_row ) : ?>
		<li class="in-findings__item">
			<span class="in-findings__num" aria-hidden="true"><?php echo esc_html( str_pad( $tng_i + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
			<h3 class="h3 in-findings__title"><?php echo esc_html( $tng_row['title'] ); ?></h3>
			<div class="rich-text in-findings__text">
				<?php echo wp_kses_post( wpautop( $tng_row['text'] ) ); ?>
			</div>
		</li>
		<?php endforeach; ?>
	</ol>
</section>
