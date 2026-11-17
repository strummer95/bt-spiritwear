<?php
/**
 * BT Spirit Wear — self-updater (GitHub API, no CDN delay).
 * Same mechanism as the other BT plugins: manifest.json read through api.github.com
 * (instant on push), releases ship uniquely-named zips so URLs are never stale.
 */
if (!defined('ABSPATH')) exit;

function btsw_gh_repo() {
    return 'strummer95/bt-spiritwear';
}

/** Read the manifest through the GitHub API (instant; reflects latest push). */
function btsw_update_manifest() {
    $cached = get_transient('btsw_manifest');
    if ($cached !== false) return $cached;

    $url  = 'https://api.github.com/repos/' . btsw_gh_repo() . '/contents/manifest.json';
    $resp = wp_remote_get($url, array(
        'timeout' => 10,
        'headers' => array(
            'Accept'     => 'application/vnd.github.raw',
            'User-Agent' => 'BT-SpiritWear-Updater',
        ),
    ));

    $info = array();
    if (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200) {
        $j = json_decode(wp_remote_retrieve_body($resp), true);
        if (is_array($j)) $info = $j;
    }
    set_transient('btsw_manifest', $info, 30 * MINUTE_IN_SECONDS);
    return $info;
}

/**
 * The only place an update may come from: a .zip in this plugin's own repo on
 * raw.githubusercontent.com. A manifest that names any other host or repo is
 * ignored, so a tampered manifest cannot hand WordPress someone else's code.
 */
function btsw_update_package_ok($url) {
    $url  = (string) $url;
    $base = 'https://raw.githubusercontent.com/' . btsw_gh_repo() . '/';
    return strpos($url, '..') === false
        && preg_match('#^' . preg_quote($base, '#') . '[A-Za-z0-9._/-]+\.zip$#', $url) === 1;
}

add_filter('pre_set_site_transient_update_plugins', 'btsw_push_update');
function btsw_push_update($transient) {
    if (!is_object($transient) || empty($transient->checked)) return $transient;
    $info = btsw_update_manifest();
    if (empty($info['version']) || empty($info['download_url'])) return $transient;
    if (!btsw_update_package_ok($info['download_url'])) return $transient;

    $file = plugin_basename(BTSW_FILE);
    if (version_compare($info['version'], BTSW_VERSION, '>')) {
        $transient->response[$file] = (object) array(
            'slug'        => 'bt-spiritwear',
            'plugin'      => $file,
            'new_version' => $info['version'],
            'package'     => $info['download_url'],
            'url'         => isset($info['homepage']) ? $info['homepage'] : '',
            'tested'      => isset($info['tested']) ? $info['tested'] : '',
        );
    }
    return $transient;
}

function btsw_force_update_check() {
    delete_transient('btsw_manifest');
    delete_site_transient('update_plugins');
}

add_filter('plugins_api', 'btsw_update_info', 20, 3);
function btsw_update_info($res, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'bt-spiritwear') return $res;
    $info = btsw_update_manifest();
    if (empty($info)) return $res;
    $o = new stdClass();
    $o->name          = 'BT Spirit Wear';
    $o->slug          = 'bt-spiritwear';
    $o->version       = isset($info['version']) ? $info['version'] : BTSW_VERSION;
    $o->author        = 'Duck and Rabbit Co.';
    $o->homepage      = isset($info['homepage']) ? $info['homepage'] : '';
    $o->download_link = (isset($info['download_url']) && btsw_update_package_ok($info['download_url'])) ? $info['download_url'] : '';
    $o->tested        = isset($info['tested']) ? $info['tested'] : '';
    $o->sections      = array('changelog' => isset($info['changelog']) ? $info['changelog'] : '');
    return $o;
}
