<?php
/**
 * acf/about-numbers — four counters on the teal texture (parallax),
 * staggered by CSS (nth-child). Values count up and keep a suffix ("250+").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_about_numbers' ) ) {
	return;
}

$tng_numbers = array_values(
	array_filter(
		(array) tng_field( 'numbers', array() ),
		static function ( $row ) {
			return isset( $row['value'] ) && '' !== $row['value'];
		}
	)
);
if ( ! $tng_numbers ) {
	return;
}
?>
<section class="about-numbers has-parallax" id="numbers" data-section="about-numbers" data-toggle="show_about_numbers">
	<img src="<?php echo esc_url( tng_img_url( 'background', tng_texture_url() ) ); ?>" alt="" class="parallax-img">
	<h2 class="visually-hidden">Our Numbers</h2>
	<ul class="about-numbers__list">
		<?php foreach ( array_slice( $tng_numbers, 0, 4 ) as $tng_n ) : ?>
		<li class="about-numbers__item">
			<p class="counter dark about-numbers__num"><?php echo esc_html( $tng_n['value'] ); ?></p>
			<p class="subhead dark about-numbers__label"><?php echo esc_html( isset( $tng_n['label'] ) ? $tng_n['label'] : '' ); ?></p>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
