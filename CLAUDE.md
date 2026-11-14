# t-n-g: how to work on this project

Tilleke & Gibbins law firm site. Static HTML pages in this folder → ported into the `tng-core` WordPress plugin → live at https://tng.mydemobb.com/. **Read [HANDOVER.md](HANDOVER.md) first**: it has the full state, every section's measurements, the deploy history and what's still to come. This file covers how the user works and the rules that have been learned the hard way.

## The user's work style

- Messages are short and casual, often just a screenshot, a mockup with red measurement markers, or a Figma link. Work out the rest from the code, HANDOVER and the design; don't ask about things you can check yourself. Ask only for real decisions (see "Open questions" below).
- The user expects jobs done **end to end**: build the static page → port it to WordPress → deploy → verify on live. "Make sure all is good" means deploy and verify on the live site at 1440, not just locally.
- The user often hands over and leaves (going to sleep, working away from home). Finish the whole job, verify it, then report once. Don't stop halfway to ask something with a sensible default.
- Reports: what changed, live URLs, what was verified (and how), and a short list of what needs the user or client (content to confirm, licensing, design gaps, open bugs). No long narration.
- When the user says "keep this in memory" (client rules, open questions), write it into this file or HANDOVER.md. Claude's local memory does not travel between the user's two machines; the repo does.
- Git: commit only when asked. Commits go straight to `master` and get pushed (the user works from two machines). `temporary/` (client QA spreadsheet) stays out of git.
- Playwright: the user's Playwright browser normally stays logged in to wp-admin; try it first. If it's logged out, ask the user to log in. **Never ask for credentials.**
- Mac machine (no Playwright MCP there): launch the ms-playwright Chromium visibly with `--user-data-dir=.playwright-mcp/profile --remote-debugging-port=9222` (the profile keeps the wp-admin login) and drive it over CDP from a small Node script (Node's built-in WebSocket; `Emulation.setDeviceMetricsOverride` 1440 wide).
- Figma: when real assets are needed the user gives the file link plus a short-lived personal access token. Use it via the REST API, never write the token into the repo, delete any copy when done.
- Keep HANDOVER.md current whenever something is built, deployed or decided.

## Frontend conventions (static HTML)

- **All measurements are vw on a 1440 base** (px / 1440 × 100), each with a `/* px */` comment. Mockups are sometimes scaled: check a known width first.
- **The user owns the type classes** (`.h1–.h4 .body .subhead .button-label .counter .counter-label`, `.dark`) and the root vars. Never rename them; `--sidepaddong-m` is a deliberate typo, keep it. When the user adds a class, migrate usages to it.
- Sections: page-prefixed BEM blocks, each with `data-section`, `data-toggle` and a comment naming the SCF block + fields. Animations live in `assets/js/main.js` (`GROUPS`, `RULES`, SplitText reveals). Details: HANDOVER "Conventions" and "Starting a new page".
- Preview: `npx serve -l 5173 .` → http://localhost:5173/ (Playwright can't open file:// URLs). Verify with `getBoundingClientRect` at 1440. Use viewport screenshots (element screenshots time out because of the marquee) and save them inside the project (`.playwright-mcp/`, gitignored).
- Python on Windows: open repo files with `newline=''` (files are LF).

## WordPress rules

- Plugin source `wp/tng-core/`; live folder is `wp-content/plugins/tng/`, so the zip root folder must be `tng`.
- **Every deploy:**
  1. Copy `assets/css/style.css` and `assets/js/main.js` into `wp/tng-core/assets/`, and concatenate `assets/css/responsive/*.css` in the order `assets/css/responsive.css` imports them into `wp/tng-core/assets/css/responsive.css`: `cat assets/css/responsive/{pages,foundation,home,people-insights,services,about,careers}.css > wp/tng-core/assets/css/responsive.css` (WP gets no @import waterfall).
  2. Bump `TNG_CORE_VERSION` in both places in `tng-core.php` (Cloudflare caches assets by `?ver=`).
  3. Run `php -l` on every PHP file (a load-time fatal takes wp-admin down) and check none has a UTF-8 BOM. Never write PHP with PowerShell `Set-Content` / `Out-File`: the BOM it adds locked wp-admin once. If no PHP is installed, download the portable "latest" zip from windows.php.net into the scratchpad (Windows). On the Mac, `brew install php` fails (it tries to build httpd / LLVM from source); download the static CLI from https://dl.static-php.dev/static-php-cli/common/ (`php-8.3.x-cli-macos-aarch64.tar.gz`) into the scratchpad instead.
  4. Zip with bsdtar: from Git Bash `/c/Windows/System32/tar.exe -a -c -f` (not `Compress-Archive`, not Git's own tar). On the Mac, `/usr/bin/tar` is bsdtar: `tar -a -c -f ../tng-core.zip tng` from the staging folder (`COPYFILE_DISABLE=1` keeps `._*` files out).
  5. wp-admin → Plugins → Add Plugin → Upload → "Replace current with uploaded" (Playwright: `page.setInputFiles('#pluginzip', path)`).
  6. If fields changed: `node wp/build-scf-json.mjs`, then SCF → Tools → Import `wp/scf-import.json`. **Import before seeding.** (Scripted: the form also has a hidden input named `acf_import_file`; target `input[type=file][name=acf_import_file]` and click `button[name=import_type][value=json]`, then check for "Imported N items".)
  7. Tools → TNG Seed.
  8. Verify on live at 1440: section heights match the static page, no broken or placeholder images, no PHP notices, console shows only the known warnings (Cloudflare beacon, GSAP `.pro-card not found`).
- **Live content belongs to the user and client.** The seeder is strictly create-only: it never touches existing posts, pages, terms or filled fields. The only exception is a page whose content is still exactly `<!-- wp:tng/under-development /-->`. Block field data lives inside page content, so never rewrite page content. Prefer render-time healing in templates. If something gets overwritten, restore it from page revisions (REST `/wp-json/wp/v2/pages/<id>/revisions?context=edit`).
- Field keys are deterministic (`field_tng_<prefix>_<name>`) and shared between `wp/build-scf-json.mjs` and `inc/seed.php`. Keep them in sync. Avoid field names that shadow WP or block attributes (`style`, `anchor`, `layout`, `post`, `post_type`, `name`). Any import script must `wp_slash()` its input.
- Imported posts (`_tng_src`) may be corrected only while untouched (`post_modified_gmt` = `_tng_mod`): loader op `resync`, dry run first, read the counts, then `--write` (it skips and reports edited posts and keeps the previous values in `_tng_resync`). Store upload URLs root-relative, never the current host (it changes at cutover; HANDOVER "Cutover").
- Old URLs: core's 404 slug guessing is off (it opened the wrong person / article), so every old path needs a rule: the import map (option `tng_redirects`), `tng_fix_redirects()` / the patterns in `tng_legacy_target()` (inc/helpers.php). Re-run the audit's URL lists after changing them.
- Images: an uploaded field always wins; otherwise `tng_bundled_img()` serves the Figma exports bundled in `wp/tng-core/assets/img/` (HANDOVER "1.1.3").
- Header → Logo and Footer → Logo are the same stored option (`options_logo`).
- Avoid deploying while someone is editing: replacing the plugin deactivates it for a moment.
- Playwright quirks: a stale chrome can lock the profile (kill the `ms-playwright-mcp` chrome.exe processes). Elements hidden by GSAP reveals can't be clicked normally, so call `el.click()` in evaluate. Lazy images never fire `load` off-screen, so don't await them; HTTP-check the URLs instead.

## Client rules

- **The client must be able to update the site easily from wp-admin (user, 2026-11-14).** When building anything, prefer what an editor can change without a developer: content from pages / posts / terms / SCF fields, lists that fill themselves (e.g. the header top-row dropdowns list a page's child pages), no hard-coded copy or links in templates. If something can only be changed in code, say so in the report.

- **Service hero contacts** (industry, practice and subpractice pages; from reviewer gelo.c): hide a country tab that has no contacts. The active tab follows the visitor's country (KH / ID / LA / MM / VN → that one, everyone else → Thailand), falling back to Thailand, then to the first visible tab. It is chosen client-side via `GET /wp-json/tng/v1/geo` (Cloudflare `CF-IPCountry`), because HTML may be edge-cached. Already built (1.1.0).
- **Client QA tracker**: `temporary/Tilleke & Gibbins - Website Feedback.xlsx` (not in git; copy it by hand between machines). Columns run No. → DEV's Note. Status flow: "To be reviewed by DEV" → fix → "Awaiting confirmation" plus a DEV's Note → the reviewer sets "Done" or "Further amendment". When the user hands back a filled tracker, fix the items and report per row (No. + status + DEV's Note). Don't edit the user's copy without asking. Google Sheets drops in-cell screenshots on .xlsx download, so ask for screenshots in chat.

## Open questions (ask before acting)

- Career path pages, "Opportunities for <path>": should the job list be grouped or filtered by location? It's built as one flat list; the user isn't sure.
- Content to confirm with the client: CSR contact phone / email, the museum's two different emails, office details for 5 of 6 locations, the Laos photo licence (watermarked iStock comp in Figma).
- ~729 existing insights and ~700 professionals still need importing. Propose scripted, idempotent, create-only imports (check what the old site runs: WXR / REST / sitemap scrape; map old categories to the shared taxonomies, sideload images, keep slugs or add redirects). Test on ~20 before the full run.
