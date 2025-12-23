<?php
/**
 * Data aggregation for analytics statistics.
 *
 * @package PrivacyAnalytics\Lite\Database
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Database;

/**
 * Aggregates raw hits into daily statistics.
 */
class Aggregator
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
	 * Main aggregation method called by cron.
	 *
	 * @return void
	 */
	public function aggregate_hits(): void
	{
		$hits = $this->get_hits_to_aggregate();

		if (empty($hits)) {
			// No hits to process.
			return;
		}

		$stats = $this->calculate_aggregated_stats($hits);

		if (empty($stats)) {
			// No stats to save.
			return;
		}

		$success = $this->save_aggregated_stats($stats);

		if ($success) {
			$this->prune_raw_hits();
		} else {
			error_log('Privacy Analytics Lite: Failed to save aggregated stats, keeping raw hits.');
		}
	}

	/**
	 * Retrieve hits from pa_hits_temp table.
	 *
	 * @return array<int, array<string, mixed>> Array of hit records.
	 */
	private function get_hits_to_aggregate(): array
	{
		global $wpdb;

		$hits_table = $this->table_manager->get_hits_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT visitor_hash, page_path, referrer, hit_date FROM {$hits_table}",
			ARRAY_A
		);

		if (!is_array($results)) {
			return array();
		}

		return $results;
	}

	/**
	 * Calculate aggregated statistics from hits.
	 *
	 * @param array<int, array<string, mixed>> $hits Array of hit records.
	 * @return array<int, array<string, mixed>> Array of aggregated statistics.
	 */
	private function calculate_aggregated_stats(array $hits): array
	{
		$grouped = array();

		foreach ($hits as $hit) {
			// Extract date from hit_date.
			$hit_date = $hit['hit_date'] ?? '';
			if (empty($hit_date)) {
				continue;
			}

			// Convert datetime to date (YYYY-MM-DD).
			$stat_date = date('Y-m-d', strtotime($hit_date));
			if (false === $stat_date) {
				continue;
			}

			$page_path = $hit['page_path'] ?? '';
			$referrer = $hit['referrer'] ?? null;

			// Create group key.
			$key = $this->get_group_key($stat_date, $page_path, $referrer);

			if (!isset($grouped[$key])) {
				$grouped[$key] = array(
					'stat_date' => $stat_date,
					'page_path' => $page_path,
					'referrer' => $referrer,
					'hit_count' => 0,
					'unique_visitors' => array(),
				);
			}

			// Increment hit count.
			++$grouped[$key]['hit_count'];

			// Track unique visitors.
			$visitor_hash = $hit['visitor_hash'] ?? '';
			if (!empty($visitor_hash)) {
				$grouped[$key]['unique_visitors'][$visitor_hash] = true;
			}
		}

		// Convert to final stats format.
		$stats = array();
		foreach ($grouped as $group) {
			$stats[] = array(
				'stat_date' => $group['stat_date'],
				'page_path' => $group['page_path'],
				'referrer' => $group['referrer'],
				'hit_count' => $group['hit_count'],
				'unique_visitors' => count($group['unique_visitors']),
			);
		}

		return $stats;
	}

	/**
	 * Get group key for aggregation.
	 *
	 * @param string      $stat_date Date in YYYY-MM-DD format.
	 * @param string      $page_path Page path.
	 * @param string|null $referrer  Referrer or null.
	 * @return string Group key.
	 */
	private function get_group_key(string $stat_date, string $page_path, ?string $referrer): string
	{
		$referrer_key = $referrer ?? 'direct';
		return hash('sha256', $stat_date . '|' . $page_path . '|' . $referrer_key);
	}

	/**
	 * Save aggregated statistics to pa_daily_stats table.
	 *
	 * @param array<int, array<string, mixed>> $stats Array of aggregated statistics.
	 * @return bool True on success, false on failure.
	 */
	private function save_aggregated_stats(array $stats): bool
	{
		global $wpdb;

		$stats_table = $this->table_manager->get_stats_table_name();
		$success = true;

		foreach ($stats as $stat) {
			$stat_date = $stat['stat_date'] ?? '';
			$page_path = $stat['page_path'] ?? '';
			$referrer = $stat['referrer'] ?? null;
			$hit_count = absint($stat['hit_count'] ?? 0);
			$unique_visitors = absint($stat['unique_visitors'] ?? 0);

			// Check if record exists.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$stats_table} WHERE stat_date = %s AND page_path = %s AND (referrer = %s OR (referrer IS NULL AND %s IS NULL))",
					$stat_date,
					$page_path,
					$referrer,
					$referrer
				)
			);

			if ($existing) {
				// Update existing record - add new counts to existing.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$stats_table} SET hit_count = hit_count + %d, unique_visitors = GREATEST(unique_visitors, %d) WHERE id = %d",
						$hit_count,
						$unique_visitors,
						absint($existing)
					)
				);

				if (false === $result) {
					$success = false;
					error_log('Privacy Analytics Lite: Failed to update aggregated stat - ' . $wpdb->last_error);
				}
			} else {
				// Insert new record.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->insert(
					$stats_table,
					array(
						'stat_date' => $stat_date,
						'page_path' => $page_path,
						'referrer' => $referrer,
						'hit_count' => $hit_count,
						'unique_visitors' => $unique_visitors,
					),
					array('%s', '%s', '%s', '%d', '%d')
				);

				if (false === $result) {
					$success = false;
					error_log('Privacy Analytics Lite: Failed to insert aggregated stat - ' . $wpdb->last_error);
				}
			}
		}

		return $success;
	}

	/**
	 * Truncate pa_hits_temp table after successful aggregation.
	 *
	 * @return void
	 */
	private function prune_raw_hits(): void
	{
		global $wpdb;

		$hits_table = $this->table_manager->get_hits_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query("TRUNCATE TABLE {$hits_table}");
	}
}

