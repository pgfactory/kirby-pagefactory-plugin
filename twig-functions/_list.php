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
            'type' => ['[users,variables,functions] Selects the objects to be listed.', false],
            //'options' => ['[short] Specifies how to render the list.', false],
            //'option' => ['Synonym for "options".', false],
        ],
        'summary' => <<<EOT
# list()

Renders a list of requested type.

Available ``types``:

- ``variables``     >> lists all variables
- ``macros``     >> lists all macros including their help text
- ``users``     >> lists all users

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $sourceCode, $inx) = $str;
    }

    // assemble output:
    $type = $args['type'].' ';

    if ($type[0] === 'v') {     // variables
        $str = PageFactory::$trans->renderVariables();

    } elseif ($type[0] === 'f') {   // functions
        $str = renderTwigFunctions();

    } elseif ($type[0] === 'u') {   // users
        $users = kirby()->users();
        $str = "<ul>\n";
        foreach ($users->data() as $user) {
            $username = (string)$user->name();
            $email = (string)$user->email();
            $str .= "\t<li>$username &lt;$email&gt;</li>\n";
        }
        $str .= "</ul>\n";

    }
    $str = <<<EOT
$sourceCode
<div class='pfy-list pfy-list-$inx'>
$str
</div>
EOT;

    return $str;
}

