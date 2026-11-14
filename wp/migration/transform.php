<?php
/**
 * Old site (www.tilleke.com) saved HTML → normalised JSON lines for the importer.
 *
 *   php wp/migration/transform.php            # parse everything in temporary/migration/raw/
 *   php wp/migration/transform.php --check    # self-check on fixed samples, writes nothing
 *   options: --raw=DIR --urls=DIR --out=DIR
 *
 * Reads only local files (never fetches). Deterministic: same input, same output.
 * A page that fails is logged in data/_errors.tsv and skipped.
 * Shape: temporary/migration/import-design.md 1.7 + loader-contract.md; field names
 * and kinds must match tng_import_types() in wp/tng-core/inc/import.php.
 * Needs PHP 8.x CLI with dom + mbstring + json.
 */

error_reporting( E_ALL );
ini_set( 'memory_limit', '2048M' );
libxml_use_internal_errors( true );

const BASE      = 'https://www.tilleke.com';
const FIRM      = 'Tilleke & Gibbins';
const COUNTRIES = array( 'cambodia', 'indonesia', 'laos', 'myanmar', 'thailand', 'vietnam' );
const LOC_IDS   = array( 4551 => 'cambodia', 4544 => 'indonesia', 4549 => 'laos', 4546 => 'myanmar', 4536 => 'thailand', 4542 => 'vietnam' );
const INDUSTRIES = array( 'automotive', 'aviation', 'consumer-products', 'energy', 'fintech', 'insurance', 'life-sciences', 'technology' );
const PRACTICE_SLUGS = array( 'corporate-and-ma' => 'corporate-ma' ); // old → new where they differ
// Old sub-practice slug → [new practice slug, name]; all five are tabs of the IP page.
const SUBS = array(
	'commercialization' => array( 'commercialization', 'Commercialization' ),
	'trademark'         => array( 'trademarks', 'Trademarks' ),
	'patent'            => array( 'patents', 'Patents' ),
	'enforcement'       => array( 'enforcement', 'Enforcement' ),
	'litigation'        => array( 'litigation', 'Litigation' ),
);
const RES_CATS = array( // old resource_category slug → verbatim name
	'publications'                            => 'Publications',
	'award'                                   => 'Awards & Rankings',
	'case-studies'                            => 'Case Studies',
	'case-studies-museum-of-counterfeit-goods' => 'Case Studies (Museum of Counterfeit Goods)',
	'csr-news'                                => 'CSR News',
	'museum-of-counterfeit-goods'             => 'Museum of Counterfeit Goods',
	'news'                                    => 'News',
	'textile-collection'                      => 'Textile Collection',
	'webinars'                                => 'Webinars',
);
// Terms that already exist on the new site (inc/seed.php). Anything else is emitted as new.
const NEW_SITE = array(
	'industry'         => array( 'automotive' => 'Automotive', 'aviation' => 'Aviation', 'consumer-products' => 'Consumer Products', 'energy' => 'Energy', 'fintech' => 'Fintech', 'insurance' => 'Insurance', 'life-sciences' => 'Life Sciences', 'technology' => 'Technology' ),
	'practice'         => array( 'antitrust-and-competition' => 'Antitrust and Competition', 'banking-and-finance' => 'Banking and Finance', 'capital-markets' => 'Capital Markets', 'compliance-and-investigations' => 'Compliance and Investigations', 'corporate-ma' => 'Corporate/M&A', 'customs-and-international-trade' => 'Customs and International Trade', 'dispute-resolution-and-litigation' => 'Dispute Resolution and Litigation', 'employment' => 'Employment', 'intellectual-property' => 'Intellectual Property', 'real-estate' => 'Real Estate', 'regulatory-affairs' => 'Regulatory Affairs', 'tax' => 'Tax', 'commercialization' => 'Commercialization', 'trademarks' => 'Trademarks', 'patents' => 'Patents' ),
	'location'         => array( 'cambodia' => 'Cambodia', 'indonesia' => 'Indonesia', 'laos' => 'Laos', 'myanmar' => 'Myanmar', 'thailand' => 'Thailand', 'vietnam' => 'Vietnam', 'bangkok' => 'Bangkok', 'hanoi' => 'Hanoi', 'ho-chi-minh-city' => 'Ho Chi Minh City', 'phnom-penh' => 'Phnom Penh', 'vientiane' => 'Vientiane', 'yangon' => 'Yangon' ),
	'position'         => array( 'partner' => 'Partner', 'consultant' => 'Consultant', 'senior-associate' => 'Senior Associate', 'associate' => 'Associate', 'patent-agent' => 'Patent Agent' ),
	'language'         => array( 'english' => 'English', 'thai' => 'Thai', 'vietnamese' => 'Vietnamese', 'lao' => 'Lao', 'burmese' => 'Burmese', 'khmer' => 'Khmer', 'indonesian' => 'Indonesian' ),
	'insight_category' => array( 'legal-updates' => 'Legal Updates', 'publications' => 'Publications' ),
);
// Old term → the term it duplicates (slug => [slug, name]): spelling variants of one language.
const ALIASES = array(
	'language' => array(
		'bahasa-indonesia' => array( 'indonesian', 'Indonesian' ),
		'bahasa-malaysia'  => array( 'malay', 'Malay' ),
	),
);
const PARENTS = array( // child term slug → parent slug (for terms we emit)
	'practice' => array( 'commercialization' => 'intellectual-property', 'trademarks' => 'intellectual-property', 'patents' => 'intellectual-property', 'enforcement' => 'intellectual-property', 'litigation' => 'intellectual-property' ),
	'location' => array( 'bangkok' => 'thailand', 'hanoi' => 'vietnam', 'ho-chi-minh-city' => 'vietnam', 'phnom-penh' => 'cambodia', 'vientiane' => 'laos', 'yangon' => 'myanmar', 'jakarta' => 'indonesia' ),
);
// Old position facet values (archive filter), longest first for matching against the free-text title.
const POSITIONS = array( 'Operations Professionals', 'Patent Agent', 'Regulatory Consultant', 'Trademark Specialist', 'Patent Specialist', 'Senior Associate', 'Consultant', 'Associate', 'Partner', 'Counsel' );
// Text that marks demo / filler widgets on the old site.
const FILLER = array( 'lorem ipsum', 'in the history of modern astronomy', 'there is something about parenthood' );
// Old page path → new page path (import-design 6, inventory 4.3).
const PAGE_MAP = array(
	'/'                                  => '/',
	'/about/'                            => '/about/',
	'/history/'                          => '/about/history/',
	'/awards-rankings/'                  => '/about/awards-rankings/',
	'/pro-bono/'                         => '/about/pro-bono/',
	'/csr/'                              => '/about/csr/',
	'/museum/'                           => '/about/museum-of-counterfeit-goods/',
	'/textile/'                          => '/about/textile-collection/',
	'/careers/'                          => '/careers/',
	'/job-opportunities/'                => '/careers/job-opportunities/',
	'/career-role/legal-professionals/'  => '/careers/legal-professionals/',
	'/career-role/operations-professionals/' => '/careers/operations-professionals/',
	'/career-role/internships/'          => '/careers/internships/',
	'/services/'                         => '/services/',
	'/practice-category/industries/'     => '/services/',
	'/practice-category/practices/'      => '/services/',
	'/contact/'                          => '/contact/',
	'/insights/'                         => '/insights/',
	'/insights-archive/'                 => '/insights/',
	'/professionals/'                    => '/professionals/',
	'/locations/'                        => '/',
	'/print-insight/'                    => '/',
	'/print-professional/'               => '/',
	'/test-widget/'                      => '/',
	'/pdf-download/'                     => '/',
	'/ja/'                               => '/',
	'/zh/'                               => '/',
);
const UTILITY_PAGES = array( '/print-insight/', '/print-professional/', '/test-widget/', '/pdf-download/' );

/* ================================================================ helpers */

/** Canonical old URL; mirrors tng_legacy_key() in wp/tng-core/inc/helpers.php. '' if not the old site. */
function key_of( $url ) {
	$url = trim( html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( '' === $url || preg_match( '#^(mailto|tel|javascript|data):#i', $url ) ) {
		return '';
	}
	if ( str_starts_with( $url, '//' ) ) {
		$url = 'https:' . $url;
	}
	$p = parse_url( $url );
	if ( false === $p ) {
		return '';
	}
	if ( isset( $p['host'] ) && ! preg_match( '/^(www\.)?tilleke\.com$/i', $p['host'] ) ) {
		return '';
	}
	if ( ! isset( $p['host'] ) && ! str_starts_with( $url, '/' ) ) {
		return '';
	}
	$path = '/' . ltrim( $p['path'] ?? '/', '/' );
	$path = preg_replace_callback( '/[^\x21-\x7E]/', fn( $m ) => rawurlencode( $m[0] ), $path );
	$path = preg_replace_callback( '/%[0-9a-f]{2}/i', fn( $m ) => strtoupper( $m[0] ), $path );
	if ( ! preg_match( '/\.[a-z0-9]{1,5}$/i', $path ) && ! str_ends_with( $path, '/' ) ) {
		$path .= '/';
	}
	return BASE . $path;
}

/** Relation link → key without the TranslatePress /ja/ or /zh/ prefix (JA/ZH posts link /ja/professionals/x/). */
function rel_key( $href ) {
	$key = key_of( $href );
	return '' === $key ? '' : preg_replace( '#^' . preg_quote( BASE, '#' ) . '/(ja|zh)/(?=(professionals|practices|sub_practices|locations|career-role)/)#i', BASE . '/', $key );
}

function path_of( $key ) {
	return substr( $key, strlen( BASE ) );
}

/** Slug of the last path segment, as stored by WP (percent-encoding lowercase). */
function slug_of( $key ) {
	$segs = array_values( array_filter( explode( '/', path_of( $key ) ), 'strlen' ) );
	return strtolower( (string) end( $segs ) );
}

/** WordPress-ish slug for a new term name. */
function slugify( $name ) {
	$s = strtolower( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) );
	$s = str_replace( array( '&', '/' ), ' ', $s );
	$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
	return trim( $s, '-' );
}

/** Cloudflare email obfuscation: first byte is the XOR key for the rest. */
function cf_decode( $hex ) {
	if ( ! preg_match( '/^([0-9a-f]{2})+$/i', $hex ) || strlen( $hex ) < 4 ) {
		return '';
	}
	$k   = hexdec( substr( $hex, 0, 2 ) );
	$out = '';
	for ( $i = 2; $i < strlen( $hex ); $i += 2 ) {
		$out .= chr( hexdec( substr( $hex, $i, 2 ) ) ^ $k );
	}
	return rawurldecode( $out );
}

function email_from_href( $href ) {
	if ( preg_match( '#/cdn-cgi/l/email-protection\#([0-9a-f]+)#i', $href, $m ) ) {
		return cf_decode( $m[1] );
	}
	if ( preg_match( '#^mailto:([^?]+)#i', $href, $m ) ) {
		return rawurldecode( $m[1] );
	}
	return '';
}

function norm_space( $s ) {
	$s = str_replace( array( "\u{200B}", "\u{FEFF}", "\u{00AD}", "\u{00A0}" ), array( '', '', '', ' ' ), (string) $s );
	$s = preg_replace( "#</?span(\\s+class=['\"]ep-highlight['\"])?>#", '', $s ); // ElasticPress highlight printed as text
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/** Plain text of a node; <br> becomes a newline when $br. Cloudflare email spans are decoded. */
function text_of( $node, $br = false ) {
	if ( ! $node ) {
		return '';
	}
	$out = '';
	foreach ( $node->childNodes as $c ) {
		if ( XML_TEXT_NODE === $c->nodeType || XML_CDATA_SECTION_NODE === $c->nodeType ) {
			$out .= $c->nodeValue;
		} elseif ( XML_ELEMENT_NODE === $c->nodeType ) {
			$tag = strtolower( $c->nodeName );
			if ( in_array( $tag, array( 'script', 'style', 'svg', 'noscript' ), true ) ) {
				continue;
			}
			if ( 'br' === $tag ) {
				$out .= $br ? "\n" : ' ';
			} elseif ( $c->hasAttribute( 'data-cfemail' ) ) {
				$out .= cf_decode( $c->getAttribute( 'data-cfemail' ) );
			} else {
				$out .= text_of( $c, $br ) . ( preg_match( '/^(p|div|li|h[1-6]|tr)$/', $tag ) ? ' ' : '' );
			}
		}
	}
	if ( $br ) {
		return trim( implode( "\n", array_map( 'norm_space', explode( "\n", $out ) ) ) );
	}
	return norm_space( $out );
}

function first_words( $text, $n = 55 ) {
	$w = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $w ) <= $n ) {
		return implode( ' ', $w );
	}
	return implode( ' ', array_slice( $w, 0, $n ) ) . '…';
}

/** Plain-text excerpt; CJK has no spaces, so cut by characters there. */
function excerpt_of( $text, $lang ) {
	$text = norm_space( $text );
	if ( 'en' !== $lang ) {
		return mb_strlen( $text ) > 120 ? mb_substr( $text, 0, 120 ) . '…' : $text;
	}
	return first_words( $text );
}

function load_doc( $html ) {
	$d = new DOMDocument();
	$d->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT | LIBXML_PARSEHUGE );
	return array( $d, new DOMXPath( $d ) );
}

function cls( $c ) {
	return "contains(concat(' ',normalize-space(@class),' '),' $c ')";
}

function q( $xp, $expr, $ctx = null ) {
	$r = $ctx ? $xp->query( $expr, $ctx ) : $xp->query( $expr );
	return $r ? iterator_to_array( $r ) : array();
}

function q1( $xp, $expr, $ctx = null ) {
	$r = q( $xp, $expr, $ctx );
	return $r ? $r[0] : null;
}

/** True when $n sits inside a posts loop item (derived lists, not this page's data). */
function in_loop( $n, $root ) {
	for ( $p = $n->parentNode; $p && $p !== $root; $p = $p->parentNode ) {
		if ( $p instanceof DOMElement && ( 'loop' === $p->getAttribute( 'data-elementor-type' ) || 'article' === strtolower( $p->nodeName ) ) ) {
			return true;
		}
	}
	return false;
}

function widget( $xp, $root, $id ) {
	return q1( $xp, ".//div[@data-id='$id']/div[" . cls( 'elementor-widget-container' ) . ']', $root );
}

function strip_site( $title ) {
	return trim( preg_replace( '/\s*[-|–]\s*Tilleke\s*(&|&amp;|and)\s*Gibbins\s*$/u', '', html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

function meta_content( $xp, $attr, $name ) {
	$n = q1( $xp, "//meta[@$attr='$name']" );
	return $n ? html_entity_decode( $n->getAttribute( 'content' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
}

/** Yoast JSON-LD: WebPage url / name / dates, primary image. */
function jsonld( $xp ) {
	$out = array( 'url' => '', 'name' => '', 'published' => '', 'modified' => '', 'image' => '', 'lang' => '' );
	foreach ( q( $xp, "//script[@type='application/ld+json']" ) as $s ) {
		$j = json_decode( $s->textContent, true );
		if ( ! is_array( $j ) ) {
			continue;
		}
		foreach ( $j['@graph'] ?? array( $j ) as $g ) {
			$types = (array) ( $g['@type'] ?? array() );
			if ( array_intersect( $types, array( 'WebPage', 'ItemPage', 'CollectionPage', 'ProfilePage', 'AboutPage', 'ContactPage' ) ) && '' === $out['url'] ) {
				$out['url']       = (string) ( $g['url'] ?? '' );
				$out['name']      = (string) ( $g['name'] ?? '' );
				$out['published'] = (string) ( $g['datePublished'] ?? '' );
				$out['modified']  = (string) ( $g['dateModified'] ?? '' );
				$out['lang']      = (string) ( $g['inLanguage'] ?? '' );
				if ( ! empty( $g['thumbnailUrl'] ) ) {
					$out['image'] = (string) $g['thumbnailUrl'];
				}
			}
			if ( in_array( 'ImageObject', $types, true ) && str_ends_with( (string) ( $g['@id'] ?? '' ), '#primaryimage' ) ) {
				$out['image'] = (string) ( $g['contentUrl'] ?? $g['url'] ?? $out['image'] );
			}
		}
	}
	return $out;
}

function seo_of( $xp ) {
	$t = q1( $xp, '//head/title' ) ?: q1( $xp, '//title' );
	return array(
		'title'       => $t ? norm_space( html_entity_decode( $t->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '',
		'description' => norm_space( meta_content( $xp, 'name', 'description' ) ),
		'noindex'     => (bool) preg_match( '/noindex/i', meta_content( $xp, 'name', 'robots' ) ),
	);
}

/** First non-empty og:image (the theme prints a second, empty og block). */
function og_image( $xp ) {
	foreach ( q( $xp, "//meta[@property='og:image']" ) as $m ) {
		if ( '' !== trim( $m->getAttribute( 'content' ) ) ) {
			return trim( $m->getAttribute( 'content' ) );
		}
	}
	return '';
}

/** ISO 8601 → [local Asia/Bangkok, GMT], both Y-m-d H:i:s. */
function dates_of( $iso ) {
	if ( '' === $iso ) {
		return array( '', '' );
	}
	try {
		$d = new DateTimeImmutable( $iso );
	} catch ( Exception $e ) {
		return array( '', '' );
	}
	$gmt = $d->setTimezone( new DateTimeZone( 'UTC' ) );
	return array( $gmt->setTimezone( new DateTimeZone( 'Asia/Bangkok' ) )->format( 'Y-m-d H:i:s' ), $gmt->format( 'Y-m-d H:i:s' ) );
}

/** Root-relative path for links to old-site pages; uploads and other hosts stay absolute. */
function link_href( $href ) {
	$href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( '' === $href || '#' === $href || preg_match( '#^javascript:#i', $href ) ) {
		return '';
	}
	$mail = email_from_href( $href );
	if ( $mail ) {
		return 'mailto:' . $mail;
	}
	if ( preg_match( '#^(mailto|tel):#i', $href ) || str_starts_with( $href, '#' ) ) {
		return $href;
	}
	if ( preg_match( '/^[\w.+-]+@[\w-]+(\.[\w-]+)+$/', $href ) ) { // href="events@tilleke.com"
		return 'mailto:' . $href;
	}
	if ( preg_match( '#^(professionals|insights|practices|locations)/#', $href ) ) { // href="professionals/aaron"
		$href = '/' . $href;
	}
	$key = key_of( $href );
	if ( '' === $key ) {
		return $href; // another host
	}
	$path = path_of( $key );
	if ( preg_match( '#^/(wp-content|sites)/#', $path ) ) {
		return $key; // a file: absolute, the loader maps uploads
	}
	$p = parse_url( $href );
	return $path . ( isset( $p['query'] ) ? '?' . $p['query'] : '' ) . ( isset( $p['fragment'] ) ? '#' . $p['fragment'] : '' );
}

function abs_src( $src ) {
	$src = trim( html_entity_decode( $src, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( str_starts_with( $src, '//' ) ) {
		return 'https:' . $src;
	}
	if ( str_starts_with( $src, '/' ) ) {
		return BASE . $src;
	}
	return $src;
}

/* ================================================================ state */

$G = array(
	'media'     => array(), // orig src => record
	'dropped'   => array(), // [item, url, why]
	'deadlinks' => array(), // [item, url]
	'rels'      => array(), // [item, kind, target key]
	'unhomed'   => array(), // "tax/slug" => [name, count]
	'skipped'   => array(), // [src, why]
	'errors'    => array(), // [file, src, message]
	'notes'     => array(), // free-form [src, note]
	'terms'     => array(), // "tax/slug" => [taxonomy, slug, name, parent, src]
	'known'     => array(), // keys from the URL lists
);

function note( $src, $msg ) {
	$GLOBALS['G']['notes'][] = array( $src, $msg );
}

function is_dead_drupal( $url ) {
	// the Drupal-era files, on the old domain or the retired dev host (no longer answers)
	return (bool) preg_match( '#^https?://((www\.)?tilleke\.com/sites/default/files/|tilleke-old\.lexiconthai\.dev/)#i', $url );
}

/** Embed URL → [watch URL, link text] for the players old bodies used; else [src, src]. */
function embed_link( $src ) {
	if ( preg_match( '#youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]+)#', $src, $m ) ) {
		return array( 'https://www.youtube.com/watch?v=' . $m[1], 'Watch the video on YouTube' );
	}
	if ( preg_match( '#open\.spotify\.com/embed/(.+?)(\?|$)#', $src, $m ) ) {
		return array( 'https://open.spotify.com/' . $m[1], 'Listen on Spotify' );
	}
	return array( $src, $src );
}

/** Register a media reference. Sized variants (-300x200, -scaled) collapse to the original file. */
/** Registers a media file; returns the URL as referenced (for bodies), or its media src when $field (image fields look media up by src). */
function media( $url, $item, $role, $alt = '', $field = false ) {
	$url = abs_src( $url );
	$key = key_of( $url );
	if ( '' === $key || ! str_contains( $key, '/wp-content/uploads/' ) ) {
		return $url;
	}
	$orig = preg_replace( '/-(\d+x\d+)(?=\.[a-z0-9]+$)/i', '', $key );
	$orig = preg_replace( '/-scaled(?=\.[a-z0-9]+$)/i', '', $orig );
	if ( str_contains( $orig, '/uploads/elementor/thumbs/' ) ) {
		note( $item, 'elementor thumb referenced: ' . $key );
	}
	$ext  = strtolower( pathinfo( parse_url( $orig, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$kind = in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tif', 'tiff' ), true ) ? 'image' : ( 'pdf' === $ext ? 'pdf' : 'file' );
	$m    = &$GLOBALS['G']['media'][ $orig ];
	if ( ! $m ) {
		preg_match( '#/uploads/(\d{4}/\d{2})/#', $orig, $pm );
		$m = array( 'src' => $orig, 'path' => $pm[1] ?? '', 'kind' => $kind, 'alt' => '', 'title' => '', 'caption' => '', 'referenced_as' => array(), 'uses' => array() );
	}
	if ( $key !== $orig ) {
		$m['referenced_as'][ $key ] = 1;
	} else {
		$m['direct'] = true;
	}
	if ( '' === $m['alt'] && '' !== trim( $alt ) ) {
		$m['alt'] = norm_space( $alt );
	}
	$m['uses'][ $item . '|' . $role ] = array( 'item' => $item, 'role' => $role );
	return $field ? $orig : $key;
}

function rel( $item, $kind, $target ) {
	$GLOBALS['G']['rels'][] = array( $item, $kind, $target );
}

/** Record a term we need (existing on new site or not). */
function want_term( $tax, $slug, $name, $src = '' ) {
	if ( isset( ALIASES[ $tax ][ $slug ] ) ) {
		list( $slug, $name ) = ALIASES[ $tax ][ $slug ];
	}
	$k = "$tax/$slug";
	$t = &$GLOBALS['G']['terms'][ $k ];
	if ( ! $t ) {
		$exists = isset( NEW_SITE[ $tax ][ $slug ] );
		$t      = array(
			'taxonomy' => $tax,
			'slug'     => $slug,
			'name'     => $exists ? NEW_SITE[ $tax ][ $slug ] : $name,
			'parent'   => PARENTS[ $tax ][ $slug ] ?? '',
			'src'      => $src,
			'exists'   => $exists,
			'uses'     => 0,
		);
	}
	if ( '' === $t['src'] && '' !== $src ) {
		$t['src'] = $src;
	}
	++$t['uses'];
	if ( ! $t['exists'] ) {
		$GLOBALS['G']['unhomed'][ $k ] = $t['name'];
	}
	return $slug;
}

/** Old /practices/<slug>/ or /sub_practices/<slug>/ link → [taxonomy, new slug] or null. */
function service_term( $href, $item, $name = '' ) {
	$key = rel_key( $href );
	if ( '' === $key ) {
		return null;
	}
	$path = strtolower( path_of( $key ) );
	if ( preg_match( '#^/practices/([^/]+)/$#', $path, $m ) ) {
		rel( $item, 'service', BASE . $path );
		$old = $m[1];
		if ( in_array( $old, INDUSTRIES, true ) || ( $GLOBALS['INDUSTRY_PAGES'][ $old ] ?? false ) ) {
			return array( 'industry', want_term( 'industry', $old, $name, BASE . $path ) );
		}
		return array( 'practice', want_term( 'practice', PRACTICE_SLUGS[ $old ] ?? $old, $name, BASE . $path ) );
	}
	if ( preg_match( '#^/sub_practices/([^/]+)/$#', $path, $m ) ) {
		rel( $item, 'service', BASE . $path );
		$s = SUBS[ $m[1] ] ?? array( $m[1], $name );
		return array( 'practice', want_term( 'practice', $s[0], $s[1], BASE . $path ) );
	}
	return null;
}

function location_term( $href, $item ) {
	$key = rel_key( $href );
	if ( '' !== $key && preg_match( '#^/locations/([^/]+)/$#', strtolower( path_of( $key ) ), $m ) ) {
		rel( $item, 'location', BASE . '/locations/' . $m[1] . '/' );
		return want_term( 'location', $m[1], ucfirst( $m[1] ), BASE . '/locations/' . $m[1] . '/' );
	}
	return null;
}

function pro_key( $href, $item ) {
	$key = rel_key( $href );
	if ( '' === $key || ! preg_match( '#^/professionals/[^/]+/$#i', path_of( $key ) ) ) {
		return '';
	}
	$key = strtolower( $key ); // body links use other casing (/professionals/NWE-OO/); WP slugs are lowercase
	rel( $item, 'professional', $key );
	return $key;
}

/* ================================================================ body cleaning */

const KEEP = array( 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'strong', 'em', 'u', 'sup', 'sub', 'br', 'hr', 'a', 'img', 'figure', 'figcaption', 'pre', 'code', 'dl', 'dt', 'dd', 's' );
const RENAME = array( 'b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'strike' => 's', 'del' => 's' );
const DROP = array( 'script', 'style', 'noscript', 'svg', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'object', 'embed', 'template', 'canvas', 'map', 'audio', 'source', 'picture' ); // video: see clean_node()
const BLOCKS = array( 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'table', 'figure', 'pre', 'dl', 'hr' );

/**
 * Elementor / WP rendered HTML → clean semantic HTML: no classes, styles, scripts or
 * wrappers. Old-site links become root-relative, Cloudflare emails are decoded, images
 * register in the media manifest, dead Drupal images are dropped. $skip: callback(DOMElement) → bool.
 */
function clean_html( $nodes, $item, $role = 'inline', $skip = null ) {
	$doc  = new DOMDocument( '1.0', 'UTF-8' );
	$wrap = $doc->appendChild( $doc->createElement( 'div' ) );
	foreach ( ( $nodes instanceof DOMNode ) ? array( $nodes ) : $nodes as $n ) {
		foreach ( $n->childNodes as $c ) {
			clean_node( $c, $wrap, $doc, $item, $role, $skip );
		}
	}
	tidy( $wrap, $doc );
	$html = '';
	foreach ( $wrap->childNodes as $c ) {
		$html .= $doc->saveHTML( $c );
	}
	return trim( preg_replace( '/\n{2,}/', "\n", $html ) );
}

function clean_node( $n, $out, $doc, $item, $role, $skip ) {
	if ( XML_TEXT_NODE === $n->nodeType || XML_CDATA_SECTION_NODE === $n->nodeType ) {
		$t = str_replace( array( "\u{200B}", "\u{FEFF}" ), '', $n->nodeValue );
		$t = preg_replace( '/\[\/?(jet_|elementor-template|vc_|caption|embed|et_pb_)[^\]]*\]/i', '', $t );
		$t = preg_replace( '/[ \t\r\n]+/', ' ', $t );
		$t = preg_replace( "#</?span(\\s+class=['\"]ep-highlight['\"])?>#", '', $t );
		if ( '' !== $t ) {
			$out->appendChild( $doc->createTextNode( $t ) );
		}
		return;
	}
	if ( XML_ELEMENT_NODE !== $n->nodeType ) {
		return; // comments, PIs
	}
	if ( $skip && $skip( $n ) ) {
		return;
	}
	$tag = strtolower( $n->nodeName );
	$tag = RENAME[ $tag ] ?? $tag;
	if ( in_array( $tag, DROP, true ) ) {
		return;
	}
	if ( 'video' === $tag ) { // self-hosted player: <video controls> with the old upload (the loader maps it)
		$src = '';
		foreach ( array_merge( array( $n ), iterator_to_array( $n->getElementsByTagName( 'source' ) ) ) as $s ) {
			if ( '' !== trim( $s->getAttribute( 'src' ) ) ) {
				$src = preg_replace( '/\?.*$/', '', abs_src( $s->getAttribute( 'src' ) ) );
				break;
			}
		}
		if ( '' !== $src ) {
			$v = $out->appendChild( $doc->createElement( 'video' ) );
			$v->setAttribute( 'controls', '' );
			$v->setAttribute( 'preload', 'metadata' );
			$v->setAttribute( 'src', media( $src, $item, 'video' ) );
			if ( '' !== trim( $n->getAttribute( 'poster' ) ) ) {
				$v->setAttribute( 'poster', media( abs_src( $n->getAttribute( 'poster' ) ), $item, 'poster' ) );
			}
		}
		return;
	}
	if ( 'iframe' === $tag ) { // embeds (video): keep as a link
		$src = abs_src( $n->getAttribute( 'src' ) ?: $n->getAttribute( 'data-src' ) );
		if ( preg_match( '#^https?://#', $src ) ) {
			list( $href, $label ) = embed_link( $src );
			$p = $out->appendChild( $doc->createElement( 'p' ) );
			$a = $p->appendChild( $doc->createElement( 'a' ) );
			$a->setAttribute( 'href', $href );
			$a->appendChild( $doc->createTextNode( $label ) );
			note( $item, 'iframe kept as link: ' . $src );
		}
		return;
	}
	if ( $n->hasAttribute( 'data-cfemail' ) ) {
		$mail = cf_decode( $n->getAttribute( 'data-cfemail' ) );
		if ( 'a' === $tag && '' !== $mail ) { // <a class="__cf_email__">: the old page's mailto link
			$a = $out->appendChild( $doc->createElement( 'a' ) );
			$a->setAttribute( 'href', 'mailto:' . $mail );
			$a->appendChild( $doc->createTextNode( $mail ) );
		} else {
			$out->appendChild( $doc->createTextNode( $mail ) );
		}
		return;
	}
	if ( 'img' === $tag ) {
		$src = '';
		foreach ( array( 'data-src', 'data-lazy-src', 'src' ) as $a ) {
			$v = trim( $n->getAttribute( $a ) );
			if ( '' !== $v && ! str_starts_with( $v, 'data:' ) ) {
				$src = abs_src( $v );
				break;
			}
		}
		if ( '' === $src ) {
			return;
		}
		if ( is_dead_drupal( $src ) ) {
			$GLOBALS['G']['dropped'][] = array( $item, $src, 'dead Drupal path' );
			return;
		}
		$alt = $n->getAttribute( 'alt' );
		$src = media( $src, $item, $role, $alt );
		$img = $out->appendChild( $doc->createElement( 'img' ) );
		$img->setAttribute( 'src', $src );
		$img->setAttribute( 'alt', norm_space( $alt ) );
		return;
	}
	if ( ! in_array( $tag, KEEP, true ) ) { // div, span, section, font …: unwrap
		foreach ( $n->childNodes as $c ) {
			clean_node( $c, $out, $doc, $item, $role, $skip );
		}
		return;
	}
	$el = $doc->createElement( $tag );
	if ( 'a' === $tag ) {
		$href = link_href( $n->getAttribute( 'href' ) );
		if ( '' === $href ) { // anchor without a target: keep its content only
			foreach ( $n->childNodes as $c ) {
				clean_node( $c, $out, $doc, $item, $role, $skip );
			}
			return;
		}
		if ( is_dead_drupal( $href ) ) {
			$GLOBALS['G']['deadlinks'][] = array( $item, $href );
		} elseif ( str_contains( $href, '/wp-content/uploads/' ) ) {
			$href = media( $href, $item, 'pdf' === strtolower( pathinfo( parse_url( $href, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ? 'download' : 'linked' );
		}
		$el->setAttribute( 'href', $href );
		$anchor = $n->getAttribute( 'id' ) ?: $n->getAttribute( 'name' ); // footnote targets (#_ftn1 / #_ftnref1)
		if ( preg_match( '/^[A-Za-z_][\w.:-]*$/', $anchor ) ) {
			$el->setAttribute( 'id', $anchor );
		}
	}
	if ( in_array( $tag, array( 'td', 'th' ), true ) ) {
		foreach ( array( 'colspan', 'rowspan' ) as $a ) {
			if ( $n->hasAttribute( $a ) && (int) $n->getAttribute( $a ) > 1 ) {
				$el->setAttribute( $a, (string) (int) $n->getAttribute( $a ) );
			}
		}
	}
	$out->appendChild( $el );
	foreach ( $n->childNodes as $c ) {
		clean_node( $c, $el, $doc, $item, $role, $skip );
	}
}

function is_blank( $el ) {
	if ( in_array( strtolower( $el->nodeName ), array( 'img', 'br', 'hr', 'td', 'th', 'tr', 'video' ), true ) ) {
		return false;
	}
	foreach ( array( 'img', 'video' ) as $t ) {
		foreach ( $el->getElementsByTagName( $t ) as $unused ) {
			return false;
		}
	}
	return '' === norm_space( $el->textContent );
}

/** Remove empty elements, wrap stray inline runs in <p>, trim whitespace. */
function tidy( $wrap, $doc ) {
	do { // empties (inner first)
		$removed = 0;
		$all     = array();
		foreach ( $wrap->getElementsByTagName( '*' ) as $e ) {
			$all[] = $e;
		}
		foreach ( array_reverse( $all ) as $e ) {
			if ( $e->parentNode && ! in_array( strtolower( $e->nodeName ), array( 'br', 'hr', 'img', 'td', 'th', 'tr', 'video' ), true ) && is_blank( $e ) ) {
				// an inline element holding only a space ("issued<strong> </strong>two") keeps the space
				if ( ! in_array( strtolower( $e->nodeName ), array_merge( BLOCKS, array( 'li', 'tbody', 'thead', 'tfoot', 'caption', 'figcaption', 'dt', 'dd' ) ), true ) && preg_match( '/[\s\x{00A0}]/u', $e->textContent ) ) {
					$e->parentNode->replaceChild( $doc->createTextNode( ' ' ), $e );
				} else {
					$e->parentNode->removeChild( $e );
				}
				++$removed;
			}
		}
	} while ( $removed );
	// whitespace-only text between block children
	foreach ( iterator_to_array( $wrap->getElementsByTagName( '*' ) ) as $e ) {
		if ( in_array( strtolower( $e->nodeName ), array( 'ul', 'ol', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'dl', 'blockquote', 'figure' ), true ) ) {
			foreach ( iterator_to_array( $e->childNodes ) as $c ) {
				if ( XML_TEXT_NODE === $c->nodeType && '' === trim( $c->nodeValue ) ) {
					$e->removeChild( $c );
				}
			}
		}
	}
	// stray inline content at the top level (unwrapped divs) → <p>
	$kids = iterator_to_array( $wrap->childNodes );
	$run  = array();
	$flush = function () use ( &$run, $wrap, $doc ) {
		$text = '';
		foreach ( $run as $r ) {
			$text .= $r->textContent;
		}
		$has_img = false;
		foreach ( $run as $r ) {
			if ( $r instanceof DOMElement && ( 'img' === $r->nodeName || $r->getElementsByTagName( 'img' )->length ) ) {
				$has_img = true;
			}
		}
		if ( $run && ( '' !== norm_space( $text ) || $has_img ) ) {
			$p = $doc->createElement( 'p' );
			$wrap->insertBefore( $p, $run[0] );
			foreach ( $run as $r ) {
				$p->appendChild( $r );
			}
		} else {
			foreach ( $run as $r ) {
				if ( 'br' === $r->nodeName || XML_TEXT_NODE === $r->nodeType ) {
					$wrap->removeChild( $r );
				}
			}
		}
		$run = array();
	};
	foreach ( $kids as $k ) {
		if ( $k instanceof DOMElement && in_array( strtolower( $k->nodeName ), array_merge( BLOCKS, array( 'li', 'tr', 'td', 'th', 'tbody', 'thead', 'figcaption', 'caption', 'dt', 'dd' ) ), true ) ) {
			$flush();
		} else {
			$run[] = $k;
		}
	}
	$flush();
	// trim leading / trailing spaces and <br> inside blocks
	foreach ( iterator_to_array( $wrap->getElementsByTagName( '*' ) ) as $e ) {
		if ( ! in_array( strtolower( $e->nodeName ), array( 'p', 'li', 'h2', 'h3', 'h4', 'h5', 'h6', 'td', 'th', 'figcaption', 'blockquote', 'dt', 'dd' ), true ) ) {
			continue;
		}
		foreach ( array( 'firstChild', 'lastChild' ) as $end ) {
			while ( $e->$end && ( ( 'br' === $e->$end->nodeName ) || ( XML_TEXT_NODE === $e->$end->nodeType && '' === trim( $e->$end->nodeValue ) ) ) ) {
				$e->removeChild( $e->$end );
			}
			if ( $e->$end && XML_TEXT_NODE === $e->$end->nodeType ) {
				$e->$end->nodeValue = 'firstChild' === $end ? ltrim( $e->$end->nodeValue ) : rtrim( $e->$end->nodeValue );
			}
		}
	}
}

function html_text( $html ) {
	return norm_space( html_entity_decode( strip_tags( preg_replace( '#<(/p|br|/li|/h\d)[^>]*>#i', ' $0', $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/* ================================================================ parsers */

function single_root( $xp, $type ) {
	return q1( $xp, "//div[@data-elementor-type='single' and " . cls( "type-$type" ) . ']' );
}

function lang_of( $key ) {
	return preg_match( '#^/(ja|zh)/#', path_of( $key ), $m ) ? $m[1] : 'en';
}

function parse_professional( $key, $xp, $doc ) {
	$root = single_root( $xp, 'professional' );
	if ( ! $root ) {
		throw new RuntimeException( 'no professional single template' );
	}
	$ld    = jsonld( $xp );
	$seo   = seo_of( $xp );
	$title = text_of( widget( $xp, $root, '57c0a98' ) ) ?: strip_site( $ld['name'] ?: meta_content( $xp, 'property', 'og:title' ) );
	if ( '' === $title ) {
		throw new RuntimeException( 'no name' );
	}
	$phone = $email = '';
	$extra = array();
	$c     = widget( $xp, $root, '19ae5f3' );
	foreach ( $c ? q( $xp, './/a', $c ) : array() as $a ) {
		$h = $a->getAttribute( 'href' );
		if ( str_starts_with( $h, 'tel:' ) ) {
			$v = text_of( $a ) ?: rawurldecode( substr( $h, 4 ) );
			'' === $phone ? $phone = $v : $extra[] = $v;
		} elseif ( $m = email_from_href( $h ) ) {
			'' === $email ? $email = $m : $extra[] = $m;
		}
	}
	if ( $extra ) {
		note( $key, 'extra contacts not imported: ' . implode( ', ', $extra ) );
	}
	$portrait = $ld['image'] ?: og_image( $xp );
	if ( ! $portrait && ( $img = q1( $xp, './/img', widget( $xp, $root, 'a35ea7d' ) ?: $root ) ) ) {
		$portrait = $img->getAttribute( 'src' );
	}
	$portrait = $portrait ? media( $portrait, $key, 'portrait', $title, true ) : null;

	$bio_el = widget( $xp, $root, '8fc973d' );
	$bio    = $bio_el ? clean_html( $bio_el, $key ) : '';
	$exp_el = widget( $xp, $root, 'e675a57' );
	$exp    = $exp_el ? clean_html( $exp_el, $key ) : '';

	$terms = array( 'location' => array(), 'industry' => array(), 'practice' => array(), 'position' => array(), 'language' => array() );
	$edu   = array();
	$boxes = q( $xp, './/div[' . cls( 'practices-professional' ) . ' or ' . cls( 'pro-industries' ) . ' or ' . cls( 'pro-education' ) . ']', $root );
	foreach ( $boxes as $box ) {
		if ( in_loop( $box, $root ) ) {
			continue;
		}
		$h3    = q1( $xp, './/h3', $box );
		$label = strtolower( text_of( $h3 ) );
		if ( in_array( $label, array( 'industries', 'practices' ), true ) ) {
			foreach ( q( $xp, './/a', $box ) as $a ) {
				if ( $t = service_term( $a->getAttribute( 'href' ), $key, text_of( $a ) ) ) {
					$terms[ $t[0] ][] = $t[1];
				}
			}
		} elseif ( 'location' === $label || 'locations' === $label ) {
			foreach ( q( $xp, './/a', $box ) as $a ) {
				if ( $t = location_term( $a->getAttribute( 'href' ), $key ) ) {
					$terms['location'][] = $t;
				}
			}
		} elseif ( 'languages' === $label || 'language' === $label ) {
			foreach ( q( $xp, './/p|.//li', $box ) as $p ) {
				$name = text_of( $p );
				if ( '' !== $name ) {
					$terms['language'][] = want_term( 'language', slugify( $name ), $name );
				}
			}
		} elseif ( 'education' === $label ) {
			foreach ( q( $xp, './/p|.//li', $box ) as $p ) {
				if ( '' !== ( $e = text_of( $p ) ) ) {
					$edu[] = array( 'entry' => $e );
				}
			}
			// 66 profiles hold the entries as bare text in the <ul>, one per line
			if ( ! $edu && ( $ul = q1( $xp, './/ul', $box ) ) ) {
				foreach ( preg_split( '/\R/u', $ul->textContent ) as $line ) {
					if ( '' !== ( $e = trim( preg_replace( '/\s+/u', ' ', $line ) ) ) ) {
						$edu[] = array( 'entry' => $e );
					}
				}
			}
		} elseif ( '' !== $label ) { // sections with no target field → appended to the biography (design 1.7)
			$bio .= '<h3>' . htmlspecialchars( text_of( $h3 ) ) . '</h3>' . clean_html( $box, $key, 'inline', fn( $e ) => $e === $h3 );
			note( $key, 'extra section appended to biography: ' . text_of( $h3 ) );
		}
	}
	$position_title = text_of( widget( $xp, $root, 'f9635d3' ), true );
	// Position facet isn't on the single page: inferred from the title. Every facet value it
	// names ("Consultant and Patent Agent" = both); longest first, a match is cut out so
	// "Senior Associate" doesn't also count as "Associate".
	$rest = $position_title;
	foreach ( POSITIONS as $p ) {
		$re = '/\b' . preg_quote( $p, '/' ) . '\b/i';
		if ( preg_match( $re, $rest ) ) {
			$terms['position'][] = want_term( 'position', slugify( $p ), $p );
			$rest = preg_replace( $re, ' ', $rest );
		}
	}
	list( $date, $gmt ) = dates_of( $ld['published'] );
	foreach ( $terms as $t => $v ) {
		$terms[ $t ] = array_values( array_unique( $v ) );
	}
	return array(
		'src'      => $key,
		'slug'     => slug_of( $key ),
		'status'   => 'publish',
		'date'     => $date,
		'date_gmt' => $gmt,
		'title'    => $title,
		'content'  => '',
		'terms'    => $terms,
		'fields'   => array(
			'portrait'       => $portrait,
			'headshot'       => null,
			'position_title' => $position_title,
			'phone'          => $phone,
			'email'          => $email,
			'lead'           => '',
			'biography'      => $bio,
			'education'      => $edu,
			'experience'     => $exp,
		),
		'seo'      => $seo,
		'legacy'   => array( 'id' => old_id( $xp ), 'lang' => lang_of( $key ), 'position_inferred' => (bool) $terms['position'] ),
	);
}

function old_id( $xp ) {
	$b = q1( $xp, '//body' );
	return $b && preg_match( '/\bpostid-(\d+)/', $b->getAttribute( 'class' ), $m ) ? (int) $m[1] : ( $b && preg_match( '/\bpage-id-(\d+)/', $b->getAttribute( 'class' ), $m ) ? (int) $m[1] : 0 );
}

function parse_resource( $key, $xp, $doc ) {
	$root = single_root( $xp, 'resource' );
	if ( ! $root ) {
		throw new RuntimeException( 'no resource single template' );
	}
	$ld   = jsonld( $xp );
	$seo  = seo_of( $xp );
	$lang = lang_of( $key );
	preg_match_all( '/\bresource_category-([a-z0-9-]+)/', $root->getAttribute( 'class' ), $m );
	$cats = array_values( array_unique( $m[1] ) );
	sort( $cats );

	$title = '';
	foreach ( array( 'd08ae5c', 'f6ffe1b' ) as $id ) {
		if ( $w = widget( $xp, $root, $id ) ) {
			$title = text_of( $w );
			break;
		}
	}
	$title = $title ?: strip_site( meta_content( $xp, 'property', 'og:title' ) ?: $ld['name'] );
	if ( '' === $title ) {
		throw new RuntimeException( 'no title' );
	}
	list( $date, $gmt ) = dates_of( $ld['published'] ?: meta_content( $xp, 'property', 'article:published_time' ) );
	if ( '' === $date ) {
		throw new RuntimeException( 'no datePublished' );
	}

	$body_w = null;
	foreach ( q( $xp, ".//div[@data-widget_type='theme-post-content.default']/div[" . cls( 'elementor-widget-container' ) . ']', $root ) as $w ) {
		if ( ! in_loop( $w, $root ) ) {
			$body_w = $w;
			break;
		}
	}
	$body = $body_w ? clean_html( $body_w, $key ) : '';

	$pub = text_of( widget( $xp, $root, '2086dab' ) );
	$pdf = '';
	foreach ( q( $xp, './/div[' . cls( 'download-button' ) . ']//a[@href]', $root ) as $a ) {
		$pdf = abs_src( $a->getAttribute( 'href' ) );
		break;
	}
	$content = $body;
	if ( '' !== $pub && FIRM !== $pub ) { // design Q6 default: source publication as a first line
		$content = '<p><em>' . htmlspecialchars( $pub ) . '</em></p>' . $content;
	}
	// "Download Full Article": an old upload goes to the module_report field (shown as the
	// download banner on the Standard template, user 2026-11-13); anything else stays a body link.
	$report = null;
	if ( '' !== $pdf ) {
		if ( is_dead_drupal( $pdf ) ) {
			$GLOBALS['G']['deadlinks'][] = array( $key, $pdf );
		}
		if ( str_contains( $pdf, '/wp-content/uploads/' ) && '' !== key_of( $pdf ) ) {
			$report = media( $pdf, $key, 'download', '', true );
		} elseif ( ! str_contains( $content, htmlspecialchars( $pdf ) ) ) {
			$content .= '<p><a href="' . htmlspecialchars( $pdf ) . '">Download Full Article</a></p>';
		}
	}

	// A real post thumbnail only: without one, Yoast's image is the first body image
	// (mostly tables), which would become a cropped hero. Skip it when the body shows it anyway.
	$feat = null;
	if ( str_contains( $root->getAttribute( 'class' ), 'has-post-thumbnail' ) ) {
		$img = $ld['image'] ?: og_image( $xp );
		$img = ( $img && ! is_dead_drupal( abs_src( $img ) ) ) ? abs_src( $img ) : '';
		$base_name = $img ? preg_replace( '/(-\d+x\d+|-scaled)?\.[a-z0-9]+$/i', '', basename( parse_url( $img, PHP_URL_PATH ) ) ) : '';
		if ( '' !== $base_name && ! str_contains( $content, '/' . $base_name ) ) {
			$feat = media( $img, $key, 'featured', '', true );
		} elseif ( '' !== $base_name ) {
			note( $key, 'thumbnail is also the first body image: not used as featured image' );
		}
	}

	$pros   = array();
	$former = array(); // cards with no profile link: people who left (name only, field former_authors)
	foreach ( q( $xp, './/article[' . cls( 'type-professional' ) . ']', $root ) as $art ) {
		$linked = false;
		foreach ( q( $xp, ".//a[contains(@href,'/professionals/')]", $art ) as $a ) {
			if ( $k = pro_key( $a->getAttribute( 'href' ), $key ) ) {
				$pros[] = $k;
				$linked = true;
				break;
			}
		}
		if ( ! $linked && '' !== ( $name = text_of( q1( $xp, './/h6', $art ) ) ) ) {
			$former[] = $name;
		}
	}
	$former = array_values( array_unique( $former ) );
	$terms = array( 'location' => array(), 'industry' => array(), 'practice' => array() );
	foreach ( q( $xp, './/div[' . cls( 'left-sidebar' ) . ']//div[' . cls( 'pro-industries' ) . ']', $root ) as $box ) {
		foreach ( q( $xp, './/p[not(.//a)]', $box ) as $p ) { // JA / ZH posts print names without links
			$name = text_of( $p );
			$hit  = null;
			foreach ( array( 'industry', 'practice', 'location' ) as $tax ) {
				$slug = array_search( mb_strtolower( $name ), array_map( 'mb_strtolower', NEW_SITE[ $tax ] ), true );
				if ( false !== $slug ) {
					$hit = array( $tax, want_term( $tax, $slug, $name ) );
					break;
				}
			}
			$hit ? $terms[ $hit[0] ][] = $hit[1] : note( $key, "sidebar tag with no term: $name" );
		}
		foreach ( q( $xp, './/a', $box ) as $a ) {
			$h = $a->getAttribute( 'href' );
			if ( $t = service_term( $h, $key, text_of( $a ) ) ) {
				$terms[ $t[0] ][] = $t[1];
			} elseif ( $t = location_term( $h, $key ) ) {
				$terms['location'][] = $t;
			}
		}
	}
	foreach ( $terms as $t => $v ) {
		$terms[ $t ] = array_values( array_unique( $v ) );
	}
	$type = in_array( 'award', $cats, true ) ? 'award' : 'insight';
	if ( $report && 'award' === $type ) { // awards have no file field: keep the body link
		$content .= '<p><a href="' . htmlspecialchars( $report ) . '">Download Full Article</a></p>';
		$report   = null;
	}
	if ( 'insight' === $type ) {
		$terms['insight_category'] = array();
		foreach ( $cats as $c ) {
			$terms['insight_category'][] = want_term( 'insight_category', $c, RES_CATS[ $c ] ?? ucwords( str_replace( '-', ' ', $c ) ) );
		}
	} elseif ( count( $cats ) > 1 ) {
		note( $key, 'award with other categories: ' . implode( ',', $cats ) );
	}

	$exc = preg_replace( '/\s*(\[(…|&hellip;|\.\.\.)\]|\[…\])\s*$/u', '', norm_space( meta_content( $xp, 'property', 'og:description' ) ) );
	$exc = '' !== $exc ? $exc : excerpt_of( html_text( $body ), $lang );
	if ( '' === $exc ) {
		$exc = $title;
	}

	$item = array(
		'src'            => $key,
		'slug'           => slug_of( $key ),
		'status'         => 'publish',
		'date'           => $date,
		'date_gmt'       => $gmt,
		'title'          => $title,
		'excerpt'        => $exc,
		'content'        => $content,
		'featured_image' => $feat,
		'terms'          => $terms,
		'fields'         => array( 'related_professionals' => array_values( array_unique( $pros ) ) ) + ( $former ? array( 'former_authors' => implode( "\n", $former ) ) : array() ) + ( $report ? array( 'module_report' => $report ) : array() ),
		'seo'            => $seo,
		'lang'           => $lang,
		'legacy'         => array( 'id' => old_id( $xp ), 'categories' => array_map( fn( $c ) => RES_CATS[ $c ] ?? $c, $cats ), 'source_publication' => $pub, 'pdf' => $pdf, 'has_thumbnail' => str_contains( $root->getAttribute( 'class' ), 'has-post-thumbnail' ) ),
	);
	if ( 'insight' === $type ) {
		$item = array_slice( $item, 0, 6, true ) + array( 'template' => 'standard' ) + array_slice( $item, 6, null, true );
	}
	return array( $type, $item );
}

/** Job body → summary + responsibilities groups (a <p> that is only <strong> text, or h2-h4, starts a group). */
function split_job( $html ) {
	list( $d, $xp ) = load_doc( '<div id="x">' . $html . '</div>' );
	$summary = array();
	$groups  = array();
	$cur     = null;
	foreach ( q( $xp, "//div[@id='x']/*" ) as $el ) {
		$tag  = strtolower( $el->nodeName );
		$text = text_of( $el );
		$is_h = preg_match( '/^h[2-6]$/', $tag ) || ( 'p' === $tag && $el->childNodes->length && $el->getElementsByTagName( 'strong' )->length && norm_space( text_of( q1( $xp, './/strong', $el ) ) ) === $text && mb_strlen( $text ) < 80 );
		if ( $is_h ) {
			if ( $cur && '' !== $cur['list'] ) {
				$groups[] = $cur;
			}
			$cur = array( 'heading' => rtrim( $text, ': ' ), 'list' => '' );
			continue;
		}
		if ( preg_match( '/^(please (submit|send)|send (your )?cv)/i', $text ) ) {
			continue; // apply line → apply_email
		}
		if ( $cur ) {
			$cur['list'] .= $d->saveHTML( $el );
		} elseif ( 'p' === $tag ) {
			$summary[] = preg_replace( '/^position summary\s*:\s*/i', '', $text );
		}
	}
	if ( $cur && '' !== $cur['list'] ) {
		$groups[] = $cur;
	}
	// "Position Summary:" written as a heading group holding only paragraphs → summary
	if ( ! $summary && $groups && preg_match( '/summary/i', $groups[0]['heading'] ) && ! preg_match( '/<(ul|ol)/', $groups[0]['list'] ) ) {
		$summary[] = html_text( $groups[0]['list'] );
		array_shift( $groups );
	}
	return array( trim( implode( "\n\n", array_filter( $summary ) ) ), $groups );
}

function parse_job( $key, $xp, $doc ) {
	$root = single_root( $xp, 'careers_opportunitie' );
	if ( ! $root ) {
		throw new RuntimeException( 'no job single template' );
	}
	$ld    = jsonld( $xp );
	$title = text_of( widget( $xp, $root, 'da4cdac' ) ) ?: strip_site( meta_content( $xp, 'property', 'og:title' ) );
	if ( '' === $title ) {
		throw new RuntimeException( 'no title' );
	}
	$meta    = text_of( q1( $xp, './/div[' . cls( 'meta-job' ) . ']', $root ) );
	$country = preg_match( '/Location\s*:\s*(.*?)\s*(Department\s*:|$)/i', $meta, $m ) ? $m[1] : '';
	$dept    = preg_match( '/Department\s*:\s*(.*)$/i', $meta, $m ) ? trim( $m[1] ) : '';
	$terms   = array( 'location' => array() );
	$cslug   = slugify( $country );
	if ( in_array( $cslug, COUNTRIES, true ) ) {
		$terms['location'][] = want_term( 'location', $cslug, $country );
	} elseif ( '' !== $country ) {
		note( $key, "job location not a country: $country" );
	}
	$body_w = widget( $xp, $root, '3241ddb' );
	$body   = $body_w ? clean_html( $body_w, $key ) : '';
	list( $summary, $groups ) = split_job( $body );
	$path = '';
	foreach ( q( $xp, ".//a[contains(@href,'/career-role/')]", $root ) as $a ) {
		$k = key_of( $a->getAttribute( 'href' ) );
		if ( $k && preg_match( '#^/career-role/([^/]+)/$#', path_of( $k ), $m ) ) {
			rel( $key, 'career_role', $k );
			$path = 'careers/' . $m[1];
			break;
		}
	}
	if ( '' === $path && q1( $xp, ".//a[contains(@href,'/events/')]", $root ) ) {
		$path = 'careers/internships'; // the old "Internships" role link points at an events post (404)
		note( $key, 'career path from the Internships link (an /events/ URL)' );
	}
	$apply = '';
	foreach ( array( widget( $xp, $root, '878e4ec' ), $body_w ) as $w ) {
		foreach ( $w ? q( $xp, './/a[@href]', $w ) : array() as $a ) {
			if ( $e = email_from_href( $a->getAttribute( 'href' ) ) ) {
				$apply = $e;
				break 2;
			}
		}
	}
	list( $date, $gmt ) = dates_of( $ld['published'] );
	return array(
		'src'      => $key,
		'slug'     => slug_of( $key ),
		'status'   => 'publish',
		'date'     => $date,
		'date_gmt' => $gmt,
		'title'    => $title,
		'content'  => $body,
		'terms'    => $terms,
		'fields'   => array( 'department' => $dept, 'career_path' => $path, 'summary' => $summary, 'responsibilities' => $groups, 'apply_email' => $apply ),
		'seo'      => seo_of( $xp ),
		'legacy'   => array( 'id' => old_id( $xp ), 'country' => $country ),
	);
}

/** Non-loop widgets of a root in document order: [type, element, container]. */
function widgets( $xp, $root ) {
	$out = array();
	foreach ( q( $xp, './/div[@data-widget_type]', $root ) as $w ) {
		if ( ! in_loop( $w, $root ) ) {
			$out[] = array( $w->getAttribute( 'data-widget_type' ), $w, q1( $xp, './div[' . cls( 'elementor-widget-container' ) . ']', $w ) ?: $w );
		}
	}
	return $out;
}

function inside( $n, $anc ) {
	for ( $p = $n->parentNode; $p; $p = $p->parentNode ) {
		if ( $p === $anc ) {
			return true;
		}
	}
	return false;
}

function successes( $xp, $root, $key, $exclude = array() ) {
	$rows = array();
	foreach ( q( $xp, './/div[' . cls( 'case-study-carousel' ) . ']/div[' . cls( 'item' ) . ']', $root ) as $it ) {
		foreach ( $exclude as $ex ) {
			if ( inside( $it, $ex ) ) {
				continue 2;
			}
		}
		$a = q1( $xp, './/h2//a', $it ) ?: q1( $xp, './/a', $it );
		$p = q1( $xp, ".//div[contains(@class,'content')]/p", $it );
		if ( $a && $k = key_of( $a->getAttribute( 'href' ) ) ) {
			rel( $key, 'case_study', $k );
		}
		$rows[] = array( 'title' => text_of( q1( $xp, './/h2', $it ) ), 'text' => text_of( $p ), 'link_url' => $a ? link_href( $a->getAttribute( 'href' ) ) : '' );
	}
	return $rows;
}

/** Experience list(s) outside the excluded subtrees. */
function experience( $xp, $root, $key, $exclude = array() ) {
	$parts = array();
	foreach ( q( $xp, './/div[' . cls( 'profess-practice-experience-list' ) . ']', $root ) as $e ) {
		if ( in_loop( $e, $root ) || 'div' !== $e->nodeName || ( $e->hasAttribute( 'data-widget_type' ) && ! str_starts_with( $e->getAttribute( 'data-widget_type' ), 'shortcode' ) ) ) {
			continue;
		}
		foreach ( $exclude as $ex ) {
			if ( inside( $e, $ex ) ) {
				continue 2;
			}
		}
		$parts[] = clean_html( $e, $key );
	}
	return implode( '', array_unique( $parts ) );
}

function parse_service( $key, $xp, $doc ) {
	$root = single_root( $xp, 'practices' );
	if ( ! $root ) {
		throw new RuntimeException( 'no practices single template' );
	}
	$old = slug_of( $key );
	$ind = str_contains( $root->getAttribute( 'class' ), 'practice_category-industries' );
	$tax = $ind ? 'industry' : 'practice';
	if ( $ind !== in_array( $old, INDUSTRIES, true ) ) {
		note( $key, 'practice_category class disagrees with the industry list' );
	}
	$slug = $ind ? $old : ( PRACTICE_SLUGS[ $old ] ?? $old );
	$ld   = jsonld( $xp );
	$name = strip_site( meta_content( $xp, 'property', 'og:title' ) ?: $ld['name'] );
	want_term( $tax, $slug, $name, $key );

	$tabs     = q( $xp, './/div[' . cls( 'elementor-widget-tabs' ) . ']', $root );
	$fields   = array();
	$img      = $ld['image'] ?: og_image( $xp );
	$fields['hero_image'] = $img ? media( $img, $key, 'hero', '', true ) : null;

	// contacts per country tab
	$names = array();
	foreach ( q( $xp, './/div[' . cls( 'keydiv' ) . ']', $root ) as $k ) {
		$names[ $k->getAttribute( 'data-location' ) ] = text_of( $k );
	}
	$contacts = array();
	foreach ( q( $xp, './/div[' . cls( 'locations-contacts' ) . ']/div[@data-location]', $root ) as $box ) {
		$id      = (int) $box->getAttribute( 'data-location' );
		$country = LOC_IDS[ $id ] ?? slugify( $names[ $id ] ?? '' );
		$people  = array();
		foreach ( q( $xp, ".//a[contains(@href,'/professionals/')]", $box ) as $a ) {
			if ( $pk = pro_key( $a->getAttribute( 'href' ), $key ) ) {
				$people[] = $pk;
			}
		}
		$people = array_values( array_unique( $people ) );
		if ( $people && $country ) {
			want_term( 'location', $country, ucfirst( $country ) );
			$contacts[] = array( 'location' => $country, 'people' => $people );
			if ( count( $people ) > 4 ) {
				note( $key, "contacts $country: " . count( $people ) . ' people (field max is 4)' );
			}
		}
	}
	$fields['contacts'] = $contacts;

	// overview: post content widget, outside tabs / shortcodes
	$heading = '';
	$texts   = array();
	foreach ( q( $xp, ".//div[@data-widget_type='theme-post-content.default']", $root ) as $pc ) {
		foreach ( widgets( $xp, $pc ) as list( $type, $w, $c ) ) {
			foreach ( $tabs as $t ) {
				if ( inside( $w, $t ) || $w === $t ) {
					continue 2;
				}
			}
			if ( 'heading.default' === $type && '' === $heading ) {
				$heading = text_of( $c );
			} elseif ( 'text-editor.default' === $type && ! preg_match( '/^overview$/i', text_of( $c ) ) ) {
				$texts[] = clean_html( $c, $key );
			}
		}
		break;
	}
	if ( ! $ind && preg_match( '/^[“"](.+?)[”"]\s*[—–-]+\s*(.+)$/u', $heading, $m ) ) {
		$fields['quote']        = trim( $m[1] );
		$fields['quote_source'] = trim( $m[2] );
	} else {
		$fields['overview_heading'] = $heading;
	}
	$fields['overview_text'] = implode( '', $texts );
	$fields['successes']     = successes( $xp, $root, $key, $tabs );
	$fields['experience']    = experience( $xp, $root, $key, $tabs );

	$items = array( array( 'taxonomy' => $tax, 'slug' => $slug, 'src' => $key, 'fields' => $fields, 'seo' => seo_of( $xp ) ) );

	// sub-practice tabs (IP) → child practice terms
	foreach ( $tabs as $t ) {
		foreach ( q( $xp, './/div[' . cls( 'elementor-tab-content' ) . ']', $t ) as $tc ) {
			$title = text_of( q1( $xp, "preceding-sibling::div[contains(@class,'elementor-tab-mobile-title')][1]", $tc ) );
			$sub   = SUBS[ strtolower( $title ) ] ?? array( slugify( $title ), $title );
			$src   = BASE . '/sub_practices/' . strtolower( $title ) . '/';
			want_term( 'practice', $sub[0], $sub[1], isset( SUBS[ strtolower( $title ) ] ) ? $src : '' );
			$f    = array( 'overview_heading' => '', 'overview_text' => '', 'experience' => '' );
			$seen = 0;
			foreach ( widgets( $xp, $tc ) as list( $type, $w, $c ) ) {
				if ( 'text-editor.default' !== $type || preg_match( '/^experience$/i', text_of( $c ) ) ) {
					continue;
				}
				0 === $seen++ ? $f['overview_heading'] = text_of( $c ) : $f['overview_text'] .= clean_html( $c, $src );
			}
			$f['experience'] = experience( $xp, $tc, $src );
			$items[]         = array( 'taxonomy' => 'practice', 'slug' => $sub[0], 'src' => $src, 'fields' => $f, 'seo' => array( 'title' => '', 'description' => '', 'noindex' => false ), 'legacy' => array( 'from_tab_of' => $key ) );
		}
	}
	return $items;
}

function parse_location( $key, $xp, $doc ) {
	$root = single_root( $xp, 'locations' );
	if ( ! $root ) {
		throw new RuntimeException( 'no locations single template' );
	}
	$slug = slug_of( $key );
	$ld   = jsonld( $xp );
	want_term( 'location', $slug, strip_site( meta_content( $xp, 'property', 'og:title' ) ), $key );
	$img    = $ld['image'] ?: og_image( $xp );
	$fields = array( 'hero_image' => $img ? media( $img, $key, 'hero', '', true ) : null, 'intro' => '', 'overview_heading' => '', 'overview_text' => '', 'successes' => array(), 'experience' => '' );
	$ws     = widgets( $xp, $root );
	$state  = 'intro';
	foreach ( $ws as list( $type, $w, $c ) ) {
		$t = text_of( $c );
		if ( 'text-editor.default' === $type && preg_match( '/^overview$/i', $t ) ) {
			$state = 'overview';
			continue;
		}
		if ( 'text-editor.default' === $type && preg_match( '/^experience$/i', $t ) ) {
			$state = 'experience';
			continue;
		}
		if ( 'intro' === $state && 'text-editor.default' === $type && '' === $fields['intro'] && mb_strlen( $t ) > 60 ) {
			$fields['intro'] = $t;
		} elseif ( 'overview' === $state && 'heading.default' === $type && '' === $fields['overview_heading'] ) {
			$fields['overview_heading'] = $t;
		} elseif ( 'overview' === $state && 'text-editor.default' === $type && '' === $fields['overview_text'] ) {
			$fields['overview_text'] = clean_html( $c, $key );
			$state                   = 'done';
		} elseif ( 'experience' === $state && 'shortcode.default' === $type ) {
			$fields['experience'] = clean_html( $c, $key );
			$state                = 'done';
		}
	}
	$fields['successes'] = successes( $xp, $root, $key );
	$items               = array( array( 'taxonomy' => 'location', 'slug' => $slug, 'src' => $key, 'fields' => $fields, 'seo' => seo_of( $xp ) ) );

	// offices → city terms
	foreach ( q( $xp, './/div[' . cls( 'location-map' ) . ']', $root ) as $box ) {
		$office = text_of( q1( $xp, './/h3[' . cls( 'offname' ) . ']', $box ) ?: q1( $xp, './/h2', $box ) );
		$city   = trim( preg_replace( '/\s+office$/i', '', $office ) );
		if ( '' === $city ) {
			continue;
		}
		$cs    = slugify( $city );
		$phone = $email = $addr = $map = '';
		foreach ( q( $xp, './/div[' . cls( 'mapicon' ) . ' or ' . cls( 'mapiconbtn' ) . ']', $box ) as $mi ) {
			$a = q1( $xp, './/a[@href]', $mi );
			$h = $a ? $a->getAttribute( 'href' ) : '';
			if ( str_starts_with( $h, 'tel:' ) ) {
				$phone = text_of( $a );
			} elseif ( $e = email_from_href( $h ) ) {
				$email = $e;
			} elseif ( preg_match( '/\.pdf$/i', $h ) ) {
				$map = media( abs_src( $h ), BASE . "/locations/$slug/#$cs", 'office map' );
			} elseif ( ! $a && '' !== text_of( $mi ) ) {
				$addr = text_of( $mi );
			}
		}
		if ( ! isset( PARENTS['location'][ $cs ] ) ) {
			note( $key, "office city with no parent mapping: $city" );
		}
		want_term( 'location', $cs, $city );
		$items[] = array(
			'taxonomy' => 'location',
			'slug'     => $cs,
			'src'      => '',
			'fields'   => array( 'phone' => $phone, 'email' => $email, 'address' => $addr ),
			'seo'      => array( 'title' => '', 'description' => '', 'noindex' => false ),
			'legacy'   => array( 'from' => $key, 'map_pdf' => $map ),
		);
	}
	return $items;
}

/** /contact/ office cards (posts loop of the old `city` type) → city term fields; office_name = legal entity. */
function parse_contact_offices( $key, $xp ) {
	$items = array();
	foreach ( q( $xp, "//article[contains(concat(' ',normalize-space(@class),' '),' type-city ')]" ) as $art ) {
		$city = trim( preg_replace( '/\s+office$/i', '', text_of( q1( $xp, ".//div[@data-widget_type='heading.default']", $art ) ) ) );
		if ( '' === $city ) {
			continue;
		}
		$cs     = slugify( $city );
		$entity = text_of( q1( $xp, ".//div[@data-widget_type='text-editor.default']", $art ) );
		$phone  = $email = $addr = '';
		foreach ( q( $xp, ".//div[@data-widget_type='icon-list.default']//li", $art ) as $li ) {
			$a = q1( $xp, './/a[@href]', $li );
			$h = $a ? $a->getAttribute( 'href' ) : '';
			if ( str_starts_with( $h, 'tel:' ) ) {
				$phone = text_of( $li );
			} elseif ( $e = email_from_href( $h ) ) {
				$email = $e;
			} elseif ( '' !== text_of( $li ) ) {
				$addr = text_of( $li, true );
			}
		}
		if ( ! isset( PARENTS['location'][ $cs ] ) ) {
			note( $key, "office city with no parent mapping: $city" );
		}
		want_term( 'location', $cs, $city );
		$items[] = array(
			'taxonomy' => 'location',
			'slug'     => $cs,
			'src'      => '',
			'fields'   => array( 'office_name' => $entity, 'phone' => $phone, 'email' => $email, 'address' => $addr, 'map_query' => str_replace( "\n", ', ', $addr ) ),
			'seo'      => array( 'title' => '', 'description' => '', 'noindex' => false ),
			'legacy'   => array( 'from' => $key ),
		);
	}
	return $items;
}

function parse_page( $key, $xp, $doc ) {
	$path = path_of( $key );
	$id   = old_id( $xp );
	$root = q1( $xp, "//div[@data-elementor-type='wp-page']" ) ?: q1( $xp, "//div[@data-elementor-type='wp-post' and @data-elementor-id='$id']" ) ?: q1( $xp, "//div[@data-elementor-type='single']" ) ?: q1( $xp, '//main' );
	$ld   = jsonld( $xp );
	$title = strip_site( meta_content( $xp, 'property', 'og:title' ) ?: $ld['name'] ) ?: strip_site( seo_of( $xp )['title'] );
	$filler = array();
	$skip   = function ( $e ) use ( &$filler, $key ) {
		$t = $e->getAttribute( 'data-widget_type' );
		if ( '' === $t ) {
			return false;
		}
		if ( preg_match( '/^(posts\.|html\.|loop-grid|archive-posts|nav-menu|search-form)/', $t ) ) {
			return true; // derived lists, scripts
		}
		$lower = mb_strtolower( $e->textContent );
		foreach ( FILLER as $f ) {
			if ( str_contains( $lower, $f ) ) {
				$filler[] = $t . ' ' . $e->getAttribute( 'data-id' );
				return true;
			}
		}
		return false;
	};
	$content = $root ? clean_html( $root, $key, 'page', $skip ) : '';
	foreach ( $filler as $f ) {
		$GLOBALS['G']['skipped'][] = array( $key, "filler widget dropped from page content: $f" );
	}
	$img = $ld['image'] ?: og_image( $xp );
	return array(
		'src'            => $key,
		'old_path'       => $path,
		'new_path'       => PAGE_MAP[ $path ] ?? ( preg_match( '#^/career-role/([^/]+)/$#', $path, $m ) ? "/careers/$m[1]/" : null ),
		'title'          => $title,
		'lang'           => lang_of( $key ) !== 'en' ? lang_of( $key ) : ( str_starts_with( (string) ( q1( $xp, '//html' ) ? q1( $xp, '//html' )->getAttribute( 'lang' ) : '' ), 'zh' ) ? 'zh' : 'en' ),
		'featured_image' => $img ? media( $img, $key, 'featured', '', true ) : null,
		'content'        => $content,
		'seo'            => seo_of( $xp ),
		'legacy'         => array( 'id' => old_id( $xp ) ),
	);
}

/* ================================================================ crawl index */

function read_tsv( $file ) {
	$rows = array();
	if ( ! is_file( $file ) ) {
		return $rows;
	}
	$fh = fopen( $file, 'r' );
	while ( ( $line = fgets( $fh ) ) !== false ) {
		$rows[] = explode( "\t", rtrim( $line, "\r\n" ) );
	}
	fclose( $fh );
	return $rows;
}

/** file name => [url, status] from _log.tsv (url, status, bytes, type, file) and _crawl-log.tsv (time, url, status, final_url, bytes, cf, file, note). */
function crawl_index( $raw ) {
	$idx = array();
	foreach ( read_tsv( "$raw/_log.tsv" ) as $r ) {
		if ( count( $r ) >= 5 && '' !== $r[4] ) {
			$idx[ $r[4] ] = array( $r[0], (int) $r[1] );
		}
	}
	$crawl = read_tsv( "$raw/_crawl-log.tsv" );
	array_shift( $crawl );
	foreach ( $crawl as $r ) {
		if ( count( $r ) >= 7 && '' !== $r[6] ) {
			$idx[ $r[6] ] = array( '' !== $r[3] ? $r[3] : $r[1], (int) $r[2] );
		}
	}
	return $idx;
}

function kind_of( $path ) {
	$p = strtolower( $path );
	return match ( true ) {
		(bool) preg_match( '#^/professionals/[^/]+/$#', $p )                  => 'professional',
		(bool) preg_match( '#^/(ja/|zh/)?insights/[^/]+/$#', $p )             => 'resource',
		(bool) preg_match( '#^/practices/[^/]+/$#', $p )                      => 'service',
		(bool) preg_match( '#^/sub_practices/[^/]+/$#', $p )                  => 'sub_practice',
		(bool) preg_match( '#^/locations/[^/]+/$#', $p )                      => 'location',
		(bool) preg_match( '#^/job-opportunities/[^/]+/$#', $p )              => 'job',
		(bool) preg_match( '#^/events/#', $p )                                => 'event',
		(bool) preg_match( '#^/(resource-category|practice-category)/#', $p ) => 'archive',
		in_array( $p, array( '/professionals/', '/insights-archive/', '/insights/', '/job-opportunities/', '/locations/' ), true ) => 'archive',
		(bool) preg_match( '#\.(xml|txt|pdf|jpe?g|png|gif|mp4|docx?)$#', $p ) => 'file',
		default                                                               => 'page',
	};
}

/* ================================================================ redirects */

function redirects( $keys, $types ) {
	$out = array();
	$add = function ( $from, $to ) use ( &$out ) {
		$f = path_of( key_of( BASE . $from ) );
		if ( $to !== null && $f !== $to && ! isset( $out[ $f ] ) ) {
			$out[ $f ] = array( 'from' => $f, 'to' => $to, 'code' => 301, 'regex' => false );
		}
	};
	foreach ( $keys as $key => $unused ) {
		$path = path_of( $key );
		$lp   = strtolower( $path );
		$slug = slug_of( $key );
		switch ( kind_of( $path ) ) {
			case 'service':
				$add( $path, in_array( $slug, INDUSTRIES, true ) || ( $GLOBALS['INDUSTRY_PAGES'][ $slug ] ?? false ) ? "/industry/$slug/" : '/practice/' . ( PRACTICE_SLUGS[ $slug ] ?? $slug ) . '/' );
				break;
			case 'sub_practice':
				$add( $path, '/practice/' . ( SUBS[ $slug ][0] ?? $slug ) . '/' );
				break;
			case 'location':
				$add( $path, "/location/$slug/" );
				break;
			case 'job':
				$add( $path, "/jobs/$slug/" );
				break;
			case 'event':
				$add( $path, '/careers/internships/' );
				break;
			case 'archive':
				if ( preg_match( '#^/resource-category/([^/]+)/$#', $lp, $m ) ) {
					$add( $path, 'award' === $m[1] ? '/about/awards-rankings/' : '/insights/?res=' . $m[1] . '#results' );
				} elseif ( preg_match( '#^/practice-category/#', $lp ) ) {
					$add( $path, '/services/' );
				} elseif ( isset( PAGE_MAP[ $path ] ) ) {
					$add( $path, PAGE_MAP[ $path ] );
				}
				break;
			case 'resource':
				$t = $types[ $key ] ?? null;
				if ( 'award' === $t ) {
					$add( $path, "/awards/$slug/" );
				} elseif ( preg_match( '#^/(ja|zh)/insights/#', $lp ) ) {
					$add( $path, "/insights/$slug/" );
				}
				break;
			case 'page':
				if ( isset( PAGE_MAP[ $path ] ) ) {
					$add( $path, PAGE_MAP[ $path ] );
				} elseif ( preg_match( '#^/career-role/([^/]+)/$#', $lp, $m ) ) {
					$add( $path, "/careers/$m[1]/" );
				}
				break;
		}
	}
	// Not an old-site URL, but our own privacy notices (clients, video surveillance) link to it.
	$add( '/offices/bangkok/', '/location/thailand/' );
	ksort( $out, SORT_STRING );
	return array_values( $out );
}

/* ================================================================ output */

function jl( $v ) {
	return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
}

function write_jsonl( $file, $rows ) {
	$fh = fopen( "$file.tmp", 'w' );
	foreach ( $rows as $r ) {
		fwrite( $fh, jl( $r ) . "\n" );
	}
	fclose( $fh );
	rename( "$file.tmp", $file );
}

function filled( $v ) {
	return ! ( null === $v || '' === $v || false === $v || array() === $v );
}

/** Per-field fill share for a list of items (top level, terms.*, fields.*). */
function fill_table( $items ) {
	$n = count( $items );
	if ( ! $n ) {
		return "(none)\n";
	}
	$cnt = array();
	foreach ( $items as $it ) {
		foreach ( $it as $k => $v ) {
			if ( in_array( $k, array( 'terms', 'fields', 'seo', 'legacy' ), true ) && is_array( $v ) ) {
				foreach ( $v as $k2 => $v2 ) {
					$cnt[ "$k.$k2" ] = ( $cnt[ "$k.$k2" ] ?? 0 ) + ( filled( $v2 ) ? 1 : 0 );
				}
			} else {
				$cnt[ $k ] = ( $cnt[ $k ] ?? 0 ) + ( filled( $v ) ? 1 : 0 );
			}
		}
	}
	$s = "| field | filled | share |\n|---|---|---|\n";
	foreach ( $cnt as $k => $c ) {
		$pct  = round( 100 * $c / $n );
		$flag = ( 0 === $c || $c === $n ) && $n >= 3 && ! in_array( $k, array( 'src', 'slug', 'status', 'date', 'date_gmt', 'title', 'taxonomy', 'seo.title', 'excerpt', 'template', 'lang', 'legacy.id', 'seo.noindex', 'fields.lead', 'fields.headshot', 'old_path' ), true ) ? ' ⚑' : '';
		$s   .= "| `$k` | $c / $n | $pct%$flag |\n";
	}
	return $s;
}

/* ================================================================ main */

function run( $raw, $urls, $out ) {
	global $G;
	$GLOBALS['INDUSTRY_PAGES'] = array();
	foreach ( glob( "$urls/*.tsv" ) as $f ) {
		foreach ( read_tsv( $f ) as $r ) {
			if ( $r && str_starts_with( $r[0], 'http' ) && ( $k = key_of( $r[0] ) ) ) {
				$G['known'][ strtolower( $k ) ] = $k;
			}
		}
	}
	$idx   = crawl_index( $raw );
	$files = array();
	foreach ( scandir( $raw ) as $f ) {
		if ( '.' === $f[0] || '_' === $f[0] || preg_match( '/\.(hdr|head|tmp|part|tsv)$/', $f ) || ! is_file( "$raw/$f" ) ) {
			continue;
		}
		$files[] = $f;
	}
	sort( $files, SORT_STRING );

	// pass 1: locate every page (URL, kind) so industries are known before relations resolve
	$pages = array();
	$stat  = array();
	foreach ( $files as $f ) {
		$html = file_get_contents( "$raw/$f" );
		if ( ! preg_match( '/<html|<!doctype html/i', substr( $html, 0, 4096 ) ) ) {
			continue; // xml sitemaps, robots, binaries
		}
		$url    = $idx[ $f ][0] ?? '';
		$status = $idx[ $f ][1] ?? 0;
		if ( '' === $url ) { // not in either log: take the page's own URL
			if ( preg_match( '#"@type":\s*"WebPage",\s*"@id":\s*"([^"]+)"#', $html, $m ) || preg_match( '#<link rel="canonical" href="([^"]+)"#', $html, $m ) ) {
				$url = stripslashes( $m[1] );
				$G['notes'][] = array( $f, 'not in crawl logs; URL from the page: ' . $url );
			} else {
				$G['errors'][] = array( $f, '', 'no URL (not in logs, no canonical)' );
				continue;
			}
		}
		$key = key_of( $url );
		if ( '' === $key ) {
			$G['errors'][] = array( $f, $url, 'URL is not on the old site' );
			continue;
		}
		if ( $status && 200 !== $status ) {
			$G['skipped'][] = array( $key, "HTTP $status" );
			continue;
		}
		if ( isset( $pages[ $key ] ) ) {
			$G['notes'][] = array( $key, "saved twice ($pages[$key] and $f); first kept" );
			continue;
		}
		$pages[ $key ] = $f;
		if ( 'service' === kind_of( path_of( $key ) ) && str_contains( $html, 'practice_category-industries' ) ) {
			$GLOBALS['INDUSTRY_PAGES'][ slug_of( $key ) ] = true;
		}
	}
	ksort( $pages, SORT_STRING );

	$out_rows = array( 'professional' => array(), 'insight' => array(), 'award' => array(), 'job' => array(), 'term_fields' => array(), 'page' => array() );
	$types    = array();
	foreach ( $pages as $key => $f ) {
		$kind = kind_of( path_of( $key ) );
		$stat[ $kind ]['seen'] = ( $stat[ $kind ]['seen'] ?? 0 ) + 1;
		if ( 'event' === $kind ) {
			$G['skipped'][] = array( $key, 'events post: demo content' );
			continue;
		}
		if ( 'archive' === $kind || 'file' === $kind ) {
			$G['skipped'][] = array( $key, "$kind page: redirect only" );
			continue;
		}
		if ( 'page' === $kind && in_array( path_of( $key ), UTILITY_PAGES, true ) ) {
			$G['skipped'][] = array( $key, 'utility page: redirect only' );
			continue;
		}
		if ( 'sub_practice' === $kind ) { // broken template; content comes from the IP page tabs
			$s = SUBS[ slug_of( $key ) ] ?? array( slug_of( $key ), ucfirst( slug_of( $key ) ) );
			want_term( 'practice', $s[0], $s[1], $key );
			$stat[ $kind ]['ok'] = ( $stat[ $kind ]['ok'] ?? 0 ) + 1;
			continue;
		}
		try {
			list( $doc, $xp ) = load_doc( file_get_contents( "$raw/$f" ) );
			switch ( $kind ) {
				case 'professional':
					$out_rows['professional'][] = parse_professional( $key, $xp, $doc );
					break;
				case 'resource':
					list( $t, $item )   = parse_resource( $key, $xp, $doc );
					$out_rows[ $t ][]   = $item;
					$types[ $key ]      = $t;
					break;
				case 'job':
					$out_rows['job'][] = parse_job( $key, $xp, $doc );
					break;
				case 'service':
					array_push( $out_rows['term_fields'], ...parse_service( $key, $xp, $doc ) );
					break;
				case 'location':
					array_push( $out_rows['term_fields'], ...parse_location( $key, $xp, $doc ) );
					break;
				default:
					$out_rows['page'][] = parse_page( $key, $xp, $doc );
					if ( '/contact/' === path_of( $key ) ) {
						array_push( $out_rows['term_fields'], ...parse_contact_offices( $key, $xp ) );
					}
			}
			$stat[ $kind ]['ok'] = ( $stat[ $kind ]['ok'] ?? 0 ) + 1;
		} catch ( Throwable $e ) {
			$stat[ $kind ]['fail'] = ( $stat[ $kind ]['fail'] ?? 0 ) + 1;
			$G['errors'][]         = array( $f, $key, $e->getMessage() );
		}
	}

	// slug clashes inside one post type
	foreach ( array( 'professional', 'insight', 'award', 'job' ) as $t ) {
		$by = array();
		foreach ( $out_rows[ $t ] as $it ) {
			$by[ $it['slug'] ][] = $it['src'];
		}
		foreach ( $by as $s => $srcs ) {
			if ( count( $srcs ) > 1 ) {
				note( $srcs[0], "$t slug clash '$s': " . implode( ' ', $srcs ) );
			}
		}
	}

	// term_fields: one row per taxonomy/slug (a tab and a sub_practices page may both describe a term)
	$tf = array();
	foreach ( $out_rows['term_fields'] as $r ) {
		$k = $r['taxonomy'] . '/' . $r['slug'];
		$tf[ $k ] = isset( $tf[ $k ] ) ? array_replace( $tf[ $k ], array( 'fields' => array_merge( array_filter( $r['fields'], 'filled' ), array_filter( $tf[ $k ]['fields'], 'filled' ) ) ) ) : $r;
	}
	ksort( $tf, SORT_STRING );
	$out_rows['term_fields'] = array_values( $tf );

	// terms: parents first, then by taxonomy/slug; parents that are needed get added
	foreach ( $G['terms'] as $t ) {
		if ( $t['parent'] && ! isset( $G['terms'][ $t['taxonomy'] . '/' . $t['parent'] ] ) ) {
			$G['terms'][ $t['taxonomy'] . '/' . $t['parent'] ] = array( 'taxonomy' => $t['taxonomy'], 'slug' => $t['parent'], 'name' => NEW_SITE[ $t['taxonomy'] ][ $t['parent'] ] ?? ucfirst( $t['parent'] ), 'parent' => '', 'src' => '', 'exists' => isset( NEW_SITE[ $t['taxonomy'] ][ $t['parent'] ] ), 'uses' => 0 );
		}
	}
	$terms = array_values( $G['terms'] );
	usort( $terms, fn( $a, $b ) => array( $a['taxonomy'], '' !== $a['parent'], $a['slug'] ) <=> array( $b['taxonomy'], '' !== $b['parent'], $b['slug'] ) );
	$term_rows = array_map( fn( $t ) => array( 'taxonomy' => $t['taxonomy'], 'slug' => $t['slug'], 'name' => $t['name'], 'parent' => $t['parent'], 'src' => $t['src'], 'exists_on_new_site' => $t['exists'] ), $terms );

	$all_keys = $G['known'] ? array_flip( $G['known'] ) : array();
	foreach ( $pages as $k => $unused ) {
		$all_keys[ $k ] = 1;
	}
	ksort( $all_keys, SORT_STRING );
	$redir = redirects( $all_keys, $types );

	ksort( $G['media'], SORT_STRING );
	$media = array();
	foreach ( $G['media'] as $m ) {
		$m['referenced_as'] = array_keys( $m['referenced_as'] );
		sort( $m['referenced_as'] );
		$m['uses'] = array_values( $m['uses'] );
		usort( $m['uses'], fn( $a, $b ) => array( $a['item'], $a['role'] ) <=> array( $b['item'], $b['role'] ) );
		// Never linked as the original: the old site may only keep the variants (WP deletes
		// nothing, but these 404 there). Download the -scaled file, else the largest size.
		if ( empty( $m['direct'] ) && $m['referenced_as'] ) {
			$area = fn( $u ) => str_contains( $u, '-scaled.' ) ? PHP_INT_MAX : ( preg_match( '/-(\d+)x(\d+)\.[a-z0-9]+$/i', $u, $d ) ? $d[1] * $d[2] : 0 );
			$best = $m['referenced_as'];
			usort( $best, fn( $a, $b ) => $area( $b ) <=> $area( $a ) );
			$m['url'] = $best[0];
		}
		unset( $m['direct'] );
		$media[] = $m;
	}

	@mkdir( $out, 0777, true );
	write_jsonl( "$out/professionals.jsonl", $out_rows['professional'] );
	write_jsonl( "$out/insights.jsonl", $out_rows['insight'] );
	write_jsonl( "$out/awards.jsonl", $out_rows['award'] );
	write_jsonl( "$out/jobs.jsonl", $out_rows['job'] );
	write_jsonl( "$out/terms.jsonl", $term_rows );
	write_jsonl( "$out/term_fields.jsonl", $out_rows['term_fields'] );
	write_jsonl( "$out/pages.jsonl", $out_rows['page'] );
	write_jsonl( "$out/redirects.jsonl", $redir );
	write_jsonl( "$out/media.jsonl", $media );
	$fh = fopen( "$out/_errors.tsv", 'w' );
	fwrite( $fh, "file\tsrc\terror\n" );
	foreach ( $G['errors'] as $e ) {
		fwrite( $fh, implode( "\t", $e ) . "\n" );
	}
	fclose( $fh );

	report( $out, $stat, $out_rows, $term_rows, $redir, $media, $all_keys, count( $files ), count( $pages ) );
	fwrite( STDERR, sprintf( "pages %d · professionals %d · insights %d · awards %d · jobs %d · term_fields %d · pages %d · terms %d · media %d · redirects %d · errors %d\n", count( $pages ), count( $out_rows['professional'] ), count( $out_rows['insight'] ), count( $out_rows['award'] ), count( $out_rows['job'] ), count( $out_rows['term_fields'] ), count( $out_rows['page'] ), count( $term_rows ), count( $media ), count( $redir ), count( $G['errors'] ) ) );
}

function report( $out, $stat, $rows, $terms, $redir, $media, $all_keys, $nfiles, $npages ) {
	global $G;
	$r  = "# Transform report\n\n";
	$r .= 'Generated by `wp/migration/transform.php` from `temporary/migration/raw/` (' . $nfiles . " files, $npages distinct HTML pages). Deterministic: re-running on the same input gives the same output. Flag ⚑ = a field filled on 0% or 100% of the items (worth a second look when the sample is varied).\n\n";
	$r .= "## Pages by old type\n\n| old type | pages | parsed | failed |\n|---|---|---|---|\n";
	ksort( $stat );
	foreach ( $stat as $k => $s ) {
		$r .= "| $k | " . ( $s['seen'] ?? 0 ) . ' | ' . ( $s['ok'] ?? 0 ) . ' | ' . ( $s['fail'] ?? 0 ) . " |\n";
	}
	$r .= "\n## Output\n\n| file | rows |\n|---|---|\n";
	foreach ( array( 'professionals' => $rows['professional'], 'insights' => $rows['insight'], 'awards' => $rows['award'], 'jobs' => $rows['job'], 'term_fields' => $rows['term_fields'], 'pages' => $rows['page'], 'terms' => $terms, 'redirects' => $redir, 'media' => $media ) as $f => $v ) {
		$r .= "| `$f.jsonl` | " . count( $v ) . " |\n";
	}
	$lang = array();
	foreach ( array_merge( $rows['insight'], $rows['award'] ) as $it ) {
		$lang[ $it['lang'] ] = ( $lang[ $it['lang'] ] ?? 0 ) + 1;
	}
	ksort( $lang );
	$r .= "\nResource languages: " . ( $lang ? implode( ', ', array_map( fn( $k, $v ) => "$k $v", array_keys( $lang ), $lang ) ) : 'none' ) . ".\n";
	$cats = array();
	foreach ( array_merge( $rows['insight'], $rows['award'] ) as $it ) {
		foreach ( $it['legacy']['categories'] as $c ) {
			$cats[ $c ] = ( $cats[ $c ] ?? 0 ) + 1;
		}
	}
	ksort( $cats );
	$r .= 'Old categories seen: ' . ( $cats ? implode( ', ', array_map( fn( $k, $v ) => "$k $v", array_keys( $cats ), $cats ) ) : 'none' ) . ".\n";
	$multi = array_filter( array_merge( $rows['insight'], $rows['award'] ), fn( $it ) => count( $it['legacy']['categories'] ) > 1 );
	$r .= 'Items with more than one old category: ' . count( $multi ) . ( $multi ? ' (' . implode( ', ', array_map( fn( $it ) => '`' . path_of( $it['src'] ) . '` ' . implode( '+', $it['legacy']['categories'] ), $multi ) ) . ')' : '' ) . ".\n";

	$res  = array_merge( $rows['insight'], $rows['award'] );
	$nopos = array_filter( $rows['professional'], fn( $p ) => ! $p['terms']['position'] );
	$r   .= "\n## Mapping cases for a human decision\n\n";
	$r   .= '- Professionals whose position facet could not be inferred from the title (no position term): ' . count( $nopos ) . ' of ' . count( $rows['professional'] ) . ( $nopos ? ' (' . implode( '; ', array_map( fn( $p ) => $p['slug'] . ': ' . $p['fields']['position_title'], $nopos ) ) . ')' : '' ) . ".\n";
	$r   .= '- Resources with no old category (imported as insight, no insight_category): ' . count( array_filter( $res, fn( $i ) => ! $i['legacy']['categories'] ) ) . ".\n";
	$r   .= '- Source publication added as a first `<p><em>` line (design Q6 default): ' . count( array_filter( $res, fn( $i ) => '' !== $i['legacy']['source_publication'] && FIRM !== $i['legacy']['source_publication'] ) ) . '; "Download Full Article" PDF: ' . count( array_filter( $res, fn( $i ) => '' !== $i['legacy']['pdf'] ) ) . ' (in the `module_report` field: ' . count( array_filter( $res, fn( $i ) => ! empty( $i['fields']['module_report'] ) ) ) . '; the rest as a body link). Featured images (real post thumbnails only): ' . count( array_filter( $res, fn( $i ) => $i['featured_image'] ) ) . ".\n";
	$r   .= '- Old pages with no page on the new site (content kept in pages.jsonl, no redirect): ' . implode( ', ', array_map( fn( $p ) => '`' . rawurldecode( $p['old_path'] ) . '`', array_filter( $rows['page'], fn( $p ) => null === $p['new_path'] ) ) ) . ".\n";
	foreach ( array( 'professional' => 'Professionals', 'insight' => 'Insights', 'award' => 'Awards', 'job' => 'Jobs', 'page' => 'Pages' ) as $k => $label ) {
		$r .= "\n## Field fill: $label (" . count( $rows[ $k ] ) . ")\n\n" . fill_table( $rows[ $k ] );
	}
	foreach ( array( 'industry', 'practice', 'location' ) as $tax ) {
		$sub = array_values( array_filter( $rows['term_fields'], fn( $x ) => $x['taxonomy'] === $tax ) );
		$r  .= "\n## Field fill: term_fields $tax (" . count( $sub ) . ")\n\n" . fill_table( $sub );
	}

	$r .= "\n## Terms with no home on the new site (emitted as new terms, create-only)\n\n";
	$new = array_filter( $terms, fn( $t ) => ! $t['exists_on_new_site'] );
	$r  .= $new ? "| taxonomy | slug | name | parent |\n|---|---|---|---|\n" . implode( '', array_map( fn( $t ) => "| {$t['taxonomy']} | `{$t['slug']}` | {$t['name']} | {$t['parent']} |\n", $new ) ) : "None.\n";

	$known = array_change_key_case( array_flip( array_map( 'strtolower', array_keys( $all_keys ) ) ) );
	$miss  = array();
	foreach ( $G['rels'] as list( $item, $kind, $target ) ) {
		if ( ! isset( $known[ strtolower( $target ) ] ) ) {
			$miss[ "$kind|$target" ][] = $item;
		}
	}
	ksort( $miss );
	$r .= "\n## Relations pointing at URLs not in the URL lists or the crawl\n\n" . ( $miss ? implode( '', array_map( fn( $k, $v ) => '- ' . str_replace( '|', ' → `', $k ) . '` (from ' . count( $v ) . ' item' . ( count( $v ) > 1 ? 's' : '' ) . ', e.g. `' . path_of( $v[0] ) . "`)\n", array_keys( $miss ), $miss ) ) : "None.\n" );
	$prokeys = array_flip( array_map( fn( $p ) => $p['src'], $rows['professional'] ) );
	$notyet  = array();
	foreach ( $G['rels'] as list( $item, $kind, $target ) ) {
		if ( 'professional' === $kind && ! isset( $prokeys[ $target ] ) ) {
			$notyet[ $target ] = 1;
		}
	}
	$r .= "\nProfessionals referenced but not (yet) parsed from raw/: " . count( $notyet ) . ".\n";

	$r .= "\n## Dropped images\n\n" . ( $G['dropped'] ? implode( '', array_map( fn( $d ) => '- `' . path_of( $d[0] ) . '`: ' . $d[1] . " ({$d[2]})\n", $G['dropped'] ) ) : "None.\n" );
	$r .= "\n## Links to dead Drupal paths (kept in the body)\n\n" . ( $G['deadlinks'] ? implode( '', array_map( fn( $d ) => '- `' . path_of( $d[0] ) . '`: ' . $d[1] . "\n", $G['deadlinks'] ) ) : "None.\n" );
	$r .= "\n## Skipped\n\n" . ( $G['skipped'] ? implode( '', array_map( fn( $s ) => '- `' . path_of( $s[0] ) . '`: ' . $s[1] . "\n", $G['skipped'] ) ) : "None.\n" );
	$r .= "\n## Failed pages\n\n" . ( $G['errors'] ? implode( '', array_map( fn( $e ) => "- `{$e[0]}` {$e[1]}: {$e[2]}\n", $G['errors'] ) ) : "None.\n" );
	$roles = array();
	foreach ( $media as $m ) {
		foreach ( $m['uses'] as $u ) {
			$roles[ $u['role'] ] = ( $roles[ $u['role'] ] ?? 0 ) + 1;
		}
	}
	ksort( $roles );
	$r .= "\n## Media\n\n" . count( $media ) . ' unique files (' . count( array_filter( $media, fn( $m ) => 'pdf' === $m['kind'] ) ) . ' PDF). Uses by role: ' . implode( ', ', array_map( fn( $k, $v ) => "$k $v", array_keys( $roles ), $roles ) ) . ". Sized variants (`-300x200`, `-scaled`) are listed under the original file in `referenced_as`.\n";
	$r .= "\n## Notes\n\n" . ( $G['notes'] ? implode( '', array_map( fn( $n ) => '- `' . ( str_starts_with( $n[0], BASE ) ? path_of( $n[0] ) : $n[0] ) . '`: ' . $n[1] . "\n", $G['notes'] ) ) : "None.\n" );
	file_put_contents( "$out/_report.md", $r );
}

/* ================================================================ self-check */

function check() {
	$ok   = 0;
	$fail = 0;
	$eq   = function ( $label, $got, $want ) use ( &$ok, &$fail ) {
		if ( $got === $want ) {
			++$ok;
		} else {
			++$fail;
			fwrite( STDERR, "FAIL $label\n  got:  " . var_export( $got, true ) . "\n  want: " . var_export( $want, true ) . "\n" );
		}
	};
	// email decoding (alan's data-cfemail sample)
	$eq( 'cf email', cf_decode( '5c3d303d32723d1c28353030393739723f3331' ), 'alan.a@tilleke.com' );
	$eq( 'cf href', email_from_href( '/cdn-cgi/l/email-protection#91f0fdf0ffbff0d1e5f8fdfdf4faf4bff2fefc' ), 'alan.a@tilleke.com' );
	// dates: Yoast JSON-LD is GMT; local = Asia/Bangkok
	$eq( 'date', dates_of( '2020-11-24T19:17:19+00:00' ), array( '2020-11-25 02:17:19', '2020-11-24 19:17:19' ) );
	$eq( 'date tz', dates_of( '2022-06-22T09:40:14+09:00' ), array( '2022-06-22 07:40:14', '2022-06-22 00:40:14' ) );
	// slugs / keys
	$eq( 'key no slash', key_of( 'https://www.tilleke.com/insights/vinh-quoc-nguyen-speak-ma-disputes-viac-seminar' ), 'https://www.tilleke.com/insights/vinh-quoc-nguyen-speak-ma-disputes-viac-seminar/' );
	$eq( 'key cjk', key_of( 'https://www.tilleke.com/ja/insights/タイ、電子/' ), 'https://www.tilleke.com/ja/insights/%E3%82%BF%E3%82%A4%E3%80%81%E9%9B%BB%E5%AD%90/' );
	$eq( 'key enc', key_of( 'https://www.tilleke.com/ja/insights/%e3%82%bf%e3%82%a4' ), 'https://www.tilleke.com/ja/insights/%E3%82%BF%E3%82%A4/' );
	$eq( 'slug cjk', slug_of( key_of( 'https://www.tilleke.com/ja/insights/%e3%82%bf%e3%82%a4' ) ), '%e3%82%bf%e3%82%a4' );
	$eq( 'key file', key_of( 'https://tilleke.com/wp-content/uploads/2023/06/Alan-Adcock.jpg?x=1' ), 'https://www.tilleke.com/wp-content/uploads/2023/06/Alan-Adcock.jpg' );
	$eq( 'key other host', key_of( 'https://example.com/a' ), '' );
	$eq( 'media field src', media( 'https://www.tilleke.com/wp-content/uploads/2020/07/X-scaled.jpg', 'x', 'hero', '', true ), 'https://www.tilleke.com/wp-content/uploads/2020/07/X.jpg' );
	$eq( 'alias bahasa', want_term( 'language', 'bahasa-indonesia', 'Bahasa Indonesia' ), 'indonesian' );
	$eq( 'alias malay', $GLOBALS['G']['terms']['language/malay']['name'] ?? ( want_term( 'language', 'bahasa-malaysia', 'Bahasa Malaysia' ) ? $GLOBALS['G']['terms']['language/malay']['name'] : '' ), 'Malay' );
	$eq( 'slug', slug_of( 'https://www.tilleke.com/professionals/alan/' ), 'alan' );
	$eq( 'slugify', slugify( 'Chinese (Hokkien)' ), 'chinese-hokkien' );
	$eq( 'text noise', norm_space( "  a\u{200B}b <span class='ep-highlight'>c</span>\u{00A0} " ), 'ab c' );
	$eq( 'rel ja', rel_key( 'https://www.tilleke.com/ja/professionals/nwe-oo/' ), 'https://www.tilleke.com/professionals/nwe-oo/' );
	$eq( 'link', link_href( 'https://www.tilleke.com/insights/x?y=1' ), '/insights/x/?y=1' );
	$eq( 'embed yt', embed_link( 'https://www.youtube.com/embed/hYNEwI4Jsbw?feature=oembed' ), array( 'https://www.youtube.com/watch?v=hYNEwI4Jsbw', 'Watch the video on YouTube' ) );
	$eq( 'dead dev host', is_dead_drupal( 'https://tilleke-old.lexiconthai.dev/sites/default/files/x.jpg' ), true );
	// body cleaning
	$GLOBALS['G'] = array( 'media' => array(), 'dropped' => array(), 'deadlinks' => array(), 'rels' => array(), 'unhomed' => array(), 'skipped' => array(), 'errors' => array(), 'notes' => array(), 'terms' => array(), 'known' => array() );
	$html = '<div class="elementor-widget-container"><div class="x" style="color:red"> Intro <b>bold</b></div>'
		. '<p class="MsoNormal" style="a:b">Para <a href="https://www.tilleke.com/professionals/alan" class="c" target="_blank">Alan</a> <span class="__cf_email__" data-cfemail="5c3d303d32723d1c28353030393739723f3331">[email&#160;protected]</span></p>'
		. '<p>&nbsp;</p><script>alert(1)</script><style>.a{}</style><h1>Head</h1>'
		. '<ul><li><i class="fa fa-x"></i>One</li></ul>'
		. '<p><img src="https://www.tilleke.com/sites/default/files/x.jpg"><img class="wp-image-1" src="https://www.tilleke.com/wp-content/uploads/2026/11/set-1024x683.jpg" alt="Set" srcset="a 1x"></p>'
		. '<table class="tablepress"><tr><td colspan="2" style="w">c</td></tr></table></div>';
	list( $d, $xp ) = load_doc( $html );
	$got = clean_html( q1( $xp, "//div[@class='elementor-widget-container']" ), BASE . '/insights/t/' );
	$eq( 'clean', $got, '<p>Intro <strong>bold</strong></p><p>Para <a href="/professionals/alan/">Alan</a> alan.a@tilleke.com</p><h2>Head</h2><ul><li>One</li></ul><p><img src="https://www.tilleke.com/wp-content/uploads/2026/11/set-1024x683.jpg" alt="Set"></p><table><tr><td colspan="2">c</td></tr></table>' );
	$eq( 'dropped drupal', count( $GLOBALS['G']['dropped'] ), 1 );
	$eq( 'media orig', array_keys( $GLOBALS['G']['media'] ), array( 'https://www.tilleke.com/wp-content/uploads/2026/11/set.jpg' ) );
	// fix round 2026-11-14: spaces held by empty inline tags, Cloudflare mailto links, footnote anchors, players, relative links
	list( $d, $xp ) = load_doc( '<div id="b"><p>issued<strong> </strong>two <a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="5c3d303d32723d1c28353030393739723f3331">[email&#160;protected]</a>'
		. ' <a href="#_ftn1" name="_ftnref1"><sup>1</sup></a> <a href="events@tilleke.com">x</a> <a href="professionals/aaron">y</a></p>'
		. '<div class="wp-video"><video poster="https://www.tilleke.com/wp-content/uploads/2023/08/1.jpg" controls="controls"><source type="video/mp4" src="https://www.tilleke.com/wp-content/uploads/2023/08/Metaverse-01.mp4?_=1" /><a href="x">x</a></video></div></div>' );
	$eq( 'clean fix1', clean_html( q1( $xp, "//div[@id='b']" ), BASE . '/insights/t/' ), '<p>issued two <a href="mailto:alan.a@tilleke.com">alan.a@tilleke.com</a> <a href="#_ftn1" id="_ftnref1"><sup>1</sup></a> <a href="mailto:events@tilleke.com">x</a> <a href="/professionals/aaron/">y</a></p><video controls="" preload="metadata" src="https://www.tilleke.com/wp-content/uploads/2023/08/Metaverse-01.mp4" poster="https://www.tilleke.com/wp-content/uploads/2023/08/1.jpg"></video>' );
	$eq( 'job split', split_job( '<p><strong>Key Responsibilities</strong></p><ul><li>a</li></ul><p><strong>Specification</strong></p><p><strong>Skills</strong></p><ul><li>b</li></ul><p><strong>Please send the CV to:</strong> x</p>' ), array( '', array( array( 'heading' => 'Key Responsibilities', 'list' => '<ul><li>a</li></ul>' ), array( 'heading' => 'Skills', 'list' => '<ul><li>b</li></ul>' ) ) ) );
	fwrite( STDERR, "self-check: $ok passed, $fail failed\n" );
	return $fail ? 1 : 0;
}

/* ================================================================ cli */

if ( realpath( $argv[0] ?? '' ) !== __FILE__ ) {
	return; // included (e.g. by a hand-check script): functions only
}
$opt  = getopt( '', array( 'raw:', 'urls:', 'out:', 'check' ) );
$repo = dirname( __DIR__, 2 );
if ( isset( $opt['check'] ) ) {
	exit( check() );
}
run( $opt['raw'] ?? "$repo/temporary/migration/raw", $opt['urls'] ?? "$repo/temporary/migration/urls", $opt['out'] ?? "$repo/temporary/migration/data" );
