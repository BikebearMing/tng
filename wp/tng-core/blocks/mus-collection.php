<?php
/**
 * acf/mus-collection — museum collection: full-width label, quote (.h2) +
 * source, text, image on the right level with the text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_museum_collection' ) ) {
	return;
}

$tng_quote  = tng_field( 'quote', '' );
$tng_source = tng_field( 'source', '' );
?>
<section class="mus-collection" id="collection" data-section="museum-collection" data-toggle="show_museum_collection">
	<p class="subhead pro-label mus-collection__label"><?php echo esc_html( tng_field( 'label', 'Collection' ) ); ?></p>
	<?php if ( $tng_quote ) : ?>
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
	<img src="<?php echo esc_url( tng_img_url( 'image', tng_asset( 'img/about-museum-collection.jpg' ) ) ); ?>" alt="" class="mus-collection__image" loading="lazy">
</section>
