<?php

namespace PgFactory\PageFactory;


use Kirby\Cms\Permissions;
use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\MarkdownPlus\Permission;

class SiteNav
{
    public const DEFAULT_LIST_TAG = 'ol';
    private const MIN_NPAGES_FOR_SITESEARCH = 4;
    private static array $defaultSiteSearchOptions;

    public static int $inx = 1;
    public static int $primInx = 0;
    private static bool $deep = true;
    private static string $listTag = self::DEFAULT_LIST_TAG;
    public static int $pageNr = 1;
    public static int $nPages = 0;
    /** @var object|false  Previous page with content before the current page, or false if none */
    public static $prev = false;
    /** @var object|null|false  Next page with content after the current page, or null/false if none */
    public static $next = null;
    private static ?object $currPg = null;
    private static array $siteStruct = [];
    private static ?string $defaultNav = null;
    private static string $branchClass = '';
    private static bool $sitemapUpdateEnabled = true;
    private static array $sitemap = [];
    private static string $sitemapFile;
    private static array $pages;
    private static array $siteSearchOptions = [];


    /**
     * @return void
     */
    public static function init(): void
    {
        self::$sitemapFile = Utils::resolvePath(PFY_SITEMAP_FILE);
        if ($maintainSitemap = kirby()->option('pgfactory.pagefactory.maintainSitemap', PageFactory::$dev)) {
            if (is_string($maintainSitemap)) {
                $maintainSitemap = Permissions::evaluate($maintainSitemap);
            } elseif ($maintainSitemap === true) {
                $maintainSitemap = Permission::isLocalhost() || !Permission::isLoggedIn();
            }
        }
        self::$sitemapUpdateEnabled &= $maintainSitemap;
        self::$defaultSiteSearchOptions = [
            'fieldType'     => 'icon',
            'ignore'        => ['/<script\b.*?<\/script>/s', '/<style\b.*?<\/style>/s'],
            'resultsUrl'    =>  pfyPage()->url(),
        ];

        $tree = site()->children();
        self::$siteStruct = self::_parseSite($tree, true);
        self::$nPages = count(self::$pages);
    } // init

    /**
     * @param $subtree
     * @return array
     */
    private static function _parseSite($subtree, bool $topLevel = false): array
    {
        $out = [];
        $i = 0;
        foreach ($subtree->listed() as $pg) {
	        // define branch class, in case it's used by the current page:
            if ($topLevel || !self::$branchClass) {
                self::$branchClass = 'branch-' . translateToIdentifier($pg->title()->value(), toLowerCase: true);
            }
            if ($visibility = $pg->visible()->value()) {
                $visible = Permission::evaluate($visibility);
                if (!$visible) {
                    continue;
                }
            }
            if ($url = $pg->targeturl()->value()) {
                // get field 'TargetUrl' from metafile if set:
                if (preg_match_all('/%(\w{3,20})%/', $url, $m)) {
                    foreach($m[1] as $j => $varName) {
                        if ($val = $pg->$varName()) {
                            $url = str_replace($m[0][$j], $val, $url);
                        }
                    }
                }
                $out[$i]['url'] = $url;
                $id = $pg->id();
                self::$pages[$id] = $pg;
            }

            // check and evaluate time constraints (showFrom,showTill) from variables in meta-file:
            $showFrom = $pg->showfrom()->value();
            $showTill = $pg->showtill()->value();
            if ($showFrom || $showTill) {
                if (!MdPlusHelper::isNowVisible($showFrom, $showTill)) {
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
                Page::addBodyTagClass(self::$branchClass);

            } elseif (!self::$next && $hasContent) {
                // drag $prev along until $curr has been reached (but skipping pages without content):
                self::$prev = $pg;
            }
            if (!self::$currPg && $hasContent) {
                self::$pageNr++;
            }
            $hasChildren = !$pg->children()->listed()->isEmpty();
            $out[$i]['pg'] = $pg;
            $id = $pg->id();
            self::$pages[$id] = $pg;
            if (self::$deep && $hasChildren) {
                $out[$i]['sub'] = self::_parseSite($pg->children());
            }
            $i++;
        }
        return $out;
    } // _parseSite


    /**
     * Renders the default nav menu
     * @param array|null $args
     * @return string
     */
    public static function render(?array $args = null): string
    {
        // if site consists of just 1 page, return empty nav/sitemap:
        if (self::$nPages <= 1) {
            return '';
        }

        list($inx, $type, $class, $wrapperClass) = self::parseOptions($args);

        // === render: ========
        $out = '';
        $dataPageNr = '';
        // type=branch:
        if (str_contains($type, 'branch')) {
            $out = self::renderBranch($wrapperClass, $args);

        // default type:
        } elseif (site()->hasListedChildren()) {
            $out = self::_render(self::$siteStruct);
            $pageNr = self::$pageNr;
            $dataPageNr = " data-currpagenr='$pageNr'";
        }
        $overlay = '';
        if ($out) {
            // site search:
            list($siteSearchWidget, $overlay) = self::renderSiteSearchWidget();

            $placeholder = ($type === 'top')? '<div class="pfy-top-nav-placeholder" style="color:transparent">placeholder</div>': '';
            $id = ($args['id'] ?? false) ? " id='{$args['id']}'" : '';
            $out = <<<EOT

<div id='pfy-nav-$inx' class='pfy-nav-wrapper $wrapperClass'$dataPageNr data-nav-inx='$inx'>
  <nav$id class='pfy-nav $class' style="display: none;">
$out
  </nav>
$siteSearchWidget$placeholder
</div><!-- /pfy-nav-wrapper -->
EOT;
        }
        if ($type === 'top') {
            if (!self::$defaultNav) {
                self::$defaultNav = $out;
            }
        }

        self::updateSitemap();

        return $out . $overlay;
    } // render



    /**
     * Recursively renders nav for a sub-tree
     * @param array $subtree
     * @param string $indent
     * @param string $prefix
     * @return string
     */
    private static function _render(array $subtree, string $indent = '', string $prefix = ''): string
    {
        $out = '';
        foreach ($subtree as $elem) {
            $pg = $elem['pg'];

            $hasContent = self::hasMdContent($pg);
            $curr = $pg->isActive() ? ' aria-current="page"': '';
            if ($elem['url'] ?? false) { // use field 'TargetUrl' from metafile if set
                $url = $elem['url'];
            } else {
                $url = $pg->url() . '/';
            }
            $title = $pg->title()->html();
            $hasChildren = !$pg->children()->listed()->isEmpty();

            $class = '';
            // if folder contains no md-file, fall through to first child page:
            if ($hasChildren && !$hasContent) {
                if ($pg1 = $pg->children()->listed()->first()) {
                    $url = $pg1->url().'/';
                    $class = 'pfy-nav-no-direct-child';
                }
            }
            $class = $class ? " class='$class'" : '';

            if (self::$deep && ($elem['sub'] ?? false)) {
                if ($hasContent) {
                    self::$sitemap[$url] = $url;
                }
                $out .= "$indent <li$class><a href='$url'$curr>$title</a>";
                $out .=  self::_render($elem['sub'], "$indent    ");
                $out .= "$indent </li>\n";
            } else {
                self::$sitemap[$url] = $url;
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
     * @param object $pg
     * @return bool
     */
    private static function hasMdContent(object $pg): bool
    {
        $path = $pg->root();
        $mdFiles = glob("$path/*.md");
        if ($mdFiles) {
            foreach ($mdFiles as $file) {
                if (basename($file)[0] !== '#') {
                    return true;
                }
            }
        }
        return false;
    } // hasMdContent


    /**
     * Renders a branch-type navigation showing children of the current top-level parent
     * @param string $wrapperClass
     * @param array $args
     * @return string
     */
    private static function renderBranch(string &$wrapperClass, array $args): string
    {
        $prefix = ($args['prefix'] ?? false) ?: '';
        if ($prefix) {
            $prefix = "<li class='pfy-nav-prefix'><a href='~/'>$prefix</a></li>";
        }

        // find top-level parent:
        $page = pfyPage();
        while ($page->parent()) {
            $page = $page->parent();
        }
        $label = (string)$page->title();
        $out = '';
        if ($page->hasListedChildren()) {
            // get children of top-level parent:
            $found = null;
            foreach (self::$siteStruct as $elem) {
                $pg = $elem['pg'];
                if ($pg === $page) {
                    $found = $elem;
                    break;
                }
            }
            if ($found && ($found['sub'] ?? false)) {
                $out .= self::_render($found['sub'], prefix: $prefix);
            }
        } else {
            $wrapperClass .= ' pfy-nav-empty';
        }
        $wrapperClass .= ' pfy-nav-branch';

        // handle option 'showHome' to prepend special nav-element pointing to Homepage:
        if ($homeTitle = ($args['showHome'] ?? false)) {
            $pg = site()->homePage();
            $curr = $pg->isActive() ? ' aria-current="page"': '';
            $url = $pg->url().'/';
            $title = $homeTitle ?: $pg->title()->html();
            $home = "\n <li$curr><a href='$url'>$title</a></li>\n";
            if (preg_match('/(\s*<(ol|ul)>)(.*)/s', $out, $m)) {
                $out = $m[1] . $home . $m[3];
            }
        }

        $out = "<div class='pfy-nav-branch-title'>$label</div>\n$out";
        return $out;
    } // renderBranch


    /**
     * @return void
     * @throws \Exception
     */
    private static function updateSitemap()
    {
        if (self::$sitemapUpdateEnabled && (PageFactory::$forceAssetsUpdate || !file_exists(self::$sitemapFile))) {
            if (self::$sitemapUpdateEnabled) {
                writeFile(self::$sitemapFile, implode("\n", self::$sitemap));
                self::$sitemapUpdateEnabled = false;
            }
        }
    } // updateSitemap


    /**
     * @return array
     */
    public static function getVisiblePages()
    {
        return self::$pages;
    } // getVisiblePages


    /**
     * @return array
     */
    private static function renderSiteSearchWidget(): array
    {
        if ((self::$nPages < self::MIN_NPAGES_FOR_SITESEARCH) ||
                !kirby()->option('pgfactory.pagefactory.activateSiteSearch', true)) {
            return ['',''];
        }
        $siteSearchWidget = $overlay = '';
        if (self::$siteSearchOptions) {
            $ss = new SiteSearch(self::$siteSearchOptions);
            $siteSearchWidget = $ss->renderSearchField();
            $urlArg = 'q';
            if ($_GET[$urlArg] ?? false) {
                $query = $_GET[$urlArg];
                $pageContents = PageFactory::$allPagesContent;
                $overlay = $ss->renderSearchResults($query, $pageContents);
                $label = TransVars::getVariable('pfy-searchresult-label');
                $overlay = <<<EOT

    <!-- === search results ================================================ -->
<dialog id="pfy-sitesearch-results" class="pfy-search-popup pfy-popup-wrapper">
        <div class="pfy-search-header pfy-popup-header pfy-draggable">
            <div>$label</div>
            <button class="pfy-close-button pfy-popup-close-button" type="button">
                <span class="visually-hidden">Close modal</span> 
                <span aria-hidden="true">✕</span>
            </button>
        </div>
        <div class="pfy-popup-container">
$overlay
        </div>
    </dialog>
    <!-- === /search results =============================================== -->
EOT;
            }
        }
        return [$siteSearchWidget, $overlay];
    } // renderSiteSearchWidget


    /**
     * @param array $options
     * @return array
     */
    private static function parseOptions(array $options): array
    {
        $options = $options ?? [];

        $inx = self::$inx++; // index of nav-element
        $wrapperClass = $options['wrapperClass'] ?? '';
        $class = $options['class'] ?? '';
        self::$listTag = ($options['listTag'] ?? false) ?: self::DEFAULT_LIST_TAG;
        $type = $options['type'] ?? '';

        // 'top' shorthand:
        if (str_contains($type, 'top')) {
            $wrapperClass .= ' pfy-nav-horizontal pfy-nav-indented pfy-nav-animated pfy-nav-collapsible pfy-nav-collapsed';
            if (($options['isPrimary'] ?? null) === null) {
                $options['isPrimary'] = true;
            }

            // 'side' shorthand:
        } elseif (str_contains($type, 'side')) {
            $wrapperClass .= ' pfy-nav-indented pfy-nav-animated pfy-nav-collapsible pfy-nav-open-current';

            // 'sitemap' shorthand:
        } elseif (str_contains($type, 'sitemap')) {
            $wrapperClass .= ' pfy-sitemap pfy-sitemap-horizontal pfy-nav-indented';

            // else, e.g. sitemap:
        } else {
            $wrapperClass .= ' pfy-nav-plain';
        }

        // add pfy-nav-collapsible if pfy-nav-collapsed:
        if (str_contains($wrapperClass, 'pfy-nav-collapsed') && !str_contains($wrapperClass, 'pfy-nav-collapsible')) {
            $wrapperClass .= ' pfy-nav-collapsible';
        }

        // find out whether this is the primary nav:
        if (($options['isPrimary'] ?? false) || str_contains($wrapperClass, 'pfy-primary-nav')) {
            self::$primInx++;
            if (!str_contains($wrapperClass, 'pfy-primary-nav')) {
                $wrapperClass .= ' pfy-primary-nav';
            }
            if (!($options['id'] ?? false)) {
                $options['id'] = 'pfy-primary-nav' . ((self::$primInx > 1) ? '-'.self::$primInx : '');
            }
        }

        if (!str_contains($wrapperClass, 'pfy-nav-collapsible')) {
            $wrapperClass .= ' pfy-nav-not-collapsible';
        }

        self::$siteSearchOptions = [];
        if ($options['siteSearch']) {
            if (is_array($options['siteSearch'])) {
                self::$siteSearchOptions = $options['siteSearch'] + self::$defaultSiteSearchOptions;
            } elseif ($options['isPrimary']) {
                self::$siteSearchOptions = self::$defaultSiteSearchOptions;
            }
        }

        return [$inx, $type, $class, $wrapperClass];
    } // parseOptions

} // SiteNav
