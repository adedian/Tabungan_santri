/* Riwayat Tabungan: cari, filter (jenjang/kelas/bulan/tahun/mutasi/tanggal/santri), sort, paginasi,
   ringkasan sesuai filter, ubah & hapus transaksi. Data awal dari JSON di halaman; diperbarui otomatis lewat polling. */
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
  try { raw = JSON.parse($('#history-data').textContent); } catch (e) { return; }
  var canEdit = !!raw.can_edit, canDelete = !!raw.can_delete, TODAY = raw.today, MONTHS = raw.months;

  var F = raw.filters;
  var state = {
    q: F.q, student_id: F.student_id, jenjang: F.jenjang, kelas: F.kelas, month: F.month, year: F.year, mutation: F.mutation,
    from: F.from, to: F.to, sort: F.sort, dir: F.dir, page: F.page, per_page: F.per_page
  };
  var data = raw, options = raw.options, lastIds = null, fresh = {}, ctrl = null, seq = 0;
  var ctl = {
    q: $('#f-q'), jenjang: $('#f-jenjang'), kelas: $('#f-kelas'), month: $('#f-month'), year: $('#f-year'),
    mutation: $('#f-mutation'), from: $('#f-from'), to: $('#f-to')
  };

  /* ---------- Util ---------- */
  function fmtDate(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }
  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function today() { var p = TODAY.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }

  /* ---------- Kontrol filter ---------- */
  function kelasOptions(j) { return j ? options.classes[j] : Array.from(new Set(options.classes.TK.concat(options.classes.SD))); }
  function fillKelas() {
    var opts = kelasOptions(state.jenjang); ctl.kelas.textContent = '';
    el('option', null, 'Semua', ctl.kelas).value = '';
    opts.forEach(function (k) { el('option', null, k, ctl.kelas).value = k; });
    if (state.kelas && opts.indexOf(state.kelas) === -1) { state.kelas = ''; }
    ctl.kelas.value = state.kelas;
  }
  function fillYears() {
    ctl.year.textContent = ''; el('option', null, 'Semua', ctl.year).value = '';
    var ys = options.years.slice(); if (state.year && ys.indexOf(state.year) === -1) { ys.push(state.year); ys.sort(function (a, b) { return b - a; }); }
    ys.forEach(function (y) { el('option', null, String(y), ctl.year).value = String(y); });
    ctl.year.value = state.year ? String(state.year) : '';
  }
  function updateFilterCount() {
    var n = [state.jenjang, state.kelas, state.month, state.year, state.mutation, state.from, state.to].filter(Boolean).length;
    var b = $('#f-count'); b.hidden = n === 0; b.textContent = String(n);
  }
  function syncControls() {
    ctl.q.value = state.q; ctl.jenjang.value = state.jenjang; ctl.month.value = state.month ? String(state.month) : '';
    ctl.mutation.value = state.mutation; ctl.from.value = state.from; ctl.to.value = state.to;
    fillKelas(); fillYears();
  }

  var studentCombo = App.Combobox($('#f-student'), {
    fetch: function (q, signal) { return App.api('api/students/search?status=semua&limit=8&q=' + encodeURIComponent(q), { signal: signal }).then(function (r) { return r.data.items; }); },
    render: function (s) { return { title: s.name, sub: s.jenjang + ' • Kelas ' + s.kelas + ' • ID ' + s.code + (s.status === 'nonaktif' ? ' • nonaktif' : ''), right: App.rupiah(s.saldo) }; },
    display: function (s) { return s.name; },
    emptyText: 'Tidak ada santri yang cocok.',
    onSelect: function (s) { state.student_id = s.id; state.page = 1; load(); },
    onClear: function () { state.student_id = 0; state.page = 1; load(); }
  });
  if (raw.student) { studentCombo.setValue(raw.student); }

  function queryString() {
    var p = new URLSearchParams();
    if (state.q) { p.set('q', state.q); }
    if (state.student_id) { p.set('student_id', state.student_id); }
    if (state.jenjang) { p.set('jenjang', state.jenjang); }
    if (state.kelas) { p.set('kelas', state.kelas); }
    if (state.month) { p.set('month', state.month); }
    if (state.year) { p.set('year', state.year); }
    if (state.mutation) { p.set('mutation', state.mutation); }
    if (state.from) { p.set('from', state.from); }
    if (state.to) { p.set('to', state.to); }
    if (!(state.sort === 'date' && state.dir === 'desc')) { p.set('sort', state.sort); p.set('dir', state.dir); }
    if (state.page > 1) { p.set('page', state.page); }
    if (state.per_page !== 25) { p.set('per_page', state.per_page); }
    return p.toString();
  }

  /* ---------- Render ---------- */
  var tbody = $('#tbody'), wrap = $('#table-wrap'), empty = $('#empty');

  function renderSummary() {
    var s = data.summary;
    $('#s-count').textContent = App.formatMoney(s.count);
    $('#s-masuk').textContent = App.rupiah(s.masuk);
    $('#s-keluar').textContent = App.rupiah(s.keluar);
    var net = $('#s-net'); net.textContent = App.rupiah(s.net);
    net.className = 'stat-value ' + (s.net < 0 ? 'is-keluar' : '');
  }

  function renderRows() {
    tbody.textContent = '';
    var has = data.items.length > 0;
    wrap.hidden = !has; empty.hidden = has;
    if (!has) {
      var filtered = state.q || state.student_id || state.jenjang || state.kelas || state.month || state.year || state.mutation || state.from || state.to;
      $('#empty-title').textContent = filtered ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi';
      $('#empty-text').textContent = filtered ? 'Coba ubah atau hapus sebagian filter.' : 'Belum terdapat data transaksi tabungan pada periode ini.';
    }
    var now = Date.now();
    data.items.forEach(function (t) { if (lastIds && lastIds.indexOf(t.id) === -1) { fresh[t.id] = now + 2500; } });

    data.items.forEach(function (t) {
      var masuk = t.mutation === 'masuk';
      var tr = el('tr', fresh[t.id] && fresh[t.id] > now ? 'is-new' : '', null, tbody);
      el('td', 'nowrap tabular', fmtDate(t.date), tr);
      var nm = el('td', 'col-name', null, tr); el('div', 'cell-main', null, nm).appendChild(App.studentLink(t.student_id, t.student)); el('div', 'cell-sub nowrap', t.code, nm);
      el('td', null, null, tr).appendChild(el('span', 'badge badge-outline', t.jenjang));
      el('td', 'nowrap', t.kelas, tr);
      el('td', 'nowrap', (MONTHS[t.period_month] || '') + ' ' + t.period_year, tr);
      var mt = el('td', null, null, tr);
      var b = el('span', 'badge ' + (masuk ? 'badge-masuk' : 'badge-keluar'), null, mt);
      b.appendChild(App.icon(masuk ? 'arrow-down-left' : 'arrow-up-right')); b.appendChild(document.createTextNode(masuk ? 'Masuk' : 'Keluar'));
      el('td', 'num', null, tr).appendChild(el('span', 'amt ' + (masuk ? 'is-masuk' : 'is-keluar'), (masuk ? '+' : '−') + App.rupiah(t.amount)));
      el('td', 'cell-desc', t.description, tr);
      el('td', 'num amt', App.rupiah(t.saldo), tr);
      if (canEdit || canDelete) {
        var ac = el('td', 'col-actions', null, tr);
        if (canEdit) {
          var eb = el('button', 'btn btn-ghost btn-icon btn-sm', null, ac); eb.type = 'button'; eb.title = 'Ubah';
          eb.setAttribute('aria-label', 'Ubah transaksi ' + t.code); eb.appendChild(App.icon('pencil'));
          eb.addEventListener("click", function () { actions.edit(t); });
        }
        if (canDelete) {
          var db = el('button', 'btn btn-ghost btn-icon btn-sm', null, ac); db.type = 'button'; db.title = 'Hapus';
          db.setAttribute('aria-label', 'Hapus transaksi ' + t.code); db.appendChild(App.icon('trash'));
          db.addEventListener("click", function () { actions.remove(t); });
        }
      }
    });
    lastIds = data.items.map(function (t) { return t.id; });
  }

  function renderPager() {
    App.Table.pager($('#pager'), {
      page: data.page, pages: data.pages, total: data.total, perPage: state.per_page, perPageOptions: [10, 25, 50], noun: 'transaksi',
      onPage: function (p) { state.page = p; load(); },
      onPerPage: function (n) { state.per_page = n; state.page = 1; load(); }
    });
  }
  function render() { options = data.options; fillKelas(); fillYears(); updateFilterCount(); renderSummary(); renderRows(); renderPager(); }

  /* ---------- Muat data ---------- */
  function load(opts) {
    opts = opts || {};
    if (ctrl) { ctrl.abort(); }
    ctrl = new AbortController(); var mine = ++seq;
    wrap.classList.add('is-refreshing');
    var qs = queryString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    var api = new URLSearchParams(qs); api.set('sort', state.sort); api.set('dir', state.dir);
    return App.api('api/savings/list?' + api.toString(), { signal: ctrl.signal, headers: opts.background ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) { if (mine !== seq) { return; } data = res.data; state.page = data.page; render(); })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seq) { App.toast('error', e.message); } })
      .then(function () { if (mine === seq) { wrap.classList.remove('is-refreshing'); } });
  }

  /* ---------- Ubah & hapus (modul bersama) ---------- */
  var actions = App.SavingsActions({ onChanged: function () { return load(); } });

  /* ---------- Pasang ---------- */
  var sorter = App.Table.sortHeaders($('#thead'), state, function () { state.page = 1; load(); });
  syncControls(); render();

  function bind(c, key, num) { c.addEventListener('change', function () { state[key] = num ? (parseInt(c.value, 10) || 0) : c.value; state.page = 1; load(); }); }
  ctl.q.addEventListener('input', App.debounce(function () { state.q = ctl.q.value.trim(); state.page = 1; load(); }, 260));
  $('#filters').addEventListener('submit', function (e) { e.preventDefault(); });
  ctl.jenjang.addEventListener('change', function () { state.jenjang = ctl.jenjang.value; state.kelas = ''; state.page = 1; fillKelas(); load(); });
  bind(ctl.kelas, 'kelas'); bind(ctl.month, 'month', true); bind(ctl.year, 'year', true); bind(ctl.mutation, 'mutation');
  bind(ctl.from, 'from'); bind(ctl.to, 'to');

  $$('.chip[data-range]').forEach(function (c) {
    c.addEventListener('click', function () {
      var t = today(), from = new Date(t);
      if (c.dataset.range === 'week') { from.setDate(from.getDate() - 6); } else if (c.dataset.range === 'month') { from.setDate(1); }
      state.from = iso(from); state.to = iso(t); ctl.from.value = state.from; ctl.to.value = state.to; state.page = 1; load();
    });
  });
  var toolbar = $('#filters'), toggle = $('#f-toggle');
  toggle.addEventListener('click', function () { var open = !toolbar.classList.contains('is-open'); toolbar.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', String(open)); });
  if ([state.jenjang, state.kelas, state.month, state.year, state.mutation, state.from, state.to].some(Boolean)) { toolbar.classList.add('is-open'); toggle.setAttribute('aria-expanded', 'true'); }

  $('#f-reset').addEventListener('click', function () {
    state.q = ''; state.student_id = 0; state.jenjang = ''; state.kelas = ''; state.month = 0; state.year = 0; state.mutation = ''; state.from = ''; state.to = '';
    state.sort = 'date'; state.dir = 'desc'; state.page = 1;
    studentCombo.clear(); $('#f-student').value = ''; syncControls(); sorter.paint(); load(); ctl.q.focus();
  });

  App.watch(['savings', 'students'], function () { return load({ background: true }); }, data.rev);
})();
