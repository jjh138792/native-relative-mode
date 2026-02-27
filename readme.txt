=== Native Relative Mode Pro (Source-Data Mode) ===
Contributors: jjh138792
Tags: relative-urls, root-relative, multi-domain, domain-agnostic, cloudflare, migration, mirror-site, source-data-relative
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
终极多域名/迁移解决方案：输出层相对化 + 保存时源数据入库相对化 + 可选强制绝对前缀 + Canonical/Cookie 配置。

适合镜像站、无感切换、匿名部署、开发迁移场景。强烈推荐搭配 Cloudflare 橙云 + 源站 IP 白名单 + Host 白名单使用。

详细动机和教程：https://你的站点文章链接

== Installation ==
1. 上传插件文件夹到 /wp-content/plugins/
2. 后台搜索 “Native Relative Mode Pro” 并激活
3. 进入 设置 → Relative Mode 配置强制前缀、Cookie 等
4. 配置服务器安全规则（见教程）

== Frequently Asked Questions ==
= 源数据相对化会破坏序列化吗？
= 不会。我们只用 str_replace 替换 URL 部分，不碰序列化结构。迁移时仍推荐 WP-CLI search-replace --precise。

= 为什么需要 Cloudflare？
= 防止陌生域名直连源站伪造 Host。

= 如何迁移历史数据？
= 插件内不做高风险写库。使用 WP-CLI：
wp search-replace 'https://old-domain.com' '' --all-tables --precise --recurse-objects --dry-run

== Changelog ==
= 2.0.0 =
* 输出缓冲 + 源数据入库双相对化
* 添加后台设置页（强制前缀、Canonical、Cookie、源数据开关）
* 更安全 URL 判断（仅本站转相对）

= 1.0.0 =
* 初始输出层相对化版本
