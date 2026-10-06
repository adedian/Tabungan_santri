/* Halaman cetak: tombol Cetak, cetak otomatis (?autoprint=1), terapkan opsi otomatis, dan pencatatan audit. */
(function () {
  'use strict';

  var body = document.body, ref = body.getAttribute('data-log-ref') || '';
  var logged = false;

  function meta(n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; }

  // Catat ke audit log sekali per pemuatan halaman (gagal mencatat tidak menghalangi pencetakan)
  function log() {
    if (logged || !ref) { return; }
    logged = true;
    try {
      fetch(meta('base-url').replace(/\/$/, '') + '/api/print/log', {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': meta('csrf-token'), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ ref: ref })
      }).catch(function () { /* diam */ });
    } catch (e) { /* diam */ }
  }

  window.addEventListener('beforeprint', log);

  // Tombol Kembali: ke halaman asal bila datang dari halaman lain di aplikasi ini, selain itu ke tautan induk
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-back]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    try {
      var r = document.referrer ? new URL(document.referrer) : null;
      if (r && r.origin === location.origin && r.pathname !== location.pathname && history.length > 1) { e.preventDefault(); history.back(); }
    } catch (err) { /* ikuti tautan */ }
  });

  var btn = document.getElementById('btn-print');
  if (btn) { btn.addEventListener('click', function () { window.print(); }); }

  // Ubah opsi -> terapkan otomatis
  var form = document.getElementById('print-options');
  if (form) {
    Array.prototype.forEach.call(form.querySelectorAll('input[type="date"], select, input[type="checkbox"]'), function (el) {
      el.addEventListener('change', function () { form.submit(); });
    });
  }

  if (body.getAttribute('data-autoprint') === '1') { window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); }); }
})();
