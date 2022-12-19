<?php

/*
 * 
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'parameters' => [
        '' => ['', false],
    ],
    'summary' => <<<EOT
[Short description of macro.]
EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class replace_with_filename extends Macros
{
    public static $inx = 1;

    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render(array $args, string $argStr): string
    {
        $inx = self::$inx++;

        // $arg = $args['my-arg'];
        $str = '';

        return $str;
    } // render
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
