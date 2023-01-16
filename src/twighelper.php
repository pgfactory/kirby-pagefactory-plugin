<?php
namespace Usility\PageFactory;


/**
 * Finds available twig-functions
 * @param bool $forRegistering
 * @return array
 */
function findTwigFunctions(bool $forRegistering = false): array
{
    $functions = [];
    $dir = glob("site/plugins/pagefactory/twig-functions/*.php");
    foreach ($dir as $file) {
        $actualName = basename($file, '.php');
        if ($actualName[0] !== '#') {
            if ($forRegistering) {
                $name = ltrim($actualName, '_');
                $functions["*$name"] = "Usility\\PageFactory\\$actualName";
            } else {
                $functions[] = ltrim($actualName, '_');
            }
        }
    }
    return $functions;
} // findTwigFunctions