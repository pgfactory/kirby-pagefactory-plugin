<?php

namespace PgFactory\PageFactory;

use Kirby\Data\Yaml;
use PgFactory\MarkdownPlus\Permission;

class Frontmatter
{

    private static string $sectionsCss = '';
    private static string $sectionsScss = '';
    /**
     * @param $mdStr
     * @return array|false
     * @throws Kirby\Exception\InvalidArgumentException
     */
    public static function extract(string $mdStr): array|false
    {
        $mdStr .= "\n";
        $wrapperTag = 'section';
        $wrapperClass = '';
        $fields = preg_split('!\n-{4}\n!', $mdStr);
        $n = sizeof($fields)-1;
        $mdStr = $fields[$n];
        $continue = true;

        // loop through all fields and add them to the content
        for ($i=0; $i<$n; $i++) {
            $field = trim($fields[$i]);
            $pos = strpos($field, ':');
            $key = camelCase(trim(substr($field, 0, $pos)));
            $key = strtolower($key);

            // Don't add fields with empty keys
            if (empty($key) === true) {
                continue;
            }

            $value = trim(substr($field, $pos + 1));

            if ($key === 'variables') {
                $value = str_replace('{{', "'{=={'", $value);
                $values = Yaml::decode($value);
                foreach ($values as $k => $v) {
                    $v = str_replace("'{=={'", '{{', $v);
                    if (is_string($v) && str_contains($v, '{{')) {
                        $v = TransVars::translate($v);
                    }
                    TransVars::setVariable($k, $v);
                }

            } elseif (str_contains('description,keywords,author', $key)) {
                Page::addHead("  <meta name='$key' content='$value'>\n");

            } elseif ($key === 'robots') {
                Page::applyRobotsAttrib($value);

            } elseif ($key === 'head') {
                Page::addHead($value);

            } elseif ($key === 'wrappertag') {
                $wrapperTag = $value;

            } elseif ($key === 'wrapperclass') {
                $wrapperClass = ' '.$value;

            } elseif ($key === 'css') {
                // hold back till ".this"/"#this" can be resolved:
                self::$sectionsCss = $value;

            } elseif ($key === 'scss') {
                // hold back till ".this"/"#this" can be resolved:
                self::$sectionsScss = $value;

            } elseif ($key === 'js') {
                Page::addJs($value);

            } elseif ($key === 'jsready') {
                Page::addJsReady($value);

            } elseif ($key === 'jq') {
                Page::addJq($value);

            } elseif ($key === 'assets') {
                $assets = Yaml::decode($value);
                foreach ($assets as $asset) {
                    Assets::addAssets($asset);
                }

            } elseif ($key === 'visibility') {
                if (!Permission::evaluate($value)) {
                    $continue = false;
                }

            } else {
                // unescape escaped dividers within a field
                TransVars::setVariable($key, $value);
            }
        }
        return $continue ? [$mdStr, $wrapperTag, $wrapperClass]: false;
    } // extract


    /**
     * @param string $wrapperId
     * @return void
     */
    public static function propagaterStyles(string $wrapperId): void
    {
        if (self::$sectionsCss) {
            self::$sectionsCss = str_replace(['#this', '.this'], ["#$wrapperId", ".$wrapperId"], self::$sectionsCss);
            Page::addCss(self::$sectionsCss);
        }
        if (self::$sectionsScss) {
            self::$sectionsScss = str_replace(['#this', '.this'], ["#$wrapperId", ".$wrapperId"], self::$sectionsScss);
            Page::addScss(self::$sectionsScss);
        }
    } // propagaterStyles


} // Frontmatter