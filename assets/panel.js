/*
 * Panel pages: say something while the server is thinking.
 *
 * Placing an order writes rows, builds a payment string and sends mail, which
 * takes long enough that a still page reads as a broken one. Anything that
 * submits shows a dimmed overlay with a line describing the operation, so
 * the wait is visibly the machine working rather than nothing happening.
 */
(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);

  // ---- busy overlay -------------------------------------------------------

  let overlay = null;

  function ensureOverlay() {
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'busy-backdrop';
    overlay.hidden = true;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML =
      '<div class="busy-box">' +
      '<div class="busy-spinner" aria-hidden="true"></div>' +
      '<div class="busy-text"></div>' +
      '</div>';

    document.body.appendChild(overlay);
    return overlay;
  }

  let busyWatchdog = null;
  let busySlow = null;

  function showBusy(text) {
    const el = ensureOverlay();
    const label = el.querySelector('.busy-text');
    label.textContent = text;
    el.hidden = false;

    // Shared hosting sometimes takes its time, and a spinner that never
    // lifts reads as a dead page. After a few seconds this says so and
    // offers a way out - the work itself has usually finished on the
    // server by then, only the answer is late.
    clearTimeout(busyWatchdog);
    clearTimeout(busySlow);
    busySlow = setTimeout(function () {
      label.textContent = text + ' - trvá to déle než obvykle. '
        + 'Akce nejspíš proběhla; zavři tohle a obnov stránku.';
      el.classList.add('is-slow');
    }, 4000);
    busyWatchdog = setTimeout(hideBusy, 9000);
  }

  function hideBusy() {
    clearTimeout(busyWatchdog);
    clearTimeout(busySlow);
    if (overlay) {
      overlay.hidden = true;
      overlay.classList.remove('is-slow');
    }
  }

  // Exposed so the sign-in dialog in the studio can use the same overlay.
  window.H3D_BUSY = { show: showBusy, hide: hideBusy };

  // A click always gets rid of it. Being stuck behind a grey sheet with no
  // way out is worse than seeing a page whose result is not in yet.
  document.addEventListener('click', function (e) {
    if (overlay && !overlay.hidden && e.target.closest('.busy-backdrop')) hideBusy();
  });

  /*
   * Any form can say what it is doing with data-busy. Forms without it stay
   * silent: a message that says nothing useful is worse than none, and most
   * of these submit instantly.
   */
  document.querySelectorAll('form[data-busy]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      /*
       * The check waits a tick on purpose. Listeners run in the order they
       * were registered, so reading defaultPrevented immediately only sees
       * cancellations from handlers added before this one - a validation
       * script loaded later would cancel the submit and still leave a
       * spinner over a page going nowhere. By the next tick every handler
       * has had its say.
       */
      setTimeout(() => {
        if (e.defaultPrevented) return;
        showBusy(form.dataset.busy);
      }, 0);
    });
  });

  // Coming back through the history cache would otherwise leave the overlay
  // stuck over a page that is no longer loading.
  window.addEventListener('pageshow', hideBusy);

  // ---- order confirmation -------------------------------------------------

  const dialog = $('orderDialog');
  if (!dialog) return;

  function close() {
    dialog.hidden = true;

    // Drop the marker from the address so a refresh does not reopen it.
    const url = new URL(window.location.href);
    url.searchParams.delete('ordered');
    history.replaceState(null, '', url.pathname + url.search);
  }

  dialog.querySelectorAll('[data-close]').forEach((el) =>
    el.addEventListener('click', close)
  );

  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) close();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !dialog.hidden) close();
  });

  const first = dialog.querySelector('a, button');
  if (first) first.focus();
})();
