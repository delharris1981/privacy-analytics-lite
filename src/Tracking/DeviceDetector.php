<?php
/**
 * Device detection logic.
 *
 * @package PrivacyAnalytics\Lite\Tracking
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Tracking;

/**
 * Parses User Agent strings into broad, privacy-compliant categories.
 */
class DeviceDetector {

	/**
	 * Analyze User Agent to determine device type and OS.
	 *
	 * @param string $user_agent User Agent string.
	 * @return array{device_type: string, os: string} Detected device info.
	 */
	public function analyze( string $user_agent ): array {
		return array(
			'device_type' => $this->detect_device_type( $user_agent ),
			'os'          => $this->detect_os( $user_agent ),
		);
	}

	/**
	 * Detect device type (Desktop, Tablet, Mobile).
	 *
	 * @param string $user_agent User Agent string.
	 * @return string Device type.
	 */
	private function detect_device_type( string $user_agent ): string {
		if ( preg_match( '/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $user_agent ) ) {
			return 'Tablet';
		}

		if ( preg_match( '/(mobile|android|iphone|ipod|blackberry|phone|opera mini|iemobile|webos)/i', $user_agent ) ) {
			return 'Mobile';
		}

		return 'Desktop';
	}

	/**
	 * Detect Operating System.
	 *
	 * @param string $user_agent User Agent string.
	 * @return string Operating System.
	 */
	private function detect_os( string $user_agent ): string {
		if ( preg_match( '/iphone|ipad|ipod/i', $user_agent ) ) {
			return 'iOS';
		}

		if ( preg_match( '/android/i', $user_agent ) ) {
			return 'Android';
		}

		if ( preg_match( '/windows/i', $user_agent ) ) {
			return 'Windows';
		}

		if ( preg_match( '/macintosh|mac os x/i', $user_agent ) ) {
			return 'MacOS';
		}

		if ( preg_match( '/linux/i', $user_agent ) ) {
			return 'Linux';
		}

		return 'Other';
	}
}
