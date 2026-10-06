/* ==========================================================================
   App.Chart — grafik kolom berkelompok (SVG, tanpa dependensi)

   Spesifikasi (dataviz): batang ≤ 24px, ujung atas bulat 4px & rata di garis dasar, jarak 2px antar batang,
   grid hairline solid, satu sumbu, legenda untuk ≥ 2 seri, label langsung hanya pada puncak,
   tooltip pada hover DAN fokus keyboard, tabel pendamping untuk pembaca layar.
   Teks memakai token teks (bukan warna seri). Label dari server dimasukkan lewat textContent.

   Pemakaian:
     var chart = App.Chart.columns(hostEl, {
       series: [{key:'masuk', name:'Masuk', cls:'s1'}, {key:'keluar', name:'Keluar', cls:'s2'}],
       format: App.rupiah, height: 300, emptyText: '...'
     });
     chart.update([{label:'6 Okt', title:'Selasa, 6 Oktober 2026', masuk:50000, keluar:0}, ...]);
   ========================================================================== */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';

  function svg(name, attrs, parent) {
    var el = document.createElementNS(NS, name);
    for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { el.setAttribute(k, attrs[k]); } }
    if (parent) { parent.appendChild(el); }
    return el;
  }
  function html(tag, cls, text, parent) {
    var el = document.createElement(tag);
    if (cls) { el.className = cls; }
    if (text != null) { el.textContent = text; }
    if (parent) { parent.appendChild(el); }
    return el;
  }

  /** Sumbu "rapi": 0, 250 rb, 500 rb, ... dengan langkah 1/2/2,5/5 × 10^n. */
  function niceTicks(max, target) {
    var raw = Math.max(max, 1) / target, pow = Math.pow(10, Math.floor(Math.log(raw) / Math.LN10)), step = pow;
    [1, 2, 2.5, 5, 10].some(function (m) { step = m * pow; return step >= raw; });
    var top = Math.ceil(max / step) * step || step, ticks = [];
    for (var v = 0; v <= top + step / 1000; v += step) { ticks.push(Math.round(v)); }
    return ticks;
  }

  /** Batang dengan ujung atas bulat 4px, dasar rata. */
  function barPath(x, base, w, h) {
    var r = Math.min(4, h, w / 2), top = base - h;
    return 'M' + x + ',' + base + 'V' + (top + r) + 'Q' + x + ',' + top + ' ' + (x + r) + ',' + top +
      'H' + (x + w - r) + 'Q' + (x + w) + ',' + top + ' ' + (x + w) + ',' + (top + r) + 'V' + base + 'Z';
  }

  function columns(host, opts) {
    var series = opts.series, fmt = opts.format || App.rupiah, H = opts.height || 300;
    var M = { t: 22, r: 8, b: 30, l: 54 };
    var buckets = [], destroyed = false, active = -1;

    host.classList.add('chart');
    var stage = html('div', 'chart-stage', null, host);
    var tip = html('div', 'chart-tip', null, host);
    tip.hidden = true; tip.setAttribute('role', 'status');

    function hideTip() { tip.hidden = true; if (active >= 0) { setActive(-1); } }

    var groups = [];
    function setActive(i) {
      active = i;
      groups.forEach(function (g, idx) { g.classList.toggle('is-active', idx === i); });
    }

    function showTip(i, anchorX, anchorY) {
      var b = buckets[i];
      setActive(i);
      tip.textContent = '';
      html('div', 'chart-tip-title', b.title || b.label, tip);
      series.forEach(function (s) {
        var row = html('div', 'chart-tip-row', null, tip);
        html('span', 'chart-key chart-key-' + s.cls, null, row);
        html('strong', null, fmt(b[s.key]), row);
        html('span', 'chart-tip-name', s.name, row);
      });
      tip.hidden = false;
      var hw = host.clientWidth, tw = tip.offsetWidth, th = tip.offsetHeight;
      var x = Math.min(Math.max(anchorX - tw / 2, 4), hw - tw - 4);
      var y = Math.max(anchorY - th - 10, 4);
      tip.style.left = x + 'px'; tip.style.top = y + 'px';
    }

    function render() {
      stage.textContent = ''; groups = [];
      if (!buckets.length) { return; }

      var max = 0;
      buckets.forEach(function (b) { series.forEach(function (s) { max = Math.max(max, b[s.key]); }); });
      if (max === 0) {
        html('div', 'chart-empty', opts.emptyText || 'Belum ada data pada periode ini.', stage);
        return;
      }

      var W = Math.max(280, host.clientWidth), pw = W - M.l - M.r, ph = H - M.t - M.b;
      var ticks = niceTicks(max, 4), top = ticks[ticks.length - 1];
      var y = function (v) { return M.t + ph - (v / top) * ph; };
      var n = buckets.length, slot = pw / n, base = M.t + ph;
      var barW = Math.max(3, Math.min(24, (slot * 0.74 - 2) / series.length));
      var groupW = barW * series.length + 2 * (series.length - 1);

      var root = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, width: W, height: H, 'class': 'chart-svg', role: 'img',
        'aria-label': (opts.label || 'Grafik') + '. Gunakan tampilan tabel untuk membaca semua nilai.' }, stage);

      // grid hairline + label sumbu Y
      ticks.forEach(function (t) {
        svg('line', { x1: M.l, x2: W - M.r, y1: y(t), y2: y(t), 'class': t === 0 ? 'chart-axis' : 'chart-grid' }, root);
        var tx = svg('text', { x: M.l - 8, y: y(t), dy: '.32em', 'text-anchor': 'end', 'class': 'chart-tick' }, root);
        tx.textContent = App.compact(t);
      });

      // label sumbu X (dijarangkan bila sempit)
      var every = Math.ceil(54 / slot);
      buckets.forEach(function (b, i) {
        if (i % every !== 0 && i !== n - 1) { return; }
        if (i !== n - 1 && (n - 1 - i) < every && every > 1) { return; } // jangan berdesakan dengan label terakhir
        var tx = svg('text', { x: M.l + slot * i + slot / 2, y: H - 8, 'text-anchor': 'middle', 'class': 'chart-tick' }, root);
        tx.textContent = b.label;
      });

      // puncak: satu-satunya label langsung
      var peak = { v: 0, i: -1, s: null };
      buckets.forEach(function (b, i) { series.forEach(function (s) { if (b[s.key] > peak.v) { peak = { v: b[s.key], i: i, s: s }; } }); });

      buckets.forEach(function (b, i) {
        var g = svg('g', { 'class': 'chart-group', tabindex: 0, role: 'img',
          'aria-label': (b.title || b.label) + ': ' + series.map(function (s) { return s.name + ' ' + fmt(b[s.key]); }).join(', ') }, root);
        var sx = M.l + slot * i;
        svg('rect', { x: sx, y: M.t, width: slot, height: ph, 'class': 'chart-hl' }, g);

        var gx = sx + (slot - groupW) / 2;
        series.forEach(function (s, si) {
          var v = b[s.key]; if (v <= 0) { return; }
          var h = Math.max(2, base - y(v)), x = gx + si * (barW + 2);
          svg('path', { d: barPath(x, base, barW, h), 'class': 'chart-bar chart-' + s.cls }, g);
          if (peak.i === i && peak.s === s) {
            var lx = Math.min(Math.max(x + barW / 2, M.l + 18), W - M.r - 18);
            var lt = svg('text', { x: lx, y: y(v) - 6, 'text-anchor': 'middle', 'class': 'chart-peak' }, g);
            lt.textContent = App.compact(v);
          }
        });
        // area sentuh = seluruh slot (lebih besar dari batangnya) + celah
        var hit = svg('rect', { x: sx, y: M.t, width: slot, height: ph + M.b - 6, 'class': 'chart-hit' }, g);
        var barTop = y(Math.max.apply(null, series.map(function (s) { return b[s.key]; })));
        var place = function () { showTip(i, sx + slot / 2, barTop - 20); }; // di atas batang & label puncak
        hit.addEventListener('pointerenter', place);
        hit.addEventListener('pointermove', place);
        hit.addEventListener('pointerleave', hideTip);
        g.addEventListener('focus', place);
        g.addEventListener('blur', hideTip);
        groups.push(g);
      });
      root.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hideTip(); } });
    }

    var ro = null, raf = 0, lastW = 0;
    if (typeof ResizeObserver !== 'undefined') {
      ro = new ResizeObserver(function () {
        if (destroyed || host.clientWidth === lastW) { return; }
        lastW = host.clientWidth;
        cancelAnimationFrame(raf); raf = requestAnimationFrame(function () { hideTip(); render(); });
      });
      ro.observe(host);
    }

    return {
      update: function (data) { buckets = data || []; hideTip(); lastW = host.clientWidth; render(); },
      destroy: function () { destroyed = true; if (ro) { ro.disconnect(); } host.textContent = ''; }
    };
  }

  /** Legenda: kotak warna + nama seri (teks memakai token teks, bukan warna seri). */
  function legend(parent, series) {
    var ul = html('ul', 'chart-legend', null, parent);
    series.forEach(function (s) {
      var li = html('li', null, null, ul);
      html('span', 'chart-swatch chart-key-' + s.cls, null, li);
      html('span', null, s.name, li);
      var val = html('strong', 'chart-legend-val', '', li); val.setAttribute('data-legend-val', s.key);
    });
    return ul;
  }

  /** Tabel pendamping (setara data grafik) untuk pembaca layar / pengguna yang butuh angka persis. */
  function table(buckets, series, columnLabel, fmt) {
    fmt = fmt || App.rupiah;
    var wrap = html('div', 'table-wrap');
    var t = html('table', 'table', null, wrap);
    var head = html('tr', null, null, html('thead', null, null, t));
    html('th', null, columnLabel || 'Periode', head);
    series.forEach(function (s) { html('th', 'num', s.name, head); });
    var body = html('tbody', null, null, t);
    buckets.forEach(function (b) {
      var tr = html('tr', null, null, body);
      html('td', 'nowrap', b.title || b.label, tr);
      series.forEach(function (s) { html('td', 'num', fmt(b[s.key]), tr); });
    });
    return wrap;
  }

  App.Chart = { columns: columns, legend: legend, table: table, niceTicks: niceTicks };
})();
