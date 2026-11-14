<?php
/**
 * acf/overview — the top-label overview layout (label over a rule; heading on
 * the left, text from 729). Variants:
 *  - about: About overview
 *  - code:  Code of conduct (text + link under it)
 *  - path:  career path page overview
 *  - info:  About child pages (Pro Bono, CSR, museum); optional image at the
 *           foot of the heading column (CSR)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_overview' ) ) {
	return;
}

$tng_variant = tng_field( 'variant', 'about' );
$tng_classes = array(
	'about' => array( 'about-overview', 'about-overview', 'show_about_overview' ),
	'code'  => array( 'about-code', 'about-code', 'show_about_code' ),
	'path'  => array( 'lp-overview', 'path-overview', 'show_path_overview' ),
	'info'  => array( 'info-overview', 'info-overview', 'show_page_overview' ),
);
$tng_c       = isset( $tng_classes[ $tng_variant ] ) ? $tng_classes[ $tng_variant ] : $tng_classes['about'];
$tng_id      = sanitize_title( tng_field( 'section_id', '' ) );
$tng_heading = tng_field( 'heading', '' );
$tng_text    = tng_field( 'text', '' );
$tng_link    = tng_field( 'link_label', '' );
?>
<section class="svc-overview svc-overview--top-label <?php echo esc_attr( $tng_c[0] ); ?><?php echo tng_block_class( $block ); ?>"<?php echo $tng_id ? ' id="' . esc_attr( $tng_id ) . '"' : ''; ?> data-section="<?php echo esc_attr( $tng_c[1] ); ?>" data-toggle="<?php echo esc_attr( $tng_c[2] ); ?>">
	<p class="subhead pro-label svc-overview__label"><?php echo esc_html( tng_field( 'label', 'Overview' ) ); ?></p>
	<h2 class="h2 svc-overview__heading"><?php echo nl2br( esc_html( trim( $tng_heading ) ), false ); ?></h2>
	<?php if ( 'info' === $tng_variant && get_field( 'show_image' ) ) : ?>
	<img src="<?php echo esc_url( tng_img_url( 'image', tng_asset( 'img/about-csr-planting.jpg' ) ) ); ?>" alt="" class="info-overview__image">
	<?php endif; ?>
	<?php if ( 'code' === $tng_variant ) : ?>
	<div class="about-code__body">
		<div class="rich-text svc-overview__text">
			<?php echo wp_kses_post( $tng_text ); ?>
		</div>
		<?php if ( $tng_link ) : ?>
		<a href="<?php echo esc_url( tng_link_or( tng_field( 'link_url', '#' ), tng_code_of_conduct_url() ) ); ?>" class="button-label link-arrow about-code__link">
			<?php echo esc_html( $tng_link ); ?>
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
		<?php endif; ?>
	</div>
	<?php else : ?>
	<div class="rich-text svc-overview__text">
		<?php echo tng_heal_text_links( wp_kses_post( $tng_text ) ); // phpcs:ignore -- kses'd ?>
	</div>
	<?php endif; ?>
</section>
