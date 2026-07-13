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
    window.clearTimeout(layer._closeTimer);
    activeTrigger = trigger || document.activeElement;
    layer.hidden = false;
    body.classList.add('modal-open');
    const panel = layer.querySelector('.modal-panel,.cart-drawer') || layer;
    window.requestAnimationFrame(() => {
      layer.classList.add('is-visible');
      window.setTimeout(() => (panel.querySelector('input,button,a') || panel).focus(), 120);
    });
    const listener = event => {
      if (event.key === 'Escape') closeLayer(layer);
      trap(event, panel);
    };
    layer._keyListener = listener;
    document.addEventListener('keydown', listener);
  };

  const closeLayer = layer => {
    if (!layer || layer.hidden) return;
    layer.classList.remove('is-visible');
    if (layer._keyListener) document.removeEventListener('keydown', layer._keyListener);
    layer._closeTimer = window.setTimeout(() => {
      layer.hidden = true;
      if (![...document.querySelectorAll('.modal-layer,.drawer-layer')].some(item => !item.hidden)) body.classList.remove('modal-open');
      if (activeTrigger?.focus) activeTrigger.focus();
    }, 340);
  };

  const addressForm=document.querySelector('[data-address-form]');
  const prepareNewAddress=()=>{
    if(!addressForm)return;
    addressForm.reset();
    addressForm.action=addressForm.dataset.createAction;
    try{
      const defaults=JSON.parse(addressForm.dataset.defaults||'{}');
      Object.entries(defaults).forEach(([name,value])=>{const input=addressForm.elements.namedItem(name);if(input&&value)input.value=value;});
    }catch{}
    document.querySelector('[data-address-modal-title]').textContent='Adaugă o adresă';
    const submit=addressForm.querySelector('[data-address-submit]');
    if(submit)submit.textContent='Salvează adresa';
  };
  document.addEventListener('click',event=>{
    const add=event.target.closest('[data-address-new]');
    if(add)prepareNewAddress();
    const edit=event.target.closest('[data-address-edit]');
    if(!edit||!addressForm)return;
    prepareNewAddress();
    try{
      const data=JSON.parse(edit.dataset.address||'{}');
      addressForm.action=edit.dataset.action||addressForm.dataset.createAction;
      ['name','contact_first_name','contact_last_name','phone','line1','line2','city','county','postal_code'].forEach(key=>{
        const field=addressForm.elements.namedItem(key);
        if(field)field.value=data[key]||'';
      });
      const defaultField=addressForm.elements.namedItem('is_default');
      if(defaultField)defaultField.checked=Boolean(data.is_default);
      document.querySelector('[data-address-modal-title]').textContent='Editează adresa';
      const submit=addressForm.querySelector('[data-address-submit]');
      if(submit)submit.textContent='Salvează modificările';
    }catch{prepareNewAddress();}
  },true);

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
  const menuBackdrop = document.querySelector('[data-menu-backdrop]');
  const menuLabel = menuToggle?.querySelector('[data-menu-label]');
  let menuCloseTimer = 0;
  const setMobileMenu = open => {
    if (!menuToggle || !mobileMenu) return;
    window.clearTimeout(menuCloseTimer);
    menuToggle.setAttribute('aria-expanded', String(open));
    if (menuLabel) menuLabel.textContent = open ? 'Închide meniul' : 'Deschide meniul';
    document.body.classList.toggle('menu-open', open);
    if (open) {
      mobileMenu.hidden = false;
      if (menuBackdrop) menuBackdrop.hidden = false;
      window.requestAnimationFrame(() => {
        mobileMenu.classList.add('is-open');
        menuBackdrop?.classList.add('is-open');
      });
      return;
    }
    mobileMenu.classList.remove('is-open');
    menuBackdrop?.classList.remove('is-open');
    menuCloseTimer = window.setTimeout(() => {
      if (menuToggle.getAttribute('aria-expanded') === 'false') {
        mobileMenu.hidden = true;
        if (menuBackdrop) menuBackdrop.hidden = true;
      }
    }, 240);
  };
  menuToggle?.addEventListener('click', event => {
    event.stopPropagation();
    setMobileMenu(menuToggle.getAttribute('aria-expanded') !== 'true');
  });
  menuBackdrop?.addEventListener('click', () => setMobileMenu(false));
  mobileMenu?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setMobileMenu(false)));
  document.addEventListener('pointerdown', event => {
    if (menuToggle?.getAttribute('aria-expanded') !== 'true') return;
    if (mobileMenu?.contains(event.target) || menuToggle?.contains(event.target)) return;
    setMobileMenu(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menuToggle?.getAttribute('aria-expanded') === 'true') {
      setMobileMenu(false);
      menuToggle.focus();
    }
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900 && menuToggle?.getAttribute('aria-expanded') === 'true') setMobileMenu(false);
  });

  const catalogFilters = document.getElementById('catalog-filters');
  const catalogFilterBackdrop = document.querySelector('.catalog-filter-backdrop');
  const setCatalogFilters = open => {
    if (!catalogFilters) return;
    catalogFilters.classList.toggle('open', open);
    body.classList.toggle('catalog-filters-open', open);
    document.querySelectorAll('[data-filter-toggle]').forEach(item => item.setAttribute('aria-expanded', String(open)));
    if (catalogFilterBackdrop) catalogFilterBackdrop.hidden = !open;
  };
  document.querySelectorAll('[data-filter-toggle]').forEach(button => button.addEventListener('click', () => setCatalogFilters(!catalogFilters?.classList.contains('open'))));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && catalogFilters?.classList.contains('open')) setCatalogFilters(false); });
  window.addEventListener('resize', () => { if (window.innerWidth > 900 && catalogFilters?.classList.contains('open')) setCatalogFilters(false); });

  const catalogFilterForm = document.querySelector('[data-catalog-filter-form]');
  const filterResultCount = document.querySelector('[data-filter-result-count]');
  const filterResultButton = document.querySelector('[data-filter-result-button]');
  let filterCountTimer = 0;
  let filterCountController = null;
  const refreshFilterResultCount = () => {
    if (!catalogFilterForm || !filterResultCount) return;
    window.clearTimeout(filterCountTimer);
    filterCountTimer = window.setTimeout(async () => {
      filterCountController?.abort();
      filterCountController = new AbortController();
      const params = new URLSearchParams(new FormData(catalogFilterForm));
      params.delete('page');
      if (filterResultButton) filterResultButton.classList.add('is-counting');
      try {
        const response = await fetch(catalogFilterForm.action + (params.toString() ? '?' + params.toString() : ''), {
          headers: {'X-Requested-With':'XMLHttpRequest'},
          signal: filterCountController.signal
        });
        if (!response.ok) return;
        const html = await response.text();
        const page = new DOMParser().parseFromString(html, 'text/html');
        const count = page.querySelector('[data-catalog-total]')?.dataset.catalogTotal;
        if (count !== undefined) filterResultCount.textContent = String(Number(count) || 0);
      } catch (error) {
        if (error.name !== 'AbortError') console.warn('Numărul produselor nu a putut fi actualizat.', error);
      } finally {
        if (filterResultButton) filterResultButton.classList.remove('is-counting');
      }
    }, 180);
  };
  catalogFilterForm?.addEventListener('change', refreshFilterResultCount);
  catalogFilterForm?.addEventListener('input', event => {
    if (event.target.matches('input[type="number"]')) refreshFilterResultCount();
  });
  const searchInput = document.querySelector('[data-search-input]');
  const searchResults = document.querySelector('[data-search-results]');
  let searchTimer = 0;
  searchInput?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    const query = searchInput.value.trim();
    if (query.length < 2) { searchResults.innerHTML = '<p>Scrie cel puțin două caractere.</p>'; return; }
    searchResults.innerHTML = '<p>Se caută în întregul magazin…</p>';
    searchTimer = window.setTimeout(async () => {
      try {
        const response = await fetch(`${window.APP_BASE_PATH || ''}/api/search?q=${encodeURIComponent(query)}`, {headers:{Accept:'application/json'}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Căutarea nu este disponibilă.');
        const allResultsUrl = `${window.APP_BASE_PATH || ''}/shop?q=${encodeURIComponent(query)}`;
        searchResults.innerHTML = data.items.length ? data.items.map(item => `<a class="search-result" href="${item.url}"><img src="${item.image}" alt=""><span><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.category || '')}</small></span><b>${escapeHtml(item.price)}</b></a>`).join('') + `<a class="text-link" href="${allResultsUrl}">Vezi toate rezultatele →</a>` : '<p>Nu am găsit produse pentru această căutare.</p>';
      } catch (error) { searchResults.innerHTML = `<p>${escapeHtml(error.message)}</p>`; }
    }, 300);
  });
  const escapeHtml = value => String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const setHeaderCount = (selector, value) => document.querySelectorAll(selector).forEach(item => { item.textContent = Number(value) > 0 ? String(value) : ''; });
  const setQuickCartState = (productId, active = true) => {
    if (!productId) return;
    document.querySelectorAll('[data-cart-product="' + productId + '"]').forEach(item => {
      item.classList.toggle('active', active);
      item.setAttribute('aria-pressed', String(active));
      const productName = item.closest('.product-card')?.querySelector('h3')?.textContent?.trim() || '';
      item.setAttribute('aria-label', (active ? 'Elimină din coș' : 'Adaugă în coș') + (productName ? ': ' + productName : ''));
    });
  };

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
      setHeaderCount('[data-cart-count]', data.cart_count);
      document.querySelector('[data-cart-added-product]').innerHTML = `<p><strong>${escapeHtml(data.item.name)}</strong><br>${escapeHtml(data.item.variant)} · ${data.item.quantity} buc.</p>`;
      openLayer(document.getElementById('cart-added-modal'), button);
    } catch (error) { toast(error.message, 'error'); }
    finally { button.disabled = false; button.textContent = 'Adaugă în coș'; }
  });

  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-quick-cart]');
    if (!button) return;
    event.preventDefault();
    if (button.disabled) return;
    button.disabled = true;
    button.classList.add('is-loading');
    try {
      const productId = Number(button.dataset.cartProduct || 0);
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/cart/toggle-product`, {method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json'},body:JSON.stringify({variant_id:Number(button.dataset.quickCart),product_id:productId,_csrf:csrf})});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Coșul nu a putut fi actualizat.');
      setHeaderCount('[data-cart-count]', data.cart_count);
      setQuickCartState(data.product_id || productId, Boolean(data.active));
      if (data.active) {
        const added = document.querySelector('[data-cart-added-product]');
        if (added && data.item) added.innerHTML = `<p><strong>${escapeHtml(data.item.name)}</strong><br>${escapeHtml(data.item.variant)} · ${data.item.quantity} buc.</p>`;
        openLayer(document.getElementById('cart-added-modal'), button);
      } else {
        toast('Produs eliminat din coș.');
        loadCartDrawer();
      }
    } catch (error) { toast(error.message, 'error'); }
    finally { button.disabled = false; button.classList.remove('is-loading'); }
  });
  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-wishlist-product]');
    if (!button) return;
    event.preventDefault();
    try {
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/wishlist/toggle`,{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json'},body:JSON.stringify({product_id:Number(button.dataset.wishlistProduct),_csrf:csrf})});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Favoritele nu au putut fi actualizate.');
      document.querySelectorAll(`[data-wishlist-product="${button.dataset.wishlistProduct}"]`).forEach(item => {
        item.classList.toggle('active', data.active);
        item.setAttribute('aria-pressed', String(data.active));
        const productName = item.closest('.product-card')?.querySelector('h3')?.textContent?.trim() || '';
        item.setAttribute('aria-label', `${data.active ? 'Elimină' : 'Adaugă'}${productName ? ` ${productName}` : ''} ${data.active ? 'din' : 'la'} favorite`);
      });
      setHeaderCount('[data-wishlist-count]', data.count);
      toast(data.active ? 'Produs adăugat la favorite.' : 'Produs eliminat din favorite.');
    } catch (error) { toast(error.message, 'error'); }
  });

  async function loadCartDrawer(){
    const target = document.querySelector('[data-cart-drawer-content]'); if (!target) return;
    target.innerHTML = '<p>Se încarcă produsele…</p>';
    try { const response=await fetch(`${window.APP_BASE_PATH || ''}/api/cart`,{headers:{Accept:'application/json'}}); const data=await response.json(); if(!response.ok)throw new Error(data.message||'Coș indisponibil.'); target.innerHTML=data.html; }
    catch(error){target.innerHTML=`<p>${escapeHtml(error.message)}</p>`;}
  }

  document.querySelectorAll('[data-product-gallery]').forEach(gallery => {
    const viewport=gallery.querySelector('[data-gallery-viewport]');
    const track=gallery.querySelector('[data-gallery-track]');
    const slides=[...gallery.querySelectorAll('[data-gallery-slide]')];
    const thumbs=[...gallery.querySelectorAll('[data-gallery-thumb]')];
    const dots=[...gallery.querySelectorAll('[data-gallery-dot]')];
    const status=gallery.querySelector('[data-gallery-status]');
    if(!viewport||!track||!slides.length)return;

    let index=0,startX=0,deltaX=0,dragging=false,suppressClick=false;
    const normalize=value=>(value+slides.length)%slides.length;
    const render=(animate=true)=>{
      track.style.transition=animate?'transform .38s cubic-bezier(.22,.61,.36,1)':'none';
      track.style.transform='translate3d('+(-index*100)+'%,0,0)';
      slides.forEach((slide,i)=>slide.setAttribute('aria-hidden',String(i!==index)));
      thumbs.forEach((thumb,i)=>thumb.classList.toggle('active',i===index));
      dots.forEach((dot,i)=>dot.classList.toggle('active',i===index));
      thumbs[index]?.scrollIntoView({block:'nearest',inline:'nearest',behavior:animate?'smooth':'auto'});
      if(status)status.textContent='Imaginea '+(index+1)+' din '+slides.length;
    };
    const goTo=value=>{index=normalize(value);render();};
    gallery.querySelector('[data-gallery-prev]')?.addEventListener('click',()=>goTo(index-1));
    gallery.querySelector('[data-gallery-next]')?.addEventListener('click',()=>goTo(index+1));
    thumbs.forEach((thumb,i)=>thumb.addEventListener('click',()=>goTo(i)));
    dots.forEach((dot,i)=>dot.addEventListener('click',()=>goTo(i)));

    viewport.addEventListener('keydown',event=>{
      if(event.key==='ArrowLeft'){event.preventDefault();goTo(index-1);}
      if(event.key==='ArrowRight'){event.preventDefault();goTo(index+1);}
    });
    viewport.addEventListener('pointerdown',event=>{
      if(event.button!==0)return;
      dragging=true;startX=event.clientX;deltaX=0;suppressClick=false;
      viewport.classList.add('is-dragging');
      viewport.setPointerCapture?.(event.pointerId);
      track.style.transition='none';
    });
    viewport.addEventListener('pointermove',event=>{
      if(!dragging)return;
      deltaX=event.clientX-startX;
      if(Math.abs(deltaX)>7)suppressClick=true;
      const width=Math.max(1,viewport.clientWidth);
      track.style.transform='translate3d('+((-index*width)+deltaX)+'px,0,0)';
    });
    const finish=event=>{
      if(!dragging)return;
      dragging=false;viewport.classList.remove('is-dragging');
      viewport.releasePointerCapture?.(event.pointerId);
      const threshold=Math.min(90,viewport.clientWidth*.16);
      if(deltaX<=-threshold)index=normalize(index+1);
      else if(deltaX>=threshold)index=normalize(index-1);
      render();
      window.setTimeout(()=>{suppressClick=false;},80);
    };
    viewport.addEventListener('pointerup',finish);
    viewport.addEventListener('pointercancel',finish);
    slides.forEach(slide=>slide.addEventListener('click',event=>{
      if(suppressClick){event.preventDefault();event.stopImmediatePropagation();}
    },true));
    render(false);
  });

  const productLightbox=document.querySelector('[data-lightbox]');
  const lightboxSources=[...document.querySelectorAll('[data-lightbox-src]')];
  const lightboxImage=productLightbox?.querySelector('[data-lightbox-image]');
  const lightboxStage=productLightbox?.querySelector('[data-lightbox-stage]');
  const lightboxZoomOutput=productLightbox?.querySelector('[data-lightbox-zoom]');
  const lightboxStatus=productLightbox?.querySelector('[data-lightbox-status]');
  let lightboxIndex=0,lightboxScale=1,lightboxX=0,lightboxY=0,lightboxPanning=false,lightboxStartX=0,lightboxStartY=0;

  const paintLightbox=()=>{
    if(!lightboxImage)return;
    lightboxImage.style.transform='translate3d('+lightboxX+'px,'+lightboxY+'px,0) scale('+lightboxScale+')';
    lightboxStage?.classList.toggle('is-zoomed',lightboxScale>1);
    if(lightboxZoomOutput)lightboxZoomOutput.textContent=Math.round(lightboxScale*100)+'%';
  };
  const resetLightboxZoom=()=>{lightboxScale=1;lightboxX=0;lightboxY=0;paintLightbox();};
  const setLightboxZoom=value=>{
    lightboxScale=Math.max(1,Math.min(4,Math.round(value*100)/100));
    if(lightboxScale===1){lightboxX=0;lightboxY=0;}
    paintLightbox();
  };
  const renderLightboxSource=()=>{
    const source=lightboxSources[lightboxIndex];
    if(!source||!lightboxImage)return;
    lightboxImage.src=source.dataset.lightboxSrc;
    lightboxImage.alt=source.querySelector('img')?.alt||'Imagine produs mărită';
    resetLightboxZoom();
    if(lightboxStatus)lightboxStatus.textContent='Imaginea '+(lightboxIndex+1)+' din '+lightboxSources.length;
  };
  const moveLightbox=direction=>{
    if(lightboxSources.length<2)return;
    lightboxIndex=(lightboxIndex+direction+lightboxSources.length)%lightboxSources.length;
    renderLightboxSource();
  };
  const openProductLightbox=source=>{
    if(!productLightbox)return;
    const found=lightboxSources.indexOf(source);
    lightboxIndex=found>=0?found:0;
    renderLightboxSource();
    productLightbox.hidden=false;
    body.classList.add('modal-open');
    window.requestAnimationFrame(()=>productLightbox.classList.add('is-visible'));
    productLightbox.querySelector('.lightbox-dialog')?.focus();
  };
  const closeProductLightbox=()=>{
    if(!productLightbox||productLightbox.hidden)return;
    productLightbox.classList.remove('is-visible');
    productLightbox.hidden=true;
    body.classList.remove('modal-open');
    resetLightboxZoom();
  };

  lightboxSources.forEach(button=>button.addEventListener('click',()=>openProductLightbox(button)));
  productLightbox?.querySelectorAll('[data-lightbox-close]').forEach(button=>button.addEventListener('click',closeProductLightbox));
  productLightbox?.querySelector('[data-lightbox-prev]')?.addEventListener('click',()=>moveLightbox(-1));
  productLightbox?.querySelector('[data-lightbox-next]')?.addEventListener('click',()=>moveLightbox(1));
  productLightbox?.querySelector('[data-lightbox-zoom-in]')?.addEventListener('click',()=>setLightboxZoom(lightboxScale+.25));
  productLightbox?.querySelector('[data-lightbox-zoom-out]')?.addEventListener('click',()=>setLightboxZoom(lightboxScale-.25));
  productLightbox?.querySelector('[data-lightbox-reset]')?.addEventListener('click',resetLightboxZoom);
  lightboxStage?.addEventListener('wheel',event=>{
    event.preventDefault();
    setLightboxZoom(lightboxScale+(event.deltaY<0?.25:-.25));
  },{passive:false});
  lightboxStage?.addEventListener('dblclick',()=>setLightboxZoom(lightboxScale>1?1:2));
  lightboxStage?.addEventListener('pointerdown',event=>{
    if(lightboxScale<=1||event.button!==0)return;
    lightboxPanning=true;
    lightboxStartX=event.clientX-lightboxX;
    lightboxStartY=event.clientY-lightboxY;
    lightboxStage.setPointerCapture?.(event.pointerId);
    lightboxStage.classList.add('is-panning');
  });
  lightboxStage?.addEventListener('pointermove',event=>{
    if(!lightboxPanning)return;
    lightboxX=event.clientX-lightboxStartX;
    lightboxY=event.clientY-lightboxStartY;
    paintLightbox();
  });
  const stopLightboxPan=event=>{
    if(!lightboxPanning)return;
    lightboxPanning=false;
    lightboxStage?.releasePointerCapture?.(event.pointerId);
    lightboxStage?.classList.remove('is-panning');
  };
  lightboxStage?.addEventListener('pointerup',stopLightboxPan);
  lightboxStage?.addEventListener('pointercancel',stopLightboxPan);
  document.addEventListener('keydown',event=>{
    if(!productLightbox||productLightbox.hidden)return;
    if(event.key==='Escape')closeProductLightbox();
    if(event.key==='ArrowLeft')moveLightbox(-1);
    if(event.key==='ArrowRight')moveLightbox(1);
    if(event.key==='+'||event.key==='=')setLightboxZoom(lightboxScale+.25);
    if(event.key==='-')setLightboxZoom(lightboxScale-.25);
    if(event.key==='0')resetLightboxZoom();
  });

  document.querySelector('[data-newsletter-form]')?.addEventListener('submit',async event=>{
    event.preventDefault();
    const form=event.currentTarget;
    const button=form.querySelector('button[type="submit"]');
    const original=button?.textContent;
    if(button){button.disabled=true;button.textContent='Se salvează…';}
    try{
      const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});
      const data=await response.json();
      if(!response.ok)throw new Error(data.message||'Abonarea nu a putut fi salvată.');
      toast(data.message||'Te-ai abonat cu succes.');
      form.reset();
    }catch(error){toast(error.message||'Abonarea nu a putut fi salvată.','error');}
    finally{if(button){button.disabled=false;button.textContent=original;}}
  });

  const savedAddressInputs=[...document.querySelectorAll('[data-checkout-address]')];
  const checkoutForm=document.querySelector('[data-checkout-form]');
  const addressLabel=document.querySelector('[data-address-label]');
  const applyCheckoutAddress=()=>{
    if(!savedAddressInputs.length||!checkoutForm)return;
    const option=savedAddressInputs.find(input=>input.checked);
    document.querySelectorAll('.checkout-address-option').forEach(card=>card.classList.toggle('is-selected',card.querySelector('[data-checkout-address]')?.checked));
    if(!option||option.value==='new'){
      ['address','address_2','city','county','postal_code'].forEach(name=>{const input=checkoutForm.elements.namedItem(name);if(input)input.value='';});
      return;
    }
    try{const values=JSON.parse(option.dataset.address||'{}');Object.entries(values).forEach(([name,value])=>{const input=checkoutForm.elements.namedItem(name);if(input)input.value=value||'';});}catch{}
  };
  savedAddressInputs.forEach(input=>input.addEventListener('change',applyCheckoutAddress));
  applyCheckoutAddress();
  checkoutForm?.elements.namedItem('save_address')?.addEventListener('change',event=>{if(addressLabel)addressLabel.hidden=!event.currentTarget.checked;});
  if(checkoutForm){
    const checkoutButton=checkoutForm.querySelector('button[type="submit"]');
    checkoutForm.addEventListener('invalid',event=>{
      event.preventDefault();
      const field=event.target;
      const label=field.closest('label')?.childNodes?.[0]?.textContent?.trim()||'câmp obligatoriu';
      field.closest('.form-card')?.scrollIntoView({behavior:'smooth',block:'center'});
      window.setTimeout(()=>field.focus({preventScroll:true}),350);
      toast(`Completează: ${label}.`,'error');
    },true);
    checkoutForm.addEventListener('submit',async event=>{
      event.preventDefault();
      if(!checkoutButton||checkoutButton.disabled)return;
      const originalLabel=checkoutButton.textContent;
      checkoutButton.disabled=true;
      checkoutButton.classList.add('is-loading');
      checkoutButton.textContent=checkoutForm.elements.namedItem('payment_method')?.value==='stripe'?'Se deschide plata securizată…':'Se plasează comanda…';
      try{
        const response=await fetch(checkoutForm.action,{
          method:'POST',
          body:new FormData(checkoutForm),
          headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
          credentials:'same-origin'
        });
        const contentType=response.headers.get('content-type')||'';
        const data=contentType.includes('application/json')?await response.json():null;
        if(!response.ok){
          if(data?.redirect)window.setTimeout(()=>window.location.assign(data.redirect),900);
          throw new Error(data?.message||'Comanda nu a putut fi plasată. Verifică datele completate.');
        }
        if(!data?.redirect)throw new Error('Nu am primit adresa pentru continuarea plății.');
        window.location.assign(data.redirect);
      }catch(error){
        toast(error.message||'Comanda nu a putut fi plasată.','error');
        checkoutButton.disabled=false;
        checkoutButton.classList.remove('is-loading');
        checkoutButton.textContent=originalLabel;
      }
    });
  }

  document.querySelectorAll('[data-coupon-countdown]').forEach(node=>{
    const deadline=Date.parse(node.dataset.couponCountdown||'');
    if(!Number.isFinite(deadline))return;
    const paint=()=>{const left=deadline-Date.now();if(left<=0){node.textContent='Expirat';return false;}const days=Math.floor(left/86400000);const hours=Math.floor(left%86400000/3600000);const minutes=Math.floor(left%3600000/60000);node.textContent=`Expiră în ${days?days+'z ':''}${String(hours).padStart(2,'0')}h ${String(minutes).padStart(2,'0')}m`;return true;};
    if(paint())window.setInterval(paint,60000);
  });
    const giftForm = document.querySelector('[data-gift-configurator]');
  const giftPicker = giftForm?.querySelector('[data-gift-products-dialog]');
  const giftPickerTrigger = giftForm?.querySelector('[data-gift-products-open]');
  const giftProductCards = giftForm ? [...giftForm.querySelectorAll('[data-gift-product-card]')] : [];
  const giftSearch = giftForm?.querySelector('[data-gift-search]');
  const giftCategory = giftForm?.querySelector('[data-gift-category]');
  const giftMore = giftForm?.querySelector('[data-gift-more]');
  const giftResults = giftForm?.querySelector('[data-gift-results]');
  const giftProductsEmpty = giftForm?.querySelector('[data-gift-products-empty]');
  const giftSelectedList = giftForm?.querySelector('[data-gift-selected-list]');
  let giftVisibleLimit = 12;
  const normalizeGiftText = value => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLocaleLowerCase('ro');
  const filterGiftProducts = (reset = false) => {
    if (!giftForm) return;
    if (reset) giftVisibleLimit = 12;
    const query = normalizeGiftText(giftSearch?.value);
    const category = giftCategory?.value || '';
    const matches = giftProductCards.filter(card => {
      const nameMatch = !query || normalizeGiftText(card.dataset.productName).includes(query);
      const categoryMatch = !category || card.dataset.productCategory === category;
      return nameMatch && categoryMatch;
    });
    giftProductCards.forEach(card => { card.hidden = true; });
    matches.slice(0, giftVisibleLimit).forEach(card => { card.hidden = false; });
    const shown = Math.min(giftVisibleLimit, matches.length);
    if (giftResults) giftResults.textContent = shown + ' din ' + matches.length + ' produse afișate';
    if (giftMore) giftMore.hidden = shown >= matches.length;
    if (giftProductsEmpty) giftProductsEmpty.hidden = matches.length !== 0;
  };
  const renderGiftSelected = (selected, max) => {
    const selectedCount = giftForm?.querySelector('[data-gift-selected-count]');
    const pickerSelected = giftForm?.querySelector('[data-gift-picker-selected]');
    if (selectedCount) selectedCount.textContent = selected.length + (selected.length === 1 ? ' produs ales' : ' produse alese');
    if (pickerSelected) pickerSelected.textContent = selected.length + ' / ' + max + ' produse selectate';
    if (!giftSelectedList) return;
    giftSelectedList.replaceChildren();
    if (!selected.length) {
      const empty = document.createElement('p');
      empty.textContent = 'Produsele selectate vor apărea aici.';
      giftSelectedList.append(empty);
      return;
    }
    selected.forEach(input => {
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.giftRemove = input.value;
      button.setAttribute('aria-label', 'Elimină ' + input.dataset.name);
      const label = document.createElement('span');
      label.textContent = input.dataset.name;
      const remove = document.createElement('b');
      remove.setAttribute('aria-hidden', 'true');
      remove.textContent = '×';
      button.append(label, remove);
      giftSelectedList.append(button);
    });
  };
  const openGiftPicker = () => {
    if (!giftPicker) return;
    giftPicker.hidden = false;
    document.body.classList.add('modal-open');
    filterGiftProducts();
    if (window.matchMedia('(min-width: 761px)').matches) window.setTimeout(() => giftSearch?.focus(), 80);
  };
  const closeGiftPicker = () => {
    if (!giftPicker) return;
    giftPicker.hidden = true;
    document.body.classList.remove('modal-open');
    giftPickerTrigger?.focus();
  };
  giftPickerTrigger?.addEventListener('click', openGiftPicker);
  giftForm?.querySelectorAll('[data-gift-products-close]').forEach(button => button.addEventListener('click', closeGiftPicker));
  giftSearch?.addEventListener('input', () => filterGiftProducts(true));
  giftCategory?.addEventListener('change', () => filterGiftProducts(true));
  giftMore?.addEventListener('click', () => { giftVisibleLimit += 12; filterGiftProducts(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && giftPicker && !giftPicker.hidden) closeGiftPicker(); });
  giftSelectedList?.addEventListener('click', event => {
    const removeButton = event.target.closest('[data-gift-remove]');
    if (!removeButton) return;
    const input = giftProductCards.map(card => card.querySelector('input[name="components[]"]')).find(item => item?.value === removeButton.dataset.giftRemove);
    if (!input) return;
    input.checked = false;
    input.dispatchEvent(new Event('change', {bubbles:true}));
  });
  const giftBoxPicker = giftForm?.querySelector('[data-gift-boxes-dialog]');
  const giftBoxTrigger = giftForm?.querySelector('[data-gift-boxes-open]');
  const giftBoxCards = giftForm ? [...giftForm.querySelectorAll('[data-gift-box-card]')] : [];
  const giftBoxSearch = giftForm?.querySelector('[data-gift-box-search]');
  const giftBoxMore = giftForm?.querySelector('[data-gift-box-more]');
  const giftBoxResults = giftForm?.querySelector('[data-gift-box-results]');
  const giftBoxesEmpty = giftForm?.querySelector('[data-gift-boxes-empty]');
  let giftBoxVisibleLimit = 8;
  const filterGiftBoxes = (reset = false) => {
    if (!giftForm) return;
    if (reset) giftBoxVisibleLimit = 8;
    const query = normalizeGiftText(giftBoxSearch?.value);
    const matches = giftBoxCards.filter(card => !query || normalizeGiftText(card.dataset.boxName).includes(query));
    giftBoxCards.forEach(card => { card.hidden = true; });
    matches.slice(0, giftBoxVisibleLimit).forEach(card => { card.hidden = false; });
    const shown = Math.min(giftBoxVisibleLimit, matches.length);
    if (giftBoxResults) giftBoxResults.textContent = shown + ' din ' + matches.length + ' cutii afișate';
    if (giftBoxMore) giftBoxMore.hidden = shown >= matches.length;
    if (giftBoxesEmpty) giftBoxesEmpty.hidden = matches.length !== 0;
  };
  const renderGiftBox = template => {
    if (!template) return;
    const previewImage = giftForm?.querySelector('[data-gift-box-preview-image]');
    const previewName = giftForm?.querySelector('[data-gift-box-preview-name]');
    const previewMeta = giftForm?.querySelector('[data-gift-box-preview-meta]');
    const selectedLabel = giftForm?.querySelector('[data-gift-box-selected]');
    if (previewImage) {
      previewImage.src = template.dataset.image || '';
      previewImage.alt = template.dataset.name || 'Cutie Gift Box';
    }
    if (previewName) previewName.textContent = template.dataset.name || 'Cutie Gift Box';
    if (previewMeta) previewMeta.textContent = template.dataset.meta || '';
    if (selectedLabel) selectedLabel.textContent = template.dataset.name || 'Cutie selectată';
  };
  const openGiftBoxPicker = () => {
    if (!giftBoxPicker) return;
    giftBoxPicker.hidden = false;
    document.body.classList.add('modal-open');
    filterGiftBoxes();
    if (window.matchMedia('(min-width: 761px)').matches) window.setTimeout(() => giftBoxSearch?.focus(), 80);
  };
  const closeGiftBoxPicker = () => {
    if (!giftBoxPicker) return;
    giftBoxPicker.hidden = true;
    document.body.classList.remove('modal-open');
    giftBoxTrigger?.focus();
  };
  giftBoxTrigger?.addEventListener('click', openGiftBoxPicker);
  giftForm?.querySelectorAll('[data-gift-boxes-close]').forEach(button => button.addEventListener('click', closeGiftBoxPicker));
  giftBoxSearch?.addEventListener('input', () => filterGiftBoxes(true));
  giftBoxMore?.addEventListener('click', () => { giftBoxVisibleLimit += 8; filterGiftBoxes(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && giftBoxPicker && !giftBoxPicker.hidden) closeGiftBoxPicker(); });
  const updateGiftSummary = () => {
    if (!giftForm) return;
    const template = giftForm.querySelector('input[name="template_id"]:checked');
    renderGiftBox(template);
    const selected = [...giftForm.querySelectorAll('input[name="components[]"]:checked')];
    const min = Number(template?.dataset.min || 1);
    const max = Number(template?.dataset.max || 6);
    const total = Number(template?.dataset.price || 0) + selected.reduce((sum, item) => sum + Number(item.dataset.price || 0), 0);
    const limit = giftForm.querySelector('[data-gift-limit]');
    const totalNode = giftForm.querySelector('[data-gift-total]');
    const summary = giftForm.querySelector('[data-gift-summary]');
    const progress = giftForm.querySelector('[data-gift-progress]');
    if (limit) limit.textContent = `Alege între ${min} și ${max} produse pentru cutie.`;
    if (totalNode) totalNode.textContent = formatMoney(total);
    if (progress) progress.textContent = selected.length + ' din maximum ' + max + ' produse selectate';
    renderGiftSelected(selected, max);
    if (summary) summary.textContent = selected.length ? selected.map(item => item.dataset.name).join(', ') : 'Alege produsele pentru cutie.';
    giftForm.querySelectorAll('input[name="components[]"]:not(:checked)').forEach(input => { input.disabled = selected.length >= max; });
  };
  giftForm?.addEventListener('change', updateGiftSummary);
  giftForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const template = form.querySelector('input[name="template_id"]:checked');
    const selected = [...form.querySelectorAll('input[name="components[]"]:checked')];
    const min = Number(template?.dataset.min || 1);
    const max = Number(template?.dataset.max || 6);
    if (!template) { toast('Alege cutia pentru Gift Box.', 'error'); return; }
    if (selected.length < min || selected.length > max) { toast(`Alege între ${min} și ${max} produse pentru cutie.`, 'error'); return; }
    const button = form.querySelector('[type="submit"]');
    button.disabled = true; const giftSubmitText = button.textContent; button.textContent = 'Se salvează…';
    try {
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/gift-box`, {method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json'},body:JSON.stringify({template_id:Number(template.value),components:selected.map(item => Number(item.value)),recipient_name:form.recipient_name?.value || '',gift_message:form.gift_message?.value || '',edit_group:form.edit_group?.value || '',_csrf:csrf})});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Gift Box-ul nu a putut fi adăugat.');
      setHeaderCount('[data-cart-count]', data.cart_count);
      document.querySelector('[data-cart-added-product]').innerHTML = `<p><strong>Gift Box personalizat</strong><br>${data.components.length} produse alese · ${escapeHtml(data.group)}</p>`;
      openLayer(document.getElementById('cart-added-modal'), button);
      if (!form.edit_group?.value) form.reset();
      updateGiftSummary();
    } catch (error) { toast(error.message, 'error'); }
    finally { button.disabled = false; button.textContent = giftSubmitText || 'Adaugă Gift Box în coș'; }
  });
  updateGiftSummary();

  const productDescription=document.querySelector('[data-product-description-content]');
  const productDescriptionMore=document.querySelector('[data-product-description-more]');
  const productDescriptionFade=document.querySelector('[data-product-description-fade]');
  if(productDescription&&productDescriptionMore){
    const descriptionLimit=()=>window.matchMedia('(max-width:760px)').matches?500:620;
    const prepareDescription=()=>{
      const needsCollapse=productDescription.scrollHeight>descriptionLimit()+35;
      productDescriptionMore.hidden=!needsCollapse;
      if(productDescriptionFade)productDescriptionFade.hidden=!needsCollapse;
      if(!needsCollapse){productDescription.classList.remove('is-collapsed');productDescriptionMore.setAttribute('aria-expanded','true');}
      else if(!productDescriptionMore.hasAttribute('aria-expanded'))productDescriptionMore.setAttribute('aria-expanded','false');
    };
    productDescriptionMore.addEventListener('click',()=>{
      const expanded=productDescriptionMore.getAttribute('aria-expanded')==='true';
      productDescriptionMore.setAttribute('aria-expanded',String(!expanded));
      productDescription.classList.toggle('is-collapsed',expanded);
      productDescriptionMore.querySelector('span').textContent=expanded?'Vezi mai mult':'Vezi mai puțin';
      if(productDescriptionFade)productDescriptionFade.hidden=!expanded;
      if(expanded)document.getElementById('descriere')?.scrollIntoView({behavior:'smooth',block:'start'});
    });
    window.requestAnimationFrame(prepareDescription);
    window.addEventListener('load',prepareDescription,{once:true});
  }
  const productTabs=[...document.querySelectorAll('[data-product-tab]')];
  const productSections=productTabs.map(tab=>document.querySelector(tab.getAttribute('href'))).filter(Boolean);
  if(productTabs.length&&productSections.length&&'IntersectionObserver'in window){
    const sectionObserver=new IntersectionObserver(entries=>{const visible=entries.filter(entry=>entry.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];if(!visible)return;productTabs.forEach(tab=>tab.classList.toggle('is-active',tab.getAttribute('href')==='#'+visible.target.id));},{rootMargin:'-25% 0px -60% 0px',threshold:[0,.1,.35]});
    productSections.forEach(section=>sectionObserver.observe(section));
  }
  document.querySelectorAll('.review-rating-field').forEach(field=>{
    const output=field.querySelector('[data-rating-label]');
    field.querySelectorAll('input[name="rating"]').forEach(input=>input.addEventListener('change',()=>{if(output)output.textContent=`${input.value} din 5 stele`;field.classList.add('has-rating');}));
  });})();
