/* Tabungan Alumni — "Tarik Data": Detail (per alumni) atau Rekap (agregat). Hanya baca.
   Data awal dari JSON di halaman; "Tampilkan Data" mengambil ulang lewat /api/alumni. */
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
  function fmtDate(iso) { var p = String(iso || '').split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : '–'; }

  var raw;
  try { raw = JSON.parse($('#alumni-data').textContent); } catch (e) { return; }

  var F = raw.filters;
  var state = { mode: F.mode, q: F.q, year: F.year, academic_year: F.academic_year, sort: F.sort, dir: F.dir, page: F.page, per_page: F.per_page };
  var data = raw, ctrl = null, seq = 0;

  var fYear = $('#a-year'), fAy = $('#a-ay'), fQ = $('#a-q');
  var tbody = $('#tbody'), wrap = $('#detail-wrap'), empty = $('#empty');

  /* ---------- Kontrol ---------- */
  function fillOptions() {
    fYear.textContent = ''; el('option', null, 'Semua tahun', fYear).value = '';
    data.options.years.forEach(function (y) { el('option', null, String(y), fYear).value = String(y); });
    fYear.value = state.year ? String(state.year) : '';
    fAy.textContent = ''; el('option', null, 'Semua tahun ajaran', fAy).value = '';
    data.options.academic_years.forEach(function (a) { el('option', null, a, fAy).value = a; });
    fAy.value = state.academic_year || '';
  }
  function syncControls() {
    Array.prototype.forEach.call(document.querySelectorAll('input[name="mode"]'), function (r) { r.checked = r.value === state.mode; });
    fQ.value = state.q; fillOptions();
    $('#a-q-field').hidden = state.mode === 'rekap';
  }

  function queryString() {
    var p = new URLSearchParams();
    p.set('mode', state.mode);
    if (state.q && state.mode === 'detail') { p.set('q', state.q); }
    if (state.year) { p.set('year', state.year); }
    if (state.academic_year) { p.set('academic_year', state.academic_year); }
    if (state.mode === 'detail') {
      if (!(state.sort === 'year' && state.dir === 'desc')) { p.set('sort', state.sort); p.set('dir', state.dir); }
      if (state.page > 1) { p.set('page', state.page); }
      if (state.per_page !== 25) { p.set('per_page', state.per_page); }
    }
    return p.toString();
  }

  /* ---------- Render ---------- */
  function renderSummary() {
    var s = data.summary;
    $('#s-count').textContent = App.formatMoney(s.alumni);
    $('#s-saldo').textContent = App.rupiah(s.saldo);
    $('#s-masuk').textContent = App.rupiah(s.masuk);
    $('#s-keluar').textContent = App.rupiah(s.keluar);
  }
  function filterText() {
    var t = [];
    t.push(state.year ? 'Tahun lulus ' + state.year : 'Semua tahun lulus');
    if (state.academic_year) { t.push('TA ' + state.academic_year); }
    if (state.q && state.mode === 'detail') { t.push('cari “' + state.q + '”'); }
    return t.join(' • ');
  }

  function renderDetail() {
    var has = data.items.length > 0;
    wrap.hidden = !has; empty.hidden = has;
    if (!has) {
      var filtered = state.q || state.year || state.academic_year;
      $('#empty-title').textContent = filtered ? 'Tidak ada alumni yang cocok' : 'Belum ada alumni';
      $('#empty-text').textContent = filtered ? 'Coba ubah filter tahun lulus, tahun ajaran, atau kata kunci.' : 'Santri yang lulus lewat menu Kenaikan Kelas (SD kelas 6 + Naik) akan muncul di sini.';
    }
    $('#detail-sub').textContent = filterText();
    tbody.textContent = '';
    var offset = (data.page - 1) * state.per_page;
    data.items.forEach(function (a, i) {
      var tr = el('tr', null, null, tbody);
      el('td', 'tabular muted', String(offset + i + 1), tr);
      var nm = el('td', null, null, tr);
      var link = el('a', 'cell-link', a.name, el('div', 'cell-main', null, nm)); link.href = App.url('tabungan/alumni/' + a.id);
      el('div', 'cell-sub', 'ID ' + a.code + (a.nis ? ' • NIS ' + a.nis : ''), nm);
      var kc = el('td', 'nowrap', null, tr); el('span', 'badge badge-outline', a.jenjang, kc); kc.appendChild(document.createTextNode(' ')); el('span', 'cell-main', a.kelas, kc);
      var yc = el('td', null, null, tr); el('div', 'tabular', a.graduation_year == null ? '–' : String(a.graduation_year), yc); el('div', 'cell-sub', a.graduated_at ? fmtDate(a.graduated_at) : '', yc);
      el('td', 'num amt is-masuk', App.rupiah(a.masuk), tr);
      el('td', 'num amt is-keluar', App.rupiah(a.keluar), tr);
      el('td', 'num amt', App.rupiah(a.saldo), tr);
      var ac = el('td', 'col-actions', null, tr);
      var b = el('a', 'btn btn-secondary btn-sm', null, ac); b.href = App.url('tabungan/alumni/' + a.id);
      b.appendChild(App.icon('eye')); b.appendChild(document.createTextNode('Lihat Detail'));
    });
    App.Table.pager($('#pager'), {
      page: data.page, pages: data.pages, total: data.total, perPage: state.per_page, perPageOptions: [10, 25, 50], noun: 'alumni',
      onPage: function (p) { state.page = p; load(); }, onPerPage: function (n) { state.per_page = n; state.page = 1; load(); }
    });
  }

  function renderRecap() {
    var rows = data.recap, body = $('#recap-body'), foot = $('#recap-foot');
    body.textContent = ''; foot.textContent = '';
    var has = rows.length > 0;
    $('#recap-empty').hidden = has; $('#recap-table').closest('.table-wrap').hidden = !has;
    $('#recap-th').textContent = data.recap_by === 'kelas' ? 'Kelas Terakhir' : 'Tahun Lulus';
    $('#rekap-sub').textContent = filterText();
    $('#rekap-title').textContent = 'Rekap Tabungan Alumni' + (state.year ? ' — Tahun Lulus ' + state.year : '');
    rows.forEach(function (r) {
      var tr = el('tr', null, null, body);
      el('td', 'cell-main', r.label, tr);
      el('td', 'num tabular', App.formatMoney(r.alumni), tr);
      el('td', 'num amt is-masuk', App.rupiah(r.masuk), tr);
      el('td', 'num amt is-keluar', App.rupiah(r.keluar), tr);
      el('td', 'num amt', App.rupiah(r.saldo), tr);
    });
    if (has) {
      var s = data.summary, ft = el('tr', null, null, foot);
      el('td', null, 'Total', ft);
      el('td', 'num tabular', App.formatMoney(s.alumni), ft);
      el('td', 'num', App.rupiah(s.masuk), ft); el('td', 'num', App.rupiah(s.keluar), ft); el('td', 'num', App.rupiah(s.saldo), ft);
    }
  }

  function render() {
    var s = data.filters; state.page = s.page;
    fillOptions(); renderSummary();
    var rekap = state.mode === 'rekap';
    $('#pane-detail').hidden = rekap; $('#pane-rekap').hidden = !rekap;
    $('#a-q-field').hidden = rekap;
    if (rekap) { renderRecap(); } else { renderDetail(); }
  }

  /* ---------- Muat ---------- */
  function load(opts) {
    opts = opts || {};
    if (ctrl) { ctrl.abort(); }
    ctrl = new AbortController(); var mine = ++seq;
    var qs = queryString();
    history.replaceState(null, '', location.pathname + '?' + qs);
    var api = new URLSearchParams(qs); api.set('sort', state.sort); api.set('dir', state.dir);
    return App.api('api/alumni?' + api.toString(), { signal: ctrl.signal, headers: opts.background ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) { if (mine !== seq) { return; } data = res.data; render(); })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seq) { App.toast('error', e.message); } });
  }

  /* ---------- Pasang ---------- */
  var sorter = App.Table.sortHeaders($('#thead'), state, function () { state.page = 1; load(); });
  syncControls(); render();

  $('#pull-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var m = document.querySelector('input[name="mode"]:checked');
    state.mode = m ? m.value : 'detail';
    state.year = parseInt(fYear.value, 10) || 0; state.academic_year = fAy.value; state.q = fQ.value.trim(); state.page = 1;
    $('#a-q-field').hidden = state.mode === 'rekap';
    var btn = $('#a-submit'); App.setLoading(btn, true, 'Memuat...');
    load().then(function () { App.setLoading(btn, false); sorter.paint(); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('input[name="mode"]'), function (r) {
    r.addEventListener('change', function () { $('#a-q-field').hidden = r.value === 'rekap' && r.checked; });
  });

  App.watch(['savings', 'students'], function () { return load({ background: true }); }, data.rev);
})();
