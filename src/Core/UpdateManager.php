<?php
/**
 * Update and migration manager.
 *
 * @package PrivacyAnalytics\Lite\Core
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Core;

use PrivacyAnalytics\Lite\Database\TableManager;

/**
 * Handles plugin updates and database migrations.
 */
final class UpdateManager
{

    /**
     * Constructor.
     *
     * @param TableManager $table_manager Table manager instance.
     */
    public function __construct(private TableManager $table_manager)
    {
    }

    /**
     * Check for plugin updates and trigger migrations if needed.
     *
     * @return void
     */
    public function check_for_updates(): void
    {
        $stored_version = get_option('privacy_analytics_lite_version', '0.0.0');
        $current_version = PRIVACY_ANALYTICS_LITE_VERSION;

        if (version_compare($stored_version, $current_version, '<')) {
            $this->run_upgrade($stored_version, $current_version);
        }
    }

    /**
     * Run upgrade logic.
     *
     * @param string $from Version upgrading from.
     * @param string $to   Version upgrading to.
     * @return void
     */
    private function run_upgrade(string $from, string $to): void
    {
        // 1. Ensure database schema is up to date.
        $this->table_manager->create_tables();

        // 2. Clear any critical transients or caches.
        // Reserved for future use.

        // 3. Update the stored version.
        update_option('privacy_analytics_lite_version', $to);

        // 4. Set update transient for admin notice.
        set_transient('pa_lite_updated', $to, 60);

        /**
         * Hook for version-specific migration logic.
         *
         * @param string $to   New version.
         * @param string $from Old version.
         */
        do_action('privacy_analytics_lite_updated', $to, $from);
    }
}
