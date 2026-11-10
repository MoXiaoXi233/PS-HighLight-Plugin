# PS-Highlight

一个服务端渲染的 Typecho 代码高亮插件，双引擎（highlight.php / Phiki），带结果缓存。

代码在 PHP 里高亮完再输出 HTML，所以不依赖浏览器执行高亮脚本，首屏不会出现「先白后彩」的跳变，也不吃客户端的 CPU。

## 特性

- **双引擎**
  - **highlight.php**：基于 highlight.js，速度快，兼容现成 hljs 主题
  - **Phiki**：基于 TextMate 语法，精度更高，支持嵌套语法，输出内联样式
- **主题**：highlight.php 自带 13 套经典主题，Phiki 自带 62 套
- **结果缓存**：同一段代码 + 同一套配置只高亮一次，之后直接读缓存文件
- **行号 / 复制按钮**：可在插件设置里开关
- **评论高亮**：可选，需要放开评论的 `class` / `style` 属性

## 环境要求

| 项目 | 要求 |
|------|------|
| Typecho | 1.2.0 及以上（插件使用 `TypechoPlugin\PS_Highlight` 命名空间，旧版 1.1 不支持） |
| PHP | 8.0 及以上，推荐 8.2+ |
| 扩展 | `dom`、`mbstring`、`json` |
| 目录可写 | 插件目录下的 `runtime/`（缓存用），不可写时会自动退回系统临时目录 |

依赖已内置在 `vendor/`（highlight.php 9.18.1.10、Phiki），不需要 composer。

## 安装

目录名必须是 **`PS_Highlight`**，因为 Typecho 按「命名空间 = 目录名」加载插件：

```bash
cd /path/to/typecho/usr/plugins
git clone https://github.com/MoXiaoXi233/PS-HighLight-Plugin.git PS_Highlight
```

然后到后台 **控制台 → 插件** 启用「PS-Highlight」，在设置里选引擎和主题。

> 直接下载压缩包时，也请把解压出来的文件夹重命名为 `PS_Highlight`。

## 配置

| 选项 | 说明 |
|------|------|
| 高亮引擎 | `highlight.php`（默认）或 `Phiki` |
| highlight.php 主题 | 仅 highlight.php 引擎生效，插件会自动往 `<head>` 注入对应 CSS |
| Phiki 主题 | 仅 Phiki 引擎生效，样式内联，不需要额外 CSS |
| 显示行号 | 代码块左侧输出行号 |
| 显示复制按钮 | 代码块右上角复制按钮 |

换引擎或主题后缓存按配置指纹自动失效，不需要手动清理。

## 评论高亮

代码高亮默认只作用于文章正文。要让评论里的代码块也高亮：

1. 后台 **设置 → 评论**
2. 把「评论允许的 HTML 标签」改为：

```html
<pre class="" style=""><code class="" style=""><span class="" style="">
```

## 缓存

- 位置：`usr/plugins/PS_Highlight/runtime/cache/*.html`
- 键：`md5(代码内容 + 引擎/主题/行号指纹)`
- 回收：写入时约 2% 概率触发一次，清理 30 天未访问的文件
- 想全量刷新：直接删掉 `runtime/cache/` 下的文件即可

## 目录结构

```
Plugin.php              插件入口、钩子、配置表单、缓存
Engine/
  EngineFactory.php     引擎选择
  EngineInterface.php   引擎接口
  HighlightPhpEngine.php
  PhikiEngine.php
assets/css|js           前端样式与复制按钮脚本
vendor/Highlight        highlight.php
vendor/Phiki            Phiki 与语法/主题资源
```

## 已知取舍

- 高亮在请求路径上同步执行。首次访问含代码的页面会有几十毫秒开销，命中缓存后接近 0。如果站点代码块极多，建议开启页面级缓存（如 nginx `fastcgi_cache`）。
- highlight.php 与 Phiki 的主题不通用，切换引擎需要重新选主题。

## 引用库

| 库 | 版本 | 许可 |
|----|------|------|
| [highlight.php](https://github.com/scrivo/highlight.php) | 9.18.1.10 | BSD-3-Clause |
| [Phiki](https://github.com/phikiphp/phiki) | 见 `vendor/Phiki` | MIT |

## 许可证

MIT，见 [LICENSE](LICENSE)。
