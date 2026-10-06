/* Audit overflow horizontal untuk Revisi 1 (butir 44–45): memuat tiap halaman di iframe dengan lebar tertentu
 * (media query mengikuti lebar iframe) lalu membandingkan scrollWidth dengan lebar tampilan.
 *
 * Pakai (konsol browser, sudah login, halaman mana pun di aplikasi ini):
 *   // tempel isi berkas ini, lalu:
 *   await __auditPages(['/dashboard', '/tabungan', '/santri'])           // semua lebar standar
 *   await __auditPages(['/santri'], [360, 768])                            // lebar tertentu
 * Hasil: { checked: n, problems: [ {path, w, sw, cw, off:[elemen yang keluar bingkai]} ] }  → problems kosong = lulus.
 * Elemen position:fixed (sidebar off-canvas) dan elemen di dalam kontainer scroll internal tidak dihitung. */
(function () {
  var WIDTHS = [360, 375, 390, 414, 430, 768, 1024, 1280, 1366, 1440, 1920];

  function audit(win) {
    var doc = win.document, de = doc.documentElement, w = de.clientWidth, off = [], all = doc.body.querySelectorAll('*');
    for (var i = 0; i < all.length; i++) {
      var e = all[i], r = e.getBoundingClientRect();
      if (!r.width || !r.height) { continue; }
      if (r.right > w + 1 || r.left < -1) {
        var p = e, skip = false, clipped = false;
        while (p && p !== doc.body) {
          var cs = win.getComputedStyle(p);
          if (cs.position === 'fixed') { skip = true; break; }
          if (p !== e && /(auto|scroll|hidden|clip)/.test(cs.overflowX)) {
            var pr = p.getBoundingClientRect();
            if (pr.right <= w + 1 && pr.left >= -1) { clipped = true; break; }
          }
          p = p.parentElement;
        }
        if (skip || clipped) { continue; }
        off.push(e.tagName.toLowerCase() + '.' + String(typeof e.className === 'string' ? e.className : '').split(' ')[0] + '#' + e.id + ' L' + Math.round(r.left) + ' R' + Math.round(r.right));
      }
    }
    return { sw: de.scrollWidth, cw: w, over: de.scrollWidth > w + 1, off: off.slice(0, 6), n: off.length };
  }

  window.__audit = audit; // satu dokumen: __audit(window)

  window.__auditPages = async function (paths, widths) {
    widths = widths || WIDTHS;
    var problems = [], checked = 0;
    for (var pi = 0; pi < paths.length; pi++) {
      // semua lebar dimuat paralel agar cepat
      var jobs = widths.map(function (w) {
        var f = document.createElement('iframe');
        f.style.cssText = 'position:fixed;left:-99999px;top:0;border:0;height:1400px;width:' + w + 'px';
        document.body.appendChild(f);
        return new Promise(function (res) { f.onload = res; f.src = '/Tabungan_santri' + paths[pi]; })
          .then(function () { return new Promise(function (res) { setTimeout(res, 1100); }); })
          .then(function () {
            var r;
            try { r = audit(f.contentWindow); } catch (e) { r = { over: true, n: 1, off: ['audit error: ' + e.message] }; }
            f.remove();
            return { w: w, r: r };
          });
      });
      var out = await Promise.all(jobs);
      out.forEach(function (o) {
        checked++;
        if (o.r.over || o.r.n > 0) { problems.push({ path: paths[pi], w: o.w, sw: o.r.sw, cw: o.r.cw, n: o.r.n, off: o.r.off }); }
      });
    }
    return { checked: checked, problems: problems };
  };
})();
