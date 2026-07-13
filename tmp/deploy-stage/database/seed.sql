SET NAMES utf8mb4;

INSERT INTO roles (id, name, label) VALUES
    (1, 'super_admin', 'Super administrator'),
    (2, 'manager', 'Manager'),
    (3, 'order_operator', 'Operator comenzi'),
    (4, 'catalog_manager', 'Manager catalog'),
    (5, 'content_editor', 'Editor conținut'),
    (6, 'publisher', 'Publicator'),
    (7, 'accountant', 'Contabil'),
    (8, 'support', 'Suport clienți'),
    (9, 'customer', 'Client')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO permissions (name, label) VALUES
    ('*', 'Acces complet'),
    ('dashboard.view', 'Vizualizare dashboard'),
    ('orders.view', 'Vizualizare comenzi'),
    ('orders.update', 'Actualizare comenzi'),
    ('orders.refund', 'Rambursare plăți'),
    ('products.view', 'Vizualizare produse'),
    ('products.create', 'Creare produse'),
    ('products.update', 'Actualizare produse'),
    ('products.delete', 'Arhivare produse'),
    ('categories.manage', 'Administrare categorii'),
    ('customers.view', 'Vizualizare clienți'),
    ('cms.manage', 'Administrare CMS'),
    ('atelier.manage', 'Administrare Atelier'),
    ('atelier.publish', 'Publicare Atelier'),
    ('billing.view', 'Vizualizare facturi'),
    ('billing.manage', 'Administrare facturare'),
    ('billing.issue', 'Emitere facturi'),
    ('shipping.manage', 'Administrare livrare'),
    ('seo.manage', 'Administrare SEO'),
    ('settings.manage', 'Administrare setări'),
    ('reports.view', 'Vizualizare rapoarte'),
    ('audit.view', 'Vizualizare audit')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE name = '*';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name IN ('dashboard.view','orders.view','orders.update','products.view','products.create','products.update','categories.manage','customers.view','cms.manage','atelier.manage','atelier.publish','billing.view','shipping.manage','seo.manage','reports.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name IN ('dashboard.view','orders.view','orders.update','shipping.manage');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name IN ('dashboard.view','products.view','products.create','products.update','products.delete','categories.manage');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE name IN ('dashboard.view','cms.manage','atelier.manage','seo.manage');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE name IN ('dashboard.view','cms.manage','atelier.manage','atelier.publish','seo.manage');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE name IN ('dashboard.view','billing.view','reports.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE name IN ('dashboard.view','orders.view','customers.view');

INSERT INTO media_assets (id, path, mime_type, original_name, alt_text, width, height) VALUES
    (1, '/assets/images/brand-board-reference.png', 'image/png', '01-brand-board-reference.png', 'Universul vizual Maison Bébé', 1800, 1100),
    (2, '/assets/images/packaging-reference.png', 'image/png', '02-packaging-reference.png', 'Gift Box Maison Bébé cu produse pentru bebeluș', 1600, 1600),
    (3, '/assets/images/logo-reference.png', 'image/png', '03-logo-reference.png', 'Logo Maison Bébé', 1000, 1000),
    (4, '/assets/images/giftbox-reference.png', 'image/png', '04-giftbox-reference.png', 'Cutie cadou Maison Bébé', 1080, 1920),
    (5, '/assets/images/presentation-reference.png', 'image/png', '05-preferred-presentation-reference.png', 'Prezentare Maison Bébé', 1600, 2400)
ON DUPLICATE KEY UPDATE alt_text = VALUES(alt_text);

INSERT INTO categories (id, parent_id, image_id, name, slug, description, is_active, is_featured, show_in_menu, sort_order, seo_title, seo_description) VALUES
    (1, NULL, 1, 'Nou-născut', 'nou-nascut', 'Piese blânde și esențiale pentru primele 3 luni.', 1, 1, 1, 10, 'Nou-născut 0-3 luni | Maison Bébé', 'Haine și accesorii premium pentru primele luni.'),
    (2, NULL, 2, '0-12 luni', '0-12-luni', 'Alegeri confortabile pentru primul an.', 1, 1, 1, 20, 'Bebeluși 0-12 luni | Maison Bébé', 'Selecții pentru primul an al bebelușului.'),
    (3, NULL, 1, '12-24 luni', '12-24-luni', 'Materiale naturale pentru micile explorări.', 1, 1, 1, 30, 'Copii 12-24 luni | Maison Bébé', 'Haine delicate și practice pentru 12-24 luni.'),
    (4, NULL, 2, 'Gift Box', 'gift-box', 'Daruri pregătite cu grijă pentru începuturi prețioase.', 1, 1, 1, 40, 'Gift Box pentru bebeluși | Maison Bébé', 'Cutii cadou premium, gata de oferit.'),
    (5, NULL, 4, 'Accesorii', 'accesorii', 'Detalii moi și utile pentru fiecare zi.', 1, 1, 1, 50, 'Accesorii bebeluși | Maison Bébé', 'Accesorii delicate și utile pentru bebeluși.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO collections (id, image_id, name, slug, description, is_active, is_featured, seo_title, seo_description) VALUES
    (1, 1, 'Primele zile', 'primele-zile', 'O selecție calmă pentru sosirea acasă.', 1, 1, 'Colecția Primele zile | Maison Bébé', 'Piese premium pentru primele zile împreună.'),
    (2, 2, 'Daruri cu sens', 'daruri-cu-sens', 'Gift Box-uri și obiecte păstrate cu drag.', 1, 1, 'Daruri cu sens | Maison Bébé', 'Cadouri premium pentru bebeluși și părinți.'),
    (3, 4, 'Atelier Ivory', 'atelier-ivory', 'Texturi naturale în tonuri ivory.', 1, 1, 'Atelier Ivory | Maison Bébé', 'Colecție în tonuri ivory și materiale naturale.')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO products (id, primary_category_id, name, slug, sku, brand, material, short_description, description_html, care_html, status, is_featured, is_gift_box, robots_index, include_sitemap, seo_title, seo_description, published_at) VALUES
    (1, 1, 'Salopetă din bumbac organic', 'salopeta-bumbac-organic', 'MB-SAL-001', 'Maison Bébé', '100% bumbac organic', 'O salopetă moale, creată pentru primele îmbrățișări.', '<p>Țesătură fină, cusături delicate și croială lejeră pentru confort deplin.</p>', '<p>Spălare delicată la 30°C. Fără înălbitori.</p>', 'active', 1, 0, 1, 1, 'Salopetă din bumbac organic | Maison Bébé', 'Salopetă premium pentru nou-născuți, din bumbac organic.', NOW()),
    (2, 4, 'Gift Box Bun Venit', 'gift-box-bun-venit', 'MB-GB-001', 'Maison Bébé', 'Bumbac organic, lemn natur', 'Un dar care spune „bun venit” cu delicatețe.', '<p>Cutie cadou pregătită manual cu piese esențiale pentru primele zile.</p>', '<p>Produsele textile se spală conform etichetelor individuale.</p>', 'active', 1, 1, 1, 1, 'Gift Box Bun Venit | Maison Bébé', 'Cutie cadou premium pentru nou-născut.', NOW()),
    (3, 1, 'Set body Ivory', 'set-body-ivory', 'MB-BOD-001', 'Maison Bébé', 'Bumbac organic', 'Trei body-uri fine în nuanțe calde.', '<p>Închidere practică și fibre certificate pentru pielea sensibilă.</p>', '<p>Spălare la 30°C.</p>', 'active', 1, 0, 1, 1, 'Set body Ivory | Maison Bébé', 'Set de body-uri ivory din bumbac organic.', NOW()),
    (4, 5, 'Păturică tricotată premium', 'paturica-tricotata-premium', 'MB-PAT-001', 'Maison Bébé', 'Bumbac tricotat', 'O păturică moale pentru somn și plimbări.', '<p>Textură aerisită și margini finisate manual.</p>', '<p>Spălare delicată, uscare plană.</p>', 'active', 1, 0, 1, 1, 'Păturică tricotată premium | Maison Bébé', 'Păturică moale tricotată pentru bebeluși.', NOW()),
    (5, 5, 'Jucărie iepuraș din muselină', 'jucarie-iepuras-muselina', 'MB-JUC-001', 'Maison Bébé', 'Muselină și umplutură hipoalergenică', 'Un companion tandru pentru momente liniștite.', '<p>Formă simplă, textură plăcută și detalii brodate.</p>', '<p>Spălare manuală.</p>', 'active', 0, 0, 1, 1, 'Iepuraș din muselină | Maison Bébé', 'Jucărie moale din muselină pentru bebeluși.', NOW()),
    (6, 2, 'Cardigan Atelier', 'cardigan-atelier', 'MB-CAR-001', 'Maison Bébé', 'Bumbac pieptănat', 'Cardigan fin pentru seri răcoroase.', '<p>Nasturi sidefați și croială lejeră.</p>', '<p>Spălare delicată la 30°C.</p>', 'active', 0, 0, 1, 1, 'Cardigan Atelier | Maison Bébé', 'Cardigan premium din bumbac pentru bebeluși.', NOW()),
    (7, 3, 'Set confort două piese', 'set-confort-doua-piese', 'MB-SET-001', 'Maison Bébé', 'Jerseu organic', 'Un set simplu și comod pentru fiecare zi.', '<p>Bluză și pantalon cu talie moale.</p>', '<p>Spălare la 30°C.</p>', 'active', 0, 0, 1, 1, 'Set confort două piese | Maison Bébé', 'Set confortabil din jerseu organic.', NOW()),
    (8, 5, 'Botosei din bumbac', 'botosei-din-bumbac', 'MB-BOT-001', 'Maison Bébé', 'Bumbac organic', 'Botosei fini pentru piciorușe mici.', '<p>Elastic moale și interior neted.</p>', '<p>Spălare manuală.</p>', 'active', 0, 0, 1, 1, 'Botosei din bumbac | Maison Bébé', 'Botosei moi din bumbac organic.', NOW()),
    (9, 4, 'Gift Box Prima Îmbrățișare', 'gift-box-prima-imbratisare', 'MB-GB-002', 'Maison Bébé', 'Bumbac organic și muselină', 'O selecție caldă pentru primul cadou.', '<p>Textile, jucărie moale și felicitare personalizabilă.</p>', '<p>Îngrijire conform etichetelor.</p>', 'active', 1, 1, 1, 1, 'Gift Box Prima Îmbrățișare | Maison Bébé', 'Gift box premium pentru un nou început.', NOW()),
    (10, 2, 'Set muselină pentru vară', 'set-muselina-vara', 'MB-MUS-001', 'Maison Bébé', 'Muselină de bumbac', 'Lejer, respirabil și blând cu pielea.', '<p>Set aerisit pentru zile luminoase.</p>', '<p>Spălare delicată.</p>', 'active', 0, 0, 1, 1, 'Set muselină pentru vară | Maison Bébé', 'Set de vară din muselină pentru bebeluși.', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), short_description = VALUES(short_description), updated_at = NOW();

INSERT IGNORE INTO product_categories (product_id, category_id, is_primary) VALUES
    (1,1,1),(1,2,0),(2,4,1),(2,1,0),(3,1,1),(3,2,0),(4,5,1),(4,2,0),(5,5,1),(6,2,1),(7,3,1),(8,5,1),(8,1,0),(9,4,1),(9,2,0),(10,2,1);

INSERT IGNORE INTO collection_products (collection_id, product_id, sort_order) VALUES
    (1,1,10),(1,3,20),(1,4,30),(1,8,40),(2,2,10),(2,9,20),(2,5,30),(3,1,10),(3,6,20),(3,10,30);

INSERT IGNORE INTO product_images (product_id, media_id, alt_text, sort_order, is_primary) VALUES
    (1,1,'Salopetă din bumbac organic Maison Bébé',10,1),(1,2,'Detaliu ambalaj Maison Bébé',20,0),
    (2,2,'Gift Box Bun Venit',10,1),(2,4,'Cutie cadou Maison Bébé',20,0),
    (3,1,'Set body Ivory',10,1),(4,2,'Păturică tricotată premium',10,1),
    (5,4,'Jucărie iepuraș din muselină',10,1),(6,1,'Cardigan Atelier',10,1),
    (7,1,'Set confort două piese',10,1),(8,2,'Botosei din bumbac',10,1),
    (9,2,'Gift Box Prima Îmbrățișare',10,1),(10,1,'Set muselină pentru vară',10,1);

INSERT INTO product_options (id, product_id, name, sort_order) VALUES
    (1,1,'Mărime',10),(2,3,'Mărime',10),(3,6,'Mărime',10),(4,7,'Mărime',10),(5,10,'Mărime',10)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO product_option_values (id, option_id, value, sort_order) VALUES
    (1,1,'0-3 luni',10),(2,1,'3-6 luni',20),(3,2,'0-3 luni',10),(4,2,'3-6 luni',20),
    (5,3,'6-12 luni',10),(6,3,'12-18 luni',20),(7,4,'12-18 luni',10),(8,4,'18-24 luni',20),
    (9,5,'3-6 luni',10),(10,5,'6-12 luni',20)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO product_variants (id, product_id, sku, price_minor, compare_at_price_minor, stock_qty, low_stock_threshold, weight_grams, is_active) VALUES
    (1,1,'MB-SAL-001-03',18900,NULL,18,3,180,1),(2,1,'MB-SAL-001-36',18900,NULL,12,3,190,1),
    (3,2,'MB-GB-001-STD',45900,NULL,8,2,1400,1),(4,3,'MB-BOD-001-03',21900,24900,16,3,240,1),
    (5,3,'MB-BOD-001-36',21900,24900,14,3,260,1),(6,4,'MB-PAT-001-STD',27900,NULL,9,2,420,1),
    (7,5,'MB-JUC-001-STD',9900,NULL,21,4,120,1),(8,6,'MB-CAR-001-612',24900,NULL,7,2,220,1),
    (9,7,'MB-SET-001-1218',26900,NULL,10,2,320,1),(10,8,'MB-BOT-001-STD',7900,NULL,25,4,80,1),
    (11,9,'MB-GB-002-STD',52900,NULL,6,2,1600,1),(12,10,'MB-MUS-001-36',23900,NULL,13,3,210,1)
ON DUPLICATE KEY UPDATE price_minor = VALUES(price_minor), stock_qty = VALUES(stock_qty);

INSERT IGNORE INTO variant_option_values (variant_id, option_value_id) VALUES (1,1),(2,2),(4,3),(5,4),(8,5),(9,7),(12,9);

INSERT IGNORE INTO inventory_movements (variant_id, movement_type, quantity, note)
SELECT id, 'initial', stock_qty, 'Stoc demo inițial' FROM product_variants;

INSERT INTO coupons (id, code, discount_type, discount_value, minimum_order_minor, max_uses, max_uses_per_user, is_active) VALUES
    (1,'BUNVENIT10','percent',10,15000,1000,1,1),
    (2,'GIFT50','fixed',5000,40000,300,1,1)
ON DUPLICATE KEY UPDATE discount_value = VALUES(discount_value);

INSERT INTO gift_box_templates (id, product_id, name, slug, description, base_price_minor, stock_qty, min_components, max_components, rules_json, is_active, sort_order) VALUES
    (1,2,'Bun Venit','bun-venit','Un cadou complet pentru primele zile.',7900,8,3,5,JSON_OBJECT('allowed_categories',JSON_ARRAY(1,5)),1,10),
    (2,9,'Prima Îmbrățișare','prima-imbratisare','Textile și detalii alese cu grijă.',9900,6,3,6,JSON_OBJECT('allowed_categories',JSON_ARRAY(1,2,5)),1,20),
    (3,NULL,'Cutia Atelier','cutia-atelier','Configurează un dar unic.',6900,0,2,6,JSON_OBJECT('allowed_categories',JSON_ARRAY(1,2,3,5)),0,30)
ON DUPLICATE KEY UPDATE description = VALUES(description), base_price_minor = VALUES(base_price_minor), stock_qty = VALUES(stock_qty), min_components = VALUES(min_components), max_components = VALUES(max_components), is_active = VALUES(is_active), sort_order = VALUES(sort_order);

INSERT INTO blog_categories (id, image_id, name, slug, description, is_active, is_indexable, meta_title, meta_description) VALUES
    (1,1,'Ghiduri','ghiduri','Ghiduri practice pentru începuturi senine.',1,1,'Ghiduri pentru părinți | Atelier Maison Bébé','Materiale, mărimi și îngrijire.'),
    (2,2,'Începuturi','inceputuri','Povești pentru primele luni.',1,1,'Începuturi | Atelier Maison Bébé','Idei și repere pentru primele luni.'),
    (3,1,'Materiale','materiale','Despre fibrele pe care le alegem.',1,1,'Materiale naturale | Atelier Maison Bébé','Ghiduri despre materiale blânde.'),
    (4,2,'Cadouri','cadouri','Daruri care rămân în amintire.',1,1,'Cadouri pentru bebeluși | Atelier Maison Bébé','Idei de cadouri premium.'),
    (5,4,'Din Atelier','din-atelier','Detalii din spatele colecțiilor.',1,1,'Din Atelier | Maison Bébé','Povești și procese Maison Bébé.'),
    (6,1,'Noutăți','noutati','Colecții și lansări.',1,1,'Noutăți Maison Bébé','Cele mai noi lansări.'),
    (7,1,'Inspirație','inspiratie','Idei pentru momente prețioase.',1,1,'Inspirație | Atelier Maison Bébé','Idei și selecții în stil Maison Bébé.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO blog_tags (id, name, slug, is_indexable) VALUES
    (1,'Bumbac organic','bumbac-organic',0),(2,'Nou-născut','nou-nascut',0),(3,'Gift Box','gift-box',0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO blog_posts (id, featured_image_id, og_image_id, title, slug, excerpt, content_html, status, robots_index, meta_title, meta_description, published_at) VALUES
    (1,1,1,'Cum alegi materialele potrivite pentru bebeluși','cum-alegi-materialele-potrivite-pentru-bebelusi','Un ghid despre fibre naturale, texturi și confort.', '<p>Pielea unui bebeluș merită materiale simple, respirabile și atent finisate.</p><h2>Începe cu fibre naturale</h2><p>Bumbacul organic și muselina sunt alegeri blânde pentru garderoba de început.</p><h2>Privește fiecare detaliu</h2><p>Cusăturile, capsele și etichetele trebuie să rămână discrete.</p>', 'published',1,'Cum alegi materialele potrivite pentru bebeluși | Atelier Maison Bébé','Ghid practic pentru alegerea materialelor naturale.',DATE_SUB(NOW(), INTERVAL 12 DAY)),
    (2,2,2,'Ghid pentru primul Gift Box','ghid-pentru-primul-gift-box','Cum compui un dar echilibrat, frumos și util.', '<p>Un Gift Box reușit combină piese utile, o textură memorabilă și un mesaj personal.</p><h2>Alege o bază practică</h2><p>Un body, o păturică și un accesoriu formează un început armonios.</p>', 'published',1,'Ghid pentru primul Gift Box | Atelier Maison Bébé','Idei pentru un Gift Box premium și util.',DATE_SUB(NOW(), INTERVAL 7 DAY)),
    (3,4,4,'Din Atelier: ambalarea fiecărui dar','din-atelier-ambalarea-fiecarui-dar','Micile gesturi care transformă o cutie într-o amintire.', '<p>Fiecare cutie este pregătită în tonuri calde, cu hârtie fină și panglică.</p><h2>Ordinea detaliilor</h2><p>Produsele sunt așezate astfel încât deschiderea să devină un moment în sine.</p>', 'published',1,'Ambalarea fiecărui dar | Atelier Maison Bébé','Povestea ambalării în Atelier Maison Bébé.',DATE_SUB(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE title = VALUES(title), updated_at = NOW();

INSERT IGNORE INTO blog_post_categories (post_id, category_id, is_primary) VALUES (1,3,1),(1,1,0),(2,4,1),(2,1,0),(3,5,1);
INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (1,1),(1,2),(2,3),(3,3);
INSERT IGNORE INTO blog_post_products (post_id, product_id, sort_order) VALUES (1,1,10),(1,3,20),(2,2,10),(2,9,20),(3,2,10);

INSERT INTO pages (id, slug, title, content_html, status, robots_index, meta_title, meta_description, published_at) VALUES
    (1,'despre-noi','Despre Maison Bébé','<p><strong>Maison Bébé este un boutique online premium dedicat celor mai prețioase începuturi.</strong></p><h2>Un loc creat pentru a dărui frumos</h2><p>Am creat un loc în care părinții, familia și prietenii pot găsi cu ușurință cadouri elegante și articole atent selecționate pentru nou-născuți și bebeluși.</p><p>În magazinul nostru vei descoperi Gift Box-uri premium, pregătite cu grijă pentru cele mai speciale momente, dar și hăinuțe, accesorii și produse esențiale pentru primele luni de viață.</p><h2>Ales cu atenție. Pregătit cu dragoste.</h2><p>Fiecare produs este ales punând accent pe calitate, confort, materiale delicate și un design rafinat. Ne dorim ca fiecare comandă să ofere aceeași emoție ca atunci când dăruiești un cadou pregătit cu dragoste.</p><blockquote>La Maison Bébé, credem că cele mai frumoase începuturi merită cele mai delicate alegeri.</blockquote><p>De aceea, fiecare comandă este ambalată elegant și pregătită pentru a transforma fiecare livrare într-o experiență memorabilă.</p><h2>În universul Maison Bébé vei descoperi</h2><ul><li>Gift Box-uri pentru nou-născuți</li><li>Hăinuțe premium pentru bebeluși</li><li>Accesorii pentru bebeluși</li><li>Cadouri pentru baby shower și botez</li><li>Produse atent selecționate pentru primele luni de viață</li></ul><h2>Eleganță, calitate și emoție</h2><p>Maison Bébé își propune să devină locul în care eleganța, calitatea și emoția se întâlnesc pentru a celebra fiecare nou început.</p>','published',1,'Despre Maison Bébé | Boutique premium pentru bebeluși','Descoperă Maison Bébé, boutique online premium cu Gift Box-uri, hăinuțe și accesorii atent selecționate pentru nou-născuți și bebeluși.',NOW()),
    (2,'livrare-si-retur','Livrare și retur','<p><strong>Conținut orientativ - necesită validare juridică.</strong></p><h2>Livrare</h2><p>Comenzile sunt pregătite cu grijă și predate curierului.</p><h2>Retur</h2><p>Condițiile finale vor fi validate înainte de publicarea comercială.</p>','published',1,'Livrare și retur | Maison Bébé','Informații despre livrare și retur.' ,NOW()),
    (3,'termeni-si-conditii','Termeni și condiții','<p><strong>Placeholder pentru validare juridică.</strong></p><p>Textul final va fi furnizat și validat de specialist.</p>','published',1,'Termeni și condiții | Maison Bébé','Termenii de utilizare Maison Bébé.',NOW()),
    (4,'confidentialitate','Politica de confidențialitate','<p><strong>Placeholder pentru validare juridică și GDPR.</strong></p>','published',1,'Confidențialitate | Maison Bébé','Politica de confidențialitate Maison Bébé.',NOW()),
    (5,'cookies','Politica cookies','<p><strong>Placeholder pentru validare juridică.</strong></p>','published',1,'Cookies | Maison Bébé','Informații despre cookies.',NOW()),
    (6,'metode-de-plata','Metode de plată','<p>Plata ramburs este disponibilă. Metodele card apar numai după configurarea și testarea providerului activ.</p>','published',1,'Metode de plată | Maison Bébé','Metode de plată disponibile.',NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), content_html = VALUES(content_html);

INSERT INTO homepage_sections (section_key, title, content_json, is_active, sort_order) VALUES
    ('announcement','Livrare gratuită',JSON_OBJECT('text','Livrare gratuită pentru comenzi de peste 500 lei'),1,5),
    ('hero','Ales cu grijă pentru cele mai prețioase începuturi.',JSON_OBJECT('eyebrow','Maison Bébé','body','Hăinuțe, accesorii și daruri pregătite cu delicatețe.','cta_label','Descoperă colecția','cta_url','/shop','image','/assets/images/brand-board-reference.png'),1,10),
    ('benefits','Promisiunea noastră',JSON_OBJECT('items',JSON_ARRAY('Materiale naturale','Livrare atentă','Ambalaj cadou','Retur simplu')),1,20),
    ('collections','Colecțiile noastre',JSON_OBJECT('mode','featured','limit',5),1,30),
    ('featured_products','Selecții Maison Bébé',JSON_OBJECT('mode','featured','limit',4),1,40),
    ('gift_box','Daruri cu sens',JSON_OBJECT('product_ids',JSON_ARRAY(2,9)),1,50),
    ('atelier','Din Atelier Maison Bébé',JSON_OBJECT('mode','latest','limit',3),1,60),
    ('newsletter','Scrisori din Atelier',JSON_OBJECT('body','Povești, ghiduri și noutăți trimise rar și cu grijă.'),1,70)
ON DUPLICATE KEY UPDATE title = VALUES(title), content_json = VALUES(content_json), is_active = VALUES(is_active), sort_order = VALUES(sort_order);

INSERT INTO settings (setting_key, value_json, is_public) VALUES
    ('site',JSON_OBJECT('name','Maison Bébé','currency','RON','locale','ro_RO','timezone','Europe/Bucharest'),1),
    ('contact',JSON_OBJECT('email','comenzi@maison-bebe.ro','phone','','schedule','Luni - Vineri, 09:00 - 17:00'),1),
    ('commerce',JSON_OBJECT('free_shipping_threshold',50000,'stock_reservation_minutes',20,'tax_rate',19),0),
    ('features',JSON_OBJECT('google_auth',false,'newsletter_popup',false,'cookie_consent',true,'guest_wishlist',true),1),
    ('order_email',JSON_OBJECT('enabled',true,'sender_purpose','orders'),0),
    ('invoice_email',JSON_OBJECT('enabled',true,'sender_purpose','invoices'),0),
    ('seo',JSON_OBJECT('default_title','Maison Bébé - daruri pentru începuturi prețioase','default_description','Haine, accesorii și Gift Box-uri premium pentru bebeluși.'),1)
ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), is_public = VALUES(is_public);

INSERT INTO payment_providers (id, code, name, provider_type, environment, is_enabled, is_default, sort_order) VALUES
    (1,'cod','Ramburs','cod','live',1,1,10),
    (2,'stripe','Stripe','card','test',0,0,20),
    (3,'netopia','NETOPIA Payments','card','sandbox',0,0,30)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO shipping_providers (id, code, name, environment, is_enabled, is_default, config_json) VALUES
    (1,'manual','Livrare manuală / curier la adresă','manual',1,1,JSON_OBJECT('base_price_minor',2500,'free_threshold_minor',50000)),
    (2,'generic_api','Curier API configurabil','sandbox',0,0,JSON_OBJECT())
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO company_profiles (id, legal_name, trade_name, tax_id, registration_number, vat_status, address_json, billing_email, phone, website, logo_id, default_currency, is_active) VALUES
    (1,'DE COMPLETAT SRL','Maison Bébé','DE_COMPLETAT',NULL,'configurabil',JSON_OBJECT('line1','De completat','city','De completat','county','De completat','postal_code','','country_code','RO'),'comenzi@maison-bebe.ro','', 'https://maison-bebe.ro',3,'RON',1)
ON DUPLICATE KEY UPDATE trade_name = VALUES(trade_name), billing_email = VALUES(billing_email);

INSERT INTO invoice_series (id, company_profile_id, prefix, next_number, document_type, is_active) VALUES (1,1,'MB',1,'invoice',1)
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

INSERT INTO invoice_templates (id, code, name, template_type, is_active, is_default, customer_type) VALUES
    (1,'classic_ivory','Classic Ivory','builtin',1,1,'individual'),
    (2,'editorial_taupe','Editorial Taupe','builtin',1,0,'any'),
    (3,'minimal_fiscal','Minimal Fiscal','builtin',1,0,'company'),
    (4,'gift_atelier','Gift Atelier','builtin',1,0,'any')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO invoice_template_versions (template_id, version_no, html_template, css_template, metadata_json) VALUES
    (1,1,'<h1>{{company.name}}</h1><p>Factura {{invoice.number}}</p><div>{{customer.name}}</div><table>{{items[]}}</table><strong>{{totals.grand_total}}</strong>','body{font-family:Georgia;color:#3D312B;background:#FFFCF8}',JSON_OBJECT('builtin',true)),
    (2,1,'<header>{{company.name}}</header><h1>Factura {{invoice.number}}</h1><table>{{items[]}}</table><strong>{{totals.grand_total}}</strong>','header{background:#CBB3A1;padding:20px}body{font-family:Georgia}',JSON_OBJECT('builtin',true)),
    (3,1,'<h1>{{company.name}} - {{invoice.number}}</h1><table>{{items[]}}</table><strong>{{totals.grand_total}}</strong>','body{font-family:Arial;font-size:11px}table{width:100%;border-collapse:collapse}',JSON_OBJECT('builtin',true)),
    (4,1,'<h1>{{company.name}}</h1><p>Un dar pregătit cu grijă</p><p>Factura {{invoice.number}}</p><table>{{items[]}}</table><strong>{{totals.grand_total}}</strong>','body{font-family:Georgia;color:#8A6F5E;background:#F7F3EE}',JSON_OBJECT('builtin',true))
ON DUPLICATE KEY UPDATE html_template = VALUES(html_template), css_template = VALUES(css_template);

INSERT INTO invoice_connectors (id, code, name, mode, environment, is_enabled, is_default, last_health_status) VALUES
    (1,'internal','Motor intern Maison Bébé','internal','internal',1,1,'healthy'),
    (2,'generic_external','Conector extern configurabil','external','sandbox',0,0,'not_configured')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO fiscal_rule_sets (id, name, is_active, effective_from) VALUES (1,'Reguli implicite PF/PJ',1,'2026-01-01 00:00:00')
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);
INSERT INTO fiscal_rule_versions (rule_set_id, version_no, rules_json) VALUES
    (1,1,JSON_OBJECT('individual',JSON_OBJECT('template','classic_ivory','efactura','configurable'),'company',JSON_OBJECT('template','minimal_fiscal','efactura','configurable'),'vat_rate',19))
ON DUPLICATE KEY UPDATE rules_json = VALUES(rules_json);

