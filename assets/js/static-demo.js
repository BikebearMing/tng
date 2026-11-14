// TNG — static preview only (not deployed to WordPress). Stands in for
// GET /wp-json/tng/v1/archive so the insights / awards filter bar works on
// the static pages: filters a small set of real posts (assets/data/*-demo.json,
// copied from staging 2026-11-14) the way inc/archive.php does and returns the
// same { html, total, more }. main.js (initArchive) calls window.tngArchiveSource.
(function () {
  'use strict';
  var grid = document.querySelector('.ins-grid[data-demo]');
  if (!grid) return;
  var ARROW = '<span class="arrow-circle arrow-circle--lg" aria-hidden="true"><svg viewBox="0 0 10 10" fill="none"><path d="M3 1l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var data = null;
  var roster = [];

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function load() {
    if (data) return Promise.resolve(data);
    var rosterUrl = document.querySelector('input[data-roster]');
    return Promise.all([
      fetch(grid.dataset.demo).then(function (r) { return r.json(); }),
      rosterUrl ? fetch(rosterUrl.dataset.roster).then(function (r) { return r.json(); }) : [],
    ]).then(function (res) { data = res[0]; roster = res[1]; return data; });
  }
  function card(it) {
    var d = it.date.split('-');
    return '<li><a href="' + esc(grid.dataset.link || 'insight.html') + '" class="post-card">' +
      '<h3 class="h3 post-card__title">' + esc(it.title) + '</h3>' +
      '<p class="body post-card__excerpt">' + esc(it.excerpt) + '</p>' +
      '<p class="post-card__foot"><time class="subhead" datetime="' + it.date + '">' + MONTHS[d[1] - 1] + ' ' + d[2] + ', ' + d[0] + '</time>' + ARROW + '</p>' +
      '</a></li>';
  }
  // ?pro= is a slug, else a typed name: exact name, then every word
  function pros(value) {
    var v = value.toLowerCase();
    var bySlug = roster.filter(function (p) { return p.slug === v; });
    if (bySlug.length) return bySlug.map(function (p) { return p.slug; });
    var exact = roster.filter(function (p) { return p.name.toLowerCase() === v; });
    var words = v.split(/\s+/);
    return (exact.length ? exact : roster.filter(function (p) {
      return words.every(function (w) { return p.name.toLowerCase().indexOf(w) !== -1; });
    })).map(function (p) { return p.slug; });
  }

  window.tngArchiveSource = function (p, page, size) {
    return load().then(function (all) {
      var q = (p.q || '').toLowerCase();
      var who = p.pro ? pros(p.pro) : null;
      var rows = all.filter(function (it) {
        if (q && (it.title + ' ' + it.excerpt).toLowerCase().indexOf(q) === -1) return false;
        if (who && !it.pro.some(function (s) { return who.indexOf(s) !== -1; })) return false;
        if (p.date && it.date.slice(0, 4) !== p.date) return false;
        return ['res', 'ind', 'prac', 'loc'].every(function (k) { return !p[k] || it[k].indexOf(p[k]) !== -1; });
      });
      if (p.sort === 'oldest') rows.reverse();
      else if (q && p.sort !== 'newest') { // relevance: title matches first, then newest
        rows = rows.filter(function (it) { return it.title.toLowerCase().indexOf(q) !== -1; })
          .concat(rows.filter(function (it) { return it.title.toLowerCase().indexOf(q) === -1; }));
      }
      var slice = rows.slice((page - 1) * size, page * size);
      return new Promise(function (ok) {
        setTimeout(function () { ok({ html: slice.map(card).join(''), total: rows.length, more: page * size < rows.length }); }, 150);
      });
    });
  };
})();
