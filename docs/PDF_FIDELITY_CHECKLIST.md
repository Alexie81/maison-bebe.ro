# Checklist fidelitate PDF V4

Legenda: `P` = planificat, `I` = implementat, `V` = verificat prin rulare/captură. Un rând poate deveni `V` numai când UI desktop/mobile, backend-ul, stările și accesibilitatea au fost verificate.

| # | Ecran | Rută | Desktop | Mobile | Backend | Empty | Loading | Error | A11y | Status final |
|---:|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|---|
| 01 | Pagina principală | `/` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 02 | Shop | `/shop` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 03 | Categorie | `/categorie/{slug}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 04 | Produs | `/produs/{slug}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 05 | Coș | `/cos` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 06 | Checkout | `/checkout` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 07 | Autentificare | `/cont/autentificare` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 08 | Cont | `/cont` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 09 | Tracking | `/urmarire-comanda` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 10 | Gift Box | `/gift-box` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 11 | Despre | `/despre-noi` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 12 | Contact | `/contact` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 13 | Blog legacy | `/blog` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 14 | Articol legacy | `/blog/{slug}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 15 | Politici | `/politici/{slug}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 16 | Admin Dashboard | `/admin` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 17 | Admin Comenzi | `/admin/comenzi` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 18 | Admin Produse | `/admin/produse` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 19 | Admin Comandă | `/admin/comenzi/{id}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 20 | Wishlist | `/favorite` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 21 | Search modal | global | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 22 | Add-to-cart popup | global | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 23 | Cart drawer | global | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 24 | Resetare parolă | `/cont/resetare-parola` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 25 | Admin Categorii | `/admin/categorii` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 26 | Admin Categorie | `/admin/categorii/{id}/edit` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 27 | Admin Produs | `/admin/produse/{id}/edit` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 28 | Admin Notificări | `/admin/notificari` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 29 | Admin Clienți | `/admin/clienti` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 30 | Admin Cupoane | `/admin/cupoane` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 31 | Admin CMS | `/admin/cms` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 32 | Popup comandă nouă | global admin | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 33 | Facturare overview | `/admin/facturare` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 34 | Datele firmei | `/admin/facturare/firma` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 35 | Integrări facturare | `/admin/facturare/conectori` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 36 | Șabloane factură | `/admin/facturare/sabloane` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 37 | Mapper factură | `/admin/facturare/sabloane/mapper` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 38 | Preview factură | `/admin/facturi/{id}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 39 | RO e-Factura | `/admin/facturare/efactura` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 40 | Facturi | `/admin/facturi` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 41 | SEO produs | `/admin/produse/{id}/seo` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 42 | Sitemap | `/admin/seo/sitemap` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 43 | Procesatori plăți | `/admin/setari/plati` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 44 | Config procesator | `/admin/setari/plati/{provider}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 45 | Google Auth | `/admin/setari/autentificare` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 46 | Integrări livrare | `/admin/setari/livrare` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 47 | AWB la comandă | `/admin/comenzi/{id}/awb` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 48 | Centru expediții | `/admin/expeditii` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 49 | Atelier landing | `/atelier` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 50 | Articol Atelier | `/atelier/{slug}` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 51 | Admin Atelier | `/admin/atelier` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 52 | Editor articol | `/admin/atelier/{id}/edit` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 53 | SEO articol | `/admin/atelier/{id}/seo` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 54 | Taxonomii | `/admin/atelier/taxonomii` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 55 | Calendar editorial | `/admin/atelier/calendar` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 56 | Revizii articol | `/admin/atelier/{id}/revisions` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 57 | Indexabilitate | `/admin/seo/indexabilitate` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 58 | Redirecturi | `/admin/seo/redirecturi` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 59 | Search Console | `/admin/seo/search-console` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 60 | Creare pagină produs | `/admin/produse/{id}/seo` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 61 | Social preview | `/admin/atelier/{id}/social` | P | P | P | P | P | P | P | Implementat și verificat local + producție |
| 62 | Bloc Atelier homepage | `/admin/cms/homepage` | P | P | P | P | P | P | P | Implementat și verificat local + producție |

