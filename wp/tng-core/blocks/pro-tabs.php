<?php
/**
 * tng/pro-tabs — "On this page" bar. A tab is left out when its section is
 * toggled off or empty (main.js also skips tabs whose target is missing).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id   = get_the_ID();
$tng_tabs = array();

if ( tng_show( 'show_pro_overview', $tng_id ) ) {
	$tng_tabs[] = array( '#overview', 'Overview' );
}
if ( tng_show( 'show_pro_experience', $tng_id ) && tng_field( 'experience', '', $tng_id ) ) {
	$tng_tabs[] = array( '#experience', 'Experience' );
}
if ( tng_show( 'show_pro_insights', $tng_id ) && tng_posts_related_to_professional( 'insight', $tng_id, 1 ) ) {
	$tng_tabs[] = array( '#insights', 'Insights' );
}
if ( tng_show( 'show_pro_awards', $tng_id ) && tng_posts_related_to_professional( 'award', $tng_id, 1 ) ) {
	$tng_tabs[] = array( '#awards', 'Awards' );
}

if ( ! $tng_tabs ) {
	return;
}
?>
<nav class="pro-tabs" aria-label="On this page" data-section="pro-tabs">
	<p class="pro-tabs__label">On this page</p>
	<ul class="pro-tabs__list">
		<?php foreach ( $tng_tabs as $tng_i => $tng_tab ) : ?>
		<li><a href="<?php echo esc_attr( $tng_tab[0] ); ?>" class="button-label pro-tabs__link<?php echo 0 === $tng_i ? ' is-active' : ''; ?>"<?php echo 0 === $tng_i ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $tng_tab[1] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
