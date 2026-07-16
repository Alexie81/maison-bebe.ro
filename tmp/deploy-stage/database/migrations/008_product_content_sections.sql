ALTER TABLE products
    ADD COLUMN shipping_html MEDIUMTEXT NULL AFTER care_html,
    ADD COLUMN gift_wrap_html MEDIUMTEXT NULL AFTER shipping_html;