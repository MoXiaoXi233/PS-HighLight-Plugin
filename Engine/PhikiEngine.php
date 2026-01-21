<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Phiki 引擎实现
 */
class HighlightPHP_Engine_PhikiEngine implements HighlightPHP_Engine_EngineInterface
{
    private static $instance = null;
    private $phiki = null;
    private $theme = 'github-light';

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
        if ($this->phiki === null) {
            // 从工厂配置读取主题
            require_once __DIR__ . '/EngineFactory.php';
            $config = HighlightPHP_Engine_EngineFactory::getConfig();
            $this->theme = $config['phiki_theme'] ?? 'github-light';

            // PSR-4 autoloading for Phiki
            spl_autoload_register(function ($class) {
                $prefix = 'Phiki\\';
                $base_dir = __DIR__ . '/../vendor/Phiki/src/';

                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }

                $relative_class = substr($class, $len);
                $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

                if (file_exists($file)) {
                    require $file;
                }
            });

            // 预加载关键文件
            require_once __DIR__ . '/../vendor/Phiki/src/Grammar/Grammar.php';
            require_once __DIR__ . '/../vendor/Phiki/src/Environment.php';
            require_once __DIR__ . '/../vendor/Phiki/src/Theme/Theme.php';
            require_once __DIR__ . '/../vendor/Phiki/src/Theme/ThemeRepository.php';

            $this->phiki = new \Phiki\Phiki();
        }
    }

    public function isHighlighted($classAttr)
    {
        return strpos($classAttr, 'phiki') !== false;
    }

    public function highlight($code, $language)
    {
        if (!$language) {
            $language = 'plaintext';
        }

        try {
            // Phiki 返回完整的 <pre><code>...</code></pre>
            return (string) $this->phiki->codeToHtml($code, $language, $this->theme);
        } catch (Exception $e) {
            // 如果高亮失败，返回转义的原始代码
            return '<pre><code>' . htmlspecialchars($code) . '</code></pre>';
        }
    }

    public function getCodeClass($language)
    {
        // Phiki 使用内联样式，不需要 class
        return '';
    }

    public function getEngineName()
    {
        return 'phiki';
    }
}
