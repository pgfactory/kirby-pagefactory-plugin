<?php
namespace Usility\PageFactory;

/*
 * Twig function
 */

function import($args = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'file' => ['[filename] Loads given file and injects its content into the page. '.
            'If "file" contains glob-style wildcards, then all matching files will be loaded. '.
            'If file-extension is ".md", loaded content will be markdown-compiled automatically.', false],
//        'snippet' => ['[filename] Loads given Kirby snippet, compiles it and '.
//            'injects the resulting string into the HTML output.', false],
            'literal' => ['If true, file content will be rendered as is - i.e. in \<pre> tags.', false],
            'mdCompile' => ['If true, file content will be markdown-compiled before rendering.', false],
            'wrapperTag' => ['If defined, output will be wrapped in given tag.', false],
            'wrapperClass' => ['If defined, given class will be applied to the wrapper tag.', ''],
        ],
        'summary' => <<<EOT
# import()

Loads content from a file.
EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = prepareTwigFunction(__FILE__, $config, $args))) {
        return $str;
    } else {
        list($str, $options, $inx, $funcName) = $str;
    }

    // assemble output:
    $imp = new Import();
    $str .= $imp->render($options, $args);

    $str = shieldStr($str);

    return $str;
}



class Import
{
    public static $inx = 1;


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
//        $snippet = $args['snippet'];
        $literal = $args['literal'];
        $wrapperTag = $args['wrapperTag'];
        $wrapperClass = $args['wrapperClass'];
        $this->mdCompile = $args['mdCompile'];

        $str = '';
        // handle 'file':
        if ($file) {
            $str = $this->importFile($file);
        }

        // handle 'snippet':
//        if ($snippet) {
//            $str .= $this->importSnippet($snippet);
//        }

        if ($literal && !$wrapperTag) {
            $wrapperTag = 'pre';
        }

        if ($wrapperTag) {
            $str = <<<EOT

    <$wrapperTag class='pfy-imported pfy-imported-$inx $wrapperClass'>
$str</$wrapperTag>

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
            if (($file[0]??false) !== '~') {
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
            if (($file[0]??false) !== '~') {
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
$class = '';
$image = '';
$src = 'https://www.youtube.com/watch?v=9ocG1FWjTbk';
$id = '9ocG1FWjTbk';
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
