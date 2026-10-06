/* Kenaikan Kelas: pilih kelas → tinjau santri → tentukan Naik / Tidak Naik → konfirmasi (pratinjau) → proses.
   Tidak ada yang berubah sebelum konfirmasi akhir. Server memvalidasi ulang semuanya dan memproses dalam satu transaksi DB. */
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
  function fmtDateTime(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(s));
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : String(s);
  }

  var raw;
  try { raw = JSON.parse($('#promo-data').textContent); } catch (e) { return; }

  var fYear = $('#p-year'), fTo = $('#p-toyear'), fJ = $('#p-jenjang'), fK = $('#p-kelas'), fTarget = $('#p-target');
  var card = $('#review-card'), tbody = $('#tbody'), wrap = $('#table-wrap'), empty = $('#empty');
  var info = null;            // hasil /api/promotions/candidates
  var decisions = {};         // id -> 'naik' | 'tidak_naik'
  var picked = {};            // id -> true (kotak centang untuk tandai massal)
  var seq = 0;

  /* ---------- Pilihan awal ---------- */
  raw.years.forEach(function (y) { var o = el('option', null, y.value, fYear); o.value = y.value; o.dataset.to = y.to; });
  fYear.value = raw.default_year;

  function toYear() { var o = fYear.options[fYear.selectedIndex]; return o ? o.dataset.to : ''; }
  function fillKelas() {
    fK.textContent = ''; el('option', null, 'Pilih kelas', fK).value = '';
    var list = fJ.value ? (raw.classes[fJ.value] || []) : [];
    list.forEach(function (k) { el('option', null, k, fK).value = k; });
    fK.disabled = !fJ.value;
  }

  /* ---------- Muat daftar santri ---------- */
  function resetReview() { info = null; decisions = {}; picked = {}; card.hidden = true; fTarget.value = ''; fTarget.disabled = true; $('#p-target-hint').textContent = 'Terisi otomatis menurut tangga kelas setelah kelas asal dipilih.'; }

  function load() {
    fTo.value = toYear();
    if (!fJ.value || !fK.value) { resetReview(); return; }
    var mine = ++seq;
    var qs = new URLSearchParams({ from_year: fYear.value, jenjang: fJ.value, kelas: fK.value });
    App.api('api/promotions/candidates?' + qs.toString()).then(function (res) {
      if (mine !== seq) { return; }
      info = res.data; decisions = {}; picked = {};
      render();
    }).catch(function (e) {
      if (mine !== seq) { return; }
      resetReview(); App.toast('error', e.message, { duration: 9000 });
    });
  }

  /* ---------- Render ---------- */
  function isGrad() { return info && info.target.type === 'lulus'; }

  function targetText() { return isGrad() ? 'Lulus' : info.target.jenjang + ' ' + (fTarget.value.trim().toUpperCase() || info.target.kelas); }

  function render() {
    card.hidden = false;
    var grad = isGrad();
    fTarget.disabled = grad || !!info.processed;
    fTarget.value = grad ? 'Lulus — menjadi alumni' : info.target.kelas;
    $('#p-target-hint').textContent = grad
      ? 'Kelas 6 SD: santri yang Naik dinyatakan lulus dan menjadi alumni (tidak ada kelas 7).'
      : (info.target.jenjang !== info.jenjang ? 'Naik ke jenjang ' + info.target.jenjang + '. Rombel dapat diubah (mis. 1A / 1B).' : 'Dapat diubah bila rombel tujuan berbeda (mis. 2A atau 2B), tingkat kelas harus sesuai.');
    $('#review-sub').textContent = info.jenjang + ' ' + info.kelas + ' → ' + (grad ? 'Lulus' : info.target.jenjang + ' ' + info.target.kelas) + ' • TA ' + info.from_year + ' → ' + info.to_year;
    $('#grad-note').hidden = !grad;

    var done = $('#done-note');
    if (info.processed) {
      var p = info.processed;
      done.hidden = false;
      $('div', done).textContent = 'Kenaikan kelas untuk tahun ajaran ini sudah diproses oleh ' + p.by + ' pada ' + fmtDateTime(p.at) + ' ('
        + (p.to ? p.naik + ' naik' : p.lulus + ' lulus') + ', ' + p.tinggal + ' tidak naik). Tidak dapat diproses ulang.';
    } else { done.hidden = true; }

    var has = info.items.length > 0 && !info.processed;
    $('#review-body .bulk-bar').hidden = !has; wrap.hidden = !has; $('#promo-foot').hidden = !has; empty.hidden = has || !!info.processed;
    if (!has && !info.processed) {
      $('#empty-title').textContent = 'Tidak ada santri yang perlu diproses';
      $('#empty-text').textContent = 'Semua santri aktif di kelas ini sudah diproses pada tahun ajaran ini, atau belum ada santri aktif.'
        + (info.inactive ? ' (' + info.inactive + ' santri nonaktif tidak ikut proses.)' : '');
    }
    renderRows(); refreshState();
  }

  function renderRows() {
    tbody.textContent = '';
    var grad = isGrad();
    info.items.forEach(function (s) {
      var tr = el('tr', null, null, tbody); tr.dataset.id = s.id;
      var c = el('td', 'col-check td-check', null, tr); c.setAttribute('data-label', '');
      var cb = el('input', 'bulk-check', null, c); cb.type = 'checkbox'; cb.checked = !!picked[s.id]; cb.setAttribute('aria-label', 'Pilih ' + s.name);
      cb.addEventListener('change', function () { if (cb.checked) { picked[s.id] = true; } else { delete picked[s.id]; } refreshState(); });
      var nm = el('td', null, null, tr); el('div', 'cell-main', s.name, nm);
      if (s.nis) { el('div', 'cell-sub', 'NIS ' + s.nis, nm); }
      var kc = el('td', 'nowrap', null, tr); el('span', 'badge badge-outline', info.jenjang, kc); kc.appendChild(document.createTextNode(' ')); el('span', 'cell-main', info.kelas, kc);
      el('td', 'num amt', App.rupiah(s.saldo), tr);
      var st = el('td', null, null, tr); var box = el('div', 'promo-status', null, st);
      el('span', 'badge badge-muted', 'Belum Diproses', box).dataset.state = '1';
      var seg = el('div', 'segmented', null, box); seg.setAttribute('role', 'group'); seg.setAttribute('aria-label', 'Status ' + s.name);
      [['naik', grad ? 'Naik (Lulus)' : 'Naik'], ['tidak_naik', 'Tidak Naik']].forEach(function (o) {
        var b = el('button', null, o[1], seg); b.type = 'button'; b.dataset.v = o[0]; b.setAttribute('aria-pressed', 'false');
        b.addEventListener('click', function () { decisions[s.id] = o[0]; refreshState(); });
      });
    });
  }

  function refreshState() {
    if (!info) { return; }
    var n = { naik: 0, tidak_naik: 0, none: 0 }, pickedN = 0, grad = isGrad();
    info.items.forEach(function (s) {
      var d = decisions[s.id]; n[d || 'none']++; if (picked[s.id]) { pickedN++; }
    });
    Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr) {
      var id = tr.dataset.id, d = decisions[id], cb = tr.querySelector('input.bulk-check');
      cb.checked = !!picked[id]; tr.classList.toggle('is-picked', !!picked[id]);
      var badge = tr.querySelector('[data-state]');
      badge.textContent = d === 'naik' ? (grad ? 'Naik → Lulus' : 'Naik') : d === 'tidak_naik' ? 'Tidak Naik' : 'Belum Diproses';
      badge.className = 'badge ' + (d === 'naik' ? 'badge-success' : d === 'tidak_naik' ? 'badge-gold' : 'badge-muted');
      Array.prototype.forEach.call(tr.querySelectorAll('.segmented button'), function (b) { b.setAttribute('aria-pressed', String(b.dataset.v === d)); });
    });
    var all = $('#r-all'), total = info.items.length;
    all.checked = total > 0 && pickedN === total; all.indeterminate = pickedN > 0 && pickedN < total;
    $('#r-count').textContent = pickedN ? pickedN + ' santri dipilih' : '';
    $('#r-mark-naik').disabled = !pickedN; $('#r-mark-stay').disabled = !pickedN;
    $('#t-naik').textContent = (grad ? 'Lulus ' : 'Naik ') + n.naik; $('#t-stay').textContent = 'Tidak naik ' + n.tidak_naik; $('#t-none').textContent = 'Belum diproses ' + n.none;
    var btn = $('#r-process'); btn.disabled = !(total > 0 && n.none === 0 && !info.processed);
    btn.title = n.none ? 'Tentukan status ' + n.none + ' santri lagi' : '';
  }

  function mark(value) {
    Object.keys(picked).forEach(function (id) { decisions[id] = value; });
    picked = {}; refreshState();
  }

  $('#r-all').addEventListener('change', function (e) {
    picked = {}; if (e.target.checked) { info.items.forEach(function (s) { picked[s.id] = true; }); }
    refreshState();
  });
  $('#r-mark-naik').addEventListener('click', function () { mark('naik'); });
  $('#r-mark-stay').addEventListener('click', function () { mark('tidak_naik'); });

  /* ---------- Pratinjau + konfirmasi ---------- */
  var dlg = $('#promo-dialog'), form = $('#promo-form');
  function openPreview() {
    var grad = isGrad(), naik = [], stay = [];
    info.items.forEach(function (s) { (decisions[s.id] === 'naik' ? naik : stay).push(s.name); });
    var tujuan = targetText();
    $('#pd-lead').textContent = 'Periksa kembali sebelum diproses. ' + info.jenjang + ' ' + info.kelas + ' → ' + (grad ? 'Lulus (menjadi alumni)' : tujuan) + ', TA ' + info.from_year + ' → ' + info.to_year + '.';
    var sum = $('#pd-summary'); sum.textContent = '';
    function row(label, value, total) {
      if (total) { var r = el('div', 'is-total', null, sum); el('dt', null, label, r); el('dd', null, value, r); }
      else { el('dt', null, label, sum); el('dd', null, value, sum); }
    }
    row(grad ? 'Lulus (menjadi alumni)' : 'Naik kelas', naik.length + ' santri');
    row('Tidak naik', stay.length + ' santri');
    row('Total diproses', info.items.length + ' santri', true);

    var names = $('#pd-names'); names.textContent = '';
    [[grad ? 'Lulus' : 'Naik ke ' + tujuan, naik], ['Tetap di ' + info.jenjang + ' ' + info.kelas, stay]].forEach(function (g) {
      if (!g[1].length) { return; }
      var box = el('div', 'promo-names-group', null, names); el('b', null, g[0] + ' (' + g[1].length + ')', box);
      el('p', 'small muted', g[1].join(', '), box);
    });
    $('#pd-error').hidden = true;
    dlg.showModal(); $('#pd-back').focus();
  }

  $('#r-process').addEventListener('click', function () {
    if (!info || $('#r-process').disabled) { return; }
    if (!isGrad()) {
      var t = fTarget.value.trim();
      if (t === '') { fTarget.focus(); App.toast('error', 'Kelas tujuan wajib diisi.'); return; }
    }
    openPreview();
  });
  $('#pd-back').addEventListener('click', function () { dlg.close(); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('#pd-ok'), err = $('#pd-error');
    err.hidden = true;
    App.setLoading(btn, true, 'Memproses...');
    App.api('api/promotions', { method: 'POST', data: {
      from_year: info.from_year, jenjang: info.jenjang, kelas: info.kelas, target_kelas: isGrad() ? '' : fTarget.value.trim(), decisions: decisions
    } }).then(function (res) {
      dlg.close(); App.toast('success', res.message, { duration: 9000 });
      return reloadAfter();
    }).catch(function (e2) {
      $('div', err).textContent = e2.message; err.hidden = false;
      if (e2.status === 409) { reloadAfter(); }
    }).then(function () { App.setLoading(btn, false); });
  });

  /* ---------- Daftar kelas & riwayat proses (diperbarui setelah proses) ---------- */
  function renderRecent(list) {
    var body = $('#recent-body'); body.textContent = '';
    $('#recent-empty').hidden = list.length > 0; $('#recent-table').closest('.table-wrap').hidden = list.length === 0;
    list.forEach(function (p) {
      var tr = el('tr', null, null, body);
      el('td', 'nowrap', 'TA ' + p.from_year + ' → ' + p.to_year, tr);
      el('td', 'nowrap', p.from + ' → ' + (p.to || 'Lulus'), tr);
      el('td', 'num tabular', String(p.naik), tr); el('td', 'num tabular', String(p.lulus), tr); el('td', 'num tabular', String(p.tinggal), tr);
      var by = el('td', null, null, tr); el('div', 'cell-main', p.by, by); el('div', 'cell-sub', fmtDateTime(p.at), by);
    });
  }
  function reloadAfter() {
    return window.fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var m = /<script type="application\/json" id="promo-data">([\s\S]*?)<\/script>/.exec(html);
        if (m) { raw = JSON.parse(m[1]); renderRecent(raw.recent); }
        // kelas yang sudah selesai diproses tidak lagi punya santri aktif yang tertinggal: segarkan pilihan
        var cur = fK.value; fillKelas(); if ((raw.classes[fJ.value] || []).indexOf(cur) !== -1) { fK.value = cur; }
        load();
      });
  }

  /* ---------- Pasang ---------- */
  fYear.addEventListener('change', load);
  fJ.addEventListener('change', function () { fillKelas(); resetReview(); fTo.value = toYear(); });
  fK.addEventListener('change', load);
  $('#pick-form').addEventListener('submit', function (e) { e.preventDefault(); });
  fTo.value = toYear(); fillKelas(); renderRecent(raw.recent);
})();
