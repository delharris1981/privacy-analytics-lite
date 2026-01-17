<?php
/**
 * Plugin Name: Privacy-First Analytics Lite
 * Plugin URI: https://example.com/privacy-analytics-lite
 * Description: Privacy-compliant, server-side analytics with aggregated data. No cookies, no PII, zero tracking scripts.
 * Version: 1.6.3
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: privacy-analytics-lite
 *
 * @package PrivacyAnalytics\Lite
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants.
define('PRIVACY_ANALYTICS_LITE_VERSION', '1.6.3');
define('PRIVACY_ANALYTICS_LITE_PLUGIN_FILE', __FILE__);
define('PRIVACY_ANALYTICS_LITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRIVACY_ANALYTICS_LITE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check minimum PHP version.
if (version_compare(PHP_VERSION, '8.2', '<')) {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<?php
					echo esc_html(
						sprintf(
							/* translators: %s: PHP version */
							__('Privacy-First Analytics Lite requires PHP 8.2 or higher. You are running PHP %s.', 'privacy-analytics-lite'),
							PHP_VERSION
						)
					);
					?>
			</p>
		</div>
		<?php
		}
	);
	return;
}

// Check minimum WordPress version.
if (version_compare(get_bloginfo('version'), '6.8', '<')) {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<?php
					echo esc_html(
						sprintf(
							/* translators: %s: WordPress version */
							__('Privacy-First Analytics Lite requires WordPress 6.8 or higher. You are running WordPress %s.', 'privacy-analytics-lite'),
							get_bloginfo('version')
						)
					);
					?>
			</p>
		</div>
		<?php
		}
	);
	return;
}

// Load Composer autoloader.
$autoloader = PRIVACY_ANALYTICS_LITE_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoloader)) {
	require_once $autoloader;
} else {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error">
			<p>
				<?php
					echo esc_html__('Privacy-First Analytics Lite: Composer autoloader not found. Please run `composer install`.', 'privacy-analytics-lite');
					?>
			</p>
		</div>
		<?php
		}
	);
	return;
}

// Initialize the plugin.
use PrivacyAnalytics\Lite\Core\Plugin;

register_activation_hook(__FILE__, array(Plugin::class, 'activate'));
register_deactivation_hook(__FILE__, array(Plugin::class, 'deactivate'));

Plugin::get_instance();

