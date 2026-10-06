/* Pengguna (khusus Super Admin): daftar (cari/filter/sort/paginasi), tambah/ubah, aktif/nonaktif, atur ulang kata sandi.
   Data awal dari JSON di halaman. Server menegakkan semua aturan; JS hanya menyembunyikan aksi yang pasti ditolak. */
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
  try { raw = JSON.parse($('#users-data').textContent); } catch (e) { return; }
  var ROLES = raw.roles, ME = raw.me;
  var F = raw.filters;
  var state = { q: F.q, role: F.role, status: F.status, sort: F.sort, dir: F.dir, page: F.page, per_page: F.per_page };
  var data = raw, ctrl = null, seq = 0;
  var fq = $('#f-q'), fr = $('#f-role'), fs = $('#f-status');

  var ROLE_HINT = {
    super_admin: 'Akses penuh, termasuk pengguna & pengaturan.',
    admin: 'Ubah/hapus transaksi, kelola santri, ekspor laporan, audit log.',
    operator: 'Mencatat & melihat tabungan, santri, laporan (tanpa ekspor).'
  };

  function fmtLogin(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(String(s || ''));
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] : 'Belum pernah';
  }
  function syncControls() { fq.value = state.q; fr.value = state.role; fs.value = state.status; }
  function queryString() {
    var p = new URLSearchParams();
    if (state.q) { p.set('q', state.q); }
    if (state.role) { p.set('role', state.role); }
    if (state.status) { p.set('status', state.status); }
    if (state.sort !== 'name') { p.set('sort', state.sort); }
    if (state.dir !== 'asc') { p.set('dir', state.dir); }
    if (state.page > 1) { p.set('page', state.page); }
    if (state.per_page !== 25) { p.set('per_page', state.per_page); }
    return p.toString();
  }

  /* ---------- Render ---------- */
  var tbody = $('#tbody'), wrap = $('#table-wrap'), empty = $('#empty');

  function iconBtn(parent, icon, label, onClick) {
    var b = el('button', 'btn btn-ghost btn-icon btn-sm', null, parent); b.type = 'button';
    b.title = label; b.setAttribute('aria-label', label); b.appendChild(App.icon(icon));
    b.addEventListener('click', onClick);
    return b;
  }

  function renderRows() {
    tbody.textContent = '';
    var has = data.items.length > 0;
    wrap.hidden = !has; empty.hidden = has;
    data.items.forEach(function (u) {
      var self = u.id === ME, tr = el('tr', null, null, tbody);
      var nm = el('td', null, null, tr);
      var main = el('div', 'cell-main', u.name, nm);
      if (self) { main.appendChild(document.createTextNode(' ')); el('span', 'badge badge-gold', 'Anda', main); }
      if (u.email) { el('div', 'cell-sub', u.email, nm); }
      el('td', 'nowrap tabular', u.username, tr);
      el('td', null, null, tr).appendChild(el('span', 'badge badge-outline', ROLES[u.role] || u.role));
      el('td', 'nowrap tabular' + (u.last_login_at ? '' : ' muted'), fmtLogin(u.last_login_at), tr);
      el('td', null, null, tr).appendChild(el('span', 'badge ' + (u.status === 'aktif' ? 'badge-success' : 'badge-muted'), u.status === 'aktif' ? 'Aktif' : 'Nonaktif'));
      var ac = el('td', 'col-actions', null, tr);
      iconBtn(ac, 'pencil', 'Ubah ' + u.name, function () { openForm(u); });
      iconBtn(ac, 'lock', 'Atur ulang kata sandi ' + u.name, function () { openPassword(u); });
      if (!self) {
        var off = u.status === 'aktif';
        iconBtn(ac, off ? 'user-x' : 'user-check', (off ? 'Nonaktifkan ' : 'Aktifkan ') + u.name, function () { toggleStatus(u); });
      }
    });
    if (!has) {
      var filtered = state.q || state.role || state.status;
      $('#empty-title').textContent = filtered ? 'Tidak ada pengguna yang cocok' : 'Tidak ada pengguna';
    }
  }
  function renderPager() {
    App.Table.pager($('#pager'), {
      page: data.page, pages: data.pages, total: data.total, perPage: state.per_page, perPageOptions: [10, 25, 50], noun: 'pengguna',
      onPage: function (p) { state.page = p; load(); },
      onPerPage: function (n) { state.per_page = n; state.page = 1; load(); }
    });
  }
  function render() { renderRows(); renderPager(); }

  function load() {
    if (ctrl) { ctrl.abort(); }
    ctrl = new AbortController(); var mine = ++seq;
    wrap.classList.add('is-refreshing');
    var qs = queryString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    var api = new URLSearchParams(qs); api.set('sort', state.sort); api.set('dir', state.dir);
    return App.api('api/users?' + api.toString(), { signal: ctrl.signal })
      .then(function (res) { if (mine !== seq) { return; } data = res.data; state.page = data.page; render(); })
      .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seq) { App.toast('error', e.message); } })
      .then(function () { if (mine === seq) { wrap.classList.remove('is-refreshing'); } });
  }

  /* ---------- Util form ---------- */
  function clearErrors(form, prefix, box) {
    $$('.field.has-error', form).forEach(function (f) { f.classList.remove('has-error'); });
    $$('.field-error', form).forEach(function (s) { s.textContent = ''; });
    $$('[aria-invalid]', form).forEach(function (i) { i.removeAttribute('aria-invalid'); i.removeAttribute('aria-describedby'); });
    box.hidden = true;
  }
  function showErrors(form, prefix, box, errors, message) {
    var first = null;
    Object.keys(errors || {}).forEach(function (k) {
      var field = $('[data-field="' + k + '"]', form), msg = $('#' + prefix + k, form); if (!field || !msg) { return; }
      field.classList.add('has-error'); msg.textContent = ''; msg.appendChild(App.icon('circle-alert')); msg.appendChild(document.createTextNode(errors[k]));
      var input = $('input,select', field); input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', prefix + k);
      if (!first) { first = input; }
    });
    if (first) { first.focus(); } else if (message) { $('div', box).textContent = message; box.hidden = false; }
  }

  /* ---------- Tambah / ubah ---------- */
  var dlg = $('#user-dialog'), form = $('#user-form'), editing = null, errBox = $('#ud-error');

  function updateRoleHint() { $('#ud-role-hint').textContent = ROLE_HINT[$('#ud-role').value] || ''; }
  function openForm(u) {
    editing = u || null; clearErrors(form, 'err-', errBox); form.reset();
    $('#ud-title').textContent = u ? 'Ubah Pengguna' : 'Tambah Pengguna';
    $('.modal-icon', dlg).replaceChildren(App.icon(u ? 'pencil' : 'user-plus'));
    $('#ud-pw-field').hidden = !!u;
    if (u) {
      $('#ud-name').value = u.name; $('#ud-username').value = u.username; $('#ud-email').value = u.email || ''; $('#ud-role').value = u.role;
    }
    var self = !!u && u.id === ME, role = $('#ud-role');
    role.disabled = self; // peran akun sendiri tidak dapat diubah (ditegakkan juga di server)
    if (self) { $('#ud-role-hint').textContent = 'Peran akun Anda sendiri tidak dapat diubah.'; } else { updateRoleHint(); }
    dlg.showModal(); $('#ud-name').focus();
  }
  function submitForm(e) {
    e.preventDefault(); clearErrors(form, 'err-', errBox);
    var btn = $('#ud-save'), body = {};
    new FormData(form).forEach(function (v, k) { body[k] = v; });
    if (editing && editing.id === ME) { body.role = editing.role; } // <select disabled> tidak ikut FormData
    App.setLoading(btn, true, 'Menyimpan...');
    var req = editing ? App.api('api/users/' + editing.id, { method: 'PUT', data: body }) : App.api('api/users', { method: 'POST', data: body });
    req.then(function (res) {
      dlg.close(); App.toast('success', res.message);
      if (!editing) { state.q = ''; state.role = ''; state.status = ''; state.page = 1; syncControls(); }
      return load();
    }).catch(function (err) {
      showErrors(form, 'err-', errBox, err.status === 422 ? err.errors : {}, err.message);
    }).then(function () { App.setLoading(btn, false); });
  }

  /* ---------- Aktif / nonaktif ---------- */
  function toggleStatus(u) {
    var off = u.status === 'aktif', target = off ? 'nonaktif' : 'aktif';
    var go = function () {
      return App.api('api/users/' + u.id + '/status', { method: 'PUT', data: { status: target } })
        .then(function (res) { App.toast('success', res.message); return load(); })
        .catch(function (e) { App.toast('error', e.message); });
    };
    if (!off) { go(); return; }
    App.confirm({
      title: 'Nonaktifkan pengguna?',
      message: 'Pengguna tidak dapat login lagi dan sesi yang sedang berjalan berakhir pada permintaan berikutnya. Riwayat aktivitasnya tetap tersimpan.',
      details: [{ label: 'Pengguna', value: u.name }, { label: 'Username', value: u.username }, { label: 'Peran', value: ROLES[u.role] || u.role }],
      confirmText: 'Nonaktifkan', tone: 'danger'
    }).then(function (yes) { if (yes) { go(); } });
  }

  /* ---------- Atur ulang kata sandi ---------- */
  var pdlg = $('#pw-dialog'), pform = $('#pw-form'), pbox = $('#pw-error'), pwUser = null;
  function openPassword(u) {
    pwUser = u; clearErrors(pform, 'pw-err-', pbox); pform.reset();
    $('#pw-who').textContent = 'Pengguna: ' + u.name + ' (' + u.username + ')';
    pdlg.showModal(); $('#pw-password').focus();
  }
  function submitPassword(e) {
    e.preventDefault(); clearErrors(pform, 'pw-err-', pbox);
    var btn = $('#pw-save');
    App.setLoading(btn, true, 'Menyimpan...');
    App.api('api/users/' + pwUser.id + '/password', { method: 'PUT', data: { password: $('#pw-password').value } })
      .then(function (res) { pdlg.close(); pform.reset(); App.toast('success', res.message); })
      .catch(function (err) { showErrors(pform, 'pw-err-', pbox, err.status === 422 ? err.errors : {}, err.message); })
      .then(function () { App.setLoading(btn, false); });
  }

  /* ---------- Pasang ---------- */
  var sorter = App.Table.sortHeaders($('#thead'), state, function () { state.page = 1; load(); });
  syncControls(); render();

  fq.addEventListener('input', App.debounce(function () { state.q = fq.value.trim(); state.page = 1; load(); }, 260));
  $('#filters').addEventListener('submit', function (e) { e.preventDefault(); });
  fr.addEventListener('change', function () { state.role = fr.value; state.page = 1; load(); });
  fs.addEventListener('change', function () { state.status = fs.value; state.page = 1; load(); });
  $('#f-reset').addEventListener('click', function () {
    state.q = ''; state.role = ''; state.status = ''; state.sort = 'name'; state.dir = 'asc'; state.page = 1;
    syncControls(); sorter.paint(); load(); fq.focus();
  });

  $('#btn-add').addEventListener('click', function () { openForm(null); });
  $('#ud-cancel').addEventListener('click', function () { dlg.close(); });
  $('#ud-role').addEventListener('change', updateRoleHint);
  form.addEventListener('submit', submitForm);
  $('#pw-cancel').addEventListener('click', function () { pdlg.close(); pform.reset(); });
  pform.addEventListener('submit', submitPassword);
  pdlg.addEventListener('close', function () { pform.reset(); }); // jangan menyisakan kata sandi di DOM
})();
