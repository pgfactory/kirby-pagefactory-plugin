<?php

namespace PgFactory\PageFactory;

use Exception;
use Kirby\Data\Yaml;
use PgFactory\MarkdownPlus\MdPlusHelper;
use PgFactory\MarkdownPlus\Permission;

class Frontmatter
{

    private static string $sectionsCss = '';
    private static string $sectionsScss = '';
    private static array $metaKeys = ['description', 'keywords', 'author'];

    /**
     * @param $mdStr
     * @return array|false
     * @throws Exception
     */
    public static function extract(string $mdStr): array|false
    {
        $mdStr .= "\n";
        $wrapperTag = 'section';
        $wrapperClass = '';
        $fields = Frontmatter::extractFields($mdStr);

        // first check visibility and showFrom/showTill fields in frontmatter:
        if (!self::evaluateVisibility($fields)) {
            return false;
        }

        // loop through remaining fields and evaluate them:
        foreach ($fields as $key => $value) {
            if ($key === '') {
                continue;
            }

            if ($key === 'variables') {
                $value = str_replace('{{', "'{=={'", $value);
                $values = Yaml::decode($value);
                foreach ($values as $k => $v) {
                    if (is_string($v)) {
                        $v = str_replace("'{=={'", '{{', $v);
                        if (str_contains($v, '{{')) {
                            $v = TransVars::translate($v);
                            $v = str_replace(['{!!{', '}!!}', '⟮'], ['{{', '}}', '('], $v);
                        }
                    }
                    TransVars::setVariable($k, $v);
                }

            } elseif (in_array($key, self::$metaKeys, true)) {
                Page::append($key, $value);

            } elseif ($key === 'title') {
                TransVars::setVariable('headTitle', $value);

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
                self::$sectionsCss .= $value;

            } elseif ($key === 'scss') {
                // hold back till ".this"/"#this" can be resolved:
                self::$sectionsScss .= $value;

            } elseif ($key === 'js') {
                Page::addJs($value);

            } elseif ($key === 'jsready') {
                Page::addJsReady($value);

            } elseif ($key === 'assets') {
                $assets = Yaml::decode($value);
                foreach ($assets as $asset) {
                    $asset = trim($asset, '"\'');
                    Assets::addAssets($asset);
                }

            } elseif ($key === 'slidingpanels') {
                PageFactory::$slidingPanels = $value;

            } else {
                TransVars::setVariable($key, $value);
            }
        }

        return [$mdStr, $wrapperTag, $wrapperClass];
    } // extract


    /**
     * @param string $wrapperId
     * @return void
     */
    public static function propagateStyles(string $wrapperId): void
    {
        if (self::$sectionsCss) {
            self::$sectionsCss = str_replace(['#this', '.this'], ["#$wrapperId", ".$wrapperId"], self::$sectionsCss);
            Page::addCss(self::$sectionsCss);
            self::$sectionsCss = '';
        }
        if (self::$sectionsScss) {
            self::$sectionsScss = str_replace(['#this', '.this'], ["#$wrapperId", ".$wrapperId"], self::$sectionsScss);
            Page::addScss(self::$sectionsScss);
            self::$sectionsScss = '';
        }
    } // propagateStyles


    /**
     * @param array $fields
     * @return bool
     * @throws \Exception
     */
    private static function evaluateVisibility(array &$fields): bool
    {
        $showFrom = false;
        $showTill = false;
        $visible = true;
        foreach ($fields as $key => $value) {

            if ($key === '') {
                continue;
            }

            if (($key === 'visibility') || ($key === 'visible')) {
                if (!Permission::evaluate($value)) {
                    $visible = false;
                }
                unset($fields[$key]);

            } elseif ($key === 'showfrom') {
                $value = trim($value, '\'"');
                if (!preg_match('/\d\d:\d\d/', $value)) {
                    $value .= ' 00:00:00';
                }
                $showFrom = $value;
                unset($fields[$key]);

            } elseif ($key === 'showtill') {
                $value = trim($value, '\'"');
                if (!preg_match('/\d\d:\d\d/', $value)) {
                    $value .= ' 23:59:59';
                }
                $showTill = $value;
                unset($fields[$key]);
            }
        }

        // check and evaluate time constraints:
        if ($visible && ($showFrom || $showTill)) {
            $visible = MdPlusHelper::isNowVisible($showFrom, $showTill);
        }
        return $visible;
    } // evaluateVisibility


    /**
     * @param string $mdStr
     * @return array
     */
    public static function extractFields(string &$mdStr): array
    {
        $out = [];
        $mdStr .= "\n";
        $fields = preg_split('!\n-{4}\n!', $mdStr);
        $mdStr = $fields[count($fields) - 1];
        unset($fields[count($fields) - 1]);
        foreach ($fields as $field) {
            $pos = strpos($field, ':');
            $key = strtolower(camelCase(trim(substr($field, 0, $pos))));
            $out[$key] = trim(substr($field, $pos + 1));
        }
        return $out;
    } // extractFields

} // Frontmatter
