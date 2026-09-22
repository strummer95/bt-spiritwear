<?php
/**
 * BT Spirit Wear — admin page (status + updates).
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page('BT Spirit Wear', 'BT Spirit Wear', 'manage_options', 'bt-spiritwear', 'btsw_admin_page', 'dashicons-megaphone', 58);
});

/** Sit with the other BT tools in the sidebar (grouping lives in BT Quote). */
add_filter('bt_menu_group_order', function ($w) {
    $w = (array) $w;
    if (!in_array('bt spirit wear', $w, true)) $w[] = 'bt spirit wear';
    sort($w);
    return $w;
});

function btsw_admin_page() {
    if (!current_user_can('manage_options')) return;

    $snippet = btsw_snippet_active();
    $live    = !$snippet && shortcode_exists('bt_spiritwear');

    echo '<div class="wrap"><h1>BT Spirit Wear</h1>';
    echo '<p>The spirit wear store landing page. Put <code>[bt_spiritwear]</code> on a page (it lives on <a href="' . esc_url(home_url('/spiritwear/')) . '" target="_blank">/spiritwear/</a>). Leads are emailed to <strong>' . esc_html(btsw_lead_email()) . '</strong>.</p>';

    echo '<h2>Status</h2><table class="widefat" style="max-width:640px"><tbody>';
    echo '<tr><td>Landing page</td><td>';
    if ($live) {
        echo '<span style="color:#1a7f37;font-weight:700">Running from this plugin</span>';
    } elseif ($snippet) {
        echo '<span style="color:#b26d00;font-weight:700">Standing by</span> &nbsp;The old snippet is still active in Code Snippets. Deactivate it and this plugin takes over.';
    } else {
        echo '<span style="color:#b91c1c;font-weight:700">Not registered</span>';
    }
    echo '</td></tr>';
    echo '</tbody></table>';

    echo '<div style="margin-top:28px">';
    bt_admin_updates_panel(array(
        'slug'     => 'bt-spiritwear',
        'version'  => BTSW_VERSION,
        'manifest' => 'btsw_update_manifest',
        'flush'    => 'btsw_force_update_check',
    ));
    echo '</div></div>';
}
