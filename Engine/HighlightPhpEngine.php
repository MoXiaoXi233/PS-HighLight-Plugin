<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * highlight.php 引擎实现
 */
class HighlightPHP_Engine_HighlightPhpEngine implements HighlightPHP_Engine_EngineInterface
{
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        if (self::$instance === null) {
            require_once __DIR__ . '/../vendor/Highlight/Autoloader.php';
            spl_autoload_register('\\Highlight\\Autoloader::load');
            \Highlight\Highlighter::registerAllLanguages();
        }
    }

    public function isHighlighted($classAttr)
    {
        return strpos($classAttr, 'hljs') !== false;
    }

    public function highlight($code, $language)
    {
        $highlighter = new \Highlight\Highlighter(false);
        $highlighter->setClassPrefix('hljs-');
        $highlighter->setTabReplace('    ');

        if ($language) {
            $result = $highlighter->highlight($language, $code);
        } else {
            $result = $highlighter->highlightAuto($code);
        }

        return $result->value;
    }

    public function getCodeClass($language)
    {
        $classes = 'hljs';
        if ($language) {
            $classes .= ' language-' . $language;
        }
        return $classes;
    }

    public function getEngineName()
    {
        return 'highlight.php';
    }
}
