<?php
/**
 * acf/page-hero — shared inner-page hero (navy). "compact" drops the min
 * height (contact page variant).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_page_hero' ) ) {
	return;
}

$tng_title   = tng_field( 'title', get_the_title() );
$tng_compact = (bool) get_field( 'compact' );
?>
<section class="page-hero<?php echo $tng_compact ? ' page-hero--compact' : ''; ?>" data-section="page-hero" data-toggle="show_page_hero">
	<?php echo tng_breadcrumbs( $tng_title ); ?>

	<div class="page-hero__content">
		<h1 class="h1 dark"><?php echo esc_html( $tng_title ); ?></h1>
		<?php $tng_intro = tng_field( 'intro', '' ); ?>
		<?php if ( $tng_intro ) : ?>
		<p class="body dark page-hero__intro"><?php echo esc_html( $tng_intro ); ?></p>
		<?php endif; ?>
	</div>
</section>
