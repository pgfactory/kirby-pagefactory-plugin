<?php

// find twig-functions in pagefactory:
if (file_exists('site/plugins/pagefactory/src/twighelper.php')) {
    require_once 'site/plugins/pagefactory/src/twighelper.php';
    $functions = Usility\PageFactory\findTwigFunctions(true);
} else {
    $function = false;
}

// Defaults recommended by PageFactory plugin:
return [
    'smartypants' => true,
    'languages' => true, // enables language option in Panel

    'amteich.twig.env.functions' => $functions, // register pagefactory's twig-functions
    //'amteich.twig.cache' => false,            // disable if necessary

    'usility.pagefactory.options' => [
        'default-nav'   => true,            // set to true when using the built-in nav() function
        //'timezone'		=> 'Europe/Zurich', // Override PageFactory's guess if necessary
    ],

];
