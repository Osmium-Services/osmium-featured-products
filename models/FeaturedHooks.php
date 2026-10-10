<?php

declare(strict_types=1);

namespace Osmium\Services\FeaturedProducts\Models;

use Osmium\Core\Models\Model;

/**
 * The handlers for the core hooks this service listens to.
 */
class FeaturedHooks
{
    /**
     * featured.products: the featured product ids in display order, for core to turn into product cards.
     *
     * @param array{dataSource: object} $payload
     */
    public static function featuredProducts(array $payload): array
    {
        return ['productIds' => (new FeaturedProduct($payload['dataSource']))->listProductIds()];
    }

    /**
     * dashboard.widgets: a card when a slot is empty or points at a product that cannot be shown.
     *
     * @param array{osmium: object, role: string} $payload
     */
    public static function dashboard(array $payload): ?array
    {
        $osmium = $payload['osmium'];
        $slots = (new FeaturedProduct($osmium->dataSource))->listCurrent();
        $pagesById = Model::pagesById();
        $imagesRoot = $_SERVER['DOCUMENT_ROOT'] . $osmium->config->site->imagesPath;

        $problems = [];
        foreach ($slots as $slot) {
            $page = $pagesById[$slot['page_id']] ?? null;
            $thumbnail = FeaturedRules::thumbnailPath($slot['brand_name'] ?? '', $slot['model_name'] ?? '');
            $issues = FeaturedRules::issues(
                slot: $slot,
                pageIsLive: $page !== null && $page['deleted_at'] === null,
                hasThumbnail: \file_exists($imagesRoot . $thumbnail),
            );
            if ($issues !== []) $problems[] = ['title' => \trim(($slot['brand_name'] ?? '') . ' ' . ($slot['model_name'] ?? '')), 'issues' => $issues];
        }

        return FeaturedWidget::build(emptyCount: FeaturedProduct::SLOT_COUNT - \count($slots), problems: $problems);
    }
}
