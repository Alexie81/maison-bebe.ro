ALTER TABLE gift_box_templates ADD COLUMN IF NOT EXISTS stock_qty INT NOT NULL DEFAULT 0 AFTER base_price_minor;
ALTER TABLE gift_box_templates ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER is_active;
ALTER TABLE gift_box_templates ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER updated_at;

INSERT INTO settings (setting_key,value_json,is_public)
VALUES ('gift_box_configurator', JSON_OBJECT('enabled', true), 0)
ON DUPLICATE KEY UPDATE value_json=COALESCE(value_json, VALUES(value_json));

UPDATE gift_box_templates t
LEFT JOIN (
    SELECT p.id product_id, COALESCE(SUM(v.stock_qty),0) stock_qty
    FROM products p
    LEFT JOIN product_variants v ON v.product_id=p.id AND v.is_active=1
    GROUP BY p.id
) pv ON pv.product_id=t.product_id
SET t.stock_qty=COALESCE(NULLIF(t.stock_qty,0), pv.stock_qty, 0)
WHERE t.deleted_at IS NULL;

INSERT INTO products (primary_category_id,name,slug,sku,brand,short_description,status,is_gift_box,robots_index,include_sitemap,published_at,deleted_at)
SELECT NULL, CONCAT('Cutie configurator - ', t.name), CONCAT('gift-box-cutie-',t.id,'-',t.slug), CONCAT('GBBOX-',t.id), 'Maison Bébé', t.description, 'active', 1, 0, 0, NOW(), NOW()
FROM gift_box_templates t
WHERE t.deleted_at IS NULL
ON DUPLICATE KEY UPDATE name=VALUES(name), short_description=VALUES(short_description), status='active', is_gift_box=1, robots_index=0, include_sitemap=0, deleted_at=NOW(), updated_at=NOW();

UPDATE gift_box_templates t
JOIN products p ON p.sku=CONCAT('GBBOX-',t.id)
SET t.product_id=p.id
WHERE t.deleted_at IS NULL;

INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,is_active)
SELECT t.product_id, CONCAT('GBBOX-',t.id,'-STD'), t.base_price_minor, t.stock_qty, t.is_active
FROM gift_box_templates t
WHERE t.deleted_at IS NULL AND t.product_id IS NOT NULL
ON DUPLICATE KEY UPDATE price_minor=VALUES(price_minor), stock_qty=VALUES(stock_qty), is_active=VALUES(is_active), updated_at=NOW();

INSERT INTO product_images (product_id,media_id,alt_text,sort_order,is_primary)
SELECT t.product_id,t.image_id,t.name,0,1
FROM gift_box_templates t
WHERE t.deleted_at IS NULL AND t.product_id IS NOT NULL AND t.image_id IS NOT NULL
ON DUPLICATE KEY UPDATE alt_text=VALUES(alt_text), is_primary=1;