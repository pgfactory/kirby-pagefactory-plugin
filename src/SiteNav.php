<?php

namespace PgFactory\PageFactory;


use PgFactory\MarkdownPlus\Permission;

define('DEFAULT_NAV_LIST_TAG',  'ol');          // the list tag to be used

class SiteNav
{
    public static $inx = 1;
    private static $primaryNavInitialized = false;
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
        $wrapperClass = $args['wrapperClass'];

        // find out whether this is the primary nav:
        if (!self::$primaryNavInitialized && (($args['isPrimary']??null) !== false)) {
            self::$primaryNavInitialized = true;
            if (!str_contains($wrapperClass, 'pfy-primary-nav')) {
                $wrapperClass .= ' pfy-primary-nav';
            }
            if (!($args['id']??false)) {
                $args['id'] = 'pfy-primary-nav';
            }
        }

        $class = $args['class'];
        $this->listTag = ($args['listTag']??false) ?: DEFAULT_NAV_LIST_TAG;
        $type = $args['type']??'';

        // 'top' shorthand:
        if (str_contains($type, 'top')) {
            $wrapperClass .= ' pfy-nav-horizontal pfy-nav-indented pfy-nav-animated pfy-nav-hoveropen pfy-nav-collapsible pfy-encapsulated';

        // 'side' shorthand:
        } elseif (str_contains($type, 'side')) {
            $wrapperClass .= ' pfy-nav-indented pfy-nav-animated pfy-encapsulated pfy-nav-collapsible';

        // 'branch' shorthand:
        } elseif (str_contains($type, 'branch')) {
            $wrapperClass .= ' pfy-nav-indented pfy-nav-animated pfy-encapsulated pfy-nav-collapsible';

        // else, e.g. sitemap:
        } else {
            $wrapperClass .= ' pfy-nav-plain pfy-nav-indented';
        }

        // add pfy-nav-collapsible if pfy-nav-collapsed:
        if (str_contains($wrapperClass, 'pfy-nav-collapsed') && !str_contains($wrapperClass, 'pfy-nav-collapsible')) {
            $wrapperClass .= ' pfy-nav-collapsible';
        }


        // === render: ========
        $out = false;
        // type=branch:
        if (str_contains($args['type']??'', 'branch')) {
            $out = $this->renderBranch($wrapperClass);

        // default type:
        } elseif ($this->site->hasListedChildren()) {
            $tree = $this->site->children()->listed();
            // render nav if homepage has siblings or children:
            if ((sizeof($tree->data()) > 1) || (sizeof($tree->children()->listed()->data()) > 0)) {
                $out = $this->_render($tree);
            } else {
                $wrapperClass .= ' pfy-nav-empty';
            }
        }

        if ($out !== false) {
            $placeholder = ($type === 'top')? '<div class="pfy-top-nav-placeholder">placeholder</div>': '';
            $id = ($args['id']) ? " id='{$args['id']}'" : '';
            $out = <<<EOT

<div id='pfy-nav-$inx' class='pfy-nav-wrapper $wrapperClass'>
<!--  <nav$id class='pfy-nav $class'>-->
  <nav$id class='pfy-nav $class' style="display: none;">
$out
  </nav>
$placeholder
</div><!-- /pfy-nav-wrapper -->
EOT;
        }
        return $out;
    } // render



    /**
     * Recursively renders nav for a sub-tree
     * @param $subtree
     * @return string
     */
    private function _render($subtree, $indent = ''): string
    {
        $out = '';
        foreach ($subtree->listed() as $pg) {
            // check and handle visibility-restriction on page:
            if ($visibility = $pg->visible()->value()) {
                $visible = Permission::evaluate($visibility);
                if (!$visible) {
                    continue;
                }
            }

            $hasContent = $this->hasMdContent($pg);
            // set $next once $curr was passed and the next page with content has been reached:
            if (self::$next === false && $hasContent) {
                self::$next = $pg;
            }
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
            if ($this->deep && $hasChildren) {
                $out .= "$indent<li$class><a href='$url'$curr>$title</a>";
                $out .=  $this->_render($pg->children(), "$indent    ");
                $out .= "$indent</li>\n";
            } else {
                $out .= "$indent<li><a href='$url'$curr>$title</a></li>\n";
            }
        }
        if (!$out) {
            return '';
        }

        $out = "\n$indent<{$this->listTag}>\n$out$indent</{$this->listTag}>\n";
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


    /**
     * @return string
     */
    private function renderBranch(string &$wrapperClass): string
    {
        // find top-level parent:
        $page = page();
        while ($page->parent()) {
            $page = $page->parent();
        }
        if ($page->hasListedChildren()) {
            // get children of top-level parent:
            $label = (string)$page->title();
            $out = "<div class='pfy-nav-branch-title'>$label</div>\n";
            $subtree = $page->children();
            $out .= $this->_render($subtree);
        } else {
            $out = '';
            $wrapperClass .= ' pfy-nav-empty';
        }
        $wrapperClass .= ' pfy-nav-branch';
        return $out;
    } // renderBranch


} // DefaultNav
