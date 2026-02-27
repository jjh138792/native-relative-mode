<?php
/**
 * Plugin Name: Native Relative Mode
 * Description: 前台原生相对路径输出，RSS/Sitemap 保持绝对。
 */

if (!defined('ABSPATH')) exit;

function jjh_is_absolute_required_context(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    if (function_exists('is_feed') && is_feed()) return true;
    if (strpos($uri, '/feed') !== false) return true;
    if (strpos($uri, '/wp-sitemap') !== false) return true;

    // REST 保持绝对更稳（客户端兼容性）
    if (strpos($uri, '/wp-json') !== false) return true;

    return false;
}

function jjh_rel_url($url) {
    if (!is_string($url) || $url === '') return $url;
    if (jjh_is_absolute_required_context()) return $url;

    // 协议相对 //example.com/path 也转相对
    if (strpos($url, '//') === 0) {
        $url = (is_ssl() ? 'https:' : 'http:') . $url;
    }

    $parts = wp_parse_url($url);
    if (!$parts) return $url;

    // 非 http(s) 或第三方协议不改
    if (!empty($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http','https'], true)) {
        return $url;
    }

    $path = $parts['path'] ?? '/';
    if ($path === '') $path = '/';
    if ($path[0] !== '/') $path = '/' . $path;

    $q = isset($parts['query']) ? ('?' . $parts['query']) : '';
    $f = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

    // 统一返回相对路径
    return $path . $q . $f;
}

function jjh_relativize_html_attrs($html) {
    if (!is_string($html) || $html === '' || jjh_is_absolute_required_context()) return $html;

    if (!class_exists('DOMDocument')) return $html; // 无 DOM 扩展时直接跳过

    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    if (!$ok) {
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $html;
    }

    $xpath = new DOMXPath($dom);

    // 仅改 HTML 属性里的 URL，不碰纯文本/JSON/代码块
    $attrMap = [
        'a' => ['href'],
        'img' => ['src', 'srcset'],
        'script' => ['src'],
        'link' => ['href'],
        'source' => ['src', 'srcset'],
        'video' => ['src', 'poster'],
        'audio' => ['src'],
        'iframe' => ['src'],
        'form' => ['action'],
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
                        $u = jjh_rel_url($u);
                        $newParts[] = trim($u . ' ' . $d);
                    }
                    $node->setAttribute($attr, implode(', ', $newParts));
                } else {
                    $node->setAttribute($attr, jjh_rel_url($val));
                }
            }
        }
    }

    // 可选：处理内联 style 的 url(...)（只改本站 http(s)）
    foreach ($xpath->query('//*[@style]') as $node) {
        $style = $node->getAttribute('style');
        $style = preg_replace_callback('/url\(([^\)]+)\)/i', function($m){
            $raw = trim($m[1], " \t\n\r\0\x0B\"'");
            $new = jjh_rel_url($raw);
            return 'url(' . $new . ')';
        }, $style);
        $node->setAttribute('style', $style);
    }

    $out = $dom->saveHTML();
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    // 去掉 loadHTML 加的 XML 声明
    $out = preg_replace('/^<\?xml[^>]+>\s*/', '', $out);
    return $out;
}

function jjh_rel_content($content) {
    return jjh_relativize_html_attrs($content);
}

// 防止 WordPress 把“当前 Host”纠正回历史域名
add_filter('redirect_canonical', '__return_false', 999);

// 允许当前 Host 通过 wp_safe_redirect 白名单
add_filter('allowed_redirect_hosts', function($hosts){
    $h = $_SERVER['HTTP_HOST'] ?? '';
    $h = preg_replace('/[^a-zA-Z0-9\.\-:\[\]]/', '', (string)$h);
    if ($h && !in_array($h, $hosts, true)) $hosts[] = $h;
    return $hosts;
}, 99);

// 链接层
add_filter('home_url', 'jjh_rel_url', 99);
add_filter('site_url', 'jjh_rel_url', 99);
add_filter('post_link', 'jjh_rel_url', 99);
add_filter('page_link', 'jjh_rel_url', 99);
add_filter('post_type_link', 'jjh_rel_url', 99);
add_filter('term_link', 'jjh_rel_url', 99);
add_filter('author_link', 'jjh_rel_url', 99);
add_filter('day_link', 'jjh_rel_url', 99);
add_filter('month_link', 'jjh_rel_url', 99);
add_filter('year_link', 'jjh_rel_url', 99);
add_filter('attachment_link', 'jjh_rel_url', 99);
add_filter('search_link', 'jjh_rel_url', 99);
add_filter('the_permalink', 'jjh_rel_url', 99);

// 静态资源
add_filter('script_loader_src', 'jjh_rel_url', 99);
add_filter('style_loader_src', 'jjh_rel_url', 99);
add_filter('wp_get_attachment_url', 'jjh_rel_url', 99);
add_filter('wp_calculate_image_srcset', function($sources){
    if (!is_array($sources) || jjh_is_absolute_required_context()) return $sources;
    foreach ($sources as $w => $item) {
        if (!empty($item['url'])) $sources[$w]['url'] = jjh_rel_url($item['url']);
    }
    return $sources;
}, 99);

// 内容层
add_filter('the_content', 'jjh_rel_content', 99);
add_filter('widget_text', 'jjh_rel_content', 99);
add_filter('widget_text_content', 'jjh_rel_content', 99);

// 菜单
add_filter('nav_menu_link_attributes', function($atts){
    if (!empty($atts['href'])) $atts['href'] = jjh_rel_url($atts['href']);
    return $atts;
}, 99);

// canonical：普通前台相对化，feed/sitemap 保绝对
add_filter('get_canonical_url', function($url){
    return jjh_rel_url($url);
}, 99);