<?php
/*
Plugin Name: BT Spirit Wear
Plugin URI: https://boomerts.com
Description: Boomer T's spirit wear store landing page and lead form. Provides the [bt_spiritwear] shortcode used on /spiritwear/ and emails each lead to orders@boomerts.com.
Version: 1.0.0
Author: Duck and Rabbit Co.
*/

if (!defined('ABSPATH')) exit;

define('BTSW_VERSION', '1.0.0');
define('BTSW_DIR', plugin_dir_path(__FILE__));
define('BTSW_FILE', __FILE__);

/** Where lead notifications go. Filterable. */
function btsw_lead_email() {
    return apply_filters('btsw_lead_email', 'orders@boomerts.com');
}

require_once BTSW_DIR . 'includes/landing.php';
require_once BTSW_DIR . 'includes/bt-admin.php';
require_once BTSW_DIR . 'includes/admin.php';
require_once BTSW_DIR . 'includes/updater.php';

/**
 * Is the old Code Snippets version still switched on?
 *
 * Both define [bt_spiritwear] and both handle the same form post, so if both
 * ran, every lead would be emailed twice. While the snippet is active this
 * plugin stays completely idle and shows a notice instead. Deactivate the
 * snippet and the plugin takes over on the next page load, with no gap where
 * the page is broken.
 */
function btsw_snippet_active() {
    return function_exists('bt_spiritwear_render') || function_exists('bt_spiritwear_handle_post');
}

add_action('init', 'btsw_boot', 1);
function btsw_boot() {
    if (btsw_snippet_active()) return;
    add_shortcode('bt_spiritwear', 'btsw_render');
    add_action('init', 'btsw_handle_post', 10);
    add_filter('request', 'btsw_strip_post_query_vars');
}

add_action('admin_notices', function () {
    if (!current_user_can('manage_options') || !btsw_snippet_active()) return;
    echo '<div class="notice notice-warning"><p><strong>BT Spirit Wear is waiting.</strong> '
       . 'The old Spirit Wear snippet is still active in Code Snippets, so the plugin is standing by to avoid sending every lead twice. '
       . 'Deactivate that snippet and the plugin takes over automatically.</p></div>';
});
