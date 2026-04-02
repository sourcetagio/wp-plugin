<?php
/**
 * Plugin Name: SourceTag
 * Plugin URI: https://sourcetag.io
 * Description: Lead attribution tracking. Captures UTM parameters, click IDs, and referrer data in your form submissions.
 * Version: 1.0.0
 * Author: SourceTag
 * Author URI: https://sourcetag.io
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sourcetag
 */

if (!defined('ABSPATH')) {
    exit;
}

// Polyfill for PHP < 8.0
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $suffix): bool {
        $len = strlen($suffix);
        return $len === 0 || substr($haystack, -$len) === $suffix;
    }
}

define('SOURCETAG_VERSION', '1.0.0');
define('SOURCETAG_CDN', 'https://cdn.sourcetag.io');

/**
 * Auto-update from GitHub releases
 */
require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$sourcetag_updater = PucFactory::buildUpdateChecker(
    'https://github.com/sourcetagio/wp-plugin/',
    __FILE__,
    'sourcetag'
);
$sourcetag_updater->getVcsApi()->enableReleaseAssets();

/**
 * Admin settings page
 */
function sourcetag_admin_menu() {
    add_options_page(
        'SourceTag Settings',
        'SourceTag',
        'manage_options',
        'sourcetag',
        'sourcetag_settings_page'
    );
}
add_action('admin_menu', 'sourcetag_admin_menu');

/**
 * Register settings
 */
function sourcetag_register_settings() {
    register_setting('sourcetag_settings', 'sourcetag_script_url', [
        'type' => 'string',
        'sanitize_callback' => 'sourcetag_sanitise_script_url',
        'default' => '',
    ]);
    register_setting('sourcetag_settings', 'sourcetag_server_cookie', [
        'type' => 'boolean',
        'default' => true,
    ]);
}
add_action('admin_init', 'sourcetag_register_settings');

/**
 * Sanitise the script URL input.
 * Only accepts URLs from our CDN.
 */
function sourcetag_sanitise_script_url($input) {
    $input = trim(strip_tags($input));
    $input = esc_url_raw($input, ['https']);
    if (empty($input)) {
        return '';
    }
    $host = parse_url($input, PHP_URL_HOST);
    if ($host !== 'cdn.sourcetag.io') {
        add_settings_error('sourcetag_script_url', 'invalid_url', 'Script URL must be from cdn.sourcetag.io');
        return '';
    }
    return $input;
}

/**
 * Settings page HTML
 */
function sourcetag_settings_page() {
    $script_url = get_option('sourcetag_script_url', '');
    $server_cookie = get_option('sourcetag_server_cookie', true);
    ?>
    <div class="wrap">
        <h1>SourceTag Settings</h1>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php settings_fields('sourcetag_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sourcetag_script_url">Script URL</label>
                    </th>
                    <td>
                        <input type="url" id="sourcetag_script_url" name="sourcetag_script_url"
                            value="<?php echo esc_attr($script_url); ?>" class="large-text"
                            placeholder="https://cdn.sourcetag.io/scripts/your-site-id/st.js" />
                        <p class="description">
                            Copy the script URL from your site's <strong>Install Script</strong> section in the
                            <a href="https://app.sourcetag.io/dashboard" target="_blank">SourceTag dashboard</a>.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sourcetag_server_cookie">Server-side cookies</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="sourcetag_server_cookie" name="sourcetag_server_cookie"
                                value="1" <?php checked($server_cookie, true); ?> />
                            Enable 400-day cookie persistence
                        </label>
                        <p class="description">
                            Recommended. Re-sets the attribution cookie via HTTP headers so browsers like
                            Safari, Brave, and Firefox allow it to persist for up to 400 days
                            (vs 7 days for JavaScript-only cookies).
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Inject the tracking script into <head>
 * Loaded without async/defer so it runs before forms are interacted with.
 *
 * If server-side cookies are enabled, adds data-server-cookie attribute
 * pointing to the REST endpoint. The JS script reads this and calls it
 * on first visit and new sessions to get 400-day Set-Cookie headers.
 */
function sourcetag_inject_script() {
    $script_url = get_option('sourcetag_script_url', '');
    if (empty($script_url)) {
        return;
    }

    $server_cookie = get_option('sourcetag_server_cookie', true);

    $attrs = 'src="' . esc_url($script_url) . '"';
    if ($server_cookie) {
        $attrs .= ' data-server-cookie="' . esc_url(rest_url('sourcetag/v1/set-cookie')) . '"';
    }

    echo '<script ' . $attrs . '></script>' . "\n";
}
add_action('wp_head', 'sourcetag_inject_script', 1);

/**
 * Server-side cookie refresh
 *
 * Re-sets the attribution cookie via PHP setcookie() for Safari 400-day
 * persistence. Only fires when:
 * 1. The cookie exists (JS script created it)
 * 2. There are UTM params or a new external referrer (new marketing touch)
 *    OR it's been more than 24 hours since the last PHP refresh
 *
 * This avoids sending Set-Cookie on every single page load while still
 * keeping the cookie alive in Safari.
 *
 * The JS script handles all data logic (cookie creation, touch updates,
 * visit counting, channel categorisation). PHP only refreshes the expiry.
 */
function sourcetag_refresh_cookie() {
    $server_cookie = get_option('sourcetag_server_cookie', true);
    if (!$server_cookie) {
        return;
    }

    $cookie_name = '_sourcetag';

    if (!isset($_COOKIE[$cookie_name])) {
        return;
    }

    // Check if we should refresh: new attribution data OR 24hr+ since last refresh
    $should_refresh = false;

    // New marketing touch (UTM params or external referrer)
    if (!empty($_GET['utm_source']) || !empty($_GET['utm_medium']) || !empty($_GET['utm_campaign'])
        || !empty($_GET['gclid']) || !empty($_GET['fbclid']) || !empty($_GET['msclkid'])
        || !empty($_GET['ttclid']) || !empty($_GET['gbraid']) || !empty($_GET['wbraid'])) {
        $should_refresh = true;
    }

    // Check external referrer (not same domain)
    if (!$should_refresh && !empty($_SERVER['HTTP_REFERER'])) {
        $ref_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $our_host = parse_url(home_url(), PHP_URL_HOST);
        if ($ref_host && $ref_host !== $our_host && !str_ends_with($ref_host, '.' . $our_host)) {
            $should_refresh = true;
        }
    }

    // Periodic refresh: check a marker cookie for last refresh time
    $refresh_marker = $cookie_name . '_r';
    if (!$should_refresh && !isset($_COOKIE[$refresh_marker])) {
        // Marker cookie expired (24hr TTL) or doesn't exist = time to refresh
        $should_refresh = true;
    }

    if (!$should_refresh) {
        return;
    }

    // Sanitise cookie value via JSON round-trip before re-setting
    $safe_value = sourcetag_sanitise_cookie_value($_COOKIE[$cookie_name]);
    if ($safe_value === false) {
        return;
    }

    // Re-set the attribution cookie via HTTP header with 400-day expiry
    setcookie($cookie_name, $safe_value, [
        'expires' => time() + (400 * 86400),
        'path' => '/',
        'secure' => is_ssl(),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);

    // Set refresh marker (24hr TTL) so we don't refresh on every page load
    setcookie($refresh_marker, '1', [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
add_action('init', 'sourcetag_refresh_cookie');

/**
 * REST endpoint for JS-initiated cookie setting
 *
 * The JS script calls this on first visit to set the cookie via HTTP
 * Set-Cookie header (before the PHP refresh hook has anything to refresh).
 */
function sourcetag_register_rest_route() {
    $server_cookie = get_option('sourcetag_server_cookie', true);
    if (!$server_cookie) {
        return;
    }

    register_rest_route('sourcetag/v1', '/set-cookie', [
        'methods' => 'POST',
        'callback' => 'sourcetag_set_cookie_handler',
        'permission_callback' => 'sourcetag_check_origin',
        'args' => [
            'name' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'value' => [
                'required' => true,
                'type' => 'string',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'sourcetag_register_rest_route');

/**
 * Handle the JS-initiated cookie set request.
 * Only accepts the configured cookie name to prevent abuse.
 */
function sourcetag_set_cookie_handler($request) {
    $cookie_name = sanitize_text_field($request->get_param('name'));
    $cookie_value = $request->get_param('value');

    // Only allow the standard cookie name
    $allowed_name = '_sourcetag';
    if ($cookie_name !== $allowed_name) {
        return new WP_REST_Response(['error' => 'Invalid cookie name'], 400);
    }

    if (empty($cookie_value)) {
        return new WP_REST_Response(['error' => 'Missing value'], 400);
    }

    // Sanitise via JSON round-trip to strip injected characters
    $cookie_value = sourcetag_sanitise_cookie_value($cookie_value);
    if ($cookie_value === false) {
        return new WP_REST_Response(['error' => 'Invalid cookie value'], 400);
    }

    setcookie($cookie_name, $cookie_value, [
        'expires' => time() + (400 * 86400),
        'path' => '/',
        'secure' => is_ssl(),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);

    return new WP_REST_Response(['ok' => true], 200);
}

/**
 * Validate that the request originates from the same site.
 * Checks Origin and Referer headers against the WordPress home URL.
 */
function sourcetag_check_origin() {
    $home_host = parse_url(home_url(), PHP_URL_HOST);
    if (!$home_host) {
        return false;
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if ($origin) {
        $origin_host = parse_url($origin, PHP_URL_HOST);
        if ($origin_host && ($origin_host === $home_host || str_ends_with($origin_host, '.' . $home_host))) {
            return true;
        }
    }

    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if ($referer) {
        $ref_host = parse_url($referer, PHP_URL_HOST);
        if ($ref_host && ($ref_host === $home_host || str_ends_with($ref_host, '.' . $home_host))) {
            return true;
        }
    }

    return false;
}

/**
 * Sanitise a cookie value via JSON round-trip.
 * Returns the clean value, or false if invalid.
 */
function sourcetag_sanitise_cookie_value($value) {
    if (strlen($value) > 4096) {
        return false;
    }

    // URL-decode first (JS sends encodeURIComponent, $_COOKIE is already decoded by PHP)
    $value = urldecode($value);

    // JSON round-trip to strip any injected characters
    $parsed = json_decode($value, true);
    if ($parsed === null && $value !== 'null') {
        return false;
    }

    // Re-encode to strip any injected characters
    return wp_json_encode($parsed);
}

/**
 * Add settings link on plugins page
 */
function sourcetag_plugin_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=sourcetag')) . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sourcetag_plugin_links');
