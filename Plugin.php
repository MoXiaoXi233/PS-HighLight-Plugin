<?php

namespace TypechoPlugin\PS_Highlight;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Select;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho 服务器端代码高亮插件，采用后端渲染的方案，前端友好
 *
 * @package PS_Highlight
 * @author MoXiify
 * @version 1.0.0
 * @link https://www.moxiify.cn
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法
     */
    public static function activate()
    {
        // 文章内容高亮（文章页、独立页等）
        \Typecho\Plugin::factory('Widget_Abstract_Contents')->contentEx = __CLASS__ . '::highlight';
        // 评论内容高亮
        \Typecho\Plugin::factory('Widget_Abstract_Comments')->contentEx = __CLASS__ . '::highlight';

        $message = '插件已激活，代码高亮功能已启用。';
        $message .= '<br><br><strong>默认引擎：</strong>highlight.php (hljs)';
        $message .= '<br>可在插件设置中切换到 Phiki 引擎';
        $message .= '<br><br><strong>重要提示：</strong>评论高亮需要在后台设置允许 <code>class</code> 和 <code>style</code> 属性：';
        $message .= '<br>进入 <a href="' . Options::alloc()->adminUrl . 'options-discussion.php">设置 → 评论</a>，';
        $message .= '将"评论允许的 HTML 标签"修改为：<code>&lt;pre class="" style=""&gt;&lt;code class="" style=""&gt;&lt;span class="" style=""&gt;</code>';

        return $message;
    }

    /**
     * 禁用插件方法
     */
    public static function deactivate()
    {
        // 引擎会自动清理
    }

    /**
     * 获取插件配置面板
     * @param Form $form
     */
    public static function config(Form $form)
    {
        // 引擎选择
        $engine = new Select(
            'engine',
            [
                'highlight.php' => 'highlight.php (兼容 highlight.js CSS)',
                'phiki' => 'Phiki (TextMate 语法，更高精度)'
            ],
            'highlight.php',
            '高亮引擎',
            'highlight.php: 速度快，兼容 highlight.js 主题<br>Phiki: 精度更高，支持嵌套语法，内联样式'
        );
        $form->addInput($engine);

        // highlight.php 主题选择
        $hljsThemes = [
            'github' => 'GitHub',
            'github-dark' => 'GitHub Dark',
            'monokai' => 'Monokai',
            'dracula' => 'Dracula',
            'nord' => 'Nord',
            'atom-one-dark' => 'Atom One Dark',
            'atom-one-light' => 'Atom One Light',
            'vs' => 'VS Code',
            'vs2015' => 'VS Code 2015',
            'xcode' => 'Xcode',
            'solarized-light' => 'Solarized Light',
            'solarized-dark' => 'Solarized Dark'
        ];

        $hljsTheme = new Select(
            'hljsTheme',
            $hljsThemes,
            'github',
            'highlight.php 主题',
            '仅在使用 highlight.php 引擎时生效，需要在主题中引入对应的 CSS 文件'
        );
        $form->addInput($hljsTheme);

        // Phiki 主题选择
        $phikiThemes = self::getPhikiThemes();
        $phikiTheme = new Select(
            'phikiTheme',
            $phikiThemes,
            'github-light',
            'Phiki 主题',
            '仅在使用 Phiki 引擎时生效'
        );
        $form->addInput($phikiTheme);
    }

    /**
     * 获取所有可用的 Phiki 主题
     */
    private static function getPhikiThemes()
    {
        $themes = [];
        $themeDir = __DIR__ . '/vendor/Phiki/resources/themes/';

        if (is_dir($themeDir)) {
            foreach (glob($themeDir . '*.json') as $file) {
                $name = basename($file, '.json');
                // 转换为可读标题
                $label = str_replace(['-', '_'], ' ', $name);
                $label = ucwords($label);
                $themes[$name] = $label;
            }
        }

        // 按名称排序
        asort($themes);
        return $themes;
    }

    /**
     * 个人用户的配置面板
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
        // 当前不需要个人配置
    }

    /**
     * 代码高亮处理入口
     */
    public static function highlight($text, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $text : $lastResult;

        // 快速失败：无 pre 标签
        if (strpos($content, '<pre') === false) {
            return $content;
        }

        // 加载引擎
        $engine = self::getEngine();
        return self::processContent($content, $engine);
    }

    /**
     * 获取引擎实例
     */
    private static function getEngine()
    {
        require_once __DIR__ . '/Engine/EngineFactory.php';
        require_once __DIR__ . '/Engine/EngineInterface.php';
        require_once __DIR__ . '/Engine/HighlightPhpEngine.php';
        require_once __DIR__ . '/Engine/PhikiEngine.php';

        // 读取插件配置
        $options = Options::alloc();
        $pluginConfig = $options->plugin('PS_Highlight');

        $config = [
            'engine' => isset($pluginConfig->engine) ? $pluginConfig->engine : 'highlight.php',
            'phiki_theme' => isset($pluginConfig->phikiTheme) ? $pluginConfig->phikiTheme : 'github-light',
        ];

        Engine\EngineFactory::setConfig($config);
        $engine = Engine\EngineFactory::getEngine();

        return $engine;
    }

    /**
     * 处理内容，高亮所有代码块
     */
    private static function processContent($content, $engine)
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $pres = $dom->getElementsByTagName('pre');
        $modified = false;

        foreach ($pres as $pre) {
            $code = self::findCodeElement($pre);
            if ($code === null) continue;

            // 幂等性：已高亮则跳过（检查任一引擎的标记）
            $classAttr = $code->getAttribute('class');
            if ($engine->isHighlighted($classAttr) ||
                strpos($classAttr, 'hljs') !== false ||
                strpos($classAttr, 'phiki') !== false) {
                continue;
            }

            // 提取语言和代码
            $language = self::extractLanguage($code);
            $codeText = $code->textContent;

            // 调用引擎高亮
            $highlightedHtml = $engine->highlight($codeText, $language);

            // 检查是否是 Phiki 引擎（返回完整的 <pre> 结构）
            if (strpos($highlightedHtml, '<pre') === 0) {
                // Phiki 返回完整结构，直接替换
                $preFragment = $dom->createDocumentFragment();
                $preFragment->appendXML($highlightedHtml);
                $pre->parentNode->replaceChild($preFragment->firstChild, $pre);
            } else {
                // highlight.php 只返回 code 内容，需要构建结构
                $newPre = $dom->createElement('pre');
                $newCode = $dom->createElement('code');
                $newCode->setAttribute('class', $engine->getCodeClass($language));

                $codeFragment = $dom->createDocumentFragment();
                $codeFragment->appendXML($highlightedHtml);
                $newCode->appendChild($codeFragment);
                $newPre->appendChild($newCode);

                $pre->parentNode->replaceChild($newPre, $pre);
            }

            $modified = true;
        }

        if (!$modified) {
            return $content;
        }

        // 保存并清理 HTML
        $html = $dom->saveHTML();
        return self::cleanHtml($html);
    }

    /**
     * 查找 pre 下的 code 元素
     */
    private static function findCodeElement(\DOMElement $pre)
    {
        foreach ($pre->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->tagName === 'code') {
                return $child;
            }
        }
        return null;
    }

    /**
     * 从 class 中提取语言
     */
    private static function extractLanguage(\DOMElement $code)
    {
        $class = $code->getAttribute('class');

        // 匹配 language-xxx 或 lang-xxx
        if (preg_match('/\b(?:language|lang)-([a-z0-9_+#-]+)\b/i', $class, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 清理 DOMDocument 添加的多余内容
     */
    private static function cleanHtml($html)
    {
        // 移除 XML 声明（使用 str_replace 更可靠）
        $html = str_replace('<?xml encoding="UTF-8">', '', $html);
        $html = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $html);

        // 移除可能的 DOCTYPE
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);

        // 移除 html 和 body 标签
        $html = preg_replace('/<\/?html[^>]*>/i', '', $html);
        $html = preg_replace('/<\/?body[^>]*>/i', '', $html);

        // 移除 head 标签（如果存在）
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html);

        return trim($html);
    }
}
