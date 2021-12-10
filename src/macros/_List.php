<?php

/*
 *
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'type' => ['[variables, users]', false],
    ],
    'summary' => <<<EOT
Renders a list of requested type.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class _List extends Macros
{
    public static $inx = 1;
    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Macro rendering method
     * @param $args                     // array of arguments
     * @param $argStr                   // original argument string
     * @return string                   // HTML or Markdown
     */
    public function render($args, $argStr)
    {
        $inx = self::$inx++;

        $type = $args['type'];
        $str = '';

        if ($type === 'variables') {
            $str = PageFactory::$trans->render();
        }
        $str = <<<EOT
    <div class='lzy-list lzy-list-$inx'>
$str
    </div>
EOT;


        return $str;
    }
}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
