<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro
 */
return function($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'file' => ['', false],
        ],
        'summary' => <<<EOT

# css()

Renders a \<link> tag for given CSS file.
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
    if (!$file = ($options['file']??false)) {
        throw new \Exception("Error: file '$file' not found");
    }
    $file = Utils::resolveUrls($file, forResoucres: true);
    $str .= css($file);

    return $str;
};
