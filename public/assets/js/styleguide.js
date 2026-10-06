/* Demo interaksi untuk halaman Panduan Komponen (tidak dipakai halaman lain). */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-demo-form]');
    if (form) { form.addEventListener('submit', function (e) { e.preventDefault(); }); }

    var host = document.getElementById('sg-chart');
    if (host && App.Chart) {
      var series = [{ key: 'masuk', name: 'Masuk', cls: 's1' }, { key: 'keluar', name: 'Keluar', cls: 's2' }];
      var sample = [[0, 0], [145000, 0], [125000, 0], [160000, 30000], [85000, 90000], [150000, 25000], [170000, 0], [80000, 45000], [110000, 120000], [240000, 15000]];
      var labels = ['3 Agu', '10 Agu', '17 Agu', '24 Agu', '31 Agu', '7 Sep', '14 Sep', '21 Sep', '28 Sep', '5 Okt'];
      var chart = App.Chart.columns(host, { series: series, label: 'Contoh grafik aktivitas tabungan' });
      chart.update(sample.map(function (v, i) { return { label: labels[i], title: 'Minggu ' + labels[i], masuk: v[0], keluar: v[1] }; }));
      App.Chart.legend(document.getElementById('sg-legend'), series);
      var tot = sample.reduce(function (a, v) { return [a[0] + v[0], a[1] + v[1]]; }, [0, 0]);
      document.querySelector('[data-legend-val="masuk"]').textContent = App.rupiah(tot[0]);
      document.querySelector('[data-legend-val="keluar"]').textContent = App.rupiah(tot[1]);
    }

    document.querySelectorAll('[data-demo-toast]').forEach(function (b) {
      b.addEventListener('click', function () {
        var t = b.getAttribute('data-demo-toast');
        var msg = { success: 'Transaksi berhasil disimpan', error: 'Saldo santri tidak mencukupi.', warning: 'Periksa kembali nominal yang dimasukkan.' };
        App.toast(t, msg[t]);
      });
    });

    document.querySelectorAll('[data-demo-loading]').forEach(function (b) {
      b.addEventListener('click', function () {
        App.setLoading(b, true, 'Menyimpan...');
        setTimeout(function () { App.setLoading(b, false); App.toast('success', 'Transaksi berhasil disimpan'); }, 1600);
      });
    });

    document.querySelectorAll('[data-demo-confirm]').forEach(function (b) {
      b.addEventListener('click', function () {
        App.confirm({
          title: 'Konfirmasi Transaksi',
          message: 'Periksa kembali data berikut sebelum menyimpan.',
          confirmText: 'Simpan Transaksi',
          details: [
            { label: 'Nama', value: 'Ahmad Rizky' },
            { label: 'Mutasi', value: 'Keluar' },
            { label: 'Nominal', value: App.rupiah(100000) },
            { label: 'Saldo setelah transaksi', value: App.rupiah(5000), total: true }
          ]
        }).then(function (yes) { if (yes) { App.toast('success', 'Transaksi berhasil disimpan'); } });
      });
    });

    document.querySelectorAll('[data-demo-delete]').forEach(function (b) {
      b.addEventListener('click', function () {
        App.confirm({
          title: 'Hapus transaksi?',
          message: 'Data transaksi akan dihapus dan saldo santri akan diperbarui.',
          confirmText: 'Hapus', tone: 'danger'
        }).then(function (yes) { if (yes) { App.toast('success', 'Transaksi dihapus'); } });
      });
    });
  });
})();
