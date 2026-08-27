/*
 * Administration navigation intentionally uses normal browser navigation.
 * The previous PJAX layer could leave a stale/transparent busy layer over
 * the panel and make perfectly valid admin links appear unclickable.
 * Native links also preserve forms, CSRF state and server-rendered tab state.
 */
(function () {
  'use strict';
  document.querySelectorAll('.admin-tabs a, .subtabs a').forEach(function (a) {
    a.removeAttribute('data-pjax');
  });
})();
