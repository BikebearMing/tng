# TNG â€” Handover

Law firm website (Tilleke & Gibbins). Frontend is built as static HTML in this folder first; the WordPress backend (SCF fields + content seeding) comes at the end. Last updated 2026-09-28.

**Status 2026-09-28 (late):** the static frontend is done for Home, Professionals (grid + single), Insight (Standard, no-image, Module), Contact and Careers â€” **and the WordPress backend is built and live** at https://tng.mydemobb.com/. Every built page has a WP counterpart rendering the same markup, seeded with the HTML's content. See [WordPress build (done)](#wordpress-build-done-2026-09-28) at the end of this file for what exists, how to update it, and what's left.

## Division of labour

- **User** builds the frontend design in HTML/CSS. Claude helps build sections from mockups on request.
- **Claude** owns the backend later: use the Playwright browser to drive wp-admin, set up Secure Custom Fields (SCF, the ACF fork), and seed content. Do not touch WordPress until the user says so. User logs into the browser themselves; never ask for credentials.

## Site facts

- wp-admin: https://tng.mydemobb.com/wp-admin/ (WordPress 7.1.1). SCF is installed and active (menu "SCF": Post Types, Taxonomies, Options Pages UIs). Other active plugins: Insert PHP Code Snippet, Simple Custom CSS and JS, SVG Support, Disable Comments RB, Headers Security Advanced & HSTS WP.
- Theme: Twenty Twenty-Five (block theme). Client edits in Gutenberg and wants to preview pages, so each section is planned as an SCF block with a show/hide toggle. CPT singles and taxonomy archives live in the Site Editor, not PHP templates.

## Content model (agreed with user)

- **Post types:** Insight (two inner-page templates, Standard and Module, differ in layout and fields; togglable sections), Professional (~700 lawyers; grid page with search, alphabet, and facet filters; inner page with overview, experience, tied insights, tied awards; togglable sections), Award (own inner page; tied to professionals, locations, later services), Job (job posts; the careers page lists open jobs per Location, no single-job mockup yet), Services (later).
- **Taxonomies:** industry, practice, location (fixed 6â€“7 terms, shared by professionals/insights/awards; term pages get SCF fields for hero/intro), position, language, insight category, tag.
- **Filter plan for professionals:** custom REST endpoint returns the whole roster once (compact JSON, transient-cached, invalidated on save); all filtering runs client-side. 700 professionals must be imported by script, not clicked in.
- **Site search (discussed, not built):** no search UI plugin. A custom REST endpoint `/wp-json/tng/v1/search?q=` returns results grouped by type (top 3â€“5 each + counts); a live panel under the header renders them; Enter goes to a `/search/?s=` results page with type tabs. Professionals can be matched client-side from the roster JSON. Add Relevanssi (free) underneath only if native relevance is too weak; it hooks into the same WP_Query. Waiting on a mockup of the search state.
- **Decisions pending:** see "Confirm with the user first" in the WordPress build plan. Later: the Services post type plugs in via SCF Post Types UI + the existing shared taxonomies.

## Local project

```
index.html              homepage: header, 5 sections, footer
professionals.html      professionals grid: page-hero, pro-filter (search, alphabet, facets), pro-grid
professional.html       professional single: pro-hero, pro-tabs, pro-overview, pro-experience, pro-related Ã—2 (Site Editor template later)
insight.html            insight single, Standard template: in-hero, in-bar, in-article
insight-no-image.html   same template with the featured image off (in-hero--no-image), 3 taxonomy rows
insight-module.html     insight single, Module template: in-hero--module, in-panels, in-findings, related professionals, in-download, in-share-row, related insights (no reading bar)
contact.html            contact page: page-hero--compact, contact-offices (city tabs + details + Google map)
location.html           location term page (Cambodia): loc-hero, pro-tabs--six, loc-overview, people row,
                        insights, loc-successes, experience, awards, loc-office (map)
service.html            industry / practice term page (Automotive): svc-hero (country tabs → contacts),
                        pro-tabs--six, svc-overview, svc-subs, svc-successes (slider), experience,
                        people, insights, awards
practice.html           practice term page (Intellectual Property): service template, svc-overview--top-label
                        with a quote, no experience section (toggled off in the mock)
subpractice.html        subpractice = child practice term (Commercialization): service hero (4-level
                        breadcrumb), no on-this-page tabs, top-label overview with a heading,
                        "Representative Matters" (the experience block); rest of the mock pending
about.html              about page: about-hero, pro-tabs--about (5), top-label overview, about-values
                        (accordion + image), about-numbers (staggered counters), about-code
careers.html            careers page: careers-hero, career-paths, careers-values (slider), careers-people, careers-cta, careers-jobs
under-development.html  full-height navy placeholder (page-hero--ud): breadcrumb, h1, two link-arrows;
                        used in WP for every route with no design yet (simplified static header, no megamenus)
assets/css/style.css    globals â†’ homepage sections â†’ footer â†’ header + megamenus â†’ professionals pages
                        â†’ .site-header--light â†’ insight pages â†’ insight Module â†’ page-hero--compact
                        + contact â†’ careers (in that order)
assets/js/main.js       Lenis, header scroll state, megamenu hover, GSAP reveals, tabs, marquee, parallax,
                        professionals alphabet toggle, facet reveal, pro-card batch reveal, pro-tabs
                        scrollspy, "See more" / "Read more" caps, reading bar, share row, contact office
                        tabs, Blaze slider init
assets/vendor/          gsap, ScrollTrigger, SplitText, blaze-slider, lenis (copied from node_modules)
assets/flags/           cambodia/indonesia/laos/myanmar/thailand/vietnam.svg (location pills)
assets/*.jpg|png|svg    images (Rectangle 1.jpg = banner, hp-location-bg.jpg = teal texture,
                        regional-presence.jpg = map, guy-1/lady-2 = portraits, legal-500.png, ALB badge,
                        lexmundi/multilaw/dna.png = footer network logos at natural size,
                        module-bg.jpg = Module hero / download banner, careers-medal.svg = placeholder
                        slide image the user will replace, header-logo-black.png = unused so far)
wp/tng-core/            the WordPress plugin (blocks, templates, enqueues, REST, seeder) — source of
                        truth for the live site; see "WordPress build (done)" below for deploying
wp/build-scf-json.mjs   generates wp/scf-import.json (CPTs, taxonomies, 22 field groups) for SCF → Tools import
wp/tng-core.zip         the deployed plugin build (root folder renamed to tng/)
```

Git: https://github.com/BikebearMing/tng, branch `master`, worked on from two machines. Pull before starting; `git stash` / `git pull` / `git stash pop` when the pull is blocked by local edits. `.playwright-mcp/` and `node_modules/` are gitignored.

Preview: `npx serve -l 5173 .` then http://localhost:5173/. The Playwright browser cannot open file:// URLs.

Note: `Rectangle 1.jpg` (2.4 MB), `hp-location-bg.jpg` (6.7 MB) and `module-bg.jpg` (1 MB) need compressing before production.

Tooling notes (Windows machine): the Bash tool sometimes fails to start (`EPERM ... uv_spawn bash.exe`); PowerShell still works, so run Python scripts through it. Python on Windows writes CRLF in text mode; the repo files are LF, so open files with `newline=''` when rewriting them.

## Starting a new page

1. Copy `index.html`, keep `<head>`, the `<header class="site-header">` block, the `<footer>` block and the script tags at the bottom. Put page content inside `<main>`.
2. If the page does not open with a dark hero, add `site-header--light` to the header (see professional.html): navy text and a dark rule over the transparent 89px header, then the usual white bar once scrolled. Give such pages enough top padding to clear the header.
3. Prefix new section classes with the page (`pro-` professionals, `in-` insight, etc.) and keep the `data-section` + SCF comment on every section. `page-hero` is the shared inner-page hero.
4. New CSS goes after the header block at the end of `style.css`, one `/* ==== Page â€” Section ==== */` banner per section.
5. Anything new that should animate: add group containers to `GROUPS` and rule/underline elements to `RULES` in main.js. Text classes animate automatically.

## Conventions

- **Measurements are vw on a 1440px base** (px / 1440 Ã— 100). Every value carries a `/* px */` comment. User supplies mockups with red measurement markers; convert each to vw. Mockups are sometimes scaled: check a known width (a nav label, a logo) to find the scale before reading markers.
- **Type classes in globals (user owns these):** `.h1 .h2 .h3 .h4 .body .subhead .button-label .counter .counter-label`, `.dark` forces white text with `!important`. Palette vars: `--white --red --navy --black --grey`. Side paddings: `--sidepadding-xs` (20px) and `--sidepaddong-m` (85px, typo is the user's, keep it). `body { min-height: 200vh }` is a leftover in globals.
- **Shared pieces:** `.link-arrow` (text + chevron + red underline that draws in on scroll, arrow nudges on hover), `.arrow-circle` / `.arrow-circle--lg` (red circle icon), `.visually-hidden` (screen-reader-only labels). The banner still uses its own `.hp-featured-banner__icon`; the location cards reuse that class too.
- **Section naming:** `hp-<section>` BEM blocks. Each `<section>` has `data-section` and `data-toggle` attributes plus a comment listing the intended SCF block and fields, so the HTML â†’ SCF mapping is explicit. Site-wide chrome (header, footer) maps to SCF **options pages**, not blocks.
- **Parallax:** add `has-parallax` to a section and put `<img class="parallax-img">` inside. Overlays are `::before` at z-index -1; the image sits at -2.
- **Grey rules** are pseudo-elements scaled from 0 and revealed by an `is-in` class (added by ScrollTrigger batch in main.js).
- **Icons inside `<button>`:** Chromium wraps a button's children in an anonymous block, so they are never flex items and the reset's `svg { display: block }` pushes the icon to a new line. Make the icon `inline-block; vertical-align: middle` and space it with a margin (see `.site-header__search`).
- **List items with inline-flex links** pick up a 20px strut from the root font; set the `li` to `display: flex` when the row height matters.

## Site-wide chrome

### Header (`.site-header`, before `<main>`)

- Fixed, 89 tall, transparent, white text over the hero. Logo 21 in / 9 down / 71 tall. Rule at 35 spanning 200â†’1258. Top row (About / Careers / Contact, `.subhead`) ends 9 above the rule, main row (`.button-label`) starts 9 below; both centred in that span with 33 / 42 gaps. Search + ENG centred on the rule, 17 from the right edge, 26 apart.
- **Scrolled state** `is-scrolled`: toggled from the Lenis scroll event past `data-scrolled-at` (40px). White bar, `--black` text and logo, rule `#e7eaee`, compact to 72 (logo 8 / 56, rule 33, rows 9 either side). Everything transitions over 0.4s.
- **Light pages** `site-header--light`: same header, navy text and `rgba(7,20,35,.15)` rule from the start, grey pill on hover; still switches to the white bar on scroll (see Starting a new page).
- Header type classes carry no `.dark`; they inherit colour from the header so the state swap happens in one place.
- Main links get a pill on hover (`#eef0f3` scrolled, faint white over the hero); padding is cancelled with negative margins so text never moves. No pill while a megamenu is open (user's call).
- **Megamenus** (`li.has-mega` â†’ `.site-header__mega`): open on `:hover` / `:focus-within`, or `.is-open` for scripting. White card with soft shadow, 12 below the pill, aligned to its left edge; an invisible `::before` bridge covers the gap. Rows rise in with a 50ms stagger (delays for up to 12 `li`), image fades up after 0.15s; closing resets so it replays.
  - *Locations*: list of Location terms + Global Reach (18px, grey, active = black + red circle), 187Ã—226 image on the right that crossfades per hovered item (main.js swaps `is-active` on rows and images). Picsum placeholders.
  - *Services*: Industries list in the same style, divider, Practices label + two-column grid of 14px/600 links. `--rows` inline var sets items per column (6 now). Practices are static, not filtered by industry. Columns 220 wide (mock says 209; widened so the longest practice clears column 2).
- Not built: mobile menu, search panel, language switcher behaviour. Touch devices follow the link on tap.
- SCF: options page `site/header` with WP menus for the top and main rows, plus the taxonomies feeding both megamenus (see the comment above the header in index.html).

### Footer (`footer#footer.hp-footer`, after `<main>`)

- Background `#041f36`. Grid: brand column from 39 in, right column starting at 728. Brand: logo (text stand-in), 3 outlined 44px social circles 25 below, newsletter heading 75 below, 308Ã—57 field with white Subscribe pill. Right: Networks label â†’ logos at natural size (38/39 apart) â†’ Quick links label 54 below â†’ two columns 212 apart. Bottom bar: 1px rule, copyright left, legal links right (235 / 119 apart), 8 / 13 padding.
- Social icons, network logos and quick-link columns are stagger groups; the bar rule draws in. Footer reveals use `startFor(el)` â†’ `top bottom`, because the page ends there and its last rows can never reach the 88% line.
- Newsletter form has no action. SCF: options page `site/footer` (see the comment above the footer).

### Logo

Both header and footer use a text stand-in in Times with a red ampersand (`.site-header__logo`, `.hp-footer__logo`). Times renders narrower than the real wordmark. When the SVG arrives: drop it in both places with `fill: currentColor` so the header's colour swap keeps working.

## Animation system (main.js)

- Headings (`.h1â€“.h4`, `.counter-label`): SplitText lines with `mask: 'lines'`, rise from `TEXT_START` (180%) with 0.1s line stagger. Masks get a 0.2em bottom bleed (padding + negative margin) so descenders aren't clipped; split elements become `flow-root` so height stays exact.
- Body (`.body`, `.subhead` that aren't links): lines + words, word stagger with `amount` capped at 0.9s.
- Counters (`.counter`): count up from 0, keep suffix like "+".
- WYSIWYG copy (`.rich-text`, shared by the insight body and the professional bio): picked by tag since it has no classes. `CONTENT_TEXT` (h2â€“h4, direct `p`, `li`, `blockquote`) gets the heading line reveal, lines only (no word split, too many spans on long articles); `CONTENT_MEDIA` (figure / img) rises in. `bleedMasks` also turns `list-item` elements into flow-root so the bleed doesn't add height. A `beforeprint` handler finishes every `once` reveal so Print never outputs hidden text.
- Groups (`GROUPS` list): children rise 40px and fade with 0.12s stagger; text/counters inside a group child delay by that child's index.
- Rules and `.link-arrow` underlines: `is-in` class via `ScrollTrigger.batch`, 0.12s between siblings. `drawRules` runs two batches: page elements at the 88% line, footer elements at the viewport bottom.
- `startFor(el)` picks the trigger start: `top bottom` inside the footer, `REVEAL_START` (88%) elsewhere.
- Careers marquee: columns cloned once and looped by âˆ’50% (left up, right down). Marquee container uses `align-items: flex-start` so the loop distance is exact.
- Tabs: active-state toggle only; content swap waits for WP data.
- Splits wait for `document.fonts.ready`. Text targets are hidden via `gsap.set` at script start to avoid a flash.
- Megamenu reveals are pure CSS transitions (see Header), not GSAP.
- **The header is excluded from every reveal** (`revealTargets()` filters out `.site-header` descendants, both in the initial hide and the reveal loops). Reveals once set inline `visibility: visible` on the megamenu labels inside the closed (`visibility: hidden`) panels, which made the invisible panels hit-testable: hovering the sticky bar under the header popped the menus open on every page. The closed panel is also `pointer-events: none` now (open state restores `auto`), so nothing inside it can ever catch the pointer again.

## Verifying in a browser

- Element screenshots in Playwright time out on this page because the marquee never settles; use viewport screenshots.
- If the Playwright MCP browser is locked by another session, drive the ms-playwright Chromium (`%LOCALAPPDATA%\ms-playwright\chromium-*\chrome-win64\chrome.exe`) over the DevTools protocol from a small Node script: launch with `--headless=new --remote-debugging-port`, connect with Node 22's built-in WebSocket, `Page.navigate`, scroll or add state classes via `Runtime.evaluate`, then `Page.captureScreenshot`. A plain `--screenshot` with a tall window breaks the 100vh banner, and `#hash` navigation gives a blank capture.
- Measure with `getBoundingClientRect` in the same evaluate call rather than reading pixels; note the headless viewport is 1424 wide, so vw values come out Ã—0.989.

## Sections built (index.html order)

1. Featured banner, 100vh, parallax + navy multiply gradient, recent insights panel.
2. Intro + counters + regional presence (teal texture parallax, black fade overlay, map panel, 6 location cards).
3. Insights by industry: label with grey rule, tabs (`.h3`, red indicator on active), 643Ã—517 feature card, 3-row list.
4. Careers: heading + link, T-shaped rules with note + ALB badge, two-column portrait marquee. Background is solid navy; the textured version isn't in assets yet.
5. Awards & Rankings: light grey panel, heading + Legal 500 logo, description + Read more, white card.

Placeholder images (picsum.photos) remain on the location cards, the insights feature image and the Locations megamenu.

## Professionals pages (built on the other machine, pulled 2026-09-25)

- **professionals.html**: `page-hero` (429 min height), `pro-filter` (search field, Aâ€“Z alphabet buttons, facet dropdowns), `pro-grid` of `.pro-card`. Alphabet is an active-state toggle only (one letter, click again to clear); facet label + chevron rise in while the buttons stay put; cards rise in row by row via `ScrollTrigger.batch`. Real filtering waits for the roster JSON endpoint.
- **professional.html**: `site-header--light`, `pro-hero` with locations (flag SVGs) and contacts as stagger groups. Becomes the Professional single template in the Site Editor.
  - `pro-tabs`: "On this page" bar, white, sticky at 72 (z 5), 72 tall. Columns 329 / 380 / 380 / rest, labels 20 in, active tab = black + 3px red underline over the bar's border. main.js: click scrolls the section to just under header + tabs (Lenis `scrollTo` with offset), scrollspy marks the section crossing the viewport centre. Tabs link to `#overview`, `#experience`, `#insights`, `#awards`; only Overview is built, the others are skipped until their sections exist. Template chrome; a tab is left out when its section is off or empty.
  - `pro-overview` (`#overview`): bio column 191 in / 476, facts column 180 further / 416. `.pro-label` (shared) = `.subhead` + a grey rule 10 below that draws in (RULES). Lead paragraph `.h3` (textarea field), biography `.rich-text` (WYSIWYG, shared with the insight body). Facts `<dl>`: Industries / Practices navy pills (link to term pages), Language text, Education (repeater of textareas, line breaks kept). `.pro-facts` is a stagger group. 69 bottom padding + grey bottom border.
  - `pro-experience` (`#experience`): light grey `#eef0f3`, `.pro-label` 87 in, list 56 under the rule at 364 â†’ 1078 (714 wide). The list is a **WYSIWYG bullet list** (`.rich-text`), restyled here: 12x14 triangle markers, text 32 past them, items 46 apart. `data-cap="4"`: main.js marks later `li`s `.is-extra` (hidden, skipped by the line reveal) and adds a centred "Read more" / "Read less" `.button-label` 63 under the list; extras fade in on open. 56 bottom padding.
  - `pro-related` (`#insights`, `#awards`): heading column 28 in (`.h2`, max 250 so "Awards & Rankings" wraps) + `.link-arrow` "View all" 15 below (17 gap to the chevron); 3 cards from 378, 18 apart, tops level with the heading. 71 top/bottom; stacked sections get 66 / grey rule 28 in (draws in, RULES `.pro-related + .pro-related`) / 54. Cards = latest 3 Insights / Awards whose related-professionals relationship includes this professional; "View all" = the archive filtered to them.
- **`.post-card`** (shared, reuse for location pages): white, 1px `#dfe4ea` border, min 249 tall. `.h3` title (max 285, fitted so the mockup's line counts match), grey `.body` excerpt (max 260) 16 below, date `.subhead` + 20 red `.arrow-circle--lg` on the bottom edge (15 up, arrow 14 in). Hover: darker border, soft shadow, arrow nudges. `.pro-related__cards` is a stagger group.

## Insight pages (started 2026-09-25)

- Three versions: **Standard** (`insight.html`), **Standard without featured image** (`insight-no-image.html`: the same template with `show_featured_image` off, hero gets `in-hero--no-image` and no `<img>`, title max 760), **Module** (`insight-module.html`, separate template made of stackable SCF module blocks; complete as of 2026-09-28, see below). Build parts shared by both templates once, with the `in-` prefix.
- **in-hero**: min 567 tall, `#041f36`, white header. Breadcrumbs, title (`.h1`, max 540), date, then Location / Industries / Practices rows anchored 23 off the bottom (at least 83 under the date). Rows: `<dl>`, label centred on the first pill line, 1px rule at 70 as the `dd`'s left border (full row height), 27-tall outlined pills 8 apart / wrapped lines 6 apart, location pills carry the flag (`assets/flags/*.svg`, all 6 countries). Pills link to their term pages; a row hides when the post has no terms. **Cap:** `data-cap="3"` on the `<dl>`; main.js hides pills past 3 and appends a "See more" / "See less" toggle (no JS = all pills show). Featured image absolute: 697 wide, 81 top, 15 right/bottom, `object-fit: cover`. `.in-hero__meta` is a stagger group.
- **in-bar** (reading bar): white, `position: sticky` at 72 (under the compact header), so it must stay a direct child of `<main>`. Title `.h4` + date `.subhead`, 1px grey track with a 3px red fill scrubbed by ScrollTrigger from the hero's bottom to the end of `<main>`. Template chrome, not a toggle block. The next section starts 34 below the track. `z-index: 5` so it slides under the header (10).
- **in-article**: grid, sidebar 191 in / 357 wide, body 70 past it / 589 wide. The sidebar (`.in-article__aside`) is sticky at 189 (72 header + 83 bar + 34).
  - `.in-related`: grey panel, `.h3` heading + a `+` icon (behaviour not decided), 91-tall white cards (70 headshot, `.h4` name, phone, email, 18 red arrow circle). Needs a cropped **headshot** field on Professional, separate from the hero cutout.
  - `.in-share`: Print box + LinkedIn / email / copy-link squares (53). Hrefs, print and copy are wired in main.js.
  - `.in-content.rich-text`: the **WYSIWYG body** (post content). No classes inside; every element is styled by tag (p, h2, h3, ul, ol, li, strong, a, blockquote, figure, img). 18/22 Inter Tight, blocks 23 apart, h2 = `.h2` size with 73 above (106 after a list) and 27 below. The mockup's body line breaks don't quite match Inter Tight; it may have been set in Inter.

## Insight Module template (2026-09-28)

- **in-hero--module**: modifier on `in-hero`. No reading bar on this template (user's call); main.js skips the progress when `.in-bar` is absent. Cover `.in-hero__bg` image (`assets/module-bg.jpg`) instead of navy, no taxonomy rows or featured image. Breadcrumbs, 74 to the title, 17 date, 122 to `.in-hero__lead` (`.h3`, line-height 27, max 480 fitted to the mockup's 3 lines), 44 bottom. Content pushes the hero to ~582 (mock image 567); every measured gap is kept. `.in-hero__report`: white 300Ã—136 card, 20 right / 32 bottom, `.h3` label + red arrow circle, links to the report PDF.
- **in-panels**: teal texture (`hp-location-bg.jpg`, parallax) under a 35% navy tint, 104 / 102 padding, panels 191 in from both sides, 20 apart (`.in-panels__list` is a stagger group). `.in-panel`: heading `.h2` 23 in / 226 wide / 76 to a 1px rule, `.rich-text` body 31 past the rule / 79 from the right (18/22 shared WYSIWYG styles; line breaks match the mock). `.in-panel--dark` = navy with white copy, optional `.link-arrow` 105 below. Mock breaks "About / the survey" by hand; width alone gives "About the / survey", so the editor adds a line break if wanted.
- **in-findings** (Key findings, toggle `show_module_findings`): `.h3` heading 21 in, rows between 1px rules (21 in both sides, draw in via RULES). Number `.in-findings__num` = Roboto Condensed 220/220, 500, âˆ’15.4 tracking (user's spec), numbered from row order, rises in with the heading reveal (added to HEADINGS). Title `.h3` at 625, 257 wide (mock 256; +1 so the line breaks hold); text `.rich-text` 126 past it, 94 from the right, both 28 under the rule. 27 under each number, section ends 115 under the last.
- **Related professionals** (toggle `show_module_professionals`): `.pro-related--people`, the professional page's related block with 4 `.pro-card`s (the grid card, 248 wide, 9 apart) from 384, heading 33 in, 38 right, 61 bottom, no top padding.
- **in-download** (toggle `show_module_download`): 317 tall, two halves. Left = red link to the PDF, `.h2` label 30 / 32 in, 14 to a 16 white circle with a red chevron (nudges on hover). Right = cover image (falls back to the hero background).
- **in-share-row** (toggle `show_module_share`): centred `.h3` "Share the insight", 35 to a centred 77-tall grey rule (heading `::after`), 36 to the Standard template's `.in-share` buttons (print box fixed at 158, 19 to the squares; main.js wiring is shared). 105 top / 93 bottom.
- **Related insights** (toggle `show_module_insights`): `.pro-related.in-related-insights`, the professional page's block with 3 `.post-card`s; grey rule on top (28 in, draws in via RULES), cards 33 below it.
- Every Module section carries a `data-toggle`; the page is a stack of independent blocks.

## Contact page (2026-09-28)

- Header "Contact" links (top row + footer quick links) now point to `contact.html` on every page.
- **page-hero--compact**: the shared `page-hero` without its 429 min height; title 150 under the breadcrumbs, 27 bottom (360 tall). Intro at 720 as before.
- **contact-offices** (toggle `show_contact_offices`): city tabs (`.h2` buttons, 5 apart, grey `#c4cad2`, active black + 20 red arrow 11 past the name) 20 in; panel from 409 = details (281 wide) + 42 + 697Ã—471 map iframe, 12 from the right; 13 top / 19 bottom. Details: `.h4` office name 18 down with a rule 11 under it, phone / email rows 21 below (40 outlined circles, 12 to 18px text, 16 apart), address `.rich-text` 32 below.
  - One panel per office, all rendered; main.js toggles `hidden` + `is-active` on click and with arrow keys (ARIA tabs), fading the new panel's parts in. Hidden maps are lazy iframes, so each map loads only when its tab is opened.
  - Maps are keyless embeds: `https://www.google.com/maps?q=<query>&output=embed`. URL-encode the query (`&` in "Tilleke & Gibbins" must be `%26`, or Google finds the wrong office).
  - Data comes from the Location taxonomy terms (term fields: office_name, phone, email, address, map_query). Only Bangkok's details are real (from the mock; its phone/email are the mock's Cambodia ones); the other five repeat them with a TODO. Maps already search "Tilleke & Gibbins, <city>" and land on the right offices.
  - Tabs, office name and address are excluded from the SplitText reveals (buttons can't hold split lines; hidden panels would split at zero width); `.contact-offices__tabs` and `.contact-offices__panels` rise as GROUPS instead.
  - The mock cuts long city names off ("Ho Chi Min", "Vient"); they're shown in full here.

## Careers page (started 2026-09-28)

- Header "Careers" links (top row + footer quick links) now point to `careers.html` on every page.
- **careers-hero** (toggle `show_careers_hero`): the insight hero's frame (navy `#041f36`, 567 min, image 697 wide at 81 / 15 / 15), text stacked on the bottom: `.h1`, 89 to the `.body` intro (max 436), 20 to a `.link-arrow` "All job opportunities", 32 bottom. Image is a picsum placeholder. The mock's navy looks faintly textured; plain navy for now.
- **career-paths** (toggle `show_career_paths`): three `.career-card`s 18 under the hero, 18 in, 14 apart, 103 bottom. Card = post-card border/hover, `.h3` title + 20 red arrow on its line (a grid, not inside the heading, so the heading still splits), grey `.body` text (max 200) 28 below. `.career-paths__list` is a stagger group.
- **careers-values** (toggle `show_careers_values`): `.h2` heading 18 in (max 320 so it breaks "Together at / Tilleke & Gibbins"); Blaze slider from 482 to 18 from the right, one 372-tall card at a time, looping (the global `.blaze-slider` init). Card halves: `.h3` title 19 / 21 in, `.rich-text` text (max 428, fitted to the mock's breaks) pinned 23 off the bottom, image 236 tall centred on the right (`<img>`, placeholder `assets/careers-medal.svg`; user swaps in the real image; SCF image field). Prev / next = `.blaze-prev` / `.blaze-next`, 44 red circles 14 under the card, 14 apart; 70 bottom. Slides 2â€“3 repeat slide 1's copy (TODO).
- **careers-people** (toggle `show_careers_people`): `.pro-label` "Career Development" 14 down (rule to 15 from the right), `.h2` 27 under the rule, then a grid 40 below: `.rich-text` (max 500, fitted to the mock's line breaks) | image from 720 to 15 from the right, 693 tall, top level with the text (`.careers-people__media` rises as a group). 17 bottom.
- **careers-cta** (toggle `show_careers_cta`): `#eef0f3` panel 15 in from the sides, 101 top / bottom, centred `.h2` + `.link-arrow` 21 under it.
- **careers-jobs** (toggle `show_careers_jobs`): centred 853 column, 67 top (from the CTA panel) / bottom. Grey `.subhead` label, 10 to `.h2`, 35 to the rows. Rows are links: 63 tall, bordered, 10 apart; `.h4` place 19 in, grey count 24 before a 20 red arrow, 20 from the right; card hover. Automatic from Location terms with open Job posts (+ count, filtered listing link). `.careers-jobs__list` is a stagger group; "View all" centred 35 below.

## Location page (2026-11-03)

`location.html`, Cambodia as the example; becomes the `taxonomy-location` term template in WP (replacing the under-development placeholder). Header megamenu "Cambodia" links to it on every page. Mostly reuse:

- **loc-hero**: the `in-hero` frame + `.in-hero__image`. Title (`.h1`) 150 under the breadcrumbs, office block 91 below: name (`.body` 600) over a 465 rule (11 under), phone / email rows 14 under it (30 outlined circles `.loc-hero__icon`, 10 to the 14px text, 7 apart; `.loc-hero__lines` is a stagger group), address `.body` 50 past the email + underlined "View on map" (Google Maps link) 14 below; 23 bottom. Hero ends ~574 (mock 567). Plain navy (mock is faintly textured, like careers).
- **pro-tabs--six**: the professional tabs in six fixed columns 226 / 254 / 241 / 259 / 214 / rest (labels at 20 / 246 / 500 / 741 / 1000 / 1214 in both the location and service mocks; the last tab's underline tail is 50 so it fits), "On this page" 14 from the top, `nowrap`, underline = 20 + label + 70. Overview / Professionals / Insights / Client Successes / Experience / Awards & Rankings.
- **loc-overview**: `.pro-label` 33 under the tabs (rule to 20 from the right), `.h2` heading 20 in (max 500), `.rich-text` at 16/22, 545 wide ending 175 from the right, both 23 under the rule; 129 bottom. Mock copy is likely Inter: Inter Tight wraps 6/9/6 vs the mock's 7/10/6 at the measured box (kept the box).
- **Professionals**: `.pro-related--people` (Module template block) unchanged.
- **Insights**: `.pro-related.loc-insights` (rule above via `+`), 83 bottom.
- **loc-successes** (Client Successes): teal texture parallax, no tint. White `.pro-label` 20 in (rule to 373, 8 under the card top); cards from 413 to 28 from the right, 32 top / 192 bottom, 20 apart (a list; repeater on the term). Card: `.h2` title max 384, 22 / 26 in, `.link-arrow` "Read more" 32 under it; `.body` text 256 (mock 252, +4 so the 8 lines hold) level with the title, 207 from the right. Built as a stack, not a slider.
- **Experience**: the professional `pro-experience` block unchanged (cap 4 + Read more).
- **Awards**: `.pro-related.loc-awards`, 54 top / 65 bottom.
- **loc-office**: `--navy` strip, info 29 in: `.pro-label` "Office" 22 down (rule to 706), 20 under it the city `.h2` + at 375 the phone / email rows (hero's `.loc-hero__lines`, 10 apart) and address (max 258, fitted to the mock's 4 lines) 26 below. Map iframe 704 x 441, 9 top / bottom, 12 right, 18 past the info, `z=14`.
- Term fields to add in WP: hero image, overview heading + text, client successes repeater (title, text, link), experience (wysiwyg), section toggles; office data comes from the existing city child term fields.

## Service page (2026-11-03)

`service.html`, Automotive as the example; becomes the `taxonomy-industry` / `taxonomy-practice` term template. Megamenu "Automotive" links to it on every page. Mock had no markers for the hero/overview (scale taken from the page width, 1.907).

- **svc-hero**: `in-hero` frame, text on the bottom (`margin-top: auto`): `.h1`, 97 to the country tabs (`.button-label` buttons 16 apart, `rgba(159,178,197,.5)`, active white + 45-wide 2px red underline 4 under the label), 30 to the contacts (`.svc-contact` 291 wide, 1px left rule, 10 in: `.h4` name link, 6 to phone + email `.body` 13 apart), 32 bottom. Tabs are ARIA tabs driven by the contact page's handler (main.js now runs it on `.contact-offices, .svc-hero__content`). Panels are left out of the SplitText reveals (`revealTargets` skips `.svc-hero__panels`; hidden panels would split at zero width) and rise as GROUPS. Countries 2–6 repeat Cambodia's contacts (TODO). WP: term repeater contacts = location + professionals relationship. **Client rules (gelo.c, Figma comments) for industry / practice / subgroup pages:** (1) hide a country tab that has no contacts; (2) the active tab on load follows the visitor's country (Cambodia / Indonesia / Laos / Myanmar / Vietnam → that one), otherwise Thailand is the default; fall back to Thailand, then the first visible tab, if the pick is hidden. The site is behind Cloudflare (`CF-IPCountry`), but HTML may be edge-cached, so pick the tab client-side rather than in the server render. The static mock still defaults to Cambodia with all six tabs.
- **svc-overview**: 40 under the tabs; `.h2` heading 20 in (max 470), right column from 729: `.pro-label` (rule to 95 from the right) + `.rich-text` 16/22, 543 wide, 25 under the rule. Line counts match the mock (4/12/3).
- **svc-subs** (Subpractices): label 204 in both sides, 3 cards 333 × 249, 18 apart, 38 under the rule; card = post-card border, 130 image, `.h3` 10 under it / 12 in, red arrow 15 from right/bottom. 115 top (measured on the practice mock: overview text → label), 80 bottom. WP: child terms + an image field on each term.
- **svc-successes**: `loc-successes` as a Blaze slider (global init), cards from 416, 42 top, min 268; prev / next = `careers-values__btn` at 60, 10 apart, 22 under; 175 bottom. Slides 2–3 repeat slide 1 (TODO). `.loc-success__title` max is now 400 (shared) so "global consumer bank" holds one line.
- **Experience / Professionals / Insights / Awards**: the location page's blocks; professionals (`.svc-people`) 46 under the grey panel, then the stacked `pro-related` rules (66 / 54).

### Practice + subpractice variants (2026-11-03)

- **`svc-overview--top-label`**: the "Overview" label spans both columns (rule to 20 from the right); left item and text both 23 under the rule. Practice puts a quote there (`figure.svc-overview__quote`: `.h3` blockquote max 420 + `.subhead` source 12 under it); subpractice a `.h2` heading (max 520 on `--sub`, two lines in the mock) and 47 bottom before the grey panel.
- **practice.html** (IP): the mock has no Experience section, so it and its tab are dropped (the template rule: no section → no tab); the remaining five tabs keep the fixed columns from the left.
- **subpractice.html** (Commercialization): hero breadcrumb Home / Practices / Intellectual Property / Commercialization, no pro-tabs, Representative Matters = `pro-experience` with that label. Subpractice cards on the service/practice pages link here; the megamenu's "Intellectual Property" links to practice.html.
- Client contact rules (hide empty countries, geo default) apply to all three hero variants — see the service section above.

## About page (2026-11-03)

Header "About" links (top row + footer quick links) point to `about.html` on every page.

- **about-hero**: `in-hero` frame, title (`.h1`, max 540) + intro (`.body`, max 510) on the bottom 120 apart, 26 bottom. Image is a picsum placeholder (the mock shows the teal texture — but using `hp-location-bg.jpg` both here and in Our Numbers made headless Chromium paint neither; one use per page is fine).
- **pro-tabs--about**: five columns 294 / 323 / 306 / 326 / rest (labels at 20 / 314 / 637 / 943 / 1269).
- **Overview**: `svc-overview--top-label` + `.about-overview` (heading max 530, five lines).
- **about-values**: accordion 595 wide from 20 + image 111 past it to 12 from the right (667 tall). Items between rules, `.h3` button 19 above/below (62 closed), CSS "+" that turns into "−" when open (the mock shows "+" on the open item too); panel height animates via `grid-template-rows: 0fr → 1fr`; one open at a time (main.js), refreshes ScrollTrigger after. The `.h3` buttons are excluded from SplitText (HEADINGS). Items 2–3 repeat item 1's copy (TODO). 150 top is a guess.
- **about-numbers**: teal texture parallax, four `.counter`s (count up, keep "+") at Roboto Condensed 198 / −0.07em (key-findings style), staggered by nth-child margins: row 1 at 20 / 733, row 2 311 lower at 380 / 1017; labels `.subhead` 600 just under the digits; 24 top / 104 bottom.
- **about-vision** (`#vision`): `.h1` tab buttons 10 in (active black + 20 red arrow 11 past, others `#c4cad2`), panels from 350 behind a 1px rule: `.h3` text 30 past it (277 wide, 5 lines like the mock) + image 67 past the text to 14 from the right, 549 tall; 90 bottom. ARIA tabs via the shared contact-tabs handler; tabs and panels are left out of SplitText (rise as GROUPS). Mission repeats Vision's copy (TODO); images are picsum.
- **about-creed** (`#creed`): `.pro-label` (rule to 16 from the right), two columns (530 from 20; 729), `.h2` 26 under the rule, `.rich-text` lists 13 below with plain discs (18 indent, no gap between items); 111 bottom.
- **about-code**: top-label layout, label 29 from the top (rule to 16 from the right), heading max 560 (mock 540; fitted to its six lines), text 526 wide, `.link-arrow` 24 below, 172 bottom.
- **more-from** (reusable, last section before the footer): grey `#eef0f3`, `.h2` "More from<br>About" 20 in / 52 down; `.h3` links from 729 to 12 from the right, rows 40 above / below a 1px `#d3dae2` rule (first row flush, 11 under the heading top), 76 under the last rule; hover red. WP: a page block listing the parent's child pages (heading defaults to "More from <parent>"). History and Awards & Rankings link to `#` until those pages exist.

## Insights archive (2026-11-03)

`insights.html` (WP: the posts page / insight archive). Header "Insights" links (main nav + footer quick links) and the insight pages' breadcrumbs point to it.

- **ins-hero**: `in-hero` frame, text on the bottom: `.h1`, 64 to the intro (`.body`, max 440: six lines like the mock), 18 to a white `.link-arrow` "Subscribe to Updates", 28 bottom. Image is the teal texture placeholder.
- **ins-featured**: light grey `#eef0f3`; `.pro-label` "Featured" 14 from the top (rule to 16 from the right); grid of text + image (416 x 335, 21 under the rule, 19 from the right, 21 from the bottom). Text: date (`.subhead` `#718396`) 23 under the rule, 10 to the `.h2` title (links to the post), 21 to the `.body` `#718396` excerpt (max 310, 7 lines), min 98 to "Read more" pinned to the bottom (5 above the image's bottom edge). WP: post-object field picks the insight. Image is picsum.
- **ins-filter**: the professionals filter (`pro-filter`, sticky) in a compact modifier: both rows 48 on a five-column grid, labels 14 semibold 16 in. Row 1 (`.ins-filter__bar`, 2fr 2fr 1fr): search by name, search by professionals (red 30 buttons 16 from the divider), Date button + 20 calendar icon 20 from the right. Row 2: Resources / Sort by Relevance / Industries / Practices / Locations facets (20 chevrons 17 from each divider). Open panels + date picker await design (closed state only, like professionals).
- **ins-grid**: 90 / 204 sides / 53. Active-filter chips (`.ins-chip`: 31 pill, grey key + semibold value, x 37 past it; `.ins-chip--reset` navy, x 17 past) 2 apart, 24 to the cards; `post-card` x 3, 18 apart, rows 46 apart (title max 292 here to keep the mock's breaks); count 39 below (reuses `pro-grid__more`), View more 14 below it. 12 per page; rows 2-4 repeat row 1 (TODO). Chips and filtering are static: no JS yet.
- **Content migration (open problem):** the live site has ~729 insights; plan a scripted import (WXR / REST / scrape → WP-CLI importer in tng-core, create-only, map categories to the shared taxonomies, sideload images, link authors to Professionals, keep slugs or redirect). Not started.
- **Open question (ask the client):** what "Subscribe to Updates" does is unknown; the button is built and links to `#`. Likely the same newsletter signup as the footer's "Subscribe to our newsletter" (scroll to it, or a signup page / external list), so check before wiring it.

## Awards & Rankings archive (2026-11-03)

`awards.html` (WP: the award archive, a child page of About; breadcrumb Home / About / Awards & Rankings). About's "More from" Awards link points to it. Built from `insights.html`; differences only:

- **Hero** `ins-hero--no-link`: no Subscribe link, intro 36 off the bottom. Intro copy was read off a low-res mock (TODO confirm). Hero image is picsum (mock: gold abstract).
- **Featured** `ins-featured--no-date`: no date, the `.h2` title (max 640, 3 lines) sits where the date was; min gap to Read more 30 instead of 98 so the block stays 417. No featured image → `.ins-featured__logo` navy tile with the logo (text stand-in).
- **Filter**: same as insights (facet labels unreadable in the mock; insights' repeated, TODO).
- **Grid** `ins-grid--plain`: no chips (they appear only when a filter is set), no count; 54 top, View more 55 below the grid, 77 bottom; 15 cards (5 rows, placeholder copy from the mock). Cards link to `#` until the award single exists.
- **More from About**: Overview / History / Pro Bono, each with a 20 red arrow pinned 20 from the right (`.more-from__link:has(.arrow-circle)`). More-from links are now left out of SplitText (HEADINGS) so the arrow stays on the line; the list rises as a GROUP. The About page's more-from has no arrows (its mock showed none) — check which is current.

## Job single (2026-11-03)

`job.html` (WP: CPT job single). Light page: `site-header--light`. "Back to Job Opportunities" → `careers.html#job-opportunities` (the careers-jobs section now has that id).

- **job-hero**: 567, gradient `#f9f9fb → #adbaca` (sampled from the mock; darker than the professional hero's). Back link 123 from the top (16 semibold `link-arrow`, left chevron, 2px red underline); `.h1` 123 under its underline; navy location pill (flag + name, 26 tall) 15 under the title; Department (`dl`: 12 semibold label, 13 to a 278-wide rule, value 16) on the bottom, 32 up; apply card (white, 284 wide, 20 from the right, 26 from the bottom): `.h3` "Ready to join us?", 38 to "Please send the CV to:" + underlined email (16/22). WP fields: location term, department, apply heading/text/email (defaults from the careers options).
- **job-intro**: summary as `.h3` centred, max 640 (7 lines like the mock), 74 above and below.
- **job-summary**: "Position Summary" `.pro-label`, column 300 in / 292 from the right; groups = `.h3` heading (max 290) + `.rich-text` list (16/22, items 22 apart, 4px discs 18 indent, max 403 so the lines break like the mock) from 734, 16 below the heading's top; 48 under the last item, rule, 28 to the next heading (22 under the label's rule for the first). Groups 2–3 repeat group 1's list (TODO). 74 bottom is a guess (mock stops).
- **job-cta** (last section): teal texture parallax, 537 tall. `.h2` "Ready to join us?" centred 119 from the top, `.body` text + semibold underlined email 11 below; three white cards (327 wide, 21 apart, centred, 93 under the text, 173 tall): `.h3` title (line breaks kept, `<br>` = textarea newlines) 12 / 17 in, 16 semibold `link-arrow` (2px red underline) 15 off the bottom; 85 bottom. Card links: recruitment process (`#`, no page yet), professionals (filter by the job's department), the job location's term page (`location.html` stand-in). The email's underline is drawn on SplitText's `.split-word`s (inline-blocks don't inherit `text-decoration`).
- Careers "Jobs Available" country rows still link to `#` (no per-country listing mock yet).

## Career path page: Legal Professionals (2026-11-03)

`careers-legal.html` (WP: a child page of Careers; the same template should serve Operations Professionals and Internship once their mocks confirm it). The Careers "Legal Professionals" card links to it.

- **lp-hero**: navy `#041f36`, 398 min, no image. `.h1` left, `.body` intro (max 430, 5 lines) from 720 with the tops level, 76 under the intro (title 104 above the hero bottom as in the mock). (The avatar bubble in the mock is a Figma comment marker, not design.)
- **lp-overview**: `svc-overview--top-label` with overrides: label 18 in, 16 under the hero, rule to 12 from the right; `.h2` (max 540, 3 lines) 24 / text (16/22, from 729, max 535) 19 under the rule.
- **lp-people**: the careers page's `careers-people` block, 99 under the overview text, text column 335 (`.lp-people`).
- **lp-team** ("Hear it from our team"): teal parallax, 191 sides, 91 / 65, 640 tall. Left 243: `.h2` + ARIA tabs (28 numbered circles, active red; role 24 semibold + name 14), 69 apart. Card 63 past: white 753 x 484, quote `.rich-text` (max 304, fitted) after a ” mark, person block (rule, name + grey position) on the bottom, photo 348 wide inset 10 / 12 / 13. Tabs use the shared tabs handler (`.lp-team` added); panels are outside SplitText and rise as GROUPS; `.lp-team__panel[hidden]` needed because the panel is `display: grid`. Panels 2–5 repeat panel 1's quote; photos are picsum (TODO). The mock's "Asscociate" typo is fixed to "Associate".
- **lp-jobs** ("Opportunities for / Legal Professionals"): the careers page's `careers-jobs` rows, centred 842 (299 sides), 91 top, 150 bottom (mock white space, a guess). Rows = job title (`.h4`) + location pill (`job-hero__tag`) 30 before the red arrow; link to the job single. WP: open Job posts in this career path. **Open question:** the user thinks this list may be location-wise (grouped / filtered by location) — unconfirmed; ask before wiring. Rows 2–5 repeat row 1 (TODO).
- **More from Careers** (last section): the reusable `more-from` with arrows (as on awards): Operations Professionals (`#`), Internships (`#`), All Job Opportunities (`careers.html#job-opportunities`). WP default = the Careers page's other children + the jobs link.

## Frontend still to come (after or alongside WordPress)

- Pages with no mockup yet: Award single, Location term pages, Services, Job listing, search results, 404.
- Pending mockups: search state, mobile menu (nothing is responsive below desktop yet; everything is vw on 1440).
- Placeholders to swap: logo SVG (text stand-in), picsum images (location cards, insights feature, Locations megamenu, insight hero, careers hero, careers people), `careers-medal.svg`, contact office details (5 of 6 repeat Bangkok), careers slides 2â€“3 copy, job counts.

## WordPress build (done 2026-09-28)

Built overnight by Claude via Playwright + the SCF JSON import + the `tng-core` plugin. Everything below is **live** on https://tng.mydemobb.com/ (user "isaac" was already logged into the Playwright browser; never ask for credentials).

### What exists

- **CPTs** (SCF Post Types UI, client-editable, imported from `wp/scf-import.json`): `insight` (/insights/…), `professional` (/professionals/…), `award` (/awards/…), `job` (/jobs/…). Professionals have no block editor — their edit screen is the SCF field form (Profile / Overview / Experience / Sections tabs).
- **Taxonomies** (shared, hierarchical): `industry`, `practice`, `location`, `position`, `language`, `insight_category` (+ built-in tags on insight). **Location is hierarchical:** countries (Cambodia…Vietnam, flags bundled in the plugin) with **office city children** (Bangkok, Hanoi, Ho Chi Minh City, Phnom Penh, Vientiane, Yangon) carrying the office term fields (office_name, phone, email, address, map_query). The contact page lists city terms that have an office_name; careers-jobs rows count jobs per country (children included).
- **Field groups** (22, all UI-editable): per-CPT groups (Professional, Insight — Standard, Insight — Module, Award), Location term fields, Site Header/Footer options fields, and one group per page block (location: block).
- **Options pages**: registered in code as admin menu **Site Chrome → Header / Footer** (slugs `tng-site-header` / `tng-site-footer`). The planned `professionals/archive` options page was dropped — the page hero / filter placeholder are block fields on the Professionals page instead (simpler for the client).
- **Plugin `tng-core`** — source in `wp/tng-core/`, installed on the server at `wp-content/plugins/tng/` (see Deploying below). It registers:
  - 15 `acf/*` page blocks (fields on the block): home/featured-banner, home/intro-stats (regional presence nested inside with its own toggle — one block because the DOM nests them), home/insights, home/careers, home/awards, page-hero, pro-filter, pro-grid, contact-offices, careers-hero/-paths/-values/-people/-cta/-jobs.
  - 18 `tng/*` template blocks (render from the post's fields / options): site-header (light attr), site-footer, pro-hero/-tabs/-overview/-experience/-insights/-awards, insight-hero/-bar/-article, module-hero/-panels/-findings/-professionals/-download/-share/-insights.
  - Block-theme **templates**: `tng-blank` (pages), `single-professional`, `single-insight` (Standard), `single-insight-module` (assigned per insight via the template picker; "Global Disputes Forecast" uses it), and **under-development placeholders** (1.0.5, block `tng/under-development` = `.page-hero--ud`: breadcrumb + "This page is under development" + home/contact link-arrows) for `taxonomy`, `archive`, `single-award`, `single-job`, plus `tng-404`/`tng-search` — those two use plugin-only slugs pushed to the top via the `404_template_hierarchy`/`search_template_hierarchy` filters, because TT25 ships its own 404/search templates and a theme template always beats a plugin template with the same slug. Placeholder **pages** About / Services / Insights (same block, `tng-blank` template) exist so header/footer links don't dead-end; a seeder "heal pass" points any option link still on `#` at the page whose title matches its label (customised URLs untouched). The bare `/insights/` URL is the placeholder page; insight singles at `/insights/<slug>/` coexist fine.
  - **Enqueues**: fonts, blaze/lenis css, style.css, `wp-overrides.css` (kills TT25 global styles leftovers, body 200vh, blockGap, `[hidden]` fix), then gsap → ScrollTrigger → SplitText → blaze → lenis → main.js → `tng-wp.js`. Editor gets style.css + `editor.css` but **never main.js**. TT25 global styles are dequeued; admin bar hidden on the front end.
  - **`tng-wp.js`** (WP-only): professionals roster filtering (search + A–Z by surname + View more, client-side over the endpoint; facet dropdowns still await their open-panel design) and the homepage insights tab content swap (per-tab JSON embedded by the block). It must load after main.js; the alphabet listener waits a tick so main.js settles the active state first.
  - **REST**: `GET /wp-json/tng/v1/roster` — transient-cached, busted on professional/term saves.
  - **Seeder**: Tools → **TNG Seed**, idempotent and since 1.0.3 **strictly create-only**: existing posts/pages/office terms/filled fields are never touched, so re-running can never wipe editor work. (Earlier it re-wrote page content on every run; one re-run clobbered image fields the user had set on the Home and Careers pages — recovered from revisions. Block image fields live in page content, so anything that rewrites content wipes them.) Creates terms + offices, 10 professionals (Aimee Van Dyk fully populated), ~14 insights (AI Act article with full body; Global Disputes Forecast as Module with panels + findings), 3 awards (related to Aimee), 5 jobs × 4 countries, the four pages (block markup with flat ACF data), options values, permalinks (/%postname%/), front page = Home.
- **Toggles verified**: unchecking a section toggle removes the section (and its pro-tab). Fields never saved count as ON (`tng_show`: null → true), so bare blocks render with defaults.
- **Images**: no Media Library seeding. Templates fall back to bundled plugin assets (banner, texture, map, portraits, logos, flags, badge) and picsum placeholders exactly like the static pages; any image field the client fills overrides the fallback. The three big JPGs are compressed **in the plugin copy only** (repo originals untouched): Rectangle 1 2.3MB→154KB, hp-location-bg 6.6MB→562KB, module-bg 0.9MB→88KB.

### Deploying plugin updates

1. Edit `wp/tng-core/**`, then **bump `TNG_CORE_VERSION`** (both places in tng-core.php) — Cloudflare caches assets by `?ver=`, so no bump = stale css/js for visitors.
2. Build the zip with **bsdtar, not Compress-Archive** (Compress-Archive writes backslash entry paths that the Linux server mangles into a nested broken install):
   `cd wp; mkdir _zip; cp -r tng-core _zip/tng; cd _zip; tar -a -c -f ../tng-core.zip tng; cd ..; rm -rf _zip` — the zip root folder **must be `tng`** because that's the installed folder name.
3. wp-admin → Plugins → Add Plugin → Upload → select zip → Install Now → **"Replace current with uploaded"**.
4. Content/field changes: regenerate `wp/scf-import.json` (`node wp/build-scf-json.mjs`) and re-import via SCF → Tools. Field keys are shared between the generator and `inc/seed.php` — keep them in sync. Re-running TNG Seed is safe.

### Known quirks / cleanup

- A stray empty `wp-content/plugins/tng-core/` folder is left on the server from a first failed upload (the Compress-Archive bug); WP can't delete it without file access — remove it whenever FTP/file-manager access exists. That's why the live plugin folder is `tng/`, not `tng-core/`.
- Permalinks 404'd after seeding until Settings → Permalinks was re-saved once (.htaccess write) — done; remember it if rewrites ever go stale.
- The seeder wraps `wp_insert_post` input in `wp_slash()` (fixed in 1.0.2): WP strips one level of backslashes from post data, which once turned the JSON escapes inside serialized ACF block attrs (`<` for `<`, `&` for `&`) into literal `u003c…` junk on the Professionals/Contact intros and the Careers people text. Any future import script (e.g. the 700-lawyer roster) must `wp_slash()` too, or names like O'Brien lose characters.
- Plugin replace briefly deactivates the plugin ("Removing the current plugin…"); an editor save hitting that window fails silently (this is how an insight's featured image once didn't stick). Avoid deploying while someone is editing.
- Restoring editor work: WP keeps page revisions; the REST route `/wp-json/wp/v2/pages/<id>/revisions?context=edit` (with the `wpApiSettings.nonce` from any editor page) returns raw block markup that can be PUT back — that's how the overwritten Home/Careers edits were recovered.
- Insight hero breadcrumb/"Insights" links point to `#` until an insights archive page exists (same for header/footer Insights links).
- Seeded posts carry the mock's future dates (a seed-time filter forces `publish`). Feature-card ordering within a day is via post times.
- Standard insights have no featured image set, so they all render the `--no-image` hero variant; set a featured image on a post to get the image variant.
- Module download banner + related-insights sections hide themselves (no report PDF uploaded, no other Fintech insights) — expected content-driven behaviour.
- The GSAP console warning `.pro-card not found` on pages without a grid is benign.
- `wp/tng-core.zip` in the repo is the currently-deployed build artifact.

### Still to come (unchanged from the frontend list + new)

- Pages with no mockup: Insights archive, Awards archive/single body, Location term pages, Services, Job single/listing, search, 404 — all of these now render the **under-development page** (`under-development.html` / `tng/under-development`) until their designs arrive; replacing one means registering a real template (or filling the placeholder page) over it. The pro-filter facet open panels await design; real filtering for them is wired to add on top of the roster JSON.
- Real data: the 700-row roster import (script it through a `tng-core` importer or WP-CLI — never by hand), real office details (5 of 6 currently repeat Bangkok's, as in the mock), report PDFs, real images, logo SVG.
- Site search endpoint + UI (mockup pending).
