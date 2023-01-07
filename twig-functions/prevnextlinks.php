<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function prevnextlinks($args = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'class' => ['Class to be applied to the element', false],
            'wrapperClass' => ['Class to be applied to the wrapper element. '.
                'Predefined: ".pfy-show-as-text" and ".pfy-show-as-arrows".', false],
        ],
        'summary' => <<<EOT
# PrevNextLinks()

Renders two links, one to the next page, one to the previous page. Activates scripts to trigger on cursor 
keys <- (left) and  -> (right).

Classes:
- .pfy-previous-page-link
- .pfy-next-page-link
- .pfy-show-as-text     13em>> predefined styles  (apply to wrapperClass)
- .pfy-show-as-arrows     13em>> predefined styles  (apply to wrapperClass)

Use variables ``\{{ pfy-previous-page-text }}`` and ``\{{ pfy-next-page-text }}`` to define the text (visible and invisible parts).

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $args))) {
        return $str;
    } else {
        list($str, $options, $inx, $funcName) = $str;
    }

    // assemble output:
    $obj = new PrevNextLinks();

    $str .= $obj->render($options);

    //$str = markdown($str); // markdown-compile

    return shieldStr($str);
}



class PrevNextLinks
{
    public static $inx = 1;


    /**
     * Renders HTML for elements pointing to next and previous page. No element rendered if
     * corresponding page does not exist (e.g. beyond first/last page)
     * @param $args                     // array of arguments
     * @return string                   // HTML or Markdown
     */
    public function render(array $args): string
    {
        $this->class = $args['class'];
        $this->page = page();
        $this->pages = pages();
        $this->twig = new TwigVars();

        $out = "\n<div class='pfy-page-switcher-wrapper {$args['wrapperClass']}'>\n";
//        $out = "\n    <div class='pfy-page-switcher-wrapper {$args['wrapperClass']}'>\n";
        $out .= $this->renderPrevLink();
        $out .= $this->renderNextLink();
        $out .= "</div>\n";
//        $out .= "    </div>\n";

        return $out;
    } // render


    /**
     * Renders HTML element for previous page link.
     * @return string
     */
    private function renderPrevLink(): string
    {
        $out = '<div></div>';
        $current = $this->page->url();
        $next = $this->pages->listed()->first();
        $prev = false;
        // parse sitemap till current page found:
        while ($next && ($current !== $next->url())) {
            $prev = $next;
            $next = $this->findNext($next);
        }

        if ($prev) {
            PageFactory::$trans->setVariable('pfy-prev-page-title', (string)$prev->title());
            $url = $prev->url();
            $title = $this->twig->getVariable('pfy-link-to-prev-page');
            $text = $this->twig->getVariable('pfy-previous-page-text');
            $prevLink = "<a href='$url' title='$title'>\n\t\t$text\n\t\t</a>";
//            $prevLink = "<a href='$url' title='{{ pfy-link-to-prev-page }}'>\n\t\t{{ pfy-previous-page-text }}\n\t\t</a>";
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
        $out = '<div></div>';
        $next = $this->findNext(page());
        if ($next) {
            $this->twig->setVariable('pfy-next-page-title', (string)$next->title());
//            PageFactory::$trans->setVariable('pfy-next-page-title', (string)$next->title());
            $nextUrl = $next->url();
            $title = $this->twig->getVariable('pfy-link-to-next-page');
            $text = $this->twig->getVariable('pfy-next-page-text');
            $nextLink = "<a href='$nextUrl' title='$title'>\n\t\t$text\n\t\t</a>";
//            $nextLink = twig($nextLink);
//            $nextLink = "<a href='$nextUrl' title='{{ pfy-link-to-next-page }}'>\n\t\t{{ pfy-next-page-text }}\n\t\t</a>";
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
