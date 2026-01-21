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

            // Load dependencies
            require_once __DIR__ . '/../vendor/Phiki/src/Grammar/Grammar.php';
            require_once __DIR__ . '/../vendor/Phiki/src/Environment.php';

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
            $html = (string) $this->phiki->codeToHtml($code, $language, $this->theme);

            // Phiki 返回完整的 <pre><code>...</code></pre>
            // 我们只需要 code 内部内容，所以需要提取
            if (preg_match('#<code[^>]*>(.*?)</code>#s', $html, $matches)) {
                return $matches[1];
            }
            return $html;
        } catch (Exception $e) {
            // 如果高亮失败，返回转义的原始代码
            return htmlspecialchars($code);
        }
    }

    public function getCodeClass($language)
    {
        $classes = 'phiki';
        if ($language) {
            $classes .= ' language-' . $language;
        }
        return $classes;
    }

    public function getEngineName()
    {
        return 'phiki';
    }
}
