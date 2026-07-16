(() => {
  'use strict';
  const base=window.APP_BASE_PATH||'';const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';let lastSeen=Number(localStorage.getItem('maison_admin_last_notification')||0);
  const ADMIN_BRAND='Maison B\u00e9b\u00e9';
  const fixAdminText=value=>String(value||'')
    .replace(/Admin\s+Maison\s+B(?:\u00c3\u00a9|\u0102\u00a9|\ufffd)b(?:\u00c3\u00a9|\u0102\u00a9|\ufffd)/gi,'Admin '+ADMIN_BRAND)
    .replace(/Maison\s+B(?:\u00c3\u00a9|\u0102\u00a9|\ufffd)b(?:\u00c3\u00a9|\u0102\u00a9|\ufffd)/gi,ADMIN_BRAND)
    .replace(/B(?:\u00c3\u00a9|\u0102\u00a9|\ufffd)b(?:\u00c3\u00a9|\u0102\u00a9|\ufffd)/gi,'B\u00e9b\u00e9')
    .replace(/\u00c4\u0192/g,'ă')
    .replace(/\u00c8\u0099/g,'ș')
    .replace(/\u00c8\u009b/g,'ț')
    .replace(/\u00c3\u00a2/g,'â')
    .replace(/\u00c3\u00ae/g,'î')
    .replace(/\u00c2/g,'')
    .trim();
  const adminPageLabel=value=>{const clean=fixAdminText(value).split('|')[0].trim();return clean||'Administrare magazin';};
  const updateAdminTitle=value=>{const page=adminPageLabel(value);const topbarLabel=document.querySelector('.admin-topbar p');if(topbarLabel)topbarLabel.innerHTML='<small>'+ADMIN_BRAND+' / Admin / '+page+'</small><strong>'+page+'</strong>';document.title=page+' | Admin '+ADMIN_BRAND;};
  const menu=document.querySelector('.admin-sidebar');
  const menuToggle=document.querySelector('[data-admin-menu]');
  const sidebarCollapse=document.querySelector('[data-admin-sidebar-collapse]');
  const sidebarStorageKey='maison_admin_sidebar_collapsed';
  const applySidebarState=collapsed=>{
    const desktop=matchMedia('(min-width:1051px)').matches;
    document.body.classList.toggle('admin-sidebar-collapsed',desktop&&collapsed);
    if(!sidebarCollapse)return;
    sidebarCollapse.setAttribute('aria-expanded',String(!(desktop&&collapsed)));
    const label=desktop&&collapsed?'Extinde bara laterală':'Restrânge bara laterală';
    sidebarCollapse.setAttribute('aria-label',label);
    sidebarCollapse.title=label;
  };
  if(sidebarCollapse){
    let collapsed=false;
    try{collapsed=localStorage.getItem(sidebarStorageKey)==='1';}catch{}
    applySidebarState(collapsed);
    sidebarCollapse.addEventListener('click',()=>{
      collapsed=!document.body.classList.contains('admin-sidebar-collapsed');
      try{localStorage.setItem(sidebarStorageKey,collapsed?'1':'0');}catch{}
      applySidebarState(collapsed);
    });
    addEventListener('resize',()=>applySidebarState(collapsed),{passive:true});
  }
  const overlay=document.createElement('button');
  overlay.type='button';overlay.className='admin-sidebar-overlay';overlay.setAttribute('aria-label','Închide meniul');overlay.hidden=true;
  menu?.insertAdjacentElement('afterend',overlay);
  const closeButton=document.createElement('button');
  closeButton.type='button';closeButton.className='admin-sidebar-close';closeButton.setAttribute('aria-label','Închide meniul');closeButton.textContent='×';
  menu?.querySelector('.admin-brand')?.insertAdjacentElement('afterend',closeButton);
  const setMenu=open=>{if(!menu||!menuToggle)return;menu.classList.toggle('open',open);overlay.hidden=!open;document.body.classList.toggle('admin-menu-open',open);menuToggle.setAttribute('aria-expanded',String(open));menuToggle.setAttribute('aria-label',open?'Închide meniul de administrare':'Deschide meniul de administrare');if(open)closeButton.focus();};
  menuToggle?.addEventListener('click',()=>setMenu(!menu?.classList.contains('open')));
  closeButton.addEventListener('click',()=>setMenu(false));overlay.addEventListener('click',()=>setMenu(false));
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu?.classList.contains('open')){setMenu(false);menuToggle?.focus();}});
  const iconPaths={dashboard:'<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',orders:'<path d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h4"/>',bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',truck:'<path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',box:'<path d="m12 2 9 5-9 5-9-5 9-5ZM3 7v10l9 5 9-5V7M12 12v10"/>',gift:'<path d="M3 9h18v12H3zM12 9v12M2 5h20v4H2z"/>',user:'<circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/>',users:'<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0 1 12 0M14 16a5 5 0 0 1 7 5"/>',key:'<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l3 3"/>',tag:'<path d="m2 12 10 10 10-10V2H12L2 12Z"/><circle cx="17" cy="7" r="1"/>',page:'<path d="M6 2h9l4 4v16H6zM15 2v5h5M9 12h7M9 16h7"/>',search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',mail:'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7L22 7"/>',card:'<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',shield:'<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Zm-3-10 2 2 4-4"/>'};
  const iconFor=path=>path==='/admin'?'dashboard':path.includes('autentificare')?'key':path.includes('utilizatori')?'users':path.includes('comenzi')?'orders':path.includes('notificari')?'bell':path.includes('expeditii')||path.includes('livrare')?'truck':path.includes('gift-box')?'gift':path.includes('produse')?'box':path.includes('categorii')?'dashboard':path.includes('clienti')?'user':path.includes('cupoane')?'tag':path.includes('seo')?'search':path.includes('email')?'mail':path.includes('plati')?'card':path.includes('securitate')?'shield':'page';
  const normalizedPath=location.pathname.replace((window.APP_BASE_PATH||'').replace(/\/$/,''),'')||'/';
  let currentLabel='Administrare magazin';
  menu?.querySelectorAll('nav>a').forEach(link=>{let path=new URL(link.href,location.origin).pathname.replace((window.APP_BASE_PATH||'').replace(/\/$/,''),'')||'/';const active=path==='/admin'?(normalizedPath==='/admin'||normalizedPath==='/admin/'):(normalizedPath===path||normalizedPath.startsWith(path+'/'));link.classList.toggle('is-active',active);if(active){link.setAttribute('aria-current','page');currentLabel=link.childNodes[0]?.textContent?.trim()||link.textContent.trim();}const icon=document.createElement('span');icon.className='admin-nav-icon';icon.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true">'+iconPaths[iconFor(path)]+'</svg>';link.insertAdjacentElement('afterbegin',icon);const labelText=[...link.childNodes].filter(node=>node.nodeType===Node.TEXT_NODE).map(node=>node.textContent).join(' ').trim();[...link.childNodes].filter(node=>node.nodeType===Node.TEXT_NODE).forEach(node=>node.remove());const descriptions={dashboard:'Imaginea de ansamblu',orders:'Pregătire și status',bell:'Evenimente importante',truck:'Livrări și AWB-uri',box:'Catalog, preț și stoc',gift:'Configurator cadouri',user:'Conturi și istoric',tag:'Reduceri și promoții',page:'Conținut și setări',search:'Vizibilitate în Google',mail:'Expeditori și mesaje',card:'Card și ramburs',shield:'Protecția contului'};const copy=document.createElement('span');copy.className='admin-nav-copy';const copyTitle=document.createElement('strong');copyTitle.textContent=labelText;const copyHelp=document.createElement('small');copyHelp.textContent=descriptions[iconFor(path)]||'Administrare';copy.append(copyTitle,copyHelp);link.insertBefore(copy,link.querySelector('b'));link.title=labelText;link.addEventListener('click',()=>{if(matchMedia('(max-width:1050px)').matches)setMenu(false);});});
  updateAdminTitle(currentLabel);
  const pageLoader=document.querySelector('[data-admin-page-loader]');
  const loadingDots=pageLoader?.querySelector('[data-admin-loading-dots]');
  let loadingDotsTimer=null;
  const showPageLoader=()=>{
    if(!pageLoader)return;
    pageLoader.hidden=false;
    pageLoader.setAttribute('aria-hidden','false');
    document.querySelector('.admin-main')?.setAttribute('aria-busy','true');
    let frame=0;
    const frames=['.','..','...','..'];
    if(loadingDots)loadingDots.textContent=frames[0];
    clearInterval(loadingDotsTimer);
    loadingDotsTimer=setInterval(()=>{frame=(frame+1)%frames.length;if(loadingDots)loadingDots.textContent=frames[frame];},260);
  };
  const hidePageLoader=()=>{
    clearInterval(loadingDotsTimer);
    document.querySelector('.admin-main')?.removeAttribute('aria-busy');
    if(!pageLoader)return;
    pageLoader.hidden=true;
    pageLoader.setAttribute('aria-hidden','true');
  };
  const relativeAdminPath=url=>new URL(url,location.href).pathname.replace((window.APP_BASE_PATH||'').replace(/\/$/,''),'')||'/';
  const updateAdminIdentity=url=>{
    const path=relativeAdminPath(url);
    let label='Administrare magazin';
    menu?.querySelectorAll('nav>a').forEach(link=>{
      const linkPath=relativeAdminPath(link.href);
      const active=linkPath==='/admin'?(path==='/admin'||path==='/admin/'):(path===linkPath||path.startsWith(linkPath+'/'));
      link.classList.toggle('is-active',active);
      if(active){
        link.setAttribute('aria-current','page');
        label=link.querySelector('.admin-nav-copy strong')?.textContent?.trim()||link.textContent.trim();
      }else link.removeAttribute('aria-current');
    });
    updateAdminTitle(label);
  };
  const slugifyAdmin=value=>String(value||'')
    .replace(/[ăâĂÂ]/g,'a')
    .replace(/[îÎ]/g,'i')
    .replace(/[șşȘŞ]/g,'s')
    .replace(/[țţȚŢ]/g,'t')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g,'')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g,'-')
    .replace(/^-+|-+$/g,'')
    .replace(/-{2,}/g,'-');
  const initAutoSlugs=(root=document)=>{
    root.querySelectorAll('form').forEach(form=>{
      const slug=form.querySelector('input[name="slug"]');
      if(!slug||slug.dataset.autoSlugReady==='1')return;
      const source=form.querySelector('[data-slug-source],input[name="name"],input[name="title"],input[name="box_name"],input[name="category_name"],input[name="collection_name"],input[name="label"]');
      if(!source)return;
      slug.dataset.autoSlugReady='1';
      if(!slug.value.trim())slug.dataset.slugAuto='1';
      slug.readOnly=true;
      slug.setAttribute('aria-readonly','true');
      slug.placeholder='generat automat din titlu';
      slug.classList.add('admin-slug-input');
      const sync=()=>{if(slug.dataset.slugAuto==='1')slug.value=slugifyAdmin(source.value);};
      source.addEventListener('input',sync);
      source.addEventListener('change',sync);
      slug.addEventListener('input',()=>{slug.dataset.slugAuto=slug.value.trim()?'0':'1';sync();});
      sync();
    });
  };
  const enhancePartialContent=root=>{
    root.querySelectorAll('.admin-table').forEach(table=>{
      const headers=[...table.querySelectorAll('thead th')].map(th=>th.textContent.trim());
      if(!headers.length)return;
      table.classList.add('admin-table-cards');
      table.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((cell,index)=>cell.dataset.label=headers[index]||''));
    });
    root.querySelectorAll('.admin-panel input:not([type="hidden"]),.admin-panel select,.admin-panel textarea').forEach(control=>{
      if(control.getAttribute('aria-label')||control.id)return;
      const label=control.closest('label')?.childNodes[0]?.textContent?.trim();
      if(label)control.setAttribute('aria-label',label);
    });
    initAutoSlugs(root);
    document.dispatchEvent(new CustomEvent('maison:admin-content',{detail:{root}}));
  };
  const renderAdminDocument=(parsed,url,{push=true}={})=>{
    const nextMain=parsed.querySelector('.admin-main');
    const currentMain=document.querySelector('.admin-main');
    if(!nextMain||!currentMain)throw new Error('Conținutul primit nu este valid.');
    currentMain.replaceChildren(...[...nextMain.childNodes].map(node=>document.importNode(node,true)));
    enhancePartialContent(currentMain);
    initLaunchGuide(currentMain);
    currentMain.classList.remove('is-admin-content-entering');
    void currentMain.offsetWidth;
    currentMain.classList.add('is-admin-content-entering');
    if(push)history.pushState({adminPartial:true},'',url);
    else history.replaceState(history.state,'',url);
    updateAdminIdentity(url);
    window.scrollTo({top:0,left:0,behavior:'auto'});
  };
  const loadAdminPage=async(url,{push=true}={})=>{
    const target=new URL(url,location.href);
    showPageLoader();
    try{
      const response=await fetch(target.href,{headers:{Accept:'text/html','X-Admin-Partial':'1'},credentials:'same-origin'});
      if(!response.ok)throw new Error('Pagina nu a putut fi incarcata.');
      const parsed=new DOMParser().parseFromString(await response.text(),'text/html');
      renderAdminDocument(parsed,target.href,{push});
      hidePageLoader();
    }catch(error){
      hidePageLoader();
      location.assign(target.href);
    }
  };
  const resultModal=document.querySelector('[data-admin-result-modal]');
  let resultModalReturnFocus=null;
  const showAdminResult=(type,title,message)=>{
    if(!resultModal)return;
    resultModalReturnFocus=document.activeElement;
    const isError=type==='error';
    resultModal.classList.toggle('is-error',isError);
    resultModal.querySelector('[data-admin-result-icon]').textContent=isError?'!':'✓';
    resultModal.querySelector('[data-admin-result-eyebrow]').textContent=isError?'SALVAREA NU A REUȘIT':'SALVAT CU SUCCES';
    resultModal.querySelector('[data-admin-result-title]').textContent=title;
    resultModal.querySelector('[data-admin-result-message]').textContent=message;
    resultModal.hidden=false;
    document.body.style.overflow='hidden';
    resultModal.querySelector('.admin-result-card>.admin-button')?.focus();
  };
  const closeAdminResult=()=>{if(!resultModal)return;resultModal.hidden=true;document.body.style.overflow='';resultModalReturnFocus?.focus?.();};
  resultModal?.querySelectorAll('[data-admin-result-close]').forEach(button=>button.addEventListener('click',closeAdminResult));
  const adminListUrl=action=>{
    const url=new URL(action,location.href);const path=relativeAdminPath(url.href);
    const routes=[
      [/^\/admin\/produse(?:\/|$)/,'/admin/produse'],
      [/^\/admin\/(?:categorii|colectii)(?:\/|$)/,'/admin/categorii'],
      [/^\/admin\/gift-box(?:\/|$)/,'/admin/gift-box'],
      [/^\/admin\/cupoane(?:\/|$)/,'/admin/cupoane'],
      [/^\/admin\/atelier\/taxonomii(?:\/|$)/,'/admin/atelier/taxonomii'],
      [/^\/admin\/atelier(?:\/|$)/,'/admin/atelier'],
      [/^\/admin\/cms\/pagini(?:\/|$)/,'/admin/cms'],
      [/^\/admin\/comenzi(?:\/|$)/,'/admin/comenzi'],
      [/^\/admin\/facturi(?:\/|$)/,'/admin/facturi'],
      [/^\/admin\/utilizatori(?:\/|$)/,'/admin/utilizatori']
    ];
    const match=routes.find(([pattern])=>pattern.test(path));
    return new URL(base+(match?.[1]||path),location.origin).href;
  };
  const responseMessage=(parsed,selector,fallback)=>fixAdminText(parsed.querySelector(selector)?.textContent?.trim()||fallback);
  document.addEventListener('submit',async event=>{
    const form=event.target.closest('form');
    if(!form||event.defaultPrevented||String(form.method||'get').toLowerCase()!=='post'||form.dataset.nativeSubmit==='1')return;
    if(form.hasAttribute('data-confirm-delete')&&form.dataset.adminConfirmed!=='1')return;
    const action=new URL(form.action||location.href,location.href);
    if(action.origin!==location.origin||!relativeAdminPath(action.href).startsWith('/admin'))return;
    event.preventDefault();
    const submitter=event.submitter||form.querySelector('[type="submit"]');
    const originalLabel=submitter?.textContent;
    if(submitter){submitter.disabled=true;submitter.classList.add('is-loading');}
    showPageLoader();
    try{
      const formData=event.submitter&&typeof FormData==='function'?new FormData(form,event.submitter):new FormData(form);
      const response=await fetch(action.href,{method:'POST',body:formData,headers:{Accept:'text/html','X-Admin-Partial':'1'},credentials:'same-origin'});
      const html=await response.text();
      const parsed=new DOMParser().parseFromString(html,'text/html');
      const returnedError=parsed.querySelector('.admin-alert.error');
      if(!response.ok||returnedError){
        const message=responseMessage(parsed,'.admin-alert.error,main p,.error-message',response.status===413?'Fișierele sunt prea mari pentru a fi încărcate.':'Verifică datele introduse și încearcă din nou.');
        throw new Error(message);
      }
      const successMessage=responseMessage(parsed,'.admin-alert.success','Modificările au fost salvate corect.');
      const listUrl=adminListUrl(action.href);
      let listDocument=parsed;
      if(new URL(response.url||action.href).pathname!==new URL(listUrl).pathname){
        const listResponse=await fetch(listUrl,{headers:{Accept:'text/html','X-Admin-Partial':'1'},credentials:'same-origin'});
        if(!listResponse.ok)throw new Error('Datele s-au salvat, dar lista nu a putut fi reîncărcată.');
        listDocument=new DOMParser().parseFromString(await listResponse.text(),'text/html');
      }
      renderAdminDocument(listDocument,listUrl,{push:true});
      hidePageLoader();
      showAdminResult('success','Salvarea este gata.',successMessage);
    }catch(error){
      hidePageLoader();
      showAdminResult('error','Datele tale au rămas în formular.',fixAdminText(error?.message||'Salvarea nu a reușit. Încearcă din nou.'));
    }finally{
      if(submitter&&document.contains(submitter)){submitter.disabled=false;submitter.classList.remove('is-loading');if(originalLabel)submitter.textContent=originalLabel;}
      delete form.dataset.adminConfirmed;
    }
  });
  menu?.querySelector('nav')?.addEventListener('click',event=>{
    const link=event.target.closest('a[href]');
    if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||link.target==='_blank')return;
    const target=new URL(link.href,location.href);
    if(target.origin!==location.origin||target.href===location.href)return;
    event.preventDefault();
    if(matchMedia('(max-width:1050px)').matches)setMenu(false);
    loadAdminPage(target.href);
  });
  addEventListener('popstate',()=>loadAdminPage(location.href,{push:false}));
  const updateLiveClock=()=>{
    const clock=document.querySelector('[data-admin-live-clock]');
    if(!clock)return;
    const now=new Date();
    const months=['IAN','FEB','MAR','APR','MAI','IUN','IUL','AUG','SEP','OCT','NOI','DEC'];
    const pad=value=>String(value).padStart(2,'0');
    const dateNode=clock.querySelector('[data-live-date]');
    const timeNode=clock.querySelector('[data-live-time]');
    if(dateNode)dateNode.textContent=`${pad(now.getDate())}-${months[now.getMonth()]}-${now.getFullYear()}`;
    if(timeNode)timeNode.textContent=`${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    clock.dateTime=now.toISOString();
  };
  updateLiveClock();setInterval(updateLiveClock,1000);
  document.addEventListener('click',event=>document.querySelectorAll('.admin-help-menu[open]').forEach(details=>{if(!details.contains(event.target))details.open=false;}));
  enhancePartialContent(document);
  let swRegistration=null;if('serviceWorker'in navigator){navigator.serviceWorker.register(base+'/sw.js',{scope:base+'/'}).then(reg=>{swRegistration=reg;}).catch(()=>{});}
  const notifyButton=document.querySelectorAll('[data-enable-browser-notifications]');notifyButton.forEach(button=>button.addEventListener('click',async()=>{if(!('Notification'in window))return;const result=await Notification.requestPermission();const label=button.querySelector('span');const text=result==='granted'?'Notificări active':'Notificări blocate';if(label)label.textContent=text;else button.textContent=text;}));
  const nativeNotification=async item=>{if(Notification.permission!=='granted')return;const options={body:item.body,icon:base+'/assets/images/logo-reference.png',badge:base+'/assets/images/logo-reference.png',tag:'order-'+item.id,data:{url:base+item.url},renotify:true};if(swRegistration){await swRegistration.showNotification(item.title,options);return;}const n=new Notification(item.title,options);n.onclick=()=>{window.focus();location.href=base+item.url;n.close();};};
  const showPopup=item=>{const popup=document.querySelector('[data-order-popup]');if(!popup)return;popup.querySelector('[data-popup-title]').textContent=item.title;popup.querySelector('[data-popup-body]').textContent=item.body;popup.querySelector('[data-popup-link]').href=base+item.url;popup.hidden=false;nativeNotification(item);};
  document.querySelector('[data-close-order-popup]')?.addEventListener('click',()=>document.querySelector('[data-order-popup]').hidden=true);
  const poll=async()=>{try{const response=await fetch(base+'/admin/api/notifications/unread',{headers:{Accept:'application/json'}});if(!response.ok)return;const data=await response.json();const count=Math.max(0,Number(data.count)||0);document.querySelectorAll('[data-admin-notification-count]').forEach(x=>{x.textContent=count>0?String(count):'';x.hidden=count<1;x.setAttribute('aria-label',count>0?`${count} notificări necitite`:'');});const newest=data.items?.[0];if(newest&&Number(newest.id)>lastSeen){lastSeen=Number(newest.id);localStorage.setItem('maison_admin_last_notification',String(lastSeen));if(newest.type==='new_order')showPopup(newest);}}catch{}};
  poll();setInterval(poll,25000);
  const api=async(path)=>{const response=await fetch(base+path,{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf,'Content-Type':'application/json'},body:JSON.stringify({_csrf:csrf})});if(!response.ok)throw new Error('Operațiunea nu a reușit.');};
  document.querySelectorAll('[data-mark-read]').forEach(button=>button.addEventListener('click',async()=>{await api('/admin/api/notifications/'+button.dataset.markRead+'/read');button.closest('.notification-item')?.classList.remove('unread');button.remove();}));
  document.querySelector('[data-mark-all-read]')?.addEventListener('click',async()=>{await api('/admin/api/notifications/read-all');location.reload();});
  const productEditor=document.querySelector('[data-product-editor]');
  if(productEditor){
    const optionList=productEditor.querySelector('[data-option-groups]');
    const variantsTarget=productEditor.querySelector('[data-variants]');
    const optionEmpty=productEditor.querySelector('[data-option-empty]');
    const optionSummary=productEditor.querySelector('[data-option-summary]');
    const variantSummary=productEditor.querySelector('[data-variant-summary]');
    let optionRefreshTimer=null;
    const syncOptionGroup=row=>{const values=[...new Set([...row.querySelectorAll('[data-option-value-input]')].map(input=>input.value.trim()).filter(Boolean))];const hidden=row.querySelector('[data-option-values-json]');if(hidden)hidden.value=JSON.stringify(values);return values;};
    const allOptionGroups=()=>[...optionList.querySelectorAll('[data-option-group]')].map(row=>({name:row.querySelector('[name="option_name[]"]')?.value.trim()||'',values:syncOptionGroup(row)}));
    const optionGroups=()=>allOptionGroups().filter(group=>group.name&&group.values.length);
    const comboKey=map=>JSON.stringify(Object.keys(map).sort().reduce((result,key)=>{result[key]=map[key];return result;},{}));
    const syncVariant=row=>{const hidden=row.querySelector('[data-variant-options-json]');return hidden?JSON.parse(hidden.value||'{}'):{};};
    const rowState=row=>{let options={};try{options=syncVariant(row)}catch{}return{id:row.querySelector('[name="variant_id[]"]')?.value||'',sku:row.querySelector('.variant-sku strong')?.textContent?.trim()||'Generat automat',options,price:row.querySelector('[name="variant_price[]"]')?.value||'',stock:row.querySelector('[name="variant_stock[]"]')?.value||'0',unlimited:row.querySelector('[data-unlimited-stock]')?.checked||false};};
    const renderVariantOptions=row=>{const target=row.querySelector('[data-variant-option-selects]');if(!target)return;let selected={};try{selected=JSON.parse(row.querySelector('[data-variant-options-json]')?.value||'{}')}catch{}target.innerHTML=Object.entries(selected).map(([name,value])=>`<span class="variant-combo-tag"><small>${escapeAdmin(name)}</small><b>${escapeAdmin(value)}</b></span>`).join('');};
    const variantRow=state=>{const row=document.createElement('article');row.className='variant-row'+(state.unlimited?' is-unlimited':'');row.dataset.variantRow='';row.innerHTML=`<input type="hidden" name="variant_id[]" value="${escapeAdmin(state.id||'')}"><input type="hidden" name="variant_options_json[]" value="${escapeAdmin(JSON.stringify(state.options||{}))}" data-variant-options-json><div class="variant-sku"><span>SKU</span><strong>${escapeAdmin(state.sku||'Generat automat')}</strong></div><div class="variant-option-selects" data-variant-option-selects></div><label data-variant-price-label><span>Preț variantă (lei)</span><input type="number" step="0.01" min="0" name="variant_price[]" value="${escapeAdmin(state.price||'')}" required></label><div class="variant-stock-control"><input type="hidden" name="variant_unlimited[]" value="${state.unlimited?'1':'0'}" data-unlimited-value><label class="variant-stock-field" data-stock-field><span>Stoc disponibil</span><input type="number" min="0" name="variant_stock[]" value="${escapeAdmin(state.stock||'0')}" ${state.unlimited?'readonly':''} data-stock-input></label><label class="admin-switch-row"><input type="checkbox" ${state.unlimited?'checked':''} data-unlimited-stock><span class="admin-switch" aria-hidden="true"><i></i></span><b>Stoc nelimitat</b></label></div><button type="button" class="icon-action danger" data-remove-variant aria-label="Șterge varianta">×</button>`;renderVariantOptions(row);return row;};
    const combinations=groups=>groups.reduce((items,group)=>items.flatMap(item=>group.values.map(value=>({...item,[group.name]:value}))),[{}]);
    const updateSummaries=()=>{const groups=allOptionGroups();const values=groups.reduce((count,group)=>count+group.values.length,0);if(optionSummary)optionSummary.textContent=`${groups.length} ${groups.length===1?'grup':'grupuri'} · ${values} ${values===1?'opțiune':'opțiuni'}`;const variantRows=[...variantsTarget.querySelectorAll('[data-variant-row]')];if(variantSummary)variantSummary.textContent=`${variantRows.length} ${variantRows.length===1?'preț':'prețuri de completat'}`;variantsTarget.classList.toggle('has-single-variant',variantRows.length===1);variantRows.forEach(row=>{const unlimited=row.querySelector('[data-unlimited-stock]')?.checked||false;row.classList.toggle('is-unlimited',unlimited);const stock=row.querySelector('[data-stock-input]');if(stock)stock.setAttribute('aria-disabled',unlimited?'true':'false');const remove=row.querySelector('[data-remove-variant]');if(remove)remove.hidden=variantRows.length===1;});};
    const syncVariantsToOptions=()=>{const rows=[...variantsTarget.querySelectorAll('[data-variant-row]')];const states=rows.map(rowState);const template=states[0]||{id:'',sku:'Generat automat',options:{},price:'',stock:'0',unlimited:false};const groups=allOptionGroups();const ready=groups.length>0&&groups.every(group=>group.name&&group.values.length);optionEmpty.hidden=groups.length>0;const addVariant=productEditor.querySelector('[data-add-variant]');if(addVariant)addVariant.hidden=true;
      if(!groups.length){const first=states[0]||template;first.options={};variantsTarget.replaceChildren(variantRow(first));const label=variantsTarget.querySelector('[data-variant-price-label] span');if(label)label.textContent='Preț produs (lei)';updateSummaries();return;}
      if(!ready){rows.forEach(row=>{renderVariantOptions(row);const label=row.querySelector('[data-variant-price-label] span');if(label)label.textContent='Preț produs (temporar)';});updateSummaries();return;}
      const existing=new Map(states.map(state=>[comboKey(state.options),state]));const generated=combinations(groups).map((options,index)=>{const match=existing.get(comboKey(options));if(match)return match;return{id:'',sku:'Generat automat',options,price:template.price,stock:template.stock,unlimited:template.unlimited};});variantsTarget.replaceChildren(...generated.map(variantRow));updateSummaries();};
    const scheduleVariantSync=(delay=250)=>{clearTimeout(optionRefreshTimer);optionRefreshTimer=setTimeout(syncVariantsToOptions,delay);};
    const valueRow=(value='')=>{const row=document.createElement('div');row.className='option-value-row';row.innerHTML=`<input value="${escapeAdmin(value)}" data-option-value-input aria-label="Valoare opțiune"><button type="button" class="option-value-remove" data-remove-option-value aria-label="Șterge opțiunea">×</button>`;return row;};
    productEditor.querySelector('[data-add-option]')?.addEventListener('click',()=>{const row=document.createElement('article');row.className='option-editor-row';row.dataset.optionGroup='';row.innerHTML='<span class="option-drag" aria-hidden="true">⋮⋮</span><label class="option-name-field">Denumire grup<input name="option_name[]" placeholder="Ex: Culoare"></label><div class="option-values-block"><span>Opțiuni</span><input type="hidden" name="option_values_json[]" value="[]" data-option-values-json><div class="option-value-list" data-option-value-list></div><button type="button" class="admin-button secondary option-value-add" data-add-option-value>+ Adaugă opțiune</button></div><button type="button" class="icon-action danger" data-remove-option aria-label="Șterge grupul">×</button>';row.querySelector('[data-option-value-list]').append(valueRow());optionList.append(row);row.querySelector('[name="option_name[]"]')?.focus();syncVariantsToOptions();});
    optionList?.addEventListener('input',event=>{const group=event.target.closest('[data-option-group]');if(group)syncOptionGroup(group);scheduleVariantSync();});
    optionList?.addEventListener('change',event=>{if(event.target.closest('[data-option-group]'))syncVariantsToOptions();});
    optionList?.addEventListener('click',event=>{const group=event.target.closest('[data-option-group]');if(!group)return;const add=event.target.closest('[data-add-option-value]');if(add){const row=valueRow();group.querySelector('[data-option-value-list]').append(row);row.querySelector('input')?.focus();syncOptionGroup(group);updateSummaries();return;}const removeValue=event.target.closest('[data-remove-option-value]');if(removeValue){const rows=group.querySelectorAll('.option-value-row');if(rows.length<=1){rows[0].querySelector('input').value='';rows[0].querySelector('input').focus();}else{removeValue.closest('.option-value-row').remove();}syncOptionGroup(group);syncVariantsToOptions();return;}const removeGroup=event.target.closest('[data-remove-option]');if(removeGroup){group.remove();syncVariantsToOptions();}});
    variantsTarget?.addEventListener('click',event=>{const button=event.target.closest('[data-remove-variant]');if(!button)return;if(variantsTarget.querySelectorAll('[data-variant-row]').length<=1){alert('Produsul trebuie să aibă cel puțin un preț.');return;}button.closest('[data-variant-row]')?.remove();updateSummaries();});
    variantsTarget?.addEventListener('change',event=>{const toggle=event.target.closest('[data-unlimited-stock]');if(!toggle)return;const row=toggle.closest('[data-variant-row]');const hidden=row?.querySelector('[data-unlimited-value]');const stock=row?.querySelector('[data-stock-input]');if(hidden)hidden.value=toggle.checked?'1':'0';if(row)row.classList.toggle('is-unlimited',toggle.checked);if(stock){stock.readOnly=toggle.checked;stock.setAttribute('aria-disabled',toggle.checked?'true':'false');if(toggle.checked)stock.value='0';}});
    syncVariantsToOptions();

    const richEditors=[...productEditor.querySelectorAll('[data-rich-editor]')];
    const updateRichStats=editor=>{
        const surface=editor.querySelector('[data-rich-surface]');
        if(!surface)return;
        const raw=(surface.innerText||'').replace(/\u00a0/g,' ');
        const clean=raw.trim();
        const words=clean?(clean.match(/[\p{L}\p{N}]+(?:['’\-][\p{L}\p{N}]+)*/gu)||[]).length:0;
        const characters=raw.replace(/\n+$/,'').length;
        const lines=Math.max(1,raw.replace(/\n+$/,'').split('\n').length);
        editor.querySelector('[data-rich-words]')?.replaceChildren(String(words));
        editor.querySelector('[data-rich-characters]')?.replaceChildren(String(characters));
        editor.querySelector('[data-rich-lines]')?.replaceChildren(String(lines));
    };
    const syncRichEditor=editor=>{
        const surface=editor.querySelector('[data-rich-surface]');
        const input=editor.querySelector('[data-rich-input]');
        if(!surface||!input)return;
        const clone=surface.cloneNode(true);
        clone.querySelectorAll('img').forEach(image=>{
            image.classList.remove('is-selected');
            const source=image.getAttribute('src')||'';
            const absolutePrefix=location.origin+base+'/uploads/';
            const relativePrefix=base+'/uploads/';
            if(base&&source.startsWith(absolutePrefix))image.setAttribute('src','/uploads/'+source.slice(absolutePrefix.length));
            else if(base&&source.startsWith(relativePrefix))image.setAttribute('src','/uploads/'+source.slice(relativePrefix.length));
        });
        clone.querySelectorAll('figcaption').forEach(caption=>{
            const text=(caption.textContent||'').trim();
            if(!text||text==='Adaugă o descriere opțională')caption.remove();
        });
        input.value=clone.innerHTML.trim();
        const preview=editor.querySelector('[data-rich-preview]');
        if(preview&&!preview.hidden)preview.innerHTML=clone.innerHTML;
        updateRichStats(editor);
    };
    const uploadRichImage=async(editor,file)=>{
        if(!file)return;
        const status=editor.querySelector('[data-rich-status]');
        if(status)status.textContent='Se încarcă imaginea…';
        const formData=new FormData();
        formData.append('image',file);
        formData.append('_csrf',csrf);
        try{
            const response=await fetch(base+'/admin/produse/editor/imagine',{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf},body:formData});
            const data=await response.json();
            if(!response.ok)throw new Error(data.message||'Imaginea nu a putut fi încărcată.');
            const image=editor._insertRichImage?.(data);
            syncRichEditor(editor);
            if(image)editor._selectRichImage?.(image);
            if(status)status.textContent='Imagine adăugată. Apasă imaginea pentru dimensiune și poziție.';
        }catch(error){if(status)status.textContent=error.message;}
    };
    richEditors.forEach(editor=>{
        const surface=editor.querySelector('[data-rich-surface]');
        const preview=editor.querySelector('[data-rich-preview]');
        const fileInput=editor.querySelector('[data-rich-image-input]');
        const inspector=editor.querySelector('[data-rich-image-inspector]');
        const widthInput=editor.querySelector('[data-rich-image-width]');
        const radiusInput=editor.querySelector('[data-rich-image-radius]');
        let savedRange=null;
        let selectedImage=null;
        const selectionElement=()=>{
            if(!savedRange)return null;
            const node=savedRange.commonAncestorContainer;
            return node.nodeType===Node.ELEMENT_NODE?node:node.parentElement;
        };
        const saveSelection=()=>{
            const selection=window.getSelection();
            if(!selection?.rangeCount)return;
            const range=selection.getRangeAt(0);
            if(surface?.contains(range.commonAncestorContainer))savedRange=range.cloneRange();
        };
        const restoreSelection=(collapse=false)=>{
            if(!savedRange)return null;
            const range=savedRange.cloneRange();
            if(collapse)range.collapse(false);
            const selection=window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            return range;
        };
        const blockTag=()=>{
            let node=selectionElement();
            while(node&&node!==surface){if(/^(P|H2|H3|H4|BLOCKQUOTE|PRE)$/.test(node.tagName))return node.tagName.toLowerCase();node=node.parentElement;}
            return 'p';
        };
        const updateToolbarState=()=>{
            editor.querySelectorAll('[data-rich-command]').forEach(button=>{
                const command=button.dataset.richCommand;
                if(['bold','italic','underline','insertUnorderedList','insertOrderedList'].includes(command)){
                    try{button.classList.toggle('is-active',document.queryCommandState(command));}catch{}
                }
            });
            const current=blockTag();
            editor.querySelectorAll('[data-rich-format]').forEach(button=>button.classList.toggle('is-active',button.dataset.richFormat===current));
        };
        const runCommand=(command,value=null)=>{
            restoreSelection();
            surface?.focus();
            document.execCommand(command,false,value);
            saveSelection();
            updateToolbarState();
            syncRichEditor(editor);
        };
        const toggleBlock=tag=>runCommand('formatBlock','<'+(blockTag()===tag?'p':tag)+'>');
        const applyFontSize=size=>{
            if(!size)return;
            restoreSelection();surface?.focus();
            document.execCommand('fontSize',false,'7');
            surface?.querySelectorAll('font[size="7"]').forEach(font=>{
                const span=document.createElement('span');
                span.style.fontSize=size;
                span.innerHTML=font.innerHTML;
                font.replaceWith(span);
            });
            saveSelection();syncRichEditor(editor);
        };
        const selectImage=image=>{
            selectedImage?.classList.remove('is-selected');
            selectedImage=image||null;
            if(!selectedImage){if(inspector)inspector.hidden=true;return;}
            selectedImage.classList.add('is-selected');
            if(inspector)inspector.hidden=false;
            const width=Math.min(100,Math.max(20,parseInt(selectedImage.style.width||'100',10)||100));
            const radius=Math.min(48,Math.max(0,parseInt(selectedImage.style.borderRadius||'0',10)||0));
            if(widthInput)widthInput.value=String(width);
            if(radiusInput)radiusInput.value=String(radius);
            editor.querySelector('[data-rich-image-width-output]')?.replaceChildren(width+'%');
            editor.querySelector('[data-rich-image-radius-output]')?.replaceChildren(radius+'px');
            const alignment=selectedImage.style.marginLeft==='auto'&&selectedImage.style.marginRight==='0px'?'right':selectedImage.style.marginLeft==='0px'&&selectedImage.style.marginRight==='auto'?'left':'center';
            editor.querySelectorAll('[data-rich-image-align]').forEach(button=>button.classList.toggle('is-active',button.dataset.richImageAlign===alignment));
        };
        const insertRichImage=data=>{
            const figure=document.createElement('figure');
            const image=document.createElement('img');
            image.src=data.url;
            image.alt='';
            image.width=Number(data.width)||1200;
            image.height=Number(data.height)||800;
            image.style.width='80%';
            image.style.maxWidth='100%';
            image.style.height='auto';
            image.style.marginLeft='auto';
            image.style.marginRight='auto';
            const caption=document.createElement('figcaption');
            caption.innerHTML='';
            figure.append(image,caption);
            const after=document.createElement('p');after.innerHTML='<br>';
            const range=restoreSelection(true);
            let top=range?.endContainer?.nodeType===Node.ELEMENT_NODE?range.endContainer:range?.endContainer?.parentElement;
            while(top&&top.parentElement!==surface)top=top.parentElement;
            if(top&&top!==surface){top.after(figure,after);}else{surface?.append(figure,after);}
            savedRange=document.createRange();savedRange.selectNodeContents(after);savedRange.collapse(true);
            return image;
        };
        editor._restoreRichSelection=restoreSelection;
        editor._insertRichImage=insertRichImage;
        editor._selectRichImage=selectImage;
        if(localStorage.getItem('mb-rich-editor-theme')==='dark')editor.classList.add('is-dark');
        editor.querySelector('[data-rich-theme]')?.classList.toggle('is-active',editor.classList.contains('is-dark'));
        surface?.addEventListener('focus',()=>{try{document.execCommand('styleWithCSS',false,true)}catch{}});
        ['keyup','mouseup','input'].forEach(name=>surface?.addEventListener(name,()=>{saveSelection();updateToolbarState();syncRichEditor(editor);}));
        surface?.addEventListener('click',event=>{const image=event.target.closest('img');if(image&&surface.contains(image)){event.preventDefault();selectImage(image);}else if(!event.target.closest('figcaption'))selectImage(null);});
        surface?.addEventListener('blur',()=>syncRichEditor(editor));
        surface?.addEventListener('paste',event=>{
            const imageItem=[...(event.clipboardData?.items||[])].find(item=>item.type.startsWith('image/'));
            if(imageItem){event.preventDefault();uploadRichImage(editor,imageItem.getAsFile());return;}
            setTimeout(()=>{saveSelection();syncRichEditor(editor);},0);
        });
        editor.querySelectorAll('.rich-editor-tools button').forEach(button=>button.addEventListener('mousedown',event=>event.preventDefault()));
        editor.querySelectorAll('[data-rich-command]').forEach(button=>button.addEventListener('click',()=>runCommand(button.dataset.richCommand)));
        editor.querySelectorAll('[data-rich-format]').forEach(button=>button.addEventListener('click',()=>toggleBlock(button.dataset.richFormat)));
        editor.querySelector('[data-rich-font]')?.addEventListener('change',event=>{if(event.target.value)runCommand('fontName',event.target.value);event.target.value='';});
        editor.querySelector('[data-rich-size]')?.addEventListener('change',event=>{applyFontSize(event.target.value);event.target.value='';});
        editor.querySelector('[data-rich-checklist]')?.addEventListener('click',()=>runCommand('insertHTML','<ul><li>☐ Element de verificat</li></ul><p><br></p>'));
        editor.querySelector('[data-rich-highlight]')?.addEventListener('click',event=>{const active=event.currentTarget.classList.toggle('is-active');runCommand('hiliteColor',active?'#ffd86b':'transparent');});
        editor.querySelector('[data-rich-table]')?.addEventListener('click',()=>runCommand('insertHTML','<table><tbody><tr><td>Coloana 1</td><td>Coloana 2</td></tr><tr><td>Conținut</td><td>Conținut</td></tr></tbody></table><p><br></p>'));
        editor.querySelector('[data-rich-search]')?.addEventListener('click',()=>{const term=prompt('Ce text vrei să cauți în editor?');if(term){surface?.focus();window.find?.(term,false,false,true,false,false,false);}});
        editor.querySelector('[data-rich-theme]')?.addEventListener('click',event=>{
            editor.classList.toggle('is-dark');
            event.currentTarget.classList.toggle('is-active',editor.classList.contains('is-dark'));
            localStorage.setItem('mb-rich-editor-theme',editor.classList.contains('is-dark')?'dark':'light');
        });
        editor.querySelector('[data-rich-color]')?.addEventListener('input',event=>runCommand('foreColor',event.target.value));
        editor.querySelector('[data-rich-color-reset]')?.addEventListener('click',()=>runCommand('foreColor','#3d312b'));
        editor.querySelector('[data-rich-link]')?.addEventListener('click',()=>{const href=prompt('Introdu adresa linkului (https://...)');if(href)runCommand('createLink',href);});
        editor.querySelector('[data-rich-image]')?.addEventListener('click',()=>fileInput?.click());
        fileInput?.addEventListener('change',()=>{uploadRichImage(editor,fileInput.files?.[0]);fileInput.value='';});
        widthInput?.addEventListener('input',event=>{if(!selectedImage)return;const value=event.target.value;selectedImage.style.width=value+'%';selectedImage.style.height='auto';selectedImage.removeAttribute('width');selectedImage.removeAttribute('height');editor.querySelector('[data-rich-image-width-output]')?.replaceChildren(value+'%');syncRichEditor(editor);});
        radiusInput?.addEventListener('input',event=>{if(!selectedImage)return;const value=event.target.value;selectedImage.style.borderRadius=value+'px';editor.querySelector('[data-rich-image-radius-output]')?.replaceChildren(value+'px');syncRichEditor(editor);});
        editor.querySelectorAll('[data-rich-image-align]').forEach(button=>button.addEventListener('click',()=>{if(!selectedImage)return;const align=button.dataset.richImageAlign;selectedImage.style.marginLeft=align==='left'?'0':'auto';selectedImage.style.marginRight=align==='right'?'0':'auto';editor.querySelectorAll('[data-rich-image-align]').forEach(item=>item.classList.toggle('is-active',item===button));syncRichEditor(editor);}));
        editor.querySelectorAll('[data-rich-image-move]').forEach(button=>button.addEventListener('click',()=>{if(!selectedImage)return;const figure=selectedImage.closest('figure')||selectedImage;const direction=button.dataset.richImageMove;if(direction==='up'&&figure.previousElementSibling)figure.parentElement.insertBefore(figure,figure.previousElementSibling);if(direction==='down'&&figure.nextElementSibling)figure.parentElement.insertBefore(figure.nextElementSibling,figure);syncRichEditor(editor);}));
        editor.querySelector('[data-rich-image-close]')?.addEventListener('click',()=>selectImage(null));
        editor.querySelectorAll('[data-rich-mode]').forEach(button=>button.addEventListener('click',()=>{
            const mode=button.dataset.richMode;const isPreview=mode==='preview';syncRichEditor(editor);
            if(preview){preview.innerHTML=editor.querySelector('[data-rich-input]')?.value||'';preview.hidden=!isPreview;}
            if(surface)surface.hidden=isPreview;editor.classList.toggle('is-preview',isPreview);if(inspector&&isPreview)inspector.hidden=true;
            editor.querySelectorAll('[data-rich-mode]').forEach(item=>{const active=item.dataset.richMode===mode;item.classList.toggle('is-active',active);item.setAttribute('aria-pressed',String(active));});
        }));
        syncRichEditor(editor);updateToolbarState();
    });
    productEditor.addEventListener('submit',()=>richEditors.forEach(syncRichEditor));
    const imageInput=productEditor.querySelector('[data-product-images-input]');
    const imageGrid=productEditor.querySelector('[data-product-images]');
    const orderInput=productEditor.querySelector('[data-image-order]');
    const primaryInput=productEditor.querySelector('[data-primary-image-token]');
    let selectedFiles=[];
    const existingImageCount=()=>imageGrid?.querySelectorAll('[data-image-token^="existing:"]').length||0;
    const syncImages=()=>{
        if(!imageGrid||!orderInput||!primaryInput)return;
        const cards=[...imageGrid.querySelectorAll('[data-image-card]')];
        orderInput.value=JSON.stringify(cards.map(card=>card.dataset.imageToken));
        if(!cards.some(card=>card.dataset.imageToken===primaryInput.value))primaryInput.value=cards[0]?.dataset.imageToken||'';
        cards.forEach(card=>card.classList.toggle('is-primary',card.dataset.imageToken===primaryInput.value));
        imageGrid.classList.toggle('is-empty',cards.length===0);
        document.querySelector('[data-product-image-count]')?.replaceChildren(String(cards.length));
    };
    const rebuildFileList=()=>{
        const transfer=new DataTransfer();selectedFiles.forEach(file=>transfer.items.add(file));imageInput.files=transfer.files;
        imageGrid.querySelectorAll('[data-image-token^="new:"]').forEach(card=>card.remove());
        const addTile=imageGrid.querySelector('.product-image-add');
        selectedFiles.forEach((file,index)=>{
            const card=document.createElement('article');card.className='product-image-card';card.draggable=true;card.dataset.imageCard='';card.dataset.imageToken=`new:${index}`;
            card.innerHTML=`<img src="${URL.createObjectURL(file)}" alt="Previzualizare" draggable="false"><span class="image-drag-handle" aria-hidden="true">⠿</span><button type="button" class="image-remove" data-remove-image aria-label="Șterge fotografia">×</button><button type="button" class="image-primary" data-primary-image aria-label="Setează fotografia principală">★</button><span class="image-primary-label">Principală</span>`;
            imageGrid.insertBefore(card,addTile);
        });
        syncImages();
    };
    const productFileKey=file=>`${file.name}:${file.size}:${file.lastModified}`;
    const addProductImages=files=>{
        const known=new Set(selectedFiles.map(productFileKey));
        const allowed=[...files].filter(file=>/^image\/(jpeg|png|webp)$/i.test(file.type)).filter(file=>{const key=productFileKey(file);if(known.has(key))return false;known.add(key);return true;});
        if(!allowed.length)return;
        const available=Math.max(0,12-existingImageCount()-selectedFiles.length);
        if(!available){alert('Poți încărca maximum 12 fotografii pentru un produs.');return;}
        selectedFiles=[...selectedFiles,...allowed.slice(0,available)];
        if(allowed.length>available)alert('Au fost adăugate doar fotografiile care încap în limita de 12.');
        rebuildFileList();
    };
    imageInput?.addEventListener('change',()=>addProductImages(imageInput.files||[]));
    ['dragenter','dragover'].forEach(name=>imageGrid?.addEventListener(name,event=>{if(!dragged&&event.dataTransfer?.types?.includes('Files')){event.preventDefault();imageGrid.classList.add('is-file-over');}}));
    ['dragleave','drop'].forEach(name=>imageGrid?.addEventListener(name,event=>{if(dragged){if(name==='drop')event.preventDefault();imageGrid.classList.remove('is-file-over');return;}if(event.dataTransfer?.types?.includes('Files')){event.preventDefault();imageGrid.classList.remove('is-file-over');if(name==='drop'&&event.dataTransfer.files?.length)addProductImages(event.dataTransfer.files);}}));
    imageGrid?.addEventListener('click',event=>{const card=event.target.closest('[data-image-card]');if(!card)return;if(event.target.closest('[data-primary-image]')){primaryInput.value=card.dataset.imageToken;syncImages();return;}if(event.target.closest('[data-remove-image]')){const token=card.dataset.imageToken;if(token.startsWith('existing:')){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='delete_image_ids[]';hidden.value=token.split(':')[1];productEditor.append(hidden);card.remove();syncImages();}else{selectedFiles.splice(Number(token.split(':')[1]),1);rebuildFileList();}}});
    let dragged=null;
    imageGrid?.addEventListener('dragstart',event=>{dragged=event.target.closest('[data-image-card]');dragged?.classList.add('is-dragging');});
    imageGrid?.addEventListener('dragover',event=>{event.preventDefault();const target=event.target.closest('[data-image-card]');if(!dragged||!target||dragged===target)return;const box=target.getBoundingClientRect();imageGrid.insertBefore(dragged,event.clientX<box.left+box.width/2?target:target.nextSibling);});
    imageGrid?.addEventListener('dragend',()=>{dragged?.classList.remove('is-dragging');dragged=null;syncImages();});
    syncImages();
    const specificationsEditor=productEditor.querySelector('[data-specifications-editor]');
    const specificationsSurface=specificationsEditor?.querySelector('[data-rich-surface]');
    let specificationsTimer=0;
    const buildGeneratedSpecifications=()=>{
      const rows=[];
      const material=productEditor.elements.namedItem('material')?.value?.trim();
      if(material)rows.push(['Material',material]);
      productEditor.querySelectorAll('[data-option-group]').forEach(group=>{
        const name=group.querySelector('input[name="option_name[]"]')?.value?.trim();
        const values=[...group.querySelectorAll('[data-option-value-input]')].map(input=>input.value.trim()).filter(Boolean);
        if(name&&values.length)rows.push([name,[...new Set(values)].join(', ')]);
      });
      if(!rows.length)return '<p>Completează materialul sau opțiunile produsului pentru a genera specificațiile.</p>';
      return '<table><tbody>'+rows.map(([label,value])=>`<tr><th>${escapeAdmin(label)}</th><td>${escapeAdmin(value)}</td></tr>`).join('')+'</tbody></table>';
    };
    const renderGeneratedSpecifications=force=>{
      if(!specificationsEditor||!specificationsSurface)return;
      if(!force&&specificationsEditor.dataset.autoSpecifications!=='1')return;
      specificationsSurface.innerHTML=buildGeneratedSpecifications();
      syncRichEditor(specificationsEditor);
      const status=specificationsEditor.querySelector('[data-rich-status]');
      if(status)status.textContent='Specificații generate din datele produsului. Poți edita orice celulă.';
    };
    specificationsSurface?.addEventListener('input',()=>{specificationsEditor.dataset.autoSpecifications='0';});
    specificationsEditor?.querySelector('[data-generate-specifications]')?.addEventListener('click',()=>{renderGeneratedSpecifications(true);specificationsEditor.dataset.autoSpecifications='0';});
    const scheduleSpecifications=event=>{
      if(event.target?.name!=='material'&&!event.target?.closest?.('[data-option-group]'))return;
      window.clearTimeout(specificationsTimer);specificationsTimer=window.setTimeout(()=>renderGeneratedSpecifications(false),180);
    };
    productEditor.addEventListener('input',scheduleSpecifications);
    productEditor.addEventListener('change',scheduleSpecifications);

    const productFormToast=(message,type='error')=>{
      const region=document.querySelector('[data-toast-region]');
      if(!region){window.alert(message);return;}
      const node=document.createElement('div');
      node.className=`toast toast-${type}`;
      node.textContent=message;
      region.append(node);
      window.setTimeout(()=>node.remove(),5200);
    };
    let productSubmitting=false;
    productEditor.addEventListener('submit',async event=>{
      if(productSubmitting)return;
      event.preventDefault();
      richEditors.forEach(syncRichEditor);
      optionList?.querySelectorAll('[data-option-group]').forEach(syncOptionGroup);
      variantsTarget?.querySelectorAll('[data-variant-row]').forEach(syncVariant);
      syncImages();
      const noCategory=!productEditor.elements.namedItem('primary_category_id')?.value
        &&!productEditor.elements.namedItem('new_category_name')?.value?.trim()
        &&![...productEditor.querySelectorAll('input[name="categories[]"]')].some(input=>input.checked)
        &&![...productEditor.querySelectorAll('input[name="collections[]"]')].some(input=>input.checked);
      if(noCategory&&productEditor.dataset.productExisting!=='1'){
        const accepted=window.confirm('Produsul nu este asociat unei categorii. Sigur vrei să creezi acest produs fără categorie? Îl poți organiza ulterior.');
        if(!accepted)return;
      }
      const submitButton=productEditor.querySelector('button[type="submit"]');
      const initialText=submitButton?.textContent||'';
      if(submitButton){submitButton.disabled=true;submitButton.textContent='Se salvează…';}
      productSubmitting=true;
      try{
        const response=await fetch(productEditor.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(productEditor),redirect:'follow'});
        if(!response.ok){
          let message='Produsul nu a putut fi salvat. Verifică datele marcate.';
          try{const data=await response.json();message=data.message||message;}catch{}
          throw new Error(message);
        }
        if(response.redirected){window.location.assign(response.url);return;}
        const data=await response.json().catch(()=>null);
        if(data?.redirect){window.location.assign(data.redirect);return;}
        productFormToast(data?.message||'Produsul a fost salvat.','success');
      }catch(error){
        productFormToast(error.message||'Produsul nu a putut fi salvat.','error');
      }finally{
        productSubmitting=false;
        if(submitButton){submitButton.disabled=false;submitButton.textContent=initialText;}
      }
    });
  }
  function escapeAdmin(value){return String(value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));}
  const confirmModal=document.querySelector('[data-confirm-modal]');let pendingDeleteForm=null;
  document.addEventListener('submit',event=>{const form=event.target.closest('form[data-confirm-delete]');if(!form||!confirmModal||form.dataset.adminConfirmed==='1')return;event.preventDefault();pendingDeleteForm=form;const message=form.dataset.confirmMessage||'Elementul va fi arhivat și eliminat din zona publică.';confirmModal.querySelector('[data-confirm-message]').textContent=message;confirmModal.hidden=false;document.body.style.overflow='hidden';confirmModal.querySelector('[data-confirm-cancel]')?.focus()});
  const closeConfirm=()=>{if(confirmModal)confirmModal.hidden=true;pendingDeleteForm=null;document.body.style.overflow=''};
  document.querySelectorAll('[data-confirm-cancel]').forEach(button=>button.addEventListener('click',closeConfirm));
  document.querySelector('[data-confirm-accept]')?.addEventListener('click',()=>{const form=pendingDeleteForm;if(!form)return;confirmModal.hidden=true;document.body.style.overflow='';form.dataset.adminConfirmed='1';form.requestSubmit()});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&confirmModal&&!confirmModal.hidden)closeConfirm()});
  document.addEventListener('change',async event=>{
    const input=event.target.closest('[data-category-status]');
    if(!input)return;
    const form=input.closest('[data-category-toggle]');
    const label=form?.querySelector('[data-category-status-label]');
    if(!form)return;
    const previous=!input.checked;
    form.classList.add('is-saving');
    try{
      const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form)});
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||'Starea nu a putut fi actualizată.');
      if(label)label.textContent=data.active?'Vizibilă':'Ascunsă';
      const region=document.querySelector('[data-toast-region]');
      if(region){const node=document.createElement('div');node.className='toast';node.textContent=data.message;region.append(node);setTimeout(()=>node.remove(),3200);}
    }catch(error){
      input.checked=previous;
      if(label)label.textContent=previous?'Vizibilă':'Ascunsă';
      alert(error.message);
    }finally{form.classList.remove('is-saving');}
  });
  const cleanSeoText=value=>{
    const holder=document.createElement('div');
    holder.innerHTML=String(value||'');
    return (holder.textContent||'').replace(/\s+/g,' ').trim();
  };
  const clipSeo=(value,max)=>{
    const text=String(value||'').replace(/\s+/g,' ').trim();
    if(text.length<=max)return text;
    const clipped=text.slice(0,max+1).replace(/\s+\S*$/,'').replace(/[\s,;:–-]+$/,'');
    return clipped+'…';
  };
  const ensureSentence=value=>{
    const text=String(value||'').trim();
    return text&&!/[.!?…]$/.test(text)?text+'.':text;
  };
  document.querySelectorAll('[data-seo-assistant]').forEach(assistant=>{
    const form=assistant.closest('form');
    const titleField=assistant.querySelector('[data-seo-title]');
    const descriptionField=assistant.querySelector('[data-seo-description]');
    const titleCount=assistant.querySelector('[data-seo-title-count]');
    const descriptionCount=assistant.querySelector('[data-seo-description-count]');
    const previewTitle=assistant.querySelector('[data-seo-preview-title]');
    const previewDescription=assistant.querySelector('[data-seo-preview-description]');
    if(!form||!titleField||!descriptionField)return;
    let writing=false;
    let timer=0;
    const hasExistingSeo=Boolean(titleField.value.trim()||descriptionField.value.trim());
    titleField.dataset.seoManual='false';
    descriptionField.dataset.seoManual='false';
    const sources=()=>{
      const kind=assistant.dataset.seoKind;
      const name=cleanSeoText(form.elements.namedItem(kind==='article'?'title':'name')?.value);
      const lead=cleanSeoText(form.elements.namedItem(kind==='article'?'excerpt':'short_description')?.value);
      const material=cleanSeoText(form.elements.namedItem('material')?.value);
      const categorySelect=form.elements.namedItem('primary_category_id');
      const category=categorySelect?.selectedOptions?.[0]?.value?cleanSeoText(categorySelect.selectedOptions[0].textContent):'';
      const rich=kind==='article'?cleanSeoText(form.elements.namedItem('content_html')?.value):cleanSeoText(form.elements.namedItem('description_html')?.value);
      return {kind,name,lead,material,category,rich};
    };
    const build=()=>{
      const source=sources();
      let title=source.name||'Maison Bébé';
      if(source.kind==='product'){
        const qualifier=[source.category,source.material].find(value=>value&&value.toLocaleLowerCase('ro')!==title.toLocaleLowerCase('ro'));
        const suffix=' | Maison Bébé';
        const base=qualifier?`${title} – ${qualifier}`:title;
        title=(base+suffix).length<=60?base+suffix:clipSeo(title,60-suffix.length)+suffix;
      }else{
        const suffix=' | Maison Bébé';
        title=(title+suffix).length<=60?title+suffix:clipSeo(title,60-suffix.length)+suffix;
      }
      let description=source.lead||source.rich;
      if(!description&&source.kind==='product')description=`Descoperă ${source.name||'selecția Maison Bébé'}${source.material?' din '+source.material:''}, ales cu grijă pentru confortul și începuturile celor mici.`;
      if(!description&&source.kind==='article')description=`Descoperă povestea ${source.name||'Maison Bébé'} și idei atent pregătite pentru cele mai prețioase începuturi.`;
      if(description.length<85&&source.kind==='product'){
        const addition=`${source.category?' Face parte din colecția '+source.category+'.':''} Comandă online de la Maison Bébé.`;
        description=(description+' '+addition).trim();
      }
      return {title:clipSeo(title,60),description:clipSeo(ensureSentence(description),160)};
    };
    const paint=()=>{
      titleCount.textContent=`${titleField.value.length}/60`;
      descriptionCount.textContent=`${descriptionField.value.length}/160`;
      titleCount.classList.toggle('is-good',titleField.value.length>=35&&titleField.value.length<=60);
      descriptionCount.classList.toggle('is-good',descriptionField.value.length>=110&&descriptionField.value.length<=160);
      if(previewTitle)previewTitle.textContent=titleField.value||'Titlul paginii';
      if(previewDescription)previewDescription.textContent=descriptionField.value||'Descrierea paginii va apărea aici.';
    };
    const generate=(force=false)=>{
      const suggestion=build();
      writing=true;
      if(force||titleField.dataset.seoManual!=='true')titleField.value=suggestion.title;
      if(force||descriptionField.dataset.seoManual!=='true')descriptionField.value=suggestion.description;
      if(force){titleField.dataset.seoManual='false';descriptionField.dataset.seoManual='false';}
      writing=false;paint();
    };
    [titleField,descriptionField].forEach(field=>field.addEventListener('input',()=>{if(!writing)field.dataset.seoManual='true';paint();}));
    form.addEventListener('input',event=>{
      if(event.target.closest('[data-seo-assistant]'))return;
      window.clearTimeout(timer);timer=window.setTimeout(()=>generate(false),220);
    });
    form.addEventListener('change',event=>{if(!event.target.closest('[data-seo-assistant]'))generate(false);});
    assistant.querySelector('[data-seo-regenerate]')?.addEventListener('click',()=>generate(true));
    if(!hasExistingSeo)generate(false);
    paint();
  });
  const normalizeCouponText=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('ro').trim();
  const initCouponBuilders=(root=document)=>{
  root.querySelectorAll('[data-coupon-builder]').forEach(builder=>{
    if(builder.dataset.couponBuilderReady==='1')return;
    builder.dataset.couponBuilderReady='1';
    const modal=builder.querySelector('[data-coupon-picker]');
    const search=builder.querySelector('[data-coupon-product-search]');
    const productCards=[...builder.querySelectorAll('[data-coupon-product-card]')];
    const scopeFilters=[...builder.querySelectorAll('[data-coupon-filter-kind]')];
    const scopeActions=[...builder.querySelectorAll('[data-coupon-scope-kind]')];
    const categoryInputs=[...builder.querySelectorAll('[data-coupon-category]')];
    const collectionInputs=[...builder.querySelectorAll('[data-coupon-collection]')];
    const productInputs=[...builder.querySelectorAll('[data-coupon-product]')];
    const allCatalogButton=(modal||builder).querySelector('[data-coupon-all-catalog]');
    let activeKind='all',activeId='';
    const hasSelectedGroups=[...categoryInputs,...collectionInputs].some(input=>input.checked);
    const hasSelectedProducts=productInputs.some(input=>input.checked);
    let allCatalogMode=!hasSelectedGroups&&(!hasSelectedProducts||(productInputs.length>0&&productInputs.every(input=>input.checked)));
    if(allCatalogMode)productInputs.forEach(input=>{input.checked=true;});
    const selectedNames=(inputs,selector)=>inputs.filter(input=>input.checked).map(input=>input.closest(selector)?.querySelector('strong')?.textContent?.replace(/^Toată (?:categoria|colecția) „|”$/g,'')||'').filter(Boolean);
    const fillSummary=(node,names,empty)=>{
      if(!node)return;node.replaceChildren();
      if(!names.length){const span=document.createElement('span');span.textContent=empty;node.append(span);return;}
      names.slice(0,3).forEach(name=>{const span=document.createElement('span');span.className='coupon-summary-chip';span.textContent=name;node.append(span);});
      if(names.length>3){const more=document.createElement('span');more.className='coupon-summary-chip more';more.textContent=`+${names.length-3}`;node.append(more);}
    };
    const syncCouponSelection=()=>{
      const categoryNames=selectedNames(categoryInputs,'[data-coupon-scope-kind="category"]');
      const collectionNames=selectedNames(collectionInputs,'[data-coupon-scope-kind="collection"]');
      const productNames=selectedNames(productInputs,'[data-coupon-product-card]');
      fillSummary(builder.querySelector('[data-coupon-category-summary]'),categoryNames,'Nicio categorie selectată');
      fillSummary(builder.querySelector('[data-coupon-collection-summary]'),collectionNames,'Nicio colecție selectată');
      fillSummary(builder.querySelector('[data-coupon-product-summary]'),productNames,'Niciun produs selectat');
      const hasConfiguredSelection=!allCatalogMode&&(categoryNames.length>0||collectionNames.length>0||productNames.length>0);
      const allCatalog=allCatalogMode;
      const selectedCategoryIds=categoryInputs.filter(input=>input.checked).map(input=>String(input.value));
      const selectedCollectionIds=collectionInputs.filter(input=>input.checked).map(input=>String(input.value));
      allCatalogButton?.classList.toggle('is-active',allCatalog);
      allCatalogButton?.setAttribute('aria-pressed',allCatalog?'true':'false');
      (modal||builder).querySelector('[data-coupon-selection-count]')?.replaceChildren(allCatalog?'Tot catalogul':String(categoryNames.length+collectionNames.length+productNames.length));
    builder.querySelector('[data-coupon-scope-note]')?.classList.toggle('is-all-catalog',allCatalog);
      productCards.forEach(card=>{
        const direct=Boolean(card.querySelector('[data-coupon-product]')?.checked);
        const categories=(card.dataset.productCategories||'').split(',').filter(Boolean);
        const collections=(card.dataset.productCollections||'').split(',').filter(Boolean);
        const byCategory=selectedCategoryIds.some(id=>categories.includes(id));
        const byCollection=selectedCollectionIds.some(id=>collections.includes(id));
        const covered=allCatalog||byCategory||byCollection;
        card.classList.toggle('is-selected',direct);
        card.classList.toggle('is-covered',covered&&!direct);
        card.dataset.selectionSource=allCatalog?'Catalog complet':byCategory&&byCollection?'Categorie + colecÈ›ie':byCategory?'Inclus prin categorie':byCollection?'Inclus prin colecÈ›ie':'';
      });
    };
    const filterCouponProducts=()=>{
      const query=normalizeCouponText(search?.value);
      let visible=0;
      productCards.forEach(card=>{
        const categories=(card.dataset.productCategories||'').split(',').filter(Boolean);
        const collections=(card.dataset.productCollections||'').split(',').filter(Boolean);
        const matchesScope=activeKind==='all'||(activeKind==='category'&&categories.includes(activeId))||(activeKind==='collection'&&collections.includes(activeId));
        const matchesSearch=!query||normalizeCouponText(card.dataset.productName).includes(query);
        card.hidden=!(matchesScope&&matchesSearch);if(!card.hidden)visible++;
      });
      scopeActions.forEach(action=>action.hidden=activeKind==='all'||action.dataset.couponScopeKind!==activeKind||action.dataset.couponScopeId!==activeId);
      const empty=(modal||builder).querySelector('[data-coupon-products-empty]');if(empty)empty.hidden=visible!==0;
    };
    const openPicker=()=>{if(!modal)return;document.querySelector('.admin-main')?.classList.remove('is-admin-content-entering');const form=builder.closest('form');if(form&&modal.parentElement!==document.body){if(!form.id)form.id=`coupon-form-${modal.id||Date.now()}`;modal.querySelectorAll('input,select,textarea').forEach(control=>control.setAttribute('form',form.id));document.body.append(modal);}modal.hidden=false;document.body.style.overflow='hidden';window.setTimeout(()=>search?.focus(),80);filterCouponProducts();};
    const closePicker=()=>{if(!modal)return;modal.hidden=true;document.body.style.overflow='';syncCouponSelection();builder.querySelector('[data-coupon-picker-open]')?.focus();};
    builder.querySelector('[data-coupon-picker-open]')?.addEventListener('click',openPicker);
    builder.querySelectorAll('[data-coupon-picker-close]').forEach(button=>button.addEventListener('click',closePicker));
    builder.querySelector('[data-coupon-picker-apply]')?.addEventListener('click',closePicker);
    scopeFilters.forEach(button=>button.addEventListener('click',()=>{activeKind=button.dataset.couponFilterKind||'all';activeId=button.dataset.couponFilterId||'';scopeFilters.forEach(item=>item.classList.toggle('is-active',item===button));filterCouponProducts();}));
    search?.addEventListener('input',filterCouponProducts);
    allCatalogButton?.addEventListener('click',event=>{
      allCatalogMode=!allCatalogMode;
      if(allCatalogMode){
        [...categoryInputs,...collectionInputs].forEach(input=>{input.checked=false;});
        productInputs.forEach(input=>{input.checked=true;});
        activeKind='all';activeId='';
        if(search)search.value='';
        scopeFilters.forEach(item=>item.classList.toggle('is-active',(item.dataset.couponFilterKind||'all')==='all'));
        filterCouponProducts();
      }else{
        [...categoryInputs,...collectionInputs,...productInputs].forEach(input=>{input.checked=false;});
      }
      syncCouponSelection();
      event.currentTarget.blur();
    });
    builder.querySelector('[data-coupon-select-visible]')?.addEventListener('click',()=>{allCatalogMode=false;productCards.filter(card=>!card.hidden).forEach(card=>{card.querySelector('[data-coupon-product]').checked=true;});syncCouponSelection();});
    builder.querySelector('[data-coupon-clear-visible]')?.addEventListener('click',()=>{allCatalogMode=false;productCards.filter(card=>!card.hidden).forEach(card=>{card.querySelector('[data-coupon-product]').checked=false;});syncCouponSelection();});
    [...categoryInputs,...collectionInputs].forEach(input=>input.addEventListener('change',()=>{
      const leavingFullCatalog=allCatalogMode;
      allCatalogMode=false;
      if(leavingFullCatalog)productInputs.forEach(productInput=>{productInput.checked=false;});
      syncCouponSelection();
    }));
    productInputs.forEach(input=>input.addEventListener('change',()=>{allCatalogMode=false;syncCouponSelection();}));
    modal?.addEventListener('keydown',event=>{if(event.key==='Escape')closePicker();});
    syncCouponSelection();filterCouponProducts();
  });
  };
  initCouponBuilders(document);
  document.addEventListener('maison:admin-content',event=>initCouponBuilders(event.detail?.root||document));
  document.querySelector('[data-order-status-form]')?.addEventListener('change',event=>{
    const status=event.target.closest('input[name="status"]');if(!status)return;
    const message=document.querySelector('[data-order-public-message]');if(message&&!message.value.trim())message.placeholder=status.dataset.statusMessage||'';
  });})();


const setCouponCreateModal=(open,modal=document.querySelector('[data-coupon-create-modal]'))=>{
  if(!modal)return;
  document.querySelector('.admin-main')?.classList.remove('is-admin-content-entering');
  modal.hidden=!open;
  document.body.style.overflow=open?'hidden':'';
  if(open)window.setTimeout(()=>modal.querySelector('input[name="code"]')?.focus(),80);
};
document.addEventListener('click',event=>{
  if(event.target.closest('[data-coupon-create-open]'))setCouponCreateModal(true);
  if(event.target.closest('[data-coupon-create-close]'))setCouponCreateModal(false,event.target.closest('[data-coupon-create-modal]'));
});
document.addEventListener('keydown',event=>{const modal=document.querySelector('[data-coupon-create-modal]:not([hidden])');if(event.key==='Escape'&&modal)setCouponCreateModal(false,modal);});
const invoiceActionModal=document.querySelector('[data-invoice-action-modal]');
const setInvoiceActionModal=open=>{if(!invoiceActionModal)return;invoiceActionModal.hidden=!open;document.body.style.overflow=open?'hidden':'';};
document.querySelector('[data-invoice-modal-open]')?.addEventListener('click',()=>setInvoiceActionModal(true));
document.querySelectorAll('[data-invoice-modal-close]').forEach(button=>button.addEventListener('click',()=>setInvoiceActionModal(false)));
invoiceActionModal?.addEventListener('keydown',event=>{if(event.key==='Escape')setInvoiceActionModal(false);});
// Ghid de lansare: ascundere locală și redeschidere din meniul de ajutor.
function initLaunchGuide(root=document){
  const launchGuide=root.querySelector?.('[data-launch-guide]');
  if(!launchGuide||launchGuide.dataset.guideReady==='1')return;
  launchGuide.dataset.guideReady='1';
  const launchGuideKey='maison-bebe-admin-launch-guide-hidden';
  const forceGuide=new URLSearchParams(window.location.search).get('ghid')==='1';
  let hidden=false;
  try{hidden=!forceGuide&&localStorage.getItem(launchGuideKey)==='1';}catch(error){}
  if(forceGuide){
    try{localStorage.setItem(launchGuideKey,'0');}catch(error){}
    hidden=false;
  }
  launchGuide.hidden=hidden;
  launchGuide.querySelector('[data-hide-launch-guide]')?.addEventListener('click',()=>{
    launchGuide.hidden=true;
    try{localStorage.setItem(launchGuideKey,'1');}catch(error){}
    const url=new URL(window.location.href);
    if(url.searchParams.has('ghid')){
      url.searchParams.delete('ghid');
      history.replaceState(history.state,'',url.href);
    }
  });
}
initLaunchGuide(document);
