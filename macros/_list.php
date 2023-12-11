<?php
namespace PgFactory\PageFactory;

/*
 * Twig function
 */

/**
 * @param $argStr
 * @return array|string
 * @throws \Kirby\Exception\InvalidArgumentException
 */
function _list($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'type' => ['[users,variables,functions,subpages] Selects the objects to be listed.', false],
            'page' => ['Defines the page of which to list subpages.', '\~page/'],
            'asLinks' => ['If true and type=subpages, listed elements are wrapped in &lt;a> tags.', false],
            'wrapperTag' => ['Defines the wrapper tag. If false, no wrapper is applied.', 'div'],
            'options' => ['{template,prefix,suffix,separator,listWrapperTag} Specifies how to render the list.', false],
        ],
        'summary' => <<<EOT
# list()

Renders a list of requested type.

Available ``types``:

- ``variables``     10em>> lists all variables
- ``macros``        10em>> lists all macros including their help text
- ``users``         10em>> lists all users
- ``subpages``      10em>> lists all sub-pages

Available ``options`` for  type **users**:

- ``template``      10em>> should contain placeholders like '%name%' and '%email%' or any fields defined in Kirby's user admin
- ``prefix``        10em>> string to prepend to each element
- ``suffix``        10em>>  string to append to each element
- ``separator``     10em>>  string placed between elements
- ``listWrapperTag``    10em>> 'ul' or 'ol' or tag or false (for no wrapper) 

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $sourceCode, $inx) = $str;
    }

    $options = $args['options'];
    if (is_string($options)) {
        $options = [$options];
    }

    // assemble output:
    $type = $args['type'].' ';
    $asLinks = $args['asLinks']??false;
    $class = '';

    if ($type[0] === 'v') {     // variables
        $str = TransVars::renderVariables();
        $str = shieldStr($str);
        $class = 'pfy-variables';

    } elseif ($type[0] === 'm') {   // macros
        $str = TransVars::renderMacros();
        $class = 'pfy-macros';

    } elseif ($type[0] === 'u') {   // users
        $str = renderUserList($args);
        $class = 'pfy-users';

    } elseif ($type[0] === 's') {   // sub-pages
        $str = renderSubpages($args['page'], $asLinks);
        $class = 'pfy-subpages';
    }

    if ($tag = $args['wrapperTag']) {
        $str = <<<EOT
<$tag class='pfy-list pfy-list-$inx $class'>
$str
</$tag>
EOT;
    }

    return $sourceCode.$str;
}


/**
 * @param $page1
 * @param bool $asLinks
 * @return string
 */
function renderSubpages($page1, bool $asLinks): string
{
    $page = $page1;
    if ($page === '~page/' || $page === '\\~page/') {
        $pages = page()->children()->listed();
    } elseif ($page === '/' || $page === '~/') {
        $pages = site()->children()->listed();
    } else {
        $pages = page($page)->children()->listed();
    }
    $str = '';
    foreach ($pages as $page) {
        $elem = $page->title()->value();
        if ($asLinks) {
            $url = $page->url();
            $elem = "<a href='$url'>$elem</a>";
        }
        $str .= "<li>$elem</li>\n";
    }
    if ($str) {
        $str = "<ul>\n$str</ul>\n";
    } else {
        $text = TransVars::getVariable('pfy-list-empty', true);
        $str = "<div class='pfy-list-empty'>$text</div>";
    }
    return $str;
} // renderSubpages


/**
 * @return string
 */
function renderUserList($args): string
{
    $options = (array)$args['options']??[];

    $str = Utils::getUsers($options);

    if (!$str) {
        $text = TransVars::getVariable('pfy-list-empty', true);
        $str = "<div class='pfy-list-empty'>$text</div>";
    } else {
        $wrapperTag = $options['wrapperTag']??'ul';
        if ($wrapperTag) {
            $str = "<$wrapperTag>$str</$wrapperTag>\n";
        }
    }
    return $str;
} // renderUserList

