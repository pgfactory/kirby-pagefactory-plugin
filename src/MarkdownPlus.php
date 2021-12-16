<?php
namespace Usility\PageFactory;

use Exception;

/*
 * MarkdownPlus extends \cebe\markdown\MarkdownExtra
 * Why not Kirby's native ParsedownExtra?
 *  -> no access to array of lines surrounding current line -> not possible to inject lines
 *  -> required by DivBlock pattern
 */


class MarkdownPlus extends \cebe\markdown\MarkdownExtra
{
    private static $asciiTableInx = 0;
    private static $imageInx = 0;
    // skip is a pseudo tag:
    private $inlineTags = ',a,abbr,acronym,b,bdo,big,br,button,cite,code,dfn,em,i,img,input,kbd,label,'.
            'map,object,output,q,samp,script,select,small,span,strong,sub,sup,textarea,time,tt,var,skip,';

    public function __construct()
    {
        $this->kirby        = kirby();
        $this->lang         = PageFactory::$lang;
        $this->langCode     = PageFactory::$langCode;
        $this->trans        = PageFactory::$trans;
    }


    // compile including pre- and postprocessing:
    public function compile($str)
    {
        if (!$str) {
            return '';
        }
        $str = $this->preprocess($str);
        $html = parent::parse($str);
        $html = $this->postprocess($html);
        return $html;
    }


    // compile without pre- and postprocessing:
    public function text($str)
    {
        $html = parent::parse($str);
        return $html;
    }



    // === AsciiTable ==================
    protected function identifyAsciiTable($line, $lines, $current)
    {
        // asciiTable starts with '|==='
        if (strncmp($line, '|===', 4) === 0) {
            return 'asciiTable';
        }
        return false;
    }

    protected function consumeAsciiTable($lines, $current)
    {
        $block = [
            'asciiTable',
            'content' => [],
            'caption' => false,
            'args' => false
        ];
        $firstLine = $lines[$current];
        if (preg_match('/^\|===*\s+{?:?(.+?)}?$/', $firstLine, $m)) {
            $block['args'] = $m[1];
        }
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (strncmp($line, '|===', 4) !== 0) {
                $block['content'][] = $line;
            } else {
                // stop consuming when code block is over
                break;
            }
        }
        return [$block, $i];
    }

    protected function renderAsciiTable($block)
    {
        $table = [];
        $nCols = 0;
        $row = 0;
        $col = -1;

        $inx = self::$asciiTableInx;
        $inx++;

        for ($i = 0; $i < sizeof($block['content']); $i++) {
            $line = $block['content'][$i];

            if (strncmp($line, '|---', 4) === 0) {  // new row
                $row++;
                $col = -1;
                continue;
            }

            if (isset($line[0]) && ($line[0] === '|')) {  // next cell starts
                $line = substr($line,1);
                $cells = preg_split('/\s(?<!\\\)\|/', $line); // pattern is ' |'
                foreach ($cells as $cell) {
                    if ($cell && ($cell[0] === '>')) {
                        $cells2 = explode('|', $cell);
                        foreach ($cells2 as $j => $c) {
                            $col++;
                            $table[$row][$col] = $c;
                        }
                        unset($cells2);
                        unset($c);
                    } else {
                        $col++;
                        $table[$row][$col] = str_replace('\|', '|', $cell);
                    }
                }

            } else {
                $table[$row][$col] .= "\n$line";
            }
            $nCols = max($nCols, $col);
        }
        $nCols++;
        $nRows = $row+1;
        unset($cells);


        $id = $class = $style = $attr = $text = $tag = '';
        if ($block['args']) {
            $args = parseInlineBlockArguments($block['args'], true);
            list($tag, $id, $class, $style, $attr, $text) = array_values($args);
            $attr = $args['htmlAttrs'];
        }
        if (!$id) {
            $id = "lzy-table$inx";
        }
        if (!$class) {
            $class = "lzy-table$inx";
        }
        if ($style) {
            $style = " style='$style'";
        }
        if ($tag) {
            $text = $text ? "$tag $text": $tag;
        }

        // now render the table:
        $out = "\t<table id='$id' class='lzy-table $class'$style$attr><!-- asciiTable -->\n";
        if ($text) {
            $out .= "\t  <caption>$text</caption>\n";
        }

        // render header as defined in first row, e.g. |# H1|H2
        $row = 0;
        if (isset($table[0][0]) && ($table[0][0][0] === '#')) {
            $row = 1;
            $table[0][0] = substr($table[0][0],1);
            $out .= "\t  <thead>\n";
            for ($col = 0; $col < $nCols; $col++) {
                $cell = isset($table[0][$col]) ? $table[0][$col] : '';
                $cell = parent::parseParagraph(trim($cell));
                $cell = trim($cell);
                if (preg_match('|^<p>(.*)</p>$|', $cell, $m)) {
                    $cell = $m[1];
                }
                $out .= "\t\t\t<th class='th$col'>$cell</th>\n";
            }
            $out .= "\t  </thead>\n";
        }

        $out .= "\t  <tbody>\n";
        for (; $row < $nRows; $row++) {
            $out .= "\t\t<tr>\n";
            $colspan = 1;
            for ($col = 0; $col < $nCols; $col++) {
                $cell = isset($table[$row][$col]) ? $table[$row][$col] : '';
                if ($cell === '>') {    // colspan?  e.g. |>|
                    $colspan++;
                    continue;
                } elseif ($cell) {
                    $cell = parent::parseParagraph(trim($cell));
                }
                $colspanAttr = '';
                if ($colspan > 1) {
                    $colspanAttr = " colspan='$colspan'";
                }
                $out .= "\t\t\t<td class='row".($row+1)." col".($col+1)."'$colspanAttr>$cell</td>\n";
                $colspan = 1;
            }
            $out .= "\t\t</tr>\n";
        }

        $out .= "\t  </tbody>\n";
        $out .= "\t</table><!-- /asciiTable -->\n";

        return $out;
    } // AsciiTable




    // === DivBlock ==================
    protected function identifyDivBlock($line, $lines, $current)
    {
        // if a line starts with at least 3 colons it is identified as a div-block
        if (preg_match('/^[:$@]{3,10}\s+\S/', $line)) {
            return 'divBlock';
        }
        return false;
    } // identifyDivBlock

    protected function consumeDivBlock($lines, $current)
    {
        $line = rtrim($lines[$current]);
        // create block array
        $block = [
            'divBlock',
            'content' => [],
            'tag' => 'div',
            'marker' => $line[0],
            'attributes' => '',
            'literal' => false,
            'lzyBlockType' => true
        ];

        // detect class or id and fence length (can be more than 3 backticks)
        $depth = 0;
        $marker = $block['marker'];
        if (preg_match("/($marker{3,10})(.*)/",$line, $m)) {
            $fence = $m[1];
            $rest = trim($m[2]);
            if ($rest && ($rest[0] === '{')) {      // non-lzy block: e.g. "::: {#id}
                $block['lzyBlockType'] = false;
                $depth = 1;
                $rest = trim(str_replace(['{','}'], '', $rest));
            }
        } else {
            throw new Exception("Error in Markdown source line $current: $line");
        }
        $attrs = parseInlineBlockArguments($rest);

        $tag = $attrs['tag'];
        if (stripos($this->inlineTags, ",$tag,") !== false) {
            if ($attrs['literal'] === null) {
                $attrs['literal'] = true;
            }
        }

        $block['tag'] = $tag ? $tag : 'div';
        $block['attributes'] = $attrs['htmlAttrs'];
        $block['lang'] = $attrs['lang'];;
        $block['literal'] = $attrs['literal'];;
        $block['meta'] = '';
        $block['mdCompile'] = ($attrs['mdCompile'] !== false);

        // consume all lines until end-tag, i.e. :::...
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match("/^($marker{3,10})\s*(.*)/", $line, $m)) { // it's a potential fence line
                $fenceEndCandidate = $m[1];
                $rest = $m[2];
                if ($fence === $fenceEndCandidate) {    // end tag we have to consider:
                    if ($rest !== '') {    // case nested or consequitive block
                        if ($block['lzyBlockType']) {   // lzy-style -> consecutive block starts:
                            $i--;
                            break;
                        }
                        $depth++;

                    } else {                    // end of block
                        $depth--;
                        if ($depth < 1) {       // only in case of non-lzyBlocks we may have to skip nested end-tags:
                            break;
                        }
                    }
                }
            }
            $block['content'][] = $line;
        }

        $content = implode("\n", $block['content']);
        if ($block['literal'] || ($block['mdCompile'] === false)) {
            unset($block['content']);
            $block['content'][0] = shieldStr($content);

        } else {
            unset($block['content']);
            $block['content'][0] = parent::parseParagraph($content);
        }
        return [$block, $i];
    } // consumeDivBlock

    protected function renderDivBlock($block)
    {
        $tag = $block['tag'];
        $attrs = $block['attributes'];

        // exclude blocks with lang option set but is not current language:
        if ($block['lang'] && ($block['lang'] !== $this->lang)) {
            return '';
        }

        $out = $block['content'][0];

        if (strpos($block['meta'], 'html') !== false) {
            return $out;
        }

        return "\t\t<$tag $attrs>\n$out\n\t\t</$tag>\n\n";
    } // renderDivBlock




    // === Tabulator ==================
    protected function identifyTabulator($line, $lines, $current)
    {
        if (preg_match('/(\s\s|\t) ([.\d]{1,3}\w{1,2})? >> [\s\t]/x', $line)) { // identify patterns like '{{ tab( 7em ) }}'
            return 'tabulator';
        }
        return false;
    } // identifyTabulator

    protected function consumeTabulator($lines, $current)
    {
        $block = [
            'tabulator',
            'content' => [],
            'width' => '6em',
        ];

        $last = $current;
        // consume following lines containing >>
        for($i = $current, $count = count($lines); $i <= $count-1; $i++) {
            $line = $lines[$i];
            if (preg_match('/([.\d]{1,3}\w{1,2})? >> [\s\t]/x', $line, $m)) {
                $block['content'][] = $line;
                if (@$m[1]) {
                    $j = sizeof($block['content'])-1;
                    $block['widths'][ $j ] = $m[1];
                }
                $last = $i;
            } elseif (empty($line)) {
                continue;
            } else {
                break;
            }
        }
        return [$block, $last];
    } // consumeTabulator

    protected function renderTabulator($block)
    {
        $out = '';
        foreach ($block['content'] as $l) {
            $parts = preg_split('/[\s\t]* ([.\d]{1,3}\w{1,2})? >> [\s\t]/x', $l);
            $line = '';
            foreach ($parts as $n => $elem) {
                $style = '';
                if ($w = @$block['widths'][$n]) {
                    $style = " style='width:$w';";
                } elseif ($n === 0) {
                    $style = " style='width:{$block['width']}';";
                } elseif (($n === 1) && ($w = @$block['widths'][$n-1])) {
                    $style = " style='width:calc(100% - $w - 0.4em)';";
                }
                $elem = parent::parseParagraph($elem);
                $line .= "<span class='c".($n+1)."'$style>$elem</span>";
            }
            $out .= "<div class='lzy-tabulator-wrapper'>$line</div>\n";
        }
        return $out;
    } // renderTabulator




    // === DefinitionList ==================
    protected function identifyDefinitionList($line, $lines, $current)
    {
        // if next line starts with ': ', it's a dl:
        if (isset($lines[$current+1]) && strncmp($lines[$current+1], ': ', 2) === 0) {
            return 'definitionList';
        }
        return false;
    } // identifyDefinitionList

    protected function consumeDefinitionList($lines, $current)
    {
        // create block array
        $block = [
            'definitionList',
            'content' => [],
        ];

        // consume all lines until 2 empty line
        $nEmptyLines = 0;
        for($i = $current, $count = count($lines); $i < $count; $i++) {
            if (!preg_match('/\S/', $lines[$i])) {  // empty line
                if ($nEmptyLines++ > 0) {
                    break;
                }
            } else {
                $nEmptyLines = 0;
            }
            $block['content'][] = $lines[$i];
        }
        return [$block, $i];
    } // consumeDefinitionList

    protected function renderDefinitionList($block)
    {
        $out = '';
        $md = '';
        foreach ($block['content'] as $line) {
            if (!trim($line)) {                             // end of definitin item reached
                if ($md) {
                    $html = parent::parseParagraph($md);
                    $html = "\t\t\t".str_replace("\n", "\n\t\t\t", $html);
                    $out .= "$html ";
                    $md = '';
                }
                $out .= "\n\t\t</dd>\n";

            } elseif (preg_match('/^: /', $line)) { // within dd block
                $md .= substr($line, 2) . ' ';
                if (preg_match('/\s\s$/', $md)) { // 2 blanks at end of line -> insert line break
                    $md = rtrim($md) . "<br />\n";
                }

            } else {                                        // new dt block starts
                $line = parent::parseParagraph($line);
                $out .= "\t\t<dt>$line</dt>\n\t\t<dd>\n";
            }
        }
        $out = "\t<dl>\n$out\t</dl>\n";
        return $out;
    } // renderDefinitionList



    /**
     * @marker ~~
     */

    protected function parseStrike($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~~)
        if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['strike', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '~~'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderStrike($element)
    {
        return '<del>' . $this->renderAbsy($element[1]) . '</del>';
    }




    /**
     * @marker ~
     */

    protected function parseSubscript($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^~(.{1,9}?)~/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['subscript', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~ we just return the marker and skip 1 character
        return [['text', '~'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderSubscript($element)
    {
        return '<sub>' . $this->renderAbsy($element[1]) . '</sub>';
    }



    /**
     * @marker ^
     */
    protected function parseSuperscript($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^\^(.{1,20}?)\^/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['superscript', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 1 character
        return [['text', '^'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderSuperscript($element)
    {
        return '<sup>' . $this->renderAbsy($element[1]) . '</sup>';
    }



    /**
     * @marker ==
     */
    protected function parseMarked($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ==)
        if (preg_match('/^==(.+?)==/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['marked', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '=='], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderMarked($element)
    {
        return '<mark>' . $this->renderAbsy($element[1]) . '</mark>';
    }



    /**
     * @marker ++
     */
    protected function parseInserted($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^\+\+(.+?)\+\+/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['inserted', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '++'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderInserted($element)
    {
        return '<ins>' . $this->renderAbsy($element[1]) . '</ins>';
    }



    /**
     * @marker __
     */
    protected function parseUnderlined($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^__(.+?)__/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['underlined', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '__'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderUnderlined($element)
    {
        return '<span class="underline">' . $this->renderAbsy($element[1]) . '</span>';
    }



    /**
     * @marker ``
     */
    protected function parseDoubleBacktick($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing `)
        if (preg_match('/^``(.+?)``/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['doublebacktick', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '``'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderDoubleBacktick($element)
    {
        return "<samp>" . $this->renderAbsy($element[1]) .  "</samp>";
    }




    /**
     * @marker ![
     *
     * ![alt text](img.jpg "Caption...")
     */
    protected function parseImage($markdown)
    {
        if (preg_match('/^!\[ (.+?) ]\( ( (.+?) (["\']) (.+?) \4) \s* \)/x', $markdown, $matches)) {
            return [
                ['image', $matches[1].']('.$matches[2]],
                strlen($matches[0])
            ];
        } elseif (preg_match('/^!\[ (.+?) ]\( (.+?) \)/x', $markdown, $matches)) {
            return [
                ['image', $matches[1].']('.$matches[2]],
                strlen($matches[0])
            ];
        }
        return [['text', '!['], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderImage($element)
    {
        self::$imageInx++;
        $imgInx = self::$imageInx;
        $str = $element[1];
        list($alt, $src) = explode('](', $str);
        if (preg_match('/^ (["\']) (.+) \1 \s* /x', $alt, $m)) {
            $alt = $m[2];
        }
        if (preg_match('/^ (["\']) (.+) \1 \s* /x', $src, $m)) {
            $src = $m[2];
        }
        $alt = str_replace(['"', "'"], ['&quot;','&apos;'], $alt);

        $caption = '';
        if (preg_match('/^ (.*?) \s+ (.*) /x', $src, $m)) {
            $src = $m[1];
            $caption = $m[2];
            if (preg_match('/^ (["\']) (.+) \1 \s* /x', $src, $mm)) {
                $src = $mm[2];
            }
            if (preg_match('/^ (["\']) (.+) \1 \s* /x', $caption, $mm)) {
                $caption = $mm[2];
            }
            $caption = str_replace(['"', "'"], ['&quot;','&apos;'], $caption);
        }

        $size = false;
        if (preg_match('/(.*)\[(.*?)](\.\w+)/', $src, $m)) {
            $src = $m[1].$m[3];
            $size = $m[2];
            list($maxWidth, $maxHeight) = explode('x', $size);
            $maxWidth = $maxWidth? intval($maxWidth): null;
            $maxHeight = $maxHeight? intval($maxHeight): null;
        }

        $src = trim($src);
        $files = page()->files()->filterBy('extension', 'jpg');
        $file = $files->find($src);
        if ($size) {
            $file = $file->resize($maxWidth, $maxHeight);
        }
        $html = $file->html(['alt' => $alt, 'class' => "lzy-img lzy-img-$imgInx"]);

        if ($caption) {
            $html = <<<EOT
    <figure class="lzy-figure lzy-figure-$imgInx">
        $html
        <figcaption>$caption</figcaption>
    </figure>
EOT;
        }
        return $html;
    } // renderImage



    /**
     * @marker [
     *
     * [link text](https://www.google.com)
     */
    protected function parseLink($markdown)
    {
        if (preg_match('/^\[ ([^]]+) ]\(([^)]+) \)/x', $markdown, $matches)) {
            $linkText = $matches[1];
            $link = $matches[2];
            if (strpos($link, ' ') !== false) {
                list($link, $title) = explodeTrim(' ', $link);
            } else {
                $title = '';
            }
            return [
                ['link', $link, $linkText, $title], strlen($matches[0])
            ];
        }
        return [['text', '['], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderLink($element)
    {
        $link = $element[1];
        $linkText = $element[2];
        $title = preg_replace('/^ ([\'"]) (.*) \1 $/x', "$2", $element[3]);
        return \html::link($link, ['text' => $linkText, 'title' => $title]);
    } // renderLink





    /**
     * @marker :
     */
    protected function parseIcon($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/:(\w+):/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['icon', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 1 character
        return [['text', ':'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderIcon($element)
    {
        $iconName = $element[1][0][1];
        $file = SVG_ICONS_PATH . "$iconName.svg";
        if (file_exists($file)) {
            $svg = file_get_contents($file);
            $icon = "<span class='lzy-icon'><span>$svg</span></span>";
        } else {
            $icon = "&#58;$iconName:";
        }
        return $icon;
    }






    private function preprocess($str)
    {
        $str = $this->handleShieldedCharacters($str);

        $str = $this->doMDincludes($str);

        $str = $this->handleMdVariables($str);

        $str = PageFactory::$trans->flattenMacroCalls($str); // need to flatten so MD doesn't get confused

        $str = $this->handleLineBreaks($str);

        // {{}} alone on line -> add NLs around it:
        $str = preg_replace('/(\n{{.*}}\n)/U', "\n$1\n", $str);

        $str = $this->handleOLstart($str);
//ToDo: smartypants are not compatible with macro arguments.
// -> place call after translation?
        // check for smartypants, get them compiled:
        if (@(kirby()->options())['smartypants']) {
            // we need to hide MarkdownPlus' tabulator pattern "\t>> " from SmartyPants:
            $str = preg_replace("/(\t|\s{4,})>>\s/", '@@gt@@', $str);
            $str = smartypants($str);
            $str = str_replace('@@gt@@', "\t>> ", $str); // revert shielding
        }

        return $str;
    } // preprocess


    private function postprocess($str)
    {
        // lines that contain but a variable or macro (e.g. "<p>{{ lorem( help ) }}</p>") -> remove enclosing P-tags:
        $str = preg_replace('|<p> ({{ .*? }}) </p>|xms', "$1", $str);

        // check for kirbytags, get them compiled:
        if (preg_match_all('/(\(\w*:.*?\))/ms', $str, $m)) {
            foreach ($m[1] as $i => $value) {
                $value = strip_tags(str_replace("\n",' ', $value));
                $str1 = kirby()->kirbytags($value);
                $str = str_replace($m[0][$i], $str1, $str);
            }
        }

        $str = $this->catchAndInjectTagAttributs($str); // ... {.cls}

        return $str;
    } // postprocess



    private function handleShieldedCharacters($str)
    {
        $p = 0;
        while ($p=strpos($str, '\\', $p)) {
            $o = ord($str[$p+1]);
            $unicode = "&#$o;";
            $str = substr($str, 0, $p) . $unicode . substr($str, $p+2);
            $p += 2;
        }
        return $str;
    } // handleShieldedCharacters



    private function doMDincludes($str)
    {
        // pattern: (include: -incl.md)
        while (preg_match('/(.*) \n \(include: (.*?) \) (.*)/xms', $str, $m)) {
            $s1 = $m[1];
            $s2 = $m[3];
            $args = null;
            if (preg_match('/^(.*?)\s+(.*)/', trim($m[2]), $mm)) {
                $file = trim($mm[1]);
                $args = $mm[2];
            } else {
                $file = trim($m[2]);
            }
            if (!$file) {
                return $s1.$s2;
            }

            if ($args) {
                $args = parseArgumentStr($args, ' ');
            }
            if (@$file[0] !== '~') {
                $file = "~page/$file";
            }
            $file = resolvePath($file);

            // include folder:
            if (is_dir($file)) {
                $s = '';
                $dir = getDir("$file*.md");

                // includes files from folder, wrap them in an element:
                if ($tag = @$args['wrapperTag']) {
                    if ($class = @$args['class']) {
                        $class = " class='$class'";
                    }
                    foreach ($dir as $file) {
                        $tmp = loadFile($file, 'cstyle');
                        $s .= <<<EOT

:::::::::: <$tag$class
$tmp
::::::::::

EOT;
                    }
                // include files from folder as they are, without a wrapper element:
                } else {
                    foreach ($dir as $file) {
                        $s .= loadFile($file, 'cstyle');
                    }
                }

            // include single file
            } elseif (file_exists($file)) {
                $s = loadFile($file, 'cstyle');
            } else {
                $s = "[include file '$file' not found]";
            }
            $str = $s1.$s.$s2;
        }
        return $str;
    } // doMDincludes



    private function catchAndInjectTagAttributs($str)
    {
        while (preg_match('@^ (.*?) {: ([.#][\w-]+ .*?) } \s* (\n|</\w+>) (.*)$@xms', $str, $m)) {
            $str0 = $m[1];
            $attrs0 = '&#123;' . substr($m[2],1) . '}';
            $attrs = parseInlineBlockArguments($m[2]);
            $attrsStr = $attrs['htmlAttrs'];
            $tag = $m[3];
            if ($tag !== "\n") {
                $tag = preg_replace('/\W/', '', $tag);
                if (strpos(',h1,h2,h3,h4,h5,h6,', ",$tag,") !== false) {
                    $attrsStr = $attrs0; // ignore, already handled by MD compiler
                    $str = "$str0$attrsStr{$m[3]}{$m[4]}";
                    continue;

                } elseif (strpos(',p,div,', ",$tag,") !== false) {
                    $p = strrpos($str0, "<$tag") + strlen($tag) + 1;
                    $str0 = substr($str0, 0, $p) . $attrsStr . substr($str0, $p);

                } elseif ($tag === 'li') {
                    $p1 = strrpos($str0, "<ul");
                    $p2 = strrpos($str0, "<ol");
                    $p = max($p1,$p2) + 3;
                    $str0 = substr($str0, 0, $p) . $attrsStr . substr($str0, $p);
                    $m[2] = '';

                } elseif ($tag === 'dt') {
                    $p = strrpos($str0, "<dl") + 3;
                    $str0 = substr($str0, 0, $p) . $attrsStr . substr($str0, $p);
                    $m[2] = '';

                }

            } else {
                $p = strrpos($str0, "<");
                if (preg_match('/\w+/', substr($str0, $p), $mm)) {
                    $p += strlen($mm[0]) + 1;
                } else {
                    throw new Exception('error');
                }
                $str0 = substr($str0, 0, $p) . $attrsStr . substr($str0, $p);
            }
            $str = str_replace($m[0], "$str0{$m[3]}{$m[4]}", $str);
        }
        return $str;
    } // catchAndInjectTagAttributs



    private function handleLineBreaks($str)
    {
        $str = preg_replace("/(\\\\\n|\s(?<!\\\)BR\s)/ms", "<br>\n", $str);
        return $str;
    } // handleLineBreaks



    /**
     * @param $str
     * @return string
     */
    private function handleOLstart($str): string
    {
        while (preg_match('/^(.*?) \n(\d{1,2}) ! \. (.*) $/xms', $str, $m)) {
            $str1 = $m[1];
            $n = $m[2];
            $str2 = $m[3];
            $p = strpos($str2, "\n");
            if ($p) {
                $s1 = substr($str2, 0, $p);
                $s2 = substr($str2, $p);
                if (preg_match('/{ ([.#][\w-]+ .*?) }/x', $s1, $m)) {
                    $s1 = str_replace($m[0], "{{$m[1]} start='$n'}", $s1);
                } else {
                    $s1 .= " {.lzy-list-start start='$n'}";
                }
                $str3 = "\n$n." . $s1 . $s2;
                $str = $str1 . $str3;
            }
        }
        return $str;
    } // handleOLstart



    private function handleMdVariables($str)
    {
        $out = '';
        $withinEot = false;
        $textBlock = '';
        $var = '';
        foreach (explode(PHP_EOL, $str) as $l) {
            if ($withinEot) {
                if (preg_match('/^EOT\s*$/', $l)) {
                    $withinEot = false;
                    $textBlock = str_replace("'", '&apos;', $textBlock); //??? check!
                    $textBlock = compileMarkdown($textBlock);
                    $this->trans->setVariable($var, $textBlock);
                } else {
                    $textBlock .= $l."\n";
                }
                continue;
            }
            if (preg_match('/^\$([\w.]+)\s?=\s*(.*)/', $l, $m)) { // transVars definition
                $var = trim($m[1]);
                $val = trim($m[2]);
                if ($val === '<<<EOT') {         // handle <<<EOT
                    $withinEot = true;
                    $textBlock = '';
                    continue;
                }
                // translate transvar/macro if there is any:
                if (strpos($val, '{{') !== false) {
                    $val = $this->replaceMdVariables($val);
                    $val = $this->trans->translate($val);
                }
                $val = $this->replaceMdVariables($val);
                $this->trans->setVariable($var, $val);
                continue;
            }
            if ($l && (($p = strpos($l, '$')) !== false)) {
                $l = $this->replaceMdVariables($l, $p);
            }
            $out .= $l."\n";
        }
        return $out;
    } // handleMdVariables




    private function replaceMdVariables($l, $p = false)
    {
        // replaces $var or ${var} with its content, unless shielded as \$var
        //  if variable is not defined, leaves source string untouched. exception: one of below
        // additional functions:
        //  ${var=value}    -> defines variable on the fly
        //  $var++ or $var-- or ++$var or --$var    -> auto-increaces/decreaces numeric variable
        //  ${var++} or ${var--} or ${++var} or ${--var} -> dito

        if ($p === false) {
            $p = strpos($l, '$');
        }
        while ($p !== false) {
            if (($p === 0) || ($l[$p-1] !== '\\')) {
                $str = substr($l, $p);
                if (preg_match('/^\$ ([\w.]+) (.*)/x', $str, $m)) {
                    $varName = $m[1];
                    $rest = $m[2];
                    $val = $this->trans->getVariable($varName);
                    if ($val !== null) {
                        if (strpos($val, '{{') !== false) {
                            $val = $this->trans->translate($val);
                        }

                        // ++ or -- in front of var:
                        if (($p > 2) && (substr($l, $p-2, 2) === '++')) {
                            $l = substr($l, 0, $p-2) . substr($l, $p);
                            $p = $p - 2;
                            if (preg_match('/^-?[\d.]$/', $val)) {
                                $val =  (string) (intval($val) + 1);
                                $this->trans->setVariable($varName, $val);
                            }
                        } elseif (($p > 2) && (substr($l, $p-2, 2) === '--')) {
                            $l = substr($l, 0, $p-2) . substr($l, $p);
                            $p = $p - 2;
                            if (preg_match('/^-?[\d.]$/', $val)) {
                                $val = (string) (intval($val) - 1);
                                $this->trans->setVariable($varName, $val);
                            }
                        }

                        // ++ or -- trailing
                        if (strpos($rest, '++') === 0) {
                            $rest = substr($rest, 2);
                            if (preg_match('/^-?[\d.]$/', $val)) {
                                $this->trans->setVariable($varName, (string) (intval($val) + 1));
                            }
                        } elseif (strpos($rest, '--') === 0) {
                            $rest = substr($rest, 2);
                            if (preg_match('/^-?[\d.]$/', $val)) {
                                $this->trans->setVariable($varName, (string) (intval($val) - 1));
                            }
                        }
                        $l = substr($l, 0, $p) . $val . $rest;
                    }

                    // format variant ${}:
                } elseif (preg_match('/^\$ { (.*?) } (.*)/x', $str, $mm)) {
                    $varName = $mm[1];

                    if ((strpos($varName, '++') === false) && (strpos($varName, '--') === false) && (strpos($varName, '=') === false)) {
                        $val = $this->trans->getVariable($varName);
                        if ($val !== null) {
                            if (strpos($val, '{{') !== false) {
                                $val = $this->trans->translate($val);
                            }
                        } else {
                            $val = '';
                        }
                        $rest = $mm[2];

                        // on the spot assignment:
                    } elseif (preg_match('/^ (\w+?) \s* = \s* (.*)/x', $varName, $mmm)) {
                        $varName = $mmm[1];
                        $val = $mmm[2];
                        $this->trans->setVariable($varName, $val);
                        $rest = $mm[2];

                        // increment/decrement:
                    } elseif (preg_match('/^\$ { (\+\+|--)? (\w+) (\+\+|--)? } (.*)/x', $str, $mm)) {
                        $varName = $mm[2];
                        $val = $this->trans->getVariable($varName);
                        if ($val === null) {
                            $val = 0;
                        }
                        $op1 = $mm[1];
                        $op2 = $mm[3];
                        $rest = $mm[4];
                        if ($op1 === '++') {
                            $val = (string)($val + 1);
                            $this->trans->setVariable($varName, $val);
                        } elseif ($op1 === '--') {
                            $val = (string)($val - 1);
                            $this->trans->setVariable($varName, $val);
                        }

                        if ($op2 === '++') {
                            $this->trans->setVariable($varName, (string)($val + 1));

                        } elseif ($op2 === '--') {
                            $this->trans->setVariable($varName, (string)($val - 1));
                        }
                    }
                    $l = substr($l, 0, $p) . $val . $rest;
                }

            } else {
                $l = substr($l, 0, $p-1) . substr($l, $p);
            }
            $p = strpos($l, '$', $p+1);
        }
        return $l;
    } // replaceMdVariables

} // MarkdownPlus
