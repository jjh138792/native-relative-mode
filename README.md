# Native Relative Mode Pro (Source-Data Mode)

**终极多域名/迁移/镜像解决方案**：输出层相对化 + 保存时源数据入库相对化 + 可选强制绝对前缀 + Canonical/Cookie 配置。  
**免费开源，牛逼到我自己都不敢上WP官方商城**🤣

## 为什么要做这个插件？

我被WordPress的“绝对路径诅咒”搞了几年：换个域名就404满天飞、canonical跳转、数据库替换序列化炸、插件主题硬码绝对URL……  
一开始想直接改核心源码（真疯），后来冷静下来，用MU插件 + 输出缓冲搞定前端相对。  
再后来发现：光前端相对不够，数据库里还存着绝对URL，迁移时还是得全局替换。  
于是就有了这个Pro版：**保存时直接入库相对路径**，彻底治本。  
结果越搞越疯，干脆加了后台设置、强制前缀、Cookie Domain控制……  
现在它已经不是插件了，是“推翻WP绝对路径王朝”的死亡笔记Pro版。

**80wp**：80%的时间在对抗WP的绝对路径信仰，20%在正常建站/写文章/冲AdSense。

## 功能亮点（Pro版专属）

- **输出层相对化**：ob_start捕获HTML最终输出，用DOM + XPath全局改写属性（a href、img src/srcset、script src、内联style url()等），浏览器看到的永远相对（或强制前缀）。
- **源数据入库相对化**：content_save_pre/excerpt_save_pre钩子，在保存文章/摘要时就把绝对URL转相对，数据库从此干净（迁移/导出/备份再也不用序列化替换炸裂）。
- **强制绝对前缀URL**：后台设置一个基URL（e.g. https://test.com），RSS/Sitemap/Canonical/绝对链接统一套这个前缀（解决爬虫/协议不认纯相对的边缘case）。
- **Canonical重定向开关**：禁用/启用canonical跳转，实现多域名无感访问同一内容。
- **Cookie Domain配置**：支持多子域统一登录（e.g. .example.com）。
- **安全优先**：只改本站URL，外链不动；内部API/后台逻辑保持绝对，兼容性拉满。
- **迁移建议**：插件内不做高风险SQL写库，推荐WP-CLI search-replace --precise（序列化安全）。

## 安装 & 使用

1. 下载ZIP或git clone到 `/wp-content/plugins/native-relative-mode-pro`
2. 后台激活插件
3. 去 **设置 → Relative Mode** 配置：
   - 强制绝对前缀（可选）
   - 禁用Canonical（默认开）
   - Cookie Domain（可选）
   - 源数据模式（默认开）
4. 配置Cloudflare橙云 + 源站只放行CF IP + Nginx Host白名单（安全底线，必须！）
5. 测试多域名访问、保存文章后数据库检查、RSS/Sitemap输出

**强烈推荐**：先在测试站跑一周，确认兼容性（Elementor、Yoast、缓存插件等）。

## 历史版本对比

- **1.0**：MU插件源头filter暴力相对（硬核但风险高）
- **2.0**：输出缓冲 + HTML层改写（安全进化）
- **Pro (2.0+)**：输出 + 源数据双相对 + 设置页 + 强制前缀 + Cookie控制（终极版）

## 已知限制 & 注意

- 需要PHP 8.0+（DOMDocument内置）。
- 源数据相对化只对post_content/excerpt生效，其他字段（如自定义字段）需手动或WP-CLI处理。
- 关闭canonical可能导致duplicate content风险，自行处理SEO（主域canonical）。
- 高流量站ob_start有轻微开销，可用缓存插件缓解。

## 贡献 & 反馈

欢迎issue/PR！  
想加功能？后台豁免开关、更多字段支持、自动检测入口Host加白名单……来提吧。

## 鸣谢

感谢WordPress官方文档、Stack Exchange、V2EX所有被绝对路径坑过的站长——你们是我革命的燃料🤣

**License**: GPL-2.0-or-later  
**作者**：Jjh (@Jjh138792)  
**革命口号**：80wp，从被WP搞疯，到搞疯WP。
