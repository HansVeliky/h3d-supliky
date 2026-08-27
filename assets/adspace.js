/*
 * Free hosting injects a fixed advertising strip at the bottom of every
 * page. It floats above the document, so the footer and the last card end
 * up underneath it. Rather than guessing a height, this measures whatever
 * fixed element is parked at the bottom and is not ours, and publishes it
 * as --ad-bottom for the stylesheets to reserve.
 */
(function measureAdStrip() {
  'use strict';

  function ours(el) {
    return el.closest('.wrap, .workspace, .studio-nav, .canvas-tools, .modal-back, .flyout, .busy-overlay');
  }

  /** How tall el is, if it is a foreign thing parked over the bottom edge. */
  function stripHeight(el) {
    if (!(el instanceof HTMLElement) || ours(el)) return 0;
    const cs = getComputedStyle(el);
    if ((cs.position !== 'fixed' && cs.position !== 'sticky') || cs.display === 'none') return 0;
    const r = el.getBoundingClientRect();
    // Anchored to the bottom edge and wide enough to matter.
    if (r.height < 1 || r.height > 260) return 0;
    if (r.bottom < window.innerHeight - 4) return 0;
    if (r.width < window.innerWidth * 0.5) return 0;
    return Math.round(r.height);
  }

  function measure() {
    let h = 0;

    document.querySelectorAll('body > *').forEach(function (el) {
      h = Math.max(h, stripHeight(el));
      // One level down as well: hosts like to wrap the strip in a div that
      // is not itself fixed, and a scan of body's own children measures
      // nothing at all.
      if (!(el instanceof HTMLElement) || ours(el)) return;
      for (let i = 0; i < el.children.length && i < 12; i++) {
        h = Math.max(h, stripHeight(el.children[i]));
      }
    });

    /*
     * Then ask the browser directly: what is actually covering the bottom
     * edge? Three points across the width, walking up from each to the
     * first fixed ancestor. This finds strips however deeply they are
     * wrapped, and it is what stops the footer hiding under the host's
     * banner without anybody having to measure the banner by hand.
     */
    const y = window.innerHeight - 2;
    [0.2, 0.5, 0.8].forEach(function (frac) {
      let el = document.elementFromPoint(Math.round(window.innerWidth * frac), y);
      for (let up = 0; el && up < 6; up++) {
        const got = stripHeight(el);
        if (got) { h = Math.max(h, got); break; }
        if (ours(el)) break;
        el = el.parentElement;
      }
    });

    document.documentElement.style.setProperty('--ad-bottom', h + 'px');
  }

  measure();
  window.addEventListener('resize', measure);
  window.addEventListener('load', measure);
  // The banner is often injected after the page is parsed.
  if (window.MutationObserver) {
    new MutationObserver(measure).observe(document.body, { childList: true });
  }
  setTimeout(measure, 800);
  setTimeout(measure, 2500);
})();
