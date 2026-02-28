=== Native Relative Mode Ultimate ===
Contributors: jjh138792
Tags: relative-urls, root-relative, multi-domain, domain-agnostic, migration, cloudflare, mirror-site
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
终极多域名解放系统：动态Host + 信任列表 + 输出/源数据双相对化 + 强制前缀 + Canonical/Cookie配置 + 递归锁防OOM。

适合镜像站、无感切换、迁移、匿名部署。强烈推荐搭配Cloudflare橙云 + 源站IP白名单 + Host白名单使用

== Installation ==
1. 上传文件夹到 /wp-content/plugins/
2. 后台激活插件
3. 设置 → Relative Mode 配置信任列表、前缀等

== Frequently Asked Questions ==
= 会破坏序列化吗？
= 不会。只对post_content/excerpt生效，不碰其他字段。迁移推荐WP-CLI search-replace --precise。

= 安全吗？
= 安全前提是搭配Cloudflare代理 + 源站IP白名单 + Host白名单。否则有Host伪造风险。

== Changelog ==
= 1.0.1 =
* 稳定版：递归锁防OOM + 后台/登录豁免 + 异常路径修复

== Screenshots ==
1. 后台设置页
