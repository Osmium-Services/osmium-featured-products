/**
 * Featured Products - Editor functionality
 * Handles slot selection, drag-drop reordering, and lock management
 *
 * Requires: window.FeaturedProductsConfig = { hasOwnBackup: boolean }
 */
(function() {
    'use strict';

    const ACTION_URL = adminBasePath + 'featured-products/action/';
    const POLL_INTERVAL_MS = 10000;

    // State from PHP config
    let hasOwnBackup = window.FeaturedProductsConfig?.hasOwnBackup || false;
    window.hasUnsavedChanges = hasOwnBackup;

    // Current selection state
    let currentSlotElement = null;
    let currentSlotSort = null;
    let currentProductId = null;

    /**
     * Show backup bar dynamically when entering edit mode
     * Clones from shared template in partials/backup-bar.phtml
     */
    function showBackupBar() {
        if (document.getElementById('backupBar')) return;

        const template = document.getElementById('backupBarTemplate');
        if (!template) {
            console.error('Backup bar template not found');
            return;
        }

        const bar = template.content.firstElementChild.cloneNode(true);
        document.querySelector('.row.justify-content-center').insertAdjacentElement('beforebegin', bar);
    }

    /**
     * Lock management API
     */
    window.FeaturedProducts = {
        async acquireLock() {
            try {
                const response = await fetch(ACTION_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'acquire_lock' })
                });
                const data = await response.json();
                if (data.success) {
                    hasOwnBackup = true;
                    window.hasUnsavedChanges = true;
                    if (!data.had_backup) {
                        showBackupBar();
                    }
                }
                return data.success;
            } catch (error) {
                console.error('Error acquiring lock:', error);
                return false;
            }
        },

        async releaseBackup() {
            try {
                const response = await fetch(ACTION_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'release_lock' })
                });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('backupBar')?.remove();
                    window.hasUnsavedChanges = false;
                    location.reload();
                }
            } catch (error) {
                console.error('Error releasing backup:', error);
            }
        },

        async restoreBackup() {
            if (!(await adminConfirm('Restore all changes to the original state? This will undo all your changes.'))) return;

            try {
                const response = await fetch(ACTION_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'restore_backup', csrf_token: window.csrfToken })
                });
                const data = await response.json();
                if (data.success) {
                    window.hasUnsavedChanges = false;
                    window.location.reload();
                } else {
                    adminAlert('Failed to restore: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                adminAlert('Error restoring backup');
            }
        }
    };

    /**
     * Filter products in the selection modal
     */
    function filterProducts(searchTerm) {
        const items = document.querySelectorAll('.product-item');
        const term = searchTerm.toLowerCase();

        items.forEach(item => {
            const title = item.dataset.title;
            item.style.display = title.includes(term) ? '' : 'none';
        });
    }

    /**
     * Open product selector modal for a slot
     */
    async function openProductSelectorHandler(element) {
        await window.FeaturedProducts.acquireLock();

        currentSlotElement = element;
        currentSlotSort = element.dataset.slotSort;
        currentProductId = element.dataset.productId || null;
        const slotNumber = Array.from(element.parentElement.children).indexOf(element) + 1;

        document.getElementById('selectedSlotNumber').textContent = '#' + slotNumber;
        document.getElementById('productSearch').value = '';
        filterProducts('');

        const clearBtn = document.getElementById('clearSlotBtn');
        clearBtn.style.display = currentProductId ? 'block' : 'none';

        const modal = new bootstrap.Modal(document.getElementById('productSelectorModal'));
        modal.show();
    }

    /**
     * Select a product for the current slot
     */
    async function selectProductHandler(productId) {
        const submitData = {
            action: 'update_slot',
            old_product_id: currentProductId,
            slot_sort: currentSlotSort,
            product_id: productId,
            csrf_token: window.csrfToken
        };

        try {
            const response = await fetch(ACTION_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(submitData)
            });

            const result = await response.json();

            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('productSelectorModal')).hide();
                window.location.reload();
            } else {
                adminAlert(result.error || 'Failed to update slot');
            }
        } catch (error) {
            adminAlert('An error occurred while updating the slot');
        }
    }

    /**
     * Clear the current slot
     */
    async function clearSlotHandler() {
        if (!currentProductId) return;
        if (!(await adminConfirm('Are you sure you want to clear this slot?'))) return;

        try {
            const response = await fetch(ACTION_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'clear_slot',
                    product_id: currentProductId,
                    csrf_token: window.csrfToken
                })
            });

            const result = await response.json();

            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('productSelectorModal')).hide();
                window.location.reload();
            } else {
                adminAlert(result.error || 'Failed to clear slot');
            }
        } catch (error) {
            adminAlert('An error occurred while clearing the slot');
        }
    }

    /**
     * Initialize drag-and-drop reordering
     */
    function initSortable() {
        const list = document.getElementById('featuredProductsList');
        if (!list) return;

        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'bg-primary-subtle',
            onEnd: async function(evt) {
                await window.FeaturedProducts.acquireLock();

                // Update slot numbers visually
                const items = document.querySelectorAll('#featuredProductsList .featured-product-slot');
                items.forEach((item, index) => {
                    item.querySelector('.slot-number').textContent = '#' + (index + 1);
                });

                // Collect new order
                const order = [];
                items.forEach((item, index) => {
                    const productId = item.dataset.productId;
                    if (productId) {
                        order.push({ product_id: productId, sort: index + 1 });
                    }
                });

                // Save to server
                try {
                    const response = await fetch(ACTION_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reorder', items: order, csrf_token: window.csrfToken })
                    });
                    const text = await response.text();
                    const data = JSON.parse(text);
                    if (!data.success) {
                        adminAlert('Failed to save order: ' + (data.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Reorder error:', error);
                }
            }
        });
    }

    /**
     * Poll for lock changes (someone else acquiring lock while we're viewing)
     */
    function startLockPolling() {
        setInterval(async () => {
            if (hasOwnBackup) return;

            try {
                const response = await fetch(ACTION_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'check_lock' })
                });
                const result = await response.json();

                if (result.success && result.locked) {
                    location.reload();
                }
            } catch (error) {
                console.error('Lock check error:', error);
            }
        }, POLL_INTERVAL_MS);
    }

    /**
     * Initialize event listeners
     */
    function initEventListeners() {
        const searchInput = document.getElementById('productSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterProducts(this.value);
            });
        }
    }

    // Expose functions globally for onclick handlers
    window.openProductSelector = openProductSelectorHandler;
    window.selectProduct = selectProductHandler;
    window.clearSlot = clearSlotHandler;

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initSortable();
        initEventListeners();
        startLockPolling();
    });
})();
