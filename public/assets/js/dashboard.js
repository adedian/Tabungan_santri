/* Dashboard: render dari data awal (JSON di halaman) lalu perbarui otomatis lewat polling revisi. */
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

  var SERIES = [{ key: 'masuk', name: 'Masuk', cls: 's1' }, { key: 'keluar', name: 'Keluar', cls: 's2' }];
  var RANGE_TEXT = { day: '14 hari terakhir', week: '12 minggu terakhir', month: '12 bulan terakhir' };
  var COLUMN = { day: 'Hari', week: 'Minggu', month: 'Bulan' };

  var raw;
  try { raw = JSON.parse($('#dash-data').textContent); } catch (e) { return; }

  var state = { range: 'week', view: 'chart', summary: raw.summary, cache: { week: raw.activity }, seq: 0 };
  var chart;

  /* ---------- Angka ringkasan ---------- */
  function setMoney(node, n) {
    node.textContent = '';
    if (n < 0) { node.appendChild(document.createTextNode('−')); }
    el('span', 'cur', 'Rp', node);
    node.appendChild(document.createTextNode(' ' + App.formatMoney(Math.abs(n))));
  }
  function setStat(key, value, apply) {
    var node = $('[data-stat="' + key + '"]'); if (!node) { return; }
    var changed = node.dataset.v !== undefined && node.dataset.v !== String(value);
    apply(node, value); node.dataset.v = String(value);
    if (changed) {
      var card = node.closest('.stat'); card.classList.remove('is-updated'); void card.offsetWidth; card.classList.add('is-updated');
    }
  }
  function renderStats(s) {
    var t = s.totals;
    setStat('saldo', t.saldo, setMoney);
    setStat('masuk', t.masuk, setMoney);
    setStat('keluar', t.keluar, setMoney);
    setStat('santri', s.santri, function (n, v) { n.textContent = App.formatMoney(v); });
    var note = $('[data-note="saldo"]'); if (note) { note.textContent = App.formatMoney(s.santri) + ' santri aktif'; }
    var tx = $('[data-note="transaksi"]'); if (tx) { tx.textContent = App.formatMoney(t.transaksi); }
    var j = {}; s.jenjang.forEach(function (x) { j[x.jenjang] = x.santri; });
    var jn = $('[data-note="jenjang"]'); if (jn) { jn.textContent = 'TK ' + (j.TK || 0) + ' • SD ' + (j.SD || 0); }
  }

  /* ---------- Saldo per jenjang ---------- */
  function renderJenjang(s) {
    var host = $('#jenjang-list'); host.textContent = '';
    var total = Math.max(0, s.totals.saldo);
    s.jenjang.forEach(function (j) {
      var row = el('div', 'jenjang-row', null, host);
      var meta = el('div', 'meta', null, row);
      var left = el('div', 'cluster', null, meta);
      el('span', 'badge badge-outline', j.jenjang, left);
      el('span', 'muted small', App.formatMoney(j.santri) + ' santri', left);
      el('span', 'amount', App.rupiah(j.saldo), meta);
      var meter = el('div', 'meter', null, row);
      meter.setAttribute('role', 'img');
      var pct = total > 0 ? Math.max(0, Math.min(100, Math.round(j.saldo / total * 100))) : 0;
      meter.setAttribute('aria-label', j.jenjang + ': ' + pct + '% dari total saldo');
      var fill = el('span', null, null, meter); fill.style.width = pct + '%';
      el('div', 'cell-sub', pct + '% dari total saldo', row).style.marginTop = '6px';
    });
  }

  /* ---------- Transaksi terbaru ---------- */
  function fmtDate(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }
  function renderRecent(s, prevMax) {
    var body = $('#recent-body'); body.textContent = '';
    var has = s.recent.length > 0;
    $('#recent-wrap').hidden = !has; $('#recent-empty').hidden = has;
    s.recent.forEach(function (r) {
      var tr = el('tr', prevMax != null && r.id > prevMax ? 'is-new' : '', null, body);
      el('td', 'nowrap tabular', fmtDate(r.date), tr);
      el('td', null, null, tr).appendChild(el('div', 'cell-main', null)).appendChild(App.studentLink(r.student_id, r.student));
      var kc = el('td', null, null, tr); el('span', 'badge badge-outline', r.jenjang, kc); kc.appendChild(document.createTextNode(' ')); el('span', 'cell-sub', r.kelas, kc);
      var mt = el('td', null, null, tr), masuk = r.mutation === 'masuk';
      var b = el('span', 'badge ' + (masuk ? 'badge-masuk' : 'badge-keluar'), null, mt);
      b.appendChild(App.icon(masuk ? 'arrow-down-left' : 'arrow-up-right')); b.appendChild(document.createTextNode(masuk ? 'Masuk' : 'Keluar'));
      el('td', 'num', null, tr).appendChild(el('span', 'amt ' + (masuk ? 'is-masuk' : 'is-keluar'), (masuk ? '+' : '−') + App.rupiah(r.amount)));
      el('td', 'cell-sub', r.description, tr);
    });
  }

  /* ---------- Grafik aktivitas ---------- */
  function renderActivity(a) {
    chart.update(a.buckets);
    var t = $('#chart-table'); t.textContent = '';
    t.appendChild(App.Chart.table(a.buckets, SERIES, COLUMN[a.range], App.rupiah));
    $('#activity-sub').textContent = RANGE_TEXT[a.range];
    SERIES.forEach(function (s) { $('[data-legend-val="' + s.key + '"]').textContent = App.rupiah(a.totals[s.key]); });
  }
  function loadActivity(range) {
    var frame = $('#chart-frame'), seq = ++state.seq;
    frame.classList.add('is-refreshing');
    return App.api('api/dashboard/activity?range=' + range, { headers: { 'X-Background-Poll': '1' } }).then(function (res) {
      state.cache[range] = res.data;
      if (seq === state.seq && range === state.range) { renderActivity(res.data); }
    }).catch(function (e) {
      if (seq === state.seq) { App.toast('error', e.message); }
    }).then(function () { if (seq === state.seq) { frame.classList.remove('is-refreshing'); } });
  }

  function setRange(range) {
    state.range = range;
    $$('#range-switch button').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.range === range)); });
    if (state.cache[range]) { state.seq++; $('#chart-frame').classList.remove('is-refreshing'); renderActivity(state.cache[range]); }
    else { loadActivity(range); }
  }

  function setView(view) {
    state.view = view;
    var table = view === 'table';
    $('#chart').hidden = table; $('#chart-table').hidden = !table; $('#chart-legend').hidden = table;
    var btn = $('#view-toggle'); btn.setAttribute('aria-pressed', String(table));
    btn.replaceChildren(App.icon(table ? 'bar-chart' : 'table'), el('span', null, table ? 'Grafik' : 'Tabel'));
    if (!table) { renderActivity(state.cache[state.range]); } // ukuran bisa berubah saat tersembunyi
  }

  /* ---------- Muat ulang saat data berubah (dipicu polling) ---------- */
  function refresh() {
    var prevMax = state.summary.recent.length ? Math.max.apply(null, state.summary.recent.map(function (r) { return r.id; })) : 0;
    state.cache = {}; // periode lain dimuat ulang saat dipilih
    return Promise.all([
      App.api('api/dashboard/summary', { headers: { 'X-Background-Poll': '1' } }).then(function (res) {
        state.summary = res.data; renderStats(res.data); renderJenjang(res.data); renderRecent(res.data, prevMax);
      }),
      loadActivity(state.range)
    ]);
  }

  /* ---------- Mulai ---------- */
  chart = App.Chart.columns($('#chart'), { series: SERIES, format: App.rupiah, height: 300, label: 'Aktivitas tabungan', emptyText: 'Belum ada aktivitas tabungan pada periode ini.' });
  App.Chart.legend($('#chart-legend'), SERIES);
  renderStats(state.summary); renderJenjang(state.summary); renderRecent(state.summary, null);
  renderActivity(state.cache.week);

  $$('#range-switch button').forEach(function (b) { b.addEventListener('click', function () { if (b.dataset.range !== state.range) { setRange(b.dataset.range); } }); });
  $('#view-toggle').addEventListener('click', function () { setView(state.view === 'chart' ? 'table' : 'chart'); });

  App.watch(['savings', 'students'], refresh, state.summary.rev);
})();
