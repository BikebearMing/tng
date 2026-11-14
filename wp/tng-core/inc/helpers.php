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

/**
 * Image field (return format: url) with a fallback URL. $size: an
 * intermediate size for cards (imports keep thumbnail / medium_large / large);
 * WP returns the full file when the original is smaller than that size.
 */
function tng_img_url( $name, $fallback, $post_id = false, $size = 'full' ) {
	if ( 'full' !== $size && function_exists( 'get_field' ) ) {
		$raw = get_field( $name, $post_id, false ); // attachment ID
		$url = is_numeric( $raw ) ? wp_get_attachment_image_url( (int) $raw, $size ) : '';
		if ( $url ) {
			return $url;
		}
	}
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

/**
 * Portrait for a professional with no photo: the navy logo tile (the same
 * treatment archive-featured uses for a post with no image). Never the
 * bundled stock models: a real lawyer's page must not show a stranger's face.
 * Drop a portrait-placeholder.png into assets/img/ to replace the tile.
 */
function tng_portrait_fallback( $post_id ) {
	return tng_bundled_img( array( 'portrait-placeholder.png', 'portrait-placeholder.svg' ) );
}

/**
 * Professional photo for cards, rows and the roster: headshot, else portrait,
 * else bundled fallback. medium_large (768 wide), never the ~800 KB original;
 * only the pro hero uses the full portrait.
 */
function tng_pro_photo( $post_id ) {
	$url = tng_img_url( 'headshot', '', $post_id, 'medium_large' );
	if ( ! $url ) {
		$url = tng_img_url( 'portrait', '', $post_id, 'medium_large' );
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
	// Memo: the tab bar and the section ask for the same list (LIKE over every
	// related_professionals row), so run the query once per page.
	static $memo = array();
	$n   = max( 3, (int) $count );
	$key = $post_type . ':' . (int) $professional_id . ':' . $n;
	if ( ! isset( $memo[ $key ] ) ) {
		$memo[ $key ] = tng_posts_related_query( $post_type, $professional_id, $n );
	}
	return array_slice( $memo[ $key ], 0, $count );
}

function tng_posts_related_query( $post_type, $professional_id, $count ) {
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

/**
 * Bundled design image (assets/img/, exported from Figma): URL of the first
 * of $names that exists, else $fallback. Used as the default behind every
 * image field, so an upload always wins.
 */
function tng_bundled_img( $names, $fallback = '' ) {
	foreach ( (array) $names as $name ) {
		if ( $name && file_exists( TNG_CORE_DIR . 'assets/img/' . $name ) ) {
			return tng_asset( 'img/' . $name );
		}
	}
	return $fallback;
}

/** Hero fallback for pages / terms with no design of their own: the brand teal texture. */
function tng_default_hero() {
	return tng_bundled_img( 'page-about-hero.jpg', tng_texture_url() );
}

/**
 * Location term image (megamenu / homepage cards). Fallback: the bundled
 * country photo, landscape card crop or portrait megamenu crop by $w / $h.
 */
function tng_location_image( $term, $w, $h ) {
	$url = tng_img_url( 'image', '', 'location_' . $term->term_id );
	if ( $url ) {
		return $url;
	}
	$crop = $w > $h ? 'card' : 'menu';
	return tng_bundled_img( 'loc-' . $term->slug . '-' . $crop . '.jpg', tng_bundled_img( 'loc-global-' . $crop . '.jpg' ) );
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

/** Permalink of a page by path ("about/awards-rankings"), else $fallback. */
function tng_page_url( $path, $fallback = '#' ) {
	$page = get_page_by_path( $path );
	return $page ? get_permalink( $page ) : $fallback;
}

/** Breadcrumb trail of a page's ancestors, root first: [[title, url], ...]. */
function tng_page_trail( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$trail   = array();
	foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $ancestor ) {
		$trail[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
	}
	return $trail;
}

/** URL of the child page of $parent_id whose slug matches $title (or its plural), else ''. */
function tng_child_page_url( $parent_id, $title ) {
	if ( ! $parent_id ) {
		return '';
	}
	$slug = sanitize_title( $title );
	foreach ( get_pages( array( 'parent' => $parent_id ) ) as $child ) {
		if ( $child->post_name === $slug || $child->post_name === $slug . 's' ) {
			return get_permalink( $child );
		}
	}
	return '';
}

/** Down-pointing chevron (View more). */
function tng_arrow_down() {
	return '<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** Left-pointing chevron (back links). */
function tng_arrow_left() {
	return '<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M7 1L3 5l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** Close icon (filter chips). */
function tng_icon_close() {
	return '<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M1.5 1.5l7 7M8.5 1.5l-7 7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
}

/** Country term of a post's location (a city term resolves to its country), else null. */
function tng_post_country( $post_id ) {
	$terms = get_the_terms( $post_id, 'location' );
	if ( ! is_array( $terms ) || ! $terms ) {
		return null;
	}
	$term = $terms[0];
	if ( $term->parent ) {
		$parent = get_term( $term->parent, 'location' );
		if ( $parent && ! is_wp_error( $parent ) ) {
			$term = $parent;
		}
	}
	return $term;
}

/** Navy location pill: flag + country name (job hero, career path job rows). */
function tng_country_pill( $country, $class = 'job-hero__tag', $tag = 'p' ) {
	if ( ! $country ) {
		return '';
	}
	$flag = tng_location_flag( $country );
	return '<' . $tag . ' class="' . esc_attr( $class ) . '">' . ( $flag ? '<img src="' . esc_url( $flag ) . '" alt="">' : '' ) . esc_html( $country->name ) . '</' . $tag . '>';
}

/**
 * Countries (top-level Location terms) with open Job posts, in term order:
 * [[term, count], ...]. Used by the careers rows and the job list filter.
 */
function tng_job_countries() {
	$terms = get_terms( array( 'taxonomy' => 'location', 'hide_empty' => false, 'parent' => 0 ) );
	$rows  = array();
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$count = ( new WP_Query(
			array(
				'post_type'      => 'job',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy'         => 'location',
						'terms'            => $term->term_id,
						'include_children' => true,
					),
				),
			)
		) )->found_posts;
		if ( $count > 0 ) {
			$rows[] = array( $term, $count );
		}
	}
	return $rows;
}

/**
 * The Job Opportunities page (Careers child), optionally filtered to a
 * country (?loc=<slug>#open-positions). No page yet = the careers job rows.
 */
function tng_jobs_url( $country = null ) {
	$page = get_page_by_path( 'careers/job-opportunities' );
	if ( ! $page ) {
		return tng_page_url( 'careers' ) . '#job-opportunities';
	}
	$url = get_permalink( $page );
	return $country ? add_query_arg( 'loc', $country->slug, $url ) . '#open-positions' : $url;
}

/**
 * The block's "Additional CSS class(es)" (Advanced panel), as ' class' or ''.
 * Page-specific modifiers (hist-overview, lp-team--long, rec-cta ...) ride on
 * it, so a reused block needs no extra field.
 */
function tng_block_class( $block ) {
	return ! empty( $block['className'] ) ? ' ' . esc_attr( $block['className'] ) : '';
}

/** Published page whose title is exactly $title, else null. */
function tng_page_by_title( $title ) {
	static $memo = array();
	if ( ! array_key_exists( $title, $memo ) ) {
		$ids            = get_posts( array( 'post_type' => 'page', 'title' => $title, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		$memo[ $title ] = $ids ? get_post( $ids[0] ) : null;
	}
	return $memo[ $title ];
}

/**
 * Render-time link healing: a link left on "#" (or empty) points at the page
 * it names, once that page exists: a child of $parent_id by slug, else any
 * page with that exact title. Stored content is never edited.
 */
function tng_heal_url( $url, $label, $parent_id = 0 ) {
	if ( '' !== (string) $url && '#' !== $url ) {
		return $url;
	}
	$child = tng_child_page_url( $parent_id, $label );
	if ( $child ) {
		return $child;
	}
	$page = tng_page_by_title( html_entity_decode( $label ) );
	return $page ? get_permalink( $page ) : '#';
}

/** $url unless it is empty or "#", else $fallback (render-time default; stored value untouched). */
function tng_link_or( $url, $fallback ) {
	return ( '' === trim( (string) $url ) || '#' === trim( (string) $url ) ) ? $fallback : $url;
}

/**
 * The client's Mailchimp list (the old site's /insights-newsletter-signup/ form).
 * post = the embedded form's action (field EMAIL + honeypot), form = Mailchimp's
 * hosted sign-up page with every field (name, company, interest groups).
 */
function tng_newsletter( $what = 'post' ) {
	$q = 'u=266a11a0874fa0c222f4b20fd&id=2fba872045';
	return 'form' === $what ? 'https://tilleke.us7.list-manage.com/subscribe?' . $q : 'https://tilleke.us7.list-manage.com/subscribe/post?' . $q;
}

/**
 * Former professionals credited on an imported insight / award (field
 * former_authors, one name per line): the old site's unlinked author cards.
 * Shown as plain names under Related professionals and in the Article JSON-LD.
 * Default pending the client's view; one switch hides them everywhere:
 *   add_filter( 'tng_show_former_authors', '__return_false' );
 */
function tng_former_authors( $post_id ) {
	if ( ! apply_filters( 'tng_show_former_authors', true ) || ! function_exists( 'get_field' ) ) {
		return array();
	}
	$names = preg_split( '/\R/u', (string) get_field( 'former_authors', $post_id, false ) );
	return array_values( array_unique( array_filter( array_map( 'trim', $names ), 'strlen' ) ) );
}

/** The firm's Code of Business Conduct PDF (old upload path, imported), else "#". */
function tng_code_of_conduct_url() {
	$url = wp_get_upload_dir()['baseurl'] . '/2023/09/2023_Tilleke-Code-of-Business-Conduct-EN.pdf';
	return attachment_url_to_postid( $url ) ? $url : '#';
}

/** Old-site destinations for social / network links left on "#". */
function tng_default_social( $network ) {
	$urls = array(
		'facebook'  => 'https://www.facebook.com/tillekegibbins',
		'youtube'   => 'https://www.youtube.com/channel/UCRQKPcy9kYGdYqFVkqzSqVA',
		'linkedin'  => 'https://www.linkedin.com/company/tilleke-gibbins/',
		'x'         => 'https://twitter.com/tillekegibbins',
		'lexmundi'  => 'https://www.lexmundi.com/',
		'multilaw'  => 'https://www.multilaw.com/',
		'dna'       => 'https://www.drewnetworkasia.com/',
	);
	return isset( $urls[ $network ] ) ? $urls[ $network ] : '#';
}

/**
 * Links left on "#" inside stored block copy whose destination is known (the
 * old site's targets): healed at render time by link text, content untouched.
 */
function tng_heal_text_links( $html ) {
	if ( false === strpos( (string) $html, 'href="#"' ) ) {
		return $html;
	}
	$map = array(
		'Thomson Reuters Foundation'                       => '/awards/tilleke-gibbins-named-domestic-law-firm-year-trustlaw-awards/',
		'Chambers and Partners Diversity & Inclusion Initiative' => '/awards/tilleke-gibbins-shortlisted-two-chambers-diversity-inclusion-awards-asia-pacific-2020/',
		'Vance Center for International Justice'           => '/awards/tilleke-gibbins-receives-global-recognition-for-pro-bono-work/',
		'Cyrus R. Vance Center for International Justice'  => '/awards/tilleke-gibbins-receives-global-recognition-for-pro-bono-work/',
	);
	return preg_replace_callback(
		'/<a([^>]*)href="#"([^>]*)>(.*?)<\/a>/s',
		static function ( $m ) use ( $map ) {
			$text = trim( html_entity_decode( wp_strip_all_tags( $m[3] ), ENT_QUOTES ) );
			return isset( $map[ $text ] ) ? '<a' . $m[1] . 'href="' . esc_url( home_url( $map[ $text ] ) ) . '"' . $m[2] . '>' . $m[3] . '</a>' : $m[0];
		},
		$html
	);
}

/** The site search URL (the tng-search template), optionally for a query. */
function tng_search_url( $q = '', $type = '' ) {
	$args = array( 's' => $q );
	if ( $type ) {
		$args['type'] = $type;
	}
	return add_query_arg( array_map( 'rawurlencode', $args ), home_url( '/' ) );
}

/** Teal texture used behind parallax sections. */
function tng_texture_url() {
	return tng_asset( 'hp-location-bg.jpg' );
}

/** An image sub-field value (url / array / attachment ID) as a URL, else $fallback. */
function tng_img_value( $v, $fallback = '' ) {
	if ( is_array( $v ) ) {
		$v = isset( $v['url'] ) ? $v['url'] : '';
	}
	if ( is_numeric( $v ) ) {
		$v = wp_get_attachment_image_url( (int) $v, 'full' );
	}
	return $v ? $v : $fallback;
}

/* ---------------- Old site URLs + SEO (import design 5 + 6) ---------------- */

/**
 * Canonical key of an old-site URL or path: https://www.tilleke.com + path,
 * query and fragment dropped, percent-encoding kept (hex upper-cased, raw
 * non-ASCII bytes encoded), trailing slash added unless the last segment has
 * a file extension. Imported objects store it as _tng_src; the transform
 * uses the same rule. The host is ignored, so a request on the new domain
 * maps to the same key.
 */
function tng_legacy_key( $url ) {
	$path = wp_parse_url( trim( (string) $url ), PHP_URL_PATH );
	if ( false === $path ) {
		return '';
	}
	$path = '/' . ltrim( (string) $path, '/' );
	$path = preg_replace_callback( '/[^\x21-\x7E]/', static function ( $m ) { return rawurlencode( $m[0] ); }, $path );
	$path = preg_replace_callback( '/%[0-9a-f]{2}/i', static function ( $m ) { return strtoupper( $m[0] ); }, $path );
	if ( ! preg_match( '/\.[a-z0-9]{1,5}$/i', $path ) ) {
		$path = trailingslashit( $path );
	}
	return 'https://www.tilleke.com' . $path;
}

/**
 * Fix-round rules (2026-11-14) for old paths the import maps did not cover:
 * old path => new path or URL. Matched before the imported map, so they also
 * replace the transform's "/ja/ → Home"-style defaults. Keys are paths as
 * linked (tng_legacy_key() normalises them).
 */
function tng_fix_redirects() {
	static $map = null;
	if ( null === $map ) {
		$rules = array(
			'/ja/'                           => '/japan-desk/',
			'/zh/'                           => '/china-desk/',
			'/奖项-2/'                       => '/china-desk/',
			'/企业社会责任/'                 => '/china-desk/',
			'/博物馆/'                       => '/china-desk/',
			'/locations/'                    => '/global-reach/',
			'/ja/insights/'                  => '/insights/?lang=ja#results',
			'/zh/insights/'                  => '/insights/?lang=zh#results',
			'/wp-content/uploads/2019/07/Map-TG-Bangkok-Office.pdf' => '/wp-content/uploads/2025/07/Map-TG-Bangkok-Office.pdf',
			'/professional/'                 => '/professionals/', // post type slugs (were found by core's guessing)
			'/insight/'                      => '/insights/',
			'/award/'                        => '/about/awards-rankings/',
			'/awards/'                       => '/about/awards-rankings/',
			'/job/'                          => '/careers/job-opportunities/',
			'/jobs/'                         => '/careers/job-opportunities/',
			'/recruitment-process/'          => '/careers/recruitment-process/',
			'/insights-newsletter-signup/'   => tng_newsletter( 'form' ),
			'/practices/'                    => '/services/',
			'/sub_practices/'                => '/services/',
			'/firm/'                         => '/about/',
			'/firm/profile/'                 => '/about/',
			'/firm/history/'                 => '/about/history/',
			'/firm/history/wisecounsel/'     => '/about/history/',
			'/firm/affiliations/'            => '/global-reach/',
			'/firm/community/'               => '/about/csr/',
			'/firm/community/museum/'        => '/about/museum-of-counterfeit-goods/',
			'/firm/community/probono/'       => '/about/pro-bono/',
			'/firm/community/textiles/'      => '/about/textile-collection/',
			'/global/'                       => '/global-reach/',
			'/global/lex-mundi/'             => '/global-reach/',
			'/global/multilaw/'              => '/global-reach/',
			'/practices/IP/'                 => '/practice/intellectual-property/',
			'/practices/ip/'                 => '/practice/intellectual-property/',
			'/practices/litigation/'         => '/practice/dispute-resolution-and-litigation/',
			'/practices/merger-and-acquisition/' => '/practice/corporate-ma/',
			'/practices/transportation/'     => '/services/',
			'/practices/property/'           => '/practice/real-estate/',
			'/practices/banking/'            => '/practice/banking-and-finance/',
			'/offices/phnom-penh/'           => '/location/cambodia/',
			'/offices/hanoi/'                => '/location/vietnam/',
			'/offices/ho-chi-minh-city/'     => '/location/vietnam/',
			'/offices/vientiane/'            => '/location/laos/',
			'/offices/yangon/'               => '/location/myanmar/',
			'/offices/jakarta/'              => '/location/indonesia/',
			'/insightsforcing-indemnification-clauses-in-thailand/' => '/insights/enforcing-indemnification-clauses-in-thailand/',
			// Drupal /resources/ slugs that differ from the imported post's (checked by title)
			'/resources/asialaw-profiles-2021-tilleke-gibbins-stands-above-competition-thailand-rankings/' => '/awards/asialaw-profiles-2021-tilleke-gibbins-dominates-thailand-rankings/',
			'/resources/chambers-ranks-sixteen-tilleke-gibbins-lawyers-among-best-asia-pacific/' => '/insights/chambers-ranks-fifteen-tilleke-gibbins-lawyers-among-best-asia-pacific/',
			'/resources/mip-recommends-tilleke-gibbins-trademark-work-cambodia/' => '/awards/mip-recommends-tilleke-gibbins-intellectual-property-work-cambodia/',
			'/resources/plc-insurance-and-reinsurance-multi-jurisdictional-guide-201617-%E2%80%93-vietnam-chapter/' => '/insights/plc-insurance-and-reinsurance-multi-jurisdictional-guide-201617-vietnam/',
			'/resources/practical-law-global-guide-2018-insurance-and-reinsurance-%E2%80%93-cambodia/' => '/insights/practical-law-insurance-and-reinsurance-global-guide-2018-cambodia/',
			'/resources/practical-law-global-guide-2018-insurance-and-reinsurance-%E2%80%93-laos/' => '/insights/practical-law-insurance-and-reinsurance-global-guide-2018-laos/',
			'/resources/practical-law-global-guide-2018-insurance-and-reinsurance-%E2%80%93-thailand/' => '/insights/practical-law-insurance-and-reinsurance-global-guide-2018-thailand/',
			'/resources/practical-law-global-guide-2018-insurance-and-reinsurance-%E2%80%93-vietnam/' => '/insights/practical-law-insurance-and-reinsurance-global-guide-2018-vietnam/',
			'/resources/thailand-temporarily-reduces-social-security-contributions-both-employers-and-employees/' => '/insights/thailand-temporarily-reduce-social-security-contributions-both-employers-and-employees/',
			'/resources/thailands-legal-system-undergoes-major-digital-reforms/' => '/insights/thailands-legal-system-undergoes-major-digital-reforms-2/',
			'/resources/tilleke-gibbins-again-ranked-tier-1-thailand-alb-ip-rankings-2020/' => '/insights/tilleke-gibbins-receives-twelfth-tier-1-ranking-thailand-alb-ip-rankings-2020/',
			'/resources/tilleke-gibbins-earns-%E2%80%9Cgold-bank%E2%80%9D-status-thailand-iam-patent-1000/' => '/awards/tilleke-gibbins-earns-gold-band-status-thailand-iam-patent-1000/',
		);
		$map = array();
		foreach ( $rules as $from => $to ) {
			$map[ tng_legacy_key( $from ) ] = $to;
		}
	}
	return $map;
}

/** Published insight / award whose slug WordPress would make from $slug (an old Drupal slug with – ’ “ ”), else ''. */
function tng_slug_target( $slug ) {
	$slug = sanitize_title( rawurldecode( (string) $slug ) );
	if ( '' === $slug ) {
		return '';
	}
	$ids = get_posts( array( 'post_type' => array( 'insight', 'award' ), 'name' => $slug, 'posts_per_page' => 1, 'fields' => 'ids', 'tng_all_langs' => true ) );
	return $ids ? get_permalink( $ids[0] ) : '';
}

/** New URL for an old one: [url, code], else ''. Fix-round rules, the import map, imported objects, patterns, regex rules. */
function tng_legacy_target( $key, $depth = 0 ) {
	global $wpdb;
	$fix = tng_fix_redirects();
	if ( isset( $fix[ $key ] ) ) {
		return array( $fix[ $key ], 301 );
	}
	$map = get_option( 'tng_redirects', array() );
	if ( isset( $map[ $key ] ) ) {
		return $map[ $key ];
	}
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tng_src' AND meta_value = %s", $key ) );
	foreach ( $ids as $id ) {
		$status = get_post_status( $id );
		if ( 'attachment' === get_post_type( $id ) ) {
			return array( wp_get_attachment_url( $id ), 301 );
		}
		if ( 'publish' === $status ) {
			return array( get_permalink( $id ), 301 );
		}
	}
	$path = (string) wp_parse_url( $key, PHP_URL_PATH );
	foreach ( (array) get_option( 'tng_redirects_regex', array() ) as $rule ) {
		if ( @preg_match( $rule['from'], $path ) ) { // phpcs:ignore -- patterns are validated on import
			return array( preg_replace( $rule['from'], $rule['to'], $path ), $rule['code'] );
		}
	}
	// Old archive pages: /insights-archive/page/3/ = /insights-archive/.
	if ( ! $depth && preg_match( '#^(/.+/)page/\d+/$#', $path, $m ) ) {
		return tng_legacy_target( tng_legacy_key( $m[1] ), 1 );
	}
	// The old TranslatePress copies (/ja/about/, /zh/professionals/ ...): the English page.
	if ( ! $depth && preg_match( '#^/(?:ja|zh)(/.+/)$#', $path, $m ) ) {
		$t = tng_legacy_target( tng_legacy_key( $m[1] ), 1 );
		if ( $t ) {
			return $t;
		}
		if ( url_to_postid( home_url( $m[1] ) ) ) {
			return array( $m[1], 301 );
		}
	}
	// Drupal /resources/<slug>/, old /events/<slug>/ and root-level article slugs: the post WP named from that slug.
	if ( preg_match( '#^/(?:resources/|events/)?([^/]+)/$#i', $path, $m ) && ( $url = tng_slug_target( $m[1] ) ) ) {
		return array( $url, 301 );
	}
	if ( preg_match( '#^/events/#', $path ) ) { // the old events posts were internship fairs
		return array( '/careers/internships/', 301 );
	}
	// Old professional URLs (also /ja/ /zh/ copies): the person if they are here, else the directory.
	if ( preg_match( '#^/(?:ja/|zh/)?professionals/([^/]+)/$#i', $path, $m ) ) {
		$ids = get_posts( array( 'post_type' => 'professional', 'name' => sanitize_title( $m[1] ), 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return array( $ids ? get_permalink( $ids[0] ) : '/professionals/', 301 );
	}
	if ( preg_match( '#^/(?:ja/|zh/)?practices/[^/]+/$#i', $path ) ) { // an old practice slug with no page: the services index
		return array( '/services/', 301 );
	}
	if ( preg_match( '#^/(firm|global)/#', $path ) ) {
		return array( 'firm' === strtok( ltrim( $path, '/' ), '/' ) ? '/about/' : '/global-reach/', 301 );
	}
	if ( preg_match( '#^/resource_search/#', $path ) ) {
		return array( '/insights/', 301 );
	}
	// Yoast sitemap URLs registered in Search Console.
	if ( preg_match( '#^/(sitemap_index|(?!wp-)[a-z0-9_-]+-sitemap\d*)\.xml$#i', $path ) ) {
		return array( '/wp-sitemap.xml', 301 );
	}
	return '';
}

/**
 * Old site post IDs (legacy.id of every imported post): old ID => new ID.
 * Built once from _tng_legacy and kept in an option; finalize rebuilds it.
 */
function tng_legacy_ids( $rebuild = false ) {
	$map = $rebuild ? false : get_option( 'tng_legacy_ids' );
	if ( ! is_array( $map ) ) {
		global $wpdb;
		$map = array();
		foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_tng_legacy'" ) as $row ) {
			$legacy = maybe_unserialize( $row->meta_value );
			if ( is_array( $legacy ) && ! empty( $legacy['id'] ) ) {
				$map[ (int) $legacy['id'] ] = (int) $row->post_id;
			}
		}
		update_option( 'tng_legacy_ids', $map, false );
	}
	return $map;
}

// Old shortlinks and PDF / print buttons by old ID: /?p=<old id>, /?download_insight_pdf=<old id>,
// /print-insight/?post_id=<old id>. An old ID wins over a new post that happens to share the
// number; previews (preview=, post_type=, page_id=) and logged-in drafts are left to WordPress.
add_action(
	'template_redirect',
	function () {
		if ( is_admin() || isset( $_GET['preview'] ) || isset( $_GET['post_type'] ) || isset( $_GET['page_id'] ) ) { // phpcs:ignore
			return;
		}
		$old = 0;
		$pdf = false;
		$print = isset( $_SERVER['REQUEST_URI'] ) && preg_match( '#^/(ja/|zh/)?print-(insight|professional)/#', wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore
		foreach ( $print ? array( 'post_id' ) : array( 'p', 'download_insight_pdf' ) as $k ) {
			if ( isset( $_GET[ $k ] ) && ctype_digit( (string) $_GET[ $k ] ) ) { // phpcs:ignore
				$old = (int) $_GET[ $k ]; // phpcs:ignore
				$pdf = 'download_insight_pdf' === $k;
				break;
			}
		}
		if ( ! $old ) {
			return;
		}
		$ids = tng_legacy_ids();
		if ( empty( $ids[ $old ] ) || 'publish' !== get_post_status( $ids[ $old ] ) ) {
			return;
		}
		$to = get_permalink( $ids[ $old ] );
		if ( $pdf && function_exists( 'get_field' ) ) {
			$report = get_field( 'module_report', $ids[ $old ], false );
			$to     = is_numeric( $report ) && wp_get_attachment_url( (int) $report ) ? wp_get_attachment_url( (int) $report ) : $to;
		}
		wp_safe_redirect( $to, 301 );
		exit;
	},
	0
);

// Old URLs that 404 on the new site: redirect before redirect_canonical (10) guesses.
// Attachments too: WP resolves any /<path>/<attachment-slug>/ to the attachment,
// so /practices/automotive/ would otherwise land on automotive.jpg.
add_action(
	'template_redirect',
	function () {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore
		// Drupal-era /index.php?q=<path>: resolve the path.
		if ( isset( $_GET['q'] ) && preg_match( '#^/index\.php#', $uri ) && is_string( $_GET['q'] ) ) { // phpcs:ignore
			$uri = '/' . ltrim( sanitize_text_field( wp_unslash( $_GET['q'] ) ), '/' ); // phpcs:ignore
		} elseif ( ! ( is_404() || is_attachment() ) ) {
			return;
		}
		$target = tng_legacy_target( tng_legacy_key( $uri ) );
		if ( ! $target || ! $target[0] ) {
			return;
		}
		$to = 0 === strpos( $target[0], '/' ) ? home_url( $target[0] ) : $target[0];
		// Targets come only from admin-run imports and the list above, so an external host is allowed.
		wp_redirect( esc_url_raw( $to ), (int) $target[1] ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	},
	1
);

// No guessing on 404s (core picks the first post whose slug starts with the path:
// /professionals/kitti/ opened Kittiphan). Every old path is mapped above instead.
add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

/** Stored SEO value (Yoast's own meta keys) of the queried post or term, else ''. */
function tng_seo_meta( $key ) {
	if ( is_singular() ) {
		return (string) get_post_meta( get_queried_object_id(), $key, true );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		return (string) get_term_meta( get_queried_object_id(), $key, true );
	}
	return '';
}

/** Meta description of the queried object: stored Yoast value, else the post excerpt (30 words). */
function tng_seo_description() {
	$desc = tng_seo_meta( '_yoast_wpseo_metadesc' );
	if ( '' === $desc && is_singular() ) {
		$desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_excerpt', get_queried_object_id() ) ), 30, '…' );
	}
	return $desc;
}

if ( ! defined( 'WPSEO_VERSION' ) ) { // Yoast reads the same meta itself
	add_filter(
		'pre_get_document_title',
		function ( $title ) {
			$seo = tng_seo_meta( '_yoast_wpseo_title' );
			return '' !== $seo ? $seo : $title;
		}
	);

	add_action(
		'wp_head',
		function () {
			$desc = tng_seo_description();
			if ( '' !== $desc ) {
				echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
		},
		1
	);

	add_filter(
		'wp_robots',
		function ( $robots ) {
			if ( '1' === tng_seo_meta( '_yoast_wpseo_meta-robots-noindex' ) ) {
				$robots['noindex'] = true;
			}
			return $robots;
		}
	);
}

// Term archives with no design render the placeholder: keep them out of the sitemap.
add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxonomies ) {
		return array_diff_key( $taxonomies, array_flip( array( 'post_tag', 'position', 'language', 'insight_category' ) ) );
	}
);
