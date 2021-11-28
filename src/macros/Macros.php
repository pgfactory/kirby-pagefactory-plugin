<?php

namespace Usility\PageFactory\Macros;

use Usility\PageFactory\PageFactory as PageFactory;

class Macros
{
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
            $this->trans = PageFactory::$trans;
        } else {
            $this->appRoot = null;
            $this->absAppRoot = null;
            $this->pagePath = null;
            $this->slug = null;
            $this->lang = null;
            $this->langCode = null;
            $this->debug = null;
            $this->kirby = null;
            $this->site = null;
            $this->page = null;
            $this->pages = null;
            $this->trans = null;
        }
        $this->registeredMacros = [];
        $macroLocations = [
            PFY_USER_CODE_PATH,
            PFY_MACROS_PATH,
            PFY_MACROS_PLUGIN_PATH
        ];
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


    public function execute($macroName, $args, $argStr)
    {
        $html = null;
        $helpText = false;
        $macroObj = null;
        $thisMacroName = 'Usility\\PageFactory\\Macros\\' . ucfirst( $macroName );
        if (isset($this->registeredMacros[ $macroName ])) {
            $macroObj = $this->registeredMacros[ $macroName ]['macroObj'];

        } else {
            if (isset($this->availableMacros[ $macroName ])) {
                $macroObj = include $this->availableMacros[ $macroName ];
                $this->registeredMacros[ $macroName ]['macroObj'] = $macroObj;

            } else {
                return null;
            }
        }

        if ($macroObj) {
            if (@$args[0] === 'help') {
                $helpText = $this->renderHelpText($macroObj);
                $html = \Usility\PageFactory\compileMarkdown("\n$html\n\n\n$helpText");
                return $html;

            } else {
                $args = $this->fixArgs($args, $macroObj['parameters']);
            }
            if (!method_exists($macroObj['macroObj'], 'render')) {
                return null;
            }
            $html = $macroObj['macroObj']->render($args, $argStr);

            if (@$macroObj['mdCompile']) {
                $html = \Usility\PageFactory\compileMarkdown($html);
            }
            if (!isset($macroObj['wrapInComment']) || $macroObj['wrapInComment']) {
                $html = <<<EOT

<!-- $macroName() -->
$html
<!-- /$macroName() -->

EOT;
            }
            return $html;
        }
        return null;
    } // execute



    private function fixArgs($args, $argDefs)
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
                $args[$key] = $def[1];
            }
        }
        return $args;
    } // fixArgs



    private function renderHelpText($macroObj)
    {
        $macroName = @$macroObj['name'];
        $summary = @$macroObj['summary'];
        $argDefs = @$macroObj['parameters'];
        $out = "@@@ .lzy-macro-help.lzy-encapsulated\n# Macro ``$macroName()``\n$summary\n## Aruments:\n\n";
        foreach ($argDefs as $key => $rec) {
            $default = $this->valueToString($rec[1]);
            $out .= "$key:\n: {$rec[0]} (Default: $default)\n\n";
        }
        return $out."\n\n@@@\n";
    } // renderHelpText



    private function valueToString($value)
    {
        if (is_array($value)) {
            $value = \Usility\PageFactory\var_r($value, '', true);
        } elseif (is_bool($value)) {
            $value = $value? 'true': 'false';
        }
        return $value;
    }
} // Macros
