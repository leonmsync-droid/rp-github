// Rising Repos: theme switch, "/" to search, live search and re-weighting via api/board.php,
// and leaderboard rows that open a short summary (api/preview.php) when clicked.
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

    // Leaderboard rows: click to open a short summary; the repo name still goes to the repo page.
    // Heights use offsetHeight/scrollHeight, which ignore the desktop zoom, so the animation lines up.
    var list = document.getElementById('rows');
    if (list) {
      var EASE = 'cubic-bezier(.32, .72, 0, 1)';
      var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('animate' in Element.prototype);
      var cache = {};

      var load = function (id) {
        cache[id] = cache[id] || fetch('api/preview.php?id=' + encodeURIComponent(id))
          .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
          .catch(function (e) { delete cache[id]; throw e; });
        return cache[id];
      };

      var slide = function (box, from, to, opening) {
        if (still) return Promise.resolve();
        box.style.overflow = 'hidden';
        if (box.firstElementChild) {
          box.firstElementChild.animate(
            { opacity: opening ? [0, 1] : [1, 0], transform: opening ? ['translateY(-6px)', 'none'] : ['none', 'translateY(-6px)'] },
            { duration: opening ? 520 : 260, easing: EASE, fill: 'both' });
        }
        var anim = box.animate({ height: [from + 'px', to + 'px'] }, { duration: opening ? 520 : 400, easing: EASE });
        box._anim = anim;
        return anim.finished.then(function () { if (box._anim === anim) box.style.overflow = ''; });
      };

      var open = function (row, button) {
        var item = document.createElement('li');
        item.className = 'preview';
        item.id = 'preview-' + row.dataset.id;
        item.innerHTML = '<div class="preview-body preview-loading">Loading…</div>';
        row.after(item);
        row.classList.add('open');
        button.setAttribute('aria-expanded', 'true');
        button.setAttribute('aria-controls', item.id);
        slide(item, 0, item.scrollHeight, true);
        load(row.dataset.id).then(function (html) {
          if (!item.isConnected) return;
          var from = item.offsetHeight;
          item.innerHTML = html;
          slide(item, from, item.scrollHeight, true);
        }, function () {
          item.firstElementChild.textContent = 'Couldn’t load this repo. Open its page instead.';
        });
      };

      var close = function (row, button) {
        var item = row.nextElementSibling;
        row.classList.remove('open');
        button.setAttribute('aria-expanded', 'false');
        if (!item || !item.classList.contains('preview') || item.classList.contains('closing')) return;
        item.classList.add('closing');
        slide(item, item.offsetHeight, 0, false).then(function () { item.remove(); });
      };

      list.addEventListener('click', function (event) {
        var row = event.target.closest('li.row');
        if (!row || event.target.closest('a') || String(window.getSelection())) return;
        var next = row.nextElementSibling;
        var isOpen = row.classList.contains('open');
        if (!isOpen && next && next.classList.contains('preview')) next.remove(); // still closing: replace it
        (isOpen ? close : open)(row, row.querySelector('.row-toggle'));
      });
    }

    // Repo page: hover the score history for the date, score and change from the day before.
    // Positions are worked out as a share of the chart's width, so the desktop zoom doesn't matter.
    var scoreChart = document.getElementById('history');
    if (scoreChart) {
      var tipPoints = JSON.parse(scoreChart.dataset.points || '[]');
      var W = Number(scoreChart.dataset.w);
      var H = Number(scoreChart.dataset.h);
      var tip = document.getElementById('history-tip');
      var guide = scoreChart.querySelector('.guide');
      var hoverDot = scoreChart.querySelector('.hover-dot');

      var show = function (clientX) {
        var box = scoreChart.getBoundingClientRect();
        var x = (clientX - box.left) / box.width * W;
        var i = 0;
        tipPoints.forEach(function (p, k) { if (Math.abs(p[0] - x) < Math.abs(tipPoints[i][0] - x)) i = k; });
        var p = tipPoints[i];
        var prev = tipPoints[i - 1];
        var change = prev ? p[3] - prev[3] : null;
        guide.setAttribute('x1', p[0]);
        guide.setAttribute('x2', p[0]);
        hoverDot.setAttribute('cx', p[0]);
        hoverDot.setAttribute('cy', p[1]);
        tip.innerHTML = '<b></b><span class="tip-score"></span><span class="tip-change"></span>';
        tip.children[0].textContent = p[2];
        tip.children[1].textContent = 'Score ' + p[3].toFixed(1);
        tip.children[2].textContent = change === null ? 'First day tracked'
          : (change === 0 ? 'No change' : (change > 0 ? '+' : '−') + Math.abs(change).toFixed(1)) + ' on the day before';
        tip.children[2].className = 'tip-change ' + (change > 0 ? 'up' : change < 0 ? 'down' : '');
        // The chart box has 8px padding left and right around the SVG; the box sits along the top edge.
        tip.style.left = 'calc(8px + (100% - 16px) * ' + (p[0] / W) + ')';
        tip.classList.toggle('flip', p[0] > W * 0.7); // keep it inside the box near the right edge
        scoreChart.classList.add('hovering');
        tip.classList.add('on');
      };
      var hide = function () {
        scoreChart.classList.remove('hovering');
        tip.classList.remove('on');
      };
      scoreChart.addEventListener('mousemove', function (e) { show(e.clientX); });
      scoreChart.addEventListener('mouseleave', hide);
      scoreChart.addEventListener('touchstart', function (e) { show(e.touches[0].clientX); }, { passive: true });
      scoreChart.addEventListener('touchmove', function (e) { show(e.touches[0].clientX); }, { passive: true });
      scoreChart.addEventListener('touchend', hide);
    }

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
