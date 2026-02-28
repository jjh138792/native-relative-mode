# Native Relative Mode Ultimate (Stable Fixed)

**WordPress 终极相对路径解放系统**  
前台/后台输出相对化 + 源数据入库相对化 + 动态Host/信任Host策略 + 强制绝对前缀 + Canonical/Cookie精细控制 + 递归锁防OOM + 异常路径修复

**免费开源**  

## 为什么要做这个插件？

我被WordPress的“绝对路径诅咒”搞了几年：  
换域名 → 404满天飞、canonical跳转、序列化替换炸、插件主题硬码绝对URL……  
一开始想直接改核心源码（真疯），后来用MU插件 + 输出缓冲搞定前端相对。  
再后来发现数据库里还是绝对URL，迁移时又得全局替换，于是加了保存时入库相对化。  
越搞越疯，干脆把动态Host、信任列表、跳转守卫、递归锁、后台/登录豁免、异常路径修复全塞进去……  
现在它已经不是插件了，是**推翻WP绝对路径王朝的终极死亡笔记**。

**80wp**：80%的时间在对抗WP的绝对路径信仰，20%在正常建站/写文章/冲AdSense。

## 功能亮点（Ultimate 版专属）

- **动态Host / 信任Host策略**：完全不绑固定域名（X-Forwarded-Host优先），陌生域名自动301跳转到信任列表第一行（防伪造Host滥用）
- **输出层相对化**：ob_start + DOM + XPath全局改写HTML属性（src/srcset、href、action、内联style url()），浏览器端100%相对（或强制前缀）
- **源数据入库相对化**：content_save_pre / excerpt_save_pre 保存时转相对，数据库从此干净（迁移/导出/备份零痛）
- **强制绝对前缀**：后台可配基URL，RSS/Sitemap/Canonical统一套前缀（解决爬虫/协议不认纯相对的边缘case）
- **Canonical / Cookie / 重定向守卫**：开关禁用canonical跳转、多子域统一登录、拦截跨域重定向（防意外跳转）
- **递归锁防OOM**：全局guard_enter/leave，防止极端递归调用导致内存溢出
- **后台/登录页安全豁免**：后台编辑器、wp-login.php不做DOM重写，只靠URL filter + 兜底修复
- **异常路径修复**：专治 //wp-includes/...、https://wp-includes/... 这类坏路径（常见于代理/缓存场景）

## 安装 & 使用

1. 下载ZIP→WP插件页面→上传插件→安装插件
2. 后台搜索 “Native Relative Mode Ultimate” 并激活
3. 进入 **设置 → Relative Mode** 配置：
   - 禁用固定Host / 信任Host列表
   - 强制绝对前缀（可选）
   - 禁用Canonical（默认开）
   - Cookie Domain（可选）
   - 源数据模式（默认开）
4. 配置Cloudflare橙云 + 源站只放行CF IP + Nginx Host白名单（安全底线，必须！）
5. 测试多域名访问、保存文章后数据库检查、RSS/Sitemap输出

**强烈推荐**：先在测试站跑一周，确认兼容性（Elementor、Yoast、缓存插件等）。

## 已知限制 & 注意

- 需要PHP 8.0+（DOMDocument内置）
- 源数据相对化只对post_content/excerpt生效，其他字段（如自定义字段）需手动或WP-CLI处理
- 关闭canonical可能导致duplicate content风险，自行处理SEO（主域canonical）
- 高流量站ob_start有轻微开销，可用缓存插件缓解
- 迁移历史数据：推荐WP-CLI `search-replace --precise --recurse-objects`

## 历史版本对比

- **1.0**：MU插件源头filter暴力相对（硬核但风险高）
- **2.0**：输出缓冲 + HTML层改写（安全进化）
- **Pro**：输出 + 源数据双相对 + 设置页
- **Ultimate**：动态Host + 信任列表 + 跳转守卫 + 递归锁 + 后台安全 + 异常修复（终极稳定版）

## 贡献 & 反馈

欢迎issue/PR！  
想加功能？后台豁免开关、更多字段支持、一键生成WP-CLI迁移命令……来提吧。

## 鸣谢

感谢WordPress官方文档、Stack Exchange、V2EX所有被绝对路径坑过的站长——你们是我革命的燃料🤣

**License**: GPL-2.0-or-later  
**作者**: Jjh (@Jjh138792)  
