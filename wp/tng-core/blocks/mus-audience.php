<?php
/**
 * acf/mus-audience — museum audiences, 2 x 2 straight under the overview:
 * rule, .h3 title, 16/22 text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_museum_audiences' ) ) {
	return;
}

$tng_items = array_values(
	array_filter(
		(array) tng_field( 'items', array() ),
		static function ( $row ) {
			return ! empty( $row['title'] ) || ! empty( $row['text'] );
		}
	)
);
if ( ! $tng_items ) {
	return;
}
?>
<section data-section="museum-audiences" data-toggle="show_museum_audiences">
	<ul class="mus-audience">
		<?php foreach ( $tng_items as $tng_item ) : ?>
		<li class="mus-audience__item">
			<h3 class="h3 mus-audience__title"><?php echo esc_html( isset( $tng_item['title'] ) ? $tng_item['title'] : '' ); ?></h3>
			<div class="rich-text mus-audience__text"><?php echo wp_kses_post( isset( $tng_item['text'] ) ? $tng_item['text'] : '' ); ?></div>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
