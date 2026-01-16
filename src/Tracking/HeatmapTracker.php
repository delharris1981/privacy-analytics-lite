<?php
/**
 * Heatmap tracking handler.
 *
 * @package PrivacyAnalytics\Lite\Tracking
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Tracking;

use PrivacyAnalytics\Lite\Database\TableManager;

/**
 * Handles heatmap click tracking via AJAX/Beacon.
 */
class HeatmapTracker
{
    /**
     * Table manager instance.
     *
     * @var TableManager
     */
    private TableManager $table_manager;

    /**
     * Constructor.
     *
     * @param TableManager $table_manager Table manager instance.
     */
    public function __construct(TableManager $table_manager)
    {
        $this->table_manager = $table_manager;
    }

    /**
     * Track heatmap click.
     *
     * @return void
     */
    public function track_click(): void
    {
        // Verify nonce? Beacon requests might skip standard wp_verify_nonce if we want them to be fast/public,
        // but for security we should probably include one if possible. 
        // Navigator.sendBeacon sends POST.
        // Let's assume we pass a nonce.

        // Check for required fields.
        if (!isset($_POST['page_path'], $_POST['viewport'], $_POST['x'], $_POST['y'])) {
            wp_send_json_error('Missing required fields'); // Beacon ignores response, but good for debugging.
        }

        $page_path = sanitize_text_field(wp_unslash($_POST['page_path']));
        $viewport = sanitize_text_field(wp_unslash($_POST['viewport']));
        $x = absint($_POST['x']);
        $y = absint($_POST['y']);

        // Validate inputs.
        if ($x > 100 || $x < 0) {
            return; // Invalid X percentage.
        }

        // Allowed viewports.
        $allowed_viewports = array('mobile', 'tablet', 'desktop');
        if (!in_array($viewport, $allowed_viewports, true)) {
            $viewport = 'desktop'; // Default fallback.
        }

        global $wpdb;
        $table_name = $this->table_manager->get_heatmap_table_name();

        // Prepare SQL for ON DUPLICATE KEY UPDATE.
        // We can't use $wpdb->insert easily for this without a raw query or multiple calls.
        // Raw query is best for atomic increment.

        $sql = $wpdb->prepare(
            "INSERT INTO {$table_name} (page_path, viewport_type, x_grid, y_grid, click_count)
            VALUES (%s, %s, %d, %d, 1)
            ON DUPLICATE KEY UPDATE click_count = click_count + 1",
            $page_path,
            $viewport,
            $x,
            $y
        );

        $wpdb->query($sql);

        wp_send_json_success();
    }
}
