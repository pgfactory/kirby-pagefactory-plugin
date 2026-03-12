<?php

namespace PgFactory\PageFactory;

class PrevNextLinks
{
    public static int $inx = 0;
    public static bool $initialized = false;
    private string $class = '';
    private bool $empty = true;


    /**
     * Renders HTML for elements pointing to next and previous page. No element rendered if
     * corresponding page does not exist (e.g. beyond first/last page)
     * @param $args                     // array of arguments
     * @return string                   // HTML or Markdown
     */
    public function render(array $args): string
    {
        self::$inx++;
        $this->class = $args['class'] ?? '';
        $this->empty = true;

        if (($args['type'][0] ?? '') === 'h') {
            return $this->renderHeadLinkElements();
        }

        $prev = $this->renderLink('prev');
        $center = $this->resolveCenter($args['center'] ?? false);
        $next = $this->renderLink('next');

        $wrapperClass = $args['wrapperClass'] ?? '';
        if ($this->empty) {
            $wrapperClass .= ' pfy-page-switcher-empty';
            if (!$center) {
                $wrapperClass .= ' pfy-dispno';
            }
        }

        $out = '';
        if (!self::$initialized) {
            self::$initialized = true;
            // inject script code for page-switching:
            $url = PFY_APP_BASE_URL . PFY_BASE_OFFSET . "media/plugins/pgfactory/pagefactory/js/page-switcher.js";
            // note: not using Assets::addAssets() because this may be called from corresponding snippet.
            $out .= "\t<script src='$url'></script>\n";
        }
        $out .= <<<EOT
<div class='pfy-page-switcher-wrapper $wrapperClass'>
$prev$center$next
</div><!-- /.pfy-page-switcher-wrapper -->
EOT;

        return $out;
    } // render


    /**
     * Renders the HTML <link> elements for the page's head section.
     * @return string
     */
    private function renderHeadLinkElements(): string
    {
        $out = '';
        if ($prev = SiteNav::$prev) {
            $out .= "  <link rel='prev' href='{$prev->url()}'>\n";
        }
        if ($next = SiteNav::$next) {
            $out .= "  <link rel='next' href='{$next->url()}'>\n";
        }

        return ltrim($out);
    } // renderHeadLinkElements


    /**
     * Renders HTML element for a prev or next page link.
     * @param string $direction   'prev' or 'next'
     * @return string
     */
    private function renderLink(string $direction): string
    {
        $isPrev = ($direction === 'prev');
        $page = $isPrev ? SiteNav::$prev : SiteNav::$next;
        $link = '&nbsp;';

        if ($page) {
            TransVars::setVariable("pfy-$direction-page-title", (string)$page->title());
            $url = $page->url();
            $title = TransVars::getVariable($isPrev ? 'pfy-link-to-prev-page' : 'pfy-link-to-next-page');
            $text = '<span class="pfy-page-switcher-link-text">'.$page->title()->value().'</span>';
            $text = $isPrev
                ? TransVars::getVariable('pfy-previous-page-text').$text
                : $text.TransVars::getVariable('pfy-next-page-text');
            $rel = $isPrev ? 'prev' : 'next';
            $link = "<a href='$url' title='$title' rel='$rel'>\n\t\t$text\n\t\t</a>";
            $this->empty = false;
        }

        $dirClass = $isPrev ? 'pfy-previous-page-link' : 'pfy-next-page-link';
        $out = <<<EOT
      <div class="pfy-page-switcher-links $dirClass $this->class">
        $link
      </div>

EOT;
        return $out;
    } // renderLink


    /**
     * Resolves TransVars in center text and wraps it in a div.
     * @param string|false $center
     * @return string
     */
    private function resolveCenter(string|false $center): string
    {
        if (!$center) {
            return '';
        }
        $center = (string)$center;
        // resolve %varName% notation:
        while (preg_match('/%(\w{2,32})%/', $center, $m)) {
            $k = $m[1];
            $value = TransVars::getVariable($k);
            if (!$value && (Utils::$$k ?? false)) { // if not a TransVar, check Utils special vars
                $value = Utils::$$k;
            }
            $center = str_replace($m[0], (string)$value, $center);
        }
        // resolve {{varName}} notation:
        if (str_contains($center, '{{')) {
            $center = TransVars::translate($center);
        }
        return "<div class='pfy-page-switcher-center'>$center</div><!-- /pfy-page-switcher-center -->\n";
    } // resolveCenter

} // PrevNextLinks
