/*
 * H3D browser proof-of-work.
 * The challenge id is submitted with the solved answer so the server can
 * always match this widget to its own session challenge.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-captcha]').forEach(function (box) {
    var form = box.closest('form');
    var answerField = box.querySelector('input[name="captcha_answer"]');
    var idField = box.querySelector('input[name="captcha_token"]');
    var text = box.querySelector('.captcha-text');
    var submit = form ? form.querySelector('button[type="submit"], input[type="submit"]') : null;

    if (!answerField || !idField) return;

    var salt = box.dataset.salt || '';
    var target = box.dataset.target || '';
    var range = parseInt(box.dataset.range, 10) || 120000;
    var encoder = new TextEncoder();
    var stopped = false;
    var n = 0;

    if (!window.crypto || !window.crypto.subtle) {
      if (text) text.textContent = 'Verification unavailable. Reload and try again.';
      if (submit) submit.disabled = true;
      return;
    }

    if (submit) submit.disabled = true;

    function sha(value) {
      return crypto.subtle.digest('SHA-256', encoder.encode(salt + value))
        .then(function (buf) {
          var bytes = new Uint8Array(buf);
          var out = '';
          for (var i = 0; i < bytes.length; i++) out += bytes[i].toString(16).padStart(2, '0');
          return out;
        });
    }

    function fail() {
      if (stopped) return;
      stopped = true;
      if (text) text.textContent = 'Verification failed. Reload and try again.';
      if (box) box.classList.add('captcha-fail');
      if (submit) submit.disabled = true;
    }

    function done(answer) {
      if (stopped) return;
      stopped = true;
      answerField.value = String(answer);
      if (text) text.textContent = document.documentElement.lang === 'cs' ? 'Ověření prohlížeče dokončeno' : 'Browser verification complete';
      box.classList.remove('captcha-fail');
      box.classList.add('captcha-ok');
      if (submit) submit.disabled = false;
    }

    function runBatch() {
      if (stopped) return;

      var batch = [];
      for (var i = 0; i < 256 && n < range; i++, n++) batch.push(n);

      if (!batch.length) {
        fail();
        return;
      }

      Promise.all(batch.map(sha)).then(function (hashes) {
        if (stopped) return;

        for (var i = 0; i < hashes.length; i++) {
          if (hashes[i] === target) {
            done(batch[i]);
            return;
          }
        }

        if (text) {
          text.textContent = 'Checking your browser… ' +
            Math.min(99, Math.floor((n / range) * 100)) + '%';
        }
        setTimeout(runBatch, 0);
      }).catch(fail);
    }

    runBatch();
  });
})();
