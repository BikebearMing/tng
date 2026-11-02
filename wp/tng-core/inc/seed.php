<?php
/**
 * One-click content seeder (Tools → TNG Seed). Idempotent: everything is
 * looked up by title/slug before being created, so it can run again safely.
 *
 * Seeds: settings (permalinks, front page), taxonomy terms (incl. office
 * details on location city terms), sample professionals / insights / awards /
 * jobs (the exact content of the static HTML pages), the four pages built
 * from ACF blocks, and the header/footer options.
 *
 * Field values are written by FIELD KEY — the keys come from
 * wp/build-scf-json.mjs in the repo; keep them in sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_management_page( 'TNG Seed', 'TNG Seed', 'manage_options', 'tng-seed', 'tng_seed_page' );
	}
);

function tng_seed_page() {
	$log = array();
	if ( isset( $_POST['tng_seed'] ) && check_admin_referer( 'tng_seed' ) ) {
		$log = tng_seed_all();
	}
	?>
	<div class="wrap">
		<h1>TNG Seed</h1>
		<p>Creates the demo content: terms (with office details), professionals, insights, awards, jobs, the four pages, options values, permalinks and the front page. Safe to run more than once.</p>
		<form method="post">
			<?php wp_nonce_field( 'tng_seed' ); ?>
			<p><button class="button button-primary" name="tng_seed" value="1">Seed content</button></p>
		</form>
		<?php if ( $log ) : ?>
		<h2>Result</h2>
		<ul style="font-family:monospace">
			<?php foreach ( $log as $line ) : ?>
			<li><?php echo esc_html( $line ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
	<?php
}

/* ---------------- low-level helpers ---------------- */

function tng_seed_keep_publish( $data ) {
	if ( 'future' === $data['post_status'] ) {
		$data['post_status'] = 'publish'; // demo dates are in the future on purpose
	}
	return $data;
}

function tng_seed_term( $tax, $name, $parent = 0 ) {
	$existing = term_exists( $name, $tax, $parent ?: null );
	if ( $existing ) {
		return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
	}
	$res = wp_insert_term( $name, $tax, $parent ? array( 'parent' => $parent ) : array() );
	return is_wp_error( $res ) ? 0 : (int) $res['term_id'];
}

function tng_seed_find_post( $type, $title ) {
	$q = new WP_Query(
		array(
			'post_type'      => $type,
			'title'          => $title,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);
	return $q->posts ? (int) $q->posts[0] : 0;
}

function tng_seed_post( $type, $title, $args = array() ) {
	// STRICTLY create-only: an existing post is never touched, whatever it
	// holds now belongs to the editors (block image fields live in page
	// content, so any update here would wipe their selections — it did once).
	$id = tng_seed_find_post( $type, $title );
	if ( $id ) {
		return array( $id, false );
	}
	// wp_insert_post expects slashed input and strips one level of
	// backslashes — without wp_slash, the < escapes inside serialized
	// block attributes lose their backslash and render as literal "u003c".
	$id = wp_insert_post(
		wp_slash(
			array_merge(
				array(
					'post_type'   => $type,
					'post_title'  => $title,
					'post_status' => 'publish',
				),
				$args
			)
		)
	);
	return array( (int) $id, true );
}

/** serialize an ACF block with flat (meta-style) data + field key references */
function tng_seed_block( $name, $data = array() ) {
	$attrs = array( 'name' => $name, 'mode' => 'edit' );
	if ( $data ) {
		$attrs['data'] = $data;
	}
	return serialize_block(
		array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	) . "\n\n";
}

/** flat repeater rows: name => count, name_0_sub => value (+ _key refs) */
function tng_seed_flat( $prefix, $name, $rows, $subs ) {
	$data           = array();
	$data[ $name ]  = count( $rows );
	$data[ "_$name" ] = "field_tng_{$prefix}_{$name}";
	foreach ( $rows as $i => $row ) {
		foreach ( $subs as $sub ) {
			if ( array_key_exists( $sub, $row ) ) {
				$data[ "{$name}_{$i}_{$sub}" ]  = $row[ $sub ];
				$data[ "_{$name}_{$i}_{$sub}" ] = "field_tng_{$prefix}_{$name}_{$sub}";
			}
		}
	}
	return $data;
}

/** simple field entries for block data: name => value + _name => key */
function tng_seed_data( $prefix, $fields ) {
	$data = array();
	foreach ( $fields as $name => $value ) {
		$data[ $name ]    = $value;
		$data[ "_$name" ] = "field_tng_{$prefix}_{$name}";
	}
	return $data;
}

function tng_seed_paragraphs( $paras ) {
	$out = '';
	foreach ( $paras as $p ) {
		$out .= "<!-- wp:paragraph -->\n<p>$p</p>\n<!-- /wp:paragraph -->\n\n";
	}
	return $out;
}

function tng_seed_h2( $text ) {
	return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$text</h2>\n<!-- /wp:heading -->\n\n";
}

function tng_seed_list( $items ) {
	$lis = '';
	foreach ( $items as $li ) {
		$lis .= "<!-- wp:list-item -->\n<li>$li</li>\n<!-- /wp:list-item -->\n\n";
	}
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">$lis</ul>\n<!-- /wp:list -->\n\n";
}

/* ---------------- the seed ---------------- */

function tng_seed_all() {
	$log = array();
	add_filter( 'wp_insert_post_data', 'tng_seed_keep_publish' );

	/* ---- settings ---- */
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
		$log[] = 'Permalinks set to /%postname%/';
	}
	update_option( 'timezone_string', get_option( 'timezone_string' ) ?: 'Asia/Bangkok' );

	/* ---- terms ---- */
	$industries = array( 'Automotive', 'Aviation', 'Consumer Products', 'Energy', 'Fintech', 'Insurance', 'Life Sciences', 'Technology' );
	$practices  = array( 'Antitrust and Competition', 'Banking and Finance', 'Capital Markets', 'Compliance and Investigations', 'Corporate/M&A', 'Customs and International Trade', 'Dispute Resolution and Litigation', 'Employment', 'Intellectual Property', 'Real Estate', 'Regulatory Affairs', 'Tax' );
	$positions  = array( 'Partner', 'Consultant', 'Senior Associate', 'Associate', 'Patent Agent' );
	$languages  = array( 'English', 'Thai', 'Vietnamese', 'Lao', 'Burmese', 'Khmer', 'Indonesian' );
	$categories = array( 'Legal Updates', 'Publications' );

	$ind = $pra = $pos = $lan = array();
	foreach ( $industries as $n ) { $ind[ $n ] = tng_seed_term( 'industry', $n ); }
	foreach ( $practices as $n ) { $pra[ $n ] = tng_seed_term( 'practice', $n ); }
	foreach ( $positions as $n ) { $pos[ $n ] = tng_seed_term( 'position', $n ); }
	foreach ( $languages as $n ) { $lan[ $n ] = tng_seed_term( 'language', $n ); }
	foreach ( $categories as $n ) { tng_seed_term( 'insight_category', $n ); }

	$countries = array( 'Cambodia', 'Indonesia', 'Laos', 'Myanmar', 'Thailand', 'Vietnam' );
	$loc       = array();
	foreach ( $countries as $n ) { $loc[ $n ] = tng_seed_term( 'location', $n ); }

	// Offices = city terms under their country. Bangkok's details are the
	// mock's; the other five repeat them (TODO: real office details).
	$bangkok_addr = "Supalai Grand Tower,\n26th Floor, 1011 Rama 3\nRoad, Chongnonsi,\nYannawa, Bangkok 10120";
	$offices      = array(
		array( 'Bangkok', 'Thailand', 'Supalai Grand Tower, 1011 Rama 3 Road, Bangkok' ),
		array( 'Hanoi', 'Vietnam', 'Tilleke & Gibbins, Hanoi' ),
		array( 'Ho Chi Minh City', 'Vietnam', 'Tilleke & Gibbins, Ho Chi Minh City' ),
		array( 'Phnom Penh', 'Cambodia', 'Tilleke & Gibbins, Phnom Penh' ),
		array( 'Vientiane', 'Laos', 'Tilleke & Gibbins, Vientiane' ),
		array( 'Yangon', 'Myanmar', 'Tilleke & Gibbins, Yangon' ),
	);
	foreach ( $offices as $o ) {
		$tid = tng_seed_term( 'location', $o[0], $loc[ $o[1] ] );
		$ref = 'location_' . $tid;
		// only fill offices that were never configured — edits are the user's
		if ( get_field( 'office_name', $ref ) ) {
			continue;
		}
		update_field( 'field_tng_loc_office_name', 'Tilleke & Gibbins International Ltd.', $ref );
		update_field( 'field_tng_loc_phone', '+855 23 964 210', $ref );
		update_field( 'field_tng_loc_email', 'cambodia@tilleke.com', $ref );
		update_field( 'field_tng_loc_address', $bangkok_addr, $ref );
		update_field( 'field_tng_loc_map_query', $o[2], $ref );
	}
	$log[] = 'Terms seeded (industries, practices, positions, languages, categories, locations + offices)';

	/* ---- professionals ---- */
	$pro_defs = array(
		'Aimee Van Dyk'            => array( 'Consultant', array( 'Laos', 'Myanmar', 'Thailand' ), array( 'Consumer Products', 'Fintech', 'Life Sciences' ), array( 'Banking and Finance', 'Corporate/M&A', 'Dispute Resolution and Litigation' ), array( 'English' ), 'aimee.v@tilleke.com' ),
		'Renee Van Dyk'            => array( 'Consultant', array( 'Thailand' ), array( 'Insurance' ), array( 'Dispute Resolution and Litigation' ), array( 'English' ), 'renee.v@tilleke.com' ),
		'Alan Adcock'              => array( "Partner and Director,\nIntellectual Property", array( 'Thailand' ), array( 'Technology', 'Life Sciences' ), array( 'Intellectual Property' ), array( 'English', 'Thai' ), 'alan.a@tilleke.com' ),
		'Alisa Koovimol'           => array( 'Consultant and Patent Agent', array( 'Thailand' ), array( 'Technology' ), array( 'Intellectual Property' ), array( 'Thai', 'English' ), 'alisa.k@tilleke.com' ),
		'Alongkorn Tongmee'        => array( 'Consultant', array( 'Thailand' ), array( 'Energy' ), array( 'Regulatory Affairs' ), array( 'Thai' ), 'alongkorn.t@tilleke.com' ),
		'Ampolphan Sriduangchan'   => array( 'Senior Associate & Patent Agent', array( 'Thailand' ), array( 'Life Sciences' ), array( 'Intellectual Property' ), array( 'Thai', 'English' ), 'ampolphan.s@tilleke.com' ),
		'Athistha (Nop) Chitranukroh' => array( 'Partner', array( 'Thailand', 'Vietnam' ), array( 'Technology', 'Fintech' ), array( 'Corporate/M&A', 'Banking and Finance' ), array( 'Thai', 'English' ), 'nop.c@tilleke.com' ),
		'Thammapas Chanpanich'     => array( 'Associate', array( 'Thailand' ), array( 'Technology' ), array( 'Dispute Resolution and Litigation' ), array( 'Thai' ), 'thammapas.c@tilleke.com' ),
		'Rada Lamsam'              => array( 'Senior Associate', array( 'Thailand' ), array( 'Consumer Products' ), array( 'Corporate/M&A' ), array( 'Thai', 'English' ), 'rada.l@tilleke.com' ),
		'Karnravee Jitvilai'       => array( 'Associate', array( 'Thailand' ), array( 'Insurance' ), array( 'Regulatory Affairs' ), array( 'Thai' ), 'karnravee.j@tilleke.com' ),
	);

	$pro_ids = array();
	$created = 0;
	foreach ( $pro_defs as $name => $def ) {
		list( $id, $new )  = tng_seed_post( 'professional', $name );
		$pro_ids[ $name ]  = $id;
		$created          += $new ? 1 : 0;
		if ( ! $new ) {
			continue; // existing professionals belong to the editors
		}
		list( $position, $locations, $inds, $pras, $langs, $email ) = $def;
		update_field( 'field_tng_pro_position_title', str_replace( "\n", "\r\n", $position ), $id );
		update_field( 'field_tng_pro_phone', '+66 2056 5600', $id );
		update_field( 'field_tng_pro_email', $email, $id );
		wp_set_object_terms( $id, array_map( function ( $n ) use ( $loc ) { return $loc[ $n ]; }, $locations ), 'location' );
		wp_set_object_terms( $id, array_map( function ( $n ) use ( $ind ) { return $ind[ $n ]; }, $inds ), 'industry' );
		wp_set_object_terms( $id, array_map( function ( $n ) use ( $pra ) { return $pra[ $n ]; }, $pras ), 'practice' );
		wp_set_object_terms( $id, array_map( function ( $n ) use ( $lan ) { return $lan[ $n ]; }, $langs ), 'language' );
		$first_word = preg_split( '/[\s,]+/', $position )[0];
		wp_set_object_terms( $id, in_array( $first_word, array( 'Partner', 'Consultant', 'Senior', 'Associate' ), true ) ? ( 'Senior' === $first_word ? 'Senior Associate' : $first_word ) : 'Consultant', 'position' );
	}

	// Aimee's full profile (from professional.html) — only when never filled
	$aimee = $pro_ids['Aimee Van Dyk'];
	if ( ! get_field( 'lead', $aimee ) ) {
	update_field( 'field_tng_pro_lead', 'Aimee Van Dyk is a consultant at Tilleke & Gibbins with a regional practice spanning Laos, Myanmar, and Thailand. She advises on complex, cross-border matters at the intersection of commercial transactions, disputes, and regulatory compliance, frequently across both common- and civil-law systems.', $aimee );
	update_field(
		'field_tng_pro_biography',
		'<p>Aimee works closely with clients to understand their commercial drivers and potential risk exposure, delivering practical, context-sensitive advice in unfamiliar or evolving legal and regulatory environments. Her practice covers commercial transactions and contracts, dispute strategy and resolution, market entry and regulatory compliance, and sector-specific issues, including financial services, technology, telecommunications, digital assets, consumer goods, and energy. Her matters often involve multinational structures, regional operations, or regulatory regimes that are developing or in flux.</p><p>Before joining Tilleke & Gibbins, Aimee began her legal career in 2007 and, by 2011, was heading the general litigation, banking, and commercial department of a leading Johannesburg law firm. She later practiced as a member of the Johannesburg Bar, further developing her corporate and commercial litigation expertise.</p><p>Aimee is a regular contributor to legal and industry discussions and has been recognized with a Mondaq Thought Leadership Award. She was enrolled as an attorney of the High Court of South Africa before being admitted as an advocate of the High Court of South Africa.</p>',
		$aimee
	);
	update_field(
		'field_tng_pro_education',
		array(
			array( 'entry' => "Postgraduate Certificate in Advanced Company Law I,\r\nNelson Mandela Institute of Law/WITS University" ),
			array( 'entry' => 'LLB, University of Johannesburg' ),
		),
		$aimee
	);
	update_field(
		'field_tng_pro_experience',
		'<ul><li>Actively advising an international group with investments in real estate, telecommunications, and corporate finance on commercial and dispute strategy across multiple ongoing matters in Myanmar, including assessment of operational and litigation risk, jurisdictional considerations, and enforcement options in a volatile regulatory environment.</li><li>Advised multinational technology and automotive companies in Laos and Myanmar on data privacy and cross-border data transfer compliance, including regulatory assessments for digital platforms and connected-vehicle subscription services.</li><li>Advised a Japanese pharmaceutical and healthcare products group on a multiparty, cross-border sales and purchase agreement governing supply and distribution arrangements across Myanmar, Thailand, and Vietnam, addressing contractual risk allocation and regulatory implications.</li><li>Advised a Japanese pharmaceutical and healthcare products group on a multiparty, cross-border sales and purchase agreement governing supply and distribution arrangements across Myanmar, Thailand, and Vietnam, addressing contractual risk allocation and regulatory implications.</li><li>Represented a regional energy developer in contract disputes arising from a cross-border power supply project in Laos, including pre-arbitration negotiations and enforcement strategy.</li><li>Advised a digital asset platform on licensing requirements and market entry in Thailand, including engagement with the regulator on product structuring.</li></ul>',
		$aimee
	);
	}
	$log[] = 'Professionals seeded (' . count( $pro_ids ) . ' total, ' . $created . ' new)';

	/* ---- insights ---- */
	$mk_insight = function ( $title, $date, $args = array() ) use ( $ind, $pra, $loc, $pro_ids ) {
		list( $id, $new ) = tng_seed_post(
			'insight',
			$title,
			array(
				'post_date'    => $date,
				'post_excerpt' => isset( $args['excerpt'] ) ? $args['excerpt'] : '',
				'post_content' => isset( $args['content'] ) ? $args['content'] : '',
			)
		);
		if ( ! $new ) {
			return $id; // existing insights belong to the editors
		}
		if ( ! empty( $args['industry'] ) ) {
			wp_set_object_terms( $id, array_map( function ( $n ) use ( $ind ) { return $ind[ $n ]; }, $args['industry'] ), 'industry' );
		}
		if ( ! empty( $args['practice'] ) ) {
			wp_set_object_terms( $id, array_map( function ( $n ) use ( $pra ) { return $pra[ $n ]; }, $args['practice'] ), 'practice' );
		}
		if ( ! empty( $args['location'] ) ) {
			wp_set_object_terms( $id, array_map( function ( $n ) use ( $loc ) { return $loc[ $n ]; }, $args['location'] ), 'location' );
		}
		if ( ! empty( $args['related'] ) ) {
			update_field( 'field_tng_ins_related_professionals', array_map( function ( $n ) use ( $pro_ids ) { return $pro_ids[ $n ]; }, $args['related'] ), $id );
		}
		return $id;
	};

	$generic = tng_seed_paragraphs( array(
		'Tilleke &amp; Gibbins advises international and local businesses across Southeast Asia. This update summarizes the key developments and what they mean for companies operating in the region.',
		'For more information, please contact our team.',
	) );

	$ai_body = tng_seed_paragraphs( array( 'Thailand’s Electronic Transactions Development Agency (ETDA) released a new version of the draft Act on Artificial Intelligence on July 2, 2026, for a public hearing period expected to be approximately 30 days. The draft act adopts a risk-based regulatory approach modeled in part on international frameworks—particularly the EU’s AI Act—while incorporating provisions tailored to Thailand’s regulatory landscape and digital economy objectives. If enacted in its current form, the law would introduce extraterritorial obligations, a tiered risk classification system, strict liability for AI-related damages, and new transparency requirements for AI-generated content.' ) )
		. tng_seed_h2( 'Scope and Extraterritorial Application' )
		. tng_seed_paragraphs( array( 'The draft act applies to AI development, deployment, or any other action affecting people in Thailand, even if the action occurs outside the country. Of note:' ) )
		. tng_seed_list( array(
			'This extraterritorial reach creates compliance obligations for global AI companies whose systems impact Thai residents or consumers, even if the provider has no physical presence in Thailand.',
			'Foreign AI providers serving Thai deployers or users must appoint a local coordinator or authorized representative. Depending on the type of AI system, the representative may need full authority to act on behalf of the provider without any limitation of liability.',
			'Certain activities are exempt from the draft act’s oversight, including AI used by natural persons solely for personal or household activities, AI for educational research conducted by higher education institutions with ethics committee approval, research and development activities conducted prior to distribution or service provision, and other AI systems prescribed by royal decree.',
		) )
		. tng_seed_h2( 'Risk-Based Classification Framework' )
		. tng_seed_paragraphs( array( 'The draft act establishes a tiered risk classification system with three main categories:' ) )
		. tng_seed_list( array(
			'<strong>Prohibited AI.</strong> The act outright prohibits AI systems employing cognitive-behavioral manipulation using subliminal techniques, AI systems causing unfair broad-scale discrimination from processing irrelevant data, and other categories of serious risk as determined by announcement of a forthcoming committee that will be responsible for national AI planning.',
			'<strong>High-risk AI.</strong> High-risk AI systems are those designated by royal decree as affecting national security, health, environment, energy, telecommunications, transport, or public utilities.',
			'<strong>Limited-risk AI.</strong> AI systems that interact directly with people or generate content must disclose that users are dealing with AI, and AI-generated content must be labeled as such.',
		) )
		. tng_seed_h2( 'Liability and Enforcement' )
		. tng_seed_paragraphs( array(
			'The draft act imposes strict liability on providers and deployers of high-risk AI for damage caused by their systems, unless they can prove the damage resulted from force majeure or the injured party’s own actions. Regulators may order the suspension of non-compliant systems and impose administrative fines.',
			'Businesses developing or deploying AI in Thailand should review their systems against the proposed risk categories and consider submitting comments during the public hearing period.',
		) );

	$in_ai       = $mk_insight( 'Thailand Releases New Draft Artificial Intelligence Act', '2026-07-02 10:00:00', array(
		'content'  => $ai_body,
		'excerpt'  => 'Thailand’s ETDA has released a new draft Act on Artificial Intelligence for public hearing, adopting a risk-based regulatory approach with extraterritorial reach.',
		'industry' => array( 'Technology' ),
		'location' => array( 'Thailand' ),
		'related'  => array( 'Athistha (Nop) Chitranukroh', 'Thammapas Chanpanich', 'Rada Lamsam', 'Karnravee Jitvilai' ),
	) );
	$in_forecast = $mk_insight( 'Global Disputes Forecast', '2026-07-02 09:00:00', array(
		'content'  => $generic,
		'excerpt'  => 'Geopolitical pressures, expanding disputes risk and cross-border complexities: are organizations ready for the year ahead?',
		'industry' => array( 'Fintech' ),
		'practice' => array( 'Dispute Resolution and Litigation' ),
		'related'  => array( 'Alan Adcock', 'Alisa Koovimol', 'Alongkorn Tongmee', 'Ampolphan Sriduangchan' ),
	) );
	$in_featured = $mk_insight( 'Vietnam Takes Major Step in AI Governance with New Guiding Decree', '2026-09-20 10:00:00', array(
		'content'  => $generic,
		'excerpt'  => 'Vietnam has issued a new guiding decree on AI governance, a major step in the country’s digital regulatory framework.',
		'industry' => array( 'Technology' ),
		'location' => array( 'Vietnam' ),
	) );
	$in_recent = array(
		$mk_insight( 'Practical Law: Insurance and Reinsurance Global Guide 2026 – Vietnam', '2026-12-02 12:00:00', array( 'content' => $generic, 'industry' => array( 'Insurance' ), 'location' => array( 'Vietnam' ) ) ),
		$mk_insight( 'Indonesia Expands Rules on Drug Promotion and Advertising', '2026-12-02 11:00:00', array( 'content' => $generic, 'industry' => array( 'Life Sciences' ), 'location' => array( 'Indonesia' ) ) ),
		$mk_insight( 'Thai Cabinet Advances Draft Amendments to Liberalize Nine Foreign Business Activities', '2026-12-02 10:00:00', array( 'content' => $generic, 'location' => array( 'Thailand' ) ) ),
		$mk_insight( 'US-Thailand Trade Meetings Increase Focus on Trade Enforcement', '2026-12-02 09:00:00', array( 'content' => $generic, 'practice' => array( 'Customs and International Trade' ), 'location' => array( 'Thailand' ) ) ),
	);
	$mk_insight( 'Thailand\'s New Labeling Requirements for Traditional and Electric Vehicles', '2026-12-02 13:00:00', array(
		'content'  => $generic,
		'excerpt'  => 'Obtaining marketing authorization is a fundamental requirement for bringing pharmaceutical products to the Thai market.',
		'industry' => array( 'Automotive' ),
		'location' => array( 'Thailand' ),
	) );
	$mk_insight( 'Tilleke & Gibbins Highly Ranked in Benchmark Litigation Asia-Pacific 2026', '2026-12-02 08:00:00', array( 'content' => $generic, 'industry' => array( 'Automotive' ) ) );
	$mk_insight( 'Tilleke & Gibbins Named Laos Firm of the Year at the Legal 500 Southeast Asia Awards 2026', '2026-12-02 07:00:00', array( 'content' => $generic, 'industry' => array( 'Automotive' ), 'location' => array( 'Laos' ) ) );
	$mk_insight( 'New Investment Incentives for Thai-Foreign Auto Parts Joint Ventures', '2026-12-01 10:00:00', array( 'content' => $generic, 'industry' => array( 'Automotive' ), 'location' => array( 'Thailand' ) ) );
	$mk_insight( 'Thailand Legal Basics', '2026-12-02 06:00:00', array(
		'content' => $generic,
		'excerpt' => 'Thailand Legal Basics, a valuable primer for foreign investors, explores all aspects of living and doing business in Thailand.',
		'location' => array( 'Thailand' ),
		'related' => array( 'Aimee Van Dyk' ),
	) );
	$mk_insight( 'Marketing Authorization for Pharmaceuticals in Thailand', '2026-07-01 10:00:00', array(
		'content' => $generic,
		'excerpt' => 'Obtaining marketing authorization is a fundamental requirement for bringing pharmaceutical products to the Thai market.',
		'industry' => array( 'Life Sciences' ),
		'location' => array( 'Thailand' ),
		'related' => array( 'Aimee Van Dyk' ),
	) );
	$mk_insight( 'Thomson Reuters Country Update – Thailand: Insurance 2026', '2026-07-30 10:00:00', array(
		'content' => $generic,
		'excerpt' => 'Tilleke & Gibbins’ insurance specialists in Bangkok provided Thomson Reuters’ latest country update on Thailand’s regulatory framework for the insurance industry.',
		'industry' => array( 'Insurance' ),
		'location' => array( 'Thailand' ),
		'related' => array( 'Aimee Van Dyk' ),
	) );

	// Module template data for the Global Disputes Forecast — first run only
	if ( ! get_post_meta( $in_forecast, '_wp_page_template', true ) ) {
	update_post_meta( $in_forecast, '_wp_page_template', 'single-insight-module' );
	update_field( 'field_tng_mod_module_lead', 'Geopolitical pressures, expanding disputes risk and cross-border complexities: Are organizations ready for the year ahead?', $in_forecast );
	update_field(
		'field_tng_mod_panels',
		array(
			array(
				'heading'    => 'Executive summary',
				'body'       => "<p>Geopolitics and trade policy are defining the disputes landscape in the year ahead. 79% of respondents identified tariffs, sanctions and export controls as external market factors increasing their organization's disputes exposure over the next 12 months.</p><p>This geopolitical reality is reshaping investigation concerns too: a staggering 82% of respondents fear being subject to a cross-border or multi-agency investigation in 2026, and more than half rank sanctions among their top investigation threats.</p><p>But alongside geopolitical risks, persistent threats remain. Respondents tell us cybersecurity, tax, employment and environmental, social and governance (ESG) concerns are also front of mind.</p><p>Managing these competing pressures is a delicate balancing act, and one that's hampered by constraints on resources. A notable 38% of respondents say their 2026 disputes budget is insufficient to meet current disputes risk, with limitations to funding and resourcing emerging as the top barrier to litigation preparedness.</p><p>To bridge the gap between disputes awareness and readiness, organizations need to evolve their risk frameworks and advocate for investment in disputes preparedness.</p>",
				'theme'      => 'light',
				'link_label' => '',
				'link_url'   => '',
			),
			array(
				'heading'    => 'About the survey',
				'body'       => '<p>The annual Global Disputes Forecast surveys 600 senior decision-makers with responsibility for, or who have a key role in, litigation at large organizations (annual revenue greater than USD 500 million) to understand the disputes landscape in the year ahead.</p>',
				'theme'      => 'dark',
				'link_label' => 'Download report',
				'link_url'   => '#',
			),
		),
		$in_forecast
	);
	update_field(
		'field_tng_mod_findings',
		array(
			array( 'title' => 'Technology, geopolitics and supply chain disruption drive external disputes risk', 'text' => 'As organizations face an external risk environment that is increasingly complex and uncertain, technology and data risks, geopolitics and trade policy, and operational and supply chain disruption are the top three external market factors increasing exposure to disputes' ),
			array( 'title' => 'Building resilience across multiple fronts becomes critical', 'text' => 'While cybersecurity and tax rank top of the types of disputes and investigations organizations expect to face in 2026, their risk profiles are also expanding across multiple categories, including trade/sanctions and ESG.' ),
			array( 'title' => "Resource constraints expose vulnerabilities in organizations' risk-readiness", 'text' => "38% of organizations report that their 2026 disputes budget is insufficient to meet current risks. Funding and resource constraints have also emerged as organizations' greatest barrier to litigation preparedness." ),
			array( 'title' => 'Technology, geopolitics and supply chain disruption drive external disputes risk', 'text' => 'As organizations face an external risk environment that is increasingly complex and uncertain, technology and data risks, geopolitics and trade policy, and operational and supply chain disruption are the top three external market factors increasing exposure to disputes' ),
		),
		$in_forecast
	);
	}
	$log[] = 'Insights seeded (incl. AI Act article + Global Disputes Forecast module)';

	/* ---- awards ---- */
	$award_defs = array(
		array( 'Tilleke & Gibbins Named Regional Litigation Law Firm of the Year at the 2026 ALB SE Asia Law Awards', '2026-12-02 10:00:00', 'Thailand Legal Basics, a valuable primer for foreign investors, explores all aspects of living and doing business in Thailand.' ),
		array( 'Tilleke & Gibbins Highly Ranked in Benchmark Litigation Asia-Pacific 2026', '2026-07-01 10:00:00', 'Obtaining marketing authorization is a fundamental requirement for bringing pharmaceutical products to the Thai market.' ),
		array( 'Thomson Reuters Country Update – Thailand: Insurance 2026', '2026-07-30 10:00:00', 'Tilleke & Gibbins’ insurance specialists in Bangkok provided Thomson Reuters’ latest country update on Thailand’s regulatory framework for the insurance industry.' ),
	);
	foreach ( $award_defs as $a ) {
		list( $id, $new ) = tng_seed_post( 'award', $a[0], array( 'post_date' => $a[1], 'post_excerpt' => $a[2], 'post_content' => $generic ) );
		if ( $new ) {
			update_field( 'field_tng_awd_related_professionals', array( $aimee ), $id );
		}
	}
	$log[] = 'Awards seeded (3, related to Aimee Van Dyk)';

	/* ---- jobs ---- */
	$job_titles = array( 'Associate — Corporate/M&A', 'Senior Associate — Dispute Resolution', 'Legal Consultant', 'IP Paralegal', 'Office Administrator' );
	foreach ( array( 'Cambodia', 'Indonesia', 'Thailand', 'Vietnam' ) as $country ) {
		foreach ( $job_titles as $t ) {
			list( $id, $new ) = tng_seed_post( 'job', "$t ($country)", array( 'post_content' => '<!-- wp:paragraph --><p>Join our team in ' . $country . '.</p><!-- /wp:paragraph -->' ) );
			if ( $new ) {
				wp_set_object_terms( $id, $loc[ $country ], 'location' );
			}
		}
	}
	$log[] = 'Jobs seeded (5 per country × 4 countries)';

	/* ---- pages ---- */
	$home_content = tng_seed_block( 'acf/home-featured-banner', array_merge(
		tng_seed_data( 'fb', array(
			'featured_insight' => $in_featured,
			'recent_insights'  => array_map( 'strval', $in_recent ),
		) )
	) )
	. tng_seed_block( 'acf/home-intro-stats', tng_seed_flat( 'his', 'counters', array(
		array( 'number' => '250+', 'label' => 'Lawyers' ),
		array( 'number' => '135', 'label' => 'Years of Service' ),
		array( 'number' => '50', 'label' => 'Firm of the Year Awards (2025)' ),
		array( 'number' => '7', 'label' => 'Offices' ),
	), array( 'number', 'label' ) ) )
	. tng_seed_block( 'acf/home-insights' )
	. tng_seed_block( 'acf/home-careers' )
	. tng_seed_block( 'acf/home-awards' );

	$professionals_content = tng_seed_block( 'acf/page-hero', tng_seed_data( 'phe', array(
		'title' => 'Professionals',
		'intro' => "The Tilleke & Gibbins team is driven by our core values of integrity, innovation, and collaboration to deliver the results that our clients need to thrive in Southeast Asia. With extensive industry experience, world-class legal credentials, unrivalled local knowledge, and fluency in multiple languages, our team of over 250 legal professionals are committed to our clients' long-term success. To learn more about any of our professionals, please browse or search below.",
	) ) )
	. tng_seed_block( 'acf/pro-filter' )
	. tng_seed_block( 'acf/pro-grid' );

	$contact_content = tng_seed_block( 'acf/page-hero', tng_seed_data( 'phe', array(
		'title'   => 'Contact',
		'intro'   => "We welcome your questions and comments. Full contact information for all of our offices is below. You can also feel free to search our professionals for a specific person, or just contact us and we'll be happy to put you in touch with the right person.",
		'compact' => '1',
	) ) )
	. tng_seed_block( 'acf/contact-offices' );

	$careers_people_text = "<p>We can only achieve great things together if everyone is welcome. We don't just promote diversity—we see it as an essential part of delivering the best possible results to our clients, and as a result Tilleke &amp; Gibbins is an inclusive, welcoming law firm that promotes excellence among team members from all walks of life. With more than a dozen nationalities represented in the firm, female lawyers heading up the management of all three of our regional legal departments, and a firm dedication to providing individual working solutions to meet people's unique needs—not just broad policies based on assumed traits—we are recognized both locally and internationally for our diverse and inclusive working environment. That may go some way towards explaining why we have been named an Asian Legal Business \"Employer of Choice\" every year since 2013.</p><p>Our managing partners are both widely-regarded as diversity champions in the industry. Darani Vachanavuttivong is consistently listed as one of the Top 250 Women in IP globally by Managing Intellectual Property, and Tiziana Sucharitkul is one of the most sought-after international speakers and commentators on diversity in the legal industry and among business executives.</p><p>We are committed to diversity, inclusion, and fairness in our hiring and in how we care for our employees. We guarantee equal opportunity for all applicants without regard to race, religion, sexual orientation, gender identity, physical attributes, or any other characteristics beyond ability to succeed.</p>";

	$careers_content = tng_seed_block( 'acf/careers-hero' )
	. tng_seed_block( 'acf/careers-paths' )
	. tng_seed_block( 'acf/careers-values' )
	. tng_seed_block( 'acf/careers-people', tng_seed_data( 'cpe', array( 'text' => $careers_people_text ) ) )
	. tng_seed_block( 'acf/careers-cta' )
	. tng_seed_block( 'acf/careers-jobs' );

	// Under-development placeholders so no header/footer link dead-ends at "#".
	$ud_content = '<!-- wp:tng/under-development /-->';

	$pages = array(
		'Home'          => $home_content,
		'Professionals' => $professionals_content,
		'Contact'       => $contact_content,
		'Careers'       => $careers_content,
		'About'         => $ud_content,
		'Services'      => $ud_content,
		'Insights'      => $ud_content,
	);
	$page_ids = array();
	foreach ( $pages as $title => $content ) {
		list( $id )        = tng_seed_post( 'page', $title, array( 'post_content' => $content ) );
		$page_ids[ $title ] = $id;
		update_post_meta( $id, '_wp_page_template', 'tng-blank' );
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_ids['Home'] );
	$log[] = 'Pages seeded: Home (front page), Professionals, Contact, Careers, About, Services, Insights';

	/* ---- options (first run only; a heal pass below fixes "#" links) ---- */
	$careers_url = get_permalink( $page_ids['Careers'] );
	$contact_url = get_permalink( $page_ids['Contact'] );
	$pros_url    = get_permalink( $page_ids['Professionals'] );

	if ( ! get_field( 'top_links', 'option' ) ) :
	update_field( 'field_tng_hdr_top_links', array(
		array( 'label' => 'About', 'url' => '#' ),
		array( 'label' => 'Careers', 'url' => $careers_url ),
		array( 'label' => 'Contact', 'url' => $contact_url ),
	), 'option' );
	update_field( 'field_tng_hdr_professionals_url', $pros_url, 'option' );
	update_field( 'field_tng_hdr_insights_url', '#', 'option' );

	update_field( 'field_tng_ftr_social_links', array(
		array( 'network' => 'facebook', 'url' => '#' ),
		array( 'network' => 'youtube', 'url' => '#' ),
		array( 'network' => 'linkedin', 'url' => '#' ),
	), 'option' );
	update_field( 'field_tng_ftr_networks', array(
		array( 'name' => 'Lex Mundi Member', 'style' => 'lexmundi', 'url' => '#' ),
		array( 'name' => 'Multilaw', 'style' => 'multilaw', 'url' => '#' ),
		array( 'name' => 'Drew Network Asia', 'style' => 'dna', 'url' => '#' ),
	), 'option' );
	update_field( 'field_tng_ftr_quick_links_1', array(
		array( 'label' => 'Professionals', 'url' => $pros_url ),
		array( 'label' => 'Services', 'url' => '#' ),
		array( 'label' => 'Insights', 'url' => '#' ),
		array( 'label' => 'Locations', 'url' => '#' ),
	), 'option' );
	update_field( 'field_tng_ftr_quick_links_2', array(
		array( 'label' => 'About', 'url' => '#' ),
		array( 'label' => 'Careers', 'url' => $careers_url ),
		array( 'label' => 'Contact', 'url' => $contact_url ),
	), 'option' );
	update_field( 'field_tng_ftr_copyright', '© 2026 Tilleke & Gibbins International Ltd. All rights reserved.', 'option' );
	update_field( 'field_tng_ftr_legal_links', array(
		array( 'label' => 'Privacy Policy', 'url' => '#' ),
		array( 'label' => 'Fraudulent Communications', 'url' => '#' ),
		array( 'label' => 'Legal Notice', 'url' => '#' ),
	), 'option' );
	$log[] = 'Header / footer options seeded';
	endif;

	// Heal pass: any option link still on "#" whose label matches a page now
	// gets that page's URL. Customised URLs are never touched.
	$link_map = array();
	foreach ( $page_ids as $title => $pid ) {
		$link_map[ $title ] = get_permalink( $pid );
	}
	$heal = static function ( $rows ) use ( $link_map ) {
		foreach ( $rows as &$row ) {
			if ( isset( $row['label'], $link_map[ $row['label'] ] ) && ( empty( $row['url'] ) || '#' === $row['url'] ) ) {
				$row['url'] = $link_map[ $row['label'] ];
			}
		}
		return $rows;
	};
	foreach ( array(
		'field_tng_hdr_top_links'     => 'top_links',
		'field_tng_ftr_quick_links_1' => 'quick_links_1',
		'field_tng_ftr_quick_links_2' => 'quick_links_2',
	) as $heal_key => $heal_name ) {
		$rows = get_field( $heal_name, 'option' );
		if ( $rows ) {
			update_field( $heal_key, $heal( $rows ), 'option' );
		}
	}
	if ( '#' === get_field( 'insights_url', 'option' ) ) {
		update_field( 'field_tng_hdr_insights_url', $link_map['Insights'], 'option' );
	}
	$log[] = 'Option links healed to the new placeholder pages';

	flush_rewrite_rules();
	tng_bust_roster();
	remove_filter( 'wp_insert_post_data', 'tng_seed_keep_publish' );
	$log[] = 'Rewrite rules flushed, roster cache cleared. Done.';

	return $log;
}
