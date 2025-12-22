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
use PrivacyAnalytics\Lite\Tracking\ReferrerNormalizer;
use PrivacyAnalytics\Lite\Tracking\Tracker;

/**
 * Main plugin class.
 */
final class Plugin {

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
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->table_manager = new TableManager();
		$this->init();
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	private function init(): void {
		// Phase 2: Register tracking hooks.
		$this->init_tracking();

		// Phase 3: Register aggregation cron hook.
		$this->init_aggregation();

		// Phase 4: Initialize admin dashboard.
		if ( is_admin() ) {
			$this->init_admin();
		}
	}

	/**
	 * Initialize tracking system.
	 *
	 * @return void
	 */
	private function init_tracking(): void {
		// Only track on frontend.
		if ( is_admin() ) {
			return;
		}

		$anonymizer          = new Anonymizer();
		$bot_detector        = new BotDetector();
		$referrer_normalizer = new ReferrerNormalizer();

		$this->tracker = new Tracker(
			$anonymizer,
			$bot_detector,
			$referrer_normalizer,
			$this->table_manager
		);

		// Hook to template_redirect for server-side tracking.
		add_action( 'template_redirect', array( $this->tracker, 'track_hit' ) );
	}

	/**
	 * Initialize aggregation system.
	 *
	 * @return void
	 */
	private function init_aggregation(): void {
		// Register cron hook for aggregation.
		add_action( 'privacy_analytics_lite_aggregate', array( $this, 'run_aggregation' ) );
	}

	/**
	 * Run aggregation (called by cron).
	 *
	 * @return void
	 */
	public function run_aggregation(): void {
		$aggregator = new Aggregator( $this->table_manager );
		$aggregator->aggregate_hits();
	}

	/**
	 * Initialize admin dashboard.
	 *
	 * @return void
	 */
	private function init_admin(): void {
		$dashboard = new Dashboard( $this->table_manager, PRIVACY_ANALYTICS_LITE_VERSION );
		$dashboard->init();
	}

	/**
	 * Plugin activation handler.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$table_manager = new TableManager();
		$table_manager->create_tables();

		// Schedule aggregation cron event.
		if ( ! wp_next_scheduled( 'privacy_analytics_lite_aggregate' ) ) {
			wp_schedule_event( time(), 'hourly', 'privacy_analytics_lite_aggregate' );
		}
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Unschedule aggregation cron event.
		wp_clear_scheduled_hook( 'privacy_analytics_lite_aggregate' );

		// Flush rewrite rules if needed.
		flush_rewrite_rules();
	}

	/**
	 * Get table manager instance.
	 *
	 * @return TableManager
	 */
	public function get_table_manager(): TableManager {
		return $this->table_manager;
	}
}

