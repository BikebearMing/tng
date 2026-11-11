<?php
/**
 * tng/term-subs — "Subpractices": cards for the term's child terms (image =
 * the child's card_image field, else a placeholder).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term || ! tng_term_section_visible( $tng_term, 'subpractices' ) ) {
	return;
}

$tng_kids = get_terms(
	array(
		'taxonomy'   => $tng_term->taxonomy,
		'parent'     => $tng_term->term_id,
		'hide_empty' => false,
		'orderby'    => 'name',
	)
);
if ( is_wp_error( $tng_kids ) || ! $tng_kids ) {
	return;
}
?>
<section class="svc-subs" data-section="svc-subpractices" data-toggle="show_svc_subpractices">
	<p class="subhead pro-label">Subpractices</p>
	<ul class="svc-subs__list">
		<?php foreach ( $tng_kids as $tng_kid ) : ?>
		<li>
			<a href="<?php echo esc_url( tng_term_link( $tng_kid ) ); ?>" class="svc-sub-card">
				<img src="<?php echo esc_url( tng_img_url( 'card_image', tng_bundled_img( array( $tng_kid->taxonomy . '-' . $tng_kid->slug . '-card.jpg', 'sub-card.jpg' ) ), tng_term_ref( $tng_kid ) ) ); ?>" alt="" class="svc-sub-card__img" loading="lazy">
				<h3 class="h3 svc-sub-card__title"><?php echo esc_html( $tng_kid->name ); ?></h3>
				<?php echo tng_arrow_circle( true ); ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
