(() => {
  'use strict';
  const detectedBasePath = (() => {
    const src = document.currentScript?.src || '';
    try {
      const path = new URL(src, location.href).pathname;
      return path.includes('/assets/') ? path.split('/assets/')[0] : '';
    } catch { return ''; }
  })();
  window.APP_BASE_PATH = window.APP_BASE_PATH || detectedBasePath;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const body = document.body;
  const focusable = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  let activeTrigger = null;

  const toast = (message, type = 'info') => {
    const region = document.querySelector('[data-toast-region]');
    if (!region) return;
    const node = document.createElement('div');
    node.className = `toast toast-${type}`;
    node.textContent = message;
    region.append(node);
    window.setTimeout(() => node.remove(), 4300);
  };

  const trap = (event, container) => {
    if (event.key !== 'Tab') return;
    const nodes = [...container.querySelectorAll(focusable)].filter(el => !el.hidden && el.offsetParent !== null);
    if (!nodes.length) return;
    const first = nodes[0], last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  };

  const openLayer = (layer, trigger) => {
    if (!layer) return;
    activeTrigger = trigger || document.activeElement;
    layer.hidden = false;
    body.classList.add('modal-open');
    const panel = layer.querySelector('.modal-panel,.cart-drawer') || layer;
    window.requestAnimationFrame(() => (panel.querySelector('input,button,a') || panel).focus());
    const listener = event => {
      if (event.key === 'Escape') closeLayer(layer);
      trap(event, panel);
    };
    layer._keyListener = listener;
    document.addEventListener('keydown', listener);
  };

  const closeLayer = layer => {
    if (!layer || layer.hidden) return;
    layer.hidden = true;
    if (layer._keyListener) document.removeEventListener('keydown', layer._keyListener);
    if (![...document.querySelectorAll('.modal-layer,.drawer-layer')].some(item => !item.hidden)) body.classList.remove('modal-open');
    if (activeTrigger?.focus) activeTrigger.focus();
  };

  document.addEventListener('click', event => {
    const modalTrigger = event.target.closest('[data-open-modal]');
    if (modalTrigger) openLayer(document.getElementById(modalTrigger.dataset.openModal), modalTrigger);
    const drawerTrigger = event.target.closest('[data-open-drawer]');
    if (drawerTrigger) {
      const drawer = document.getElementById(drawerTrigger.dataset.openDrawer);
      openLayer(drawer, drawerTrigger);
      loadCartDrawer();
    }
    const close = event.target.closest('[data-close-modal],[data-close-drawer]');
    if (close) closeLayer(close.closest('.modal-layer,.drawer-layer'));
  });

  const menuToggle = document.querySelector('[data-menu-toggle]');
  const mobileMenu = document.getElementById('mobile-menu');
  menuToggle?.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!open));
    mobileMenu.hidden = open;
  });

  document.querySelectorAll('[data-filter-toggle]').forEach(button => button.addEventListener('click', () => {
    const filters = document.getElementById('catalog-filters');
    filters?.classList.toggle('open');
    document.querySelector('.filter-trigger')?.setAttribute('aria-expanded', String(filters?.classList.contains('open')));
  }));

  const searchInput = document.querySelector('[data-search-input]');
  const searchResults = document.querySelector('[data-search-results]');
  let searchTimer = 0;
  searchInput?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    const query = searchInput.value.trim();
    if (query.length < 2) { searchResults.innerHTML = '<p>Scrie cel puțin două caractere.</p>'; return; }
    searchResults.innerHTML = '<p>Se caută în colecție…</p>';
    searchTimer = window.setTimeout(async () => {
      try {
        const response = await fetch(`${window.APP_BASE_PATH || ''}/api/search?q=${encodeURIComponent(query)}`, {headers:{Accept:'application/json'}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Căutarea nu este disponibilă.');
        searchResults.innerHTML = data.items.length ? data.items.map(item => `<a class="search-result" href="${item.url}"><img src="${item.image}" alt=""><span><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.category || '')}</small></span><b>${escapeHtml(item.price)}</b></a>`).join('') + `<a class="text-link" href="/shop?q=${encodeURIComponent(query)}">Vezi toate rezultatele →</a>` : '<p>Nu am găsit produse pentru această căutare.</p>';
      } catch (error) { searchResults.innerHTML = `<p>${escapeHtml(error.message)}</p>`; }
    }, 300);
  });

  const escapeHtml = value => String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

  document.querySelectorAll('[data-option-value]').forEach(button => button.addEventListener('click', () => {
    const group = button.closest('[data-option]');
    group.querySelectorAll('[data-option-value]').forEach(item => item.classList.remove('active'));
    button.classList.add('active');
    resolveVariant(button.closest('[data-add-to-cart-form]'));
  }));

  const resolveVariant = form => {
    if (!form) return;
    const selected = [...form.querySelectorAll('[data-option-value].active')].map(item => Number(item.dataset.optionValue)).sort((a,b)=>a-b);
    const variants = JSON.parse(form.querySelector('[data-variants-json]')?.textContent || '[]');
    const match = variants.find(variant => String(variant.option_value_ids || '').split(',').filter(Boolean).map(Number).sort((a,b)=>a-b).join(',') === selected.join(','));
    const input = form.querySelector('[data-variant-id]');
    const price = document.querySelector('[data-product-price]');
    const stock = form.querySelector('[data-stock-note]');
    input.value = match?.id || '';
    if (match && price) price.textContent = formatMoney(match.price_minor);
    if (stock) stock.textContent = match ? (Number(match.stock_qty) > 0 ? `${match.stock_qty} în stoc` : 'Varianta este indisponibilă') : 'Selectează toate opțiunile.';
  };

  const formatMoney = minor => new Intl.NumberFormat('ro-RO',{style:'currency',currency:'RON'}).format(Number(minor)/100);

  document.querySelector('[data-add-to-cart-form]')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.variant_id.value) { toast('Selectează varianta dorită.', 'error'); return; }
    const button = form.querySelector('[type="submit"]');
    button.disabled = true; button.textContent = 'Se adaugă…';
    try {
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/cart/items`,{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json'},body:JSON.stringify({variant_id:Number(form.variant_id.value),quantity:Number(form.quantity.value),_csrf:csrf})});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Produsul nu a putut fi adăugat.');
      document.querySelectorAll('[data-cart-count]').forEach(item => item.textContent = data.cart_count);
      document.querySelector('[data-cart-added-product]').innerHTML = `<p><strong>${escapeHtml(data.item.name)}</strong><br>${escapeHtml(data.item.variant)} · ${data.item.quantity} buc.</p>`;
      openLayer(document.getElementById('cart-added-modal'), button);
    } catch (error) { toast(error.message, 'error'); }
    finally { button.disabled = false; button.textContent = 'Adaugă în coș'; }
  });

  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-wishlist-product]');
    if (!button) return;
    event.preventDefault();
    try {
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/wishlist/toggle`,{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json'},body:JSON.stringify({product_id:Number(button.dataset.wishlistProduct),_csrf:csrf})});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Favoritele nu au putut fi actualizate.');
      document.querySelectorAll(`[data-wishlist-product="${button.dataset.wishlistProduct}"]`).forEach(item => item.classList.toggle('active', data.active));
      document.querySelectorAll('[data-wishlist-count]').forEach(item => item.textContent = data.count);
      toast(data.active ? 'Produs adăugat la favorite.' : 'Produs eliminat din favorite.');
    } catch (error) { toast(error.message, 'error'); }
  });

  async function loadCartDrawer(){
    const target = document.querySelector('[data-cart-drawer-content]'); if (!target) return;
    target.innerHTML = '<p>Se încarcă produsele…</p>';
    try { const response=await fetch(`${window.APP_BASE_PATH || ''}/api/cart`,{headers:{Accept:'application/json'}}); const data=await response.json(); if(!response.ok)throw new Error(data.message||'Coș indisponibil.'); target.innerHTML=data.html; }
    catch(error){target.innerHTML=`<p>${escapeHtml(error.message)}</p>`;}
  }

  document.querySelectorAll('[data-lightbox-src]').forEach(button => button.addEventListener('click', () => {
    const layer = document.querySelector('[data-lightbox]');
    layer.querySelector('[data-lightbox-image]').src = button.dataset.lightboxSrc;
    layer.hidden = false; body.classList.add('modal-open');
  }));
  document.querySelectorAll('[data-lightbox-close]').forEach(button => button.addEventListener('click', () => { document.querySelector('[data-lightbox]').hidden = true; body.classList.remove('modal-open'); }));

  document.querySelector('[data-newsletter-form]')?.addEventListener('submit', event => { event.preventDefault(); toast('Mulțumim. Confirmarea abonării va sosi pe email.'); event.currentTarget.reset(); });
  document.querySelector('[data-gift-configurator]')?.addEventListener('submit', event => { event.preventDefault(); toast('Alege produsele din colecție pentru a continua configurarea.'); });
})();

