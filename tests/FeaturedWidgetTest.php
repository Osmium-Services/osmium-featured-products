<?php

declare(strict_types=1);

namespace Tests\Services\FeaturedProducts;

use Osmium\Services\FeaturedProducts\Models\FeaturedWidget;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/FeaturedProduct.php';
require_once __DIR__ . '/../models/FeaturedWidget.php';

/**
 * The dashboard card for a featured list that needs attention.
 */
final class FeaturedWidgetTest extends TestCase
{
    public function test_there_is_no_card_when_every_slot_is_filled_and_fine(): void
    {
        $this->assertNull(FeaturedWidget::build(emptyCount: 0, problems: []));
    }

    public function test_it_lists_each_problem_slot_and_the_empty_slots_with_a_count_of_both(): void
    {
        $card = FeaturedWidget::build(emptyCount: 2, problems: [['title' => 'Jinko Tiger', 'issues' => ['No image', 'No price']]]);

        $this->assertSame(3, $card['count']);
        $this->assertSame('Jinko Tiger', $card['lines'][0]['text']);
        $this->assertSame('No image, No price', $card['lines'][0]['sub']);
        $this->assertSame('2 empty slots', $card['lines'][1]['text']);
        $this->assertSame('featured-products/', $card['link']['route']);
    }

    public function test_one_empty_slot_is_singular(): void
    {
        $card = FeaturedWidget::build(emptyCount: 1, problems: []);

        $this->assertSame('1 empty slot', $card['lines'][0]['text']);
    }
}
