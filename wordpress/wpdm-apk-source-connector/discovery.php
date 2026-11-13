<?php
if (!defined('ABSPATH')) exit;
final class OWC_App_Discovery {
    public static function init() {
        add_action('init', function () { register_taxonomy('owc_ecology', 'wpdmpro', array('label'=>'Ecology relevance', 'public'=>false, 'show_ui'=>true, 'show_admin_column'=>true, 'hierarchical'=>false, 'capabilities'=>array('manage_terms'=>'manage_categories','edit_terms'=>'manage_categories','delete_terms'=>'manage_categories','assign_terms'=>'edit_posts'))); });
        add_action('admin_init', array(__CLASS__, 'seed'));
        add_action('owc_apk_wpdm_hourly', array(__CLASS__, 'sample_downloads'));
        
        add_action('add_meta_boxes_wpdmpro', function () { add_meta_box('owc-discovery', 'Android requirements and file verification', array(__CLASS__,'box'), 'wpdmpro'); });
        add_action('save_post_wpdmpro', array(__CLASS__,'save_box'), 50);
        add_action('added_post_meta', array(__CLASS__,'version'),10,4);
        add_action('updated_post_meta', array(__CLASS__,'version'),10,4);
        add_action('wp_enqueue_scripts', function () {
            if (is_singular('wpdmpro') || is_page(array('archive','my-apps-apk-contributor-dashboard','app-collections','support-ecology','ecology-thank-you')) || is_post_type_archive('wpdmpro')) {
                wp_enqueue_style('owc-discovery', plugins_url('assets/discovery.css', __FILE__), array(), '2.3.2');
                wp_enqueue_script('owc-discovery', plugins_url('assets/discovery.js', __FILE__), array(), '2.3.2', true);
                wp_localize_script('owc-discovery','owcDiscovery',array('url'=>rest_url('owc-discovery/v1/'), 'nonce'=>wp_create_nonce('wp_rest')));
            }
        });
        add_action('rest_api_init', function () {
            register_rest_route('owc-discovery/v1','/view/(?P<id>\d+)',array('methods'=>'POST','callback'=>array(__CLASS__,'view'),'permission_callback'=>'__return_true'));
            register_rest_route('owc-discovery/v1','/save',array('methods'=>'POST','callback'=>array(__CLASS__,'rest_save'),'permission_callback'=>function(){ return is_user_logged_in(); }));
        });
        add_shortcode('owc_app_collections', array(__CLASS__, 'collections'));
        add_shortcode('owc_contribution_dashboard', array(__CLASS__, 'dashboard'));
        add_shortcode('owc_ecology_support', array(__CLASS__, 'support'));
        add_shortcode('owc_ecology_thanks', array(__CLASS__, 'thanks'));
        add_filter('the_content', function ($content) {
            if (!is_main_query() || !in_the_loop() || post_password_required()) return $content;
            if (is_page('my-apps-apk-contributor-dashboard') && empty($_GET['adb_page'])) return self::dashboard() . $content;
            if (is_page()) { $settings=get_option('give_settings',array()); if(!empty($settings['success_page']) && get_queried_object_id()===absint($settings['success_page'])) return $content . self::thanks(); }
            if (is_singular('wpdmpro') && !get_post_meta(get_the_ID(),'_owc_apk_connector_id',true)) return $content . self::details(get_the_ID());
            return $content;
        },30);
        add_action('template_redirect', function () { if (is_page(array('my-apps-apk-contributor-dashboard','app-collections'))) { if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true); nocache_headers(); } });
    }
    public static function list_value($value) { return is_array($value) ? $value : array(); }
    public static function labels() { return array('supports-conservation'=>'Supports conservation','nature-education'=>'Nature education','ocean'=>'Ocean','forest'=>'Forest','wildlife'=>'Wildlife','community-recommended'=>'Community recommended','astronomy'=>'Astronomy'); }
    public static function seed() {
        if (!current_user_can('manage_options') || get_option('owc_discovery_setup')==='2.3.2') return;
        foreach(self::labels() as $slug=>$name) if(!term_exists($slug,'owc_ecology')) wp_insert_term($name,'owc_ecology',array('slug'=>$slug));
        foreach(array('app-collections'=>array('My app collections','[owc_app_collections]'),'support-ecology'=>array('Support ecology','[owc_ecology_support]'),'ecology-thank-you'=>array('Thank you for your support','[owc_ecology_thanks]'),'app-offline'=>array('You are offline','<h2>You are offline</h2><p>Reconnect to browse the latest app details, save collections or donate. Previously cached public pages may still be available.</p><p><a href="/archive/">Try the app archive again</a></p>')) as $slug=>$data) {
            if (!get_page_by_path($slug)) wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$data[0],'post_name'=>$slug,'post_content'=>$data[1]));
        }
        foreach(get_posts(array('post_type'=>'wpdmpro','post_status'=>'publish','posts_per_page'=>500,'fields'=>'ids')) as $id) {
            if(!metadata_exists('post',$id,'__wpdm_download_count')) update_post_meta($id,'__wpdm_download_count',0);
            if(!metadata_exists('post',$id,'_owc_week_views')) update_post_meta($id,'_owc_week_views',0);
            if(!metadata_exists('post',$id,'_owc_week_start')) update_post_meta($id,'_owc_week_start',self::week());
            self::version(0,$id,'_owc_apk_version',OWC_APK_Archive_Improvements::field($id,'version'));
            $topics=array('org.inaturalist.android'=>array('wildlife','nature-education'),'org.inaturalist.seek'=>array('wildlife','nature-education'),'de.tu_chemnitz.mi.kahst.birdnet'=>array('wildlife','nature-education'));
            $package=get_post_meta($id,'_owc_apk_package',true); if(isset($topics[$package])) wp_set_object_terms($id,$topics[$package],'owc_ecology',true);
        }
        self::sample_downloads();
        update_option('owc_discovery_setup','2.3.2',false);
    }
    public static function get($key) { return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : ''; }
    public static function filter_values() { $out=array(); foreach(array('company','category','android','version','after','before','ecology') as $k) if(self::get('owc_'.$k)!=='') $out['owc_'.$k]=self::get('owc_'.$k); return $out; }
    public static function filters($args) {
        $mq=array('relation'=>'AND');
        foreach(array('company','category','android','version') as $key) {
            $v=self::get('owc_'.$key); if($v==='') continue;
            $manual='_owc_apk_'.$key.'_manual'; $auto='_owc_apk_'.$key;
            $compare=$key==='company'?'=':'LIKE';
            $mq[]=array('relation'=>'OR',array('key'=>$manual,'value'=>$v,'compare'=>$compare),array('relation'=>'AND',array('relation'=>'OR',array('key'=>$manual,'compare'=>'NOT EXISTS'),array('key'=>$manual,'value'=>'','compare'=>'=')),array('key'=>$auto,'value'=>$v,'compare'=>$compare)));
        }
        if(count($mq)>1) $args['meta_query']=$mq;
        $sort=self::get('owc_sort');
        if($sort==='popular') $args['meta_key']='__wpdm_download_count';
        if($sort==='week') { $args['meta_key']='_owc_week_views'; $args['meta_query'][]=array('key'=>'_owc_week_start','value'=>self::week(),'compare'=>'='); }
        $dq=array('inclusive'=>true);
        foreach(array('after','before') as $key) { $v=self::get('owc_'.$key); if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)) $dq[$key]=$v; }
        if(count($dq)>1) $args['date_query']=array($dq);
        $tag=self::get('owc_ecology'); if(isset(self::labels()[$tag])) $args['tax_query']=array(array('taxonomy'=>'owc_ecology','field'=>'slug','terms'=>$tag));
        return $args;
    }
    public static function filter_controls() {
        $out=''; foreach(array('company'=>'Developer / company (exact name)','category'=>'Store category','android'=>'Android requirements (text)','version'=>'Store version') as $k=>$label) $out.='<label>'.esc_html($label).'<input name="owc_'.$k.'" value="'.esc_attr(self::get('owc_'.$k)).'"></label>';
        foreach(array('after'=>'Added after','before'=>'Added before') as $k=>$label) $out.='<label>'.$label.'<input type="date" name="owc_'.$k.'" value="'.esc_attr(self::get('owc_'.$k)).'"></label>';
        $out.='<label>Ecology relevance<select name="owc_ecology"><option value="">All topics</option>'; foreach(self::labels() as $slug=>$label) $out.='<option value="'.$slug.'" '.selected(self::get('owc_ecology'),$slug,false).'>'.esc_html($label).'</option>';
        return $out.'</select></label><a href="'.esc_url(home_url('/archive/')).'">Reset filters</a>';
    }
    public static function company_link($name) { return '<a href="'.esc_url(add_query_arg('owc_company',$name,home_url('/archive/'))).'">'.esc_html($name).'</a>'; }
    public static function archive_header() {
        $company=self::get('owc_company');
        $out=$company?'<h2>Apps by '.esc_html($company).'</h2>':'';
        $out.='<nav class="owc-discovery-nav"><a href="'.esc_url(home_url('/app-collections/')).'">Favorites &amp; collections</a><a href="'.esc_url(home_url('/my-apps-apk-contributor-dashboard/')).'">My Apps</a><a href="'.esc_url(home_url('/support-ecology/')).'">Support ecology</a></nav><p>Popular this week is ranked by recorded app-page views since Monday. Counts begin with this update.</p>';
        return $out;
    }
    public static function tags($id) {
        $out=''; $terms=get_the_terms($id,'owc_ecology'); if(is_array($terms)) foreach($terms as $term) $out.='<a class="owc-topic" href="'.esc_url(add_query_arg('owc_ecology',$term->slug,home_url('/archive/'))).'">'.esc_html($term->name).'</a> ';
        return $out;
    }
    public static function box($post) {
        wp_nonce_field('owc_discovery_box','owc_discovery_nonce');
        foreach(array('android_manual'=>'Android requirements (verified source)','signer'=>'APK signer certificate SHA-256 (verified by apksigner)','signer_source'=>'Signature verification source / report URL') as $key=>$label) echo '<p><label>'.esc_html($label).'<input class="widefat" name="owc_'.$key.'" value="'.esc_attr(get_post_meta($post->ID,'_owc_apk_'.$key,true)).'"></label></p>';
        echo '<p>Save computes SHA-256 for local APK files in uploads or WPDM storage, up to 100 MB each. Remote files are not fetched. Certificate fingerprints must come from actual signature verification; checksums are not malware scans. Assign reviewed ecology tags in the taxonomy box.</p>';
    }
    public static function save_box($id) {
        if(wp_is_post_revision($id)||!current_user_can('edit_post',$id)||!isset($_POST['owc_discovery_nonce'])||!is_string($_POST['owc_discovery_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['owc_discovery_nonce'])),'owc_discovery_box')) return;
        foreach(array('android_manual','signer','signer_source') as $key) if(isset($_POST['owc_'.$key])&&is_string($_POST['owc_'.$key])) {
            $v=sanitize_text_field(wp_unslash($_POST['owc_'.$key]));
            if(in_array($key,array('signer','signer_source'),true)&&!current_user_can('manage_options')) continue;
            if($key==='signer') { $v=strtolower(str_replace(':','',$v)); if($v!==''&&!preg_match('/^[a-f0-9]{64}$/D',$v)) continue; }
            if($key==='signer_source') $v=esc_url_raw($v,array('https'));
            update_post_meta($id,'_owc_apk_'.$key,$v);
        }
        $hashes=array(); $uploads=wp_upload_dir();
        $roots=array(realpath($uploads['basedir']),realpath(WP_CONTENT_DIR.'/uploads/download-manager-files'));
        foreach(array_slice(self::list_value(get_post_meta($id,'__wpdm_files',true)),0,10) as $file) {
            if(!is_string($file)||strpos($file,'://')!==false) continue;
            $path=realpath($file); if(!$path) $path=realpath(WP_CONTENT_DIR.'/uploads/download-manager-files/'.ltrim($file,'/'));
            if(!$path||strtolower(pathinfo($path,PATHINFO_EXTENSION))!=='apk'||!is_file($path)) continue;
            $allowed=false; foreach($roots as $base) if($base&&strpos($path,$base.DIRECTORY_SEPARATOR)===0) $allowed=true;
            if(!$allowed||filesize($path)>100*1024*1024) continue;
            $hashes[]=array('name'=>basename($path),'sha256'=>hash_file('sha256',$path),'bytes'=>filesize($path));
        }
        update_post_meta($id,'_owc_apk_hashes',$hashes);
    }
    public static function version($mid,$id,$key,$value) {
        if(!in_array($key,array('_owc_apk_version','_owc_apk_version_manual'),true)||get_post_type($id)!=='wpdmpro'||!is_string($value)||trim($value)==='') return;
        $history=self::list_value(get_post_meta($id,'_owc_version_history',true));
        $last=end($history); if(is_array($last)&&$last['version']===$value) return;
        $history[]=array('version'=>sanitize_text_field($value),'date'=>current_time('mysql'),'source'=>'Store metadata');
        update_post_meta($id,'_owc_version_history',array_slice($history,-30));
    }
    public static function sample_downloads() {
        foreach(get_posts(array('post_type'=>'wpdmpro','post_status'=>'publish','posts_per_page'=>500,'fields'=>'ids')) as $id) {
            $count=(int)get_post_meta($id,'__wpdm_download_count',true);
            $history=self::list_value(get_post_meta($id,'_owc_download_samples',true));
            $history[wp_date('Y-m-d')]=max(0,$count); ksort($history);
            update_post_meta($id,'_owc_download_samples',array_slice($history,-31,null,true));
        }
    }
    public static function week() { return wp_date('Y-m-d',strtotime('monday this week',current_time('timestamp'))); }
    public static function view($req) {
        $id=absint($req['id']); if(get_post_type($id)!=='wpdmpro'||get_post_status($id)!=='publish'||post_password_required($id)) return new WP_Error('unavailable','App unavailable',array('status'=>404));
        // Per app cookie limits repeat views; aggregate only, no identifying data retained.
        $cookie='owc_view_'.$id; if(isset($_COOKIE[$cookie])) return array('counted'=>false);
        setcookie($cookie,'1',array('expires'=>time()+HOUR_IN_SECONDS,'path'=>'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax'));
        $week=self::week(); if(get_post_meta($id,'_owc_week_start',true)!==$week) { update_post_meta($id,'_owc_week_start',$week); update_post_meta($id,'_owc_week_views',0); }
        update_post_meta($id,'_owc_week_views',(int)get_post_meta($id,'_owc_week_views',true)+1);
        update_post_meta($id,'_owc_views',(int)get_post_meta($id,'_owc_views',true)+1);
        $daily=self::list_value(get_post_meta($id,'_owc_daily_views',true)); $day=wp_date('Y-m-d'); $daily[$day]=isset($daily[$day])?(int)$daily[$day]+1:1; ksort($daily); update_post_meta($id,'_owc_daily_views',array_slice($daily,-30,null,true));
        return array('counted'=>true);
    }
    public static function details($id) {
        if(post_password_required($id)) return '';
        $out='<section class="owc-discovery-panel" data-owc-app="'.absint($id).'"><h2>App sources &amp; verification</h2>'.self::tags($id);
        $company=OWC_APK_Archive_Improvements::field($id,'company'); if($company) $out.='<p>Developer / company: '.self::company_link($company).'</p>';
        $android=OWC_APK_Archive_Improvements::field($id,'android'); $out.='<p>Android requirements: '.esc_html($android?:'Not supplied by source').'</p>';
        $package=get_post_meta($id,'_owc_apk_package',true); if($package) $out.='<p>Official store: <a rel="noopener noreferrer" href="'.esc_url(('https://play.google.com/store/apps/details?id=' . rawurlencode($package))).'">Google Play listing</a></p>';
        $support=OWC_APK_Archive_Improvements::field($id,'support_url'); if($support) $out.='<p>Developer-provided support: <a rel="noopener noreferrer" href="'.esc_url($support).'">Support website</a></p>';
        $files=self::list_value(get_post_meta($id,'__wpdm_files',true)); $local=false;
        foreach($files as $file) if(is_string($file)&&strpos($file,'://')===false) $local=true;
        $out.='<p>'.($local?'Uploaded file: user-submitted; safety not independently verified.':'Linked source: external download or store link; no local APK verified.').'</p>';
        $hashes=self::list_value(get_post_meta($id,'_owc_apk_hashes',true)); foreach($hashes as $hash) if(is_array($hash)&&isset($hash['name'],$hash['sha256'])) $out.='<p>SHA-256 · '.esc_html($hash['name']).'<br><code>'.esc_html($hash['sha256']).'</code></p>';
        if(!$hashes) $out.='<p>APK SHA-256: not computed for this package.</p>';
        $signer=get_post_meta($id,'_owc_apk_signer',true); $proof=get_post_meta($id,'_owc_apk_signer_source',true);
        $out.='<p>Signature certificate: '.($signer?'<code>'.esc_html($signer).'</code> (administrator supplied)':'Not verified').'</p>';
        if($proof) $out.='<p><a href="'.esc_url($proof).'">Signature verification report</a></p>';
        $out.='<p>A checksum identifies a file; it does not establish that it is safe.</p>';
        $out.='<p>Downloads (WPDM total): '.number_format_i18n((int)get_post_meta($id,'__wpdm_download_count',true)).' · Recorded page views: '.number_format_i18n((int)get_post_meta($id,'_owc_views',true)).'</p>';
        $out.=self::actions($id);
        $out.='<details><summary>Previous store versions observed</summary><p>Metadata history begins with this update. These entries do not provide older APK downloads.</p><ul>';
        foreach(array_reverse(self::list_value(get_post_meta($id,'_owc_version_history',true))) as $row) if(is_array($row)) $out.='<li>'.esc_html($row['version'].' · '.$row['date']).'</li>';
        $out.='</ul></details><details><summary>Page views over time (last 30 recorded days)</summary><ul>';
        foreach(self::list_value(get_post_meta($id,'_owc_daily_views',true)) as $date=>$count) $out.='<li>'.esc_html($date).' · '.absint($count).'</li>';
         $out.='</ul></details><details><summary>Download counts over time</summary><p>WPDM cumulative totals sampled hourly when WP-Cron runs. History begins with this update.</p><ul>';
        foreach(self::list_value(get_post_meta($id,'_owc_download_samples',true)) as $date=>$count) $out.='<li>'.esc_html($date).' · '.absint($count).' total downloads</li>';
        return $out.'</ul></details></section>';
    }
    public static function actions($id) {
        if(!is_user_logged_in()) return '<p><a href="'.esc_url(wp_login_url(get_permalink($id))).'">Sign in to save or follow this app</a></p>';
        $uid=get_current_user_id(); $out='<div class="owc-user-actions">';
        foreach(array('favorites'=>'Favorite','following'=>'Follow updates') as $key=>$label) {
            $list=self::list_value(get_user_meta($uid,'_owc_'.$key,true)); $active=in_array($id,array_map('absint',$list),true);
            $out.='<button type="button" data-owc-toggle="'.$key.'" data-id="'.absint($id).'" aria-pressed="'.($active?'true':'false').'">'.($active?'Remove '.$label:$label).'</button> ';
        }
        $out.='<label>Collection name <input maxlength="80" data-owc-collection placeholder="Forest Preservation"></label><button type="button" data-owc-add data-id="'.absint($id).'">Save to collection</button><span role="status" class="owc-action-status"></span></div>';
        return $out;
    }
    public static function rest_save($req) {
        $id=absint($req->get_param('id')); $kind=sanitize_key($req->get_param('kind')); $uid=get_current_user_id();
        if(get_post_type($id)!=='wpdmpro'||get_post_status($id)!=='publish'||post_password_required($id)) return new WP_Error('unavailable','App unavailable',array('status'=>404));
        if(in_array($kind,array('favorites','following'),true)) {
            $list=array_values(array_filter(array_map('absint',self::list_value(get_user_meta($uid,'_owc_'.$kind,true))))); $on=!in_array($id,$list,true);
            if($on) { if(count($list)>=500) return new WP_Error('limit','Limit of 500 saved apps reached',array('status'=>400)); $list[]=$id; } else $list=array_values(array_diff($list,array($id)));
            update_user_meta($uid,'_owc_'.$kind,$list); if($kind==='following'&&$on) { $seen=self::list_value(get_user_meta($uid,'_owc_seen_versions',true)); $seen[$id]=OWC_APK_Archive_Improvements::field($id,'version'); update_user_meta($uid,'_owc_seen_versions',$seen); }
            return array('active'=>$on);
        }
        if($kind==='collection') {
            $name=substr(sanitize_text_field($req->get_param('name')),0,80); if(!$name) return new WP_Error('name','Enter a collection name',array('status'=>400));
            $all=self::list_value(get_user_meta($uid,'_owc_collections',true)); if(!isset($all[$name])&&count($all)>=30) return new WP_Error('limit','Limit of 30 collections reached',array('status'=>400));
            $list=isset($all[$name])?array_map('absint',(array)$all[$name]):array(); if(count($list)>=500) return new WP_Error('limit','Collection is full',array('status'=>400));
            if($req->get_param('remove')) $list=array_values(array_diff($list,array($id))); elseif(!in_array($id,$list,true)) $list[]=$id;
            $all[$name]=$list; update_user_meta($uid,'_owc_collections',$all); return array('active'=>true);
        }
        return new WP_Error('kind','Invalid action',array('status'=>400));
    }
    public static function app_list($ids,$collection='') {
        $out='<ul class="owc-saved-list">'; foreach(array_unique(array_map('absint',(array)$ids)) as $id) if(get_post_type($id)==='wpdmpro'&&get_post_status($id)==='publish'&&!post_password_required($id)) {
            $out.='<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a>';
            if($collection) $out.=' <button type="button" data-owc-remove="'.esc_attr($collection).'" data-id="'.$id.'">Remove from collection</button>';
            $out.='</li>';
        } return $out.'</ul>';
    }
    public static function collections() {
        if(!is_user_logged_in()) return '<p>Please <a href="'.esc_url(wp_login_url(home_url('/app-collections/'))).'">sign in</a> to view your private collections.</p>';
        $uid=get_current_user_id(); $out='<section class="owc-discovery-panel"><h2>My favorites</h2>'.self::app_list(get_user_meta($uid,'_owc_favorites',true)).'<h2>My collections</h2><p>Save apps from their detail pages. Collections are private to your account.</p>';
        foreach(self::list_value(get_user_meta($uid,'_owc_collections',true)) as $name=>$ids) $out.='<h3>'.esc_html($name).'</h3>'.self::app_list($ids,$name);
        return $out.self::updates().'<span class="owc-action-status" role="status"></span></section>';
    }
    public static function updates() {
        $uid=get_current_user_id(); $seen=self::list_value(get_user_meta($uid,'_owc_seen_versions',true)); $out='<h2>App update notifications</h2><p>Updates appear here when the site observes a new store version for an app you follow.</p><ul>';
        foreach(self::list_value(get_user_meta($uid,'_owc_following',true)) as $id) { $id=absint($id); if(get_post_status($id)!=='publish'||post_password_required($id)) continue; $v=OWC_APK_Archive_Improvements::field($id,'version'); $changed=$v&&isset($seen[$id])&&$seen[$id]!==$v;
            $out.='<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a> · '.esc_html($v?:'Version unavailable').($changed?' · New version observed':'').'</li>'; }
        return $out.'</ul>';
    }
    public static function dashboard() {
        if(!is_user_logged_in()) return '';
        $q=new WP_Query(array('suppress_filters'=>true,'ignore_custom_sort'=>true,'post_type'=>'wpdmpro','author'=>get_current_user_id(),'post_status'=>array('publish','pending','draft','private'),'posts_per_page'=>20,'paged'=>max(1,absint(self::get('owc_my_page'))),'orderby'=>'date','order'=>'DESC'));
        $out='<section class="owc-discovery-panel"><h2>Your contributions</h2><p>'.number_format_i18n($q->found_posts).' submitted packages · <a href="'.esc_url(home_url('/app-collections/')).'">Favorites, collections &amp; updates</a></p><div class="owc-contributions">';
        foreach($q->posts as $post) { $id=$post->ID; $out.='<article><h3>'.esc_html(get_the_title($id)).'</h3><p>'.esc_html($post->post_status==='publish'?'Published':ucfirst($post->post_status)).' · Added '.esc_html(get_the_date('M j, Y',$id)).'</p><p>Downloads: '.number_format_i18n((int)get_post_meta($id,'__wpdm_download_count',true)).' · Recorded views: '.number_format_i18n((int)get_post_meta($id,'_owc_views',true)).'</p>';
            if($post->post_status==='publish'||current_user_can('read_post',$id)) $out.='<a href="'.esc_url(get_permalink($id)).'">View app</a> ';
            if(current_user_can('edit_post',$id)) $out.='<a href="'.esc_url(get_edit_post_link($id)).'">Edit details</a>';
            $out.='</article>'; }
        $out.='</div>'.paginate_links(array('base'=>add_query_arg('owc_my_page','%#%',home_url('/my-apps-apk-contributor-dashboard/')),'format'=>'','current'=>max(1,absint(self::get('owc_my_page'))),'total'=>$q->max_num_pages));
        return $out.self::updates().'</section>';
    }
    public static function support() {
        $out='<section class="owc-discovery-panel"><h2>Support the ecology app archive</h2><p>This archive helps visitors discover tools for nature education, forests, oceans and wildlife. App topic labels describe relevance; they do not guarantee environmental outcomes.</p><p>Support can help maintain the archive, improve app information and keep discovery tools available.</p><p>Payment methods and recurring options are provided by the donation service at checkout. Review the fundraiser beneficiary and terms there.</p>';
        $url=get_option('owc_ecology_donation_url',home_url('/give/good-luck-for-the-earth-ecosystem/')); if($url) $out.='<p><a class="owc-card-action" href="'.esc_url($url).'" rel="noopener noreferrer">Open donation page</a></p>';
        else $out.='<p>A dedicated ecology fundraiser link has not been configured yet. Please use the existing donation options on this website.</p>';
        return $out.'<p><a href="'.esc_url(home_url('/archive/')).'">Explore the archive</a></p></section>';
    }
    public static function thanks() { return '<section class="owc-discovery-panel"><h2>Thank you for supporting the archive</h2><p>Your interest and support help this community grow. If you donated, your donation provider’s receipt confirms payment and the named beneficiary.</p><p><a href="'.esc_url(home_url('/archive/')).'">Discover more nature apps</a> · <a href="'.esc_url(home_url('/my-apps-apk-contributor-dashboard/')).'">Contribute an app</a></p></section>'; }

}
