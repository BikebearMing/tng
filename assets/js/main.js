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
const HEADINGS = '.h1, .h2, .h3:not(.hp-insights__tab), .h4, .counter-label';
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
  if (getComputedStyle(el).display === 'block') el.style.display = 'flow-root';
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

// Hide text targets right away so nothing flashes before the split runs
gsap.set(`${HEADINGS}, ${BODY}`, { visibility: 'hidden' });

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
    document.querySelectorAll(HEADINGS).forEach(revealHeading);
    document.querySelectorAll(BODY).forEach(revealBody);
    document.querySelectorAll('.counter').forEach(countUp);
    ScrollTrigger.refresh();
  });
});
