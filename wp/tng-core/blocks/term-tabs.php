<?php
/**
 * tng/term-tabs — "On this page" bar on location / industry / practice pages.
 * Only sections that render get a tab (tng_term_section_visible). No tabs on
 * a subpractice (child term), as in the mock.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || tng_is_subterm( $tng_term ) ) {
	return;
}

$tng_order = 'location' === $tng_term->taxonomy
	? array( 'overview', 'professionals', 'insights', 'successes', 'experience', 'awards' )
	: array( 'overview', 'successes', 'experience', 'professionals', 'insights', 'awards' );

$tng_meta = array(
	'overview'      => array( '#overview', 'Overview' ),
	'professionals' => array( '#professionals', 'Professionals' ),
	'insights'      => array( '#insights', 'Insights' ),
	'successes'     => array( '#client-successes', 'Client Successes' ),
	'experience'    => array( '#experience', tng_term_experience_label( $tng_term ) ),
	'awards'        => array( '#awards', 'Awards & Rankings' ),
);

$tng_tabs = array();
foreach ( $tng_order as $tng_section ) {
	if ( tng_term_section_visible( $tng_term, $tng_section ) ) {
		$tng_tabs[] = $tng_meta[ $tng_section ];
	}
}
if ( ! $tng_tabs ) {
	return;
}
?>
<nav class="pro-tabs pro-tabs--six" aria-label="On this page" data-section="pro-tabs">
	<p class="pro-tabs__label">On this page</p>
	<ul class="pro-tabs__list">
		<?php foreach ( $tng_tabs as $tng_i => $tng_tab ) : ?>
		<li><a href="<?php echo esc_attr( $tng_tab[0] ); ?>" class="button-label pro-tabs__link<?php echo 0 === $tng_i ? ' is-active' : ''; ?>"<?php echo 0 === $tng_i ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $tng_tab[1] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
