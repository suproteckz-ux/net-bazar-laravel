/* storefront.js — NetBazar progressive enhancement (no framework) */
(function () {
  'use strict';

  /* ── GA4 helper — silent no-op if gtag not loaded ───────────────── */
  function ga4push(name, params) {
    if (typeof gtag === 'function') { gtag('event', name, params); }
  }

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
     expand/collapse branches without a round-trip.
     The toggle button (.nb-tree__toggler) is separate from the link
     (.nb-tree__link), so clicking the name navigates while the button
     only expands/collapses. No e.preventDefault() needed.             */
  document.querySelectorAll('.nb-tree__toggler').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var isOpen = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      var branchId = this.getAttribute('aria-controls');
      if (branchId) {
        var branch = document.getElementById(branchId);
        if (branch) { branch.hidden = isOpen; }
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

  /* ── WhatsApp button in product cards ────────────────────────────── */
  document.querySelectorAll('.nb-card__wa-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      ga4push('contact_whatsapp', {
        placement: 'product_card',
        item_id: this.dataset.ga4ItemId || undefined,
      });
      window.open(this.dataset.wa, '_blank', 'noopener,noreferrer');
    });
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        e.stopPropagation();
        ga4push('contact_whatsapp', {
          placement: 'product_card',
          item_id: this.dataset.ga4ItemId || undefined,
        });
        window.open(this.dataset.wa, '_blank', 'noopener,noreferrer');
      }
    });
  });

  /* ── WA anchor link tracking (header, footer, drawer, OOS dialog) ── */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-ga4-placement]');
    if (!el) { return; }
    var href = el.getAttribute('href') || '';
    if (href.indexOf('wa.me') === -1) { return; }
    ga4push('contact_whatsapp', {
      placement: el.dataset.ga4Placement,
      item_id:   el.dataset.ga4ItemId || undefined,
    });
  });

  /* ── OOS (out-of-stock) dialog ───────────────────────────────────── */
  var oosDialog = document.getElementById('nb-oos-dialog');
  var oosWaBtn  = document.getElementById('nb-oos-wa-btn');
  var oosClose  = document.getElementById('nb-oos-close');
  var waPhone   = (document.querySelector('meta[name="wa-phone"]') || {}).content || '';

  function openOosDialog(productName, sku) {
    var waText = 'Здравствуйте! Подскажите, когда появится в наличии '
      + productName + ', артикул ' + sku + '?';
    if (oosWaBtn) {
      oosWaBtn.href = 'https://wa.me/' + waPhone + '?text=' + encodeURIComponent(waText);
      oosWaBtn.dataset.ga4ItemId = sku;
    }
    if (oosDialog && oosDialog.showModal) {
      oosDialog.showModal();
    }
  }

  if (oosClose && oosDialog) {
    oosClose.addEventListener('click', function () { oosDialog.close(); });
  }

  if (oosDialog) {
    /* Escape key is handled natively by <dialog>; also close on backdrop click */
    oosDialog.addEventListener('click', function (e) {
      var panel = oosDialog.querySelector('.nb-oos-dialog__panel');
      if (panel && !panel.contains(e.target)) { oosDialog.close(); }
    });
  }

  /* ── AJAX cart forms ─────────────────────────────────────────────── */
  /* Intercepts .nb-cart-form submits, POSTs with Accept: application/json,
     shows OOS dialog on 409, gives inline success feedback on 200.
     Falls back to regular form submit on network error.               */

  function updateCartBadges(count) {
    var badges = document.querySelectorAll('.nb-badge');
    badges.forEach(function (b) { b.textContent = count; });
  }

  document.querySelectorAll('.nb-cart-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var btn         = form.querySelector('[type="submit"]');
      var origText    = btn ? btn.textContent.trim() : '';
      var productName = form.dataset.productName || form.querySelector('[name="sku"]').value;
      var sku         = (form.querySelector('[name="sku"]') || {}).value || '';

      fetch(form.action, {
        method:  'POST',
        headers: { 'Accept': 'application/json' },
        body:    new FormData(form),
      })
      .then(function (res) {
        return res.json().then(function (data) {
          return { status: res.status, data: data };
        });
      })
      .then(function (result) {
        if (result.data.ok) {
          /* Success: brief button feedback */
          if (btn) {
            btn.textContent = 'Добавлено!';
            btn.disabled = true;
            setTimeout(function () {
              btn.textContent = origText;
              btn.disabled = false;
            }, 2200);
          }
          updateCartBadges(result.data.cart_count || 0);
          if (result.data.ga4_item) {
            ga4push('add_to_cart', {
              currency: 'KZT',
              value: (result.data.ga4_item.price || 0) * (result.data.ga4_item.quantity || 1),
              items: [result.data.ga4_item],
            });
          }
        } else {
          /* Error: show OOS dialog with correct WA link */
          openOosDialog(
            result.data.product_name || productName,
            result.data.sku         || sku
          );
        }
      })
      .catch(function () {
        /* Network / parse error: fall back to full-page form submit */
        form.submit();
      });
    });
  });

})();
