# PROMPT MASTER CODEX - MAISON BÉBÉ V4

## REGULĂ ZERO - PDF-UL V4 ESTE CONTRACTUL ȘI SOURCE OF TRUTH

Înainte să modifici sau să creezi cod:

1. Localizează fișierul exact **`Maison_Bebe_Specificatie_Complete_Website_V4.pdf`**.
2. Citește-l integral, inclusiv suplimentul V4 și documentația V3 inclusă în același PDF.
3. Inspectează toate imaginile/machetele din PDF și, dacă pachetul conține directorul **`mockups/boards/`**, inspectează toate fișierele de acolo.
4. Creează `docs/PDF_PAGE_INVENTORY.md` cu inventarul paginilor/ecranelor și rutele propuse.
5. Creează `docs/PDF_FIDELITY_CHECKLIST.md` cu rând separat pentru fiecare concept vizual **01-62**.

**Nu trata PDF-ul ca inspirație. Nu recrea „aproximativ”. Nu înlocui designul cu Bootstrap template, AdminLTE, dashboard SaaS generic, WordPress, WooCommerce sau o temă cumpărată.**

Ordinea de prioritate în caz de conflict este:

1. mockup-ul vizual din PDF;
2. descrierea funcțională aferentă mockup-ului din PDF;
3. regulile tehnice și de date din PDF;
4. acest prompt;
5. presupunerile tale.

Când PDF-ul arată un control sau o interacțiune, implementează și comportamentul real aferent. Un buton fără handler, un status doar vizual sau o pagină admin fără persistență MySQL nu este implementare completă.

## CERINȚĂ DE LIVRARE

Proiectul trebuie să funcționeze pe un server Apache obișnuit cu:

- Apache 2.4+
- PHP 8.2+
- MySQL 8+
- HTML5
- CSS3 custom
- JavaScript ES6
- PDO
- mod_rewrite
- `.htaccess`

Nu cere Node.js în producție. Build tooling local este permis doar dacă rezultatul final rulează pe Apache/PHP/MySQL fără proces Node persistent.

---

# MASTER PROMPT PENTRU CODEX - MAISON BÉBÉ - IMPLEMENTARE DUPĂ PDF

## 0. REGULA ABSOLUTĂ: PDF-UL ESTE SOURCE OF TRUTH

Înainte să scrii cod, localizează și inspectează integral fișierul **`Maison_Bebe_Specificatie_Complete_Website_V4.pdf`**. Acest PDF este contractul vizual, UX, funcțional și tehnic al proiectului. Nu îl trata ca inspirație aproximativă. **Implementează website-ul după PDF, cu fidelitate maximă, pagină cu pagină, desktop și mobile.**

Ordinea de prioritate în caz de conflict:
1. Mockup-ul vizual din PDF pentru pagina/ecranul respectiv.
2. Specificația funcțională aferentă paginii din PDF.
3. Design system-ul și paleta din PDF.
4. Cerințele tehnice și de securitate din PDF.
5. Acest prompt.

Obligații înainte de implementare:
- Citește toate paginile PDF-ului, nu doar coperta sau primele pagini.
- Fă un inventar intern al tuturor ecranelor, pop-up-urilor, drawer-elor, empty states, loading states, error states și ecranelor admin.
- Nu elimina ecrane pentru că par „secundare”.
- Nu înlocui designul cu Bootstrap, Tailwind UI, AdminLTE, un template generic, un design SaaS sau o interpretare proprie.
- Nu schimba ierarhia vizuală, paleta, densitatea, tipografia, poziționarea headerului, stilul butoanelor sau caracterul editorial.
- Când o imagine de produs din PDF nu este disponibilă ca asset, creează un placeholder coerent în aceeași familie vizuală, dar păstrează exact layout-ul.
- Păstrează proporțiile responsive: mobile nu este desktop micșorat; urmează mockup-ul mobile din PDF.

### Ecrane și interacțiuni vizuale obligatorii din PDF V4

Storefront și cont client:
- Homepage desktop + mobile.
- Shop / catalog.
- Categorie.
- Colecție.
- Pagina produs.
- Favorite / Wishlist.
- Coș complet.
- Mini-cart / cart drawer.
- Popup „produs adăugat în coș”.
- Popup / modal de căutare cu sugestii live.
- Checkout.
- Confirmare comandă.
- Login.
- Register.
- Google Auth.
- Resetare parolă.
- Contul meu.
- Comenzile mele.
- Detaliu comandă client.
- Urmărire comandă.
- Adrese.
- Favorite în cont.
- Gift Box-uri și configurator.
- Despre noi.
- Contact.
- Blog.
- Articol.
- Politici și termeni.
- 404/500 tematizate.

Admin panel:
- Dashboard.
- Facturare overview, date firmă, reguli PF/PJ, facturi, conectori, șabloane, mapper, RO e-Factura.
- Procesatori plăți și configurare Stripe/NETOPIA.
- Configurare Google Auth.
- Integrări livrare, AWB și centru expediții.
- Comenzi.
- Detaliu comandă.
- Produse.
- Adaugă/editează produs.
- Categorii.
- Creează/editează categorie.
- Clienți.
- Centru notificări.
- Popup comandă nouă.
- Cupoane și promoții.
- CMS/pagini/blocuri homepage.
- Recenzii.
- Setări.
- Roluri și permisiuni.
- Audit log.

### Regula pentru categorii și produse

Adminul trebuie să poată:
- crea, edita, arhiva și ordona categorii;
- defini categorie părinte și subcategorii nelimitate logic;
- seta nume, slug, descriere, imagine, status, poziție în meniu, SEO title și SEO description;
- asocia un produs la **una sau mai multe categorii**;
- seta o **categorie principală** pentru canonical/breadcrumb;
- asocia produsul și la una sau mai multe colecții;
- filtra produsele după categorii în admin;
- modifica asocierile fără duplicarea produsului;
- folosi tabel pivot `product_categories(product_id, category_id)` și, dacă este necesar, `primary_category_id` pe produs sau o regulă echivalentă documentată;
- preveni relații invalide și sluguri duplicate;
- păstra integritatea la ștergere/arhivare.

### Regula pentru pop-up-uri și overlays

Implementează real, accesibil și responsive:
- modal căutare;
- popup adăugat în coș;
- cart drawer;
- confirmări de ștergere;
- selector variantă lipsă / eroare stoc;
- login prompt când este necesar;
- newsletter opțional dacă este activat din admin;
- cookie consent configurabil;
- admin popup comandă nouă;
- toast-uri succes/eroare;
- lightbox galerie produs.

Pentru toate:
- focus trap;
- `Escape` închide unde este sigur;
- click pe backdrop conform contextului;
- `aria-modal`, role, label;
- blocare scroll body;
- restaurare focus la elementul declanșator;
- animații discrete și `prefers-reduced-motion`.

### Regula de livrare

Nu te opri după schelet. Extensiile V3 de facturare, e-Factura, pagini produs indexabile, sitemap automat, plăți selectabile, Google Auth și AWB sunt obligatorii.  Continuă până când proiectul este instalabil și funcțional pe Apache + PHP + MySQL. Nu lăsa TODO-uri pentru funcționalitățile obligatorii. Dacă o integrare externă necesită credențiale, implementează adapterul real, configurația `.env`, modul test și fallback-ul operațional documentat.


# 0A. EXTENSII V4 OBLIGATORII - FACTURARE, E-FACTURA, SEO LIFECYCLE, PLATI, GOOGLE AUTH SI LIVRARE/AWB

Această secțiune este **obligatorie și are prioritate asupra oricărei formulări mai vechi din prompt**. PDF-ul V4 include concepte vizuale dedicate pentru modulele de mai jos. Nu implementa simple formulare decorative: toate trebuie conectate la servicii, tabele, queue-uri, audit și stări persistente.

## 0A.1 Regula de arhitectură: toate integrările sunt adapters selectabile din Admin Panel

Creează contracte PHP stabile și implementări separate pentru:

```php
interface InvoiceProviderInterface {
    public function healthCheck(): ProviderHealth;
    public function createInvoice(InvoiceDocument $invoice): ProviderResult;
    public function cancelInvoice(InvoiceDocument $invoice): ProviderResult;
    public function downloadArtifacts(string $externalId): ProviderArtifacts;
    public function syncStatus(string $externalId): ProviderStatus;
}

interface PaymentGatewayInterface {
    public function healthCheck(): ProviderHealth;
    public function createPayment(Order $order): PaymentInitResult;
    public function handleReturn(array $request): PaymentReturnResult;
    public function handleWebhook(string $payload, array $headers): PaymentWebhookResult;
    public function refund(Payment $payment, int $amountMinor): RefundResult;
}

interface ShippingProviderInterface {
    public function healthCheck(): ProviderHealth;
    public function quote(ShipmentDraft $shipment): array;
    public function createAwb(ShipmentDraft $shipment): AwbResult;
    public function cancelAwb(Shipment $shipment): ProviderResult;
    public function getLabel(Shipment $shipment): BinaryDocument;
    public function track(Shipment $shipment): TrackingResult;
}
```

Reguli:
- providerul activ se selectează din Admin Panel;
- există mod `sandbox/test` și `live` când providerul îl oferă;
- secretele sunt criptate la nivel de aplicație și niciodată returnate integral în HTML;
- `healthCheck()` și butonul „Testează conexiunea” sunt reale;
- fiecare request extern are correlation id, timeout, retry controlat și log tehnic fără secrete;
- operațiile de emitere/plată/AWB sunt idempotente;
- nicio integrare externă nu trebuie să blocheze pierderea unei comenzi valide;
- păstrează fallback operațional clar.

## 0A.2 Admin Panel - secțiune completă „Facturare”

Adaugă în sidebar o zonă `Facturare` cu subpagini:
- Overview;
- Datele firmei;
- Reguli PJ-PF / PJ-PJ;
- Facturi și documente;
- Șabloane factură;
- Import și mapare model;
- Conectori sisteme externe;
- Centru RO e-Factura;
- Serii și numerotare;
- Jurnal erori / retry;
- Export contabilitate.

### 0A.2.1 Datele firmei

Adminul poate introduce și modifica:
- denumire legală;
- denumire comercială;
- CUI/CIF;
- număr Registrul Comerțului;
- sediu social complet;
- țară și cod poștal;
- statut TVA / cod TVA;
- regim fiscal configurabil;
- capital social opțional;
- IBAN-uri multiple;
- bancă;
- email facturare;
- telefon;
- website;
- logo fiscal;
- semnătură / elemente vizuale opționale;
- serii de facturi și următorul număr;
- monedă implicită;
- termene de plată;
- note și clauze implicite.

Creează `company_profiles` astfel încât sistemul să poată suporta mai mult de o entitate emitentă în viitor, chiar dacă seed-ul are una singură.

### 0A.2.2 Facturare PJ -> PF și PJ -> PJ

Checkout-ul și adminul trebuie să suporte explicit:

**PJ -> PF / B2C**
- `customer_type = individual`;
- nume și prenume;
- adresă de facturare;
- email;
- telefon;
- câmpurile personale strict necesare, fără a cere identificatori sensibili inutil;
- snapshot al datelor în factură.

**PJ -> PJ / B2B**
- `customer_type = company`;
- denumire companie;
- CUI/CIF;
- nr. Registrul Comerțului opțional/configurabil;
- sediu;
- țară;
- status TVA unde este disponibil;
- persoană de contact;
- email facturare;
- IBAN opțional.

În checkout oferă selector clar `Persoană fizică / Persoană juridică`; schimbarea tipului arată câmpurile relevante. Toate datele se validează server-side și sunt salvate ca snapshot pe comandă/factură, astfel încât modificarea ulterioară a profilului clientului să nu rescrie documentele istorice.

Nu hardcoda pe termen nelimitat reguli fiscale în controller. Creează un `FiscalRuleEngine` configurabil/versionat care decide:
- tip document;
- șablon implicit;
- tratament TVA;
- necesitatea transmiterii către RO e-Factura conform configurației și regulilor aflate în vigoare;
- termen de transmitere configurabil;
- validări suplimentare.

## 0A.3 Integrare cu sistem de facturare existent + fallback intern

Adminul trebuie să poată alege:
1. provider extern de facturare;
2. motor intern Maison Bébé;
3. mod hibrid cu fallback.

Creează `invoice_connectors` și registry de adapters. Include în interfața vizuală exemple de conectori externi, fără coupling în business logic. Un provider se activează numai după configurarea credențialelor și un test de conexiune reușit.

Flux:
1. comanda ajunge într-un trigger configurabil, de exemplu `payment_confirmed` sau `order_confirmed`;
2. se creează `invoice_issue_job` cu `idempotency_key` unic;
3. `FiscalRuleEngine` decide PF/PJ și regulile aplicabile;
4. se construiește un `InvoiceDocument` intern normalizat;
5. routerul trimite către provider extern sau motorul intern;
6. se arhivează snapshotul, PDF-ul și XML-ul disponibil;
7. se inițiază transmiterea e-Factura dacă regula/configurația o cere;
8. se pune emailul clientului în queue;
9. erorile intră în retry queue și admin notification center.

Fallback-ul intern nu are voie să emită automat un duplicat dacă providerul extern a creat deja documentul dar răspunsul s-a pierdut. Folosește idempotency, reconciliation și status `unknown_requires_sync`.

## 0A.4 Motor intern de facturare Maison Bébé

Implementează un motor intern real:
- serii și numerotare concurent-safe;
- tranzacție MySQL / row locking pentru alocarea numărului;
- documente draft și emise;
- factură normală;
- storno / corecție ca flux separat;
- snapshot emitent;
- snapshot client;
- linii de factură;
- discount pe linie și total;
- TVA pe linie și sumar;
- monedă;
- curs de schimb opțional;
- referință comandă;
- referință plată;
- scadență;
- note;
- PDF branduit;
- XML fiscal separat unde este aplicabil;
- hash document și audit trail;
- arhivare nemodificabilă a versiunii emise.

După emitere, o factură nu se editează destructiv. Corecțiile se fac prin operațiuni/documente dedicate și se păstrează istoricul.

## 0A.5 Șabloane de factură Maison Bébé

Implementează în Admin Panel cel puțin aceste patru șabloane selectabile, cu preview desktop și PDF:

1. `Classic Ivory`
   - fundal alb/ivory;
   - logo Maison Bébé sus;
   - serif editorial;
   - accente discrete taupe;
   - recomandat PJ -> PF.

2. `Editorial Taupe`
   - bară superioară taupe;
   - compoziție editorială premium;
   - accent vizual mai puternic;
   - potrivit comenzilor premium.

3. `Minimal Fiscal`
   - densitate mai mare;
   - tabel clar;
   - foarte ușor de citit și arhivat;
   - recomandat PJ -> PJ.

4. `Gift Atelier`
   - branding delicat de cadou;
   - potrivit Gift Box și comenzi speciale;
   - păstrează toate informațiile fiscale obligatorii.

Adminul poate seta șablon implicit:
- global;
- per tip client PF/PJ;
- per canal;
- per categorie de comandă, de exemplu Gift Box;
- manual pe factură înainte de emitere.

Creează un editor de șablon bazat pe HTML/CSS sigur și variabile allowlisted. Nu permite PHP arbitrar în template.

## 0A.6 Încărcarea unui model de factură și „mapare inteligentă”

Cerința este un sistem flexibil, nu o promisiune falsă că orice document poate fi înțeles perfect automat.

Suportă:
- PDF ca fundal/template;
- PNG/JPG ca fundal;
- template HTML/CSS;
- import de model cu text parseabil unde este posibil.

Construiește `Invoice Template Mapper`:
- upload fișier;
- preview pagină;
- detectare dimensiune și orientare;
- încercare de detectare a zonelor dacă fișierul conține text parseabil;
- propuneri de mapare;
- confirmare manuală obligatorie;
- drag & drop / resize pentru zone;
- câmpuri și arrays;
- test cu date demo;
- preview PDF înainte de activare;
- versionare template.

Variabile allowlisted, de exemplu:
```text
{{company.name}}
{{company.tax_id}}
{{invoice.number}}
{{invoice.issue_date}}
{{invoice.due_date}}
{{customer.name}}
{{customer.tax_id}}
{{customer.address}}
{{items[].name}}
{{items[].quantity}}
{{items[].unit_price}}
{{items[].vat_rate}}
{{totals.subtotal}}
{{totals.vat}}
{{totals.grand_total}}
{{payment.method}}
{{order.number}}
```

Pentru tabele repetabile, mapperul trebuie să definească o zonă de start, row template și reguli de continuare pe pagina următoare. Dacă modelul nu poate fi automat interpretat, sistemul trece elegant în mod manual asistat, fără eroare fatală.

## 0A.7 RO e-Factura - integrare directă, queue și status

Implementează un `AnafEInvoiceConnector` izolat de restul aplicației.

Cerințe:
- wizard Admin pentru conectare;
- configurație developer application;
- flux OAuth conform documentației ANAF;
- utilizator autorizat prin mecanismul cerut de ANAF și drepturile relevante;
- token access/refresh stocate criptat;
- refresh controlat;
- generare document XML în formatul cerut de sistemul național și validare înainte de transmitere;
- upload asincron;
- salvare identificator upload;
- polling / sync status;
- download răspunsuri și artefacte;
- asociere răspuns la factura locală;
- retry cu backoff pentru erori tranzitorii;
- dead-letter / `requires_attention` pentru erori persistente;
- notificare admin;
- audit complet;
- separare sandbox/test unde este disponibil și producție;
- limitare rate și throttling configurabil.

Admin page `Centrul RO e-Factura` trebuie să arate:
- conectat/deconectat;
- expirare token;
- queue;
- trimise;
- în procesare;
- acceptate;
- respinse/erori;
- ultima sincronizare;
- buton refresh;
- detaliu răspuns;
- retry manual autorizat;
- link către factura internă.

Important: implementarea poate automatiza transmiterea și schimbul de documente astfel încât operatorul să nu le trimită manual contabilului, dar nu trebuie să pretindă că aplicația înlocuiește obligațiile sau controlul profesional contabil. Include export contabil și acces controlat pentru contabil.

## 0A.8 Export și colaborare cu contabilitatea

Adaugă:
- export facturi pe perioadă;
- PDF;
- XML;
- CSV;
- ZIP securizat;
- filtru PF/PJ;
- status e-Factura;
- status plată;
- note;
- jurnal de erori;
- rol `accountant` read-only configurabil;
- acces la documente, fără acces la setările sensibile ale magazinului;
- opțional email digest periodic, nu trimitere manuală a fiecărei facturi.

## 0A.9 Lifecycle SEO real pentru fiecare produs creat din Admin Panel

Cerința utilizatorului este obligatorie: fiecare produs are **o pagină publică separată și indexabilă**, administrată din aceeași înregistrare de produs.

Nu crea câte un fișier PHP fizic per produs. Folosește o rută dinamică stabilă:
```text
/produs/{slug}
```

Comportament:
- la creare produs: se creează în DB produsul + slug unic + starea paginii;
- `draft`: pagina nu este indexabilă și nu intră în sitemap;
- `published/active`: ruta răspunde `200`, canonical self, intră în sitemap;
- la editare: se editează aceeași pagină publică deoarece view-ul citește datele produsului din DB;
- la schimbare slug: creează automat redirect `301` de la slugul vechi la cel nou și păstrează istoricul;
- la arhivare: scoate din sitemap și aplică regula SEO configurată;
- la ștergere permanentă: adminul trebuie să aleagă între `301` către produs înlocuitor, `301` către categorie relevantă sau `410 Gone`; nu lăsa automat soft-404;
- dacă produsul revine, tratează redirecturile fără loop;
- sitemap-ul se actualizează automat și atomic;
- `lastmod` se schimbă la modificări publice relevante;
- filtrele și combinațiile de query params nu produc index bloat.

În Admin Product Editor adaugă tab `SEO & pagină publică` cu:
- slug;
- URL preview;
- status indexare;
- include/exclude sitemap;
- meta title;
- meta description;
- canonical override numai pentru rol autorizat;
- robots index/noindex;
- Open Graph title/description/image;
- structured data preview;
- Google-like snippet preview;
- selector acțiune la ștergere;
- produs înlocuitor pentru 301;
- istoric sluguri.

### Structured data produs

Generează JSON-LD din date reale:
- `Product`;
- `Offer` / `AggregateOffer` după variante;
- `availability` pe baza stocului real;
- `sku`;
- `brand`;
- `image`;
- `BreadcrumbList`;
- `AggregateRating` numai dacă există recenzii reale și eligibile.

## 0A.10 Sitemap automat

Creează:
```text
/sitemap.xml
/sitemaps/products.xml
/sitemaps/categories.xml
/sitemaps/content.xml
```

Reguli:
- regenerate/invalidare cache la publish, unpublish, delete, restore, slug change;
- exclude draft, archived, private, noindex;
- includere doar URL canonical 200;
- generare atomică: scriere temporară + rename;
- lock pentru a evita coruperea concurrentă;
- cron de reconciliere periodică;
- buton Admin „Regenerează acum”;
- dashboard cu număr URL-uri incluse/excluse și ultimele evenimente.

## 0A.11 Admin Panel - procesator de plăți selectabil

Adaugă `Setări -> Plăți` cu:
- listă providers;
- Stripe;
- NETOPIA Payments;
- Ramburs;
- posibilitatea de a adăuga ulterior alți adapters;
- selectare provider card implicit;
- activare/dezactivare per metodă;
- ordine afișare checkout;
- test/live;
- monede acceptate;
- limită minimă/maximă opțională;
- health status;
- buton test conexiune;
- webhook status;
- ultim eveniment;
- jurnal erori.

### Stripe

Folosește SDK/API oficială și flux server-side adecvat. Pentru integrarea modernă preferă Checkout Sessions + Payment Element unde se potrivește; dacă arhitectura aleasă cere PaymentIntents, documentează motivul. Obligatoriu:
- webhook HTTPS;
- verificare semnătură;
- idempotency;
- nu marca `paid` din redirectul browserului;
- stochează provider payment id;
- reconciliere;
- refund prin adapter;
- test mode.

### NETOPIA Payments

Implementează adapter separat pentru API modernă disponibilă providerului, cu:
- JSON/API token unde cere versiunea folosită;
- configurare sandbox/live;
- status asincron;
- reconciliere;
- callback/webhook conform documentației providerului;
- idempotency locală;
- refund dacă API/contractul activ îl permite, altfel marchează operațiunea ca manuală și nu simula succesul.

Checkout-ul nu cunoaște detaliile providerului. El lucrează numai cu `PaymentGatewayInterface`.

## 0A.12 Google Auth pentru conturile clienților

Conturile clienților trebuie să suporte:
- email + parolă;
- Google Sign-In;
- guest checkout;
- legare controlată a identităților.

Admin `Setări -> Autentificare`:
- enable/disable Google;
- client ID;
- client secret mascat;
- redirect URI afișat copyable;
- status test;
- reguli cont nou;
- reguli de account linking.

Securitate:
- Authorization Code flow server-side;
- `state` anti-CSRF;
- verificarea tokenului/issuer/audience conform bibliotecii oficiale;
- redirect URI strict;
- `oauth_accounts` cu unique `(provider, provider_user_id)`;
- session ID regenerate;
- nu stoca parola Google;
- nu trata simpla adresă email primită dintr-un request neverificat ca dovadă de identitate.

## 0A.13 Livrare, integrare curieri și generare AWB direct din comandă

Adaugă `Setări -> Livrare` și `Comenzi -> Expediții`.

Adminul poate:
- conecta unul sau mai mulți curieri care oferă API;
- introduce credențiale per provider;
- seta sandbox/live unde este disponibil;
- selecta curier implicit;
- mapa servicii;
- configura livrare standard;
- configura ramburs;
- configura locker/pickup dacă providerul suportă;
- configura prag gratuitate;
- configura reguli greutate/zonă;
- păstra fallback manual.

La detaliul comenzii:
- panou `Livrare și AWB`;
- alege curier;
- alege serviciu;
- greutate;
- număr colete;
- dimensiuni opționale;
- ramburs;
- observații;
- buton `Generează AWB`;
- AWB salvat în DB;
- download etichetă PDF;
- print etichetă;
- anulare AWB dacă providerul permite;
- tracking URL;
- tracking timeline;
- status sincronizat;
- email client cu AWB/tracking;
- AWB vizibil și în contul clientului la comanda respectivă.

Automatizare opțională configurabilă:
- când comanda intră în `ready_to_ship`, generează AWB;
- dacă automatizarea e activă, folosește idempotency și nu genera două AWB-uri;
- dacă API-ul e indisponibil, status `awb_pending_retry`, notificare admin, retry queue;
- nu pierde comanda;
- fallback manual.

## 0A.14 Tabele MySQL suplimentare obligatorii

Adaugă minimum:

```text
company_profiles
company_bank_accounts
invoice_series
invoice_templates
invoice_template_versions
invoice_template_fields
invoice_connectors
invoice_connector_credentials
invoices
invoice_items
invoice_events
invoice_artifacts
invoice_issue_jobs
fiscal_rule_sets
fiscal_rule_versions
anaf_connections
anaf_token_store
efactura_submissions
accounting_exports

payment_providers
payment_provider_credentials
payment_provider_health
payments
payment_events
refunds

shipping_providers
shipping_provider_credentials
shipping_services
shipments
shipment_events
shipment_labels
awb_jobs

redirects
sitemap_events
seo_page_states
product_slug_history

oauth_accounts
```

Cerințe:
- InnoDB;
- FK reale;
- unique keys pentru idempotency și provider event IDs;
- timestamps;
- `created_by` / `updated_by` unde are sens;
- encryption envelope pentru credentials/tokens;
- soft delete doar unde este justificat;
- documentele emise nu se șterg cascade accidental.

## 0A.15 Rute Admin/API suplimentare

Exemple obligatorii:

```text
GET  /admin/facturare
GET  /admin/facturare/firma
POST /admin/facturare/firma
GET  /admin/facturare/sabloane
POST /admin/facturare/sabloane/upload
POST /admin/facturare/sabloane/{id}/map
POST /admin/facturare/sabloane/{id}/activate
GET  /admin/facturare/conectori
POST /admin/facturare/conectori/{provider}/test
GET  /admin/facturare/efactura
POST /admin/facturare/efactura/connect
POST /admin/facturare/efactura/sync
POST /admin/facturi/{id}/emit
POST /admin/facturi/{id}/retry-efactura

GET  /admin/setari/plati
POST /admin/setari/plati/{provider}
POST /admin/setari/plati/{provider}/test
POST /webhooks/payments/stripe
POST /webhooks/payments/netopia

GET  /admin/setari/autentificare
POST /admin/setari/autentificare/google

GET  /admin/setari/livrare
POST /admin/setari/livrare/{provider}
POST /admin/setari/livrare/{provider}/test
POST /admin/comenzi/{id}/awb
POST /admin/comenzi/{id}/awb/cancel
GET  /admin/comenzi/{id}/awb/label
POST /cron-or-internal/shipment-sync

POST /admin/produse
PATCH /admin/produse/{id}
DELETE /admin/produse/{id}
GET /produs/{slug}
GET /sitemap.xml
GET /sitemaps/products.xml
```

## 0A.16 Cron/queue-uri suplimentare

Adaugă scripturi compatibile Apache/shared hosting:

```text
cron/process_invoice_jobs.php
cron/process_efactura_queue.php
cron/sync_efactura_status.php
cron/reconcile_invoice_providers.php
cron/reconcile_payments.php
cron/process_awb_jobs.php
cron/sync_shipment_tracking.php
cron/rebuild_sitemaps.php
cron/send_email_queue.php
```

Fiecare job:
- locking;
- batch limit;
- timeout;
- retries;
- exponential backoff;
- dead-letter/attention state;
- metrics minimale;
- safe re-run.

## 0A.17 Variabile `.env.example` suplimentare

```dotenv
APP_ENCRYPTION_KEY=

INVOICE_DEFAULT_PROVIDER=internal
INVOICE_AUTO_ISSUE_TRIGGER=payment_confirmed
INVOICE_DEFAULT_TEMPLATE=classic_ivory

ANAF_CLIENT_ID=
ANAF_CLIENT_SECRET=
ANAF_REDIRECT_URI=
ANAF_ENV=production

STRIPE_PUBLISHABLE_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_MODE=test

NETOPIA_API_KEY=
NETOPIA_MODE=sandbox
NETOPIA_WEBHOOK_SECRET=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

SHIPPING_DEFAULT_PROVIDER=
SHIPPING_AUTO_AWB=false
```

Nu presupune că toate credențialele există. UI-ul trebuie să arate `Neconfigurat`, iar website-ul să rămână funcțional cu metodele active disponibile.

## 0A.18 Acceptance criteria V4 - obligatorii

Nu declara proiectul complet până când toate sunt demonstrate:
- Admin poate introduce datele firmei și o serie de facturi.
- Admin poate alege PF/PJ și sistemul emite corect din punct de vedere al structurii interne cele două tipuri de client.
- Admin poate selecta motor intern sau conector extern.
- Motorul intern poate genera PDF din cel puțin 4 șabloane Maison Bébé.
- Admin poate încărca un model propriu și mapa câmpuri prin mapper asistat.
- Emiterea repetată a aceluiași job nu creează duplicat.
- RO e-Factura connector are wizard, token lifecycle, queue, status și error handling; fără credențiale reale rămâne în starea `not_configured`, nu simulează succes.
- Facturile pot fi exportate contabilului fără trimitere manuală individuală.
- La creare produs se generează URL separat `/produs/{slug}`.
- La editare se schimbă aceeași pagină publică.
- La schimbare slug se creează 301.
- La ștergere/arhivare se aplică explicit 301/410/noindex conform alegerii.
- Sitemap-ul se actualizează automat și exclude draft/noindex.
- Admin poate selecta Stripe sau NETOPIA ca procesator card implicit și poate activa ramburs.
- Webhook-urile de plată sunt verificate și idempotente.
- Google login funcționează când credențialele sunt configurate.
- Admin poate conecta un ShippingProvider și genera AWB din detaliul comenzii.
- AWB-ul este salvat la comandă, eticheta poate fi descărcată, iar tracking-ul apare în contul clientului.
- Dacă providerul de curierat e indisponibil, comanda nu se pierde și jobul intră în retry.
- Toate ecranele V4 respectă mockup-urile din PDF desktop și mobile.


---

# PROMPT PENTRU CODEX - MAISON BÉBÉ

Construiește integral un website e-commerce premium numit **Maison Bébé**, pentru produse de bebeluși, haine, accesorii și Gift Box-uri. Nu livra pseudocod, mock API-uri fără implementare sau pagini goale. Livrează un proiect funcțional, instalabil pe un server Apache obișnuit, cu PHP și MySQL.

## 1. Constrângeri tehnice obligatorii

- Server: Apache 2.4+.
- Backend: PHP 8.2+ procedural-organizat sau OOP simplu, fără Laravel, Symfony, WordPress, WooCommerce sau alt framework full-stack.
- Bază de date: MySQL 8.0+ folosind `utf8mb4`, InnoDB și PDO.
- Frontend: HTML5, CSS3, JavaScript ES6 modular, fără React/Vue/Angular și fără dependență Node.js în producție.
- Permite Composer pentru biblioteci PHP strict necesare, de exemplu PHPMailer, Google API Client și Stripe PHP SDK.
- URL-uri curate prin `.htaccess` și `mod_rewrite`.
- Proiectul trebuie să ruleze din document root sau subdirector configurabil.
- Toate secretele trebuie în `.env` sau fișier de configurare nepublic, niciodată hardcodate în repository.
- Folosește prepared statements PDO peste tot.
- Respectă tranzacții MySQL în checkout, rezervarea stocului, confirmarea plății și anulări.

## 2. Direcție vizuală obligatorie

Reproduce fidel conceptul Maison Bébé: elegant, delicat, premium, foarte aerisit, nuanțe ivory/beige/taupe, fotografii luminoase, spațiu alb generos și serif editorial pentru titluri.

Paleta de bază:
- `#F7F3EE` - fundal principal ivory.
- `#EFE6DC` - crem deschis.
- `#E8DDD2` - bej cald.
- `#CBB3A1` - taupe deschis.
- `#8A6F5E` - maro accent.
- `#3D312B` - text principal foarte închis.
- `#756960` - text secundar.
- `#FFFFFF` / `#FFFCF8` - suprafețe și carduri.

Tipografie:
- Titluri: Playfair Display ca primă alegere; fallback Noto Serif / Georgia.
- UI și body: Montserrat ca primă alegere; fallback Inter / Arial.
- Logo: `MAISON BÉBÉ` cu tracking mare, serif elegant, simbol botanic fin deasupra.

Reguli de design:
- Border-uri de 1px în nuanțe foarte deschise.
- Butoane principale maro `#8A6F5E`, text alb, fără gradient.
- Butoane secundare transparente cu border taupe.
- Radius discret, fără aspect de aplicație SaaS pe storefront.
- Admin panel modern, mai funcțional, cu sidebar închis și carduri albe.
- Mobile-first responsive, fără scroll orizontal.
- Header desktop: top announcement bar, nav stânga, logo central, acțiuni dreapta.
- Header mobil: hamburger, logo central, wishlist/coș dreapta.
- Footer complet cu magazin, ajutor, legal, newsletter și date companie.

## 3. Structura proiectului

Creează o structură clară, de exemplu:

```text
/
  public/
    index.php
    .htaccess
    assets/
      css/
      js/
      images/
      uploads/
  app/
    Config/
    Core/
    Controllers/
    Models/
    Services/
    Repositories/
    Middleware/
    Views/
    Helpers/
  routes/
    web.php
    api.php
    admin.php
  storage/
    logs/
    cache/
    mail/
    private_uploads/
  cron/
    send_email_queue.php
    release_expired_stock.php
    cleanup_sessions.php
  database/
    schema.sql
    seed.sql
    migrations/
  config/
    app.php
  vendor/
  .env.example
  composer.json
  README.md
```

Poți ajusta structura, dar păstrează separarea clară între public, business logic, views, routes și storage.

## 4. Routing și bootstrap

Implementează un front controller `public/index.php` și un router PHP simplu care suportă:
- GET, POST, PUT/PATCH prin method override și DELETE.
- Parametri dinamici, de exemplu `/produs/{slug}`.
- Middleware pentru guest, auth, admin, CSRF și rate limiting.
- Răspuns HTML pentru pagini și JSON pentru endpoint-uri AJAX.
- Pagini 404 și 500 tematizate Maison Bébé.

## 5. Pagini storefront obligatorii

### 5.1 Homepage `/`

Implementează:
- Announcement bar editabil din admin.
- Header responsive.
- Hero split 42/58 cu headline, descriere, CTA și imagine.
- Beneficii: materiale premium, livrare rapidă, ambalaj cadou, retur simplu.
- Colecții circulare: Nou-născut, 0-12 luni, 12-24 luni, Gift Box, Accesorii.
- Produse recomandate.
- Gift Box-uri recomandate.
- Bloc editorial / poveste brand.
- Recenzii selectate.
- Newsletter.
- Footer complet.
- Toate blocurile principale activabile/dezactivabile și ordonabile din admin.

### 5.2 Shop `/shop`

Implementează:
- Grid responsive 4 coloane desktop, 2 mobil.
- Filtre server-side + AJAX opțional: categorie, colecție, mărime, culoare, material, interval preț, disponibilitate.
- Sortare: recomandate, noutăți, preț ascendent/descendent, popularitate.
- Paginare SEO-friendly.
- Query params persistente.
- Card produs cu imagine principală, imagine hover desktop, badge, nume, preț, preț vechi, wishlist.

### 5.3 Categorie `/categorie/{slug}`

Implementează:
- H1, descriere editorială, imagine opțională.
- Breadcrumbs.
- Aceleași filtre ca shop.
- SEO metadata unică.
- JSON-LD BreadcrumbList.

### 5.4 Colecție `/colectie/{slug}`

Implementează colecții curatoriate separate de categorii, cu hero, descriere și produse ordonate manual.

### 5.5 Pagina produs `/produs/{slug}`

Implementează:
- Galerie imagini, thumbnails, zoom/lightbox.
- Nume, SKU, rating, preț, reducere.
- Variante reale: mărime, culoare sau alte opțiuni.
- Selectarea combinației trebuie să rezolve un `product_variant` concret.
- Preț și stoc pot varia pe variantă.
- Buton adaugă în coș.
- Wishlist.
- Descriere, compoziție, îngrijire, livrare și retur, ghid mărimi.
- Stoc disponibil / stoc redus.
- Produse similare.
- Recenzii aprobate și formular recenzie pentru clienți eligibili.
- Schema.org Product și Offer.
- Open Graph.

### 5.6 Gift Box `/gift-box`

Implementează două moduri:
1. Gift Box-uri preconfigurate ca produse normale.
2. Configurator de Gift Box personalizat.

Configuratorul trebuie să permită:
- Alegere cutie/template.
- Alegere produse eligibile.
- Cantități și limite pe componentă.
- Mesaj cadou.
- Nume destinatar opțional.
- Validare stoc pentru toate componentele.
- Preț calculat dinamic.
- Persistarea configurației în coș și apoi în order items / customizations.

### 5.7 Coș `/cos`

Implementează:
- Coș pentru guest prin token cookie securizat.
- Coș pentru user autentificat.
- Merge automat guest cart în user cart după login.
- Modificare cantitate.
- Eliminare produs.
- Varianta afișată clar.
- Gift Box configuration afișată clar.
- Cod promoțional.
- Prag livrare gratuită.
- Recalculare server-side obligatorie.
- Niciodată nu accepta total trimis de client ca sursă de adevăr.

### 5.8 Checkout `/checkout`

Implementează checkout în 1 pagină, responsive:
- Date client.
- Login opțional sau checkout guest.
- Adresă facturare/livrare.
- Checkbox adresă diferită.
- Metodă livrare.
- Metodă plată.
- Câmp mesaj cadou.
- Cod promoțional.
- Sumar sticky desktop.
- Validare client + server.
- Termeni obligatorii.
- Protecție double-submit.
- Idempotency key pentru creare comandă.

### 5.9 Autentificare `/login`, `/register`

Implementează:
- Cont email/parolă.
- `password_hash()` și `password_verify()`.
- Verificare email opțional configurabil.
- Resetare parolă cu token expirat și one-time use.
- Google OAuth 2.0 / OpenID Connect.
- Buton „Continuă cu Google”.
- Flow Authorization Code server-side.
- `state` anti-CSRF.
- Validare token și `sub` Google.
- Leagă contul Google de user existent doar după reguli sigure; nu permite account takeover.
- Stochează provider și provider_user_id în `oauth_accounts`.
- Redirect după login la URL sigur intern.

### 5.10 Cont client `/cont`

Implementează:
- Dashboard.
- Comenzile mele.
- Detaliu comandă.
- Timeline status comandă.
- Adrese salvate.
- Favorite.
- Date personale.
- Preferințe notificări.
- Schimbare parolă pentru conturile cu parolă.
- Deconectare din sesiunea curentă și opțional toate sesiunile.

### 5.11 Urmărire comandă `/urmarire-comanda`

Implementează:
- Pentru guest: order number + email sau token tracking semnat.
- Pentru auth: acces direct la comenzile proprii.
- Timeline public.
- Status public separat de note interne.
- AWB și link curier dacă există.
- Fără expunere de date personale către persoane neautorizate.

### 5.12 Despre noi `/despre-noi`

CMS editabil:
- Hero/poveste.
- Valori.
- Galerie.
- Blocuri text-imagine.
- SEO.

### 5.13 Contact `/contact`

Implementează:
- Date contact.
- Formular nume, email, telefon opțional, subiect, mesaj.
- CSRF.
- Honeypot.
- Rate limit.
- Salvare în DB.
- Email către admin prin queue.
- Răspuns de confirmare.

### 5.14 Blog `/blog`, `/blog/{slug}`

Implementează:
- Listare articole.
- Categorii.
- Paginare.
- Articol cu imagine, autor, dată, timp estimat, blocuri HTML sanitizate.
- Meta title, meta description, canonical.
- Open Graph.
- JSON-LD Article.
- Articole similare.

### 5.15 Politici `/legal/{slug}`

Pagini CMS pentru:
- Termeni și condiții.
- Confidențialitate.
- Cookies.
- Livrare și retur.
- Metode de plată.
- ANPC / SOL.

Nu inventa texte juridice definitive; livrează structură și conținut placeholder clar marcat pentru validare juridică.

## 6. Header, navigație și căutare

Implementează:
- Mega-menu opțional pentru colecții.
- Search overlay.
- Căutare produse după nume, SKU, taguri.
- Sugestii AJAX după minim 2 caractere.
- Wishlist count.
- Cart count.
- User menu.
- Header sticky discret.

## 7. Admin panel obligatoriu `/admin`

Adminul trebuie să fie complet funcțional și protejat prin RBAC.

### 7.1 Dashboard

Afișează:
- Vânzări azi.
- Comenzi noi.
- Clienți noi.
- Produse cu stoc redus.
- Grafic vânzări.
- Comenzi recente.
- Notificări interne.
- Shortcut-uri operaționale.

### 7.2 Comenzi

Implementează:
- Listă cu search, filtre, sortare, paginare.
- Filtre: status, payment status, metodă plată, dată, client.
- Detaliu comandă complet.
- Produse și variante.
- Adrese.
- Plată.
- Istoric status.
- Note interne.
- AWB.
- Actualizare status.
- Opțiune „actualizează și notifică clientul”.
- Export CSV.
- Print order / packing slip.

### 7.3 Produse

Implementează CRUD complet:
- Nume.
- Slug unic.
- SKU.
- Descriere scurtă/lungă.
- Categorie.
- Colecții multiple.
- Brand opțional.
- Material.
- Status draft/active/archived.
- SEO.
- Imagini multiple cu ordonare.
- Variante.
- Opțiuni și valori.
- Preț normal.
- Preț promoțional.
- Cost intern opțional.
- Stoc.
- Prag stoc redus.
- Greutate și dimensiuni.
- Produse similare.

### 7.4 Categorii și colecții

CRUD cu:
- nume;
- slug;
- părinte pentru categorii;
- descriere;
- imagine;
- SEO;
- ordine;
- activ/inactiv.

### 7.5 Clienți

Implementează:
- Listă clienți.
- Search.
- Detaliu client.
- Comenzi.
- Total cheltuit.
- Adrese.
- OAuth providers legate.
- Note interne.
- Blocare cont fără ștergere istoric.

### 7.6 Recenzii

Implementează:
- pending/approved/rejected.
- rating 1-5.
- verificare achiziție.
- răspuns admin opțional.

### 7.7 Cupoane

CRUD:
- cod unic;
- procent sau sumă fixă;
- minim comandă;
- perioadă;
- limită globală;
- limită per user;
- categorii/produse incluse sau excluse;
- activ/inactiv.

### 7.8 Conținut CMS

Implementează:
- homepage sections;
- pages;
- blog;
- announcement bar;
- footer;
- contact details;
- SEO defaults.

### 7.9 Setări

Implementează:
- date magazin;
- email admin;
- monedă RON;
- TVA configurabil;
- prag livrare gratuită;
- metode plată;
- metode livrare;
- email templates;
- Google OAuth configuration din env;
- feature flags.

## 8. Model complet de statusuri comandă

Separă `order_status`, `payment_status` și `fulfillment_status`.

### Order status recomandat
- `new`
- `confirmed`
- `processing`
- `ready_for_shipping`
- `shipped`
- `delivered`
- `cancelled`
- `return_requested`
- `returned`
- `partially_refunded`
- `refunded`

### Payment status
- `unpaid`
- `pending`
- `paid`
- `failed`
- `partially_refunded`
- `refunded`

### Fulfillment status
- `unfulfilled`
- `picking`
- `packed`
- `handed_to_courier`
- `delivered`
- `returned`

Creează `order_status_history` pentru fiecare schimbare, cu:
- order_id;
- old_status;
- new_status;
- public_label;
- public_message;
- is_public;
- changed_by_user_id nullable;
- source: admin/system/payment/courier;
- timestamp.

Nu șterge istoricul.

## 9. Plăți

Implementează arhitectură modulară:

```php
interface PaymentGatewayInterface {
    public function createPayment(Order $order): PaymentInitResult;
    public function handleReturn(array $request): PaymentReturnResult;
    public function handleWebhook(string $payload, array $headers): PaymentWebhookResult;
    public function refund(Payment $payment, int $amountMinor): RefundResult;
}
```

Metode obligatorii:
- Ramburs / cash on delivery.
- Card online printr-un adapter real.

Pentru card, implementează preferabil Stripe Checkout folosind SDK-ul oficial PHP și variabile env. Dacă providerul real nu este configurat, aplicația trebuie să poată rula în modul test/dev fără a marca artificial plăți ca reușite.

Reguli obligatorii:
- Nu considera plata reușită doar din redirect-ul browserului.
- Confirmă prin webhook verificat.
- Verifică semnătura webhook.
- Salvează toate payment events pentru audit.
- Idempotency pentru webhook-uri duplicate.
- Sume în unități minore unde este potrivit.
- Nu stoca date de card.
- Actualizează comanda în tranzacție.
- După payment success: status plată `paid`, notificare admin, email client, opțional order status `confirmed`.

Pregătește o interfață clară pentru a putea adăuga ulterior NETOPIA sau alt provider fără rescrierea checkout-ului.

## 10. Notificări pentru comandă nouă

Implementează simultan trei niveluri:

### 10.1 Notificare internă în admin

La fiecare comandă nouă:
- inserează rând în `notifications`;
- admin bell badge cu număr necitit;
- dropdown cu ultimele notificări;
- link direct la comandă;
- polling AJAX la 20-30 secunde pentru hosting Apache simplu;
- endpoint securizat `/admin/api/notifications/unread`;
- marchează citit individual sau toate.

### 10.2 Email intern

După crearea comenzii:
- adaugă un job în `email_queue`;
- destinatari din `ADMIN_ORDER_EMAILS`;
- subiect: `Comandă nouă #MBxxxx - 408,00 lei`;
- include client, produse, total, plată, livrare și link admin.

Folosește PHPMailer prin SMTP.
Nu bloca tranzacția checkout dacă SMTP nu răspunde. Comanda trebuie să rămână validă, iar emailul să fie reîncercat.

### 10.3 Email client

Trimite:
- confirmare comandă;
- plată confirmată;
- status expediată;
- status livrată;
- anulare;
- retur/refund, când este cazul.

### 10.4 Queue simplă compatibilă cu shared hosting

Tabel `email_queue` cu:
- id;
- template_key;
- recipient;
- subject;
- payload_json;
- status pending/sending/sent/failed;
- attempts;
- next_attempt_at;
- last_error;
- created_at;
- sent_at.

Script cron:
`php cron/send_email_queue.php`

Procesează batch mic, folosește locking și retry exponential.

Opțional: implementează Web Push ca feature separat, dar email + notification center sunt obligatorii.

## 11. Inventar și rezervare stoc

Implementează stoc la nivel de variantă.

Tabele și mecanisme:
- `product_variants.stock_qty` ca snapshot disponibil.
- `inventory_movements` pentru audit.
- `stock_reservations` pentru checkout/plată pending.

Reguli:
- La checkout, lock row `SELECT ... FOR UPDATE`.
- Verifică stocul.
- Creează rezervare cu expirare, de exemplu 15-30 minute configurabil.
- Pentru ramburs, confirmă și transformă rezervarea în scădere de stoc.
- Pentru card, finalizează la payment success conform strategiei documentate.
- Cron eliberează rezervările expirate.
- Anularea restituie stocul dacă este cazul.
- Previne overselling.

## 12. Baza de date MySQL

Creează `database/schema.sql` și `database/seed.sql` complete.

Tabele minime obligatorii:

### Identitate și securitate
- `users`
- `user_addresses`
- `oauth_accounts`
- `password_resets`
- `email_verifications`
- `roles`
- `permissions`
- `user_roles`
- `role_permissions`

### Catalog
- `categories`
- `collections`
- `collection_products`
- `products`
- `product_images`
- `product_options`
- `product_option_values`
- `product_variants`
- `variant_option_values`
- `inventory_movements`
- `stock_reservations`

### Coș și wishlist
- `carts`
- `cart_items`
- `wishlists`
- `wishlist_items`

### Comenzi
- `orders`
- `order_items`
- `order_addresses`
- `order_status_history`
- `order_notes`
- `shipments`

### Plăți
- `payments`
- `payment_events`
- `refunds`

### Marketing
- `coupons`
- `coupon_usages`

### Gift Box
- `gift_box_templates`
- `gift_box_components`
- `gift_box_customizations`

### Conținut
- `blog_categories`
- `blog_posts`
- `pages`
- `reviews`
- `contact_messages`

### Sistem
- `notifications`
- `email_queue`
- `settings`
- `audit_logs`

Cerințe DB:
- FK reale.
- Indexuri pe slug, email, order_number, status, created_at și FK-uri folosite frecvent.
- Unique constraints.
- Soft delete doar unde are sens.
- Timestamps.
- DECIMAL pentru bani sau integer minor units, dar folosește o strategie consistentă.
- Documentează strategia de bani.

## 13. Generarea numerelor de comandă

Implementează număr public, de exemplu `MB20260710-01234`, separat de PK numeric.
Trebuie să fie unic și greu de enumerat trivial.

## 14. Securitate obligatorie

Implementează:
- CSRF token pentru toate mutațiile web.
- SameSite cookies.
- `Secure` în HTTPS.
- `HttpOnly` pentru sesiuni.
- `session_regenerate_id(true)` după login.
- PDO prepared statements.
- Escaping output HTML.
- Sanitizare conținut CMS cu allowlist.
- Upload validation MIME + extensie + dimensiune.
- Nume de fișiere random.
- Blocare execuție PHP în uploads prin `.htaccess`.
- Rate limiting pentru login, register, reset password, contact, tracking.
- Audit log pentru acțiuni admin.
- RBAC.
- Security headers: CSP realistă, X-Content-Type-Options, Referrer-Policy, frame-ancestors.
- Validare redirect URLs.
- Protecție IDOR: fiecare comandă a clientului trebuie verificată după ownership.
- Webhook signature verification.
- Secret rotation documentată.
- Niciun secret în JS.

## 15. Roluri și permisiuni

Seed minim:
- `super_admin`
- `manager`
- `order_operator`
- `catalog_manager`
- `content_editor`
- `customer`

Exemple permisiuni:
- orders.view
- orders.update
- orders.refund
- products.view
- products.create
- products.update
- products.delete
- customers.view
- cms.manage
- settings.manage
- reports.view

## 16. SEO tehnic

Implementează:
- title și meta description pe pagină.
- canonical.
- robots meta.
- sitemap.xml dinamic.
- robots.txt.
- Open Graph.
- JSON-LD Product, BreadcrumbList, Organization, Article.
- sluguri curate.
- paginare indexabilă controlat.
- imagini cu alt.
- lazy loading sub fold.
- WebP/AVIF dacă serverul suportă, cu fallback.
- 301 pentru slug vechi opțional prin tabel redirects.

## 17. Performanță

Implementează:
- asset versioning.
- cache headers pentru static.
- imagini responsive `srcset`.
- lazy loading.
- query pagination.
- evită N+1 queries.
- indexuri DB.
- cache simplu file-based pentru setări și homepage, invalidat la update.
- minificare opțională în build, dar fără Node obligatoriu pe server.

## 18. Accesibilitate

Implementează:
- semantic HTML.
- labels reale.
- focus vizibil.
- keyboard navigation.
- alt text.
- aria pentru modals/menu/cart drawer.
- contrast suficient.
- `prefers-reduced-motion`.

## 19. Email templates

Creează template-uri HTML responsive, branduite Maison Bébé:
- new_order_admin
- order_confirmation_customer
- payment_confirmed
- order_processing
- order_shipped
- order_delivered
- order_cancelled
- password_reset
- email_verification
- contact_admin

Include versiune text fallback.

## 20. Livrare

Implementează metode configurabile:
- Curier la adresă.
- Locker/Easybox ca opțiune generică inițială.
- Ridicare personală opțional.

Tabel `shipments`:
- order_id;
- carrier;
- service;
- awb;
- tracking_url;
- status;
- shipped_at;
- delivered_at.

Nu inventa integrare cu un curier dacă nu există credențiale. Creează adapter interface și funcționare manuală completă cu AWB introdus din admin.

## 21. API/AJAX intern

Creează endpoint-uri JSON pentru:
- cart add/update/remove;
- wishlist toggle;
- search suggestions;
- variant availability;
- coupon apply/remove;
- checkout validation;
- admin notification polling;
- admin mark notification read;
- order status update;
- image ordering.

Toate mutațiile trebuie CSRF-protected dacă folosesc sesiune.

## 22. `.htaccess`

Livrează reguli pentru:
- front controller;
- blocarea accesului la `.env`, config, storage, database;
- disable directory listing;
- protecția uploads;
- canonical HTTPS opțional configurabil;
- cache headers pentru assets;
- fără redirect loop.

## 23. Instalare

Livrează `README.md` foarte clar:
1. cerințe Apache/PHP/MySQL;
2. creare DB;
3. import schema;
4. import seed;
5. configurare `.env`;
6. `composer install --no-dev`;
7. permisiuni storage/uploads;
8. DocumentRoot recomandat către `/public`;
9. alternativă dacă hostingul nu permite schimbarea DocumentRoot;
10. cron jobs;
11. Google OAuth redirect URI;
12. SMTP;
13. Stripe webhook;
14. creare primul super admin.

Creează `.env.example` cu:

```env
APP_ENV=production
APP_URL=https://example.ro
APP_KEY=
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maison_bebe
DB_USERNAME=
DB_PASSWORD=
SESSION_NAME=maison_session
SMTP_HOST=
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Maison Bébé"
ADMIN_ORDER_EMAILS=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=
FREE_SHIPPING_THRESHOLD=50000
STOCK_RESERVATION_MINUTES=20
```

## 24. Seed data

Include seed cu:
- categoriile Nou-născut, 0-12 luni, 12-24 luni, Gift Box, Accesorii;
- 8-12 produse demo;
- variante de mărime;
- 2-3 colecții;
- 3 Gift Box templates;
- pagini legale placeholder;
- 3 articole blog;
- roluri și permisiuni.

Nu include parole reale. Pentru super admin, oferă script CLI sau instrucțiune sigură.

## 25. Teste și criterii de acceptanță

Creează minimum:
- teste pentru calcul total comandă;
- coupon validation;
- stock reservation;
- order status transitions;
- ownership access la comenzi;
- CSRF;
- login;
- Google OAuth callback logic izolată unde este posibil;
- webhook idempotency;
- cart merge;
- guest order tracking authorization.

Dacă nu folosești PHPUnit, creează un test runner PHP simplu, dar preferă PHPUnit prin Composer.

### Acceptance criteria critice

- Site-ul se instalează pe Apache + PHP + MySQL fără Node în producție.
- Homepage și toate paginile sunt responsive.
- Admin poate crea un produs cu variante și imagini.
- Clientul poate adăuga o variantă în coș.
- Checkout recalculează totul server-side.
- Se creează comandă în DB.
- Comanda nouă apare imediat în admin notifications.
- Se creează email intern în queue.
- Clientul primește confirmare prin queue.
- Admin schimbă statusul și clientul vede timeline actualizat.
- Google login funcționează când credențialele sunt configurate.
- Plata ramburs funcționează.
- Plata card funcționează în test mode când Stripe este configurat.
- Webhook-ul este idempotent și verificat.
- Nu există SQL injection evident, XSS stored evident sau IDOR pe comenzi.
- Toate paginile critice au state loading/error/empty.

## 26. Ordinea de implementare

Lucrează incremental și păstrează proiectul rulabil după fiecare etapă:

1. Bootstrap, config, env, router, DB, error handling.
2. Schema și seed.
3. Design system, layout, header, footer.
4. Catalog + homepage + shop + category + product.
5. Auth email + sessions.
6. Google OAuth.
7. Cart + wishlist.
8. Checkout + orders + stock.
9. Payment abstraction + COD + card.
10. Email queue + notifications.
11. Customer account + tracking.
12. Admin dashboard + orders.
13. Admin products/categories/collections.
14. Coupons/reviews/CMS/blog.
15. Gift Box configurator.
16. SEO/security/performance.
17. Tests/docs/deployment.

## 27. Modul de lucru cerut de la Codex

- Înainte de cod, inspectează repository-ul existent.
- Nu șterge fișiere utile fără motiv.
- Creează un plan scurt, apoi implementează.
- După fiecare modul, rulează verificări sintaxă PHP și teste.
- Folosește `php -l` pe fișierele PHP modificate.
- Verifică SQL schema.
- Nu lăsa TODO-uri pentru funcționalități obligatorii.
- Nu crea butoane fără handler.
- Nu crea formulare fără backend.
- Nu crea statusuri doar vizuale; persistă în DB.
- Nu crea plăți fake în production mode.
- Nu hardcoda user/admin/demo în business logic.
- Documentează deciziile importante.

## 28. Livrabile finale obligatorii

La final repository-ul trebuie să conțină:
- cod complet storefront;
- cod complet admin;
- SQL schema;
- seed;
- migrations sau strategie de upgrade;
- `.env.example`;
- `.htaccess`;
- Composer config;
- README instalare;
- cron scripts;
- email templates;
- teste;
- date demo;
- document de arhitectură scurt;
- listă credențiale/config necesare pentru Google, SMTP și payment provider.

## 29. Cerință finală de fidelitate vizuală

Aspectul trebuie să urmeze strict conceptul premium Maison Bébé:
- ivory/cream;
- serif editorial;
- logo central;
- fotografii calde;
- mult spațiu alb;
- grile curate;
- mobile elegant;
- storefront fără aspect generic Bootstrap;
- admin funcțional, dar în aceeași familie cromatică.

Începe implementarea direct. Livrează fișiere reale și funcționale, nu doar explicații.


# 31. VERIFICARE FINALĂ OBLIGATORIE FAȚĂ DE PDF

La final, înainte să consideri proiectul terminat:

1. Deschide din nou `Maison_Bebe_Specificatie_Complete_Website_V4.pdf`.
2. Compară fiecare mockup desktop și mobile cu implementarea.
3. Creează o matrice `docs/PDF_FIDELITY_CHECKLIST.md` cu câte un rând pentru fiecare ecran din PDF și coloane:
   - ecran;
   - rută;
   - desktop implementat;
   - mobile implementat;
   - funcționalitate backend;
   - empty state;
   - loading state;
   - error state;
   - accesibilitate;
   - status final.
4. Nu marca finalizat un ecran dacă este doar vizual fără backend, sau doar backend fără fidelitate vizuală.
5. Verifică explicit:
   - wishlist;
   - cart drawer;
   - popup add-to-cart;
   - search modal;
   - categorii many-to-many cu produse;
   - categorie principală;
   - variante și stoc;
   - Google Auth;
   - plăți și webhook;
   - notification center;
   - email queue;
   - popup comandă nouă;
   - timeline comandă client;
   - admin create/edit category;
   - admin create/edit product;
   - asociere produs-categorii;
   - mobile parity.
6. Rulează testele și oferă în README pașii exacți de instalare pe Apache.

**Nu declara proiectul complet până când checklist-ul de fidelitate față de PDF nu este integral completat.**


# 0B. EXTENSII V4 OBLIGATORII - ATELIER MAISON BÉBÉ, ARTICOLE INDEXABILE ȘI SEO AVANSAT

Această secțiune este obligatorie și are prioritate asupra oricărei formulări mai vechi din prompt. Ea adaugă cerințele editoriale și SEO finale cerute de beneficiar.

## 0B.1 Numele și poziționarea secțiunii editoriale

Nu folosi ca denumire principală un simplu „Blog” în interfața publică. Implementează:

- nume public: **Atelier Maison Bébé**;
- subtitlu: **Povești pentru începuturi prețioase**;
- rută landing: `GET /atelier`;
- rută articol: `GET /atelier/{slug}`.

Categorii editoriale seed recomandate:

- Ghiduri;
- Începuturi;
- Materiale;
- Cadouri;
- Din Atelier;
- Noutăți;
- Inspirație.

Poți păstra alias/redirect pentru `/blog` dacă există compatibilitate istorică, dar URL-ul public preferat din implementarea nouă este `/atelier`.

## 0B.2 FIECARE PRODUS PUBLICAT = PAGINĂ SEPARATĂ ȘI INDEXABILĂ TEHNIC

Cerință absolută:

- când adminul creează un produs și îl publică, sistemul trebuie să creeze **logic** o pagină publică separată;
- ruta recomandată este `GET /produs/{slug}`;
- pagina este generată din MySQL prin router/controller PHP;
- **nu crea câte un fișier `.php` fizic pentru fiecare produs**;
- o pagină dinamică este o pagină separată și indexabilă dacă are URL propriu și răspunde corect.

### La CREATE produs

În aceeași unitate logică/tranzacțională:

1. validează datele;
2. generează sau validează slug unic;
3. salvează produsul;
4. sincronizează categoriile many-to-many;
5. salvează categoria principală;
6. sincronizează colecțiile;
7. salvează variantele, SKU-urile și stocul;
8. salvează imaginile;
9. salvează câmpurile SEO;
10. dacă statusul este `published/active`, ruta publică devine disponibilă cu HTTP 200;
11. creează eveniment pentru sitemap;
12. invalidează cache-ul afectat;
13. scrie audit log.

Pagina produs publicată trebuie să aibă:

- URL unic;
- HTTP 200;
- HTML server-rendered pentru conținutul principal;
- `<title>` unic;
- meta description editabilă/unică;
- canonical self;
- `index,follow` implicit pentru produs public eligibil;
- H1 unic;
- Product JSON-LD bazat pe date reale;
- Offer/availability bazate pe date reale;
- BreadcrumbList;
- Open Graph;
- imagini responsive;
- internal links;
- includere în `/sitemaps/products.xml`.

### La EDIT produs

- editezi aceeași pagină publică;
- URL-ul rămâne stabil dacă slugul nu se schimbă;
- modificările semnificative pot actualiza `lastmod`;
- cache-ul paginii se invalidează;
- structured data se regenerează din datele curente;
- dacă slugul se schimbă, creează automat redirect permanent din vechiul URL către noul URL.

### La DELETE / ARCHIVE produs

Adminul trebuie să aleagă sau sistemul să aplice o politică explicită:

- `archive`;
- `301` către produs înlocuitor;
- `301` către categorie relevantă, numai dacă este o destinație real utilă;
- `410 Gone` dacă produsul este eliminat definitiv și nu există înlocuitor.

Interzis:

- soft 404 cu HTTP 200;
- redirectarea tuturor produselor șterse către homepage;
- păstrarea redirecturilor/404/410 în sitemap;
- ștergerea slugului vechi fără istoric când URL-ul a fost public.

## 0B.3 FIECARE ARTICOL PUBLICAT = PAGINĂ SEPARATĂ ȘI INDEXABILĂ TEHNIC

Când adminul publică un articol:

- creează logic ruta `GET /atelier/{slug}`;
- HTML principal server-rendered;
- HTTP 200;
- canonical self;
- index,follow dacă articolul este public și eligibil;
- intră în `/sitemaps/atelier.xml`;
- are meta title și description;
- are H1 unic;
- are `Article` sau `BlogPosting` JSON-LD;
- are `BreadcrumbList`;
- are `datePublished` și `dateModified` bazate pe date reale;
- are autor/publisher configurat;
- are featured image și OG metadata;
- are internal links către articole și produse relevante.

La schimbarea slugului articolului:

- vechiul URL -> `301` -> noul URL;
- sitemap-ul păstrează numai URL-ul nou;
- canonical devine noul URL;
- istoricul rămâne în `url_redirects`.

La ștergere:

- 301 la articol înlocuitor real; sau
- 301 la categorie editorială relevantă dacă este justificat; sau
- 410 Gone.

Drafturile, preview-urile și articolele programate înainte de publicare:

- nu intră în sitemap;
- nu sunt indexabile;
- preview-ul folosește token securizat și expiring;
- nu este accesibil prin enumerare ID.

## 0B.4 Admin Panel - modul complet „Atelier Maison Bébé”

Adaugă în Admin Panel o zonă proprie:

- Overview / listă articole;
- Adaugă articol;
- Editează articol;
- Categorii editoriale;
- Etichete;
- Calendar editorial;
- Revizii;
- SEO și indexare;
- Open Graph / social preview;
- blocuri homepage;
- setări editoriale.

### Listă articole

Afișează:

- titlu;
- slug;
- featured image;
- categorie;
- autor;
- status;
- data publicării;
- status SEO/indexabilitate;
- updated_at;
- acțiuni.

Filtre:

- status;
- categorie;
- autor;
- publicat/programat;
- indexabil/noindex;
- necesită optimizare SEO.

Statusuri recomandate:

- `draft`;
- `in_review`;
- `scheduled`;
- `published`;
- `archived`.

## 0B.5 Editor articol real

Editorul trebuie să suporte:

- titlu;
- slug;
- excerpt;
- categorie principală;
- categorii multiple;
- tag-uri;
- autor;
- featured image;
- conținut bogat;
- H2/H3;
- paragraf;
- liste;
- citate;
- linkuri;
- imagini;
- galerie;
- CTA;
- produs asociat;
- bloc produse;
- note interne;
- preview.

Conținutul HTML:

- se sanitizează server-side prin allowlist;
- previne stored XSS;
- linkurile externe pot avea reguli `rel` configurabile;
- imaginile sunt validate și optimizate.

## 0B.6 Calendar editorial și programare

Implementează:

- calendar lunar;
- vizualizare articole programate;
- `scheduled_at`;
- timezone configurabilă;
- cron pentru publicare;
- job idempotent;
- la publish: update sitemap, cache invalidation, audit, notificare internă;
- la eșec: retry și notification center.

## 0B.7 Revizii articol

Creează:

- `blog_post_revisions`;
- version number;
- editor_user_id;
- snapshot sau strategie delta documentată;
- diff;
- restore;
- restore creează versiune nouă, nu șterge istoricul.

## 0B.8 Taxonomii editoriale

Creează:

```sql
blog_categories
blog_tags
blog_post_categories
blog_post_tags
```

Reguli:

- relații many-to-many;
- o categorie principală opțională;
- slug unic;
- CRUD din admin;
- categorii editoriale pot avea pagină indexabilă dacă au conținut suficient;
- tag-urile sunt implicit `noindex` pentru a evita pagini subțiri și duplicate;
- adminul poate schimba această politică explicit.

## 0B.9 Sitemap automat V4

Implementează:

```text
/sitemap.xml
/sitemaps/products.xml
/sitemaps/categories.xml
/sitemaps/atelier.xml
/sitemaps/pages.xml
```

Reguli:

- numai URL-uri canonice dorite în Google;
- URL-uri absolute;
- UTF-8;
- `lastmod` numai pentru schimbări reale semnificative;
- nu include redirecturi;
- nu include 404;
- nu include 410;
- nu include noindex;
- nu include admin;
- nu include cont client;
- nu include coș/checkout;
- nu include preview.

Actualizare automată la:

- create/publish produs;
- edit produs semnificativ;
- slug produs;
- archive/delete produs;
- publish articol;
- edit articol semnificativ;
- slug articol;
- unpublish/delete articol;
- publish/unpublish categorie.

Folosește:

- `sitemap_events`;
- generare atomică;
- cache invalidation;
- cron de reconciliere.

## 0B.10 Search Console - integrare opțională, corectă

Poți implementa din Admin Panel:

- conectare proprietate Search Console;
- submit sitemap prin Search Console API;
- listare sitemap status;
- URL Inspection pentru diagnostic dacă există autorizare și quota.

**Interdicție explicită:**

Nu utiliza Google Indexing API pentru produse sau articole generale. Nu trimite pagini e-commerce obișnuite către Indexing API. Implementarea trebuie să respecte eligibilitatea oficială Google.

În UI afișează clar:

> „Pagina este eligibilă tehnic pentru crawling/indexare; Google decide dacă și când o indexează.”

Nu afișa:

> „Garantat indexată în Google”.

## 0B.11 Centru intern de indexabilitate

Creează `/admin/seo/indexabilitate`.

Pentru fiecare URL important verifică:

- tip entitate;
- HTTP status;
- publicat/draft;
- meta robots;
- X-Robots-Tag;
- canonical;
- canonical target status;
- în sitemap / nu;
- title prezent și duplicate;
- H1;
- structured data availability;
- orphan candidate;
- redirect chain;
- conflict slug.

Detectează:

- `noindex` în sitemap;
- canonical către 404;
- canonical către redirect;
- 200 soft 404 candidate;
- pagină publicată absentă din sitemap;
- pagină draft în sitemap;
- produs/articol fără internal links;
- title duplicate.

## 0B.12 Redirect manager

Creează:

```sql
url_redirects (
  id,
  source_path UNIQUE,
  target_path NULL,
  http_status,
  reason,
  entity_type,
  entity_id,
  hit_count,
  created_at,
  updated_at
)
```

Suportă:

- 301;
- 308 dacă este justificat;
- 410 fără target.

Protecții:

- loop detection;
- chain detection;
- target 404 detection;
- duplicate source detection.

## 0B.13 Structured Data obligatoriu

### Produs

Generează JSON-LD din DB și numai cu date reale:

- Product;
- name;
- image;
- description;
- sku;
- brand dacă este real;
- offers;
- price;
- priceCurrency;
- availability;
- url;
- aggregateRating numai dacă există date reale și eligibile.

### Articol

Generează:

- Article sau BlogPosting;
- headline;
- image;
- datePublished;
- dateModified;
- author;
- publisher;
- mainEntityOfPage.

### Ambele

- BreadcrumbList;
- canonical coerent;
- nu fabrica recenzii/ratinguri.

## 0B.14 Baza de date V4 - tabele suplimentare

Creează și integrează:

```text
blog_posts
blog_categories
blog_tags
blog_post_categories
blog_post_tags
blog_post_revisions
url_redirects
sitemap_events
search_console_connections
seo_audit_results
```

Câmpuri minime `blog_posts`:

```text
id
slug UNIQUE
title
excerpt
content_html
featured_image_id
author_user_id
status
robots_index
canonical_url
meta_title
meta_description
og_title
og_description
og_image_id
published_at
scheduled_at
created_at
updated_at
deleted_at
```

Indexuri pe:

- slug;
- status;
- published_at;
- scheduled_at;
- updated_at;
- foreign keys.

## 0B.15 Open Graph și social preview

În editorul articolului:

- OG title;
- OG description;
- OG image;
- preview card;
- fallback featured image;
- fallback brand image;
- X card metadata;
- Pinterest metadata opțională.

Aceste date nu înlocuiesc meta SEO.

## 0B.16 Integrarea Atelier cu homepage și produse

Adminul poate configura un bloc „Din Atelier Maison Bébé” pe homepage:

- activ/inactiv;
- titlu;
- mod manual sau automat;
- selectare articole;
- ordine drag/drop;
- număr de carduri;
- link către `/atelier`.

Articolele pot asocia produse relevante. Pagina produsului poate afișa ghiduri relevante; articolul poate afișa produse relevante. Evită linking automat spammy.

## 0B.17 SEO - reguli tehnice obligatorii

- conținut principal server-rendered;
- URL-uri descriptive lowercase;
- fără `.html` în URL-urile publice noi;
- canonical coerent;
- title unic;
- H1 unic;
- meta description editabilă;
- 404 real;
- no soft 404;
- 301 la schimbări permanente;
- pagination crawlable;
- query params controlate;
- filtre fără crawl traps;
- robots.txt nu blochează paginile ce trebuie indexate;
- sitemap declarat în robots.txt;
- imagini responsive cu dimensiuni explicite;
- lazy loading sub fold;
- alt text contextual;
- performanță și Core Web Vitals tratate ca cerință.

## 0B.18 Criterii de acceptanță V4 suplimentare

Nu declara proiectul finalizat până când trec toate:

1. Creează produs publicat în admin -> URL separat `/produs/{slug}` răspunde 200.
2. Pagina produs are canonical self, title, description și Product JSON-LD.
3. Produsul apare în `products.xml`.
4. Editează produs -> aceeași pagină se actualizează.
5. Schimbă slug -> vechiul URL răspunde 301 către noul URL.
6. Șterge produs -> strategia 301/410 selectată este aplicată și sitemap-ul este curățat.
7. Creează articol publicat -> `/atelier/{slug}` răspunde 200.
8. Articolul are canonical self și Article/BlogPosting JSON-LD.
9. Articolul apare în `atelier.xml`.
10. Articol programat se publică o singură dată prin cron.
11. Draft/preview nu intră în sitemap.
12. Admin poate crea/edit categorii și tag-uri editoriale.
13. Reviziile pot fi comparate și restaurate.
14. Centru indexabilitate detectează noindex-in-sitemap.
15. Redirect manager detectează loop-uri.
16. Search Console integration nu folosește Indexing API pentru produse/articole.
17. Homepage poate afișa blocul Atelier configurat din admin.
18. Fiecare concept vizual 49-62 este implementat desktop și mobile.

# 0C. CERINȚĂ DE TRASABILITATE ABSOLUTĂ

Creează în repository:

```text
docs/REQUIREMENTS_TRACEABILITY_MATRIX.md
```

Pentru fiecare cerință majoră din PDF, include:

- requirement id;
- pagina/capitolul PDF;
- ecranul mockup;
- ruta frontend/admin;
- controller/service;
- tabele MySQL;
- job/queue dacă există;
- test automat;
- status implementare.

Include explicit rânduri pentru:

- homepage;
- shop;
- categorii;
- produs indexabil;
- wishlist;
- search modal;
- add-to-cart popup;
- cart drawer;
- checkout PF/PJ;
- cont client;
- Google Auth;
- tracking comandă;
- notificare comandă nouă;
- facturare internă;
- facturare externă;
- mapper șablon factură;
- e-Factura;
- Stripe;
- NETOPIA;
- COD;
- AWB;
- Atelier landing;
- articol indexabil;
- editor articol;
- programare;
- revizii;
- sitemap automat;
- redirect manager;
- Search Console;
- SEO indexability center.

# 0D. REGULA FINALĂ DE FIDELITATE VIZUALĂ

Pentru fiecare ecran 01-62:

- screenshot desktop;
- screenshot mobile;
- compară cu PDF;
- notează diferențele;
- corectează înainte de „done”.

Designul trebuie să păstreze exact familia vizuală:

- ivory/cream;
- alb cald;
- taupe;
- maro cald;
- serif editorial pentru titluri;
- sans-serif curat pentru UI;
- spațiu alb generos;
- linii fine;
- butoane discrete;
- fotografii calde;
- admin panel funcțional în aceeași familie, nu dashboard generic.

---

