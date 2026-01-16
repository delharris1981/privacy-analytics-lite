/**
 * Privacy Analytics Lite - Heatmap Tracker
 * 
 * Minimal footprint grid-based tracking.
 */
(function () {
    'use strict';

    if (!window.privacy_analytics) {
        return;
    }

    document.addEventListener('click', function (e) {
        // 1. Calculate Grid Coordinates
        // X = Percentage (0-100) to handle responsiveness
        // Y = 20px buckets for vertical scroll depth
        const docWidth = document.body.clientWidth || window.innerWidth;
        const x = e.pageX;
        const y = e.pageY;

        if (docWidth === 0) return;

        const xGrid = Math.floor((x / docWidth) * 100);
        const yGrid = Math.floor(y / 20);

        // 2. Determine Viewport Type
        let viewport = 'desktop';
        const width = window.innerWidth;
        if (width < 768) {
            viewport = 'mobile';
        } else if (width < 1024) {
            viewport = 'tablet';
        }

        // 3. Prepare Data
        const data = new FormData();
        data.append('action', 'pa_track_heatmap');
        data.append('page_path', window.privacy_analytics.page_path);
        data.append('viewport', viewport);
        data.append('x', xGrid.toString());
        data.append('y', yGrid.toString());

        // 4. Send Beacon (Fire and forget, non-blocking)
        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.privacy_analytics.ajax_url, data);
        } else {
            // Fallback for older browsers (optional, skipping for Lite)
            // fetch(window.privacy_analytics.ajax_url, { method: 'POST', body: data, keepalive: true });
        }
    }, { passive: true });

})();
