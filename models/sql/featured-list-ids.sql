SELECT F.product_id
FROM {TABLE} AS F
INNER JOIN {PREFIX}products AS P ON P.id = F.product_id
WHERE F.deleted_at IS NULL
ORDER BY F.sort ASC
LIMIT 5
