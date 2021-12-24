<?php

namespace Usility\PageFactory;

define('NAV_ARROW', '<span>&#9727;</span>');

 // classes:
define('NAV_LIST_TAG',  'ol');          // the list tag to be used
define('NAV_LEVEL',     'lzy-lvl-');    // identifies the nesting level
define('NAV_CURRENT',   'lzy-curr');    // the currently opened page
define('NAV_ACTIVE',    'lzy-active');  // currently open page and all its ancestors

class DefaultNav
{
    public function __construct($pfy)
    {
        $this->pfy = $pfy;
        $this->page = $pfy->page;
        $this->site = $pfy->site;
        $this->arrow = NAV_ARROW;
        $this->hidden = 'false';
        $pfy->pg->addJqFiles('site/plugins/pagefactory/js/nav.jq');
    } // __construct



    /**
     * Renders the default nav menu
     * @return string
     */
    public function render($args, $inx): string
    {
        $wrapperClass = $args['wrapperClass'];
        $class = $args['class'];

        if (strpos($wrapperClass, 'lzy-nav-collapsed')) {
            $this->hidden = 'true';
        }

        $tree = $this->site->children();
        $out = $this->_render($tree);
        if (!$out) {
            return '';
        }

        $out = <<<EOT

    <div id='lzy-nav-$inx' class='lzy-nav-wrapper $wrapperClass'>
      <nav class='lzy-nav $class'>
$out
      </nav>
    </div><!-- /lzy-nav-wrapper -->
EOT;
        return $out;
    } // render



    /**
     * Recursively renders nav for a sub-tree
     * @param $subtree
     * @return string
     */
    private function _render($subtree): string
    {
        $out = '';
        foreach ($subtree->listed() as $pg) {
            $depth = $pg->depth();
            $indent = '  '.str_repeat('    ', $depth+1);
            $curr = $pg->isActive();
            $class = NAV_LEVEL . $depth;
            if ($curr) {
                $class .= ' ' . NAV_CURRENT;
            }
            $active = $pg->isAncestorOf($this->page);
            if ($active) {
                $class .= ' ' . NAV_ACTIVE;
            }
            $url = $pg->url();
            $title = $pg->title()->html();
            $hasChildren = !$pg->children()->listed()->isEmpty();
            if ($hasChildren) {
                $aElem = "<span class='lzy-nav-label'>$title</span><span class='lzy-nav-arrow' ".
                         "aria-hidden='{$this->hidden}'>{$this->arrow}</span>";
                $class .= ' lzy-has-children lzy-nav-has-content';
                $out .= "  $indent<li class='$class'><a href='$url'>$aElem</a>\n";
                $out .= "  $indent  <div class='lzy-nav-sub-wrapper' aria-hidden='{$this->hidden}'>\n";
                $out .= $this->_render($pg->children());
                $out .= "  $indent  </div>\n";
                $out .= "  $indent</li>\n";
            } else {
                $out .= "  $indent<li class='$class'><a href='$url'>$title</a></li>\n";
            }
        }
        if (!$out) {
            return '';
        }

        $out = "$indent<" . NAV_LIST_TAG . ">\n$out$indent</" . NAV_LIST_TAG . ">\n";
        return $out;
    } // _render

} // DefaultNav
