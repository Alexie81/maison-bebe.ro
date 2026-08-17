(()=>{
  'use strict';

  let activeAdvanced=null;
  let activeAdvancedHome=null;
  const one=(selector,root=document)=>root.querySelector(selector);
  const all=(selector,root=document)=>[...root.querySelectorAll(selector)];
  const number=value=>{const parsed=Number(String(value??'').replace(',','.'));return Number.isFinite(parsed)?parsed:0;};
  const money=value=>number(value).toLocaleString('ro-RO',{minimumFractionDigits:2,maximumFractionDigits:2});
  const units=value=>number(value).toLocaleString('ro-RO',{maximumFractionDigits:0});
  const currencyEntries=[
    ['RON','Leu românesc','lei leu romanesc romania'],['EUR','Euro','euro'],['USD','Dolar american','dolari americani'],['GBP','Liră sterlină','lire sterline pound sterling'],['TRY','Liră turcească','TL lire turcesti lira turceasca turcia'],
    ['AED','Dirham Emiratele Arabe Unite','dirham emirate'],['AFN','Afghani afgan','afganistan'],['ALL','Lek albanez','albania'],['AMD','Dram armean','armenia'],['ANG','Gulden antilez','antilele olandeze'],['AOA','Kwanza angoleză','angola'],['ARS','Peso argentinian','argentina'],['AUD','Dolar australian','australia'],['AWG','Florin aruban','aruba'],['AZN','Manat azer','azerbaidjan'],
    ['BAM','Marcă convertibilă bosniacă','bosnia hertegovina'],['BBD','Dolar Barbados','barbados'],['BDT','Taka bengaleză','bangladesh'],['BGN','Lev bulgăresc','bulgaria'],['BHD','Dinar Bahrain','bahrain'],['BIF','Franc Burundi','burundi'],['BMD','Dolar Bermude','bermude'],['BND','Dolar Brunei','brunei'],['BOB','Boliviano bolivian','bolivia'],['BRL','Real brazilian','brazilia'],['BSD','Dolar Bahamas','bahamas'],['BTN','Ngultrum bhutanez','bhutan'],['BWP','Pula Botswana','botswana'],['BYN','Rublă belarusă','belarus'],['BZD','Dolar Belize','belize'],
    ['CAD','Dolar canadian','canada'],['CDF','Franc congolez','congo'],['CHF','Franc elvețian','elvetia'],['CLF','Unidad de Fomento chiliană','chile'],['CLP','Peso chilian','chile'],['CNH','Renminbi chinezesc offshore','china yuan'],['CNY','Yuan renminbi chinezesc','china'],['COP','Peso columbian','columbia'],['CRC','Colón costarican','costa rica'],['CUP','Peso cubanez','cuba'],['CVE','Escudo Capul Verde','capul verde'],['CZK','Coroană cehă','cehia'],
    ['DJF','Franc Djibouti','djibouti'],['DKK','Coroană daneză','danemarca'],['DOP','Peso dominican','republica dominicana'],['DZD','Dinar algerian','algeria'],['EGP','Liră egipteană','lire egiptene egipt'],['ERN','Nakfa eritreean','eritreea'],['ETB','Birr etiopian','etiopia'],['FJD','Dolar Fiji','fiji'],['FKP','Liră Insulele Falkland','falkland'],['FOK','Coroană feroeză','insulele feroe'],
    ['GEL','Lari georgian','georgia'],['GGP','Liră Guernsey','guernsey'],['GHS','Cedi ghanez','ghana'],['GIP','Liră Gibraltar','gibraltar'],['GMD','Dalasi gambian','gambia'],['GNF','Franc guineean','guineea'],['GTQ','Quetzal guatemalez','guatemala'],['GYD','Dolar Guyana','guyana'],['HKD','Dolar Hong Kong','hong kong'],['HNL','Lempira honduriană','honduras'],['HRK','Kuna croată','croatia'],['HTG','Gourde haitian','haiti'],['HUF','Forint maghiar','ungaria'],
    ['IDR','Rupie indoneziană','indonezia'],['ILS','Șechel israelian','israel'],['IMP','Liră Insula Man','isle of man'],['INR','Rupie indiană','india'],['IQD','Dinar irakian','irak'],['IRR','Rial iranian','iran'],['ISK','Coroană islandeză','islanda'],['JEP','Liră Jersey','jersey'],['JMD','Dolar jamaican','jamaica'],['JOD','Dinar iordanian','iordania'],['JPY','Yen japonez','japonia'],['KES','Șiling kenyan','kenya'],['KGS','Som kârgâz','kargazstan'],['KHR','Riel cambodgian','cambodgia'],['KID','Dolar Kiribati','kiribati'],['KMF','Franc comorian','comore'],['KRW','Won sud-coreean','coreea de sud'],['KWD','Dinar kuweitian','kuweit'],['KYD','Dolar Insulele Cayman','cayman'],['KZT','Tenge kazah','kazahstan'],
    ['LAK','Kip laoțian','laos'],['LBP','Liră libaneză','liban lire libaneze'],['LKR','Rupie srilankeză','sri lanka'],['LRD','Dolar liberian','liberia'],['LSL','Loti lesothian','lesotho'],['LYD','Dinar libian','libia'],['MAD','Dirham marocan','maroc'],['MDL','Leu moldovenesc','moldova'],['MGA','Ariary malgaș','madagascar'],['MKD','Denar macedonean','macedonia'],['MMK','Kyat Myanmar','myanmar birmania'],['MNT','Tugrik mongol','mongolia'],['MOP','Pataca Macao','macao'],['MRU','Ouguiya mauritană','mauritania'],['MUR','Rupie mauritiană','mauritius'],['MVR','Rufiyaa maldiviană','maldive'],['MWK','Kwacha Malawi','malawi'],['MXN','Peso mexican','mexic'],['MYR','Ringgit malaezian','malaezia'],['MZN','Metical mozambican','mozambic'],
    ['NAD','Dolar namibian','namibia'],['NGN','Naira nigeriană','nigeria'],['NIO','Córdoba nicaraguan','nicaragua'],['NOK','Coroană norvegiană','norvegia'],['NPR','Rupie nepaleză','nepal'],['NZD','Dolar neozeelandez','noua zeelanda'],['OMR','Rial oman','oman'],['PAB','Balboa panamez','panama'],['PEN','Sol peruan','peru'],['PGK','Kina Papua Noua Guinee','papua'],['PHP','Peso filipinez','filipine'],['PKR','Rupie pakistaneză','pakistan'],['PLN','Zlot polonez','polonia'],['PYG','Guaraní paraguayan','paraguay'],['QAR','Rial qatarez','qatar'],
    ['RSD','Dinar sârbesc','serbia'],['RUB','Rublă rusească','rusia'],['RWF','Franc ruandez','rwanda'],['SAR','Rial saudit','arabia saudita'],['SBD','Dolar Insulele Solomon','solomon'],['SCR','Rupie Seychelles','seychelles'],['SDG','Liră sudaneză','sudan'],['SEK','Coroană suedeză','suedia'],['SGD','Dolar Singapore','singapore'],['SHP','Liră Sfânta Elena','saint helena'],['SLE','Leone Sierra Leone','sierra leone'],['SLL','Leone vechi Sierra Leone','sierra leone vechi'],['SOS','Șiling somalez','somalia'],['SRD','Dolar surinamez','surinam'],['SSP','Liră sud-sudaneză','sudanul de sud'],['STN','Dobra São Tomé','sao tome'],['SYP','Liră siriană','siria'],['SZL','Lilangeni Eswatini','eswatini'],
    ['THB','Baht thailandez','thailanda'],['TJS','Somoni tadjic','tadjikistan'],['TMT','Manat turkmen','turkmenistan'],['TND','Dinar tunisian','tunisia'],['TOP','Paʻanga Tonga','tonga'],['TTD','Dolar Trinidad și Tobago','trinidad tobago'],['TVD','Dolar Tuvalu','tuvalu'],['TWD','Dolar taiwanez','taiwan'],['TZS','Șiling tanzanian','tanzania'],['UAH','Hrivnă ucraineană','ucraina'],['UGX','Șiling ugandez','uganda'],['UYU','Peso uruguayan','uruguay'],['UZS','Som uzbec','uzbekistan'],['VES','Bolívar venezuelean','venezuela'],['VND','Dong vietnamez','vietnam'],['VUV','Vatu Vanuatu','vanuatu'],['WST','Tala samoană','samoa'],['XAF','Franc CFA Africa Centrală','cfa cemac'],['XCD','Dolar Caraibe de Est','caraibe'],['XCG','Gulden caraibian','curacao sint maarten caribbean guilder'],['XDR','Drepturi speciale de tragere','fondul monetar international'],['XOF','Franc CFA Africa de Vest','cfa'],['XPF','Franc CFP','polinezia'],['YER','Rial yemenit','yemen'],['ZAR','Rand sud-african','africa de sud'],['ZMW','Kwacha zambian','zambia'],['ZWG','Zimbabwe Gold','aur zimbabwe'],['ZWL','Dolar zimbabwean','zimbabwe']
  ];
  const normalizeCurrencySearch=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('ro').replace(/[^a-z0-9]+/g,' ').trim();

  function renderCurrencies(picker,query=''){
    const results=one('[data-currency-results]',picker),empty=one('[data-currency-empty]',picker),selected=one('[data-nir-currency]',picker)?.value||'RON',needle=normalizeCurrencySearch(query);if(!results)return;
    const matches=currencyEntries.filter(([code,name,aliases])=>!needle||normalizeCurrencySearch(`${code} ${name} ${aliases}`).includes(needle));
    results.innerHTML=matches.map(([code,name])=>`<button type="button" role="option" data-currency-option="${code}" aria-selected="${code===selected}"><b>${code}</b><span><strong>${name}</strong><small>${code==='TRY'?'Pe facturi poate apărea și ca TL':'Cod ISO 4217'}</small></span><i>${code===selected?'✓':'Alege'}</i></button>`).join('');
    if(empty)empty.hidden=matches.length!==0;
  }

  function toggleCurrencyPicker(picker,open){
    const popover=one('[data-currency-popover]',picker),trigger=one('[data-currency-toggle]',picker),search=one('[data-currency-search]',picker);if(!popover||!trigger)return;
    all('[data-currency-picker]').forEach(other=>{if(other===picker)return;one('[data-currency-popover]',other)?.setAttribute('hidden','');one('[data-currency-toggle]',other)?.setAttribute('aria-expanded','false');});
    popover.hidden=!open;trigger.setAttribute('aria-expanded',String(open));
    document.body.classList.toggle('is-currency-picker-open',open);
    if(open){if(search)search.value='';renderCurrencies(picker);setTimeout(()=>search?.focus(),30);}
  }

  function selectCurrency(picker,code){
    const entry=currencyEntries.find(item=>item[0]===code);if(!entry)return;
    const input=one('[data-nir-currency]',picker),triggerCode=one('[data-currency-trigger-code]',picker),triggerName=one('[data-currency-trigger-name]',picker);
    if(input){input.value=code;input.dispatchEvent(new Event('change',{bubbles:true}));}
    if(triggerCode)triggerCode.textContent=code;if(triggerName)triggerName.textContent=entry[1];toggleCurrencyPicker(picker,false);
  }

  const formatExchangeDate=value=>String(value||'').split('-').reverse().join('.');

  function showNirExchangeRateMeta(editor){
    const currency=String(one('[data-nir-currency]',editor)?.value||'RON').toUpperCase(),rate=one('[data-nir-exchange-rate]',editor),date=one('[data-nir-exchange-date]',editor),source=one('[data-nir-exchange-source]',editor),help=one('[data-nir-exchange-help]',editor);
    if(!help||!rate)return;
    if(currency==='RON'){help.textContent='Document în lei · curs 1,000000.';return;}
    const numeric=number(rate.value),origin=String(source?.value||'').trim(),rateDate=formatExchangeDate(date?.value||'');
    if(origin&&numeric>0){help.textContent=`1 ${currency} = ${numeric.toLocaleString('ro-RO',{minimumFractionDigits:4,maximumFractionDigits:6})} RON · ${origin}${rateDate?' · '+rateDate:''}`;return;}
    help.textContent='Cursul curent va fi preluat automat. Poți introduce și un curs istoric.';
  }

  function syncNirCurrency(editor){
    const select=one('[data-nir-currency]',editor);if(!select)return;
    const currency=String(select.value||'RON').toUpperCase();
    all('[data-nir-currency-label]',editor).forEach(label=>label.textContent=currency);
    const rate=one('[data-nir-exchange-rate]',editor),date=one('[data-nir-exchange-date]',editor),source=one('[data-nir-exchange-source]',editor),manual=one('[data-nir-exchange-manual]',editor),edit=one('[data-edit-exchange-rate]',editor),refresh=one('[data-refresh-exchange-rate]',editor);
    if(rate){rate.readOnly=true;if(currency==='RON')rate.value='1.000000';}
    if(edit){edit.disabled=currency==='RON';edit.setAttribute('aria-pressed','false');}
    if(refresh)refresh.disabled=currency==='RON';
    if(currency==='RON'){if(date)date.value=new Date().toISOString().slice(0,10);if(source)source.value='RON';if(manual)manual.value='0';}
    showNirExchangeRateMeta(editor);
  }

  function enableManualNirExchangeRate(editor){
    const currency=String(one('[data-nir-currency]',editor)?.value||'RON').toUpperCase(),rate=one('[data-nir-exchange-rate]',editor),edit=one('[data-edit-exchange-rate]',editor),help=one('[data-nir-exchange-help]',editor);
    if(currency==='RON'||!rate)return;
    rate.readOnly=false;if(edit)edit.setAttribute('aria-pressed','true');
    if(help)help.textContent='Introdu cursul istoric în RON. Data cursului va fi data facturii.';
    rate.focus();rate.select();
  }

  function markNirExchangeRateManual(editor){
    const currency=String(one('[data-nir-currency]',editor)?.value||'RON').toUpperCase();if(currency==='RON')return;
    const manual=one('[data-nir-exchange-manual]',editor),source=one('[data-nir-exchange-source]',editor),date=one('[data-nir-exchange-date]',editor),invoiceDate=one('input[name="invoice_date"]',editor);
    if(manual)manual.value='1';if(source)source.value='Manual';if(date)date.value=invoiceDate?.value||date.value||new Date().toISOString().slice(0,10);
    showNirExchangeRateMeta(editor);
  }

  async function loadNirExchangeRate(editor){
    const currency=String(one('[data-nir-currency]',editor)?.value||'RON').toUpperCase();syncNirCurrency(editor);if(currency==='RON')return;
    const endpoint=editor.dataset.exchangeRateUrl,rate=one('[data-nir-exchange-rate]',editor),date=one('[data-nir-exchange-date]',editor),source=one('[data-nir-exchange-source]',editor),help=one('[data-nir-exchange-help]',editor),button=one('[data-refresh-exchange-rate]',editor);
    const manual=one('[data-nir-exchange-manual]',editor),edit=one('[data-edit-exchange-rate]',editor);
    if(!endpoint||!rate)return;editor.dataset.exchangeRateLoading=currency;rate.readOnly=true;if(manual)manual.value='0';if(edit)edit.setAttribute('aria-pressed','false');if(button)button.disabled=true;if(help)help.textContent=`Se preia cursul curent pentru ${currency}…`;
    try{
      const url=new URL(endpoint,location.href);url.searchParams.set('currency',currency);
      const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'}),data=await response.json();
      if(!response.ok)throw new Error(data.message||'Cursul nu a putut fi preluat.');
      if(String(one('[data-nir-currency]',editor)?.value||'').toUpperCase()!==currency)return;
      rate.value=Number(data.rate).toFixed(6);if(date)date.value=data.date||'';if(source)source.value=data.source||'BNR';if(manual)manual.value='0';
      if(help)help.textContent=`1 ${currency} = ${Number(data.rate).toLocaleString('ro-RO',{minimumFractionDigits:4,maximumFractionDigits:6})} RON · ${data.source} · ${String(data.date||'').split('-').reverse().join('.')}${data.stale?' · ultimul curs salvat':''}`;
    }catch(error){if(help)help.textContent=`${error.message} Valoarea salvată a rămas neschimbată.`;}
    finally{if(button)button.disabled=false;delete editor.dataset.exchangeRateLoading;}
  }

  function closeActionMenus(except=null){
    all('.accounting-action-menu[open]').forEach(menu=>{
      if(menu===except)return;
      menu.removeAttribute('open');
      one(':scope>summary',menu)?.setAttribute('aria-expanded','false');
    });
  }

  function closeDatePickers(except=null){
    all('[data-nir-date-picker]').forEach(picker=>{
      if(picker===except)return;
      const popover=one('[data-nir-date-picker-popover]',picker),trigger=one('[data-nir-date-picker-toggle]',picker);
      if(popover)popover.hidden=true;
      if(trigger)trigger.setAttribute('aria-expanded','false');
    });
  }

  function clipboardText(event){
    const plain=event.clipboardData?.getData('text/plain')||'';
    const html=event.clipboardData?.getData('text/html')||'';
    if(!html)return [plain];
    const holder=document.createElement('div');holder.innerHTML=html;
    return [plain,holder.textContent||''];
  }

  function normalizeCompanyCode(value){
    let text=String(value||'').normalize('NFKC')
      .replace(/[\u200B-\u200D\u2060\uFEFF]/g,'')
      .replace(/^(?:CUI|CIF|COD\s*FISCAL|VAT(?:\s*NO)?)[\s:.-]*/i,'')
      .trim()
      .toUpperCase();
    const romanian=text.match(/\bRO\s*[-.:]?\s*([0-9][0-9\s.,-]{1,14})\b/);
    if(romanian)return `RO${romanian[1].replace(/\D/g,'')}`;
    const foreign=text.match(/\b([A-Z]{2})\s*[-.:]?\s*([0-9][0-9\s.,-]{1,14})\b/);
    if(foreign)return `${foreign[1]}${foreign[2].replace(/\D/g,'')}`;
    const numeric=text.match(/\b([0-9][0-9\s.,-]{1,14})\b/);
    if(numeric)return numeric[1].replace(/\D/g,'');
    text=text.replace(/\s+/g,'').replace(/[^A-Z0-9.-]/g,'');
    return /^(?=.*\d)[A-Z0-9][A-Z0-9.-]{1,19}$/.test(text)?text:'';
  }

  function showPasteFeedback(field,message,type='error'){
    const label=field.closest('label');if(!label)return;
    let feedback=one('[data-paste-feedback]',label);
    if(!feedback){feedback=document.createElement('small');feedback.dataset.pasteFeedback='1';label.append(feedback);}
    feedback.className=`accounting-paste-feedback ${type==='success'?'is-success':'is-error'}`;
    feedback.textContent=message;
    window.clearTimeout(Number(feedback.dataset.timer||0));
    feedback.dataset.timer=String(window.setTimeout(()=>feedback.remove(),type==='success'?2500:6000));
  }

  function pasteCompanyCode(event,field){
    const candidates=clipboardText(event);
    const clean=candidates.map(normalizeCompanyCode).find(Boolean)||'';
    event.preventDefault();
    if(!clean){
      field.setCustomValidity('Textul copiat nu conține un CUI valid.');
      showPasteFeedback(field,'Textul din sursă este codificat greșit. Copiază doar CUI-ul sau introdu cifrele manual.');
      return;
    }
    const start=field.selectionStart??0,end=field.selectionEnd??field.value.length;
    field.setRangeText(clean,start,end,'end');
    field.setCustomValidity('');
    field.dispatchEvent(new Event('input',{bubbles:true}));
    field.dispatchEvent(new Event('change',{bubbles:true}));
    showPasteFeedback(field,`CUI lipit corect: ${clean}`,'success');
  }

  function lockPage(){
    const modalOpen=one('.accounting-modal:not([hidden])');
    document.body.style.overflow=modalOpen?'hidden':'';
  }

  function renumber(editor){
    all('[data-nir-line]',editor).forEach((line,index)=>{
      const marker=one('.nir-line-number',line);if(marker)marker.textContent=String(index+1);
      const remember=one('[data-remember-mapping], input[name^="line_remember_mapping"]',line);
      if(remember)remember.name=`line_remember_mapping[${index}]`;
    });
  }

  function calculateLine(line,write=false){
    const invoiced=number(one('[data-qty-invoiced]',line)?.value);
    const received=number(one('[data-qty-received]',line)?.value);
    const accepted=number(one('[data-qty-accepted]',line)?.value);
    const price=number(one('[data-unit-price]',line)?.value);
    const discount=number(one('[data-discount]',line)?.value);
    const rate=number(one('[data-vat-rate]',line)?.value);
    const net=Math.max(0,invoiced*price-discount),vat=net*rate/100,total=net+vat;
    if(write){
      const netInput=one('[data-net]',line),vatInput=one('[data-vat]',line),totalInput=one('[data-total]',line);
      if(netInput)netInput.value=net.toFixed(2);
      if(vatInput)vatInput.value=vat.toFixed(2);
      if(totalInput)totalInput.value=total.toFixed(2);
    }
    return {
      accepted,
      net:number(one('[data-net]',line)?.value),
      vat:number(one('[data-vat]',line)?.value),
      total:number(one('[data-total]',line)?.value),
      received,
      invoiced
    };
  }

  function updateSummary(editor){
    const lines=all('[data-nir-line]',editor);let accepted=0,net=0,vat=0,total=0;
    lines.forEach(line=>{
      const values=calculateLine(line);accepted+=values.accepted;net+=values.net;vat+=values.vat;total+=values.total;
      const title=one('[data-nir-line-title]',line),name=one('[data-line-name]',line);
      if(title)title.textContent=name?.value.trim()||'Produs nou';
      const qtySummary=one('[data-line-qty-summary]',line),totalSummary=one('[data-line-total-summary]',line);
      if(qtySummary)qtySummary.textContent=units(values.accepted);
      if(totalSummary)totalSummary.textContent=money(values.total);
    });
    const values={
      '[data-review-lines]':lines.length,
      '[data-review-accepted]':units(accepted),
      '[data-review-net]':money(net),
      '[data-review-vat]':money(vat),
      '[data-review-total]':money(total)
    };
    Object.entries(values).forEach(([selector,value])=>{const node=one(selector,editor);if(node)node.textContent=String(value);});
    renumber(editor);
  }

  function closeProductDropdowns(except=null){
    all('[data-product-dropdown]:not([hidden])').forEach(dropdown=>{
      if(dropdown===except)return;
      dropdown.hidden=true;
      const trigger=one('[data-toggle-product-dropdown]',dropdown.closest('[data-product-select]'));
      if(trigger)trigger.setAttribute('aria-expanded','false');
    });
  }

  function hydrateProductDropdown(dropdown){
    const results=one('[data-inline-product-results]',dropdown);
    const template=one('#nir-product-results-template');
    if(!results||!template||results.childElementCount)return;
    results.append(template.content.cloneNode(true));
  }

  function toggleProductDropdown(trigger){
    const select=trigger.closest('[data-product-select]'),dropdown=one('[data-product-dropdown]',select);
    if(!dropdown)return;
    const willOpen=dropdown.hidden;
    closeProductDropdowns(willOpen?dropdown:null);
    dropdown.hidden=!willOpen;
    trigger.setAttribute('aria-expanded',String(willOpen));
    if(willOpen){
      hydrateProductDropdown(dropdown);
      const search=one('[data-inline-product-search]',dropdown);
      if(search){search.value='';all('[data-pick-product]',dropdown).forEach(item=>item.hidden=false);setTimeout(()=>search.focus(),30);}
    }
  }

  function productSupplierMappings(line){
    try{
      const value=JSON.parse(line.dataset.supplierMappings||'[]');
      return Array.isArray(value)?value:[];
    }catch(error){return [];}
  }

  function preferredSupplierProductName(line){
    const editor=line.closest('[data-nir-editor]');
    const supplierTax=normalizeCompanyCode(one('input[name="supplier_tax_id"]',editor)?.value||'');
    const supplierName=normalizeSearch(one('[data-nir-supplier-name]',editor)?.value||'');
    const mappings=productSupplierMappings(line);
    const byTax=supplierTax?mappings.find(mapping=>normalizeCompanyCode(mapping.supplier_tax_id_normalized||mapping.supplier_tax_id||'')===supplierTax):null;
    const byName=!byTax&&supplierName?mappings.find(mapping=>normalizeSearch(mapping.supplier_name||'')===supplierName):null;
    return String((byTax||byName)?.supplier_product_name||line.dataset.catalogProductName||'').trim();
  }

  function fillSupplierProductName(line){
    const field=one('[data-line-name]',line),value=preferredSupplierProductName(line);
    if(!field||!value||(field.value.trim()&&line.dataset.nameAutofilled!=='1'))return;
    if(field.value!==value){
      line.dataset.fillingSupplierName='1';
      field.value=value;
      field.dispatchEvent(new Event('input',{bubbles:true}));
      delete line.dataset.fillingSupplierName;
    }
    line.dataset.nameAutofilled='1';
  }

  function refreshSupplierProductNames(editor){
    if(!editor)return;
    all('[data-nir-line]',editor).forEach(fillSupplierProductName);
  }

  function selectProduct(product,line){
    one('[data-line-variant-id]',line).value=product.dataset.variantId||'';
    line.dataset.catalogProductName=product.dataset.product||'';
    line.dataset.supplierMappings=product.dataset.supplierMappings||'[]';
    one('[data-selected-product]',line).textContent=[product.dataset.product,product.dataset.variant].filter(Boolean).join(' · ');
    one('[data-selected-sku]',line).textContent=`SKU ${product.dataset.sku}`;
    one('[data-nir-line-sku]',line).textContent=product.dataset.sku||'Necesită asociere';
    const image=one('[data-selected-product-image]',line),placeholder=one('[data-selected-product-placeholder]',line);
    if(image){image.src=product.dataset.image||'';image.hidden=!product.dataset.image;}
    if(placeholder)placeholder.hidden=Boolean(product.dataset.image);
    const state=one('[data-line-map-state]',line);if(state){state.textContent='Asociat';state.classList.add('is-mapped');}
    const kind=one('[data-selected-product-kind]',line);if(kind)kind.hidden=product.dataset.kind!=='gift_box';
    const note=one('[data-online-note]',line);if(note)note.hidden=product.dataset.unlimited!=='1';
    fillSupplierProductName(line);
    closeProductDropdowns();
  }

  function openAccountingModal(name){
    const modal=one(`[data-accounting-modal="${CSS.escape(name)}"]`);if(!modal)return;
    document.querySelector('.admin-main')?.classList.remove('is-admin-content-entering');
    modal.hidden=false;lockPage();
    setTimeout(()=>one('input:not([type="hidden"]),select,button',modal)?.focus(),30);
  }

  function closeAccountingModal(modal){
    if(!modal)return;modal.hidden=true;lockPage();
  }

  function openLineDetails(line){
    const modal=one('[data-line-details-modal]'),slot=one('[data-line-details-slot]',modal),advanced=one('[data-line-advanced]',line);
    if(!modal||!slot||!advanced)return;
    closeLineDetails();
    activeAdvanced=advanced;activeAdvancedHome=line;
    advanced.hidden=false;slot.append(advanced);modal.hidden=false;lockPage();
  }

  function closeLineDetails(){
    const modal=one('[data-line-details-modal]');
    if(activeAdvanced&&activeAdvancedHome){activeAdvanced.hidden=true;activeAdvancedHome.append(activeAdvanced);}
    activeAdvanced=null;activeAdvancedHome=null;
    if(modal)modal.hidden=true;lockPage();
  }

  const normalizeSearch=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('ro').replace(/[^a-z0-9]+/g,' ').trim();

  function initAccountingProductSearch(root){
    all('[data-accounting-product-search]',root).forEach(search=>{
      if(search.dataset.ready)return;search.dataset.ready='1';
      const input=one('[data-accounting-product-search-input]',search),popup=one('[data-accounting-product-search-popup]',search),count=one('[data-accounting-product-search-count]',search),empty=one('[data-accounting-product-search-empty]',search);
      const results=all('[data-accounting-product-search-result]',search);let visible=[];let active=-1;
      if(!input||!popup)return;
      results.forEach(result=>{result.dataset.normalized=normalizeSearch(result.dataset.search);result.dataset.normalizedName=normalizeSearch(result.dataset.name);result.dataset.normalizedSku=normalizeSearch(result.dataset.sku);});
      const score=(result,query)=>{
        if(!query)return 1;
        const tokens=query.split(' ').filter(Boolean);if(!tokens.every(token=>result.dataset.normalized.includes(token)))return -1;
        if(result.dataset.normalizedSku===query)return 120;
        if(result.dataset.normalizedSku.startsWith(query))return 105;
        if(result.dataset.normalizedName.startsWith(query))return 100;
        if(result.dataset.normalizedName.split(' ').some(word=>word.startsWith(query)))return 85;
        if(result.dataset.normalizedName.includes(query))return 70;
        return 50-tokens.length;
      };
      const activate=index=>{
        visible.forEach(result=>result.classList.remove('is-active'));
        active=visible.length?Math.max(0,Math.min(index,visible.length-1)):-1;
        if(active>=0){visible[active].classList.add('is-active');visible[active].scrollIntoView({block:'nearest'});}
      };
      const run=()=>{
        const query=normalizeSearch(input.value);
        const ranked=results.map((result,index)=>({result,index,score:score(result,query)})).filter(item=>item.score>=0).sort((a,b)=>b.score-a.score||a.index-b.index);
        results.forEach(result=>{result.hidden=true;result.classList.remove('is-active');});
        visible=ranked.slice(0,8).map(item=>item.result);
        visible.forEach(result=>{result.hidden=false;result.parentElement.append(result);});
        if(count)count.textContent=String(ranked.length);
        if(empty)empty.hidden=ranked.length!==0;
        active=-1;popup.hidden=false;
      };
      input.addEventListener('focus',run);input.addEventListener('input',run);
      input.addEventListener('keydown',event=>{
        if(event.key==='ArrowDown'){event.preventDefault();if(popup.hidden)run();activate(active+1);}
        else if(event.key==='ArrowUp'){event.preventDefault();activate(active<=0?visible.length-1:active-1);}
        else if(event.key==='Enter'&&active>=0){event.preventDefault();visible[active].click();}
        else if(event.key==='Escape'){popup.hidden=true;input.blur();}
      });
    });
    if(!document.body.dataset.accountingProductSearchReady){
      document.body.dataset.accountingProductSearchReady='1';
      document.addEventListener('click',event=>{if(!event.target.closest('[data-accounting-product-search]'))all('[data-accounting-product-search-popup]').forEach(popup=>popup.hidden=true);});
      document.addEventListener('keydown',event=>{
        if(event.key!=='/'||event.ctrlKey||event.metaKey||event.altKey||event.target.matches('input,textarea,select,[contenteditable="true"]'))return;
        const input=one('[data-accounting-product-search-input]');if(input){event.preventDefault();input.focus();}
      });
    }
  }

  function addLine(editor){
    const template=one('#nir-line-template'),container=one('[data-nir-lines]',editor);if(!template||!container)return;
    const fragment=template.content.cloneNode(true),line=one('[data-nir-line]',fragment);
    container.append(fragment);updateSummary(editor);
    line?.scrollIntoView({behavior:'smooth',block:'center'});
    setTimeout(()=>one('[data-line-name]',line)?.focus(),260);
  }

  function resetOnlyLine(line,editor){
    const template=one('#nir-line-template');if(!template)return;
    const fresh=template.content.cloneNode(true),replacement=one('[data-nir-line]',fresh);
    line.replaceWith(fresh);updateSummary(editor);
    one('[data-line-name]',replacement)?.focus();
  }

  function init(root=document){
    all('[data-nir-editor]',root).forEach(editor=>{
      if(editor.dataset.accountingReady)return;
      editor.dataset.accountingReady='1';syncNirCurrency(editor);updateSummary(editor);
      if(one('[data-nir-currency]',editor)?.value!=='RON'){
        if(editor.dataset.hasSavedExchangeRate==='1')showNirExchangeRateMeta(editor);else loadNirExchangeRate(editor);
      }
    });
    all('[data-currency-picker]',root).forEach(picker=>renderCurrencies(picker));
    initAccountingProductSearch(root);
  }

  document.addEventListener('input',event=>{
    const exchangeRate=event.target.closest('[data-nir-exchange-rate]');
    if(exchangeRate){markNirExchangeRateManual(exchangeRate.closest('[data-nir-editor]'));return;}
    const companyCode=event.target.closest('[data-company-code]');
    if(companyCode){
      companyCode.value=companyCode.value.toUpperCase().replace(/[^A-Z0-9.-]/g,'');
      companyCode.setCustomValidity('');
      refreshSupplierProductNames(companyCode.closest('[data-nir-editor]'));
      return;
    }
    const supplierName=event.target.closest('[data-nir-supplier-name]');
    if(supplierName){
      refreshSupplierProductNames(supplierName.closest('[data-nir-editor]'));
      return;
    }
    const search=event.target.closest('[data-inline-product-search]');
    if(search){
      const query=search.value.trim().toLocaleLowerCase('ro');
      all('[data-pick-product]',search.closest('[data-product-dropdown]')).forEach(item=>item.hidden=query!==''&&!item.dataset.search.includes(query));
      return;
    }
    const currencySearch=event.target.closest('[data-currency-search]');
    if(currencySearch){renderCurrencies(currencySearch.closest('[data-currency-picker]'),currencySearch.value);return;}
    const line=event.target.closest('[data-nir-line]');if(!line)return;
    if(event.target.matches('[data-line-name]')&&line.dataset.fillingSupplierName!=='1')delete line.dataset.nameAutofilled;
    if(event.target.matches('[data-qty-invoiced]')){
      const received=one('[data-qty-received]',line),accepted=one('[data-qty-accepted]',line);
      if(received)received.value=event.target.value;
      if(accepted)accepted.value=event.target.value;
    }else if(event.target.matches('[data-qty-received]')){
      const accepted=one('[data-qty-accepted]',line);if(accepted)accepted.value=event.target.value;
    }
    const write=event.target.matches('[data-qty-invoiced],[data-unit-price],[data-discount],[data-vat-rate]');
    calculateLine(line,write);updateSummary(line.closest('[data-nir-editor]'));
  });

  document.addEventListener('change',event=>{
    if(event.target.matches('[data-late-entry-toggle]')){const field=one('[data-late-entry-reason]',event.target.closest('form'));if(field)field.hidden=!event.target.checked;}
    if(event.target.matches('[data-nir-currency]'))loadNirExchangeRate(event.target.closest('[data-nir-editor]'));
    if(event.target.matches('input[name="invoice_date"]')){
      const editor=event.target.closest('[data-nir-editor]'),manual=one('[data-nir-exchange-manual]',editor),date=one('[data-nir-exchange-date]',editor);
      if(manual?.value==='1'&&date){date.value=event.target.value;showNirExchangeRateMeta(editor);}
    }
    if(event.target.matches('[data-nir-date-range-form] input[type="date"]')){
      const form=event.target.form,from=one('input[name="from"]',form),to=one('input[name="to"]',form);if(!from||!to)return;
      if(event.target===from&&to.value<from.value)to.value=from.value;
      if(event.target===to&&from.value>to.value)from.value=to.value;
    }
    const supplier=event.target.matches('input[name="supplier_name"]')?event.target:null;
    if(supplier){const option=all('#nir-suppliers option').find(item=>item.value===supplier.value);const tax=one('input[name="supplier_tax_id"]',supplier.form);if(option&&tax&&!tax.value)tax.value=option.dataset.taxId||'';refreshSupplierProductNames(supplier.closest('[data-nir-editor]'));}
  });

  document.addEventListener('paste',event=>{
    const field=event.target.closest('[data-company-code]');
    if(field)pasteCompanyCode(event,field);
  });

  document.addEventListener('click',event=>{
    const currencyToggle=event.target.closest('[data-currency-toggle]');
    if(currencyToggle){const picker=currencyToggle.closest('[data-currency-picker]'),popover=one('[data-currency-popover]',picker);toggleCurrencyPicker(picker,popover.hidden);return;}
    const currencyClose=event.target.closest('[data-currency-close]');
    if(currencyClose){toggleCurrencyPicker(currencyClose.closest('[data-currency-picker]'),false);return;}
    const currencyOption=event.target.closest('[data-currency-option]');
    if(currencyOption){selectCurrency(currencyOption.closest('[data-currency-picker]'),currencyOption.dataset.currencyOption);return;}
    const dateToggle=event.target.closest('[data-nir-date-picker-toggle]');
    if(dateToggle){const picker=dateToggle.closest('[data-nir-date-picker]'),popover=one('[data-nir-date-picker-popover]',picker),open=popover.hidden;closeActionMenus();closeDatePickers(open?picker:null);popover.hidden=!open;dateToggle.setAttribute('aria-expanded',String(open));return;}
    const dateClose=event.target.closest('[data-nir-date-picker-close]');
    if(dateClose){const picker=dateClose.closest('[data-nir-date-picker]');one('[data-nir-date-picker-popover]',picker).hidden=true;one('[data-nir-date-picker-toggle]',picker).setAttribute('aria-expanded','false');return;}
    const rateRefresh=event.target.closest('[data-refresh-exchange-rate]');
    if(rateRefresh){loadNirExchangeRate(rateRefresh.closest('[data-nir-editor]'));return;}
    const rateEdit=event.target.closest('[data-edit-exchange-rate]');
    if(rateEdit){enableManualNirExchangeRate(rateEdit.closest('[data-nir-editor]'));return;}
    const modalTrigger=event.target.closest('[data-open-accounting-modal]');
    if(modalTrigger){openAccountingModal(modalTrigger.dataset.openAccountingModal);return;}
    const modalClose=event.target.closest('[data-close-accounting-modal]');
    if(modalClose){closeAccountingModal(modalClose.closest('[data-accounting-modal]'));return;}
    const lineDetails=event.target.closest('[data-open-line-details]');
    if(lineDetails){openLineDetails(lineDetails.closest('[data-nir-line]'));return;}
    if(event.target.closest('[data-close-line-details]')){closeLineDetails();return;}
    const toggle=event.target.closest('[data-toggle-product-dropdown]');
    if(toggle){toggleProductDropdown(toggle);return;}
    const closeDropdown=event.target.closest('[data-close-product-dropdown]');
    if(closeDropdown){closeProductDropdowns();return;}
    const product=event.target.closest('[data-pick-product]');
    if(product){const line=product.closest('[data-nir-line]');if(line)selectProduct(product,line);return;}
    const add=event.target.closest('[data-add-nir-line]');
    if(add){const editor=add.closest('[data-nir-editor]');if(editor)addLine(editor);return;}
    const remove=event.target.closest('[data-remove-nir-line]');
    if(remove){
      const line=remove.closest('[data-nir-line]'),editor=line.closest('[data-nir-editor]');
      if(activeAdvancedHome===line)closeLineDetails();
      if(all('[data-nir-line]',editor).length===1)resetOnlyLine(line,editor);else{line.remove();updateSummary(editor);}
      return;
    }
    if(!event.target.closest('[data-product-select]'))closeProductDropdowns();
    if(!event.target.closest('[data-nir-date-picker]'))closeDatePickers();
    if(!event.target.closest('.accounting-action-menu'))closeActionMenus();
    if(!event.target.closest('[data-currency-picker]'))all('[data-currency-picker]').forEach(picker=>toggleCurrencyPicker(picker,false));
  });

  document.addEventListener('toggle',event=>{
    const menu=event.target.closest?.('.accounting-action-menu');
    if(!menu)return;
    one(':scope>summary',menu)?.setAttribute('aria-expanded',String(menu.open));
    if(menu.open)closeActionMenus(menu);
  },true);

  document.addEventListener('submit',event=>{
    if(event.target.matches('[data-nir-date-range-form]')){const from=one('input[name="from"]',event.target),to=one('input[name="to"]',event.target),error=one('[data-nir-date-range-error]',event.target);if(from&&to&&from.value>to.value){event.preventDefault();if(error)error.hidden=false;to.focus();return;}if(error)error.hidden=true;}
    if(event.target.matches('[data-nir-confirm]')&&!window.confirm('Confirmarea este definitivă și va crea mișcări numai în Stocuri Conta. Continui?'))event.preventDefault();
  });

  document.addEventListener('keydown',event=>{
    if(event.key!=='Escape')return;
    if(one('[data-line-details-modal]:not([hidden])')){closeLineDetails();return;}
    const modal=one('[data-accounting-modal]:not([hidden])');if(modal){closeAccountingModal(modal);return;}
    const datePicker=one('[data-nir-date-picker-popover]:not([hidden])');if(datePicker){closeDatePickers();return;}
    const currencyPopover=one('[data-currency-popover]:not([hidden])');if(currencyPopover){toggleCurrencyPicker(currencyPopover.closest('[data-currency-picker]'),false);return;}
    if(one('.accounting-action-menu[open]')){closeActionMenus();return;}
    closeProductDropdowns();
  });

  document.addEventListener('DOMContentLoaded',()=>init(document));
  document.addEventListener('maison:admin-content',event=>init(event.detail?.root||document));
})();
