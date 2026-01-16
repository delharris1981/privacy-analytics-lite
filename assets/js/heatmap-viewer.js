/**
 * Privacy Analytics Lite - Heatmap Viewer
 */
(function () {
    'use strict';

    if (!window.pa_heatmap_data) {
        return;
    }

    const config = window.pa_heatmap_data;

    function init() {
        // Create container overlay
        const overlay = document.createElement('div');
        overlay.id = 'pa-heatmap-overlay';
        document.body.appendChild(overlay);

        // Create canvas
        const canvas = document.createElement('canvas');
        canvas.id = 'pa-heatmap-canvas';
        resizeCanvas(canvas);
        overlay.appendChild(canvas);

        // Close button
        const closeBtn = document.createElement('button');
        closeBtn.id = 'pa-heatmap-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.onclick = function () {
            document.body.removeChild(overlay);
        };
        overlay.appendChild(closeBtn);

        // Legend
        const legend = document.createElement('div');
        legend.id = 'pa-heatmap-legend';
        legend.innerHTML = '<span>Less</span><div class="pa-gradient"></div><span>More</span>';
        overlay.appendChild(legend);

        // Fetch Data
        fetchData(canvas);

        // Handle resize
        window.addEventListener('resize', () => {
            resizeCanvas(canvas);
            fetchData(canvas); // Re-draw
        });
    }

    function resizeCanvas(canvas) {
        const body = document.body;
        const html = document.documentElement;
        const height = Math.max(body.scrollHeight, body.offsetHeight, html.clientHeight, html.scrollHeight, html.offsetHeight);

        canvas.width = window.innerWidth;
        canvas.height = height;
    }

    function fetchData(canvas) {
        const params = new URLSearchParams({
            action: 'pa_get_heatmap_data',
            nonce: config.nonce,
            page_path: config.page_path,
            viewport: getViewport()
        });

        fetch(config.ajax_url + '?' + params.toString())
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    drawHeatmap(canvas, response.data);
                } else {
                    console.error('Heatmap data error:', response.data);
                }
            })
            .catch(err => console.error('Fetch error:', err));
    }

    function getViewport() {
        const width = window.innerWidth;
        if (width < 768) return 'mobile';
        if (width < 1024) return 'tablet';
        return 'desktop';
    }

    function drawHeatmap(canvas, points) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Heatmap Config
        const radius = 25;
        const blur = 15;

        // Draw points with radial gradient (simple heat approach)
        points.forEach(pt => {
            // x is percentage 0-100
            // y is 20px buckets
            const xPx = (pt.x / 100) * document.body.clientWidth; // Use clientWidth for content layout match
            const yPx = pt.y * 20;
            const count = pt.count;

            // Only draw if within bounds (responsiveness might shift things slightly)
            if (xPx > canvas.width) return;

            ctx.beginPath();
            // Simple opacity based on count? For Lite, let's keep it visually simpler.
            // Usually heatmaps need a library like simpleheat.js, but we want zero dep.
            // Let's implement a basic radial draw.

            // Alpha based on count (capped).
            const alpha = Math.min(count * 0.2, 1); // 5 clicks = max opacity

            const gradient = ctx.createRadialGradient(xPx, yPx, 0, xPx, yPx, radius);
            gradient.addColorStop(0, `rgba(255, 0, 0, ${alpha})`);
            gradient.addColorStop(0.5, `rgba(255, 255, 0, ${alpha * 0.5})`);
            gradient.addColorStop(1, 'rgba(0, 0, 255, 0)');

            ctx.fillStyle = gradient;
            // Global Composite Operation 'screen' or 'lighter' helps stack colors
            ctx.globalCompositeOperation = 'lighter';
            ctx.arc(xPx, yPx, radius, 0, 2 * Math.PI);
            ctx.fill();
        });

        // Reset composite
        ctx.globalCompositeOperation = 'source-over';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
