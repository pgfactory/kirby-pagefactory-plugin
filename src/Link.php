<?php

namespace PgFactory\PageFactory;


define('SUPPORTED_TYPES',   ',pdf,png,gif,jpg,jpeg,txt,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,mail,mailto,file,'.
    'sms,tel,gsm,geo,slack,twitter,facebook,instagram,tiktok,');
define('PROTO_TYPES',        ',mailto:,sms:,tel:,gsm:,geo:,slack:,twitter:,facebook:,instagram:,tiktok:,');
define('DOWNLOAD_TYPES',        ',txt,doc,docx,dotx,xls,xlsx,xltx,ppt,pptx,potx,odt,ods,ots,ott,odp,otp,png,gif,jpg,jpeg,');

class Link
{
    public static $instanceCounter = 1;
    public $inx;
    public $url;
    private $args;
    private $text;
    private $title;
    private $id;
    private $class;
    private $alt;
    private $proto;
    private $target;
    private $type;
    private $linkCat;
    private $icon;
    private $iconBefore;
    private $attributes;
    private $hiddenText;
    private $isExternalLink;
    private $download;
    private $iconReplacements = [
        'gsm' => 'mobile',
        'mailto' => 'mail',
    ];


    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render(array $args): string
    {
        if (!$this->url = ($args['url']??false)) {
            return '';
        }

        $this->inx = self::$instanceCounter++;
        $this->args = &$args;
        $this->text = false;
        $this->title = false;
        $this->id = $args['id']??'';
        $this->class = $args['class']??'';
        $this->alt = $args['alt']??'';
        $this->proto = '';
        $this->target = false;
        $this->type = false;
        $this->linkCat = false;
        $this->icon = false;
        $this->iconBefore = !(($args['iconPosition']??false) && ($args['iconPosition'] === 'after'));
        $this->attributes = $args['attr']??'';
        $this->hiddenText = '';
        $this->isExternalLink = false;
        $this->download = $this->args['download']??'';

        $this->fixUrl();

        $this->determineLinkType();

        $attributes = $this->assembleAttributes();

        $this->text = $this->getText();

        $this->addIcon();

        $str = "<a href='$this->proto$this->url' $attributes>$this->text</a>";
        $str = TransVars::resolveVariables($str);
        return $str;
    } // render


    /**
     * Attempts to determines the type of this link based on type-arg, proto, extension etc.
     * @return void
     */
    private function determineLinkType()
    {
        $proto = $this->getProto();
        if ($proto && (stripos(PROTO_TYPES, $proto))) {
            return;
        }

        $type = $this->args['type']??'';
        if ($type) {
            $this->type = $type;
            $this->icon = $type;
            switch ($type) {
                case 'pdf':
                    $this->linkCat = 'pdf';
                    $this->proto = '';
                    break;
                case 'mail':
                    $this->linkCat = 'mail';
                    $this->proto = 'mailto:';
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
                    $this->linkCat = 'special';
                    $this->proto = "$type:";
                    break;
            }
            return;
        }

        // email:
        if (filter_var($this->url, FILTER_VALIDATE_EMAIL)) {
            $this->type = 'mail';
            $this->linkCat = 'mail';
            return;
        }

        $ext = strtolower(fileExt($this->url, false, true));
        if ($ext) {
            if ($ext === 'pdf') {
                $this->type = 'pdf';
                $this->linkCat = 'pdf';
                return;
            }
            if (stripos(DOWNLOAD_TYPES, $ext)) {
                $this->type = 'download';
                $this->linkCat = 'download';
            }
        }
    } // determineLinkType


    /**
     * Extracts the proto part of an url, presets category, type where possible
     * @return mixed|string
     */
    private function getProto()
    {
        if (preg_match('/^(\w+:)(.*)/', $this->url, $m)) {
            // link with standard proto 'http' or 'https':
            if (preg_match('|^(https?://)(.*)|', $this->url, $mm)) {
                $this->proto = $mm[1];
                $this->url = $mm[2];
                $this->type = 'link';
                $this->linkCat = 'link';
                $this->isExternalLink = true;

                // link with some other proto, e.g. 'tel:':
            } elseif (stripos($this->url, 'pdf') === 0) {
                $this->proto = '';
                $this->url = $m[2];
                $this->type = 'pdf';
                $this->linkCat = 'pdf';
            } else {
                $this->proto = $m[1];
                $this->url = $m[2];
                $this->type = str_replace(':','', $this->proto);
                $this->linkCat = 'special';
            }
        } elseif (stripos($this->url, 'www.') === 0) {
            $this->proto = 'https://';
            $this->type = 'link';
            $this->linkCat = 'link';
            $this->isExternalLink = true;
        }
        return $this->proto;
    } // getProto


    /**
     * Assembles link tag attributes based on type, proto, text, class etc.
     * @return string
     */
    private function assembleAttributes()
    {
        $attr = '';
        // intercept special cases of type: internal / external:
        if (stripos($this->type, 'exter') !== false) {
            if (!$this->proto) {
                $this->type = 'link';
                if (!$this->proto) {
                    $this->proto = 'https://';
                }
            }
            $this->icon = 'external';
        } elseif (stripos($this->type, 'inter') !== false) {
            if (!$this->proto) {
                $this->type = 'link';
            }
            $this->icon = '';
        }

        // handle link categories:
        switch($this->linkCat) {
            case 'download':
                $this->class .= ' pfy-link-download';
                $this->download = true;
                if (!$this->icon) {
                    $this->icon = 'download';
                }
                if (!$this->text) {
                    $this->text = base_name($this->url);
                }
                $this->title .= "{{ pfy-opens-download }}";
                break;

            case 'pdf':
                $this->class .= ' pfy-link-pdf';
                $this->icon = 'pdf';
                $this->text = base_name($this->url);
                $this->target = true;
                break;

            case 'image':
                $this->download = true;
                $this->icon = 'download';
                break;

            case 'special':
                $this->class .= " pfy-link-$this->type";
                $this->icon = $this->type;
                $this->title .= "{{ pfy-opens-$this->type }}";
                break;

            case 'mail':
                $this->processMailLink();
                break;

            default:
                $this->processRegularLink();
        }

        // id:
        if ($this->id) {
            $attr .= " id='$this->id'";
        }

        // icon:
        if (!$this->iconBefore) {
            $this->class .= ' pfy-icon-trailing';
        }
        $class = trim("pfy-link $this->class");
        $attr .= " class='$class'";

        // alt:
        if ($this->alt) {
            $attr .= " alt='$this->alt'";
        }

        // target:
        if (($this->args['target']??null) !== null) {
            $this->target = $this->args['target'];
        }
        if (($this->target === true) || ($this->target === 'newwin')) {
            $attr .= " target='_blank' rel='noreferrer'";
            $this->title .= '{{ pfy-opens-in-new-win }}';
            if (!$this->icon) {
                $this->icon = 'external';
            }
        } elseif ($this->target) {
            $attr .= " target='{$this->target}' rel='noreferrer'";
            $this->title .= '{{ pfy-opens-in-new-win }}';
            if (!$this->icon) {
                $this->icon = 'external';
            }
        }

        // title:
        if ($this->args['title']??false) {
            $attr .= " title='{$this->args['title']}'";
        } elseif ($this->title) {
            $attr .= " title='$this->title'";
        }

        // download:
        if ($this->download) {
            $attr .= " download";
        }

        // explicit attributes:
        if ($this->attributes) {
            $attr .= $this->attributes;
        }
        return $attr;
    } // assembleAttributes


    /**
     * Determines the link text where not explicitly given
     * @return mixed|string
     */
    private function getText()
    {
        if ($this->args['text']??false) {
            $this->text = trim(compileMarkdown($this->args['text'], true));

        } elseif (!$this->text) {
            $url = $this->url;

            // check whether url points to a page within this site:
            if ($url[0] === '~') {
                $url = Utils::resolveUrl($url);
            }
            $pg = page($url);
            if ($pg) {
                $this->text = (string)$pg->title();
            } else {
                // page not found -> use url instead:
                $this->text = $this->url;
            }
        }
        if ($this->args['hiddenText']??false) {
            $this->hiddenText = trim("{$this->args['hiddenText']} $this->hiddenText");
        }
        if ($this->hiddenText) {
            $this->text .= "<span class='pfy-invisible'>{$this->hiddenText}</span>";
        }
        return $this->text;
    } // getText


    /**
     * Processes Regular Links
     * @return void
     */
    private function processRegularLink()
    {
        if ($this->isExternalLink) {
            $this->addClass('pfy-link-https pfy-external-link pfy-print-url');
            $this->target = PageFactory::$config['externalLinksToNewWindow']??'';
        }
    } // processRegularLink


    /**
     * Processes E-Mail Links
     * @return void
     */
    private function processMailLink()
    {
        $this->class .= ' pfy-link-mail';
        $this->icon = 'mail';
        $this->proto = 'mailto:';
        if (!$this->text) {
            $this->text = $this->url;
        }

        // handle subject and body arguments:
        if ($this->args['subject']) {
            $subject = urlencode($this->args['subject']);
            $this->url .= "?subject=$subject";
        }
        if ($this->args['body']) {
            $body = $this->args['body'];
            // body arg can contain spec. cars, e.g. line-breaks, so it may be shielded by the user:
            $body = unshieldStr($body, true);
            // handle line-breaks:
            $body = str_replace(["\\n", '&#92;n', ' BR '], "\n", $body);
            $body = rawurlencode($body);
            if ($subject) {
                $this->url .= "&body=$body";
            } else {
                $this->url .= "?body=$body";
            }
        }
    } // processMailLink


    /**
     * Adds the link icon
     * @return void
     * @throws \Exception
     */
    private function addIcon()
    {
        $icon = '';
        if (isset($this->args['icon']) && ($this->args['icon'] === false)) {
            return;
        }
        if ($this->args['icon']??false) {
            $icon = $this->args['icon'];
        } elseif ($this->icon) {
            $icon = $this->icon;
        } elseif ($this->isExternalLink && (PageFactory::$config['externalLinksToNewWindow']??false)) {
            $icon = 'external';
        }

        if ($icon) {
            $iconName = str_replace(array_keys($this->iconReplacements), array_values($this->iconReplacements), $icon);
            if (iconExists($iconName)) {
                $icon = renderIcon($iconName, 'pfy-link-icon');
            } else {
                $icon = '';
            }
            if ($this->iconBefore) {
                $this->text = "$icon<span class='pfy-link-text'>$this->text</span>";
            } else {
                $this->text = "<span class='pfy-link-text'>$this->text</span>$icon";
            }
        }
    } // addIcon


    /**
     * Adds a class, unless already added
     * @param $class
     * @return void
     */
    private function addClass($class)
    {
        $classes = explodeTrim(', ', $class);
        foreach ($classes as $class) {
            if (strpos($this->class, $class) === false) {
                $this->class .= " $class";
            }
        }
    } // addClass


    /**
     * Fixes special case where some wierd pattern in the path has triggered MD-compilation
     * @return void
     */
    private function fixUrl()
    {
        if (strpbrk($this->url, '<')) {
            $this->url = str_replace(['<em>','</em>'], '_', $this->url);
            $this->url = str_replace(['<sub>','</sub>'], '~', $this->url);
            $this->url = str_replace(['<sup>','</sup>'], '^', $this->url);
            $this->url = str_replace(['<mark>','</mark>'], '==', $this->url);
            $this->url = str_replace(['<ins>','</ins>'], '++', $this->url);
            $this->url = str_replace(['<del>','</del>'], '~~', $this->url);
            $this->url = str_replace(['<code>','</code>'], '`', $this->url);
            $this->url = str_replace(['<samp>','</samp>'], '``', $this->url);
            $this->url = str_replace(['<span class="underline">','</span>'], '__', $this->url);
        }
    } // fixUrl

} // Link


