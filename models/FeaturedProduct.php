<?php

declare(strict_types=1);

namespace Osmium\Services\FeaturedProducts\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * The featured list: up to five product slots, in display order. A cleared slot is soft-deleted.
 */
class FeaturedProduct extends Model
{
    public const SLOT_COUNT = 5;

    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'featured_products');
    }

    /** @return array<int, array<string, mixed>> The filled slots with their product, brand and page, in order */
    public function listCurrent(): array
    {
        return $this->many('featured-list-current.sql', []);
    }

    /** @return int[] The featured product ids in display order */
    public function listProductIds(): array
    {
        return \array_map('intval', \array_column($this->many('featured-list-ids.sql', []), 'product_id'));
    }

    /** @return array<int, array<string, mixed>> Every available, priced, live product an admin can pick */
    public function listSelectableProducts(): array
    {
        return $this->many('featured-list-products.sql', []);
    }

    public function insertSlot(int $productId, int $sort): void
    {
        $this->run('featured-insert-slot.sql', [':product_id' => $productId, ':sort' => $sort]);
    }

    public function updateSlot(int $productId, int $oldProductId): void
    {
        $this->run('featured-update-slot.sql', [':product_id' => $productId, ':old_product_id' => $oldProductId]);
    }

    public function clearSlot(int $productId): void
    {
        $this->run('featured-clear-slot.sql', [':product_id' => $productId]);
    }

    /** @param array<int, array{product_id: int|string, sort: int|string}> $items */
    public function updateSorts(array $items): void
    {
        foreach ($items as $item) {
            $this->run('featured-update-sort.sql', [':sort' => (int) $item['sort'], ':product_id' => (int) $item['product_id']]);
        }
    }

    private function run(string $file, array $bindings): void
    {
        $this->database->prepare(sql: $this->loadSqlFile($file), bindings: $bindings)->execute();
    }

    private function many(string $file, array $bindings): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile($file), bindings: $bindings)->resultset();
    }
}
