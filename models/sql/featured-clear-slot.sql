UPDATE {TABLE}
SET deleted_at = NOW()
WHERE product_id = :product_id AND deleted_at IS NULL
