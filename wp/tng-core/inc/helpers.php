<?php
/**
 * Shared render helpers. Every block template outputs the exact markup of the
 * static HTML pages (same classes and data-* attributes) so assets/css/style.css
 * and assets/js/main.js work unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** URL of a bundled asset (the plugin ships a copy of the repo's assets/). */
function tng_asset( $path ) {
	return TNG_CORE_URL . 'assets/' . ltrim( $path, '/' );
}

/**
 * Toggle check. ACF returns null when a toggle was never saved (e.g. a block
 * just inserted, or seeded without the field) — treat that as ON so sections
 * default to visible; only an explicit off hides them.
 */
function tng_show( $name, $post_id = false ) {
	$v = function_exists( 'get_field' ) ? get_field( $name, $post_id ) : null;
	return null === $v ? true : (bool) $v;
}

/** get_field with a default for empty values. */
function tng_field( $name, $default = '', $post_id = false ) {
	$v = function_exists( 'get_field' ) ? get_field( $name, $post_id ) : null;
	return ( null === $v || '' === $v ) ? $default : $v;
}

/** Image field (return format: url) with a fallback URL. */
function tng_img_url( $name, $fallback, $post_id = false ) {
	$v = function_exists( 'get_field' ) ? get_field( $name, $post_id ) : null;
	if ( is_array( $v ) ) {
		$v = isset( $v['url'] ) ? $v['url'] : '';
	}
	if ( is_numeric( $v ) ) {
		$v = wp_get_attachment_image_url( (int) $v, 'full' );
	}
	return $v ? $v : $fallback;
}

/** The right-pointing chevron used everywhere. $stroke: 1.5 (link-arrow) or 2 (circles). */
function tng_arrow( $stroke = 2 ) {
	return '<svg viewBox="0 0 10 10" fill="none"' . ( 1.5 === (float) $stroke ? ' aria-hidden="true"' : '' ) . '><path d="M3 1l4 4-4 4" stroke="currentColor" stroke-width="' . esc_attr( $stroke ) . '" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** <span class="arrow-circle"> icon. */
function tng_arrow_circle( $lg = false, $class = '' ) {
	$classes = 'arrow-circle' . ( $lg ? ' arrow-circle--lg' : '' ) . ( $class ? ' ' . $class : '' );
	return '<span class="' . esc_attr( $classes ) . '" aria-hidden="true">' . tng_arrow( 2 ) . '</span>';
}

/** Card date: "Dec 02, 2026". */
function tng_card_date( $post ) {
	return get_the_date( 'M d, Y', $post );
}

/** Hero date: "July 2, 2026". */
function tng_hero_date( $post ) {
	return get_the_date( 'F j, Y', $post );
}

/** Bundled portrait fallback so seeded professionals look like the mock. */
function tng_portrait_fallback( $post_id ) {
	return tng_asset( ( $post_id % 2 ) ? 'guy-1.jpg' : 'lady-2.jpg' );
}

/** Professional photo for cards: headshot, else portrait, else bundled fallback. */
function tng_pro_photo( $post_id ) {
	$url = tng_img_url( 'headshot', '', $post_id );
	if ( ! $url ) {
		$url = tng_img_url( 'portrait', '', $post_id );
	}
	return $url ? $url : tng_portrait_fallback( $post_id );
}

/** Position with line breaks kept (textarea, new_lines: br). */
function tng_pro_position( $post_id ) {
	return tng_field( 'position_title', '', $post_id );
}

/** The shared .post-card (insights + awards). */
function tng_post_card( $post ) {
	$url = get_permalink( $post );
	ob_start();
	?>
	<li>
		<a href="<?php echo esc_url( $url ); ?>" class="post-card">
			<h3 class="h3 post-card__title"><?php echo esc_html( get_the_title( $post ) ); ?></h3>
			<p class="body post-card__excerpt"><?php echo esc_html( get_the_excerpt( $post ) ); ?></p>
			<p class="post-card__foot"><time class="subhead" datetime="<?php echo esc_attr( get_the_date( 'Y-m-d', $post ) ); ?>"><?php echo esc_html( tng_card_date( $post ) ); ?></time><?php echo tng_arrow_circle( true ); ?></p>
		</a>
	</li>
	<?php
	return ob_get_clean();
}

/** The professionals grid card (.pro-card). */
function tng_pro_card( $post ) {
	$id = is_object( $post ) ? $post->ID : (int) $post;
	ob_start();
	?>
	<li>
		<a href="<?php echo esc_url( get_permalink( $id ) ); ?>" class="pro-card">
			<img src="<?php echo esc_url( tng_pro_photo( $id ) ); ?>" alt="" class="pro-card__img" loading="lazy">
			<div class="pro-card__info">
				<p class="pro-card__name"><?php echo esc_html( get_the_title( $id ) ); ?><?php echo tng_arrow_circle( false ); ?></p>
				<p class="subhead pro-card__position"><?php echo wp_kses( tng_pro_position( $id ), array( 'br' => array() ) ); ?></p>
			</div>
		</a>
	</li>
	<?php
	return ob_get_clean();
}

/**
 * Posts of $post_type whose ACF relationship field contains this professional.
 * Relationship values are stored serialized, so match the quoted ID.
 */
function tng_posts_related_to_professional( $post_type, $professional_id, $count = 3 ) {
	return get_posts(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => $count,
			'meta_query'     => array(
				array(
					'key'     => 'related_professionals',
					'value'   => '"' . (int) $professional_id . '"',
					'compare' => 'LIKE',
				),
			),
		)
	);
}

/** IDs from a relationship field (return format id), tolerant of objects. */
function tng_rel_ids( $name, $post_id = false ) {
	$v = function_exists( 'get_field' ) ? get_field( $name, $post_id ) : null;
	if ( ! is_array( $v ) ) {
		return array();
	}
	return array_map(
		static function ( $item ) {
			return is_object( $item ) ? $item->ID : (int) $item;
		},
		$v
	);
}

/** Breadcrumbs: Home / [trail...] / current. $trail = array of [label, url]. */
function tng_breadcrumbs( $current, $trail = array() ) {
	$html = '<nav class="breadcrumbs" aria-label="Breadcrumb"><p class="subhead"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a>';
	foreach ( $trail as $step ) {
		$html .= ' / <a href="' . esc_url( $step[1] ) . '">' . esc_html( $step[0] ) . '</a>';
	}
	$html .= ' / <span aria-current="page">' . esc_html( $current ) . '</span></p></nav>';
	return $html;
}

/** Term link helper that never echoes a WP_Error. */
function tng_term_link( $term ) {
	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '#' : $link;
}

/** Country flag URL for a location term (field, else bundled SVG by slug). */
function tng_location_flag( $term ) {
	$url = tng_img_url( 'flag', '', 'location_' . $term->term_id );
	if ( $url ) {
		return $url;
	}
	$bundled = array( 'cambodia', 'indonesia', 'laos', 'myanmar', 'thailand', 'vietnam' );
	return in_array( $term->slug, $bundled, true ) ? tng_asset( 'flags/' . $term->slug . '.svg' ) : '';
}

/** Location term image (megamenu / homepage cards) with the picsum fallback the mock uses. */
function tng_location_image( $term, $w, $h ) {
	$url = tng_img_url( 'image', '', 'location_' . $term->term_id );
	return $url ? $url : 'https://picsum.photos/seed/' . rawurlencode( $term->slug ) . '/' . (int) $w . '/' . (int) $h;
}

/** The share buttons block (Standard sidebar + Module share row). */
function tng_share_buttons() {
	ob_start();
	?>
	<button type="button" class="in-share__print" data-share="print">
		Print
		<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 2h10v4H5V2zM3 7h14a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2v3H4v-3H2a1 1 0 0 1-1-1V9a2 2 0 0 1 2-2zm3 6v4h8v-4H6zm9-4a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>
	</button>
	<a href="#" class="in-share__icon" data-share="linkedin" target="_blank" rel="noopener" aria-label="Share on LinkedIn">
		<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M17 1H3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zM6.6 16H4V7.8h2.6V16zM5.3 6.7a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM16 16h-2.6v-4c0-1 0-2.2-1.3-2.2s-1.6 1-1.6 2.1V16H7.9V7.8h2.5v1.1c.4-.7 1.2-1.3 2.5-1.3 2.6 0 3.1 1.7 3.1 4V16z"/></svg>
	</a>
	<a href="#" class="in-share__icon" data-share="email" aria-label="Share by email">
		<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M1 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v.4l-9 5.4-9-5.4V5zm0 2.7V15a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7.7l-8.5 5.1a1 1 0 0 1-1 0L1 7.7z"/></svg>
	</a>
	<button type="button" class="in-share__icon" data-share="copy" aria-label="Copy link">
		<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M8.5 11.5a3.5 3.5 0 0 0 5 0l3-3a3.5 3.5 0 0 0-5-5l-1 1"/><path d="M11.5 8.5a3.5 3.5 0 0 0-5 0l-3 3a3.5 3.5 0 0 0 5 5l1-1"/></svg>
	</button>
	<?php
	return ob_get_clean();
}

/** The pro-related section shared by professional singles and the Module template. */
function tng_related_section( $args ) {
	$d = wp_parse_args(
		$args,
		array(
			'id'           => '',
			'class'        => 'pro-related',
			'data_section' => '',
			'data_toggle'  => '',
			'heading'      => '',
			'link_label'   => 'View all',
			'link_url'     => '#',
			'cards'        => '', // pre-rendered <li> items
		)
	);
	ob_start();
	?>
	<section class="<?php echo esc_attr( $d['class'] ); ?>"<?php echo $d['id'] ? ' id="' . esc_attr( $d['id'] ) . '"' : ''; ?> data-section="<?php echo esc_attr( $d['data_section'] ); ?>" data-toggle="<?php echo esc_attr( $d['data_toggle'] ); ?>">
		<div class="pro-related__head">
			<h2 class="h2 pro-related__title"><?php echo esc_html( $d['heading'] ); ?></h2>
			<a href="<?php echo esc_url( $d['link_url'] ); ?>" class="button-label link-arrow pro-related__link">
				<?php echo esc_html( $d['link_label'] ); ?>
				<?php echo tng_arrow( 1.5 ); ?>
			</a>
		</div>
		<ul class="pro-related__cards">
			<?php echo $d['cards']; // phpcs:ignore ?>
		</ul>
	</section>
	<?php
	return ob_get_clean();
}
