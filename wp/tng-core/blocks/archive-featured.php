<?php
/**
 * acf/archive-featured — one featured insight / award: date (insights only),
 * title, excerpt, "Read more" + the featured image, or a navy logo tile when
 * the post has none.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_archive_featured' ) ) {
	return;
}

$tng_pid = (int) tng_field( 'featured_post', 0 );
if ( ! $tng_pid || 'publish' !== get_post_status( $tng_pid ) ) {
	$tng_type   = 'award' === tng_field( 'fallback_type', 'insight' ) ? 'award' : 'insight';
	$tng_latest = get_posts( array( 'post_type' => $tng_type, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	$tng_pid    = $tng_latest ? (int) $tng_latest[0] : 0;
}
if ( ! $tng_pid ) {
	return;
}

$tng_dated = 'award' !== get_post_type( $tng_pid );
$tng_url   = get_permalink( $tng_pid );
$tng_img   = get_the_post_thumbnail_url( $tng_pid, 'large' );
?>
<section class="ins-featured<?php echo $tng_dated ? '' : ' ins-featured--no-date'; ?>" data-section="archive-featured" data-toggle="show_archive_featured">
	<p class="subhead pro-label"><?php echo esc_html( tng_field( 'label', 'Featured' ) ); ?></p>
	<article class="ins-featured__post">
		<div class="ins-featured__body">
			<?php if ( $tng_dated ) : ?>
			<p class="subhead ins-featured__date"><time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d', $tng_pid ) ); ?>"><?php echo esc_html( get_the_date( 'M j, Y', $tng_pid ) ); ?></time></p>
			<?php endif; ?>
			<h2 class="h2 ins-featured__title"><a href="<?php echo esc_url( $tng_url ); ?>"><?php echo esc_html( get_the_title( $tng_pid ) ); ?></a></h2>
			<p class="body ins-featured__text"><?php echo esc_html( get_the_excerpt( $tng_pid ) ); ?></p>
			<a href="<?php echo esc_url( $tng_url ); ?>" class="button-label link-arrow ins-featured__link">
				Read more
				<?php echo tng_arrow( 1.5 ); ?>
			</a>
		</div>
		<?php if ( $tng_img ) : ?>
		<img src="<?php echo esc_url( $tng_img ); ?>" alt="" class="ins-featured__image" loading="lazy">
		<?php else : ?>
		<div class="ins-featured__image ins-featured__logo" aria-hidden="true">
			<span>Tilleke</span>
			<span><em>&amp;</em>Gibbins</span>
		</div>
		<?php endif; ?>
	</article>
</section>
