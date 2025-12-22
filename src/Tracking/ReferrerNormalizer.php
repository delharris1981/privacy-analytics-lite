<?php
/**
 * Referrer normalization and grouping.
 *
 * @package PrivacyAnalytics\Lite\Tracking
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Tracking;

/**
 * Normalizes and groups referral sources.
 */
class ReferrerNormalizer {

	/**
	 * Normalize referrer URL to grouped source name.
	 *
	 * @param string $referrer Referrer URL.
	 * @return string|null Normalized source name or null for direct traffic.
	 */
	public function normalize_referrer( string $referrer ): ?string {
		if ( empty( $referrer ) ) {
			return null;
		}

		// Parse URL to get host.
		$parsed = wp_parse_url( $referrer );
		if ( false === $parsed || ! isset( $parsed['host'] ) ) {
			return null;
		}

		$host = strtolower( $parsed['host'] );

		// Remove www. prefix for matching.
		$host_clean = preg_replace( '/^www\./', '', $host );

		// Use match() expression for source grouping (PHP 8.2 feature).
		return match ( true ) {
			// Social media platforms.
			str_contains( $host_clean, 't.co' ) => 'Twitter',
			str_contains( $host_clean, 'twitter.com' ) => 'Twitter',
			str_contains( $host_clean, 'facebook.com' ) => 'Facebook',
			str_contains( $host_clean, 'fb.com' ) => 'Facebook',
			str_contains( $host_clean, 'linkedin.com' ) => 'LinkedIn',
			str_contains( $host_clean, 'instagram.com' ) => 'Instagram',
			str_contains( $host_clean, 'pinterest.com' ) => 'Pinterest',
			str_contains( $host_clean, 'reddit.com' ) => 'Reddit',
			str_contains( $host_clean, 'youtube.com' ) => 'YouTube',
			str_contains( $host_clean, 'youtu.be' ) => 'YouTube',
			str_contains( $host_clean, 'tiktok.com' ) => 'TikTok',

			// Search engines.
			str_contains( $host_clean, 'google.' ) => 'Google',
			str_contains( $host_clean, 'bing.com' ) => 'Bing',
			str_contains( $host_clean, 'yahoo.com' ) => 'Yahoo',
			str_contains( $host_clean, 'duckduckgo.com' ) => 'DuckDuckGo',
			str_contains( $host_clean, 'baidu.com' ) => 'Baidu',
			str_contains( $host_clean, 'yandex.' ) => 'Yandex',

			// Email clients.
			str_contains( $host_clean, 'mail.' ) => 'Email',
			str_contains( $host_clean, 'outlook.com' ) => 'Email',
			str_contains( $host_clean, 'gmail.com' ) => 'Email',

			// Default: return the clean hostname.
			default => $host_clean,
		};
	}
}

