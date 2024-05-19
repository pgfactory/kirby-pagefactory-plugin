<?php

namespace PgFactory\PageFactory;

define('SUPPORTED_TYPES',   ',pdf,png,gif,jpg,jpeg,txt,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,mail,mailto,file,'.
    'sms,tel,gsm,geo,slack,twitter,facebook,instagram,tiktok,');
define('PROTO_TYPES',        ',mailto:,sms:,tel:,gsm:,geo:,slack:,twitter:,facebook:,instagram:,tiktok:,');
define('DOWNLOAD_TYPES',        ',txt,doc,docx,dotx,xls,xlsx,xltx,ppt,pptx,potx,odt,ods,ots,ott,odp,otp,png,gif,jpg,jpeg,');

class Link
{
    private static $instanceCounter = 1;
    private static $url;
    private static $args;
    private static $text;
    private static $title;
    private static $id;
    private static $class;
    private static $alt;
    private static $proto;
    private static $target;
    private static $type;
    private static $linkCat;
    private static $icon;
    private static $iconBefore;
    private static $attributes;
    private static $hiddenText;
    private static $isExternalLink;
    private static $download;
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
        if (!self::$url = ($args['url'] ?? false)) {
            return '';
        }

        self::$args = $args;
        self::$text = false;
        self::$title = false;
        self::$id = $args['id'] ?? '';
        self::$class = $args['class'] ?? '';
        self::$alt = $args['alt'] ?? '';
        self::$proto = '';
        self::$target = false;
        self::$type = false;
        self::$linkCat = false;
        self::$icon = false;
        self::$iconBefore = !(($args['iconPosition'] ?? false) && ($args['iconPosition'] === 'after'));
        self::$attributes = $args['attr'] ?? '';
        self::$hiddenText = '';
        self::$isExternalLink = false;
        self::$download = $args['download'] ?? '';

        self::fixUrl();
        self::determineLinkType();
        $attributes = self::assembleAttributes();
        self::$text = self::getText();
        self::addIcon();

        $url = dir_name(self::$url).rawurlencode(base_name(self::$url));
        $str = "<a href='" . self::$proto . $url . "' $attributes>" . self::$text . "</a>";
        $str = TransVars::resolveVariables($str);

        return $str;
    }

    private static function determineLinkType()
    {
        $proto = self::getProto();
        if ($proto && (stripos(PROTO_TYPES, $proto))) {
            return;
        }

        $type = self::$args['type'] ?? '';
        if ($type) {
            self::$type = $type;
            self::$icon = $type;
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
            }
            return;
        }

        if (filter_var(self::$url, FILTER_VALIDATE_EMAIL)) {
            self::$type = 'mail';
            self::$linkCat = 'mail';
            return;
        }

        $ext = strtolower(fileExt(self::$url, false, true));
        if ($ext) {
            if ($ext === 'pdf') {
                self::$type = 'pdf';
                self::$linkCat = 'pdf';
                return;
            }
            if (stripos(DOWNLOAD_TYPES, $ext)) {
                self::$type = 'download';
                self::$linkCat = 'download';
            }
        }
    }

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

        } else {
            $ext = fileExt(self::$url);
            switch ($ext) {
                case 'pdf':
                    self::$proto = '';
                    self::$type = 'pdf';
                    self::$linkCat = 'pdf';
                    break;
            }
        }
        return self::$proto;
    }

    private static function assembleAttributes()
    {
        $attr = '';

        if (stripos(self::$type, 'exter') !== false) {
            if (!self::$proto) {
                self::$type = 'link';
                if (!self::$proto) {
                    self::$proto = 'https://';
                }
            }
            self::$icon = 'external';
        } elseif (stripos(self::$type, 'inter') !== false) {
            if (!self::$proto) {
                self::$type = 'link';
            }
            self::$icon = '';
        }

        switch (self::$linkCat) {
            case 'download':
                self::$class .= ' pfy-link-download';
                self::$download = true;
                if (!self::$icon) {
                    self::$icon = 'download';
                }
                if (!self::$text) {
                    self::$text = base_name(self::$url);
                }
                self::$title .= "{{ pfy-opens-download }}";
                break;

            case 'pdf':
                self::$class .= ' pfy-link-pdf';
                self::$icon = 'pdf';
                self::$text = base_name(self::$url);
                self::$target = true;
                break;

            case 'image':
                self::$download = true;
                self::$icon = 'download';
                break;

            case 'special':
                self::$class .= " pfy-link-" . self::$type;
                self::$icon = self::$type;
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

        if ((self::$args['target'] ?? null) !== null) {
            self::$target = self::$args['target'];
        }
        if ((self::$target === true) || (self::$target === 'newwin')) {
            $attr .= " target='_blank' rel='noreferrer'";
            self::$title .= '{{ pfy-opens-in-new-win }}';
            if (!self::$icon) {
                self::$icon = 'external';
            }
        } elseif (self::$target) {
            $attr .= " target='" . self::$target . "' rel='noreferrer'";
            self::$title .= '{{ pfy-opens-in-new-win }}';
            if (!self::$icon) {
                self::$icon = 'external';
            }
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
    }

    private static function getText()
    {
        if (self::$args['text'] ?? false) {
            self::$text = trim(compileMarkdown(self::$args['text'], true));
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
    }

    private static function processRegularLink()
    {
        if (self::$isExternalLink) {
            self::addClass('pfy-link-https pfy-external-link pfy-print-url');
            self::$target = PageFactory::$config['externalLinksToNewWindow'] ?? '';
        }
    }

    private static function processMailLink()
    {
        self::$class .= ' pfy-link-mail';
        self::$icon = 'mail';
        self::$proto = 'mailto:';
        if (!self::$text) {
            self::$text = self::$url;
        }

        if (self::$args['subject']) {
            $subject = urlencode(self::$args['subject']);
            self::$url .= "?subject=$subject";
        }
        if (self::$args['body']) {
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
    }

    private static function addIcon()
    {
        $icon = '';
        if (isset(self::$args['icon']) && (self::$args['icon'] === false)) {
            return;
        }
        if (self::$args['icon'] ?? false) {
            $icon = self::$args['icon'];
        } elseif (self::$icon) {
            $icon = self::$icon;
        } elseif (self::$isExternalLink && (PageFactory::$config['externalLinksToNewWindow'] ?? false)) {
            $icon = 'external';
        }

        if ($icon) {
            $iconName = str_replace(array_keys(self::$iconReplacements), array_values(self::$iconReplacements), $icon);
            if (iconExists($iconName)) {
                $icon = renderIcon($iconName, 'pfy-link-icon');
            } else {
                $icon = '';
            }
            if (self::$iconBefore) {
                self::$text = "$icon<span class='pfy-link-text'>" . self::$text . "</span>";
            } else {
                self::$text = "<span class='pfy-link-text'>" . self::$text . "</span>$icon";
            }
        }
    }

    private static function addClass($class)
    {
        $classes = explodeTrim(', ', $class);
        foreach ($classes as $class) {
            if (strpos(self::$class, $class) === false) {
                self::$class .= " $class";
            }
        }
    }

    private static function fixUrl()
    {
        if (strpbrk(self::$url, '<')) {
            self::$url = str_replace(['<em>', '</em>'], '_', self::$url);
            self::$url = str_replace(['<sub>', '</sub>'], '~', self::$url);
            self::$url = str_replace(['<sup>', '</sup>'], '^', self::$url);
            self::$url = str_replace(['<mark>', '</mark>'], '==', self::$url);
            self::$url = str_replace(['<ins>', '</ins>'], '++', self::$url);
            self::$url = str_replace(['<del>', '</del>'], '~~', self::$url);
            self::$url = str_replace(['<code>', '</code>'], '`', self::$url);
            self::$url = str_replace(['<samp>', '</samp>'], '``', self::$url);
            self::$url = str_replace(['<span class="underline">', '</span>'], '__', self::$url);
        }
    }
}

