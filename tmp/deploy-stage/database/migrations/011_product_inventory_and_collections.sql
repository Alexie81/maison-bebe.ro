ALTER TABLE product_variants ADD COLUMN IF NOT EXISTS track_inventory TINYINT(1) NOT NULL DEFAULT 1 AFTER stock_qty;
ALTER TABLE collections ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER is_featured;
UPDATE categories SET is_featured=0 WHERE is_featured=1;
