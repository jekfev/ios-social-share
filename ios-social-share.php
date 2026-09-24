<?php
/*
Plugin Name: iPhone Social Share Buttons
Description: Легкие кнопки VK, Telegram, MAX, Одноклассники и WhatsApp в стиле iPhone с уникальным счетчиком нажатий.
Version: 1.3
Author: Evgeny Fed
License: GPL-2.0-or-later
*/

if (!defined('ABSPATH')) exit;

define('IOS_SOCIAL_SHARE_VERSION', '1.3');
define('IOS_SOCIAL_SHARE_OPTION', 'ios_social_share_version');
define('IOS_SOCIAL_SHARE_COOKIE', 'ios_social_visitor');

function ios_social_share_counts_table() {
    global $wpdb;
    return $wpdb->prefix . 'ios_social_share_counts';
}

function ios_social_share_unique_table() {
    global $wpdb;
    return $wpdb->prefix . 'ios_social_share_unique';
}

function ios_social_share_install() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $counts = ios_social_share_counts_table();
    $unique = ios_social_share_unique_table();

    dbDelta("CREATE TABLE {$counts} (
        post_id bigint(20) unsigned NOT NULL,
        network varchar(20) NOT NULL,
        unique_count bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (post_id, network),
        KEY network (network)
    ) {$charset};");

    dbDelta("CREATE TABLE {$unique} (
        post_id bigint(20) unsigned NOT NULL,
        network varchar(20) NOT NULL,
        visitor_hash char(64) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (post_id, network, visitor_hash),
        KEY visitor_hash (visitor_hash),
        KEY created_at (created_at)
    ) {$charset};");

    update_option(IOS_SOCIAL_SHARE_OPTION, IOS_SOCIAL_SHARE_VERSION, false);
}
register_activation_hook(__FILE__, 'ios_social_share_install');

add_action('plugins_loaded', function () {
    if (get_option(IOS_SOCIAL_SHARE_OPTION) !== IOS_SOCIAL_SHARE_VERSION) {
        ios_social_share_install();
    }
});

function ios_social_share_visitor_hash() {
    if (is_user_logged_in()) {
        $id = 'user:' . get_current_user_id();
    } else {
        $id = isset($_COOKIE[IOS_SOCIAL_SHARE_COOKIE])
            ? sanitize_text_field(wp_unslash($_COOKIE[IOS_SOCIAL_SHARE_COOKIE]))
            : '';

        if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) return '';
        $id = 'visitor:' . strtolower($id);
    }

    return hash_hmac('sha256', $id, wp_salt('auth'));
}

function ios_social_share_ip_hash() {
    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
        : '';

    return $ip ? hash_hmac('sha256', $ip, wp_salt('auth')) : '';
}

function ios_social_share_cookie_js() {
    return <<<'JS'
(function () {
    var name = 'ios_social_visitor';
    function getCookie(n) {
        var p = n + '=', c = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < c.length; i++) {
            if (c[i].indexOf(p) === 0) return decodeURIComponent(c[i].substring(p.length));
        }
        return '';
    }
    function uuid() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 3 | 8);
            return v.toString(16);
        });
    }
    if (!getCookie(name)) {
        document.cookie = name + '=' + encodeURIComponent(uuid()) +
            '; path=/; max-age=157680000; SameSite=Lax' +
            (location.protocol === 'https:' ? '; Secure' : '');
    }
})();
JS;
}

function ios_social_share_html() {
    $post_id = get_the_ID();
    if (!$post_id) return '';

    global $wpdb;
    $table = ios_social_share_counts_table();
    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT network, unique_count FROM {$table} WHERE post_id = %d", $post_id),
        ARRAY_A
    );

    $counts = [];
    foreach ($rows as $row) $counts[$row['network']] = (int) $row['unique_count'];

    $base = plugin_dir_url(__FILE__) . 'icons/';
    $networks = [
        'vk' => ['name' => 'VK', 'bg' => '#0077FF', 'icon' => $base . 'vk.svg'],
        'telegram' => ['name' => 'Telegram', 'bg' => '#229ED9', 'icon' => $base . 'telegram.svg'],
        'max' => ['name' => 'MAX', 'bg' => 'linear-gradient(135deg,#00BFFF 0%,#471AFF 48%,#9500FF 100%)', 'icon' => $base . 'max-logo.svg'],
        'ok' => ['name' => 'Одноклассники', 'bg' => '#EE8208', 'icon' => $base . 'odnoklassniki.svg'],
        'whatsapp' => ['name' => 'WhatsApp', 'bg' => '#25D366', 'icon' => $base . 'whatsapp.svg'],
    ];

    ob_start(); ?>
    <div class="ios-social-share"
        data-post-id="<?php echo esc_attr($post_id); ?>"
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('ios_social_share')); ?>">
        <?php foreach ($networks as $key => $network):
            $count = $counts[$key] ?? 0; ?>
            <a href="#" class="ios-social-share__item"
                data-network="<?php echo esc_attr($key); ?>"
                target="_blank" rel="noopener noreferrer"
                aria-label="Поделиться в <?php echo esc_attr($network['name']); ?>">
                <span class="ios-social-share__badge" <?php echo $count ? '' : 'hidden'; ?>>
                    <?php echo $count > 99 ? '99+' : esc_html($count); ?>
                </span>
                <span class="ios-social-share__icon" style="background:<?php echo esc_attr($network['bg']); ?>">
                    <img src="<?php echo esc_url($network['icon']); ?>?v=1.3"
                        alt="" width="50" height="50" loading="lazy" decoding="async">
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <style>
    .ios-social-share{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:20px 0}
    .ios-social-share__item{position:relative;display:block;width:50px;height:50px;text-decoration:none!important;outline:0}
    .ios-social-share__icon{width:50px;height:50px;display:flex;align-items:center;justify-content:center;border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.14);transition:transform .12s ease,filter .12s ease}
    .ios-social-share__icon img{display:block;width:29px;height:29px;object-fit:contain;filter:brightness(0) invert(1)}
    .ios-social-share__item[data-network=max] .ios-social-share__icon img{width:42px;height:42px;filter:none}
    .ios-social-share__badge{position:absolute;z-index:10;top:-7px;right:-7px;min-width:21px;height:21px;padding:0 5px;display:flex;align-items:center;justify-content:center;box-sizing:border-box;border-radius:999px;background:#ff3b30;color:#fff;border:2px solid #fff;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","SF Pro Text",Arial,sans-serif;font-size:11px;font-weight:700;line-height:1;box-shadow:0 1px 3px rgba(0,0,0,.2)}
    .ios-social-share__item:hover .ios-social-share__icon{transform:scale(1.04);filter:brightness(1.04)}
    .ios-social-share__item:active .ios-social-share__icon{transform:scale(.92)}
    @media(max-width:600px){.ios-social-share{gap:10px}}
    </style>

    <script>
    document.addEventListener('DOMContentLoaded',function(){
        <?php echo ios_social_share_cookie_js(); ?>
        document.querySelectorAll('.ios-social-share').forEach(function(w){
            var postId=w.dataset.postId, ajax=w.dataset.ajaxUrl, nonce=w.dataset.nonce;
            var url=window.location.href, title=document.title;
            var share={
                vk:'https://vk.com/share.php?url='+encodeURIComponent(url)+'&title='+encodeURIComponent(title),
                telegram:'https://t.me/share/url?url='+encodeURIComponent(url)+'&text='+encodeURIComponent(title),
                max:'https://max.ru/:share?text='+encodeURIComponent(title+' '+url),
                ok:'https://connect.ok.ru/offer?url='+encodeURIComponent(url)+'&title='+encodeURIComponent(title),
                whatsapp:'https://wa.me/?text='+encodeURIComponent(title+' '+url)
            };
            w.querySelectorAll('.ios-social-share__item').forEach(function(b){
                var n=b.dataset.network, badge=b.querySelector('.ios-social-share__badge');
                b.href=share[n];
                b.addEventListener('click',function(){
                    if(b.dataset.sending==='1'||b.dataset.counted==='1')return;
                    b.dataset.sending='1';
                    var fd=new FormData();
                    fd.append('action','ios_social_share_click');
                    fd.append('nonce',nonce);
                    fd.append('post_id',postId);
                    fd.append('network',n);
                    fetch(ajax,{method:'POST',body:fd,keepalive:true,credentials:'same-origin'})
                    .then(function(r){return r.json()})
                    .then(function(d){
                        b.dataset.sending='0';
                        if(!d||!d.success||!d.data)return;
                        b.dataset.counted=d.data.unique?'1':'0';
                        var c=parseInt(d.data.count,10)||0;
                        if(c){badge.hidden=false;badge.textContent=c>99?'99+':c}
                    }).catch(function(){b.dataset.sending='0'});
                });
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

function ios_social_share() { echo ios_social_share_html(); }
add_shortcode('ios_social_share','ios_social_share_html');
add_action('wp_ajax_ios_social_share_click','ios_social_share_click');
add_action('wp_ajax_nopriv_ios_social_share_click','ios_social_share_click');

function ios_social_share_click() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])),'ios_social_share')) {
        wp_send_json_error(['message'=>'Invalid nonce'],403);
    }

    $post_id=isset($_POST['post_id'])?absint($_POST['post_id']):0;
    $network=isset($_POST['network'])?sanitize_key($_POST['network']):'';
    $allowed=['vk','telegram','max','ok','whatsapp'];

    if(!$post_id||!in_array($network,$allowed,true))wp_send_json_error(['message'=>'Invalid data'],400);

    $post=get_post($post_id);
    if(!$post||$post->post_status!=='publish')wp_send_json_error(['message'=>'Post not available'],404);

    $ip=ios_social_share_ip_hash();
    if($ip){
        $key='ios_share_rl_'.substr($ip,0,32);
        $data=get_transient($key);
        if(!is_array($data))$data=['count'=>0];
        $data['count']++;
        if($data['count']>60)wp_send_json_error(['message'=>'Too many requests'],429);
        set_transient($key,$data,10*MINUTE_IN_SECONDS);
    }

    $visitor=ios_social_share_visitor_hash();
    if(!$visitor)wp_send_json_error(['message'=>'Visitor identifier unavailable'],400);

    global $wpdb;
    $unique=ios_social_share_unique_table();
    $counts=ios_social_share_counts_table();
    $now=current_time('mysql',true);

    $inserted=$wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$unique} (post_id,network,visitor_hash,created_at) VALUES (%d,%s,%s,%s)",
        $post_id,$network,$visitor,$now
    ));
    if($inserted===false)wp_send_json_error(['message'=>'Database error'],500);

    $is_unique=$inserted===1;
    if($is_unique){
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$counts} (post_id,network,unique_count) VALUES (%d,%s,1)
             ON DUPLICATE KEY UPDATE unique_count=unique_count+1",
            $post_id,$network
        ));
    }

    $count=(int)$wpdb->get_var($wpdb->prepare(
        "SELECT unique_count FROM {$counts} WHERE post_id=%d AND network=%s",
        $post_id,$network
    ));

    wp_send_json_success(['unique'=>$is_unique,'count'=>$count]);
}
