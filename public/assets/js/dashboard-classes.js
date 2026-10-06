/* Dashboard — Saldo Tabungan Setiap Kelas (filter jenjang + periode hari/bulan/tahun).
   Data awal dari JSON dashboard (#dash-data → classes); "Tampilkan"/"Reset" memuat ulang lewat /api/dashboard/classes;
   dashboard.js memanggil App.ClassBalances.reload() tiap polling mendeteksi perubahan data (transaksi baru/hapus/kenaikan kelas).
   Angka: Saldo Awal + Masuk − Keluar ± Pindah kelas = Saldo Akhir. Semua teks lewat textContent. */
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
  try { raw = JSON.parse($('#dash-data').textContent); } catch (e) { return; }
  if (!raw.classes || !$('#cls-section')) { return; }

  var body = $('#cls-body'), form = $('#cls-filter');
  var ctl = { jenjang: $('#cf-jenjang'), period: $('#cf-period'), date: $('#cf-date'), month: $('#cf-month'), year: $('#cf-year') };
  var data = raw.classes, applied = null, ctrl = null, seq = 0;

  function today() { return data.today; } // tanggal server (zona waktu aplikasi), bukan jam perangkat
  function defaults() { var t = today().split('-'); return { jenjang: '', period: 'month', date: today(), month: parseInt(t[1], 10), year: parseInt(t[0], 10) }; }
  function signed(n) { return (n > 0 ? '+' : n < 0 ? '−' : '') + App.rupiah(Math.abs(n)); }

  /* ---------- Filter ---------- */
  function fillOptions() {
    var jn = data.options.jenjang, cur = ctl.jenjang.value;
    ctl.jenjang.textContent = ''; el('option', null, 'Semua', ctl.jenjang).value = '';
    jn.forEach(function (j) { el('option', null, j, ctl.jenjang).value = j; });
    ctl.jenjang.value = cur;
    var yc = ctl.year.value; ctl.year.textContent = '';
    data.options.years.forEach(function (y) { el('option', null, String(y), ctl.year).value = String(y); });
    if (yc) { ctl.year.value = yc; }
  }
  function setControls(f) {
    ctl.jenjang.value = f.jenjang; ctl.period.value = f.period; ctl.date.value = f.date; ctl.month.value = String(f.month);
    if (!Array.prototype.some.call(ctl.year.options, function (o) { return o.value === String(f.year); })) { el('option', null, String(f.year), ctl.year).value = String(f.year); }
    ctl.year.value = String(f.year);
    toggleFields();
  }
  function readControls() {
    return { jenjang: ctl.jenjang.value, period: ctl.period.value, date: ctl.date.value || today(), month: parseInt(ctl.month.value, 10), year: parseInt(ctl.year.value, 10) };
  }
  /* Hanya tampilkan field yang relevan: Hari → tanggal; Bulan → bulan + tahun; Tahun → tahun */
  function toggleFields() {
    $$('[data-for]', form).forEach(function (f) { f.hidden = f.getAttribute('data-for').split(' ').indexOf(ctl.period.value) === -1; });
  }
  function queryString(f) {
    var p = new URLSearchParams({ jenjang: f.jenjang, period: f.period });
    if (f.period === 'day') { p.set('date', f.date); }
    if (f.period === 'month') { p.set('month', f.month); }
    if (f.period !== 'day') { p.set('year', f.year); }
    return p.toString();
  }

  /* ---------- Render ---------- */
  var PERIOD_NOUN = { day: 'hari', month: 'bulan', year: 'tahun' };

  function money(parent, cls, n) { return el('span', cls, App.rupiah(n), parent); }

  function renderTotal(host, d, f) {
    var t = d.totals, card = el('div', 'cls-total', null, host);
    var main = el('div', 'cls-total-main', null, card);
    el('div', 'cls-total-label', (f.jenjang ? 'Total Saldo ' + f.jenjang : 'Total Saldo Seluruh Kelas') + ' • Saldo Akhir', main);
    el('div', 'cls-total-value', App.rupiah(t.akhir), main);
    el('div', 'cls-total-period', d.period.label, main);
    var eq = el('dl', 'cls-eq', null, card);
    function row(label, val, cls, sign) {
      var r = el('div', 'cls-eq-row ' + (cls || ''), null, eq);
      el('dt', null, label, r); el('dd', null, sign ? signed(val) : App.rupiah(val), r);
    }
    row('Saldo Awal Periode', t.awal);
    row('+ Mutasi Masuk', t.masuk, 'is-masuk');
    row('− Mutasi Keluar', t.keluar, 'is-keluar');
    if (t.pindah !== 0) { row('± Pindah kelas / lulus', t.pindah, 'is-pindah', true); }
  }

  function renderTile(parent, c, jenjang) {
    var zero = c.awal === 0 && c.masuk === 0 && c.keluar === 0 && c.akhir === 0 && c.pindah === 0;
    var tile = el('article', 'cls-tile' + (zero ? ' is-zero' : ''), null, parent);
    var head = el('div', 'cls-tile-head', null, tile);
    el('h4', 'cls-tile-name', c.label, head);
    if (c.n > 0) { el('span', 'cls-tile-n', c.n + ' trx', head); }
    el('div', 'cls-tile-cap', 'Saldo Akhir', tile);
    el('div', 'cls-tile-value', App.rupiah(c.akhir), tile);
    var flow = el('div', 'cls-flow', null, tile);
    var a = el('div', 'cls-flow-item is-masuk', null, flow); a.appendChild(App.icon('arrow-down-left')); el('span', 'cls-flow-label', 'Masuk', a); money(a, 'cls-flow-val', c.masuk);
    var b = el('div', 'cls-flow-item is-keluar', null, flow); b.appendChild(App.icon('arrow-up-right')); el('span', 'cls-flow-label', 'Keluar', b); money(b, 'cls-flow-val', c.keluar);
    var foot = el('div', 'cls-tile-foot', null, tile);
    el('span', null, 'Awal ' + App.rupiah(c.awal), foot);
    if (c.pindah !== 0) { el('span', 'is-pindah', 'Pindah kelas ' + signed(c.pindah), foot); }
    tile.setAttribute('aria-label', jenjang + ' ' + c.label + ': saldo akhir ' + App.rupiah(c.akhir) + ', masuk ' + App.rupiah(c.masuk) + ', keluar ' + App.rupiah(c.keluar));
  }

  function render() {
    var f = data.filters, host = body; host.textContent = '';
    $('#cls-sub').textContent = (f.jenjang ? 'Jenjang ' + f.jenjang : 'Semua jenjang') + ' • ' + data.period.label + ' • saldo akhir per ' + shortDate(data.period.to);
    renderTotal(host, data, f);
    if (!data.has_data) {
      var note = el('div', 'alert alert-info cls-empty', null, host);
      note.appendChild(App.icon('info'));
      var t = el('div', null, null, note);
      el('b', null, 'Belum ada data transaksi pada periode ini.', t);
      t.appendChild(document.createTextNode(' Saldo akhir sama dengan saldo awal (Rp 0 bila memang belum ada saldo).'));
    }
    data.groups.forEach(function (g) {
      var sec = el('section', 'cls-group', null, host);
      var head = el('div', 'cls-group-head', null, sec);
      var left = el('div', 'cls-group-title', null, head);
      el('span', 'badge badge-outline', g.jenjang, left);
      el('h3', null, g.jenjang === 'TK' ? 'Taman Kanak-kanak' : g.jenjang === 'SD' ? 'Sekolah Dasar' : g.jenjang, left);
      var sub = el('div', 'cls-group-sub', null, head);
      el('span', 'muted small', 'Saldo akhir ' + g.jenjang, sub); money(sub, 'cls-group-val', g.totals.akhir);
      var grid = el('div', 'cls-grid', null, sec);
      g.classes.forEach(function (c) { renderTile(grid, c, g.jenjang); });
    });
    if (!data.groups.length) {
      var e = el('div', 'empty', null, host);
      el('h3', null, 'Tidak ada kelas', e); el('p', null, 'Belum ada kelas pada jenjang ini.', e);
    }
  }
  function shortDate(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }

  function skeleton() {
    body.textContent = '';
    var t = el('div', 'cls-total is-skeleton', null, body); el('div', 'skeleton skeleton-line', null, t).style.width = '40%'; el('div', 'skeleton skeleton-line', null, t).style.width = '60%';
    var grid = el('div', 'cls-grid', null, el('section', 'cls-group', null, body));
    for (var i = 0; i < 6; i++) { var s = el('div', 'cls-tile is-skeleton', null, grid); el('div', 'skeleton skeleton-line', null, s).style.width = '50%'; el('div', 'skeleton skeleton-line', null, s).style.width = '75%'; el('div', 'skeleton skeleton-line', null, s).style.width = '90%'; }
    el('div', 'sr-only', 'Memuat data...', body);
  }

  /* ---------- Muat ---------- */
  function load(f, opts) {
    opts = opts || {};
    if (ctrl) { ctrl.abort(); }
    ctrl = new AbortController(); var mine = ++seq;
    body.setAttribute('aria-busy', 'true');
    if (!opts.background) { skeleton(); } else { body.classList.add('is-refreshing'); }
    var btn = $('#cf-apply'); if (!opts.background) { App.setLoading(btn, true, 'Memuat...'); }
    return App.api('api/dashboard/classes?' + queryString(f), { signal: ctrl.signal, headers: opts.background ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) {
        if (mine !== seq) { return; }
        data = res.data; applied = data.filters; fillOptions(); setControls(applied); render();
        var qs = isDefault(applied) ? '' : '?' + queryString(applied);
        history.replaceState(null, '', location.pathname + qs);
      })
      .catch(function (e) {
        if (e && e.name === 'AbortError') { return; }
        if (mine === seq) { App.toast('error', e.message); if (!opts.background && data) { render(); } }
      })
      .then(function () {
        if (mine === seq) { body.setAttribute('aria-busy', 'false'); body.classList.remove('is-refreshing'); App.setLoading(btn, false); }
      });
  }
  function isDefault(f) { var d = defaults(); return f.jenjang === '' && f.period === 'month' && f.month === d.month && f.year === d.year; }

  /* ---------- Pasang ---------- */
  fillOptions(); setControls(data.filters); applied = data.filters; render();

  ctl.period.addEventListener('change', toggleFields);
  form.addEventListener('submit', function (e) { e.preventDefault(); load(readControls()); });
  $('#cf-reset').addEventListener('click', function () { var d = defaults(); setControls(d); load(d); });

  App.ClassBalances = {
    /** Muat ulang dengan filter yang SEDANG DITERAPKAN (bukan isian yang belum ditekan "Tampilkan"). */
    reload: function (opts) { return load(applied || data.filters, opts || {}); }
  };
})();
