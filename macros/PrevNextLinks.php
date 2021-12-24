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
        $class = $args['class'];        // <- how to access an argument

        $out = '';
        if ($this->page->hasPrevListed()) {
            $prev = '~/'.$this->page->prevListed();
            $prevLink ="<a href='$prev'>{{ lzy-previous-page-text }}";
            $out .= <<<EOT
    <div class="lzy-page-switcher-links lzy-previous-page-link $class">
        $prevLink
    </div>

EOT;

        }

        if ($this->page->hasNextListed()) {
            $next = '~/'.$this->page->nextListed();
            $nextLink = "<a href='$next'>{{ lzy-next-page-text }}";
            $out .= <<<EOT
    <div class="lzy-page-switcher-links lzy-next-page-link $class">
        $nextLink
    </div>

EOT;
        }

        return $out;
    }
} // PrevNextLinks




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;

