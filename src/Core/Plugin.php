<?php
/**
 * Main plugin orchestration class.
 *
 * @package PrivacyAnalytics\Lite\Core
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Core;

use PrivacyAnalytics\Lite\Admin\Dashboard;
use PrivacyAnalytics\Lite\Database\Aggregator;
use PrivacyAnalytics\Lite\Database\TableManager;
use PrivacyAnalytics\Lite\Tracking\Anonymizer;
use PrivacyAnalytics\Lite\Tracking\BotDetector;
use PrivacyAnalytics\Lite\Tracking\DeviceDetector;
use PrivacyAnalytics\Lite\Tracking\ReferrerNormalizer;
use PrivacyAnalytics\Lite\Tracking\Tracker;
use PrivacyAnalytics\Lite\Tracking\HeatmapTracker;

/**
 * Main plugin class.
 */
final class Plugin
{

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Table manager instance.
	 *
	 * @var TableManager
	 */
	private TableManager $table_manager;

	/**
	 * Tracker instance.
	 *
	 * @var Tracker|null
	 */
	private ?Tracker $tracker = null;

	/**
	 * Update manager instance.
	 *
	 * @var UpdateManager|null
	 */
	private ?UpdateManager $update_manager = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		$this->table_manager = new TableManager();
		$this->init();
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	private function init(): void
	{
		// Phase 2: Register tracking hooks.
		$this->init_tracking();

		// Phase 3: Register aggregation cron hook.
		$this->init_aggregation();

		// Phase 3.5: Heatmap Tracking handlers (AJAX).
		$this->init_heatmap_handlers();

		// Phase 4: Initialize admin dashboard, updates, and GitHub updater.
		if (is_admin()) {
			$this->update_manager = new UpdateManager($this->table_manager);
			add_action('admin_init', array($this->update_manager, 'check_for_updates'));

			// Check for remote updates from GitHub.
			new GitHubUpdater();

			$this->init_admin();
		}
	}

	/**
	 * Initialize tracking system.
	 *
	 * @return void
	 */
	private function init_tracking(): void
	{
		// Only track on frontend.
		if (is_admin()) {
			return;
		}

		$anonymizer = new Anonymizer();
		$bot_detector = new BotDetector();
		$referrer_normalizer = new ReferrerNormalizer();
		$device_detector = new DeviceDetector();

		$this->tracker = new Tracker(
			$anonymizer,
			$bot_detector,
			$referrer_normalizer,
			$device_detector,
			$this->table_manager
		);

		// Hook to template_redirect for server-side tracking.
		add_action('template_redirect', array($this->tracker, 'track_hit'));
	}

	/**
	 * Initialize aggregation system.
	 *
	 * @return void
	 */
	private function init_aggregation(): void
	{
		// Register cron hook for aggregation.
		add_action('privacy_analytics_lite_aggregate', array($this, 'run_aggregation'));
	}

	/**
	 * Initialize heatmap handlers.
	 *
	 * @return void
	 */
	private function init_heatmap_handlers(): void
	{
		$heatmap_tracker = new HeatmapTracker($this->table_manager);
		add_action('wp_ajax_pa_track_heatmap', array($heatmap_tracker, 'track_click'));
		add_action('wp_ajax_nopriv_pa_track_heatmap', array($heatmap_tracker, 'track_click'));
		add_action('wp_ajax_pa_get_heatmap_data', array($heatmap_tracker, 'handle_ajax_get_heatmap_data'));

		// Register Heatmap Viewer script (enqueue only when needed).
		add_action('wp_enqueue_scripts', function () {
			if (isset($_GET['pa_heatmap']) && $_GET['pa_heatmap'] === 'true' && current_user_can('manage_options')) {
				wp_enqueue_style(
					'pa-heatmap-viewer',
					PRIVACY_ANALYTICS_LITE_PLUGIN_URL . 'assets/css/heatmap-viewer.css',
					array(),
					PRIVACY_ANALYTICS_LITE_VERSION
				);

				wp_enqueue_script(
					'pa-heatmap-viewer',
					PRIVACY_ANALYTICS_LITE_PLUGIN_URL . 'assets/js/heatmap-viewer.js',
					array(),
					PRIVACY_ANALYTICS_LITE_VERSION,
					true
				);

				wp_localize_script('pa-heatmap-viewer', 'pa_heatmap_data', array(
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('pa_heatmap_nonce'),
					'page_path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
				));
			}
		});
	}

	/**
	 * Run aggregation (called by cron).
	 *
	 * @return void
	 */
	public function run_aggregation(): void
	{
		$aggregator = new Aggregator($this->table_manager);
		$aggregator->aggregate_hits();
	}

	/**
	 * Initialize admin dashboard.
	 *
	 * @return void
	 */
	private function init_admin(): void
	{
		$dashboard = new Dashboard($this->table_manager, PRIVACY_ANALYTICS_LITE_VERSION);
		$dashboard->init();
	}

	/**
	 * Plugin activation handler.
	 *
	 * @return void
	 */
	public static function activate(): void
	{
		$table_manager = new TableManager();
		$table_manager->create_tables();

		// Record initial version to avoid unnecessary migrations on first install.
		if (!get_option('privacy_analytics_lite_version')) {
			update_option('privacy_analytics_lite_version', PRIVACY_ANALYTICS_LITE_VERSION);
		}

		// Schedule aggregation cron event.
		if (!wp_next_scheduled('privacy_analytics_lite_aggregate')) {
			wp_schedule_event(time(), 'hourly', 'privacy_analytics_lite_aggregate');
		}
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * @return void
	 */
	public static function deactivate(): void
	{
		// Unschedule aggregation cron event.
		wp_clear_scheduled_hook('privacy_analytics_lite_aggregate');

		// Flush rewrite rules if needed.
		flush_rewrite_rules();
	}

	/**
	 * Get table manager instance.
	 *
	 * @return TableManager
	 */
	public function get_table_manager(): TableManager
	{
		return $this->table_manager;
	}
}

