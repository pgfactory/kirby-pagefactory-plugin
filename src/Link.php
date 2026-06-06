<?php

namespace PgFactory\PageFactory;

class Link
{
    const SUPPORTED_TYPES = ',pdf,png,gif,jpg,jpeg,txt,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,mail,mailto,file,' .
        'sms,tel,gsm,geo,slack,twitter,facebook,instagram,tiktok,zip,';
    const PROTO_TYPES = ',https://,http://,mailto:,sms:,tel:,gsm:,geo:,slack:,twitter:,facebook:,instagram:,tiktok:,';
    const DOWNLOAD_TYPES = ',txt,doc,docx,dotx,xls,xlsx,xltx,ppt,pptx,potx,odt,ods,ots,ott,odp,otp,png,gif,jpg,jpeg,zip,';

    private static $url;
    private static $args;
    private static $text;
    private static $title;
    private static $id;
    private static $class;
    private static $alt;
    private static $proto;
    private static $target = '';
    private static $type;
    private static $ext;
    private static $linkCat;
    private static $icon = '';
    private static $iconBefore;
    private static $attributes;
    private static $hiddenText;
    private static $isExternalLink;
    private static $download;
    private static $clickLinkInstances = 0;
    private static $iconReplacements = [
        'gsm' => 'mobile',
        'mailto' => 'mail',
    ];

    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public static function render(array $args): string
    {
        if (!isset($args['url'])) {
            return '';
        }
        self::$url = $args['url'];
        self::$args = $args;
        self::$text = '';
        self::$title = '';
        self::$id = $args['id'] ?? '';

        if ($countClicks = ($args['countClicks'] ?? false)) {
            if (!self::$id) {
                self::$clickLinkInstances++;
                self::$id = 'pfy-cc-link-' . self::$clickLinkInstances;
            }
            $id = self::$id;
            if ($countClicks === true) {
                $countClicks = 'true';
            }
            $js = <<<EOT
pfyHandleEvent("#$id", ev => {
    execAjaxPromise('count=$countClicks');
})
EOT;
            Page::addJsReady($js);
        }


        self::$class = $args['class'] ?? '';
        self::$alt = $args['alt'] ?? '';
        self::$proto = '';
        self::$target = $args['target'] ?? null;
        self::$type = '';
        self::$ext = strtolower(fileExt(self::$url, couldBeUrl: true));
        self::$linkCat = '';
        self::$icon = $args['icon'] ?? null;
        self::$iconBefore = ($args['iconPosition'] ?? '') !== 'after';
        self::$attributes = $args['attr'] ?? '';
        self::$hiddenText = '';
        self::$isExternalLink = false;
        self::$download = $args['download'] ?? '';

        self::$url = self::fixUrl(self::$url);
        self::determineLinkType();
        $attributes = self::assembleAttributes();
        self::$text = self::getText();
        self::addIcon();

        if (self::$type && in_array(self::$type, ['tel', 'gsm', 'sms', 'mobile'])) {
            $url = str_replace(' ', '', self::$url);
        } elseif (self::$type === 'pdf' && !str_starts_with(self::$url, '<span immutable')) {
            $url = dir_name(self::$url) . rawurlencode(base_name(self::$url));
        } else {
            $url = self::$url;
        }
        $str = "<a href='" . self::$proto . $url . "' $attributes>" . self::$text . "</a>";
        $str = TransVars::resolveVariables($str);

        return $str;
    } // render


    /**
     * @return void
     */
    private static function determineLinkType()
    {
        $proto = self::getProto();
        if ($proto && stripos(self::PROTO_TYPES, $proto) !== false) {
            return;
        }

        $type = (self::$args['type']??false) ?: self::$ext;
        if ($type) {
            self::$type = $type;
            switch ($type) {
                case 'pdf':
                    self::$linkCat = 'pdf';
                    self::$proto = '';
                    break;
                case 'mail':
                    self::$linkCat = 'mail';
                    self::$proto = 'mailto:';
                    break;
                case 'sms':
                case 'tel':
                case 'gsm':
                case 'geo':
                case 'slack':
                case 'twitter':
                case 'facebook':
                case 'instagram':
                case 'tiktok':
                    self::$linkCat = 'special';
                    self::$proto = "$type:";
                    break;
                default:
                    if (filter_var(self::$url, FILTER_VALIDATE_EMAIL)) {
                        self::$type = 'mail';
                        self::$linkCat = 'mail';
                        return;
                    } elseif (str_contains(self::DOWNLOAD_TYPES, ",$type,")) {
                        self::$type = 'download';
                        self::$linkCat = 'download';
                        self::compileUrl();
                    }
            }
            return;
        }
    } // determineLinkType


    /**
     * @return string
     */
    private static function getProto()
    {
        if (preg_match('/^(\w+:)(.*)/', self::$url, $m)) {
            if (preg_match('|^(https?://)(.*)|', self::$url, $mm)) {
                self::$proto = $mm[1];
                self::$url = $mm[2];
                self::$type = 'link';
                self::$linkCat = 'link';
                self::$isExternalLink = true;
            } elseif (str_starts_with(self::$url, 'pdf')) {
                self::$proto = '';
                self::$url = $m[2];
                self::$type = 'pdf';
                self::$linkCat = 'pdf';
            } else {
                self::$proto = $m[1];
                self::$url = $m[2];
                self::$type = str_replace(':', '', self::$proto);
                self::$linkCat = 'special';
            }
        } elseif (str_starts_with(self::$url, 'www.')) {
            self::$proto = 'https://';
            self::$type = 'link';
            self::$linkCat = 'link';
            self::$isExternalLink = true;

        } elseif (self::$ext === 'pdf') {
            self::$proto = '';
            self::$type = 'pdf';
            self::$linkCat = 'pdf';
        }
        return self::$proto;
    } // getProto


    /**
     * @return string
     */
    private static function assembleAttributes()
    {
        $attr = '';

        if (str_starts_with(self::$type, 'extern')) {
            if (!self::$proto) {
                self::$type = 'link';
                self::$proto = 'https://';
            }
        } elseif (str_starts_with(self::$type, 'intern')) {
            if (!self::$proto) {
                self::$type = 'link';
            }
        }

        switch (self::$linkCat) {
            case 'download':
                self::$class .= ' pfy-link-download';
                self::$download = true;
                if (!self::$text) {
                    self::$text = base_name(self::$url);
                }
                self::$title .= "{{ pfy-opens-download }}";
                break;

            case 'pdf':
                self::$class .= ' pfy-link-pdf';
                self::$text = base_name(self::$url);
                self::$target = self::$target ?: true;
                break;

            case 'zip':
                self::$class .= ' pfy-link-zip';
                self::$text = base_name(self::$url);
                self::$download = true;
                break;

            case 'image':
                self::$download = true;
                break;

            case 'special':
                self::$class .= " pfy-link-" . self::$type;
                self::$title .= "{{ pfy-opens-" . self::$type . " }}";
                break;

            case 'mail':
                self::processMailLink();
                break;

            default:
                self::processRegularLink();
        }

        if (self::$id) {
            $attr .= " id='" . self::$id . "'";
        }

        if (!self::$iconBefore) {
            self::$class .= ' pfy-icon-trailing';
        }
        $class = trim("pfy-link " . self::$class);
        $attr .= " class='" . $class . "'";

        if (self::$alt) {
            $attr .= " alt='" . self::$alt . "'";
        }

        if ((self::$target === true) || (self::$target === 'newwin')) {
            $attr .= " target='_blank' rel='noreferrer'";
            self::$title .= '{{ pfy-opens-in-new-win }}';
        } elseif (self::$target) {
            $attr .= " target='" . self::$target . "' rel='noreferrer'";
            self::$title .= '{{ pfy-opens-in-new-win }}';
        }

        if (self::$args['title'] ?? false) {
            $attr .= " title='" . self::$args['title'] . "'";
        } elseif (self::$title) {
            $attr .= " title='" . self::$title . "'";
        }

        if (self::$download) {
            $attr .= " download";
        }

        if (self::$attributes) {
            $attr .= self::$attributes;
        }
        return $attr;
    } // assembleAttributes


    /**
     * @return string
     * @throws \Exception
     */
    private static function getText()
    {
        if (self::$args['text'] ?? false) {
            self::$text = trim(markdownParagraph(self::$args['text'], true));
        } elseif (!self::$text) {
            $url = preg_replace('|^~/|', '', self::$url);
            if ($pg = page($url)) {
                self::$text = (string)$pg->title();
            } else {
                self::$text = self::$url;
            }
        }
        if (self::$args['hiddenText'] ?? false) {
            self::$hiddenText = trim(self::$args['hiddenText'] . " " . self::$hiddenText);
        }
        if (self::$hiddenText) {
            self::$text .= "<span class='pfy-invisible'>" . self::$hiddenText . "</span>";
        }
        return self::$text;
    } // getText


    /**
     * @return void
     */
    private static function processRegularLink()
    {
        if (self::$isExternalLink) {
            self::addClass('pfy-link-https pfy-external-link pfy-print-url');
            self::$target = (self::$target !== null) ? self::$target : PageFactory::$config['externalLinksToNewWindow'] ?? '';
        }
    } // processRegularLink


    /**
     * @return void
     */
    private static function processMailLink()
    {
        self::$class .= ' pfy-link-mail';
        self::$proto = 'mailto:';
        if (!self::$text) {
            self::$text = self::$url;
        }

        $subject = '';
        if (self::$args['subject'] ?? false) {
            $subject = urlencode(self::$args['subject']);
            self::$url .= "?subject=$subject";
        }
        if (self::$args['body'] ?? false) {
            $body = self::$args['body'];
            $body = unshieldStr($body, true);
            $body = str_replace(["\\n", '&#92;n', ' BR '], "\n", $body);
            $body = rawurlencode($body);
            if ($subject) {
                self::$url .= "&body=$body";
            } else {
                self::$url .= "?body=$body";
            }
        }
    } // processMailLink


    /**
     * @return void
     * @throws \Exception
     */
    private static function addIcon()
    {
        $icon = self::determineIcon();

        if ($icon) {
            $iconName = str_replace(array_keys(self::$iconReplacements), array_values(self::$iconReplacements), $icon);
            $icon = Utils::renderPfyIcon($iconName, 'pfy-link-icon');
            if (self::$iconBefore) {
                self::$text = "$icon<span class='pfy-link-text'>" . self::$text . "</span>";
            } else {
                self::$text = "<span class='pfy-link-text'>" . self::$text . "</span>$icon";
            }
        }
    } // addIcon


    /**
     * @return string
     */
    private static function determineIcon(): string
    {
        $icon = '';
        if (isset(self::$args['icon']) && (self::$args['icon'] === false)) { // explicit request no icon
            return '';
        }

        if (str_starts_with(self::$type, 'intern')) {
            $icon = '';
        } elseif (self::$linkCat === 'download') {
            $icon = 'download';

        } else {
            switch (self::$type) {
                case 'mailto':
                    $icon = 'mail';
                    break;
                case 'image':
                    $icon = 'download';
                    break;
                default:
                    if (self::$proto === 'https://' || (self::$target)) {
                        $icon = 'external';
                    } elseif (self::$type !== 'link') {
                        $icon = self::$type;
                    }
            }
        }

        return $icon;
    } // determineIcon


    /**
     * @param $class
     * @return void
     */
    private static function addClass($class)
    {
        $classes = explodeTrim(', ', $class);
        foreach ($classes as $class) {
            if (!str_contains(self::$class, $class)) {
                self::$class .= " $class";
            }
        }
    }

    /**
     * @param $url
     * @return string
     */
    public static function fixUrl($url): string
    {
        if (strpbrk($url, '<')) {
            $url = str_replace('~/<span immutable', '<span immutable', $url); // remove '~/'

            $url = str_replace(['<em>', '</em>'], '_', $url);
            $url = str_replace(['<sub>', '</sub>'], '~', $url);
            $url = str_replace(['<sup>', '</sup>'], '^', $url);
            $url = str_replace(['<mark>', '</mark>'], '==', $url);
            $url = str_replace(['<ins>', '</ins>'], '++', $url);
            $url = str_replace(['<del>', '</del>'], '~~', $url);
            $url = str_replace(['<code>', '</code>'], '`', $url);
            $url = str_replace(['<samp>', '</samp>'], '``', $url);
            $url = preg_replace('#<span class="underline">(.*?)</span>#s', '__$1__', $url);
        }
        if (page()->id() === 'home') {
            $url = str_replace('~page/', '~page/home/', $url);
        }
        return $url;
    } // fixUrl


    /**
     * @return void
     */
    private static function compileUrl()
    {
        if (str_starts_with(self::$url, '~page/')) {
            $imgFile = page()->image(basename(self::$url));
            if ($imgFile) {
                self::$url = $imgFile->url();
            }
        }
    } // compileUrl

} // LINK

