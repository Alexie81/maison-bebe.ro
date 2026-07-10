# Inventar PDF V4 și hartă de rute

Source of truth: `Maison_Bebe_Specificatie_Complete_Website_V4.pdf`, 209 pagini. Suplimentul V4 este la paginile PDF 1-31, specificația V3 la paginile 32-177, promptul consolidat la paginile 178-208, iar încheierea V4 la pagina 209. Toate cele 62 de planșe din `mockups/boards/` și cele 124 de capturi desktop/mobile din `mockups/screens/` au fost inventariate.

| # | Concept / ecran | Pagini PDF | Rută / suprafață |
|---:|---|---:|---|
| 01 | Pagina principală | 44-45 | `GET /` |
| 02 | Shop / Produse | 46-47 | `GET /shop` |
| 03 | Categorie | 48-49 | `GET /categorie/{slug}` |
| 04 | Produs | 50-51 | `GET /produs/{slug}` |
| 05 | Coș | 52-53 | `GET /cos` |
| 06 | Checkout PF/PJ | 54-55 | `GET /checkout`, `POST /checkout/create` |
| 07 | Autentificare / Google | 56-57 | `GET/POST /cont/autentificare`, `/auth/google/*` |
| 08 | Contul meu | 58-59 | `GET /cont` și subrutele `/cont/*` |
| 09 | Urmărire comandă | 60-61 | `GET/POST /urmarire-comanda` |
| 10 | Gift Box-uri | 62-63 | `GET /gift-box` |
| 11 | Despre noi | 64-65 | `GET /despre-noi` |
| 12 | Contact | 66-67 | `GET/POST /contact` |
| 13 | Blog legacy | 68-69 | `GET /blog` -> `301 /atelier` |
| 14 | Articol legacy | 70-71 | `GET /blog/{slug}` -> `301 /atelier/{slug}` |
| 15 | Politici și termeni | 72-73 | `GET /politici/{slug}` și alias `/legal/{slug}` |
| 16 | Admin - Dashboard | 74-75 | `GET /admin` |
| 17 | Admin - Comenzi | 76-77 | `GET /admin/comenzi` |
| 18 | Admin - Produse | 78-79 | `GET /admin/produse` |
| 19 | Admin - Detaliu comandă | 80-81 | `GET /admin/comenzi/{id}` |
| 20 | Favorite / Wishlist | 82-83 | `GET /favorite`, API `/api/wishlist/*` |
| 21 | Popup căutare | 84-85 | overlay global, `GET /api/search` |
| 22 | Popup adăugat în coș | 86-87 | overlay global după `POST /api/cart/items` |
| 23 | Mini-cart / drawer | 88-89 | overlay global, `GET /api/cart` |
| 24 | Resetare parolă | 90-91 | `GET/POST /cont/resetare-parola` |
| 25 | Admin - Categorii | 92-93 | `GET /admin/categorii` |
| 26 | Admin - Creează/editează categorie | 94-95 | `/admin/categorii/creare`, `/admin/categorii/{id}/edit` |
| 27 | Admin - Adaugă/editează produs | 96-97 | `/admin/produse/creare`, `/admin/produse/{id}/edit` |
| 28 | Admin - Centru notificări | 98-99 | `GET /admin/notificari`, API `/admin/api/notifications/*` |
| 29 | Admin - Clienți | 100-101 | `GET /admin/clienti` |
| 30 | Admin - Cupoane | 102-103 | `GET/POST /admin/cupoane` |
| 31 | Admin - CMS | 104-105 | `GET/POST /admin/cms` |
| 32 | Admin - Popup comandă nouă | 106-107 | overlay global alimentat de notificări |
| 33 | Admin - Facturare overview | 108-109 | `GET /admin/facturare` |
| 34 | Admin - Datele firmei | 110-111 | `GET/POST /admin/facturare/firma` |
| 35 | Admin - Integrări facturare | 112-113 | `GET/POST /admin/facturare/conectori` |
| 36 | Admin - Șabloane factură | 114-115 | `GET/POST /admin/facturare/sabloane` |
| 37 | Admin - Mapper factură | 116-117 | `GET/POST /admin/facturare/sabloane/mapper` |
| 38 | Admin - Editor/preview factură | 118-119 | `GET /admin/facturi/{id}` |
| 39 | Admin - Centru RO e-Factura | 120-121 | `GET/POST /admin/facturare/efactura` |
| 40 | Admin - Facturi și arhivă | 122-123 | `GET /admin/facturi` |
| 41 | Admin - SEO lifecycle produs | 124-125 | `GET/POST /admin/produse/{id}/seo` |
| 42 | Admin - Sitemap și indexare | 126-127 | `GET/POST /admin/seo/sitemap` |
| 43 | Admin - Procesatori de plăți | 128-129 | `GET /admin/setari/plati` |
| 44 | Admin - Configurare procesator | 130-131 | `GET/POST /admin/setari/plati/{provider}` |
| 45 | Admin - Google Auth | 132-133 | `GET/POST /admin/setari/autentificare` |
| 46 | Admin - Integrări livrare/AWB | 134-135 | `GET/POST /admin/setari/livrare` |
| 47 | Admin - Generare AWB | 136-137 | `GET/POST /admin/comenzi/{id}/awb` |
| 48 | Admin - Centru expediții | 138-139 | `GET /admin/expeditii` |
| 49 | Atelier Maison Bébé | supliment 6 | `GET /atelier` |
| 50 | Articol Atelier | supliment 7 | `GET /atelier/{slug}` |
| 51 | Admin - Atelier listă | supliment 8 | `GET /admin/atelier` |
| 52 | Admin - Editor articol | supliment 9 | `/admin/atelier/creare`, `/admin/atelier/{id}/edit` |
| 53 | Admin - SEO articol | supliment 10 | `GET/POST /admin/atelier/{id}/seo` |
| 54 | Admin - Taxonomii editoriale | supliment 11 | `GET/POST /admin/atelier/taxonomii` |
| 55 | Admin - Calendar editorial | supliment 12 | `GET /admin/atelier/calendar` |
| 56 | Admin - Revizii articol | supliment 13 | `GET/POST /admin/atelier/{id}/revisions` |
| 57 | Admin - Centru indexabilitate | supliment 14 | `GET/POST /admin/seo/indexabilitate` |
| 58 | Admin - Redirecturi | supliment 15 | `GET/POST /admin/seo/redirecturi` |
| 59 | Admin - Search Console | supliment 16 | `GET/POST /admin/seo/search-console` |
| 60 | Admin - Creare pagină produs | supliment 17 | integrat în `/admin/produse/{id}/seo` |
| 61 | Admin - Social preview | supliment 18 | `GET/POST /admin/atelier/{id}/social` |
| 62 | Admin - Bloc Atelier homepage | supliment 19 | `GET/POST /admin/cms/homepage` |

## Stări suplimentare obligatorii

- Confirmare comandă: `/comanda-confirmata/{token}`.
- Register: `/cont/inregistrare`.
- Comenzi, detaliu, adrese și favorite în cont: `/cont/comenzi`, `/cont/comenzi/{number}`, `/cont/adrese`, `/cont/favorite`.
- Colecție: `/colectie/{slug}`.
- Erori tematizate: răspunsuri HTTP reale 404/410/500.
- Preview securizat produs/articol: token cu expirare, `noindex`, exclus din sitemap.

