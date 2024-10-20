<?php
namespace PgFactory\PageFactory;

require_once dirname(__DIR__).'/src/Import.php';


return function($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['[filename] Loads given file and injects its content into the page. '.
            'If "file" contains glob-style wildcards, then all matching files will be loaded. '.
            'If file-extension is ".md", loaded content will be markdown-compiled automatically.', false],
            'subfolder' => ['Looks for file(s) in given sub-folders, e.g. ``subfolder:*``.', false],
            'template' => ['.', false],
            'literal' => ['If true, file content will be rendered as is - i.e. in \<pre> tags.', false],
            'highlight' => ['(true|list-of-markers) If true, patters ``&#96;``, ``&#96;&#96;`` and ``&#96;&#96;&#96;` are used. '.
                'These patterns will be detected and wrapped in "&lt;span class=\'hl{n}\'> elements".', false],
            'translate' => ['If true, variables and macros inside imported files will be translated resp. executed.', false],
            'mdCompile' => ['If true, file content will be markdown-compiled before rendering.', false],
            'wrapperTag' => ['If defined, output will be wrapped in given tag.', false],
            'wrapperClass' => ['If defined, given class will be applied to the wrapper tag.', ''],
            'elemHeader' => ['If defined, given text is prepended to the imported content.', ''],
            'elemFooter' => ['If defined, given text is appended to the imported content.', ''],
        ],
        'summary' => <<<EOT
# import()

Loads content from a file.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $source) = $str;
    }

    // assemble output:
    $str = Import::render($args);

    return $source.$str;
};


