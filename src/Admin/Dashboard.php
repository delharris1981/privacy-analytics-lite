<?php
/**
 * Admin dashboard for analytics.
 *
 * @package PrivacyAnalytics\Lite\Admin
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Admin;

use PrivacyAnalytics\Lite\Database\TableManager;

/**
 * Admin dashboard class.
 */
class Dashboard
{

	/**
	 * Table manager instance.
	 *
	 * @var TableManager
	 */
	private TableManager $table_manager;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Constructor.
	 *
	 * @param TableManager $table_manager Table manager instance.
	 * @param string       $version       Plugin version.
	 */
	public function __construct(TableManager $table_manager, string $version)
	{
		$this->table_manager = $table_manager;
		$this->version = $version;
	}

	/**
	 * Initialize dashboard.
	 *
	 * @return void
	 */
	public function init(): void
	{
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_ajax_privacy_analytics_get_stats', array($this, 'ajax_get_stats'));
		add_action('admin_notices', array($this, 'maybe_display_update_notice'));
	}

	/**
	 * Add admin menu item.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void
	{
		add_menu_page(
			__('Privacy Analytics', 'privacy-analytics-lite'),
			__('Privacy Analytics', 'privacy-analytics-lite'),
			'manage_options',
			'privacy-analytics-lite',
			array($this, 'render_dashboard'),
			'dashicons-chart-line',
			30
		);
	}

	/**
	 * Enqueue dashboard assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets(string $hook): void
	{
		// Only load on our admin page.
		if ('toplevel_page_privacy-analytics-lite' !== $hook) {
			return;
		}

		// Enqueue Frappe Charts from CDN.
		// Enqueue Frappe Charts from local assets.
		wp_enqueue_script(
			'frappe-charts',
			PRIVACY_ANALYTICS_LITE_PLUGIN_URL . 'assets/js/frappe-charts.min.umd.js',
			array(),
			'1.6.2',
			true
		);

		// Enqueue dashboard CSS.
		wp_enqueue_style(
			'privacy-analytics-lite-admin',
			PRIVACY_ANALYTICS_LITE_PLUGIN_URL . 'assets/css/admin-dashboard.css',
			array(),
			$this->version
		);

		// Enqueue dashboard JS.
		wp_enqueue_script(
			'privacy-analytics-lite-admin',
			PRIVACY_ANALYTICS_LITE_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			array('frappe-charts'),
			$this->version,
			true
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void
	{
		// Check capability.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'privacy-analytics-lite'));
		}

		// Defaults to last 30 days which matches the JS default.
		// We'll let JS handle the initial fetch or do a default fetch here.
		// For SSR consistency, we compute defaults.
		$end_date = current_time('Y-m-d');
		$start_date = date('Y-m-d', strtotime('-29 days', strtotime($end_date)));

		// Get stats data.
		$summary_stats = $this->get_summary_stats($start_date, $end_date);
		$daily_trends = $this->get_daily_trends($start_date, $end_date);
		$hourly_stats = $this->get_hourly_stats();
		$top_pages = $this->get_top_pages($start_date, $end_date);
		$referrer_stats = $this->get_referrer_stats($start_date, $end_date);
		$device_stats = $this->get_device_stats($start_date, $end_date);
		$os_stats = $this->get_os_stats($start_date, $end_date);

		// Check for update transient and set a flag for JS.
		$is_updated = (bool) get_transient('pa_lite_updated');
		if ($is_updated) {
			add_filter('admin_body_class', function ($classes) {
				return "$classes pa-just-updated";
			});
		}
		?>
		<div class="wrap privacy-analytics-dashboard">
			<div class="pa-header">
				<h1><?php echo esc_html__('Privacy Analytics', 'privacy-analytics-lite'); ?></h1>

				<div class="pa-date-controls">
					<select id="pa-date-range-selector" class="pa-select">
						<option value="7"><?php echo esc_html__('Last 7 Days', 'privacy-analytics-lite'); ?></option>
						<option value="30" selected><?php echo esc_html__('Last 30 Days', 'privacy-analytics-lite'); ?></option>
						<option value="90"><?php echo esc_html__('Last 90 Days', 'privacy-analytics-lite'); ?></option>
						<option value="custom"><?php echo esc_html__('Custom Range', 'privacy-analytics-lite'); ?></option>
					</select>

					<div id="pa-custom-date-inputs" class="pa-date-inputs" style="display: none;">
						<input type="date" id="pa-date-start" value="<?php echo esc_attr($start_date); ?>"
							max="<?php echo esc_attr($end_date); ?>">
						<span class="pa-date-separator">-</span>
						<input type="date" id="pa-date-end" value="<?php echo esc_attr($end_date); ?>"
							max="<?php echo esc_attr($end_date); ?>">
						<button type="button" id="pa-date-apply"
							class="button button-secondary"><?php echo esc_html__('Apply', 'privacy-analytics-lite'); ?></button>
					</div>
				</div>
			</div>

			<!-- Summary Stats Cards -->
			<div class="pa-stats-grid">
				<div class="pa-stat-card">
					<div class="pa-stat-label"><?php echo esc_html__('Total Hits', 'privacy-analytics-lite'); ?></div>
					<div class="pa-stat-value"><?php echo esc_html(number_format_i18n($summary_stats['total_hits'])); ?>
					</div>
					<div class="pa-stat-period"><?php echo esc_html__('Last 30 days', 'privacy-analytics-lite'); ?></div>
				</div>
				<div class="pa-stat-card">
					<div class="pa-stat-label"><?php echo esc_html__('Unique Visitors', 'privacy-analytics-lite'); ?></div>
					<div class="pa-stat-value">
						<?php echo esc_html(number_format_i18n($summary_stats['unique_visitors'])); ?>
					</div>
					<div class="pa-stat-period"><?php echo esc_html__('Last 30 days', 'privacy-analytics-lite'); ?></div>
				</div>
				<div class="pa-stat-card">
					<div class="pa-stat-label"><?php echo esc_html__('Top Pages', 'privacy-analytics-lite'); ?></div>
					<div class="pa-stat-value"><?php echo esc_html(number_format_i18n(count($top_pages))); ?></div>
					<div class="pa-stat-period"><?php echo esc_html__('Tracked', 'privacy-analytics-lite'); ?></div>
				</div>
			</div>

			<!-- Daily Trends Chart -->
			<div class="pa-chart-container">
				<h2><?php echo esc_html__('Daily Trends', 'privacy-analytics-lite'); ?></h2>
				<div id="pa-daily-trends-chart" class="pa-chart"
					data-chart-data="<?php echo esc_attr(wp_json_encode($daily_trends)); ?>"></div>
			</div>



			<!-- New Charts Grid -->
			<div class="pa-charts-grid">
				<!-- Hourly Traffic Chart -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__('Hourly Traffic (Last 24h)', 'privacy-analytics-lite'); ?></h2>
					<div id="pa-hourly-chart" class="pa-chart"
						data-chart-data="<?php echo esc_attr(wp_json_encode($hourly_stats)); ?>"></div>
				</div>

				<!-- Referrer Distribution Chart (Donut) -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__('Referrer Distribution', 'privacy-analytics-lite'); ?></h2>
					<div id="pa-referrer-donut-chart" class="pa-chart"
						data-chart-data="<?php echo esc_attr(wp_json_encode($referrer_stats['chart_data'])); ?>"></div>
				</div>
			</div>

			<!-- Device & OS Charts Grid -->
			<div class="pa-charts-grid">
				<!-- Device Type Chart -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__('Device Types', 'privacy-analytics-lite'); ?></h2>
					<div id="pa-device-chart" class="pa-chart"
						data-chart-data="<?php echo esc_attr(wp_json_encode($device_stats['chart_data'])); ?>"></div>
				</div>

				<!-- OS Chart -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__('Operating Systems', 'privacy-analytics-lite'); ?></h2>
					<div id="pa-os-chart" class="pa-chart"
						data-chart-data="<?php echo esc_attr(wp_json_encode($os_stats['chart_data'])); ?>"></div>
				</div>
			</div>


			<!-- Existing Charts Grid -->
			<div class="pa-charts-grid">
				<!-- Top Pages Chart -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__('Top Pages', 'privacy-analytics-lite'); ?></h2>
					<div id="pa-top-pages-chart" class="pa-chart"
						data-chart-data="<?php echo esc_attr(wp_json_encode($top_pages['chart_data'])); ?>"></div>
				</div>

				<!-- Referral Sources Chart -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__('Referral Sources', 'privacy-analytics-lite'); ?></h2>
					<div id="pa-referrer-chart" class="pa-chart"
						data-chart-data="<?php echo esc_attr(wp_json_encode($referrer_stats['chart_data'])); ?>"></div>
				</div>
			</div>

			<!-- Tables -->
			<div class="pa-tables-grid">
				<!-- Top Pages Table -->
				<div class="pa-table-container">
					<h2><?php echo esc_html__('Top Pages', 'privacy-analytics-lite'); ?></h2>
					<?php $this->render_top_pages_table($top_pages['table_data']); ?>
				</div>

				<!-- Referrer Sources Table -->
				<div class="pa-table-container">
					<h2><?php echo esc_html__('Referral Sources', 'privacy-analytics-lite'); ?></h2>
					<?php $this->render_referrer_table($referrer_stats['table_data']); ?>
				</div>
			</div>

			<!-- Device Tables -->
			<div class="pa-tables-grid">
				<div class="pa-table-container">
					<h2><?php echo esc_html__('Device Types', 'privacy-analytics-lite'); ?></h2>
					<?php $this->render_device_table($device_stats['table_data']); ?>
				</div>

				<div class="pa-table-container">
					<h2><?php echo esc_html__('Operating Systems', 'privacy-analytics-lite'); ?></h2>
					<?php $this->render_os_table($os_stats['table_data']); ?>
				</div>
			</div>
			<!-- Footer -->
			<div class="pa-footer">
				<div class="pa-footer-left">
					<?php echo esc_html__('Thank you for creating with WordPress.', 'privacy-analytics-lite'); ?>
				</div>
				<div class="pa-footer-right">
					<span class="pa-version">Version <?php echo esc_html($this->version); ?></span>
					<button id="pa-whats-new-btn"
						class="button button-link"><?php echo esc_html__('What\'s New', 'privacy-analytics-lite'); ?></button>
				</div>
			</div>

			<!-- What's New Modal -->
			<div class="pa-modal-overlay">
				<div class="pa-modal">
					<div class="pa-modal-header">
						<h2><?php echo esc_html__('What\'s New', 'privacy-analytics-lite'); ?></h2>
						<button id="pa-modal-close" class="pa-modal-close">&times;</button>
					</div>
					<div class="pa-modal-content">
						<?php echo wp_kses_post($this->get_latest_changelog()); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the latest changelog entry.
	 *
	 * @return string HTML formatted changelog.
	 */
	private function get_latest_changelog(): string
	{
		$changelog_path = PRIVACY_ANALYTICS_LITE_PLUGIN_DIR . 'CHANGELOG.md';

		if (!file_exists($changelog_path)) {
			return '<p>' . esc_html__('Changelog not found.', 'privacy-analytics-lite') . '</p>';
		}

		$content = file_get_contents($changelog_path);
		if (!$content) {
			return '';
		}

		// Extract the first version section.
		if (preg_match('/## \[\d+\.\d+\.\d+\].*?(?=## \[\d+\.\d+\.\d+\]|$)/s', $content, $matches)) {
			$markdown = $matches[0];

			// Simple Markdown to HTML conversion.
			// Convert headers
			$html = preg_replace('/### (.*)/', '<h3>$1</h3>', $markdown);
			// Convert list items
			$html = preg_replace('/- (.*)/', '<li>$1</li>', $html);
			// Wrap lists
			$html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
			// Convert bold
			$html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
			// Convert code
			$html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);
			// Paragraphs (double newlines)
			$html = preg_replace('/\n\n/', '<br><br>', $html);

			return $html;
		}

		return '<p>' . esc_html__('No recent changes found.', 'privacy-analytics-lite') . '</p>';
	}

	/**
	 * Get summary statistics.
	 *
	 * @return array<string, int> Summary stats.
	 */
	public function ajax_get_stats(): void
	{
		// Check capability.
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Insufficient permissions');
		}

		// Get date range from request.
		$date_start = isset($_GET['date_start']) ? sanitize_text_field(wp_unslash($_GET['date_start'])) : '';
		$date_end = isset($_GET['date_end']) ? sanitize_text_field(wp_unslash($_GET['date_end'])) : '';

		// Validate dates.
		if (!$date_start || !$date_end) {
			// Fallback to last 30 days.
			$date_end = current_time('Y-m-d');
			$date_start = date('Y-m-d', strtotime('-29 days', strtotime($date_end)));
		}

		$summary_stats = $this->get_summary_stats($date_start, $date_end);
		$daily_trends = $this->get_daily_trends($date_start, $date_end);
		$hourly_stats = $this->get_hourly_stats();
		$top_pages = $this->get_top_pages($date_start, $date_end);
		$referrer_stats = $this->get_referrer_stats($date_start, $date_end);
		$device_stats = $this->get_device_stats($date_start, $date_end);
		$os_stats = $this->get_os_stats($date_start, $date_end);

		wp_send_json_success(array(
			'summary_stats' => $summary_stats,
			'daily_trends' => $daily_trends,
			'hourly_stats' => $hourly_stats,
			'top_pages' => $top_pages,
			'referrer_stats' => $referrer_stats,
			'device_stats' => $device_stats,
			'os_stats' => $os_stats,
		));
	}

	/**
	 * Get summary statistics.
	 *
	 * @return array<string, int> Summary stats.
	 */
	private function get_summary_stats(string $start_date, string $end_date): array
	{
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();
		$hits_table = $this->table_manager->get_hits_table_name();

		// Get aggregated stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$aggregated = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					SUM(hit_count) as total_hits,
					SUM(unique_visitors) as unique_visitors
				FROM {$stats_table}
				WHERE stat_date BETWEEN %s AND %s",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Get raw hits stats (real-time).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(*) as total_hits,
					COUNT(DISTINCT visitor_hash) as unique_visitors
				FROM {$hits_table}
				WHERE hit_date BETWEEN %s AND %s",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$total_hits = absint($aggregated['total_hits'] ?? 0) + absint($raw['total_hits'] ?? 0);
		$unique_visitors = absint($aggregated['unique_visitors'] ?? 0) + absint($raw['unique_visitors'] ?? 0);

		return array(
			'total_hits' => $total_hits,
			'unique_visitors' => $unique_visitors,
		);
	}

	/**
	 * Get daily trends data for chart.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array<string, array<int|string, mixed>> Chart data.
	 */
	private function get_daily_trends(string $start_date, string $end_date): array
	{
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();
		$hits_table = $this->table_manager->get_hits_table_name();

		// Get aggregated daily stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$aggregated = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					stat_date,
					SUM(hit_count) as total_hits,
					SUM(unique_visitors) as total_visitors
				FROM {$stats_table}
				WHERE stat_date BETWEEN %s AND %s
				GROUP BY stat_date
				ORDER BY stat_date ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Get raw hits daily stats.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DATE(hit_date) as stat_date,
					COUNT(*) as total_hits,
					COUNT(DISTINCT visitor_hash) as total_visitors
				FROM {$hits_table}
				WHERE hit_date BETWEEN %s AND %s
				GROUP BY DATE(hit_date)
				ORDER BY stat_date ASC",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		// Merge data.
		$merged = array();

		// Process aggregated.
		if (is_array($aggregated)) {
			foreach ($aggregated as $row) {
				$date = $row['stat_date'];
				$merged[$date] = array(
					'hits' => absint($row['total_hits'] ?? 0),
					'visitors' => absint($row['total_visitors'] ?? 0),
				);
			}
		}

		// Process raw.
		if (is_array($raw)) {
			foreach ($raw as $row) {
				$date = $row['stat_date'];
				if (!isset($merged[$date])) {
					$merged[$date] = array('hits' => 0, 'visitors' => 0);
				}
				$merged[$date]['hits'] += absint($row['total_hits'] ?? 0);
				// Note: Simply adding visitors is an approximation as assumed in design.
				$merged[$date]['visitors'] += absint($row['total_visitors'] ?? 0);
			}
		}

		// Sort by date.
		ksort($merged);

		$labels = array();
		$hits_values = array();
		$visitor_values = array();

		foreach ($merged as $date => $data) {
			$labels[] = date('M j', strtotime($date));
			$hits_values[] = $data['hits'];
			$visitor_values[] = $data['visitors'];
		}

		return array(
			'labels' => $labels,
			'datasets' => array(
				array(
					'name' => __('Page Views', 'privacy-analytics-lite'),
					'values' => $hits_values,
				),
				array(
					'name' => __('Unique Visitors', 'privacy-analytics-lite'),
					'values' => $visitor_values,
				),
			),
		);
	}



	/**
	 * Get hourly stats for the last 24 hours.
	 *
	 * @return array<string, array<int|string, mixed>> Chart data.
	 */
	private function get_hourly_stats(): array
	{
		global $wpdb;

		$hits_table = $this->table_manager->get_hits_table_name();

		// Get hits for last 24 hours.
		$end_time = current_time('mysql');
		$start_time = date('Y-m-d H:i:s', strtotime('-24 hours', strtotime($end_time)));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DATE_FORMAT(hit_date, '%%H:00') as hour_label,
					COUNT(*) as hits
				FROM {$hits_table}
				WHERE hit_date >= %s
				GROUP BY DATE_FORMAT(hit_date, '%%Y-%%m-%%d %%H')
				ORDER BY hit_date ASC",
				$start_time
			),
			ARRAY_A
		);

		$data_map = array();
		if (is_array($results)) {
			foreach ($results as $row) {
				$data_map[$row['hour_label']] = absint($row['hits']);
			}
		}

		// Fill in missing hours.
		$labels = array();
		$values = array();
		// We want to show the next 24 hours from start.
		for ($i = 0; $i <= 23; $i++) {
			$timestamp = strtotime("+{$i} hours", strtotime($start_time));
			// Round to hour
			$label = date('H:00', $timestamp);

			$labels[] = $label;
			$values[] = $data_map[$label] ?? 0;
		}

		return array(
			'labels' => $labels,
			'datasets' => array(
				array(
					'name' => __('Hourly Hits', 'privacy-analytics-lite'),
					'values' => $values,
				),
			),
		);
	}

	/**
	 * Get top pages data.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array<string, array<int, array<string, mixed>>> Top pages data.
	 */
	private function get_top_pages(string $start_date, string $end_date): array
	{
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();
		$hits_table = $this->table_manager->get_hits_table_name();

		// Aggregated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$aggregated = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					page_path,
					SUM(hit_count) as total_hits,
					SUM(unique_visitors) as total_visitors
				FROM {$stats_table}
				WHERE stat_date BETWEEN %s AND %s
				GROUP BY page_path",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Raw.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					page_path,
					COUNT(*) as total_hits,
					COUNT(DISTINCT visitor_hash) as total_visitors
				FROM {$hits_table}
				WHERE hit_date BETWEEN %s AND %s
				GROUP BY page_path",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$merged = array();

		if (is_array($aggregated)) {
			foreach ($aggregated as $row) {
				$path = $row['page_path'];
				$merged[$path] = array(
					'hits' => absint($row['total_hits'] ?? 0),
					'visitors' => absint($row['total_visitors'] ?? 0),
				);
			}
		}

		if (is_array($raw)) {
			foreach ($raw as $row) {
				$path = $row['page_path'];
				if (!isset($merged[$path])) {
					$merged[$path] = array('hits' => 0, 'visitors' => 0);
				}
				$merged[$path]['hits'] += absint($row['total_hits'] ?? 0);
				$merged[$path]['visitors'] += absint($row['total_visitors'] ?? 0);
			}
		}

		// Sort by hits descending.
		uasort($merged, function ($a, $b) {
			return $b['hits'] <=> $a['hits'];
		});

		// Limit to top 10.
		$merged = array_slice($merged, 0, 10);

		$labels = array();
		$values = array();
		$table_data = array();

		foreach ($merged as $path => $data) {
			$display_path = strlen($path) > 30 ? substr($path, 0, 30) . '...' : $path;

			$labels[] = $display_path;
			$values[] = $data['hits'];

			$table_data[] = array(
				'page_path' => $path,
				'total_hits' => $data['hits'],
				'total_visitors' => $data['visitors'],
			);
		}

		return array(
			'chart_data' => array(
				'labels' => $labels,
				'datasets' => array(
					array(
						'name' => __('Hits', 'privacy-analytics-lite'),
						'values' => $values,
					),
				),
			),
			'table_data' => $table_data,
		);
	}

	/**
	 * Get referrer statistics.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array<string, array<int, array<string, mixed>>> Referrer stats data.
	 */
	private function get_referrer_stats(string $start_date, string $end_date): array
	{
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();
		$hits_table = $this->table_manager->get_hits_table_name();

		// Aggregated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$aggregated = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					COALESCE(referrer, 'Direct') as source,
					SUM(hit_count) as total_hits,
					SUM(unique_visitors) as total_visitors
				FROM {$stats_table}
				WHERE stat_date BETWEEN %s AND %s
				GROUP BY referrer",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Raw.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					COALESCE(referrer, 'Direct') as source,
					COUNT(*) as total_hits,
					COUNT(DISTINCT visitor_hash) as total_visitors
				FROM {$hits_table}
				WHERE hit_date BETWEEN %s AND %s
				GROUP BY referrer",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$merged = array();

		if (is_array($aggregated)) {
			foreach ($aggregated as $row) {
				$source = $row['source'];
				// Handle empty/null in DB vs COALESCE
				if (empty($source)) {
					$source = 'Direct';
				}

				$merged[$source] = array(
					'hits' => absint($row['total_hits'] ?? 0),
					'visitors' => absint($row['total_visitors'] ?? 0),
				);
			}
		}

		if (is_array($raw)) {
			foreach ($raw as $row) {
				$source = $row['source'];
				if (empty($source)) {
					$source = 'Direct';
				}

				if (!isset($merged[$source])) {
					$merged[$source] = array('hits' => 0, 'visitors' => 0);
				}
				$merged[$source]['hits'] += absint($row['total_hits'] ?? 0);
				$merged[$source]['visitors'] += absint($row['total_visitors'] ?? 0);
			}
		}

		// Sort by hits.
		uasort($merged, function ($a, $b) {
			return $b['hits'] <=> $a['hits'];
		});

		// Limit to top 10 (optional but good for performance/UI).
		$merged = array_slice($merged, 0, 10);

		$labels = array();
		$values = array();
		$table_data = array();

		foreach ($merged as $source => $data) {
			$labels[] = $source;
			$values[] = $data['hits'];

			$table_data[] = array(
				'source' => $source,
				'total_hits' => $data['hits'],
				'total_visitors' => $data['visitors'],
			);
		}

		return array(
			'chart_data' => array(
				'labels' => $labels,
				'datasets' => array(
					array(
						'name' => __('Hits', 'privacy-analytics-lite'),
						'values' => $values,
					),
				),
			),
			'table_data' => $table_data,
		);
	}

	/**
	 * Render top pages table.
	 *
	 * @param array<int, array<string, mixed>> $data Table data.
	 * @return void
	 */
	private function render_top_pages_table(array $data): void
	{
		if (empty($data)) {
			echo '<p>' . esc_html__('No data available.', 'privacy-analytics-lite') . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__('Page Path', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Hits', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Unique Visitors', 'privacy-analytics-lite'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($data as $row): ?>
					<tr>
						<td><?php echo esc_html($row['page_path'] ?? ''); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['total_hits'] ?? 0)); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['total_visitors'] ?? 0)); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render referrer table.
	 *
	 * @param array<int, array<string, mixed>> $data Table data.
	 * @return void
	 */
	private function render_referrer_table(array $data): void
	{
		if (empty($data)) {
			echo '<p>' . esc_html__('No data available.', 'privacy-analytics-lite') . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__('Source', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Hits', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Unique Visitors', 'privacy-analytics-lite'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($data as $row): ?>
					<tr>
						<td><?php echo esc_html($row['source'] ?? 'Direct'); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['total_hits'] ?? 0)); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['total_visitors'] ?? 0)); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Display update notice if transient is set.
	 *
	 * @return void
	 */
	public function maybe_display_update_notice(): void
	{
		$new_version = get_transient('pa_lite_updated');
		if (!$new_version) {
			return;
		}

		// Don't delete yet, we need it for the dashboard flag.
		// It will expires in 60s anyway or be cleared below.

		?>
		<div class="pa-premium-notice notice">
			<div class="pa-notice-content">
				<div class="pa-notice-icon">
					<span class="dashicons dashicons-sparkles"></span>
				</div>
				<div class="pa-notice-text">
					<h3><?php echo esc_html(sprintf(__('Updated to version %s!', 'privacy-analytics-lite'), $new_version)); ?>
					</h3>
					<p><?php echo esc_html__('Welcome back! We\'ve polished your analytics dashboard.', 'privacy-analytics-lite'); ?>
					</p>
				</div>
			</div>
			<div class="pa-notice-actions">
				<button type="button" id="pa-whats-new-btn" class="pa-btn-premium">
					<?php echo esc_html__('What\'s New?', 'privacy-analytics-lite'); ?>
				</button>
			</div>
		</div>

		<?php $this->render_update_modal($new_version); ?>

		<?php
		// Clear it now that we've rendered the notice components.
		delete_transient('pa_lite_updated');
	}

	/**
	 * Render the update changelog modal.
	 *
	 * @param string $version New version.
	 * @return void
	 */
	private function render_update_modal(string $version): void
	{
		$changelog = $this->get_latest_changelog_notes($version);
		?>
		<div class="pa-modal-overlay">
			<div class="pa-modal">
				<div class="pa-modal-header">
					<h2><?php echo esc_html(sprintf(__('Version %s Release Notes', 'privacy-analytics-lite'), $version)); ?>
					</h2>
				</div>
				<div class="pa-modal-body">
					<?php if ($changelog): ?>
						<div class="pa-changelog-content">
							<?php echo wp_kses_post($changelog); ?>
						</div>
					<?php else: ?>
						<p><?php echo esc_html__('No release notes found for this version.', 'privacy-analytics-lite'); ?></p>
					<?php endif; ?>
				</div>
				<div class="pa-modal-footer">
					<button type="button" id="pa-modal-close" class="pa-btn-premium">
						<?php echo esc_html__('Got it, let\'s go!', 'privacy-analytics-lite'); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Parse the CHANGELOG.md file for specific version notes.
	 *
	 * @param string $version Target version.
	 * @return string|false HTML notes or false if not found.
	 */
	private function get_latest_changelog_notes(string $version): string|bool
	{
		$changelog_file = PRIVACY_ANALYTICS_LITE_PLUGIN_DIR . 'CHANGELOG.md';
		if (!file_exists($changelog_file)) {
			return false;
		}

		$content = file_get_contents($changelog_file);
		if (!$content) {
			return false;
		}

		// Look for the section for the given version.
		// Format: ## [1.3.2] - 2025-12-26
		$pattern = '/## \[' . preg_quote($version, '/') . '\] - .*?\n(.*?)(?=## \[|\z)/s';
		if (preg_match($pattern, $content, $matches)) {
			// Convert basic markdown to HTML (simple replacement for lists and headers)
			$notes = $matches[1];
			$notes = preg_replace('/### (.*?)\n/', '<h3>$1</h3>', $notes);
			$notes = preg_replace('/- (.*?)\n/', '<li>$1</li>', $notes);
			$notes = preg_replace('/(<li>.*?<\/li>)+/s', '<ul>$0</ul>', $notes);
			return $notes;
		}

		return false;
	}

	/**
	 * Get device statistics.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array<string, array<int, array<string, mixed>>> Device stats data.
	 */
	private function get_device_stats(string $start_date, string $end_date): array
	{
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();
		$hits_table = $this->table_manager->get_hits_table_name();

		// Aggregated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$aggregated = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					device_type,
					SUM(hit_count) as total_hits,
					SUM(unique_visitors) as total_visitors
				FROM {$stats_table}
				WHERE stat_date BETWEEN %s AND %s
				GROUP BY device_type",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Raw.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					device_type,
					COUNT(*) as total_hits,
					COUNT(DISTINCT visitor_hash) as total_visitors
				FROM {$hits_table}
				WHERE hit_date BETWEEN %s AND %s
				GROUP BY device_type",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$merged = array();
		// Initial merge logic same as others...
		// Simplified for brevity in this response, utilizing the same pattern:
		if (is_array($aggregated)) {
			foreach ($aggregated as $row) {
				$key = $row['device_type'] ?: 'Unknown';
				$merged[$key] = array(
					'hits' => absint($row['total_hits'] ?? 0),
					'visitors' => absint($row['total_visitors'] ?? 0),
				);
			}
		}

		if (is_array($raw)) {
			foreach ($raw as $row) {
				$key = $row['device_type'] ?: 'Unknown';
				if (!isset($merged[$key])) {
					$merged[$key] = array('hits' => 0, 'visitors' => 0);
				}
				$merged[$key]['hits'] += absint($row['total_hits'] ?? 0);
				$merged[$key]['visitors'] += absint($row['total_visitors'] ?? 0);
			}
		}

		uasort($merged, function ($a, $b) {
			return $b['hits'] <=> $a['hits'];
		});

		$labels = array();
		$values = array();
		$table_data = array();

		foreach ($merged as $type => $data) {
			$labels[] = $type;
			$values[] = $data['hits'];
			$table_data[] = array('type' => $type, 'hits' => $data['hits'], 'visitors' => $data['visitors']);
		}

		return array(
			'chart_data' => array('labels' => $labels, 'datasets' => array(array('name' => 'Hits', 'values' => $values))),
			'table_data' => $table_data,
		);
	}

	/**
	 * Get OS statistics.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array<string, array<int, array<string, mixed>>> OS stats data.
	 */
	private function get_os_stats(string $start_date, string $end_date): array
	{
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();
		$hits_table = $this->table_manager->get_hits_table_name();

		// Aggregated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$aggregated = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					os,
					SUM(hit_count) as total_hits,
					SUM(unique_visitors) as total_visitors
				FROM {$stats_table}
				WHERE stat_date BETWEEN %s AND %s
				GROUP BY os",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Raw.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					os,
					COUNT(*) as total_hits,
					COUNT(DISTINCT visitor_hash) as total_visitors
				FROM {$hits_table}
				WHERE hit_date BETWEEN %s AND %s
				GROUP BY os",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			),
			ARRAY_A
		);

		$merged = array();
		if (is_array($aggregated)) {
			foreach ($aggregated as $row) {
				$key = $row['os'] ?: 'Unknown';
				$merged[$key] = array(
					'hits' => absint($row['total_hits'] ?? 0),
					'visitors' => absint($row['total_visitors'] ?? 0),
				);
			}
		}

		if (is_array($raw)) {
			foreach ($raw as $row) {
				$key = $row['os'] ?: 'Unknown';
				if (!isset($merged[$key])) {
					$merged[$key] = array('hits' => 0, 'visitors' => 0);
				}
				$merged[$key]['hits'] += absint($row['total_hits'] ?? 0);
				$merged[$key]['visitors'] += absint($row['total_visitors'] ?? 0);
			}
		}

		uasort($merged, function ($a, $b) {
			return $b['hits'] <=> $a['hits'];
		});

		$labels = array();
		$values = array();
		$table_data = array();

		foreach ($merged as $os => $data) {
			$labels[] = $os;
			$values[] = $data['hits'];
			$table_data[] = array('os' => $os, 'hits' => $data['hits'], 'visitors' => $data['visitors']);
		}

		return array(
			'chart_data' => array('labels' => $labels, 'datasets' => array(array('name' => 'Hits', 'values' => $values))),
			'table_data' => $table_data,
		);
	}

	/**
	 * Render device table.
	 */
	private function render_device_table(array $data): void
	{
		if (empty($data)) {
			echo '<p>' . esc_html__('No data available.', 'privacy-analytics-lite') . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__('Device Type', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Hits', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Visitors', 'privacy-analytics-lite'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($data as $row): ?>
					<tr>
						<td><?php echo esc_html($row['type']); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['hits'])); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['visitors'])); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render OS table.
	 */
	private function render_os_table(array $data): void
	{
		if (empty($data)) {
			echo '<p>' . esc_html__('No data available.', 'privacy-analytics-lite') . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__('Operating System', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Hits', 'privacy-analytics-lite'); ?></th>
					<th><?php echo esc_html__('Visitors', 'privacy-analytics-lite'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($data as $row): ?>
					<tr>
						<td><?php echo esc_html($row['os']); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['hits'])); ?></td>
						<td><?php echo esc_html(number_format_i18n($row['visitors'])); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

