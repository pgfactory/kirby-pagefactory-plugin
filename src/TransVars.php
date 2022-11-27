<?php
namespace Usility\PageFactory;


class TransVars
{
    public static $transVars = [];
    private static $filesLoaded = [];
    public static $noTranslate = false;
    private $macros = null;
    public $lang;
    public $langCode;

    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->lang = \Usility\PageFactory\PageFactory::$lang;
        $this->langCode = \Usility\PageFactory\PageFactory::$langCode;
        if (!$this->macros) {
            $this->macros = new Macros($pfy);
        }

        // load PageFactory's standard variables:
        $this->loadTransVarsFromFiles(PFY_DEFAULT_TRANSVARS);

        // load variables defined in extensons:
        if (PageFactory::$availableExtensions) {
            foreach (PageFactory::$availableExtensions as $extPath) {
                $folder = $extPath . 'variables/';
                if (file_exists($folder)) {
                    $this->loadTransVarsFromFiles($folder);
                }
            }
        }

        // load custom variables:
        if (file_exists(PFY_CUSTOM_PATH.'variables/')) {
            $this->loadTransVarsFromFiles(PFY_CUSTOM_PATH.'variables/');
        }

        $this->hideIfNotDefined = false;
    } // __construct


    /**
     * Accepts an array of variables, adds them to known variable definitions.
     * @param array $array
     * @return void
     */
    public function setVariables(array $array): void
    {
        foreach ($array as $key => $value) {
            $this->setVariable($key, $value);
        }
    }


    /**
     * Adds a variable to known variable definitions.
     * $value can be string or array of lang-specific variants
     * @param string $varName
     * @param $value
     * @param $lang
     * @return void
     */
    public function setVariable(string $varName, $value, $lang = false): void
    {
        $lang = $this->extractLang($varName, $lang);
        $transVars = &self::$transVars;

        if (is_array($value)) {
            $transVars = array_merge($transVars, $value);
        } elseif (is_string($value)) {
            unset(self::$transVars[$varName]);
            if (!$lang) {
                self::$transVars[$varName]['_'] = $value;
            } else {
                self::$transVars[$varName][$lang] = $value;
            }
        }
    } // setVariable


    /**
     * Appends to a variable
     * @param string $varName
     * @param $value
     * @param $lang
     * @return void
     */
    public function addToVariable(string $varName, $value, $lang = false): void
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


    /**
     * Translates a string, which can contain {{ variables }} and {{ macros() }}.
     * @param string $str
     * @param $depth
     * @return string
     * @throws \Exception
     */
    public function translate(string $str, $depth = 0): string
    {
        if ($depth > 20) {
            fatalError("TransVar: too many nesting levels.");
        }
        list ($p1, $p2) = strPosMatching($str);
        while ($p1 !== false) {
            $s1 = substr($str, 0, $p1);
            $s2 = substr($str, $p1+2, $p2-$p1-2);
            $s2 = str_replace(["\n", "\t"], ['↵', '    '], $s2);
            $s3 = substr($str, $p2+2);

            if (preg_match('/^([#^mM]*) \s* ([^(]+) (.*)/x', $s2, $m)) {
                $modif = $m[1];
                $varName = $m[2];
                $argStr = $m[3];

                if ((self::$noTranslate !== false) && ($depth > self::$noTranslate)) {
                    $s2 = "<span style='background:#fffbbb;'>&#123;&#123;$s2}}</span>";
                    $str = "$s1$s2$s3";
                    list ($p1, $p2) = strPosMatching($str);
                    continue;
                }

                if (($modif[0]??'') === '#') {
                    $str = $s1.$s3;
                    list ($p1, $p2) = strPosMatching($str);
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
                if ($s2 && is_string($s2)&& preg_match('/(?<!\\\\){{/', $s2)) {
                    $s2 = $this->translate($s2, $depth+1);
                }

                // modifier 'm' -> md-compile inline-level:
                if (($modif[0]??'') === 'm') {
                    $s2 = PageFactory::$md->parseParagraph($s2);

                // modifier 'M' -> md-compile block-level
                } elseif (($modif[0]??'') === 'M') {
                    $s2 = PageFactory::$md->parse($s2);
                }

            } else { // no more instances left, terminate now:
                return $str;
            }
            $str = "$s1$s2$s3";
            list ($p1, $p2) = strPosMatching($str);
        }
        return $str;
    } // translate


    /**
     * Translates a macro identified by its name. Uppercase letters and dashes are ignored.
     * @param string $macroName
     * @param string $argStr
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function translateMacro(string $macroName, string $argStr)
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
        if (strpos(',date,email,file,gist,tel,twitter,video,', $macroName) !== false) {
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


    /**
     * Translates a variable identified by its name. $varName may end in '.lang' (e.g. '.en') to select a specific lang.
     * @param string $varName
     * @param $lang
     * @return string
     */
    private function translateVariable(string $varName, $lang = false)
    {
        $varName = trim($varName);
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
        } elseif (!$this->hideIfNotDefined) {
            $out = $varName;
        }
        return $out;
    } // translateVariable


    /**
     * Flattens an argument string, i.e. basically replacing "\n" with '↵'
     * @param string $mdStr
     * @return string
     * @throws \Exception
     */
    public function flattenMacroCalls(string $mdStr): string
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


    /**
     * Loads variables from given files.
     * @param $files
     * @return void
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    public static function loadTransVarsFromFiles($files)
    {
        if (is_dir($files)) {
            $files = getDir($files.'*.yaml');
        } elseif (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (!isset(self::$filesLoaded[$file])) {
                $data = loadFile($file, true, true);
                self::$transVars = array_merge(self::$transVars, $data);
            }
            self::$filesLoaded[$file] = true;
        }
    } // loadTransVarsFromFiles


    /**
     * Looks for trailing '.lang' pattern and extracts it.
     * @param string $varName
     * @param $lang
     * @return string
     */
    private function extractLang(string &$varName, $lang = false): string
    {
        $varName = trim($varName);
        if (preg_match('/^(.*)\.(\w\w\d?)$/', $varName, $m)) {
            $varName = $m[1];
            $lang = $m[2];
        }
        return $lang;
    } // extractLang


    /**
     * Renders to original source text of the macro call (for documentation purposes)
     * @param string $macroName
     * @param $argStr
     * @return string
     */
    private function renderMacroSource(string $macroName, $argStr): string
    {
        $argStr = rtrim($argStr, "↵\t ");
        $argStr = str_replace('~', '&sim;', $argStr);
        if (preg_match('/(showSource:\s*([^↵,]*),?\s*)/ms', $argStr, $m)) {
            $argStr = str_replace($m[0], '', $argStr);
        }
        $end = ' }}';
        if (strpos($argStr, '↵') !== false) {
            $end = "\n}}";
            $argStr = "\n".$argStr;
        }
        $argStr = str_replace('↵', "\n", $argStr);
        $html = <<<EOT

    <div class="pfy-source-code pfy-encapsulated">
        <pre><code>&#123;{ $macroName($argStr)$end</code></pre>
    </div>

EOT;
        return $html;
    } // renderMacroSource


    /**
     * Renders all known variables.
     * @return string
     */
    public function render(): string
    {
        PageFactory::$assets->addAssets('site/plugins/pagefactory/scss/transvar-list.scss');
        $out = "\t<dl class='pfy-transvar-list'>\n";
        $vars = self::$transVars;
        ksort($vars);
        $keys = array_keys(self::$transVars);
        natcasesort($keys);
        foreach ($keys as $varName) {
            $rec = self::$transVars[$varName];
            $out .= "\t\t<dt class='pfy-transvar-key'>$varName</dt>\n";

            if ($varName === 'content') {
                $out .= "\t\t\t<dd class='pfy-transvar-single-value'>(skipped)</dd>\n";
            } elseif (is_array($rec)) {
                foreach ($rec as $lang => $value) {
                    $value = htmlentities($value);
                    $out .= "\t\t\t<dd><span class='pfy-transvar-lang'>$lang</span><span>$value</span></dd>\n";
                }
            } else {
                $value = htmlentities($rec);
                $out .= "\t\t\t<dd><span class='pfy-transvar-lang'>_</span><span>$value</span></dd>\n";
            }
        }
        $out .= "\t</dl>\n";
        return $out;
    } // render


    /**
     * Looks up a variable and returns its content.
     * @param string $varName
     * @param $lang
     * @return string
     */
    public function getVariable(string $varName, $lang = false): string
    {
        return $this->translateVariable($varName, $lang);
    } // getVariable


    /**
     * Getter
     * @param $propertyName
     * @return mixed
     */
    public function get($propertyName)
    {
        return $this->$propertyName??'';
    } // get

} // TransVars
