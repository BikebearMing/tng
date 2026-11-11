<?php
/**
 * tng/term-successes — client successes on the teal texture. Location pages
 * stack the cards; industry / practice pages show them as a Blaze slider.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'successes' ) ) {
	return;
}

$tng_rows     = tng_term_successes( $tng_term );
$tng_location = 'location' === $tng_term->taxonomy;

$tng_card = static function ( $row, $tag ) {
	ob_start();
	?>
	<<?php echo $tag; // phpcs:ignore ?> class="loc-success">
		<h3 class="h2 loc-success__title"><?php echo esc_html( $row['title'] ); ?></h3>
		<?php if ( ! empty( $row['link_url'] ) ) : ?>
		<a href="<?php echo esc_url( $row['link_url'] ); ?>" class="button-label link-arrow loc-success__link">
			Read more
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
		<?php endif; ?>
		<?php if ( ! empty( $row['text'] ) ) : ?>
		<p class="body loc-success__text"><?php echo esc_html( $row['text'] ); ?></p>
		<?php endif; ?>
	</<?php echo $tag; // phpcs:ignore ?>>
	<?php
	return ob_get_clean();
};

if ( $tng_location ) :
	?>
<section class="loc-successes has-parallax" id="client-successes" data-section="loc-successes" data-toggle="show_loc_successes">
	<img src="<?php echo esc_url( tng_texture_url() ); ?>" alt="" class="parallax-img">
	<p class="subhead dark pro-label loc-successes__label">Client Successes</p>
	<ul class="loc-successes__list">
		<?php foreach ( $tng_rows as $tng_row ) { echo $tng_card( $tng_row, 'li' ); } // phpcs:ignore ?>
	</ul>
</section>
	<?php
	return;
endif;
?>
<section class="loc-successes svc-successes has-parallax" id="client-successes" data-section="svc-successes" data-toggle="show_svc_successes">
	<img src="<?php echo esc_url( tng_texture_url() ); ?>" alt="" class="parallax-img">
	<p class="subhead dark pro-label loc-successes__label">Client Successes</p>
	<div class="blaze-slider svc-successes__slider">
		<div class="blaze-container">
			<div class="blaze-track-container">
				<div class="blaze-track">
					<?php foreach ( $tng_rows as $tng_row ) { echo $tng_card( $tng_row, 'article' ); } // phpcs:ignore ?>
				</div>
			</div>
		</div>
		<?php if ( count( $tng_rows ) > 1 ) : ?>
		<div class="svc-successes__nav">
			<button type="button" class="blaze-prev careers-values__btn svc-successes__btn" aria-label="Previous"><svg viewBox="0 0 10 10" fill="none"><path d="M7 1L3 5l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
			<button type="button" class="blaze-next careers-values__btn svc-successes__btn" aria-label="Next"><?php echo tng_arrow( 2 ); ?></button>
		</div>
		<?php endif; ?>
	</div>
</section>
