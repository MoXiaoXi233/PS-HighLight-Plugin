<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * HighlightPHP - Typecho 服务器端代码高亮插件
 *
 * 支持两种高亮引擎：
 * - highlight.php: 兼容 highlight.js，使用 CSS 类名
 * - Phiki: 基于 TextMate 语法，使用内联样式
 *
 * @package HighlightPHP
 * @author pure
 * @version 2.0.0
 * @link https://github.com
 */
class HighlightPHP_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法
     */
    public static function activate()
    {
        // 文章内容高亮（文章页、独立页等）
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = __CLASS__ . '::highlight';
        // 评论内容高亮
        Typecho_Plugin::factory('Widget_Abstract_Comments')->contentEx = __CLASS__ . '::highlight';

        $message = _t('插件已激活，代码高亮功能已启用。');
        $message .= '<br><br><strong>当前引擎：</strong>' . self::getEngineName();
        $message .= '<br><br><strong>重要提示：</strong>评论高亮需要在后台设置允许 <code>class</code> 属性：';
        $message .= '<br>进入 <a href="' . Helper::options()->adminUrl . 'options-discussion.php">设置 → 评论</a>，';
        $message .= '将"评论允许的 HTML 标签"修改为：<code>&lt;pre class=""&gt;&lt;code class=""&gt;&lt;span class=""&gt;</code>';

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
     * 获取当前引擎名称
     */
    private static function getEngineName()
    {
        require_once __DIR__ . '/Engine/EngineFactory.php';
        $engineType = HighlightPHP_Engine_EngineFactory::getEngineType();
        return $engineType === 'phiki' ? 'Phiki (TextMate)' : 'highlight.php (hljs)';
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

        return HighlightPHP_Engine_EngineFactory::getEngine();
    }

    /**
     * 处理内容，高亮所有代码块
     */
    private static function processContent($content, $engine)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
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

            // 构建新的 pre 和 code 元素
            $newPre = $dom->createElement('pre');

            $newCode = $dom->createElement('code');
            $newCode->setAttribute('class', $engine->getCodeClass($language));

            // 将高亮后的 HTML 作为 code 的内容
            $codeFragment = $dom->createDocumentFragment();
            $codeFragment->appendXML($highlightedHtml);
            $newCode->appendChild($codeFragment);

            $newPre->appendChild($newCode);

            $pre->parentNode->replaceChild($newPre, $pre);

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
    private static function findCodeElement(DOMElement $pre)
    {
        foreach ($pre->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'code') {
                return $child;
            }
        }
        return null;
    }

    /**
     * 从 class 中提取语言
     */
    private static function extractLanguage(DOMElement $code)
    {
        $class = $code->getAttribute('class');

        // 匹配 language-xxx
        if (preg_match('/\blanguage-([a-z0-9_+#-]+)\b/i', $class, $m)) {
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
