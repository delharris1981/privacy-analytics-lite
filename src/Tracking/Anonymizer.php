<?php
/**
 * Privacy anonymization and hashing logic.
 *
 * @package PrivacyAnalytics\Lite\Tracking
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Tracking;

/**
 * Handles privacy-compliant anonymization of visitor data.
 */
class Anonymizer {

	/**
	 * Transient key prefix for daily salt.
	 *
	 * @var string
	 */
	private const SALT_TRANSIENT_PREFIX = 'pa_daily_salt_';

	/**
	 * Generate visitor hash using partial IP, user agent, and daily salt.
	 *
	 * @param string $partial_ip  Partial IP address (last octet/segment only).
	 * @param string $user_agent  User agent string.
	 * @return string Hashed visitor identifier.
	 */
	public function generate_visitor_hash( string $partial_ip, string $user_agent ): string {
		$salt = $this->get_daily_salt();
		return hash_hmac( 'sha256', $partial_ip . $user_agent, $salt );
	}

	/**
	 * Get or generate daily salt that rotates every 24 hours.
	 *
	 * @return string Daily salt.
	 */
	public function get_daily_salt(): string {
		$date_key = date( 'Y-m-d' );
		$transient_key = self::SALT_TRANSIENT_PREFIX . $date_key;

		$salt = get_transient( $transient_key );

		if ( false === $salt ) {
			// Generate new salt for today.
			$salt = wp_generate_password( 64, true, true );
			// Store for 25 hours to ensure coverage across day boundary.
			set_transient( $transient_key, $salt, DAY_IN_SECONDS + HOUR_IN_SECONDS );
		}

		return $salt;
	}

	/**
	 * Extract partial IP address (last octet for IPv4, last segment for IPv6).
	 *
	 * @param string $ip Full IP address.
	 * @return string Partial IP (never stored permanently).
	 */
	public function get_partial_ip( string $ip ): string {
		// Validate and sanitize IP.
		$ip = filter_var( $ip, FILTER_VALIDATE_IP );
		if ( false === $ip ) {
			return '0.0.0.0';
		}

		// Handle IPv4.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			return '0.0.0.' . end( $parts );
		}

		// Handle IPv6.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			// Remove IPv6-mapped IPv4 addresses prefix if present.
			$ip = str_replace( '::ffff:', '', $ip );
			$parts = explode( ':', $ip );
			// Get last non-empty segment.
			$last_segment = '';
			for ( $i = count( $parts ) - 1; $i >= 0; $i-- ) {
				if ( ! empty( $parts[ $i ] ) ) {
					$last_segment = $parts[ $i ];
					break;
				}
			}
			return '::' . $last_segment;
		}

		return '0.0.0.0';
	}

	/**
	 * Hash user agent string.
	 *
	 * @param string $user_agent User agent string.
	 * @return string Hashed user agent.
	 */
	public function hash_user_agent( string $user_agent ): string {
		return hash( 'sha256', $user_agent );
	}
}

