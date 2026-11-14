<?php
/**
 * acf/mus-collection — museum collection: full-width label, quote (.h2) +
 * source, text, image on the right level with the text. With a heading
 * (Textile Collection "History") the heading replaces the quote and the
 * section sits straight under the previous one (--heading).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_museum_collection' ) ) {
	return;
}

$tng_label   = tng_field( 'label', 'Collection' );
$tng_heading = tng_field( 'heading', '' );
$tng_quote   = tng_field( 'quote', '' );
$tng_source  = tng_field( 'source', '' );
?>
<section class="mus-collection<?php echo $tng_heading ? ' mus-collection--heading' : ''; ?>" id="<?php echo esc_attr( sanitize_title( $tng_label ) ); ?>" data-section="museum-collection" data-toggle="show_museum_collection">
	<p class="subhead pro-label mus-collection__label"><?php echo esc_html( $tng_label ); ?></p>
	<?php if ( $tng_heading ) : ?>
	<h2 class="h2 mus-collection__heading"><?php echo esc_html( $tng_heading ); ?></h2>
	<?php elseif ( $tng_quote ) : ?>
	<figure class="mus-collection__quote">
		<blockquote class="h2"><?php echo esc_html( $tng_quote ); ?></blockquote>
		<?php if ( $tng_source ) : ?>
		<figcaption class="body"><?php echo esc_html( $tng_source ); ?></figcaption>
		<?php endif; ?>
	</figure>
	<?php endif; ?>
	<div class="rich-text mus-collection__text">
		<?php echo wp_kses_post( tng_field( 'text', '' ) ); ?>
	</div>
	<img src="<?php echo esc_url( tng_img_url( 'image', tng_bundled_img( 'about-' . get_post_field( 'post_name', get_the_ID() ) . '-' . sanitize_title( $tng_label ) . '.jpg', tng_asset( 'img/about-museum-collection.jpg' ) ) ) ); ?>" alt="" class="mus-collection__image" loading="lazy">
</section>
