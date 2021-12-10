<?php
namespace Usility\PageFactory;


class TransVars
{
    public static $transVars = [];
    private $macros = null;
    public $lang;
    public $langCode;
    private $extractedVars = [];

    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->lang = \Usility\PageFactory\PageFactory::$lang;
        $this->langCode = \Usility\PageFactory\PageFactory::$langCode;
        if (!$this->macros) {
            $this->macros = new Macros($pfy);
        }
        $this->loadTransVarsFromFile([ PFY_PLUGIN_PATH . 'config/transvars.yaml', PFY_DEFAULT_TRANSVARS]);
    } // __construct



    public function setVariables(array $array): void
    {
        foreach ($array as $key => $value) {
            $this->setVariable($key, $value);
        }
    }


    public function setVariable($varName, $value, $lang = false)
    {
        $lang = $this->extractLang($varName, $lang);

        if (is_array($value)) {
            self::$transVars[$varName] = array_merge(self::$transVars[$varName], $value);
        } elseif (is_string($value)) {
            unset(self::$transVars[$varName]);
            if (!$lang) {
                self::$transVars[$varName]['_'] = $value;
            } else {
                self::$transVars[$varName][$lang] = $value;
            }
        }
    } // setVariable



    public function addToVariable($varName, $value, $lang = false)
    {
        $lang = $this->extractLang($varName, $lang);

        if (is_array($value)) {
            self::$transVars[$varName] = array_merge(self::$transVars[$varName], $value);
        } elseif (is_string($value)) {
            if (!$lang) {
                self::$transVars[$varName]['_'] .= $value;
            } else {
                self::$transVars[$varName][$lang] .= $value;
            }
        }
    } // setVariable



    public function translate($str, $unShield = false)
    {
        $str = PageFactory::$trans->reInjectVars($str);
        list ($p1, $p2) = strPosMatching($str);
        while ($p1) {
            $s1 = substr($str, 0, $p1);
            $s2 = substr($str, $p1+2, $p2-$p1-2);
            $s2 = str_replace(["\n", "\t"], ['↵', '    '], $s2);
            $s3 = substr($str, $p2+2);

            if (preg_match('/^([#^]*) \s* ([\w.-]+) (.*)/x', $s2, $m)) {
                $modif = $m[1];
                $varName = $m[2];
                $argStr = $m[3];

                if (@$modif[0] === '#') {
                    $str = $s1.$s3;
                    continue;

                } elseif ($modif === '^') {
                    $this->hideIfNotDefined = true;
                }
                if (trim($argStr)) {
                    $argStr = rtrim($argStr);
                    $argStr = trim($argStr, '()↵');
                    $s2 = $this->translateMacro($varName, $argStr);
                } else {
                    $s2 = $this->translateVariable($varName);
                }

            } else {
                return $str;
            }
            $str = "$s1$s2$s3";
            list ($p1, $p2) = strPosMatching($str);
        }
        return $str;
    } // translate



    private function translateMacro($macroName, $argStr)
    {
        $macroName = strtolower(str_replace('-', '', $macroName));
        $str = '';
        $argStr0 = $argStr;
        $argStr = str_replace(['↵',"\t"], ["\n",'    '], $argStr);

        $showSource = false;
        if (strpos($argStr, 'showSource') !== false) {
            $argStr = preg_replace(['/(?<!\\\\)<strong>/', '/(?<!\\\\)<\/strong>/'], '', $argStr);
            $argStr = preg_replace(['/(?<!\\\\)<em>/', '/(?<!\\\\)<\/em>/'], '', $argStr);
            $showSource = true;
        }
        $args = parseArgumentStr($argStr, ',', ['!!', '%%']);

        if ($showSource) {
            $str = $this->renderMacroSource($macroName, $argStr0);
        }

        // is it a KerbyText call?
        if (strpos(',date,email,file,gist,image,link,tel,twitter,video,', $macroName) !== false) {
            $a = array_shift($args);
            foreach ($args as $k => $v) {
                $a .= " $k: $v";
            }
            $out = "($macroName: $a)";
            $str .= kirbytext($out);

        } else {
            $str .= $this->macros->execute($macroName, $args, $argStr);
            if ($str !== null) {
                return  $str;
            } elseif ($this->hideIfNotDefined) {
                return '';
            } else {
                return null;
            }
        }
        return $str;
    } // translateMacro



    private function translateVariable($varName, $lang = false)
    {
        $lang = $this->extractLang($varName, $lang);
        if (!$lang) {
            $lang = PageFactory::$lang;
            $langCode = PageFactory::$langCode;
        } else {
            $langCode = substr($lang, 0, 2);
        }
        $out = false;
        if (isset(self::$transVars[ $varName ])) {
            $out = self::$transVars[ $varName ];
            if (is_array($out)) {
                if (isset($out[$lang])) {
                    $out = $out[$lang];
                } elseif (isset($out[$langCode])) {
                    $out = $out[$langCode];
                } elseif (isset($out['_'])) {
                    $out = $out['_'];
                } else {
                    $out = false;
                }
            }
        }
        return $out;
    } // translateVariable



    public function extractVars($str)
    {
        $vars = &$this->extractedVars;
        list($p1, $p2) = strPosMatching($str);
        while ($p1) {
            $s1 = substr($str, 0, $p1);
            $s2 = substr($str, $p1+2, $p2-$p1-2);
            $s3 = substr($str, $p2+2);
            $s2 = str_replace(["\n", "\t"], ['↵', '    '], $s2);
            $inx = crc32($s2);
            $vars[$inx] = $s2;
            $str = "$s1{!%$inx%!}$s3";
            list($p1, $p2) = strPosMatching($str);
        }
        return $str;
    } // extractVars



    public function reInjectVars($str)
    {
        $vars = &$this->extractedVars;
        if (preg_match_all('/{!%(\d+)%!}/', $str, $m)) {
            foreach ($m[1] as $i => $s) {
                $inx = $m[1][$i];
                $var = "{{ {$vars[$inx]} }}";
                $str = str_replace($m[0][$i], $var, $str);
            }
        }
        return $str;
    } // reInjectVars


    public function flattenMacroCalls($mdStr)
    {
        list($p1, $p2) = strPosMatching($mdStr);
        while ($p1) {
            $s1 = substr($mdStr, 0, $p1);
            $s2 = substr($mdStr, $p1+2, $p2-$p1-2);
            $s3 = substr($mdStr, $p2+2);
            $s2 = str_replace("\n", '↵', $s2);
            $mdStr = "$s1{{{$s2}}}$s3";
            list($p1, $p2) = strPosMatching($mdStr, $p2 + 2, '{{', '}}');
        }
        return $mdStr;
    } // flattenMacroCalls



    public function getVariable($varName, $lang = false)
    {
        return $this->translateVariable($varName, $lang);
    } // getVariable



    private function loadTransVarsFromFile($file)
    {
        $data = loadFiles($file, true, true);
        self::$transVars = array_merge_recursive(self::$transVars, $data);
    } // loadTransVarsFromFile



    private function extractLang(&$varName, $lang = false)
    {
        $varName = trim($varName);
        if (preg_match('/^(.*)\.(\w\w\d?)$/', $varName, $m)) {
            $varName = $m[1];
            $lang = $m[2];
        }
        return $lang;
    } // extractLang



    private function renderMacroSource($macroName, $argStr)
    {
        $argStr = rtrim($argStr, "↵\t ");
        $argStr = str_replace('~', '&sim;', $argStr);
        if (preg_match('/(showSource:\s*([^↵,]*),?\s*)/ms', $argStr, $m)) {
            $argStr = str_replace($m[0], '', $argStr);
        }
        $end = ' }}';
        if (strpos($argStr, '↵') !== false) {
            $end = "\n}}";
        }
        $argStr = str_replace('↵', "\n", $argStr);
        $html = <<<EOT

    <div class="lzy-source-code lzy-encapsulated">
        <pre><code>
&#123;{ $macroName($argStr)$end
        </code></pre>
    </div>

EOT;
        return $html;
    } // renderMacroSource



    public function get($propertyName)
    {
        return @$this->$propertyName;
    }


    public function render()
    {
        $this->pfy->pg->addAssets('site/plugins/pagefactory/scss/transvar-list.scss');
        $out = "\t<dl class='lzy-transvar-list'>\n";
        $vars = self::$transVars;
        ksort($vars);
        $keys = array_keys(self::$transVars);
        natcasesort($keys);
        foreach ($keys as $varName) {
            $rec = self::$transVars[$varName];
            $out .= "\t\t<dt class='lzy-transvar-key'>$varName</dt>\n";

            if ($varName === 'content') {
                $out .= "\t\t\t<dd class='lzy-transvar-single-value'>(skipped)</dd>\n";
            } elseif (is_array($rec)) {
                foreach ($rec as $lang => $value) {
                    $value = htmlentities($value);
                    $out .= "\t\t\t<dd><span class='lzy-transvar-lang'>$lang</span><span>$value</span></dd>\n";
                }
            } else {
                $value = htmlentities($rec);
                $out .= "\t\t\t<dd><span class='lzy-transvar-lang'>_</span><span>$value</span></dd>\n";
            }
        }
        $out .= "\t</dl>\n";
        return $out;
    } // render

} // TransVars
