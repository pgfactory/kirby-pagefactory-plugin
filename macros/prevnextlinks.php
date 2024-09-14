<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

return function ($argStr = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'class' => ['Class to be applied to the element', false],
            'wrapperClass' => ['Class to be applied to the wrapper element. ', false],
            'type' => ['[links,head-elem] "links" returns a DIV to be placed inside page body. '.
                '"head-elem" returns two &lt;link> elements for the head section. ', 'links'],
            'option' => ['[top,bottom] Defines which default class to apply.', 'bottom'],
            'center' => ['[str] Defines text that is rendered between the preveous and next links.', null],
        ],
        'summary' => <<<EOT
# $funcName()

Renders two links, one to the next page, one to the previous page. Activates scripts to trigger on cursor 
keys <- (left) and  -> (right).

Classes:
- .pfy-previous-page-link
- .pfy-next-page-link
- .pfy-show-as-arrows-and-text     13em>> predefined styles  (apply to wrapperClass)
- .pfy-show-as-top-arrows     13em>> predefined styles  (apply to wrapperClass)

Use variables ``\{{ pfy-previous-page-text }}`` and ``\{{ pfy-next-page-text }}`` to define the text (visible and invisible parts).

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($options, $str) = $str;
    }

    $options['wrapperClass'] .= ($options['option'][0] === 't')? ' pfy-show-as-top-arrows': ' pfy-show-as-arrows-and-text';

    // assemble output:
    $obj = new PrevNextLinks();

    $str .= $obj->render($options);

    return $str;
};



class PrevNextLinks
{
    public static $inx = 0;
    public static $initialized = false;
    public $class;
    public $page;
    public $pages;
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
        $this->class = $args['class'];
        $this->page  = PageFactory::$page;
        $this->pages = PageFactory::$pages;

        $wrapperClass = $args['wrapperClass'];

        if (($args['type'][0]??'') === 'h') {
            $out = $this->renderHeadLinkElements();

        } else {
            $prev = $this->renderPrevLink();

            $center = '';
            if ($args['center']??false) {
                $center = $args['center'];
                while (preg_match('/%(\w{2,32})%/', $center, $m)) {
                    $value = TransVars::getVariable($m[1]);
                    $center = str_replace($m[0], $value, $center);
                }
                // handle transvars in {{}} notation:
                if (str_contains($center, '{{')) {
                    $center = TransVars::translate($center);
                }
                $center = "<div class='pfy-page-switcher-center'>$center</div>\n";
            }

            $next = $this->renderNextLink();

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
                $url = PageFactory::$appUrl . "media/plugins/pgfactory/pagefactory/js/page-switcher.js";
                $out .= "\t<script src='$url'></script>\n";
            }
            $out .= <<<EOT
<div class='pfy-page-switcher-wrapper $wrapperClass'>
$prev$center$next
</div><!-- /.pfy-page-switcher-wrapper -->
EOT;
        }

        return $out;
    } // render


    /**
     * Renders the HTML <link> elements for the page's head section.
     * @return string
     */
    private function renderHeadLinkElements(): string
    {
        $out = "";
        $prev = SiteNav::$prev;
        if ($prev) {
            $url = $prev->url();
            $out = "<link rel='prev' href='$url'>\n";
        }
        $next = SiteNav::$next;
        if ($next) {
            $url = $next->url();
            $out .= "  <link rel='next' href='$url'>\n";
        }

        return $out;
    } // renderHeadLinkElements


    /**
     * Renders HTML element for previous page link.
     * @return string
     */
    private function renderPrevLink(): string
    {
        $prev = SiteNav::$prev;
        if ($prev) {
            TransVars::setVariable('pfy-prev-page-title', (string)$prev->title());
            $url = $prev->url();
            $title = TransVars::getVariable('pfy-link-to-prev-page');
            $text = '<span class="pfy-page-switcher-link-text">'.$prev->title()->value().'</span>';
            $text = TransVars::getVariable('pfy-previous-page-text').$text;
            $prevLink = "<a href='$url' title='$title' rel='prev'>\n\t\t$text\n\t\t</a>";
            $this->empty = false;
        } else {
            $prevLink = '&nbsp;';
        }
        $out = <<<EOT
      <div class="pfy-page-switcher-links pfy-previous-page-link $this->class">
        $prevLink
      </div>

EOT;
        return $out;
    } // renderPrevLink


    /**
     * Renders HTML element for next page link.
     * @return string
     */
    private function renderNextLink(): string
    {
        $next = SiteNav::$next;
        if ($next) {
            $nextUrl = $next->url();
            $title = TransVars::getVariable('pfy-link-to-next-page');
            $text = '<span class="pfy-page-switcher-link-text">'.$next->title()->value().'</span>';
            $text = $text.TransVars::getVariable('pfy-next-page-text');
            $nextLink = "<a href='$nextUrl' title='$title' rel='next'>\n\t\t$text\n\t\t</a>";
            $this->empty = false;
        } else {
            $nextLink = '&nbsp;';
        }
        $out = <<<EOT
      <div class="pfy-page-switcher-links pfy-next-page-link $this->class">
        $nextLink
      </div>

EOT;
        return $out;
    } // renderNextLink

} // PrevNextLinks
