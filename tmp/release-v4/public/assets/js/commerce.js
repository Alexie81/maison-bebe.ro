(() => {
  'use strict';
  const base = window.APP_BASE_PATH || '';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const request = async (path, options = {}) => {
    const response = await fetch(base + path, {headers:{Accept:'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json',...(options.headers||{})},...options});
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Operațiunea nu a reușit.');
    return data;
  };
  const notify = message => {
    const region = document.querySelector('[data-toast-region]');
    if (!region) return;
    const node=document.createElement('div');node.className='toast';node.textContent=message;region.append(node);setTimeout(()=>node.remove(),3500);
  };
  document.querySelectorAll('[data-cart-quantity]').forEach(input => input.addEventListener('change', async () => {
    input.disabled=true;
    try { await request(`/api/cart/items/${input.dataset.cartQuantity}`,{method:'PATCH',body:JSON.stringify({quantity:Number(input.value),_csrf:csrf})}); location.reload(); }
    catch(error){notify(error.message);input.disabled=false;}
  }));
  document.querySelectorAll('[data-cart-remove]').forEach(button => button.addEventListener('click', async () => {
    button.disabled=true;
    try { await request(`/api/cart/items/${button.dataset.cartRemove}`,{method:'DELETE',body:JSON.stringify({_csrf:csrf})}); location.reload(); }
    catch(error){notify(error.message);button.disabled=false;}
  }));
  document.querySelector('[data-coupon-form]')?.addEventListener('submit', async event => {
    event.preventDefault(); const form=event.currentTarget; const button=form.querySelector('button');button.disabled=true;
    try { await request('/api/cart/coupon',{method:'POST',body:JSON.stringify({code:form.code.value,_csrf:csrf})}); location.reload(); }
    catch(error){notify(error.message);button.disabled=false;}
  });
})();

