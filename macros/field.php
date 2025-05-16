<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'name' => ['Name of the page\'s data field.', 'ContentBlocks'],
            'class' => ['Class applied to the wrapper tag.', 'pfy-field'],
            'wrapperClass' => ['Synonym for "class".', null],
            'wrapperTag' => ['Wrapper tag applied to the output.', 'div'],
            'markdown' => ['If true, field value will be markdown compiled.', false],
            'literal' => ['If true, field value will be rendered as is.', false],
        ],
        'summary' => <<<EOT

# $funcName()

With Kirby you can define so called "fields", essentially configurable 
paage content elements which you can set and modify in Kirby's panel
(or in page's meta-files).

``ContentBlock`` (the default field) lets you assemble page content from basic building blocks,
such as titles, text, images etc.

**Note:**:  
Use option "markdown: true" to markdown compile content.  
MarkdownPlus syntax extensions are active in this case.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    // assemble output:
    $str .= '';
    $name = $options['name'];
    $class = $options['wrapperClass'] ?: $options['class'];
    $tag = $options['wrapperTag'];

    // get field from kirby:
    $out = page()->$name()->value();

    if ($options['literal']) {
        $str .= shieldStr($out);

    } else {
        // markdown compile:
        if ($out && $options['markdown']) {
            $out = markdown($out);
        }

        // resolve variables and macros:
        if (str_contains($out, "{{")) {
            $out = TransVars::translate($out);
        }
        $out = <<<EOT
<$tag class="$class">
$out
</$tag>

EOT;
    }
    return $str . $out;
};
