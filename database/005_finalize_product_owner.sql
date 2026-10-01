USE lavalust_lab6;

-- Run after every existing product has a valid user_id.
-- Verify first: SELECT COUNT(*) FROM products WHERE user_id IS NULL;
-- The count must be 0 before running this migration.
ALTER TABLE products
    MODIFY user_id INT UNSIGNED NOT NULL,
    ADD KEY products_user_id_idx (user_id),
    ADD CONSTRAINT products_user_id_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;