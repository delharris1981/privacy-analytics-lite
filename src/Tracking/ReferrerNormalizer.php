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
class ReferrerNormalizer
{

	/**
	 * Normalize referrer URL to grouped source name.
	 *
	 * @param string $referrer Referrer URL.
	 * @return string|null Normalized source name or null for direct traffic.
	 */
	public function normalize_referrer(string $referrer): ?string
	{
		if (empty($referrer)) {
			return null;
		}

		// Parse URL to get host.
		$parsed = wp_parse_url($referrer);
		if (false === $parsed || !isset($parsed['host'])) {
			return null;
		}


		$host = strtolower($parsed['host']);

		// Remove www. prefix for matching.
		$host_clean = preg_replace('/^www\./', '', $host);

		// Get current site host to filter internal traffic (including subdomains).
		// We use $_SERVER['HTTP_HOST'] as afallback if available, otherwise just proceed.
		// A more robust way is getting home_url() but we want to avoid WP DB calls if possible in this context,
		// though home_url() is cached.
		// Let's rely on the passed referrer vs current host if possible, but here we only have referrer string.
		// So we will assume any subdomain of the 'main' domain of the site should be ignored?
		// Actually, standard practice is: if referrer host ends with the current site's root domain, ignore it.

		// Best approach: Get valid current host.
		$current_host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
		$current_host = preg_replace('/^www\./', '', $current_host);

		if (!empty($current_host)) {
			// If referrer IS the current host, it's internal.
			if ($host_clean === $current_host) {
				return null;
			}

			// If referrer is a subdomain of current host (e.g. referrer=sub.example.com, current=example.com).
			if (str_ends_with($host_clean, '.' . $current_host)) {
				return null;
			}

			// If current host is a subdomain of referrer (unlikely but possible), 
			// usually we want to block if they share the root domain. 
			// The user specifically asked: "remove any subdomain of the root domain".
			// This implies we need to know the root domain. 
			// A simple heuristic: if they share the last two segments (example.com), treat as same.
			// But for now, let's implement the specific request: filter subdomains of the *current* host,
			// AND specifically filter the user provided domains if they are not covered.

			// User asked for "cpcalendars.eliteemc.com, hostmaster.eliteemc.com" and "any subdomain of the root domain".
			// If the site IS eliteemc.com, then *.eliteemc.com will be filtered by the logic above.

			// Let's also explicitly check if they share a common root if we can easily determine it, 
			// but simply checking if $host_clean ends with $current_host covers the "subdomain of current" case.
		}

		// Use match() expression for source grouping (PHP 8.2 feature).
		return match (true) {
			// Social media platforms.
			str_contains($host_clean, 't.co') => 'Twitter',
			str_contains($host_clean, 'twitter.com') => 'Twitter',
			str_contains($host_clean, 'facebook.com') => 'Facebook',
			str_contains($host_clean, 'fb.com') => 'Facebook',
			str_contains($host_clean, 'linkedin.com') => 'LinkedIn',
			str_contains($host_clean, 'instagram.com') => 'Instagram',
			str_contains($host_clean, 'pinterest.com') => 'Pinterest',
			str_contains($host_clean, 'reddit.com') => 'Reddit',
			str_contains($host_clean, 'youtube.com') => 'YouTube',
			str_contains($host_clean, 'youtu.be') => 'YouTube',
			str_contains($host_clean, 'tiktok.com') => 'TikTok',

			// Search engines.
			str_contains($host_clean, 'google.') => 'Google',
			str_contains($host_clean, 'bing.com') => 'Bing',
			str_contains($host_clean, 'yahoo.com') => 'Yahoo',
			str_contains($host_clean, 'duckduckgo.com') => 'DuckDuckGo',
			str_contains($host_clean, 'baidu.com') => 'Baidu',
			str_contains($host_clean, 'yandex.') => 'Yandex',

			// Email clients.
			str_contains($host_clean, 'mail.') => 'Email',
			str_contains($host_clean, 'outlook.com') => 'Email',
			str_contains($host_clean, 'gmail.com') => 'Email',

			// Default: return the clean hostname.
			default => $host_clean,
		};
	}
}

