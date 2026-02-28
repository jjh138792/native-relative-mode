<?php
/**
 * Plugin Name: Native Relative Mode Ultimate (Stable Fixed)
 * Description: 稳定版：前台相对化、后台/登录页安全、动态Host/信任Host、递归锁修复OOM.
 * Version: 1.0.1
 * Author: jjh138792
 * License: GPLv2 or later
 */
if (!defined('ABSPATH')) exit;

/* =========================
 * 全局递归锁（修复OOM关键）
 * ========================= */
if (!function_exists('jjh_guard_enter')) {
    function jjh_guard_enter($key){
        if (!isset($GLOBALS['jjh_guard_locks']) || !is_array($GLOBALS['jjh_guard_locks'])) {
            $GLOBALS['jjh_guard_locks'] = [];
        }
        if (!empty($GLOBALS['jjh_guard_locks'][$key])) return false;
        $GLOBALS['jjh_guard_locks'][$key] = 1;
        return true;
    }
}
if (!function_exists('jjh_guard_leave')) {
    function jjh_guard_leave($key){
        if (!isset($GLOBALS['jjh_guard_locks']) || !is_array($GLOBALS['jjh_guard_locks'])) {
            $GLOBALS['jjh_guard_locks'] = [];
        }
        $GLOBALS['jjh_guard_locks'][$key] = 0;
    }
}

/* =========================
 * 基础工具
 * ========================= */
function jjh_current_req_host(): string {
    $h = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
    if (strpos($h, ',') !== false) $h = trim(explode(',', $h)[0]);
    $h = preg_replace('/[^a-zA-Z0-9\.\-:\[\]]/', '', (string)$h);
    $h = preg_replace('/:\d+$/', '', $h);
    return strtolower((string)$h);
}

function jjh_current_scheme(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) return 'https';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
    return 'http';
}

function jjh_is_login_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($uri, '/wp-login.php') !== false;
}

function jjh_abs_required_context(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (function_exists('is_feed') && is_feed()) return true;
    if (strpos($uri, '/feed') !== false) return true;
    if (strpos($uri, '/wp-sitemap') !== false) return true;
    if (strpos($uri, '/wp-json') !== false) return true;
    if (function_exists('wp_is_json_request') && wp_is_json_request()) return true;
    return false;
}

function jjh_is_html_response(): bool {
    return !jjh_abs_required_context();
}

function jjh_dynamic_host_enabled(): bool {
    return get_option('jjh_disable_fixed_host', '1') === '1';
}

function jjh_get_trusted_hosts(): array {
    $raw = (string)get_option('jjh_trusted_hosts', '');
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $hosts = [];
    foreach ((array)$lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strpos($line, '://') !== false) {
            $h = wp_parse_url($line, PHP_URL_HOST);
            $h = preg_replace('/[^a-zA-Z0-9\.\-]/', '', (string)$h);
        } else {
            $h = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $line);
        }
        if ($h) $hosts[] = strtolower($h);
    }
    return array_values(array_unique($hosts));
}

function jjh_is_same_site_host(string $host): bool {
    $host = strtolower($host);
    // 注意：用 option 原始值，避免递归触发 home_url/site_url filter
    $homeHost = strtolower((string)wp_parse_url((string)get_option('home'), PHP_URL_HOST));
    $siteHost = strtolower((string)wp_parse_url((string)get_option('siteurl'), PHP_URL_HOST));
    $forceHost = strtolower((string)wp_parse_url((string)get_option('jjh_force_abs_base', ''), PHP_URL_HOST));

    $cands = array_filter([$homeHost, $siteHost, $forceHost]);
    foreach (jjh_get_trusted_hosts() as $h) $cands[] = $h;
    $cands = array_values(array_unique($cands));
    return in_array($host, $cands, true);
}

/* =========================
 * 设置项
 * ========================= */
add_action('admin_init', function() {
    register_setting('jjh_relative_mode', 'jjh_disable_fixed_host', [
        'type' => 'string',
        'sanitize_callback' => fn($v) => $v === '1' ? '1' : '0',
        'default' => '1'
    ]);

    register_setting('jjh_relative_mode', 'jjh_trusted_hosts', [
        'type' => 'string',
        'sanitize_callback' => fn($v) => trim((string)$v),
        'default' => ''
    ]);

    register_setting('jjh_relative_mode', 'jjh_force_abs_base', [
        'type' => 'string',
        'sanitize_callback' => function($v){
            $v = trim((string)$v);
            if ($v === '') return '';
            if (strpos($v, '://') === false) $v = 'https://' . $v;
            $p = wp_parse_url($v);
            if (!$p || empty($p['host'])) return '';
            $scheme = (!empty($p['scheme']) && in_array(strtolower($p['scheme']), ['http','https'], true)) ? strtolower($p['scheme']) : 'https';
            $host = preg_replace('/[^a-zA-Z0-9\.\-]/', '', (string)$p['host']);
            $port = isset($p['port']) ? ':' . intval($p['port']) : '';
            return $host ? ($scheme . '://' . $host . $port) : '';
        },
        'default' => ''
    ]);

    register_setting('jjh_relative_mode', 'jjh_disable_canonical_redirect', [
        'type' => 'string',
        'sanitize_callback' => fn($v) => $v === '0' ? '0' : '1',
        'default' => '1'
    ]);

    register_setting('jjh_relative_mode', 'jjh_cookie_domain', [
        'type' => 'string',
        'sanitize_callback' => function($v){
            $v = trim((string)$v);
            if ($v === '') return '';
            return preg_replace('/[^a-zA-Z0-9\.\-]/', '', $v);
        },
        'default' => ''
    ]);

    register_setting('jjh_relative_mode', 'jjh_source_data_mode', [
        'type' => 'string',
        'sanitize_callback' => fn($v) => $v === '1' ? '1' : '0',
        'default' => '1'
    ]);
});

add_action('admin_menu', function() {
    add_options_page('Relative Mode', 'Relative Mode', 'manage_options', 'jjh-relative-mode', function() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
          <h1>Relative Mode 设置</h1>
          <form method="post" action="options.php">
            <?php settings_fields('jjh_relative_mode'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row">禁用固定 Host</th>
                <td>
                  <?php $dfh = get_option('jjh_disable_fixed_host', '1'); ?>
                  <label><input type="radio" name="jjh_disable_fixed_host" value="1" <?php checked($dfh, '1'); ?>> 开启（动态Host）</label><br>
                  <label><input type="radio" name="jjh_disable_fixed_host" value="0" <?php checked($dfh, '0'); ?>> 关闭（启用信任Host列表）</label>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="jjh_trusted_hosts">信任 Host 列表（关闭固定Host时生效）</label></th>
                <td><textarea name="jjh_trusted_hosts" id="jjh_trusted_hosts" class="large-text code" rows="6"><?php echo esc_textarea(get_option('jjh_trusted_hosts', '')); ?></textarea></td>
              </tr>
              <tr>
                <th scope="row"><label for="jjh_force_abs_base">强制绝对前缀URL（可空）</label></th>
                <td><input name="jjh_force_abs_base" id="jjh_force_abs_base" type="text" class="regular-text" value="<?php echo esc_attr(get_option('jjh_force_abs_base', '')); ?>" placeholder="例如: https://test.com"></td>
              </tr>
              <tr>
                <th scope="row">禁用 Canonical 强制重定向</th>
                <td>
                  <?php $canon = get_option('jjh_disable_canonical_redirect', '1'); ?>
                  <label><input type="radio" name="jjh_disable_canonical_redirect" value="1" <?php checked($canon, '1'); ?>> 开启（默认）</label><br>
                  <label><input type="radio" name="jjh_disable_canonical_redirect" value="0" <?php checked($canon, '0'); ?>> 关闭</label>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="jjh_cookie_domain">Cookie Domain（可空）</label></th>
                <td><input name="jjh_cookie_domain" id="jjh_cookie_domain" type="text" class="regular-text" value="<?php echo esc_attr(get_option('jjh_cookie_domain', '')); ?>"></td>
              </tr>
              <tr>
                <th scope="row">源数据保存时相对化</th>
                <td>
                  <?php $sd = get_option('jjh_source_data_mode', '1'); ?>
                  <label><input type="radio" name="jjh_source_data_mode" value="1" <?php checked($sd, '1'); ?>> 开启（默认）</label><br>
                  <label><input type="radio" name="jjh_source_data_mode" value="0" <?php checked($sd, '0'); ?>> 关闭</label>
                </td>
              </tr>
            </table>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    });
});

/* Host白名单策略（仅前台） */
add_action('template_redirect', function() {
    if (is_admin() || jjh_is_login_request() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    if (jjh_dynamic_host_enabled()) return;

    $trusted = jjh_get_trusted_hosts();
    if (empty($trusted)) return;

    $reqHost = jjh_current_req_host();
    if ($reqHost === '' || in_array($reqHost, $trusted, true)) return;

    $target = jjh_current_scheme() . '://' . $trusted[0] . ($_SERVER['REQUEST_URI'] ?? '/');
    wp_safe_redirect($target, 301);
    exit;
}, 0);

/* 动态Host接管URL生成 */
function jjh_apply_current_host($url) {
    if (!jjh_dynamic_host_enabled()) return $url;
    if (!is_string($url) || $url === '') return $url;

    if (!jjh_guard_enter(__FUNCTION__)) return $url;
    try {
        $p = wp_parse_url($url);
        if (!$p || empty($p['host'])) return $url;

        $host = strtolower((string)$p['host']);
        // 修复异常协议相对本地路径：//wp-includes/... -> /wp-includes/...
        if (in_array($host, ['wp-includes', 'wp-content', 'wp-admin'], true)) {
            $path = $p['path'] ?? '';
            $query = isset($p['query']) ? ('?' . $p['query']) : '';
            $frag = isset($p['fragment']) ? ('#' . $p['fragment']) : '';
            return '/' . $host . $path . $query . $frag;
        }

        $reqHost = jjh_current_req_host();
        if (!$reqHost) return $url;

        $scheme = !empty($p['scheme']) ? $p['scheme'] : jjh_current_scheme();
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $path   = $p['path'] ?? '/';
        if ($path === '') $path = '/';
        $query  = isset($p['query']) ? '?' . $p['query'] : '';
        $frag   = isset($p['fragment']) ? '#' . $p['fragment'] : '';

        return $scheme . '://' . $reqHost . $port . $path . $query . $frag;
    } finally {
        jjh_guard_leave(__FUNCTION__);
    }
}

add_filter('pre_option_home', function($v){
    if (!jjh_dynamic_host_enabled()) return $v;
    $h = jjh_current_req_host();
    return $h ? (jjh_current_scheme() . '://' . $h) : $v;
}, 99);
add_filter('pre_option_siteurl', function($v){
    if (!jjh_dynamic_host_enabled()) return $v;
    $h = jjh_current_req_host();
    return $h ? (jjh_current_scheme() . '://' . $h) : $v;
}, 99);

$jjh_url_filters = [
    'home_url','site_url','admin_url','network_admin_url','network_home_url','network_site_url',
    'includes_url','content_url','plugins_url','theme_file_uri','rest_url',
    'login_url','logout_url','lostpassword_url','register_url'
];
foreach ($jjh_url_filters as $f) {
    add_filter($f, 'jjh_apply_current_host', 99, 4);
}

add_filter('allowed_redirect_hosts', function($hosts){
    $h = jjh_current_req_host();
    if ($h && !in_array($h, $hosts, true)) $hosts[] = $h;
    foreach (jjh_get_trusted_hosts() as $th) {
        if (!in_array($th, $hosts, true)) $hosts[] = $th;
    }
    return $hosts;
}, 99);

/* 强制前缀 + 相对化 */
function jjh_force_base_of_url($url) {
    if (!is_string($url) || $url === '') return $url;
    $forceBase = trim((string)get_option('jjh_force_abs_base', ''));
    if ($forceBase === '') return $url;

    if (!jjh_guard_enter(__FUNCTION__)) return $url;
    try {
        $p = wp_parse_url($url);
        if (!$p) return $url;
        $fp = wp_parse_url($forceBase);
        if (!$fp || empty($fp['host'])) return $url;

        $scheme = $fp['scheme'] ?? jjh_current_scheme();
        if (!in_array(strtolower($scheme), ['http','https'], true)) return $url;

        $host  = $fp['host'];
        $port  = isset($fp['port']) ? ':' . $fp['port'] : '';
        $path  = $p['path'] ?? '/';
        if ($path === '') $path = '/';
        $query = isset($p['query']) ? '?' . $p['query'] : '';
        $frag  = isset($p['fragment']) ? '#' . $p['fragment'] : '';

        return $scheme . '://' . $host . $port . $path . $query . $frag;
    } finally {
        jjh_guard_leave(__FUNCTION__);
    }
}

function jjh_fix_double_slash_local($u) {
    if (!is_string($u)) return $u;
    $u = trim($u);
    // 修复 //wp-includes/... //wp-content/... //wp-admin/... 这类错误本地路径
    if (preg_match('#^//(wp-(includes|content|admin)(/.*)?)$#i', $u, $m)) {
        return '/' . ltrim($m[1], '/');
    }
    return $u;
}

function jjh_fix_malformed_root_urls_in_html($html) {
    if (!is_string($html) || $html === '') return $html;
    // href/src="//wp-..." -> "/wp-..."
    $html = preg_replace('#(["\'\(])//(wp-(includes|content|admin)(/[^"\'\)\s]*)?)#i', '$1/$2', $html);
    return $html;
}

function jjh_rel_url_safe($url) {
    if (!is_string($url) || $url === '') return $url;
    if (!jjh_guard_enter(__FUNCTION__)) return $url;
    try {
        $url = jjh_fix_double_slash_local($url);
        $url = jjh_force_base_of_url($url);

        $cf = function_exists('current_filter') ? current_filter() : '';
        $needs_absolute_in_admin = in_array($cf, [
            'home_url','site_url','admin_url','network_admin_url','network_home_url','network_site_url',
            'login_url','logout_url','lostpassword_url','register_url','rest_url','get_canonical_url'
        ], true);

        // 后台/登录页的核心URL保持绝对，避免 theme.php 等依赖 host 的逻辑报错
        if ((is_admin() || jjh_is_login_request()) && $needs_absolute_in_admin) {
            if (strlen($url) > 0 && $url[0] === '/' && (strlen($url) < 2 || $url[1] !== '/')) {
                $host = jjh_current_req_host();
                if ($host) return jjh_current_scheme() . '://' . $host . $url;
            }
            return $url;
        }

        if (strlen($url) > 0 && $url[0] === '/' && (strlen($url) < 2 || $url[1] !== '/')) return $url;

        if (strpos($url, '//') === 0) {
            $h = parse_url((is_ssl() ? 'https:' : 'http:') . $url, PHP_URL_HOST);
            if ($h && !jjh_is_same_site_host(strtolower((string)$h))) return $url;
        }

        $parts = wp_parse_url($url);
        if (!$parts) return $url;

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme && !in_array($scheme, ['http','https'], true)) return $url;
        if (empty($parts['host'])) return $url;

        $host = strtolower((string)$parts['host']);

        // 再兜底一次异常主机名场景：https://wp-includes/js/... -> /wp-includes/js/...
        if (in_array($host, ['wp-includes', 'wp-content', 'wp-admin'], true)) {
            $path = $parts['path'] ?? '';
            $q = isset($parts['query']) ? '?' . $parts['query'] : '';
            $f = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
            return '/' . $host . $path . $q . $f;
        }

        if (!jjh_is_same_site_host($host)) return $url;
        if (jjh_abs_required_context()) return $url;

        $path = $parts['path'] ?? '/';
        if ($path === '') $path = '/';
        if ($path[0] !== '/') $path = '/' . $path;
        $q = isset($parts['query']) ? '?' . $parts['query'] : '';
        $f = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return $path . $q . $f;
    } finally {
        jjh_guard_leave(__FUNCTION__);
    }
}

$jjh_rel_filters = [
    'home_url','site_url','admin_url','network_admin_url','network_home_url','network_site_url',
    'includes_url','content_url','plugins_url','theme_file_uri','rest_url',
    'login_url','logout_url','lostpassword_url','register_url',
    'post_link','page_link','post_type_link','term_link','author_link','day_link','month_link','year_link','attachment_link','search_link',
    'script_loader_src','style_loader_src','wp_get_attachment_url','the_permalink','get_canonical_url'
];
foreach ($jjh_rel_filters as $f) {
    add_filter($f, 'jjh_rel_url_safe', 99, 4);
}

// 后台专用兜底（高优先级）：修正 //wp-includes / //wp-content / //wp-admin 异常路径
function jjh_admin_asset_final_fix($url) {
    if (!is_string($url) || $url === '') return $url;

    // 仅后台或登录页生效，避免影响前台
    if (!(is_admin() || jjh_is_login_request())) return $url;

    $url = trim($url);

    // //wp-includes/js/... -> /wp-includes/js/...
    if (preg_match('#^//(wp-(includes|content|admin)(/.*)?)$#i', $url, $m)) {
        return '/' . ltrim($m[1], '/');
    }

    // https://wp-includes/js/... -> /wp-includes/js/...
    $p = wp_parse_url($url);
    if ($p && !empty($p['host'])) {
        $h = strtolower((string)$p['host']);
        if (in_array($h, ['wp-includes', 'wp-content', 'wp-admin'], true)) {
            $path = $p['path'] ?? '';
            $q = isset($p['query']) ? '?' . $p['query'] : '';
            $f = isset($p['fragment']) ? '#' . $p['fragment'] : '';
            return '/' . $h . $path . $q . $f;
        }
    }

    return $url;
}

add_filter('script_loader_src', 'jjh_admin_asset_final_fix', 99999);
add_filter('style_loader_src', 'jjh_admin_asset_final_fix', 99999);

add_filter('wp_calculate_image_srcset', function($sources){
    if (!is_array($sources)) return $sources;
    foreach ($sources as $w => $item) {
        if (!empty($item['url'])) $sources[$w]['url'] = jjh_rel_url_safe($item['url']);
    }
    return $sources;
}, 99);

/* 前台DOM增强：后台和登录页禁用 */
function jjh_relativize_html($html) {
    if (!is_string($html) || $html === '') return $html;
    if (!class_exists('DOMDocument')) return $html;

    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$ok) {
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $attrMap = [
        'a' => ['href'], 'img' => ['src','srcset'], 'script' => ['src'], 'link' => ['href'],
        'source' => ['src','srcset'], 'video' => ['src','poster'], 'audio' => ['src'], 'iframe' => ['src'], 'form' => ['action'],
    ];

    foreach ($attrMap as $tag => $attrs) {
        foreach ($xpath->query('//' . $tag) as $node) {
            foreach ($attrs as $attr) {
                if (!$node->hasAttribute($attr)) continue;
                $val = $node->getAttribute($attr);
                if ($attr === 'srcset') {
                    $parts = array_map('trim', explode(',', $val));
                    $newParts = [];
                    foreach ($parts as $p) {
                        if ($p === '') continue;
                        $seg = preg_split('/\s+/', $p);
                        $u = $seg[0] ?? '';
                        $d = $seg[1] ?? '';
                        $newParts[] = trim(jjh_rel_url_safe($u) . ' ' . $d);
                    }
                    $node->setAttribute($attr, implode(', ', $newParts));
                } else {
                    $node->setAttribute($attr, jjh_rel_url_safe($val));
                }
            }
        }
    }

    $out = $dom->saveHTML();
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return preg_replace('/^<\?xml[^>]+>\s*/', '', $out);
}

add_action('init', function(){
    if (!jjh_is_html_response()) return;

    // 修复渲染崩坏：后台与登录页不做DOM重写（核心规则仍由URL过滤器接管）
    if (is_admin() || jjh_is_login_request()) return;

    // 只处理 GET 页面，降低性能开销
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;

    ob_start(function($html){
        if (!is_string($html) || $html === '') return $html;

        // 先做超轻量修复（解决 //wp-includes 这类坏路径）
        $html = jjh_fix_malformed_root_urls_in_html($html);

        // 性能保护：超大页面不做DOM改写
        if (strlen($html) > 350000) return $html;

        // 页面里没有绝对URL特征时，跳过DOM改写
        if (strpos($html, 'http://') === false && strpos($html, 'https://') === false) return $html;

        return jjh_relativize_html($html);
    });
}, 0);

/* Canonical / Redirect / Cookie / 源数据 */
add_filter('redirect_canonical', function($url){
    return get_option('jjh_disable_canonical_redirect', '1') === '1' ? false : $url;
}, 9999);

add_filter('wp_redirect', function($location, $status){
    $reqHost = jjh_current_req_host();
    if (!$reqHost) return $location;

    $p = wp_parse_url($location);
    if (!$p || empty($p['host'])) return $location;

    $targetHost = strtolower((string)$p['host']);
    $reqHostL   = strtolower((string)$reqHost);

    if ($targetHost !== $reqHostL && jjh_is_same_site_host($targetHost)) {
        $scheme = !empty($p['scheme']) ? $p['scheme'] : jjh_current_scheme();
        $path   = $p['path'] ?? '/';
        $query  = isset($p['query']) ? '?' . $p['query'] : '';
        $frag   = isset($p['fragment']) ? '#' . $p['fragment'] : '';
        return $scheme . '://' . $reqHost . $path . $query . $frag;
    }

    return $location;
}, 9999, 2);

add_action('plugins_loaded', function(){
    $cd = trim((string)get_option('jjh_cookie_domain', ''));
    if ($cd !== '' && !defined('COOKIE_DOMAIN')) define('COOKIE_DOMAIN', $cd);
}, 1);

function jjh_source_data_mode_enabled(): bool {
    return get_option('jjh_source_data_mode', '1') === '1';
}

function jjh_normalize_store_relative($content) {
    if (!jjh_source_data_mode_enabled()) return $content;
    if (!is_string($content) || $content === '') return $content;

    $bases = array_unique(array_filter([
        rtrim((string)get_option('home'), '/'),
        rtrim((string)get_option('siteurl'), '/'),
        rtrim((string)get_option('jjh_force_abs_base', ''), '/'),
    ]));

    foreach ($bases as $b) {
        if ($b === '') continue;
        $content = str_replace($b . '/', '/', $content);
        $content = str_replace($b, '', $content);
    }
    return $content;
}
add_filter('content_save_pre', 'jjh_normalize_store_relative', 20);
add_filter('excerpt_save_pre', 'jjh_normalize_store_relative', 20);
