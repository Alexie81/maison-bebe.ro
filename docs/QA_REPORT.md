# Raport QA Maison Bébé V4

Data: 10.07.2026

## Rezultat

- PHP lint: OK pentru toate fișierele PHP.
- JavaScript syntax: OK pentru storefront, commerce, admin, parallax și service worker.
- Smoke tests: 7/7 trecute.
- Rute publice verificate: 20/20 cu HTTP 200.
- Rute admin verificate după autentificare: 40/40 cu HTTP 200.
- SMTP `mail.maison-bebe.ro:465` SSL: autentificare reușită, fără mesaj de test trimis.
- Factură internă: emitere idempotentă, serie atomică, PDF și hash SHA-256 verificate.
- AWB manual: generare idempotentă verificată.
- Sitemap XML și `robots.txt`: HTTP 200, XML valid generat din DB.
- Directoare private și `.env`: HTTP 403.
- Homepage: verificat la 1440×900 și 390×844; parallax desktop și mobil activ, cu `prefers-reduced-motion` respectat.

## Limitări intenționate până la credențiale

- Stripe/NETOPIA sunt dezactivate.
- Google Auth este dezactivat.
- Curierul API generic este dezactivat; livrarea manuală funcționează.
- Conectorul extern de facturare și OAuth ANAF sunt dezactivate.

Aceste stări sunt vizibile și configurabile în admin; aplicația nu declară succes extern fără răspuns verificat de furnizor.

## Verificare producție

- `https://maison-bebe.ro/`, `/shop` și rutele de administrare: HTTP 200.
- Persistența coșului după reload: verificată pe domeniul live.
- Starea selectată a favoritelor după reload: verificată pe domeniul live.
- Hero mobil, banda de livrare și parallax: verificate vizual pe domeniul live.
- Datele temporare create de testul live au fost eliminate prin interfață.
