<?php
/**
 * Bot user agent detection.
 *
 * @package PrivacyAnalytics\Lite\Tracking
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Tracking;

/**
 * Detects common bot and crawler user agents.
 */
class BotDetector {

	/**
	 * Common bot user agent patterns.
	 *
	 * @var array<string>
	 */
	private const BOT_PATTERNS = array(
		'googlebot',
		'bingbot',
		'slurp',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'sogou',
		'exabot',
		'facebot',
		'ia_archiver',
		'scrapy',
		'python-requests',
		'curl',
		'wget',
		'go-http-client',
		'java/',
		'php/',
		'ruby',
		'node-fetch',
		'axios',
		'okhttp',
		'httpclient',
		'apache-httpclient',
		'libwww-perl',
		'perl',
		'bot',
		'crawler',
		'spider',
		'scraper',
		'feed',
		'rss',
		'validator',
		'check',
		'monitor',
		'uptime',
		'pingdom',
		'newrelic',
		'ping',
		'health',
	);

	/**
	 * Check if user agent is a bot.
	 *
	 * @param string $user_agent User agent string.
	 * @return bool True if bot, false otherwise.
	 */
	public function is_bot( string $user_agent ): bool {
		if ( empty( $user_agent ) ) {
			return false;
		}

		$user_agent_lower = strtolower( $user_agent );

		foreach ( self::BOT_PATTERNS as $pattern ) {
			if ( str_contains( $user_agent_lower, $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}

