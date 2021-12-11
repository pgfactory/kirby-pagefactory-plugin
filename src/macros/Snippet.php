<?php

/*
 * Macro Snippet()
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'snippet' => ['[filename] Loads given Kirby snippet, compiles it and '.
            'injects the resulting string into the HTML output.', false],
    ],
    'summary' => <<<EOT
Loads a Kirby snippet.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class Snippet extends Macros
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
    public function render(array $args, string $argStr): string
    {
        $str = '';
        $snippet = $args['snippet'];

        if ($snippet) {
            $file = resolvePath($snippet);
            if (!file_exists($file)) {
                $file = "site/snippets/$file";
            }
            $snippetStr = @file_get_contents($file);
            if ($snippetStr) {
                $this->handlePrematureOutput();
                eval(" ?>$snippetStr<?php");
                $str = ob_get_contents();
                ob_clean();
            }
        }

        return $str;
    } // render


    /**
     * While PageFactory is in process, output to stdout consists of error messages.
     * If there is such output, it is caught in PHP's output_buffer.
     * In contrast, Kirby snippets send their output directly to stdout. Thus, we need to make sure that the
     * output_buffer is not polluted at this point.
     * @return void
     */
    private function handlePrematureOutput()
    {
        if (ob_get_length()) {
            if (!file_exists(PFY_LOGS_PATH)) {
                mkdir(PFY_LOGS_PATH);
            }
            file_put_contents('site/log/prematureOutput.txt', ob_get_contents());
            ob_clean();
        }
    } // handlePrematureOutput
} // Import




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;


