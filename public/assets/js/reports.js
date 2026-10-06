/* Laporan Tabungan: filter periode & lainnya, ringkasan, grafik aktivitas, rekap per kelas / per santri, tautan ekspor.
   Data awal dari JSON di halaman; diperbarui otomatis lewat polling revisi. */
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
  try { raw = JSON.parse($('#report-data').textContent); } catch (e) { return; }
  var TODAY = raw.today, SERIES = [{ key: 'masuk', name: 'Masuk', cls: 's1' }, { key: 'keluar', name: 'Keluar', cls: 's2' }];
  var GRAN = { day: 'Per hari', week: 'Per minggu', month: 'Per bulan' };

  var F = raw.filters, urlView = new URLSearchParams(location.search).get('view');
  var state = {
    from: F.from, to: F.to, jenjang: F.jenjang, kelas: F.kelas, month: F.month, year: F.year, mutation: F.mutation, student_id: F.student_id,
    tab: urlView === 'santri' ? 'santri' : 'kelas',
    sort: raw.students.sort, dir: raw.students.dir, page: raw.students.page, per_page: raw.students.per_page
  };
  var summaryData = raw, students = raw.students, options = raw.options, ctrlS = null, ctrlT = null, seqS = 0, seqT = 0, chart;
  var ctl = { from: $('#f-from'), to: $('#f-to'), jenjang: $('#f-jenjang'), kelas: $('#f-kelas'), month: $('#f-month'), year: $('#f-year'), mutation: $('#f-mutation') };

  /* ---------- Util ---------- */
  function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function today() { var p = TODAY.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function fmtDate(isoStr) { var p = String(isoStr).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : isoStr; }
  function money(n) { return App.rupiah(n); }
  function cellMoney(tr, n, cls) { return el('td', 'num ' + (cls || ''), money(n), tr); }

  /* ---------- Filter ---------- */
  function filterParams() {
    var p = new URLSearchParams();
    ['from', 'to', 'jenjang', 'kelas', 'mutation'].forEach(function (k) { if (state[k]) { p.set(k, state[k]); } });
    ['month', 'year', 'student_id'].forEach(function (k) { if (state[k]) { p.set(k, state[k]); } });
    return p;
  }
  function studentParams() {
    var p = filterParams(); p.set('sort', state.sort); p.set('dir', state.dir); p.set('page', state.page); p.set('per_page', state.per_page); return p;
  }
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
  function activeExtra() { return [state.jenjang, state.kelas, state.month, state.year, state.mutation, state.student_id].filter(Boolean).length; }
  function updateCount() { var n = activeExtra(), b = $('#f-count'); b.hidden = n === 0; b.textContent = String(n); }
  function syncControls() {
    ctl.from.value = state.from; ctl.to.value = state.to; ctl.jenjang.value = state.jenjang; ctl.month.value = state.month ? String(state.month) : ''; ctl.mutation.value = state.mutation;
    fillKelas(); fillYears(); updateCount();
  }

  var combo = App.Combobox($('#f-student'), {
    fetch: function (q, signal) { return App.api('api/students/search?status=semua&limit=8&q=' + encodeURIComponent(q), { signal: signal }).then(function (r) { return r.data.items; }); },
    render: function (s) { return { title: s.name, sub: s.jenjang + ' • Kelas ' + s.kelas + ' • ID ' + s.code + (s.status === 'nonaktif' ? ' • nonaktif' : ''), right: money(s.saldo) }; },
    display: function (s) { return s.name; },
    emptyText: 'Tidak ada santri yang cocok.',
    onSelect: function (s) { state.student_id = s.id; changed(); },
    onClear: function () { state.student_id = 0; changed(); }
  });
  if (raw.student) { combo.setValue(raw.student); }

  /* ---------- Render: ringkasan ---------- */
  function renderSummary() {
    var s = summaryData.summary;
    $('#s-masuk').textContent = money(s.masuk); $('#s-keluar').textContent = money(s.keluar);
    var net = $('#s-net'); net.textContent = money(s.net); net.className = 'stat-value ' + (s.net < 0 ? 'is-keluar' : '');
    $('#s-count').textContent = App.formatMoney(s.count);
    $('#s-students').textContent = App.formatMoney(s.students) + ' santri bertransaksi';
    var p = $('#period'); p.textContent = '';
    p.appendChild(document.createTextNode('Periode: ')); el('b', null, summaryData.period, p);
    p.appendChild(document.createTextNode(' • ' + summaryData.filter_label));
    $('#rekap-sub').textContent = summaryData.period;
  }

  /* ---------- Render: grafik ---------- */
  var viewMode = 'chart';
  function renderActivity() {
    var a = summaryData.activity;
    chart.update(a.buckets);
    var t = $('#chart-table'); t.textContent = ''; t.appendChild(App.Chart.table(a.buckets, SERIES, 'Periode', App.rupiah));
    $('#act-sub').textContent = a.buckets.length ? GRAN[a.granularity] + ' • ' + a.buckets.length + ' periode' : 'Tidak ada data';
    SERIES.forEach(function (s) { $('[data-legend-val="' + s.key + '"]').textContent = money(a.totals[s.key]); });
  }
  function setView(mode) {
    viewMode = mode; var table = mode === 'table';
    $('#chart').hidden = table; $('#chart-table').hidden = !table; $('#chart-legend').hidden = table;
    var b = $('#view-toggle'); b.setAttribute('aria-pressed', String(table));
    b.replaceChildren(App.icon(table ? 'bar-chart' : 'table'), el('span', null, table ? 'Grafik' : 'Tabel'));
    if (!table) { renderActivity(); }
  }

  /* ---------- Render: rekap per kelas ---------- */
  function renderClass() {
    var rows = summaryData.by_class, s = summaryData.summary, body = $('#class-body'), foot = $('#class-foot');
    body.textContent = ''; foot.textContent = '';
    $('#class-wrap').hidden = rows.length === 0; $('#class-empty').hidden = rows.length > 0;
    rows.forEach(function (r) {
      var tr = el('tr', null, null, body);
      el('td', null, null, tr).appendChild(el('span', 'badge badge-outline', r.jenjang));
      el('td', 'cell-main', r.kelas, tr);
      el('td', 'num tabular', App.formatMoney(r.students), tr); el('td', 'num tabular', App.formatMoney(r.count), tr);
      cellMoney(tr, r.masuk, 'amt is-masuk'); cellMoney(tr, r.keluar, 'amt is-keluar'); cellMoney(tr, r.net, 'amt' + (r.net < 0 ? ' is-keluar' : ''));
    });
    if (rows.length) {
      var f = el('tr', null, null, foot);
      el('td', null, 'Total', f).colSpan = 2;
      el('td', 'num', App.formatMoney(s.students), f); el('td', 'num', App.formatMoney(s.count), f);
      cellMoney(f, s.masuk); cellMoney(f, s.keluar); cellMoney(f, s.net);
    }
  }

  /* ---------- Render: rekap per santri ---------- */
  function renderStudents() {
    var body = $('#student-body'), foot = $('#student-foot'), s = summaryData.summary;
    body.textContent = ''; foot.textContent = '';
    var has = students.items.length > 0;
    $('#student-wrap').hidden = !has; $('#student-empty').hidden = has;
    var lbl = $('#th-saldo .th-sort span'); if (lbl) { lbl.textContent = students.as_of ? 'Saldo per ' + fmtDate(students.as_of) : 'Saldo saat ini'; }
    students.items.forEach(function (r) {
      var tr = el('tr', null, null, body);
      el('td', null, null, tr).appendChild(App.studentLink(r.id, r.name));
      var kc = el('td', 'nowrap', null, tr); el('span', 'badge badge-outline', r.jenjang, kc); kc.appendChild(document.createTextNode(' ' + r.kelas));
      el('td', 'num tabular', App.formatMoney(r.count), tr);
      cellMoney(tr, r.masuk, 'amt is-masuk'); cellMoney(tr, r.keluar, 'amt is-keluar'); cellMoney(tr, r.net, 'amt' + (r.net < 0 ? ' is-keluar' : '')); cellMoney(tr, r.saldo, 'amt');
    });
    if (has) {
      var f = el('tr', null, null, foot);
      el('td', null, 'Total (' + App.formatMoney(students.total) + ' santri)', f).colSpan = 2;
      el('td', 'num', App.formatMoney(s.count), f); cellMoney(f, s.masuk); cellMoney(f, s.keluar); cellMoney(f, s.net); cellMoney(f, students.total_saldo);
    }
    App.Table.pager($('#pager'), {
      page: students.page, pages: students.pages, total: students.total, perPage: students.per_page, perPageOptions: [10, 25, 50], noun: 'santri',
      onPage: function (p) { state.page = p; loadStudents(); }, onPerPage: function (n) { state.per_page = n; state.page = 1; loadStudents(); }
    });
  }

  function renderAll() { options = summaryData.options; fillKelas(); fillYears(); updateCount(); renderSummary(); if (viewMode === 'chart') { renderActivity(); } else { renderActivity(); } renderClass(); renderStudents(); updateLinks(); }

  /* ---------- Tautan ekspor & rincian ---------- */
  function updateLinks() {
    var fp = filterParams().toString();
    $$('[data-export]').forEach(function (a) {
      var p = a.getAttribute('data-export').split(':');
      a.href = App.url('laporan/export?dataset=' + p[0] + '&format=' + p[1] + (fp ? '&' + fp : ''));
    });
    var d = $('#link-detail'); if (d) { d.href = App.url('tabungan' + (fp ? '?' + fp : '')); }
    var pr = $('#link-print'); if (pr) { pr.href = App.url('print/laporan' + (fp ? '?' + fp : '')); }
  }
  function syncUrl() {
    var p = state.tab === 'santri' ? studentParams() : filterParams();
    if (state.tab === 'santri') { p.set('view', 'santri'); if (state.sort === 'name' && state.dir === 'asc') { p.delete('sort'); p.delete('dir'); } if (state.page === 1) { p.delete('page'); } if (state.per_page === 25) { p.delete('per_page'); } }
    var qs = p.toString(); history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
  }

  /* ---------- Muat data ---------- */
  var chartFrame = $('#chart-frame'), studentWrap = $('#student-wrap');
  function loadSummary(bg) {
    if (ctrlS) { ctrlS.abort(); } ctrlS = new AbortController(); var mine = ++seqS;
    chartFrame.classList.add('is-refreshing');
    return App.api('api/reports/summary?' + filterParams().toString(), { signal: ctrlS.signal, headers: bg ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) { if (mine !== seqS) { return; } summaryData = res.data; options = summaryData.options; fillKelas(); fillYears(); updateCount(); renderSummary(); renderActivity(); renderClass(); updateLinks(); })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seqS) { App.toast('error', e.message); } })
      .then(function () { if (mine === seqS) { chartFrame.classList.remove('is-refreshing'); } });
  }
  function loadStudents(bg) {
    if (ctrlT) { ctrlT.abort(); } ctrlT = new AbortController(); var mine = ++seqT;
    studentWrap.classList.add('is-refreshing'); syncUrl();
    return App.api('api/reports/students?' + studentParams().toString(), { signal: ctrlT.signal, headers: bg ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) { if (mine !== seqT) { return; } students = res.data; state.page = students.page; renderStudents(); })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seqT) { App.toast('error', e.message); } })
      .then(function () { if (mine === seqT) { studentWrap.classList.remove('is-refreshing'); } });
  }
  function changed() { state.page = 1; updateCount(); updateLinks(); return Promise.all([loadSummary(), loadStudents()]); }

  /* ---------- Pasang ---------- */
  chart = App.Chart.columns($('#chart'), { series: SERIES, format: App.rupiah, height: 300, label: 'Aktivitas tabungan pada periode laporan', emptyText: 'Tidak ada aktivitas pada periode dan filter ini.' });
  App.Chart.legend($('#chart-legend'), SERIES);
  var sorter = App.Table.sortHeaders($('#thead'), state, function () { state.page = 1; loadStudents(); });

  function setTab(tab) {
    state.tab = tab;
    $$('#tab-switch button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.tab === tab)); });
    $('#pane-kelas').hidden = tab !== 'kelas'; $('#pane-santri').hidden = tab !== 'santri';
    $('#rekap-title').textContent = tab === 'kelas' ? 'Rekap per Kelas' : 'Rekap per Santri';
    syncUrl();
  }

  syncControls(); renderAll(); setTab(state.tab);

  function bind(c, key, num) { c.addEventListener('change', function () { state[key] = num ? (parseInt(c.value, 10) || 0) : c.value; changed(); }); }
  bind(ctl.from, 'from'); bind(ctl.to, 'to'); bind(ctl.month, 'month', true); bind(ctl.year, 'year', true); bind(ctl.kelas, 'kelas'); bind(ctl.mutation, 'mutation');
  ctl.jenjang.addEventListener('change', function () { state.jenjang = ctl.jenjang.value; state.kelas = ''; fillKelas(); changed(); });
  $('#filters').addEventListener('submit', function (e) { e.preventDefault(); });

  $$('.chip[data-range]').forEach(function (c) {
    c.addEventListener('click', function () {
      var t = today(), from = '', to = '';
      switch (c.dataset.range) {
        case 'month': from = iso(new Date(t.getFullYear(), t.getMonth(), 1)); to = iso(t); break;
        case 'lastmonth': from = iso(new Date(t.getFullYear(), t.getMonth() - 1, 1)); to = iso(new Date(t.getFullYear(), t.getMonth(), 0)); break;
        case 'year': from = iso(new Date(t.getFullYear(), 0, 1)); to = iso(t); break;
        default: break; // semua periode
      }
      state.from = from; state.to = to; ctl.from.value = from; ctl.to.value = to; changed();
    });
  });
  $$('#tab-switch button').forEach(function (b) { b.addEventListener('click', function () { setTab(b.dataset.tab); if (b.dataset.tab === 'santri') { loadStudents(); } }); });
  $('#view-toggle').addEventListener('click', function () { setView(viewMode === 'chart' ? 'table' : 'chart'); });

  var toolbar = $('#filters'), toggle = $('#f-toggle');
  toggle.addEventListener('click', function () { var open = !toolbar.classList.contains('is-open'); toolbar.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', String(open)); });
  if (activeExtra() > 0) { toolbar.classList.add('is-open'); toggle.setAttribute('aria-expanded', 'true'); }

  $('#f-reset').addEventListener('click', function () {
    state.from = ''; state.to = ''; state.jenjang = ''; state.kelas = ''; state.month = 0; state.year = 0; state.mutation = ''; state.student_id = 0;
    state.sort = 'name'; state.dir = 'asc'; combo.clear(); $('#f-student').value = ''; syncControls(); sorter.paint(); changed();
  });

  App.watch(['savings', 'students'], function () { return Promise.all([loadSummary(true), loadStudents(true)]); }, raw.rev);
})();
