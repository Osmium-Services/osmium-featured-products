SELECT
    P.id,
    P.model_name,
    P.price_exc_tax,
    PB.name AS brand_name,
    PP.page_id
FROM {PREFIX}products AS P
INNER JOIN {PREFIX}productbrands AS PB ON PB.id = P.brand_id
INNER JOIN {PREFIX}productstatus AS PS ON PS.id = P.status_id
LEFT JOIN {PREFIX}products_pages AS PP ON PP.product_id = P.id AND PP.is_qr = 0
WHERE P.deleted_at IS NULL
  AND PS.product_available = 1
  AND P.price_exc_tax > 0
ORDER BY PB.name, P.model_name
