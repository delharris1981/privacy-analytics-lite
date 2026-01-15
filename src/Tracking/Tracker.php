<?php
/**
 * Main tracking class for server-side analytics.
 *
 * @package PrivacyAnalytics\Lite\Tracking
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Tracking;

use PrivacyAnalytics\Lite\Database\TableManager;
use PrivacyAnalytics\Lite\Models\Hit;

/**
 * Handles server-side page view tracking.
 */
class Tracker
{

	/**
	 * Anonymizer instance.
	 *
	 * @var Anonymizer
	 */
	private Anonymizer $anonymizer;

	/**
	 * Bot detector instance.
	 *
	 * @var BotDetector
	 */
	private BotDetector $bot_detector;

	/**
	 * Referrer normalizer instance.
	 *
	 * @var ReferrerNormalizer
	 */
	private ReferrerNormalizer $referrer_normalizer;

	/**
	 * Device detector instance.
	 *
	 * @var DeviceDetector
	 */
	private DeviceDetector $device_detector;

	/**
	 * Table manager instance.
	 *
	 * @var TableManager
	 */
	private TableManager $table_manager;

	/**
	 * Constructor.
	 *
	 * @param Anonymizer         $anonymizer          Anonymizer instance.
	 * @param BotDetector        $bot_detector        Bot detector instance.
	 * @param ReferrerNormalizer $referrer_normalizer Referrer normalizer instance.
	 * @param DeviceDetector     $device_detector     Device detector instance.
	 * @param TableManager       $table_manager        Table manager instance.
	 */
	public function __construct(
		Anonymizer $anonymizer,
		BotDetector $bot_detector,
		ReferrerNormalizer $referrer_normalizer,
		DeviceDetector $device_detector,
		TableManager $table_manager
	) {
		$this->anonymizer = $anonymizer;
		$this->bot_detector = $bot_detector;
		$this->referrer_normalizer = $referrer_normalizer;
		$this->device_detector = $device_detector;
		$this->table_manager = $table_manager;
	}

	/**
	 * Track page hit (called on template_redirect hook).
	 *
	 * @return void
	 */
	public function track_hit(): void
	{
		if (!$this->should_track()) {
			return;
		}

		// Get visitor IP.
		$ip = $this->get_visitor_ip();
		if (empty($ip)) {
			return;
		}

		// Get user agent.
		$user_agent = $this->get_user_agent();
		if (empty($user_agent)) {
			return;
		}

		// Anonymize visitor data.
		$partial_ip = $this->anonymizer->get_partial_ip($ip);
		$visitor_hash = $this->anonymizer->generate_visitor_hash($partial_ip, $user_agent);
		$user_agent_hash = $this->anonymizer->hash_user_agent($user_agent);

		// Get page data.
		$page_path = $this->get_page_path();
		$referrer = $this->get_referrer();
		$normalized_referrer = $this->referrer_normalizer->normalize_referrer($referrer ?? '');

		// Get device info.
		$device_info = $this->device_detector->analyze($user_agent);

		// Create hit object.
		$hit = new Hit(
			$visitor_hash,
			$page_path,
			$normalized_referrer,
			$user_agent_hash,
			$device_info['device_type'],
			$device_info['os'],
			current_time('mysql')
		);

		// Save hit.
		$this->save_hit($hit);
	}

	/**
	 * Check if current request should be tracked.
	 *
	 * @return bool True if should track, false otherwise.
	 */
	private function should_track(): bool
	{
		// Don't track in admin area.
		if (is_admin()) {
			return false;
		}

		// Don't track logged-in administrators.
		if (current_user_can('manage_options')) {
			return false;
		}

		// Don't track 404 pages.
		if (is_404()) {
			return false;
		}

		// Don't track bots.
		$user_agent = $this->get_user_agent();
		if (!empty($user_agent) && $this->bot_detector->is_bot($user_agent)) {
			return false;
		}

		return true;
	}

	/**
	 * Get visitor IP address.
	 *
	 * @return string IP address or empty string.
	 */
	private function get_visitor_ip(): string
	{
		// Check for proxy headers (in order of preference).
		$headers = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare.
			'HTTP_X_REAL_IP',            // Nginx proxy.
			'HTTP_X_FORWARDED_FOR',      // Standard proxy header.
			'REMOTE_ADDR',               // Direct connection.
		);

		foreach ($headers as $header) {
			if (!isset($_SERVER[$header])) {
				continue;
			}

			$ip = sanitize_text_field(wp_unslash($_SERVER[$header]));

			// Handle comma-separated IPs (X-Forwarded-For can contain multiple).
			if (str_contains($ip, ',')) {
				$ips = explode(',', $ip);
				$ip = trim($ips[0]);
			}

			// Validate IP.
			if (filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Get user agent string.
	 *
	 * @return string User agent or empty string.
	 */
	private function get_user_agent(): string
	{
		if (!isset($_SERVER['HTTP_USER_AGENT'])) {
			return '';
		}

		return sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
	}

	/**
	 * Get current page path.
	 *
	 * @return string Sanitized page path.
	 */
	private function get_page_path(): string
	{
		// Get request URI.
		$request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

		// Parse to get path only (remove query string).
		$parsed = wp_parse_url($request_uri);
		$path = $parsed['path'] ?? '/';

		// Ensure path starts with /.
		if (!str_starts_with($path, '/')) {
			$path = '/' . $path;
		}

		// Truncate to 255 characters (database limit).
		if (strlen($path) > 255) {
			$path = substr($path, 0, 255);
		}

		return sanitize_text_field($path);
	}

	/**
	 * Get HTTP referrer.
	 *
	 * @return string|null Referrer URL or null.
	 */
	private function get_referrer(): ?string
	{
		if (!isset($_SERVER['HTTP_REFERER'])) {
			return null;
		}

		$referrer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));

		// Truncate to 255 characters (database limit).
		if (strlen($referrer) > 255) {
			$referrer = substr($referrer, 0, 255);
		}

		return $referrer;
	}

	/**
	 * Save hit to database.
	 *
	 * @param Hit $hit Hit object.
	 * @return void
	 */
	private function save_hit(Hit $hit): void
	{
		global $wpdb;

		$table_name = $this->table_manager->get_hits_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table_name,
			array(
				'visitor_hash' => $hit->visitor_hash,
				'page_path' => $hit->page_path,
				'referrer' => $hit->referrer,
				'user_agent_hash' => $hit->user_agent_hash,
				'device_type' => $hit->device_type,
				'os' => $hit->os,
				'hit_date' => $hit->hit_date,
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
		);

		// Log errors but don't break page load.
		if (false === $wpdb->last_result) {
			error_log('Privacy Analytics Lite: Failed to insert hit - ' . $wpdb->last_error);
		}
	}
}

