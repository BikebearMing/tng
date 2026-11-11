<?php
/**
 * acf/careers-hero — navy hero with image (insight hero frame).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_careers_hero' ) ) {
	return;
}

$tng_title = tng_field( 'title', get_the_title() );
?>
<section class="careers-hero" data-section="careers-hero" data-toggle="show_careers_hero">
	<?php echo tng_breadcrumbs( $tng_title ); ?>

	<div class="careers-hero__content">
		<h1 class="h1 dark"><?php echo esc_html( $tng_title ); ?></h1>
		<p class="body dark careers-hero__intro"><?php echo esc_html( tng_field( 'intro', 'We are always seeking talented lawyers, operations professionals, and interns to join our amazing team. We offer you the chance to join a well-established firm with a reputation for excellence, engage with an unrivaled client base, and develop your international career. At Tilleke & Gibbins, we achieve great things together.' ) ); ?></p>
		<?php $tng_link = tng_field( 'link_url', '#' ); ?>
		<a href="<?php echo esc_url( '#' === $tng_link ? '#job-opportunities' : $tng_link ); ?>" class="button-label dark link-arrow careers-hero__link">
			<?php echo esc_html( tng_field( 'link_label', 'All job opportunities' ) ); ?>
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
	</div>

	<img src="<?php echo esc_url( tng_img_url( 'image', tng_bundled_img( 'page-careers-hero.jpg' ) ) ); ?>" alt="" class="careers-hero__image">
</section>
