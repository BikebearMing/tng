<?php
/**
 * acf/mus-cases — museum case studies: the service client-successes slider
 * (Blaze) on the teal texture; every card carries an image on the right.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_museum_cases' ) ) {
	return;
}

$tng_rows = array_values(
	array_filter(
		(array) tng_field( 'items', array() ),
		static function ( $row ) {
			return ! empty( $row['title'] );
		}
	)
);
if ( ! $tng_rows ) {
	return;
}

$tng_fallback = tng_asset( 'img/about-museum-case.jpg' );
?>
<section class="loc-successes svc-successes mus-cases has-parallax" id="case-studies" data-section="museum-cases" data-toggle="show_museum_cases">
	<img src="<?php echo esc_url( tng_img_url( 'background', tng_texture_url() ) ); ?>" alt="" class="parallax-img">
	<p class="subhead dark pro-label loc-successes__label"><?php echo esc_html( tng_field( 'label', 'Case Studies' ) ); ?></p>
	<div class="blaze-slider svc-successes__slider">
		<div class="blaze-container">
			<div class="blaze-track-container">
				<div class="blaze-track">
					<?php foreach ( $tng_rows as $tng_row ) : ?>
					<article class="loc-success loc-success--media">
						<h3 class="h2 loc-success__title"><?php echo esc_html( $tng_row['title'] ); ?></h3>
						<?php if ( ! empty( $tng_row['text'] ) ) : ?>
						<p class="body loc-success__text"><?php echo esc_html( $tng_row['text'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $tng_row['link_url'] ) ) : ?>
						<a href="<?php echo esc_url( $tng_row['link_url'] ); ?>" class="button-label link-arrow loc-success__link">
							Read more
							<?php echo tng_arrow( 1.5 ); // phpcs:ignore ?>
						</a>
						<?php endif; ?>
						<img src="<?php echo esc_url( tng_img_value( isset( $tng_row['image'] ) ? $tng_row['image'] : '', $tng_fallback ) ); ?>" alt="" class="loc-success__media" loading="lazy">
					</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php if ( count( $tng_rows ) > 1 ) : ?>
		<div class="svc-successes__nav">
			<button type="button" class="blaze-prev careers-values__btn svc-successes__btn" aria-label="Previous"><svg viewBox="0 0 10 10" fill="none"><path d="M7 1L3 5l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
			<button type="button" class="blaze-next careers-values__btn svc-successes__btn" aria-label="Next"><?php echo tng_arrow( 2 ); // phpcs:ignore ?></button>
		</div>
		<?php endif; ?>
	</div>
</section>
