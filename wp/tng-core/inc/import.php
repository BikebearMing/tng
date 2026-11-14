<?php
/**
 * Old site → new site importer endpoint: POST /wp-json/tng/v1/import.
 *
 * Design: temporary/migration/import-design.md (section 2). Contract for the
 * transform and the driver: temporary/migration/loader-contract.md. Driven by
 * wp/migration/import.mjs over CDP from a logged-in wp-admin tab.
 *
 * - Admin only (manage_options), cookie auth + X-WP-Nonce (core checks it).
 * - Dry run unless the body says "dry_run": false.
 * - Create-only, idempotent on the _tng_src meta (canonical old URL): an
 *   object that exists in any status, trash included, is "skipped".
 * - Everything written carries _tng_run (+ _tng_at, _tng_mod) so rollback
 *   can remove exactly one run and never touches hand-made content.
 * - One write request at a time (option lock); each request stops starting
 *   items after TNG_IMPORT_BUDGET seconds and returns the rest as "pending".
 *
 * Can be removed (with its require line) after launch.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TNG_IMPORT_BUDGET = 20; // seconds; Cloudflare cuts proxied requests at 100

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'tng/v1',
			'/import',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => 'tng_import_request',
			)
		);
	}
);

/* ---------------- what each type accepts ---------------- */

/**
 * Post types: taxonomies, field whitelist (field-key prefix => name => kind)
 * and one field key that must exist (else scf-import.json wasn't imported).
 * Kinds: text | media (attachment ID from a media src) | rel (professional
 * srcs → IDs) | page (page path → ID) | array (repeater: sub name => kind).
 * Toggles (show_*) and headings with defaults are never imported.
 */
function tng_import_types() {
	$rel = array( 'related_professionals' => 'rel', 'former_authors' => 'text' ); // former_authors: names, one per line
	return array(
		'professional' => array(
			'tax'    => array( 'location', 'industry', 'practice', 'position', 'language' ),
			'fields' => array(
				'pro' => array(
					'portrait'       => 'media',
					'headshot'       => 'media',
					'position_title' => 'text',
					'phone'          => 'text',
					'email'          => 'text',
					'lead'           => 'text',
					'biography'      => 'text',
					'education'      => array( 'entry' => 'text' ),
					'experience'     => 'text',
				),
			),
			'check'  => 'field_tng_pro_portrait',
		),
		'insight'      => array(
			'tax'    => array( 'location', 'industry', 'practice', 'insight_category' ),
			'fields' => array(
				'ins' => $rel,
				// Module template (hand-written items only; old resources import as Standard)
				'mod' => array(
					'module_background'            => 'media',
					'module_lead'                  => 'text',
					'module_report'                => 'media',
					'module_report_label'          => 'text',
					'panels_background'            => 'media',
					'panels'                       => array( 'heading' => 'text', 'body' => 'text', 'theme' => 'text', 'link_label' => 'text', 'link_url' => 'text' ),
					'findings_heading'             => 'text',
					'findings'                     => array( 'title' => 'text', 'text' => 'text' ),
					'module_professionals_heading' => 'text',
					'download_label'               => 'text',
					'download_image'               => 'media',
					'share_heading'                => 'text',
					'module_insights_heading'      => 'text',
				),
			),
			'check'  => 'field_tng_ins_related_professionals',
		),
		'award'        => array(
			'tax'    => array( 'location', 'industry', 'practice' ),
			'fields' => array( 'awd' => $rel ),
			'check'  => 'field_tng_awd_related_professionals',
		),
		'job'          => array(
			'tax'    => array( 'location' ),
			'fields' => array(
				'job' => array(
					'department'       => 'text',
					'career_path'      => 'page',
					'summary'          => 'text',
					'responsibilities' => array( 'heading' => 'text', 'list' => 'text' ),
					'apply_email'      => 'text',
				),
			),
			'check'  => 'field_tng_job_department',
		),
	);
}

/** Term fields (fill-empty only): field-key prefix per taxonomy => name => kind. */
function tng_import_term_field_defs( $taxonomy ) {
	$successes = array( 'title' => 'text', 'text' => 'text', 'link_url' => 'text' );
	if ( 'location' === $taxonomy ) {
		return array(
			'loc' => array(
				'flag'             => 'media',
				'image'            => 'media',
				'intro'            => 'text',
				'office_name'      => 'text',
				'phone'            => 'text',
				'email'            => 'text',
				'address'          => 'text',
				'map_query'        => 'text',
				'hero_image'       => 'media',
				'overview_heading' => 'text',
				'overview_text'    => 'text',
				'successes'        => $successes,
				'experience'       => 'text',
			),
		);
	}
	if ( in_array( $taxonomy, array( 'industry', 'practice' ), true ) ) {
		return array(
			'svc' => array(
				'hero_image'       => 'media',
				'contacts'         => array( 'location' => 'location', 'people' => 'rel' ),
				'overview_heading' => 'text',
				'quote'            => 'text',
				'quote_source'     => 'text',
				'overview_text'    => 'text',
				'card_image'       => 'media',
				'successes'        => $successes,
				'experience_label' => 'text',
				'experience'       => 'text',
			),
		);
	}
	return array();
}

const TNG_IMPORT_TAXONOMIES = array( 'industry', 'practice', 'location', 'position', 'language', 'insight_category' );

/* ---------------- request ---------------- */

function tng_import_request( WP_REST_Request $request ) {
	$start = microtime( true );
	$p     = $request->get_json_params();
	if ( is_array( $p ) && isset( $p['b64'] ) ) { // WAF workaround: the same JSON, base64-encoded
		$p = json_decode( (string) base64_decode( (string) $p['b64'], true ), true );
	}
	if ( ! is_array( $p ) ) {
		return new WP_Error( 'tng_import_body', 'Body must be a JSON object.', array( 'status' => 400 ) );
	}
	$run = isset( $p['run'] ) ? (string) $p['run'] : '';
	$op  = isset( $p['op'] ) ? (string) $p['op'] : '';
	if ( ! preg_match( '/^[a-z0-9-]{1,32}$/', $run ) ) {
		return new WP_Error( 'tng_import_run', 'run must match [a-z0-9-]{1,32}.', array( 'status' => 400 ) );
	}
	$ops = array( 'media', 'terms', 'professional', 'term_fields', 'insight', 'award', 'job', 'redirects', 'page', 'resync', 'finalize', 'rollback', 'retire_demo', 'status', 'cutover' );
	if ( ! in_array( $op, $ops, true ) ) {
		return new WP_Error( 'tng_import_op', 'Unknown op. One of: ' . implode( ', ', $ops ), array( 'status' => 400 ) );
	}
	$dry    = ! ( array_key_exists( 'dry_run', $p ) && false === $p['dry_run'] ); // only an explicit false writes
	$author = isset( $p['author'] ) ? (int) $p['author'] : get_current_user_id();
	if ( ! get_userdata( $author ) ) {
		return new WP_Error( 'tng_import_author', 'author is not a user ID.', array( 'status' => 400 ) );
	}
	$items = isset( $p['items'] ) && is_array( $p['items'] ) ? array_values( $p['items'] ) : array();

	$missing = tng_import_check_setup( $op );
	if ( $missing ) {
		return new WP_Error( 'tng_import_setup', $missing, array( 'status' => 500 ) );
	}

	$locked = false;
	if ( ! $dry && 'status' !== $op ) {
		if ( ! add_option( 'tng_import_lock', time(), '', 'no' ) ) {
			if ( time() - (int) get_option( 'tng_import_lock' ) < 300 ) {
				return new WP_Error( 'tng_import_locked', 'Another import request is running.', array( 'status' => 409 ) );
			}
			update_option( 'tng_import_lock', time(), false ); // stale (> 5 min): take it over
		}
		$locked = true;
	}

	$ctx = array(
		'run'    => $run,
		'dry'    => $dry,
		'author' => $author,
		'start'  => $start,
		'seen'   => array(),
		'types'  => isset( $p['types'] ) ? array_map( 'strval', (array) $p['types'] ) : array(), // retire_demo only
		'from'   => isset( $p['from'] ) ? (string) $p['from'] : '', // cutover only
		'to'     => isset( $p['to'] ) ? (string) $p['to'] : '',
		'cursor' => isset( $p['cursor'] ) ? (array) $p['cursor'] : array(),
		'confirm' => isset( $p['confirm'] ) ? (string) $p['confirm'] : '',
	);
	tng_import_quiet();
	try {
		$out = tng_import_dispatch( $op, $items, $ctx );
	} finally {
		wp_defer_term_counting( false ); // recount the terms touched by this request
		if ( $locked ) {
			delete_option( 'tng_import_lock' );
		}
	}

	return rest_ensure_response(
		array(
			'run'     => $run,
			'op'      => $op,
			'dry_run' => $dry,
			'elapsed' => round( microtime( true ) - $start, 2 ),
		) + $out
	);
}

/** Error message when SCF / the field groups / the post types aren't in place, else ''. */
function tng_import_check_setup( $op ) {
	if ( ! function_exists( 'acf_get_field' ) ) {
		return 'Secure Custom Fields is not active.';
	}
	$types = tng_import_types();
	$keys  = array();
	if ( isset( $types[ $op ] ) ) {
		if ( ! post_type_exists( $op ) ) {
			return "Post type $op is not registered: import scf-import.json first.";
		}
		$keys[] = $types[ $op ]['check'];
		if ( 'insight' === $op ) {
			$keys[] = 'field_tng_mod_panels';
		}
	}
	if ( 'term_fields' === $op ) {
		$keys = array( 'field_tng_loc_flag', 'field_tng_svc_hero_image' );
	}
	foreach ( $keys as $key ) {
		if ( ! acf_get_field( $key ) ) {
			return "Field $key does not exist: import scf-import.json first.";
		}
	}
	return '';
}

/** Per-request settings: deferred term counts, no roster busting, fewer image sizes. */
function tng_import_quiet() {
	wp_defer_term_counting( true );
	// The roster transient is rebuilt by finalize, once, not on every save.
	remove_action( 'save_post_professional', 'tng_bust_roster' );
	remove_action( 'deleted_post', 'tng_bust_roster' );
	foreach ( array( 'industry', 'practice', 'location', 'position', 'language' ) as $tax ) {
		remove_action( "edited_{$tax}", 'tng_bust_roster' );
		remove_action( "delete_{$tax}", 'tng_bust_roster' );
		remove_action( "created_{$tax}", 'tng_bust_roster' );
	}
	// Only the sizes the templates and wp-admin use; no PDF preview renders.
	add_filter(
		'intermediate_image_sizes_advanced',
		static function ( $sizes ) {
			return array_intersect_key( $sizes, array_flip( array( 'thumbnail', 'medium_large', 'large' ) ) );
		}
	);
	add_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );
}

function tng_import_dispatch( $op, $items, &$ctx ) {
	switch ( $op ) {
		case 'status':
			return tng_import_status();
		case 'finalize':
			if ( ! $ctx['dry'] ) {
				tng_bust_roster();
				tng_legacy_ids( true ); // old ?p= IDs → new posts
				wp_cache_flush();
			}
			return array( 'done' => ! $ctx['dry'] ) + tng_import_status();
		case 'cutover':
			return tng_import_cutover( $ctx );
		case 'rollback':
			return tng_import_rollback( $ctx );
		case 'retire_demo':
			return tng_import_retire_demo( $ctx );
		case 'redirects':
			return tng_import_redirects( $items, $ctx );
	}

	$results = array();
	foreach ( $items as $item ) {
		$key = tng_import_item_key( $op, $item );
		if ( microtime( true ) - $ctx['start'] > TNG_IMPORT_BUDGET ) {
			$results[] = array( 'src' => $key, 'status' => 'pending' );
			continue;
		}
		try {
			if ( ! is_array( $item ) ) {
				$r = tng_import_error( 'item is not an object' );
			} elseif ( 'media' === $op ) {
				$r = tng_import_media( $item, $ctx );
			} elseif ( 'terms' === $op ) {
				$r = tng_import_term( $item, $ctx );
			} elseif ( 'term_fields' === $op ) {
				$r = tng_import_term_fields( $item, $ctx );
			} elseif ( 'page' === $op ) {
				$r = tng_import_page( $item, $ctx );
			} elseif ( 'resync' === $op ) {
				$r = tng_import_resync( $item, $ctx );
			} else {
				$r = tng_import_post( $op, $item, $ctx );
			}
		} catch ( Throwable $e ) {
			$r = tng_import_error( get_class( $e ) . ': ' . $e->getMessage() );
		}
		$results[] = array( 'src' => $key ) + $r;
	}
	return array( 'results' => $results );
}

/** What a result line is keyed by (the driver keys its progress log the same way). */
function tng_import_item_key( $op, $item ) {
	if ( ! is_array( $item ) ) {
		return '';
	}
	if ( 'terms' === $op || 'term_fields' === $op ) {
		return ( isset( $item['taxonomy'] ) ? $item['taxonomy'] : '' ) . ':' . ( isset( $item['slug'] ) ? $item['slug'] : '' );
	}
	if ( 'page' === $op && empty( $item['src'] ) ) {
		return isset( $item['path'] ) ? (string) $item['path'] : '';
	}
	return isset( $item['src'] ) ? tng_legacy_key( $item['src'] ) : '';
}

function tng_import_error( $msg ) {
	return array( 'status' => 'error', 'error' => $msg );
}

/* ---------------- lookups ---------------- */

/** ID of the object of $type imported from $src, any status (trash too), else 0. */
function tng_import_find( $src, $type ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_tng_src' AND m.meta_value = %s AND p.post_type = %s LIMIT 1",
			$src,
			$type
		)
	);
}

/** Attachment ID for a media src (cached per request), else 0. */
function tng_import_media_id( $src ) {
	static $cache = array();
	$key = tng_legacy_key( $src );
	if ( ! isset( $cache[ $key ] ) || ! $cache[ $key ] ) {
		$cache[ $key ] = tng_import_find( $key, 'attachment' );
	}
	return $cache[ $key ];
}

/** Professional IDs for srcs, in order: [ids, missing srcs]. One query. */
function tng_import_pro_ids( $srcs ) {
	global $wpdb;
	$keys = array_values( array_unique( array_filter( array_map( 'tng_legacy_key', array_filter( (array) $srcs, 'is_string' ) ) ) ) );
	if ( ! $keys ) {
		return array( array(), array() );
	}
	$in   = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.meta_value AS src, p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_tng_src' AND p.post_type = 'professional' AND p.post_status <> 'trash' AND m.meta_value IN ($in)", // phpcs:ignore
			$keys
		),
		OBJECT_K
	);
	$ids     = array();
	$missing = array();
	foreach ( $keys as $k ) {
		if ( isset( $rows[ $k ] ) ) {
			$ids[] = (int) $rows[ $k ]->ID;
		} else {
			$missing[] = $k;
		}
	}
	return array( $ids, $missing );
}

/**
 * A field value from the normalised JSON → what update_field() takes.
 * Returns null for "nothing to write" (missing media / page), WP_Error for bad input.
 */
function tng_import_value( $kind, $value, &$notes ) {
	if ( is_array( $kind ) ) { // repeater: rows keyed by sub-field name
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'x', 'expects an array of rows' );
		}
		$rows = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				return new WP_Error( 'x', 'row is not an object' );
			}
			$out = array();
			foreach ( $row as $sub => $v ) {
				if ( ! isset( $kind[ $sub ] ) ) {
					return new WP_Error( 'x', "unknown sub-field $sub" );
				}
				$v = tng_import_value( $kind[ $sub ], $v, $notes );
				if ( is_wp_error( $v ) ) {
					return $v;
				}
				$out[ $sub ] = $v;
			}
			$rows[] = $out;
		}
		return $rows;
	}
	switch ( $kind ) {
		case 'text': // rich text fields (bios, job groups) get the same upload URL rewrite as bodies
			return is_scalar( $value ) ? tng_import_rewrite_content( (string) $value, $notes ) : new WP_Error( 'x', 'expects a string' );
		case 'media':
			$id = is_string( $value ) ? tng_import_media_id( $value ) : 0;
			if ( ! $id ) {
				$notes[] = 'media_missing:' . ( is_string( $value ) ? tng_legacy_key( $value ) : '?' );
				return null;
			}
			return $id;
		case 'rel':
			list( $ids, $missing ) = tng_import_pro_ids( $value );
			foreach ( $missing as $m ) {
				$notes[] = 'rel_missing:' . $m;
			}
			return $ids;
		case 'page':
			$page = is_string( $value ) ? get_page_by_path( trim( $value, '/' ) ) : null;
			if ( ! $page ) {
				$notes[] = 'page_missing:' . ( is_string( $value ) ? $value : '?' );
				return null;
			}
			return (int) $page->ID;
		case 'location': // taxonomy sub-field (service contacts): country slug → term ID
			$term = is_string( $value ) ? get_term_by( 'slug', $value, 'location' ) : false;
			if ( ! $term ) {
				$notes[] = 'term_missing:location/' . ( is_string( $value ) ? $value : '?' );
				return null;
			}
			return (int) $term->term_id;
	}
	return new WP_Error( 'x', 'unknown kind' );
}

/** Empty for import purposes: nothing to write / nothing stored. */
function tng_import_empty( $v ) {
	return null === $v || '' === $v || false === $v || array() === $v;
}

/** Resolve item.fields against a whitelist: [key => value] or WP_Error. */
function tng_import_fields( $defs, $fields, &$notes ) {
	$out = array();
	foreach ( (array) $fields as $name => $value ) {
		$found = false;
		foreach ( $defs as $prefix => $names ) {
			if ( isset( $names[ $name ] ) ) {
				$found = true;
				if ( tng_import_empty( $value ) ) {
					break;
				}
				$v = tng_import_value( $names[ $name ], $value, $notes );
				if ( is_wp_error( $v ) ) {
					return new WP_Error( 'x', "field $name: " . $v->get_error_message() );
				}
				if ( ! tng_import_empty( $v ) ) {
					$out[ "field_tng_{$prefix}_{$name}" ] = array( $name, $v );
				}
				break;
			}
		}
		if ( ! $found ) {
			return new WP_Error( 'x', "unknown field $name" );
		}
	}
	return $out;
}

/* ---------------- content ---------------- */

/** Old uploads URL → [attachment ID, new URL (matching size, else full)], or [0, '']. */
function tng_import_upload_url( $url ) {
	$orig = preg_replace( '/-(\d+x\d+|scaled)(?=\.[a-z0-9]+$)/i', '', $url );
	$orig = preg_replace( '/-scaled(?=\.[a-z0-9]+$)/i', '', $orig ); // name-scaled-1024x683.jpg
	$id   = tng_import_media_id( $url );
	$id   = $id ? $id : tng_import_media_id( $orig );
	if ( ! $id ) {
		return array( 0, '' );
	}
	if ( preg_match( '/-(\d+)x(\d+)\.[a-z0-9]+$/i', $url, $d ) ) {
		$meta = wp_get_attachment_metadata( $id );
		foreach ( ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) ? $meta['sizes'] : array() as $size => $s ) {
			if ( (int) $s['width'] === (int) $d[1] && (int) $s['height'] === (int) $d[2] ) {
				return array( $id, wp_get_attachment_image_url( $id, $size ) );
			}
		}
	}
	return array( $id, wp_get_attachment_url( $id ) );
}

/**
 * Old uploads URLs in a body → imported attachments, as root-relative URLs
 * (never the current host: it changes at cutover). <img> tags also get
 * class wp-image-<id> (WP builds srcset at render) and lose the old
 * srcset / sizes. Unmapped URLs stay as they are, with a note.
 */
function tng_import_rewrite_content( $html, &$notes ) {
	$re   = '#https?://(?:www\.)?tilleke\.com/wp-content/uploads/[^"\'\s<>()?\#]+#i';
	$html = preg_replace_callback(
		'#<img\b[^>]*>#i',
		static function ( $m ) use ( $re, &$notes ) {
			$tag = $m[0];
			if ( ! preg_match( '#\ssrc=(["\'])(.*?)\1#i', $tag, $s ) || ! preg_match( $re, $s[2] ) ) {
				return $tag;
			}
			list( $id, $url ) = tng_import_upload_url( $s[2] );
			if ( ! $id ) {
				return $tag; // noted by the URL pass below
			}
			$tag = str_replace( $s[0], ' src="' . esc_url( wp_make_link_relative( $url ) ) . '"', $tag );
			$tag = preg_replace( '#\s(?:srcset|sizes)=(["\']).*?\1#i', '', $tag );
			if ( preg_match( '#\sclass=(["\'])(.*?)\1#i', $tag, $c ) ) {
				$classes = trim( preg_replace( '/\s+/', ' ', preg_replace( '/\bwp-image-\d+\b/', '', $c[2] ) ) . ' wp-image-' . $id );
				$tag     = str_replace( $c[0], ' class="' . esc_attr( $classes ) . '"', $tag );
			} else {
				$tag = preg_replace( '#^<img\b#i', '<img class="wp-image-' . $id . '"', $tag );
			}
			return $tag;
		},
		(string) $html
	);
	return preg_replace_callback(
		$re,
		static function ( $m ) use ( &$notes ) {
			list( $id, $url ) = tng_import_upload_url( $m[0] );
			if ( ! $id ) {
				$notes[] = 'media_unmapped:' . $m[0];
				return $m[0];
			}
			return wp_make_link_relative( $url ); // root-relative: survives the domain switch
		},
		$html
	);
}

/** Card excerpt: the item's, else the body's first 55 words; never leftover [shortcodes]. */
function tng_import_excerpt( $excerpt, $content ) {
	$text = '' !== trim( (string) $excerpt ) ? (string) $excerpt : (string) $content;
	$text = preg_replace( '/\[\/?[a-z][^\]]*\]/i', '', strip_shortcodes( $text ) );
	$text = preg_replace( '#</(p|h[1-6]|li|div|figure|blockquote)>#i', '$0 ', $text ); // keep words apart
	return wp_trim_words( wp_strip_all_tags( $text ), 55, '…' );
}

/* ---------------- ops ---------------- */

function tng_import_post( $type, $item, &$ctx ) {
	global $wpdb;
	$cfg = tng_import_types()[ $type ];
	if ( empty( $item['src'] ) || ! is_string( $item['src'] ) ) {
		return tng_import_error( 'src required' );
	}
	$src = tng_legacy_key( $item['src'] );
	$id  = tng_import_find( $src, $type );
	if ( $id ) {
		return array( 'status' => 'skipped', 'id' => $id, 'notes' => array( 'exists' ) );
	}
	if ( isset( $ctx['seen'][ $src ] ) ) {
		return array( 'status' => 'skipped', 'notes' => array( 'duplicate_in_batch' ) );
	}

	$title  = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
	$slug   = isset( $item['slug'] ) ? sanitize_title( (string) $item['slug'] ) : '';
	$status = isset( $item['status'] ) ? (string) $item['status'] : 'publish';
	if ( '' === $title || '' === $slug ) {
		return tng_import_error( 'title and slug required' );
	}
	if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
		return tng_import_error( 'status must be publish or future' );
	}
	foreach ( array( 'date', 'date_gmt' ) as $d ) {
		if ( ! empty( $item[ $d ] ) && ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $item[ $d ] ) ) {
			return tng_import_error( "$d must be Y-m-d H:i:s" );
		}
	}

	$owner = $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status NOT IN ('trash', 'auto-draft') LIMIT 1", $type, $slug ) );
	if ( $owner ) {
		$from = get_post_meta( $owner->ID, '_tng_src', true );
		return tng_import_error( 'slug taken by #' . $owner->ID . ' (' . $type . ', ' . ( $from ? 'imported from ' . $from : 'not imported' ) . ')' );
	}
	if ( isset( $ctx['seen'][ "slug:$slug" ] ) ) {
		return tng_import_error( 'slug taken by another item in this batch' );
	}

	// terms (the terms pass runs first, so a missing one is an error)
	$terms   = array();
	$missing = array();
	foreach ( isset( $item['terms'] ) ? (array) $item['terms'] : array() as $tax => $slugs ) {
		if ( ! in_array( $tax, $cfg['tax'], true ) ) {
			return tng_import_error( "taxonomy $tax not allowed on $type" );
		}
		$terms[ $tax ] = array();
		foreach ( (array) $slugs as $s ) {
			$t = get_term_by( 'slug', (string) $s, $tax );
			if ( $t ) {
				$terms[ $tax ][] = (int) $t->term_id;
			} else {
				$missing[] = "$tax/$s";
			}
		}
	}
	if ( $missing ) {
		return tng_import_error( 'term_missing: ' . implode( ', ', $missing ) );
	}

	$notes  = array();
	$fields = tng_import_fields( $cfg['fields'], isset( $item['fields'] ) ? $item['fields'] : array(), $notes );
	if ( is_wp_error( $fields ) ) {
		return tng_import_error( $fields->get_error_message() );
	}

	$template = isset( $item['template'] ) ? (string) $item['template'] : 'standard';
	if ( ! in_array( $template, array( 'standard', 'module' ), true ) || ( 'module' === $template && 'insight' !== $type ) ) {
		return tng_import_error( 'template must be standard (or module on an insight)' );
	}

	$content = tng_import_rewrite_content( isset( $item['content'] ) ? (string) $item['content'] : '', $notes );
	$thumb   = 0;
	if ( ! empty( $item['featured_image'] ) ) {
		$thumb = tng_import_media_id( (string) $item['featured_image'] );
		if ( ! $thumb ) {
			$notes[] = 'media_missing:' . tng_legacy_key( (string) $item['featured_image'] );
		}
	}

	$meta = array(
		'_tng_src' => $src,
		'_tng_run' => $ctx['run'],
		'_tng_at'  => gmdate( 'Y-m-d H:i:s' ),
	);
	$seo  = isset( $item['seo'] ) && is_array( $item['seo'] ) ? $item['seo'] : array();
	if ( ! empty( $seo['title'] ) ) {
		$meta['_yoast_wpseo_title'] = (string) $seo['title'];
	}
	if ( ! empty( $seo['description'] ) ) {
		$meta['_yoast_wpseo_metadesc'] = (string) $seo['description'];
	}
	if ( ! empty( $seo['noindex'] ) ) {
		$meta['_yoast_wpseo_meta-robots-noindex'] = '1';
	}
	if ( 'module' === $template ) {
		$meta['_wp_page_template'] = 'single-insight-module';
	}
	// Old-site extras kept for later filtering / editorial checks (not rendered).
	$legacy = isset( $item['legacy'] ) && is_array( $item['legacy'] ) ? $item['legacy'] : array();
	$lang   = ! empty( $item['lang'] ) ? $item['lang'] : ( isset( $legacy['lang'] ) ? $legacy['lang'] : '' );
	if ( is_string( $lang ) && preg_match( '/^[a-z]{2}$/', $lang ) ) {
		$meta['_tng_lang'] = $lang;
	}
	if ( $legacy ) {
		$meta['_tng_legacy'] = $legacy;
	}

	$ctx['seen'][ $src ]          = 1;
	$ctx['seen'][ "slug:$slug" ] = 1;
	if ( $ctx['dry'] ) {
		return array( 'status' => 'would_create', 'notes' => $notes );
	}

	$postarr = array(
		'post_type'    => $type,
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => $status,
		'post_author'  => $ctx['author'],
		'meta_input'   => $meta,
	);
	if ( in_array( $type, array( 'insight', 'award' ), true ) ) {
		$postarr['post_excerpt'] = tng_import_excerpt( isset( $item['excerpt'] ) ? $item['excerpt'] : '', $content );
	}
	if ( ! empty( $item['date'] ) ) {
		$postarr['post_date'] = (string) $item['date'];
	}
	if ( ! empty( $item['date_gmt'] ) ) {
		$postarr['post_date_gmt'] = (string) $item['date_gmt'];
	}

	$id = wp_insert_post( wp_slash( $postarr ), true );
	if ( is_wp_error( $id ) ) {
		return tng_import_error( $id->get_error_message() );
	}
	foreach ( $terms as $tax => $ids ) {
		wp_set_object_terms( $id, $ids, $tax );
	}
	foreach ( $fields as $key => $pair ) {
		update_field( $key, wp_slash( $pair[1] ), $id ); // update_metadata() unslashes
	}
	if ( $thumb ) {
		set_post_thumbnail( $id, $thumb );
	}
	// Rollback deletes the post only while this still equals post_modified_gmt.
	add_post_meta( $id, '_tng_mod', get_post_field( 'post_modified_gmt', $id, 'raw' ) );

	$name = get_post_field( 'post_name', $id );
	if ( $name !== $slug ) {
		$notes[] = 'slug_changed:' . $name;
	}
	return array(
		'status' => 'created',
		'id'     => $id,
		'url'    => get_permalink( $id ),
		'notes'  => $notes,
	);
}

function tng_import_media( $item, &$ctx ) {
	if ( empty( $item['src'] ) || ! is_string( $item['src'] ) ) {
		return tng_import_error( 'src required' );
	}
	$src = tng_legacy_key( $item['src'] );
	$id  = tng_import_find( $src, 'attachment' );
	if ( $id ) {
		return array( 'status' => 'skipped', 'id' => $id, 'notes' => array( 'exists' ) );
	}
	$path = isset( $item['path'] ) ? (string) $item['path'] : '';
	if ( '' === $path && preg_match( '#/uploads/(\d{4}/\d{2})/#', $src, $m ) ) {
		$path = $m[1];
	}
	if ( '' !== $path && ! preg_match( '#^\d{4}/\d{2}$#', $path ) ) {
		return tng_import_error( 'path must be YYYY/MM' );
	}
	if ( $ctx['dry'] ) {
		return array( 'status' => 'would_create', 'notes' => array() );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	if ( ! empty( $item['data'] ) ) { // fallback when the host can't download from the old site
		$bytes = base64_decode( (string) $item['data'], true );
		$tmp   = wp_tempnam( 'tng-import' );
		if ( false === $bytes || ! $tmp || false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore
			return tng_import_error( 'bad data' );
		}
	} else {
		$tmp = download_url( isset( $item['url'] ) ? (string) $item['url'] : $src, 60 );
		if ( is_wp_error( $tmp ) ) {
			// the code (http_404, http_429, http_520 …) lets the driver tell a dead file from a struggling origin
			return tng_import_error( 'download: ' . $tmp->get_error_code() . ' ' . $tmp->get_error_message() );
		}
	}

	$file = array(
		'name'     => rawurldecode( wp_basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) ),
		'tmp_name' => $tmp,
	);
	// Third argument keeps the old YYYY/MM folder, so most old upload paths stay valid.
	$up = wp_handle_sideload( $file, array( 'test_form' => false ), '' !== $path ? $path : null );
	if ( isset( $up['error'] ) ) {
		@unlink( $tmp ); // phpcs:ignore
		return tng_import_error( $up['error'] );
	}

	$meta = array(
		'_tng_src' => $src,
		'_tng_run' => $ctx['run'],
		'_tng_at'  => gmdate( 'Y-m-d H:i:s' ),
	);
	if ( ! empty( $item['alt'] ) ) {
		$meta['_wp_attachment_image_alt'] = (string) $item['alt'];
	}
	$id = wp_insert_attachment(
		wp_slash(
			array(
				'post_mime_type' => $up['type'],
				'guid'           => $up['url'],
				'post_title'     => ! empty( $item['title'] ) ? (string) $item['title'] : preg_replace( '/\.[^.]+$/', '', $file['name'] ),
				'post_excerpt'   => isset( $item['caption'] ) ? (string) $item['caption'] : '',
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $ctx['author'],
				'meta_input'     => $meta,
			)
		),
		$up['file'],
		0,
		true
	);
	if ( is_wp_error( $id ) ) {
		@unlink( $up['file'] ); // phpcs:ignore
		return tng_import_error( $id->get_error_message() );
	}
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
	return array(
		'status' => 'created',
		'id'     => $id,
		'url'    => wp_get_attachment_url( $id ),
		'notes'  => wp_basename( $up['file'] ) !== $file['name'] ? array( 'renamed:' . wp_basename( $up['file'] ) ) : array(),
	);
}

function tng_import_term( $item, &$ctx ) {
	$tax  = isset( $item['taxonomy'] ) ? (string) $item['taxonomy'] : '';
	$slug = isset( $item['slug'] ) ? sanitize_title( (string) $item['slug'] ) : '';
	$name = isset( $item['name'] ) ? trim( (string) $item['name'] ) : '';
	if ( ! in_array( $tax, TNG_IMPORT_TAXONOMIES, true ) || ! taxonomy_exists( $tax ) ) {
		return tng_import_error( "taxonomy $tax not allowed" );
	}
	if ( '' === $slug || '' === $name ) {
		return tng_import_error( 'slug and name required' );
	}
	$term = get_term_by( 'slug', $slug, $tax );
	if ( $term ) { // existing: never renamed or re-parented
		return array( 'status' => 'skipped', 'id' => (int) $term->term_id, 'notes' => array( 'exists' ) );
	}
	$parent = 0;
	if ( ! empty( $item['parent'] ) ) {
		$p = get_term_by( 'slug', (string) $item['parent'], $tax );
		if ( ! $p && ! isset( $ctx['seen'][ "$tax:" . $item['parent'] ] ) ) {
			return tng_import_error( 'parent missing: ' . $item['parent'] );
		}
		$parent = $p ? (int) $p->term_id : 0;
	} elseif ( in_array( $tax, array( 'industry', 'practice' ), true ) ) {
		// Every top-level industry / practice is listed in the Services megamenu.
		return tng_import_error( 'new top-level ' . $tax . ' needs the user\'s approval: create it in wp-admin, then rerun' );
	}
	$ctx['seen'][ "$tax:$slug" ] = 1;
	if ( $ctx['dry'] ) {
		return array( 'status' => 'would_create', 'notes' => array() );
	}
	$r = wp_insert_term( wp_slash( $name ), $tax, array( 'slug' => $slug, 'parent' => $parent ) );
	if ( is_wp_error( $r ) ) {
		return tng_import_error( $r->get_error_message() );
	}
	$tid = (int) $r['term_id'];
	add_term_meta( $tid, '_tng_run', $ctx['run'] );
	add_term_meta( $tid, '_tng_at', gmdate( 'Y-m-d H:i:s' ) );
	if ( ! empty( $item['src'] ) ) {
		add_term_meta( $tid, '_tng_src', tng_legacy_key( $item['src'] ) );
	}
	return array( 'status' => 'created', 'id' => $tid, 'url' => tng_term_link( get_term( $tid, $tax ) ), 'notes' => array() );
}

/** Raw stored value of a term field (ACF, unformatted) or of a term meta key ("meta:<key>"). */
function tng_import_term_raw( $name, $term ) {
	if ( 0 === strpos( $name, 'meta:' ) ) {
		return get_term_meta( $term->term_id, substr( $name, 5 ), true );
	}
	return get_field( $name, tng_term_ref( $term ), false );
}

/**
 * Whether a term field still holds exactly what TNG Seed wrote: the city
 * office mock (Bangkok's details on every city) or the mock copy on Cambodia /
 * Automotive / Intellectual Property / Commercialization. Values come from
 * inc/seed.php itself. Anything an editor changed is not "seeded".
 */
function tng_import_is_seeded( $name, $term ) {
	if ( 0 === strpos( $name, 'meta:' ) || ! function_exists( 'tng_seed_mock_term_values' ) ) {
		return false;
	}
	$ref = tng_term_ref( $term );
	if ( 'location' === $term->taxonomy && $term->parent ) {
		foreach ( tng_seed_mock_offices() as $o ) {
			if ( sanitize_title( $o[0] ) === $term->slug ) {
				$mock = tng_seed_mock_office_values( $o[2] );
				return isset( $mock[ $name ] ) && (string) get_field( $name, $ref, false ) === $mock[ $name ];
			}
		}
		return false;
	}
	if ( 'contacts' === $name ) {
		return tng_import_contacts_are_demo( $ref );
	}
	$all = tng_seed_mock_term_values();
	$key = $term->taxonomy . ':' . $term->slug;
	if ( ! isset( $all[ $key ][ $name ] ) ) {
		return false;
	}
	$seeded = $all[ $key ][ $name ];
	if ( is_array( $seeded ) ) { // repeater of text sub-fields: compare by sub-field name
		$rows = get_field( $name, $ref );
		return is_array( $rows ) && array_map( static function ( $r ) { return array_map( 'strval', (array) $r ); }, $rows ) === $seeded;
	}
	return (string) get_field( $name, $ref, false ) === $seeded;
}

/** Service contacts that point only at demo professionals (seeded, or since deleted). */
function tng_import_contacts_are_demo( $ref ) {
	$rows = get_field( 'contacts', $ref, false );
	$ids  = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$people = isset( $row['field_tng_svc_contacts_people'] ) ? $row['field_tng_svc_contacts_people'] : ( isset( $row['people'] ) ? $row['people'] : array() );
		$ids    = array_merge( $ids, array_map( 'intval', (array) $people ) );
	}
	if ( ! $ids ) {
		return false;
	}
	$demo = tng_import_demo_titles()['professional'];
	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( $p && ( 'professional' !== $p->post_type || ! in_array( $p->post_title, $demo, true ) || get_post_meta( $id, '_tng_src', true ) ) ) {
			return false;
		}
	}
	return true;
}

function tng_import_term_fields( $item, &$ctx ) {
	$tax  = isset( $item['taxonomy'] ) ? (string) $item['taxonomy'] : '';
	$defs = tng_import_term_field_defs( $tax );
	$term = $defs ? get_term_by( 'slug', isset( $item['slug'] ) ? (string) $item['slug'] : '', $tax ) : false;
	if ( ! $term ) {
		return tng_import_error( $defs ? 'term not found' : "taxonomy $tax has no fields" );
	}
	$notes  = array();
	$fields = tng_import_fields( $defs, isset( $item['fields'] ) ? $item['fields'] : array(), $notes );
	if ( is_wp_error( $fields ) ) {
		return tng_import_error( $fields->get_error_message() );
	}
	$seo = isset( $item['seo'] ) && is_array( $item['seo'] ) ? $item['seo'] : array();
	foreach ( array( 'title' => '_yoast_wpseo_title', 'description' => '_yoast_wpseo_metadesc' ) as $k => $meta_key ) {
		if ( ! empty( $seo[ $k ] ) ) {
			$fields[ "meta:$meta_key" ] = array( "meta:$meta_key", (string) $seo[ $k ] );
		}
	}
	if ( ! empty( $seo['noindex'] ) ) {
		$fields['meta:_yoast_wpseo_meta-robots-noindex'] = array( 'meta:_yoast_wpseo_meta-robots-noindex', '1' );
	}

	// Fill-empty only: a field that holds anything is never overwritten, except
	// TNG Seed's mock copy still exactly as seeded (approved to be replaced).
	$write = array();
	foreach ( $fields as $key => $pair ) {
		if ( tng_import_empty( tng_import_term_raw( $pair[0], $term ) ) ) {
			$write[ $key ] = $pair;
		} elseif ( tng_import_is_seeded( $pair[0], $term ) ) {
			$write[ $key ] = $pair;
			$notes[]       = 'replaced_seed:' . $pair[0];
		} else {
			$notes[] = 'kept:' . $pair[0];
		}
	}
	if ( ! $write ) {
		return array( 'status' => 'skipped', 'id' => (int) $term->term_id, 'notes' => $notes );
	}
	if ( $ctx['dry'] ) {
		return array( 'status' => 'would_fill', 'id' => (int) $term->term_id, 'notes' => array_merge( $notes, array( 'fill:' . implode( ',', wp_list_pluck( $write, 0 ) ) ) ) );
	}
	$hashes = array();
	foreach ( $write as $key => $pair ) {
		if ( 0 === strpos( $key, 'meta:' ) ) {
			update_term_meta( $term->term_id, substr( $key, 5 ), wp_slash( $pair[1] ) );
		} else {
			update_field( $key, wp_slash( $pair[1] ), tng_term_ref( $term ) );
		}
		// What was written, so rollback clears it only while it is unchanged.
		$hashes[ $pair[0] ] = md5( maybe_serialize( tng_import_term_raw( $pair[0], $term ) ) );
	}
	add_term_meta( $term->term_id, '_tng_filled', array( 'run' => $ctx['run'], 'fields' => $hashes ) );
	return array( 'status' => 'filled', 'id' => (int) $term->term_id, 'notes' => array_merge( $notes, array( 'fill:' . implode( ',', array_keys( $hashes ) ) ) ) );
}

/**
 * Redirects: option tng_redirects (canonical old URL => [target, code, run])
 * and tng_redirects_regex ([from, to, code, run], matched against the path).
 * Create-only: an existing "from" is never overwritten. Read on 404s only.
 */
function tng_import_redirects( $items, &$ctx ) {
	$map     = get_option( 'tng_redirects', array() );
	$regex   = get_option( 'tng_redirects_regex', array() );
	$results = array();
	foreach ( $items as $item ) {
		$from = is_array( $item ) && isset( $item['from'] ) ? (string) $item['from'] : '';
		$to   = is_array( $item ) && isset( $item['to'] ) ? trim( (string) $item['to'] ) : '';
		$code = is_array( $item ) && isset( $item['code'] ) ? (int) $item['code'] : 301;
		$is_re = is_array( $item ) && ! empty( $item['regex'] );
		$key  = $is_re ? $from : tng_legacy_key( $from );
		$r    = array( 'src' => $key );
		if ( '' === $from || '' === $to || ! in_array( $code, array( 301, 302, 307, 308 ), true ) ) {
			$results[] = $r + tng_import_error( 'from, to and code (301/302/307/308) required' );
			continue;
		}
		if ( $is_re && false === @preg_match( $from, '' ) ) { // phpcs:ignore
			$results[] = $r + tng_import_error( 'invalid regex' );
			continue;
		}
		$exists = $is_re ? in_array( $from, wp_list_pluck( $regex, 'from' ), true ) : isset( $map[ $key ] );
		if ( $exists ) {
			$results[] = $r + array( 'status' => 'skipped', 'notes' => array( 'exists' ) );
			continue;
		}
		if ( $is_re ) {
			$regex[] = array( 'from' => $from, 'to' => $to, 'code' => $code, 'run' => $ctx['run'] );
		} else {
			$map[ $key ] = array( $to, $code, $ctx['run'] );
		}
		$results[] = $r + array( 'status' => $ctx['dry'] ? 'would_create' : 'created', 'notes' => array() );
	}
	if ( ! $ctx['dry'] ) {
		update_option( 'tng_redirects', $map, false );
		update_option( 'tng_redirects_regex', $regex, false );
	}
	return array( 'results' => $results );
}

/* ---------------- fix round 2026-11-14: page, resync, cutover ---------------- */

/**
 * Create a page at a path (create-only; an existing page there, any status, is
 * "skipped"). Item: path, title, content (block markup), template
 * (tng-text | tng-blank), optional src (old URL), seo { title, description }.
 */
function tng_import_page( $item, &$ctx ) {
	$path     = trim( isset( $item['path'] ) ? (string) $item['path'] : '', '/' );
	$title    = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
	$template = isset( $item['template'] ) ? (string) $item['template'] : 'tng-blank';
	if ( ! preg_match( '#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $path ) || '' === $title || ! in_array( $template, array( 'tng-text', 'tng-blank' ), true ) ) {
		return tng_import_error( 'path ([a-z0-9-/]), title and template (tng-text | tng-blank) required' );
	}
	$page = get_page_by_path( $path, OBJECT, 'page' );
	if ( $page ) {
		return array( 'status' => 'skipped', 'id' => (int) $page->ID, 'notes' => array( 'exists' ) );
	}
	$parent = 0;
	if ( false !== strpos( $path, '/' ) ) {
		$p = get_page_by_path( dirname( $path ), OBJECT, 'page' );
		if ( ! $p ) {
			return tng_import_error( 'parent page missing: ' . dirname( $path ) );
		}
		$parent = (int) $p->ID;
	}
	if ( $ctx['dry'] ) {
		return array( 'status' => 'would_create', 'notes' => array() );
	}
	$meta = array( '_tng_run' => $ctx['run'], '_tng_at' => gmdate( 'Y-m-d H:i:s' ), '_wp_page_template' => $template );
	if ( ! empty( $item['src'] ) ) {
		$meta['_tng_src'] = tng_legacy_key( (string) $item['src'] );
	}
	$seo = isset( $item['seo'] ) && is_array( $item['seo'] ) ? $item['seo'] : array();
	if ( ! empty( $seo['title'] ) ) {
		$meta['_yoast_wpseo_title'] = (string) $seo['title'];
	}
	if ( ! empty( $seo['description'] ) ) {
		$meta['_yoast_wpseo_metadesc'] = (string) $seo['description'];
	}
	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => basename( $path ),
				'post_parent'  => $parent,
				'post_content' => isset( $item['content'] ) ? (string) $item['content'] : '',
				'post_author'  => $ctx['author'],
				'meta_input'   => $meta,
			)
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return tng_import_error( $id->get_error_message() );
	}
	add_post_meta( $id, '_tng_mod', get_post_field( 'post_modified_gmt', $id, 'raw' ) );
	return array( 'status' => 'created', 'id' => (int) $id, 'url' => get_permalink( $id ), 'notes' => array() );
}

/** A <video> whose file did not come over (still on the old host): a line of text instead of a dead player. */
function tng_import_unplayable( $html, &$notes ) {
	return preg_replace_callback(
		'#<video\b[^>]*>.*?</video>#is',
		static function ( $m ) use ( &$notes ) {
			if ( ! preg_match( '#\ssrc="https?://(www\.)?tilleke\.com/#i', $m[0] ) ) {
				return preg_replace( '#\sposter="https?://(www\.)?tilleke\.com/[^"]*"#i', '', $m[0] ); // a poster that did not come over
			}
			$notes[] = 'video_unavailable';
			return '<em>(Video interview not available.)</em>';
		},
		(string) $html
	);
}

/** Kind of an item field (text | media | rel | page | array) from a type's whitelist, else ''. */
function tng_import_kind( $defs, $name ) {
	foreach ( $defs as $names ) {
		if ( isset( $names[ $name ] ) ) {
			return $names[ $name ];
		}
	}
	return '';
}

/**
 * Re-sync one imported post from the re-run transform (item = transform item +
 * "type"). Only a post nobody edited since import (post_modified_gmt still
 * equals _tng_mod) is touched; anything else is "skipped" with the reason.
 * Content / excerpt are rewritten when they differ (on an untouched post they
 * are still exactly what the importer wrote); fields are filled when empty,
 * and text fields replaced when they differ. Previous values are kept in
 * _tng_resync (run, at, prev) for an undo; _tng_mod moves to the new
 * modified time so rollback keeps treating the post as the importer's.
 */
function tng_import_resync( $item, &$ctx ) {
	$type  = isset( $item['type'] ) ? (string) $item['type'] : '';
	$types = tng_import_types();
	if ( ! isset( $types[ $type ] ) || empty( $item['src'] ) || ! is_string( $item['src'] ) ) {
		return tng_import_error( 'type (' . implode( ' | ', array_keys( $types ) ) . ') and src required' );
	}
	$id = tng_import_find( tng_legacy_key( $item['src'] ), $type );
	if ( ! $id ) {
		return tng_import_error( 'not imported' );
	}
	$post = get_post( $id );
	$mod  = (string) get_post_meta( $id, '_tng_mod', true );
	if ( 'trash' === $post->post_status ) {
		return array( 'status' => 'skipped', 'id' => $id, 'notes' => array( 'trashed' ) );
	}
	if ( '' === $mod || $mod !== $post->post_modified_gmt ) {
		return array( 'status' => 'skipped', 'id' => $id, 'notes' => array( 'edited since import: modified ' . $post->post_modified_gmt . ' GMT, imported ' . $mod ) );
	}

	$notes   = array();
	$changes = array(); // post columns
	$fields  = array(); // field key => [name, value]
	$prev    = array();
	if ( 'professional' !== $type && isset( $item['content'] ) ) {
		$content = tng_import_unplayable( tng_import_rewrite_content( (string) $item['content'], $notes ), $notes );
		if ( $content !== $post->post_content ) {
			$changes['post_content'] = $content;
		}
		if ( in_array( $type, array( 'insight', 'award' ), true ) ) {
			$excerpt = tng_import_excerpt( isset( $item['excerpt'] ) ? $item['excerpt'] : '', $content );
			if ( $excerpt !== $post->post_excerpt ) {
				$changes['post_excerpt'] = $excerpt;
			}
		}
	}
	$resolved = tng_import_fields( $types[ $type ]['fields'], isset( $item['fields'] ) ? $item['fields'] : array(), $notes );
	if ( is_wp_error( $resolved ) ) {
		return tng_import_error( $resolved->get_error_message() );
	}
	foreach ( $resolved as $key => $pair ) {
		$kind  = tng_import_kind( $types[ $type ]['fields'], $pair[0] );
		$raw   = get_post_meta( $id, $pair[0], true );
		$empty = is_array( $kind ) ? ! (int) $raw : tng_import_empty( $raw );
		if ( $empty || ( 'text' === $kind && (string) $raw !== (string) $pair[1] ) ) {
			$fields[ $key ]       = $pair;
			$prev[ $pair[0] ]     = $raw;
		}
	}
	foreach ( $changes as $col => $unused ) {
		$prev[ $col ] = $post->$col;
	}
	if ( ! $changes && ! $fields ) {
		return array( 'status' => 'unchanged', 'id' => $id, 'notes' => $notes );
	}
	$what = array_merge( array_keys( $changes ), array_map( static function ( $p ) { return 'field:' . $p[0]; }, array_values( $fields ) ) );
	if ( isset( $changes['post_content'] ) ) {
		$what[] = 'delta:' . ( strlen( $changes['post_content'] ) - strlen( $post->post_content ) );
	}
	if ( $ctx['dry'] ) {
		return array( 'status' => 'would_update', 'id' => $id, 'notes' => array_merge( $notes, array( 'change:' . implode( ',', $what ) ) ) );
	}
	add_post_meta( $id, '_tng_resync', wp_slash( array( 'run' => $ctx['run'], 'at' => gmdate( 'Y-m-d H:i:s' ), 'prev' => $prev ) ) );
	if ( $changes ) {
		$r = wp_update_post( wp_slash( array( 'ID' => $id ) + $changes ), true );
		if ( is_wp_error( $r ) ) {
			return tng_import_error( $r->get_error_message() );
		}
	}
	foreach ( $fields as $key => $pair ) {
		update_field( $key, wp_slash( $pair[1] ), $id );
	}
	clean_post_cache( $id );
	update_post_meta( $id, '_tng_mod', get_post_field( 'post_modified_gmt', $id, 'raw' ) );
	return array( 'status' => 'updated', 'id' => $id, 'notes' => array_merge( $notes, array( 'change:' . implode( ',', $what ) ) ) );
}

/**
 * Host search-replace for the domain switch: $from → $to in a string,
 * serialized data (re-serialized with the right lengths, recursively) and JSON
 * (https:\/\/host). http:// becomes https://. $n counts replacements.
 */
function tng_cutover_replace( $value, $from, $to, &$n ) {
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ tng_cutover_replace( $k, $from, $to, $n ) ] = tng_cutover_replace( $v, $from, $to, $n );
		}
		return $out;
	}
	if ( is_object( $value ) ) { // incomplete classes are left alone (not ours to rewrite)
		if ( $value instanceof __PHP_Incomplete_Class ) {
			return $value;
		}
		foreach ( get_object_vars( $value ) as $k => $v ) {
			$value->$k = tng_cutover_replace( $v, $from, $to, $n );
		}
		return $value;
	}
	if ( ! is_string( $value ) || false === stripos( $value, $from ) ) {
		return $value;
	}
	if ( is_serialized( $value ) ) {
		$data = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore
		if ( false !== $data || 'b:0;' === $value ) {
			return serialize( tng_cutover_replace( $data, $from, $to, $n ) ); // phpcs:ignore
		}
	}
	$n    += substr_count( strtolower( $value ), strtolower( $from ) );
	$value = str_ireplace( array( 'http://' . $from, 'http:\/\/' . $from ), array( 'https://' . $to, 'https:\/\/' . $to ), $value );
	return str_ireplace( $from, $to, $value );
}

/** The columns the cutover scans: table => [primary key, columns, group-by column or ''] */
function tng_cutover_targets() {
	global $wpdb;
	return array(
		$wpdb->posts         => array( 'ID', array( 'post_content', 'post_excerpt', 'post_title' ), 'post_type' ),
		$wpdb->postmeta      => array( 'meta_id', array( 'meta_value' ), 'meta_key' ),
		$wpdb->options       => array( 'option_id', array( 'option_value' ), 'option_name' ),
		$wpdb->termmeta      => array( 'meta_id', array( 'meta_value' ), 'meta_key' ),
		$wpdb->term_taxonomy => array( 'term_taxonomy_id', array( 'description' ), 'taxonomy' ),
		$wpdb->usermeta      => array( 'umeta_id', array( 'meta_value' ), 'meta_key' ),
		$wpdb->users         => array( 'ID', array( 'user_url' ), '' ),
		$wpdb->comments      => array( 'comment_ID', array( 'comment_content', 'comment_author_url' ), '' ),
	);
}

/**
 * Cutover: replace the staging host in the database (body: from, to; dry run
 * by default). Skips siteurl / home (Settings → General, step 1 of the
 * runbook), transients, the import lock and post GUIDs (WP convention; only
 * counted). A real run also needs "confirm": "<from> -> <to>" and goes in
 * batches of 300 rows ("more" + "cursor": call again with the cursor).
 */
function tng_import_cutover( &$ctx ) {
	global $wpdb;
	$from = strtolower( trim( $ctx['from'] ) );
	$to   = strtolower( trim( $ctx['to'] ) );
	if ( ! preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $from ) || ! preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $to ) || $from === $to ) {
		return array( 'results' => array( tng_import_error( 'from and to must be two different host names' ) ) );
	}
	if ( ! $ctx['dry'] && $ctx['confirm'] !== "$from -> $to" ) {
		return array( 'results' => array( tng_import_error( 'a real run needs "confirm": "' . $from . ' -> ' . $to . '"' ) ) );
	}
	$skip_options = array( 'siteurl', 'home', 'tng_import_lock' );
	$like         = '%' . $wpdb->esc_like( $from ) . '%';
	$report       = array();
	$skipped      = array();
	$start_at     = isset( $ctx['cursor']['table'] ) ? (int) $ctx['cursor']['table'] : 0; // index into the targets
	$start_after  = isset( $ctx['cursor']['after'] ) ? (int) $ctx['cursor']['after'] : 0;
	$cursor       = null;
	$rows_done    = 0;
	foreach ( array_values( tng_cutover_targets() ) as $i => $def ) {
		$table = array_keys( tng_cutover_targets() )[ $i ];
		if ( $i < $start_at ) {
			continue;
		}
		list( $pk, $cols, $group ) = $def;
		$short = substr( $table, strlen( $wpdb->prefix ) );
		$where = implode( ' OR ', array_map( static function ( $c ) use ( $wpdb, $like ) { return $wpdb->prepare( "`$c` LIKE %s", $like ); }, $cols ) ); // phpcs:ignore
		$sel   = "`$pk`, " . implode( ', ', array_map( static function ( $c ) { return "`$c`"; }, $cols ) ) . ( $group ? ", `$group`" : '' );
		$after = $i === $start_at ? $start_after : 0;
		do {
			$rows = $wpdb->get_results( "SELECT $sel FROM `$table` WHERE ($where) AND `$pk` > $after ORDER BY `$pk` LIMIT 300", ARRAY_A ); // phpcs:ignore
			foreach ( $rows as $row ) {
				$after = (int) $row[ $pk ];
				$g     = $group ? (string) $row[ $group ] : '';
				if ( 'options' === $short && ( in_array( $g, $skip_options, true ) || preg_match( '/^_(site_)?transient_/', $g ) ) ) {
					$skipped[] = $g;
					continue;
				}
				$set = array();
				$hit = 0;
				foreach ( $cols as $c ) {
					$n = 0;
					$v = tng_cutover_replace( $row[ $c ], $from, $to, $n );
					if ( $n ) {
						$set[ $c ] = $v;
						$hit      += $n;
					}
				}
				if ( ! $hit ) {
					continue;
				}
				$label = $short . ( '' !== $g ? ':' . $g : '' );
				if ( ! isset( $report[ $label ] ) ) {
					$report[ $label ] = array( 'rows' => 0, 'matches' => 0 );
				}
				$report[ $label ]['rows']++;
				$report[ $label ]['matches'] += $hit;
				if ( ! $ctx['dry'] ) {
					$wpdb->update( $table, $set, array( $pk => $row[ $pk ] ) );
					++$rows_done;
				}
			}
			if ( ! $ctx['dry'] && count( $rows ) === 300 && microtime( true ) - $ctx['start'] > TNG_IMPORT_BUDGET ) {
				$cursor = array( 'table' => $i, 'after' => $after ); // resume here
				break 2;
			}
		} while ( count( $rows ) === 300 );
	}
	$more = null !== $cursor;
	ksort( $report );
	$guids = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE guid LIKE %s", $like ) );
	if ( ! $ctx['dry'] && ! $more ) {
		wp_cache_flush();
	}
	return array(
		'from'    => $from,
		'to'      => $to,
		'report'  => $report,
		'total'   => array( 'rows' => array_sum( wp_list_pluck( $report, 'rows' ) ), 'matches' => array_sum( wp_list_pluck( $report, 'matches' ) ) ),
		'not_changed' => array( 'options' => array_values( array_unique( $skipped ) ), 'post_guids' => $guids ),
		'written' => $rows_done,
		'more'    => $more,
		'cursor'  => $more ? $cursor : null,
		'results' => array(),
	);
}

/* ---------------- status, rollback, demo retirement ---------------- */

function tng_import_status() {
	global $wpdb;
	$posts = $wpdb->get_results( "SELECT p.post_type AS type, m.meta_value AS run, p.post_status AS status, COUNT(*) AS n FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_tng_run' GROUP BY p.post_type, m.meta_value, p.post_status", ARRAY_A );
	$terms = $wpdb->get_results( "SELECT tt.taxonomy, tm.meta_value AS run, COUNT(*) AS n FROM {$wpdb->termmeta} tm JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tm.meta_key = '_tng_run' GROUP BY tt.taxonomy, tm.meta_value", ARRAY_A );
	$redirects = array();
	foreach ( array_merge( array_values( get_option( 'tng_redirects', array() ) ), get_option( 'tng_redirects_regex', array() ) ) as $r ) {
		$run               = isset( $r['run'] ) ? $r['run'] : $r[2];
		$redirects[ $run ] = ( isset( $redirects[ $run ] ) ? $redirects[ $run ] : 0 ) + 1;
	}
	return array(
		'posts'        => $posts,
		'terms'        => $terms,
		'redirects'    => $redirects,
		'demo_retired' => get_option( 'tng_demo_retired' ),
		'demo_left'    => count( tng_import_demo_candidates() ),
		'locked'       => (bool) get_option( 'tng_import_lock' ),
		// Editability checks (1.3.15): stored banner labels and languages per post type.
		'labels'       => $wpdb->get_results( "SELECT p.post_type AS type, IFNULL(t.meta_value, '') AS template, m.meta_value AS label, COUNT(*) AS n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_wp_page_template' WHERE m.meta_key = 'download_label' GROUP BY p.post_type, template, m.meta_value", ARRAY_A ),
		'langs'        => $wpdb->get_results( "SELECT p.post_type AS type, m.meta_value AS lang, COUNT(*) AS n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_tng_lang' GROUP BY p.post_type, m.meta_value", ARRAY_A ),
		'reports'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'module_report' AND meta_value <> ''" ),
	);
}

/**
 * Delete what one run created, dry run by default, at most 50 deletions per
 * request ("more": true = call again). Order: posts, term fields, terms,
 * attachments, redirects. Posts edited since import are kept.
 */
function tng_import_rollback( &$ctx ) {
	global $wpdb;
	$run     = $ctx['run'];
	$dry     = $ctx['dry'];
	$results = array();
	$done    = 0;
	$more    = false;
	$budget  = static function () use ( &$done, $ctx ) {
		return $done < 50 && microtime( true ) - $ctx['start'] < TNG_IMPORT_BUDGET;
	};

	// 1. posts
	$posts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, p.post_type, p.post_modified_gmt, mm.meta_value AS mod_at FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_tng_run' AND m.meta_value = %s LEFT JOIN {$wpdb->postmeta} mm ON mm.post_id = p.ID AND mm.meta_key = '_tng_mod' WHERE p.post_type IN ('professional', 'insight', 'award', 'job', 'page') ORDER BY p.ID",
			$run
		)
	);
	$gone = array(); // posts this rollback removes (or would)
	foreach ( $posts as $p ) {
		if ( $p->mod_at !== $p->post_modified_gmt ) {
			$results[] = array( 'kind' => $p->post_type, 'id' => (int) $p->ID, 'status' => 'kept', 'notes' => array( 'edited since import' ) );
			continue;
		}
		$gone[] = (int) $p->ID;
		if ( $dry ) {
			$results[] = array( 'kind' => $p->post_type, 'id' => (int) $p->ID, 'status' => 'would_delete' );
		} elseif ( $budget() ) {
			wp_delete_post( $p->ID, true );
			$done++;
			$results[] = array( 'kind' => $p->post_type, 'id' => (int) $p->ID, 'status' => 'deleted' );
		} else {
			$more = true;
		}
	}

	// 2. term fields filled by the run, cleared only where still exactly the imported value
	if ( ! $more ) {
		$rows = $wpdb->get_results( "SELECT meta_id, term_id FROM {$wpdb->termmeta} WHERE meta_key = '_tng_filled'" );
		foreach ( $rows as $row ) {
			$term  = get_term( (int) $row->term_id );
			$entry = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_id = %d", $row->meta_id ) ) );
			if ( ! $term || is_wp_error( $term ) || ! is_array( $entry ) || $entry['run'] !== $run ) {
				continue;
			}
			if ( ! $dry && ! $budget() ) {
				$more = true;
				break;
			}
			$cleared = array();
			$kept    = array();
			foreach ( $entry['fields'] as $name => $hash ) {
				if ( md5( maybe_serialize( tng_import_term_raw( $name, $term ) ) ) !== $hash ) {
					$kept[] = $name;
					continue;
				}
				$cleared[] = $name;
				if ( ! $dry ) {
					if ( 0 === strpos( $name, 'meta:' ) ) {
						delete_term_meta( $term->term_id, substr( $name, 5 ) );
					} else {
						delete_field( $name, tng_term_ref( $term ) );
					}
				}
			}
			if ( ! $dry ) {
				delete_metadata_by_mid( 'term', $row->meta_id );
				$done++;
			}
			$results[] = array( 'kind' => 'term_fields', 'id' => (int) $term->term_id, 'status' => $dry ? 'would_clear' : 'cleared', 'notes' => array_merge( array( 'clear:' . implode( ',', $cleared ) ), $kept ? array( 'kept (edited):' . implode( ',', $kept ) ) : array() ) );
		}
	}

	// 3. terms the run created, children first, only when nothing else uses them
	if ( ! $more ) {
		$terms = $wpdb->get_results( $wpdb->prepare( "SELECT tt.term_id, tt.taxonomy FROM {$wpdb->termmeta} tm JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tm.meta_key = '_tng_run' AND tm.meta_value = %s ORDER BY tt.term_id DESC", $run ) );
		$ours  = array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );
		foreach ( $terms as $t ) {
			$objects = array_diff( array_map( 'intval', (array) get_objects_in_term( (int) $t->term_id, $t->taxonomy ) ), $gone );
			$kids    = array_diff( array_map( 'intval', (array) get_terms( array( 'taxonomy' => $t->taxonomy, 'parent' => (int) $t->term_id, 'hide_empty' => false, 'fields' => 'ids' ) ) ), $ours );
			if ( $objects || $kids ) {
				$results[] = array( 'kind' => $t->taxonomy, 'id' => (int) $t->term_id, 'status' => 'kept', 'notes' => array( $objects ? 'in use by ' . count( $objects ) . ' objects' : 'has other child terms' ) );
				continue;
			}
			if ( $dry ) {
				$results[] = array( 'kind' => $t->taxonomy, 'id' => (int) $t->term_id, 'status' => 'would_delete' );
			} elseif ( $budget() ) {
				wp_delete_term( (int) $t->term_id, $t->taxonomy );
				$done++;
				$results[] = array( 'kind' => $t->taxonomy, 'id' => (int) $t->term_id, 'status' => 'deleted' );
			} else {
				$more = true;
			}
		}
	}

	// 4. attachments, unless something outside the deleted posts still uses them as an image field
	if ( ! $more ) {
		$atts      = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_tng_run' AND m.meta_value = %s WHERE p.post_type = 'attachment' ORDER BY p.ID", $run ) );
		$post_keys = "'_thumbnail_id','portrait','headshot','module_background','module_report','panels_background','download_image'";
		$term_keys = "'flag','image','hero_image','card_image'";
		$not_gone  = $gone ? ' AND post_id NOT IN (' . implode( ',', $gone ) . ')' : '';
		foreach ( $atts as $aid ) {
			$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ($post_keys) AND meta_value = %s$not_gone", $aid ) ) // phpcs:ignore
				+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key IN ($term_keys) AND meta_value = %s", $aid ) ); // phpcs:ignore
			if ( $used ) {
				$results[] = array( 'kind' => 'attachment', 'id' => (int) $aid, 'status' => 'kept', 'notes' => array( 'used by ' . $used . ' image fields' ) );
				continue;
			}
			if ( $dry ) {
				$results[] = array( 'kind' => 'attachment', 'id' => (int) $aid, 'status' => 'would_delete', 'notes' => array( 'inline use in post bodies is not checked' ) );
			} elseif ( $budget() ) {
				wp_delete_attachment( (int) $aid, true );
				$done++;
				$results[] = array( 'kind' => 'attachment', 'id' => (int) $aid, 'status' => 'deleted' );
			} else {
				$more = true;
			}
		}
	}

	// 5. redirects tagged with the run
	if ( ! $more ) {
		$map   = get_option( 'tng_redirects', array() );
		$regex = get_option( 'tng_redirects_regex', array() );
		$keep  = array_filter( $map, static function ( $r ) use ( $run ) { return $r[2] !== $run; } );
		$keepr = array_values( array_filter( $regex, static function ( $r ) use ( $run ) { return $r['run'] !== $run; } ) );
		$n     = count( $map ) - count( $keep ) + count( $regex ) - count( $keepr );
		if ( $n ) {
			$results[] = array( 'kind' => 'redirects', 'id' => 0, 'status' => $dry ? 'would_delete' : 'deleted', 'notes' => array( $n . ' rules' ) );
			if ( ! $dry ) {
				update_option( 'tng_redirects', $keep, false );
				update_option( 'tng_redirects_regex', $keepr, false );
			}
		}
	}

	if ( ! $dry ) {
		tng_bust_roster();
	}
	return array( 'results' => $results, 'more' => $more );
}

/** Demo content from inc/seed.php: type => exact titles. */
function tng_import_demo_titles() {
	return array(
		'professional' => array( 'Aimee Van Dyk', 'Renee Van Dyk', 'Alan Adcock', 'Alisa Koovimol', 'Alongkorn Tongmee', 'Ampolphan Sriduangchan', 'Athistha (Nop) Chitranukroh', 'Thammapas Chanpanich', 'Rada Lamsam', 'Karnravee Jitvilai', 'Jay Cohen', 'David Mol' ),
		'insight'      => array(
			'Thailand Releases New Draft Artificial Intelligence Act',
			'Global Disputes Forecast',
			'Vietnam Takes Major Step in AI Governance with New Guiding Decree',
			'Practical Law: Insurance and Reinsurance Global Guide 2026 – Vietnam',
			'Indonesia Expands Rules on Drug Promotion and Advertising',
			'Thai Cabinet Advances Draft Amendments to Liberalize Nine Foreign Business Activities',
			'US-Thailand Trade Meetings Increase Focus on Trade Enforcement',
			'Thailand\'s New Labeling Requirements for Traditional and Electric Vehicles',
			'Tilleke & Gibbins Highly Ranked in Benchmark Litigation Asia-Pacific 2026',
			'Tilleke & Gibbins Named Laos Firm of the Year at the Legal 500 Southeast Asia Awards 2026',
			'New Investment Incentives for Thai-Foreign Auto Parts Joint Ventures',
			'Thailand Legal Basics',
			'Marketing Authorization for Pharmaceuticals in Thailand',
			'Thomson Reuters Country Update – Thailand: Insurance 2026',
			'Cambodia Updates Trademark Registration Procedures',
			'Doing Business in Cambodia 2026',
			'Protecting Automotive Designs and Trademarks in Southeast Asia',
		),
		'award'        => array(
			'Tilleke & Gibbins Named Regional Litigation Law Firm of the Year at the 2026 ALB SE Asia Law Awards',
			'Tilleke & Gibbins Highly Ranked in Benchmark Litigation Asia-Pacific 2026',
			'Thomson Reuters Country Update – Thailand: Insurance 2026',
			'Tilleke & Gibbins Named Cambodia Law Firm of the Year 2026',
			'Tilleke & Gibbins Recognized in IP Stars 2026',
		),
		'job'          => array_merge(
			array( 'Assistant, Trademark Executive', 'Associate (Cross-Border Transactions)', 'Associate (Dispute Resolution)', 'Senior Associate (Intellectual Property)' ),
			...array_map(
				static function ( $country ) {
					return array_map(
						static function ( $t ) use ( $country ) {
							return "$t ($country)";
						},
						array( 'Associate — Corporate/M&A', 'Senior Associate — Dispute Resolution', 'Legal Consultant', 'IP Paralegal', 'Office Administrator' )
					);
				},
				array( 'Cambodia', 'Indonesia', 'Thailand', 'Vietnam' )
			)
		),
	);
}

/**
 * Demo candidates: type + exact seeded title (the seeder's own list), not
 * imported (no _tng_src), not trashed. $types limits the post types.
 * "touched" is informational only (the 1.0.x seed run itself left
 * post_modified != post_date on most of them); it no longer blocks retirement.
 */
function tng_import_demo_candidates( $types = array() ) {
	global $wpdb;
	$out = array();
	foreach ( tng_import_demo_titles() as $type => $titles ) {
		if ( $types && ! in_array( $type, $types, true ) ) {
			continue;
		}
		$in   = implode( ',', array_fill( 0, count( $titles ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date_gmt, p.post_modified_gmt FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_tng_src' WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit') AND m.meta_id IS NULL AND p.post_title IN ($in)", // phpcs:ignore
				array_merge( array( $type ), $titles )
			)
		);
		foreach ( $rows as $r ) {
			$touched = array();
			if ( wp_get_post_revisions( $r->ID, array( 'fields' => 'ids' ) ) ) {
				$touched[] = 'has revisions';
			}
			if ( get_post_thumbnail_id( $r->ID ) ) {
				$touched[] = 'has a featured image';
			}
			if ( $r->post_modified_gmt !== $r->post_date_gmt ) {
				$touched[] = 'modified ' . $r->post_modified_gmt;
			}
			$out[] = array( 'kind' => $type, 'id' => (int) $r->ID, 'title' => $r->post_title, 'touched' => $touched );
		}
	}
	return $out;
}

/**
 * Trash the seeded demo posts (dry run by default), optionally only some
 * post types (body "types": ["professional", "job"]), and stop TNG Seed from
 * recreating those types (tng_demo_retired = list of retired types).
 * Trash, never delete: 30 days to undo, and WP renames trashed slugs
 * (__trashed) so the real posts can take them. "touched" is reported, not a
 * reason to keep (the user approved retiring the seeder's list, 2026-11-13).
 */
function tng_import_retire_demo( &$ctx ) {
	$types = array_values( array_intersect( (array) $ctx['types'], array_keys( tng_import_demo_titles() ) ) );
	if ( count( $types ) !== count( (array) $ctx['types'] ) ) { // a typo must never widen the run to every type
		return array( 'results' => array( tng_import_error( 'types must be some of: ' . implode( ', ', array_keys( tng_import_demo_titles() ) ) ) ) );
	}
	$results = array();
	foreach ( tng_import_demo_candidates( $types ) as $c ) {
		$notes = $c['touched'];
		unset( $c['touched'] );
		if ( $ctx['dry'] ) {
			$results[] = $c + array( 'status' => 'would_trash', 'notes' => $notes );
		} else {
			$results[] = $c + array( 'status' => wp_trash_post( $c['id'] ) ? 'trashed' : 'error', 'notes' => $notes );
		}
	}
	if ( ! $ctx['dry'] ) {
		$retired = get_option( 'tng_demo_retired' );
		$retired = is_array( $retired ) ? $retired : ( $retired ? array_keys( tng_import_demo_titles() ) : array() );
		update_option( 'tng_demo_retired', array_values( array_unique( array_merge( $retired, $types ? $types : array_keys( tng_import_demo_titles() ) ) ) ) );
		tng_bust_roster();
	}
	return array( 'results' => $results );
}
