<?php
/**
 * acf/services-index — the Services page directory: every top-level term of
 * the chosen taxonomy (industry or practice), A–Z, as "More from" rows with
 * red arrows, linking to the term pages. Heading sticks under the header.
 * Industries sit on white (Additional CSS class svc-index--light).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_services_index' ) ) {
	return;
}

$tng_tax   = 'practice' === tng_field( 'taxonomy', 'industry' ) ? 'practice' : 'industry';
$tng_terms = get_terms( array( 'taxonomy' => $tng_tax, 'hide_empty' => false, 'parent' => 0, 'orderby' => 'name' ) );
if ( is_wp_error( $tng_terms ) || ! $tng_terms ) {
	return;
}
$tng_id = 'practice' === $tng_tax ? 'practices' : 'industries';
?>
<section class="more-from svc-index<?php echo tng_block_class( $block ); ?>" id="<?php echo esc_attr( $tng_id ); ?>" data-section="services-<?php echo esc_attr( $tng_id ); ?>" data-toggle="show_services_index">
	<h2 class="h2 more-from__title svc-index__title"><?php echo esc_html( tng_field( 'heading', 'practice' === $tng_tax ? 'Practices' : 'Industries' ) ); ?></h2>
	<ul class="more-from__list">
		<?php foreach ( $tng_terms as $tng_term ) : ?>
		<li><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="h3 more-from__link"><?php echo esc_html( $tng_term->name ); ?><?php echo tng_arrow_circle( true ); // phpcs:ignore ?></a></li>
		<?php endforeach; ?>
	</ul>
</section>
