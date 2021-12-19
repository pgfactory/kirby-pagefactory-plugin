<?php

/*
 * Macro Import()
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'file' => ['[filename] Loads given file and injects its content into the page. '.
            'If "file" contains glob-style wildcards, then all matching files will be loaded. '.
            'If file-extension is ".md", loaded content will be markdown-compiled automatically.', false],
        'snippet' => ['[filename] Loads given Kirby snippet, compiles it and '.
            'injects the resulting string into the HTML output.', false],
        'mdCompile' => ['If true, file content will be markdown-compiled before rendering.', false],
        'wrapperTag' => ['If defined, output will be wrapped in given tag.', false],
        'wrapperClass' => ['If defined, given class will be applied to the wrapper tag.', ''],
    ],
    'summary' => <<<EOT
Loads content from a file.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class Import extends Macros
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
        $inx = self::$inx++;

        $file = $args['file'];
        $snippet = $args['snippet'];
        $wrapperTag = $args['wrapperTag'];
        $wrapperClass = $args['wrapperClass'];
        $this->mdCompile = $args['mdCompile'];

        $str = '';
        // handle 'file':
        if ($file) {
            $str = $this->importFile($file);
        }

        // handle 'snippet':
        if ($snippet) {
            $str .= $this->importSnippet($snippet);
        }

        if ($wrapperTag) {
            $str = <<<EOT

    <$wrapperTag class='lzy-imported lzy-imported-$inx $wrapperClass'>
$str    </$wrapperTag><!-- lzy-imported -->

EOT;
        }

        return $str;
    } // render


    /**
     * Imports file(s), markdown-compiles it if necessary
     * @param string $file
     * @return string
     */
    private function importFile(string $file): string
    {
        $str = '';
        if ($file && (((strpbrk($file, '*{') !== false)) || ($file[strlen($file)-1] === '/'))) {
            if (@$file[0] !== '~') {
                $file = "~page/$file";
            }
            $file = resolvePath($file);
            $files = getDir($file);
            foreach ($files as $key => $file) {
                $files[$key] = "~/$file";
            }
        } else {
            $files = [$file];
        }
        foreach ($files as $file) {
            if (@$file[0] !== '~') {
                $file = "~page/$file";
            }
            $file = resolvePath($file);
            $s = @file_get_contents($file);
            if ($this->mdCompile || (fileExt($file) === 'md')) {
                $s = compileMarkdown($s);
            }
            $str .= $s;
        }
        return $str;
    } // importFIle


    /**
     * Imports a Kirby Snippet
     * @param string $snippet
     * @return string
     */
    private function importSnippet(string $snippet): string
    {
        $file = resolvePath($snippet);
        if (!file_exists($file)) {
            $file = "site/snippets/$file";
        }
        $str = '';
        $snippetStr = @file_get_contents($file);
        if ($snippetStr) {
            $this->handlePrematureOutput();
            eval(" ?>$snippetStr<?php");
            $str = ob_get_contents();
            ob_clean();
        }
        return $str;
    } // importSnippet


    /**
     * While PageFactory is in process, output to stdout consists of error messages.
     * If there is such output, it is caught in PHP's output_buffer.
     * In contrast, Kirby snippets send their output directly to stdout. Thus, we need to make sure that the
     * output_buffer is not polluted at this point.
     * @return void
     */
    private function handlePrematureOutput(): void
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


