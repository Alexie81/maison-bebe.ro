(() => {
  'use strict';

  const sentEvents = window.__MAISON_GA4_EVENTS__ = window.__MAISON_GA4_EVENTS__ || [];
  const safeJson = value => {
    try { return JSON.parse(value || '{}'); } catch { return null; }
  };
  const clone = value => safeJson(JSON.stringify(value)) || {};
  const measurementId = () => document.querySelector('meta[name="google-analytics-measurement-id"]')?.content?.trim()
    || document.querySelector('script[src*="googletagmanager.com/gtag/js?id="]')?.src?.match(/[?&]id=([^&]+)/)?.[1]
    || '';
  const itemFrom = element => {
    const host = element?.closest?.('[data-ga4-item]') || element;
    return safeJson(host?.dataset?.ga4Item || '');
  };
  const validItems = items => (Array.isArray(items) ? items : []).filter(item => item && item.item_id && item.item_name);
  const payloadForItems = (items, extras = {}) => {
    const normalized = validItems(items).map((item, index) => ({...clone(item), index, quantity: Math.max(1, Number(item.quantity || 1))}));
    const value = normalized.reduce((total, item) => total + Math.max(0, Number(item.price || 0) - Number(item.discount || 0)) * item.quantity, 0);
    return {currency:'RON', value:Number(value.toFixed(2)), ...clone(extras), items:normalized};
  };
  const emit = (name, params = {}, options = {}) => {
    if (!name || !params || typeof params !== 'object') return false;
    const payload = clone(params);
    const destination = measurementId();
    if (destination && !payload.send_to) payload.send_to = destination;
    if (window.__MAISON_GA4_DEBUG__) payload.debug_mode = true;
    sentEvents.push({name, params:payload, timestamp:Date.now()});
    document.dispatchEvent(new CustomEvent('maison:ga4', {detail:{name, params:payload}}));
    if (typeof window.gtag !== 'function') {
      options.callback?.();
      return false;
    }
    if (options.callback) {
      let completed = false;
      const done = () => { if (completed) return; completed = true; options.callback(); };
      payload.event_callback = done;
      payload.event_timeout = Math.max(300, Number(options.timeout || 900));
      payload.transport_type = 'beacon';
      window.setTimeout(done, payload.event_timeout + 80);
    }
    window.gtag('event', name, payload);
    return true;
  };
  const emitThenNavigate = (events, url) => {
    const valid = (Array.isArray(events) ? events : []).filter(event => event?.name && event?.params);
    if (!valid.length) { window.location.assign(url); return; }
    valid.slice(0, -1).forEach(event => emit(event.name, event.params));
    const last = valid[valid.length - 1];
    emit(last.name, last.params, {callback:() => window.location.assign(url), timeout:850});
  };
  const updateProductVariant = (form, variant, priceMinor) => {
    const current = itemFrom(form);
    if (!current || !variant) return;
    current.item_id = String(variant.sku || current.item_id);
    current.price = Number((Number(priceMinor || variant.price_minor || 0) / 100).toFixed(2));
    const label = String(variant.option_label || '').trim();
    if (label && label.toLowerCase() !== 'standard') current.item_variant = label;
    else delete current.item_variant;
    form.dataset.ga4Item = JSON.stringify(current);
  };

  window.MaisonGA4 = {
    event: emit,
    emitThenNavigate,
    itemFrom,
    payloadForItems,
    updateProductVariant,
    events: sentEvents,
  };

  const populateCheckoutIdentifiers = form => {
    if (!form || typeof window.gtag !== 'function') return;
    const clientInput = form.elements.namedItem('ga_client_id');
    const sessionInput = form.elements.namedItem('ga_session_id');
    const destination = measurementId();
    if (!destination) return;
    window.gtag('get', destination, 'client_id', value => { if (clientInput && /^\d+\.\d+$/.test(String(value || ''))) clientInput.value = value; });
    window.gtag('get', destination, 'session_id', value => { if (sessionInput && /^\d+$/.test(String(value || ''))) sessionInput.value = value; });
  };
  const checkoutForm = document.querySelector('[data-checkout-form]');
  if (checkoutForm) {
    populateCheckoutIdentifiers(checkoutForm);
    checkoutForm.addEventListener('submit', () => populateCheckoutIdentifiers(checkoutForm), true);
  }

  document.querySelectorAll('[data-ga4-auto-event]').forEach(node => {
    const name = node.dataset.ga4AutoEvent || '';
    const payload = safeJson(node.textContent);
    if (!name || !payload) return;
    const once = node.dataset.ga4Once || '';
    if (once) {
      const key = 'maison_ga4_' + once;
      try { if (window.localStorage.getItem(key) === 'sent') return; } catch {}
      emit(name, payload);
      try { window.localStorage.setItem(key, 'sent'); } catch {}
      return;
    }
    emit(name, payload);
  });

  document.querySelectorAll('[data-ga4-product-detail][data-ga4-item]').forEach(node => {
    const item = itemFrom(node);
    if (item) emit('view_item', payloadForItems([item]));
  });

  document.querySelectorAll('[data-ga4-list]').forEach(list => {
    const listId = list.dataset.ga4List || '';
    const listName = list.dataset.ga4ListName || listId;
    const items = [...list.querySelectorAll('[data-ga4-item]')].map((node, index) => {
      const item = itemFrom(node);
      return item ? {...item, index, item_list_id:listId, item_list_name:listName} : null;
    }).filter(Boolean);
    if (items.length) emit('view_item_list', {item_list_id:listId, item_list_name:listName, items});
  });

  document.querySelectorAll('[data-ga4-promotion]').forEach(node => {
    const promotion = safeJson(node.dataset.ga4Promotion || '');
    if (promotion) emit('view_promotion', {...promotion, items:[]});
  });

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-ga4-select]');
    if (!trigger) return;
    const item = itemFrom(trigger);
    if (!item) return;
    emit('select_item', {
      item_list_id:item.item_list_id || trigger.closest('[data-ga4-list]')?.dataset.ga4List || 'catalog',
      item_list_name:item.item_list_name || trigger.closest('[data-ga4-list]')?.dataset.ga4ListName || 'Catalog',
      items:[item],
    });
  });
  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-ga4-promotion-select]');
    const promotion = safeJson(trigger?.closest('[data-ga4-promotion]')?.dataset.ga4Promotion || '');
    if (promotion) emit('select_promotion', {...promotion, items:[]});
  });

  const consentCookie = 'maison_consent_v2';
  const readConsent = () => {
    const stored = document.cookie.split(';').map(value => value.trim()).find(value => value.startsWith(consentCookie + '='))?.split('=')[1] || '';
    try { return decodeURIComponent(stored); } catch { return stored; }
  };
  const writeConsent = value => {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${consentCookie}=${encodeURIComponent(value)}; Max-Age=31536000; Path=/; SameSite=Lax${secure}`;
  };
  const consentLayer = document.querySelector('[data-consent-layer]');
  const consentPanel = consentLayer?.querySelector('[data-consent-options]');
  const storedConsent = readConsent();
  const analyticsChoice = consentLayer?.querySelector('[name="consent_analytics"]');
  const adsChoice = consentLayer?.querySelector('[name="consent_ads"]');
  if (analyticsChoice) analyticsChoice.checked = storedConsent === '' || storedConsent === 'all' || storedConsent === 'analytics';
  if (adsChoice) adsChoice.checked = storedConsent === '' || storedConsent === 'all';
  const setConsent = choice => {
    const previousChoice = readConsent();
    const analytics = choice === 'all' || choice === 'analytics';
    const ads = choice === 'all';
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', {
        analytics_storage: analytics ? 'granted' : 'denied',
        ad_storage: ads ? 'granted' : 'denied',
        ad_user_data: ads ? 'granted' : 'denied',
        ad_personalization: ads ? 'granted' : 'denied',
      });
    }
    writeConsent(choice);
    if (analytics && previousChoice !== 'all' && previousChoice !== 'analytics') {
      window.setTimeout(() => emit('maison_consent_granted', {
        page_location: window.location.href,
        page_title: document.title,
      }), 50);
    }
    if (consentLayer) {
      consentLayer.hidden = true;
      consentLayer.classList.remove('is-customizing');
    }
  };
  if (consentLayer && !storedConsent) window.setTimeout(() => { consentLayer.hidden = false; }, 350);
  document.querySelectorAll('[data-consent-accept]').forEach(button => button.addEventListener('click', () => setConsent('all')));
  document.querySelectorAll('[data-consent-reject]').forEach(button => button.addEventListener('click', () => setConsent('essential')));
  document.querySelectorAll('[data-consent-customize]').forEach(button => button.addEventListener('click', () => {
    if (!consentLayer || !consentPanel) return;
    const openedFromFooter = !button.closest('[data-consent-layer]');
    consentLayer.hidden = false;
    consentPanel.hidden = openedFromFooter ? false : !consentPanel.hidden;
    consentLayer.classList.toggle('is-customizing', !consentPanel.hidden);
    consentLayer.querySelectorAll('[data-consent-customize]').forEach(control => control.setAttribute('aria-expanded', String(!consentPanel.hidden)));
  }));
  document.querySelectorAll('[data-consent-save]').forEach(button => button.addEventListener('click', () => {
    const analytics = Boolean(consentLayer?.querySelector('[name="consent_analytics"]')?.checked);
    const ads = Boolean(consentLayer?.querySelector('[name="consent_ads"]')?.checked);
    setConsent(ads ? 'all' : (analytics ? 'analytics' : 'essential'));
  }));

  if (window.__MAISON_GA4_DEBUG__) {
    window.setTimeout(() => emit('maison_debug_check', {
      page_location: window.location.href,
      page_title: document.title,
      diagnostic_source: 'maison_bebe_storefront',
    }), 1200);
  }
})();
