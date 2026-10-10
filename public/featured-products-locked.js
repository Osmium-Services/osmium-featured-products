/**
 * Featured Products - Lock polling for locked state
 * Polls server to detect when lock is released, then reloads page
 */
(function() {
    'use strict';

    const POLL_INTERVAL_MS = 10000;

    async function checkLock() {
        try {
            const response = await fetch(adminBasePath + 'featured-products/action/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'check_lock' })
            });
            const result = await response.json();

            if (result.success && !result.locked) {
                location.reload();
            }
        } catch (error) {
            console.error('Lock check error:', error);
        }
    }

    setInterval(checkLock, POLL_INTERVAL_MS);
})();
