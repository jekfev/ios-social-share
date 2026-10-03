<?php
/*
Plugin Name: iPhone Social Share Buttons
Description: Легкие кнопки VK, Telegram, MAX, Одноклассники и WhatsApp в стиле iPhone с уникальным счетчиком нажатий.
Version: 1.4
Author: Evgeny Fed
License: GPL-2.0-or-later
*/

if (!defined('ABSPATH')) {
    exit;
}

const IOS_SOCIAL_SHARE_VERSION = '1.4';
const IOS_SOCIAL_SHARE_OPTION = 'ios_social_share_version';
const IOS_SOCIAL_SHARE_NETWORKS_OPTION = 'ios_social_share_networks';
const IOS_SOCIAL_SHARE_COOKIE = 'ios_social_visitor';

function ios_social_share_get_counts_table() {
    global $wpdb;
    return $wpdb->prefix . 'ios_social_share_counts';
}

function ios_social_share_get_unique_table() {
    global $wpdb;
    return $wpdb->prefix . 'ios_social_share_unique';
}

function ios_social_share_install() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $counts_table = ios_social_share_get_counts_table();
    $unique_table = ios_social_share_get_unique_table();

    $sql_counts = "CREATE TABLE {$counts_table} (
        post_id bigint(20) unsigned NOT NULL,
        network varchar(20) NOT NULL,
        unique_count bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (post_id, network),
        KEY network (network)
    ) {$charset_collate};";

    $sql_unique = "CREATE TABLE {$unique_table} (
        post_id bigint(20) unsigned NOT NULL,
        network varchar(20) NOT NULL,
        visitor_hash char(64) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (post_id, network, visitor_hash),
        KEY visitor_hash (visitor_hash),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta($sql_counts);
    dbDelta($sql_unique);

    update_option(IOS_SOCIAL_SHARE_OPTION, IOS_SOCIAL_SHARE_VERSION, false);
}

register_activation_hook(__FILE__, 'ios_social_share_install');


function ios_social_share_get_enabled_networks() {
    $defaults = [
        'vk' => true,
        'telegram' => true,
        'max' => true,
        'ok' => true,
        'whatsapp' => true,
    ];

    $saved = get_option(IOS_SOCIAL_SHARE_NETWORKS_OPTION, []);

    if (!is_array($saved)) {
        return $defaults;
    }

    return array_merge($defaults, array_intersect_key($saved, $defaults));
}

function ios_social_share_register_settings() {
    register_setting(
        'ios_social_share_settings',
        IOS_SOCIAL_SHARE_NETWORKS_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => function ($value) {
                $allowed = ['vk', 'telegram', 'max', 'ok', 'whatsapp'];
                $result = [];

                foreach ($allowed as $network) {
                    $result[$network] = !empty($value[$network]);
                }

                return $result;
            },
            'default' => [
                'vk' => true,
                'telegram' => true,
                'max' => true,
                'ok' => true,
                'whatsapp' => true,
            ],
        ]
    );
}
add_action('admin_init', 'ios_social_share_register_settings');

function ios_social_share_add_settings_page() {
    add_options_page(
        'iPhone Social Share',
        'iPhone Social Share',
        'manage_options',
        'ios-social-share',
        'ios_social_share_settings_page'
    );
}
add_action('admin_menu', 'ios_social_share_add_settings_page');


function ios_social_share_render_stats() {
    global $wpdb;

    $counts_table = ios_social_share_get_counts_table();
    $posts_table = $wpdb->posts;
    $per_page = 50;
    $page = isset($_GET['ios_share_page']) ? max(1, absint($_GET['ios_share_page'])) : 1;
    $offset = ($page - 1) * $per_page;

    $total_posts = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT c.post_id)
         FROM {$counts_table} c
         INNER JOIN {$posts_table} p ON p.ID = c.post_id
         WHERE p.post_status = 'publish'"
    );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                c.post_id,
                p.post_title,
                SUM(CASE WHEN c.network = 'vk' THEN c.unique_count ELSE 0 END) AS vk,
                SUM(CASE WHEN c.network = 'telegram' THEN c.unique_count ELSE 0 END) AS telegram,
                SUM(CASE WHEN c.network = 'max' THEN c.unique_count ELSE 0 END) AS max_count,
                SUM(CASE WHEN c.network = 'ok' THEN c.unique_count ELSE 0 END) AS ok,
                SUM(CASE WHEN c.network = 'whatsapp' THEN c.unique_count ELSE 0 END) AS whatsapp,
                SUM(c.unique_count) AS total
             FROM {$counts_table} c
             INNER JOIN {$posts_table} p ON p.ID = c.post_id
             WHERE p.post_status = 'publish'
             GROUP BY c.post_id, p.post_title
             ORDER BY total DESC, c.post_id DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ),
        ARRAY_A
    );

    if (empty($rows)) {
        echo '<p>Пока нет кликов.</p>';
        return;
    }

    echo '<div class="ios-social-share-stats">';
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>Статья</th>';
    echo '<th>VK</th>';
    echo '<th>ТГ</th>';
    echo '<th>MAX</th>';
    echo '<th>OK</th>';
    echo '<th>WA</th>';
    echo '<th>Всего</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $title = $row['post_title'] !== '' ? $row['post_title'] : '(без названия)';
        $edit_url = get_edit_post_link((int) $row['post_id']);
        $display_title = wp_trim_words(wp_strip_all_tags($title), 12, '…');

        echo '<tr>';
        echo '<td>';
        if ($edit_url) {
            echo '<a href="' . esc_url($edit_url) . '">' . esc_html($display_title) . '</a>';
        } else {
            echo esc_html($display_title);
        }
        echo '</td>';
        echo '<td>' . (int) $row['vk'] . '</td>';
        echo '<td>' . (int) $row['telegram'] . '</td>';
        echo '<td>' . (int) $row['max_count'] . '</td>';
        echo '<td>' . (int) $row['ok'] . '</td>';
        echo '<td>' . (int) $row['whatsapp'] . '</td>';
        echo '<td><strong>' . (int) $row['total'] . '</strong></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    $total_pages = max(1, (int) ceil($total_posts / $per_page));

    if ($total_pages > 1) {
        $base_url = admin_url('options-general.php?page=ios-social-share');
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links([
            'base' => add_query_arg('ios_share_page', '%#%', $base_url),
            'format' => '',
            'current' => $page,
            'total' => $total_pages,
            'prev_text' => '‹',
            'next_text' => '›',
        ]);
        echo '</div></div>';
    }
}

function ios_social_share_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $networks = [
        'vk' => 'VK',
        'telegram' => 'Telegram',
        'max' => 'MAX',
        'ok' => 'Одноклассники',
        'whatsapp' => 'WhatsApp',
    ];

    $enabled = ios_social_share_get_enabled_networks();
    ?>
    <div class="wrap">
        <h1>iPhone Social Share</h1>

        <p>Выберите кнопки, которые будут отображаться на сайте.</p>

        <form method="post" action="options.php">
            <?php settings_fields('ios_social_share_settings'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <?php foreach ($networks as $key => $name): ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($name); ?></th>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr(IOS_SOCIAL_SHARE_NETWORKS_OPTION); ?>[<?php echo esc_attr($key); ?>]"
                                        value="1"
                                        <?php checked(!empty($enabled[$key])); ?>
                                    >
                                    Показывать кнопку
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr>

        <style>
            .ios-social-share-stats table {
                max-width: 900px;
            }
            .ios-social-share-stats th,
            .ios-social-share-stats td {
                padding: 6px 10px;
                white-space: nowrap;
            }
            .ios-social-share-stats th:first-child,
            .ios-social-share-stats td:first-child {
                width: 100%;
                white-space: normal;
            }
            .ios-social-share-stats + .tablenav {
                max-width: 900px;
            }
        </style>

        <h2>Статистика кликов</h2>
        <p>Учитываются уникальные клики по каждой статье и социальной сети.</p>

        <?php ios_social_share_render_stats(); ?>
    </div>
    <?php
}


add_action('plugins_loaded', function () {
    if (get_option(IOS_SOCIAL_SHARE_OPTION) !== IOS_SOCIAL_SHARE_VERSION) {
        ios_social_share_install();
    }
});

function ios_social_share_get_visitor_hash() {
    if (is_user_logged_in()) {
        $visitor_id = 'user:' . get_current_user_id();
    } else {
        $visitor_id = isset($_COOKIE[IOS_SOCIAL_SHARE_COOKIE])
            ? sanitize_text_field(wp_unslash($_COOKIE[IOS_SOCIAL_SHARE_COOKIE]))
            : '';

        if (!preg_match('/^[a-f0-9-]{36}$/i', $visitor_id)) {
            return '';
        }

        $visitor_id = 'visitor:' . strtolower($visitor_id);
    }

    return hash_hmac('sha256', $visitor_id, wp_salt('auth'));
}

function ios_social_share_get_ip_hash() {
    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
        : '';

    if ($ip === '') {
        return '';
    }

    return hash_hmac('sha256', $ip, wp_salt('auth'));
}

function ios_social_share_cookie_js() {
    return <<<'JS'
(function () {
    const cookieName = 'ios_social_visitor';

    function getCookie(name) {
        const prefix = name + '=';
        const cookies = document.cookie ? document.cookie.split('; ') : [];

        for (let i = 0; i < cookies.length; i++) {
            if (cookies[i].indexOf(prefix) === 0) {
                return decodeURIComponent(cookies[i].substring(prefix.length));
            }
        }

        return '';
    }

    function createId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    if (!getCookie(cookieName)) {
        document.cookie = cookieName + '=' + encodeURIComponent(createId()) + '; path=/; max-age=157680000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }
})();
JS;
}

function ios_social_share_html() {
    $post_id = get_the_ID();

    if (!$post_id) {
        return '';
    }

    global $wpdb;
    $counts_table = ios_social_share_get_counts_table();

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT network, unique_count FROM {$counts_table} WHERE post_id = %d",
            $post_id
        ),
        ARRAY_A
    );

    $counts = [];

    foreach ($rows as $row) {
        $counts[$row['network']] = (int) $row['unique_count'];
    }

    $icon_base = plugin_dir_url(__FILE__) . 'icons/';

    $networks = [
        'vk' => [
            'name' => 'VK',
            'background' => '#0077FF',
            'icon' => $icon_base . 'vk.svg',
            'icon_size' => '28px',
            'filter' => 'none',
        ],
        'telegram' => [
            'name' => 'Telegram',
            'background' => '#229ED9',
            'icon' => $icon_base . 'telegram.svg',
            'icon_size' => '26px',
            'filter' => 'none',
        ],
        'max' => [
            'name' => 'MAX',
            'background' => 'linear-gradient(135deg, #00BFFF 0%, #471AFF 48%, #9500FF 100%)',
            'icon' => $icon_base . 'max-logo.svg',
            'icon_size' => '24px',
            'filter' => 'none',
        ],
        'ok' => [
            'name' => 'Одноклассники',
            'background' => '#EE8208',
            'icon' => $icon_base . 'odnoklassniki.svg',
            'icon_size' => '24px',
            'filter' => 'brightness(0) invert(1)',
        ],
        'whatsapp' => [
            'name' => 'WhatsApp',
            'background' => '#25D366',
            'icon' => $icon_base . 'whatsapp.svg',
            'icon_size' => '28px',
            'filter' => 'none',
        ],
    ];

    $enabled_networks = ios_social_share_get_enabled_networks();
    $networks = array_intersect_key($networks, array_filter($enabled_networks));

    if (empty($networks)) {
        return '';
    }

    $share_urls = [
        'vk' => "'https://vk.com/share.php?url=' + encodeURIComponent(pageUrl) + '&title=' + encodeURIComponent(pageTitle)",
        'telegram' => "'https://t.me/share/url?url=' + encodeURIComponent(pageUrl) + '&text=' + encodeURIComponent(pageTitle)",
        'max' => "'https://max.ru/:share?text=' + encodeURIComponent(pageTitle + ' ' + pageUrl)",
        'ok' => "'https://connect.ok.ru/offer?url=' + encodeURIComponent(pageUrl) + '&title=' + encodeURIComponent(pageTitle)",
        'whatsapp' => "'https://wa.me/?text=' + encodeURIComponent(pageTitle + ' ' + pageUrl)",
    ];

    ob_start();
    ?>

    <div
        class="ios-social-share"
        data-post-id="<?php echo esc_attr($post_id); ?>"
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('ios_social_share')); ?>"
    >

        <?php foreach ($networks as $key => $network): ?>

            <?php $count = isset($counts[$key]) ? (int) $counts[$key] : 0; ?>

            <a
                href="#"
                class="ios-social-share__item"
                data-network="<?php echo esc_attr($key); ?>"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Поделиться в <?php echo esc_attr($network['name']); ?>"
            >

                <span
                    class="ios-social-share__badge"
                    <?php echo $count === 0 ? 'hidden' : ''; ?>
                ><?php echo $count > 99 ? '99+' : esc_html($count); ?></span>

                <span
                    class="ios-social-share__icon"
                    style="background: <?php echo esc_attr($network['background']); ?>;"
                >
                    <img
                        src="<?php echo esc_url($network['icon']); ?>?v=<?php echo esc_attr(IOS_SOCIAL_SHARE_VERSION); ?>"
                        alt=""
                        width="50"
                        height="50"
                        loading="lazy"
                        decoding="async"
                    >
                </span>

            </a>

        <?php endforeach; ?>

    </div>

    <style>
        .ios-social-share {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .ios-social-share__item {
            position: relative;
            display: block;
            width: 35px;
            height: 35px;
            text-decoration: none !important;
            outline: none;
        }

        .ios-social-share__icon {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.14);
            transition: transform 0.12s ease, filter 0.12s ease;
        }

        .ios-social-share__icon img {
            display: block;
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

<?php foreach ($networks as $key => $network): ?>
        .ios-social-share__item[data-network="<?php echo esc_attr($key); ?>"] .ios-social-share__icon img {
            width: <?php echo esc_attr($network['icon_size']); ?>;
            height: <?php echo esc_attr($network['icon_size']); ?>;
            filter: <?php echo esc_attr($network['filter']); ?>;
        }
<?php endforeach; ?>

        .ios-social-share__badge {
            position: absolute;
            z-index: 10;
            top: -7px;
            right: -7px;
            min-width: 15px;
            height: 15px;
            padding: 0 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            border-radius: 999px;
            background: #ff3b30;
            color: #fff;
            border: 0px solid #fff;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", Arial, sans-serif;
            font-size: 8px;
            font-weight: 700;
            line-height: 1;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .ios-social-share__item:hover .ios-social-share__icon {
            transform: scale(1.04);
            filter: brightness(1.04);
        }

        .ios-social-share__item:active .ios-social-share__icon {
            transform: scale(0.92);
        }

        @media (max-width: 600px) {
            .ios-social-share {
                gap: 10px;
            }
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        <?php echo ios_social_share_cookie_js(); ?>

        document.querySelectorAll('.ios-social-share').forEach(function (wrapper) {

            const postId = wrapper.dataset.postId;
            const ajaxUrl = wrapper.dataset.ajaxUrl;
            const nonce = wrapper.dataset.nonce;
            const pageUrl = window.location.href;
            const pageTitle = document.title;

            const shareUrls = {
<?php foreach ($networks as $key => $network): ?>
                <?php echo esc_js($key); ?>: <?php echo $share_urls[$key]; ?><?php echo $key === array_key_last($networks) ? '' : ','; ?>

<?php endforeach; ?>
            };

            wrapper.querySelectorAll('.ios-social-share__item').forEach(function (button) {

                const network = button.dataset.network;
                const badge = button.querySelector('.ios-social-share__badge');

                button.href = shareUrls[network];

                button.addEventListener('click', function () {

                    if (button.dataset.sending === '1' || button.dataset.counted === '1') {
                        return;
                    }

                    button.dataset.sending = '1';

                    const formData = new FormData();
                    formData.append('action', 'ios_social_share_click');
                    formData.append('nonce', nonce);
                    formData.append('post_id', postId);
                    formData.append('network', network);

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        keepalive: true,
                        credentials: 'same-origin'
                    })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {

                        button.dataset.sending = '0';

                        if (!data || !data.success || !data.data) {
                            return;
                        }

                        button.dataset.counted = data.data.unique ? '1' : '0';

                        const count = parseInt(data.data.count, 10) || 0;

                        if (count > 0) {
                            badge.hidden = false;
                            badge.textContent = count > 99 ? '99+' : count;
                        }
                    })
                    .catch(function () {
                        button.dataset.sending = '0';
                    });

                });
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}

function ios_social_share() {
    echo ios_social_share_html();
}

add_shortcode('ios_social_share', 'ios_social_share_html');

add_action('wp_ajax_ios_social_share_click', 'ios_social_share_click');
add_action('wp_ajax_nopriv_ios_social_share_click', 'ios_social_share_click');

function ios_social_share_click() {
    if (
        !isset($_POST['nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['nonce'])),
            'ios_social_share'
        )
    ) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $network = isset($_POST['network']) ? sanitize_key($_POST['network']) : '';

    $allowed = ['vk', 'telegram', 'max', 'ok', 'whatsapp'];

    if (!$post_id || !in_array($network, $allowed, true)) {
        wp_send_json_error(['message' => 'Invalid data'], 400);
    }

    $post = get_post($post_id);

    if (!$post || $post->post_status !== 'publish') {
        wp_send_json_error(['message' => 'Post not available'], 404);
    }

    $ip_hash = ios_social_share_get_ip_hash();

    if ($ip_hash !== '') {
        $rate_key = 'ios_share_rl_' . substr($ip_hash, 0, 32);
        $rate_data = get_transient($rate_key);

        if (!is_array($rate_data)) {
            $rate_data = [
                'count' => 0,
                'started' => time(),
            ];
        }

        $rate_data['count']++;

        if ($rate_data['count'] > 60) {
            wp_send_json_error(['message' => 'Too many requests'], 429);
        }

        set_transient($rate_key, $rate_data, 10 * MINUTE_IN_SECONDS);
    }

    $visitor_hash = ios_social_share_get_visitor_hash();

    if ($visitor_hash === '') {
        wp_send_json_error(['message' => 'Visitor identifier unavailable'], 400);
    }

    global $wpdb;

    $unique_table = ios_social_share_get_unique_table();
    $counts_table = ios_social_share_get_counts_table();

    $now = current_time('mysql', true);

    $inserted = $wpdb->query(
        $wpdb->prepare(
            "INSERT IGNORE INTO {$unique_table} (post_id, network, visitor_hash, created_at) VALUES (%d, %s, %s, %s)",
            $post_id,
            $network,
            $visitor_hash,
            $now
        )
    );

    if ($inserted === false) {
        wp_send_json_error(['message' => 'Database error'], 500);
    }

    if ($inserted === 1) {
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$counts_table} (post_id, network, unique_count) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE unique_count = unique_count + 1",
                $post_id,
                $network
            )
        );

        $unique = true;
    } else {
        $unique = false;
    }

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT unique_count FROM {$counts_table} WHERE post_id = %d AND network = %s",
            $post_id,
            $network
        )
    );

    wp_send_json_success([
        'unique' => $unique,
        'count' => $count,
    ]);
}
