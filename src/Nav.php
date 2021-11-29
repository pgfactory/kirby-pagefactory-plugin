<?php

namespace Usility\PageFactory;

define('NAV_ARROW', '<span>&#9727;</span>');


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
        if (!$out) {
            return '';
        }

        $out = <<<EOT
<div id='lzy-primary-nav' class='lzy-nav-wrapper lzy-primary-nav'>
	  <nav class='lzy-nav lzy-nav-colored lzy-nav-top-horizontal lzy-nav-indented lzy-nav-accordion lzy-nav-collapsed lzy-nav-animated lzy-nav-hoveropen lzy-encapsulated lzy-nav-small-tree'>
$out
    </nav>
</div>
EOT;
        return $out;
    } // render



    private function _render($subtree)
    {
        $out = '';
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
                $aElem = "<span class='lzy-nav-label'>$title</span><span class='lzy-nav-arrow' aria-hidden='true'>{$this->arrow}</span>";
                $class .= ' lzy-has-children lzy-nav-has-content';
                $out .= "\t$indent<li class='$class'><a href='$url'>$aElem</a>\n";
                $out .= "\t$indent  <div class='lzy-nav-sub-wrapper' aria-hidden='true'>\n";
                $out .= $this->_render($p->children());
                $out .= "\t$indent  </div>\n";
                $out .= "\t$indent</li>\n";
            } else {
                $out .= "\t$indent<li class='$class'><a href='$url'>$title</a></li>\n";
            }
        }
        if (!$out) {
            return '';
        }
        $out = "\t<ol>\n".$out;
        $out .= "\t</ol>\n";
        return $out;
    }

} // Nav
