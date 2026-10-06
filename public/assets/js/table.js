/* ==========================================================================
   App.Table — helper tabel data (dipakai Data Santri, Riwayat Tabungan, Laporan)
     App.Table.pager(container, {page, pages, total, perPage, perPageOptions, noun, onPage, onPerPage})
     App.Table.sortHeaders(thead, state, onChange)   // th[data-sort="kolom"]; state = {sort, dir}
     App.Table.skeleton(tbody, cols, rows)
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

  function skeleton(tbody, cols, rows) {
    tbody.textContent = '';
    for (var r = 0; r < rows; r++) {
      var tr = el('tr', null, null, tbody);
      for (var c = 0; c < cols; c++) { var td = el('td', null, null, tr); var s = el('div', 'skeleton skeleton-line', null, td); s.style.width = (40 + ((r * 7 + c * 13) % 45)) + '%'; }
    }
  }

  App.Table = { pager: pager, sortHeaders: sortHeaders, skeleton: skeleton };
})();
