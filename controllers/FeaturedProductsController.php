<?php

declare(strict_types=1);

namespace Osmium\Services\FeaturedProducts\Controllers;

use Osmium\Core\Models\Model;
use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\FeaturedProducts\Models\FeaturedProduct;
use Osmium\Services\FeaturedProducts\Models\FeaturedRules;

/**
 * Featured products admin: five slots to fill, reorder and clear, behind an edit lock so two admins
 * never change the list at once.
 *
 * Routes:
 *   - index()  → /admin/featured-products/
 *   - action() → /admin/featured-products/action/   (update_slot, clear_slot, reorder, and the lock actions)
 */
class FeaturedProductsController extends AdminController
{
    private const RECORD_TYPE = 'featured-product';
    private const LOCK_RESOURCE = 'featured-products';

    public function index(): void
    {
        $this->registerLock();

        $featured = new FeaturedProduct($this->osmium->dataSource);
        $pagesById = Model::pagesById();
        $imagesPath = $this->osmium->config->site->imagesPath;

        $slots = \array_map(fn(array $slot): array => $this->describeSlot($slot, $pagesById, $imagesPath), $featured->listCurrent());
        $slots = FeaturedRules::padToSlots($slots, FeaturedProduct::SLOT_COUNT);
        $lockStatus = $this->lockStatus();

        $this->data['admin']['featuredProducts'] = $slots;
        $this->data['admin']['allProducts'] = $this->selectableProducts($featured, $pagesById, $imagesPath);
        $this->data['admin']['emptySlotCount'] = \count(\array_filter($slots, static fn(array $slot): bool => !$slot['product_id']));
        $this->data['admin']['unavailableCount'] = \count(\array_filter($slots, static fn(array $slot): bool => $slot['has_issues'] ?? false));
        $this->data['admin']['featuredSlotsByProductId'] = $this->slotsByProductId($slots);
        $this->data['admin']['hasOwnBackup'] = $lockStatus['hasOwnBackup'];
        $this->data['admin']['lockedByOther'] = $lockStatus['lockedByOther'];
        $this->data['admin']['lockInfo'] = $lockStatus['lockInfo'];
        $this->data['admin']['jsObject'] = 'FeaturedProducts';

        $this->setView('featured-products/' . ($lockStatus['lockedByOther'] ? 'locked.phtml' : 'index.phtml'));
    }

    public function action()
    {
        \header('Content-Type: application/json');
        $this->registerLock();

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $action = $input['action'] ?? null;
        if (!$action) $this->admin->jsonError('Missing action');

        $lockHandled = $this->admin->lock->handleAction($action, self::LOCK_RESOURCE);
        if ($lockHandled) exit;

        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh the page and try again.');

        try {
            $this->handleAction($action, $input);
            $this->admin->jsonSuccess(['success' => true]);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    /** Tell core's edit lock about this list's lock table and the table it snapshots */
    private function registerLock(): void
    {
        $this->admin->lock->register(
            lockType: self::LOCK_RESOURCE,
            lockTable: 'featured_products_lock',
            snapshots: ['featured_products' => 'featured_products_snapshot'],
        );
    }

    /** A filled slot with its thumbnail, title, page url and what is wrong with it */
    private function describeSlot(array $slot, array $pagesById, string $imagesPath): array
    {
        $thumbnail = FeaturedRules::thumbnailPath($slot['brand_name'] ?? '', $slot['model_name'] ?? '');
        $page = $pagesById[$slot['page_id']] ?? null;
        $pageIsLive = $page !== null && $page['deleted_at'] === null;

        $slot['thumbnail'] = $imagesPath . $thumbnail;
        $slot['title'] = ($slot['brand_name'] ?? '') . ' ' . ($slot['model_name'] ?? '');
        $slot['url'] = $pageIsLive ? $page['url'] : null;
        $slot['issues'] = FeaturedRules::issues(
            slot: $slot,
            pageIsLive: $pageIsLive,
            hasThumbnail: \file_exists($_SERVER['DOCUMENT_ROOT'] . $slot['thumbnail']),
        );
        $slot['has_issues'] = $slot['issues'] !== [];

        return $slot;
    }

    /** Products an admin may pick: each needs a live page and an uploaded thumbnail as well as a price and availability */
    private function selectableProducts(FeaturedProduct $featured, array $pagesById, string $imagesPath): array
    {
        $existing = [];
        foreach (\glob($_SERVER['DOCUMENT_ROOT'] . $imagesPath . '/shop/*/*/thumb/*.jpg') ?: [] as $file) {
            $existing[\basename($file)] = true;
        }

        $selectable = [];
        foreach ($featured->listSelectableProducts() as $product) {
            $thumbnail = FeaturedRules::thumbnailPath($product['brand_name'] ?? '', $product['model_name'] ?? '');
            $page = $pagesById[$product['page_id']] ?? null;
            $hasLivePage = $product['page_id'] && $page && $page['deleted_at'] === null;
            if (!$hasLivePage || !isset($existing[\basename($thumbnail)])) continue;

            $product['thumbnail'] = $imagesPath . $thumbnail;
            $product['title'] = $product['brand_name'] . ' ' . $product['model_name'];
            $selectable[] = $product;
        }

        return $selectable;
    }

    private function slotsByProductId(array $slots): array
    {
        $byProduct = [];
        foreach ($slots as $index => $slot) {
            if ($slot['product_id']) $byProduct[$slot['product_id']] = $index + 1;
        }

        return $byProduct;
    }

    private function lockStatus(): array
    {
        $status = $this->admin->lock->getStatus(self::LOCK_RESOURCE);
        if ($status['lockInfo']) $status['lockInfo'] = $this->enrichLockInfo($status['lockInfo']);

        return $status;
    }

    private function enrichLockInfo(array $lockInfo): array
    {
        $nameParts = \explode(' ', $lockInfo['user_name'] ?? '');
        $lockInfo['initials'] = \strtoupper(($nameParts[0][0] ?? '') . ($nameParts[1][0] ?? ''));
        $lockInfo['started_at'] = \date('g:ia', \strtotime($lockInfo['created_at']));

        $avatarFile = $lockInfo['avatar'] ?? '';
        $lockInfo['avatarExists'] = $avatarFile && \file_exists($this->data['admin']['avatarPath'] . $avatarFile);

        return $lockInfo;
    }

    private function handleAction(string $action, array $input): void
    {
        $needsLock = \in_array($action, ['update_slot', 'clear_slot', 'reorder']);
        $lockAcquired = $this->admin->lock->acquire(self::LOCK_RESOURCE);
        if ($needsLock && !$lockAcquired) $this->admin->jsonError('Locked by another user');

        $featured = new FeaturedProduct($this->osmium->dataSource);
        match ($action) {
            'update_slot' => $this->updateSlot($featured, $input),
            'clear_slot' => $this->clearSlot($featured, $input),
            'reorder' => $this->reorder($featured, $input),
            default => $this->admin->jsonError('Unknown action'),
        };
    }

    private function updateSlot(FeaturedProduct $featured, array $input): void
    {
        $productId = $input['product_id'] ?? null;
        if (!$productId) $this->admin->jsonError('Missing product ID');

        $oldProductId = $input['old_product_id'] ?? null;
        $isUpdate = (bool) $oldProductId;

        if ($isUpdate) {
            $featured->updateSlot((int) $productId, (int) $oldProductId);
        } else {
            $featured->insertSlot((int) $productId, (int) ($input['slot_sort'] ?? 1));
        }

        $this->admin->model->changelog->log(
            description: $isUpdate ? 'Updated featured product slot' : 'Added product to featured products',
            recordType: self::RECORD_TYPE,
            recordId: (int) $productId,
        );
    }

    private function clearSlot(FeaturedProduct $featured, array $input): void
    {
        $productId = $input['product_id'] ?? null;
        if (!$productId) $this->admin->jsonError('Missing product ID');

        $featured->clearSlot((int) $productId);

        $this->admin->model->changelog->log(
            description: 'Removed product from featured products',
            recordType: self::RECORD_TYPE,
            recordId: (int) $productId,
        );
    }

    private function reorder(FeaturedProduct $featured, array $input): void
    {
        $items = $input['items'] ?? [];
        if (empty($items)) $this->admin->jsonError('No items to reorder');

        $featured->updateSorts($items);

        $this->admin->model->changelog->log(description: 'Reordered featured products', recordType: self::RECORD_TYPE);
    }
}
