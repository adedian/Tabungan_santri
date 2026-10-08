/* Dashboard — Cari Santri.
   Memakai App.Combobox (debounce 300 ms, AbortController membatalkan request lama) ke /api/students/search.
   Klik/Enter pada hasil membuka halaman detail yang sama dengan menu Tabungan (tabungan/santri/{id}; alumni -> tabungan/alumni/{id}).
   Hak akses dicek di server (route can:students.view + can:savings.view); frontend hanya mengarahkan. Teks lewat textContent. */
(function () {
  'use strict';

  var input = document.getElementById('ds-q');
  if (!input || !App.Combobox) { return; }

  var STATUS = { nonaktif: 'Nonaktif', alumni: 'Alumni' };

  App.Combobox(input, {
    delay: 300,
    fetch: function (q, signal) {
      return App.api('api/students/search?limit=8&status=semua&q=' + encodeURIComponent(q), { signal: signal })
        .then(function (r) { return r.data.items; });
    },
    render: function (s) {
      var sub = s.jenjang + ' • Kelas ' + s.kelas + ' • ID ' + s.code + (STATUS[s.status] ? ' • ' + STATUS[s.status] : '');
      return { title: s.name, sub: sub, right: App.rupiah(s.saldo) };
    },
    display: function (s) { return s.name; },
    emptyText: 'Santri tidak ditemukan. Coba gunakan nama atau ID santri yang berbeda.',
    hint: 'Ketik untuk mencari…',
    onSelect: function (s) {
      window.location.href = App.url((s.status === 'alumni' ? 'tabungan/alumni/' : 'tabungan/santri/') + s.id);
    }
  });
})();
