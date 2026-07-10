# Arhitectură Maison Bébé V4

Aplicația folosește un front controller PHP 8.2, routing custom, controllers subțiri, servicii pentru regulile de business și repositories PDO pentru date. HTML-ul critic este server-rendered; JavaScript adaugă interacțiuni AJAX și overlays fără a transforma proiectul într-un SPA.

## Principii

- MySQL 8 / InnoDB / `utf8mb4`; banii sunt stocați în unități minore (`BIGINT`) pentru calcule deterministe.
- Toate mutațiile de sesiune folosesc CSRF; login/contact/tracking au rate limiting.
- Checkout-ul, stocul, numerotarea facturilor și tranzițiile financiare folosesc tranzacții și idempotency keys.
- Adaptoarele de facturare, plată și livrare sunt separate de controllers și rămân `not_configured` dacă lipsesc credențialele.
- Queue-urile sunt MySQL și se procesează în batch-uri prin cron cu lock, retry și backoff.
- Lifecycle-ul produselor/articolelor scrie evenimente sitemap și redirecturi în aceeași tranzacție logică.
- Fișierele emise și credențialele criptate nu sunt publice; upload-urile publice blochează execuția PHP.

## Flux

`Apache -> public/index.php -> Router -> middleware -> Controller -> Service -> Repository/PDO -> View/JSON`

## Operare fără Node.js

CSS și JavaScript sunt livrate direct din `public/assets`. Composer este opțional pentru integrarea cu biblioteci externe, dar aplicația de bază și motorul intern rulează fără proces Node persistent.

