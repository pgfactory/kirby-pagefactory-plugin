<?php

namespace Usility\PageFactory;


define('DEFAULT_NAV_LIST_TAG',  'ol');          // the list tag to be used

class SiteNav
{
    public static $inx = 1;
    private bool $deep = true;
    private $args;
    private $listTag;
    private $site;
    public static $prev = false;
    public static $next = null;

    public function __construct()
    {
        $this->site = site();
        // PageFactory::$pg->addAssets('NAV');
        // Note: normally, nav() is used in Twig template, but that's too late for loading assets.
        // Thus, Pfy loads them, if option 'default-nav' is true
    } // __construct



    /**
     * Renders the default nav menu
     * @return string
     */
    public function render($args): string
    {
        $inx = self::$inx++; // index of nav-element
        $this->args = &$args;
        $wrapperClass = &$args['wrapperClass'];
        $class = $args['class'];
        $this->listTag = ($args['listTag']??false) ?: DEFAULT_NAV_LIST_TAG;

        // 'top' short-hand:
        if ($args['type'] === 'top') {
           $args['wrapperClass'] .= ' pfy-nav-horizontal pfy-nav-indented pfy-nav-animated pfy-nav-hoveropen pfy-encapsulated';

       // 'side' short-hand:
        } elseif ($args['type'] === 'side') {
           $args['wrapperClass'] .= ' pfy-nav-indented pfy-nav-animated pfy-encapsulated';
        }

        if (!str_contains($args['wrapperClass'], 'pfy-nav-horizontal')) {
            $args['wrapperClass'] .= ' pfy-nav-vertical';
        }

        if (str_contains($args['wrapperClass'], 'pfy-nav-collapsed') && !str_contains($args['wrapperClass'], 'pfy-nav-collapsible')) {
            $args['wrapperClass'] .= ' pfy-nav-collapsible';
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
  <nav class='pfy-nav $class' style="display: none;">
$out
  </nav>
  <div class="pfy-top-nav-placeholder">.</div>
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
            $hasContent = $this->hasMdContent($pg);
            // set $next once $curr was passed and the next page with content has been reached:
            if (self::$next === false && $hasContent) {
                self::$next = $pg;
            }
            $indent = '';
            $curr = $pg->isActive() ? ' aria-current="page"': '';
            if ($curr) {
                self::$next = false;
            } elseif (!self::$next && $hasContent) {
                // drag $prev along until $curr has been reached (but skipping pages without content):
                self::$prev = $pg;
            }
            $url = $pg->url();
            $title = $pg->title()->html();
            $hasChildren = !$pg->children()->listed()->isEmpty();

            // if folder contains no md-file, fall through to first child page:
            if ($hasChildren && !$hasContent) {
                if ($pg1 = $pg->children()->listed()->first()) {
                    $url = $pg1->url();
                }
            }

            $class = '';
            if (!$hasContent) {
                // if a nav item has no content it is treated as a pseudo element,
                // the link points to the next element with content:
                $class = " class='pfy-pseudo-elem'";
            }
            if ($this->deep && $hasChildren) {
                $out .= "$indent<li$class><a href='$url'$curr>$title</a>";
                $out .=  $this->_render($pg->children());
                $out .= "$indent</li>";
            } else {
                $out .= "$indent<li><a href='$url'$curr>$title</a></li>\n";
            }
        }
        if (!$out) {
            return '';
        }

        $out = "$indent<{$this->listTag}>\n$out$indent</{$this->listTag}>";
        return $out;
    } // _render



    /**
     * Check page folder for presence of .md files
     * Ignores files starting with '#'
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
