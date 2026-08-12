UPDATE product_variants v
JOIN products p ON p.id = v.product_id
SET v.track_inventory = 1,
    v.track_accounting_stock = 1
WHERE p.is_gift_box = 1;
