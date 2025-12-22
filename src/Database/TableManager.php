<?php
/**
 * Database table management.
 *
 * @package PrivacyAnalytics\Lite\Database
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Database;

/**
 * Manages database tables for the plugin.
 */
class TableManager {

	/**
	 * Create all plugin tables.
	 *
	 * @return void
	 */
	public function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Create pa_hits_temp table for raw hits.
		$hits_table = $this->get_hits_table_name();
		$hits_sql   = "CREATE TABLE {$hits_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_hash varchar(64) NOT NULL,
			page_path varchar(255) NOT NULL,
			referrer varchar(255) DEFAULT NULL,
			user_agent_hash varchar(64) NOT NULL,
			hit_date datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY hit_date (hit_date),
			KEY visitor_hash (visitor_hash)
		) {$charset_collate};";

		dbDelta( $hits_sql );

		// Create pa_daily_stats table for aggregated statistics.
		$stats_table = $this->get_stats_table_name();
		$stats_sql   = "CREATE TABLE {$stats_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			stat_date date NOT NULL,
			page_path varchar(255) NOT NULL,
			referrer varchar(255) DEFAULT NULL,
			hit_count int(11) unsigned NOT NULL DEFAULT 0,
			unique_visitors int(11) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY stat_date (stat_date),
			KEY page_path (page_path)
		) {$charset_collate};";

		dbDelta( $stats_sql );
	}

	/**
	 * Drop all plugin tables.
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		global $wpdb;

		$hits_table  = $this->get_hits_table_name();
		$stats_table = $this->get_stats_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$hits_table}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$stats_table}" );
	}

	/**
	 * Get the hits temporary table name.
	 *
	 * @return string
	 */
	public function get_hits_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'pa_hits_temp';
	}

	/**
	 * Get the daily stats table name.
	 *
	 * @return string
	 */
	public function get_stats_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'pa_daily_stats';
	}
}

