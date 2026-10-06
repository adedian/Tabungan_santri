/* ==========================================================================
   App.Table — helper tabel data (dipakai Data Santri, Riwayat Tabungan, Laporan)
     App.Table.pager(container, {page, pages, total, perPage, perPageOptions, noun, onPage, onPerPage})
     App.Table.sortHeaders(thead, state, onChange)   // th[data-sort="kolom"]; state = {sort, dir}
     App.Table.skeleton(tbody, cols, rows)
     App.Table.stack(table)    // otomatis untuk <table class="table-stack">: tiap sel diberi data-label (dari <th>) agar
                               // di layar sempit tabel tampil sebagai kartu. <th data-stack="title|full|actions|check">.
   Semua teks lewat textContent.
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

  /** [1, '…', 4, 5, 6, '…', 20] */
  function pageList(page, pages) {
    var set = {}, out = [], last = 0;
    [1, pages, page - 1, page, page + 1].forEach(function (p) { if (p >= 1 && p <= pages) { set[p] = true; } });
    Object.keys(set).map(Number).sort(function (a, b) { return a - b; }).forEach(function (p) {
      if (last && p - last > 1) { out.push('…'); }
      out.push(p); last = p;
    });
    return out;
  }

  function pager(container, o) {
    container.textContent = '';
    var total = o.total, per = o.perPage, page = o.page, pages = Math.max(1, o.pages);
    var from = total === 0 ? 0 : (page - 1) * per + 1, to = Math.min(total, page * per);
    var noun = o.noun || 'data';

    var info = el('div', 'cluster', null, container);
    el('span', null, total === 0 ? 'Tidak ada ' + noun : 'Menampilkan ' + App.formatMoney(from) + '–' + App.formatMoney(to) + ' dari ' + App.formatMoney(total) + ' ' + noun, info);

    if (o.perPageOptions && o.onPerPage) {
      var sel = el('select', 'select', null, info);
      sel.setAttribute('aria-label', 'Jumlah baris per halaman');
      sel.style.width = 'auto'; sel.style.minHeight = 'var(--control-h-sm)'; sel.style.paddingTop = '0'; sel.style.paddingBottom = '0';
      o.perPageOptions.forEach(function (n) {
        var op = el('option', null, n + ' / halaman', sel); op.value = n; op.selected = n === per;
      });
      sel.addEventListener('change', function () { o.onPerPage(parseInt(sel.value, 10)); });
    }

    if (pages <= 1) { return; }
    var nav = el('nav', 'pager', null, container); nav.setAttribute('aria-label', 'Halaman');
    function btn(label, target, opts) {
      opts = opts || {};
      var b = el('button', 'pager-btn', opts.icon ? null : String(label), nav);
      b.type = 'button';
      if (opts.icon) { b.appendChild(App.icon(opts.icon)); }
      if (opts.aria) { b.setAttribute('aria-label', opts.aria); }
      if (opts.current) { b.setAttribute('aria-current', 'page'); }
      if (opts.disabled) { b.disabled = true; } else if (!opts.current) { b.addEventListener('click', function () { o.onPage(target); }); }
      return b;
    }
    btn('', page - 1, { icon: 'chevron-left', aria: 'Halaman sebelumnya', disabled: page <= 1 });
    pageList(page, pages).forEach(function (p) {
      if (p === '…') { el('span', 'pager-btn', '…', nav).setAttribute('aria-hidden', 'true'); }
      else { btn(p, p, { current: p === page, aria: 'Halaman ' + p }); }
    });
    btn('', page + 1, { icon: 'chevron-right', aria: 'Halaman berikutnya', disabled: page >= pages });
  }

  function sortHeaders(thead, state, onChange) {
    var ths = Array.prototype.slice.call(thead.querySelectorAll('th[data-sort]'));
    var bar = mobileSort(thead, ths, state, function () { paint(); onChange(state); });
    ths.forEach(function (th) {
      var label = th.textContent.trim();
      th.textContent = '';
      var b = el('button', 'th-sort', null, th); b.type = 'button';
      el('span', null, label, b);
      b.addEventListener('click', function () {
        var key = th.getAttribute('data-sort');
        if (state.sort === key) { state.dir = state.dir === 'asc' ? 'desc' : 'asc'; } else { state.sort = key; state.dir = 'asc'; }
        paint(); onChange(state);
      });
    });
    function paint() {
      if (bar) { bar.sync(); }
      ths.forEach(function (th) {
        var active = th.getAttribute('data-sort') === state.sort;
        th.setAttribute('aria-sort', active ? (state.dir === 'asc' ? 'ascending' : 'descending') : 'none');
        var b = th.querySelector('.th-sort'), old = b.querySelector('svg'); if (old) { b.removeChild(old); }
        b.appendChild(App.icon(!active ? 'arrow-up-down' : state.dir === 'asc' ? 'chevron-up' : 'chevron-down'));
      });
    }
    paint();
    return { paint: paint };
  }

  /** Di layar sempit header tabel disembunyikan; sediakan pilihan "Urutkan" agar pengurutan tetap bisa dipakai. */
  function mobileSort(thead, ths, state, changed) {
    var wrap = thead.closest('.table-wrap');
    if (!wrap || !ths.length) { return null; }
    var box = document.createElement('div'); box.className = 'sort-bar';
    var lab = el('label', 'sort-bar-label', 'Urutkan', box);
    var sel = el('select', 'select', null, box); sel.setAttribute('aria-label', 'Urutkan menurut');
    ths.forEach(function (th) { var o = el('option', null, th.textContent.trim(), sel); o.value = th.getAttribute('data-sort'); });
    var dir = el('button', 'btn btn-secondary btn-icon', null, box); dir.type = 'button';
    sel.id = 'sort-select-' + Math.random().toString(36).slice(2, 8); lab.htmlFor = sel.id;
    sel.addEventListener('change', function () { state.sort = sel.value; changed(); });
    dir.addEventListener('click', function () { state.dir = state.dir === 'asc' ? 'desc' : 'asc'; changed(); });
    wrap.insertBefore(box, wrap.firstChild); // di dalam wrap agar ikut aturan container query
    return {
      sync: function () {
        sel.value = state.sort;
        dir.textContent = ''; dir.appendChild(App.icon(state.dir === 'asc' ? 'chevron-up' : 'chevron-down'));
        dir.setAttribute('aria-label', state.dir === 'asc' ? 'Urutan naik, klik untuk menurun' : 'Urutan turun, klik untuk menaik');
      }
    };
  }

  /* Tabel → kartu di layar sempit (CSS .table-stack). Label tiap sel diambil dari <th> kolom yang sama. */
  function stackRow(tr, heads) {
    var cells = tr.children;
    for (var i = 0; i < cells.length; i++) {
      var td = cells[i], h = heads[i];
      if (td.tagName !== 'TD' || td.hasAttribute('colspan') || !h) { continue; }
      var mode = h.mode;
      td.setAttribute('data-label', (mode === 'title' || mode === 'actions' || mode === 'check') ? '' : h.label);
      if (mode) { td.classList.add('td-' + mode); }
    }
  }
  function stack(table) {
    if (!table || table.dataset.stacked) { return; }
    table.dataset.stacked = '1';
    var heads = [];
    function readHeads() {
      heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
        var sr = th.querySelector('.sr-only');
        return { label: sr ? '' : th.textContent.trim(), mode: th.getAttribute('data-stack') || '' };
      });
    }
    function all() { readHeads(); Array.prototype.forEach.call(table.querySelectorAll('tbody tr, tfoot tr'), function (tr) { stackRow(tr, heads); }); }
    all();
    var obs = new MutationObserver(function (muts) {
      readHeads();
      muts.forEach(function (m) {
        Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1 && n.tagName === 'TR') { stackRow(n, heads); } });
      });
    });
    Array.prototype.forEach.call(table.querySelectorAll('tbody, tfoot'), function (b) { obs.observe(b, { childList: true }); });
    obs.observe(table.querySelector('thead') || table, { childList: true, subtree: true, characterData: true });
    table.addEventListener('table:restack', all);
  }
  function stackAll() { Array.prototype.forEach.call(document.querySelectorAll('table.table-stack'), stack); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', stackAll); } else { stackAll(); }

  function skeleton(tbody, cols, rows) {
    tbody.textContent = '';
    for (var r = 0; r < rows; r++) {
      var tr = el('tr', null, null, tbody);
      for (var c = 0; c < cols; c++) { var td = el('td', null, null, tr); var s = el('div', 'skeleton skeleton-line', null, td); s.style.width = (40 + ((r * 7 + c * 13) % 45)) + '%'; }
    }
  }

  App.Table = { pager: pager, sortHeaders: sortHeaders, skeleton: skeleton, stack: stack };
})();
