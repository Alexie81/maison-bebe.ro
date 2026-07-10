ALTER TABLE gift_box_templates ADD COLUMN IF NOT EXISTS image_id BIGINT UNSIGNED NULL AFTER product_id;

INSERT INTO products (primary_category_id,name,slug,sku,brand,material,short_description,description_html,status,is_featured,is_gift_box,robots_index,include_sitemap,published_at)
SELECT c.id,'Cutia Atelier Ivory','cutia-atelier-ivory','MB-BOX-ATELIER-IVORY','Maison Bébé','Carton premium','Cutie premium pentru configuratorul Gift Box.','<p>Cutie premium Maison Bébé, pregătită manual pentru un dar personalizat.</p>','active',0,1,0,0,NOW()
FROM categories c WHERE c.slug='gift-box'
ON DUPLICATE KEY UPDATE name=VALUES(name),status='active',is_gift_box=1;

INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,low_stock_threshold,weight_grams,is_active)
SELECT p.id,'MB-BOX-ATELIER-IVORY-STD',6900,999,10,350,1 FROM products p WHERE p.sku='MB-BOX-ATELIER-IVORY'
AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.sku='MB-BOX-ATELIER-IVORY-STD');

INSERT IGNORE INTO product_categories (product_id,category_id,is_primary)
SELECT p.id,c.id,1 FROM products p JOIN categories c ON c.slug='gift-box' WHERE p.sku='MB-BOX-ATELIER-IVORY';

UPDATE gift_box_templates SET product_id=(SELECT id FROM products WHERE sku='MB-BOX-ATELIER-IVORY' LIMIT 1),base_price_minor=6900,min_components=2,max_components=6 WHERE slug='cutia-atelier';