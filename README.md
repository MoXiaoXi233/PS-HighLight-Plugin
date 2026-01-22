# PS-Highlight

一个现代化的 Typecho 代码高亮插件，支持双引擎切换。

## 特性

- **双引擎支持**
  - **highlight.php**: 基于 highlight.js，速度快，兼容性好
  - **Phiki**: 基于 TextMate 语法，精度更高，支持嵌套语法，内联样式

- **丰富的主题**
  - highlight.php: 12+ 经典主题（GitHub, Monokai, Dracula 等）
  - Phiki: 60+ 现代主题（Catppuccin, Rose Pine, Tokyo Night 等）

- **开箱即用**
  - Phiki 引擎使用内联样式，无需引入 CSS 文件
  - 支持文章和评论代码高亮
  - 幂等性设计，重复处理不会出错

## 安装

1. 将 `PS-Highlight` 文件夹上传到 `/usr/plugins/` 目录
2. 进入 Typecho 后台，启用插件
3. 在插件设置中选择引擎和主题

## 配置

### 引擎选择

| 引擎 | 优点 | 缺点 | CSS 需求 |
|------|------|------|----------|
| **highlight.php** | 速度快，兼容性好 | 精度较低 | 需要引入 CSS |
| **Phiki** | 精度高，支持嵌套 | 相对较慢 | 内联样式 |

### highlight.php 主题

GitHub, GitHub Dark, Monokai, Dracula, Nord, Atom One Dark/Light, VS Code, Xcode, Solarized 等

**使用方法**: 在主题中引入对应的 highlight.js CSS 文件

```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
```

### Phiki 主题

60+ 内置主题，包括：

- GitHub 系列 (Light, Dark, Dimmed)
- Catppuccin 系列 (Frappe, Latte, Macchiato, Mocha)
- Gruvbox 系列 (Dark/Light, Hard/Medium/Soft)
- Rose Pine 系列 (Dawn, Moon)
- 以及 Monokai, Nord, Dracula, Tokyo Night, Vitesse 等

**无需引入 CSS**，直接选择即可使用。

## 评论高亮

如需在评论中使用代码高亮，需要允许 `class` 和 `style` 属性：

1. 进入 **设置 → 评论**
2. 将"评论允许的 HTML 标签"修改为：
   ```html
   <pre class="" style=""><code class="" style=""><span class="" style="">
   ```

## 引用库

### highlight.php

- **仓库**: https://github.com/scrivo/highlight.php
- **版本**: 9.18.1.10
- **许可**: BSD-3-Clause

### Phiki

- **仓库**: https://github.com/tapio475/phiki
- **许可**: MIT

## 许可证

MIT License

---

**作者**: pure
**版本**: 2.0.0
