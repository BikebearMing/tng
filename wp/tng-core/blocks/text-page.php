<?php
/**
 * tng/text-page — the whole body of the "TNG Text page" template (Privacy
 * Policy, Legal Notice, Fraudulent Communications, the data protection
 * notices): the career path hero with the title, then the insight article
 * grid with the "Legal information" nav and the page content.
 *
 * Nav = every published page on this template, top-level ones in menu order
 * with their child pages indented under them. Label = the page's "Menu
 * label" field, else its title. The copy is the page content (core blocks),
 * so the client edits it in the block editor like any page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id    = get_the_ID();
$tng_pages = get_pages(
	array(
		'meta_key'    => '_wp_page_template',
		'meta_value'  => 'tng-text',
		'sort_column' => 'menu_order,post_title',
	)
);
$tng_ids   = wp_list_pluck( $tng_pages, 'ID' );
$tng_label = static function ( $page ) {
	$label = tng_field( 'nav_label', '', $page->ID );
	return $label ? $label : $page->post_title;
};
$tng_roots = array_filter(
	$tng_pages,
	static function ( $p ) use ( $tng_ids ) {
		return ! in_array( (int) $p->post_parent, $tng_ids, true );
	}
);

$tng_content = apply_filters( 'the_content', get_the_content( null, false, $tng_id ) );
// Hero title = the page title. The privacy hub keeps "Privacy Policy" as its
// title (nav, footer) but its heading on the old site reads "Privacy Policies";
// a title the client changes wins.
$tng_title = get_the_title( $tng_id );
if ( 'privacy-policy-term-of-use' === get_post_field( 'post_name', $tng_id ) && 'Privacy Policy' === $tng_title ) {
	$tng_title = 'Privacy Policies';
}
?>
<section class="lp-hero txt-hero" data-section="text-hero">
	<?php echo tng_breadcrumbs( $tng_title, tng_page_trail( $tng_id ) ); // phpcs:ignore ?>

	<div class="lp-hero__content">
		<h1 class="h1 dark"><?php echo esc_html( $tng_title ); ?></h1>
	</div>
</section>

<section class="in-article txt-article" data-section="text-page">
	<?php if ( $tng_roots ) : ?>
	<aside class="in-article__aside">
		<nav class="txt-nav" aria-label="Legal information">
			<h2 class="h3 txt-nav__heading">Legal information</h2>
			<ul class="in-related__list txt-nav__list">
				<?php
				foreach ( $tng_roots as $tng_root ) :
					$tng_kids = array_filter(
						$tng_pages,
						static function ( $p ) use ( $tng_root ) {
							return (int) $p->post_parent === (int) $tng_root->ID;
						}
					);
					?>
				<li>
					<a href="<?php echo esc_url( get_permalink( $tng_root ) ); ?>" class="button-label txt-nav__link"<?php echo $tng_root->ID === $tng_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tng_label( $tng_root ) ); ?><?php echo tng_arrow_circle( false ); // phpcs:ignore ?></a>
					<?php if ( $tng_kids ) : ?>
					<ul class="txt-nav__sub">
						<?php foreach ( $tng_kids as $tng_kid ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $tng_kid ) ); ?>" class="txt-nav__sublink"<?php echo $tng_kid->ID === $tng_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tng_label( $tng_kid ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</aside>
	<?php endif; ?>

	<div class="in-content rich-text txt-content">
		<?php echo $tng_content; // phpcs:ignore -- post content, already filtered ?>
	</div>
</section>
