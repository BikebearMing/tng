// Generates wp/scf-import.json for SCF (ACF) Tools -> Import.
// Field keys are deterministic (field_tng_<prefix>_<name>) — the plugin seeder
// (tng-core/inc/seed.php) references the same keys. Keep them in sync.
import { writeFileSync } from 'node:fs';

const out = [];

/* ---------------- helpers ---------------- */
const fld = (prefix, name, type, label, extra = {}) => ({
  key: `field_tng_${prefix}_${name}`,
  label,
  name,
  'aria-label': '',
  type,
  instructions: '',
  required: 0,
  conditional_logic: 0,
  wrapper: { width: '', class: '', id: '' },
  ...extra,
});
const text = (p, n, l, def = '', extra = {}) => fld(p, n, 'text', l, { default_value: def, maxlength: '', placeholder: '', prepend: '', append: '', ...extra });
const textarea = (p, n, l, def = '', extra = {}) => fld(p, n, 'textarea', l, { default_value: def, maxlength: '', rows: 4, placeholder: '', new_lines: 'br', ...extra });
const toggle = (p, n, l, def = 1) => fld(p, n, 'true_false', l, { message: '', default_value: def, ui: 1, ui_on_text: '', ui_off_text: '' });
const image = (p, n, l, extra = {}) => fld(p, n, 'image', l, { return_format: 'url', library: 'all', min_width: '', min_height: '', min_size: '', max_width: '', max_height: '', max_size: '', mime_types: '', preview_size: 'medium', ...extra });
const file = (p, n, l, extra = {}) => fld(p, n, 'file', l, { return_format: 'url', library: 'all', min_size: '', max_size: '', mime_types: '', ...extra });
const wysiwyg = (p, n, l, extra = {}) => fld(p, n, 'wysiwyg', l, { default_value: '', tabs: 'all', toolbar: 'full', media_upload: 1, delay: 0, ...extra });
const select = (p, n, l, choices, def) => fld(p, n, 'select', l, { choices, default_value: def, allow_null: 0, multiple: 0, ui: 0, return_format: 'value', ajax: 0, placeholder: '' });
const relationship = (p, n, l, postType, max = '', extra = {}) => fld(p, n, 'relationship', l, {
  post_type: [postType], post_status: [''], taxonomy: [], filters: ['search'], return_format: 'id',
  min: '', max, elements: '', bidirectional: 0, bidirectional_target: [], ...extra,
});
const postObject = (p, n, l, postType) => fld(p, n, 'post_object', l, {
  post_type: [postType], post_status: [''], taxonomy: [], return_format: 'id', multiple: 0, allow_null: 1, bidirectional: 0, ui: 1, bidirectional_target: [],
});
const repeater = (p, n, l, subs, extra = {}) => fld(p, n, 'repeater', l, {
  layout: 'block', pagination: 0, min: 0, max: 0, collapsed: '', button_label: `Add ${l.toLowerCase().replace(/s$/, '')}`,
  rows_per_page: 20, sub_fields: subs, ...extra,
});
// sub-field of a repeater: key carries the parent name for uniqueness
const sub = (p, parent, name, maker, ...args) => {
  const f = maker(p, name, ...args);
  f.key = `field_tng_${p}_${parent}_${name}`;
  f.parent_repeater = `field_tng_${p}_${parent}`;
  return f;
};
const tab = (p, n, l) => fld(p, n, 'tab', l, { placement: 'top', endpoint: 0 });
const message = (p, n, msg) => fld(p, n, 'message', '', { message: msg, new_lines: 'wpautop', esc_html: 0 });

const group = (slug, title, fields, location, extra = {}) => out.push({
  key: `group_tng_${slug}`,
  title,
  fields,
  location,
  menu_order: 0,
  position: 'normal',
  style: 'default',
  label_placement: 'top',
  instruction_placement: 'label',
  hide_on_screen: '',
  active: true,
  description: '',
  show_in_rest: 0,
  ...extra,
});
const onPostType = (pt) => [[{ param: 'post_type', operator: '==', value: pt }]];
const onBlock = (b) => [[{ param: 'block', operator: '==', value: b }]];
const onTax = (t) => [[{ param: 'taxonomy', operator: '==', value: t }]];
const onOptions = (page) => [[{ param: 'options_page', operator: '==', value: page }]];

/* ---------------- post types ---------------- */
const postType = (slug, singular, plural, opts = {}) => out.push({
  key: `post_type_tng_${slug}`,
  title: plural,
  menu_order: 0,
  active: true,
  post_type: slug,
  advanced_configuration: true,
  import_source: '',
  import_date: '',
  labels: { name: plural, singular_name: singular },
  description: '',
  public: true,
  hierarchical: false,
  exclude_from_search: false,
  publicly_queryable: true,
  show_ui: true,
  show_in_menu: true,
  admin_menu_parent: '',
  show_in_admin_bar: true,
  show_in_nav_menus: true,
  show_in_rest: true,
  rest_base: '',
  rest_namespace: 'wp/v2',
  rest_controller_class: 'WP_REST_Posts_Controller',
  menu_position: '',
  menu_icon: { type: 'dashicons', value: opts.icon || 'dashicons-admin-post' },
  rename_capabilities: false,
  singular_capability_name: 'post',
  plural_capability_name: 'posts',
  supports: opts.supports || ['title', 'editor', 'excerpt', 'thumbnail'],
  taxonomies: opts.taxonomies || [],
  has_archive: false,
  has_archive_slug: '',
  rewrite: { permalink_rewrite: 'custom_permalink', slug: opts.rewrite || slug, with_front: '0', feeds: '0', pages: '1' },
  query_var: 'post_type_key',
  query_var_name: '',
  can_export: true,
  delete_with_user: false,
  register_meta_box_cb: '',
  enter_title_here: opts.titleHere || '',
});

const taxonomy = (slug, singular, plural, objectTypes, hierarchical = true) => out.push({
  key: `taxonomy_tng_${slug}`,
  title: plural,
  menu_order: 0,
  active: true,
  taxonomy: slug,
  object_type: objectTypes,
  advanced_configuration: true,
  import_source: '',
  import_date: '',
  labels: { name: plural, singular_name: singular },
  description: '',
  public: true,
  publicly_queryable: true,
  hierarchical,
  show_ui: true,
  show_in_menu: true,
  show_in_nav_menus: true,
  show_in_rest: true,
  rest_base: '',
  rest_namespace: 'wp/v2',
  rest_controller_class: 'WP_REST_Terms_Controller',
  show_tagcloud: false,
  show_in_quick_edit: true,
  show_admin_column: true,
  rewrite: { permalink_rewrite: 'custom_permalink', slug, with_front: '0', rewrite_hierarchical: '0' },
  query_var: 'post_type_key',
  query_var_name: '',
  default_term: { default_term_enabled: false },
  sort: false,
  meta_box: 'default',
  meta_box_cb: '',
  meta_box_sanitize_cb: '',
  capabilities: {},
});

postType('insight', 'Insight', 'Insights', {
  icon: 'dashicons-lightbulb',
  supports: ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
  taxonomies: ['industry', 'practice', 'location', 'insight_category', 'post_tag'],
  rewrite: 'insights',
});
postType('professional', 'Professional', 'Professionals', {
  icon: 'dashicons-groups',
  supports: ['title', 'custom-fields'],
  taxonomies: ['industry', 'practice', 'location', 'position', 'language'],
  rewrite: 'professionals',
  titleHere: 'Full name',
});
postType('award', 'Award', 'Awards', {
  icon: 'dashicons-awards',
  supports: ['title', 'editor', 'excerpt', 'custom-fields'],
  taxonomies: ['industry', 'practice', 'location'],
  rewrite: 'awards',
});
postType('job', 'Job', 'Jobs', {
  icon: 'dashicons-portfolio',
  supports: ['title', 'editor', 'custom-fields'],
  taxonomies: ['location'],
  rewrite: 'jobs',
});

taxonomy('industry', 'Industry', 'Industries', ['professional', 'insight', 'award']);
taxonomy('practice', 'Practice', 'Practices', ['professional', 'insight', 'award']);
taxonomy('location', 'Location', 'Locations', ['professional', 'insight', 'award', 'job']);
taxonomy('position', 'Position', 'Positions', ['professional']);
taxonomy('language', 'Language', 'Languages', ['professional']);
taxonomy('insight_category', 'Insight Category', 'Insight Categories', ['insight']);

/* ---------------- post/term/options field groups ---------------- */

// Professional
group('professional', 'Professional', [
  tab('pro', 'tab_profile', 'Profile'),
  image('pro', 'portrait', 'Portrait (transparent cutout, 4:5, min 772px wide)', { mime_types: 'png,webp' }),
  image('pro', 'headshot', 'Headshot (cropped photo, used on insight sidebars)'),
  textarea('pro', 'position_title', 'Position (as displayed)', '', { rows: 2 }),
  text('pro', 'phone', 'Phone'),
  text('pro', 'email', 'Email'),
  tab('pro', 'tab_overview', 'Overview'),
  textarea('pro', 'lead', 'Lead paragraph', '', { rows: 4, new_lines: '' }),
  wysiwyg('pro', 'biography', 'Biography', { media_upload: 0 }),
  repeater('pro', 'education', 'Education', [
    sub('pro', 'education', 'entry', textarea, 'Entry', '', { rows: 2 }),
  ]),
  tab('pro', 'tab_experience', 'Experience'),
  wysiwyg('pro', 'experience', 'Experience (bullet list)', { media_upload: 0 }),
  tab('pro', 'tab_sections', 'Sections'),
  toggle('pro', 'show_pro_hero', 'Show hero'),
  toggle('pro', 'show_pro_overview', 'Show overview'),
  toggle('pro', 'show_pro_experience', 'Show experience'),
  toggle('pro', 'show_pro_insights', 'Show insights'),
  text('pro', 'insights_heading', 'Insights heading', 'Insights'),
  toggle('pro', 'show_pro_awards', 'Show awards'),
  text('pro', 'awards_heading', 'Awards heading', 'Awards & Rankings'),
], onPostType('professional'));

// Insight — Standard
group('insight', 'Insight — Standard', [
  toggle('ins', 'show_in_hero', 'Show hero'),
  toggle('ins', 'show_featured_image', 'Show featured image'),
  toggle('ins', 'show_related_professionals', 'Show related professionals (sidebar)'),
  text('ins', 'related_heading', 'Related professionals heading', 'Related professionals'),
  relationship('ins', 'related_professionals', 'Related professionals', 'professional'),
], onPostType('insight'));

// Insight — Module template
group('insight_module', 'Insight — Module template', [
  message('mod', 'note', 'These fields are used when the post is assigned the "Insight — Module" template.'),
  tab('mod', 'tab_hero', 'Hero'),
  toggle('mod', 'show_in_hero_module', 'Show hero'),
  image('mod', 'module_background', 'Background image (min 1440×567)'),
  textarea('mod', 'module_lead', 'Lead', '', { rows: 3, new_lines: '' }),
  file('mod', 'module_report', 'Report (PDF)', { mime_types: 'pdf' }),
  text('mod', 'module_report_label', 'Report card label', 'Download report'),
  tab('mod', 'tab_panels', 'Panels'),
  toggle('mod', 'show_module_panels', 'Show panels'),
  image('mod', 'panels_background', 'Panels background (default: teal texture)'),
  repeater('mod', 'panels', 'Panels', [
    sub('mod', 'panels', 'heading', text, 'Heading'),
    sub('mod', 'panels', 'body', wysiwyg, 'Body', { media_upload: 0 }),
    sub('mod', 'panels', 'theme', select, 'Theme', { light: 'Light', dark: 'Dark' }, 'light'),
    sub('mod', 'panels', 'link_label', text, 'Link label (optional)'),
    sub('mod', 'panels', 'link_url', text, 'Link URL'),
  ]),
  tab('mod', 'tab_findings', 'Key findings'),
  toggle('mod', 'show_module_findings', 'Show key findings'),
  text('mod', 'findings_heading', 'Heading', 'Key findings'),
  repeater('mod', 'findings', 'Findings', [
    sub('mod', 'findings', 'title', text, 'Title'),
    sub('mod', 'findings', 'text', textarea, 'Text', '', { rows: 3, new_lines: 'wpautop' }),
  ]),
  tab('mod', 'tab_more', 'Other sections'),
  toggle('mod', 'show_module_professionals', 'Show related professionals'),
  text('mod', 'module_professionals_heading', 'Related professionals heading', 'Related Professionals'),
  toggle('mod', 'show_module_download', 'Show download banner'),
  text('mod', 'download_label', 'Download banner label', 'Download report'),
  image('mod', 'download_image', 'Download banner image (default: hero background)'),
  toggle('mod', 'show_module_share', 'Show share row'),
  text('mod', 'share_heading', 'Share heading', 'Share the insight'),
  toggle('mod', 'show_module_insights', 'Show related insights'),
  text('mod', 'module_insights_heading', 'Related insights heading', 'Related Insights'),
  relationship('mod', 'module_insights', 'Related insights (empty = latest sharing this insight’s industries)', 'insight', 3),
], onPostType('insight'), { menu_order: 1 });

// Award
group('award', 'Award', [
  relationship('awd', 'related_professionals', 'Related professionals', 'professional'),
], onPostType('award'));

// Location term fields
group('location', 'Location details', [
  image('loc', 'flag', 'Flag (default: bundled flag SVG for the six countries)'),
  image('loc', 'image', 'Image (megamenu + homepage location card)'),
  textarea('loc', 'intro', 'Intro (future location page)', '', { rows: 3, new_lines: '' }),
  message('loc', 'office_note', 'Office details below are used on the Contact page. They apply to city terms (children of a country).'),
  text('loc', 'office_name', 'Office name'),
  text('loc', 'phone', 'Phone'),
  text('loc', 'email', 'Email'),
  textarea('loc', 'address', 'Address', '', { rows: 4 }),
  text('loc', 'map_query', 'Map query (address or place for the Google Maps embed)'),
], onTax('location'));

// Site Header options
group('site_header', 'Site Header', [
  image('hdr', 'logo', 'Logo (SVG/PNG; empty = text stand-in)'),
  repeater('hdr', 'top_links', 'Top row links', [
    sub('hdr', 'top_links', 'label', text, 'Label'),
    sub('hdr', 'top_links', 'url', text, 'URL'),
  ]),
  text('hdr', 'professionals_url', 'Professionals link URL'),
  text('hdr', 'insights_url', 'Insights link URL', '#'),
  text('hdr', 'global_reach_url', 'Global Reach URL (Locations megamenu)', '#'),
  text('hdr', 'search_label', 'Search label', 'Search'),
  text('hdr', 'lang_label', 'Language label', 'ENG'),
  text('hdr', 'lang_url', 'Language switch URL', '#'),
], onOptions('tng-site-header'));

// Site Footer options
group('site_footer', 'Site Footer', [
  image('ftr', 'logo', 'Logo (empty = text stand-in)'),
  repeater('ftr', 'social_links', 'Social links', [
    sub('ftr', 'social_links', 'network', select, 'Network', { facebook: 'Facebook', youtube: 'YouTube', linkedin: 'LinkedIn' }, 'facebook'),
    sub('ftr', 'social_links', 'url', text, 'URL'),
  ]),
  text('ftr', 'newsletter_heading', 'Newsletter heading', 'Subscribe to our newsletter'),
  text('ftr', 'newsletter_action', 'Newsletter form action URL'),
  repeater('ftr', 'networks', 'Network logos', [
    sub('ftr', 'networks', 'name', text, 'Name'),
    sub('ftr', 'networks', 'style', select, 'Size preset', { lexmundi: 'Lex Mundi', multilaw: 'Multilaw', dna: 'Drew Network Asia' }, 'lexmundi'),
    sub('ftr', 'networks', 'image', image, 'Logo (empty = bundled logo for the preset)'),
    sub('ftr', 'networks', 'url', text, 'URL'),
  ]),
  repeater('ftr', 'quick_links_1', 'Quick links — column 1', [
    sub('ftr', 'quick_links_1', 'label', text, 'Label'),
    sub('ftr', 'quick_links_1', 'url', text, 'URL'),
  ]),
  repeater('ftr', 'quick_links_2', 'Quick links — column 2', [
    sub('ftr', 'quick_links_2', 'label', text, 'Label'),
    sub('ftr', 'quick_links_2', 'url', text, 'URL'),
  ]),
  text('ftr', 'copyright', 'Copyright', '© 2026 Tilleke & Gibbins International Ltd. All rights reserved.'),
  repeater('ftr', 'legal_links', 'Legal links', [
    sub('ftr', 'legal_links', 'label', text, 'Label'),
    sub('ftr', 'legal_links', 'url', text, 'URL'),
  ]),
], onOptions('tng-site-footer'));

/* ---------------- block field groups ---------------- */

group('block_featured_banner', 'Block: Featured banner', [
  toggle('fb', 'show_featured_banner', 'Show section'),
  image('fb', 'background_image', 'Background image (default: bundled banner)'),
  postObject('fb', 'featured_insight', 'Featured insight (empty = latest)', 'insight'),
  relationship('fb', 'recent_insights', 'Recent insights (empty = latest 4)', 'insight', 4),
  text('fb', 'view_all_url', '"View all" URL', '#'),
], onBlock('acf/home-featured-banner'));

group('block_intro_stats', 'Block: Intro + stats + regional presence', [
  toggle('his', 'show_intro_stats', 'Show section'),
  text('his', 'heading', 'Heading', "Southeast Asia's Leading Law Firm"),
  textarea('his', 'body', 'Body', 'Tilleke & Gibbins is a leading full-service Southeast Asian law firm proudly serving international and local businesses across six countries. We are committed to helping our clients achieve sustainable success in the region, working with them to provide unparalleled legal expertise, rooted in practical commercial insight.', { rows: 5, new_lines: '' }),
  text('his', 'cta_label', 'CTA label', 'Explore'),
  text('his', 'cta_url', 'CTA URL', '#'),
  image('his', 'background_image', 'Background (default: bundled teal texture)'),
  repeater('his', 'counters', 'Counters', [
    sub('his', 'counters', 'number', text, 'Number (e.g. 250+)'),
    sub('his', 'counters', 'label', text, 'Label'),
  ]),
  toggle('his', 'show_regional_presence', 'Show regional presence panel'),
  text('his', 'rp_heading', 'Panel heading', 'Our Regional Presence'),
  image('his', 'rp_map_image', 'Map image (default: bundled map)'),
], onBlock('acf/home-intro-stats'));

group('block_home_insights', 'Block: Insights by industry', [
  toggle('hin', 'show_insights', 'Show section'),
  text('hin', 'label', 'Label', 'Insights'),
  text('hin', 'view_all_url', '"View all" URL', '#'),
  fld('hin', 'industries', 'taxonomy', 'Industry tabs (empty = first 5 industries)', {
    taxonomy: 'industry', add_term: 0, save_terms: 0, load_terms: 0, return_format: 'id',
    field_type: 'multi_select', allow_null: 1, allow_in_bindings: 0, bidirectional: 0, multiple: 0, bidirectional_target: [],
  }),
], onBlock('acf/home-insights'));

group('block_home_careers', 'Block: Careers', [
  toggle('hca', 'show_careers', 'Show section'),
  text('hca', 'heading', 'Heading', 'Build your career with Tilleke & Gibbins'),
  text('hca', 'cta_label', 'CTA label', 'Explore opportunities'),
  text('hca', 'cta_url', 'CTA URL', '#'),
  text('hca', 'note', 'Note', "We're always looking for great people to join our team"),
  image('hca', 'badge_image', 'Badge (default: bundled ALB badge)'),
  repeater('hca', 'portraits', 'Marquee portraits (default: bundled portraits)', [
    sub('hca', 'portraits', 'image', image, 'Portrait'),
  ]),
], onBlock('acf/home-careers'));

group('block_home_awards', 'Block: Awards & Rankings', [
  toggle('haw', 'show_awards', 'Show section'),
  text('haw', 'label', 'Label', 'Awards & Rankings'),
  text('haw', 'heading', 'Heading', 'Tilleke & Gibbins Achieves Top Results in Legal 500 Asia-Pacific 2026 Rankings'),
  image('haw', 'logo', 'Logo (default: bundled Legal 500 logo)'),
  textarea('haw', 'body', 'Body', 'Tilleke & Gibbins has demonstrated continued excellence across all six jurisdictions where the firm operates in the 2026 edition of the Legal 500 Asia-Pacific rankings. The recently released rankings showcase the firm’s outstanding performance with 29 practice area recognitions and 34 individual rankings—an increase from 30 individual recognitions in 2025.', { rows: 5, new_lines: '' }),
  text('haw', 'read_more_label', 'Read more label', 'Read more'),
  text('haw', 'read_more_url', 'Read more URL', '#'),
  text('haw', 'card_text', 'Card text', 'Learn more about our latest recognitions'),
  text('haw', 'card_url', 'Card URL', '#'),
], onBlock('acf/home-awards'));

group('block_page_hero', 'Block: Page hero', [
  toggle('phe', 'show_page_hero', 'Show section'),
  text('phe', 'title', 'Title (empty = page title)'),
  textarea('phe', 'intro', 'Intro', '', { rows: 4, new_lines: '' }),
  toggle('phe', 'compact', 'Compact (no min height)', 0),
], onBlock('acf/page-hero'));

group('block_pro_filter', 'Block: Professionals filter', [
  toggle('pfi', 'show_pro_filter', 'Show section'),
  text('pfi', 'search_placeholder', 'Search placeholder', 'Search by name, expertise or industry'),
], onBlock('acf/pro-filter'));

group('block_pro_grid', 'Block: Professionals grid', [
  toggle('pgr', 'show_pro_grid', 'Show section'),
  fld('pgr', 'page_size', 'number', 'Cards per page', { default_value: 15, min: 3, max: 60, step: 1, placeholder: '', prepend: '', append: '' }),
], onBlock('acf/pro-grid'));

group('block_contact_offices', 'Block: Contact offices', [
  toggle('cof', 'show_contact_offices', 'Show section'),
  message('cof', 'note', 'Offices come from the Location taxonomy: city terms (children of a country) with an office name. Edit them under Locations.'),
], onBlock('acf/contact-offices'));

group('block_careers_hero', 'Block: Careers hero', [
  toggle('che', 'show_careers_hero', 'Show section'),
  text('che', 'title', 'Title (empty = page title)'),
  textarea('che', 'intro', 'Intro', '', { rows: 4, new_lines: '' }),
  text('che', 'link_label', 'Link label', 'All job opportunities'),
  text('che', 'link_url', 'Link URL', '#'),
  image('che', 'image', 'Image (min 1394×942)'),
], onBlock('acf/careers-hero'));

group('block_careers_paths', 'Block: Career paths', [
  toggle('cpa', 'show_career_paths', 'Show section'),
  repeater('cpa', 'paths', 'Cards', [
    sub('cpa', 'paths', 'title', text, 'Title'),
    sub('cpa', 'paths', 'text', textarea, 'Text', '', { rows: 2, new_lines: '' }),
    sub('cpa', 'paths', 'url', text, 'URL'),
  ]),
], onBlock('acf/careers-paths'));

group('block_careers_values', 'Block: Careers values slider', [
  toggle('cva', 'show_careers_values', 'Show section'),
  text('cva', 'heading', 'Heading', 'Together at Tilleke & Gibbins'),
  repeater('cva', 'slides', 'Slides', [
    sub('cva', 'slides', 'title', text, 'Title'),
    sub('cva', 'slides', 'text', textarea, 'Text', '', { rows: 4, new_lines: 'wpautop' }),
    sub('cva', 'slides', 'icon', image, 'Image (shown up to 236px tall)'),
  ]),
], onBlock('acf/careers-values'));

group('block_careers_people', 'Block: Careers people', [
  toggle('cpe', 'show_careers_people', 'Show section'),
  text('cpe', 'label', 'Label', 'Career Development'),
  text('cpe', 'heading', 'Heading', 'We value people for who they are.'),
  wysiwyg('cpe', 'text', 'Text', { media_upload: 0 }),
  image('cpe', 'image', 'Image (min 1410×1386)'),
], onBlock('acf/careers-people'));

group('block_careers_cta', 'Block: Careers CTA', [
  toggle('cct', 'show_careers_cta', 'Show section'),
  text('cct', 'heading', 'Heading', 'Curious about our recruitment process?'),
  text('cct', 'link_label', 'Link label', 'Learn more'),
  text('cct', 'link_url', 'Link URL', '#'),
], onBlock('acf/careers-cta'));

group('block_careers_jobs', 'Block: Job opportunities', [
  toggle('cjo', 'show_careers_jobs', 'Show section'),
  text('cjo', 'label', 'Label', 'See Where You Fit in'),
  text('cjo', 'heading', 'Heading', 'Job Opportunities'),
  text('cjo', 'view_all_label', 'View all label', 'View all'),
  text('cjo', 'view_all_url', 'View all URL', '#'),
], onBlock('acf/careers-jobs'));

writeFileSync(new URL('./scf-import.json', import.meta.url), JSON.stringify(out, null, 2));
console.log(`Wrote ${out.length} objects (${out.filter(o => o.key.startsWith('group_')).length} field groups, ${out.filter(o => o.key.startsWith('post_type_')).length} post types, ${out.filter(o => o.key.startsWith('taxonomy_')).length} taxonomies)`);
