/* ==========================================================================
   Tabungan Santri — JS inti (vanilla, tanpa dependensi)
   CSP: tidak ada inline script. Konfigurasi dibaca dari <meta>, data dari data-* / JSON block.

   API publik (window.App):
     App.api(path, {method, data, headers, signal})  -> Promise<json>   (CSRF + penanganan 401)
     App.toast(type, message, {duration})            type: success|error|warning
     App.confirm({title, message, details, confirmText, cancelText, tone}) -> Promise<boolean>
     App.setLoading(button, on, label)
     App.parseMoney(str) / App.formatMoney(int) / App.rupiah(int)
     App.debounce(fn, ms)
   ========================================================================== */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var meta = function (n) { var m = document.querySelector('meta[name="' + n + '"]'); return m ? m.content : ''; };

  var App = window.App = {
    baseUrl: meta('base-url').replace(/\/$/, ''),
    csrf: function () { return meta('csrf-token'); },
    syncInterval: parseInt(meta('sync-interval'), 10) || 5000
  };
  App.url = function (p) { return App.baseUrl + '/' + String(p).replace(/^\/+/, ''); };
  App.debounce = function (fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; };

  /** Tautan nama santri ke halaman Detail Tabungan (teks lewat textContent). */
  App.studentLink = function (id, name, cls) {
    var a = document.createElement("a"); a.className = "cell-link" + (cls ? " " + cls : "");
    a.href = App.url("tabungan/santri/" + id); a.textContent = name; return a;
  };
  App.icon = icon;
  function icon(name) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'icon'); svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', meta('icon-sprite') + '#' + name);
    svg.appendChild(use);
    return svg;
  }

  /* ---------- API ---------- */
  function ApiError(message, status, errors) { this.message = message; this.status = status || 0; this.errors = errors || {}; }
  ApiError.prototype = Object.create(Error.prototype);
  App.ApiError = ApiError;

  App.api = function (path, opts) {
    opts = opts || {};
    var method = (opts.method || 'GET').toUpperCase();
    var headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
    var init = { method: method, credentials: 'same-origin', headers: headers, signal: opts.signal };
    if (method !== 'GET') {
      headers['X-CSRF-Token'] = App.csrf();
      if (opts.data != null) { headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.data); }
    }
    return fetch(App.url(path), init).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (json) {
        if (res.status === 401) { window.location.href = App.url('login'); throw new ApiError('Sesi berakhir.', 401); }
        if (!res.ok || !json || json.success === false) {
          throw new ApiError((json && json.message) || 'Terjadi kesalahan. Silakan coba kembali.', res.status, json && json.errors);
        }
        return json;
      });
    }, function (err) {
      if (err && err.name === 'AbortError') { throw err; }
      throw new ApiError('Tidak dapat terhubung ke server. Periksa koneksi Anda.', 0);
    });
  };

  /* ---------- Toast ---------- */
  var toastIcons = { success: 'circle-check', error: 'circle-alert', warning: 'triangle-alert' };
  App.toast = function (type, message, opts) {
    var region = $('#toast-region'); if (!region) { return; }
    type = toastIcons[type] ? type : 'success';
    var duration = (opts && opts.duration) || (type === 'error' ? 7000 : 4500);

    var el = document.createElement('div');
    el.className = 'toast toast-' + type;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.appendChild(icon(toastIcons[type]));
    var msg = document.createElement('div'); msg.className = 'toast-msg'; msg.textContent = message; el.appendChild(msg);
    var close = document.createElement('button');
    close.type = 'button'; close.className = 'toast-close'; close.setAttribute('aria-label', 'Tutup notifikasi');
    close.appendChild(icon('x')); el.appendChild(close);
    region.appendChild(el);

    var timer;
    function dismiss() {
      clearTimeout(timer);
      if (!el.parentNode) { return; }
      el.classList.add('is-leaving');
      setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 180);
    }
    close.addEventListener('click', dismiss);
    el.addEventListener('mouseenter', function () { clearTimeout(timer); });
    el.addEventListener('mouseleave', function () { timer = setTimeout(dismiss, 2000); });
    timer = setTimeout(dismiss, duration);
    while (region.children.length > 4) { region.removeChild(region.firstChild); }
    return dismiss;
  };

  /* ---------- Konfirmasi (<dialog>) ---------- */
  App.confirm = function (o) {
    o = o || {};
    var dlg = $('#confirm-dialog');
    if (!dlg || typeof dlg.showModal !== 'function') { return Promise.resolve(window.confirm(o.message || o.title || 'Lanjutkan?')); }

    $('#confirm-title', dlg).textContent = o.title || 'Konfirmasi';
    var body = $('#confirm-message', dlg); body.textContent = o.message || ''; body.hidden = !o.message;
    var list = $('#confirm-details', dlg); list.textContent = '';
    (o.details || []).forEach(function (d) {
      var dt = document.createElement('dt'); dt.textContent = d.label;
      var dd = document.createElement('dd'); dd.textContent = d.value;
      if (d.total) { var row = document.createElement('div'); row.className = 'is-total'; row.appendChild(dt); row.appendChild(dd); list.appendChild(row); }
      else { list.appendChild(dt); list.appendChild(dd); }
    });
    list.hidden = !(o.details && o.details.length);

    var danger = o.tone === 'danger';
    var ok = $('#confirm-ok', dlg), cancel = $('#confirm-cancel', dlg), ic = $('#confirm-icon', dlg);
    ok.textContent = o.confirmText || 'Ya, lanjutkan';
    ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
    cancel.textContent = o.cancelText || 'Batal';
    ic.className = 'modal-icon' + (danger ? ' is-danger' : '');
    ic.textContent = ''; ic.appendChild(icon(danger ? 'triangle-alert' : 'info'));

    bindConfirm(dlg);
    if (pendingConfirm) { settleConfirm(false); }  // konfirmasi sebelumnya yang masih menggantung dianggap batal
    if (dlg.open) { dlg.close('cancel'); }

    return new Promise(function (resolve) {
      pendingConfirm = resolve;
      dlg.returnValue = 'cancel';
      dlg.showModal();
      cancel.focus(); // aksi aman jadi fokus awal
    });
  };

  // Satu listener tetap + satu resolver aktif: tidak ada listener menumpuk, hasil ditentukan saat klik
  // (bukan dari returnValue yang bisa terlambat), dan event 'close' basi diabaikan bila dialog sudah dibuka lagi.
  var pendingConfirm = null;
  function settleConfirm(value) { var p = pendingConfirm; pendingConfirm = null; if (p) { p(value); } }
  function bindConfirm(dlg) {
    if (dlg.dataset.bound) { return; }
    dlg.dataset.bound = '1';
    $('#confirm-ok', dlg).addEventListener('click', function () { settleConfirm(true); });
    dlg.addEventListener('close', function () { if (!dlg.open) { settleConfirm(false); } });
  }

  /* ---------- Tombol memuat ---------- */
  App.setLoading = function (btn, on, label) {
    if (!btn) { return; }
    if (on) {
      if (btn.dataset.origHtml === undefined) { btn.dataset.origHtml = btn.innerHTML; }
      btn.classList.add('is-loading'); btn.disabled = true; btn.setAttribute('aria-busy', 'true');
      if (label) { btn.textContent = label; }
    } else {
      btn.classList.remove('is-loading'); btn.disabled = false; btn.removeAttribute('aria-busy');
      if (btn.dataset.origHtml !== undefined) { btn.innerHTML = btn.dataset.origHtml; delete btn.dataset.origHtml; }
    }
  };

  /* ---------- Format uang ---------- */
  App.parseMoney = function (s) { var d = String(s == null ? '' : s).replace(/\D+/g, ''); return d === '' ? 0 : parseInt(d, 10); };
  App.formatMoney = function (n) { return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
  App.rupiah = function (n) { n = Math.round(Number(n) || 0); return (n < 0 ? '-' : '') + 'Rp ' + App.formatMoney(Math.abs(n)); };

  /** Ringkas untuk sumbu/label grafik: 250 rb, 1,5 jt, 2 M. */
  App.compact = function (n) {
    n = Math.abs(Math.round(Number(n) || 0));
    function f(v, unit) { return (Math.round(v * 10) / 10).toString().replace('.', ',') + ' ' + unit; }
    if (n >= 1e9) { return f(n / 1e9, 'M'); }
    if (n >= 1e6) { return f(n / 1e6, 'jt'); }
    if (n >= 1e3) { return f(n / 1e3, 'rb'); }
    return String(n);
  };

  /* ---------- Realtime: polling ringan ----------
     App.watch(['savings','students'], onChange, initialRev)
     - satu request kecil per interval (hanya membaca 1 baris sync_state di server)
     - berhenti saat tab tersembunyi; langsung cek saat tab kembali terlihat
     - backoff eksponensial saat server/jaringan bermasalah
     - onChange boleh mengembalikan Promise; revisi baru dicatat setelah berhasil (kegagalan -> dicoba lagi) */
  App.watch = function (scopes, onChange, initialRev) {
    var rev = initialRev == null ? null : Number(initialRev);
    var errors = 0, timer = null, running = false;
    var ind = $('[data-sync]'), label = ind && $('.sync-label', ind);

    function state(s) {
      if (!ind) { return; }
      ind.classList.toggle('is-offline', s === 'offline');
      ind.classList.toggle('is-busy', s === 'busy');
      if (label) { label.textContent = s === 'offline' ? 'Menyambung ulang…' : s === 'busy' ? 'Memperbarui…' : 'Terhubung'; }
    }
    function delay() { return Math.min(60000, App.syncInterval * Math.pow(2, Math.min(errors, 4))); }
    function schedule() { clearTimeout(timer); timer = setTimeout(tick, delay()); }

    function tick() {
      if (document.hidden || running) { schedule(); return; }
      running = true;
      App.api('api/sync?scopes=' + encodeURIComponent(scopes.join(',')), { headers: { 'X-Background-Poll': '1' } })
        .then(function (res) {
          errors = 0;
          var cur = res.data.rev;
          if (rev === null) { rev = cur; state('ok'); return; }
          if (cur === rev) { state('ok'); return; }
          state('busy');
          return Promise.resolve(onChange(cur)).then(function () { rev = cur; state('ok'); });
        })
        .catch(function (e) { if (!(e && e.status === 401)) { errors++; state('offline'); } })
        .then(function () { running = false; schedule(); });
    }

    document.addEventListener('visibilitychange', function () { if (!document.hidden) { clearTimeout(timer); tick(); } });
    window.addEventListener('online', function () { errors = 0; clearTimeout(timer); tick(); });
    schedule();
  };

  // Input nominal: tampil "10.000", server tetap menerima (digit saja diambil). Posisi kursor dijaga.
  function bindMoney(input) {
    input.setAttribute('inputmode', 'numeric'); input.setAttribute('autocomplete', 'off');
    input.addEventListener('input', function () {
      var raw = input.value, pos = input.selectionStart == null ? raw.length : input.selectionStart;
      var digitsBefore = raw.slice(0, pos).replace(/\D+/g, '').length;
      var digits = raw.replace(/\D+/g, '').replace(/^0+(?=\d)/, '');
      var formatted = digits === '' ? '' : App.formatMoney(digits);
      input.value = formatted;
      var p = 0, seen = 0;
      while (p < formatted.length && seen < digitsBefore) { if (/\d/.test(formatted[p])) { seen++; } p++; }
      try { input.setSelectionRange(p, p); } catch (e) { /* abaikan */ }
    });
    if (input.value) { input.value = App.formatMoney(App.parseMoney(input.value)); }
  }

  /* ---------- Label dinamis (mis. Mutasi -> Keterangan Masuk/Keluar) ---------- */
  function bindLabel(select) {
    var target = $(select.getAttribute('data-bind-label'));
    if (!target) { return; }
    var labels = {}, holders = {};
    try { labels = JSON.parse(select.getAttribute('data-labels') || '{}'); } catch (e) { /* abaikan */ }
    try { holders = JSON.parse(select.getAttribute('data-placeholders') || '{}'); } catch (e) { /* abaikan */ }
    var field = target.getAttribute('for') ? document.getElementById(target.getAttribute('for')) : null;
    function apply(animate) {
      var v = select.value, form = select.form;
      if (form) { form.dataset.mutation = v; }
      if (labels[v]) {
        target.textContent = labels[v];
        if (animate) { target.classList.remove('swap'); void target.offsetWidth; target.classList.add('swap'); }
      }
      if (field && holders[v]) { field.placeholder = holders[v]; }
    }
    select.addEventListener('change', function () { apply(true); });
    apply(false);
  }

  /* ---------- Sidebar ---------- */
  function initSidebar() {
    var sb = $('#sidebar'), ov = $('.sidebar-overlay'); if (!sb) { return; }
    var toggles = $$('[data-sidebar-toggle]');
    var mq = window.matchMedia('(max-width: 1023px)');
    function set(open) {
      sb.classList.toggle('is-open', open); if (ov) { ov.classList.toggle('is-open', open); }
      document.body.classList.toggle('no-scroll', open && mq.matches);
      toggles.forEach(function (t) { if (t.hasAttribute('aria-expanded')) { t.setAttribute('aria-expanded', String(open)); } });
      if (open) { var first = $('.nav-link:not([aria-disabled="true"])', sb); if (first && mq.matches) { first.focus(); } }
    }
    toggles.forEach(function (t) { t.addEventListener('click', function () { set(!sb.classList.contains('is-open')); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sb.classList.contains('is-open')) { set(false); var b = $('.menu-btn'); if (b) { b.focus(); } } });
    mq.addEventListener('change', function () { set(false); });
    $$('.nav-link', sb).forEach(function (a) { a.addEventListener('click', function () { if (mq.matches) { set(false); } }); });
  }

  /* ---------- Dropdown ---------- */
  function initDropdowns() {
    var open = null;
    function close() { if (open) { $('[data-dropdown-menu]', open).hidden = true; $('[data-dropdown-toggle]', open).setAttribute('aria-expanded', 'false'); open = null; } }
    $$('[data-dropdown]').forEach(function (dd) {
      var toggle = $('[data-dropdown-toggle]', dd), menu = $('[data-dropdown-menu]', dd);
      toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var wasOpen = open === dd; close();
        if (!wasOpen) { menu.hidden = false; toggle.setAttribute('aria-expanded', 'true'); open = dd; var f = $('a,button', menu); if (f) { f.focus(); } }
      });
    });
    document.addEventListener('click', function (e) { if (open && !open.contains(e.target)) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) { var t = $('[data-dropdown-toggle]', open); close(); t.focus(); } });
  }

  /* ---------- Form: cegah klik ganda & konfirmasi ---------- */
  function initForms() {
    document.addEventListener('submit', function (e) {
      var form = e.target; if (!(form instanceof HTMLFormElement) || e.defaultPrevented) { return; }

      if (form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
        e.preventDefault();
        App.confirm({
          title: form.getAttribute('data-confirm-title') || 'Konfirmasi',
          message: form.getAttribute('data-confirm'),
          confirmText: form.getAttribute('data-confirm-ok') || 'Ya, lanjutkan',
          tone: form.getAttribute('data-confirm-tone') || 'primary'
        }).then(function (yes) { if (yes) { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit(e.submitter || undefined) : form.submit(); } });
        return;
      }
      var text = form.getAttribute('data-loading-text');
      var btn = e.submitter || $('button[type="submit"]', form);
      if (text !== null && btn) { setTimeout(function () { App.setLoading(btn, true, text || 'Memproses...'); }, 0); }
    });
    // Kembali via tombol back (bfcache): pulihkan tombol
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) { $$('.btn.is-loading').forEach(function (b) { App.setLoading(b, false); }); $$('form[data-confirmed]').forEach(function (f) { delete f.dataset.confirmed; }); }
    });
  }

  /* ---------- Flash dari server -> toast ---------- */
  function initFlash() {
    var el = $('#flash-data'); if (!el) { return; }
    try { var items = JSON.parse(el.textContent || '[]'); items.forEach(function (m) { App.toast(m.type, m.message); }); } catch (e) { /* abaikan */ }
  }

  /* ---------- Tombol Kembali ----------
     <a data-back href="/halaman-induk">: bila pengunjung datang dari halaman lain di aplikasi ini, kembali ke halaman itu
     (riwayat browser, filter ikut terjaga); bila tidak (dibuka langsung/disegarkan), ikuti tautan halaman induk. */
  function initBack() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a[data-back]') : null;
      if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
      try {
        var ref = document.referrer ? new URL(document.referrer) : null;
        var here = location.pathname;
        if (ref && ref.origin === location.origin && ref.pathname !== here && !/\/login\/?$/.test(ref.pathname) && history.length > 1) {
          e.preventDefault(); history.back();
        }
      } catch (err) { /* ikuti tautan */ }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSidebar(); initDropdowns(); initForms(); initFlash(); initBack();
    $$('input[data-money]').forEach(bindMoney);
    $$('select[data-bind-label]').forEach(bindLabel);
  });
})();
