# TNG — Handover

Law firm website (Tilleke & Gibbins). Frontend is being built as static HTML in this folder first; the WordPress backend (SCF fields + content seeding) comes at the end. Last updated 2026-09-22.

## Division of labour

- **User** builds the frontend design in HTML/CSS. Claude helps build sections from mockups on request.
- **Claude** owns the backend later: use the Playwright browser to drive wp-admin, set up Secure Custom Fields (SCF, the ACF fork), and seed content. Do not touch WordPress until the user says so. User logs into the browser themselves; never ask for credentials.

## Site facts

- wp-admin: https://tng.mydemobb.com/wp-admin/ (WordPress 7.1.1). SCF is installed and active (menu "SCF": Post Types, Taxonomies, Options Pages UIs). Other active plugins: Insert PHP Code Snippet, Simple Custom CSS and JS, SVG Support, Disable Comments RB, Headers Security Advanced & HSTS WP.
- Theme: Twenty Twenty-Five (block theme). Client edits in Gutenberg and wants to preview pages, so each section is planned as an SCF block with a show/hide toggle. CPT singles and taxonomy archives live in the Site Editor, not PHP templates.

## Content model (agreed with user)

- **Post types:** Insight (two inner-page templates, differ in layout and possibly fields; togglable sections), Professional (~700 lawyers; grid page with search, alphabet, and facet filters; inner page with overview, experience, tied insights, tied awards; togglable sections), Award (own inner page; tied to professionals, locations, later services), Careers/job posts (later), Services (later).
- **Taxonomies:** industry, practice, location (fixed 6–7 terms, shared by professionals/insights/awards; term pages get SCF fields for hero/intro), position, language, insight category, tag.
- **Filter plan for professionals:** custom REST endpoint returns the whole roster once (compact JSON, transient-cached, invalidated on save); all filtering runs client-side. 700 professionals must be imported by script, not clicked in.
- **Decisions pending:** none blocking. Later: services/careers post types plug in via SCF Post Types UI + existing shared taxonomies.

## Local project

```
index.html              homepage: header, 5 sections, footer
assets/css/style.css    globals + per-section styles
assets/js/main.js       Lenis, GSAP animations, tabs, marquee, parallax
assets/vendor/          gsap, ScrollTrigger, SplitText, blaze-slider, lenis (copied from node_modules)
assets/*.jpg|png        images (Rectangle 1.jpg = banner, hp-location-bg.jpg = teal texture,
                        regional-presence.jpg = map, guy-1/lady-2 = portraits, legal-500.png, ALB badge,
                        lexmundi/multilaw/dna.png = footer network logos at natural size)
```

Preview: `npx serve -l 5173 .` then http://localhost:5173/. The Playwright browser cannot open file:// URLs.

Note: `Rectangle 1.jpg` (2.4 MB) and `hp-location-bg.jpg` (6.7 MB) need compressing before production.

## Conventions

- **Measurements are vw on a 1440px base** (px / 1440 × 100). Every value carries a `/* px */` comment. User supplies mockups with red measurement markers; convert each to vw.
- **Type classes in globals (user owns these):** `.h1 .h2 .h3 .h4 .body .subhead .button-label .counter .counter-label`, `.dark` forces white text. Palette vars: `--white --red --navy --black --grey`. Side paddings: `--sidepadding-xs` (20px) and `--sidepaddong-m` (85px, typo is the user's, keep it).
- **Shared pieces:** `.link-arrow` (text + chevron + red underline that draws in on scroll, arrow nudges on hover), `.arrow-circle` / `.arrow-circle--lg` (red circle icon). The banner still uses its own `.hp-featured-banner__icon`; the location cards reuse that class too.
- **Section naming:** `hp-<section>` BEM blocks. Each `<section>` has `data-section` and `data-toggle` attributes plus a comment listing the intended SCF block and fields, so the HTML → SCF mapping is explicit.
- **Parallax:** add `has-parallax` to a section and put `<img class="parallax-img">` inside. Overlays are `::before` at z-index -1; the image sits at -2.
- **Grey rules** are pseudo-elements scaled from 0 and revealed by an `is-in` class (added by ScrollTrigger batch in main.js).

## Animation system (main.js)

- Headings (`.h1–.h4`, `.counter-label`): SplitText lines with `mask: 'lines'`, rise from `TEXT_START` (180%) with 0.1s line stagger. Masks get a 0.2em bottom bleed (padding + negative margin) so descenders aren't clipped; split elements become `flow-root` so height stays exact.
- Body (`.body`, `.subhead` that aren't links): lines + words, word stagger with `amount` capped at 0.9s.
- Counters (`.counter`): count up from 0, keep suffix like "+".
- Groups (`GROUPS` list): children rise 40px and fade with 0.12s stagger; text/counters inside a group child delay by that child's index.
- Rules and `.link-arrow` underlines: `is-in` class via `ScrollTrigger.batch`, 0.12s between siblings.
- Careers marquee: columns cloned once and looped by −50% (left up, right down). Marquee container uses `align-items: flex-start` so the loop distance is exact.
- Tabs: active-state toggle only; content swap waits for WP data.
- Splits wait for `document.fonts.ready`. Text targets are hidden via `gsap.set` at script start to avoid a flash.
- Footer reveals use `startFor(el)` → `top bottom` instead of the 88% line, because the page ends at the footer and its bottom rows can never reach 88%. `drawRules` runs two batches for the same reason.
- Element screenshots in Playwright time out on this page because the marquee never settles; use viewport screenshots. If the Playwright MCP browser is locked by another session, drive the ms-playwright Chromium over CDP from a Node script instead (a full-page `--screenshot` with a tall window breaks the 100vh banner).

## Sections built (index.html order)

0. Header (`<header class="site-header">`, before `<main>`): fixed, 89 tall and transparent over the banner; past `data-scrolled-at` (40px, toggled from the Lenis scroll event in main.js) it gets `is-scrolled`: white bar, navy text and logo, grey rule, compact to 72 tall. Main links get a pill on hover. Locations has a megamenu (`.has-mega` → `.site-header__mega`): opens on hover/focus-within (or `.is-open` for scripting), white card 12 below the pill, list of Location terms + Global Reach with the active one black + red circle, 187×226 image that crossfades per item (main.js swaps `is-active`). Placeholder picsum images for now. Services has a second megamenu (`.site-header__mega--services`): Industries list in the same style (active follows hover, 9 between rows), divider, Practices in a two-column grid (`--rows` inline var sets items per column; 6 now). Practices are static, not filtered by industry. Reveal stagger is generic: any `li` inside a `.site-header__mega`, delays for up to 12 items. Header type classes drop `.dark` and inherit colour from the header, because `.dark` is `!important` and would block the swap. Logo 21/9, rule at 35 spanning 200→1258, top row (About/Careers/Contact, `.subhead`) 9 above the rule, main row (`.button-label`) 9 below, both centred in that span; Search + ENG centred on the rule at 17 from the right. No mobile menu yet, search and language are static. Maps to an SCF options page with two WP menus. Logo is the same text stand-in as the footer.

1. Featured banner, 100vh, parallax + navy multiply gradient, recent insights panel.
2. Intro + counters + regional presence (teal texture parallax, black fade overlay, map panel, 6 location cards).
3. Insights by industry: label with grey rule, tabs (`.h3`, red indicator on active), 643×517 feature card, 3-row list.
4. Careers: heading + link, T-shaped rules with note + ALB badge, two-column portrait marquee. Background is solid navy; the textured version isn't in assets yet.
5. Awards & Rankings: light grey panel, heading + Legal 500 logo, description + Read more, white card.

6. Footer (`<footer id="footer" class="hp-footer">`, outside `<main>`): #041f36 background, grid with the right column at 728px; brand column (logo, 3 outlined social circles, newsletter form), Networks logos, Quick links in two columns, bottom bar with copyright and legal links. Maps to an SCF **options page**, not a block (see the comment in index.html).

Placeholder images (picsum.photos) remain on the location cards and the insights feature image. The footer logo is a text stand-in in Times (`.hp-footer__logo`); swap in the real wordmark SVG when supplied. The newsletter form has no action yet.

## Next steps

- User continues with remaining homepage sections and other pages (professionals grid + inner, insight inner ×2 templates, location inner, awards inner).
- When the user says go: derive SCF field groups from the `data-section` comments, register CPTs/taxonomies in SCF's UI, write a must-use plugin for SCF blocks + the roster REST endpoint, seed sample content, then import the real roster from the client's spreadsheet.
