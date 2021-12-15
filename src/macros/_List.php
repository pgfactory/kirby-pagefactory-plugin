<?php

/*
 *
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'type' => ['Selects the objects to be listed.', false],
        'options' => ['[short] Specifies how to render the list.', false],
        'option' => ['Synonym for "options".', false],
    ],
    'summary' => <<<EOT
Renders a list of requested type.

Available ``types``:

- ``variables``     >> lists all variables
- ``macros``     >> lists all macros including their help text
- ``users``     >> lists all users

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
        $options = $args['options'];
        $options .= $args['option'];
        $str = '';

        if ($type === 'variables') {
            $str = PageFactory::$trans->render();

        } elseif ($type === 'macros') {
            $str = parent::listMacros($options);

        } elseif ($type === 'users') {
            $users = $this->pfy->kirby->users();
            $str = "\t<h2>{{ lzy-user-list }}</h2>\n\t<ul>\n";
            foreach ($users->data() as $user) {
                $username = (string)$user->name();
                $email = (string)$user->email();
                $str .= "\t\t<li>$username &lt;$email&gt;</li>\n";
            }
            $str .= "\t</ul>\n";

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
