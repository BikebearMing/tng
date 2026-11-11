<?php
/**
 * acf/path-hero — career path page hero: navy, no image; title on the left,
 * intro from 720 (tops level).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_path_hero' ) ) {
	return;
}

$tng_intro = tng_field( 'intro', '' );
?>
<section class="lp-hero" data-section="path-hero" data-toggle="show_path_hero">
	<?php echo tng_breadcrumbs( get_the_title(), tng_page_trail() ); ?>

	<div class="lp-hero__content">
		<h1 class="h1 dark"><?php echo esc_html( tng_field( 'title', get_the_title() ) ); ?></h1>
		<?php if ( $tng_intro ) : ?>
		<p class="body dark lp-hero__intro"><?php echo esc_html( $tng_intro ); ?></p>
		<?php endif; ?>
	</div>
</section>
