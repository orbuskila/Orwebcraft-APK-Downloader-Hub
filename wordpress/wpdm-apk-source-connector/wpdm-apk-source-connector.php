<?php
/**
 * Plugin Name: WPDM APK Source Connector
 * Description: Syncs Android app submissions and a managed app list to WPDM packages in the site's Downloads archive.
 * Version: 2.3.3
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

final class OWC_WPDM_APK_Connector {
    const SETTINGS = 'owc_apk_wpdm_settings';
    const HOOK = 'owc_apk_wpdm_hourly';
    const META = '_owc_apk_connector_id';
    const LOCK = 'owc_apk_wpdm_lock';
    public static function init() {
        if (get_option('owc_apk_wpdm_bridge_version') !== '2.0.1') {
            update_option('owc_apk_wpdm_bridge_version', '2.0.1', false);
            delete_option('owc_apk_wpdm_repair_offset');
            if (!wp_next_scheduled('owc_apk_wpdm_repair')) wp_schedule_single_event(time() + 45, 'owc_apk_wpdm_repair');
            if (!wp_next_scheduled('owc_apk_wpdm_publish_pending')) wp_schedule_single_event(time() + 30, 'owc_apk_wpdm_publish_pending');
            add_action('init', array(__CLASS__, 'publish_pending'), 30);
            wp_clear_scheduled_hook('owc_apk_wpdm_nature_seed');
            delete_option('owc_apk_wpdm_nature_queue');
        }
        add_action('owc_apk_wpdm_publish_pending', array(__CLASS__, 'publish_pending'));
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_owc_apk_wpdm_save', array(__CLASS__, 'save'));
        add_action('admin_post_owc_apk_wpdm_sync', array(__CLASS__, 'run'));
        add_action('admin_post_owc_apk_wpdm_add_apps', array(__CLASS__, 'add_curated_apps'));
        add_action('admin_post_owc_apk_wpdm_repair', array(__CLASS__, 'run_repair'));
        add_action('admin_post_owc_apk_wpdm_refresh', array(__CLASS__, 'refresh_one'));
        add_action('admin_post_owc_apk_wpdm_sync_companies', array(__CLASS__, 'sync_companies'));
        add_action('admin_post_owc_apk_wpdm_front_submit', array(__CLASS__, 'front_submit'));
        add_action('admin_post_nopriv_owc_apk_wpdm_front_submit', array(__CLASS__, 'front_submit'));
        add_filter('do_shortcode_tag', array(__CLASS__, 'downloader_controls'), 99, 4);
        add_action(self::HOOK, array(__CLASS__, 'sync'));
        add_action('owc_apk_wpdm_repair', array(__CLASS__, 'repair'));
        add_action('save_post_wpdmpro', array(__CLASS__, 'protect_status'), 999, 3);
        add_action('add_meta_boxes_wpdmpro', array(__CLASS__, 'description_box'));
        add_action('save_post_wpdmpro', array(__CLASS__, 'save_description_box'), 30, 3);
        add_filter('template_include', array(__CLASS__, 'app_template'), 999);
        add_action('wp_ajax_owc_apk_wpdm_capture', array(__CLASS__, 'capture'));
        add_action('wp_ajax_nopriv_owc_apk_wpdm_capture', array(__CLASS__, 'capture'));
        add_action('rest_api_init', array(__CLASS__, 'register_capture_route'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'scripts'), 110);
        add_shortcode('owc_apk_downloads', array(__CLASS__, 'catalog'));
        add_action('admin_notices', array(__CLASS__, 'notice'));
        if (!wp_next_scheduled(self::HOOK)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::HOOK);
    }
    public static function activate() {
        $settings = self::settings();
        $settings['capture'] = 1;
        update_option(self::SETTINGS, $settings, false);
        if (!wp_next_scheduled(self::HOOK)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::HOOK);
    }
    public static function deactivate() { wp_clear_scheduled_hook(self::HOOK); wp_clear_scheduled_hook('owc_apk_archive_maintenance'); wp_clear_scheduled_hook('owc_apk_wpdm_repair'); wp_clear_scheduled_hook('owc_apk_wpdm_nature_seed'); }
    private static function settings() {
        return wp_parse_args((array) get_option(self::SETTINGS, array()), array('apps' => '', 'capture' => 1, 'form_url' => ''));
    }
    public static function package_id($input) {
        $input = trim((string) $input);
        if (preg_match('~^https://play\.google\.com/store/apps/details(?:\?|/|$)~i', $input)) {
            $query = (string) wp_parse_url($input, PHP_URL_QUERY);
            parse_str($query, $parts);
            $input = isset($parts['id']) && is_string($parts['id']) ? $parts['id'] : '';
        }
        elseif (preg_match('~^https://(?:www\.)?apkcombo\.com/~i', $input)) {
            $path = (string) wp_parse_url($input, PHP_URL_PATH);
            $input = preg_match('~/([A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+)/?$~D', $path, $found) ? $found[1] : '';
        }
        return strlen($input) <= 180 && preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/D', $input) ? $input : '';
    }
    private static function apkcombo_app_url($url, $id) {
        $url = trim((string) $url);
        if (!preg_match('~^https://(?:www\.)?apkcombo\.com/~i', $url)) return '';
        if (strcasecmp((string) self::package_id($url), $id) !== 0) return '';
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return 'https://apkcombo.com' . $path;
    }
    private static function entries($raw) {
        $entries = array();
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $id = self::package_id($parts[0]);
            if ($id) $entries[strtolower($id)] = array($id, isset($parts[1]) ? sanitize_text_field($parts[1]) : '');
            if (count($entries) >= 250) break;
        }
        return $entries;
    }
    private static function curated_apps() {
        return array(
            'org.inaturalist.android' => 'iNaturalist',
            'org.inaturalist.seek' => 'Seek by iNaturalist',
            'com.labs.merlinbirdid.app' => 'Merlin Bird ID by Cornell Lab',
            'edu.cornell.birds.ebird' => 'eBird by Cornell Lab',
            'org.plantnet' => 'PlantNet Plant Identification',
            'edu.uga.engr.geolog.marinedebristrack' => 'Marine Debris Tracker',
            'gov.nasa.globe.observer' => 'GLOBE Observer',
            'gov.nasa.jpl.earthnow.activity' => 'Earth-Now',
            'com.google.earth' => 'Google Earth',
            'gov.nasa' => 'NASA',
            'com.vitotechnology.StarWalk2Free' => 'Star Walk 2 Plus',
            'com.vitotechnology.SolarWalk2' => 'Solar Walk 2',
            'com.windyty.android' => 'Windy.com',
        );
    }
    public static function menu() {
        add_options_page('WPDM APK Connector', 'WPDM APK Connector', 'manage_options', 'owc-apk-wpdm', array(__CLASS__, 'page'));
    }
    public static function notice() {
        if (current_user_can('manage_options') && !post_type_exists('wpdmpro')) echo '<div class="notice notice-warning"><p>WPDM APK Connector needs WordPress Download Manager activated.</p></div>';
        if (isset($_GET['owc_refresh']) && current_user_can('edit_posts')) echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['owc_refresh']))) . '</p></div>';
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $settings = self::settings();
        echo '<div class="wrap"><h1>WPDM APK Connector</h1>';
        if (isset($_GET['owc_result'])) echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['owc_result']))) . '</p></div>';
        echo '<p>Valid app submissions are published to the Downloads archive automatically. Enter one Android package name or Google Play URL per line, with an optional title after |. The plugin tries to fetch the app name and icon from Google Play. No APK binary is imported.</p>';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="owc_apk_wpdm_save">';
        wp_nonce_field('owc_apk_wpdm_save');
        echo '<p><label for="owc_apps"><strong>Apps</strong></label></p><textarea id="owc_apps" name="apps" rows="12" cols="85" class="large-text code" placeholder="com.example.app | App title">' . esc_textarea($settings['apps']) . '</textarea>';
        echo '<p><label for="owc_form_url">Your APK Downloader form page URL (optional)</label><br><input type="url" class="regular-text" id="owc_form_url" name="form_url" value="' . esc_attr($settings['form_url']) . '" placeholder="' . esc_attr(home_url('/apk-downloader/')) . '"></p>';
        echo '<p><label><input type="checkbox" name="capture" value="1" ' . checked($settings['capture'], 1, false) . '> Automatically publish valid submissions from [apk_downloader_form] to WPDM Downloads.</label></p>';
        submit_button('Save and sync'); echo '</form>';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="owc_apk_wpdm_sync">';
        wp_nonce_field('owc_apk_wpdm_sync'); submit_button('Sync now', 'secondary'); echo '</form>';
        echo '<h2>Developer/company names</h2><p>Refresh existing connector apps from Google Play in batches of 5. Each click processes the next batch. Names unavailable from Google Play remain editable in each package’s App details.</p>';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="owc_apk_wpdm_sync_companies">';
        wp_nonce_field('owc_apk_wpdm_sync_companies');
        echo '<input type="hidden" name="offset" value="' . esc_attr((int) get_option('owc_apk_company_offset', 0)) . '">';
        submit_button('Sync developer names from Google Play', 'secondary'); echo '</form>';
        echo '<h2>Add nature and space apps from Google Play</h2><p>This adds only missing package IDs to your managed list and syncs them to the Downloads archive. Existing entries and manually edited titles are kept. Review the list before clicking:</p><ul>';
        foreach (self::curated_apps() as $id => $title) echo '<li>' . esc_html($title) . ' <code>' . esc_html($id) . '</code> · <a href="' . esc_url(self::play_url($id)) . '" target="_blank" rel="noopener noreferrer">Google Play</a></li>';
        echo '</ul><form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="owc_apk_wpdm_add_apps">';
        wp_nonce_field('owc_apk_wpdm_add_apps'); submit_button('Add these apps and sync', 'secondary'); echo '</form>';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post"><input type="hidden" name="action" value="owc_apk_wpdm_repair">';
        wp_nonce_field('owc_apk_wpdm_repair'); submit_button('Repair imported apps', 'secondary'); echo '</form>';
        echo '<p>The connector adds a Connect and sync with archive button inside the existing <code>[apk_downloader_form]</code>. The connector-only <code>[owc_apk_downloads]</code> list displays published packages. Packages manually set to Private remain private.</p></div>';
    }
    private static function redirect($message) {
        wp_safe_redirect(add_query_arg(array('page' => 'owc-apk-wpdm', 'owc_result' => rawurlencode($message)), admin_url('options-general.php')));
        exit;
    }
    public static function save() {
        if (!current_user_can('manage_options') || !check_admin_referer('owc_apk_wpdm_save')) wp_die('Access denied');
        $raw = isset($_POST['apps']) && is_string($_POST['apps']) ? wp_unslash($_POST['apps']) : '';
        $entries = self::entries(substr($raw, 0, 35000));
        $lines = array();
        foreach ($entries as $entry) $lines[] = $entry[0] . ($entry[1] ? ' | ' . $entry[1] : '');
        $form_url = isset($_POST['form_url']) && is_string($_POST['form_url']) ? esc_url_raw(wp_unslash($_POST['form_url'])) : '';
        if ($form_url && (wp_parse_url($form_url, PHP_URL_SCHEME) !== wp_parse_url(home_url(), PHP_URL_SCHEME) || strtolower((string) wp_parse_url($form_url, PHP_URL_HOST)) !== strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)))) self::redirect('The APK Downloader form URL must be on this site.');
        update_option(self::SETTINGS, array('apps' => implode("\n", $lines), 'capture' => !empty($_POST['capture']) ? 1 : 0, 'form_url' => $form_url), false);
        $result = self::sync();
        self::redirect(is_wp_error($result) ? $result->get_error_message() : sprintf('Saved. %d created, %d updated.', $result['created'], $result['updated']));
    }
    public static function run() {
        if (!current_user_can('manage_options') || !check_admin_referer('owc_apk_wpdm_sync')) wp_die('Access denied');
        $result = self::sync();
        self::redirect(is_wp_error($result) ? $result->get_error_message() : sprintf('%d created, %d updated.', $result['created'], $result['updated']));
    }
    public static function sync_companies() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options') || !check_admin_referer('owc_apk_wpdm_sync_companies')) wp_die('Access denied');
        if (!post_type_exists('wpdmpro')) self::redirect('Activate WordPress Download Manager first.');
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $ids = get_posts(array('post_type' => 'wpdmpro', 'post_status' => 'any', 'meta_key' => self::META, 'fields' => 'ids', 'numberposts' => 5, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC'));
        $found = 0;
        foreach ($ids as $post_id) {
            $id = self::package_id(get_post_meta($post_id, '_owc_apk_package', true));
            if (!$id) continue;
            delete_transient('owc_play_' . md5($id));
            $meta = self::play_meta($id);
            if ($meta['company']) update_post_meta($post_id, '_owc_apk_company', $meta['company']);
            if (get_post_meta($post_id, '_owc_apk_company_manual', true) || get_post_meta($post_id, '_owc_apk_company', true)) ++$found;
        }
        if (count($ids) === 5) {
            update_option('owc_apk_company_offset', $offset + 5, false);
            self::redirect(sprintf('Processed apps %d–%d; %d have a developer name. Click Sync developer names again for the next batch.', $offset + 1, $offset + count($ids), $found));
        }
        delete_option('owc_apk_company_offset');
        self::redirect(sprintf('Finished the remaining %d apps; %d have a developer name. Names unavailable from Google Play can be entered in App details.', count($ids), $found));
    }
    public static function add_curated_apps() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options') || !check_admin_referer('owc_apk_wpdm_add_apps')) wp_die('Access denied');
        if (!post_type_exists('wpdmpro')) self::redirect('Activate WordPress Download Manager first.');
        if (get_transient(self::LOCK)) self::redirect('A sync is already running. Try again shortly.');
        $settings = self::settings();
        $existing = self::entries($settings['apps']);
        $added = 0;
        foreach (self::curated_apps() as $id => $title) {
            if (isset($existing[strtolower($id)]) || count($existing) >= 250) continue;
            $existing[strtolower($id)] = array($id, $title);
            ++$added;
        }
        $lines = array();
        foreach ($existing as $entry) $lines[] = $entry[0] . ($entry[1] ? ' | ' . $entry[1] : '');
        $settings['apps'] = implode("\n", $lines);
        update_option(self::SETTINGS, $settings, false);
        $result = self::sync();
        self::redirect(is_wp_error($result) ? sprintf('%d package IDs added; sync: %s', $added, $result->get_error_message()) : sprintf('%d package IDs added; %d listings created, %d updated, %d failed.', $added, $result['created'], $result['updated'], $result['failed']));
    }
    public static function run_repair() {
        if (!current_user_can('manage_options') || !check_admin_referer('owc_apk_wpdm_repair')) wp_die('Access denied');
        self::repair();
        self::redirect('Processed a batch of imported apps. Remaining batches continue through WP-Cron.');
    }
    public static function refresh_one() {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id) || !check_admin_referer('owc_apk_refresh_' . $post_id)) wp_die('Access denied');
        $id = self::package_id(get_post_meta($post_id, '_owc_apk_package', true));
        if (!$id || get_post_type($post_id) !== 'wpdmpro') wp_die('Not an imported app');
        delete_transient('owc_play_' . md5($id));
        $result = self::upsert($id);
        $message = is_wp_error($result) ? $result->get_error_message() : 'App information refreshed. Fields unavailable from Google Play can be entered in App details.';
        wp_safe_redirect(add_query_arg(array('post' => $post_id, 'action' => 'edit', 'owc_refresh' => rawurlencode($message)), admin_url('post.php')));
        exit;
    }
    private static function links($id, $apkcombo = '') {
        $links = array();
        $form_url = self::settings()['form_url'];
        if ($form_url) $links['APK Downloader on this site'] = $form_url;
        $links['APKCube'] = 'https://apkcube.com/go?q=' . rawurlencode($id) . '&to=download';
        $links['APKCombo app page'] = $apkcombo ?: 'https://apkcombo.com/downloader/';
        return $links;
    }
    private static function play_url($id) {
        return 'https://play.google.com/store/apps/details?id=' . rawurlencode($id);
    }
    public static function play_meta($id) {
        $key = 'owc_play_' . md5($id);
        $cached = get_transient($key);
        if (is_array($cached) && !empty($cached['image']) && !empty($cached['screenshots']) && isset($cached['android'], $cached['updated'], $cached['category'], $cached['description'], $cached['screenshots'], $cached['version'], $cached['about'], $cached['company'])) return $cached;
        $data = array('android' => '', 'updated' => '', 'category' => '', 'title' => '', 'image' => '', 'description' => '', 'about' => '', 'whats_new' => '', 'version' => '', 'company' => '', 'support_email' => '', 'support_url' => '', 'screenshots' => array());
        $response = wp_safe_remote_get(self::play_url($id) . '&hl=en', array('timeout' => 10, 'redirection' => 2, 'limit_response_size' => 1500000, 'headers' => array('Accept-Language' => 'en')));
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $html = wp_remote_retrieve_body($response);
            if (preg_match_all('/<meta\s+[^>]*>/i', $html, $tags)) {
                foreach ($tags[0] as $tag) {
                    if (!preg_match('/\b(?:property|name|itemprop)=["\'](og:title|og:image|og:description|twitter:description|description)["\']/i', $tag, $kind) || !preg_match('/\bcontent=["\']([^"\']+)["\']/i', $tag, $value)) continue;
                    $text = html_entity_decode($value[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (strtolower($kind[1]) === 'og:title') $data['title'] = sanitize_text_field(preg_replace('/\s+[-–]\s+Apps on Google Play\s*$/i', '', $text));
                    if (strtolower($kind[1]) === 'og:image' && wp_parse_url($text, PHP_URL_SCHEME) === 'https' && strtolower((string) wp_parse_url($text, PHP_URL_HOST)) === 'play-lh.googleusercontent.com') $data['image'] = esc_url_raw($text);
                    if (in_array(strtolower($kind[1]), array('og:description', 'twitter:description', 'description'), true) && !$data['description']) $data['description'] = wp_html_excerpt(sanitize_text_field($text), 450, '…');
                }
            }
            if (preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $scripts)) {
                $walk = function ($node) use (&$walk, &$data) {
                    if (!is_array($node)) return;
                    $type = isset($node['@type']) && is_string($node['@type']) ? $node['@type'] : '';
                    if (in_array($type, array('SoftwareApplication', 'MobileApplication'), true)) {
                        foreach (array('dateModified' => 'updated', 'applicationCategory' => 'category', 'operatingSystem' => 'android') as $source_key => $target_key) {
                            if (!empty($node[$source_key]) && is_string($node[$source_key])) $data[$target_key] = self::clean_detail($node[$source_key], 100);
                        }
                        foreach (array('author', 'publisher') as $company_key) {
                            $source = isset($node[$company_key]) ? $node[$company_key] : null;
                            $name = is_array($source) && isset($source['name']) ? $source['name'] : $source;
                            if (!$data['company'] && is_string($name)) $data['company'] = self::clean_detail($name, 160);
                        }
                        if (!empty($node['description']) && is_string($node['description'])) {
                            $about = self::clean_detail($node['description'], 6000);
                            if (strlen($about) > strlen($data['about'])) $data['about'] = $about;
                        }
                        if (!$data['version'] && !empty($node['softwareVersion']) && is_scalar($node['softwareVersion'])) $data['version'] = self::clean_detail((string) $node['softwareVersion'], 80);
                        if (!$data['whats_new'] && !empty($node['releaseNotes']) && is_string($node['releaseNotes'])) $data['whats_new'] = self::clean_detail($node['releaseNotes'], 3000);
                    }
                    foreach ($node as $child) if (is_array($child)) $walk($child);
                };
                foreach ($scripts[1] as $json) $walk(json_decode($json, true));
            }
            // The developer link appears near the title on Play. Read it from
            // the source too, for hosts where PHP's DOM extension is unavailable.
            if (!$data['company'] && preg_match_all('~<a\b(?=[^>]*\bhref=["\']([^"\']*(?:/store/apps/(?:developer|dev)(?:\?|/))[^"\']*)["\'])[^>]*>(.*?)</a>~is', $html, $developer_links, PREG_SET_ORDER)) {
                foreach ($developer_links as $link) {
                    $name = self::clean_detail($link[2], 160);
                    if ($name) { $data['company'] = $name; break; }
                }
            }
            // Prefer the full listing text to the social preview when structured data supplies it.
            if (!$data['description'] && $data['about']) $data['description'] = wp_html_excerpt(sanitize_text_field($data['about']), 450, '…');
            if (class_exists('DOMDocument')) self::read_detail_sections($html, $data);
            if (!$data['description'] && $data['about']) $data['description'] = wp_html_excerpt(sanitize_text_field($data['about']), 450, '…');
            if (preg_match_all('/<img\s+[^>]*>/i', $html, $images)) {
                foreach ($images[0] as $tag) {
                    if (!preg_match('/\balt=["\']([^"\']+)["\']/i', $tag, $alt)) continue;
                    $is_icon = !$data['image'] && strcasecmp($alt[1], 'Icon image') === 0;
                    if (!$is_icon && stripos($alt[1], 'screenshot') === false) continue;
                    if (!preg_match_all('/\s(?:src|data-src|srcset|data-srcset)=["\']([^"\']+)["\']/i', $tag, $sources)) continue;
                    foreach ($sources[1] as $source) {
                        $source = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        foreach (preg_split('/[\s,]+/', trim($source)) as $url) {
                            if (!self::is_play_image($url)) continue;
                            if ($is_icon) {
                                $data['image'] = esc_url_raw($url);
                            } else {
                                $data['screenshots'][strtok($url, '=')] = esc_url_raw($url);
                            }
                            break 2;
                        }
                    }
                    if (count($data['screenshots']) >= 4 && $data['image']) break;
                }
                $data['screenshots'] = array_slice(array_values($data['screenshots']), 0, 4);
            }
        }
        if ($data['image'] && $data['screenshots']) set_transient($key, $data, $data['company'] ? DAY_IN_SECONDS : 2 * HOUR_IN_SECONDS);
        return $data;
    }
    private static function clean_detail($value, $limit) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\t ]+/', ' ', str_replace(array("\r\n", "\r"), "\n", $value));
        $value = preg_replace('/\n{3,}/', "\n\n", trim($value));
        return wp_html_excerpt($value, $limit, '…');
    }
    private static function detail_node_text($node) {
        if ($node->nodeType === XML_TEXT_NODE) return $node->nodeValue;
        if (!($node instanceof DOMElement)) return '';
        if (strtolower($node->tagName) === 'br') return "\n";
        $text = '';
        foreach ($node->childNodes as $child) $text .= self::detail_node_text($child);
        return $text;
    }
    private static function read_detail_sections($html, &$data) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$loaded) return;
        $xpath = new DOMXPath($dom);
        if (!$data['company']) {
            foreach ($xpath->query('//a[@href]') as $anchor) {
                $href = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!preg_match('~(?:^|/)store/apps/(?:developer|dev)(?:\?|/)~', $href)) continue;
                $name = self::clean_detail($anchor->textContent, 160);
                if ($name) { $data['company'] = $name; break; }
            }
        }
        foreach ($xpath->query('//h2|//h3') as $heading) {
            $label = strtolower(trim($heading->textContent));
            $kind = '';
            if ($label === 'about this app') $kind = 'about';
            elseif ($label === "what's new" || $label === 'what’s new') $kind = 'whats_new';
            elseif ($label === 'app support') $kind = 'support';
            if (!$kind) continue;
            // Play nests headings in a header; the actual text and links are in the section body.
            $section = $heading->parentNode;
            while ($section && !($section instanceof DOMElement && strtolower($section->tagName) === 'section')) $section = $section->parentNode;
            if (!$section) continue;
            if ($kind === 'about' || $kind === 'whats_new') {
                $body = $xpath->query('.//*[@data-g-id="description" or @data-g-id="recent-changes"]', $section)->item(0);
                if ($body) {
                    $content = self::clean_detail(self::detail_node_text($body), $kind === 'about' ? 6000 : 3000);
                    if ($kind === 'about' && strlen($content) > strlen($data['about'])) $data['about'] = $content;
                    if ($kind === 'whats_new' && $content) $data['whats_new'] = $content;
                }
            }
            if ($kind === 'support') {
                foreach ($xpath->query('.//a[@href]', $section) as $anchor) {
                    $href = trim($anchor->getAttribute('href'));
                    $link_label = strtolower($anchor->getAttribute('aria-label') . ' ' . $anchor->textContent);
                    if (!$data['support_email'] && strpos($link_label, 'support email') !== false && stripos($href, 'mailto:') === 0) {
                        $email = sanitize_email(rawurldecode(strtok(substr($href, 7), '?')));
                        if (is_email($email)) $data['support_email'] = $email;
                    }
                    if (!$data['support_url'] && strpos($link_label, 'website') !== false && wp_parse_url($href, PHP_URL_SCHEME) === 'https') $data['support_url'] = esc_url_raw($href);
                }
            }
        }
    }
    private static function is_play_image($url) {
        return is_string($url) && wp_parse_url($url, PHP_URL_SCHEME) === 'https'
            && strtolower((string) wp_parse_url($url, PHP_URL_HOST)) === 'play-lh.googleusercontent.com';
    }
    private static function available_play_image($url) {
        if (!self::is_play_image($url)) return false;
        $response = wp_safe_remote_get($url, array('timeout' => 5, 'redirection' => 0, 'limit_response_size' => 65536));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;
        $body = wp_remote_retrieve_body($response);
        $info = function_exists('getimagesizefromstring') ? @getimagesizefromstring($body) : false;
        return $info && in_array($info['mime'], array('image/jpeg', 'image/png', 'image/webp'), true);
    }
    private static function attach_icon($post_id, $id, $url) {
        if (!$url || has_post_thumbnail($post_id)) return;
        $response = wp_safe_remote_get($url, array('timeout' => 10, 'redirection' => 2, 'limit_response_size' => 1024 * 1024));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return;
        $body = wp_remote_retrieve_body($response);
        if (strlen($body) > 1024 * 1024 || !function_exists('getimagesizefromstring')) return;
        $info = @getimagesizefromstring($body);
        $types = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp');
        if (!$info || !isset($types[$info['mime']]) || $info[0] > 2048 || $info[1] > 2048) return;
        $uploaded = wp_upload_bits(sanitize_file_name($id . '-icon.' . $types[$info['mime']]), null, $body);
        if (!empty($uploaded['error'])) return;
        $attachment = wp_insert_attachment(array('post_mime_type' => $info['mime'], 'post_title' => $id . ' app icon', 'post_status' => 'inherit'), $uploaded['file'], $post_id, true);
        if (is_wp_error($attachment) || !$attachment) return;
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $uploaded['file']));
        set_post_thumbnail($post_id, $attachment);
    }
    private static function upsert($id, $title = '', $apkcombo = '') {
        if (!post_type_exists('wpdmpro')) return new WP_Error('wpdm_missing', 'Activate WordPress Download Manager first.');
        $matches = get_posts(array('post_type' => 'wpdmpro', 'post_status' => 'any', 'meta_key' => self::META, 'meta_value' => strtolower($id), 'fields' => 'ids', 'numberposts' => 1));
        $existing = $matches ? (int) $matches[0] : 0;
        $apkcombo = $apkcombo ?: ($existing ? self::apkcombo_app_url(get_post_meta($existing, '_owc_apk_apkcombo_url', true), $id) : '');
        $meta = self::play_meta($id);
        if ($existing) {
            if (!$meta['description']) $meta['description'] = (string) get_post_meta($existing, '_owc_apk_description', true);
            foreach (array('about', 'whats_new', 'version', 'company', 'support_email', 'support_url') as $field) {
                if (!$meta[$field]) $meta[$field] = (string) get_post_meta($existing, '_owc_apk_' . $field, true);
            }
            $saved_screenshots = (array) get_post_meta($existing, '_owc_apk_screenshots', true);
            $saved_screenshots = array_values(array_filter($saved_screenshots, array(__CLASS__, 'is_play_image')));
            if ($saved_screenshots) $meta['screenshots'] = $saved_screenshots;
            $old_icon = (string) get_post_meta($existing, '_owc_apk_icon_url', true);
            if (self::is_play_image($old_icon)) $meta['image'] = $old_icon;
        }
        if (!$existing || get_post_status($existing) === 'pending') {
            $icon_available = ($existing && has_post_thumbnail($existing)) || self::available_play_image($meta['image']);
            $meta['screenshots'] = $icon_available ? array_values(array_filter($meta['screenshots'], array(__CLASS__, 'available_play_image'))) : array();
            if (!$icon_available || !$meta['screenshots']) {
                delete_transient('owc_play_' . md5($id));
                return new WP_Error('owc_artwork_unavailable', 'Google Play artwork is unavailable. The app was not published. Try again later.', array('status' => 503));
            }
        }
        $play = self::play_url($id);
        $display_title = $title ?: ($meta['title'] ?: $id);
        if ($existing) {
            $current_title = get_the_title($existing);
            if ($current_title !== $id) $display_title = $current_title;
        }
        $links = array();
        foreach (self::links($id, $apkcombo) as $label => $url) $links[] = '<a href="' . esc_url($url) . '" rel="nofollow noopener noreferrer" target="_blank">' . esc_html($apkcombo ? $label : ($label === 'APKCombo app page' ? 'APKCombo Downloader' : $label)) . '</a>';
        $image = $meta['image'];
        $manual_description = $existing ? (string) get_post_meta($existing, '_owc_apk_description_manual', true) : '';
        $description = $manual_description ?: $meta['description'];
        $detail = array();
        foreach (array('about', 'whats_new', 'version', 'company', 'support_email', 'support_url') as $field) {
            $manual = $existing ? (string) get_post_meta($existing, '_owc_apk_' . $field . '_manual', true) : '';
            $detail[$field] = $manual ?: $meta[$field];
        }
        if (!$detail['about']) $detail['about'] = $description;
        $gallery = '';
        if ($meta['screenshots']) {
            $gallery = '<h3>App previews</h3><div class="owc-play-previews" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(105px,1fr));gap:10px;max-width:640px">';
            foreach ($meta['screenshots'] as $index => $shot) {
                $gallery .= '<a href="' . esc_url($play) . '" target="_blank" rel="noopener noreferrer"><img src="' . esc_url($shot) . '" alt="' . esc_attr($display_title . ' preview ' . ($index + 1)) . '" loading="lazy" width="140" height="220" style="width:100%;height:190px;object-fit:contain;border-radius:9px;background:#f4f6f8"></a>';
            }
            $gallery .= '</div>';
        }
        $content = '<div class="owc-play-app-card" style="display:flex;align-items:center;gap:16px;padding:18px;border:1px solid #dce3e8;border-radius:12px;max-width:640px;margin:20px 0">'
            . '<a href="' . esc_url($play) . '" target="_blank" rel="noopener noreferrer"><img src="' . esc_url($image) . '" alt="' . esc_attr($display_title) . ' app icon" width="96" height="96" loading="lazy" style="width:96px;height:96px;object-fit:contain;border-radius:18px"></a>'
            . '<div><strong style="font-size:1.25em">' . esc_html($display_title) . '</strong><br><span>Developer/company: ' . esc_html($detail['company'] ?: 'Not listed') . '</span><br><small>' . esc_html($id) . '</small><p style="margin:10px 0 0"><a href="' . esc_url($play) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:9px 14px;border-radius:7px;background:#188038;color:#fff;text-decoration:none">View on Google Play</a></p></div></div>'
            . ($detail['version'] ? '<p><strong>Version:</strong> ' . esc_html($detail['version']) . '</p>' : '')
            . '<h2>About this app</h2><p>' . ($detail['about'] ? nl2br(esc_html($detail['about'])) : 'App details are not available yet.') . '</p>'
            . ($detail['whats_new'] ? '<h2>What’s new</h2><p>' . nl2br(esc_html($detail['whats_new'])) . '</p>' : '')
            . (($detail['support_email'] || $detail['support_url']) ? '<h2>App support</h2><p>'
                . ($detail['support_url'] ? '<a href="' . esc_url($detail['support_url']) . '" rel="nofollow noopener noreferrer" target="_blank">Support website</a> ' : '')
                . ($detail['support_email'] ? '<a href="mailto:' . esc_attr($detail['support_email']) . '">' . esc_html($detail['support_email']) . '</a>' : '') . '</p>' : '')
            . $gallery
            . '<p>Other sources: ' . implode(' · ', $links) . '</p><p>The app is available on its source site; no APK file is stored here.</p>';
        $post = array('post_type' => 'wpdmpro', 'post_title' => $display_title, 'post_content' => $content, 'post_excerpt' => $description);
        if ($existing) {
            $post['ID'] = $existing;
            if (get_post_status($existing) === 'pending') $post['post_status'] = 'publish';
            $result = wp_update_post(wp_slash($post), true);
        } else {
            $post['post_status'] = 'publish';
            $result = wp_insert_post(wp_slash($post), true);
        }
        if (is_wp_error($result) || !$result) return new WP_Error('create_failed', 'Could not save the WPDM package.');
        update_post_meta($result, self::META, strtolower($id));
        update_post_meta($result, '_owc_apk_package', $id);
        if ($apkcombo) update_post_meta($result, '_owc_apk_apkcombo_url', $apkcombo);
        // Published packages are available to visitors; manually private packages stay restricted.
        self::set_access_for_status($result);
        if (!$existing) {
            update_post_meta($result, '__wpdm_files', array($play));
            update_post_meta($result, '__wpdm_link_label', 'View on Google Play');
        }
        update_post_meta($result, '_owc_apk_icon_url', $image);
        update_post_meta($result, '_owc_apk_description', $meta['description']);
        foreach (array('about', 'whats_new', 'version', 'company', 'support_email', 'support_url', 'android', 'category', 'updated') as $field) update_post_meta($result, '_owc_apk_' . $field, $meta[$field]);
        update_post_meta($result, '_owc_apk_screenshots', $meta['screenshots']);
        update_post_meta($result, '__wpdm_preview_image', $image);
        if ($meta['image']) self::attach_icon($result, $id, $meta['image']);
        clean_post_cache($result);
        return $existing ? 'updated' : 'created';
    }
    private static function set_access_for_status($post_id) {
        $status = get_post_status($post_id);
        update_post_meta($post_id, '__wpdm_access', $status === 'publish' ? array('guest') : array('administrator', 'editor'));
    }
    public static function protect_status($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || !get_post_meta($post_id, self::META, true)) return;
        self::set_access_for_status($post_id);
    }
    public static function publish_pending() {
        if (!post_type_exists('wpdmpro')) return;
        $ids = get_posts(array('post_type' => 'wpdmpro', 'post_status' => 'pending', 'meta_key' => self::META, 'fields' => 'ids', 'numberposts' => 50));
        $published = 0;
        foreach ($ids as $post_id) {
            $id = self::package_id(get_post_meta($post_id, '_owc_apk_package', true));
            if ($id && !is_wp_error(self::upsert($id))) ++$published;
        }
        if (count($ids) === 50 && $published && !wp_next_scheduled('owc_apk_wpdm_publish_pending')) {
            wp_schedule_single_event(time() + 30, 'owc_apk_wpdm_publish_pending');
        }
    }
    public static function description_box() {
        add_meta_box('owc-apk-summary', 'App details', array(__CLASS__, 'description_box_html'), 'wpdmpro', 'normal', 'default');
    }
    public static function description_box_html($post) {
        if (!get_post_meta($post->ID, self::META, true)) {
            echo '<p>This field is for packages imported by the APK connector.</p>';
            return;
        }
        wp_nonce_field('owc_apk_summary_' . $post->ID, 'owc_apk_summary_nonce');
        $manual = (string) get_post_meta($post->ID, '_owc_apk_description_manual', true);
        $auto = (string) get_post_meta($post->ID, '_owc_apk_description', true);
        echo '<p>Google Play summary: ' . ($auto ? esc_html($auto) : 'Not available from the last fetch.') . '</p>';
        $refresh = wp_nonce_url(add_query_arg(array('action' => 'owc_apk_wpdm_refresh', 'post_id' => $post->ID), admin_url('admin-post.php')), 'owc_apk_refresh_' . $post->ID);
        echo '<p><a class="button" href="' . esc_url($refresh) . '">Refresh app information</a></p>';
        echo '<p><label for="owc_apk_summary_manual">Optional description to use when Play metadata is missing or needs correction</label></p>';
        echo '<textarea id="owc_apk_summary_manual" name="owc_apk_summary_manual" rows="5" class="widefat">' . esc_textarea($manual) . '</textarea>';
        $fields = array('company' => 'Developer/company name', 'about' => 'About this app', 'whats_new' => 'What’s new', 'version' => 'App version', 'support_email' => 'App support email', 'support_url' => 'App support website (https)');
        foreach ($fields as $key => $label) {
            $value = (string) get_post_meta($post->ID, '_owc_apk_' . $key . '_manual', true);
            $fetched = (string) get_post_meta($post->ID, '_owc_apk_' . $key, true);
            echo '<p><label for="owc_apk_' . esc_attr($key) . '_manual"><strong>' . esc_html($label) . '</strong></label><br><small>Fetched: ' . ($fetched ? esc_html(wp_html_excerpt($fetched, 250, '…')) : 'Not available') . '</small></p>';
            if (in_array($key, array('about', 'whats_new'), true)) echo '<textarea id="owc_apk_' . esc_attr($key) . '_manual" name="owc_apk_' . esc_attr($key) . '_manual" rows="5" class="widefat">' . esc_textarea($value) . '</textarea>';
            else echo '<input id="owc_apk_' . esc_attr($key) . '_manual" name="owc_apk_' . esc_attr($key) . '_manual" class="widefat" value="' . esc_attr($value) . '">';
        }
    }
    public static function save_description_box($post_id, $post, $update) {
        if (!get_post_meta($post_id, self::META, true) || !isset($_POST['owc_apk_summary_nonce']) || !is_string($_POST['owc_apk_summary_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['owc_apk_summary_nonce'])), 'owc_apk_summary_' . $post_id) || !current_user_can('edit_post', $post_id)) return;
        $manual = isset($_POST['owc_apk_summary_manual']) && is_string($_POST['owc_apk_summary_manual']) ? sanitize_textarea_field(wp_unslash($_POST['owc_apk_summary_manual'])) : '';
        update_post_meta($post_id, '_owc_apk_description_manual', wp_html_excerpt($manual, 1200, '…'));
        foreach (array('company' => 160, 'about' => 6000, 'whats_new' => 3000, 'version' => 80, 'support_email' => 254, 'support_url' => 1000) as $field => $limit) {
            $key = 'owc_apk_' . $field . '_manual';
            $input = isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            $value = in_array($field, array('about', 'whats_new'), true) ? sanitize_textarea_field($input) : sanitize_text_field($input);
            $value = wp_html_excerpt($value, $limit, '…');
            if ($field === 'support_email') $value = is_email($value) ? sanitize_email($value) : '';
            if ($field === 'support_url') $value = wp_parse_url($value, PHP_URL_SCHEME) === 'https' ? esc_url_raw($value) : '';
            update_post_meta($post_id, '_owc_apk_' . $field . '_manual', $value);
        }
    }
    public static function app_template($template) {
        if (!is_singular('wpdmpro')) return $template;
        $post_id = get_queried_object_id();
        if (!$post_id || !get_post_meta($post_id, self::META, true)) return $template;
        $app_template = plugin_dir_path(__FILE__) . 'templates/single-app.php';
        return is_readable($app_template) ? $app_template : $template;
    }
    public static function repair() {
        if (!post_type_exists('wpdmpro')) return;
        $offset = (int) get_option('owc_apk_wpdm_repair_offset', 0);
        $ids = get_posts(array('post_type' => 'wpdmpro', 'post_status' => 'any', 'meta_key' => self::META, 'fields' => 'ids', 'numberposts' => 10, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC'));
        foreach ($ids as $post_id) {
            $id = self::package_id(get_post_meta($post_id, '_owc_apk_package', true));
            if ($id) {
                if (!get_post_meta($post_id, '_owc_apk_company', true)) delete_transient('owc_play_' . md5($id));
                self::upsert($id);
            }
        }
        if (count($ids) === 10) {
            update_option('owc_apk_wpdm_repair_offset', $offset + 10, false);
            if (!wp_next_scheduled('owc_apk_wpdm_repair')) wp_schedule_single_event(time() + 60, 'owc_apk_wpdm_repair');
        } else delete_option('owc_apk_wpdm_repair_offset');
    }
    public static function sync() {
        if (!post_type_exists('wpdmpro')) return new WP_Error('wpdm_missing', 'Activate WordPress Download Manager first.');
        if (get_transient(self::LOCK)) return new WP_Error('sync_busy', 'A sync is already running.');
        set_transient(self::LOCK, 1, 5 * MINUTE_IN_SECONDS);
        try {
            $counts = array('created' => 0, 'updated' => 0, 'failed' => 0);
            foreach (self::entries(self::settings()['apps']) as $entry) {
                $result = self::upsert($entry[0], $entry[1]);
                if (is_wp_error($result)) ++$counts['failed']; else ++$counts[$result];
            }
            return $counts;
        } finally { delete_transient(self::LOCK); }
    }
    public static function scripts() {
        if (is_singular('wpdmpro') && get_post_meta(get_queried_object_id(), self::META, true)) {
            wp_enqueue_style('owc-apk-app-detail', plugins_url('assets/app-detail.css', __FILE__), array(), '2.3.2');
        }
        // This optional script syncs the original Generate button. The separate
        // archive control is rendered with the shortcode and works without it.
        if (!self::settings()['capture']) return;
        $config = array('url' => admin_url('admin-ajax.php'), 'archive' => home_url('/archive/'));
        wp_enqueue_script('owc-apk-wpdm-capture', plugins_url('capture.js', __FILE__), array(), '2.3.2', true);
        wp_add_inline_script('owc-apk-wpdm-capture', 'window.owcApkWpdm=' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', 'before');
    }
    public static function downloader_controls($html, $tag, $attr, $match) {
        if ($tag !== 'apk_downloader_form' || !self::settings()['capture'] || !is_string($html) || !preg_match('/<form\b[^>]*\bid=["\']apkdownloader["\']/i', $html) || !preg_match('/<\/form\s*>/i', $html)) return $html;
        $action = esc_url(admin_url('admin-post.php'));
        $controls = '<div class="owc-apk-archive-action" style="margin:18px 0;padding:18px;border:1px solid #b9dcc5;border-radius:10px;background:#f5fbf6">'
            . '<strong>Connect this app to the Downloads archive</strong><p>Enter an Android package name or Google Play link above. You can also paste its APKCombo app page link below. The app entry will be published to the archive with available Google Play details; no APK file is uploaded.</p>'
            . '<label for="owc-apkcombo-link">APKCombo app page link (optional)</label><input id="owc-apkcombo-link" name="apkcombo" type="url" placeholder="https://apkcombo.com/app-name/com.example.app/" style="display:block;width:100%;max-width:540px;margin:6px 0 12px">'
            . '<input type="hidden" name="action" value="owc_apk_wpdm_front_submit">'
            . '<button type="submit" class="owc-apk-add-archive" formaction="' . $action . '" formmethod="post" onclick="this.form.elements.namedItem(\'action\').value=\'owc_apk_wpdm_front_submit\';this.form.setAttribute(\'action\',this.formAction);this.form.method=\'post\';this.form.submit();return false;" style="padding:11px 17px;border:0;border-radius:8px;background:#207847;color:#fff;cursor:pointer">Connect and sync with archive</button>'
            . '</div>';
        $html = preg_replace('/<\/form\s*>/i', $controls . '</form>', $html, 1);
        if (!empty($_GET['owc_archive'])) {
            $post_id = absint($_GET['owc_archive']);
            if ($post_id && get_post_type($post_id) === 'wpdmpro' && get_post_status($post_id) === 'publish' && get_post_meta($post_id, self::META, true)) {
                $id = (string) get_post_meta($post_id, '_owc_apk_package', true);
                $combo = self::apkcombo_app_url(get_post_meta($post_id, '_owc_apk_apkcombo_url', true), $id);
                $html .= '<div class="owc-apk-archive-result" role="status" style="margin:16px 0;padding:18px;border:1px solid #b9dcc5;border-radius:10px"><strong>Published in Downloads</strong><p><a href="' . esc_url(get_permalink($post_id)) . '">View this app</a> · <a href="' . esc_url(home_url('/archive/')) . '">Open archive</a></p>'
                    . '<p>Copy the prepared package name or Google Play link, then paste it into APKCombo Downloader.</p>'
                    . self::copy_field('Package name', $id) . self::copy_field('Google Play link', self::play_url($id))
                    . ($combo ? self::copy_field('APKCombo app page link', $combo) . '<p><a href="' . esc_url($combo) . '" target="_blank" rel="noopener noreferrer">Open this app on APKCombo</a></p>' : '')
                    . '<p><a href="https://apkcombo.com/downloader/" target="_blank" rel="noopener noreferrer">Open APKCombo Downloader</a></p></div>';
            }
        } elseif (isset($_GET['owc_archive_error'])) {
            $html .= '<p role="alert">Could not sync this app: ' . esc_html(sanitize_text_field(wp_unslash($_GET['owc_archive_error']))) . '</p>';
        }
        $html .= '<section class="owc-apk-recent" aria-label="Apps in Downloads" style="margin:24px 0"><h2>Apps in Downloads</h2>'
            . self::catalog(array('limit' => 12))
            . '<p><a href="' . esc_url(home_url('/archive/')) . '">Browse the full Downloads archive</a></p></section>';
        return $html;
    }
    private static function copy_field($label, $value) {
        return '<p><label>' . esc_html($label) . '<br><input type="text" readonly value="' . esc_attr($value) . '" onclick="this.select()" style="width:100%;max-width:540px" aria-label="' . esc_attr($label) . '"></label> <button type="button" onclick="var i=this.previousElementSibling.querySelector(\'input\');i.select();if(navigator.clipboard)navigator.clipboard.writeText(i.value);this.textContent=\'Copied / selected\';">Copy</button></p>';
    }
    public static function front_submit() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('Submission unavailable', '', array('response' => 405));
        $input = isset($_POST['package']) && is_string($_POST['package']) ? wp_unslash($_POST['package']) : '';
        $apkcombo = isset($_POST['apkcombo']) && is_string($_POST['apkcombo']) ? wp_unslash($_POST['apkcombo']) : '';
        $result = self::capture_data($input, $apkcombo);
        $referer = wp_get_referer();
        $return = $referer && wp_validate_redirect($referer, false) ? $referer : home_url('/apk-downloader/');
        $return = remove_query_arg(array('owc_archive', 'owc_archive_error'), $return);
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('owc_archive_error', $result->get_error_message(), $return));
            exit;
        }
        $matches = get_posts(array('post_type' => 'wpdmpro', 'post_status' => 'publish', 'meta_key' => self::META, 'meta_value' => strtolower($result['package']), 'fields' => 'ids', 'numberposts' => 1));
        wp_safe_redirect(add_query_arg('owc_archive', $matches ? (int) $matches[0] : 0, $return));
        exit;
    }
    public static function register_capture_route() {
        register_rest_route('owc-apk/v1', '/capture', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'capture_rest'),
            'permission_callback' => '__return_true',
        ));
    }
    private static function capture_data($input, $submitted_apkcombo = '') {
        if (!self::settings()['capture']) return new WP_Error('owc_disabled', 'App submissions are disabled.', array('status' => 403));
        $id = self::package_id($input);
        if (!$id) return new WP_Error('owc_invalid_package', 'Enter a valid Android package name, Google Play URL or APKCombo app URL.', array('status' => 400));
        $apkcombo = self::apkcombo_app_url($submitted_apkcombo ?: $input, $id);
        if ($submitted_apkcombo && !$apkcombo) return new WP_Error('owc_invalid_apkcombo', 'The APKCombo app URL must match the submitted package.', array('status' => 400));
        $result = self::upsert($id, '', $apkcombo);
        if (is_wp_error($result) && $result->get_error_code() === 'owc_artwork_unavailable') return $result;
        if (is_wp_error($result)) return new WP_Error('owc_save_failed', $result->get_error_message(), array('status' => 500));
        $matches = get_posts(array('post_type' => 'wpdmpro', 'post_status' => 'any', 'meta_key' => self::META, 'meta_value' => strtolower($id), 'fields' => 'ids', 'numberposts' => 1));
        $post_id = $matches ? (int) $matches[0] : 0;
        $published = $post_id && get_post_status($post_id) === 'publish';
        if (!$apkcombo && $post_id) $apkcombo = self::apkcombo_app_url(get_post_meta($post_id, '_owc_apk_apkcombo_url', true), $id);
        return array(
            'result' => $result,
            'published' => (bool) $published,
            'archive_url' => home_url('/archive/'),
            'app_url' => $published ? get_permalink($post_id) : '',
            'play_url' => self::play_url($id),
            'package' => $id,
            'apkcombo_url' => $apkcombo,
            'apkcombo_downloader_url' => 'https://apkcombo.com/downloader/',
        );
    }
    public static function capture_rest($request) {
        $input = $request->get_param('package');
        $apkcombo = $request->get_param('apkcombo');
        $result = self::capture_data(is_string($input) ? $input : '', is_string($apkcombo) ? $apkcombo : '');
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(array('success' => true, 'data' => $result));
    }
    public static function capture() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') wp_send_json_error('Submission unavailable', 403);
        $input = isset($_POST['package']) && is_string($_POST['package']) ? wp_unslash($_POST['package']) : '';
        $apkcombo = isset($_POST['apkcombo']) && is_string($_POST['apkcombo']) ? wp_unslash($_POST['apkcombo']) : '';
        $result = self::capture_data($input, $apkcombo);
        if (is_wp_error($result)) wp_send_json_error($result->get_error_message(), (int) ($result->get_error_data()['status'] ?? 500));
        wp_send_json_success($result);
    }
    public static function catalog($atts) {
        if (!post_type_exists('wpdmpro')) return '';
        $atts = shortcode_atts(array('limit' => 20), $atts, 'owc_apk_downloads');
        $limit = max(1, min(100, (int) $atts['limit']));
        $posts = get_posts(array('post_type' => 'wpdmpro', 'post_status' => 'publish', 'numberposts' => $limit, 'meta_key' => self::META, 'orderby' => 'date', 'order' => 'DESC'));
        if (!$posts) return '<p>No apps have been added yet.</p>';
        $out = '<div class="owc-apk-downloads" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px">';
        foreach ($posts as $post) {
            $id = get_post_meta($post->ID, '_owc_apk_package', true);
            $icon = get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: get_post_meta($post->ID, '_owc_apk_icon_url', true);
            $description = (string) get_post_meta($post->ID, '_owc_apk_description', true);
            $screenshots = (array) get_post_meta($post->ID, '_owc_apk_screenshots', true);
            $company = trim((string) get_post_meta($post->ID, '_owc_apk_company_manual', true));
            if (!$company) $company = trim((string) get_post_meta($post->ID, '_owc_apk_company', true));
            $out .= '<article style="border:1px solid #dce3e8;border-radius:12px;padding:15px"><a href="' . esc_url(get_permalink($post)) . '">';
            if ($icon) $out .= '<img src="' . esc_url($icon) . '" alt="' . esc_attr(get_the_title($post)) . ' app icon" width="80" height="80" loading="lazy" style="width:80px;height:80px;object-fit:contain;border-radius:15px;display:block;margin-bottom:10px">';
            $out .= '<strong>' . esc_html(get_the_title($post)) . '</strong></a><br><span>Developer/company: ' . esc_html($company ?: 'Not listed') . '</span><br><small>' . esc_html($id) . '</small>';
            if ($description) $out .= '<p>' . esc_html(wp_trim_words($description, 22)) . '</p>';
            if (!empty($screenshots[0])) $out .= '<a href="' . esc_url(get_permalink($post)) . '"><img src="' . esc_url($screenshots[0]) . '" alt="' . esc_attr(get_the_title($post) . ' preview') . '" loading="lazy" width="140" height="180" style="width:100%;height:130px;object-fit:contain;border-radius:8px;background:#f4f6f8"></a>';
            $out .= '<p style="margin:10px 0 0"><a href="' . esc_url(get_permalink($post)) . '">View in Downloads</a> · <a href="' . esc_url(self::play_url($id)) . '" target="_blank" rel="noopener noreferrer">Google Play</a></p></article>';
        }
        return $out . '</div>';
    }
}
require_once __DIR__ . '/archive-improvements.php';
OWC_WPDM_APK_Connector::init();
OWC_APK_Archive_Improvements::init();
register_activation_hook(__FILE__, array('OWC_WPDM_APK_Connector', 'activate'));
register_deactivation_hook(__FILE__, array('OWC_WPDM_APK_Connector', 'deactivate'));

require_once __DIR__ . '/discovery.php';
OWC_App_Discovery::init();
