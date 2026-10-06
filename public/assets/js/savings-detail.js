/* Detail Tabungan Santri: profil mini + riwayat transaksi santri (cari/filter/sort/paginasi, ubah/hapus).
   Data awal dari JSON di halaman; profil & daftar diperbarui otomatis lewat polling revisi. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  function el(tag, cls, text, parent) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text != null) { e.textContent = text; }
    if (parent) { parent.appendChild(e); }
    return e;
  }

  var raw;
  try { raw = JSON.parse($('#detail-data').textContent); } catch (e) { return; }
  var SID = raw.profile.student.id, canEdit = !!raw.can_edit, canDelete = !!raw.can_delete, MONTHS = raw.months;

  var F = raw.list.filters;
  var state = { q: F.q, mutation: F.mutation, month: F.month, year: F.year, from: F.from, to: F.to, sort: F.sort, dir: F.dir, page: F.page, per_page: F.per_page };
  var data = raw.list, profile = raw.profile, fresh = {}, lastIds = null, ctrl = null, seq = 0, years = raw.list.options.years;
  var ctl = { q: $('#f-q'), mutation: $('#f-mutation'), month: $('#f-month'), year: $('#f-year'), from: $('#f-from'), to: $('#f-to') };

  function fmtDate(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }
  function dash(v) { return v === null || v === undefined || v === '' ? '–' : String(v); }

  /* ---------- Profil ---------- */
  function setMoney(node, n) {
    node.textContent = '';
    if (n < 0) { node.appendChild(document.createTextNode('−')); }
    el('span', 'cur', 'Rp', node); node.appendChild(document.createTextNode(' ' + App.formatMoney(Math.abs(n))));
  }
  function renderProfile() {
    var s = profile.student, m = profile.summary;
    ['name', 'jenjang', 'kelas', 'code'].forEach(function (k) { Array.prototype.forEach.call(document.querySelectorAll('[data-f="' + k + '"]'), function (n) { n.textContent = s[k]; }); });
    ['nis', 'no_urut', 'dawis_blok'].forEach(function (k) { var n = $('[data-f="' + k + '"]'); if (n) { n.textContent = dash(s[k]); } });
    var title = $('.page-title'); if (title) { title.textContent = s.name; }
    var lead = $('.page-lead'); if (lead) { lead.textContent = s.jenjang + ' — Kelas ' + s.kelas + ' • ID ' + s.code; }
    var active = s.status === 'aktif';
    var st = $('#p-status'); st.textContent = active ? 'Aktif' : 'Nonaktif'; st.className = 'badge ' + (active ? 'badge-success' : 'badge-muted');
    $('#inactive-note').hidden = active; var add = $('#btn-add'); if (add) { add.hidden = !active; }
    [['saldo', m.saldo], ['masuk', m.masuk], ['keluar', m.keluar]].forEach(function (p) {
      var node = $('[data-stat="' + p[0] + '"]'), changed = node.dataset.v !== undefined && node.dataset.v !== String(p[1]);
      setMoney(node, p[1]); node.dataset.v = String(p[1]);
      if (changed) { var card = node.closest('.stat'); card.classList.remove('is-updated'); void card.offsetWidth; card.classList.add('is-updated'); }
    });
    $('#p-last').textContent = m.last_date ? 'Transaksi terakhir ' + fmtDate(m.last_date) : 'Belum ada transaksi';
  }

  /* ---------- Filter ---------- */
  function fillYears() {
    ctl.year.textContent = ''; el('option', null, 'Semua', ctl.year).value = '';
    var ys = years.slice(); if (state.year && ys.indexOf(state.year) === -1) { ys.push(state.year); ys.sort(function (a, b) { return b - a; }); }
    ys.forEach(function (y) { el('option', null, String(y), ctl.year).value = String(y); });
    ctl.year.value = state.year ? String(state.year) : '';
  }
  function syncControls() { ctl.q.value = state.q; ctl.mutation.value = state.mutation; ctl.month.value = state.month ? String(state.month) : ''; ctl.from.value = state.from; ctl.to.value = state.to; fillYears(); }

  function queryString() {
    var p = new URLSearchParams();
    ['q', 'mutation', 'from', 'to'].forEach(function (k) { if (state[k]) { p.set(k, state[k]); } });
    if (state.month) { p.set('month', state.month); }
    if (state.year) { p.set('year', state.year); }
    if (!(state.sort === 'date' && state.dir === 'desc')) { p.set('sort', state.sort); p.set('dir', state.dir); }
    if (state.page > 1) { p.set('page', state.page); }
    if (state.per_page !== 25) { p.set('per_page', state.per_page); }
    return p.toString();
  }

  /* ---------- Daftar ---------- */
  var tbody = $('#tbody'), wrap = $('#table-wrap'), empty = $('#empty');
  var actions = App.SavingsActions({ onChanged: function () { return refresh(); } });

  function renderRows() {
    tbody.textContent = '';
    var has = data.items.length > 0;
    wrap.hidden = !has; empty.hidden = has;
    if (!has) {
      var filtered = state.q || state.mutation || state.month || state.year || state.from || state.to;
      $('#empty-title').textContent = filtered ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi';
      $('#empty-text').textContent = filtered ? 'Coba ubah atau hapus sebagian filter.' : 'Belum terdapat data transaksi tabungan untuk santri ini.';
    }
    var now = Date.now();
    data.items.forEach(function (t) { if (lastIds && lastIds.indexOf(t.id) === -1) { fresh[t.id] = now + 2500; } });
    data.items.forEach(function (t) {
      var masuk = t.mutation === 'masuk';
      var tr = el('tr', fresh[t.id] && fresh[t.id] > now ? 'is-new' : '', null, tbody);
      var d = el('td', 'nowrap', null, tr); el('div', 'tabular', fmtDate(t.date), d); el('div', 'cell-sub nowrap', t.code, d);
      el('td', 'nowrap', (MONTHS[t.period_month] || '') + ' ' + t.period_year, tr);
      var b = el('span', 'badge ' + (masuk ? 'badge-masuk' : 'badge-keluar'), null, el('td', null, null, tr));
      b.appendChild(App.icon(masuk ? 'arrow-down-left' : 'arrow-up-right')); b.appendChild(document.createTextNode(masuk ? 'Masuk' : 'Keluar'));
      el('td', 'num', null, tr).appendChild(el('span', 'amt ' + (masuk ? 'is-masuk' : 'is-keluar'), (masuk ? '+' : '−') + App.rupiah(t.amount)));
      el('td', 'cell-desc', t.description, tr);
      el('td', 'num amt', App.rupiah(t.saldo), tr);
      if (canEdit || canDelete) {
        var ac = el('td', 'col-actions', null, tr);
        if (canEdit) { var eb = el('button', 'btn btn-ghost btn-icon btn-sm', null, ac); eb.type = 'button'; eb.title = 'Ubah'; eb.setAttribute('aria-label', 'Ubah transaksi ' + t.code); eb.appendChild(App.icon('pencil')); eb.addEventListener('click', function () { actions.edit(t); }); }
        if (canDelete) { var db = el('button', 'btn btn-ghost btn-icon btn-sm', null, ac); db.type = 'button'; db.title = 'Hapus'; db.setAttribute('aria-label', 'Hapus transaksi ' + t.code); db.appendChild(App.icon('trash')); db.addEventListener('click', function () { actions.remove(t); }); }
      }
    });
    lastIds = data.items.map(function (t) { return t.id; });
  }
  function renderPager() {
    App.Table.pager($('#pager'), {
      page: data.page, pages: data.pages, total: data.total, perPage: state.per_page, perPageOptions: [10, 25, 50], noun: 'transaksi',
      onPage: function (p) { state.page = p; loadList(); }, onPerPage: function (n) { state.per_page = n; state.page = 1; loadList(); }
    });
  }
  function renderSub() {
    var s = data.summary;
    $('#hist-sub').textContent = App.formatMoney(s.count) + ' transaksi • Masuk ' + App.rupiah(s.masuk) + ' • Keluar ' + App.rupiah(s.keluar);
  }
  function renderList() { years = data.options.years; fillYears(); renderSub(); renderRows(); renderPager(); }

  function loadList(opts) {
    opts = opts || {};
    if (ctrl) { ctrl.abort(); }
    ctrl = new AbortController(); var mine = ++seq;
    wrap.classList.add('is-refreshing');
    var qs = queryString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    var api = new URLSearchParams(qs); api.set('sort', state.sort); api.set('dir', state.dir); api.set('student_id', SID);
    return App.api('api/savings/list?' + api.toString(), { signal: ctrl.signal, headers: opts.background ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) { if (mine !== seq) { return; } data = res.data; state.page = data.page; renderList(); })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seq) { App.toast('error', e.message); } })
      .then(function () { if (mine === seq) { wrap.classList.remove('is-refreshing'); } });
  }
  function loadProfile(opts) {
    return App.api('api/savings/student/' + SID, { headers: opts && opts.background ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) { profile = res.data; renderProfile(); })
      .catch(function (e) { if (e && e.status === 404) { location.href = App.url('santri'); } else if (!(opts && opts.background)) { App.toast('error', e.message); } });
  }
  function refresh(opts) { return Promise.all([loadProfile(opts), loadList(opts)]); }

  /* ---------- Pasang ---------- */
  var sorter = App.Table.sortHeaders($('#thead'), state, function () { state.page = 1; loadList(); });
  syncControls(); renderProfile(); renderList();

  function bind(c, key, num) { c.addEventListener('change', function () { state[key] = num ? (parseInt(c.value, 10) || 0) : c.value; state.page = 1; loadList(); }); }
  ctl.q.addEventListener('input', App.debounce(function () { state.q = ctl.q.value.trim(); state.page = 1; loadList(); }, 260));
  $('#filters').addEventListener('submit', function (e) { e.preventDefault(); });
  bind(ctl.mutation, 'mutation'); bind(ctl.month, 'month', true); bind(ctl.year, 'year', true); bind(ctl.from, 'from'); bind(ctl.to, 'to');
  $('#f-reset').addEventListener('click', function () {
    state.q = ''; state.mutation = ''; state.month = 0; state.year = 0; state.from = ''; state.to = ''; state.sort = 'date'; state.dir = 'desc'; state.page = 1;
    syncControls(); sorter.paint(); loadList(); ctl.q.focus();
  });

  App.watch(['savings', 'students'], function () { return refresh({ background: true }); }, raw.profile.rev);
})();
