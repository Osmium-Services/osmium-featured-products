<?php

declare(strict_types=1);

namespace Osmium\Services\FeaturedProducts\Models;

/**
 * What makes a featured slot unusable, and where a product's thumbnail lives. Free of the database
 * and the file system: callers say whether the page and thumbnail exist.
 */
class FeaturedRules
{
    /** A name as the shop's image folders spell it: lower case, a-z 0-9 and single hyphens only */
    public static function pathSegment(string $name): string
    {
        $path = \mb_strtolower(\trim($name));
        $path = \str_replace(['/', '\\', '&'], '', $path);
        $path = \preg_replace('/[^a-z0-9-]+/', '-', $path);
        $path = \preg_replace('/-+/', '-', $path);

        return \trim($path, '-');
    }

    /** The thumbnail's path under the images folder, such as /shop/brand/model/thumb/brand-model.jpg */
    public static function thumbnailPath(string $brandName, string $modelName): string
    {
        $brand = self::pathSegment($brandName);
        $model = self::pathSegment($modelName);

        return '/shop/' . $brand . '/' . $model . '/thumb/' . $brand . '-' . $model . '.jpg';
    }

    /**
     * What is wrong with a filled slot, in the words shown beside it.
     *
     * @param array<string, mixed> $slot A featured-list-current row
     * @return string[]
     */
    public static function issues(array $slot, bool $pageIsLive, bool $hasThumbnail): array
    {
        $issues = [];
        if (($slot['product_available'] ?? 0) == 0) $issues[] = 'Unavailable';
        if (($slot['product_deleted'] ?? null) !== null) $issues[] = 'Deleted';
        if (($slot['price_exc_tax'] ?? 0) == 0) $issues[] = 'No price';
        if (!$pageIsLive) $issues[] = 'No page';
        if (!$hasThumbnail) $issues[] = 'No image';

        return $issues;
    }

    /**
     * Fill the list out to the slot count with empty slots, each taking the lowest sort number not in use.
     *
     * @param array<int, array<string, mixed>> $slots
     * @return array<int, array<string, mixed>>
     */
    public static function padToSlots(array $slots, int $slotCount): array
    {
        $taken = \array_column($slots, 'sort');
        $next = 1;

        while (\count($slots) < $slotCount) {
            while (\in_array($next, $taken)) $next++;

            $slots[] = ['sort' => $next, 'product_id' => null, 'thumbnail' => null, 'title' => 'Empty Slot', 'url' => null];
            $taken[] = $next;
            $next++;
        }

        return $slots;
    }
}
