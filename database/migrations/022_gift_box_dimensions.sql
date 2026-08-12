ALTER TABLE gift_box_templates
    ADD COLUMN IF NOT EXISTS length_cm DECIMAL(8,2) NULL AFTER stock_qty,
    ADD COLUMN IF NOT EXISTS width_cm DECIMAL(8,2) NULL AFTER length_cm,
    ADD COLUMN IF NOT EXISTS height_cm DECIMAL(8,2) NULL AFTER width_cm;
