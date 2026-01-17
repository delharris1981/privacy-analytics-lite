<?php
/**
 * Remote update checker using GitHub API.
 *
 * @package PrivacyAnalytics\Lite\Core
 */

declare(strict_types=1);

namespace PrivacyAnalytics\Lite\Core;

/**
 * Handles remote updates from GitHub.
 */
final class GitHubUpdater
{

    /**
     * Repository details.
     */
    private string $username = 'delharris1981';
    private string $repo = 'privacy-analytics-lite';
    private string $slug;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->slug = plugin_basename(PRIVACY_ANALYTICS_LITE_PLUGIN_FILE);

        add_filter('site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_popup_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    /**
     * Check GitHub for a newer version.
     *
     * @param object $transient Plugins update transient.
     * @return object
     */
    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_data();

        if ($remote) {
            // Normalize version strings (remove 'v' prefix if present).
            $remote_version = ltrim($remote->tag_name, 'v');
            $local_version = PRIVACY_ANALYTICS_LITE_VERSION;

            if (version_compare($local_version, $remote_version, '<')) {
                $res = new \stdClass();
                $res->slug = 'privacy-analytics-lite';
                $res->plugin = $this->slug;
                $res->new_version = $remote_version;
                $res->package = $remote->zipball_url;
                $res->url = 'https://github.com/' . $this->username . '/' . $this->repo;

                $transient->response[$this->slug] = $res;
            }
        }

        return $transient;
    }

    /**
     * Provide info for the "View details" popup.
     */
    public function plugin_popup_info($res, $action, $args)
    {
        if ('plugin_information' !== $action) {
            return $res;
        }

        if ('privacy-analytics-lite' !== $args->slug) {
            return $res;
        }

        $remote = $this->get_remote_data();
        if (!$remote) {
            return $res;
        }

        $res = new \stdClass();
        $res->name = 'Privacy-First Analytics Lite';
        $res->slug = 'privacy-analytics-lite';
        $res->version = ltrim($remote->tag_name, 'v');
        $res->author = $this->username;
        $res->homepage = 'https://github.com/' . $this->username . '/' . $this->repo;
        $res->download_link = $remote->zipball_url;
        $res->sections = array(
            'description' => 'Privacy-compliant, server-side analytics with aggregated data.',
            'changelog' => $this->parse_changelog($remote->body),
        );

        return $res;
    }

    /**
     * Fix folder naming after GitHub download.
     *
     * @param bool  $response   Install response.
     * @param array $hook_extra Extra info.
     * @param array $result     Installation result.
     * @return array|bool
     */
    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        // Check if it's a plugin.
        if (!isset($hook_extra['type']) || 'plugin' !== $hook_extra['type']) {
            return $result;
        }

        // Identify if this is our plugin.
        $is_our_plugin = false;

        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->slug) {
            $is_our_plugin = true;
        } elseif (isset($result['destination_name']) && $result['destination_name'] === dirname($this->slug)) {
            $is_our_plugin = true;
        }

        if (!$is_our_plugin) {
            return $result;
        }

        // The folder name WordPress expects.
        $proper_destination = WP_PLUGIN_DIR . '/' . dirname($this->slug);

        // Source is where GitHub unzipped it (usually something-hash).
        $source = $result['destination'];

        // Move it to the proper folder.
        if ($source !== $proper_destination) {
            $wp_filesystem->move($source, $proper_destination);
            $result['destination'] = $proper_destination;
        }

        return $result;
    }

    /**
     * Fetch data from GitHub API.
     */
    private function get_remote_data()
    {
        $cache_key = 'pa_lite_github_update_info';
        $remote = get_transient($cache_key);

        if (false !== $remote) {
            return $remote;
        }

        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        ));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $remote = json_decode(wp_remote_retrieve_body($response));

        // Cache for 12 hours to avoid rate limiting.
        set_transient($cache_key, $remote, 12 * HOUR_IN_SECONDS);

        return $remote;
    }

    /**
     * Basic Markdown to HTML for the changelog popup.
     */
    private function parse_changelog(string $markdown): string
    {
        // Simple conversion for the info popup.
        $html = $markdown;
        $html = preg_replace('/### (.*?)\n/', '<h4>$1</h4>', $html);
        $html = preg_replace('/## (.*?)\n/', '<h4>$1</h4>', $html);
        $html = preg_replace('/- (.*?)\n/', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*?<\/li>)+/s', '<ul>$0</ul>', $html);

        return wp_kses_post($html);
    }
}
