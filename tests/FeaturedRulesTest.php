<?php

declare(strict_types=1);

namespace Tests\Services\FeaturedProducts;

use Osmium\Services\FeaturedProducts\Models\FeaturedRules;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/FeaturedRules.php';

/**
 * What makes a featured slot unusable, thumbnail paths and the empty slots.
 */
final class FeaturedRulesTest extends TestCase
{
    public function test_a_thumbnail_path_is_built_from_the_brand_and_model_folders(): void
    {
        $this->assertSame(
            '/shop/jinko-solar/tiger-neo-54/thumb/jinko-solar-tiger-neo-54.jpg',
            FeaturedRules::thumbnailPath('Jinko Solar', 'Tiger Neo 54'),
        );
    }

    public function test_path_segments_drop_slashes_and_ampersands_and_collapse_hyphens(): void
    {
        $this->assertSame('a-b', FeaturedRules::pathSegment('  A / & -- B  '));
    }

    public function test_a_fine_slot_has_no_issues(): void
    {
        $slot = ['product_available' => 1, 'product_deleted' => null, 'price_exc_tax' => '99.00'];

        $this->assertSame([], FeaturedRules::issues($slot, pageIsLive: true, hasThumbnail: true));
    }

    public function test_every_problem_with_a_slot_is_named(): void
    {
        $slot = ['product_available' => 0, 'product_deleted' => '2026-01-01 00:00:00', 'price_exc_tax' => 0];

        $this->assertSame(
            ['Unavailable', 'Deleted', 'No price', 'No page', 'No image'],
            FeaturedRules::issues($slot, pageIsLive: false, hasThumbnail: false),
        );
    }

    public function test_empty_slots_take_the_lowest_unused_sort_numbers(): void
    {
        $padded = FeaturedRules::padToSlots([['sort' => 2, 'product_id' => 9], ['sort' => 4, 'product_id' => 8]], 5);

        $this->assertCount(5, $padded);
        $this->assertSame([1, 3, 5], \array_column(\array_slice($padded, 2), 'sort'));
        $this->assertNull($padded[2]['product_id']);
    }

    public function test_a_full_list_is_left_alone(): void
    {
        $slots = [['sort' => 1, 'product_id' => 1], ['sort' => 2, 'product_id' => 2]];

        $this->assertSame($slots, FeaturedRules::padToSlots($slots, 2));
    }
}
