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
class Dashboard {

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
	public function __construct( TableManager $table_manager, string $version ) {
		$this->table_manager = $table_manager;
		$this->version        = $version;
	}

	/**
	 * Initialize dashboard.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add admin menu item.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Privacy Analytics', 'privacy-analytics-lite' ),
			__( 'Privacy Analytics', 'privacy-analytics-lite' ),
			'manage_options',
			'privacy-analytics-lite',
			array( $this, 'render_dashboard' ),
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
	public function enqueue_assets( string $hook ): void {
		// Only load on our admin page.
		if ( 'toplevel_page_privacy-analytics-lite' !== $hook ) {
			return;
		}

		// Enqueue Frappe Charts from CDN.
		wp_enqueue_script(
			'frappe-charts',
			'https://cdn.jsdelivr.net/npm/frappe-charts@1.7.1/dist/frappe-charts.min.iife.js',
			array(),
			'1.7.1',
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
			array( 'frappe-charts' ),
			$this->version,
			true
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'privacy-analytics-lite' ) );
		}

		// Get stats data.
		$summary_stats = $this->get_summary_stats();
		$daily_trends  = $this->get_daily_trends();
		$top_pages     = $this->get_top_pages();
		$referrer_stats = $this->get_referrer_stats();

		?>
		<div class="wrap privacy-analytics-dashboard">
			<h1><?php echo esc_html__( 'Privacy Analytics', 'privacy-analytics-lite' ); ?></h1>

			<!-- Summary Stats Cards -->
			<div class="pa-stats-grid">
				<div class="pa-stat-card">
					<div class="pa-stat-label"><?php echo esc_html__( 'Total Hits', 'privacy-analytics-lite' ); ?></div>
					<div class="pa-stat-value"><?php echo esc_html( number_format_i18n( $summary_stats['total_hits'] ) ); ?></div>
					<div class="pa-stat-period"><?php echo esc_html__( 'Last 30 days', 'privacy-analytics-lite' ); ?></div>
				</div>
				<div class="pa-stat-card">
					<div class="pa-stat-label"><?php echo esc_html__( 'Unique Visitors', 'privacy-analytics-lite' ); ?></div>
					<div class="pa-stat-value"><?php echo esc_html( number_format_i18n( $summary_stats['unique_visitors'] ) ); ?></div>
					<div class="pa-stat-period"><?php echo esc_html__( 'Last 30 days', 'privacy-analytics-lite' ); ?></div>
				</div>
				<div class="pa-stat-card">
					<div class="pa-stat-label"><?php echo esc_html__( 'Top Pages', 'privacy-analytics-lite' ); ?></div>
					<div class="pa-stat-value"><?php echo esc_html( number_format_i18n( count( $top_pages ) ) ); ?></div>
					<div class="pa-stat-period"><?php echo esc_html__( 'Tracked', 'privacy-analytics-lite' ); ?></div>
				</div>
			</div>

			<!-- Daily Trends Chart -->
			<div class="pa-chart-container">
				<h2><?php echo esc_html__( 'Daily Trends', 'privacy-analytics-lite' ); ?></h2>
				<div id="pa-daily-trends-chart" class="pa-chart" data-chart-data="<?php echo esc_attr( wp_json_encode( $daily_trends ) ); ?>"></div>
			</div>

			<!-- Charts Grid -->
			<div class="pa-charts-grid">
				<!-- Top Pages Chart -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__( 'Top Pages', 'privacy-analytics-lite' ); ?></h2>
					<div id="pa-top-pages-chart" class="pa-chart" data-chart-data="<?php echo esc_attr( wp_json_encode( $top_pages['chart_data'] ) ); ?>"></div>
				</div>

				<!-- Referral Sources Chart -->
				<div class="pa-chart-container">
					<h2><?php echo esc_html__( 'Referral Sources', 'privacy-analytics-lite' ); ?></h2>
					<div id="pa-referrer-chart" class="pa-chart" data-chart-data="<?php echo esc_attr( wp_json_encode( $referrer_stats['chart_data'] ) ); ?>"></div>
				</div>
			</div>

			<!-- Tables -->
			<div class="pa-tables-grid">
				<!-- Top Pages Table -->
				<div class="pa-table-container">
					<h2><?php echo esc_html__( 'Top Pages', 'privacy-analytics-lite' ); ?></h2>
					<?php $this->render_top_pages_table( $top_pages['table_data'] ); ?>
				</div>

				<!-- Referrer Sources Table -->
				<div class="pa-table-container">
					<h2><?php echo esc_html__( 'Referral Sources', 'privacy-analytics-lite' ); ?></h2>
					<?php $this->render_referrer_table( $referrer_stats['table_data'] ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get summary statistics.
	 *
	 * @return array<string, int> Summary stats.
	 */
	private function get_summary_stats(): array {
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_row(
			"SELECT 
				SUM(hit_count) as total_hits,
				SUM(unique_visitors) as unique_visitors
			FROM {$stats_table}
			WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array(
				'total_hits'      => 0,
				'unique_visitors' => 0,
			);
		}

		return array(
			'total_hits'      => absint( $results['total_hits'] ?? 0 ),
			'unique_visitors' => absint( $results['unique_visitors'] ?? 0 ),
		);
	}

	/**
	 * Get daily trends data for chart.
	 *
	 * @return array<string, array<int|string, mixed>> Chart data.
	 */
	private function get_daily_trends(): array {
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT 
				stat_date,
				SUM(hit_count) as total_hits,
				SUM(unique_visitors) as total_visitors
			FROM {$stats_table}
			WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			GROUP BY stat_date
			ORDER BY stat_date ASC",
			ARRAY_A
		);

		if ( ! is_array( $results ) || empty( $results ) ) {
			return array(
				'labels'   => array(),
				'datasets' => array(),
			);
		}

		$labels        = array();
		$hits_values   = array();
		$visitor_values = array();

		foreach ( $results as $row ) {
			$labels[]        = date( 'M j', strtotime( $row['stat_date'] ) );
			$hits_values[]   = absint( $row['total_hits'] ?? 0 );
			$visitor_values[] = absint( $row['total_visitors'] ?? 0 );
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'name'   => __( 'Page Views', 'privacy-analytics-lite' ),
					'values' => $hits_values,
				),
				array(
					'name'   => __( 'Unique Visitors', 'privacy-analytics-lite' ),
					'values' => $visitor_values,
				),
			),
		);
	}

	/**
	 * Get top pages data.
	 *
	 * @return array<string, array<int, array<string, mixed>>> Top pages data.
	 */
	private function get_top_pages(): array {
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT 
				page_path,
				SUM(hit_count) as total_hits,
				SUM(unique_visitors) as total_visitors
			FROM {$stats_table}
			WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			GROUP BY page_path
			ORDER BY total_hits DESC
			LIMIT 10",
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			$results = array();
		}

		$labels  = array();
		$values  = array();
		$table_data = array();

		foreach ( $results as $row ) {
			$page_path = $row['page_path'] ?? '';
			$hits      = absint( $row['total_hits'] ?? 0 );
			$visitors  = absint( $row['total_visitors'] ?? 0 );

			// Truncate long page paths for chart.
			$display_path = strlen( $page_path ) > 30 ? substr( $page_path, 0, 30 ) . '...' : $page_path;

			$labels[] = $display_path;
			$values[] = $hits;

			$table_data[] = array(
				'page_path'      => $page_path,
				'total_hits'     => $hits,
				'total_visitors' => $visitors,
			);
		}

		return array(
			'chart_data' => array(
				'labels'   => $labels,
				'datasets' => array(
					array(
						'name'   => __( 'Hits', 'privacy-analytics-lite' ),
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
	 * @return array<string, array<int, array<string, mixed>>> Referrer stats data.
	 */
	private function get_referrer_stats(): array {
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT 
				COALESCE(referrer, 'Direct') as source,
				SUM(hit_count) as total_hits,
				SUM(unique_visitors) as total_visitors
			FROM {$stats_table}
			WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
			GROUP BY referrer
			ORDER BY total_hits DESC",
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			$results = array();
		}

		$labels    = array();
		$values    = array();
		$table_data = array();

		foreach ( $results as $row ) {
			$source   = $row['source'] ?? 'Direct';
			$hits     = absint( $row['total_hits'] ?? 0 );
			$visitors = absint( $row['total_visitors'] ?? 0 );

			$labels[] = $source;
			$values[] = $hits;

			$table_data[] = array(
				'source'          => $source,
				'total_hits'     => $hits,
				'total_visitors' => $visitors,
			);
		}

		return array(
			'chart_data' => array(
				'labels'   => $labels,
				'datasets' => array(
					array(
						'name'   => __( 'Hits', 'privacy-analytics-lite' ),
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
	private function render_top_pages_table( array $data ): void {
		if ( empty( $data ) ) {
			echo '<p>' . esc_html__( 'No data available.', 'privacy-analytics-lite' ) . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Page Path', 'privacy-analytics-lite' ); ?></th>
					<th><?php echo esc_html__( 'Hits', 'privacy-analytics-lite' ); ?></th>
					<th><?php echo esc_html__( 'Unique Visitors', 'privacy-analytics-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['page_path'] ?? '' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['total_hits'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['total_visitors'] ?? 0 ) ); ?></td>
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
	private function render_referrer_table( array $data ): void {
		if ( empty( $data ) ) {
			echo '<p>' . esc_html__( 'No data available.', 'privacy-analytics-lite' ) . '</p>';
			return;
		}

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Source', 'privacy-analytics-lite' ); ?></th>
					<th><?php echo esc_html__( 'Hits', 'privacy-analytics-lite' ); ?></th>
					<th><?php echo esc_html__( 'Unique Visitors', 'privacy-analytics-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['source'] ?? 'Direct' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['total_hits'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['total_visitors'] ?? 0 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

