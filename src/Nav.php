<?php

//namespace Usility\PageFactory\Nav;

//use Kirby\Cms\App as Kirby;
namespace Usility\PageFactory;

define('NAV_ARROW', '<span>&#9727;</span>'); // '&#9657;'); // '&#9013;'; // '&#9657;'; //'&#9656;';
//define('NAV_ARROW', '&#9727;'); // '&#9657;'); // '&#9013;'; // '&#9657;'; //'&#9656;';
//define('NAV_ARROW', '&#9657;'); // '&#9657;'); // '&#9013;'; // '&#9657;'; //'&#9656;';
//define('NAV_ARROW', '<span class=\'lzy-icon lzy-icon-triangle\'></span>'); // '&#9657;'); // '&#9013;'; // '&#9657;'; //'&#9656;';

class Nav
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->page = $pfy->page;
        $this->site = $pfy->site;
        $this->arrow = NAV_ARROW;
        $pfy->jsFiles[] = 'site/plugins/pagefactory/js/nav.js';
        $pfy->js .= "var screenSizeBreakpoint = 480;\n";
    } // __construct



    public function render()
    {
        $elem = $this->site->children();
        $out = $this->_render($elem);

        $out = <<<EOT
<div id='lzy-primary-nav' class='lzy-nav-wrapper lzy-primary-nav'>
	  <nav class='lzy-nav lzy-nav-colored lzy-nav-top-horizontal lzy-nav-indented lzy-nav-accordion lzy-nav-collapsed lzy-nav-animated lzy-nav-hoveropen lzy-encapsulated lzy-nav-small-tree'>
$out
    </nav>
</div>
EOT;

//    $nav = $this->site->children()->listed();
//    $out = '';
//    foreach ($nav as $elem) {
//      $x = $elem->toStructure();
//      $url = $elem->url();
//      $name = $elem->title()->html();
//      $liClass = 'lzy-lvl1';
////      $liClass = 'lzy-lvl1 lzy-curr lzy-active';
//      $out .= "\t<li class='$liClass'>><a href='$url'>$name</a></li>\n";
//    }
//
//    $out = <<<EOT
//<div id='lzy-primary-nav' class='lzy-nav-wrapper lzy-primary-nav'>
//	  <nav class='lzy-nav lzy-nav-colored lzy-nav-top-horizontal lzy-nav-indented lzy-nav-accordion lzy-nav-collapsed lzy-nav-animated lzy-nav-hoveropen lzy-encapsulated lzy-nav-small-tree'>
//		<ol>
//$out
//    </ol>
//    </nav>
//</div>
//EOT;
        return $out;
    } // render



    private function _render($subtree)
    {
//        $depth = $subtree->depth();
//        if ($depth > 1) {
//            $out = "\t<ol style='margin-top: -10000px'>\n";
//        }
        $out = "\t<ol>\n";
        foreach ($subtree->listed() as $p) {
            $depth = $p->depth();
            $indent = str_repeat('    ', $depth);
            $curr = $p->isActive();
            $class = "lzy-lvl$depth";
            if ($curr) {
                $class .= " lzy-curr";
            }
            $active = $p->isAncestorOf($this->page);
            if ($active) {
                $class .= " lzy-active";
            }
            $url = $p->url();
            $title = $p->title()->html();
            $hasChildren = !$p->children()->listed()->isEmpty();
            if ($hasChildren) {
//            if ($p->hasChildren()) {
                $aElem = "<span class='lzy-nav-label'>$title</span><span class='lzy-nav-arrow' aria-hidden='true'>{$this->arrow}</span>";
                $class .= ' lzy-has-children lzy-nav-has-content';
                $out .= "\t$indent<li class='$class'><a href='$url'>$aElem</a>\n";
//                $out .= "\t$indent<li class='$class'><a href='$url'>$title</a>\n";
                $out .= "\t$indent  <div class='lzy-nav-sub-wrapper' aria-hidden='true'>\n";
                $out .= $this->_render($p->children());
                $out .= "\t$indent  </div>\n";
                $out .= "\t$indent</li>\n";
            } else {
                $out .= "\t$indent<li class='$class'><a href='$url'>$title</a></li>\n";
            }
        }
        $out .= "\t</ol>\n";
        return $out;
    }

} // Nav
