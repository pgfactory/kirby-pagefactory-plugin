<?php

namespace PgFactory\PageFactory;


use PgFactory\MarkdownPlus\Permission;

define('DEFAULT_NAV_LIST_TAG',  'ol');          // the list tag to be used

class SiteNav
{
    public static $inx = 1;
    public static $primInx = 0;
    private static bool $deep = true;
    private static $listTag;
    public static int $pageNr = 1;
    public static $prev = false;
    public static $next = null;
    private static object $currPg;
    private static array $siteStruct = [];
    private static string|null $defaultNav = null;


    public static function init(): void
    {
        $tree = site()->children()->listed();
        self::$siteStruct = self::_parseSite($tree);
    } // init

    private static function _parseSite($subtree)
    {
        $out = [];
        $i = 0;
        foreach ($subtree->listed() as $pg) {
            if ($visibility = $pg->visible()->value()) {
                $visible = Permission::evaluate($visibility);
                if (!$visible) {
                    continue;
                }
            }
            if ($showFrom = $pg->showfrom()->value()) {
                if (strtotime($showFrom) > time()) {
                    continue;
                }
            }
            if ($showTill = $pg->showtill()->value()) {
                if (strtotime($showTill) < time()) {
                    continue;
                }
            }
            $hasContent = self::hasMdContent($pg);
            // set $next once $curr was passed and the next page with content has been reached:
            if (self::$next === false && $hasContent) {
                self::$next = $pg;
            }
            $curr = $pg->isActive();
            if ($curr) {
                self::$currPg = $pg;
                self::$next = false;
            } elseif (!self::$next && $hasContent) {
                // drag $prev along until $curr has been reached (but skipping pages without content):
                self::$prev = $pg;
            }
            if (!(self::$currPg??false)) {
                self::$pageNr++;
            }
            $hasChildren = !$pg->children()->listed()->isEmpty();
            $out[$i]['pg'] = $pg;
            if (self::$deep && $hasChildren) {
                $out[$i]['sub'] = self::_parseSite($pg->children());
            }
            $i++;
        }
        return $out;
    } // _parseSite


    /**
     * Renders the default nav menu
     * @return string
     */
    public static function render(array|null $args = null): string
    {
        // if site consists of just 1 page, return empty nav/sitemap:
        if (sizeof(self::$siteStruct) === 1) {
            return '';
        }

        $site = site();
        $inx = self::$inx++; // index of nav-element
        $wrapperClass = $args['wrapperClass'];

        $class = $args['class'];
        self::$listTag = ($args['listTag']??false) ?: DEFAULT_NAV_LIST_TAG;
        $type = $args['type']??'';

        // 'top' shorthand:
        if (str_contains($type, 'top')) {
            $wrapperClass .= ' pfy-nav-horizontal pfy-nav-indented pfy-nav-animated pfy-nav-collapsible pfy-nav-collapsed pfy-encapsulated';
            if ($args['isPrimary'] === null) {
                $args['isPrimary'] = true;
            }

        // 'side' shorthand:
        } elseif (str_contains($type, 'side')) {
            $wrapperClass .= ' pfy-nav-indented pfy-nav-animated pfy-encapsulated pfy-nav-collapsible pfy-nav-open-current';

        // 'branch' shorthand:
        //} elseif (str_contains($type, 'branch')) {
        // => no classes added by default

        // 'sitemap' shorthand:
        } elseif (str_contains($type, 'sitemap')) {
            $wrapperClass .= ' pfy-encapsulated pfy-sitemap pfy-sitemap-horizontal pfy-nav-indented';

        // else, e.g. sitemap:
        } else {
            $wrapperClass .= ' pfy-nav-plain';
        }

        // add pfy-nav-collapsible if pfy-nav-collapsed:
        if (str_contains($wrapperClass, 'pfy-nav-collapsed') && !str_contains($wrapperClass, 'pfy-nav-collapsible')) {
            $wrapperClass .= ' pfy-nav-collapsible';
        }

        // find out whether this is the primary nav:
        if ( $args['isPrimary'] || str_contains($wrapperClass, 'pfy-primary-nav')) {
            self::$primInx++;
            if (!str_contains($wrapperClass, 'pfy-primary-nav')) {
                $wrapperClass .= ' pfy-primary-nav';
            }
            if (!($args['id']??false)) {
                $args['id'] = 'pfy-primary-nav' . ((self::$primInx > 1) ? '-'.self::$primInx : '');
            }
        }

        // === render: ========
        $out = false;
        $dataPageNr = '';
        // type=branch:
        if (str_contains($args['type']??'', 'branch')) {
            $out = self::renderBranch($wrapperClass, $args);

        // default type:
        } elseif ($site->hasListedChildren()) {
            $out = self::_render(self::$siteStruct);
            $pageNr = self::$pageNr;
            $dataPageNr = " data-currpagenr='$pageNr'";
        }

        if ($out !== false) {
            $placeholder = ($type === 'top')? '<div class="pfy-top-nav-placeholder" style="color:transparent">placeholder</div>': '';
            $id = ($args['id']) ? " id='{$args['id']}'" : '';
            $out = <<<EOT

<div id='pfy-nav-$inx' class='pfy-nav-wrapper $wrapperClass'$dataPageNr data-nav-inx='$inx'>
  <nav$id class='pfy-nav $class' style="display: none;">
$out
  </nav>
$placeholder
</div><!-- /pfy-nav-wrapper -->
EOT;
        }
        if (($args['type']??false) === 'top') {
            if (!self::$defaultNav) {
                self::$defaultNav = $out;
            }
        }

        return $out;
    } // render



    /**
     * Recursively renders nav for a sub-tree
     * @param $subtree
     * @return string
     */
    private static function _render($subtree, $indent = '', $prefix = ''): string
    {
        $out = '';
        foreach ($subtree as $elem) {
            $pg = $elem['pg'];

            $hasContent = self::hasMdContent($pg);
            // set $next once $curr was passed and the next page with content has been reached:
            if (self::$next === false && $hasContent) {
                self::$next = $pg;
            }
            $curr = $pg->isActive() ? ' aria-current="page"': '';
            $url = $pg->url();
            $title = $pg->title()->html();
            $hasChildren = !$pg->children()->listed()->isEmpty();

            $class = '';
            // if folder contains no md-file, fall through to first child page:
            if ($hasChildren && !$hasContent) {
                if ($pg1 = $pg->children()->listed()->first()) {
                    $url = $pg1->url();
                    $class = 'pfy-nav-no-direct-child';
                }
            }
            $class = $class ? " class='$class'" : '';

            if (self::$deep && ($elem['sub']??false)) {
                $out .= "$indent <li$class><a href='$url'$curr>$title</a>";
                $out .=  self::_render($elem['sub'], "$indent    ");
                $out .= "$indent </li>\n";
            } else {
                $out .= "$indent <li><a href='$url'$curr>$title</a></li>\n";
            }
        }
        if (!$out) {
            return '';
        }

        $listTag = self::$listTag;
        $out = "\n$indent<{$listTag}>$prefix\n$out$indent</{$listTag}>\n";
        return $out;
    } // _render



    /**
     * Check page folder for presence of .md files
     * Ignores files starting with '#'
     * @param string $path
     * @return bool
     */
    private static function hasMdContent($pg): bool
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
    private static function renderBranch(string &$wrapperClass, array $args): string
    {
        $prefix = ($args['prefix'] ?? false) ?: '';
        if ($prefix) {
            $prefix = "<li class='pfy-nav-prefix'><a href='~/'>$prefix</a></li>";
        }

        // find top-level parent:
        $page = page();
        while ($page->parent()) {
            $page = $page->parent();
        }
        if ($page->hasListedChildren()) {
            // get children of top-level parent:
            $label = (string)$page->title();
            $out = "<div class='pfy-nav-branch-title'>$label</div>\n";
            foreach (self::$siteStruct as $elem) {
                $pg = $elem['pg'];
                if ($pg === $page) {
                    break;
                }
            }
            if ($elem['sub']??false) {
                $out .= self::_render($elem['sub'], prefix: $prefix);
            }
        } else {
            $out = '';
            $wrapperClass .= ' pfy-nav-empty';
        }
        $wrapperClass .= ' pfy-nav-branch';
        return $out;
    } // renderBranch


} // DefaultNav
