<?php
/**
 * acf/careers-values — heading + Blaze slider of value cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers_values' ) ) {
	return;
}

$tng_slides = tng_field( 'slides', array() );
if ( ! $tng_slides ) {
	$tng_default_text = "Our clients operate in a dynamic global marketplace, and so do we. As the premier law firm in Southeast Asia, we work with many of the world's top companies on the region's biggest deals and most crucial disputes. Together, we are proud to play an important role in growing our clients' businesses, and developing the vibrant economies of Southeast Asia.";
	$tng_slides       = array(
		array( 'title' => 'Market-Leading Practice', 'text' => $tng_default_text, 'icon' => '' ),
		array( 'title' => 'Regional Reach', 'text' => $tng_default_text, 'icon' => '' ),
		array( 'title' => 'Growing Together', 'text' => $tng_default_text, 'icon' => '' ),
	);
}
?>
<section class="careers-values" data-section="careers-values" data-toggle="show_careers_values">
	<h2 class="h2 careers-values__heading"><?php echo esc_html( tng_field( 'heading', 'Together at Tilleke & Gibbins' ) ); ?></h2>

	<div class="blaze-slider careers-values__slider">
		<div class="blaze-container">
			<div class="blaze-track-container">
				<div class="blaze-track">
			<?php
			foreach ( $tng_slides as $tng_row ) :
				$tng_icon = ! empty( $tng_row['icon'] ) ? $tng_row['icon'] : '';
				if ( is_array( $tng_icon ) ) {
					$tng_icon = $tng_icon['url'];
				}
				if ( ! $tng_icon ) {
					$tng_icon = tng_asset( 'careers-medal.svg' );
				}
				?>
			<article class="careers-values__slide">
				<h3 class="h3 careers-values__title"><?php echo esc_html( $tng_row['title'] ); ?></h3>
				<div class="rich-text careers-values__text">
					<p><?php echo esc_html( $tng_row['text'] ); ?></p>
				</div>
				<img src="<?php echo esc_url( $tng_icon ); ?>" alt="" class="careers-values__icon" loading="lazy">
			</article>
			<?php endforeach; ?>
				</div>
			</div>
		</div>
		<div class="careers-values__nav">
			<button type="button" class="blaze-prev careers-values__btn" aria-label="Previous"><svg viewBox="0 0 10 10" fill="none"><path d="M7 1L3 5l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
			<button type="button" class="blaze-next careers-values__btn" aria-label="Next"><svg viewBox="0 0 10 10" fill="none"><path d="M3 1l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
		</div>
	</div>
</section>
