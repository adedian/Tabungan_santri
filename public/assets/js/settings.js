/* Pengaturan identitas lembaga: nama + logo.
   Logo dibaca sebagai data URL, digambar ke <canvas> (maks. 256 px) lalu dikirim sebagai PNG.
   Re-encode ini menghapus metadata dan menyeragamkan format; server tetap memvalidasi ulang. */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var raw;
  try { raw = JSON.parse($('#settings-data').textContent); } catch (e) { return; }

  var MAX_PX = raw.logo_max_px || 256, MAX_FILE = 5 * 1024 * 1024;
  var state = { name: raw.school_name, version: raw.logo_version, pending: null, remove: false };
  var saved = { name: raw.school_name, version: raw.logo_version };

  var form = $('#settings-form'), nameIn = $('#st-name'), img = $('#st-logo-img'), ph = $('#st-logo-ph');
  var file = $('#st-logo-file'), pick = $('#st-logo-pick'), rm = $('#st-logo-remove'), dirty = $('#st-dirty');
  var errBox = $('#st-error');

  function logoSrc() {
    if (state.pending) { return state.pending; }
    if (state.remove || !state.version) { return null; }
    return App.url('brand/logo?v=' + encodeURIComponent(state.version));
  }
  function paint() {
    var src = logoSrc();
    $('#st-logo-box').classList.toggle('has-img', !!src);
    img.hidden = !src; ph.style.display = src ? 'none' : '';
    if (src) { img.src = src; } else { img.removeAttribute('src'); }
    rm.hidden = !src;
    var changed = nameIn.value.trim() !== saved.name || !!state.pending || state.remove;
    dirty.hidden = !changed;
  }
  function clearErrors() {
    ['school_name', 'logo'].forEach(function (k) {
      var f = $('[data-field="' + k + '"]', form), m = $('#err-' + k);
      f.classList.remove('has-error'); m.textContent = '';
      var i = $('input', f); if (i) { i.removeAttribute('aria-invalid'); i.removeAttribute('aria-describedby'); }
    });
    errBox.hidden = true;
  }
  function showErrors(errors, message) {
    var first = null;
    Object.keys(errors || {}).forEach(function (k) {
      var f = $('[data-field="' + k + '"]', form), m = $('#err-' + k); if (!f || !m) { return; }
      f.classList.add('has-error'); m.textContent = ''; m.appendChild(App.icon('circle-alert')); m.appendChild(document.createTextNode(errors[k]));
      var i = $('input:not([type=file])', f); if (i) { i.setAttribute('aria-invalid', 'true'); i.setAttribute('aria-describedby', 'err-' + k); if (!first) { first = i; } }
    });
    if (first) { first.focus(); } else if (message) { $('div', errBox).textContent = message; errBox.hidden = false; }
  }

  /** File gambar → data URL PNG ≤ MAX_PX. Menolak berkas yang bukan gambar / terlalu besar. */
  function toPng(f) {
    return new Promise(function (resolve, reject) {
      if (!/^image\/(png|jpeg|webp|svg\+xml)$/.test(f.type)) { reject(new Error('Pilih berkas gambar PNG, JPG, WEBP, atau SVG.')); return; }
      if (f.size > MAX_FILE) { reject(new Error('Berkas terlalu besar (maksimal 5 MB).')); return; }
      var fr = new FileReader();
      fr.onerror = function () { reject(new Error('Berkas tidak dapat dibaca.')); };
      fr.onload = function () {
        var im = new Image();
        im.onerror = function () { reject(new Error('Gambar tidak dapat dibuka. Coba berkas lain.')); };
        im.onload = function () {
          var w = im.naturalWidth || im.width, h = im.naturalHeight || im.height;
          if (!w || !h) { w = h = MAX_PX; } // SVG tanpa ukuran
          var k = Math.min(1, MAX_PX / Math.max(w, h)); if (f.type === 'image/svg+xml') { k = MAX_PX / Math.max(w, h); }
          var cw = Math.max(16, Math.round(w * k)), ch = Math.max(16, Math.round(h * k));
          var c = document.createElement('canvas'); c.width = cw; c.height = ch;
          try { c.getContext('2d').drawImage(im, 0, 0, cw, ch); resolve(c.toDataURL('image/png')); }
          catch (e) { reject(new Error('Gambar tidak dapat diproses.')); }
        };
        im.src = String(fr.result);
      };
      fr.readAsDataURL(f);
    });
  }

  pick.addEventListener('click', function () { file.click(); });
  file.addEventListener('change', function () {
    var f = file.files && file.files[0]; file.value = ''; if (!f) { return; }
    clearErrors();
    toPng(f).then(function (url) { state.pending = url; state.remove = false; paint(); })
      .catch(function (e) { showErrors({ logo: e.message }); });
  });
  rm.addEventListener('click', function () { clearErrors(); state.pending = null; state.remove = !!state.version || state.remove; if (!state.version) { state.remove = false; } paint(); });
  nameIn.addEventListener('input', paint);

  form.addEventListener('submit', function (e) {
    e.preventDefault(); clearErrors();
    var btn = $('#st-save'), body = { school_name: nameIn.value };
    if (state.pending) { body.logo = state.pending; } else if (state.remove) { body.remove_logo = true; }
    App.setLoading(btn, true, 'Menyimpan...');
    App.api('api/settings', { method: 'PUT', data: body })
      .then(function (res) {
        var d = res.data; state = { name: d.school_name, version: d.logo_version, pending: null, remove: false };
        saved = { name: d.school_name, version: d.logo_version }; nameIn.value = d.school_name;
        App.toast('success', res.message); paint();
        // sidebar ikut diperbarui pada pemuatan halaman berikutnya
      })
      .catch(function (err) { showErrors(err.status === 422 ? err.errors : {}, err.message); })
      .then(function () { App.setLoading(btn, false); });
  });

  paint();
})();
