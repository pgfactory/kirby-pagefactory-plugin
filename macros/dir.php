<?php
namespace Usility\PageFactory;

function dir($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'path' => ['Selects the folder to be read. May include an optional '.
            'selection-pattern  (-> \'glob style\', e.g. "*.pdf" or "{&#92;&#42;.js,&#92;&#42;.css}")', false],
            'id' => ['Id to be applied to the enclosing li-tag (Default: pfy-dir-#)', false],
            'class' => ['Class to be applied to the enclosing li-tag (Default: pfy-dir)', 'pfy-dir'],
            'target' => ['"target" attribute to be applied to the a-tag.', false],
            'include' => ['[FILES,FOLDERS] Defines what to include in output', 'files'],
            'exclude' => ['Regex pattern by which to exclude specific elements.', false],
            'flags' => ['[REVERSE_ORDER, EXCLUDE_EXTENSION, INCLUDE_PATH, DEEP, HIERARCHICAL, ORDERED_LIST, DOWNLOAD, '.
                'AS_LINK] Activates miscellaneous modes.', false],
            'prefix' => ['If defined, string will be placed before each element.', false],
            'postfix' => ['If defined, string will be placed behind each element.', false],
            'linkPath' => ['(For internal use only)', false],
            'replaceOnElem' => ['(pattern,replace) If defined, regular expression is applied to each element. '.
                'Example: remove leading underscore:  "^_,&#39;&#39;"', false],
            'maxAge' => ['[integer] Maximum age of file (in number of days).', false],
        ],
        'summary' => <<<EOT
# dir()

Renders the content of a directory.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    // assemble output:
    $obj = new Dir();
    $str .= $obj->render($options);

    return $str;
}



class Dir
{
    public static $inx = 1;
    private $path;
    private $id;
    private $class;
    private $target;
    private $includeFiles;
    private $includeFolders;
    private $exclude;
    private $flags;
    private $maxAge;
    private $prefix;
    private $postfix;
    private $linkPath;
    private $replaceOnElem;
    private $order;
    private $deep;
    private $flat;
    private $showPath;
    private $excludeExt;
    private $tag;
    private $download;
    private $asLink;
    private $targetAttr;
    private $linkClass;

    public function render($args)
    {
        $inx = self::$inx++;

        $this->path = $args['path'];
        $this->id = $args['id'];
        $this->class = $args['class'];
        $this->target = $args['target'];
        $this->includeFiles = str_contains(strtolower($args['include']), 'files');
        $this->includeFolders = str_contains(strtolower($args['include']), 'folders');
        $this->exclude = $args['exclude'];
        $this->flags = strtoupper($args['flags']);
        $this->maxAge = $args['maxAge'];
        $this->prefix = $args['prefix'];
        $this->postfix = $args['postfix'];
        $this->linkPath = $args['linkPath'];
        $this->replaceOnElem = $args['replaceOnElem'];

        $this->order = str_contains($this->flags, 'REVERSE_ORDER');
        $this->deep = str_contains($this->flags, 'DEEP');
        $this->flat = true;
        if (str_contains($this->flags, 'HIERARCHICAL')) {
            $this->flat = false;
            $this->deep = true;
        }
        $this->showPath = str_contains($this->flags, 'INCLUDE_PATH');
        $this->excludeExt = str_contains($this->flags, 'EXCLUDE_EXTENSION');
        $this->tag = str_contains($this->flags, 'ORDERED_LIST') ? 'ol' : 'ul';
        $this->download = str_contains($this->flags, 'DOWNLOAD');
        $this->asLink = str_contains($this->flags, 'AS_LINK');

        if ($this->download) {
            $this->download = ' download';
            $this->asLink = true;
        }
        if ($filename = base_name($this->path)) {
            $pattern = $filename;
        } else {
            $pattern = '*';
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
            if ($this->flat) {
                $str = $this->straightList($dir);
            } else {
                $str = $this->hierarchicalList($path, $dir);
            }

        } else {
            $dir = getDir($path . $pattern);
            if ($this->exclude) {
                $dir = preg_grep("|$this->exclude|", $dir, PREG_GREP_INVERT);
            }
            $str = $this->straightList($dir);
        }
        return $str;
    } // render


    private function straightList($dir)
    {
        if (!$dir) {
            return TransVars::getVariable("nothing to display");
        }
        if (strpos($this->order, 'revers') !== false) {
            $dir = array_reverse($dir);
        }
        $str = '';
        $maxAge = 0;
        if ($this->maxAge) {
            $maxAge = time() - 86400 * $this->maxAge;
        }
        if ($this->replaceOnElem) {
            list($pattern, $replace) = explodeTrim(',', $this->replaceOnElem);
            $keys = array_map(function ($e) use ($pattern, $replace){
                return preg_replace("|$pattern|", $replace, basename($e));
            }, $dir);
        } else {
            $keys = array_map(function ($e) {
                return basename($e);
            }, $dir);
        }
        $dir = array_combine($keys, $dir);
        ksort($dir);
        foreach ($dir as $name => $file) {
            if (filemtime($file) < $maxAge) {
                continue;
            }
            if (is_dir($file)) {
                if (!$this->includeFolders) {
                    continue;
                }
            } elseif (!$this->includeFiles) {
                continue;
            }
            if ($this->showPath) {
                $name = $file;
            }
            if ($this->excludeExt) {
                $name = fileExt($name, true);
            }
            $filename = $name;
            $name = "$this->prefix$name$this->postfix";
            if ($this->asLink) {
                if ($this->linkPath) {
                    if (str_contains($this->linkPath, '%basename%')) {
                        $file = str_replace('%basename%', base_name($filename, false), $this->linkPath);
                    }
                    $file = strtolower($file);
                }
                $url = $this->parseUrlFile($file); // check whether it's a link file (.url or .webloc)
                if ($url) {
                    $str .= "<li class='pfy-dir-file'><a href='$url'{$this->targetAttr}{$this->linkClass}{$this->download}>$url</a></li>\n";
                } else {    // it's regular local file:
                    if (str_starts_with($file, '~')) {
                        $url = $file;
                    } else {
                        $url = '~/' . $file;
                    }
                    $str .= "<li class='pfy-dir-file'><a href='$url'{$this->targetAttr}{$this->linkClass}{$this->download}>$name</a></li>\n";
                }
            } else {
                $str .= "<li class='pfy-dir-file'>$name</li>\n";
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
        $out = "<{$this->tag}>\n";
        $sub = '';
        foreach ($hierarchy as $name => $rec) {
            if (is_array($rec)) {
                $sub .= $this->_hierarchicalList($rec, $level+1);
            } else {
                $out .= "<li>$rec</li>\n";
            }
        }
        $out .= $sub;
        $out .= "</{$this->tag}>\n";
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

