<?php

// Defaults recommended by PageFactory plugin:
return [
   'debug'  => true,
    'pgfactory.pagefactory.options' => [
        'debug_checkMetaFiles'        => true, // if false, Pagefactory will not check presence of metafiles
        'debug_compileScssWithSrcRef' => true, // injects ref to source SCSS file&line in compiled CSS
    ],
];
