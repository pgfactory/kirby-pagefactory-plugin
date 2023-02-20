<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function prevnextlinks($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'class' => ['Class to be applied to the element', false],
            'wrapperClass' => ['Class to be applied to the wrapper element. ', false],
            'type' => ['[top,bottom] Defines where the page switcher links will be displayed', 'bottom'],
        ],
        'summary' => <<<EOT
# PrevNextLinks()

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

    $options['wrapperClass'] = ($options['type'][0] === 't')? ' pfy-show-as-top-arrows': ' pfy-show-as-arrows-and-text';

    // assemble output:
    $obj = new PrevNextLinks();

    $str .= $obj->render($options);

    return shieldStr($str);
}



class PrevNextLinks
{
    public static $inx = 1;
    public $class;
    public $page;
    public $pages;


    /**
     * Renders HTML for elements pointing to next and previous page. No element rendered if
     * corresponding page does not exist (e.g. beyond first/last page)
     * @param $args                     // array of arguments
     * @return string                   // HTML or Markdown
     */
    public function render(array $args): string
    {
        $this->class = $args['class'];
        $this->page  = PageFactory::$page;
        $this->pages = PageFactory::$pages;

        $out = "\n<div class='pfy-page-switcher-wrapper {$args['wrapperClass']}'>\n";
        $out .= $this->renderPrevLink();
        $out .= $this->renderNextLink();

        // inject script code for page-switching:
        $url = PageFactory::$appUrl."media/plugins/usility/pagefactory/js/page-switcher.js";
        $out .= "\t<script data-src='$url' class='pfy-onload'></script>\n";
        $out .= "</div>\n";

        return $out;
    } // render


    /**
     * Renders HTML element for previous page link.
     * @return string
     */
    private function renderPrevLink(): string
    {
        $out = "\t<div></div>\n";

        // get first and current page:
        $current = $this->page->url();
        $next = $this->pages->first(); // i.e. first page
        $prev = false; // element 1 step behind, i.e. the right one once we hit the current

        // start from first page and walk through sitemap till current page is found:
        while ($next && ($current !== $next->url())) {
            $prev = $next;
            $next = $this->findNext($next);
        }

        if ($prev) {
            TransVars::setVariable('pfy-prev-page-title', (string)$prev->title());
            $url = $prev->url();
            $title = TransVars::getVariable('pfy-link-to-prev-page');
            $text = '<span class="pfy-page-switcher-link-text">'.$prev->title()->value().'</span>';
            $text = TransVars::getVariable('pfy-previous-page-text').$text;
            $prevLink = "<a href='$url' title='$title'>\n\t\t$text\n\t\t</a>";
            $out = <<<EOT
      <div class="pfy-page-switcher-links pfy-previous-page-link $this->class">
        $prevLink
      </div>

EOT;
        }
        return $out;
    } // renderPrevLink


    /**
     * Renders HTML element for next page link.
     * @return string
     */
    private function renderNextLink(): string
    {
        $out = "\t<div></div>\n";
        $next = $this->findNext(page());
        if ($next) {
            $nextUrl = $next->url();
            $title = TransVars::getVariable('pfy-link-to-next-page');
            $text = '<span class="pfy-page-switcher-link-text">'.$next->title()->value().'</span>';
            $text = $text.TransVars::getVariable('pfy-next-page-text');
            $nextLink = "<a href='$nextUrl' title='$title'>\n\t\t$text\n\t\t</a>";
            $out = <<<EOT
      <div class="pfy-page-switcher-links pfy-next-page-link $this->class">
        $nextLink
      </div>

EOT;
        }
        return $out;
    } // renderNextLink


    /**
     * Finds the next page relative to given one.
     * @param object $page
     * @return object|null
     */
    private function findNext(object $page)
    {
        if ($page->hasListedChildren()) {     // down:
            $next = $page->children()->listed()->first();

        } elseif ($page->hasNextListed()) {   // next sibling:
            $next = $page->nextListed();

        } else {                             // next uncle:
            $next = $page;
            while ($next) {
                $next = $next->parent();
                if (!$next) {
                    break;
                }
                if ($next->hasNextListed()) {
                    $next = $next->nextListed();
                    break;
                }
            }
        }
        return $next;
    } // findNext

} // PrevNextLinks
