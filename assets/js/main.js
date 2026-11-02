// TNG — frontend scripts

gsap.registerPlugin(ScrollTrigger, SplitText);

// Lenis smooth scroll, synced to GSAP's ticker
const lenis = new Lenis();
lenis.on('scroll', ScrollTrigger.update);
gsap.ticker.add((time) => lenis.raf(time * 1000));
gsap.ticker.lagSmoothing(0);

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

/* ------------------------------------------------------------
   Text reveals
   Headings: each line rises out of a mask.
   Body copy: each word rises out of its line's mask, staggered so a
   long paragraph finishes in about the same time as a short one.
   ------------------------------------------------------------ */
// Contact office tabs and panels are left out: tab buttons can't hold split
// lines, and hidden panels would split at zero width. They rise as groups.
const HEADINGS = '.h1, .h2:not(.contact-offices__tab), .h3:not(.hp-insights__tab), .h4:not(.contact-office__name), .counter-label, .in-findings__num';
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
  '.in-hero__meta',
  '.in-panels__list',
  '.contact-offices__tabs',
  '.contact-offices__panels',
  '.career-paths__list',
  '.careers-people__media',
  '.careers-jobs__list',
  '.in-related__list',
  '.in-share',
  '.pro-facts',
  '.pro-related__cards',
].join(', ');
const GROUP_STAGGER = 0.12;

// Delay for an element based on which group child it sits in (0 if none)
function groupDelay(el) {
  const group = el.closest(GROUPS);
  if (!group) return 0;
  const child = [...group.children].find((c) => c.contains(el));
  return child ? [...group.children].indexOf(child) * GROUP_STAGGER : 0;
}

function revealGroup(group) {
  gsap.from(group.children, {
    y: 40,
    autoAlpha: 0,
    duration: 1,
    ease: EASE,
    stagger: GROUP_STAGGER,
    scrollTrigger: { trigger: group, start: startFor(group), once: true },
  });
}

function bleedMasks(split, el) {
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
   Init
   ------------------------------------------------------------ */

// The header never takes part in the reveals: its megamenus animate in CSS,
// and revealing a label inside a closed (visibility: hidden) menu would make
// it hit-testable again — hovering the sticky bar under the header would pop
// the menu open.
function revealTargets(selector) {
  return [...document.querySelectorAll(selector)].filter((el) => !el.closest('.site-header'));
}

// Hide text targets right away so nothing flashes before the split runs
gsap.set(revealTargets(`${HEADINGS}, ${BODY}, ${CONTENT_TEXT}`), { visibility: 'hidden' });

// Printing mid-page would leave unrevealed text hidden: finish every reveal first
window.addEventListener('beforeprint', () => {
  ScrollTrigger.getAll().forEach((st) => {
    if (st.vars.once) st.animation?.progress(1);
  });
});

document.addEventListener('DOMContentLoaded', () => {
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
        start: () => `bottom ${window.innerWidth * 0.05}px`, // 72 at 1440
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
    const offset = () => nav.offsetHeight + window.innerWidth * 0.05; // tabs + 72 header
    const tabs = [...nav.querySelectorAll('.pro-tabs__link')]
      .map((link) => ({ link, section: document.querySelector(link.getAttribute('href')) }))
      .filter((t) => t.section);
    const activate = (active) => {
      tabs.forEach(({ link }) => {
        link.classList.toggle('is-active', link === active);
        if (link === active) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
      });
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

  // Contact offices: a city tab shows its panel (details + map); the rest stay
  // hidden, so their lazy map iframes never load until opened
  document.querySelectorAll('.contact-offices').forEach((section) => {
    const tabs = [...section.querySelectorAll('[role="tab"]')];
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
          gsap.fromTo([...panel.querySelector('.contact-office__info').children, panel.querySelector('.contact-office__map')],
            { y: 20, autoAlpha: 0 },
            { y: 0, autoAlpha: 1, duration: 0.6, ease: EASE, stagger: 0.06, overwrite: true });
        }
      });
    };
    section.addEventListener('click', (e) => {
      const tab = e.target.closest('[role="tab"]');
      if (tab && !tab.classList.contains('is-active')) select(tab);
    });
    // Arrow keys move between tabs (WAI-ARIA tabs pattern)
    section.querySelector('[role="tablist"]').addEventListener('keydown', (e) => {
      const i = tabs.indexOf(document.activeElement);
      if (i < 0 || !['ArrowDown', 'ArrowUp', 'ArrowRight', 'ArrowLeft'].includes(e.key)) return;
      e.preventDefault();
      const next = tabs[(i + (['ArrowDown', 'ArrowRight'].includes(e.key) ? 1 : tabs.length - 1)) % tabs.length];
      next.focus();
      select(next);
    });
  });

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
