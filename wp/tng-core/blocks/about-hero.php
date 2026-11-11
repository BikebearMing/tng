<?php
/**
 * acf/about-hero — the insight hero's frame; title + intro on the bottom.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_about_hero' ) ) {
	return;
}

$tng_intro = tng_field( 'intro', '' );
?>
<section class="in-hero about-hero" data-section="about-hero" data-toggle="show_about_hero">
	<?php echo tng_breadcrumbs( get_the_title(), tng_page_trail() ); ?>

	<div class="about-hero__content">
		<h1 class="h1 dark about-hero__title"><?php echo esc_html( tng_field( 'title', get_the_title() ) ); ?></h1>
		<?php if ( $tng_intro ) : ?>
		<p class="body dark about-hero__intro"><?php echo esc_html( $tng_intro ); ?></p>
		<?php endif; ?>
	</div>

	<img src="<?php echo esc_url( tng_img_url( 'image', tng_bundled_img( array( 'page-' . get_post_field( 'post_name', get_the_ID() ) . '-hero.jpg', 'page-about-hero.jpg' ) ) ) ); ?>" alt="" class="in-hero__image">
</section>
