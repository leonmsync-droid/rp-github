// Rising Repos: theme switch, "/" to search, and live search and re-weighting via api/board.php.
(function () {
  'use strict';

  var root = document.documentElement;
  root.classList.add('js');

  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } }
  };

  var saved = store.get('theme');
  if (saved === 'light' || saved === 'dark') root.dataset.theme = saved;

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('theme');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var dark = root.dataset.theme
          ? root.dataset.theme === 'dark'
          : window.matchMedia('(prefers-color-scheme: dark)').matches;
        root.dataset.theme = dark ? 'light' : 'dark';
        store.set('theme', root.dataset.theme);
      });
    }

    // On narrow screens the filters start folded so the leaderboard comes first.
    if (window.matchMedia('(max-width: 900px)').matches) {
      document.querySelectorAll('.side-block').forEach(function (d) { d.open = false; });
    }

    var search = document.getElementById('search');
    document.addEventListener('keydown', function (e) {
      if (e.key === '/' && search && document.activeElement !== search && !/input|textarea|select/i.test(document.activeElement.tagName)) {
        e.preventDefault();
        search.focus();
      }
    });

    // Live updates only on the leaderboard page.
    var rows = document.getElementById('rows');
    var form = document.getElementById('weights');
    if (!rows || !form) return;

    var summary = document.getElementById('summary');
    var foot = document.getElementById('foot');
    var sliders = form.querySelectorAll('input[type=range]');
    var params = new URLSearchParams(location.search);
    var timer = null;
    var seq = 0;

    var showShares = function () {
      var total = 0;
      sliders.forEach(function (s) { total += Number(s.value); });
      sliders.forEach(function (s) {
        var out = form.querySelector('output[data-for="' + s.name.slice(2, -1) + '"]');
        if (out) out.textContent = total ? Math.round(Number(s.value) / total * 100) + '%' : '0%';
      });
    };

    var refresh = function () {
      var q = new URLSearchParams();
      if (params.get('lang')) q.set('lang', params.get('lang'));
      if (search && search.value.trim()) q.set('q', search.value.trim());
      var custom = false;
      sliders.forEach(function (s) {
        if (s.value !== s.dataset.default) custom = true;
      });
      if (custom) sliders.forEach(function (s) { q.set(s.name, s.value); });

      var mine = ++seq;
      rows.classList.add('loading');
      fetch('api/board.php?' + q.toString(), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (data) {
          if (mine !== seq) return;
          rows.innerHTML = data.html;
          summary.innerHTML = data.summary;
          foot.textContent = data.foot;
          history.replaceState(null, '', q.toString() ? '?' + q.toString() : location.pathname);
        })
        .catch(function () { /* keep the current list */ })
        .then(function () { if (mine === seq) rows.classList.remove('loading'); });
    };

    var later = function () {
      clearTimeout(timer);
      timer = setTimeout(refresh, 200);
    };

    sliders.forEach(function (s) {
      s.addEventListener('input', function () { showShares(); later(); });
    });
    showShares();

    var reset = document.getElementById('reset');
    if (reset) {
      reset.addEventListener('click', function (e) {
        e.preventDefault();
        sliders.forEach(function (s) { s.value = s.dataset.default; });
        showShares();
        refresh();
      });
    }

    if (search) {
      search.addEventListener('input', later);
      search.form.addEventListener('submit', function (e) { e.preventDefault(); refresh(); });
    }
  });
})();
