<?php
/**
 * acf/more-from — "More from <parent>" + links to the parent's pages.
 * Links default to: the parent page (as "Overview") + its child pages; on a
 * child page the current page is left out, on the parent it is marked current.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! tng_show( 'show_more_from' ) ) {
	return;
}

$tng_id     = get_the_ID();
$tng_parent = wp_get_post_parent_id( $tng_id );
$tng_parent = $tng_parent ? $tng_parent : $tng_id;
$tng_here   = untrailingslashit( (string) get_permalink( $tng_id ) );

$tng_heading = tng_field( 'heading', '' );
$tng_heading = $tng_heading ? wp_kses( $tng_heading, array( 'br' => array() ) ) : 'More from<br>' . esc_html( get_the_title( $tng_parent ) );

$tng_links = array_values(
	array_filter(
		(array) tng_field( 'links', array() ),
		static function ( $row ) {
			return ! empty( $row['label'] );
		}
	)
);
if ( ! $tng_links ) {
	$tng_links = array( array( 'label' => 'Overview', 'url' => get_permalink( $tng_parent ) ) );
	foreach ( get_pages( array( 'parent' => $tng_parent, 'sort_column' => 'menu_order,post_title' ) ) as $tng_child ) {
		if ( $tng_child->ID !== $tng_id ) {
			$tng_links[] = array( 'label' => $tng_child->post_title, 'url' => get_permalink( $tng_child ) );
		}
	}
}

$tng_arrows = (bool) get_field( 'arrows' );
?>
<section class="more-from" data-section="more-from" data-toggle="show_more_from">
	<h2 class="h2 more-from__title"><?php echo $tng_heading; // phpcs:ignore ?></h2>
	<ul class="more-from__list">
		<?php foreach ( $tng_links as $tng_link ) : $tng_url = ! empty( $tng_link['url'] ) ? $tng_link['url'] : '#'; ?>
		<li><a href="<?php echo esc_url( $tng_url ); ?>" class="h3 more-from__link"<?php echo untrailingslashit( $tng_url ) === $tng_here ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tng_link['label'] ); ?><?php echo $tng_arrows ? tng_arrow_circle( true ) : ''; // phpcs:ignore ?></a></li>
		<?php endforeach; ?>
	</ul>
</section>
