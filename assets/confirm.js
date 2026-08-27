/*
 * Confirmation for anything carrying data-confirm.
 *
 * Uses a real dialog rather than window.confirm so the wording can be
 * specific and the browser cannot suppress it after a couple of uses — the
 * "prevent this page from creating more dialogs" checkbox would otherwise
 * silently turn destructive buttons into one-click actions.
 */
(function () {
  'use strict';

  // The admin navigation re-runs behaviour scripts after it swaps a tab.
  // This handler lives on document, so registering it again made one click
  // open several confirmations (and then require the same number of clicks).
  if (window.__h3dConfirmWired) return;
  window.__h3dConfirmWired = true;

  function ask(message, okLabel) {
    return new Promise(function (resolve) {
      var back = document.createElement('div');
      back.className = 'modal-back';

      var box = document.createElement('div');
      box.className = 'modal';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');

      var p = document.createElement('p');
      p.textContent = message;

      var row = document.createElement('div');
      row.className = 'modal-actions';

      var no = document.createElement('button');
      no.type = 'button';
      no.className = 'btn';
      no.textContent = document.documentElement.lang === 'cs' ? 'Zpět' : 'Back';

      var yes = document.createElement('button');
      yes.type = 'button';
      yes.className = 'btn primary';
      yes.textContent = okLabel || (document.documentElement.lang === 'cs' ? 'Potvrdit' : 'Confirm');

      row.appendChild(no);
      row.appendChild(yes);
      box.appendChild(p);
      box.appendChild(row);
      back.appendChild(box);
      document.body.appendChild(back);

      function done(v) {
        document.removeEventListener('keydown', key);
        back.remove();
        resolve(v);
      }
      function key(e) {
        if (e.key === 'Escape') done(false);
        if (e.key === 'Enter') done(true);
      }

      no.addEventListener('click', function () { done(false); });
      yes.addEventListener('click', function () { done(true); });
      back.addEventListener('click', function (e) { if (e.target === back) done(false); });
      document.addEventListener('keydown', key);

      yes.focus();
    });
  }

  // Async forms (the studio support form) need the same dialog too.
  window.H3DConfirm = ask;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm]');
    if (!btn || btn.dataset.confirmed === '1') return;

    e.preventDefault();
    e.stopPropagation();

    ask(btn.dataset.confirm, btn.dataset.confirmOk).then(function (go) {
      if (!go) return;
      // Mark and re-dispatch so the form submits normally, including the
      // button's own name/value if it has one.
      btn.dataset.confirmed = '1';
      btn.click();
      delete btn.dataset.confirmed;
    });
  }, true);
})();
