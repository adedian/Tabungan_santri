/* ==========================================================================
   App.Combobox — pilihan yang bisa dicari (ARIA combobox/listbox, navigasi keyboard)

     var cb = App.Combobox(inputEl, {
       fetch:   function (q, signal) -> Promise<item[]>,
       render:  function (item) -> {title, sub, right}      // teks polos (aman: textContent)
       display: function (item) -> string                    // teks di input setelah dipilih
       onSelect(item), onClear(), delay (ms debounce, default 220),
       emptyText: 'Tidak ada hasil', hint: 'Ketik untuk mencari…'
     });
     cb.value      -> item terpilih atau null
     cb.setValue(item) / cb.clear() / cb.focus()

   Keyboard: ↓ ↑ pindah, Enter pilih, Esc tutup, Tab menutup. Mengetik setelah memilih membatalkan pilihan.
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

  App.Combobox = function (input, o) {
    var wrap = el('div', 'combo');
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var clearBtn = el('button', 'combo-clear', null, wrap);
    clearBtn.type = 'button'; clearBtn.hidden = true; clearBtn.setAttribute('aria-label', 'Hapus pilihan');
    clearBtn.appendChild(App.icon('x'));

    var pop = el('div', 'combo-pop', null, wrap); pop.hidden = true;
    var list = el('ul', 'combo-list', null, pop);
    var status = el('div', 'combo-status', null, pop);
    var uid = (input.id || 'combo') + '-list';
    list.id = uid; list.setAttribute('role', 'listbox');
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', uid); input.setAttribute('aria-expanded', 'false'); input.autocomplete = 'off';

    var items = [], active = -1, selected = null, ctrl = null, seq = 0;

    function open() { pop.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    function close() { pop.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; }
    function setActive(i) {
      active = i;
      Array.prototype.forEach.call(list.children, function (li, idx) { li.setAttribute('aria-selected', String(idx === i)); });
      if (i >= 0 && list.children[i]) {
        input.setAttribute('aria-activedescendant', list.children[i].id);
        list.children[i].scrollIntoView({ block: 'nearest' });
      } else { input.removeAttribute('aria-activedescendant'); }
    }

    function paint(result) {
      items = result; list.textContent = '';
      status.textContent = result.length ? '' : (o.emptyText || 'Tidak ada hasil');
      status.hidden = result.length > 0;
      result.forEach(function (it, i) {
        var r = o.render(it);
        var li = el('li', 'combo-option', null, list);
        li.id = uid + '-' + i; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
        var left = el('div', 'combo-main', null, li);
        el('div', 'combo-title', r.title, left);
        if (r.sub) { el('div', 'combo-sub', r.sub, left); }
        if (r.right) { el('div', 'combo-right', r.right, li); }
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(it); }); // mencegah input kehilangan fokus
        li.addEventListener('mousemove', function () { if (active !== i) { setActive(i); } });
      });
      setActive(result.length ? 0 : -1);
    }

    function search(q) {
      if (ctrl) { ctrl.abort(); }
      ctrl = new AbortController(); var mine = ++seq;
      open(); status.hidden = false; status.textContent = 'Mencari…';
      o.fetch(q, ctrl.signal).then(function (res) { if (mine === seq) { paint(res); } })
        .catch(function (e) { if (e && e.name === 'AbortError') { return; } if (mine === seq) { items = []; list.textContent = ''; status.hidden = false; status.textContent = e && e.message ? e.message : 'Gagal memuat.'; } });
    }
    var debounced = App.debounce(function () { search(input.value.trim()); }, o.delay || 220);

    function choose(it) {
      selected = it; input.value = o.display(it); clearBtn.hidden = false; wrap.classList.add('has-value');
      close(); if (o.onSelect) { o.onSelect(it); }
    }
    function clear(silent) {
      var had = selected !== null; selected = null; clearBtn.hidden = true; wrap.classList.remove('has-value');
      if (had && !silent && o.onClear) { o.onClear(); }
    }

    input.addEventListener('input', function () { if (selected) { clear(); } debounced(); });
    input.addEventListener('focus', function () { if (!selected) { search(input.value.trim()); } });
    input.addEventListener('click', function () { if (pop.hidden && !selected) { search(input.value.trim()); } });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (pop.hidden) { search(input.value.trim()); return; }
        if (!items.length) { return; }
        setActive((active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
      } else if (e.key === 'Enter') {
        if (!pop.hidden && active >= 0 && items[active]) { e.preventDefault(); choose(items[active]); }
      } else if (e.key === 'Escape') {
        if (!pop.hidden) { e.preventDefault(); e.stopPropagation(); close(); }
      } else if (e.key === 'Tab') { close(); }
    });
    clearBtn.addEventListener('click', function () { input.value = ''; clear(); input.focus(); });

    return {
      get value() { return selected; },
      setValue: function (it) { selected = it; input.value = o.display(it); clearBtn.hidden = false; wrap.classList.add('has-value'); },
      clear: function () { input.value = ''; clear(true); }, // reset dari kode: tanpa memicu onClear
      focus: function () { input.focus(); }
    };
  };
})();
