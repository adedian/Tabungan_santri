/* Data Santri: daftar (cari/filter/sort/paginasi) + tambah/ubah/nonaktifkan. Data awal dari JSON di halaman. */
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
  try { raw = JSON.parse($('#students-data').textContent); } catch (e) { return; }
  var canManage = !!raw.can_manage;

  var state = {
    q: raw.filters.q, jenjang: raw.filters.jenjang, kelas: raw.filters.kelas, status: raw.filters.status,
    sort: raw.filters.sort, dir: raw.filters.dir, page: raw.filters.page, per_page: raw.filters.per_page
  };
  var data = raw, classes = raw.classes, lastIds = null, ctrl = null, seq = 0, bulk = null;

  /* ---------- Filter ---------- */
  var fq = $('#f-q'), fj = $('#f-jenjang'), fk = $('#f-kelas'), fs = $('#f-status');

  function kelasFor(j) {
    if (j) { return classes[j].used; }
    return Array.from(new Set(classes.TK.used.concat(classes.SD.used)));
  }
  function fillKelasFilter() {
    var opts = kelasFor(state.jenjang);
    fk.textContent = '';
    var all = el('option', null, 'Semua', fk); all.value = '';
    opts.forEach(function (k) { var o = el('option', null, k, fk); o.value = k; });
    if (state.kelas && opts.indexOf(state.kelas) === -1) { state.kelas = ''; }
    fk.value = state.kelas;
  }
  function syncControls() { fq.value = state.q; fj.value = state.jenjang; fs.value = state.status; fillKelasFilter(); }

  function queryString() {
    var p = new URLSearchParams();
    if (state.q) { p.set('q', state.q); }
    if (state.jenjang) { p.set('jenjang', state.jenjang); }
    if (state.kelas) { p.set('kelas', state.kelas); }
    if (state.status !== 'aktif') { p.set('status', state.status); }
    if (state.sort !== 'name') { p.set('sort', state.sort); }
    if (state.dir !== 'asc') { p.set('dir', state.dir); }
    if (state.page > 1) { p.set('page', state.page); }
    if (state.per_page !== 25) { p.set('per_page', state.per_page); }
    return p.toString();
  }

  /* ---------- Render ---------- */
  var tbody = $('#tbody'), wrap = $('#table-wrap'), empty = $('#empty');
  var cols = canManage ? 8 : 7;

  function renderRows() {
    tbody.textContent = '';
    var has = data.items.length > 0;
    wrap.hidden = !has; empty.hidden = has;
    if (!has) {
      var filtered = state.q || state.jenjang || state.kelas || state.status !== 'aktif';
      $('#empty-title').textContent = filtered ? 'Tidak ada santri yang cocok' : 'Belum ada santri';
      $('#empty-text').textContent = filtered ? 'Coba ubah kata kunci atau filter pencarian.' : 'Belum terdapat data santri. Tambahkan santri pertama.';
      var ea = $('#empty-add'); if (ea) { ea.hidden = !!filtered; }
    }
    var ids = data.items.map(function (s) { return s.id; });
    data.items.forEach(function (s) {
      var tr = el('tr', lastIds && lastIds.indexOf(s.id) === -1 ? 'is-new' : '', null, tbody);
      el('td', 'tabular nowrap', s.code, tr);
      var nm = el('td', null, null, tr);
      el('div', 'cell-main', null, nm).appendChild(App.studentLink(s.id, s.name));
      if (s.nis) { el('div', 'cell-sub', 'NIS ' + s.nis, nm); }
      var kc = el('td', 'nowrap', null, tr);
      el('span', 'badge badge-outline', s.jenjang, kc); kc.appendChild(document.createTextNode(' ')); el('span', 'cell-main', s.kelas, kc);
      el('td', 'num tabular', s.no_urut == null ? '–' : String(s.no_urut), tr);
      el('td', s.dawis_blok ? null : 'muted', s.dawis_blok || '–', tr);
      el('td', 'num amt', App.rupiah(s.saldo), tr);
      var st = el('td', null, null, tr);
      el('span', 'badge ' + (s.status === 'aktif' ? 'badge-success' : 'badge-muted'), s.status === 'aktif' ? 'Aktif' : 'Nonaktif', st);
      if (canManage) {
        var ac = el('td', 'col-actions', null, tr);
        var eb = el('button', 'btn btn-ghost btn-icon btn-sm', null, ac); eb.type = 'button';
        eb.setAttribute('aria-label', 'Ubah ' + s.name); eb.title = 'Ubah'; eb.appendChild(App.icon('pencil'));
        eb.dataset.short = 'Ubah'; eb.addEventListener('click', function () { openForm(s); });
        var tb = el('button', 'btn btn-ghost btn-icon btn-sm', null, ac); tb.type = 'button';
        var off = s.status === 'aktif';
        tb.setAttribute('aria-label', (off ? 'Nonaktifkan ' : 'Aktifkan ') + s.name); tb.title = off ? 'Nonaktifkan' : 'Aktifkan';
        tb.dataset.short = off ? 'Nonaktifkan' : 'Aktifkan'; tb.appendChild(App.icon(off ? 'user-x' : 'user-check'));
        tb.addEventListener('click', function () { toggleStatus(s); });
      }
    });
    lastIds = ids;
    if (bulk) { bulk.afterRender(); }
  }

  function renderPager() {
    App.Table.pager($('#pager'), {
      page: data.page, pages: data.pages, total: data.total, perPage: state.per_page, perPageOptions: [10, 25, 50], noun: 'santri',
      onPage: function (p) { state.page = p; load(); },
      onPerPage: function (n) { state.per_page = n; state.page = 1; load(); }
    });
  }

  /** Cetak massal hanya untuk satu jenjang/kelas (mencegah tercetaknya seluruh santri tanpa sengaja). */
  function updatePrintLink() {
    var b = $('#btn-print-class'); if (!b) { return; }
    var show = !!(state.jenjang || state.kelas); b.hidden = !show;
    if (show) {
      var p = new URLSearchParams(); if (state.jenjang) { p.set('jenjang', state.jenjang); } if (state.kelas) { p.set('kelas', state.kelas); }
      if (state.status === 'semua' || state.status === 'nonaktif') { p.set('inactive', '1'); }
      b.href = App.url('print/rekap?' + p.toString());
    }
  }
  function render() { classes = data.classes; fillKelasFilter(); renderRows(); renderPager(); updatePrintLink(); }

  /* ---------- Muat data ---------- */
  function load(opts) {
    opts = opts || {};
    if (ctrl) { ctrl.abort(); }
    ctrl = new AbortController();
    var mine = ++seq;
    wrap.classList.add('is-refreshing');
    var qs = queryString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    var apiQs = new URLSearchParams(qs); apiQs.set('sort', state.sort); apiQs.set('dir', state.dir);
    return App.api('api/students?' + apiQs.toString(), { signal: ctrl.signal, headers: opts.background ? { 'X-Background-Poll': '1' } : {} })
      .then(function (res) {
        if (mine !== seq) { return; }
        data = res.data; state.page = data.page; render();
      })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seq) { App.toast('error', e.message); } })
      .then(function () { if (mine === seq) { wrap.classList.remove('is-refreshing'); } });
  }

  /* ---------- Form tambah/ubah ---------- */
  var dlg = $('#student-dialog'), form = $('#student-form'), editing = null;

  function fillKelasList() {
    var list = $('#sd-kelas-list'), j = $('#sd-jenjang').value; list.textContent = '';
    if (j && classes[j]) { classes[j].all.forEach(function (k) { var o = el('option', null, null, list); o.value = k; }); }
  }
  function clearErrors() {
    $$('.field.has-error', form).forEach(function (f) { f.classList.remove('has-error'); });
    $$('.field-error', form).forEach(function (s) { s.textContent = ''; });
    $$('[aria-invalid]', form).forEach(function (i) { i.removeAttribute('aria-invalid'); i.removeAttribute('aria-describedby'); });
    $('#sd-error').hidden = true;
  }
  function showErrors(errors, message) {
    var first = null;
    Object.keys(errors || {}).forEach(function (k) {
      var field = $('[data-field="' + k + '"]', form), msg = $('#err-' + k, form); if (!field || !msg) { return; }
      field.classList.add('has-error'); msg.textContent = ''; msg.appendChild(App.icon('circle-alert')); msg.appendChild(document.createTextNode(errors[k]));
      var input = $('input,select', field); input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', 'err-' + k);
      if (!first) { first = input; }
    });
    if (first) { first.focus(); } else if (message) { var box = $('#sd-error'); $('div', box).textContent = message; box.hidden = false; }
  }

  function openForm(s) {
    if (!dlg) { return; }
    editing = s || null; clearErrors(); form.reset();
    $('#sd-title').textContent = s ? 'Ubah Data Santri' : 'Tambah Santri';
    $('.modal-icon', dlg).replaceChildren(App.icon(s ? 'pencil' : 'user-plus'));
    $('#sd-status-field').hidden = !s;
    var code = $('#sd-code'); code.readOnly = !!s;
    $('#sd-code-hint').textContent = s ? 'ID santri tidak dapat diubah.' : 'Kosongkan untuk dibuat otomatis.';
    if (s) {
      $('#sd-name').value = s.name; $('#sd-jenjang').value = s.jenjang; $('#sd-kelas').value = s.kelas; code.value = s.code;
      $('#sd-nis').value = s.nis || ''; $('#sd-urut').value = s.no_urut == null ? '' : s.no_urut; $('#sd-dawis').value = s.dawis_blok || ''; $('#sd-status').value = s.status;
    } else if (state.jenjang) { $('#sd-jenjang').value = state.jenjang; if (state.kelas) { $('#sd-kelas').value = state.kelas; } }
    fillKelasList();
    dlg.showModal();
    $('#sd-name').focus();
  }

  function submitForm(e) {
    e.preventDefault(); clearErrors();
    var btn = $('#sd-save');
    var body = {}; new FormData(form).forEach(function (v, k) { body[k] = v; });
    App.setLoading(btn, true, 'Menyimpan...');
    var req = editing ? App.api('api/students/' + editing.id, { method: 'PUT', data: body }) : App.api('api/students', { method: 'POST', data: body });
    req.then(function (res) {
      dlg.close(); App.toast('success', res.message);
      if (!editing) { state.q = ''; state.jenjang = ''; state.kelas = ''; state.status = 'aktif'; state.sort = 'name'; state.dir = 'asc'; state.page = 1; syncControls(); sorter.paint(); }
      return load();
    }).catch(function (err) {
      if (err.status === 422) { showErrors(err.errors, err.message); } else { showErrors({}, err.message); }
    }).then(function () { App.setLoading(btn, false); });
  }

  function toggleStatus(s) {
    var off = s.status === 'aktif', target = off ? 'nonaktif' : 'aktif';
    var go = function () {
      return App.api('api/students/' + s.id + '/status', { method: 'PUT', data: { status: target } })
        .then(function (res) { App.toast('success', res.message); return load(); })
        .catch(function (e) { App.toast('error', e.message); });
    };
    if (!off) { go(); return; }
    var details = [{ label: 'Santri', value: s.name }, { label: 'Kelas', value: s.jenjang + ' ' + s.kelas }, { label: 'Saldo saat ini', value: App.rupiah(s.saldo), total: true }];
    App.confirm({
      title: 'Nonaktifkan santri?',
      message: 'Santri nonaktif tidak muncul pada transaksi baru. Riwayat dan saldo tetap tersimpan' + (s.saldo > 0 ? ', dan saldo yang tersisa tidak hilang.' : '.'),
      details: details, confirmText: 'Nonaktifkan', tone: 'danger'
    }).then(function (yes) { if (yes) { go(); } });
  }

  /* ---------- Hapus massal = ARSIP: data, riwayat kelas & transaksi tetap aman di database ---------- */
  if (canManage && $('#bulk-bar')) {
    bulk = App.Bulk({
      host: $('#bulk-bar'), table: $('#table-wrap table'), noun: 'santri',
      items: function () { return data.items; },
      idOf: function (s) { return s.id; },
      labelOf: function (s) { return s.name; },
      title: 'Hapus Data?',
      message: function (p) {
        return 'Anda memilih ' + p.length + ' santri. Santri akan diarsipkan (tidak tampil lagi); data, riwayat kelas, dan transaksi tabungannya tetap tersimpan. ' +
          'Santri yang masih memiliki saldo tidak akan dihapus. Apakah Anda yakin?';
      },
      details: function (p) {
        var withSaldo = p.filter(function (s) { return s.saldo !== 0; }).length;
        var rows = [{ label: 'Santri dipilih', value: String(p.length) }];
        if (withSaldo) { rows.push({ label: 'Masih bersaldo (akan dilewati)', value: String(withSaldo), total: true }); }
        return rows;
      },
      remove: function (ids) { return App.api('api/students/bulk-delete', { method: 'POST', data: { ids: ids } }); },
      onDone: function (res) {
        var sk = (res.data && res.data.skipped) || [];
        App.toast(sk.length ? 'warning' : 'success', res.message, { duration: sk.length ? 12000 : 4500 });
        sk.slice(0, 5).forEach(function (x) { App.toast('warning', x.name + ': ' + x.reason, { duration: 12000 }); });
        return load();
      }
    });
  }

  /* ---------- Pasang ---------- */
  var sorter = App.Table.sortHeaders($('#thead'), state, function () { state.page = 1; load(); });
  syncControls(); render();

  var debounced = App.debounce(function () { state.q = fq.value.trim(); state.page = 1; load(); }, 260);
  fq.addEventListener('input', debounced);
  $('#filters').addEventListener('submit', function (e) { e.preventDefault(); });
  fj.addEventListener('change', function () { state.jenjang = fj.value; state.kelas = ''; state.page = 1; fillKelasFilter(); load(); });
  fk.addEventListener('change', function () { state.kelas = fk.value; state.page = 1; load(); });
  fs.addEventListener('change', function () { state.status = fs.value; state.page = 1; load(); });
  $('#f-reset').addEventListener('click', function () {
    state.q = ''; state.jenjang = ''; state.kelas = ''; state.status = 'aktif'; state.sort = 'name'; state.dir = 'asc'; state.page = 1;
    syncControls(); sorter.paint(); load(); fq.focus();
  });

  if (canManage && dlg) {
    $('#btn-add').addEventListener('click', function () { openForm(null); });
    var ea = $('#empty-add'); if (ea) { ea.addEventListener('click', function () { openForm(null); }); }
    $('#sd-cancel').addEventListener('click', function () { dlg.close(); });
    $('#sd-jenjang').addEventListener('change', fillKelasList);
    form.addEventListener('submit', submitForm);
    $('#sd-urut').addEventListener('input', function (e) { e.target.value = e.target.value.replace(/\D+/g, ''); });
  }

  App.watch(['students', 'savings'], function () { return load({ background: true }); }, data.rev);
})();
