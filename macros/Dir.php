<?php

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'path' => ['Selects the folder to be read', false],
        'pattern' => ['The search-pattern with which to look for files (-> \'glob style\'. e.g. "{&#92;&#42;.js,&#92;&#42;.css}")', '*'],
        'deep' => ['[false,true,hierarchical] Whether to recursively descend into sub-folders. ("flat" means deep, but rendered as a non-hierarchical list.)', false],
        'showPath' => ['[false,true] Whether to render the entire path per file in deep mode.', false],
        'order' => ['[reverse] Displays result in reversed order.', false],
        'id' => ['Id to be applied to the enclosing li-tag (Default: pfy-dir-#)', false],
        'class' => ['Class to be applied to the enclosing li-tag (Default: pfy-dir)', 'pfy-dir'],
        'target' => ['"target" attribute to be applied to the a-tag.', false],
        'exclude' => ['Pattern by which to exclude specific elements.', false],
        'maxAge' => ['[integer] Maximum age of file (in number of days).', false],
        'orderedList' => ['If true, renders found objects as an ordered list (&lt;ol>).', false],
        'download' => ['If true, renders download links.', false],
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
        $pattern = $args['pattern'];
        $this->deep = $args['deep'];
        $this->showPath = $args['showPath'];
        $this->order = $args['order'];
        $this->id = $args['id'];
        $this->class = $args['class'];
        $this->target = $args['target'];
        $this->exclude = $args['exclude'];
        $this->maxAge = $args['maxAge'];
        $this->download = $args['download'];

        $this->tag = $args['orderedList'] ? 'ol' : 'ul';

        if ($this->download) {
            $this->download = ' download';
        }
        $this->path = dir_name($this->path);
        if ($this->path) {
            $this->path = fixPath($this->path);
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
            $this->id = " id='pfy-dir-$inx'";
        }
        if ($this->class) {
            $this->class = " class='{$this->class}'";
        }
        $this->linkClass = '';
        if ($this->target) {
            $this->linkClass = " class='pfy-link pfy-newwin_link'";
        }

        $path = resolvePath($this->path);
        if ($this->deep) {
            $dir = getDirDeep($path . $pattern);
            sort($dir);
            if (($this->deep === true) || ($this->deep === 'flat')) {
                $str = $this->straightList($dir);
            } else {
                $str = $this->hierarchicalList($path, $dir);
            }
//            if ($this->deep === 'flat') {
//                $str = $this->straightList($dir);
//            } else {
//                $str = $this->hierarchicalList($path, $dir);
//            }

        } else {
            $dir = getDir($path . $pattern);
            $str = $this->straightList($dir);
        }
        return $str;
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
                $name = base_name($file);
            } else {
                $name = $file;
            }
            $url = $this->parseUrlFile($file); // check whether it's a link file (.url or .webloc)
            if ($url) {
                $str .= "\t\t<li class='pfy-dir-file'><a href='$url'{$this->targetAttr}{$this->linkClass}{$this->download}>$url</a></li>\n";
            } else {    // it's regular local file:
                $url = '~/' . $file;
                $str .= "\t\t<li class='pfy-dir-file'><a href='$url'{$this->targetAttr}{$this->linkClass}{$this->download}>$name</a></li>\n";
            }
        }
        $str = <<<EOT

    <{$this->tag}{$this->id}{$this->class}>
$str   
    </{$this->tag}>
EOT;
        return $str;
    } // straightList


    private function hierarchicalList($path, $dir)
    {
        $skipLen = strlen($path);
        $hierarchy = [];
        foreach ($dir as $elem) {
            $elem = substr($elem, $skipLen);
            $expr = '$hierarchy[\'' . str_replace('/', "']['", $elem) . "'] = '$elem';";
            eval($expr);
        }
        ksort($hierarchy);
        $out = $this->_hierarchicalList($hierarchy, 1);
        return $out;
    } // hierarchicalList

    private function _hierarchicalList($hierarchy, $level)
    {
        $indent = str_pad('', $level, "\t");
        $out = "$indent<{$this->tag}>\n";
        $sub = '';
        foreach ($hierarchy as $name => $rec) {
            if (is_array($rec)) {
                $sub .= $this->_hierarchicalList($rec, $level+1);
            } else {
                $out .= "$indent  <li>$rec</li>\n";
            }
        }
        $out .= $sub;
        $out .= "$indent</{$this->tag}>\n";
        return $out;
    } // _hierarchicalList



    private function parseUrlFile($file)
    {
        $ext = fileExt($file);
        if (!file_exists($file) || (strpos('webloc,lnk', $ext) === false)) {
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
