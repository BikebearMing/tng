<?php
/**
 * tng/svc-hero — industry / practice page hero: title + contacts by country.
 *
 * Client rules (gelo.c): a country with no contacts gets no tab; the tab that
 * opens first follows the visitor's country (Cambodia / Indonesia / Laos /
 * Myanmar / Vietnam), everyone else gets Thailand. The server renders
 * Thailand (else the first country) as active; tng-wp.js asks
 * /tng/v1/geo and switches tabs client-side, because pages are edge-cached.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tng_term = tng_current_term();
if ( ! $tng_term ) {
	return;
}

$tng_ref = tng_term_ref( $tng_term );

// Contacts grouped by country; rows without a country or people are dropped.
$tng_rows      = tng_field( 'contacts', array(), $tng_ref );
$tng_countries = array();
foreach ( is_array( $tng_rows ) ? $tng_rows : array() as $tng_row ) {
	$tng_loc_id = isset( $tng_row['location'] ) ? (int) ( is_object( $tng_row['location'] ) ? $tng_row['location']->term_id : $tng_row['location'] ) : 0;
	$tng_people = array();
	foreach ( ( isset( $tng_row['people'] ) && is_array( $tng_row['people'] ) ) ? $tng_row['people'] : array() as $tng_p ) {
		$tng_pid = is_object( $tng_p ) ? $tng_p->ID : (int) $tng_p;
		if ( $tng_pid && 'publish' === get_post_status( $tng_pid ) ) {
			$tng_people[] = $tng_pid;
		}
	}
	$tng_loc = $tng_loc_id ? get_term( $tng_loc_id, 'location' ) : null;
	if ( ! $tng_loc || is_wp_error( $tng_loc ) || ! $tng_people ) {
		continue;
	}
	if ( $tng_loc->parent ) { // a city picked by mistake: file it under its country
		$tng_parent = get_term( $tng_loc->parent, 'location' );
		$tng_loc    = ( $tng_parent && ! is_wp_error( $tng_parent ) ) ? $tng_parent : $tng_loc;
	}
	if ( ! isset( $tng_countries[ $tng_loc->slug ] ) ) {
		$tng_countries[ $tng_loc->slug ] = array( $tng_loc, array() );
	}
	$tng_countries[ $tng_loc->slug ][1] = array_values( array_unique( array_merge( $tng_countries[ $tng_loc->slug ][1], $tng_people ) ) );
}
ksort( $tng_countries );
$tng_active = isset( $tng_countries['thailand'] ) ? 'thailand' : ( $tng_countries ? array_key_first( $tng_countries ) : '' );

// Breadcrumbs: Home / Industries (or Practices) / [parent term] / term
$tng_trail = array( array( 'industry' === $tng_term->taxonomy ? 'Industries' : 'Practices', tng_page_url( 'services' ) ) );
foreach ( array_reverse( get_ancestors( $tng_term->term_id, $tng_term->taxonomy, 'taxonomy' ) ) as $tng_anc_id ) {
	$tng_anc = get_term( $tng_anc_id, $tng_term->taxonomy );
	if ( $tng_anc && ! is_wp_error( $tng_anc ) ) {
		$tng_trail[] = array( $tng_anc->name, tng_term_link( $tng_anc ) );
	}
}
?>
<section class="in-hero svc-hero" data-section="svc-hero">
	<?php echo tng_breadcrumbs( $tng_term->name, $tng_trail ); ?>

	<div class="svc-hero__content">
		<h1 class="h1 dark"><?php echo esc_html( $tng_term->name ); ?></h1>
		<?php if ( $tng_countries ) : ?>
		<ul class="svc-hero__places" role="tablist" aria-label="Contacts by location" data-geo>
			<?php foreach ( $tng_countries as $tng_slug => $tng_item ) : $tng_on = $tng_slug === $tng_active; ?>
			<li><button type="button" class="button-label svc-hero__place<?php echo $tng_on ? ' is-active' : ''; ?>" role="tab" id="svc-place-<?php echo esc_attr( $tng_slug ); ?>" aria-controls="svc-contacts-<?php echo esc_attr( $tng_slug ); ?>" aria-selected="<?php echo $tng_on ? 'true' : 'false'; ?>"<?php echo $tng_on ? '' : ' tabindex="-1"'; ?> data-country="<?php echo esc_attr( $tng_slug ); ?>"><?php echo esc_html( $tng_item[0]->name ); ?></button></li>
			<?php endforeach; ?>
		</ul>
		<div class="svc-hero__panels">
			<?php foreach ( $tng_countries as $tng_slug => $tng_item ) : ?>
			<ul class="svc-hero__contacts" id="svc-contacts-<?php echo esc_attr( $tng_slug ); ?>" role="tabpanel" aria-labelledby="svc-place-<?php echo esc_attr( $tng_slug ); ?>"<?php echo $tng_slug === $tng_active ? '' : ' hidden'; ?>>
				<?php
				foreach ( $tng_item[1] as $tng_pid ) :
					$tng_phone = tng_field( 'phone', '', $tng_pid );
					$tng_email = tng_field( 'email', '', $tng_pid );
					?>
				<li class="svc-contact">
					<a href="<?php echo esc_url( get_permalink( $tng_pid ) ); ?>" class="h4 dark svc-contact__name"><?php echo esc_html( get_the_title( $tng_pid ) ); ?></a>
					<p class="body dark svc-contact__lines"><?php if ( $tng_phone ) : ?><a href="<?php echo esc_attr( tng_tel( $tng_phone ) ); ?>"><?php echo esc_html( $tng_phone ); ?></a><?php endif; ?><?php if ( $tng_email ) : ?><a href="mailto:<?php echo esc_attr( $tng_email ); ?>"><?php echo esc_html( $tng_email ); ?></a><?php endif; ?></p>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>

	<img src="<?php echo esc_url( tng_img_url( 'hero_image', tng_bundled_img( $tng_term->taxonomy . '-' . $tng_term->slug . '-hero.jpg', tng_default_hero() ), $tng_ref ) ); ?>" alt="" class="in-hero__image">
</section>
