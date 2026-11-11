<?php
/**
 * acf/about-creed — label + two columns: heading + bullet list.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_about_creed' ) ) {
	return;
}

$tng_cols = array_values(
	array_filter(
		(array) tng_field( 'columns', array() ),
		static function ( $row ) {
			return ! empty( $row['heading'] ) || ! empty( $row['list'] );
		}
	)
);
if ( ! $tng_cols ) {
	return;
}
?>
<section class="about-creed" id="creed" data-section="about-creed" data-toggle="show_about_creed">
	<p class="subhead pro-label about-creed__label"><?php echo esc_html( tng_field( 'label', 'Creed' ) ); ?></p>
	<?php foreach ( array_slice( $tng_cols, 0, 2 ) as $tng_col ) : ?>
	<div class="about-creed__col">
		<h2 class="h2"><?php echo esc_html( $tng_col['heading'] ); ?></h2>
		<div class="rich-text about-creed__list">
			<?php echo wp_kses_post( $tng_col['list'] ); ?>
		</div>
	</div>
	<?php endforeach; ?>
</section>
