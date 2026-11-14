<?php
/**
 * acf/home-featured-banner — 100vh banner + recent insights panel.
 * featured_insight empty = the latest insight; recent_insights empty = latest 4.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_featured_banner' ) ) {
	return;
}

$tng_bg = tng_img_url( 'background_image', tng_asset( 'Rectangle 1.jpg' ) );

$tng_featured = get_field( 'featured_insight' );
if ( $tng_featured && is_object( $tng_featured ) ) {
	$tng_featured = $tng_featured->ID;
}
if ( $tng_featured && 'publish' !== get_post_status( $tng_featured ) ) {
	$tng_featured = 0; // trashed / unpublished pick: fall back to the latest
}
if ( ! $tng_featured ) {
	$tng_latest   = get_posts( array( 'post_type' => 'insight', 'posts_per_page' => 1 ) );
	$tng_featured = $tng_latest ? $tng_latest[0]->ID : 0;
}

$tng_recent_ids = tng_rel_ids( 'recent_insights' );
$tng_recent     = $tng_recent_ids
	? get_posts( array( 'post_type' => 'insight', 'post__in' => $tng_recent_ids, 'orderby' => 'post__in', 'posts_per_page' => 4 ) )
	: array();
if ( ! $tng_recent ) { // none picked, or every pick is gone
	$tng_recent = get_posts( array( 'post_type' => 'insight', 'posts_per_page' => 4, 'post__not_in' => array( $tng_featured ) ) );
}
?>
<section class="hp-featured-banner has-parallax" data-section="featured-banner" data-toggle="show_featured_banner">
	<img src="<?php echo esc_url( $tng_bg ); ?>" alt="" class="parallax-img">

	<div class="hp-featured-banner__content">
		<p class="subhead dark">Featured</p>
		<h1 class="h1 dark"><?php echo esc_html( $tng_featured ? get_the_title( $tng_featured ) : 'Featured insight' ); ?></h1>
		<a href="<?php echo esc_url( $tng_featured ? get_permalink( $tng_featured ) : '#' ); ?>" class="button-label dark link-arrow hp-featured-banner__link">
			Read more
			<?php echo tng_arrow( 1.5 ); ?>
		</a>
	</div>

	<div class="hp-featured-banner__panel">
		<div class="hp-featured-banner__panel-head">
			<p class="subhead">Recent Insights</p>
			<a href="<?php echo esc_url( tng_link_or( tng_field( 'view_all_url', '#' ), tng_page_url( 'insights' ) ) ); ?>" class="subhead hp-featured-banner__view-all">
				View all
				<?php echo tng_arrow( 1.5 ); ?>
			</a>
		</div>

		<ul class="hp-featured-banner__list">
			<?php foreach ( $tng_recent as $tng_item ) : ?>
			<li class="hp-featured-banner__item">
				<a href="<?php echo esc_url( get_permalink( $tng_item ) ); ?>">
					<h4 class="h4"><?php echo esc_html( get_the_title( $tng_item ) ); ?></h4>
					<p class="subhead"><?php echo esc_html( tng_card_date( $tng_item ) ); ?></p>
				</a>
				<span class="hp-featured-banner__icon" aria-hidden="true">
					<?php echo tng_arrow( 2 ); ?>
				</span>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
