<?php

namespace Usility\PageFactory\Macros;

use \Usility\PageFactory;
use Exception;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'path' => ['Selects the folder to be read', false],
        'pattern' => ['The search-pattern with which to look for files (-> \'glob style\'.)', false],
        'deep' => ['[false,true,flat] Whether to recursively descend into sub-folders. ("flat" means deep, but rendered as a non-hierarchical list.)', false],
        'showPath' => ['[false,true] Whether to render the entire path per file in deep:flat mode.', false],
        'order' => ['[reverse] Displays result in reversed order.', false],
        'id' => ['Id to be applied to the enclosing li-tag (Default: lzy-dir-#)', false],
        'class' => ['Class to be applied to the enclosing li-tag (Default: lzy-dir)', 'lzy-dir'],
        'target' => ['"target" attribute to be applied to the a-tag.', false],
        'exclude' => ['Pattern by which to exclude specific elements.', false],
        'maxAge' => ['[integer] Maximum age of file (in number of days).', false],
        'orderedList' => ['If true, renders found objects as an ordered list (&lt;ol>).', false],
        'hierarchical' => ['.', false],
    ],
    'summary' => <<<EOT
Renders the content of a directory.
EOT,
];



class Dir extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    public function render($args, $argStr)
    {
        $inx = self::$inx++;

        $this->path = $args['path'];
        $this->pattern = $args['pattern'];
        $this->deep = $args['deep'];
        $this->showPath = $args['showPath'];
        $this->order = $args['order'];
        $this->id = $args['id'];
        $this->class = $args['class'];
        $this->target = $args['target'];
        $this->exclude = $args['exclude'];
        $this->maxAge = $args['maxAge'];
        $this->orderedList = $args['orderedList'];
        if ($args['hierarchical']) {
            $this->deep = true;
        }

        if ((strpos(\Usility\PageFactory\base_name($this->path), '*') === false)) {
            if (!$this->pattern) {
                $this->pattern = "*";
            }
        } else {
            if (!$this->pattern) {
                $this->pattern = \Usility\PageFactory\base_name($this->path);
            }
            $this->path = dirname($this->path);
        }
        if (strpos($this->pattern, ',') !== false) {
            $this->pattern = \Usility\PageFactory\explodeTrim(',', $this->pattern);
        }
        if ($this->path) {
            $this->path = \Usility\PageFactory\fixPath($this->path);
            if ($this->path[0] !== '~') {
                $this->path = '~page/' . $this->path;
            }
        } else {
            $this->path = '~page/';
        }

        if ($this->target) {
            if ($this->target === 'newwin') {
                $this->targetAttr = " target='_blank'";
            } else {
                $this->targetAttr = " target='{$this->target}'";
            }
        } else {
            $this->targetAttr = '';
        }

        if ($this->id) {
            $this->id = " id='{$this->id}'";
        } elseif ($this->id === false) {
            $this->id = " id='lzy-dir-$inx'";
        }
        if ($this->class) {
            $this->class = " class='{$this->class}'";
        }
        $this->linkClass = '';
        if ($this->target) {
            $this->linkClass = " class='lzy-link lzy-newwin_link'";
        }

        $path = \Usility\PageFactory\resolvePath($this->path);
        if ($this->deep) {
            if ($this->deep === 'flat') {
                if (is_array($this->pattern)) {
                    $dir = [];
                    foreach ($this->pattern as $pattern) {
                        $dir = array_merge($dir, \Usility\PageFactory\getDirDeep($path . $pattern));
                    }
                } else {
                    $dir = \Usility\PageFactory\getDirDeep($path . $this->pattern);
                }
                sort($dir);
                $str = $this->straightList($dir);
            } else {
                $this->pregPattern = '|'.str_replace(['.', '*'], ['\\.', '.*'], $this->pattern).'|';
                $str = $this->_hierarchicalList($path);
            }
        } else {
            if (is_array($this->pattern)) {
                $this->pattern = '{'.implode(',', $this->pattern).'}';
            }
            $dir = \Usility\PageFactory\getDir($path.$this->pattern);
            $str = $this->straightList($dir);
        }

        return$str;
    } // render



    private function straightList($dir)
    {
        if (!$dir) {
            return "{{ nothing to display }}";
        }
        if (strpos($this->order, 'revers') !== false) {
            $dir = array_reverse($dir);
        }
        $str = '';
        $maxAge = 0;
        if ($this->maxAge) {
            $maxAge = time() - 86400 * $this->maxAge;
        }
        foreach ($dir as $file) {
            if (is_dir($file) || (filemtime($file) < $maxAge)) {
                continue;
            }
            if (!$this->showPath) {
                $name = \Usility\PageFactory\base_name($file);
            } else {
                $name = $file;
            }
            $url = $this->parseUrlFile($file);
            if ($url) { // it's a link file (.url or .webloc):
//ToDo:
                throw new Exception("not implemented yet: Dir() -> .lnk/.webloc files");
//                $name = base_name($file, false);
//                require_once SYSTEM_PATH.'link.class.php';
//                $lnk = new CreateLink($this->lzy);
//                $link = $lnk->render(['href' => $url, 'text' => $name, 'target' => $this->target]);
//                $str .= "\t\t<li class='lzy-dir-file'>$link</li>\n";

            } else {    // it's regular local file:
                $url = '~/'.$file;
                $str .= "\t\t<li class='lzy-dir-file'><a href='$url'{$this->targetAttr}{$this->linkClass}>$name</a></li>\n";
            }
        }
        $tag = $this->orderedList? 'ol': 'ul';
        $str = <<<EOT

    <$tag{$this->id}{$this->class}>
$str   
    </$tag>
EOT;
        return $str;
    } // straightList




    private function _hierarchicalList($path, $lvl = 0)
    {
        $maxAge = 0;
        if ($this->maxAge) {
            $maxAge = time() - 86400 * $this->maxAge;
        }

        $dir = \Usility\PageFactory\getDir("$path*");
        if (strpos($this->order, 'revers') !== false) {
            $dir = array_reverse($dir);
        }
        $str = '';
        $indent = str_pad('', $lvl, "\t");
        foreach ($dir as $file) {   // loop over items on this level:

            if (is_dir($file)) {        // it's a dir -> decend:
                $name = basename($file);
                $nextPath = \Usility\PageFactory\fixPath($file);
                $str1 = $this->_hierarchicalList($nextPath, $lvl+1);
                $str .= "\t\t$indent  <li class='lzy-dir-folder'><span>$name</span>\n$str1\n\t\t$indent  </li>\n";

            } else {                    // it's a file
                if (filemtime($file) < $maxAge) {   // check age, skip if too old
                    continue;
                }
                $name = \Usility\PageFactory\base_name($file);
                $ext = \Usility\PageFactory\fileExt($file);

                if ($this->pattern) {       // apply pattern:
                    if (!preg_match($this->pregPattern, $name)) {
                        continue;
                    }
                }

                if ($ext === 'url') {   // special case: file-ext 'url' -> render content as link
                    $href = file_get_contents($file);
                    $name = basename($file, '.url');

                } elseif ($ext === 'webloc') {   // special case: file-ext 'webloc' -> extract link
                    $href = str_replace("\n", ' ', file_get_contents($file));
                    if (preg_match('|<string>(https?://.*)</string>|', $href, $m)) {
                        $href = $m[1];
                    }
                    $name = basename($file, '.webloc');

                } else {                // regular file:
                    $href = '~/' . $path . basename($file);
                }
                $str .= "\t\t$indent  <li class='lzy-dir-file'><a href='$href'{$this->targetAttr}{$this->linkClass}>$name</a></li>\n";
            }
        }
        $tag = $this->orderedList? 'ol': 'ul';
        $str = <<<EOT

\t\t$indent<$tag{$this->class}>
$str   
\t\t$indent</$tag>

EOT;

        return $str;

    } // _hierarchicalList



    private function parseUrlFile($file)
    {
        if (!file_exists($file)) {
            return false;
        }
        $str = file_get_contents($file);
        if (preg_match('|url=(.*)|ixm', $str, $m)) {    // Windows link
            $url = trim($m[1]);
        } elseif (preg_match('|<string>(.*)</string>|ixm', $str, $m)) { // Mac link
            $url = $m[1];
        } else {
            $url = false;
        }
        return $url;
    } // parseUrlFile
} // Dir





// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);
return $macroConfig;
