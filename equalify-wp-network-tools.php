<?php
/**
 * Plugin Name: Equalify WP Network Tools
 * Description: Tools to help use Equalify with WP Multisite.
 * Version: 1.0
 * Author: Blake
 * Network: true
 */

if (!defined('ABSPATH')) exit;

// Add "Settings" link in Network Plugins list
add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $links[] = '<a href="' . network_admin_url('plugins.php?page=equalify_wp_network_tools') . '">Settings</a>';
    return $links;
});

// Register admin page
add_action('network_admin_menu', function () {
    add_submenu_page(
        null,
        'Equalify Network Tools Settings',
        'Equalify Network Tools',
        'manage_network',
        'equalify_wp_network_tools',
        'render_equalify_network_tools_page'
    );
});

// Render admin UI
function render_equalify_network_tools_page() {
    $csv_path = get_site_option('equalify_network_tools_csv_path');
    echo '<div class="wrap"><h1>Equalify Network Tools</h1>';

    if (isset($_POST['generate_report'])) {
        $timestamp = time();
        wp_clear_scheduled_hook('equalify_network_tools_generate');
        wp_schedule_single_event($timestamp + 5, 'equalify_network_tools_generate');
        echo '<p>Scan scheduled. Reload this page in a few minutes to download your report.</p>';
    }

    echo '<form method="post">';
    submit_button('Generate Report', 'primary', 'generate_report');
    echo '</form>';

    if ($csv_path && file_exists($csv_path)) {
        $download_url = content_url(str_replace(WP_CONTENT_DIR, '', $csv_path));
        echo '<p><strong>Latest Report:</strong> <a href="' . esc_url($download_url) . '" download>Download CSV</a></p>';
    }

    echo '</div>';
}

// Register cron job
add_action('equalify_network_tools_generate', 'run_equalify_network_tools_report');

function run_equalify_network_tools_report() {
    $csv_rows = [['Site', 'Title', 'Post URL', 'Matched URL']];
    $sites = get_sites(['number' => 0]);

    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);

        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        foreach ($posts as $post) {
            preg_match_all('/href=["\']([^"\']+\.(pdf|box\.com)[^"\']*)["\']/i', $post->post_content, $matches);
            foreach ($matches[1] as $match) {
                $csv_rows[] = [
                    get_bloginfo('name'),
                    $post->post_title,
                    get_permalink($post),
                    $match
                ];
            }
        }

        restore_current_blog();
    }

    // Save CSV
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/equalify-network-report.csv';

    $fp = fopen($file_path, 'w');
    foreach ($csv_rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    update_site_option('equalify_network_tools_csv_path', $file_path);
}
