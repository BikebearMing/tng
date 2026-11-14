<?php
/**
 * acf/archive-hero — insights / awards archive hero: the insight hero's frame,
 * title, intro and an optional link ("Subscribe to Updates").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_archive_hero' ) ) {
	return;
}

$tng_intro = tng_field( 'intro', '' );
$tng_label = tng_field( 'link_label', '' );
?>
<section class="in-hero ins-hero<?php echo $tng_label ? '' : ' ins-hero--no-link'; ?>" data-section="archive-hero" data-toggle="show_archive_hero">
	<?php echo tng_breadcrumbs( get_the_title(), tng_page_trail() ); ?>

	<div class="ins-hero__content">
		<h1 class="h1 dark"><?php echo esc_html( tng_field( 'title', get_the_title() ) ); ?></h1>
		<?php if ( $tng_intro ) : ?>
		<p class="body dark ins-hero__intro"><?php echo esc_html( $tng_intro ); ?></p>
		<?php endif; ?>
		<?php if ( $tng_label ) : ?>
		<a href="<?php echo esc_url( tng_link_or( tng_field( 'link_url', '#' ), tng_newsletter( 'form' ) ) ); ?>" class="button-label dark link-arrow ins-hero__link">
			<?php echo esc_html( $tng_label ); ?>
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
		<?php endif; ?>
	</div>

	<img src="<?php echo esc_url( tng_img_url( 'image', tng_bundled_img( 'page-' . get_post_field( 'post_name', get_the_ID() ) . '-hero.jpg', tng_texture_url() ) ) ); ?>" alt="" class="in-hero__image">
</section>
