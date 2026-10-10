<?php

declare(strict_types=1);

namespace Osmium\Services\FeaturedProducts\Models;

/**
 * The dashboard card for a featured list that needs attention, in the shape core's dashboard.widgets
 * hook takes. Free of the database.
 */
class FeaturedWidget
{
    /**
     * @param array<int, array{title: string, issues: string[]}> $problems Filled slots with something wrong
     * @return array<string, mixed>|null Null when every slot is filled and fine
     */
    public static function build(int $emptyCount, array $problems): ?array
    {
        if ($emptyCount === 0 && $problems === []) return null;

        $lines = \array_map(static fn(array $slot): array => [
            'text' => $slot['title'],
            'sub' => \implode(', ', $slot['issues']),
            'route' => 'featured-products/',
        ], $problems);

        if ($emptyCount > 0) {
            $lines[] = [
                'text' => $emptyCount . ' empty slot' . ($emptyCount > 1 ? 's' : ''),
                'sub' => 'Fewer than ' . FeaturedProduct::SLOT_COUNT . ' featured products are shown',
                'route' => 'featured-products/',
            ];
        }

        return [
            'id' => 'featured-products-attention',
            'title' => 'Featured products',
            'icon' => 'bx-star',
            'roles' => ['admin', 'editor'],
            'order' => 60,
            'cols' => 'col-12 col-lg-6',
            'count' => \count($problems) + $emptyCount,
            'link' => ['label' => 'Fix', 'route' => 'featured-products/'],
            'lines' => \array_slice($lines, 0, 5),
            'emptyText' => '',
        ];
    }
}
