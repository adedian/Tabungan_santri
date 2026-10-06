/* ==========================================================================
   App.SavingsActions — ubah & hapus transaksi (dipakai Riwayat dan Detail Santri)

     var actions = App.SavingsActions({ onChanged: function () { return reload(); } });
     actions.edit(tx)     // buka dialog #edit-dialog (partial partials/savings_edit_dialog)
     actions.remove(tx)   // konfirmasi lalu hapus

   tx = {id, code, student, date, period_month, jenjang, kelas, mutation, amount, description}
   Server menolak perubahan yang membuat saldo negatif; pesannya ditampilkan apa adanya.
   ========================================================================== */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function fmtDate(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }

  App.SavingsActions = function (o) {
    var onChanged = (o && o.onChanged) || function () {};
    var dlg = $('#edit-dialog'), form = $('#edit-form'), editing = null;

    function clearErrors() {
      if (!form) { return; }
      $$('.field.has-error', form).forEach(function (f) { f.classList.remove('has-error'); });
      $$('.field-error', form).forEach(function (s) { s.textContent = ''; });
      $$('[aria-invalid]', form).forEach(function (i) { i.removeAttribute('aria-invalid'); });
      $('#ed-error').hidden = true;
    }
    function showErrors(errors, message) {
      var first = null;
      Object.keys(errors || {}).forEach(function (k) {
        var field = $('[data-field="' + k + '"]', form), msg = $('#err-' + k, form); if (!field || !msg) { return; }
        field.classList.add('has-error'); msg.textContent = ''; msg.appendChild(App.icon('circle-alert')); msg.appendChild(document.createTextNode(errors[k]));
        var input = $('input,select', field); input.setAttribute('aria-invalid', 'true'); if (!first) { first = input; }
      });
      var dup = Object.keys(errors || {}).some(function (k) { return errors[k] === message; }); // jangan tampilkan pesan yang sama dua kali
      if (message && !dup) { var box = $('#ed-error'); $('div', box).textContent = message; box.hidden = false; }
      if (first) { first.focus(); }
    }

    function edit(t) {
      if (!dlg) { return; }
      editing = t; clearErrors();
      $('#ed-sub').textContent = t.student + ' • ' + t.code;
      $('#ed-date').value = t.date; $('#ed-month').value = String(t.period_month); $('#ed-jenjang').value = t.jenjang; $('#ed-kelas').value = t.kelas;
      $('#ed-mutation').value = t.mutation; $('#ed-mutation').dispatchEvent(new Event('change'));
      $('#ed-amount').value = App.formatMoney(t.amount); $('#ed-desc').value = t.description;
      dlg.showModal(); $('#ed-date').focus();
    }

    function submit(e) {
      e.preventDefault(); clearErrors();
      var btn = $('#ed-save');
      var body = {}; new FormData(form).forEach(function (v, k) { body[k] = v; });
      body.amount = App.parseMoney(body.amount); body.period_month = parseInt(body.period_month, 10);
      App.setLoading(btn, true, 'Menyimpan...');
      App.api('api/savings/' + editing.id, { method: 'PUT', data: body })
        .then(function (res) { dlg.close(); App.toast('success', res.message); return onChanged(); })
        .catch(function (err) { showErrors(err.status === 422 ? err.errors : {}, err.message); })
        .then(function () { App.setLoading(btn, false); });
    }

    function remove(t) {
      var masuk = t.mutation === 'masuk';
      App.confirm({
        title: 'Hapus transaksi?',
        message: 'Data transaksi akan dihapus dan saldo santri akan diperbarui.',
        details: [
          { label: 'Santri', value: t.student }, { label: 'Tanggal', value: fmtDate(t.date) },
          { label: 'Mutasi', value: masuk ? 'Masuk' : 'Keluar' }, { label: 'Nominal', value: App.rupiah(t.amount), total: true }
        ],
        confirmText: 'Hapus', tone: 'danger'
      }).then(function (yes) {
        if (!yes) { return; }
        return App.api('api/savings/' + t.id, { method: 'DELETE' })
          .then(function (res) { App.toast('success', res.message); return onChanged(); })
          .catch(function (e) { App.toast('error', e.message, { duration: 9000 }); });
      });
    }

    if (dlg) {
      form.addEventListener('submit', submit);
      $('#ed-cancel').addEventListener('click', function () { dlg.close(); });
    }
    return { edit: edit, remove: remove };
  };
})();
