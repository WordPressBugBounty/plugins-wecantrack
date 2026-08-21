<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Class WecantrackDiagnostics
 *
 * Optimizer-plugin compatibility detection and the tag health check.
 *
 * The health check fetches the site's own homepage server-side and verifies
 * the tracking tag that visitors actually receive: present, pointing at the
 * right property, and not inlined into an optimizer bundle (a bundled copy
 * freezes the per-request anti-bot token minted into wct.js).
 *
 * Non-OK verdicts and sightings of optimizer plugins we have no exclusion
 * hooks for are reported to the wecantrack API (authenticated with the
 * site's own API key) so support can follow up and native exclusions can be
 * added in future releases. This class never talks to Slack or any other
 * third party directly.
 *
 * @package Wecantrack
 */
class WecantrackDiagnostics {

    const VERDICT_OK = 'ok';
    const VERDICT_CACHED_COPY = 'cached_copy';
    const VERDICT_WRONG_PROPERTY = 'wrong_property';
    const VERDICT_TAG_MISSING = 'tag_missing';

    const REPORT_THROTTLE_TRANSIENT = 'wecantrack_optimizer_reported';
    const REPORT_THROTTLE_S = 30 * 86400;

    const MAX_BUNDLE_SCANS = 5;

    /**
     * Optimizer plugins that minify/combine/delay JavaScript.
     * 'supported' means WecantrackApp registers exclusion hooks for it.
     *
     * @return array<string, array{name: string, supported: bool}> Keyed by plugin basename.
     */
    public static function optimizer_catalog() {
        return [
            'sg-cachepress/sg-cachepress.php'       => ['name' => 'SiteGround Optimizer', 'supported' => true],
            'wp-rocket/wp-rocket.php'               => ['name' => 'WP Rocket', 'supported' => true],
            'litespeed-cache/litespeed-cache.php'   => ['name' => 'LiteSpeed Cache', 'supported' => true],
            'autoptimize/autoptimize.php'           => ['name' => 'Autoptimize', 'supported' => true],
            'w3-total-cache/w3-total-cache.php'     => ['name' => 'W3 Total Cache', 'supported' => true],
            'wp-optimize/wp-optimize.php'           => ['name' => 'WP-Optimize', 'supported' => true],
            'perfmatters/perfmatters.php'           => ['name' => 'Perfmatters', 'supported' => true],
            'flying-press/flying-press.php'         => ['name' => 'FlyingPress', 'supported' => false],
            'breeze/breeze.php'                     => ['name' => 'Breeze', 'supported' => false],
            'hummingbird-performance/wp-hummingbird.php' => ['name' => 'Hummingbird', 'supported' => false],
            'wp-fastest-cache/wpFastestCache.php'   => ['name' => 'WP Fastest Cache', 'supported' => false],
            'swift-performance-lite/performance.php' => ['name' => 'Swift Performance Lite', 'supported' => false],
            'nitropack/main.php'                    => ['name' => 'NitroPack', 'supported' => false],
            'jetpack-boost/jetpack-boost.php'       => ['name' => 'Jetpack Boost', 'supported' => false],
        ];
    }

    /**
     * The catalog reduced to plugins active on this install.
     *
     * @return array<string, array{name: string, supported: bool}> Keyed by plugin basename.
     */
    public static function active_optimizers() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return array_filter(
            self::optimizer_catalog(),
            function ($basename) { return is_plugin_active($basename); },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Reports active optimizers we have no exclusion hooks for, so native
     * support can be added in a future release. Throttled to once per plugin
     * per 30 days via a transient.
     *
     * @param string $api_key The wecantrack API key.
     * @return void
     */
    public static function maybe_report_unsupported_optimizers($api_key) {
        if (empty($api_key)) {
            return;
        }

        $reported = get_transient(self::REPORT_THROTTLE_TRANSIENT);
        $reported = is_array($reported) ? $reported : [];
        $changed = false;

        foreach (self::active_optimizers() as $basename => $optimizer) {
            if ($optimizer['supported'] || isset($reported[$basename])) {
                continue;
            }

            self::report_to_api($api_key, [
                'type' => 'unknown_optimizer',
                'site_url' => home_url(),
                'details' => [
                    'plugin' => $basename,
                    'plugin_name' => $optimizer['name'],
                ],
            ]);

            $reported[$basename] = time();
            $changed = true;
        }

        if ($changed) {
            set_transient(self::REPORT_THROTTLE_TRANSIENT, $reported, self::REPORT_THROTTLE_S);
        }
    }

    /**
     * Fetches the homepage and verifies the tracking tag visitors receive.
     *
     * @param string $api_key The wecantrack API key (used to report non-OK verdicts).
     * @return array{verdict: string, message: string, details: array} The check result.
     */
    public static function run_tag_check($api_key) {
        $website_options = get_option('wecantrack_website_options');
        $expected_property = is_array($website_options) ? ($website_options['property_id'] ?? '') : '';
        $script_version = is_array($website_options) ? (int) ($website_options['script_version'] ?? 0) : 0;

        $response = wp_remote_get(
            add_query_arg('wct_tag_check', (string) time(), home_url('/')),
            [
                'timeout' => 15,
                'headers' => ['Cache-Control' => 'no-cache'],
                'user-agent' => 'wecantrack-tag-check/' . WECANTRACK_VERSION,
            ]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return [
                'verdict' => 'error',
                'message' => esc_html__('Could not fetch your homepage to inspect the tag. Try again in a minute.', 'wecantrack'),
                'details' => [],
            ];
        }

        $html = (string) wp_remote_retrieve_body($response);
        $result = self::classify_homepage_html($html, $expected_property, $script_version);

        if (!in_array($result['verdict'], [self::VERDICT_OK, 'error'], true)) {
            self::report_to_api($api_key, [
                'type' => 'tag_check',
                'verdict' => $result['verdict'],
                'site_url' => home_url(),
                'details' => $result['details'],
            ]);
        }

        return $result;
    }

    /**
     * Decides the tag verdict from the homepage HTML.
     *
     * @param string $html              The homepage HTML.
     * @param string $expected_property The property id this install should serve.
     * @param int    $script_version    The configured script version (1 or 2).
     * @return array{verdict: string, message: string, details: array} The verdict.
     */
    public static function classify_homepage_html($html, $expected_property, $script_version) {
        // 1. A wct.js script tag in the HTML (the healthy v2 shape).
        if (preg_match('~<script[^>]+src\s*=\s*["\']([^"\']*wct\.js[^"\']*)["\']~i', $html, $tag_match)) {
            $found_property = '';
            if (preg_match('~[?&]property_id=([A-Za-z0-9_-]+)~', $tag_match[1], $property_match)) {
                $found_property = $property_match[1];
            }

            if (!empty($expected_property) && !empty($found_property) && $found_property !== $expected_property) {
                return [
                    'verdict' => self::VERDICT_WRONG_PROPERTY,
                    'message' => sprintf(
                        // translators: 1: property id found on the page, 2: property id of this website.
                        esc_html__('The tag on your homepage uses property %1$s, which belongs to a different website in your account. Replace it with this site\'s snippet (%2$s).', 'wecantrack'),
                        $found_property,
                        $expected_property
                    ),
                    'details' => ['found_property_id' => $found_property, 'src' => $tag_match[1]],
                ];
            }

            return [
                'verdict' => self::VERDICT_OK,
                'message' => sprintf(
                    // translators: %s: The property id served by the tag.
                    esc_html__('Tag OK. wct.js loads with property %s, matching this site.', 'wecantrack'),
                    $found_property !== '' ? $found_property : $expected_property
                ),
                'details' => [],
            ];
        }

        // 2. No script tag, but wct.js referenced elsewhere in the HTML. The
        //    legacy v1 snippet is inline JS containing the script URL, so for
        //    v1 installs this is the healthy shape.
        if (stripos($html, 'wct.js') !== false || strpos($html, 'window._wct') !== false) {
            if ($script_version === 1) {
                return [
                    'verdict' => self::VERDICT_OK,
                    'message' => esc_html__('Tag OK. The legacy (v1) inline snippet is present on your homepage.', 'wecantrack'),
                    'details' => [],
                ];
            }

            return [
                'verdict' => self::VERDICT_CACHED_COPY,
                'message' => esc_html__('A copy of wct.js appears to be inlined into your page instead of loading from our servers. Exclude wct.js from JavaScript optimization and clear your optimizer cache.', 'wecantrack'),
                'details' => ['location' => 'inline'],
            ];
        }

        // 3. Not in the HTML at all: scan likely optimizer bundles for an
        //    inlined copy before concluding the tag is missing.
        $bundle = self::find_wct_in_bundles($html);
        if ($bundle !== null) {
            return [
                'verdict' => self::VERDICT_CACHED_COPY,
                'message' => sprintf(
                    // translators: %s: The bundle filename that contains the inlined script.
                    esc_html__('wct.js was not found as a script tag, but its code appears inside %s. Exclude wct.js from JavaScript combination and clear the optimizer cache.', 'wecantrack'),
                    basename((string) parse_url($bundle, PHP_URL_PATH))
                ),
                'details' => ['location' => 'bundle', 'bundle_src' => $bundle],
            ];
        }

        return [
            'verdict' => self::VERDICT_TAG_MISSING,
            'message' => esc_html__('The tracking tag was not found on your homepage. If tracking is enabled, an optimizer or consent tool may be removing it, or your page cache still serves an old version.', 'wecantrack'),
            'details' => [],
        ];
    }

    /**
     * Downloads up to MAX_BUNDLE_SCANS optimizer-looking script bundles from
     * the page and searches them for inlined wct.js content.
     *
     * @param string $html The homepage HTML.
     * @return string|null The bundle src containing wct.js content, or null.
     */
    private static function find_wct_in_bundles($html) {
        if (!preg_match_all('~<script[^>]+src\s*=\s*["\']([^"\']+\.js[^"\']*)["\']~i', $html, $matches)) {
            return null;
        }

        $bundle_markers = ['siteground-optimizer-assets', 'wp-rocket', 'autoptimize', 'litespeed', '/min/', '/cache/', 'breeze', 'flying-press'];
        $scanned = 0;

        foreach ($matches[1] as $src) {
            $is_bundle = false;
            foreach ($bundle_markers as $marker) {
                if (stripos($src, $marker) !== false) {
                    $is_bundle = true;
                    break;
                }
            }

            if (!$is_bundle || $scanned >= self::MAX_BUNDLE_SCANS) {
                continue;
            }

            $scanned++;
            $response = wp_remote_get($src, ['timeout' => 10]);
            if (is_wp_error($response)) {
                continue;
            }

            $body = (string) wp_remote_retrieve_body($response);
            if (strpos($body, 'window._wct') !== false || strpos($body, '_wct.st=') !== false) {
                return $src;
            }
        }

        return null;
    }

    /**
     * Sends a diagnostic report to the wecantrack API. Fire-and-forget: the
     * report must never slow down or break the admin page.
     *
     * @param string $api_key The wecantrack API key.
     * @param array  $payload The report payload (type, site_url, verdict, details).
     * @return void
     */
    public static function report_to_api($api_key, array $payload) {
        if (empty($api_key)) {
            return;
        }

        $payload['plugin_version'] = WECANTRACK_VERSION;

        wp_remote_post(WECANTRACK_API_BASE_URL . '/api/v1/plugin/diagnostics', [
            'timeout' => 5,
            'blocking' => false,
            'headers' => [
                'x-api-key' => $api_key,
                'x-wp-version' => WECANTRACK_VERSION,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'sslverify' => WecantrackHelper::get_sslverify_option(),
        ]);
    }
}
