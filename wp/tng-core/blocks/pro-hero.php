<?php
/**
 * tng/pro-hero — professional single hero. Reads the professional's fields;
 * location pills come from its Location country terms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_id = get_the_ID();
if ( ! tng_show( 'show_pro_hero', $tng_id ) ) {
	return;
}

$tng_name  = get_the_title( $tng_id );
$tng_phone = tng_field( 'phone', '', $tng_id );
$tng_email = tng_field( 'email', '', $tng_id );

$tng_locations = get_the_terms( $tng_id, 'location' );
$tng_locations = is_array( $tng_locations ) ? array_values( array_filter( $tng_locations, static function ( $t ) { return ! $t->parent; } ) ) : array();

$tng_pros_page = get_page_by_path( 'professionals' );
$tng_trail     = $tng_pros_page ? array( array( 'Professional', get_permalink( $tng_pros_page ) ) ) : array();

$tng_portrait = tng_img_url( 'portrait', tng_portrait_fallback( $tng_id ), $tng_id, 'large' ); // 1024 tall: the originals run to 1 MB
?>
<section class="pro-hero" data-section="pro-hero" data-toggle="show_pro_hero">
	<?php echo tng_breadcrumbs( $tng_name, $tng_trail ); ?>

	<div class="pro-hero__intro">
		<h1 class="h1"><?php echo esc_html( $tng_name ); ?></h1>
		<p class="pro-hero__position"><?php echo wp_kses( tng_pro_position( $tng_id ), array( 'br' => array() ) ); ?></p>
		<?php if ( $tng_locations ) : ?>
		<ul class="pro-hero__locations">
			<?php foreach ( $tng_locations as $tng_term ) : $tng_flag = tng_location_flag( $tng_term ); ?>
			<li><a href="<?php echo esc_url( tng_term_link( $tng_term ) ); ?>" class="pro-hero__location"><?php if ( $tng_flag ) : ?><img src="<?php echo esc_url( $tng_flag ); ?>" alt=""><?php endif; ?><?php echo esc_html( $tng_term->name ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>

	<ul class="pro-hero__contacts">
		<?php if ( $tng_phone ) : ?>
		<li>
			<a href="tel:<?php echo esc_attr( preg_replace( '/[^+\d]/', '', $tng_phone ) ); ?>">
				<span class="pro-hero__icon" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor"><path d="M4.4 1.3c.4-.1.8.1 1 .5l1.1 2.4c.2.4.1.8-.2 1.1l-1 .9c.7 1.5 1.9 2.8 3.5 3.5l.9-1c.3-.3.7-.4 1.1-.2l2.4 1.1c.4.2.6.6.5 1l-.4 1.7c-.1.5-.5.8-1 .8C6.9 13.1 2.9 9.1 2.8 3.7c0-.5.3-.9.8-1l.8-1.4z"/></svg></span>
				<?php echo esc_html( $tng_phone ); ?>
			</a>
		</li>
		<?php endif; ?>
		<?php if ( $tng_email ) : ?>
		<li>
			<a href="mailto:<?php echo esc_attr( $tng_email ); ?>">
				<span class="pro-hero__icon" aria-hidden="true"><svg viewBox="0 0 16 16" fill="currentColor"><path d="M2 4.5c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v.3L8 8.6 2 4.8v-.3zm0 1.6v5.4c0 .6.4 1 1 1h10c.6 0 1-.4 1-1V6.1L8.3 9.7a.6.6 0 0 1-.6 0L2 6.1z"/></svg></span>
				<?php echo esc_html( $tng_email ); ?>
			</a>
		</li>
		<?php endif; ?>
	</ul>

	<img src="<?php echo esc_url( $tng_portrait ); ?>" alt="<?php echo esc_attr( $tng_name ); ?>" class="pro-hero__portrait">
</section>
