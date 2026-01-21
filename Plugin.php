<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * HighlightPHP - Typecho 服务器端代码高亮插件
 *
 * 使用 highlight.php 在服务器端对文章中的代码块进行语法高亮
 * 行为与前端 highlight.js 一致，输出 hljs 兼容的 class 结构
 *
 * @package HighlightPHP
 * @author pure
 * @version 1.0.0
 * @link https://github.com
 */
class HighlightPHP_Plugin implements Typecho_Plugin_Interface
{
    /**
     * Highlight.php 实例缓存
     * @var \Highlight\Highlighter|null
     */
    private static $highlighter = null;

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
        self::$highlighter = null;
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

        self::initHighlighter();
        return self::processContent($content);
    }

    /**
     * 初始化 Highlight.php
     */
    private static function initHighlighter()
    {
        if (self::$highlighter === null) {
            require_once __DIR__ . '/vendor/Highlight/Autoloader.php';
            spl_autoload_register('\\Highlight\\Autoloader::load');

            \Highlight\Highlighter::registerAllLanguages();
            self::$highlighter = new \Highlight\Highlighter(false);
            self::$highlighter->setClassPrefix('hljs-');
            self::$highlighter->setTabReplace('    ');
        }
    }

    /**
     * 处理内容，高亮所有代码块
     */
    private static function processContent($content)
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

            // 幂等性：已高亮则跳过
            if (strpos($code->getAttribute('class'), 'hljs') !== false) {
                continue;
            }

            // 提取语言和代码
            $language = self::extractLanguage($code);
            $codeText = $code->textContent;

            // 调用 highlight.php
            $result = self::doHighlight($codeText, $language);

            // 构建新的 pre 和 code 元素
            $newPre = $dom->createElement('pre');

            $newCode = $dom->createElement('code');
            $classes = 'hljs';
            if ($result->language) {
                $classes .= ' language-' . $result->language;
            }
            $newCode->setAttribute('class', $classes);

            // 将高亮后的 HTML 作为 code 的内容
            $codeFragment = $dom->createDocumentFragment();
            $codeFragment->appendXML($result->value);
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

        return null; // 让 highlight.php 自动检测
    }

    /**
     * 调用 highlight.php 高亮
     */
    private static function doHighlight($codeText, $language)
    {
        if ($language) {
            return self::$highlighter->highlight($language, $codeText);
        }
        return self::$highlighter->highlightAuto($codeText);
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
