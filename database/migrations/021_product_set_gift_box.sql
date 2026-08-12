ALTER TABLE product_sets
    ADD COLUMN gift_box_template_id BIGINT UNSIGNED NULL AFTER allow_gift_box,
    ADD KEY idx_product_sets_gift_box_template (gift_box_template_id),
    ADD CONSTRAINT fk_product_sets_gift_box_template
        FOREIGN KEY (gift_box_template_id) REFERENCES gift_box_templates(id) ON DELETE SET NULL;
