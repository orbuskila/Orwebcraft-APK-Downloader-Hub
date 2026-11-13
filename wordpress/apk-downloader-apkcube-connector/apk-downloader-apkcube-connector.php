<?php
/**
 * Plugin Name: APK Downloader 1.0.0 → APKCube + APKCombo Connector
 * Description: Repairs the original APK Downloader 1.0.0 form by connecting its existing shortcode to APKCube's app card plus APKCombo's downloader.
 * Version: 1.3.1
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Author: OR Webcraft
 */
if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', function () {
    // The original plugin registers this script at priority 10. Keep its shortcode,
    // form HTML, settings, and stylesheet; replace only the failed remote request.
    if (!shortcode_exists('apk_downloader_form')) { return; }
    $scripts = wp_scripts();
    if (isset($scripts->registered['mdc_custom'])) {
        $src = (string) $scripts->registered['mdc_custom']->src;
        if (strpos($src, '/apk-downloader/js/custom.js') !== false) {
            wp_dequeue_script('mdc_custom');
            wp_deregister_script('mdc_custom');
        }
    }
    wp_enqueue_script(
        'orw-apkcube-connector',
        plugins_url('assets/connector.js', __FILE__),
        array(),
        '1.3.1',
        true
    );
}, 100);


// Render a usable link with the original shortcode even if scripts are blocked or delayed.
add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'apk_downloader_form') { return $output; }
    $link = '<div class="orw-apkcombo-always" style="margin:16px 0;padding:12px;border:1px solid #b7d9c9;border-radius:8px">'
        . '<a class="orw-apkcombo-always-link" href="https://apkcombo.com/downloader/" target="_blank" rel="noopener noreferrer" '
        . 'style="display:inline-block;padding:10px 16px;background:#00875f;color:#fff;text-decoration:none;border-radius:6px;font-weight:700">'
        . 'Open APKCombo Downloader</a>'
        . '<p style="margin:8px 0 0">Enter an app above to open its APKCombo downloader page. APKCombo creates the file link there.</p></div>';
    if (strpos($output, '</form>') !== false) {
        return preg_replace('~</form>~', '</form>' . $link, $output, 1);
    }
    return $output . $link;
}, 20, 2);

add_action('admin_notices', function () {
    if (!current_user_can('activate_plugins')) { return; }
    if (!class_exists('APK_Downloader', false)) {
        echo '<div class="notice notice-warning"><p>APKCube Connector needs the original APK Downloader 1.0.0 plugin activated. Keep its existing [apk_downloader_form] shortcode.</p></div>';
        return;
    }
    $plugin_file = WP_PLUGIN_DIR . '/apk-downloader/apk-downloader.php';
    if (is_readable($plugin_file)) {
        $data = get_file_data($plugin_file, array('Version' => 'Version'));
        if (!isset($data['Version']) || $data['Version'] !== '1.0.0') {
            echo '<div class="notice notice-warning"><p>APKCube Connector was made for the original APK Downloader 1.0.0. The installed APK Downloader has a different version; restore 1.0.0 before using this connector.</p></div>';
        }
    }
});
