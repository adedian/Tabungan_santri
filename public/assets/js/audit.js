/* Audit Log: cari, filter (modul/pengguna/tanggal), sort, paginasi, ringkasan sesuai filter.
   Data awal dari JSON di halaman. Tidak memakai polling realtime; tombol "Muat ulang" mengambil data terbaru. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function el(tag, cls, text, parent) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text != null) { e.textContent = text; }
    if (parent) { parent.appendChild(e); }
    return e;
  }

  var raw;
  try { raw = JSON.parse($('#audit-data').textContent); } catch (e) { return; }
  var TODAY = raw.today;

  var F = raw.filters;
  var state = { q: F.q, module: F.module, user_id: F.user_id, from: F.from, to: F.to, sort: F.sort, dir: F.dir, page: F.page, per_page: F.per_page };
  var data = raw, options = raw.options, ctrl = null, seq = 0;
  var ctl = { q: $('#f-q'), module: $('#f-module'), user: $('#f-user'), from: $('#f-from'), to: $('#f-to') };

  /* ---------- Util ---------- */
  function pad(n) { return String(n).padStart(2, '0'); }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function today() { var p = TODAY.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  /** '2026-10-06 08:15:30' → ['06/10/2026', '08:15:30'] */
  function splitTime(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2}:\d{2})/.exec(String(s || ''));
    return m ? [m[3] + '/' + m[2] + '/' + m[1], m[4]] : [String(s || ''), ''];
  }

  /* ---------- Kontrol filter ---------- */
  function fillSelects() {
    ctl.module.textContent = ''; el('option', null, 'Semua', ctl.module).value = '';
    var mods = options.modules.slice();
    if (state.module && mods.indexOf(state.module) === -1) { mods.push(state.module); }
    mods.forEach(function (m) { el('option', null, m, ctl.module).value = m; });
    ctl.module.value = state.module;

    ctl.user.textContent = ''; el('option', null, 'Semua', ctl.user).value = '';
    options.users.forEach(function (u) { el('option', null, u.name, ctl.user).value = String(u.id); });
    ctl.user.value = state.user_id ? String(state.user_id) : '';
  }
  function updateFilterCount() {
    var n = [state.module, state.user_id, state.from, state.to].filter(Boolean).length;
    var b = $('#f-count'); b.hidden = n === 0; b.textContent = String(n);
  }
  function syncControls() { ctl.q.value = state.q; ctl.from.value = state.from; ctl.to.value = state.to; fillSelects(); }

  function queryString() {
    var p = new URLSearchParams();
    if (state.q) { p.set('q', state.q); }
    if (state.module) { p.set('module', state.module); }
    if (state.user_id) { p.set('user_id', state.user_id); }
    if (state.from) { p.set('from', state.from); }
    if (state.to) { p.set('to', state.to); }
    if (!(state.sort === 'time' && state.dir === 'desc')) { p.set('sort', state.sort); p.set('dir', state.dir); }
    if (state.page > 1) { p.set('page', state.page); }
    if (state.per_page !== 50) { p.set('per_page', state.per_page); }
    return p.toString();
  }

  /* ---------- Render ---------- */
  var tbody = $('#tbody'), wrap = $('#table-wrap'), empty = $('#empty');

  function renderSummary() {
    var s = data.summary, t = s.latest ? splitTime(s.latest) : null;
    $('#s-count').textContent = App.formatMoney(s.count);
    $('#s-users').textContent = App.formatMoney(s.users);
    $('#s-modules').textContent = App.formatMoney(s.modules);
    $('#s-latest').textContent = t ? t[0] + ' ' + t[1].slice(0, 5) : '—';
  }

  function renderRows() {
    tbody.textContent = '';
    var has = data.items.length > 0;
    wrap.hidden = !has; empty.hidden = has;
    if (!has) {
      var filtered = state.q || state.module || state.user_id || state.from || state.to;
      $('#empty-title').textContent = filtered ? 'Tidak ada catatan yang cocok' : 'Belum ada catatan';
      $('#empty-text').textContent = filtered ? 'Coba ubah atau hapus sebagian filter.' : 'Aktivitas pengguna akan tercatat di sini.';
    }
    data.items.forEach(function (a) {
      var tr = el('tr', null, null, tbody), t = splitTime(a.created_at);
      var tm = el('td', 'nowrap tabular', null, tr); el('div', 'cell-main', t[0], tm); el('div', 'cell-sub', t[1], tm);
      el('td', 'nowrap', a.user, tr);
      el('td', null, null, tr).appendChild(el('span', 'badge badge-outline', a.module));
      el('td', 'cell-main', a.action, tr);
      el('td', 'nowrap tabular cell-sub', a.reference || '—', tr);
      el('td', 'cell-desc', a.description || '—', tr);
      el('td', 'nowrap tabular cell-sub', a.ip || '—', tr);
    });
  }

  function renderPager() {
    App.Table.pager($('#pager'), {
      page: data.page, pages: data.pages, total: data.total, perPage: state.per_page, perPageOptions: [25, 50, 100], noun: 'catatan',
      onPage: function (p) { state.page = p; load(); },
      onPerPage: function (n) { state.per_page = n; state.page = 1; load(); }
    });
  }
  function render() { options = data.options; fillSelects(); updateFilterCount(); renderSummary(); renderRows(); renderPager(); }

  /* ---------- Muat data ---------- */
  function load() {
    if (ctrl) { ctrl.abort(); }
    ctrl = new AbortController(); var mine = ++seq;
    wrap.classList.add('is-refreshing');
    var qs = queryString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    var api = new URLSearchParams(qs); api.set('sort', state.sort); api.set('dir', state.dir);
    return App.api('api/audit/list?' + api.toString(), { signal: ctrl.signal })
      .then(function (res) { if (mine !== seq) { return; } data = res.data; state.page = data.page; render(); })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seq) { App.toast('error', e.message); } })
      .then(function () { if (mine === seq) { wrap.classList.remove('is-refreshing'); } });
  }

  /* ---------- Pasang ---------- */
  var sorter = App.Table.sortHeaders($('#thead'), state, function () { state.page = 1; load(); });
  syncControls(); render();

  ctl.q.addEventListener('input', App.debounce(function () { state.q = ctl.q.value.trim(); state.page = 1; load(); }, 260));
  $('#filters').addEventListener('submit', function (e) { e.preventDefault(); });
  ctl.module.addEventListener('change', function () { state.module = ctl.module.value; state.page = 1; load(); });
  ctl.user.addEventListener('change', function () { state.user_id = parseInt(ctl.user.value, 10) || 0; state.page = 1; load(); });
  ctl.from.addEventListener('change', function () { state.from = ctl.from.value; state.page = 1; load(); });
  ctl.to.addEventListener('change', function () { state.to = ctl.to.value; state.page = 1; load(); });

  $$('.chip[data-range]').forEach(function (c) {
    c.addEventListener('click', function () {
      var t = today(), from = new Date(t);
      if (c.dataset.range === 'week') { from.setDate(from.getDate() - 6); } else if (c.dataset.range === 'month') { from.setDate(1); }
      state.from = iso(from); state.to = iso(t); ctl.from.value = state.from; ctl.to.value = state.to; state.page = 1; load();
    });
  });
  var toolbar = $('#filters'), toggle = $('#f-toggle');
  toggle.addEventListener('click', function () { var open = !toolbar.classList.contains('is-open'); toolbar.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', String(open)); });
  if ([state.module, state.user_id, state.from, state.to].some(Boolean)) { toolbar.classList.add('is-open'); toggle.setAttribute('aria-expanded', 'true'); }

  $('#f-reset').addEventListener('click', function () {
    state.q = ''; state.module = ''; state.user_id = 0; state.from = ''; state.to = ''; state.sort = 'time'; state.dir = 'desc'; state.page = 1;
    syncControls(); sorter.paint(); load(); ctl.q.focus();
  });
  $('#a-refresh').addEventListener('click', function () { var b = this; App.setLoading(b, true); load().then(function () { App.setLoading(b, false); }); });
})();
