MAISON BÉBÉ - PACHET CODEX V4
==============================

1. Deschide mai întâi CODEX_PROMPT_MAISON_BEBE_V4.md.
2. PDF-ul Maison_Bebe_Specificatie_Complete_Website_V4.pdf este SOURCE OF TRUTH și contractul vizual/funcțional/tehnic.
3. Codex trebuie să citească PDF-ul integral înainte de implementare.
4. Directorul mockups/boards conține 62 concepte vizuale responsive (desktop + mobile într-un board).
5. Directorul mockups/screens conține 124 screenshot-uri separate: desktop și mobile pentru cele 62 concepte.
6. Directorul references conține imaginile de referință furnizate în conversație și prezentarea preferată.

Ordinea de prioritate:
- mockup vizual PDF / boards;
- explicația funcțională din PDF;
- regulile tehnice din PDF;
- promptul Codex.

Arhitectura țintă:
Apache 2.4+ / PHP 8.2+ / MySQL 8+ / HTML5 / CSS3 custom / JavaScript ES6 / PDO / mod_rewrite / .htaccess.

Cerințe critice:
- fiecare produs publicat are pagină separată /produs/{slug}, indexabilă tehnic;
- fiecare articol publicat are pagină separată /atelier/{slug}, indexabilă tehnic;
- sitemap automat la create/edit/slug/delete;
- Google Indexing API NU se folosește pentru produse/articole generale;
- Admin Panel controlează produse, categorii, articole, facturare, e-Factura, plăți, Google Auth, AWB și SEO;
- PDF fidelity checklist obligatoriu pentru ecranele 01-62.
