<?php
/**
 * acf/page-tabs — "On this page" bar for block pages. Each tab points at a
 * section anchor; main.js skips tabs whose section isn't on the page.
 * Five tabs use the About columns, six the term pages' columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_page_tabs' ) ) {
	return;
}

$tng_tabs = array_values(
	array_filter(
		(array) tng_field( 'tabs', array() ),
		static function ( $row ) {
			return ! empty( $row['label'] ) && ! empty( $row['anchor'] );
		}
	)
);
if ( ! $tng_tabs ) {
	return;
}

$tng_mod = array(
	5 => ' pro-tabs--about',
	6 => ' pro-tabs--six',
);
$tng_mod = isset( $tng_mod[ count( $tng_tabs ) ] ) ? $tng_mod[ count( $tng_tabs ) ] : '';
?>
<nav class="pro-tabs<?php echo esc_attr( $tng_mod ); ?>" aria-label="On this page" data-section="pro-tabs">
	<p class="pro-tabs__label">On this page</p>
	<ul class="pro-tabs__list">
		<?php foreach ( $tng_tabs as $tng_i => $tng_tab ) : ?>
		<li><a href="#<?php echo esc_attr( ltrim( $tng_tab['anchor'], '#' ) ); ?>" class="button-label pro-tabs__link<?php echo 0 === $tng_i ? ' is-active' : ''; ?>"<?php echo 0 === $tng_i ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $tng_tab['label'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
