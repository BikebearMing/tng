// TNG — WordPress-only wiring. Loads after main.js (which owns all the
// active-state toggles and reveals); this file only swaps data:
//  - professionals grid: client-side filtering of the roster endpoint
//    (search + alphabet + "View more"); facet dropdowns wait for their design.
//  - homepage insights tabs: swap the feature card + list per industry from
//    the JSON the block embeds.
//  - insights / awards archive: "View more" appends the next page from
//    /tng/v1/archive with the page's filters (filters themselves are plain
//    GET links / a GET form, handled server-side).
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
  if (grid && window.TNG && TNG.roster) {
    var list = grid.querySelector('.pro-grid__list');
    var countEl = grid.querySelector('.pro-grid__count');
    var moreBtn = grid.querySelector('.pro-grid__view-more');
    var pageSize = Number(grid.dataset.pageSize) || 15;

    var roster = null;
    var state = { q: '', letter: '', shown: pageSize };

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
        return true;
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
      if (window.ScrollTrigger) ScrollTrigger.refresh();
    }

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
          }, 150);
        });
      }
      filter.querySelector('.pro-filter__form').addEventListener('submit', function (e) {
        e.preventDefault();
        state.q = search ? search.value : '';
        apply();
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
          }, 0);
        });
      }
    }
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
  /* ---------------- insights / awards archive: View more ---------------- */
  document.querySelectorAll('.ins-grid[data-type]').forEach(function (grid) {
    if (!window.TNG || !TNG.archive) return;
    var list = grid.querySelector('.ins-grid__list');
    var more = grid.querySelector('.pro-grid__view-more');
    var count = grid.querySelector('.pro-grid__count');
    if (!list || !more) return;
    var size = Number(grid.dataset.pageSize) || 12;
    var total = Number(grid.dataset.total) || 0;
    var params = {};
    try {
      params = JSON.parse(grid.dataset.params || '{}') || {};
    } catch (e) {
      params = {};
    }
    var page = 1;

    more.addEventListener('click', function () {
      if (more.disabled) return;
      more.disabled = true;
      var url = new URL(TNG.archive, window.location.href);
      url.searchParams.set('type', grid.dataset.type);
      url.searchParams.set('page', String(page + 1));
      url.searchParams.set('per_page', String(size));
      Object.keys(params).forEach(function (k) {
        url.searchParams.set(k, params[k]);
      });
      fetch(url.toString())
        .then(function (r) { return r.json(); })
        .then(function (data) {
          page += 1;
          var tmp = document.createElement('ul');
          tmp.innerHTML = (data && data.html) || '';
          var items = Array.prototype.slice.call(tmp.children);
          items.forEach(function (li) { list.appendChild(li); });
          if (window.gsap && items.length) {
            gsap.from(items, { autoAlpha: 0, y: 30, duration: 0.6, ease: 'power3.out', stagger: 0.06 });
          }
          total = Number(data && data.total) || total;
          var shown = list.children.length;
          if (count) {
            count.innerHTML = 'Showing <strong>' + shown + '</strong> out of <strong>' + total + '</strong> results';
          }
          more.hidden = !(data && data.more);
          more.disabled = false;
          if (window.ScrollTrigger) ScrollTrigger.refresh();
        })
        .catch(function () {
          more.disabled = false;
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
