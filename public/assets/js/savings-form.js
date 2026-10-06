/* Form Tambah Tabungan: pilih santri (combobox), isi otomatis jenjang/kelas, pratinjau saldo,
   konfirmasi untuk transaksi keluar, simpan via API, panel "Baru dicatat" yang diperbarui otomatis. */
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
  try { raw = JSON.parse($('#savings-data').textContent); } catch (e) { return; }

  var form = $('#savings-form'), student = null, balance = null, submitting = false, lastRecentIds = null;
  var f = {
    date: $('#f-date'), studentInput: $('#f-student'), studentId: $('#f-student-id'), jenjang: $('#f-jenjang'), kelas: $('#f-kelas'),
    month: $('#f-month'), mutation: $('#f-mutation'), amount: $('#f-amount'), desc: $('#f-desc')
  };
  var MONTH_NOW = raw.month, TODAY = raw.today;

  /* ---------- Util ---------- */
  function fmtDate(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }
  function initials(name) { return name.trim().split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '?'; }
  function amount() { return App.parseMoney(f.amount.value); }
  function isKeluar() { return f.mutation.value === 'keluar'; }

  /* ---------- Error inline ---------- */
  function clearErrors() {
    $$('.field.has-error', form).forEach(function (x) { x.classList.remove('has-error'); });
    $$('.field-error', form).forEach(function (s) { s.textContent = ''; });
    $$('[aria-invalid]', form).forEach(function (i) { i.removeAttribute('aria-invalid'); });
    $('#form-error').hidden = true;
  }
  function showErrors(errors, message) {
    var first = null;
    Object.keys(errors || {}).forEach(function (k) {
      var field = $('[data-field="' + k + '"]', form), msg = $('#err-' + k, form); if (!field || !msg) { return; }
      field.classList.add('has-error'); msg.textContent = ''; msg.appendChild(App.icon('circle-alert')); msg.appendChild(document.createTextNode(errors[k]));
      var input = $('input:not([type=hidden]),select', field); if (input) { input.setAttribute('aria-invalid', 'true'); if (!first) { first = input; } }
    });
    if (first) { first.focus(); }
    else if (message) { var box = $('#form-error'); $('div', box).textContent = message; box.hidden = false; }
  }
  function clearFieldError(key) {
    var field = $('[data-field="' + key + '"]', form); if (!field) { return; }
    field.classList.remove('has-error'); var m = $('#err-' + key, form); if (m) { m.textContent = ''; }
    var i = $('input:not([type=hidden]),select', field); if (i) { i.removeAttribute('aria-invalid'); }
  }

  /* ---------- Kelas & jenjang ---------- */
  function fillKelasList() {
    var list = $('#f-kelas-list'), j = f.jenjang.value; list.textContent = '';
    if (j && raw.classes[j]) { raw.classes[j].all.forEach(function (k) { el('option', null, null, list).value = k; }); }
  }
  function updateClassHint() {
    var hint = $('#class-hint');
    var differs = student && (f.jenjang.value !== student.jenjang || f.kelas.value.trim().toUpperCase() !== student.kelas);
    hint.hidden = !differs;
    if (differs) { hint.textContent = 'Berbeda dari data santri (' + student.jenjang + ' ' + student.kelas + '). Transaksi disimpan dengan jenjang/kelas ini.'; }
  }

  /* ---------- Santri terpilih ---------- */
  function showChip() {
    var chip = $('#student-chip');
    chip.hidden = !student;
    if (!student) { return; }
    $('#chip-avatar').textContent = initials(student.name);
    $('#chip-name').textContent = student.name;
    $('#chip-meta').textContent = student.jenjang + ' • Kelas ' + student.kelas + ' • ID ' + student.code;
    $('#chip-saldo').textContent = balance == null ? '…' : App.rupiah(balance);
  }
  function selectStudent(s, focusAmount) {
    student = s; balance = s.saldo;
    f.studentId.value = s.id; f.jenjang.value = s.jenjang; f.kelas.value = s.kelas;
    clearFieldError('student_id'); clearFieldError('jenjang'); clearFieldError('kelas');
    fillKelasList(); showChip(); updateClassHint(); updatePreview();
    if (focusAmount !== false) { f.amount.focus(); }
  }
  function unselectStudent() {
    student = null; balance = null; f.studentId.value = '';
    showChip(); updateClassHint(); updatePreview();
  }

  var combo = App.Combobox(f.studentInput, {
    fetch: function (q, signal) {
      return App.api('api/students/search?limit=8&q=' + encodeURIComponent(q), { signal: signal }).then(function (r) { return r.data.items; });
    },
    render: function (s) { return { title: s.name, sub: s.jenjang + ' • Kelas ' + s.kelas + ' • ID ' + s.code, right: App.rupiah(s.saldo) }; },
    display: function (s) { return s.name; },
    emptyText: 'Tidak ada santri aktif yang cocok.',
    onSelect: function (s) { selectStudent(s); },
    onClear: unselectStudent
  });

  /* ---------- Pratinjau saldo ---------- */
  function updatePreview() {
    var wrap = $('#preview-wrap'), n = amount();
    if (!student || balance == null || n <= 0) { wrap.hidden = true; return; }
    var after = balance + (isKeluar() ? -n : n), neg = after < 0, box = $('#preview');
    wrap.hidden = false; box.classList.toggle('is-negative', neg);
    $('#preview-label').textContent = neg ? 'Saldo tidak mencukupi — saldo akan menjadi' : 'Saldo setelah transaksi';
    $('#preview-value').textContent = App.rupiah(after);
  }

  /* ---------- Validasi sisi klien (server tetap memvalidasi ulang) ---------- */
  function validate() {
    var e = {};
    if (!f.studentId.value) { e.student_id = 'Nama santri wajib dipilih.'; }
    if (!f.date.value) { e.transaction_date = 'Tanggal wajib diisi.'; } else if (f.date.value > TODAY) { e.transaction_date = 'Tanggal tidak boleh melewati hari ini.'; }
    if (!f.jenjang.value) { e.jenjang = 'Jenjang wajib dipilih.'; }
    if (!f.kelas.value.trim()) { e.kelas = 'Kelas wajib diisi.'; }
    if (!f.month.value) { e.period_month = 'Bulan wajib dipilih.'; }
    if (!f.mutation.value) { e.mutation_type = 'Mutasi wajib dipilih.'; }
    if (amount() < 1) { e.amount = 'Nominal harus lebih dari 0.'; }
    if (!f.desc.value.trim()) { e.description = (isKeluar() ? 'Keterangan keluar' : 'Keterangan masuk') + ' wajib diisi.'; }
    if (!e.amount && isKeluar() && balance != null && amount() > balance) { e.amount = 'Saldo santri tidak mencukupi.'; }
    return e;
  }

  /* ---------- Simpan ---------- */
  function payload() {
    return {
      student_id: parseInt(f.studentId.value, 10), transaction_date: f.date.value, jenjang: f.jenjang.value, kelas: f.kelas.value.trim(),
      period_month: parseInt(f.month.value, 10), mutation_type: f.mutation.value, amount: amount(), description: f.desc.value.trim()
    };
  }

  function resetAfterSave() {
    // Tanggal, bulan, dan jenis mutasi dipertahankan agar input beruntun lebih cepat.
    combo.clear(); unselectStudent(); f.studentInput.value = '';
    f.kelas.value = ''; f.jenjang.value = ''; f.amount.value = ''; f.desc.value = '';
    clearErrors(); fillKelasList(); updatePreview(); f.studentInput.focus();
  }
  function resetAll() {
    form.reset(); f.date.value = TODAY; f.month.value = String(MONTH_NOW);
    combo.clear(); unselectStudent(); f.studentInput.value = '';
    f.mutation.dispatchEvent(new Event('change')); clearErrors(); fillKelasList(); updatePreview(); f.studentInput.focus();
  }

  function send() {
    var btn = $('#btn-save'); submitting = true;
    App.setLoading(btn, true, 'Menyimpan...');
    return App.api('api/savings/create', { method: 'POST', data: payload() })
      .then(function (res) {
        App.toast('success', res.message);
        resetAfterSave();
        return loadRecent();
      })
      .catch(function (err) {
        if (err.status === 422) {
          showErrors(err.errors, err.message);
          if (err.errors && err.errors.amount) { App.toast('error', err.message); }
          if (err.errors && (err.errors.student_id || err.errors.amount)) { refreshBalance(); }
        } else { App.toast('error', err.message); }
      })
      .then(function () { App.setLoading(btn, false); submitting = false; });
  }

  function onSubmit(e) {
    e.preventDefault();
    if (submitting) { return; }
    clearErrors();
    var errs = validate();
    if (Object.keys(errs).length) {
      showErrors(errs);
      if (errs.amount && errs.amount.indexOf('tidak mencukupi') !== -1) { App.toast('error', errs.amount); } // pesan inline bisa di luar layar di ponsel
      return;
    }
    if (!isKeluar()) { send(); return; }

    // Transaksi keluar: ambil saldo terbaru lalu minta konfirmasi
    submitting = true; var btn = $('#btn-save'); App.setLoading(btn, true, 'Memeriksa saldo...');
    var restore = function () { App.setLoading(btn, false); submitting = false; };
    App.api('api/savings/balance?student_id=' + encodeURIComponent(f.studentId.value)).then(function (r) {
      balance = r.data.saldo; showChip(); updatePreview();
      var n = amount();
      if (n > balance) { restore(); showErrors({ amount: 'Saldo santri tidak mencukupi.' }); App.toast('error', 'Saldo santri tidak mencukupi.'); return; }
      restore();
      return App.confirm({
        title: 'Konfirmasi Transaksi', message: 'Periksa kembali data berikut sebelum menyimpan.', confirmText: 'Simpan Transaksi',
        details: [
          { label: 'Nama', value: student.name }, { label: 'Mutasi', value: 'Keluar' }, { label: 'Nominal', value: App.rupiah(n) },
          { label: 'Keterangan', value: f.desc.value.trim() }, { label: 'Saldo setelah transaksi', value: App.rupiah(balance - n), total: true }
        ]
      }).then(function (yes) { if (yes) { return send(); } });
    }).catch(function (err) { restore(); App.toast('error', err.message); });
  }

  /* ---------- Saldo & panel "Baru dicatat" ---------- */
  function refreshBalance() {
    if (!student) { return Promise.resolve(); }
    return App.api('api/savings/balance?student_id=' + student.id, { headers: { 'X-Background-Poll': '1' } })
      .then(function (r) { balance = r.data.saldo; showChip(); updatePreview(); }).catch(function () { /* diam: dicoba lagi saat polling berikutnya */ });
  }

  var fresh = {}; // id baru -> batas waktu sorotan (bertahan walau daftar dirender ulang oleh polling)
  function renderRecent(items) {
    var ul = $('#recent-list'); ul.textContent = '';
    $('#recent-empty').hidden = items.length > 0; ul.hidden = items.length === 0;
    var now = Date.now();
    items.forEach(function (t) { if (lastRecentIds && lastRecentIds.indexOf(t.id) === -1) { fresh[t.id] = now + 2500; } });
    items.forEach(function (t) {
      var masuk = t.mutation === 'masuk';
      var li = el('li', fresh[t.id] && fresh[t.id] > now ? 'is-new' : '', null, ul);
      var left = el('div', null, null, li);
      el('div', 'who', t.student, left);
      el('div', 'when', fmtDate(t.date) + ' • ' + String(t.created_at).slice(11, 16) + ' • ' + t.jenjang + ' ' + t.kelas, left);
      var right = el('div', 'amt', null, li);
      el('span', 'amt ' + (masuk ? 'is-masuk' : 'is-keluar'), (masuk ? '+' : '−') + App.rupiah(t.amount), right);
      var b = el('div', null, null, right); b.style.marginTop = '2px';
      var badge = el('span', 'badge ' + (masuk ? 'badge-masuk' : 'badge-keluar'), null, b);
      badge.appendChild(App.icon(masuk ? 'arrow-down-left' : 'arrow-up-right')); badge.appendChild(document.createTextNode(masuk ? 'Masuk' : 'Keluar'));
    });
    lastRecentIds = items.map(function (t) { return t.id; });
  }
  function loadRecent() {
    return App.api('api/savings/recent?limit=6', { headers: { 'X-Background-Poll': '1' } })
      .then(function (r) { renderRecent(r.data.items); }).catch(function () { /* polling berikutnya mencoba lagi */ });
  }

  /* ---------- Pasang ---------- */
  fillKelasList(); renderRecent(raw.recent);
  if (raw.student) { combo.setValue(raw.student); selectStudent(raw.student); }

  form.addEventListener('submit', onSubmit);
  $('#btn-reset').addEventListener('click', resetAll);
  f.jenjang.addEventListener('change', function () { fillKelasList(); clearFieldError('jenjang'); updateClassHint(); });
  f.kelas.addEventListener('input', function () { clearFieldError('kelas'); updateClassHint(); });
  f.amount.addEventListener('input', function () { clearFieldError('amount'); updatePreview(); });
  f.mutation.addEventListener('change', function () { clearFieldError('amount'); clearFieldError('description'); updatePreview(); });
  f.date.addEventListener('input', function () { clearFieldError('transaction_date'); });
  f.desc.addEventListener('input', function () { clearFieldError('description'); });
  $$('#quick-amounts .chip').forEach(function (c) {
    c.addEventListener('click', function () { f.amount.value = App.formatMoney(c.dataset.amount); clearFieldError('amount'); updatePreview(); f.amount.focus(); });
  });

  App.watch(['savings', 'students'], function () { return Promise.all([loadRecent(), refreshBalance()]); }, raw.rev);
})();
