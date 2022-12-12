<?php
namespace Usility\PageFactory;

use cebe\markdown\MarkdownExtra;
use Exception;

/*
 * MarkdownPlus extends \cebe\markdown\MarkdownExtra
 * Why not Kirby's native ParsedownExtra?
 *  -> no access to array of lines surrounding current line -> not possible to inject lines
 *  -> required by DivBlock pattern
 */


class MarkdownPlus extends \cebe\markdown\MarkdownExtra
{
    private static $asciiTableInx   = 1;
    private static $imageInx        = 0;
    private static $tabulatorInx    = 1;
    public static $mdVariant        = 'plus';
    private $inlineTags = ',a,abbr,acronym,b,bdo,big,br,button,cite,code,dfn,em,i,img,input,kbd,label,'.
            'map,object,output,q,samp,script,select,small,span,strong,sub,sup,textarea,time,tt,var,skip,';
    // 'skip' is a pseudo tag used by MarkdownPlus.

    public function __construct($pfy = false)
    {
        $this->pfy          = $pfy;
        $this->kirby        = kirby();
        $this->trans        = PageFactory::$trans;
    }


    /**
     * Compiles a markdown string to HTML:
     * @param string $str
     * @return string
     */
    public function compile(string $str):string
    {
        if (!$str) {
            return '';
        }
        $this->lang         = PageFactory::$lang;
        $this->langCode     = PageFactory::$langCode;
        if (self::$mdVariant === 'plus') {
            $str = $this->preprocess($str);
            $html = parent::parse($str);
            $html = $this->postprocess($html);

        } elseif (self::$mdVariant === 'extra') {
            $md = new MarkdownExtra();
            $html = $md->parse($str);

        } else {
            $html = kirby()->kirbytext($str);
        }
        return $html;
    } // compile


    public function compileParagraph(string $str):string
    {
        if (!$str) {
            return '';
        }
        $this->lang         = PageFactory::$lang;
        $this->langCode     = PageFactory::$langCode;

        $str = $this->preprocess($str);
        $html = parent::parseParagraph($str);
        $html = $this->postprocess($html);

        return $html;
    } // compile


    /**
     * Compiles a markdown string to HTML without pre- and postprocessing
     * @param string $str
     * @return string
     */
    public function compileStr(string $str): string
    {
        $this->lang         = PageFactory::$lang;
        $this->langCode     = PageFactory::$langCode;
        $html = parent::parse($str);
        return $html;
    } // compileStr



    // === AsciiTable ==================
    protected function identifyAsciiTable($line)
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
            'args' => false
        ];
        $firstLine = $lines[$current];
        if (preg_match('/^\|===*\s+(.*)$/', $firstLine, $m)) {
            $block['args'] = $m[1];
        }
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (strncmp($line, '|===', 4) !== 0) {
                $block['content'][] = $line;
            } else {
                // stop consuming when second '|===' found
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

        $inx = self::$asciiTableInx++;

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

        // prepare table attributes:
        $caption = $block['args'];
        if (strpbrk($caption, '#.:=!') !== false) {
            $attrs = parseInlineBlockArguments($caption);
            if (($attrs['tag'] === 'skip') || ($attrs['lang'] && ($attrs['lang'] !== PageFactory::$lang))) {
                return '';
            }
            $caption = $attrs['text'];
            $attrsStr = $attrs['htmlAttrs'];
            $attrsStr = preg_replace('/class=["\'].*?["\']/', '', $attrsStr);
            $class = "pfy-table pfy-table-$inx";
            if ($attrs['class']) {
                $class .= ' '.$attrs['class'];
            }
            if (!$attrs['id']) {
                $attrsStr = "id='pfy-table-$inx' ".$attrsStr;
            }
            $attrsStr .= " class='$class'";
        } else {
            $attrsStr = "id='pfy-table-$inx' class='pfy-table pfy-table-$inx'";
        }

        // now render the table:
        $out = "\t<table $attrsStr>\n";
        if ($caption) {
            $caption = trim($caption,'"\'');
            $out .= "\t  <caption>$caption</caption>\n";
        }

        // render header as defined in first row, e.g. |# H1|H2
        $row = 0;
        if (isset($table[0][0]) && ($table[0][0][0] === '#')) {
            $row = 1;
            $table[0][0] = substr($table[0][0],1);
            $out .= "\t  <thead>\n\t    <tr>\n";
            for ($col = 0; $col < $nCols; $col++) {
                $cell = isset($table[0][$col]) ? $table[0][$col] : '';
                $cell = parent::parseParagraph(trim($cell));
                $cell = trim($cell);
                if (preg_match('|^<p>(.*)</p>$|', $cell, $m)) {
                    $cell = $m[1];
                }
                $out .= "\t\t\t<th class='th$col'>$cell</th>\n";
            }
            $out .= "\t    </tr>\n\t  </thead>\n";
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
                    $cell = parent::parse(trim($cell));
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
    protected function identifyDivBlock($line)
    {
        // if a line starts with at least 3 colons it is identified as a div-block
        // fence chars ':$@' -> block
        if (preg_match('/^[:$@%]{3,10}\s+\S/', $line)) {
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
            'marker' => $line[0],
            'attributes' => '',
            'literal' => false,
            'pfyBlockType' => true
        ];

        // detect class or id and fence length (can be more than 3 backticks)
        $depth = 0;
        $marker = $block['marker'];
        if (preg_match("/($marker{3,10})(.*)/",$line, $m)) {
            $fence = $m[1];
            $rest = trim($m[2]);
            if ($rest && ($rest[0] === '{')) {      // non-pfy block: e.g. "::: {#id}
                $block['pfyBlockType'] = false;
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

        $block['tag'] = $tag ? $tag : (($marker === '%') ? 'span' : 'div');
        $block['attributes'] = $attrs['htmlAttrs'];
        $block['lang'] = $attrs['lang'];;
        $block['literal'] = $attrs['literal'];;
        $block['meta'] = '';
        $block['mdCompile'] = ($attrs['mdCompile'] !== false);

        // consume all lines until end-tag, e.g. @@@
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match("/^($marker{3,10})\s*(.*)/", $line, $m)) { // it's a potential fence line
                $fenceEndCandidate = $m[1];
                $rest = $m[2];
                if ($fence === $fenceEndCandidate) {    // end tag we have to consider:
                    if ($rest !== '') {    // case nested or consequitive block
                        if ($block['pfyBlockType']) {   // pfy-style -> consecutive block starts:
                            $i--;
                            break;
                        }
                        $depth++;

                    } else {                    // end of block
                        $depth--;
                        if ($depth < 1) {       // only in case of non-pfyBlocks we may have to skip nested end-tags:
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
            // fence type '%' means parse inline only:
            if ($fence[0] === '%') {
                $block['content'][0] = parent::parseParagraph($content);

            } else {
                $block['content'][0] = shieldStr($content, true);
            }
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

        return "\n\n<$tag $attrs>\n$out</$tag><!-- $attrs -->\n\n\n";
    } // renderDivBlock




    // === Tabulator ==================
    protected function identifyTabulator($line)
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
            'widths' => [],
        ];

        $last = $current;
        $nEmptyLines = 0;
        // consume following lines containing >>
        for($i = $current, $count = count($lines); $i <= $count-1; $i++) {
            if (!preg_match('/\S/', $lines[$i])) {  // empty line
                if ($nEmptyLines++ > 0) {
                    break;
                }
            } else {
                $nEmptyLines = 0;
            }
            $line = $lines[$i];
            if (preg_match('/([.\d]{1,3}\w{1,2})? >> [\s\t]/x', $line)) {
                $block['content'][] = $line;

                preg_match_all('/([.\d]{1,3}\w{1,2})? >> [\s\t]/x', $line, $m);
                foreach ($m[1] as $j => $width) {
                    if ($width) {
                        $block['widths'][$j] = $width;
                    } elseif (isset($block['widths'][$j])) {
                        $block['widths'][$j] = '6em';
                    }
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
        $inx = self::$tabulatorInx++;
        $out = '';
        foreach ($block['content'] as $l) {
            $parts = preg_split('/[\s\t]* ([.\d]{1,3}\w{1,2})? >> [\s\t]/x', $l);
            $line = '';
            $addedWidths = 0; // px
            $addedEmsWidths = 0; // em
            foreach ($parts as $n => $elem) {
                if ($w = $block['widths'][$n]??false) {
                    $style = " style='width:$w;'";
                    $addedWidths += convertToPx($w);
                    $addedWidths += convertToPx('1.2em');
                    if (preg_match('/^([\d.]+)em$/', $w, $m)) {
                        $addedEmsWidths += intval($m[1]);
                    } else {
                        // non-'em'-width detected -> can't use em-based width:
                        $addedEmsWidths = -999999;
                    }

                } elseif ($n === 0) {
                    $style = " style='width:6em;'";
                    $addedWidths += 16;
                    $addedEmsWidths += 1;

                } else {
                    if ($addedEmsWidths > 0) {
                        // all widths defined as 'em', so we can use that value:
                        $style = " style='max-width:calc(100% - {$addedEmsWidths}em);'";
                    } else {
                        // some widths defined as non-'em' -> use px-based estimate:
                        $style = " style='max-width:calc(100% - {$addedWidths}px);'";
                    }
                }
                $elem = parent::parseParagraph($elem);
                $line .= "<span class='c".($n+1)."'$style>$elem</span>";
            }
            $out .= "<div class='pfy-tabulator-wrapper pfy-tabulator-wrapper-$inx'>$line</div>\n";
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
                    $md = rtrim($md) . "<br>\n";
                }

            } else {                                        // new dt block starts
                $line = parent::parseParagraph($line);
                $out .= "\t\t<dt>$line</dt>\n\t\t<dd>\n";
            }
        }
        $out = "\t<dl>\n$out\t</dl>\n";
        return $out;
    } // renderDefinitionList



    // === OrderedList ==================
    protected function identifyOrderedList($line)
    {
        if (preg_match('/^\d+ !? \. /x', $line)) {
            return 'orderedList';
        }
        return false;
    } // identifyOrderedList

    protected function consumeOrderedList($lines, $current)
    {
        // create block array
        $block = [
            'orderedList',
            'content' => [],
            'start' => false,
        ];

        // consume all lines until 2 empty line
        for($i = $current, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (!preg_match('/^\d+!?\./', $line)) {  // empty line
                    break;
            } elseif (preg_match('/^(\d+)!\.\s*(.*)/', $line, $m)) {
                $block['start'] = $m[1];
                $line = $m[2];
            } elseif (preg_match('/^(\d+)\.\s*(.*)/', $line, $m)) {
                $line = $m[2];
            }
            $block['content'][] = $line;
        }
        return [$block, $i];
    } // consumeOrderedList

    protected function renderOrderedList($block)
    {
        $out = '';
        $start = '';
        if ($block['start'] !== false) {
            $start = " start='{$block['start']}'";
        }
        foreach ($block['content'] as $line) {
                $line = parent::parse($line);
                $line = trim($line);
                $out .= "\t\t<li>$line</li>\n";
        }
        $out = "\t<ol$start>\n$out\t</ol>\n";
        return $out;
    } // renderOrderedList



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
    protected function renderMarked(array $element): string
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

        $attr = "src:'$src', alt:'$alt', caption:'$caption'";
        $str = $this->processByMacro('img', $attr);
        return $str;
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
            $title = '';
            // extract optional title:
            if (preg_match('/(.*?)\s+(.*)/', $link, $m)) {
                $link = $m[1];
                $title = trim($m[2], '"\'');
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
        $link = trim($element[1], '"\'');
        $linkText = $element[2];
        $title = preg_replace('/^ ([\'"]) (.*) \1 $/x', "$2", $element[3]);
        $attr = "url:'$link', ";
        $q = (strpos($linkText, "'") === false)? "'": '"';
        $attr .= "text:$q$linkText$q, ";
        $q = (strpos($title, "'") === false)? "'": '"';
        $attr .= "title:$q$title$q";
        $str = $this->processByMacro('link', $attr);
        return $str;
    } // renderLink





    /**
     * @marker :
     */
    protected function parseIcon($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^:(\w+):/', $markdown, $matches)) {
            if (iconExists($matches[1])) {
                return [
                    // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                    // other inline markdown elements inside this tag
                    ['icon', $matches[1]],
                    // return the offset of the parsed text
                    strlen($matches[0])
                ];
            }
        }
        // in case we did not find a closing ~~ we just return the marker and skip 1 character
        return [['text', ':'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderIcon($element)
    {
        $iconName = $element[1];
        $icon = renderIcon($iconName);
        return $icon;
    }





    //=== Pre- and Post-Processing ===================================
    /**
     * Applies Pre-Processing: shielding chars, includes, handling variables, fixing HTML
     * @param string $str
     * @return string
     * @throws Exception
     */
    private function preprocess(string $str): string
    {
        $str = $this->handleShieldedCharacters($str);

        $str = $this->doMDincludes($str);

        $str = $this->fixCebeBugs($str);

        $str = $this->handleMdVariables($str);

        $str = PageFactory::$trans->flattenMacroCalls($str); // need to flatten so MD doesn't get confused

        $str = $this->handleLineBreaks($str);

        // {{}} alone on line -> add NLs around it:
        $str = preg_replace('/(\n{{.*}}\n)/U', "\n$1\n", $str);

        return $str;
    } // preprocess


    /**
     * Applies Post-Processing: kirbyTags, SmartyPants, fixing HTML, attribute injection
     * @param string $str
     * @return string
     */
    private function postprocess(string $str): string
    {
        // lines that contain but a variable or macro (e.g. "<p>{{ lorem( help ) }}</p>") -> remove enclosing P-tags:
        $str = preg_replace('|<p> ({{ .*? }}) </p>|xms', "$1", $str);

        // check for kirbytags, get them compiled:
        $str = $this->handleKirbyTags($str);


        // handle smartypants:
        if (kirby()->option('smartypants')) {
            $str = $this->smartypants($str);
        }

        $str = $this->catchAndInjectTagAttributes($str); // ... {.cls}

        // clean up shielded characters, e.g. '@#123;''@#123;' to '&#123;' :
        $str = preg_replace('/@#(\d+);/ms', "&#$1;", $str);

        return $str;
    } // postprocess


    /**
     * Applies (PageFactory-)SmartyPants translation:
     * @param string $str
     * @return string
     */
    private function smartypants(string $str): string
    {
        $smartypants =    [
                '/(?<!-)-&gt;/ms'  => '&rarr;',
                '/(?<!=)=&gt;/ms'  => '&rArr;',
                '/(?<!!)&lt;-/ms'  => '&larr;',
                '/(?<!=)&lt;=/ms'  => '&lArr;',
                '/(?<!\.)\.\.\.(?!\.)/ms'  => '&hellip;',
                '/(?<!-|!)--(?!-|>)/ms'  => '&ndash;', // in particular: <!-- -->
                '/(?<!-)---(?!-)/ms'  => '&mdash;',
                '/(?<!&lt;)&lt;&lt;(?!&lt;)/ms'  => '&#171;',
                '/(?<!&gt;)&gt;&gt;(?!&gt;)/ms'  => '&#187;',
                '/\bEURO\b/ms'  => '&euro;',
                '/sS/ms'  => 'ß',
                '|1/4|ms'  => '&frac14;',
                '|1/2|ms'  => '&frac12;',
                '|3/4|ms'  => '&frac34;',
                '|0/00|ms'  => '&permil;',
                '/(?<!,),,(?!,)/ms'  => '„',
                "/(?<!')''(?!')/ms"  => '”',
                "/(?<!`)``(?!`)/ms"  => '“',
                "/(?<!~)~~(?!~)/ms"  => '≈',
                '/\bINFINITY\b/ms'  => '∞',
        ];
            $str = preg_replace(array_keys($smartypants), array_values($smartypants), $str);
        return $str;
    } // msmartypants


    /**
     * Converts shielded characters (\c) to their unicode equivalent
     * @param string $str
     * @return string
     */
    private function handleShieldedCharacters(string $str): string
    {
        $p = 0;
        while ($p=strpos($str, '\\', $p)) {
            $ch = $str[$p+1];
            if ($ch === "\n") {
                $unicode = '<br>';
            } else {
                $o = ord($str[$p + 1]);
                $unicode = "@#$o;";
            }
            $str = substr($str, 0, $p) . $unicode . substr($str, $p+2);
            $p += 2;
        }
        return $str;
    } // handleShieldedCharacters



    /**
     * Executes embedded include instructions, e.g. (include: xy)
     * @param string $str
     * @return string
     */
    private function doMDincludes(string $str): string
    {
        // pattern: "(include: -incl.md)"
        while (preg_match('/(.*) \n \(include: (.*?) \) (.*)/xms', $str, $m)) {
            $s1 = $m[1];
            $s2 = $m[3];
            $val = $this->handleInclude($m[2], ' ');
            $str = $s1.$val.$s2;
        }
        return $str;
    } // doMDincludes


    /**
     * Fixes irregular behavior of cebe/markdown compiler:
     * -> ul and ol not recognized if no empty line before pattern
     * @param string $str
     * @return string
     */
    private function fixCebeBugs(string $str): string
    {
        $lines = explode("\n", $str);
        foreach ($lines as $i => $line) {
            if (strpos($line, '- ') === 0) {
                if (preg_match('/^[^\-\s].*/',$lines[$i-1])) {
                    $lines[$i-1] .= "\n";
                }
            } elseif (preg_match('/^\d+!?\./', $line, $m)) {
                if (!preg_match('/^\d+!?\./', $lines[$i-1], $m)) {
                    $lines[$i-1] .= "\n";
                }
            }
        }
        $str = implode("\n", $lines);
        return $str;
    } // fixCebeBugs


    /**
     * Intercepts and applies attribute injection instructions of type "{: }"
     * @param string $str
     * @return string
     */
    private function catchAndInjectTagAttributes(string $str): string
    {
        if (!strpos($str, '{:')) {
            return $str;
        }
        
        // run through HTML line by line:
        $lines = explode("\n", $str);
        $attribs = '';
        $nLines = sizeof($lines);
        for ($i=0; $i<$nLines; $i++) {
            $line = &$lines[$i];
            
            // case attribs found and not consumed yet -> apply to following tag:
            if ($attribs) {
                if (preg_match_all('|<(\w+)|', $line, $m)) {
                    $line = $this->applyAttributes($line, $attribs, $m[0][0]);
                }
                $attribs = false;
            }
            
            // check whether there is anything to do:
            if (strpos($line, '{:') === false) {
                continue;
            }
            
            // handle case of '{: }' on separate line -> to be applied to next tag:
            if (preg_match('|<p>{:(.*?)}</p>|', $line, $m)) {
                $attribs = $m[1];
                unset($lines[$i]);
                continue;
            } elseif (preg_match('|<p>{:(.*?)}|', $line, $m)) {
                $attrs = parseInlineBlockArguments($m[1]);
                $attrsStr = $attrs['htmlAttrs'];
                $line = str_replace($m[0], "<p$attrsStr>", $line);
                continue;
            }
            
            // handle all other cases -> apply to previous tag:
            if (preg_match('|^(.*?)({:(.*?)})(.*)|', $line, $m)) {
                $line = str_replace($m[2], '', $line);
                if (preg_match('|<(\w+)|', $m[1], $mm)) {
                    $line = $this->applyAttributes($line, $m[3], $mm[0]);
                } else {
                    for ($j=$i-1; $j>0; $j--) {
                        $l = &$lines[$j];
                        if (preg_match('|<(\w+)|', $l, $mm)) {
                            $l = $this->applyAttributes($l, $m[3], $mm[0]);
                            break;
                        }
                    }
                }
            }
        }
        $str = implode("\n", $lines);
        return $str;
    } // catchAndInjectTagAttributes


    /**
     * Helper for catchAndInjectTagAttributes()
     * @param string $line
     * @param string $attribs
     * @param string $pattern
     * @return array|string|string[]
     */
    private function applyAttributes(string $line, string $attribs, string $pattern)
    {
        $attrs = parseInlineBlockArguments($attribs);
        if (preg_match('/(class=["\'])/', $line, $mm)) {
            $line = str_replace($mm[0], "{$mm[1]}{$attrs['class']} ", $line);

        } else {
            $attrsStr = $attrs['htmlAttrs'];
            $line = str_replace($pattern, "$pattern$attrsStr", $line);
        }
        return $line;
    } // applyAttributes


    /**
     * Translates line-break code ' BR ' to HTML <br>
     * @param string $str
     * @return string
     */
    private function handleLineBreaks(string $str): string
    {
        $str = preg_replace("/(\\\\\n|\s(?<!\\\)BR\s)/ms", "<br>\n", $str);
        return $str;
    } // handleLineBreaks


    /**
     * Parses given string and intercepts instances of MD-Variables, e.g. "$var"
     * @param string $str
     * @return string
     * @throws Exception
     */
    private function handleMdVariables(string $str): string
    {
        $out = '';
        $withinEot = false;
        $mdCompileTextBlock = false;
        $textBlock = '';
        $var = '';
        foreach (explode(PHP_EOL, $str) as $l) {
            if ($withinEot) {
                if (preg_match('/^EOT\s*$/', $l)) {
                    $withinEot = false;
                    $textBlock0 = $textBlock;
                    $textBlock = str_replace("'", '&apos;', $textBlock);
                    if ($mdCompileTextBlock) {
                        $textBlock = PageFactory::$trans->translate($textBlock0);
                        $textBlock = compileMarkdown($textBlock);
                    } else {
                        $textBlock = shieldStr($textBlock);
                    }
                    $this->trans->setVariable($var, $textBlock);
                    $mdCompileTextBlock = false;
                } else {
                    $textBlock .= $l."\n";
                }
                continue;
            }
            if (preg_match('/^\$([\w.]+)\s?=\s*(.*)/', $l, $m)) { // transVars definition
                $var = trim($m[1]);
                $val = trim($m[2]);
                if ($this->handleHeredoc($val, $withinEot, $textBlock, $mdCompileTextBlock)) {
                    continue;
                }
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


    /**
     * Helper for handleMdVariables(): handles patterns "<<<EOT"
     * @param string $val
     * @param bool $withinEot
     * @param string $textBlock
     * @param bool $mdCompileTextBlock
     * @return bool
     */
    private function handleHeredoc(string &$val, bool &$withinEot, string &$textBlock, bool &$mdCompileTextBlock): bool
    {
        // handle <<<EOT
        if ($val === '<<<EOT') {
            $withinEot = true;
            $textBlock = '';
            $mdCompileTextBlock = true;
            return true;

        // handle <<<'EOT' i.e. no md-compile
        } elseif ($val === "<<<'EOT'") {
            $withinEot = true;
            $textBlock = '';
            return true;

        // handle <<<INCLUDE
        } elseif (preg_match('/<<<INCLUDE\((.*?)\)/', $val, $m)) {
            $val = $this->handleInclude($m[1]);
        }
        return false;
    } // handleHeredoc


    /**
     * Executes include instructions
     * @param string $file
     * @param string $delim
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function handleInclude(string $file, string $delim = ','): string
    {
        $out = '';
        $argsStr = $file;
        $mdCompile = false;
        $mdCompileOverride = null;
        $file = trim($file, '"\'');
        $args = parseArgumentStr($file, $delim);
        $file = $args[0];
        if (in_array('literal', $args)) {
            $mdCompileOverride = false;
        } elseif (isset($args['literal'])) {
            $mdCompileOverride = $args['literal'];
        }

        if (strpos($file, '.php') !== false) {
            $out = $this->execAndIncludePhpFile($file, $args, $mdCompileOverride, $mdCompile);

        } else {
            $file = resolvePath($file, false, true);
            if (is_dir($file)) {
                $wrapperTag = false;
                $wrapperClass = '';
                if (isset($args['wrapperTag'])) {
                    $wrapperTag = $args['wrapperTag'];
                }
                if (isset($args['wrapperClass'])) {
                    $wrapperClass = $args['wrapperClass'].' ';
                    $wrapperTag = $wrapperTag? $wrapperTag : 'div';
                }
                $files = getDir("$file*");
                $i = 0;
                foreach ($files as $file) {
                    if ($wrapperTag) {
                        $i++;
                        $class = " class='{$wrapperClass}pfy-included-$i'";
                        $str = $this->includeFile($file, false);
                        $out .= <<<EOT

:::::::::: <$wrapperTag$class
$str
::::::::::

EOT;
                    } else {
                        $str = $this->includeFile($file, $mdCompileOverride);
                        $out .= $str;
                    }
                }
                $out = compileMarkdown($out);

            } else {
                $out = $this->includeFile($file, $mdCompileOverride);
            }
        }
        return $out;
    } // handleInclude


    /**
     * Helper for handleInclude(): includes files of type md, txt and html
     * @param string $file
     * @param null $mdCompileOverride
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function includeFile(string $file, $mdCompileOverride = null): string
    {
        if (strpos(',txt,md,html,', fileExt($file)) === false) {
            throw new \Exception("Error: unsupported file type '$file'");
        }
        $str = loadFile($file, 'cstyle');
        $str = $this->pfy->utils->extractFrontmatter($str);
        if (!file_exists($file)) {
            throw new \Exception("Error: file '$file' not found for including.");
        }
        if ((($mdCompileOverride === null) && (fileExt($file) === 'md')) || $mdCompileOverride) {
            $str = compileMarkdown($str);
        }
        return $str;
    } // includeFile


    /**
     * Helper for handleInclude(): intercepts instances of MD-Variables
     * @param $l
     * @param false $p
     * @return string
     * @throws Exception
     */
    private function replaceMdVariables($l, $p = false): string
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


    /**
     * Helper for handleInclude(): executes PHP-code and injects result
     * @param $file
     * @param $mdCompileOverride
     * @param bool $mdCompile
     * @return string
     * @throws Exception
     */
    private function execAndIncludePhpFile(string $file, $args, $mdCompileOverride = null, $mdCompile = false): string
    {
        $file = PFY_CUSTOM_PATH . basename($file);
        if (!file_exists($file)) {
            throw new \Exception("Error: php file '$file' not found for including.");
        }
        $out = (string)require($file);
        if ((($mdCompileOverride === null) && $mdCompile) || $mdCompileOverride) {
            $out = compileMarkdown($out);
        }
        return $out;
    } // execAndIncludePhpFile


    /**
     * Intercepts kirbytag patterns and sends them to Kirby.
     * Exceptions are 'link' and 'image', they are executed by corresponding macros
     * @param $str
     * @return string
     */
    private function handleKirbyTags(string $str): string
    {
        if (preg_match_all('/(\(\w*:.*?\))/ms', $str, $m)) {
            foreach ($m[1] as $i => $value) {
                $value = strip_tags(str_replace("\n", ' ', $value));
                
                // intercept '(link:' and process by link() macro:
                if (preg_match('/^ \(link: \s* ["\']? ([^\s"\']+) ["\']? (.*) \) /x', $value, $mm)) {
                    $value = "url:'{$mm[1]}' {$mm[2]}";
                    $str1 = $this->processByMacro('link', $value);
                    $str = str_replace($m[0][$i], $str1, $str);

                // intercept '(image:' and process by img() macro:
                } elseif (preg_match('/^ \(image: \s* ["\']? ([^\s"\']+) ["\']? (.*) \) /x', $value, $mm)) {
                    $value = "src:'{$mm[1]}' {$mm[2]}";
                    $str1 = $this->processByMacro('img', $value);
                    $str = str_replace($m[0][$i], $str1, $str);

                } else {
                    $str1 = kirby()->kirbytags($value);
                    $str = str_replace($m[0][$i], $str1, $str);
                }
            }
        }
        return $str;
    } // handleKirbyTags


    /**
     * Helper for handleKirbyTags(): calls macros
     * @param string $value
     * @return string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function processByMacro(string $macroName, string $argStr): string
    {
        if (strpos($argStr, ',') === false) {
            $args = parseArgumentStr($argStr, ' ');
        } else {
            $args = parseArgumentStr($argStr);
        }
        $mac = new Macros($this->pfy);
        $macroObj = $mac->loadMacro($macroName);
        $args = $mac->fixArgs($args, $macroObj['parameters']);
        $str = $macroObj['macroObj']->render($args, $argStr);
        return $str;
    } // processByMacro

} // MarkdownPlus
