UPDATE {TABLE}
SET sort = :sort, updated_at = NOW()
WHERE product_id = :product_id AND deleted_at IS NULL
