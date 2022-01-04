<?php

namespace Usility\PageFactory;

use Usility\PageFactory\PageFactory as PageFactory;


class Macros
{
    protected $appRoot;
    protected $absAppRoot;
    protected $pagePath;
    protected $slug;
    protected $lang;
    protected $langCode;
    protected $debug;
    protected $kirby;
    protected $site;
    protected $page;
    protected $pages;
    protected $trans;
    public static $registeredMacros = [];


    public function __construct($pfy = null)
    {
        $this->pfy = $pfy;
        if ($pfy) {
            $this->appRoot = PageFactory::$appRoot;
            $this->absAppRoot = PageFactory::$absAppRoot;
            $this->pagePath = PageFactory::$pagePath;
            $this->slug = $pfy->slug;
            $this->lang = PageFactory::$lang;
            $this->langCode = PageFactory::$langCode;
            $this->debug = PageFactory::$debug;
            $this->kirby = $pfy->kirby;
            $this->site = $pfy->site;
            $this->page = $pfy->page;
            $this->pages = $pfy->pages;
        }

        // find macro folders in custom/, extensions and pagefactory:
        $macroLocations = [];
        $macroLocations[] = PFY_USER_CODE_PATH;
        if (PageFactory::$extensionsPath) {
            foreach (PageFactory::$extensionsPath as $extPath) {
                $folder = $extPath . 'macros/';
                if (file_exists($folder)) {
                    $macroLocations[] = $folder;
                }
            }
        }
        $macroLocations[] = PFY_MACROS_PATH;

        $this->availableMacros = [];
        foreach ($macroLocations as $location) {
            $dir = \Usility\PageFactory\getDir($location . '*');
            foreach ($dir as $item) {
                if (is_file($item)) {
                    $macroName = strtolower(basename($item, '.php'));
                    $this->availableMacros[ $macroName ] = $item;
                } elseif (is_dir($item)) {
                    $macroName = strtolower(basename(trim($item, '/')));
                    $item = "{$item}code/index.php";
                    if (file_exists($item)) {
                        $this->availableMacros[ $macroName ] = $item;
                    }
                }
            }
        }
    } // __construct


    /**
     * Executes the macro call, provided that a corresponding macro object could be found.
     * @param string $macroName Name of the macro
     * @param array $args       array of arguments
     * @param string $argStr    arguments in string form (in case showSource is active)
     * @return string|null
     */
    public function execute(string $macroName, array $args, string $argStr): ?string
    {
        $this->trans = PageFactory::$trans;
        $str = null;
        $macroObj = $this->loadMacro($macroName);
        if ($macroObj === null) {
            return null;
        }

        if ($macroObj) {
            if (@$args[0] === 'help') {
                $helpText = $this->renderHelpText($macroObj);
                $str = \Usility\PageFactory\compileMarkdown("\n$str\n\n\n$helpText");
                return $str;

            } else {
                $args = $this->fixArgs($args, $macroObj['parameters']);
            }
            if (!method_exists($macroObj['macroObj'], 'render')) {
                return null;
            }
            $str = $macroObj['macroObj']->render($args, $argStr);

            // mdCompile if requested:
            if (@$macroObj['assetsToLoad']) {
                $assetsToLoad = $macroObj['assetsToLoad'];
                if (is_string($assetsToLoad)) {
                    $assetsToLoad = [$assetsToLoad];
                }
                foreach ($assetsToLoad as $asset) {
                    if (strpos($asset, 'jquery') !== false) {
                        $this->pfy->jQueryActive = true;
                    }
                    $this->pfy->pg->addAssets($asset);
                }
            }

            // mdCompile if requested:
            if (@$macroObj['mdCompile'] || $macroObj['macroObj']->get('mdCompile')) {
                $str = \Usility\PageFactory\compileMarkdown($str);
            }

            // wrap in comment if requested:
            if (@$macroObj['wrapInComment'] || $macroObj['macroObj']->get('wrapInComment')) {
                $str = <<<EOT

<!-- $macroName() -->
$str
<!-- /$macroName() -->

EOT;
            }
            return $str;
        }
        return null;
    } // execute



    public function loadMacro($macroName)
    {
        $this->trans = PageFactory::$trans;
        $str = null;
        $helpText = false;
        $macroObj = null;
        $macroName = strtolower(str_replace('-', '', $macroName));
        $thisMacroName = 'Usility\\PageFactory\\' . ucfirst( $macroName );
        if (isset(self::$registeredMacros[ $macroName ])) {
            $macroObj = self::$registeredMacros[ $macroName ];

        } else {
            if (isset($this->availableMacros[ $macroName ])) {
                $macroObj = include $this->availableMacros[ $macroName ];
                self::$registeredMacros[ $macroName ] = $macroObj;

                // workaround: if intendet macro name collides with PHP keyword, define macro as "_Macroname" instead.
                //  -> example: list() => class _List() and file _List.php
            } elseif (isset($this->availableMacros[ "_$macroName" ])) {
                $macroName0 = $macroName;
                $macroName = "_$macroName";
                $thisMacroName = 'Usility\\PageFactory\\' . ucfirst( $macroName );
                $macroObj = include $this->availableMacros[ $macroName ];
                self::$registeredMacros[ $macroName0 ] = $macroObj;

            } elseif (!$this->trans->hideIfNotDefined) {
                $error = "Error: macro '$macroName()' not found.";
                if (PageFactory::$debug) {
                    throw new \Exception($error);

                } else {
                    mylog($error);
                }
                return null;
            }
        }
        return $macroObj;
    } // preloadMacro


    /**
     * Cycles through the args array: where key is missing, restores it based on the argument's position.
     * @param array $args
     * @param array $argDefs
     * @return array
     */
    public function fixArgs(array $args, array $argDefs): array
    {
        $argKeys = array_keys($argDefs);
        foreach ($args as $k => $v) {
            if (is_int($k)) {
                if (isset($argKeys[$k])) {
                    unset($args[$k]);
                    $args[$argKeys[$k]] = $v;
                }
            }
        }
        foreach ($argDefs as $key => $def) {
            if (!isset($args[$key])) {
                $args[$key] = @$def[1];
            }
        }
        return $args;
    } // fixArgs



    /**
     * Renders help-test for given macro: the summary combined with the parameter descriptions.
     * @param object $macroObj
     * @return string
     */
    private function renderHelpText(array $macroObj): string
    {
        $macroName = @$macroObj['name'];
        if ($macroName[0] === '_') {
            $macroName = substr($macroName, 1);
        }
        $summary = @$macroObj['summary'];
        $argDefs = @$macroObj['parameters'];
        $out = "# Macro ``$macroName()``\n$summary\n## Aruments:\n\n";
        foreach ($argDefs as $key => $rec) {
            $default = $this->valueToString($rec[1]);
            if (is_bool($default)) {
                $default = $default? 'true' : 'false';
            }
            if (!$default) {
                $default = 'false';
            }
            $out .= "$key:\n: {$rec[0]} (Default: $default)\n\n";
        }
        $out = compileMarkdown($out);
        $out = <<<EOT
<div class='lzy-macro-help lzy-encapsulated'>
$out
</div>
EOT;

        return $out;
    } // renderHelpText



    /**
     * Renders list of all available Macros including help texts.
     * @return string
     */
    public function listMacros($option = false): string
    {
        $str = '';
        $registeredMacros = self::$registeredMacros;
        $availableMacros = $this->availableMacros;
        foreach ($availableMacros as $k => $rec) {
            if ($k[0] === '_') {
                $availableMacros[substr($k,1)] = $rec;
                unset($availableMacros[$k]);
            }
        }
        ksort($availableMacros);
        foreach ($availableMacros as $macroName => $macroFile) {
            $thisMacroName = 'Usility\\PageFactory\\' . ucfirst( $macroName );
            if (@$registeredMacros[ $macroName ]) {
                $macroObj = $registeredMacros[ $macroName ];
            } elseif (@$registeredMacros[ substr($macroName,1) ]) {
                $macroObj = $registeredMacros[ substr($macroName,1) ];
            } else {
                $macroObj = include $macroFile;
                self::$registeredMacros[ $macroName ] = $macroObj;
            }
            if (stripos($option, 'short') !== false) {
                $str .= "- $macroName()\n";
            } else {
                $str .= $this->renderHelpText($macroObj);
            }
        }
        $str = compileMarkdown($str);
        $str = <<<EOT
    <div class="lzy-macro-list">
$str    </div>
EOT;
        return $str;
    } // listMacros



    /**
     * Renders an array in a short and readable form.
     * @param mixed $value
     * @return string
     */
    private function valueToString(mixed $value): string
    {
        if (is_array($value)) {
            $value = \Usility\PageFactory\var_r($value, '', true);
        } elseif (is_bool($value)) {
            $value = $value? 'true': 'false';
        }
        return $value;
    } // valueToString



    /**
     * Getter.
     * @param string $key
     * @return string
     */
    protected function get(string $key): mixed
    {
        return @$this->$key;
    } // get



    /**
     * Setter.
     * @param string $key
     * @param mixed $value
     */
    protected function set(string $key, mixed $value): void
    {
        $this->$key = $value;
    } // set

} // Macros
