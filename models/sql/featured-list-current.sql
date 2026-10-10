SELECT
    F.product_id,
    F.sort,
    P.model_name,
    P.price_exc_tax,
    P.deleted_at AS product_deleted,
    PB.name AS brand_name,
    PP.page_id,
    PS.product_available
FROM {TABLE} AS F
LEFT JOIN {PREFIX}products AS P ON P.id = F.product_id
LEFT JOIN {PREFIX}productbrands AS PB ON PB.id = P.brand_id
LEFT JOIN {PREFIX}productstatus AS PS ON PS.id = P.status_id
LEFT JOIN {PREFIX}products_pages AS PP ON PP.product_id = P.id AND PP.is_qr = 0
WHERE F.deleted_at IS NULL
ORDER BY F.sort ASC
LIMIT 5
