/* storefront.js — NetBazar progressive enhancement (no framework) */
(function () {
  'use strict';

  /* ── Drawer (mobile category tree) ───────────────────────────────── */
  var drawer = document.getElementById('nb-drawer');

  function openDrawer() {
    if (drawer && drawer.showModal) { drawer.showModal(); }
  }
  function closeDrawer() {
    if (drawer && drawer.close) { drawer.close(); }
  }

  document.querySelectorAll('[data-drawer-open]').forEach(function (btn) {
    btn.addEventListener('click', openDrawer);
  });
  document.querySelectorAll('[data-drawer-close]').forEach(function (btn) {
    btn.addEventListener('click', closeDrawer);
  });

  if (drawer) {
    /* click on the backdrop (outside the panel) closes the drawer */
    drawer.addEventListener('click', function (e) {
      var panel = drawer.querySelector('.nb-drawer__panel');
      if (panel && !panel.contains(e.target)) { closeDrawer(); }
    });
  }

  /* ── Category tree: client-side toggle (progressive enhancement) ─── */
  /* Server renders the correct open/closed state; JS allows the user to
     expand/collapse other branches without a round-trip.               */
  document.querySelectorAll('.nb-tree__row[aria-expanded]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      e.preventDefault();                           /* stop navigation */
      var isOpen = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      var branch = this.nextElementSibling;
      if (branch && branch.classList.contains('nb-tree__branch')) {
        branch.hidden = isOpen;
      }
    });
  });

  /* ── Gallery thumb swap ───────────────────────────────────────────── */
  var mainPhoto = document.getElementById('nb-main-photo');
  document.querySelectorAll('.nb-thumb[data-photo]').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      if (mainPhoto) { mainPhoto.src = this.dataset.photo; }
      document.querySelectorAll('.nb-thumb').forEach(function (t) {
        t.classList.remove('is-active');
      });
      this.classList.add('is-active');
    });
  });

  /* ── Mobile sticky buy bar ───────────────────────────────────────── */
  if (document.querySelector('.nb-pdp__buybar')) {
    document.body.classList.add('nb-has-buybar');
  }

})();
