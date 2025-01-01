<?php

// Defaults recommended by PageFactory plugin:
return [
   'debug'  => true,
    'pgfactory.pagefactory.options' => [
        'debug_compileScssWithSrcRef' => true, // injects refs to source SCSS file&line in compiled CSS
    ],
    // disable caching on localhost:
    'cache' => [
        'pages' => [
            'active' => false,
        ],
    ],
];
