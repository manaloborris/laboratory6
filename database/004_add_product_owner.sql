USE lavalust_lab6;

-- Run once against the existing database before assigning legacy products.
ALTER TABLE products
    ADD COLUMN user_id INT UNSIGNED NULL AFTER id;
