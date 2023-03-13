<?php

// find twig-functions in pagefactory:
$functions = false;
if (file_exists('site/plugins/pagefactory/src/TransVars.php')) {
    require_once 'site/plugins/pagefactory/src/TransVars.php';
    $functions = Usility\PageFactory\TransVars::findAllMacros(true);
}

// Defaults recommended by PageFactory plugin:
return [
    'debug' => false,
    'smartypants' => true,
    'languages' => true, // enables language option in Panel

    'amteich.twig.env.functions' => $functions, // register pagefactory's twig-functions
    //'amteich.twig.cache' => false,            // disable if necessary

    'usility.pagefactory.options' => [
        'default-nav'   => true,            // set to true when using the built-in nav() function
        //'timezone'		=> 'Europe/Zurich', // Override PageFactory's guess if necessary
    ],

];
