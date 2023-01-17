<?php

namespace Usility\PageFactory;

define('NAV_ARROW', '<span><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M15 12L9 6V18L15 12Z" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"/>
</svg></span>');

 // classes:
define('NAV_LIST_TAG',  'ol');          // the list tag to be used
define('NAV_LEVEL',     'pfy-lvl-');    // identifies the nesting level
define('NAV_CURRENT',   'pfy-curr');    // the currently opened page
define('NAV_ACTIVE',    'pfy-active');  // currently open page and all its ancestors

class SiteNav
{
    public static $inx = 1;
    private bool $deep = true;
//    private bool $deep = false;
    private $args;

    public function __construct()
    {
//        $this->pfy = $pfy;
        $this->page = page();
        $this->site = site();
        $this->arrow = NAV_ARROW;
        $this->hidden = 'false';
        PageFactory::$assets->addJqFiles('site/plugins/pagefactory/assets/js/nav.jq');
    } // __construct



    /**
     * Renders the default nav menu
     * @return string
     */
    public function render($args): string
    {
        $inx = self::$inx++;
        $this->args = &$args;
        $wrapperClass = &$args['wrapperClass'];
        $class = $args['class'];

       if ($args['type'] === 'top') {
           $args['wrapperClass'] .= ' pfy-nav-top-horizontal pfy-nav-indented pfy-nav-collapsed pfy-nav-animated pfy-nav-hoveropen pfy-encapsulated pfy-nav-small-tree';
//           $this->deep = true;
       }
//ToDo: when $this->deep = false; ??
        if (str_contains($wrapperClass, 'pfy-nav-collapsed')) {
            $this->hidden = 'true';
        }
        $out = '';
        // type=branch:
        if ($this->args['type'] === 'branch') {
            // find top-level parent:
            $page = page();
            while ($page->parent()) {
                $page = $page->parent();
            }
            if (!$page->hasListedChildren()) {
                return '';
            }
            // get children of top-level parent:
            $subtree = $page->children();
            $out = $this->_render($subtree);

        // default type
        } elseif ($this->site->hasListedChildren()) {
            $tree = $this->site->children()->listed();
            // render nav if homepage has siblings or children:
            if ((sizeof($tree->data()) > 1) || (sizeof($tree->children()->listed()->data()) > 0)) {
                $out = $this->_render($tree);
            }
        }

        if (!$out) {
            return '';
        }

        $out = <<<EOT

<div id='pfy-nav-$inx' class='pfy-nav-wrapper $wrapperClass'>
  <nav class='pfy-nav $class'>
$out
  </nav>
</div><!-- /pfy-nav-wrapper -->
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
            $indent = '';
            $curr = $pg->isActive();
            $class = NAV_LEVEL . $depth;
            if ($curr) {
                $class .= ' ' . NAV_CURRENT;
            }
            $active = $pg->isAncestorOf($this->page);
            if ($active || $curr) {
                $class .= ' ' . NAV_ACTIVE;
            }
            $url = $pg->url();
            $title = $pg->title()->html();
            $hasChildren = !$pg->children()->listed()->isEmpty();

            // if folder contains no md-file, fall through to first child page:
            if ($hasChildren && !$this->hasMdContent($pg)) {
                if ($pg1 = $pg->children()->listed()->first()) {
                    $url = $pg1->url();
                }
            }

            if ($this->deep && $hasChildren) {
                $aElem = "<span class='pfy-nav-label'>$title</span><span class='pfy-nav-arrow' ".
                         "aria-hidden='{$this->hidden}'>{$this->arrow}</span>";
                $class .= ' pfy-has-children pfy-nav-has-content';
                $out .= "$indent<li class='$class'><a href='$url'>$aElem</a>\n";
                $out .= "$indent  <div class='pfy-nav-sub-wrapper' aria-hidden='{$this->hidden}'>\n";
                $out .= $this->_render($pg->children());
                $out .= "$indent</div>\n";
                $out .= "$indent</li>\n";
            } else {
                $out .= "$indent<li class='$class'><a href='$url'>$title</a></li>\n";
            }
        }
        if (!$out) {
            return '';
        }

        $out = "$indent<" . NAV_LIST_TAG . ">\n$out$indent</" . NAV_LIST_TAG . ">\n";
        return $out;
    } // _render



    /**
     * Check page folder for presence of .md files
     * @param string $path
     * @return bool
     */
    private function hasMdContent($pg): bool
    {
        $path = $pg->root();
        $mdFiles = glob("$path/*.md");
        $hasContent = false;
        if ($mdFiles && is_array($mdFiles)) {
            foreach ($mdFiles as $file) {
                if (basename($file)[0] !== '#') {
                    $hasContent = true;
                    break;
                }
            }
        }
        return $hasContent;
    } // hasMdContent


} // DefaultNav
