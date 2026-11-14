// TNG — frontend scripts

gsap.registerPlugin(ScrollTrigger, SplitText);

// Lenis smooth scroll, synced to GSAP's ticker
const lenis = new Lenis();
lenis.on('scroll', ScrollTrigger.update);
gsap.ticker.add((time) => lenis.raf(time * 1000));
gsap.ticker.lagSmoothing(0);

// Below 1200 the site uses the small (phone / tablet) layout: see assets/css/responsive/
const SMALL = window.matchMedia('(max-width: 1199px)');
// Reduced motion: no reveals, counters or marquee; content is shown as it is
const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------
   Header: fixed over the page; past data-scrolled-at px it turns
   into the white, compact bar (see .site-header.is-scrolled).
   ------------------------------------------------------------ */
const header = document.querySelector('.site-header');
if (header) {
  const threshold = Number(header.dataset.scrolledAt) || 40;
  const setScrolled = (y) => header.classList.toggle('is-scrolled', y > threshold);
  setScrolled(window.scrollY);
  lenis.on('scroll', ({ scroll }) => setScrolled(scroll));
}

/* Megamenu: hovering or focusing a location marks it active and shows
   its image. The panel itself opens on CSS :hover / :focus-within. */
document.querySelectorAll('.site-header__mega').forEach((mega) => {
  const items = [...mega.querySelectorAll('.site-header__mega-list li')];
  const images = [...mega.querySelectorAll('.site-header__mega-media img')];
  const activate = (i) => {
    items.forEach((li, j) => li.classList.toggle('is-active', j === i));
    images.forEach((img, j) => img.classList.toggle('is-active', j === i));
  };
  items.forEach((li, i) => {
    li.addEventListener('mouseenter', () => activate(i));
    li.querySelector('a').addEventListener('focus', () => activate(i));
  });
});

/* Mobile / tablet menu: the toggle opens the nav as a full-screen panel;
   megamenus become accordions. Lenis is stopped (html gets overflow: clip
   from lenis.css) and the page behind is made inert. */
const toggle = document.querySelector('.site-header__toggle');
if (header && toggle) {
  const nav = header.querySelector('.site-header__nav');
  const triggers = [...nav.querySelectorAll('.has-mega > a')];
  nav.setAttribute('data-lenis-prevent', '');
  const behind = [...document.querySelectorAll('main, footer')];

  // An accordion hides its parent's own page, so its first row links there
  // ("All services") whenever the trigger has a real URL. Hidden on desktop.
  // Top-row dropdowns (About / Careers) are accordions here too.
  triggers.forEach((a) => {
    const href = a.getAttribute('href');
    if (!href || href === '#') return;
    const all = document.createElement('a');
    all.href = href;
    all.className = 'site-header__mega-all';
    const label = a.textContent.trim();
    // Top row (About / Careers): "About overview", not "All about"
    all.innerHTML = `${a.closest('.site-header__top') ? `${label} overview` : `All ${label.toLowerCase()}`}<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M3 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
    a.nextElementSibling?.prepend(all);
  });

  // Triggers are disclosure buttons only in the small layout
  const syncTriggers = () => triggers.forEach((a) => {
    if (SMALL.matches) a.setAttribute('aria-expanded', String(a.parentElement.classList.contains('is-open')));
    else a.removeAttribute('aria-expanded');
  });

  const setMenu = (open) => {
    header.classList.toggle('is-menu-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Menu');
    behind.forEach((el) => { el.inert = open; });
    if (open) lenis.stop(); else lenis.start();
    if (!open) header.querySelectorAll('.has-mega.is-open').forEach((li) => li.classList.remove('is-open'));
    syncTriggers();
  };
  syncTriggers();

  toggle.addEventListener('click', () => setMenu(!header.classList.contains('is-menu-open')));
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || !header.classList.contains('is-menu-open')) return;
    setMenu(false);
    toggle.focus();
  });
  nav.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link || !SMALL.matches) return;
    const mega = link.parentElement.matches('.has-mega') ? link.parentElement : null;
    if (mega) { // trigger: expand / collapse instead of navigating
      e.preventDefault();
      const open = mega.classList.toggle('is-open');
      link.setAttribute('aria-expanded', String(open));
      return;
    }
    setMenu(false); // a real link: close, then navigate
  });
  // Crossing to desktop: .has-mega.is-open would open a desktop megamenu
  SMALL.addEventListener('change', () => setMenu(false));
}

/* ------------------------------------------------------------
   Text reveals
   Headings: each line rises out of a mask.
   Body copy: each word rises out of its line's mask, staggered so a
   long paragraph finishes in about the same time as a short one.
   ------------------------------------------------------------ */
// Contact office tabs and panels are left out: tab buttons can't hold split
// lines, and hidden panels would split at zero width. They rise as groups.
const HEADINGS = '.h1:not(.about-vision__tab), .h2:not(.contact-offices__tab), .h3:not(.hp-insights__tab, .about-acc__btn, .more-from__link), .h4:not(.contact-office__name), .counter-label, .in-findings__num';
const BODY = '.body, .subhead:not(a)';
const REVEAL_START = 'top 88%';
const EASE = 'power3.out';
// How far below the mask floor text starts, as a % of its line height.
// Anything past ~110 is hidden by the mask, so higher values mostly add speed.
const TEXT_START = 180;

// The footer sits at the very end of the page, so its top can never reach
// the 88% line; its reveals fire as soon as the element enters the viewport.
function startFor(el) {
  return el.closest('.hp-footer') ? 'top bottom' : REVEAL_START;
}

// Masks clip at the line box, so descenders (g, p, y) would be cut off on
// tight line-heights. Extend each mask below its line and pull the next
// line back up by the same amount so layout doesn't move.
const MASK_BLEED = '0.2em';

/* ------------------------------------------------------------
   Groups: children of these containers rise in one after another.
   Text inside a child waits for that child's slot, so a card and
   the copy in it read as one staggered unit.
   ------------------------------------------------------------ */
const GROUPS = [
  '.hp-featured-banner__list',
  '.hp-intro__counters',
  '.hp-intro__locations',
  '.hp-insights__tab-list',
  '.hp-insights__list',
  '.hp-awards__panel',
  '.hp-footer__social',
  '.hp-footer__networks',
  '.hp-footer__quick-col',
  '.pro-hero__locations',
  '.pro-hero__contacts',
  '.loc-hero__lines',
  '.info-hero__lines',
  '.mus-audience',
  '.tex-gallery__grid',
  '.tex-gallery__social',
  '.loc-successes__list',
  '.svc-hero__places',
  '.svc-hero__panels',
  '.svc-subs__list',
  '.about-acc',
  '.about-vision__tabs',
  '.about-vision__panels',
  '.more-from__list',
  '.ins-chips',
  '.ins-grid__list',
  '.job-cta__list',
  '.lp-team__tabs',
  '.lp-team__panels',
  '.in-hero__meta',
  '.in-panels__list',
  '.contact-offices__tabs',
  '.contact-offices__panels',
  '.career-paths__list',
  '.careers-people__media',
  '.careers-jobs__list',
  '.job-list__filter',
  '.in-related__list',
  '.in-share',
  '.pro-facts',
  '.pro-related__cards',
].join(', ');
const GROUP_STAGGER = 0.12;

// A stacked group taller than the screen (small layout) reveals each child as it arrives
const isTallSmall = (group) => SMALL.matches && group.offsetHeight > window.innerHeight;

// Delay for an element based on which group child it sits in (0 if none)
function groupDelay(el) {
  const group = el.closest(GROUPS);
  if (!group || isTallSmall(group)) return 0;
  const child = [...group.children].find((c) => c.contains(el));
  return child ? [...group.children].indexOf(child) * GROUP_STAGGER : 0;
}

function revealGroup(group) {
  if (isTallSmall(group)) {
    gsap.set(group.children, { y: 40, autoAlpha: 0 });
    ScrollTrigger.batch(group.children, {
      start: startFor(group),
      once: true,
      onEnter: (batch) => gsap.to(batch, { y: 0, autoAlpha: 1, duration: 1, ease: EASE, stagger: GROUP_STAGGER }),
    });
    return;
  }
  gsap.from(group.children, {
    y: 40,
    autoAlpha: 0,
    duration: 1,
    ease: EASE,
    stagger: GROUP_STAGGER,
    scrollTrigger: { trigger: group, start: startFor(group), once: true },
  });
}

// SplitText's default aria ("auto") hides the split lines from screen readers
// and labels the parent instead, which also hides any link inside the copy
// (while it stays focusable). Copy with links keeps its own markup readable.
const ariaFor = (el) => (el.querySelector('a') ? 'none' : 'auto');

function bleedMasks(split, el) {
  // A link that sits on a line break is cloned into each line; a clone left
  // with no text would be an empty focus stop
  el.querySelectorAll('a').forEach((a) => { if (!a.textContent.trim() && !a.children.length) a.remove(); });
  gsap.set(split.masks, { paddingBottom: MASK_BLEED, marginBottom: `-${MASK_BLEED}` });
  // A plain block lets the last mask's negative margin collapse through it,
  // which would leave the element one bleed taller. flow-root contains it.
  if (['block', 'list-item'].includes(getComputedStyle(el).display)) el.style.display = 'flow-root';
}

function revealHeading(el) {
  SplitText.create(el, {
    type: 'lines',
    mask: 'lines',
    linesClass: 'split-line',
    aria: ariaFor(el),
    autoSplit: true,
    onSplit(self) {
      bleedMasks(self, el);
      return gsap.from(self.lines, {
        yPercent: TEXT_START,
        duration: 1,
        ease: EASE,
        stagger: 0.1,
        delay: groupDelay(el),
        scrollTrigger: { trigger: el, start: startFor(el), once: true },
        onStart: () => gsap.set(el, { visibility: 'visible' }),
      });
    },
  });
}

function revealBody(el) {
  SplitText.create(el, {
    type: 'lines,words',
    mask: 'lines',
    linesClass: 'split-line',
    wordsClass: 'split-word',
    aria: ariaFor(el),
    autoSplit: true,
    onSplit(self) {
      bleedMasks(self, el);
      // total stagger grows with word count but is capped, so long copy
      // reads as one sweep rather than a slow trickle
      const amount = Math.min(0.9, self.words.length * 0.035);
      return gsap.from(self.words, {
        yPercent: TEXT_START,
        duration: 0.8,
        ease: EASE,
        stagger: { amount },
        delay: groupDelay(el),
        scrollTrigger: { trigger: el, start: startFor(el), once: true },
        onStart: () => gsap.set(el, { visibility: 'visible' }),
      });
    },
  });
}

/* ------------------------------------------------------------
   WYSIWYG copy (.rich-text): no classes to hook, so elements are
   picked by tag. Headings and text blocks get the line reveal (no
   word split: long articles would create thousands of spans);
   images and figures rise in like group children.
   ------------------------------------------------------------ */
const CONTENT_TEXT = ['h2', 'h3', 'h4', '> p', 'li', 'blockquote']
  .map((t) => `.rich-text:not(.contact-office__address) ${t}`)
  .join(', ');
const CONTENT_MEDIA = '.rich-text > figure, .rich-text > img, .rich-text > p > img:only-child';

function revealMedia(el) {
  gsap.from(el, {
    y: 40,
    autoAlpha: 0,
    duration: 1,
    ease: EASE,
    scrollTrigger: { trigger: el, start: REVEAL_START, once: true },
  });
}

/* ------------------------------------------------------------
   Counters: "250+" counts up from 0 to 250 and keeps its suffix.
   ------------------------------------------------------------ */
function countUp(el) {
  const match = el.textContent.trim().match(/^([^\d]*)(\d[\d,]*)(.*)$/);
  if (!match) return;
  const [, prefix, digits, suffix] = match;
  const target = parseInt(digits.replace(/,/g, ''), 10);
  const state = { value: 0 };
  el.textContent = `${prefix}0${suffix}`;
  gsap.to(state, {
    value: target,
    duration: 2,
    ease: 'power2.out',
    delay: groupDelay(el),
    scrollTrigger: { trigger: el, start: REVEAL_START, once: true },
    onUpdate: () => {
      el.textContent = `${prefix}${Math.round(state.value).toLocaleString('en-US')}${suffix}`;
    },
  });
}

/* ------------------------------------------------------------
   Rules and link underlines: CSS handles the draw, JS adds .is-in
   when the element scrolls into view. Batched so siblings stagger.
   ------------------------------------------------------------ */
const RULES = [
  '.hp-featured-banner__item',
  '.hp-insights__label',
  '.hp-insights__item',
  '.hp-careers__foot',
  '.hp-careers__rule',
  '.hp-footer__bar',
  '.link-arrow',
  '.pro-label',
  '.pro-related + .pro-related',
  '.in-findings__item',
  '.in-related-insights',
].join(', ');

function drawRules() {
  const all = [...document.querySelectorAll(RULES)];
  const inFooter = (el) => Boolean(el.closest('.hp-footer'));
  [all.filter((el) => !inFooter(el)), all.filter(inFooter)].forEach((els) => {
    if (!els.length) return;
    ScrollTrigger.batch(els, {
      start: startFor(els[0]),
      once: true,
      onEnter: (batch) => {
        batch.forEach((el, i) => gsap.delayedCall(i * 0.12, () => el.classList.add('is-in')));
      },
    });
  });
}

/* ------------------------------------------------------------
   Filter bars (professionals, insights, awards): dropdown panels.
   Each trigger (aria-controls) has its panel right after it in the
   DOM, so Tab runs trigger → options → next trigger. Options are
   buttons with aria-pressed; arrows / Home / End move between them,
   Esc, a click outside or tabbing out closes. Choosing one relabels
   the trigger and fires `tng:facet` ({ key, value, option }) on the
   .pro-filter; the archive controller below and tng-wp.js (roster)
   do the filtering. Panels hang in .pro-filter (sticky on desktop,
   relative below 1200) and are placed from their trigger.
   ------------------------------------------------------------ */
const triggerOf = (panel) => document.querySelector(`[aria-controls="${panel.id}"]`);

// Select `value` in a panel ('' = its "all" row) and relabel its trigger
function setFacet(panel, value) {
  let picked = null;
  panel.querySelectorAll('.flt-option').forEach((o) => {
    const on = o.dataset.value === value;
    o.setAttribute('aria-pressed', String(on));
    if (on) picked = o;
  });
  if (!picked) return setFacet(panel, '');
  const trigger = triggerOf(panel);
  const label = trigger?.querySelector('span');
  if (!label) return picked;
  const set = value !== '';
  label.textContent = set ? trigger.dataset.prefix + picked.textContent.trim() : trigger.dataset.label;
  if (set && !trigger.dataset.prefix) {
    const sr = document.createElement('span');
    sr.className = 'visually-hidden';
    sr.textContent = `${trigger.dataset.label}: `;
    label.prepend(sr);
  }
  trigger.classList.toggle('is-set', set);
  return picked;
}

let openPanel = null;

function closePanel(focusTrigger = false) {
  if (!openPanel) return;
  const panel = openPanel;
  openPanel = null;
  panel.hidden = true;
  const trigger = triggerOf(panel);
  trigger.setAttribute('aria-expanded', 'false');
  if (focusTrigger) trigger.focus();
}

// Hang a panel under its anchor inside the bar; keep it on screen
function placePanel(panel, anchor) {
  const bar = panel.closest('.pro-filter');
  const stick = SMALL.matches ? (header?.offsetHeight || 0) : parseFloat(getComputedStyle(bar).top) || 0;
  let b = bar.getBoundingClientRect();
  const a = anchor.getBoundingClientRect();
  const below = a.bottom - b.top; // anchor's bottom inside the bar
  panel.style.top = `${below}px`;
  panel.style.maxHeight = `${Math.max(200, window.innerHeight - stick - below - 24)}px`;
  if (window.innerWidth < 768) {
    panel.style.left = panel.style.minWidth = ''; // phone CSS: full width between the gutters
  } else {
    panel.style.minWidth = `${a.width}px`;
    const edge = SMALL.matches ? 20 : 0; // tablet: keep off the screen edge
    panel.style.left = `${Math.max(edge, Math.min(a.left - b.left, b.width - panel.offsetWidth - edge))}px`;
  }
  // Running off the bottom: scroll until the bar sits under the header
  const over = panel.getBoundingClientRect().bottom + 16 - window.innerHeight;
  if (over > 0 && b.top > stick) lenis.scrollTo(window.scrollY + Math.min(over, b.top - stick), { duration: 0.6 });
}

function openFacet(panel) {
  closePanel();
  openPanel = panel;
  const trigger = triggerOf(panel);
  trigger.setAttribute('aria-expanded', 'true');
  panel.hidden = false;
  placePanel(panel, trigger);
  (panel.querySelector('.flt-option[aria-pressed="true"]') || panel.querySelector('.flt-option'))?.focus({ preventScroll: true });
  // opacity only: a visibility: hidden (autoAlpha) row would drop the focus just given
  if (!REDUCED) {
    gsap.fromTo(panel, { opacity: 0, y: -6 }, { opacity: 1, y: 0, duration: 0.3, ease: EASE });
    const rows = panel.querySelectorAll('li');
    gsap.fromTo(rows, { opacity: 0, y: 10 }, { opacity: 1, y: 0, duration: 0.5, ease: EASE, stagger: { amount: Math.min(0.3, rows.length * 0.03) } });
  }
}

function initFilterBar(bar) {
  bar.addEventListener('click', (e) => {
    const trigger = e.target.closest('[aria-controls^="flt-"]:not(input)');
    if (trigger) {
      const panel = document.getElementById(trigger.getAttribute('aria-controls'));
      if (openPanel === panel) closePanel(true); else openFacet(panel);
      return;
    }
    const option = e.target.closest('.flt-panel[role="group"] .flt-option');
    if (!option) return;
    const panel = option.closest('.flt-panel');
    setFacet(panel, option.dataset.value);
    closePanel(true);
    bar.dispatchEvent(new CustomEvent('tng:facet', { detail: { key: panel.dataset.key, value: option.dataset.value, option } }));
  });

  bar.addEventListener('keydown', (e) => {
    const trigger = e.target.closest('[aria-controls^="flt-"]:not(input)');
    if (trigger && e.key === 'ArrowDown') {
      e.preventDefault();
      openFacet(document.getElementById(trigger.getAttribute('aria-controls')));
      return;
    }
    const panel = e.target.closest('.flt-panel[role="group"]');
    if (!panel) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      closePanel(true);
      return;
    }
    const options = [...panel.querySelectorAll('.flt-option')];
    const i = options.indexOf(document.activeElement);
    const moves = { ArrowDown: i + 1, ArrowRight: i + 1, ArrowUp: i - 1, ArrowLeft: i - 1, Home: 0, End: options.length - 1 };
    if (!(e.key in moves)) return;
    e.preventDefault();
    options[(moves[e.key] + options.length) % options.length].focus();
  });

  // Tabbing (or clicking) away from the trigger + panel closes it
  bar.addEventListener('focusout', (e) => {
    if (!openPanel || !bar.contains(openPanel)) return;
    const keep = [openPanel, triggerOf(openPanel)];
    if (e.relatedTarget && !keep.some((el) => el.contains(e.relatedTarget))) closePanel();
  });
}

document.addEventListener('pointerdown', (e) => {
  if (openPanel && !openPanel.contains(e.target) && !triggerOf(openPanel).contains(e.target)) closePanel();
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && openPanel) closePanel(true);
});
// Width changes only: phones fire resize when the URL bar or keyboard comes and goes
let panelWidth = window.innerWidth;
window.addEventListener('resize', () => {
  if (window.innerWidth !== panelWidth) closePanel();
  panelWidth = window.innerWidth;
});

/* ------------------------------------------------------------
   Professional type-ahead (insights / awards "Search by
   Professionals"): an ARIA combobox over the roster JSON
   (data-roster). Choosing a person fires `tng:facet` with
   key "pro" and their slug; typing and pressing Enter submits the
   typed name instead (the archive matches the exact name, then words).
   ------------------------------------------------------------ */
const fold = (s) => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function initTypeahead(input) {
  const list = document.getElementById(input.getAttribute('aria-controls'));
  const bar = input.closest('.pro-filter');
  let roster = null;
  let items = [];
  let active = -1;

  const load = () => roster
    ? Promise.resolve(roster)
    : fetch(input.dataset.roster).then((r) => r.json()).then((data) => (roster = data.map((p) => ({
      name: p.name,
      slug: p.slug || String(p.url || '').split('/').filter(Boolean).pop(),
      position: String(p.position || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(),
      key: fold(p.name),
    }))));
  input.tngRoster = load; // the archive controller looks names up by slug

  const hide = () => {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    active = -1;
  };
  const mark = (i) => {
    active = i;
    list.querySelectorAll('[role="option"]').forEach((li, j) => li.setAttribute('aria-selected', String(j === i)));
    const li = list.children[i];
    if (li && li.id) {
      input.setAttribute('aria-activedescendant', li.id);
      li.scrollIntoView({ block: 'nearest' });
    } else input.removeAttribute('aria-activedescendant');
  };
  const choose = (i) => {
    const p = items[i];
    if (!p) return;
    input.value = p.name;
    input.dataset.slug = p.slug;
    input.dataset.name = p.name;
    hide();
    bar.dispatchEvent(new CustomEvent('tng:facet', { detail: { key: 'pro', value: p.slug, label: p.name } }));
  };

  const render = () => {
    const q = fold(input.value.trim());
    if (q.length < 2) return hide();
    load().then(() => {
      if (fold(input.value.trim()) !== q) return; // typed on meanwhile
      const words = q.split(/\s+/);
      items = roster
        .filter((p) => words.every((w) => p.key.includes(w)))
        .sort((a, b) => Number(!a.key.split(/\s+/).some((w) => w.startsWith(words[0]))) - Number(!b.key.split(/\s+/).some((w) => w.startsWith(words[0]))))
        .slice(0, 8);
      list.innerHTML = items.length
        ? items.map((p, i) => `<li id="${list.id}-${i}" role="option" aria-selected="false" class="flt-option flt-option--pro"><span class="flt-option__name">${escapeHtml(p.name)}</span><span class="flt-option__meta">${escapeHtml(p.position)}</span></li>`).join('')
        : '<li role="option" aria-disabled="true" aria-selected="false" class="flt-panel__empty">No professional matches</li>';
      active = -1;
      const opening = list.hidden;
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      placePanel(list, input.closest('.pro-filter__search'));
      if (opening && !REDUCED) gsap.fromTo(list, { opacity: 0, y: -6 }, { opacity: 1, y: 0, duration: 0.3, ease: EASE });
    }).catch(hide);
  };

  let debounce;
  input.addEventListener('input', () => {
    if (input.value !== input.dataset.name) delete input.dataset.slug; // edited: no longer the chosen person
    clearTimeout(debounce);
    debounce = setTimeout(render, 120);
  });
  input.addEventListener('focus', () => { load().catch(() => {}); });
  input.addEventListener('keydown', (e) => {
    const open = !list.hidden && items.length;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!open) return render();
      e.preventDefault();
      mark((active + (e.key === 'ArrowDown' ? 1 : items.length - 1 + (active < 0 ? 1 : 0))) % items.length);
    } else if (e.key === 'Enter' && open && active >= 0) {
      e.preventDefault();
      choose(active);
    } else if (e.key === 'Escape' && !list.hidden) {
      e.preventDefault();
      e.stopPropagation();
      hide();
    }
  });
  input.addEventListener('blur', () => setTimeout(hide, 150));
  list.addEventListener('mousedown', (e) => e.preventDefault()); // keep focus in the field
  list.addEventListener('click', (e) => {
    const li = e.target.closest('[role="option"]:not([aria-disabled])');
    if (li) choose([...list.children].indexOf(li));
  });
}

/* ------------------------------------------------------------
   Insights / awards archive: the bar drives the grid in place.
   State = the URL query string (keys in inc/archive.php), pushed on
   every change, so a view can be shared and Back / Forward replay it.
   Results come from GET /wp-json/tng/v1/archive (data-source), or on
   the static preview from window.tngArchiveSource (assets/js/static-demo.js).
   Without JS the bar is a plain GET form and the chips are links.
   ------------------------------------------------------------ */
const ARCHIVE_KEYS = ['q', 'pro', 'date', 'res', 'ind', 'prac', 'loc', 'lang', 'sort'];
const CHIP_LABELS = { q: 'Name', pro: 'Professional', date: 'Date', res: 'Resources', ind: 'Industries', prac: 'Practices', loc: 'Locations', lang: 'Language' };
const LANG_NAMES = { ja: 'Japanese', zh: 'Chinese', en: 'English' };
const CLOSE_ICON = '<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M1.5 1.5l7 7M8.5 1.5l-7 7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';

function initArchive(grid, bar) {
  const size = Number(grid.dataset.pageSize) || 12;
  const withCount = grid.dataset.count !== '0';
  const qInput = bar?.querySelector('input[name="q"]');
  const proInput = bar?.querySelector('input[data-roster]');
  const status = grid.querySelector('[data-status]');
  const more = grid.querySelector('.pro-grid__view-more');
  const fromUrl = () => {
    const sp = new URLSearchParams(window.location.search);
    const out = {};
    ARCHIVE_KEYS.forEach((k) => { const v = (sp.get(k) || '').trim(); if (v) out[k] = v; });
    return out;
  };
  const toUrl = (p) => {
    const sp = new URLSearchParams();
    ARCHIVE_KEYS.forEach((k) => { if (p[k]) sp.set(k, p[k]); });
    const qs = sp.toString();
    return `${window.location.pathname}${qs ? `?${qs}` : ''}#results`;
  };
  let params = fromUrl();
  let page = 1;
  let ticket = 0;

  const source = (p, n) => {
    if (window.tngArchiveSource) return window.tngArchiveSource(p, n, size);
    const url = new URL(grid.dataset.source, window.location.href);
    url.searchParams.set('type', grid.dataset.type);
    url.searchParams.set('page', String(n));
    url.searchParams.set('per_page', String(size));
    Object.keys(p).forEach((k) => url.searchParams.set(k, p[k]));
    return fetch(url).then((r) => { if (!r.ok) throw new Error(r.status); return r.json(); });
  };

  const valueLabel = (k, v) => {
    if (k === 'lang') return LANG_NAMES[v] || v.toUpperCase();
    if (k === 'pro') return proInput && proInput.value.trim() ? proInput.value.trim() : v;
    const opt = bar?.querySelector(`.flt-panel[data-key="${k}"] .flt-option[data-value="${CSS.escape(v)}"]`);
    return opt ? opt.textContent.trim() : v;
  };

  const renderChips = () => {
    grid.querySelector('.ins-chips')?.remove();
    const keys = ARCHIVE_KEYS.filter((k) => params[k] && k !== 'sort'); // the server's chip order
    grid.classList.toggle('ins-grid--no-chips', !keys.length && withCount);
    grid.classList.toggle('ins-grid--plain', !keys.length && !withCount);
    if (!keys.length) return;
    const ul = document.createElement('ul');
    ul.className = 'ins-chips';
    ul.setAttribute('aria-label', 'Active filters');
    ul.innerHTML = keys.map((k) => {
      const rest = { ...params };
      delete rest[k];
      const v = valueLabel(k, params[k]);
      return `<li><a href="${escapeHtml(toUrl(rest))}" class="ins-chip" data-key="${k}" aria-label="${escapeHtml(`Remove filter: ${CHIP_LABELS[k]}, ${v}`)}"><span class="ins-chip__key">${CHIP_LABELS[k]}</span><span class="ins-chip__value">${escapeHtml(v)}</span>${CLOSE_ICON}</a></li>`;
    }).join('') + `<li><a href="${escapeHtml(toUrl({}))}" class="ins-chip ins-chip--reset" data-key=""><span class="ins-chip__value">Reset</span>${CLOSE_ICON}</a></li>`;
    grid.prepend(ul);
  };

  const syncBar = () => {
    if (!bar) return;
    if (qInput) qInput.value = params.q || '';
    if (proInput) {
      if (!params.pro) { proInput.value = ''; delete proInput.dataset.slug; } else if (params.pro !== proInput.dataset.slug && params.pro !== proInput.value.trim()) {
        proInput.value = params.pro; // a slug from Back / a shared URL: show the name once the roster is in
        proInput.dataset.slug = params.pro;
        proInput.tngRoster?.().then((r) => {
          const p = r.find((x) => x.slug === params.pro);
          if (p) { proInput.value = p.name; proInput.dataset.name = p.name; renderChips(); }
        }).catch(() => {});
      }
    }
    bar.querySelectorAll('.flt-panel[role="group"]').forEach((panel) => setFacet(panel, params[panel.dataset.key] || ''));
    // the no-JS hidden inputs follow, in case the form is submitted natively
    bar.querySelectorAll('input[type="hidden"]').forEach((h) => h.remove());
  };

  const render = (data, append) => {
    let list = grid.querySelector('.ins-grid__list');
    let empty = grid.querySelector('.ins-grid__empty');
    const box = grid.querySelector('.ins-grid__more');
    if (!list) {
      list = document.createElement('ul');
      list.className = 'ins-grid__list';
      box.before(list);
    }
    if (!empty) {
      empty = document.createElement('p');
      empty.className = 'body ins-grid__empty';
      empty.textContent = 'No results match these filters. Remove a filter above or reset them all.';
      list.after(empty);
    }
    const tmp = document.createElement('ul');
    tmp.innerHTML = data.html || '';
    const items = [...tmp.children];
    if (!append) list.replaceChildren();
    items.forEach((li) => list.appendChild(li));
    const total = Number(data.total) || 0;
    list.hidden = !total;
    empty.hidden = Boolean(total);
    let count = grid.querySelector('.pro-grid__count');
    if (withCount && !count) {
      count = document.createElement('p');
      count.className = 'pro-grid__count';
      box.prepend(count);
    }
    if (count) {
      count.hidden = !total;
      count.innerHTML = `Showing <strong>${list.children.length}</strong> out of <strong>${total}</strong> results`;
    }
    if (more) more.hidden = !data.more;
    if (items.length && !REDUCED) gsap.from(items, { autoAlpha: 0, y: 30, duration: 0.6, ease: EASE, stagger: 0.06 });
    if (status) status.textContent = total ? `${total} result${total === 1 ? '' : 's'}${append ? `, showing ${list.children.length}` : ''}` : 'No results match these filters';
    ScrollTrigger.refresh();
  };

  const apply = (next, { push = true, scroll = true } = {}) => {
    params = next;
    page = 1;
    const mine = ++ticket;
    syncBar();
    renderChips();
    if (push) window.history.pushState({ tngArchive: true }, '', toUrl(params));
    grid.classList.add('is-loading');
    grid.setAttribute('aria-busy', 'true');
    return source(params, 1).then((data) => {
      if (mine !== ticket) return;
      render(data, false);
      // Deep in the results: bring their top back under the (sticky) bar
      if (scroll) {
        const top = SMALL.matches ? (header?.offsetHeight || 0) : (bar ? bar.getBoundingClientRect().bottom : 0);
        if (grid.getBoundingClientRect().top < top - 1) lenis.scrollTo(grid, { offset: -top, duration: 0.8 });
      }
    }).catch(() => {
      if (mine === ticket) window.location.assign(toUrl(params)); // let the server render it
    }).finally(() => {
      if (mine !== ticket) return;
      grid.classList.remove('is-loading');
      grid.removeAttribute('aria-busy');
    });
  };

  const change = (key, value) => {
    const next = { ...params };
    if (value) next[key] = value; else delete next[key];
    apply(next);
  };

  if (bar) {
    bar.addEventListener('tng:facet', (e) => change(e.detail.key, e.detail.value));
    bar.querySelector('form')?.addEventListener('submit', (e) => {
      e.preventDefault();
      const next = { ...params };
      const q = qInput ? qInput.value.trim() : '';
      if (q) next.q = q; else delete next.q;
      if (proInput) {
        const typed = proInput.value.trim();
        const pro = proInput.dataset.slug && typed === proInput.dataset.name ? proInput.dataset.slug : typed;
        if (pro) next.pro = pro; else delete next.pro;
      }
      apply(next);
    });
    // Clearing a search field (its x, or deleting the text) drops that filter
    [qInput, proInput].forEach((input) => input?.addEventListener('search', () => {
      if (!input.value.trim() && params[input.name]) change(input.name, '');
    }));
  }

  grid.addEventListener('click', (e) => {
    const chip = e.target.closest('.ins-chip[data-key]');
    if (!chip || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault();
    const key = chip.dataset.key;
    apply(key ? Object.fromEntries(Object.entries(params).filter(([k]) => k !== key)) : {});
    bar?.querySelector(key === 'q' ? 'input[name="q"]' : key === 'pro' ? 'input[data-roster]' : `[aria-controls="flt-${key}"]`)?.focus({ preventScroll: true });
  });

  more?.addEventListener('click', () => {
    if (more.disabled) return;
    more.disabled = true;
    const mine = ticket;
    source(params, page + 1).then((data) => {
      if (mine !== ticket) return;
      page += 1;
      render(data, true);
    }).catch(() => {}).finally(() => { more.disabled = false; });
  });

  window.addEventListener('popstate', () => {
    const next = fromUrl();
    if (JSON.stringify(next) !== JSON.stringify(params)) apply(next, { push: false, scroll: false });
  });

  // Static preview: nothing is server-rendered, so draw the URL's state now
  if (window.tngArchiveSource) apply(params, { push: false, scroll: false });
}

/* ------------------------------------------------------------
   Init
   ------------------------------------------------------------ */

// The header never takes part in the reveals: its megamenus animate in CSS,
// and revealing a label inside a closed (visibility: hidden) menu would make
// it hit-testable again — hovering the sticky bar under the header would pop
// the menu open.
// Service hero contacts and the about vision / mission text sit in tab panels
// (mostly hidden, so they'd split at zero width); they rise as groups instead.
// Japanese / Chinese copy is left as it is: SplitText breaks words on spaces,
// so mixed CJK + Latin text ("超过 250 名") would split into ragged lines.
function revealTargets(selector) {
  return [...document.querySelectorAll(selector)].filter((el) => !el.closest('.site-header, .svc-hero__panels, .about-vision__panels, .lp-team__panels') && !el.matches(':lang(ja), :lang(zh)'));
}

// SplitText wraps each word, which adds a break opportunity between a link
// (or <strong> / <em>) and the punctuation right after it: "museum@tilleke.com"
// + "." could wrap the full stop onto a line of its own. Glue them first.
function glueTrailingPunctuation() {
  document.querySelectorAll('main a, main strong, main em, main b, main i').forEach((el) => {
    const next = el.nextSibling;
    // ponytail: single-word elements only (emails, URLs, names); gluing a multi-word
    // link would stop it wrapping. Split the last word out if that case shows up.
    if (!next || next.nodeType !== 3 || /\s/.test(el.textContent.trim()) || el.closest('.site-header, :lang(ja), :lang(zh)')) return;
    const m = next.textContent.match(/^[.,;:!?)\]”’»]+/);
    if (!m) return;
    const glue = document.createElement('span');
    glue.style.whiteSpace = 'nowrap';
    next.textContent = next.textContent.slice(m[0].length);
    el.replaceWith(glue);
    glue.append(el, m[0]);
  });
}

// Hide text targets right away so nothing flashes before the split runs
if (!REDUCED) gsap.set(revealTargets(`${HEADINGS}, ${BODY}, ${CONTENT_TEXT}`), { visibility: 'hidden' });

// Printing mid-page would leave unrevealed text hidden: finish every reveal first
window.addEventListener('beforeprint', () => {
  ScrollTrigger.getAll().forEach((st) => {
    if (st.vars.once) st.animation?.progress(1);
  });
});

document.addEventListener('DOMContentLoaded', () => {
  // Imported article tables wider than their column scroll inside it (desktop;
  // small screens scroll every table) instead of running off the column
  document.querySelectorAll('.rich-text table').forEach((t) => {
    if (t.scrollWidth > t.parentElement.clientWidth + 1) t.classList.add('is-wide');
  });
  // Blaze sliders: any element with .blaze-slider gets initialised
  document.querySelectorAll('.blaze-slider').forEach((el) => {
    new BlazeSlider(el, {
      all: { slidesToShow: 1, slideGap: '20px', loop: true },
    });
  });

  // Insights tabs: active state only for now; content swap comes with the WP data
  document.querySelectorAll('.hp-insights__tab-list').forEach((list) => {
    list.addEventListener('click', (e) => {
      const tab = e.target.closest('.hp-insights__tab');
      if (!tab) return;
      list.querySelectorAll('.hp-insights__tab').forEach((t) => {
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });
    });
  });

  // Professionals alphabet: one letter at a time; clicking the active one clears it.
  // Active state only for now; filtering comes with the roster JSON.
  document.querySelectorAll('.pro-filter__alpha').forEach((list) => {
    list.addEventListener('click', (e) => {
      const letter = e.target.closest('button');
      if (!letter) return;
      const on = !letter.classList.contains('is-active');
      list.querySelectorAll('button').forEach((b) => {
        b.classList.toggle('is-active', on && b === letter);
        b.setAttribute('aria-pressed', on && b === letter ? 'true' : 'false');
      });
    });
  });

  // Insight reading bar: the red fill runs from the moment the bar sticks
  // under the compact header (the hero's bottom) to the end of <main>
  const progress = document.querySelector('.in-bar__progress span');
  if (progress) {
    gsap.to(progress, {
      scaleX: 1,
      ease: 'none',
      scrollTrigger: {
        trigger: '.in-hero',
        // the bar's own sticky top: 72 at 1440 (5vw), --header-h below 1200
        start: () => `bottom ${parseFloat(getComputedStyle(progress.closest('.in-bar')).top)}px`,
        endTrigger: 'main',
        end: 'bottom bottom',
        scrub: true,
        invalidateOnRefresh: true,
      },
    });
  }

  // Insight share row: links built from the page URL; print and copy on click
  document.querySelectorAll('.in-share').forEach((row) => {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.title);
    const linkedin = row.querySelector('[data-share="linkedin"]');
    const email = row.querySelector('[data-share="email"]');
    if (linkedin) linkedin.href = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
    if (email) email.href = `mailto:?subject=${title}&body=${url}`;
    row.querySelector('[data-share="print"]')?.addEventListener('click', () => window.print());
    const copy = row.querySelector('[data-share="copy"]');
    copy?.addEventListener('click', () => {
      navigator.clipboard.writeText(window.location.href).then(() => {
        copy.classList.add('is-copied');
        setTimeout(() => copy.classList.remove('is-copied'), 1500);
      });
    });
  });

  // Professional on-this-page tabs: click scrolls the section to just under
  // the header + tabs; the tab of the section crossing the middle of the
  // viewport is marked active. Tabs whose section isn't on the page are skipped.
  document.querySelectorAll('.pro-tabs').forEach((nav) => {
    const offset = () => nav.offsetHeight + parseFloat(getComputedStyle(nav).top); // tabs + header (its sticky top)
    const list = nav.querySelector('.pro-tabs__list');
    const tabs = [...nav.querySelectorAll('.pro-tabs__link')]
      .map((link) => ({ link, section: document.querySelector(link.getAttribute('href')) }))
      .filter((t) => t.section);
    const activate = (active) => {
      tabs.forEach(({ link }) => {
        link.classList.toggle('is-active', link === active);
        if (link === active) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
      });
      // Small layout: the list is a scroll strip; keep the active tab in view (no-op on desktop)
      if (list.scrollWidth > list.clientWidth) {
        list.scrollTo({ left: active.parentElement.offsetLeft - parseFloat(getComputedStyle(list).paddingLeft), behavior: 'smooth' });
      }
    };
    tabs.forEach(({ link, section }) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        lenis.scrollTo(section, { offset: -offset() });
      });
      ScrollTrigger.create({
        trigger: section,
        start: 'top center',
        end: 'bottom center',
        onToggle: (self) => self.isActive && activate(link),
      });
    });
  });

  // Professional experience: the list shows `data-cap` items, the rest sit
  // behind "Read more" (toggles to "Read less").
  document.querySelectorAll('.pro-experience__list[data-cap]').forEach((wrap) => {
    const extras = [...wrap.querySelectorAll(':scope > ul > li')].slice(Number(wrap.dataset.cap));
    if (!extras.length) return;
    extras.forEach((li) => li.classList.add('is-extra'));
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'button-label pro-experience__more';
    btn.textContent = 'Read more';
    btn.setAttribute('aria-expanded', 'false');
    wrap.after(btn);
    btn.addEventListener('click', () => {
      const open = wrap.classList.toggle('is-expanded');
      btn.textContent = open ? 'Read less' : 'Read more';
      btn.setAttribute('aria-expanded', String(open));
      if (open) {
        gsap.fromTo(extras,
          { autoAlpha: 0, y: 20 },
          { autoAlpha: 1, y: 0, duration: 0.6, ease: EASE, stagger: 0.08 });
      }
      ScrollTrigger.refresh(); // section height changed
    });
  });

  // Insight hero rows: each row shows `data-cap` pills, the rest sit behind
  // "See more" (toggles to "See less"). Rows at or under the cap are untouched.
  document.querySelectorAll('.in-hero__meta[data-cap]').forEach((meta) => {
    const cap = Number(meta.dataset.cap);
    meta.querySelectorAll('.in-hero__tags').forEach((list) => {
      const extras = [...list.children].slice(cap);
      if (!extras.length) return;
      extras.forEach((li) => li.classList.add('is-extra'));
      const li = document.createElement('li');
      li.innerHTML = '<button type="button" class="in-hero__more" aria-expanded="false">See more</button>';
      list.appendChild(li);
      const btn = li.firstChild;
      btn.addEventListener('click', () => {
        const open = list.classList.toggle('is-expanded');
        btn.textContent = open ? 'See less' : 'See more';
        btn.setAttribute('aria-expanded', String(open));
        if (open) gsap.from(extras, { autoAlpha: 0, y: 10, duration: 0.5, ease: EASE, stagger: 0.04 });
        ScrollTrigger.refresh(); // hero height changed
      });
    });
  });

  // Careers marquee: each column's items are cloned once, then the column
  // translates by half its height on a loop, so the seam is invisible.
  document.querySelectorAll('.hp-careers__column').forEach((col) => {
    if (REDUCED) return;
    const items = [...col.children];
    items.forEach((item) => col.appendChild(item.cloneNode(true)));
    const down = col.dataset.direction === 'down';
    gsap.fromTo(
      col,
      { yPercent: down ? -50 : 0 },
      { yPercent: down ? 0 : -50, duration: 24, ease: 'none', repeat: -1 }
    );
  });

  // Parallax: any .has-parallax section moves its .parallax-img on scroll
  document.querySelectorAll('.has-parallax').forEach((section) => {
    const img = section.querySelector('.parallax-img');
    if (!img) return;
    gsap.fromTo(
      img,
      { yPercent: -10 },
      {
        yPercent: 10,
        ease: 'none',
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: 'bottom top',
          scrub: true,
        },
      }
    );
  });

  // Facets: label and chevron rise in, the buttons (and their divider lines) stay put
  const facets = document.querySelector('.pro-filter__facets');
  if (facets) {
    gsap.from(facets.querySelectorAll('.pro-filter__facet > *'), {
      y: 40,
      autoAlpha: 0,
      duration: 1,
      ease: EASE,
      stagger: GROUP_STAGGER,
      scrollTrigger: { trigger: facets, start: REVEAL_START, once: true },
    });
  }

  // Filter bars: dropdown panels, the professional type-ahead, the archive grid
  document.querySelectorAll('.pro-filter').forEach(initFilterBar);
  document.querySelectorAll('input[data-roster]').forEach(initTypeahead);
  const archiveGrid = document.querySelector('.ins-grid[data-type]');
  if (archiveGrid) initArchive(archiveGrid, document.querySelector('.ins-filter'));

  // Contact offices: a city tab shows its panel (details + map); the rest stay
  // hidden, so their lazy map iframes never load until opened. The service
  // hero's country tabs (contacts per location) and the about page's vision /
  // mission tabs use the same pattern.
  document.querySelectorAll('.contact-offices, .svc-hero__content, .about-vision, .lp-team').forEach((section) => {
    const tabs = [...section.querySelectorAll('[role="tab"]')];
    const tablist = section.querySelector('[role="tablist"]');
    if (!tabs.length || !tablist) return; // e.g. a service hero with no contacts
    const select = (tab) => {
      tabs.forEach((t) => {
        const on = t === tab;
        const panel = section.querySelector(`#${t.getAttribute('aria-controls')}`);
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        panel.hidden = !on;
        panel.classList.toggle('is-active', on);
        if (on) {
          const parts = panel.matches('.contact-office')
            ? [...panel.querySelector('.contact-office__info').children, panel.querySelector('.contact-office__map')]
            : [...panel.children];
          gsap.fromTo(parts,
            { y: 20, autoAlpha: 0 },
            { y: 0, autoAlpha: 1, duration: 0.6, ease: EASE, stagger: 0.06, overwrite: true });
        }
      });
      // Small layout: a tab list that is a scroll strip brings the chosen tab
      // into view, sideways only (never scrolls the page; no-op on desktop)
      if (tablist.scrollWidth > tablist.clientWidth) {
        const pad = parseFloat(getComputedStyle(tablist).paddingLeft);
        const t = tab.getBoundingClientRect();
        const l = tablist.getBoundingClientRect();
        const dx = t.left - pad < l.left ? t.left - pad - l.left : Math.max(0, t.right + pad - l.right);
        if (dx) tablist.scrollBy({ left: dx, behavior: 'smooth' });
      }
    };
    section.addEventListener('click', (e) => {
      const tab = e.target.closest('[role="tab"]');
      if (tab && !tab.classList.contains('is-active')) select(tab);
    });
    // Arrow keys move between tabs (WAI-ARIA tabs pattern)
    tablist.addEventListener('keydown', (e) => {
      const i = tabs.indexOf(document.activeElement);
      if (i < 0 || !['ArrowDown', 'ArrowUp', 'ArrowRight', 'ArrowLeft'].includes(e.key)) return;
      e.preventDefault();
      const next = tabs[(i + (['ArrowDown', 'ArrowRight'].includes(e.key) ? 1 : tabs.length - 1)) % tabs.length];
      next.focus();
      select(next);
    });
  });

  // About accordion: one item open at a time; the panel height animates in CSS
  document.querySelectorAll('.about-acc').forEach((acc) => {
    acc.addEventListener('click', (e) => {
      const btn = e.target.closest('.about-acc__btn');
      if (!btn) return;
      const item = btn.closest('.about-acc__item');
      const open = !item.classList.contains('is-open');
      acc.querySelectorAll('.about-acc__item').forEach((it) => {
        const on = it === item && open;
        it.classList.toggle('is-open', on);
        it.querySelector('.about-acc__btn').setAttribute('aria-expanded', on ? 'true' : 'false');
      });
      setTimeout(() => ScrollTrigger.refresh(), 550); // after the height transition
    });
  });

  if (REDUCED) {
    document.querySelectorAll(RULES).forEach((el) => el.classList.add('is-in'));
    return;
  }

  // Professional cards rise in row by row as they enter
  gsap.set('.pro-card', { autoAlpha: 0, y: 40 });
  ScrollTrigger.batch('.pro-card', {
    start: REVEAL_START,
    once: true,
    onEnter: (batch) => gsap.to(batch, { autoAlpha: 1, y: 0, duration: 1, ease: EASE, stagger: GROUP_STAGGER }),
  });

  drawRules();

  // Text splits need the web fonts, or line breaks land in the wrong place
  document.fonts.ready.then(() => {
    glueTrailingPunctuation();
    document.querySelectorAll(GROUPS).forEach(revealGroup);
    revealTargets(HEADINGS).forEach(revealHeading);
    // Capped rows can't be split while hidden; "Read more" fades them in instead
    revealTargets(CONTENT_TEXT).forEach((el) => {
      if (!el.closest('.is-extra')) revealHeading(el);
    });
    document.querySelectorAll(CONTENT_MEDIA).forEach(revealMedia);
    revealTargets(BODY).forEach(revealBody);
    document.querySelectorAll('.counter').forEach(countUp);
    ScrollTrigger.refresh();
  });
});
