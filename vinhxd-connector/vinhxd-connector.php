<?php
/**
 * Plugin Name: VinhXD Connector
 * Plugin URI: https://meg.vinhxd.com
 * Description: Kết nối web với Vĩnh XD, cập nhật giao diện, chức năng và xử lý lỗi.
 * Version: 1.4.0
 * Author: Vĩnh XD
 * Author URI: https://vinhxd.com
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

define('VXD_MEG_URL', 'https://meg.vinhxd.com/api/update.php');
define('VXD_CONN_VER', '1.4.0');
define('VXD_CONN_FILE', __FILE__);
define('VXD_CONN_URL', plugin_dir_url(__FILE__));

if (file_exists(__DIR__ . '/includes/guard-core.php')) require_once __DIR__ . '/includes/guard-core.php';
if (file_exists(__DIR__ . '/includes/guard-admin.php')) require_once __DIR__ . '/includes/guard-admin.php';

final class VXD_Connector {
    private static $inst; private $key; private $domain; private $products = null; private $features = null;
    public static function init() { return self::$inst ??= new self(); }
    private function __construct() {
        $this->key = (string)get_option('vxd_meg_key', '');
        $this->domain = preg_replace('~^www\.~', '', strtolower((string)parse_url(home_url(), PHP_URL_HOST)));
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_menu', [$this, 'maybe_hide_menu'], 999);
        add_action('admin_init', [$this, 'settings']);
        add_action('init', [$this, 'remote_endpoint']);
        add_action('vxd_meg_daily_ping', [$this, 'ping']);
        if (!wp_next_scheduled('vxd_meg_daily_ping')) wp_schedule_event(time() + 300, 'daily', 'vxd_meg_daily_ping');
        if ($this->key) {
            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_plugins']);
            add_filter('pre_set_site_transient_update_themes', [$this, 'check_themes']);
            add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
            add_filter('auto_update_plugin', [$this, 'auto_update'], 10, 2);
            add_filter('auto_update_theme', [$this, 'auto_update'], 10, 2);
            add_filter('http_request_host_is_external', [$this, 'trust_vxd_host'], 10, 2);
        }
        add_action('admin_init', [$this, 'maybe_sync_features'], 5);
        add_action('admin_init', [$this, 'maybe_check_health'], 6);
        add_action('admin_notices', [$this, 'health_notice']);
        add_action('load-index.php', [$this, 'oto_takeover']);
        add_action('wp_dashboard_setup', [$this, 'oto_dashboard'], 99999);
        add_action('admin_head', [$this, 'oto_admin_css']);
        add_action('all_admin_notices', [$this, 'oto_render_full'], 1);
        // Không gửi mail WP "plugin đã cập nhật tự động" (spam khách)
        add_filter('auto_plugin_update_send_email', '__return_false');
        add_filter('auto_theme_update_send_email', '__return_false');
        add_filter('automatic_updates_send_debug_email', '__return_false');
    }

    private function fetch_list() {
        $r = wp_remote_get(add_query_arg(['a' => 'list', 'domain' => $this->domain, 'key' => $this->key], VXD_MEG_URL), ['timeout' => 20]);
        $j = !is_wp_error($r) ? json_decode(wp_remote_retrieve_body($r), true) : null;
        $ok = !empty($j['ok']);
        update_option('vxd_meg_last_contact', $ok ? current_time('mysql') : get_option('vxd_meg_last_contact', ''));
        update_option('vxd_meg_last_error', !is_wp_error($r) ? ($j['error'] ?? '') : $r->get_error_message());
        if ($ok) {
            set_transient('vxd_meg_products', $j['products'] ?? [], 6 * HOUR_IN_SECONDS);
            $feat = $j['features'] ?? [];
            set_transient('vxd_meg_features', is_array($feat) ? $feat : [], 6 * HOUR_IN_SECONDS);
            if (array_key_exists('dash_oto', $feat)) update_option('vxd_meg_dash_oto', (int)$feat['dash_oto']);
            if (!empty($feat['guard']) && is_array($feat['guard']) && function_exists('vxd_guard_apply_config')) {
                vxd_guard_apply_config($feat['guard']);
            }
        }
        return $ok ? $j : null;
    }
    private function products() {
        if ($this->products !== null) return $this->products;
        $c = get_transient('vxd_meg_products');
        if ($c === false) {
            $j = $this->key ? $this->fetch_list() : null;
            $c = $j ? ($j['products'] ?? []) : [];
            if (!$j) set_transient('vxd_meg_products', [], 10 * MINUTE_IN_SECONDS);
        }
        return $this->products = is_array($c) ? $c : [];
    }
    private function features() {
        if ($this->features !== null) return $this->features;
        $c = get_transient('vxd_meg_features');
        if ($c === false && $this->key) {
            $this->fetch_list();
            $c = get_transient('vxd_meg_features');
        }
        return $this->features = is_array($c) ? $c : [];
    }
    private function installed_plugins() {
        if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $out = [];
        foreach (get_plugins() as $file => $d) $out[dirname($file) === '.' ? basename($file, '.php') : dirname($file)] = ['file' => $file, 'version' => $d['Version']];
        return $out;
    }

    public function check_plugins($t) {
        if (empty($t->checked)) return $t;
        $inst = $this->installed_plugins();
        foreach ($this->products() as $p) {
            if (($p['type'] ?? 'plugin') !== 'plugin' || empty($inst[$p['slug']])) continue;
            $file = $inst[$p['slug']]['file']; $cur = $inst[$p['slug']]['version'];
            $item = (object)['id' => $p['slug'], 'slug' => $p['slug'], 'plugin' => $file, 'new_version' => $p['version'], 'url' => $p['homepage'] ?? '', 'package' => $p['package'], 'requires' => $p['requires'] ?? '', 'requires_php' => $p['requires_php'] ?? '', 'tested' => $p['tested'] ?? '', 'icons' => [], 'banners' => []];
            if (version_compare($p['version'], $cur, '>')) { $t->response[$file] = $item; unset($t->no_update[$file]); } else { $t->no_update[$file] = $item; unset($t->response[$file]); }
        }
        return $t;
    }
    public function check_themes($t) {
        if (empty($t->checked)) return $t;
        foreach ($this->products() as $p) {
            if (($p['type'] ?? '') !== 'theme') continue; $th = wp_get_theme($p['slug']); if (!$th->exists()) continue;
            $item = ['theme' => $p['slug'], 'new_version' => $p['version'], 'url' => $p['homepage'] ?? '', 'package' => $p['package'], 'requires' => $p['requires'] ?? '', 'requires_php' => $p['requires_php'] ?? ''];
            if (version_compare($p['version'], $th->get('Version'), '>')) $t->response[$p['slug']] = $item; else $t->no_update[$p['slug']] = $item;
        }
        return $t;
    }
    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug)) return $res;
        foreach ($this->products() as $p) if ($p['slug'] === $args->slug) {
            return (object)['name' => $p['name'], 'slug' => $p['slug'], 'version' => $p['version'], 'author' => '<a href="https://vinhxd.com">Vĩnh XD</a>', 'homepage' => $p['homepage'] ?? '', 'requires' => $p['requires'] ?? '', 'requires_php' => $p['requires_php'] ?? '', 'tested' => $p['tested'] ?? '', 'last_updated' => $p['last_updated'] ?? '', 'download_link' => $p['package'], 'sections' => $p['sections'] ?? [], 'banners' => []];
        }
        return $res;
    }
    public function auto_update($update, $item) {
        if (!get_option('vxd_meg_auto', 1)) return $update;
        $slug = is_object($item) ? ($item->slug ?? ($item->theme ?? '')) : ($item['theme'] ?? '');
        foreach ($this->products() as $p) if ($p['slug'] === $slug) return true;
        return $update;
    }

    public function ping() {
        if (!$this->key) return;
        global $wpdb; $inst = $this->installed_plugins(); $plugins = [];
        foreach ($this->products() as $p) {
            if (($p['type'] ?? 'plugin') === 'theme') { $th = wp_get_theme($p['slug']); if ($th->exists()) $plugins[$p['slug']] = $th->get('Version'); }
            elseif (isset($inst[$p['slug']])) $plugins[$p['slug']] = $inst[$p['slug']]['version'];
        }
        $db = (float)$wpdb->get_var($wpdb->prepare("SELECT ROUND(SUM(data_length+index_length)/1048576,1) FROM information_schema.tables WHERE table_schema=%s", DB_NAME));
        $tt = function_exists('get_option') ? (array)get_option('thong-tin', []) : [];
        $phone = $tt['hotline'] ?? ($tt['sdt'] ?? ($tt['phone'] ?? '')); $email = $tt['email'] ?? '';
        $r = wp_remote_post(VXD_MEG_URL, ['timeout' => 25, 'body' => [
            'a' => 'ping', 'domain' => $this->domain, 'key' => $this->key,
            'wp' => get_bloginfo('version'), 'php' => PHP_VERSION,
            'theme' => wp_get_theme()->get('Name') . ' ' . wp_get_theme()->get('Version'),
            'plugins' => wp_json_encode($plugins), 'site_url' => home_url(),
            'admin_email' => get_option('admin_email'),
            'phone' => is_string($phone) ? $phone : '', 'email' => is_string($email) ? $email : '',
            'active_plugins' => count((array)get_option('active_plugins', [])),
            'connector' => VXD_CONN_VER, 'db_mb' => $db,
            'dash_oto' => $this->oto_enabled() ? 1 : 0,
            'content_health' => wp_json_encode($this->content_health(true)),
            'guard' => function_exists('vxd_guard_status_summary') ? wp_json_encode(vxd_guard_status_summary()) : '{}',
        ]]);
        $j = !is_wp_error($r) ? json_decode(wp_remote_retrieve_body($r), true) : null;
        if (isset($j['features']) && is_array($j['features'])) {
            set_transient('vxd_meg_features', $j['features'], 6 * HOUR_IN_SECONDS);
            if (array_key_exists('dash_oto', $j['features'])) update_option('vxd_meg_dash_oto', (int)$j['features']['dash_oto']);
            if (!empty($j['features']['guard']) && is_array($j['features']['guard']) && function_exists('vxd_guard_apply_config')) {
                vxd_guard_apply_config($j['features']['guard']);
            }
        }
        update_option('vxd_meg_last_ping', current_time('mysql'));
    }

    public function remote_endpoint() {
        if (empty($_GET['vxd_connector'])) return;
        nocache_headers(); header('Content-Type: application/json; charset=utf-8');
        $out = function ($d, $code = 200) { status_header($code); echo wp_json_encode($d); exit; };
        if (!$this->key) $out(['ok' => false, 'error' => 'Chưa nhập license key'], 403);
        $slug = sanitize_key($_GET['slug'] ?? ''); $ts = (int)($_GET['ts'] ?? 0); $sig = (string)($_GET['sig'] ?? '');
        if (abs(time() - $ts) > 300 || !hash_equals(hash_hmac('sha256', "$slug|$ts", $this->key), $sig)) $out(['ok' => false, 'error' => 'Chữ ký sai'], 403);
        if ($_GET['vxd_connector'] === 'status') {
            $out([
                'ok' => true, 'connector' => VXD_CONN_VER, 'wp' => get_bloginfo('version'),
                'dash_oto' => $this->oto_enabled() ? 1 : 0,
                'guard' => function_exists('vxd_guard_status_summary') ? vxd_guard_status_summary() : null,
            ]);
        }
        if ($_GET['vxd_connector'] === 'guard') {
            if (!function_exists('vxd_guard_apply_config')) $out(['ok' => false, 'error' => 'Module guard chưa nạp'], 500);
            $raw_body = file_get_contents('php://input');
            $json_cfg = $raw_body ? json_decode($raw_body, true) : null;
            $cfg = is_array($json_cfg) ? $json_cfg : (is_array($_POST) && !empty($_POST) ? wp_unslash($_POST) : []);
            $res = !empty($cfg) ? vxd_guard_apply_config($cfg) : null;
            $out(['ok' => true, 'applied' => $res, 'guard' => vxd_guard_status_summary()]);
        }
        if ($_GET['vxd_connector'] !== 'upgrade') $out(['ok' => false, 'error' => 'bad action'], 400);
        set_time_limit(300); delete_transient('vxd_meg_products'); delete_transient('vxd_meg_features'); $this->products = null; $this->features = null;
        $GLOBALS['vxd_connector_internal_upgrade'] = true;
        add_filter('auto_plugin_update_send_email', '__return_false');
        add_filter('auto_theme_update_send_email', '__return_false');
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/misc.php';
        if (!class_exists('Automatic_Upgrader_Skin')) require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
        $target = null; foreach ($this->products() as $p) if ($p['slug'] === $slug) $target = $p;
        if (!$target) $out(['ok' => false, 'error' => 'Sản phẩm không có license trên site này'], 403);
        $skin = new Automatic_Upgrader_Skin();
        if (($target['type'] ?? 'plugin') === 'theme') {
            delete_site_transient('update_themes');
            wp_update_themes(); $up = new Theme_Upgrader($skin); $r = $up->upgrade($slug); $ver = wp_get_theme($slug)->get('Version');
        } else {
            $inst = $this->installed_plugins(); if (empty($inst[$slug])) $out(['ok' => false, 'error' => 'Plugin chưa cài trên site'], 404);
            $file = $inst[$slug]['file']; $was = is_plugin_active($file); $cur = $inst[$slug]['version'];
            delete_site_transient('update_plugins');
            // Ép transient có bản mới từ MEG (tránh WP báo "latest" oan)
            $filt = function ($t) use ($target, $file) {
                if (!is_object($t)) $t = new stdClass();
                if (empty($t->response)) $t->response = [];
                $t->response[$file] = (object)[
                    'id' => $target['slug'], 'slug' => $target['slug'], 'plugin' => $file,
                    'new_version' => $target['version'], 'package' => $target['package'],
                    'url' => $target['homepage'] ?? '',
                ];
                return $t;
            };
            add_filter('pre_set_site_transient_update_plugins', $filt, 99);
            set_site_transient('update_plugins', $filt((object)['checked' => [$file => $cur], 'response' => [], 'no_update' => []]));
            $before = $this->dir_snapshot();
            $up = new Plugin_Upgrader($skin);
            $r = $up->upgrade($file);
            // Nếu vẫn fail mà package có URL → cài đè, CHỈ trong thư mục của chính plugin này
            if ($r === false && !empty($target['package']) && version_compare($target['version'], $cur, '>')) {
                $dest = $this->safe_plugin_dest($file, $slug);
                if (is_wp_error($dest)) { $r = $dest; }
                else {
                    $r = $up->run([
                        'package' => $target['package'],
                        'destination' => $dest,
                        'clear_destination' => true,
                        'abort_if_destination_exists' => false,
                        'clear_working' => true,
                        'hook_extra' => ['plugin' => $file, 'type' => 'plugin', 'action' => 'update'],
                    ]);
                }
            }
            $lost = $this->dir_lost($before, $slug);
            if ($lost) {
                remove_filter('pre_set_site_transient_update_plugins', $filt, 99);
                $this->content_health(true);
                $out(['ok' => false, 'error' => 'DỪNG: thư mục khác bị mất sau khi cập nhật: ' . implode(', ', $lost), 'lost' => $lost], 500);
            }
            remove_filter('pre_set_site_transient_update_plugins', $filt, 99);
            if ($was && !is_plugin_active($file)) activate_plugin($file);
            $inst = $this->installed_plugins(); $ver = $inst[$slug]['version'] ?? '';
        }
        if (is_wp_error($r)) $out(['ok' => false, 'error' => $r->get_error_message()]);
        // already-latest / false nhưng version đã đúng target → coi như ok
        $want = $target['version'] ?? '';
        if ($r === false && $want && version_compare((string)$ver, (string)$want, '>=')) {
            $out(['ok' => true, 'version' => $ver, 'result' => 'already-latest']);
        }
        if ($r === false) $out(['ok' => false, 'error' => 'Upgrader thất bại: ' . implode(' | ', (array)$skin->get_upgrade_messages())]);
        $out(['ok' => true, 'version' => $ver, 'result' => $r === null ? 'already-latest' : 'upgraded']);
    }


    /** Chỉ coi host của Vĩnh XD là "external hợp lệ" (trước đây mở cho mọi host) */
    public function trust_vxd_host($external, $host) {
        $host = strtolower((string)$host);
        if ($host === 'vinhxd.com' || substr($host, -11) === '.vinhxd.com') return true;
        return $external;
    }
    /** Đích cài đè an toàn: wp-content/plugins/<slug>, không bao giờ là cả thư mục plugins */
    private function safe_plugin_dest($file, $slug) {
        $dir = dirname($file);
        if ($dir === '.' || $dir === '' || $dir !== $slug || strpbrk($dir, '/\\') !== false) return new WP_Error('vxd_dest', 'Không cài đè: plugin không nằm trong thư mục riêng (' . $file . ')');
        $root = trailingslashit(wp_normalize_path(WP_PLUGIN_DIR));
        $dest = wp_normalize_path($root . $dir);
        if (strpos($dest, $root) !== 0 || untrailingslashit($dest) === untrailingslashit($root)) return new WP_Error('vxd_dest', 'Đích cài đè không hợp lệ');
        return $dest;
    }
    /** Danh sách thư mục plugin + theme trước khi cập nhật */
    private function dir_snapshot() {
        $list = function ($root) { $o = []; foreach ((array)@scandir($root) as $e) if ($e !== '.' && $e !== '..' && is_dir($root . '/' . $e)) $o[] = $e; return $o; };
        return ['plugins' => $list(WP_PLUGIN_DIR), 'themes' => $list(get_theme_root())];
    }
    /** Thư mục nào (ngoài sản phẩm đang cập nhật) đã biến mất */
    private function dir_lost($before, $slug) {
        clearstatcache();
        $lost = [];
        foreach ($before['plugins'] as $d) if ($d !== $slug && !is_dir(WP_PLUGIN_DIR . '/' . $d)) $lost[] = 'plugins/' . $d;
        foreach ($before['themes'] as $d) if ($d !== $slug && !is_dir(get_theme_root() . '/' . $d)) $lost[] = 'themes/' . $d;
        return $lost;
    }
    /** Kiểm tra web còn đủ theme/plugin không (phát hiện sự cố mất thư mục) */
    public function content_health($refresh = false) {
        $c = get_option('vxd_content_health', []);
        if (!$refresh && is_array($c) && !empty($c['time'])) return $c;
        if (!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $issues = [];
        $th = wp_get_theme();
        if (!$th->exists()) $issues[] = 'Theme đang dùng không còn trên đĩa: ' . get_stylesheet();
        elseif ($th->parent() === false && get_template() !== get_stylesheet() && !is_dir(get_theme_root() . '/' . get_template())) $issues[] = 'Theme cha bị thiếu: ' . get_template();
        $themes = (array)@scandir(get_theme_root());
        if (count(array_diff($themes, ['.', '..', 'index.php'])) === 0) $issues[] = 'Thư mục themes trống';
        $missing = [];
        foreach ((array)get_option('active_plugins', []) as $pf) if (!file_exists(WP_PLUGIN_DIR . '/' . $pf)) $missing[] = dirname($pf) === '.' ? $pf : dirname($pf);
        if ($missing) $issues[] = 'Plugin đang bật nhưng mất file: ' . implode(', ', array_slice($missing, 0, 12)) . (count($missing) > 12 ? '…' : '');
        $c = [
            'ok' => !$issues,
            'issues' => $issues,
            'plugins' => count(get_plugins()),
            'exposed_backups' => function_exists('vxd_guard_scan_exposed_backups') ? vxd_guard_scan_exposed_backups() : [],
            'time' => current_time('mysql'),
        ];
        update_option('vxd_content_health', $c, false);
        return $c;
    }
    public function maybe_check_health() {
        if (!current_user_can('manage_options') || get_transient('vxd_health_chk')) return;
        set_transient('vxd_health_chk', 1, 30 * MINUTE_IN_SECONDS);
        $this->content_health(true);
    }
    public function health_notice() {
        if (!current_user_can('manage_options')) return;
        $c = get_option('vxd_content_health', []);
        if (empty($c['issues'])) return;
        echo '<div class="notice notice-error vxd-oto-keep"><p><strong>Vĩnh XD Connector phát hiện web thiếu file:</strong></p><ul style="list-style:disc;margin-left:20px">';
        foreach ($c['issues'] as $i) echo '<li>' . esc_html($i) . '</li>';
        echo '</ul><p>Liên hệ Vĩnh XD (Zalo 0965 401 120) để khôi phục. Không cài lại plugin hàng loạt khi chưa kiểm tra.</p></div>';
    }

    public function menu() { add_options_page('Vĩnh XD Connector', 'Vĩnh XD Connector', 'manage_options', 'vxd-connector', [$this, 'page']); }
    public function maybe_hide_menu() {
        if (function_exists('xd_guard_hide_connector') && xd_guard_hide_connector()) {
            remove_submenu_page('options-general.php', 'vxd-connector');
        }
    }
    public function settings() {
        register_setting('vxd_meg', 'vxd_meg_key', ['sanitize_callback' => function ($v) {
            $old = trim((string)get_option('vxd_meg_key', ''));
            $unlocked = !function_exists('vxd_guard_is_unlocked') || vxd_guard_is_unlocked();
            if ($old !== '' && !$unlocked) return $old;
            delete_transient('vxd_meg_products'); delete_transient('vxd_meg_features');
            return trim((string)$v);
        }]);
        register_setting('vxd_meg', 'vxd_meg_auto', ['sanitize_callback' => 'absint']);
        register_setting('vxd_meg', 'vxd_enable_oto_dashboard', ['sanitize_callback' => 'absint']);
    }
    public function page() {
        $tab = sanitize_key($_GET['tab'] ?? 'meg');
        if (!in_array($tab, ['meg', 'guard', 'log'], true)) $tab = 'meg';
        $base = admin_url('options-general.php?page=vxd-connector');
        $log_cnt = count((array)get_option('xd_guard_log', []));
        $tabs = [
            'meg'   => 'Kết nối MEG',
            'guard' => '🛡️ Chống Clone & Bảo Mật',
            'log'   => '📋 Nhật ký chặn' . ($log_cnt ? ' (' . $log_cnt . ')' : ''),
        ];
        echo '<div class="wrap"><h1>Vĩnh XD Connector <small style="font-size:13px;font-weight:400;color:#64748b">v' . esc_html(VXD_CONN_VER) . '</small></h1>';
        echo '<nav class="nav-tab-wrapper" style="margin-bottom:18px;">';
        foreach ($tabs as $k => $label) {
            echo '<a href="' . esc_url(add_query_arg('tab', $k, $base)) . '" class="nav-tab' . ($tab === $k ? ' nav-tab-active' : '') . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ($tab === 'guard' && function_exists('vxd_guard_render_security_tab')) {
            vxd_guard_render_security_tab();
            echo '</div>';
            return;
        }
        if ($tab === 'log' && function_exists('vxd_guard_render_log_tab')) {
            vxd_guard_render_log_tab();
            echo '</div>';
            return;
        }

        if (isset($_GET['vxd_ping']) && check_admin_referer('vxd_ping')) {
            delete_transient('vxd_meg_products'); delete_transient('vxd_meg_features');
            $this->products = null; $this->features = null; $this->ping();
            echo '<div class="notice notice-success"><p>Đã gửi báo cáo về MEG.</p></div>';
        }
        $prods = $this->key ? $this->products() : []; $err = get_option('vxd_meg_last_error', '');
        $meg_oto = (int)get_option('vxd_meg_dash_oto', 0);
        $local_oto = (int)get_option('vxd_enable_oto_dashboard', 0);
        $unlocked = !function_exists('vxd_guard_is_unlocked') || vxd_guard_is_unlocked();
        $masked_key = $this->key !== '' ? (substr($this->key, 0, 4) . str_repeat('•', max(8, strlen($this->key) - 8)) . substr($this->key, -4)) : '';
        ?>
        <form method="post" action="options.php"><?php settings_fields('vxd_meg'); ?>
        <table class="form-table">
        <tr><th>License key</th><td>
            <?php if ($this->key !== '' && !$unlocked): ?>
                <input type="text" value="<?php echo esc_attr($masked_key); ?>" class="regular-text code" disabled>
                <p class="description">License key đang được che để bảo vệ. Mở khóa ở tab <a href="<?php echo esc_url(add_query_arg('tab', 'guard', $base)); ?>">Chống Clone &amp; Bảo Mật</a> để đổi key.</p>
            <?php else: ?>
                <input type="text" name="vxd_meg_key" value="<?php echo esc_attr($this->key); ?>" class="regular-text code" placeholder="dán key từ meg.vinhxd.com">
                <p class="description">Domain: <code><?php echo esc_html($this->domain); ?></code> — key phải được cấp cho đúng domain này.</p>
            <?php endif; ?>
        </td></tr>
        <tr><th>Tự động cập nhật</th><td><label><input type="checkbox" name="vxd_meg_auto" value="1" <?php checked(get_option('vxd_meg_auto', 1), 1); ?>> Tự cập nhật plugin/theme của VinhXD khi có bản mới</label></td></tr>
        <tr><th>Dashboard Oto</th><td>
            <label><input type="checkbox" name="vxd_enable_oto_dashboard" value="1" <?php checked($local_oto, 1); ?>> Bật dashboard Oto trên site này (ghi đè widget mặc định)</label>
            <p class="description">
                Chỉ hiện trên web xe (domain có tên hãng). MEG bật từ xa: <strong><?php echo $meg_oto ? 'Đang bật' : 'Tắt'; ?></strong>.
                Hiện tại site: <strong><?php echo $this->oto_enabled() ? 'Đang dùng Dashboard Oto' : 'Dashboard WP mặc định'; ?></strong>
                <?php if (!$this->is_oto_domain()): ?> · Domain này không khớp web xe → tick local không có hiệu lực (cần MEG bật).<?php endif; ?>
            </p>
        </td></tr>
        </table>
        <?php submit_button(); ?></form>
        <?php if ($this->key): ?><h2>Trạng thái</h2>
        <?php if ($err): ?><div class="notice notice-error inline"><p>MEG trả lời: <?php echo esc_html($err); ?></p></div><?php endif; ?>
        <p>Liên lạc MEG lần cuối: <?php echo esc_html(get_option('vxd_meg_last_contact', '—')); ?> · Ping cuối: <?php echo esc_html(get_option('vxd_meg_last_ping', '—')); ?> · <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=vxd-connector&vxd_ping=1'), 'vxd_ping')); ?>" class="button">Gửi báo cáo ngay</a></p>
        <table class="widefat striped" style="max-width:700px"><thead><tr><th>Sản phẩm</th><th>Trên MEG</th><th>Trên site</th></tr></thead><tbody>
        <?php $inst = $this->installed_plugins(); foreach ($prods as $p): $cur = ($p['type'] ?? 'plugin') === 'theme' ? (wp_get_theme($p['slug'])->exists() ? wp_get_theme($p['slug'])->get('Version') : '—') : ($inst[$p['slug']]['version'] ?? '— chưa cài'); ?>
        <tr><td><?php echo esc_html($p['name']); ?> <code><?php echo esc_html($p['slug']); ?></code></td><td><?php echo esc_html($p['version']); ?></td><td><?php echo esc_html($cur); ?><?php if ($cur !== '—' && $cur !== '— chưa cài' && version_compare($p['version'], $cur, '>')): ?> <a href="<?php echo esc_url(admin_url(($p['type'] ?? 'plugin') === 'theme' ? 'themes.php' : 'plugins.php')); ?>">→ có bản mới</a><?php endif; ?></td></tr>
        <?php endforeach; if (!$prods): ?><tr><td colspan="3">Chưa nhận được danh sách sản phẩm (key sai hoặc domain chưa whitelist).</td></tr><?php endif; ?></tbody></table>
        <?php endif; ?></div><?php
    }

    /** Đồng bộ features (dash_oto) từ MEG khi vào admin — tối đa 1 lần/giờ */
    public function maybe_sync_features() {
        if (!$this->key || !is_admin()) return;
        if (get_transient('vxd_meg_feat_sync')) return;
        set_transient('vxd_meg_feat_sync', 1, HOUR_IN_SECONDS);
        delete_transient('vxd_meg_products');
        delete_transient('vxd_meg_features');
        $this->products = null; $this->features = null;
        $this->fetch_list();
    }
    public function is_oto_domain(): bool {
        $host = $this->domain;
        $kw = ['toyota','ford','vinfast','mercedes','merc','hyundai','kia','bmw','lexus','mazda','isuzu','honda','nissan','peugeot','volvo','mitsubishi','byd','subaru','skoda','omoda','thaco','suzuki','hino','geely','lynk','jeep','xetai'];
        foreach ($kw as $k) if (str_contains($host, $k)) return true;
        return false;
    }
    public function oto_enabled(): bool {
        // 1) MEG bật/tắt từ xa (sau list/ping)
        $meg = get_option('vxd_meg_dash_oto', null);
        if ($meg !== null && $meg !== '') {
            if ((int)$meg === 1) return true;
            if ((int)$meg === 0) {
                // MEG tắt — vẫn cho tick local trên web xe
                return (int)get_option('vxd_enable_oto_dashboard', 0) === 1 && $this->is_oto_domain();
            }
        }
        $feat = $this->features();
        if (is_array($feat) && array_key_exists('dash_oto', $feat)) {
            if (!empty($feat['dash_oto'])) return true;
            return (int)get_option('vxd_enable_oto_dashboard', 0) === 1 && $this->is_oto_domain();
        }
        // 2) Tick local
        if ((int)get_option('vxd_enable_oto_dashboard', 0) === 1) return true;
        // 3) Domain xe: bật mặc định (không chờ MEG ping — tránh vào admin mà trống)
        if ($this->is_oto_domain()) return true;
        return false;
    }
    public function oto_takeover() {
        if (!$this->oto_enabled()) return;
        // 1 cột, bỏ welcome
        add_filter('screen_layout_columns', function ($c) { $c['dashboard'] = 1; return $c; });
        add_filter('get_user_option_screen_layout_dashboard', fn () => 1);
        remove_action('welcome_panel', 'wp_welcome_panel');
        add_filter('show_admin_bar', '__return_true'); // giữ admin bar
    }
    public function oto_dashboard() {
        if (!$this->oto_enabled()) return;
        global $wp_meta_boxes;
        // Xóa HẾT widget (core + theme + plugin) — kể cả đăng ký muộn
        $wp_meta_boxes['dashboard'] = [
            'normal' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
            'side'   => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
            'column3'=> ['core' => [], 'high' => [], 'default' => [], 'low' => []],
            'column4'=> ['core' => [], 'high' => [], 'default' => [], 'low' => []],
        ];
        // Không add metabox WP nữa — render full page ở oto_render_full
    }
    /** Font + CSS cho dashboard (style pastel kiểu Geely, màu Vĩnh XD) */
    public function oto_admin_css() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$this->oto_enabled() || !$screen || $screen->id !== 'dashboard') return;
        echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@600;700;800&display=swap">';
        ?>
<style id="vxd-oto-dash">
#wpbody-content > .wrap > h1, #wpbody-content > .wrap > .wp-header-end, #dashboard-widgets-wrap,
#screen-meta, #screen-meta-links, .welcome-panel, .metabox-prefs, .notice:not(.vxd-oto-keep) { display:none !important; }
#wpbody-content > .wrap { margin:0; max-width:none; padding:0; }
#wpcontent { padding-left:0 !important; } #wpbody-content { padding-bottom:0; }
body.index-php, body.index-php #wpwrap, body.index-php #wpcontent, body.index-php #wpbody { background:#fff; }
.vx { background:#fff; }
#wpbody-content .vxd-oto-keep { margin:16px 5.5% 0; }

.vx { --a:#0F6E56; --a-rgb:15,110,86; --a2:#16A37E; --ink:#0F1C18; --sub:#5F716B; --line:#E6EEEB; --soft:#F3F7F5; --soft-a:#E6F3EE;
  --deal:#0F9D6B; --r-card:24px; --r-tile:16px;
  --disp:'Lexend', 'Inter', system-ui, sans-serif;
  font-family:'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color:var(--ink);
  -webkit-font-smoothing:antialiased; padding:0 0 48px; }
.vx *, .vx *::before, .vx *::after { box-sizing:border-box; }
.vx a { text-decoration:none; }
.vx-eyebrow { font-size:11px; font-weight:700; letter-spacing:.24em; text-transform:uppercase; color:var(--a); }
.vx-eyebrow.mute { color:var(--sub); }

/* HERO */
.vx-hero { position:relative; overflow:hidden; padding:36px 5.5% 140px;
  background:
    radial-gradient(60% 80% at 4% 10%, #CFEFE4 0%, rgba(207,239,228,0) 70%),
    radial-gradient(50% 70% at 22% 40%, #D3ECF6 0%, rgba(211,236,246,0) 70%),
    radial-gradient(45% 70% at 55% 25%, #DCDDF7 0%, rgba(220,221,247,0) 70%),
    radial-gradient(40% 60% at 70% 45%, #E8DDF1 0%, rgba(232,221,241,0) 70%),
    radial-gradient(45% 70% at 98% 0%, #F8DED5 0%, rgba(248,222,213,0) 70%),
    linear-gradient(180deg, #EEF6F3 0%, #FFFFFF 100%); }
.vx-hero::after { content:""; position:absolute; inset:auto 0 0 0; height:120px; background:linear-gradient(180deg, rgba(255,255,255,0), #fff); }
.vx-top { position:relative; z-index:1; display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
.vx-brand b { display:block; font-family:var(--disp); font-weight:800; font-size:15px; letter-spacing:.02em; }
.vx-brand span { color:var(--sub); font-size:13px; }
.vx-pill { background:#fff; border-radius:999px; padding:9px 16px; box-shadow:0 6px 20px rgba(var(--a-rgb),.10); white-space:nowrap; }
.vx-title { position:relative; z-index:1; margin-top:44px; }
.vx-title h1 { margin:12px 0 10px; font-family:var(--disp); font-weight:800; font-size:clamp(34px,5.2vw,64px); line-height:1.02; letter-spacing:-.02em; color:var(--ink); }
.vx-title p { margin:0; color:var(--sub); font-size:15px; max-width:620px; line-height:1.6; }
.vx-dot { display:inline-flex; align-items:center; gap:8px; margin-top:16px; font-size:13px; color:var(--sub); }
.vx-dot i { width:10px; height:10px; border-radius:50%; background:var(--a); box-shadow:0 0 0 4px rgba(var(--a-rgb),.15); }

.vx-wrap { position:relative; z-index:2; padding:0 5.5%; margin-top:-104px; display:grid; gap:28px; }
.vx-card { background:#fff; border-radius:var(--r-card); padding:28px; box-shadow:0 24px 60px -24px rgba(16,58,48,.28), 0 2px 6px rgba(16,58,48,.04); }

/* SALEOTO */
.vx-feature { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(0,1fr); gap:32px; align-items:center; }
.vx-feature h2 { line-height:1.25 !important; margin:12px 0 10px; font-family:var(--disp); font-weight:700; font-size:clamp(22px,2.4vw,30px); line-height:1.25; letter-spacing:-.01em; }
.vx-feature p { margin:0 0 18px; color:var(--sub); line-height:1.65; font-size:14.5px; }
.vx-chips { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 22px; }
.vx-chip { background:var(--soft-a); color:var(--a); border-radius:999px; padding:7px 13px; font-size:12.5px; font-weight:600; }
.vx-btn { display:inline-flex; align-items:center; gap:10px; background:var(--a); color:#fff !important; border-radius:999px; padding:13px 22px; font-weight:600; font-size:14px; transition:transform .15s, box-shadow .15s; box-shadow:0 10px 24px -10px rgba(var(--a-rgb),.7); }
.vx-btn:hover { transform:translateY(-1px); box-shadow:0 14px 28px -10px rgba(var(--a-rgb),.8); }
.vx-btn.ghost { background:var(--soft); color:var(--ink) !important; box-shadow:none; }
.vx-btn.ghost:hover { background:var(--soft-a); color:var(--a) !important; }
.vx-btn svg { width:16px; height:16px; }
.vx-stage { position:relative; display:flex; align-items:center; justify-content:center; min-height:300px; border-radius:20px;
  background:radial-gradient(70% 70% at 50% 55%, #EAF6F1 0%, #F6FAF8 60%, #fff 100%); overflow:hidden; }
.vx-stage .ring { position:absolute; left:50%; top:58%; border:1px solid rgba(var(--a-rgb),.14); border-radius:50%; transform:translate(-50%,-50%); }
.vx-stage .r1 { width:62%; height:30%; } .vx-stage .r2 { width:82%; height:44%; } .vx-stage .r3 { width:104%; height:60%; border-color:rgba(var(--a-rgb),.09); } .vx-stage .r4 { width:128%; height:78%; border-color:rgba(var(--a-rgb),.06); }
.vx-stage img { position:relative; z-index:1; width:86%; max-width:440px; height:auto; border-radius:16px; box-shadow:0 30px 50px -24px rgba(16,58,48,.45); }

/* STATS */
.vx-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
.vx-tile { background:var(--soft); border-radius:var(--r-tile); padding:18px 18px 16px; }
.vx-tile span { display:block; color:var(--sub); font-size:12.5px; margin-bottom:8px; }
.vx-tile b { display:block; font-family:var(--disp); font-weight:700; font-size:30px; line-height:1.1; color:var(--ink); }
.vx-tile.hi { background:var(--soft-a); } .vx-tile.hi b { color:var(--a); }
.vx-tile small { display:block; margin-top:8px; font-size:12px; color:var(--sub); }

/* GRID: video + bài viết */
.vx-grid { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,1fr); gap:28px; }
.vx-sec h3 { margin:10px 0 18px; font-family:var(--disp); font-weight:700; font-size:20px; letter-spacing:-.01em; }
.vx-vid { position:relative; padding-bottom:56.25%; border-radius:18px; overflow:hidden; background:#0F1C18; }
.vx-vid iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }
.vx-actions { margin-top:16px; display:flex; flex-wrap:wrap; gap:10px; }
.vx-list { list-style:none; margin:0; padding:0; }
.vx-list li { margin:0; border-top:1px solid var(--line); }
.vx-list li:first-child { border-top:0; }
.vx-list a { display:flex; align-items:center; gap:12px; padding:13px 0; color:var(--ink); font-size:14px; line-height:1.45; font-weight:500; }
.vx-list a:hover { color:var(--a); }
.vx-list a .t { flex:1; }
.vx-list a svg { width:16px; height:16px; flex:0 0 auto; color:#A9B7B2; transition:transform .15s, color .15s; }
.vx-list a:hover svg { color:var(--a); transform:translateX(2px); }
.vx-tag { flex:0 0 auto; border-radius:999px; padding:4px 9px; font-size:10.5px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
.vx-tag.hot { background:#FDECEA; color:#C2410C; } .vx-tag.video { background:var(--soft-a); color:var(--a); }
.vx-more { display:inline-flex; align-items:center; gap:6px; margin-top:14px; color:var(--a); font-weight:600; font-size:13.5px; }
.vx-more svg { width:14px; height:14px; }

/* BAND */
.vx-band { position:relative; overflow:hidden; border-radius:var(--r-card); padding:30px 32px; color:#fff;
  background:linear-gradient(100deg, #0E1F1A 0%, #0F4B3C 55%, var(--a) 100%); display:flex; align-items:center; justify-content:space-between; gap:24px; }
.vx-band::before, .vx-band::after { content:""; position:absolute; border:1px solid rgba(255,255,255,.12); border-radius:50%; }
.vx-band::before { width:520px; height:220px; right:-120px; top:-40px; } .vx-band::after { width:700px; height:320px; right:-220px; top:-90px; border-color:rgba(255,255,255,.07); }
.vx-band .l { position:relative; z-index:1; }
.vx-band .l .vx-eyebrow { color:rgba(255,255,255,.75); }
.vx-band .l b { display:block; margin-top:10px; font-family:var(--disp); font-weight:800; font-size:clamp(24px,3vw,38px); line-height:1.2; letter-spacing:-.01em; }
.vx-band .l span { display:block; margin-top:10px; color:rgba(255,255,255,.72); font-size:14px; line-height:1.5; }
.vx-band .vx-btn { position:relative; z-index:1; background:#fff; color:var(--ink) !important; box-shadow:none; }

/* CONTACT */
.vx-contact { display:flex; align-items:center; justify-content:space-between; gap:20px; }
.vx-who { display:flex; align-items:center; gap:16px; }
.vx-ava { width:60px; height:60px; border-radius:50%; border:2px solid var(--a); padding:3px; flex:0 0 auto; }
.vx-ava span { display:flex; width:100%; height:100%; border-radius:50%; align-items:center; justify-content:center; background:var(--soft-a); color:var(--a); font-family:var(--disp); font-weight:800; font-size:17px; }
.vx-who b { display:block; margin-top:6px; font-family:var(--disp); font-weight:700; font-size:20px; line-height:1.25; }
.vx-phone { font-family:var(--disp); font-weight:800; font-size:clamp(26px,3.4vw,42px); color:var(--a) !important; letter-spacing:.01em; text-align:right; line-height:1.1; }
.vx-phone small { display:block; font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500; color:var(--sub); letter-spacing:0; margin-top:6px; }
.vx-status { text-align:center; } .vx-status .vx-dot { margin-top:0; }

@media (max-width:1100px) { .vx-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:900px) {
  .vx-feature, .vx-grid { grid-template-columns:1fr; }
  .vx-hero { padding:24px 16px 120px; } .vx-wrap { padding:0 16px; gap:20px; }
  .vx-card { padding:20px; border-radius:20px; }
  .vx-band, .vx-contact { flex-direction:column; align-items:flex-start; } .vx-phone { text-align:left; }
  .vx-stage { min-height:220px; }
  .vx-top { flex-wrap:wrap; } .vx-pill { padding:7px 12px; } .vx-pill .vx-eyebrow { font-size:10px; letter-spacing:.14em; }
}
</style>
        <?php
    }

    /** Một vài con số nhanh cho dashboard */
    private function oto_stats() {
        global $wpdb;
        $xe = post_type_exists('xe') ? (int)(wp_count_posts('xe')->publish ?? 0) : null;
        $posts = (int)(wp_count_posts('post')->publish ?? 0);
        $pages = (int)(wp_count_posts('page')->publish ?? 0);
        $lead = null; $t = $wpdb->prefix . 'fluentform_submissions';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t) {
            $lead = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$t` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        }
        return compact('xe', 'posts', 'pages', 'lead');
    }

    /** Trang dashboard full — menu trái WP + nội dung Oto */
    public function oto_render_full() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$this->oto_enabled() || !$screen || $screen->id !== 'dashboard') return;
        $img = VXD_CONN_URL . 'assets/saleoto-banner.webp';
        $st = $this->oto_stats();
        $user = wp_get_current_user();
        $name = get_bloginfo('name') ?: $this->domain;
        $arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
        $ext = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>';
        $play = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/></svg>';
        $posts = [
            ['Hướng dẫn setup quảng cáo Google Ads cho ngành ô tô', 'https://www.youtube.com/watch?v=0w3oco4qh_M', 'video'],
            ['Hướng dẫn setup Google Ads cho sale ô tô từ A-Z (kèm mẫu thực tế)', 'https://www.vinhxd.com/huong-dan-setup-google-ads-cho-sale-o-to/', ''],
            ['Thiết lập theo dõi chuyển đổi trên Google Ads: gọi điện, Zalo và gửi form', 'https://www.vinhxd.com/thiet-lap-theo-doi-chuyen-doi-tren-google-ads-goi-dien-zalo-va-gui-form/', ''],
            ['Theo dõi chuyển đổi và IP đáng ngờ từ Google Ads trực tiếp trên website', 'https://www.vinhxd.com/theo-doi-chuyen-doi-va-ip-dang-ngo-tu-google-ads-truc-tiep-tren-website/', 'hot'],
            ['Thêm chính sách cho website ô tô dễ duyệt khi chạy Google Ads', 'https://www.vinhxd.com/them-chinh-sach-cho-website-o-to-de-duyet-khi-chay-google-ads/', ''],
            ['Quy định điền form đủ 10 số điện thoại Việt Nam với Fluent Forms', 'https://www.vinhxd.com/quy-dinh-dien-form-du-10-so-dien-thoai-viet-nam-voi-fluentform/', ''],
            ['Tạo mật khẩu ứng dụng Gmail để setup SMTP trên FluentSMTP', 'https://www.vinhxd.com/tao-mat-khau-ung-dung-gmail-de-setup-smtp-tren-fluentsmtp/', ''],
        ];
        $num = function ($v) { return $v === null ? '—' : number_format_i18n($v); };
        ?>
        <div class="vx">
          <section class="vx-hero">
            <div class="vx-top">
              <div class="vx-brand"><b><?php echo esc_html($name); ?></b><span><?php echo esc_html($this->domain); ?></span></div>
              <div class="vx-pill"><span class="vx-eyebrow">Quản trị · <?php echo esc_html(date_i18n('d/m/Y')); ?></span></div>
            </div>
            <div class="vx-title">
              <div class="vx-eyebrow">Vĩnh XD · Web ô tô</div>
              <h1>Xin chào<?php echo $user && $user->display_name ? ', ' . esc_html($user->display_name) : ''; ?></h1>
              <p>Công cụ sale, video hướng dẫn và bài viết nên đọc, gọn trong một màn hình.</p>
            </div>
          </section>

          <div class="vx-wrap">
            <div class="vx-card vx-feature">
              <div>
                <div class="vx-eyebrow">Công cụ cho sale · SaleOto</div>
                <h2>Báo giá lăn bánh, trả góp, gửi khách qua Zalo trong 30 giây</h2>
                <p>Giá xe và khuyến mãi cập nhật mỗi ngày, tính lăn bánh đúng từng tỉnh, so sánh trả góp nhiều ngân hàng và tạo ảnh báo giá gửi Zalo.</p>
                <div class="vx-chips"><span class="vx-chip">Giá xe mỗi ngày</span><span class="vx-chip">Lăn bánh từng tỉnh</span><span class="vx-chip">Trả góp nhiều ngân hàng</span><span class="vx-chip">Ảnh báo giá Zalo</span></div>
                <a class="vx-btn" href="https://saleoto.top/app/" target="_blank" rel="noopener">Mở SaleOto ngay <?php echo $arrow; ?></a>
              </div>
              <a class="vx-stage" href="https://saleoto.top/" target="_blank" rel="noopener" aria-label="SaleOto">
                <i class="ring r4"></i><i class="ring r3"></i><i class="ring r2"></i><i class="ring r1"></i>
                <img src="<?php echo esc_url($img); ?>" alt="SaleOto — bộ công cụ sale ô tô" loading="lazy">
              </a>
            </div>

            <div class="vx-grid">
              <div class="vx-card vx-sec">
                <div class="vx-eyebrow">Video hướng dẫn</div>
                <h3>Quản trị web ô tô</h3>
                <div class="vx-vid"><iframe src="https://www.youtube.com/embed/9F4sz6PIzQM?list=PLmJmn3fYDVtPhvvBi964-CVgxwCa0VQCg" title="Hướng dẫn quản trị web ô tô" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
                <div class="vx-actions"><a class="vx-btn ghost" href="https://vinhxd.com/hdoto" target="_blank" rel="noopener"><?php echo $play; ?> Xem tất cả video</a></div>
              </div>
              <div class="vx-card vx-sec">
                <div class="vx-eyebrow">Bài viết nên đọc</div>
                <h3>Google Ads &amp; vận hành web</h3>
                <ul class="vx-list">
                  <?php foreach ($posts as $p): ?>
                  <li><a href="<?php echo esc_url($p[1]); ?>" target="_blank" rel="noopener">
                    <?php if ($p[2] === 'hot'): ?><span class="vx-tag hot">Hot</span><?php elseif ($p[2] === 'video'): ?><span class="vx-tag video">Video</span><?php endif; ?>
                    <span class="t"><?php echo esc_html($p[0]); ?></span><?php echo $arrow; ?></a></li>
                  <?php endforeach; ?>
                </ul>
                <a class="vx-more" href="https://www.vinhxd.com/blog/" target="_blank" rel="noopener">Xem thêm trên vinhxd.com <?php echo $ext; ?></a>
              </div>
            </div>

            <div class="vx-band">
              <div class="l">
                <div class="vx-eyebrow">Kênh YouTube · Chia sẻ kinh nghiệm</div>
                <b>Quản trị web cùng Vĩnh XD</b>
                <span>Mẹo vận hành web, chạy quảng cáo và xử lý lỗi thường gặp.</span>
              </div>
              <a class="vx-btn" href="https://www.youtube.com/channel/UCWBzVYNuS1cNtpQWLl5BwRQ" target="_blank" rel="noopener"><?php echo $play; ?> Xem kênh</a>
            </div>

            <div class="vx-card vx-contact">
              <div class="vx-who">
                <div class="vx-ava"><span>XD</span></div>
                <div><div class="vx-eyebrow">Hỗ trợ kỹ thuật</div><b>Vĩnh XD</b></div>
              </div>
              <a class="vx-phone" href="https://zalo.me/0965401120" target="_blank" rel="noopener">0965 401 120<small>Nhắn Zalo khi web cần chỉnh sửa hoặc gặp lỗi</small></a>
            </div>
            <div class="vx-card">
              <div class="vx-stats">
                <div class="vx-tile hi"><span>Khách để lại thông tin · 30 ngày</span><b><?php echo esc_html($num($st['lead'])); ?></b><small>Từ các form trên website</small></div>
                <div class="vx-tile"><span>Xe đang hiển thị</span><b><?php echo esc_html($num($st['xe'])); ?></b><small>Mẫu xe đã đăng</small></div>
                <div class="vx-tile"><span>Bài viết</span><b><?php echo esc_html($num($st['posts'])); ?></b><small>Tin tức, bảng giá, ưu đãi</small></div>
                <div class="vx-tile"><span>Trang</span><b><?php echo esc_html($num($st['pages'])); ?></b><small>Trang đang xuất bản</small></div>
              </div>
            </div>

            <div class="vx-status"><span class="vx-dot"><i></i>Website đang kết nối Vĩnh XD Connector v<?php echo esc_html(VXD_CONN_VER); ?></span></div>
          </div>
        </div>
        <?php
    }
}
register_activation_hook(__FILE__, function () {
    if (function_exists('vxd_guard_sync_mu')) vxd_guard_sync_mu();
});
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('vxd_meg_daily_ping'); });
VXD_Connector::init();
