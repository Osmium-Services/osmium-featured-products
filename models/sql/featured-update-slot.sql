UPDATE {TABLE}
SET product_id = :product_id, updated_at = NOW()
WHERE product_id = :old_product_id AND deleted_at IS NULL
