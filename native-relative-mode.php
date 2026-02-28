<?php
/**
 * Plugin Name: Native Relative Mode Pro (Source-Data Mode)
 * Description: 输出层相对化 + 源数据入库相对化 + 可选绝对前缀URL + Canonical/Cookie设置.
 */
if (!defined('ABSPATH')) exit;

/* =========================
 * 基础上下文判断
 * ========================= */
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

/* =========================
 * 设置项
 * ========================= */
add_action('admin_init', function() {
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
        'sanitize_callback' => function($v){ return $v === '0' ? '0' : '1'; },
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
        'sanitize_callback' => function($v){ return $v === '1' ? '1' : '0'; },
        'default' => '1'
    ]);
});

add_action('admin_menu', function() {
    add_options_page('Relative Mode', 'Relative Mode', 'manage_options', 'jjh-relative-mode', 'jjh_relative_mode_page');
});

function jjh_relative_mode_page() {
    if (!current_user_can('manage_options')) return;


    ?>
    <div class="wrap">
      <h1>Relative Mode 设置</h1>

      <form method="post" action="options.php">
        <?php settings_fields('jjh_relative_mode'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="jjh_force_abs_base">强制绝对前缀URL（可空）</label></th>
            <td>
              <input name="jjh_force_abs_base" id="jjh_force_abs_base" type="text" class="regular-text" value="<?php echo esc_attr(get_option('jjh_force_abs_base', '')); ?>" placeholder="例如: https://test.com">
              <p class="description">留空=不处理。填写后：RSS/Sitemap/Canonical 等绝对 URL 统一到该前缀 URL。</p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="jjh_disable_canonical_redirect">禁用 Canonical 强制重定向</label></th>
            <td>
              <?php $canon = get_option('jjh_disable_canonical_redirect', '1'); ?>
              <label><input type="radio" name="jjh_disable_canonical_redirect" value="1" <?php checked($canon, '1'); ?>> 开启（默认）</label><br>
              <label><input type="radio" name="jjh_disable_canonical_redirect" value="0" <?php checked($canon, '0'); ?>> 关闭</label>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="jjh_cookie_domain">Cookie Domain（可空）</label></th>
            <td>
              <input name="jjh_cookie_domain" id="jjh_cookie_domain" type="text" class="regular-text" value="<?php echo esc_attr(get_option('jjh_cookie_domain', '')); ?>" placeholder="例如: .test.com">
              <p class="description">留空=默认当前 Host；填写后用于多子域统一登录。</p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="jjh_source_data_mode">源数据模式</label></th>
            <td>
              <?php $sd = get_option('jjh_source_data_mode', '1'); ?>
              <label><input type="radio" name="jjh_source_data_mode" value="1" <?php checked($sd, '1'); ?>> 开启（默认，保存时写入相对路径）</label><br>
              <label><input type="radio" name="jjh_source_data_mode" value="0" <?php checked($sd, '0'); ?>> 关闭</label>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>

      <hr>
      <h2>历史数据迁移（安全模式）</h2>
      <p>不在插件内做字符串级数据库写入。请使用 WP-CLI 的 <code>search-replace --precise</code> 做序列化安全迁移。</p>
    </div>
    <?php
}

/* =========================
 * URL 处理
 * ========================= */
function jjh_force_base_of_url($url) {
    if (!is_string($url) || $url === '') return $url;

    $forceBase = trim((string)get_option('jjh_force_abs_base', ''));
    if ($forceBase === '') return $url;

    $p = wp_parse_url($url);
    if (!$p) return $url;

    $fp = wp_parse_url($forceBase);
    if (!$fp || empty($fp['host'])) return $url;

    $scheme = $fp['scheme'] ?? (is_ssl() ? 'https' : 'http');
    if (!in_array(strtolower($scheme), ['http','https'], true)) return $url;

    $host = $fp['host'];
    $port = isset($fp['port']) ? ':' . $fp['port'] : '';
    $path = $p['path'] ?? '/';
    if ($path === '') $path = '/';
    $query = isset($p['query']) ? '?' . $p['query'] : '';
    $frag = isset($p['fragment']) ? '#' . $p['fragment'] : '';

    return $scheme . '://' . $host . $port . $path . $query . $frag;
}

function jjh_rel_url_safe($url) {
    if (!is_string($url) || $url === '') return $url;

    $url = jjh_force_base_of_url($url); // 先套绝对前缀策略

    $parts = wp_parse_url($url);
    if (!$parts) return $url;

    $scheme = strtolower($parts['scheme'] ?? '');
    if ($scheme && !in_array($scheme, ['http','https'], true)) return $url;
    if (empty($parts['host'])) return $url;

    $homeHost = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    $siteHost = strtolower((string) wp_parse_url(site_url(), PHP_URL_HOST));
    $forceHost = strtolower((string) wp_parse_url((string)get_option('jjh_force_abs_base', ''), PHP_URL_HOST));
    $host = strtolower((string)$parts['host']);

    if ($host !== $homeHost && $host !== $siteHost && ($forceHost === '' || $host !== $forceHost)) return $url;

    if (jjh_abs_required_context()) return $url;

    $path = $parts['path'] ?? '/';
    if ($path === '') $path = '/';
    if ($path[0] !== '/') $path = '/' . $path;

    $q = isset($parts['query']) ? ('?' . $parts['query']) : '';
    $f = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';
    return $path . $q . $f;
}

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
        'source' => ['src','srcset'], 'video' => ['src','poster'], 'audio' => ['src'],
        'iframe' => ['src'], 'form' => ['action'],
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

function jjh_ob_start_relative_mode() {
    if (!jjh_is_html_response()) return;
    ob_start(function($html){ return jjh_relativize_html($html); });
}
add_action('init', 'jjh_ob_start_relative_mode', 0);

/* =========================
 * Canonical / Cookie
 * ========================= */
function jjh_canonical_disable_enabled(): bool {
    return get_option('jjh_disable_canonical_redirect', '1') === '1';
}
add_filter('redirect_canonical', function($redirect_url){
    return jjh_canonical_disable_enabled() ? false : $redirect_url;
}, 999);

add_action('plugins_loaded', function(){
    $cd = trim((string)get_option('jjh_cookie_domain', ''));
    if ($cd !== '' && !defined('COOKIE_DOMAIN')) define('COOKIE_DOMAIN', $cd);
}, 1);

/* =========================
 * 源数据模式（保存时改写）
 * ========================= */
function jjh_source_data_mode_enabled(): bool {
    return get_option('jjh_source_data_mode', '1') === '1';
}

function jjh_normalize_store_relative($content) {
    if (!jjh_source_data_mode_enabled()) return $content;
    if (!is_string($content) || $content === '') return $content;

    $bases = array_unique(array_filter([
        rtrim(home_url('/'), '/'),
        rtrim(site_url('/'), '/'),
        rtrim((string)get_option('jjh_force_abs_base', ''), '/'),
    ]));

    foreach ($bases as $b) {
        $content = str_replace($b . '/', '/', $content);
        $content = str_replace($b, '', $content);
    }
    return $content;
}

add_filter('content_save_pre', 'jjh_normalize_store_relative', 20);
add_filter('excerpt_save_pre', 'jjh_normalize_store_relative', 20);

/* =========================
 * 历史数据迁移（插件内禁用高风险写库）
 * =========================
 * 不在插件中执行字符串级 SQL 替换。
 * 请改用 WP-CLI（支持序列化安全替换）：
 *
 * 1) 先 dry-run
 * wp search-replace 'https://old-domain.com' '' --all-tables --precise --recurse-objects --dry-run
 *
 * 2) 确认后执行
 * wp search-replace 'https://old-domain.com' '' --all-tables --precise --recurse-objects
 *
 * 建议只对明确字段/表执行，先备份再操作。
 */
