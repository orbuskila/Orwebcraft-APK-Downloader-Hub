<?php
/** App details for packages created by WPDM APK Source Connector. */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main id="primary" class="owc-app-detail">
<?php while (have_posts()) : the_post();
    $post_id = get_the_ID();
    $package = (string) get_post_meta($post_id, '_owc_apk_package', true);
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/D', $package)) continue;
    $title = get_the_title();
    $play = 'https://play.google.com/store/apps/details?id=' . rawurlencode($package);
    $apkcombo = (string) get_post_meta($post_id, '_owc_apk_apkcombo_url', true);
    if (wp_parse_url($apkcombo, PHP_URL_SCHEME) !== 'https' || strtolower((string) wp_parse_url($apkcombo, PHP_URL_HOST)) !== 'apkcombo.com' || !preg_match('~/' . preg_quote($package, '~') . '/?$~iD', (string) wp_parse_url($apkcombo, PHP_URL_PATH))) $apkcombo = '';
    $summary = trim((string) get_post_meta($post_id, '_owc_apk_description_manual', true));
    if (!$summary) $summary = trim((string) get_post_meta($post_id, '_owc_apk_description', true));
    $details = array();
    foreach (array('about', 'whats_new', 'version', 'company', 'support_email', 'support_url') as $field) {
        $details[$field] = trim((string) get_post_meta($post_id, '_owc_apk_' . $field . '_manual', true));
        if (!$details[$field]) $details[$field] = trim((string) get_post_meta($post_id, '_owc_apk_' . $field, true));
    }
    if (!$details['about']) $details['about'] = $summary;
    if (!is_email($details['support_email'])) $details['support_email'] = '';
    if (wp_parse_url($details['support_url'], PHP_URL_SCHEME) !== 'https') $details['support_url'] = '';
    $icon = (string) get_post_meta($post_id, '_owc_apk_icon_url', true);
    if (strtolower((string) wp_parse_url($icon, PHP_URL_HOST)) !== 'play-lh.googleusercontent.com' || wp_parse_url($icon, PHP_URL_SCHEME) !== 'https') $icon = '';
    $screenshots = (array) get_post_meta($post_id, '_owc_apk_screenshots', true);
    $screenshots = array_values(array_filter($screenshots, static function ($url) {
        return is_string($url) && wp_parse_url($url, PHP_URL_SCHEME) === 'https' && strtolower((string) wp_parse_url($url, PHP_URL_HOST)) === 'play-lh.googleusercontent.com';
    }));
    $screenshots = array_slice(array_unique($screenshots), 0, 4);
    $initial = function_exists('mb_substr') ? mb_substr($title, 0, 1) : substr($title, 0, 1);
    $editing = current_user_can('edit_post', $post_id);
    $edit_url = get_edit_post_link($post_id);
    ?>
    <div class="owc-app-detail__inner">
        <nav class="owc-app-detail__crumb" aria-label="Breadcrumb"><a href="<?php echo esc_url(home_url('/archive/')); ?>">Downloads</a><span aria-hidden="true">/</span><span><?php echo esc_html($title); ?></span></nav>

        <article <?php post_class('owc-app-detail__article'); ?>>
            <header class="owc-app-detail__hero">
                <div class="owc-app-detail__identity">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php echo get_the_post_thumbnail($post_id, 'thumbnail', array('class' => 'owc-app-detail__icon', 'alt' => '')); ?>
                    <?php elseif ($icon) : ?>
                        <img class="owc-app-detail__icon" src="<?php echo esc_url($icon); ?>" alt="" width="112" height="112" fetchpriority="high">
                    <?php else : ?>
                        <span class="owc-app-detail__icon owc-app-detail__icon--letter" aria-hidden="true"><?php echo esc_html(strtoupper($initial)); ?></span>
                    <?php endif; ?>
                    <div class="owc-app-detail__heading">
                        <div class="owc-app-detail__eyebrow">Android app<?php if (get_post_status($post_id) === 'pending' && $editing) : ?><span class="owc-app-detail__pending">Pending review</span><?php endif; ?></div>
                        <h1><?php echo esc_html($title); ?></h1>
                        <p class="owc-app-detail__company">Developer/company: <?php echo $details['company'] ? OWC_App_Discovery::company_link($details['company']) : 'Not listed'; ?></p>
                        <p class="owc-app-detail__package"><?php echo esc_html($package); ?></p>
                        <?php if ($details['version']) : ?><p class="owc-app-detail__version">Version <?php echo esc_html($details['version']); ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="owc-app-detail__actions">
                    <a class="owc-app-detail__button owc-app-detail__button--primary" href="<?php echo esc_url($play); ?>" target="_blank" rel="nofollow noopener noreferrer">View on Google Play <span aria-hidden="true">↗</span></a>
                    <?php if ($editing && $edit_url) : ?><a class="owc-app-detail__button owc-app-detail__button--edit" href="<?php echo esc_url($edit_url); ?>">Edit app details</a><?php endif; ?>
                </div>
            </header>

            <?php echo OWC_App_Discovery::details($post_id); ?>
            <div class="owc-app-detail__body">
                <div class="owc-app-detail__main">
                    <section class="owc-app-detail__panel" aria-labelledby="owc-about-heading">
                        <h2 id="owc-about-heading">About this app</h2>
                        <?php if ($details['about']) : ?><div class="owc-app-detail__summary"><?php echo wpautop(esc_html($details['about'])); ?></div>
                        <?php else : ?><p class="owc-app-detail__empty">A description is not available yet. Visit Google Play for the latest app information.</p><?php endif; ?>
                    </section>

                    <?php if ($details['whats_new']) : ?>
                    <section class="owc-app-detail__panel" aria-labelledby="owc-updates-heading">
                        <h2 id="owc-updates-heading">What’s new</h2>
                        <div class="owc-app-detail__summary"><?php echo wpautop(esc_html($details['whats_new'])); ?></div>
                    </section>
                    <?php endif; ?>

                    <?php if ($screenshots) : ?>
                    <section class="owc-app-detail__panel" aria-labelledby="owc-previews-heading">
                        <h2 id="owc-previews-heading">App previews</h2>
                        <div class="owc-app-detail__previews">
                            <?php foreach ($screenshots as $index => $screenshot) : ?>
                                <a href="<?php echo esc_url($play); ?>" target="_blank" rel="nofollow noopener noreferrer" aria-label="View <?php echo esc_attr($title); ?> on Google Play, preview <?php echo esc_attr((string) ($index + 1)); ?>"><img src="<?php echo esc_url($screenshot); ?>" alt="<?php echo esc_attr($title . ' screenshot ' . ($index + 1)); ?>" loading="lazy"></a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <aside class="owc-app-detail__aside" aria-label="App links">
                    <?php if ($details['support_email'] || $details['support_url']) : ?>
                    <div class="owc-app-detail__panel">
                        <h2>App support</h2>
                        <ul>
                            <?php if ($details['support_url']) : ?><li><a href="<?php echo esc_url($details['support_url']); ?>" target="_blank" rel="nofollow noopener noreferrer">Support website <span aria-hidden="true">↗</span></a></li><?php endif; ?>
                            <?php if ($details['support_email']) : ?><li><a href="mailto:<?php echo esc_attr($details['support_email']); ?>"><?php echo esc_html($details['support_email']); ?></a></li><?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <div class="owc-app-detail__panel">
                        <h2>More app links</h2>
                        <p>Explore this app at these sources.</p>
                        <ul>
                            <li><a href="<?php echo esc_url('https://apkcube.com/go?q=' . rawurlencode($package) . '&to=download'); ?>" target="_blank" rel="nofollow noopener noreferrer">APK mirror: APKCube <span aria-hidden="true">↗</span></a></li>
                            <?php if ($apkcombo) : ?><li><a href="<?php echo esc_url($apkcombo); ?>" target="_blank" rel="nofollow noopener noreferrer">APK mirror: APKCombo app page <span aria-hidden="true">↗</span></a></li><?php endif; ?>
                            <li><a href="https://apkcombo.com/downloader/" target="_blank" rel="nofollow noopener noreferrer">APKCombo Downloader <span aria-hidden="true">↗</span></a></li>
                        </ul>
                        <?php if ($apkcombo) : ?><p><label>APKCombo app link<br><input type="text" readonly value="<?php echo esc_attr($apkcombo); ?>" style="max-width:100%;width:100%"></label><button type="button" data-owc-copy="<?php echo esc_attr($apkcombo); ?>">Copy APKCombo app link</button></p><?php endif; ?>
                        <p><label>Package name<br><input type="text" readonly value="<?php echo esc_attr($package); ?>" style="max-width:100%;width:100%"></label><button type="button" data-owc-copy="<?php echo esc_attr($package); ?>">Copy package name for APKCombo Downloader</button></p>
                        <small>Paste the package name into APKCombo Downloader. No APK file is stored here.</small>
                    </div>
                </aside>
            </div>
        </article>
    </div>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
