<?php

/*
 * PageFactory Macro Template
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ), // <- don't modify
    'parameters' => [
        'class' => ['Class to be applied to the element', false],
        'wrapperClass' => ['Class to be applied to the wrapper element. '.
            'Predefined: ".pfy-show-as-text" and ".pfy-show-as-arrows".', false],
    ],
    'summary' => <<<EOT
Renders two links, one to the next page, one to the previous page. Activates scripts to trigger on cursor 
keys <- (left) and  -> (right).

Classes:
- .pfy-previous-page-link
- .pfy-next-page-link
- .pfy-show-as-text     13em>> predefined styles
- .pfy-show-as-arrows     13em>> predefined styles

Use variables ``\{{ pfy-previous-page-text }}`` and ``\{{ pfy-next-page-text }}`` to define the text (visible and invisible parts).

EOT,                                    // <- Help text to explain function of this macro
    'mdCompile' => false,               // <- whether output needs to be markdown-compiled
    'assetsToLoad' => [ 'PAGE_SWITCHER' ],
];



class PrevNextLinks extends Macros // <- modify classname (must be qual to name of this file w/t extension!)
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Renders HTML for elements pointing to next and previous page. No element rendered if
     * corresponding page does not exist (e.g. beyond first/last page)
     * @param $args                     // array of arguments
     * @return string                   // HTML or Markdown
     */
    public function render(array $args): string
    {
        $this->class = $args['class'];

        $out = "\n    <div class='pfy-page-switcher-wrapper {$args['wrapperClass']}'>\n";
        $out .= $this->renderPrevLink();
        $out .= $this->renderNextLink();
        $out .= "    </div>\n";

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
            $prevLink = "<a href='$url' title='{{ pfy-link-to-prev-page }}'>\n\t\t{{ pfy-previous-page-text }}\n\t\t</a>";
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
            PageFactory::$trans->setVariable('pfy-next-page-title', (string)$next->title());
            $nextUrl = $next->url();
            $nextLink = "<a href='$nextUrl' title='{{ pfy-link-to-next-page }}'>\n\t\t{{ pfy-next-page-text }}\n\t\t</a>";
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




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;

