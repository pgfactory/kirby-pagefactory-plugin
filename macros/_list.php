<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function _list($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'type' => ['[users,variables,functions,subpages] Selects the objects to be listed.', false],
            'page' => ['Defines the page of which to list subpages.', '\~page/'],
            'options' => ['[AS_LINKS] Specifies how to render the list.', false],
        ],
        'summary' => <<<EOT
# list()

Renders a list of requested type.

Available ``types``:

- ``variables``     >> lists all variables
- ``macros``     >> lists all macros including their help text
- ``users``     >> lists all users
- ``subpages``     >> lists all sub-pages

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $sourceCode, $inx) = $str;
    }

    // assemble output:
    $type = $args['type'].' ';
    $asLinks = str_contains(strtoupper($args['options']), 'AS_LINKS');
    $class = '';

    if ($type[0] === 'v') {     // variables
        $str = TransVars::renderVariables();
        $class = 'pfy-variables';

    } elseif ($type[0] === 'm') {   // macros
        $str = TransVars::renderMacros();
        $class = 'pfy-macros';

    } elseif ($type[0] === 'u') {   // users
        $users = kirby()->users();
        $str = '';
        foreach ($users->data() as $user) {
            $username = (string)$user->name();
            $email = (string)$user->email();
            $str .= "<li>$username &lt;$email&gt;</li>\n";
        }
        if ($str) {
            $str = "<ul>\n$str</ul>\n";
        } else {
            $text = TransVars::getVariable('pfy-list-empty', true);
            $str = "<div class='pfy-list-empty'>$text</div>";
        }
        $class = 'pfy-users';

    } elseif ($type[0] === 's') {   // sub-pages
        $page = $args['page'];
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
        $class = 'pfy-subpages';
    }
    $str = <<<EOT
$sourceCode
<div class='pfy-list pfy-list-$inx $class'>
$str
</div>
EOT;

    return $str;
}

