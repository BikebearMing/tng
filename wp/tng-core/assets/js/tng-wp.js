// TNG — WordPress-only wiring. Loads after main.js (which owns all the
// active-state toggles and reveals); this file only swaps data:
//  - professionals grid: client-side filtering of the roster endpoint
//    (search + alphabet + facet panels + "View more"), state in the URL
//    (q, letter, ind, prac, pos, loc, lang; pushState, Back replays it).
//  - homepage insights tabs: swap the feature card + list per industry from
//    the JSON the block embeds.
//  (The insights / awards archive bar + grid live in main.js: initArchive.)
//  - service hero: pick the visitor's country tab (/tng/v1/geo; client rule:
//    KH / ID / LA / MM / VN -> that country, everyone else -> Thailand).

(function () {
  'use strict';

  var ARROW2 =
    '<svg viewBox="0 0 10 10" fill="none"><path d="M3 1l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------------- professionals grid ---------------- */
  var grid = document.querySelector('.pro-grid');
  var filter = document.querySelector('.pro-filter');
  if (grid && !grid.classList.contains('srch-results') && window.TNG && TNG.roster) { // not the search results' professionals grid
    var list = grid.querySelector('.pro-grid__list');
    var countEl = grid.querySelector('.pro-grid__count');
    var moreBtn = grid.querySelector('.pro-grid__view-more');
    var pageSize = Number(grid.dataset.pageSize) || 15;

    var roster = null;
    var state = { q: '', letter: '', facets: {}, shown: pageSize };
    var FACET_KEYS = ['ind', 'prac', 'pos', 'loc', 'lang'];

    function card(p) {
      // Position may contain <br> from the textarea field; it is site-authored.
      return (
        '<li><a href="' + escapeHtml(p.url) + '" class="pro-card">' +
        '<img src="' + escapeHtml(p.photo) + '" alt="" class="pro-card__img" loading="lazy">' +
        '<div class="pro-card__info">' +
        '<p class="pro-card__name">' + escapeHtml(p.name) +
        '<span class="arrow-circle" aria-hidden="true">' + ARROW2 + '</span></p>' +
        '<p class="subhead pro-card__position">' + p.position + '</p>' +
        '</div></a></li>'
      );
    }

    function filtered() {
      var q = state.q.trim().toLowerCase();
      return roster.filter(function (p) {
        if (state.letter && p.letter !== state.letter) return false;
        if (q && p.search.indexOf(q) === -1) return false;
        return Object.keys(state.facets).every(function (k) {
          var f = state.facets[k];
          var mine = (p.terms && p.terms[f.tax]) || [];
          return mine.some(function (id) { return f.ids.indexOf(id) !== -1; });
        });
      });
    }

    function render() {
      var all = filtered();
      var slice = all.slice(0, state.shown);
      list.innerHTML = slice.map(card).join('');
      // main.js's batch reveal only knows the initial cards; show these plainly.
      slice.length && list.querySelectorAll('.pro-card').forEach(function (el) {
        el.style.opacity = 1;
        el.style.visibility = 'visible';
        el.style.transform = 'none';
      });
      if (countEl) {
        countEl.innerHTML =
          'Showing <strong>' + slice.length + '</strong> out of <strong>' + all.length + '</strong> results';
      }
      if (moreBtn) moreBtn.hidden = all.length <= state.shown;
      var empty = grid.querySelector('.pro-grid__empty');
      if (!all.length && !empty) {
        empty = document.createElement('div');
        empty.className = 'ins-grid__empty pro-grid__empty';
        empty.innerHTML = '<p class="body">No professionals match these filters.</p>' +
          '<button type="button" class="button-label link-arrow is-in">Clear all filters</button>';
        empty.querySelector('button').addEventListener('click', function () { setAll({}); push(); });
        list.after(empty);
      }
      if (empty) empty.hidden = all.length > 0;
      if (countEl) countEl.hidden = !all.length;
      var live = grid.querySelector('[data-status]');
      if (live) live.textContent = all.length ? all.length + ' professionals' : 'No professionals match these filters';
      if (window.ScrollTrigger) ScrollTrigger.refresh();
    }

    // URL <-> state. Facet values are slugs; the panel option holds the term ids.
    function panelOf(key) { return filter && filter.querySelector('.flt-panel[data-key="' + key + '"]'); }
    function setFacetState(key, value) {
      var panel = panelOf(key);
      var opt = panel && value ? panel.querySelector('.flt-option[data-value="' + CSS.escape(value) + '"]') : null;
      if (opt && opt.dataset.ids) {
        state.facets[key] = { tax: panel.dataset.tax, slug: value, ids: opt.dataset.ids.split(',').map(Number) };
      } else {
        delete state.facets[key];
      }
      if (panel && typeof setFacet === 'function') setFacet(panel, state.facets[key] ? value : '');
    }
    function setAll(p) {
      state.q = p.q || '';
      if (search) search.value = state.q;
      state.letter = (p.letter || '').toUpperCase();
      if (filter) {
        filter.querySelectorAll('.pro-filter__alpha button').forEach(function (b) {
          var on = b.textContent.trim() === state.letter;
          b.classList.toggle('is-active', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
      }
      FACET_KEYS.forEach(function (k) { setFacetState(k, p[k] || ''); });
      apply();
    }
    function fromUrl() {
      var sp = new URLSearchParams(window.location.search);
      var p = {};
      ['q', 'letter'].concat(FACET_KEYS).forEach(function (k) { if (sp.get(k)) p[k] = sp.get(k); });
      return p;
    }
    function toUrl() {
      var sp = new URLSearchParams();
      if (state.q.trim()) sp.set('q', state.q.trim());
      if (state.letter) sp.set('letter', state.letter);
      FACET_KEYS.forEach(function (k) { if (state.facets[k]) sp.set(k, state.facets[k].slug); });
      var qs = sp.toString();
      return window.location.pathname + (qs ? '?' + qs : '');
    }
    function push(replace) {
      var url = toUrl();
      if (url === window.location.pathname + window.location.search) return;
      window.history[replace ? 'replaceState' : 'pushState']({ tngPros: true }, '', url);
    }
    window.addEventListener('popstate', function () { setAll(fromUrl()); });

    function load() {
      if (roster) return Promise.resolve(roster);
      return fetch(TNG.roster)
        .then(function (r) { return r.json(); })
        .then(function (data) { roster = data; return roster; });
    }

    function apply() {
      state.shown = pageSize;
      load().then(render);
    }

    if (moreBtn) {
      moreBtn.addEventListener('click', function () {
        state.shown += pageSize;
        load().then(render);
      });
    }

    if (filter) {
      var search = filter.querySelector('#pro-search');
      var debounce;
      if (search) {
        search.addEventListener('input', function () {
          clearTimeout(debounce);
          debounce = setTimeout(function () {
            state.q = search.value;
            apply();
            push(true); // typing replaces the entry instead of filling the history
          }, 150);
        });
      }
      filter.querySelector('.pro-filter__form').addEventListener('submit', function (e) {
        e.preventDefault();
        state.q = search ? search.value : '';
        apply();
        push(true);
      });
      filter.addEventListener('tng:facet', function (e) {
        setFacetState(e.detail.key, e.detail.value);
        apply();
        push();
      });
      // main.js toggles the active state first (its listener was attached
      // earlier); read the result afterwards.
      var alpha = filter.querySelector('.pro-filter__alpha');
      if (alpha) {
        alpha.addEventListener('click', function (e) {
          if (!e.target.closest('button')) return;
          // main.js (attached at DOMContentLoaded) toggles the active state in
          // its own handler; wait a tick so we read the settled state.
          setTimeout(function () {
            var active = alpha.querySelector('button.is-active');
            state.letter = active ? active.textContent.trim() : '';
            apply();
            push();
          }, 0);
        });
      }
    }
    // A shared / bookmarked URL: filter the server-rendered first page now
    var initial = fromUrl();
    if (Object.keys(initial).length) setAll(initial);
  }

  /* ---------------- homepage insights tabs ---------------- */
  document.querySelectorAll('.hp-insights').forEach(function (section) {
    var dataEl = section.querySelector('.hp-insights__data');
    var body = section.querySelector('.hp-insights__body');
    if (!dataEl || !body) return;
    var panels;
    try {
      panels = JSON.parse(dataEl.textContent);
    } catch (e) {
      return;
    }

    function show(i) {
      var panel = panels[i];
      if (!panel) return;
      var html = '';
      if (panel.feature) {
        var f = panel.feature;
        html +=
          '<a href="' + escapeHtml(f.url) + '" class="hp-insights__feature">' +
          '<img src="' + escapeHtml(f.image) + '" alt="">' +
          '<div class="hp-insights__card">' +
          '<h3 class="h3" style="visibility:visible">' + escapeHtml(f.title) + '</h3>' +
          '<p class="body" style="visibility:visible">' + escapeHtml(f.excerpt) + '</p>' +
          '<div class="hp-insights__card-foot">' +
          '<span class="subhead" style="visibility:visible">' + escapeHtml(f.date) + '</span>' +
          '<span class="arrow-circle" aria-hidden="true">' + ARROW2 + '</span>' +
          '</div></div></a>';
      }
      html += '<ul class="hp-insights__list">';
      panel.list.forEach(function (row) {
        html +=
          '<li class="hp-insights__item is-in"><a href="' + escapeHtml(row.url) + '">' +
          '<h4 class="h4" style="visibility:visible">' + escapeHtml(row.title) + '</h4>' +
          '<span class="subhead" style="visibility:visible">' + escapeHtml(row.date) + '</span>' +
          '<span class="arrow-circle" aria-hidden="true">' + ARROW2 + '</span>' +
          '</a></li>';
      });
      html += '</ul>';
      body.innerHTML = html;
      if (window.gsap) {
        gsap.from(body.children, { autoAlpha: 0, y: 20, duration: 0.5, ease: 'power3.out' });
      }
      if (window.ScrollTrigger) ScrollTrigger.refresh();
    }

    section.querySelectorAll('.hp-insights__tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        show(Number(tab.dataset.tabIndex) || 0);
      });
    });
  });
  /* ---------------- service hero: visitor's country tab ---------------- */
  // Runs after main.js's DOMContentLoaded handler (registered earlier), so the
  // tab buttons already switch panels when clicked.
  document.addEventListener('DOMContentLoaded', function () {
    var places = document.querySelector('.svc-hero__places[data-geo]');
    if (!places || !window.TNG || !TNG.geo) return;
    var MAP = { KH: 'cambodia', ID: 'indonesia', LA: 'laos', MM: 'myanmar', VN: 'vietnam' };

    function pick(code) {
      var want = MAP[String(code || '').toUpperCase()] || 'thailand';
      var btn =
        places.querySelector('[data-country="' + want + '"]') ||
        places.querySelector('[data-country="thailand"]') ||
        places.querySelector('[role="tab"]');
      if (btn && !btn.classList.contains('is-active')) btn.click();
    }

    var cached = null;
    try {
      cached = window.sessionStorage.getItem('tngGeo');
    } catch (e) {
      cached = null;
    }
    if (cached !== null) {
      pick(cached);
      return;
    }
    fetch(TNG.geo, { credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var code = (data && data.country) || '';
        try {
          window.sessionStorage.setItem('tngGeo', code);
        } catch (e) { /* private mode */ }
        pick(code);
      })
      .catch(function () { /* keep the server default (Thailand) */ });
  });
})();
