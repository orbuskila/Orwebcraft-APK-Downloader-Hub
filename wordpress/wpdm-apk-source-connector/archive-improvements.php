<?php
if (!defined('ABSPATH')) exit;
/** Metadata maintenance never calls upsert: legacy files, content and statuses stay intact. */
final class OWC_APK_Archive_Improvements {
    const HOOK = 'owc_apk_archive_maintenance';
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_owc_archive_repair', array(__CLASS__, 'start'));
        add_action(self::HOOK, array(__CLASS__, 'batch'));
        add_shortcode('owc_app_archive', array(__CLASS__, 'archive'));
        add_filter('template_include', function ($template) {
            if (is_post_type_archive('wpdmpro') || is_page('archive')) return __DIR__ . '/templates/archive-apps.php';
            return $template;
        }, 1001);
        add_action('wp_enqueue_scripts', function () {
            if (!is_page('archive') && !is_post_type_archive('wpdmpro') && !is_singular('wpdmpro')) return;
            wp_enqueue_style('owc-app-archive', plugins_url('assets/archive.css', __FILE__), array(), '2.3.2');
        });
        add_action('save_post_wpdmpro', function ($id) {
            if (!wp_is_post_revision($id) && !wp_next_scheduled(self::HOOK)) wp_schedule_single_event(time() + 60, self::HOOK);
        });
        add_action('add_meta_boxes_wpdmpro', array(__CLASS__, 'box'));
        add_action('save_post_wpdmpro', array(__CLASS__, 'save_box'), 40);
        add_filter('the_content', array(__CLASS__, 'legacy_details'), 15);
        if (get_option('owc_archive_schema') !== '2.2.0') {
            update_option('owc_archive_schema', '2.2.0', false);
            update_option('owc_archive_cursor', 0, false);
            if (!wp_next_scheduled(self::HOOK)) wp_schedule_single_event(time() + 60, self::HOOK);
        }
    }
    public static function box() {
        add_meta_box('owc-legacy-metadata', 'Archive metadata matching', array(__CLASS__, 'box_html'), 'wpdmpro', 'normal');
    }
    public static function box_html($post) {
        wp_nonce_field('owc_archive_match_' . $post->ID, 'owc_archive_match_nonce');
        echo '<p><label>Exact Android package ID or Google Play URL<br><input class="widefat" name="owc_archive_package" value="' . esc_attr(get_post_meta($post->ID, '_owc_apk_package', true)) . '"></label></p><p><label>Developer / Company override<br><input class="widefat" name="owc_archive_company" value="' . esc_attr(get_post_meta($post->ID, '_owc_apk_company_manual', true)) . '"></label></p><p>Save to queue metadata maintenance. An empty company override uses fetched metadata.</p>';
    }
    public static function save_box($id) {
        if (wp_is_post_revision($id) || !current_user_can('edit_post', $id) || !isset($_POST['owc_archive_match_nonce']) || !is_string($_POST['owc_archive_match_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['owc_archive_match_nonce'])), 'owc_archive_match_' . $id)) return;
        if (isset($_POST['owc_archive_package']) && is_string($_POST['owc_archive_package'])) {
            $package = OWC_WPDM_APK_Connector::package_id(wp_unslash($_POST['owc_archive_package']));
            if ($package) update_post_meta($id, '_owc_apk_package', $package);
        }
        if (isset($_POST['owc_archive_company']) && is_string($_POST['owc_archive_company'])) update_post_meta($id, '_owc_apk_company_manual', sanitize_text_field(wp_unslash($_POST['owc_archive_company'])));
    }
    public static function menu() {
        add_management_page('App archive maintenance', 'App archive maintenance', 'manage_options', 'owc-archive', array(__CLASS__, 'page'));
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>App archive maintenance</h1><p>Refresh developer/company, description, version and store metadata for old and new packages. Existing downloads, titles, content and publication statuses are preserved. Apps need an exact package ID or Google Play/APKCombo URL. No title guessing is used.</p>';
        echo '<p>Use <code>[owc_app_archive]</code> on the archive page for searchable cards and ascending title order.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="owc_archive_repair">';
        wp_nonce_field('owc_archive_repair'); submit_button('Refresh all archive metadata'); echo '</form>';
        $log = (array) get_option('owc_archive_report', array());
        echo '<h2>Latest results</h2><table class="widefat"><thead><tr><th>Package</th><th>Result</th></tr></thead><tbody>';
        foreach ($log as $row) echo '<tr><td><a href="' . esc_url(get_edit_post_link($row['id'])) . '">' . esc_html(get_the_title($row['id'])) . '</a></td><td>' . esc_html($row['status']) . '</td></tr>';
        echo '</tbody></table><p>At most five packages are processed per scheduled batch. WP-Cron runs when the site receives requests. Missing or unitless file sizes are displayed as unavailable until verified; store download sizes are never substituted for your uploaded file.</p></div>';
    }
    public static function start() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options') || !check_admin_referer('owc_archive_repair')) wp_die('Access denied');
        update_option('owc_archive_cursor', 0, false);
        update_option('owc_archive_report', array(), false);
        wp_clear_scheduled_hook(self::HOOK);
        self::batch();
        wp_safe_redirect(admin_url('tools.php?page=owc-archive')); exit;
    }
    public static function identify($post_id) {
        $ids = array();
        foreach (array('_owc_apk_package', '_owc_apk_connector_id', '_owc_apk_package_manual') as $key) {
            $id = OWC_WPDM_APK_Connector::package_id(get_post_meta($post_id, $key, true));
            if ($id) return $id;
        }
        $post = get_post($post_id);
        $text = ($post ? $post->post_content : '') . "\n" . wp_json_encode(get_post_meta($post_id, '__wpdm_files', true));
        $text = str_replace('\\/', '/', $text);
        if (preg_match_all('~https://(?:play\.google\.com/store/apps/details\?[^\s<>"\']+|(?:www\.)?apkcombo\.com/[^\s<>"\']+)~i', $text, $urls)) {
            foreach ($urls[0] as $url) {
                $id = OWC_WPDM_APK_Connector::package_id(html_entity_decode(str_replace('\\/', '/', $url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($id) $ids[$id] = true;
            }
        }
        return count($ids) === 1 ? key($ids) : '';
    }
    public static function field($id, $key) {
        $manual = trim((string) get_post_meta($id, '_owc_apk_' . $key . '_manual', true));
        return $manual ?: trim((string) get_post_meta($id, '_owc_apk_' . $key, true));
    }
    public static function size_label($raw) {
        $raw = trim((string) $raw);
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(B|KB|MB|GB|TB)$/iD', $raw, $m) && (float) $m[1] > 0) return $m[1] . ' ' . strtoupper($m[2]);
        return 'Size unavailable';
    }
    public static function batch() {
        if (!post_type_exists('wpdmpro') || get_transient('owc_archive_running')) return;
        set_transient('owc_archive_running', 1, 5 * MINUTE_IN_SECONDS);
        try {
            global $wpdb;
            $cursor = absint(get_option('owc_archive_cursor', 0));
            $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft','inherit') AND ID > %d ORDER BY ID ASC LIMIT 5", 'wpdmpro', $cursor));
            $report = (array) get_option('owc_archive_report', array());
            foreach ($ids as $post_id) {
                $status = self::repair_one((int) $post_id);
                $report[] = array('id' => (int) $post_id, 'status' => $status);
                update_option('owc_archive_cursor', (int) $post_id, false);
            }
            update_option('owc_archive_report', array_slice($report, -250), false);
            if (count($ids) === 5) {
                if (!wp_next_scheduled(self::HOOK)) wp_schedule_single_event(time() + 60, self::HOOK);
            } else {
                update_option('owc_archive_finished', time(), false);
                // A later save can start another complete maintenance pass.
                update_option('owc_archive_cursor', 0, false);
            }
        } finally { delete_transient('owc_archive_running'); }
    }
    public static function repair_one($post_id) {
        $id = self::identify($post_id);
        if (!$id) return 'Needs exact package ID / store URL; skipped';
        update_post_meta($post_id, '_owc_apk_package', $id);
        // Do not mark legacy posts as connector-owned: upsert would replace their files.
        $meta = OWC_WPDM_APK_Connector::play_meta($id);
        $found = false;
        foreach (array('company','description','about','whats_new','version','support_email','support_url','updated','category','android') as $field) {
            if (!empty($meta[$field])) { update_post_meta($post_id, '_owc_apk_' . $field, $meta[$field]); $found = true; }
        }
        if (!empty($meta['image'])) update_post_meta($post_id, '_owc_apk_icon_url', $meta['image']);
        if (!empty($meta['screenshots'])) update_post_meta($post_id, '_owc_apk_screenshots', $meta['screenshots']);
        if ($found) {
            update_post_meta($post_id, '_owc_apk_metadata_checked', time());
            update_post_meta($post_id, '_owc_apk_metadata_source', 'https://play.google.com/store/apps/details?id=' . rawurlencode($id));
        }
        $raw = get_post_meta($post_id, '__wpdm_package_size', true);
        $size = self::size_label($raw);
        if ($size !== 'Size unavailable' && $size !== $raw) {
            if (!metadata_exists('post', $post_id, '_owc_apk_original_size')) update_post_meta($post_id, '_owc_apk_original_size', $raw);
            update_post_meta($post_id, '__wpdm_package_size', $size);
        }
        return (!empty($meta['company']) ? 'Developer synchronized' : ($found ? 'Metadata refreshed; developer unavailable' : 'Store metadata unavailable; previous values preserved')) . ($size === 'Size unavailable' ? '; file size needs verification' : '');
    }
    public static function legacy_details($content) {
        if (!is_singular('wpdmpro') || !in_the_loop() || !is_main_query() || post_password_required()) return $content;
        $id = get_the_ID();
        if (get_post_meta($id, '_owc_apk_connector_id', true) || !get_post_meta($id, '_owc_apk_package', true)) return $content;
        $out = '<section class="owc-app-info"><h2>App information</h2><dl>';
        foreach (array('company'=>'Developer / Company','version'=>'Store version','updated'=>'Store updated','category'=>'Category') as $key=>$label) {
            $value = self::field($id, $key);
            if ($value) $out .= '<dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd>';
        }
        $out .= '</dl>';
        foreach (array('about'=>'About this app','whats_new'=>'What’s new') as $key=>$label) {
            $value = self::field($id, $key);
            if ($value) $out .= '<h3>' . esc_html($label) . '</h3><p>' . nl2br(esc_html($value)) . '</p>';
        }
        $out .= '<p><a href="https://play.google.com/store/apps/details?id=' . esc_attr(rawurlencode(get_post_meta($id, '_owc_apk_package', true))) . '" rel="noopener noreferrer" target="_blank">Google Play listing</a></p></section>';
        return $out . $content;
    }
    public static function archive($atts = array()) {
        $base_url = is_post_type_archive('wpdmpro') ? get_post_type_archive_link('wpdmpro') : get_permalink();
        $atts = shortcode_atts(array('limit'=>24), $atts, 'owc_app_archive');
        $search = isset($_GET['owc_q']) && is_string($_GET['owc_q']) ? sanitize_text_field(wp_unslash($_GET['owc_q'])) : '';
        $sort = isset($_GET['owc_sort']) && is_string($_GET['owc_sort']) ? sanitize_key($_GET['owc_sort']) : 'az';
        $sorts = array('az'=>array('title','ASC','A–Z'), 'za'=>array('title','DESC','Z–A'), 'new'=>array('date','DESC','Newest'), 'updated'=>array('modified','DESC','Recently edited'), 'popular'=>array('meta_value_num','DESC','Most downloaded'), 'week'=>array('meta_value_num','DESC','Popular this week'));
        if (!isset($sorts[$sort])) $sort = 'az';
        $page = isset($_GET['owc_page']) && is_scalar($_GET['owc_page']) ? max(1, absint($_GET['owc_page'])) : 1;
        $query_args = array('suppress_filters'=>true, 'ignore_custom_sort'=>true, 'post_type'=>'wpdmpro', 'post_status'=>'publish', 'has_password'=>false, 'posts_per_page'=>max(1,min(60,(int)$atts['limit'])), 'paged'=>$page, 's'=>$search, 'orderby'=>$sorts[$sort][0], 'order'=>$sorts[$sort][1]);
        $query_args = OWC_App_Discovery::filters($query_args);
        $query = new WP_Query($query_args);
        $out = '<section class="owc-archive">' . OWC_App_Discovery::archive_header() . '<form method="get" action="' . esc_url($base_url) . '" class="owc-archive-search"><label>Search apps <input type="search" name="owc_q" value="' . esc_attr($search) . '"></label><label>Sort <select name="owc_sort">';
        foreach ($sorts as $key=>$values) $out .= '<option value="' . esc_attr($key) . '" ' . selected($sort,$key,false) . '>' . esc_html($values[2]) . '</option>';
        $out .= '</select></label>' . OWC_App_Discovery::filter_controls() . '<button type="submit">Search</button></form><p>' . esc_html($query->found_posts) . ' packages</p><div class="owc-archive-grid">';
        foreach ($query->posts as $post) {
            $pid = $post->ID; $url = get_permalink($pid);
            $icon = get_the_post_thumbnail_url($pid, 'thumbnail') ?: get_post_meta($pid, '_owc_apk_icon_url', true);
            $out .= '<article class="owc-archive-card"><a href="' . esc_url($url) . '">';
            if ($icon) $out .= '<img src="' . esc_url($icon) . '" alt="" width="72" height="72" loading="lazy">';
            $out .= '<h2>' . esc_html(get_the_title($pid)) . '</h2></a>';
            $company = self::field($pid,'company');
            $out .= '<p class="owc-company">Developer / Company: ' . ($company ? OWC_App_Discovery::company_link($company) : 'Awaiting verification') . '</p><dl>';
            foreach (array('version'=>'Store version','updated'=>'Store updated','category'=>'Category') as $key=>$label) {
                $value = self::field($pid,$key);
                if ($value) $out .= '<dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd>';
            }
            $out .= '<dt>File size</dt><dd>' . esc_html(self::size_label(get_post_meta($pid,'__wpdm_package_size',true))) . '</dd><dt>Downloads</dt><dd>' . esc_html(number_format_i18n((int)get_post_meta($pid,'__wpdm_download_count',true))) . '</dd></dl>';
            $description = self::field($pid,'description');
            if (!$description) $description = $post->post_excerpt;
            if ($description) $out .= '<p>' . esc_html(wp_trim_words(wp_strip_all_tags($description),24)) . '</p>';
            $out .= OWC_App_Discovery::tags($pid);
            $out .= '<a class="owc-card-action" href="' . esc_url($url) . '">View details &amp; download</a></article>';
        }
        $out .= '</div>';
        if (!$query->posts) $out .= '<p>No matching packages.</p>';
        $pagination = paginate_links(array('base'=>add_query_arg('owc_page','%#%',$base_url), 'format'=>'', 'current'=>$page, 'total'=>$query->max_num_pages, 'add_args'=>array_merge(OWC_App_Discovery::filter_values(), array('owc_q'=>$search,'owc_sort'=>$sort)), 'type'=>'list'));
        if ($pagination) $out .= '<nav aria-label="Archive pages">' . $pagination . '</nav>';
        return $out . '</section>';
    }
}
