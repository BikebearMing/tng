<?php
/**
 * Social / search tags the old site printed through Yoast (fix round 2026-11-14):
 * document title pattern, <html lang>, Open Graph, Twitter card, JSON-LD,
 * term canonicals, robots.txt rules. Everything is built from home_url() /
 * get_permalink(), so it follows the domain switch. Skipped when Yoast is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Language of the queried post as a BCP 47 tag ('' = the site's English). */
function tng_page_lang() {
	if ( ! is_singular() ) {
		return '';
	}
	$lang = (string) get_post_meta( get_queried_object_id(), '_tng_lang', true );
	if ( '' === $lang || 'en' === $lang ) {
		return '';
	}
	return 'zh' === $lang ? 'zh-CN' : $lang; // the old site printed zh-CN
}

/** og:locale for the queried post. */
function tng_og_locale() {
	$lang = tng_page_lang();
	if ( 0 === strpos( $lang, 'ja' ) ) {
		return 'ja_JP';
	}
	if ( 0 === strpos( $lang, 'zh' ) ) {
		return 'zh_CN';
	}
	return 'en_US';
}

// Japanese / Chinese articles and the desks: <html lang> (the <main lang> filter is in templates.php).
add_filter(
	'language_attributes',
	function ( $output ) {
		$lang = tng_page_lang();
		return '' === $lang ? $output : preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $lang ) . '"', $output );
	}
);

if ( defined( 'WPSEO_VERSION' ) ) {
	return;
}

// Old pattern: "About - Tilleke & Gibbins"; Home was "Home page - Tilleke & Gibbins". Core's
// separator is already "-", but wptexturize turns " - " into an en dash: put the last one back.
add_filter(
	'document_title',
	static function ( $title ) {
		return preg_replace( '/ (?:&#8211;|–) (?!.*(?:&#8211;|–))/u', ' - ', $title, 1 );
	}
);
add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_front_page() && ! is_paged() && empty( $parts['tagline'] ) ) {
			return array( 'title' => 'Home page', 'site' => get_bloginfo( 'name', 'display' ) );
		}
		return $parts;
	}
);

// New-site IDs are not shared links: the old site's ?p= IDs are (inc/helpers.php, legacy IDs).
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

/** Canonical URL of the queried object, else ''. */
function tng_seo_url() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_singular() ) {
		return (string) wp_get_canonical_url( get_queried_object_id() );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$link = get_term_link( get_queried_object() );
		return is_wp_error( $link ) ? '' : $link;
	}
	return '';
}

/** Share image: [url, width, height, mime] (featured image, portrait, else the bundled home banner). */
function tng_seo_image() {
	$id  = is_singular() ? get_queried_object_id() : 0;
	$att = $id ? (int) get_post_thumbnail_id( $id ) : 0;
	if ( ! $att && $id && 'professional' === get_post_type( $id ) && function_exists( 'get_field' ) ) {
		$raw = get_field( 'portrait', $id, false );
		$att = is_numeric( $raw ) ? (int) $raw : 0;
	}
	if ( $att ) {
		$src = wp_get_attachment_image_src( $att, 'large' );
		if ( $src ) {
			return array( $src[0], (int) $src[1], (int) $src[2], (string) get_post_mime_type( $att ) );
		}
	}
	return array( esc_url_raw( tng_asset( 'Rectangle 1.jpg' ) ), 2200, 1161, 'image/jpeg' ); // ponytail: the home banner until a share card is designed
}

/** People credited on an insight / award: linked professionals, then former ones (names). */
function tng_seo_authors( $id ) {
	$out = array();
	foreach ( tng_rel_ids( 'related_professionals', $id ) as $pid ) {
		if ( 'publish' === get_post_status( $pid ) ) {
			$out[] = array( '@type' => 'Person', 'name' => get_the_title( $pid ), 'url' => get_permalink( $pid ) );
		}
	}
	foreach ( tng_former_authors( $id ) as $name ) {
		$out[] = array( '@type' => 'Person', 'name' => $name );
	}
	return $out;
}

/** Plain text for tags (no entities left for esc_attr to double-encode). */
function tng_seo_text( $s ) {
	return trim( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

add_action(
	'wp_head',
	function () {
		if ( is_404() || is_search() ) {
			return;
		}
		$site  = tng_seo_text( get_bloginfo( 'name' ) );
		$title = tng_seo_text( wp_get_document_title() );
		$desc  = tng_seo_text( tng_seo_description() );
		$url   = tng_seo_url();
		$id    = is_singular() ? get_queried_object_id() : 0;
		$type  = $id ? get_post_type( $id ) : '';
		$og    = is_front_page() ? 'website' : ( in_array( $type, array( 'insight', 'award' ), true ) ? 'article' : ( 'professional' === $type ? 'profile' : 'website' ) );
		list( $img, $w, $h, $mime ) = tng_seo_image();

		$tags = array(
			'og:locale'    => tng_og_locale(),
			'og:type'      => $og,
			'og:title'     => $title,
			'og:description' => $desc,
			'og:url'       => $url,
			'og:site_name' => $site,
			'og:image'     => $img,
			'og:image:width'  => $w ? (string) $w : '',
			'og:image:height' => $h ? (string) $h : '',
			'og:image:type'   => $mime,
		);
		if ( 'article' === $og ) {
			$tags['article:published_time'] = get_post_time( 'c', true, $id );
			$tags['article:modified_time']  = get_post_modified_time( 'c', true, $id );
		}
		echo "\n";
		foreach ( $tags as $prop => $value ) {
			if ( '' !== (string) $value ) {
				echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $value ) . '">' . "\n";
			}
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

		// Term pages: core prints a canonical for singular views only.
		if ( $url && ! is_singular() && ! is_front_page() ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
		}

		// JSON-LD, close to the old Yoast graph: WebSite (+ search) and Organization,
		// WebPage + breadcrumbs, Article on insights / awards, Person on professionals.
		$home  = home_url( '/' );
		$graph = array(
			array(
				'@type'           => 'WebSite',
				'@id'             => $home . '#website',
				'url'             => $home,
				'name'            => $site,
				'publisher'       => array( '@id' => $home . '#organization' ),
				'inLanguage'      => 'en-US',
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}' ),
					'query-input' => 'required name=search_term_string',
				),
			),
		);
		if ( is_front_page() ) {
			$graph[] = array(
				'@type'  => 'Organization',
				'@id'    => $home . '#organization',
				'name'   => $site,
				'url'    => $home,
				'logo'   => array( '@type' => 'ImageObject', 'url' => tng_asset( 'header-logo-navy.png' ), 'width' => 520, 'height' => 236 ),
				'sameAs' => array_values( array_map( 'tng_default_social', array( 'linkedin', 'facebook', 'youtube', 'x' ) ) ),
			);
		}
		if ( $url ) {
			$page = array(
				'@type'      => 'WebPage',
				'@id'        => $url,
				'url'        => $url,
				'name'       => $title,
				'isPartOf'   => array( '@id' => $home . '#website' ),
				'inLanguage' => tng_page_lang() ? tng_page_lang() : 'en-US',
			);
			if ( $desc ) {
				$page['description'] = $desc;
			}
			$crumbs = array( array( 'Home', $home ) );
			if ( $id && ! is_front_page() ) {
				$parents = array(
					'insight'      => array( 'Insights', tng_page_url( 'insights', '' ) ),
					'award'        => array( 'Awards & Rankings', tng_page_url( 'about/awards-rankings', '' ) ),
					'professional' => array( 'Professionals', tng_page_url( 'professionals', '' ) ),
					'job'          => array( 'Job Opportunities', tng_page_url( 'careers/job-opportunities', '' ) ),
				);
				if ( isset( $parents[ $type ] ) && $parents[ $type ][1] ) {
					$crumbs[] = $parents[ $type ];
				} elseif ( 'page' === $type ) {
					$crumbs = array_merge( $crumbs, tng_page_trail( $id ) );
				}
				$crumbs[] = array( tng_seo_text( get_the_title( $id ) ), '' );
			}
			if ( count( $crumbs ) > 1 ) {
				$items = array();
				foreach ( $crumbs as $i => $c ) {
					$items[] = array_filter( array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => tng_seo_text( $c[0] ), 'item' => $c[1] ) );
				}
				$page['breadcrumb'] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
			}
			$graph[] = $page;
		}
		if ( in_array( $type, array( 'insight', 'award' ), true ) ) {
			$article = array(
				'@type'            => 'Article',
				'@id'              => $url . '#article',
				'headline'         => tng_seo_text( get_the_title( $id ) ),
				'datePublished'    => get_post_time( 'c', true, $id ),
				'dateModified'     => get_post_modified_time( 'c', true, $id ),
				'mainEntityOfPage' => array( '@id' => $url ),
				'publisher'        => array( '@type' => 'Organization', '@id' => $home . '#organization', 'name' => $site, 'url' => $home ),
				'inLanguage'       => tng_page_lang() ? tng_page_lang() : 'en-US',
				'image'            => $img,
			);
			$authors = tng_seo_authors( $id );
			if ( $authors ) {
				$article['author'] = $authors;
			}
			$graph[] = $article;
		} elseif ( 'professional' === $type ) {
			$person = array(
				'@type'    => 'Person',
				'@id'      => $url . '#person',
				'name'     => tng_seo_text( get_the_title( $id ) ),
				'url'      => $url,
				'image'    => $img,
				'worksFor' => array( '@type' => 'Organization', '@id' => $home . '#organization', 'name' => $site, 'url' => $home ),
			);
			$job = tng_seo_text( str_replace( array( '<br>', '<br />', "\n" ), ', ', (string) tng_pro_position( $id ) ) );
			if ( '' !== $job ) {
				$person['jobTitle'] = $job;
			}
			$graph[] = $person;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
	},
	2
);

// The old site's robots.txt rules that still apply (staging stays noindex until
// "Discourage search engines" is turned off; core adds the Sitemap line then).
add_filter(
	'robots_txt',
	function ( $output ) {
		$rules = array( '/?s=', '/*&s=', '/search/', '/author/', '/*?attachment_id=', '/*/feed', '/*/embed', '/*?p=', '/*download_insight_pdf', '/print-insight', '/print-professional', '/newsletter-privacy' );
		$add   = '';
		foreach ( $rules as $r ) {
			$add .= "Disallow: $r\n";
		}
		$add .= "Crawl-delay: 10\n";
		$output = preg_replace( '/^User-agent: \*\n/m', "User-agent: *\n" . $add, (string) $output, 1 );
		// Crawlers the old file blocked that Cloudflare's managed list does not.
		foreach ( array( 'Sogou web spider', 'Sogou inst spider', 'Sogou spider2', 'Sogou blog', 'Sogou News Spider', 'Sogou Orion spider', 'SEOkicks', 'ExaSearchBot', 'cohere-training-data-crawler' ) as $bot ) {
			$output .= "\nUser-agent: $bot\nDisallow: /\n";
		}
		return $output;
	},
	20
);
