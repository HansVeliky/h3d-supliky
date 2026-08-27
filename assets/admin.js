/*
 * Admin panel: make the settings forms answer back.
 *
 * Everything here is presentation only - the server decides what is actually
 * in force, and re-renders the same states on load. This just spares you a
 * save-and-reload to find out what a checkbox does, which is what made the
 * settings screen feel dead.
 */
(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);

  // Per-user management dialogs live one-per-account in the markup. These two
  // helpers open and close them; the credit editor borrows the closer so the
  // two overlays are never stacked on top of one another.
  const userModals = Array.prototype.slice.call(document.querySelectorAll('.user-modal'));
  function closeUserModals() {
    userModals.forEach(function (m) { m.hidden = true; });
    // Actions inside the dialog ran over fetch, so the page behind it still
    // shows the old state (balances, plan end…). Refresh it once on close.
    if (window.__h3dAdminDirty && window.__h3dAdminRefresh) {
      window.__h3dAdminDirty = false;
      window.__h3dAdminRefresh();
    }
  }

  /**
   * "600" -> "10 min", the same way Quota::humanDuration does it.
   *
   * Not "closely enough" any more: the two used to disagree above an hour
   * (90 minutes read "1.5 h" here and "1 h 30 min" from the server), so the
   * sentence changed shape the moment you touched the field.
   */
  function humanDuration(seconds) {
    const s = Math.max(0, Math.round(seconds));
    if (s === 0) return '0 s';
    if (s < 60) return s + ' s';
    if (s < 3600) return Math.ceil(s / 60) + ' min';
    const h = Math.floor(s / 3600);
    const m = Math.round((s % 3600) / 60);
    return m ? h + ' h ' + m + ' min' : h + ' h';
  }

  // --- free exports: show the window fields only when they matter ----------

  const freeUser = document.querySelector('input[name="free_enabled_user"]');
  const freeGuest = document.querySelector('input[name="free_enabled_guest"]');
  const fields = $('windowFields');
  const off = $('windowOff');

  function syncWindow() {
    if (!fields || !off) return;
    const any = (freeUser && freeUser.checked) || (freeGuest && freeGuest.checked);
    fields.hidden = !any;
    off.hidden = any;
  }

  [freeUser, freeGuest].forEach((el) => el && el.addEventListener('change', () => {
    syncWindow();
    syncHints();
  }));

  // --- the "currently: N x per X" lines ------------------------------------

  function hintFor(who) {
    const hint = document.querySelector('[data-window-hint="' + who + '"]');
    const windowEl = document.querySelector('[name="cooldown_' + who + '"]');
    const countEl = document.querySelector('[name="free_per_window_' + who + '"]');
    if (!hint || !windowEl || !countEl) return;

    const enabled = who === 'user'
      ? (freeUser ? freeUser.checked : true)
      : (freeGuest ? freeGuest.checked : true);

    /*
     * The field is in MINUTES - it says so on its label, and the server
     * multiplies it by 60 on the way in. Reading it as seconds made the
     * line under it nonsense: an hour-long window read "za 1 min" and two
     * minutes read "za 2 s".
     */
    const minutes = parseInt(windowEl.value, 10) || 0;
    const count = parseInt(countEl.value, 10) || 0;

    let text;
    if (!enabled) {
      text = who === 'user'
        ? 'Nepoužívá se - volné exporty jsou pro přihlášené vypnuté.'
        : 'Nepoužívá se - volné exporty jsou pro nepřihlášené vypnuté.';
    } else if (minutes === 0) {
      text = 'Okno je 0, takže exporty jsou neomezeně zdarma.';
    } else if (count === 0) {
      text = 'Počet je 0, takže volný export nebude žádný - jen za kredity.';
    } else {
      text = 'Nyní: ' + count + '× za ' + humanDuration(minutes * 60);
    }

    hint.textContent = text;
  }

  function syncHints() {
    hintFor('user');
    hintFor('guest');
  }

  ['cooldown_user', 'free_per_window_user', 'cooldown_guest', 'free_per_window_guest']
    .forEach((name) => {
      const el = document.querySelector('[name="' + name + '"]');
      if (el) el.addEventListener('input', syncHints);
    });

  // --- credits: say plainly when the whole thing is switched off -----------

  const creditsOn = document.querySelector('input[name="credits_enabled"]');
  const costEl = document.querySelector('[name="credit_cost"]');

  function syncCredits() {
    if (!creditsOn || !costEl) return;
    const box = costEl.closest('div');
    if (!box) return;

    let warn = box.querySelector('.credits-off-note');
    const needed = !creditsOn.checked;

    if (needed && !warn) {
      warn = document.createElement('div');
      warn.className = 'hint credits-off-note';
      warn.textContent = 'Kredity jsou vypnuté, takže se tahle cena nepoužije.';
      box.appendChild(warn);
    } else if (!needed && warn) {
      warn.remove();
    }
  }

  if (creditsOn) creditsOn.addEventListener('change', syncCredits);

  // --- unsaved changes: a floating save bar --------------------------------

  (function () {
    const forms = Array.prototype.slice.call(document.querySelectorAll('form'))
      .filter(function (f) { return f.querySelector('[name="action"][value="settings"]'); });
    if (!forms.length) return;

    let activeForm = null;

    // A pill that follows the top of the screen the moment anything changes,
    // so a screenful of edited settings can never look already-saved.
    const bar = document.createElement('div');
    bar.className = 'save-bar';
    bar.hidden = true;
    bar.innerHTML = '<span>Máš neuložené změny.</span>'
      + '<button type="button" class="btn primary small">Uložit</button>';
    document.body.appendChild(bar);

    const saveBtn = bar.querySelector('button');

    function markDirty(form) { activeForm = form; bar.hidden = false; }

    forms.forEach(function (form) {
      form.addEventListener('input', function () { markDirty(form); });
      form.addEventListener('change', function () { markDirty(form); });
      form.addEventListener('submit', function () { bar.hidden = true; });
    });

    saveBtn.addEventListener('click', function () {
      if (!activeForm) return;
      // requestSubmit runs validation and fires submit; submit() is the
      // fallback for older engines.
      if (activeForm.requestSubmit) activeForm.requestSubmit();
      else activeForm.submit();
    });

    window.addEventListener('beforeunload', function (e) {
      if (bar.hidden) return;
      // Losing a screenful of settings to a stray click is worth one prompt.
      e.preventDefault();
      e.returnValue = '';
    });
  })();

  // --- credit dialog -------------------------------------------------------

  /*
   * One number, two meanings: "-50" changes the balance, "50" sets it. Both
   * are things people write, so rather than guessing, the third field shows
   * what the balance becomes. The server reads the sign the same way.
   */
  const dialog = $('creditDialog');

  if (dialog) {
    const form = dialog.querySelector('form');
    const idField = $('creditUserId');
    const email = $('creditEmail');
    const currentField = $('creditCurrent');
    const changeField = $('creditChange');
    const resultField = $('creditResult');
    const reason = $('creditReason');
    const submit = $('creditSubmit');

    let current = 0;
    let opener = null;

    const fmt = (n) => {
      const rounded = Math.round(n * 10) / 10;
      return Number.isInteger(rounded) ? String(rounded) : String(rounded).replace('.', ',');
    };

    const parse = (raw) => {
      const text = String(raw).trim().replace(',', '.').replace(/\s/g, '');
      if (text === '' || text === '+' || text === '-') return null;
      const value = Number(text);
      return Number.isFinite(value) ? { value: value, signed: /^[+-]/.test(text) } : null;
    };

    function update() {
      const parsed = parse(changeField.value);

      if (!parsed) {
        resultField.value = '';
        resultField.className = '';
        submit.disabled = true;
        return;
      }

      const after = parsed.signed ? current + parsed.value : parsed.value;

      resultField.value = fmt(after);
      resultField.className = after < 0 ? 'is-bad' : (after === current ? '' : 'is-ok');
      submit.disabled = after < 0 || after === current;
    }

    function open(btn) {
      // The credit editor is reached from inside a management dialog; close
      // that first so the two overlays do not sit on top of each other, and
      // remember which dialog to bring back after the save re-renders.
      const host = btn.closest('.user-modal');
      const reopenField = document.getElementById('creditReopen');
      if (reopenField) reopenField.value = host ? host.id : '';
      closeUserModals();
      opener = btn;
      current = parseFloat(btn.dataset.current) || 0;

      idField.value = btn.dataset.user;
      email.textContent = btn.dataset.email;
      currentField.value = fmt(current);
      changeField.value = '';
      resultField.value = '';
      resultField.className = '';
      reason.value = '';
      submit.disabled = true;

      dialog.hidden = false;
      setTimeout(function () { changeField.focus(); }, 0);
    }

    function close() {
      dialog.hidden = true;
      if (opener) opener.focus();
    }

    document.querySelectorAll('[data-credit-open]').forEach(function (btn) {
      btn.addEventListener('click', function () { open(btn); });
    });

    dialog.querySelectorAll('[data-credit-close]').forEach(function (btn) {
      btn.addEventListener('click', close);
    });

    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) close();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !dialog.hidden) close();
    });

    changeField.addEventListener('input', update);

    form.addEventListener('submit', function () {
      // panel.js only wires forms that carry data-busy at load, and this one
      // gets it too late, so the overlay is triggered directly. The button is
      // disabled to make the click register and to block a double submit.
      submit.disabled = true;
      if (window.H3D_BUSY) window.H3D_BUSY.show('Upravuji kredity…');
    });
  }

  // --- per-user management dialog ------------------------------------------

  // Full-screen user detail: tab switching plus a one-off fetch of the data
  // panes (ledger, orders, mails…) so the users list itself stays light.
  function initUserDetail(modal) {
    const bar = modal.querySelector('.ud-tabs');
    const activatePane = function (key) {
      if (!key) return;
      const target = bar && bar.querySelector('[data-udtab="' + key + '"]');
      if (!target) return;
      bar.querySelectorAll('[data-udtab]').forEach(function (x) {
        x.classList.toggle('is-on', x === target);
      });
      modal.querySelectorAll('.ud-pane').forEach(function (p) {
        p.hidden = p.dataset.udpane !== key;
      });
    };
    if (bar && !bar.dataset.wired) {
      bar.dataset.wired = '1';
      bar.addEventListener('click', function (e) {
        const b = e.target.closest('[data-udtab]');
        if (!b) return;
        activatePane(b.dataset.udtab);
      });
    }
    // A link from the support inbox can land directly in a user's
    // conversation archive instead of opening the generic management tab.
    activatePane(modal.dataset.autoPane || '');
    const rem = modal.querySelector('.ud-remote[data-detail-url]');
    if (rem && !rem.dataset.loaded) {
      rem.dataset.loaded = '1';
      fetch(rem.dataset.detailUrl, { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.text(); })
        .then(function (html) {
          rem.innerHTML = html;
          const act = modal.querySelector('.ud-tabs [data-udtab].is-on');
          const key = act ? act.dataset.udtab : 'sprava';
          modal.querySelectorAll('.ud-pane').forEach(function (p) {
            p.hidden = p.dataset.udpane !== key;
          });
        })
        .catch(function () {
          rem.dataset.loaded = '';
          rem.innerHTML = '<p class="hint">Detail se nepodařilo načíst. Zavři a otevři dialog znovu.</p>';
        });
    }
  }

  // A short-lived message inside the dialog, replacing the page-level flash
  // the fetch flow never navigates to.
  function modalToast(modal, text, isErr) {
    const box = modal.querySelector('.modal') || modal;
    const old = box.querySelector('.ud-toast');
    if (old) old.remove();
    const el = document.createElement('div');
    el.className = 'ud-toast' + (isErr ? ' err' : '');
    el.textContent = text;
    box.appendChild(el);
    setTimeout(function () { el.remove(); }, 5000);
  }

  // Actions fired from inside a user dialog run over fetch: no page reload,
  // nothing jumps - the dialog stays open on the same tab and refreshes.
  function refreshModalFromHtml(modal, html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const msg = doc.querySelector('.msg');
    modalToast(modal, msg ? msg.textContent.trim() : 'Hotovo.', !!(msg && msg.classList.contains('err')));

    const fresh = doc.getElementById(modal.id);
    if (fresh) {
      const curS = modal.querySelector('.ud-pane[data-udpane="sprava"]');
      const newS = fresh.querySelector('.ud-pane[data-udpane="sprava"]');
      if (curS && newS) { newS.hidden = curS.hidden; curS.replaceWith(newS); }
      const curT = modal.querySelector('.user-modal-tags');
      const newT = fresh.querySelector('.user-modal-tags');
      if (curT && newT) curT.replaceWith(newT);
    }
    const rem = modal.querySelector('.ud-remote');
    if (rem) {
      rem.dataset.loaded = '';
      modal.querySelectorAll('.ud-remote .ud-pane').forEach(function (p) { p.remove(); });
    }
    initUserDetail(modal);
  }

  if (!window.__h3dModalAjax) {
    window.__h3dModalAjax = true;
    document.addEventListener('submit', function (e) {
      const form = e.target;
      const modal = form.closest('.user-modal');
      const creditBack = form.closest('.credit-backdrop');
      if (!modal && !creditBack) return;
      const action = (form.querySelector('input[name="action"]') || {}).value || '';
      // Deleting or anonymising removes the account, so the page really
      // does need to reload for those two.
      if (action === 'delete_user' || action === 'anonymise_user') return;
      e.preventDefault();
      const body = new URLSearchParams(new FormData(form));
      fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        body: body.toString(),
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          let target = modal;
          if (creditBack) {
            // Close the credit editor and land back in the dialog it came from.
            creditBack.hidden = true;
            const reopenField = document.getElementById('creditReopen');
            if (reopenField && reopenField.value) {
              target = document.getElementById(reopenField.value);
              if (target) target.hidden = false;
            }
          }
          window.__h3dAdminDirty = true;
          if (target) refreshModalFromHtml(target, html);
        })
        .catch(function () { form.submit(); });
    });
  }

  if (userModals.length) {
    document.querySelectorAll('[data-user-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const modal = document.getElementById(btn.dataset.userOpen);
        if (!modal) return;
        closeUserModals();
        modal.hidden = false;
        initUserDetail(modal);
      });
    });

    document.querySelectorAll('[data-user-close]').forEach(function (btn) {
      btn.addEventListener('click', closeUserModals);
    });

    // A click on the dimmed backdrop, but not inside the dialog, dismisses it.
    userModals.forEach(function (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeUserModals();
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeUserModals();
    });

    // An action submitted from inside the dialog re-renders the page; the
    // server marks the dialog it came from so it opens right back up.
    const reopen = document.querySelector('.user-modal[data-auto-open]');
    if (reopen) {
      reopen.hidden = false;
      initUserDetail(reopen);
      // Drop the marker from the address so a later refresh or a copied
      // link does not keep popping the dialog open.
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete('reopen');
        history.replaceState(null, '', url.pathname + url.search);
      } catch (_) {}
    }
  }

  // --- editable card lists: packages, payment methods, currencies ----------

  /*
   * Three screens are the same shape: a list of cards, each opening its own
   * dialog, and the order of the cards is the order the customer sees. The
   * behaviour is written once and wired by data attributes, so a fourth list
   * needs markup only.
   *
   *   [data-edit-open="id"]  opens the dialog with that id
   *   [data-edit-close]      closes whatever dialog is open
   *   .edit-modal            one such dialog
   *   [data-sortable]        a list of draggable cards
   *     data-sort-action     POST action that stores the new order
   *     [data-sort-id]       one card; the value is what gets posted
   */
  (function initCardLists() {
    const csrfEl = document.querySelector('input[name="csrf"]');
    const csrf = csrfEl ? csrfEl.value : '';
    const endpoint = window.location.pathname + window.location.search;

    const modals = Array.prototype.slice.call(document.querySelectorAll('.edit-modal'));
    function closeModals() { modals.forEach(function (m) { m.hidden = true; }); }

    if (modals.length) {
      document.querySelectorAll('[data-edit-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const m = document.getElementById(btn.dataset.editOpen);
          if (!m) return;
          closeModals();
          m.hidden = false;
          // Land in the first field: the dialog is a form, and reaching for
          // the mouse to start typing is the thing this replaced.
          const first = m.querySelector('input:not([type=hidden]),select,textarea');
          if (first) first.focus();
        });
      });
      document.querySelectorAll('[data-edit-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModals);
      });
      modals.forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) closeModals(); });
      });

      // Bound once for the whole page, not once per tab: this script is
      // re-run every time the panel swaps a tab in, and a per-run listener
      // would pile up one Escape handler per visited tab.
      if (!window.__h3dEditEscape) {
        window.__h3dEditEscape = true;
        document.addEventListener('keydown', function (e) {
          if (e.key !== 'Escape') return;
          document.querySelectorAll('.edit-modal').forEach(function (m) { m.hidden = true; });
        });
      }
    }

    // Drag the cards to reorder them; the new order is what the customer sees.
    document.querySelectorAll('[data-sortable]').forEach(function (grid) {
      const action = grid.dataset.sortAction || '';
      if (!action) return;

      let dragEl = null;

      const elemAfter = function (y) {
        const others = Array.prototype.slice.call(grid.querySelectorAll('[data-sort-id]:not(.dragging)'));
        let closest = null, closestOffset = -Infinity;
        others.forEach(function (el) {
          const box = el.getBoundingClientRect();
          const offset = y - box.top - box.height / 2;
          if (offset < 0 && offset > closestOffset) { closestOffset = offset; closest = el; }
        });
        return closest;
      };

      const persistOrder = function () {
        const ids = Array.prototype.slice.call(grid.querySelectorAll('[data-sort-id]'))
          .map(function (el) { return el.dataset.sortId; });

        const params = new URLSearchParams();
        params.set('action', action);
        params.set('ajax', '1');
        params.set('csrf', csrf);
        ids.forEach(function (id) { params.append('order[]', id); });

        fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
          body: params.toString(),
        }).then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
          .catch(function () {});
      };

      grid.querySelectorAll('.drag-handle').forEach(function (handle) {
        const el = handle.closest('[data-sort-id]');
        if (!el) return;
        handle.addEventListener('dragstart', function (e) {
          dragEl = el;
          el.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
          try { e.dataTransfer.setData('text/plain', el.dataset.sortId || ''); } catch (_) {}
        });
        handle.addEventListener('dragend', function () {
          if (dragEl) dragEl.classList.remove('dragging');
          dragEl = null;
          persistOrder();
        });
      });

      grid.addEventListener('dragover', function (e) {
        if (!dragEl) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const after = elemAfter(e.clientY);
        if (after == null) grid.appendChild(dragEl);
        else if (after !== dragEl) grid.insertBefore(dragEl, after);
      });
    });
  })();

  // --- order dialog: ready-made replies fill the note ----------------------

  // The same handful of sentences get typed over and over ("payment arrived",
  // "wrong variable symbol"). Picking one drops it into the field, where it
  // can still be edited - it is a starting point, not a fixed choice.
  document.querySelectorAll('.reply-picker').forEach(function (sel) {
    const field = document.getElementById(sel.dataset.replyFor);
    if (!field) return;
    sel.addEventListener('change', function () {
      if (sel.value === '') return;
      field.value = sel.value;
      field.focus();
      sel.selectedIndex = 0;   // back to "own text" for the next pick
    });
  });

  // --- payment method dialog: what "cíl" means depends on the type ---------

  // Nine kinds with nine different targets - a user name, an account number,
  // a URL. Showing all nine hints at once was the old form's way; here only
  // the one for the chosen type is on screen.
  document.querySelectorAll('select[data-kind-hint]').forEach(function (sel) {
    const hint = document.getElementById(sel.dataset.kindHint);
    if (!hint) return;
    const sync = function () {
      const opt = sel.options[sel.selectedIndex];
      hint.textContent = opt ? (opt.dataset.hint || '') : '';
    };
    sel.addEventListener('change', sync);
    sync();
  });

  // --- order story popup: the ⓘ button next to an order's status -----------

  // The row stays short; dates, amounts and the refund trail open on demand.
  // Delegated once per page (the flag survives PJAX re-runs of this script).
  if (!window.__h3dOrderInfo) {
    window.__h3dOrderInfo = true;
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-order-info]');
      if (!btn) return;
      e.preventDefault();

      let data;
      try { data = JSON.parse(btn.dataset.orderInfo); } catch (_) { return; }

      const back = document.createElement('div');
      back.className = 'modal-back oinfo-back';

      const box = document.createElement('div');
      box.className = 'modal oinfo-modal';

      const head = document.createElement('div');
      head.className = 'oinfo-head';
      const h = document.createElement('h3');
      h.textContent = data.title || 'Objednávka';
      const x = document.createElement('button');
      x.type = 'button';
      x.className = 'btn small';
      x.textContent = 'Zavřít';
      head.appendChild(h);
      head.appendChild(x);

      const table = document.createElement('table');
      table.className = 'ud-kv oinfo-kv';
      (data.rows || []).forEach(function (row) {
        const tr = document.createElement('tr');
        const th = document.createElement('th');
        th.textContent = row[0];
        const td = document.createElement('td');
        td.textContent = row[1];
        tr.appendChild(th);
        tr.appendChild(td);
        table.appendChild(tr);
      });

      box.appendChild(head);
      box.appendChild(table);
      back.appendChild(box);
      document.body.appendChild(back);

      function close() {
        back.remove();
        document.removeEventListener('keydown', onKey);
      }
      function onKey(ev) { if (ev.key === 'Escape') close(); }
      x.addEventListener('click', close);
      back.addEventListener('click', function (ev) { if (ev.target === back) close(); });
      document.addEventListener('keydown', onKey);
    });
  }

  // --- generic dialogs: [data-open-modal="id"] / [data-close-modal] --------

  (function () {
    const openers = document.querySelectorAll('[data-open-modal]');
    if (!openers.length) return;
    const modals = Array.prototype.slice.call(document.querySelectorAll('.modal-back.js-modal'));
    function closeAll() { modals.forEach(function (m) { m.hidden = true; }); }

    openers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const m = document.getElementById(btn.dataset.openModal);
        if (!m) return;
        closeAll();
        m.hidden = false;
      });
    });
    document.querySelectorAll('[data-close-modal]').forEach(function (b) {
      b.addEventListener('click', closeAll);
    });
    modals.forEach(function (m) {
      m.addEventListener('click', function (e) { if (e.target === m) closeAll(); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
  })();

  syncWindow();
  // Deliberately NOT syncHints() here. The server already wrote a fuller
  // sentence under each field ("5x zdarma za 1 h, další za 1 kredit");
  // running the script's shorter version at load only replaced it with less
  // information. It takes over once something is actually edited.
  syncCredits();
})();

/* Inline editing of the internal support title. */
document.addEventListener('click', function (event) {
  const edit = event.target.closest('[data-support-title-edit]');
  if (edit) {
    const wrap = edit.closest('.support-title-wrap');
    if (!wrap) return;
    const view = wrap.querySelector('.support-admin-title');
    const form = wrap.querySelector('[data-support-title-form]');
    if (view && form) { view.hidden = true; form.hidden = false; const input = form.querySelector('input[name="admin_title"]'); if (input) { input.focus(); input.select(); } }
    return;
  }
  const cancel = event.target.closest('[data-support-title-cancel]');
  if (cancel) {
    const wrap = cancel.closest('.support-title-wrap');
    if (!wrap) return;
    const view = wrap.querySelector('.support-admin-title');
    const form = wrap.querySelector('[data-support-title-form]');
    if (view && form) { form.hidden = true; view.hidden = false; }
  }
});

/* ---- Chips editor -------------------------------------------------------
 * A comma-separated setting, shown as removable bubbles.
 *
 * The text field stays the single source of truth and keeps its name, so the
 * form posts exactly what it always posted; the bubbles are a view of it.
 * With JavaScript off the field is simply visible and editable by hand,
 * which is the whole reason the value is not moved into hidden inputs.
 */
(function initChips(){
  document.querySelectorAll('.chips-editor').forEach(function(box){
    var field = box.querySelector('input[type="text"][name]');
    var input = box.querySelector('.chips-input');
    var list  = box.querySelector('.chips-list');
    var add   = box.querySelector('[data-chips-add]');
    if(!field || !input || !list) return;

    // The field itself is the storage; the bubbles replace it on screen.
    field.classList.add('chips-source');

    function values(){
      return field.value.split(',')
        .map(function(s){ return s.trim(); })
        .filter(function(s){ return s !== ''; });
    }
    function write(list2){
      // Case-insensitive de-duplication, order kept: the first spelling wins.
      var seen = Object.create(null), out = [];
      list2.forEach(function(v){
        var k = v.toLowerCase();
        if(!seen[k]){ seen[k] = 1; out.push(v); }
      });
      field.value = out.join(',');
      paint();
    }
    function paint(){
      list.textContent = '';
      values().forEach(function(v){
        var chip = document.createElement('span');
        chip.className = 'chip';
        var text = document.createElement('span');
        text.textContent = v;
        chip.appendChild(text);
        var x = document.createElement('button');
        x.type = 'button';
        x.className = 'chip-x';
        x.setAttribute('aria-label', 'Odebrat ' + v);
        x.textContent = '×';
        x.addEventListener('click', function(){
          write(values().filter(function(item){ return item !== v; }));
          input.focus();
        });
        chip.appendChild(x);
        list.appendChild(chip);
      });
    }
    function addValue(){
      var v = input.value.trim().replace(/,/g, '');
      if(v === '') return;
      write(values().concat([v]));
      input.value = '';
    }
    input.addEventListener('keydown', function(e){
      if(e.key === 'Enter' || e.key === ','){ e.preventDefault(); addValue(); }
      // Backspace on an empty field takes the last bubble back, the way
      // every tag field people already know behaves.
      else if(e.key === 'Backspace' && input.value === ''){
        var v = values();
        if(v.length){ input.value = v[v.length - 1]; write(v.slice(0, -1)); }
      }
    });
    if(add) add.addEventListener('click', addValue);
    // Anything typed straight into the source field still shows up.
    field.addEventListener('input', paint);
    paint();
  });
})();
