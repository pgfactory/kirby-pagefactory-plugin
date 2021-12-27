<?php

/*
 * PageFactory Macro Template
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ), // <- don't modify
    'parameters' => [
        'class' => ['Class to be applied to the element', false],              // <- add your arguments here
    ],
    'summary' => <<<EOT
Renders two links, one to the next page, one to the previous page. Activates scripts to trigger on cursor 
keys <- (left) and  -> (right).

Place these links using classes ``.lzy-previous-page-link`` and ``.lzy-next-page-link``.

Use variables ``\{{ lzy-previous-page-text }}`` and ``\{{ lzy-next-page-text }}`` to define the text (visible and invisible parts).

EOT,                                    // <- Help text to explain function of this macro
    'mdCompile' => false,               // <- whether output needs to be markdown-compiled
    'assetsToLoad' => [
        'site/plugins/pagefactory/scss/page-switcher.scss',
        'site/plugins/pagefactory/js/page-switcher.js',
    ],
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
     * Macro rendering method
     * @param $args                     // array of arguments
     * @param $argStr                   // original argument string
     * @return string                   // HTML or Markdown
     */
    public function render($args, $argStr)
    {
        $this->class = $args['class'];        // <- how to access an argument

        $out = $this->renderPrevLink();
        $out .= $this->renderNextLink();

        return $out;
    }


    private function renderPrevLink()
    {
        $out = '';
        $current = $this->page->url();
        $next = $this->pages->first();
        $prev = false;
        while ($next && ($current !== $next->url())) {
            $prev = $next->url();
            if ($next->hasListedChildren()) {
                     $next = $next->children()->listed()->first();

            } elseif ($next->hasNextListed()) {
                $next = $next->nextListed();

            } elseif ($next->parent()->hasNextListed()) {
                $next = $next->parent()->nextListed();
            }
        }

        if ($prev) {
            $prevLink = "<a href='$prev'>{{ lzy-previous-page-text }}";
            $out = <<<EOT
    <div class="lzy-page-switcher-links lzy-previous-page-link $this->class">
        $prevLink
    </div>

EOT;
        }
        return $out;
    } // renderPrevLink



    private function renderNextLink()
    {
        $out = '';
        $next = '';
        if ($this->page->hasListedChildren()) {
            $next = $this->page->children()->listed()->first()->url();

        } elseif ($this->page->hasNextListed()) {
            $next = $this->page->nextListed()->url();

        } elseif ($this->page->parent()->hasNextListed()) {
            $next = $this->page->parent()->nextListed()->url();
        }
        if ($next) {
            $nextLink = "<a href='$next'>{{ lzy-next-page-text }}";
            $out = <<<EOT
    <div class="lzy-page-switcher-links lzy-next-page-link $this->class">
        $nextLink
    </div>

EOT;
        }
        return $out;
    } // renderNextLink
} // PrevNextLinks




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;

