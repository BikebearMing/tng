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
		<p>Creates the demo content: terms (with office details), professionals, insights, awards, jobs, the pages (Home, Professionals, Contact, Careers, About, Insights, Awards &amp; Rankings, Pro Bono, CSR, Museum of Counterfeit Goods, Textile Collection, Legal Professionals, Job Opportunities, the legal / text pages, Operations Professionals, Internships, Recruitment Process, History, Services, Global Reach, Japan / China Desk + placeholders), term page content, options values, permalinks and the front page. Create-only: nothing that exists is changed (a page still holding only the under-development placeholder gets its real content). Safe to run more than once.</p>
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
	// Once the importer retired the demo (op retire_demo), never recreate it:
	// the trashed demo posts are invisible to tng_seed_find_post().
	$retired = get_option( 'tng_demo_retired' ); // true (all types, 1.2.x) or the list of retired types
	if ( $retired && in_array( $type, is_array( $retired ) ? $retired : array( 'professional', 'insight', 'award', 'job' ), true ) ) {
		return array( 0, false );
	}
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
	foreach ( tng_seed_mock_offices() as $o ) {
		$tid = tng_seed_term( 'location', $o[0], $loc[ $o[1] ] );
		$ref = 'location_' . $tid;
		// only fill offices that were never configured — edits are the user's
		if ( get_field( 'office_name', $ref ) ) {
			continue;
		}
		foreach ( tng_seed_mock_office_values( $o[2] ) as $name => $value ) {
			update_field( 'field_tng_loc_' . $name, $value, $ref );
		}
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
	if ( $aimee && ! get_field( 'lead', $aimee ) ) {
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
	if ( $in_forecast && ! get_post_meta( $in_forecast, '_wp_page_template', true ) ) {
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

	tng_seed_v110( $log );
	tng_seed_v112( $log );
	tng_seed_v114( $log );
	tng_seed_v115( $log );
	tng_seed_v130( $log );
	tng_seed_v131( $log );

	flush_rewrite_rules();
	tng_bust_roster();
	remove_filter( 'wp_insert_post_data', 'tng_seed_keep_publish' );
	$log[] = 'Rewrite rules flushed, roster cache cleared. Done.';

	return $log;
}

/* ---------------- 1.1.0: About, Insights, Awards, career path, terms, jobs ---------------- */

/**
 * Page seeding for 1.1.0. Same contract as the rest of the seeder: nothing
 * that exists is ever changed, with ONE guarded exception — a page whose
 * content is still exactly the untouched under-development placeholder block
 * (About, Insights) gets its real content. Anything an editor typed stays.
 */
function tng_seed_page_content( $title, $content, $args = array() ) {
	$placeholder = '<!-- wp:tng/under-development /-->';
	$id          = tng_seed_find_post( 'page', $title );
	if ( ! $id ) {
		list( $id ) = tng_seed_post( 'page', $title, array_merge( $args, array( 'post_content' => $content ) ) );
		update_post_meta( $id, '_wp_page_template', 'tng-blank' );
		return array( $id, 'created' );
	}
	if ( $content !== $placeholder && trim( (string) get_post_field( 'post_content', $id ) ) === $placeholder ) {
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ) ); // keeps a revision
		return array( $id, 'filled (was the placeholder)' );
	}
	return array( $id, 'kept' );
}

/** Term field values, written only when the term has none yet (create-only). */
function tng_seed_term_fields( $ref, $check, $fields ) {
	if ( get_field( $check, $ref ) ) {
		return false;
	}
	foreach ( $fields as $key => $value ) {
		update_field( $key, $value, $ref );
	}
	return true;
}

/**
 * The mock copy TNG Seed writes into term fields (name => value), keyed
 * "<taxonomy>:<slug>". Shared with the importer (inc/import.php): a term
 * field still holding exactly this value counts as empty and may be
 * replaced by the real content. Contacts are not here (they point at demo
 * professionals by ID; the importer checks those separately).
 */
function tng_seed_mock_term_values() {
	$experience = '<ul><li>Actively advising an international group with investments in real estate, telecommunications, and corporate finance on commercial and dispute strategy across multiple ongoing matters in Myanmar, including assessment of operational and litigation risk, jurisdictional considerations, and enforcement options in a volatile regulatory environment.</li><li>Advised multinational technology and automotive companies in Laos and Myanmar on data privacy and cross-border data transfer compliance, including regulatory assessments for digital platforms and connected-vehicle subscription services.</li><li>Advised a Japanese pharmaceutical and healthcare products group on a multiparty, cross-border sales and purchase agreement governing supply and distribution arrangements across Myanmar, Thailand, and Vietnam, addressing contractual risk allocation and regulatory implications.</li><li>Advised a Japanese pharmaceutical and healthcare products group on a multiparty, cross-border sales and purchase agreement governing supply and distribution arrangements across Myanmar, Thailand, and Vietnam, addressing contractual risk allocation and regulatory implications.</li><li>Represented a regional energy developer in contract disputes arising from a cross-border power supply project in Laos, including pre-arbitration negotiations and enforcement strategy.</li><li>Advised a digital asset platform on licensing requirements and market entry in Thailand, including engagement with the regulator on product structuring.</li></ul>';
	$bank_text  = 'Advised a major U.S.-based consumer bank on its market entry into Cambodia, Laos, and Myanmar, including advice on offshore banking services, banking regulations, permitted banking operations, foreign exchange regulations, securities markets, doing business, and tax in each jurisdiction.';
	$bank_rows  = array_fill( 0, 3, array( 'title' => 'Emerging jurisdiction market entry for global consumer bank', 'text' => $bank_text, 'link_url' => '' ) );
	return array(
		'location:cambodia' => array(
			'overview_heading' => 'Trusted and innovative legal advisors in the emerging economy of Cambodia.',
			'overview_text'    => '<p>Built on a strong local foundation, Tilleke &amp; Gibbins office in Phnom Penh has grown into one of the strongest legal outfits on the ground in Cambodia. The firm’s 16-person team in Phnom Penh comprises a mix of local and foreign legal advisors and other business professionals to provide our clients with well-rounded legal, business, and cultural advice to help them achieve their objectives in the jurisdiction.</p><p>Under the leadership of partner Jay Cohen, a practitioner with over 15 years of experience in Asia, the firm’s Phnom Penh office handles a full range of corporate and commercial, intellectual property, and regulatory services to a wide range of clients. On the commercial side this includes market entry for multinationals, commercial transactions (M&amp;As, JVs, and business partnerships), labor and employment, and key regulatory matters for highly regulated industries—especially banking and finance, technology, telecommunications, media, e-commerce, energy, consumer goods, and life sciences (e.g., pharmaceuticals and medical devices).</p><p>Beyond this corporate and commercial work, the firm also boasts a market-leading intellectual property (IP) practice, which clients often see as a key differentiator between our firm and other corporate and commercial firms in the country. Our IP practice seamlessly compliments our strategic market entry advice and sets the firm apart as a premium one-stop shop for all legal needs in Cambodia.</p>',
			'successes'        => array( array( 'title' => 'Tilleke & Gibbins Supports CAEX with Strategic Investments from OKX Ventures and HashKey Capital', 'text' => $bank_text, 'link_url' => '' ) ),
			'experience'       => $experience,
		),
		'industry:automotive' => array(
			'overview_heading' => 'Driving growth across Southeast Asia for leading players in the global automotive industry.',
			'overview_text'    => '<p>Tilleke &amp; Gibbins acts for clients across the automotive value chain, from upstream automotive and automotive parts manufacturers to downstream automotive distribution and sales dealerships, offering a one-stop service for a full range of legal matters in Southeast Asia.</p><p>At the start, our multidisciplinary team helps automotive industry players structure and implement market entry models, including assisting with M&amp;A transactions and joint ventures, obtaining licenses and investment incentives, and protecting valuable designs, patents, and trademarks. For manufacturers, we also regularly assist with land acquisition and factory construction in industrial estates and special economic zones. Once companies are set up and operating, we continue to work closely with our clients to maintain regulatory compliance across the region, advising on antitrust and trade competition, employment, import and export, tax, and other general corporate matters. And when complications occur, we also represent automotive clients in dispute resolution, including commercial and IP litigation, product liability claims, and class actions.</p><p>Our full-service legal expertise enables clients to capitalize on opportunities for success in the region, driving growth across Southeast Asia for leading players in the global automotive industry.</p>',
			'successes'        => $bank_rows,
			'experience'       => $experience,
		),
		'practice:intellectual-property' => array(
			'quote'         => '“Flawless cooperation between six regional offices ensures a consistently top-class service across the region”',
			'quote_source'  => 'Intellectual Asset Management',
			'overview_text' => '<p>We help creators strategically position, protect, and profit from intellectual assets in diverse markets worldwide. Our success on our clients’ behalf has led to global recognition as a leading intellectual property practice by such surveys as Asialaw Profiles, Chambers Asia Pacific, The Legal 500 Asia Pacific, Managing Intellectual Property, Practical Law Company, World Trademark Review, Asia IP, Asian Legal Business, and others.</p><p>We are versatile practitioners—well-educated in the overlapping fields of science, technology, and the law by top universities and real-world practice in one of the toughest IP regions in the world. Our IP group includes lawyers and patent agents with backgrounds in chemistry, biology, computer science, food science, materials science, and physics; support personnel including investigators and government/police liaisons; and technical specialists in various scientific fields, on an as-needed basis.</p>',
			'successes'     => $bank_rows,
		),
		'practice:commercialization' => array(
			'overview_heading' => 'We help you get real value from your intellectual assets.',
			'overview_text'    => '<p>Our practitioners have extensive experience in IP licensing and contracts, including agreements relating to franchising, licensing, distributorship, technology transfer, confidentiality, joint venture, and collaboration.</p><p>In addition, the firm’s expertise extends to IP due diligence issues, such as searches for infringement, audits to identify and protect IP assets including trade secrets, advice to avoid disputes when terminating dealers or distributors, consents and assignments for IP created by employees or contractors (work for hire), valuation of IP assets, IP risk assessments and management, and freedom-to-practice investigations.</p><p>In collaboration with our technology group, we provide services related to the clearance and licensing of intellectual property as it relates to film, television, radio, and internet content.</p>',
			'experience'       => $experience,
		),
	);
}

/** City offices TNG Seed creates: [city, country, map query]. */
function tng_seed_mock_offices() {
	return array(
		array( 'Bangkok', 'Thailand', 'Supalai Grand Tower, 1011 Rama 3 Road, Bangkok' ),
		array( 'Hanoi', 'Vietnam', 'Tilleke & Gibbins, Hanoi' ),
		array( 'Ho Chi Minh City', 'Vietnam', 'Tilleke & Gibbins, Ho Chi Minh City' ),
		array( 'Phnom Penh', 'Cambodia', 'Tilleke & Gibbins, Phnom Penh' ),
		array( 'Vientiane', 'Laos', 'Tilleke & Gibbins, Vientiane' ),
		array( 'Yangon', 'Myanmar', 'Tilleke & Gibbins, Yangon' ),
	);
}

/** The mock office details TNG Seed writes on a city term (Bangkok's, repeated). */
function tng_seed_mock_office_values( $map_query ) {
	return array(
		'office_name' => 'Tilleke & Gibbins International Ltd.',
		'phone'       => '+855 23 964 210',
		'email'       => 'cambodia@tilleke.com',
		'address'     => "Supalai Grand Tower,\n26th Floor, 1011 Rama 3\nRoad, Chongnonsi,\nYannawa, Bangkok 10120",
		'map_query'   => $map_query,
	);
}

function tng_seed_v110( &$log ) {
	$t = static function ( $tax, $name, $parent = 0 ) {
		return tng_seed_term( $tax, $name, $parent );
	};

	/* ---- terms: IP subpractices ---- */
	$ip   = $t( 'practice', 'Intellectual Property' );
	$subs = array();
	foreach ( array( 'Commercialization', 'Trademarks', 'Patents' ) as $n ) {
		$subs[ $n ] = $t( 'practice', $n, $ip );
	}
	$loc = array();
	foreach ( array( 'Cambodia', 'Indonesia', 'Laos', 'Myanmar', 'Thailand', 'Vietnam' ) as $n ) {
		$loc[ $n ] = $t( 'location', $n );
	}
	$automotive = $t( 'industry', 'Automotive' );
	$corporate  = $t( 'practice', 'Corporate/M&A' );

	/* ---- professionals: the Cambodia contacts from the service mock ---- */
	$pros = array();
	foreach ( array(
		'Jay Cohen'  => array( 'Partner', 'jay.c@tilleke.com' ),
		'David Mol'  => array( 'Consultant', 'david.m@tilleke.com' ),
	) as $name => $def ) {
		list( $id, $new ) = tng_seed_post( 'professional', $name );
		$pros[ $name ]    = $id;
		if ( $new ) {
			update_field( 'field_tng_pro_position_title', $def[0], $id );
			update_field( 'field_tng_pro_phone', '+855 23 964 210', $id );
			update_field( 'field_tng_pro_email', $def[1], $id );
			wp_set_object_terms( $id, array( $loc['Cambodia'] ), 'location' );
			wp_set_object_terms( $id, array( $automotive ), 'industry' );
			wp_set_object_terms( $id, array( $ip, $corporate ), 'practice' );
			wp_set_object_terms( $id, 'English', 'language' );
			wp_set_object_terms( $id, $def[0], 'position' );
		}
	}
	foreach ( array( 'Alan Adcock', 'Alisa Koovimol', 'Athistha (Nop) Chitranukroh', 'Aimee Van Dyk' ) as $name ) {
		$pros[ $name ] = tng_seed_find_post( 'professional', $name );
	}
	$log[] = '1.1.0: professionals Jay Cohen + David Mol (Cambodia) ensured';

	/* ---- insights + awards tagged for the term pages ---- */
	$generic = tng_seed_paragraphs( array(
		'Tilleke &amp; Gibbins advises international and local businesses across Southeast Asia. This update summarizes the key developments and what they mean for companies operating in the region.',
		'For more information, please contact our team.',
	) );
	$tagged = array(
		array( 'insight', 'Cambodia Updates Trademark Registration Procedures', '2026-11-20 10:00:00', 'Cambodia’s Department of Intellectual Property has updated its trademark filing and examination procedures. Here is what brand owners need to know.', array( 'Cambodia' ), array( $ip ), array(), 'Jay Cohen' ),
		array( 'insight', 'Doing Business in Cambodia 2026', '2026-10-15 10:00:00', 'A practical overview of market entry, investment incentives, and regulatory requirements for foreign investors in Cambodia.', array( 'Cambodia' ), array( $corporate ), array(), 'David Mol' ),
		array( 'insight', 'Protecting Automotive Designs and Trademarks in Southeast Asia', '2026-11-10 10:00:00', 'How automotive manufacturers and parts suppliers can secure design, patent, and trademark protection across the region.', array( 'Thailand' ), array( $ip ), array( $automotive ), 'Alan Adcock' ),
		array( 'award', 'Tilleke & Gibbins Named Cambodia Law Firm of the Year 2026', '2026-11-01 10:00:00', 'Tilleke &amp; Gibbins has been named Cambodia Law Firm of the Year, recognizing the strength of its Phnom Penh team across corporate, regulatory, and intellectual property work.', array( 'Cambodia' ), array( $ip ), array( $automotive ), 'Jay Cohen' ),
		array( 'award', 'Tilleke & Gibbins Recognized in IP Stars 2026', '2026-10-20 10:00:00', 'Tilleke &amp; Gibbins and its practitioners have again been recognized in the IP Stars rankings for trademark, patent, and enforcement work.', array( 'Thailand' ), array( $ip ), array(), 'Alan Adcock' ),
	);
	foreach ( $tagged as $p ) {
		list( $type, $title, $date, $excerpt, $countries, $practices, $industries, $person ) = $p;
		list( $id, $new ) = tng_seed_post( $type, $title, array( 'post_date' => $date, 'post_excerpt' => $excerpt, 'post_content' => $generic ) );
		if ( ! $new ) {
			continue;
		}
		wp_set_object_terms( $id, array_map( function ( $n ) use ( $loc ) { return $loc[ $n ]; }, $countries ), 'location' );
		if ( $practices ) {
			wp_set_object_terms( $id, $practices, 'practice' );
		}
		if ( $industries ) {
			wp_set_object_terms( $id, $industries, 'industry' );
		}
		if ( ! empty( $pros[ $person ] ) ) {
			update_field( 'insight' === $type ? 'field_tng_ins_related_professionals' : 'field_tng_awd_related_professionals', array( $pros[ $person ] ), $id );
		}
	}
	$log[] = '1.1.0: tagged insights (3) + awards (2) for the location / service pages ensured';

	/* ---- term page content (only terms that have none yet) ---- */
	$mock    = tng_seed_mock_term_values();
	$contact = static function ( $country, $names ) use ( $loc, $pros ) {
		return array(
			'location' => $loc[ $country ],
			'people'   => array_values( array_filter( array_map( function ( $n ) use ( $pros ) { return isset( $pros[ $n ] ) ? $pros[ $n ] : 0; }, $names ) ) ),
		);
	};
	$contacts = array(
		'industry:automotive'            => array(
			$contact( 'Cambodia', array( 'Jay Cohen', 'David Mol' ) ),
			$contact( 'Thailand', array( 'Alan Adcock', 'Athistha (Nop) Chitranukroh' ) ),
			$contact( 'Vietnam', array( 'Athistha (Nop) Chitranukroh' ) ),
		),
		'practice:intellectual-property' => array(
			$contact( 'Thailand', array( 'Alan Adcock', 'Alisa Koovimol' ) ),
			$contact( 'Cambodia', array( 'Jay Cohen' ) ),
		),
		'practice:commercialization'     => array( $contact( 'Thailand', array( 'Alan Adcock' ) ) ),
	);
	$targets = array(
		'location:cambodia'              => array( 'Cambodia', 'location_' . $loc['Cambodia'], 'loc' ),
		'industry:automotive'            => array( 'Automotive', 'industry_' . $automotive, 'svc' ),
		'practice:intellectual-property' => array( 'Intellectual Property', 'practice_' . $ip, 'svc' ),
		'practice:commercialization'     => array( 'Commercialization', 'practice_' . $subs['Commercialization'], 'svc' ),
	);
	$done = array();
	foreach ( $targets as $key => $target ) {
		$fields = array();
		foreach ( $mock[ $key ] as $name => $value ) {
			$fields[ 'field_tng_' . $target[2] . '_' . $name ] = $value;
		}
		if ( isset( $contacts[ $key ] ) ) {
			$fields['field_tng_svc_contacts'] = $contacts[ $key ];
		}
		if ( tng_seed_term_fields( $target[1], 'overview_text', $fields ) ) {
			$done[] = $target[0];
		}
	}
	$log[] = '1.1.0: term page content filled for: ' . ( $done ? implode( ', ', $done ) : 'none (all already had content)' );

	/* ---- pages ---- */
	$ud      = '<!-- wp:tng/under-development /-->';
	$about   = tng_seed_find_post( 'page', 'About' );
	$careers = tng_seed_find_post( 'page', 'Careers' );

	list( $history ) = tng_seed_page_content( 'History', $ud, array( 'post_parent' => $about, 'menu_order' => 1 ) );
	list( $awards_page, $awards_state ) = tng_seed_page_content( 'Awards & Rankings', $ud, array( 'post_parent' => $about, 'menu_order' => 2, 'post_name' => 'awards-rankings' ) );
	list( $pro_bono ) = tng_seed_page_content( 'Pro Bono', $ud, array( 'post_parent' => $about, 'menu_order' => 3 ) );
	list( $legal, $legal_state ) = tng_seed_page_content( 'Legal Professionals', $ud, array( 'post_parent' => $careers, 'menu_order' => 1 ) );
	list( $operations ) = tng_seed_page_content( 'Operations Professionals', $ud, array( 'post_parent' => $careers, 'menu_order' => 2 ) );
	list( $internships ) = tng_seed_page_content( 'Internships', $ud, array( 'post_parent' => $careers, 'menu_order' => 3 ) );

	$url = static function ( $id ) {
		return $id ? get_permalink( $id ) : '#';
	};

	// About
	$about_content = tng_seed_block( 'acf/about-hero', tng_seed_data( 'abh', array(
		'title' => 'Southeast Asia’s Premier Law Firm',
		'intro' => 'Tilleke & Gibbins is a leading full-service Southeast Asian law firm proudly serving international and local businesses across six countries. We are committed to helping our clients achieve sustainable success in the region, working with them to provide unparalleled legal expertise, rooted in practical commerciality.',
	) ) )
	. tng_seed_block( 'acf/page-tabs', tng_seed_flat( 'ptb', 'tabs', array(
		array( 'label' => 'Overview', 'anchor' => 'overview' ),
		array( 'label' => 'Our Numbers', 'anchor' => 'numbers' ),
		array( 'label' => 'Code of Conduct', 'anchor' => 'code-of-conduct' ),
		array( 'label' => 'Vision and Mission', 'anchor' => 'vision' ),
		array( 'label' => 'Creed', 'anchor' => 'creed' ),
	), array( 'label', 'anchor' ) ) )
	. tng_seed_block( 'acf/overview', tng_seed_data( 'ovw', array(
		'variant'    => 'about',
		'section_id' => 'overview',
		'label'      => 'Overview',
		'heading'    => 'Our commercial focus, local knowledge, and extraordinary service make us the full-service law firm of choice in Southeast Asia.',
		'text'       => '<p>Tilleke &amp; Gibbins is a leading Southeast Asian regional law firm with over 250 lawyers and consultants practicing in Cambodia, Indonesia, Laos, Myanmar, Thailand, and Vietnam. We provide full-service legal solutions to the top investors and high-growth companies that drive economic expansion in Asia.<br>Established in Bangkok in 1890, we have since grown into the largest independent law firm in Thailand and a leading international firm across Southeast Asia due to our commercial focus, local knowledge, and extraordinary service.</p>',
	) ) )
	. tng_seed_block( 'acf/about-values', tng_seed_flat( 'abv', 'items', array(
		array( 'title' => 'Commercial Focus', 'text' => '<p>Your long-term success is our utmost priority, and we take the time to learn about your unique corporate culture, operational needs, and commercial goals, to determine how we can best provide value to your organization. From highly technical experience in specific fields, to a deep understanding of your business, our team is committed to providing high-quality legal advice, tailored to meet your unique needs, in order to help you succeed.</p>' ),
		array( 'title' => 'Extraordinary Service', 'text' => '<p>Your long-term success is our utmost priority, and we take the time to learn about your unique corporate culture, operational needs, and commercial goals, to determine how we can best provide value to your organization.</p>' ),
		array( 'title' => 'Local Knowledge', 'text' => '<p>Your long-term success is our utmost priority, and we take the time to learn about your unique corporate culture, operational needs, and commercial goals, to determine how we can best provide value to your organization.</p>' ),
	), array( 'title', 'text' ) ) )
	. tng_seed_block( 'acf/about-numbers', tng_seed_flat( 'abn', 'numbers', array(
		array( 'value' => '250+', 'label' => 'Lawyers' ),
		array( 'value' => '135', 'label' => 'Years of Service' ),
		array( 'value' => '50', 'label' => 'Firm of the Year Awards (2025)' ),
		array( 'value' => '7', 'label' => 'Offices' ),
	), array( 'value', 'label' ) ) )
	. tng_seed_block( 'acf/overview', tng_seed_data( 'ovw', array(
		'variant'    => 'code',
		'section_id' => 'code-of-conduct',
		'label'      => 'Code of Conduct',
		'heading'    => 'Our Code of Business Conduct is intended to help give all of our stakeholders direction as to our responsibilities and the standards we are expected to uphold.',
		'text'       => '<p>All of us at Tilleke &amp; Gibbins have a responsibility to uphold the core values that drive our firm’s success. Foremost among these values is integrity. Our clients trust us to act with integrity in handling their most sensitive legal issues and solving their most complex business problem.</p><p>By acting with integrity and doing things the right way, everyone in the firm—from 1890 up until today—has contributed to building a strong firm with an outstanding reputation for legal knowledge and client service.</p><p>Our Code of Business Conduct serves as an overview of our expected behavior and a reminder of our obligations to our clients, to our communities, and to ourselves.</p>',
		'link_label' => 'Code of Business Conduct',
		'link_url'   => '#',
	) ) )
	. tng_seed_block( 'acf/about-vision', tng_seed_flat( 'abvi', 'items', array(
		array( 'label' => 'Vision', 'text' => 'Our objective is to be the leading regional full-service law practice serving international business and civil society' ),
		array( 'label' => 'Mission', 'text' => 'Our objective is to be the leading regional full-service law practice serving international business and civil society' ),
	), array( 'label', 'text' ) ) )
	. tng_seed_block( 'acf/about-creed', array_merge(
		tng_seed_data( 'abc', array( 'label' => 'Creed' ) ),
		tng_seed_flat( 'abc', 'columns', array(
			array( 'heading' => 'As a Law Firm', 'list' => '<ul><li>We care.</li><li>We value integrity.</li><li>We are knowledgeable, dependable, responsible, perceptive, practical, realistic, competitive, community oriented.</li><li>We have style, coupled with a sense of history</li><li>We are a business, yet family.</li><li>Clients are our guests.</li></ul>' ),
			array( 'heading' => 'Our Facilities', 'list' => '<ul><li>Reflect: character, comfort, soul, stability, elegance.</li><li>Impress yet relax.</li><li>Are efficient yet friendly.</li><li>Are professional and reassuring.</li></ul>' ),
		), array( 'heading', 'list' ) )
	) )
	. tng_seed_block( 'acf/more-from', tng_seed_flat( 'mfr', 'links', array(
		array( 'label' => 'Overview', 'url' => $url( $about ) ),
		array( 'label' => 'History', 'url' => $url( $history ) ),
		array( 'label' => 'Awards & Rankings', 'url' => $url( $awards_page ) ),
	), array( 'label', 'url' ) ) );
	list( , $about_state ) = tng_seed_page_content( 'About', $about_content );

	// Insights (archive page)
	$insights_content = tng_seed_block( 'acf/archive-hero', tng_seed_data( 'arh', array(
		'title'      => 'Insights',
		'intro'      => 'Tilleke & Gibbins provides regular updates on all of the latest legal developments in Southeast Asia, ensuring that you have the up-to-date knowledge you need to navigate the ever-changing legal landscape affecting your business. You can browse our entire library of publications below, or subscribe to receive the latest legal developments straight to your inbox.',
		'link_label' => 'Subscribe to Updates',
		'link_url'   => '#',
	) ) )
	. tng_seed_block( 'acf/archive-featured', tng_seed_data( 'arf', array(
		'featured_post' => tng_seed_find_post( 'insight', 'Thailand Legal Basics' ),
		'fallback_type' => 'insight',
	) ) )
	. tng_seed_block( 'acf/archive-filter' )
	. tng_seed_block( 'acf/archive-grid', tng_seed_data( 'arg', array( 'source' => 'insight', 'page_size' => '12', 'show_count' => '1' ) ) );
	list( , $insights_state ) = tng_seed_page_content( 'Insights', $insights_content );

	// Awards & Rankings (child of About)
	$awards_content = tng_seed_block( 'acf/archive-hero', tng_seed_data( 'arh', array(
		'title' => 'Awards & Rankings',
		'intro' => 'Tilleke & Gibbins is consistently recognized by the world’s leading independent legal industry organizations as an elite firm in every jurisdiction in which we work. These accolades come only after rigorous research and client interviews, and give our clients confidence that they are working with one of the most trusted firms in Southeast Asia. Catch up on our latest awards and rankings below.',
	) ) )
	. tng_seed_block( 'acf/archive-featured', tng_seed_data( 'arf', array(
		'featured_post' => tng_seed_find_post( 'award', 'Tilleke & Gibbins Named Regional Litigation Law Firm of the Year at the 2026 ALB SE Asia Law Awards' ),
		'fallback_type' => 'award',
	) ) )
	. tng_seed_block( 'acf/archive-filter' )
	. tng_seed_block( 'acf/archive-grid', tng_seed_data( 'arg', array( 'source' => 'award', 'page_size' => '15', 'show_count' => '0' ) ) )
	. tng_seed_block( 'acf/more-from', array_merge(
		tng_seed_data( 'mfr', array( 'arrows' => '1' ) ),
		tng_seed_flat( 'mfr', 'links', array(
			array( 'label' => 'Overview', 'url' => $url( $about ) ),
			array( 'label' => 'History', 'url' => $url( $history ) ),
			array( 'label' => 'Pro Bono', 'url' => $url( $pro_bono ) ),
		), array( 'label', 'url' ) )
	) );
	list( , $awards_state ) = tng_seed_page_content( 'Awards & Rankings', $awards_content );

	// Legal Professionals (career path, child of Careers)
	$quote = '<p>My first connection to Tilleke was an internship, and because it was such a positive experience, I chose to return as an attorney when I began my professional career.</p><p>Since then I’ve been given the chance to represent an incredible range of clients in high-stakes litigation–from the world’s biggest tech companies in trouble over user activity, to foreign embassies in need of legal assistance.</p>';
	$legal_content = tng_seed_block( 'acf/path-hero', tng_seed_data( 'pth', array(
		'intro' => 'Lawyers at Tilleke are the benchmark of world-class legal practice in Southeast Asia. We build sustainable relationships with our clients, achieve a deep and enduring understanding of their businesses, and work together with them to let our expertise facilitate their commercial goals in the region.',
	) ) )
	. tng_seed_block( 'acf/overview', tng_seed_data( 'ovw', array(
		'variant' => 'path',
		'label'   => 'Overview',
		'heading' => 'We are always on the lookout for the next legal industry leaders.',
		'text'    => '<p>Our ever-expanding team of talented lawyers includes attorneys from across Southeast Asia and around the globe. They are multilingual and hold excellent academic credentials from the world’s most respected legal and academic institutions. In addition, lawyers at Tilleke &amp; Gibbins have impressive experience, having worked on major cases and for leading companies in the region and beyond.</p><p>We offer meaningful professional growth and leadership opportunities to our team members to ensure that they stay at the top of the legal profession, and we challenge and support our lawyers to expand their expertise to new practice areas and jurisdictions. With highly skilled and knowledgeable lawyers in every legal field and a commitment to master emerging legal topics, our firm promotes the development of niche or broad practices that capitalize on our professionals’ skills and interests.</p>',
	) ) )
	. tng_seed_block( 'acf/careers-people', tng_seed_data( 'cpe', array(
		'variant' => 'path',
		'label'   => 'Career Development',
		'heading' => 'Enhancing your professional and<br>personal development together.',
		'text'    => '<p>We encourage all of our lawyers to develop not only as excellent practitioners but also as leaders in their field. Tilleke lawyers are respected for the breadth of their knowledge and experience, and we promote that experience by providing you with opportunities to share your insights through conferences, seminars, publications, and more. In all practices and at all levels, our practitioners hold leadership positions in chambers of commerce, charitable foundations, and bodies dedicated to furthering the legal and economic development of the region. Within the firm, we promote rising stars accordingly with pathways to leadership. By developing a consistently strong team that works and grows together, we secure a bright future for our firm to continue our legacy of excellence.</p>',
	) ) )
	. tng_seed_block( 'acf/team-stories', tng_seed_flat( 'tms', 'stories', array(
		array( 'role' => 'Intern', 'name' => 'Piyawat Vitooraporn', 'position' => 'Senior Associate (Former Intern)', 'quote' => $quote ),
		array( 'role' => 'Associate', 'name' => 'Chandavya Ing', 'position' => 'Associate', 'quote' => $quote ),
		array( 'role' => 'Associate', 'name' => 'Chuyen Hong Huu Le', 'position' => 'Associate', 'quote' => $quote ),
		array( 'role' => 'Counsel', 'name' => 'Michael Ramirez', 'position' => 'Counsel', 'quote' => $quote ),
		array( 'role' => 'Partner', 'name' => 'Yuwadee Thean-ngarm', 'position' => 'Partner', 'quote' => $quote ),
	), array( 'role', 'name', 'position', 'quote' ) ) )
	. tng_seed_block( 'acf/path-jobs' )
	. tng_seed_block( 'acf/more-from', array_merge(
		tng_seed_data( 'mfr', array( 'arrows' => '1' ) ),
		tng_seed_flat( 'mfr', 'links', array(
			array( 'label' => 'Operations Professionals', 'url' => $url( $operations ) ),
			array( 'label' => 'Internships', 'url' => $url( $internships ) ),
			array( 'label' => 'All Job Opportunities', 'url' => $url( $careers ) . '#job-opportunities' ),
		), array( 'label', 'url' ) )
	) );
	list( , $legal_state ) = tng_seed_page_content( 'Legal Professionals', $legal_content );

	$log[] = '1.1.0 pages: About ' . $about_state . ', Insights ' . $insights_state . ', Awards & Rankings ' . $awards_state . ', Legal Professionals ' . $legal_state . '; placeholders ensured: History, Pro Bono, Operations Professionals, Internships';

	/* ---- jobs with the full job page fields ---- */
	$resp_list = '<ul><li>Assist with the preparation, organization, and submission of trademark-related documents and government fees to the Vietnam IP Office and other relevant authorities.</li><li>Maintain accurate trademark records, filing information, mailing lists, and matter data within the Firm’s internal systems.</li><li>Support trademark professionals in the administration of trademark filings, renewals, recordals, and related procedures.</li><li>Calculate government fees and service charges for trademark and related IP matters.</li><li>Keep records of all administrative documentation and ensure matter information remains accurate and current.</li></ul>';
	$jobs = array(
		array( 'Assistant, Trademark Executive', 'Thailand', 'Administrative', $operations, 'Reporting to the Head of Trademark Group and other designated supervisors, the Assistant, Trademark Executive supports trademark administration, client correspondence, filings, and related operational activities. The role provides an excellent opportunity to build practical experience in intellectual property practice, legal administration, and client service.', array( 'Trademark Administration & Case Management', 'Documentation & Client Support', 'Operational & Team Support' ) ),
		array( 'Associate (Cross-Border Transactions)', 'Thailand', 'Corporate and Commercial', $legal, 'Working with partners across our regional offices, the Associate advises international clients on cross-border transactions, market entry, and corporate structuring in Southeast Asia.', array( 'Transactions', 'Client Advisory' ) ),
		array( 'Associate (Dispute Resolution)', 'Vietnam', 'Dispute Resolution', $legal, 'The Associate supports our dispute resolution team on commercial litigation and arbitration matters for international and local clients in Vietnam.', array( 'Litigation & Arbitration', 'Client Advisory' ) ),
		array( 'Senior Associate (Intellectual Property)', 'Cambodia', 'Intellectual Property', $legal, 'The Senior Associate leads trademark, patent, and enforcement matters for clients in Cambodia, working closely with our regional IP group.', array( 'IP Prosecution & Enforcement', 'Team Leadership' ) ),
	);
	$made = 0;
	foreach ( $jobs as $j ) {
		list( $title, $country, $dept, $path, $summary, $groups ) = $j;
		list( $id, $new ) = tng_seed_post( 'job', $title, array( 'post_content' => '' ) );
		if ( ! $new ) {
			continue;
		}
		$made++;
		wp_set_object_terms( $id, array( $loc[ $country ] ), 'location' );
		update_field( 'field_tng_job_department', $dept, $id );
		if ( $path ) {
			update_field( 'field_tng_job_career_path', $path, $id );
		}
		update_field( 'field_tng_job_summary', $summary, $id );
		update_field(
			'field_tng_job_responsibilities',
			array_map(
				function ( $h ) use ( $resp_list ) {
					return array( 'heading' => $h, 'list' => $resp_list );
				},
				$groups
			),
			$id
		);
	}
	$log[] = '1.1.0: jobs with full job-page fields: ' . $made . ' new (career paths: Legal Professionals, Operations Professionals)';
}

/* ---------------- 1.1.2: About child pages (Pro Bono, CSR, museum) ---------------- */

/**
 * Same create-only contract as 1.1.0: Pro Bono exists as the under-development
 * placeholder (filled only while it is still exactly that); CSR and the museum
 * are created when no page of that title exists. Nothing else is touched.
 */
function tng_seed_v112( &$log ) {
	$about = tng_seed_find_post( 'page', 'About' );
	if ( ! $about ) {
		$log[] = '1.1.2: no About page, About child pages skipped';
		return;
	}
	$url = static function ( $id ) {
		return $id ? get_permalink( $id ) : '#';
	};
	$history = tng_seed_find_post( 'page', 'History' );
	$awards  = tng_seed_find_post( 'page', 'Awards & Rankings' );

	$more_from = static function ( $links ) {
		return tng_seed_block( 'acf/more-from', array_merge(
			tng_seed_data( 'mfr', array( 'arrows' => '1' ) ),
			tng_seed_flat( 'mfr', 'links', $links, array( 'label', 'url' ) )
		) );
	};

	// CSR first, so Pro Bono's "More from" can link to it
	$csr_content = tng_seed_block( 'acf/info-hero', tng_seed_data( 'ifh', array(
		'label'        => 'CSR contact',
		'contact_name' => 'Yuwadee Thean-ngarm',
		'phone'        => '+959 772 446 602',
		'email'        => 'yuwadee.t@tilleke.com',
	) ) )
	. tng_seed_block( 'acf/overview', tng_seed_data( 'ovw', array(
		'variant'    => 'info',
		'section_id' => 'overview',
		'label'      => 'Overview',
		'heading'    => 'Tilleke & Gibbins is dedicated to giving back to the communities in which we live and work.',
		'text'       => '<p>Across Southeast Asia, Tilleke &amp; Gibbins takes its duty to corporate social responsibility seriously, focusing on the firm’s three community pillars of education, human health, and sustainability. We take part in dozens of activities every year including regular support of rural schools through equipment donations and volunteering, reforestation and wildlife preservation projects, and long partnerships with numerous organizations that seek to improve the health and quality of life of society’s least protected people.</p><p>In addition, Tilleke &amp; Gibbins has a longstanding commitment to equality, and regularly partners with organizations that work tirelessly to support women and the LGBT+ community in business and law. While we pride ourselves on being one of Asia’s most inclusive and meritocratic workplaces, we recognize that our position comes with a duty to lead by example, and we do so at every opportunity.</p><p>Our CSR activities are not prescriptive, however, and when unforeseen events such as flooding, natural disasters, and disease outbreaks occur, Tilleke &amp; Gibbins’ staff come together to help assist those affected in any way we can, from donations to on-the-ground support. In short, Tilleke &amp; Gibbins is dedicated to giving back to the communities in which we live and work.</p>',
		'show_image' => '1',
	) ) )
	. $more_from( array(
		array( 'label' => 'Overview', 'url' => $url( $about ) ),
		array( 'label' => 'History', 'url' => $url( $history ) ),
		array( 'label' => 'Awards & Rankings', 'url' => $url( $awards ) ),
	) );
	list( $csr, $csr_state ) = tng_seed_page_content( 'CSR', $csr_content, array( 'post_parent' => $about, 'menu_order' => 4, 'post_name' => 'csr' ) );

	// Pro Bono (the 1.1.0 placeholder)
	$pro_bono_content = tng_seed_block( 'acf/info-hero', tng_seed_data( 'ifh', array(
		'label'        => 'Pro Bono contact',
		'contact_name' => 'Eric Meyer',
		'phone'        => '+66 2056 5797',
		'email'        => 'eric.m@tilleke.com',
	) ) )
	. tng_seed_block( 'acf/overview', tng_seed_data( 'ovw', array(
		'variant'    => 'info',
		'section_id' => 'overview',
		'label'      => 'Overview',
		'heading'    => 'Tilleke & Gibbins assesses each pro bono request carefully to ensure that our work has the maximum positive impact on the organizations that need it most.',
		'text'       => '<p>Tilleke &amp; Gibbins has one of Southeast Asia’s most active pro bono practices. We provide extensive support to NGOs and social enterprises in Cambodia, Indonesia, Laos, Myanmar, Thailand, and Vietnam to ensure that they are able to continue their vital work unfettered by legal compliance issues in their corporate structures, labor and employment practices, and other legal matters. We assess each pro bono request carefully to ensure that our work has the maximum positive impact on the organizations that need it most.</p><p>We also work side by side with numerous global pro bono organizations such as TrustLaw and the Lex Mundi Pro Bono Foundation to assist with global legal research projects on areas of socio-legal development that help to fight injustices across our three core pillars of education, human health, and sustainability.</p><p>Our dedication to pro bono work has been recognized on the international stage with awards from institutions and global organizations such as the <a href="#">Thomson Reuters Foundation</a>, the <a href="#">Chambers and Partners Diversity &amp; Inclusion Initiative</a>, the <a href="#">Cyrus R. Vance Center for International Justice</a>, and various others.</p>',
	) ) )
	. $more_from( array(
		array( 'label' => 'Overview', 'url' => $url( $about ) ),
		array( 'label' => 'History', 'url' => $url( $history ) ),
		array( 'label' => 'CSR', 'url' => $url( $csr ) ),
	) );
	list( , $pro_bono_state ) = tng_seed_page_content( 'Pro Bono', $pro_bono_content, array( 'post_parent' => $about, 'menu_order' => 3 ) );

	// Museum of Counterfeit Goods (slides 2-3 repeat slide 1, as on the mock)
	$case = array(
		'title' => 'Enforcement Campaigns for HAVAIANAS Flip-Flop Sandals',
		'text'  => 'Due to their unique design, HAVAIANAS flip-flop sandals have become very popular among Thai people and tourists, especially at tourist attractions and beach areas. This has resulted in the widespread distribution of counterfeit HAVAIANAS sandals across Thailand. The counterfeit products use similar, infringing designs, and are offered at prices which are three to four times lower than genuine HAVAIANAS sandals, but with very inferior quality.',
	);
	$museum_content = tng_seed_block( 'acf/info-hero', tng_seed_data( 'ifh', array(
		'label' => 'Please book your visit a minimum of five working days in advance. Visitors from the general public are welcome on:',
		'hours' => "Mondays at 2 pm &\nThursdays at 10 am",
		'phone' => '+66 2056 5555',
		'email' => 'museum.t@tilleke.com',
	) ) )
	. tng_seed_block( 'acf/overview', tng_seed_data( 'ovw', array(
		'variant'    => 'info',
		'section_id' => 'overview',
		'label'      => 'Overview',
		'heading'    => 'Our museum educates visitors about the value of intellectual property and why protecting it is important.',
		'text'       => '<p>Forming part of the Tilleke &amp; Gibbins Corporate Social Responsibility program, the Tilleke &amp; Gibbins Museum of Counterfeit Goods welcomes hundreds of visitors each year for guided tours, led by our experienced intellectual property attorneys.</p><p>The lectures and tours dispel the myth that IP infringement is a victimless crime. Visitors learn about how fake products jeopardize health and safety both for the consumers who buy them and for the workers who produce them. Our efforts educate people on how counterfeiting is closely tied up to other forms of organized crime, including drugs, firearms, human trafficking, and even terrorism. We also explain how counterfeiting and piracy rob the government of tax revenue and impose greater costs on legitimate taxpayers due to the burden of enforcement, while stifling innovation and damaging overall economic development. We welcome a range of visitors to our museum:</p>',
	) ) )
	. tng_seed_block( 'acf/mus-audience', tng_seed_flat( 'mau', 'items', array(
		array( 'title' => 'Professionals', 'text' => '<p>The team provides advanced brand-awareness training on behalf of our clients for intellectual property practitioners, Thai and foreign police, judges, members of the diplomatic corps, and other government officials. During these events, professionals will receive in-depth training on how to differentiate between genuine and counterfeit goods, thus further building the capacity of the participants and helping them to succeed in their difficult work.</p>' ),
		array( 'title' => 'Students', 'text' => '<p>The museum often hosts large classes of university students from a range of faculties, including law, design, architecture, and other fields that depend on the protection of creative ideas for their success. Visiting students have the opportunity to learn from experienced IP lawyers about the basics of intellectual property and the practical implications of piracy and counterfeiting.</p>' ),
		array( 'title' => 'Public', 'text' => '<p>The museum is open to members of the general public at designated dates and times. Visits are always subject to an advance booking, and this booking should be made a minimum of five working days in advance. To schedule a visit, please contact the Museum of Counterfeit Goods at <a href="mailto:museum@tilleke.com">museum@tilleke.com</a>.</p>' ),
		array( 'title' => 'Media', 'text' => '<p>The museum has attracted significant media attention, and numerous major foreign television broadcasting companies (including CNN and BBC) have featured the museum in their documentaries concerning the counterfeit situation in Thailand. Many local and international newspapers and magazines have also published articles on the museum, including The Christian Science Monitor and TIME magazine.</p>' ),
	), array( 'title', 'text' ) ) )
	. tng_seed_block( 'acf/mus-collection', tng_seed_data( 'mco', array(
		'label'  => 'Collection',
		'quote'  => '“A mind-boggling array of counterfeit merchandise”',
		'source' => '– Lonely Planet',
		'text'   => '<p>Established in 1989 by David Lyman, Chairman and Chief Values Officer, the Tilleke &amp; Gibbins Museum of Counterfeit Goods has grown from a collection of approximately 100 products when it was first created to more than 4,000 IP-infringing goods today, making it one of the largest of its kind in the world. The museum houses a collection covering 14 broad categories of goods, including clothing, footwear, watches and eyewear, accessories, cosmetics and perfumes, food and household products, drugs, alcohol and cigarettes, copyrighted works, stationery and office supplies, automotive parts, tools, electrical devices, and miscellaneous products.</p>',
	) ) )
	. tng_seed_block( 'acf/mus-cases', array_merge(
		tng_seed_data( 'mca', array( 'label' => 'Case Studies' ) ),
		tng_seed_flat( 'mca', 'items', array( $case, $case, $case ), array( 'title', 'text' ) )
	) )
	. $more_from( array(
		array( 'label' => 'Overview', 'url' => $url( $about ) ),
		array( 'label' => 'History', 'url' => $url( $history ) ),
		array( 'label' => 'Awards & Rankings', 'url' => $url( $awards ) ),
	) );
	list( , $museum_state ) = tng_seed_page_content( 'Museum of Counterfeit Goods', $museum_content, array( 'post_parent' => $about, 'menu_order' => 5, 'post_name' => 'museum-of-counterfeit-goods' ) );

	$log[] = '1.1.2 About child pages: Pro Bono ' . $pro_bono_state . ', CSR ' . $csr_state . ', Museum of Counterfeit Goods ' . $museum_state;
}

/* ---------------- 1.1.4: About child page Textile Collection ---------------- */

/**
 * Create-only, like 1.1.2: the page is created under About when no page of
 * that title exists; nothing else is touched. Images fall back to the bundled
 * Figma exports (info-hero / mus-collection by slug, tex-gallery's nine).
 * The social links are placeholders ('#') until the collection's accounts
 * are known.
 */
function tng_seed_v114( &$log ) {
	$about = tng_seed_find_post( 'page', 'About' );
	if ( ! $about ) {
		$log[] = '1.1.4: no About page, Textile Collection skipped';
		return;
	}
	$url = static function ( $id ) {
		return $id ? get_permalink( $id ) : '#';
	};

	$content = tng_seed_block( 'acf/info-hero', tng_seed_data( 'ifh', array(
		'label'        => 'Inquiries',
		'contact_name' => 'Wipawee Tiyawes',
		'phone'        => '+66 2056 5577',
		'email'        => 'wipawee.t@tilleke.com',
	) ) )
	. tng_seed_block( 'acf/overview', tng_seed_data( 'ovw', array(
		'variant'    => 'info',
		'section_id' => 'overview',
		'label'      => 'Overview',
		'heading'    => 'We maintain a diverse collection of textiles to represent Southeast Asia’s rich cultures, and open them to the public to educate people on this ancient and beautiful art form.',
		'text'       => '<p>The mission of our textile collection is to collect, display, and preserve the textiles, in proximity to the geographical area in which they were created. Fabrics from various ethnic groups constituting Thailand’s diverse peoples form the foundation of the collection, supplemented by textiles from neighboring countries, including Cambodia, Indonesia, Laos, Malaysia, Myanmar, and Vietnam—plus some Indian and southern Chinese pieces for comparative study.</p><p>Originally starting with just 50 pieces, the wide selection of clothing types, ceremonial items, and domestic accessories now forms one of the largest collections of its kind in the world. Pieces from the collection are permanently on display in Tilleke &amp; Gibbins’ Bangkok office, and we are also proud to regularly loan pieces to both local and international exhibitions. They can also be viewed by individual scholars and are available for educational events such as lectures and special activities.</p><p>Our textile storage room adheres to international museum standards for handling and maintaining a textile collection. It is windowless to prevent sunlight damage to the textiles, and is instead illuminated by LED lighting. Temperature and humidity are carefully controlled, and advanced air filtration and fire suppression systems are in place. It is also equipped with a special freezer (used to treat newly acquired pieces), storage trays that accommodate 20–30 textiles each, and materials such as acid-free tissue paper and Mylar, which are used for safely storing the textiles.</p>',
	) ) )
	. tng_seed_block( 'acf/tex-gallery', array_merge(
		tng_seed_data( 'txg', array( 'label' => 'Tilleke & Gibbins’ Textile Collection' ) ),
		tng_seed_flat( 'txg', 'socials', array(
			array( 'network' => 'instagram', 'url' => '#' ),
			array( 'network' => 'facebook', 'url' => '#' ),
			array( 'network' => 'x', 'url' => '#' ),
			array( 'network' => 'linkedin', 'url' => '#' ),
		), array( 'network', 'url' ) )
	) )
	. tng_seed_block( 'acf/mus-collection', tng_seed_data( 'mco', array(
		'label'   => 'History',
		'heading' => 'Interior design ideas become a museum-quality collection.',
		'text'    => '<p>The Textile Collection began in 1987, when David Lyman chose to decorate the firm’s previous offices at the Tilleke &amp; Gibbins Building and Tonson Building with traditional Southeast Asian textiles. The further acquisition of 78 rare textiles of museum quality from northeastern Laos four years later changed the initial objective of using textiles as permanent office decoration to creating a collection of textiles to preserve culture and educate the public.<br>Tilleke &amp; Gibbins’ Textile Collection is now one of the largest and most impressive in Thailand, containing over 2,000 museum-quality Southeast Asian textiles representing the diverse cultures of Southeast Asia. A number of pieces are exhibited on the walls of the firm’s head office in Bangkok, with the demonstration pieces rotating regularly to make the collection’s many treasures available to viewers. The collection today is an affirmation of Tilleke &amp; Gibbins’ deep connection to and support for the culture of our home region.</p>',
	) ) )
	. tng_seed_block( 'acf/more-from', array_merge(
		tng_seed_data( 'mfr', array( 'arrows' => '1' ) ),
		tng_seed_flat( 'mfr', 'links', array(
			array( 'label' => 'Overview', 'url' => $url( $about ) ),
			array( 'label' => 'History', 'url' => $url( tng_seed_find_post( 'page', 'History' ) ) ),
			array( 'label' => 'Awards & Rankings', 'url' => $url( tng_seed_find_post( 'page', 'Awards & Rankings' ) ) ),
		), array( 'label', 'url' ) )
	) );
	list( , $state ) = tng_seed_page_content( 'Textile Collection', $content, array( 'post_parent' => $about, 'menu_order' => 6, 'post_name' => 'textile-collection' ) );

	$log[] = '1.1.4 About child page: Textile Collection ' . $state;
}

/* ---------------- 1.1.5: Careers → Job Opportunities ---------------- */

/**
 * Create-only: the all-jobs page is created under Careers when no page of
 * that title exists (no mock: career path hero + job list + More from).
 */
function tng_seed_v115( &$log ) {
	$careers = tng_seed_find_post( 'page', 'Careers' );
	if ( ! $careers ) {
		$log[] = '1.1.5: no Careers page, Job Opportunities skipped';
		return;
	}
	$url = static function ( $title ) {
		$id = tng_seed_find_post( 'page', $title );
		return $id ? get_permalink( $id ) : '#';
	};
	$content = tng_seed_block( 'acf/path-hero', tng_seed_data( 'pth', array(
		'intro' => 'We are always seeking great people to grow our amazing team. Browse our open positions across Southeast Asia, or filter them by country.',
	) ) )
	. tng_seed_block( 'acf/job-list' )
	. tng_seed_block( 'acf/more-from', array_merge(
		tng_seed_data( 'mfr', array( 'arrows' => '1' ) ),
		tng_seed_flat( 'mfr', 'links', array(
			array( 'label' => 'Legal Professionals', 'url' => $url( 'Legal Professionals' ) ),
			array( 'label' => 'Operations Professionals', 'url' => $url( 'Operations Professionals' ) ),
			array( 'label' => 'Internships', 'url' => $url( 'Internships' ) ),
		), array( 'label', 'url' ) )
	) );
	list( , $state ) = tng_seed_page_content( 'Job Opportunities', $content, array( 'post_parent' => $careers, 'menu_order' => 4, 'post_name' => 'job-opportunities' ) );
	$log[] = '1.1.5 Careers page: Job Opportunities ' . $state;
}

/* ---------------- 1.3.0: pages with no mock ---------------- */

/**
 * Page at a path (old site URLs kept: no redirects needed). Create-only, with
 * the one guarded exception of an untouched under-development placeholder.
 * The parent is the page at the path's dirname (it must exist).
 * Returns [id, state]; state = created / filled (was the placeholder) / kept /
 * skipped (no parent).
 */
function tng_seed_page_at( $path, $title, $content, $template = 'tng-blank', $args = array() ) {
	$placeholder = '<!-- wp:tng/under-development /-->';
	$page        = get_page_by_path( $path );
	if ( $page ) {
		if ( trim( (string) $page->post_content ) === $placeholder ) {
			wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $content ) ) ); // keeps a revision
			update_post_meta( $page->ID, '_wp_page_template', $template );
			return array( $page->ID, 'filled (was the placeholder)' );
		}
		return array( $page->ID, 'kept' );
	}
	$parent = 0;
	if ( false !== strpos( $path, '/' ) ) {
		$parent_page = get_page_by_path( dirname( $path ) );
		if ( ! $parent_page ) {
			return array( 0, 'skipped (no parent page ' . dirname( $path ) . ')' );
		}
		$parent = $parent_page->ID;
	}
	$id = wp_insert_post(
		wp_slash(
			array_merge(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => basename( $path ),
					'post_parent'  => $parent,
					'post_content' => $content,
				),
				$args
			)
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return array( 0, 'failed' );
	}
	update_post_meta( $id, '_wp_page_template', $template );
	return array( (int) $id, 'created' );
}

/**
 * Page content from a seed spec (wp/tng-core/seed/<name>.json, generated
 * from the static page): a list of blocks
 *   { "block": "acf/overview", "prefix": "ovw", "className": "hist-overview",
 *     "data": { field: value }, "repeaters": { name: [ {sub: value} ] } }
 * Strings may hold {page:<path>} (that page's URL, "#" if missing),
 * {term:<taxonomy>/<slug>} (a term link) and {professional:<name>} (the ID).
 */
function tng_seed_spec( $name ) {
	$spec = json_decode( (string) file_get_contents( TNG_CORE_DIR . 'seed/' . $name . '.json' ), true );
	$fill = static function ( $v ) use ( &$fill ) {
		if ( is_array( $v ) ) {
			return array_map( $fill, $v );
		}
		return preg_replace_callback(
			'/\{(page|term|professional):([^}]+)\}/',
			static function ( $m ) {
				if ( 'page' === $m[1] ) {
					return tng_page_url( $m[2] );
				}
				if ( 'professional' === $m[1] ) { // ID of the published professional with that exact name, else ''
					$ids = get_posts( array( 'post_type' => 'professional', 'title' => $m[2], 'posts_per_page' => 1, 'fields' => 'ids' ) );
					return $ids ? (string) $ids[0] : '';
				}
				list( $tax, $slug ) = explode( '/', $m[2], 2 );
				$term = get_term_by( 'slug', $slug, $tax );
				return $term ? tng_term_link( $term ) : '#';
			},
			(string) $v
		);
	};
	$out = '';
	foreach ( (array) $spec as $b ) {
		$p    = isset( $b['prefix'] ) ? $b['prefix'] : '';
		$data = $p && ! empty( $b['data'] ) ? tng_seed_data( $p, $fill( $b['data'] ) ) : array();
		foreach ( isset( $b['repeaters'] ) ? $b['repeaters'] : array() as $rep => $rows ) {
			$subs = array();
			foreach ( $rows as $row ) {
				$subs = array_unique( array_merge( $subs, array_keys( $row ) ) );
			}
			$data = array_merge( $data, tng_seed_flat( $p, $rep, $fill( $rows ), $subs ) );
		}
		$block = tng_seed_block( $b['block'], $data );
		if ( ! empty( $b['className'] ) ) { // Advanced → Additional CSS class
			$block = preg_replace( '/^<!-- wp:([a-z\/-]+) \{/', '<!-- wp:$1 {"className":"' . esc_attr( $b['className'] ) . '",', $block, 1 );
		}
		$out .= $block;
	}
	return $out;
}

function tng_seed_v130( &$log ) {
	// Legal / text pages: the copy is core blocks (wp/tng-core/seed/*.html,
	// from the static pages), edited like any page.
	$text_pages = array(
		// path => [title, menu label, seed file, menu order]
		'privacy-policy-term-of-use'                         => array( 'Privacy Policy', '', 'privacy-policy', 1 ),
		'privacy-policy-term-of-use/pdpp_candidates'         => array( 'Personal Data Protection Policy for Candidates', 'Candidates', 'privacy-candidates', 1 ),
		'privacy-policy-term-of-use/pdpp_clients'            => array( 'Personal Data Protection Policy for Clients', 'Clients', 'privacy-clients', 2 ),
		'privacy-policy-term-of-use/pdpd_contract-parties'   => array( 'Personal Data Protection Policy for Contract Parties', 'Contract Parties', 'privacy-contract-parties', 3 ),
		'privacy-policy-term-of-use/pdpd_video-surveillance' => array( 'Personal Data Protection Policy for Video Surveillance', 'Video Surveillance', 'privacy-video-surveillance', 4 ),
		'legal-notice'                                       => array( 'Legal Notice', '', 'legal-notice', 2 ),
		'legal-notice/affiliates-partner-firms'              => array( 'Tilleke & Gibbins Affiliates and Partner Firms', 'Affiliates and Partner Firms', 'legal-notice-affiliates', 1 ),
		'fraudulent-communications'                          => array( 'Fraudulent Communications', '', 'fraudulent-communications', 3 ),
	);
	foreach ( $text_pages as $path => $p ) {
		$content = (string) file_get_contents( TNG_CORE_DIR . 'seed/' . $p[2] . '.html' );
		list( $id, $state ) = tng_seed_page_at( $path, $p[0], $content, 'tng-text', array( 'menu_order' => $p[3] ) );
		if ( 'created' === $state && $p[1] ) {
			update_field( 'field_tng_txp_nav_label', $p[1], $id );
		}
		$log[] = '1.3.0 text page /' . $path . '/: ' . $state;
	}
}

/**
 * 1.3.1+: pages built from block specs (seed/<spec>.json). Each fills its
 * untouched placeholder or is created at its path; anything else is kept.
 */
function tng_seed_v131( &$log ) {
	$pages = array(
		// path => [title, spec, menu order, page language (optional)]
		'careers/operations-professionals' => array( 'Operations Professionals', 'careers-operations', 2 ),
		'careers/internships'              => array( 'Internships', 'careers-internships', 3 ),
		'careers/recruitment-process'      => array( 'Our Recruitment Process', 'recruitment-process', 5 ),
		'about/history'                    => array( 'History', 'about-history', 1 ),
		'services'                         => array( 'Services', 'services', 0 ),
		'global-reach'                     => array( 'Global Reach', 'global-reach', 0 ),
		'japan-desk'                       => array( 'ジャパンデスク', 'japan-desk', 0, 'ja' ),
		'china-desk'                       => array( '中国业务组', 'china-desk', 0, 'zh-Hans' ),
	);
	foreach ( $pages as $path => $p ) {
		list( $id, $state ) = tng_seed_page_at( $path, $p[0], tng_seed_spec( $p[1] ), 'tng-blank', array( 'menu_order' => $p[2] ) );
		if ( ! empty( $p[3] ) && in_array( $state, array( 'created', 'filled (was the placeholder)' ), true ) ) {
			update_post_meta( $id, '_tng_lang', $p[3] ); // <main lang> (templates.php)
		}
		$log[] = '1.3.x page /' . $path . '/: ' . $state;
	}
}
