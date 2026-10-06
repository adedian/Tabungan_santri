/* ==========================================================================
   App.Bulk — mode "Pilih" + "Pilih Semua" + "Hapus Terpilih (n)" untuk tabel data.

     var bulk = App.Bulk({
       host:     document.getElementById('bulk-bar'),     // wadah bilah (di atas tabel)
       table:    document.querySelector('table'),         // tabel (kelas table-stack)
       noun:     'transaksi',
       items:    function () { return data.items; },      // item pada halaman yang tampil
       idOf:     function (item) { return item.id; },
       title:    'Hapus transaksi terpilih?',
       message:  function (picked) { return '...'; },     // teks konfirmasi
       details:  function (picked) { return [{label, value}]; },   // opsional
       remove:   function (ids) { return App.api(...); }, // Promise → respons JSON
       onDone:   function (res) { return reload(); }      // setelah berhasil
     });
     bulk.afterRender();   // PANGGIL setiap kali baris tabel selesai dirender ulang

   Kotak centang ditambahkan sebagai kolom pertama (th.col-check / td.col-check) dan hanya tampak saat mode Pilih aktif.
   Pilihan hanya berlaku untuk halaman yang tampil (berganti halaman/filter mengosongkan pilihan).
   Server tetap memvalidasi seluruh ID dan izin; ini hanya antarmuka.
   ========================================================================== */
(function () {
  'use strict';

  function el(tag, cls, text, parent) {
    var e = document.createElement(tag);
    if (cls) { e.className = cls; }
    if (text != null) { e.textContent = text; }
    if (parent) { parent.appendChild(e); }
    return e;
  }

  App.Bulk = function (o) {
    var host = o.host, table = o.table, selecting = false, selected = {}, uid = Math.random().toString(36).slice(2, 7);
    var noun = o.noun || 'data';

    /* ---- Bilah ---- */
    host.textContent = '';
    host.classList.add('bulk-bar');
    var btnPick = el('button', 'btn btn-secondary', null, host); btnPick.type = 'button';
    btnPick.appendChild(App.icon('list-checks')); btnPick.appendChild(document.createTextNode('Pilih'));

    var panel = el('div', 'bulk-panel', null, host); panel.hidden = true;
    var allLabel = el('label', 'bulk-all', null, panel); allLabel.htmlFor = 'bulk-all-' + uid;
    var allBox = el('input', 'bulk-check', null, allLabel); allBox.type = 'checkbox'; allBox.id = 'bulk-all-' + uid;
    el('span', null, 'Pilih Semua', allLabel);
    var count = el('span', 'bulk-count muted small', '', panel); count.setAttribute('aria-live', 'polite');
    var btnDel = el('button', 'btn btn-danger', null, panel); btnDel.type = 'button'; btnDel.hidden = true;
    var btnCancel = el('button', 'btn btn-ghost', 'Selesai', panel); btnCancel.type = 'button';

    function ids() { return Object.keys(selected); }
    function pickedItems() { return o.items().filter(function (it) { return selected[o.idOf(it)]; }); }

    function refreshUi() {
      var items = o.items(), n = ids().length;
      table.classList.toggle('is-selecting', selecting);
      btnPick.hidden = selecting; panel.hidden = !selecting;
      count.textContent = n > 0 ? n + ' ' + noun + ' dipilih' : '';
      btnDel.hidden = n === 0;
      btnDel.textContent = ''; btnDel.appendChild(App.icon('trash')); btnDel.appendChild(document.createTextNode('Hapus Terpilih (' + n + ')'));
      allBox.checked = items.length > 0 && n === items.length;
      allBox.indeterminate = n > 0 && n < items.length;
      allBox.disabled = items.length === 0;
      Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
        var cb = tr.querySelector('input.bulk-check'); if (cb) { tr.classList.toggle('is-picked', cb.checked); }
      });
    }

    function setSelecting(on) {
      selecting = on;
      if (!on) { selected = {}; Array.prototype.forEach.call(table.querySelectorAll('input.bulk-check'), function (c) { c.checked = false; }); }
      refreshUi();
    }

    /* ---- Kolom centang ---- */
    function afterRender() {
      var items = o.items(), keep = {};
      items.forEach(function (it) { var id = o.idOf(it); if (selected[id]) { keep[id] = true; } });
      selected = keep; // yang sudah tidak tampil dilepas dari pilihan

      var headRow = table.querySelector('thead tr');
      if (headRow && !headRow.querySelector('th.col-check')) {
        var th = el('th', 'col-check'); th.setAttribute('data-stack', 'check');
        el('span', 'sr-only', 'Pilih', th);
        headRow.insertBefore(th, headRow.firstChild);
      }
      var rows = table.querySelectorAll('tbody tr');
      items.forEach(function (it, i) {
        var tr = rows[i]; if (!tr || tr.querySelector('td.col-check')) { return; }
        var id = o.idOf(it);
        var td = el('td', 'col-check td-check'); td.setAttribute('data-label', '');
        var cb = el('input', 'bulk-check', null, td); cb.type = 'checkbox'; cb.checked = !!selected[id];
        cb.setAttribute('aria-label', 'Pilih ' + (o.labelOf ? o.labelOf(it) : noun + ' ' + (i + 1)));
        cb.addEventListener('change', function () { if (cb.checked) { selected[id] = true; } else { delete selected[id]; } refreshUi(); });
        tr.insertBefore(td, tr.firstChild);
      });
      refreshUi();
    }

    allBox.addEventListener('change', function () {
      selected = {};
      var items = o.items();
      Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr, i) {
        var cb = tr.querySelector('input.bulk-check'); if (!cb || !items[i]) { return; }
        cb.checked = allBox.checked;
        if (allBox.checked) { selected[o.idOf(items[i])] = true; }
      });
      refreshUi();
    });

    btnPick.addEventListener('click', function () { setSelecting(true); });
    btnCancel.addEventListener('click', function () { setSelecting(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && selecting && !document.querySelector('dialog[open]')) { setSelecting(false); } });

    btnDel.addEventListener('click', function () {
      var picked = pickedItems(), list = ids();
      if (!list.length) { return; }
      App.confirm({
        title: o.title || 'Hapus Data?',
        message: o.message ? o.message(picked) : 'Anda memilih ' + list.length + ' ' + noun + '. Apakah Anda yakin?',
        details: o.details ? o.details(picked) : [{ label: 'Jumlah dipilih', value: list.length + ' ' + noun, total: true }],
        confirmText: 'Hapus Data', tone: 'danger'
      }).then(function (yes) {
        if (!yes) { return; }
        App.setLoading(btnDel, true, 'Menghapus...');
        o.remove(list.map(Number))
          .then(function (res) { setSelecting(false); return o.onDone ? o.onDone(res) : null; })
          .catch(function (e) { App.toast('error', e.message, { duration: 10000 }); })
          .then(function () { App.setLoading(btnDel, false); refreshUi(); });
      });
    });

    refreshUi();
    return { afterRender: afterRender, isSelecting: function () { return selecting; }, clear: function () { setSelecting(false); } };
  };
})();
