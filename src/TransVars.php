<?php
namespace Usility\PageFactory;

use Usility\PageFactory\Macros\Macros as Macros;


class TransVars
{
    public static $transVars = [];
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
        $this->loadTransVarsFromFile([ PFY_PLUGIN_PATH . 'config/transvars.yaml', PFY_DEFAULT_TRANSVARS]);
    } // __construct



    public function addVariable($varName, $value, $lang = false)
    {
        $lang = $this->extractLang($varName, $lang);

        if (is_array($value)) {
            self::$transVars[$varName] = array_merge(self::$transVars[$varName], $value);
        } elseif (is_string($value)) {
            if (!$lang) {
                self::$transVars[$varName]['_'] = $value;
            } else {
                self::$transVars[$varName][$lang] = $value;
            }
        }
    } // addVariable



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
    } // addVariable



    public function translate($html, $unShield = false)
    {
        if (preg_match_all('/ (?<!\\\) {{ (.*?) }}/xms', $html, $m)) {
            foreach ($m[1] as $i => $value) {
                $this->hideIfNotDefined = false;
                $varName = trim($m[1][$i]);

                // note: modifier '@' is handled by PageFactory->assembleHtml()

                if ($varName[0] === '#') {
                    $html = str_replace($m[0][$i], '', $html);
                    continue;

                } elseif ($varName[0] === '^') {
                    $varName = trim(substr($varName,1));
                    $this->hideIfNotDefined = true;
                }

                // translate Macro:
                if (preg_match('/^(\w+) \((.*) \) \s* $/xms', $varName, $mm)) {
                    $macroRes = $this->translateMacro($mm[1], $mm[2]);
                    $html = str_replace($m[0][$i], $macroRes, $html);

                // translate Variable:
                } else {
                    $val = $this->translateVariable($varName);
                    if ($val !== false) {
                        $html = str_replace($m[0][$i], $val, $html);

                    // if not found among variables, maybe it was an macro without arguments:
                    } elseif ($val = $this->translateMacro($varName, '' )) {
                        $html = str_replace($m[0][$i], $val, $html);

                    } elseif ($this->hideIfNotDefined) {
                        $html = str_replace($m[0][$i], '', $html);
                    } else {
                        $html = str_replace($m[0][$i], $varName, $html);
                    }
                }
            }
        } elseif (preg_match_all('/ \((\w+): (.*?) \)/xms', $html, $m)) {
            foreach ($m[1] as $i => $ktName) {
                $argStr = $m[2][$i];
                if (preg_match('/(.*?) \[ (.*?) ] (.*)/x', $argStr, $mmm)) {
                    $s1 = $mmm[1];
                    $size = explode('x',$mmm[2]);
                    $width = intval(@$size[0]);
                    if (!$width) { $width = null; }
                    $height = intval(@$size[1]);
                    if (!$height) { $height = null; }
                    $s3 = $mmm[3];
                    $argStr = trim("$s1$s3");
                    $file = preg_replace('/(\s+.*)/', '', $argStr);
                    $imgFile = image($file);
                    if ($imgFile && ($width || $height)) {
                        $imgHtml = (string) $imgFile->resize($width, $height);
                    } else {
                        $imgHtml = (string)$imgFile;
                    }
              }
                $html = str_replace($m[0][$i], $imgHtml, $html);
            }
        }


        if ($unShield) {
            $html = str_replace('\\{', '{', $html);
        }
        return $html;
    } // translate



    private function translateMacro($macroName, $argStr)
    {
        $html = '';
        $argStr0 = $argStr;
//        $pfy = $this->pfy;
        if (strpos($argStr, 'showSource') !== false) {
            $argStr = preg_replace(['/(?<!\\\\)<strong>/', '/(?<!\\\\)<\/strong>/'], '', $argStr);
            $argStr = preg_replace(['/(?<!\\\\)<em>/', '/(?<!\\\\)<\/em>/'], '', $argStr);
        }
        $args = parseArgumentStr(trim($argStr, "↵\t "));

        if (@$args['showSource']) {
            $html = $this->renderMacroSource($macroName, $argStr0);
        }

        // is it a KerbyText call?
        if (strpos(',date,email,file,gist,image,link,tel,twitter,video,', $macroName) !== false) {
            $a = array_shift($args);
            foreach ($args as $k => $v) {
                $a .= " $k: $v";
            }
            $out = "($macroName: $a)";
            $html .= kirbytext($out);

        } else {
            $html .= $this->macros->execute($macroName, $args, $argStr);
            if ($html !== null) {
                return  $html;
            } elseif ($this->hideIfNotDefined) {
                return '';
            } else {
                return null;
            }
        }
        return $html;
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



    public function flattenMacroCalls($mdStr)
    {
        // within macro arguments, replace all \n with ↵:
        if (preg_match_all('/{{ (.*?) }}/xms', $mdStr, $m)) {
            foreach ($m[1] as $i => $item) {
                if (preg_match('/^ ([#^]? \s* [\w-]+) \((.*) \) \s* $/xms', $item, $mm)) {
                    $macroName = str_replace('-', '', $mm[1]);
                    $argStr = str_replace("\n", "↵", $mm[2]);
                    $mdStr = str_replace($m[0][$i], '{{'."$macroName($argStr) }}", $mdStr);
                }
            }
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
        self::$transVars = array_merge(self::$transVars, $data);
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

} // TransVars
