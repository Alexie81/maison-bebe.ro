# Maison Bébé V4

Aplicație ecommerce PHP 8.4 + MariaDB, publicată direct în document root-ul `maison-bebe.ro`.

## Cerințe

- PHP 8.2+ cu PDO MySQL, cURL, OpenSSL, DOM, fileinfo și ZipArchive
- MariaDB/MySQL cu InnoDB și `utf8mb4`
- Apache cu `mod_rewrite` și `mod_headers`

## Instalare / upgrade

1. Copiază `.env.example` ca `.env` și completează secretele.
2. Rulează `php bin/install.php`.
3. Creează primul administrator cu `php bin/create-admin.php email parola` sau prin installerul one-time de deploy.
4. Configurează cron la fiecare minut: `php /home/.../public_html/bin/run-cron.php`.

În lipsa cronului cPanel, workerul post-response procesează în siguranță loturi mici de email, AWB, articole programate și sitemap după cererile web.

## Zone principale

- magazin: `/`, `/shop`, `/gift-box`, `/atelier`
- client: `/cont`, `/cos`, `/checkout`, `/urmarire-comanda`
- admin: `/admin`
- email: `/admin/setari/email`
- facturare: `/admin/facturare`
- livrare/AWB: `/admin/setari/livrare`
- SEO: `/admin/seo/indexabilitate`

Conectorii externi (plăți, facturare, curier, Google, ANAF) rămân opriți până la introducerea credențialelor reale și nu simulează confirmări.

## Verificare

```text
php bin/lint.php
php tests/smoke.php
node --check public/assets/js/app.js
node --check public/assets/js/commerce.js
node --check public/assets/js/admin.js
node --check public/assets/js/parallax.js
```

Fișierele interne, `.env`, baza, documentația și facturile din `storage/` sunt blocate la acces HTTP de `.htaccess`.
