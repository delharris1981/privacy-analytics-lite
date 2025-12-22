<?php
/**
 * Hit data transfer object.
 *
 * @package PrivacyAnalytics\Lite\Models
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Models;

/**
 * Immutable hit data transfer object.
 */
readonly class Hit {

	/**
	 * Hashed visitor identifier.
	 *
	 * @var string
	 */
	public string $visitor_hash;

	/**
	 * Page path.
	 *
	 * @var string
	 */
	public string $page_path;

	/**
	 * Referrer (normalized) or null for direct traffic.
	 *
	 * @var string|null
	 */
	public ?string $referrer;

	/**
	 * Hashed user agent.
	 *
	 * @var string
	 */
	public string $user_agent_hash;

	/**
	 * Hit timestamp.
	 *
	 * @var string
	 */
	public string $hit_date;

	/**
	 * Constructor.
	 *
	 * @param string      $visitor_hash   Hashed visitor identifier.
	 * @param string      $page_path      Page path.
	 * @param string|null $referrer       Referrer or null.
	 * @param string      $user_agent_hash Hashed user agent.
	 * @param string      $hit_date       Hit timestamp.
	 */
	public function __construct(
		string $visitor_hash,
		string $page_path,
		?string $referrer,
		string $user_agent_hash,
		string $hit_date
	) {
		$this->visitor_hash   = $visitor_hash;
		$this->page_path      = $page_path;
		$this->referrer       = $referrer;
		$this->user_agent_hash = $user_agent_hash;
		$this->hit_date       = $hit_date;
	}
}

