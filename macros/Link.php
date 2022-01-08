<?php

/*
 * Img() macro
 */

namespace Usility\PageFactory;

$macroConfig =  [
    'name' => strtolower( $macroName ),
    'parameters' => [
        'url' => ['Path or URL to link target', false],
        'text' => ['Link-text. If missing, "href" will be displayed instead.', false],
        'type' => ['[intern, extern or mail, pdf, sms, tel, gsm, geo, slack] "mail" renders link as "mailto:", "intern" suppresses automatic prepending of "https://", "extern" causes link target to be opened in new window.', false],
        'id' => ['ID to be applied to the &lt;a> Tag.', false],
        'class' => ['Class to be applied to the &lt;a> Tag.', false],
        'title' => ['Title attribute to be applied to the &lt;a> Tag.', false],
        'attr' => ['Generic attribute applied to the &lt;a> Tag.', false],
        'download' => ['If true, just adds "download" to tag attributes.', false],
        'altText' => ['Text appended to "text", but made visually hidden. I.e. text only available to assistive technologies.', false],
        'target' => ['[newwin] Target attribute to be applied to the &lt;a> Tag. "newwin" means opening page in new window (or tab).', null],
        'subject' => ['In case of "mail" and "sms": subject to be preset.', false],
        'body' => ['In case of "mail": mail body to be preset.', false],
    ],
    'summary' => <<<EOT
Renders an link tag.

Supported link-types: mail, pdf, sms, tel, geo, gsm, slack, twitter, tiktok, instagram, facebook.

EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class Link extends Macros
{
    public static $instanceCounter = 1;
    public $args;

    public function __construct($pfy = null)
    {
        $this->name = strtolower(__CLASS__);
        parent::__construct($pfy);
    }


    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render(array $args, string $argStr): string
    {
        $this->inx = self::$instanceCounter++;
        $this->args = &$args;
        $this->text = @$args['text'];
        $this->title = '';
        $this->class = '';
        $this->attributes = '';
        if (!$this->url = @$args['url']) {
            return '';
        }
        $this->hiddenText = '';
        $this->isExternalLink = false;

        $this->handleLinkType();

        $attributes = $this->getAttributes();

        if (!$this->text) {
            $this->text = $this->url;
        }
        if ($this->hiddenText) {
            $this->text .= "<span class='lzy-invisible'>{$this->hiddenText}</span>";
        }

        $str = "<a href='$this->url' $attributes>$this->text</a>";
        return $str;
    } // render


    /**
     * @return string|void
     * @throws \Exception
     */
    private function handleLinkType()
    {
        $url = $this->url;

        // type overrides proto:
        if ($this->args['type']) {
            $proto = $this->args['type'];
            $this->url = preg_replace('/^\w+:/', '', $this->url);
            if (!$this->args['text']) {
                $this->text = trim($this->url);
            }

        // handle url without proto:
        } elseif (!preg_match('/^(\w+):(.*)/', $this->url, $m)) {
            // check whether it's an email-address:
            if (preg_match('/^[^:@\/]+@\w/', $this->url)) {
                $proto = 'mail';
                if (!$this->args['text']) {
                    $this->text = trim($this->url);
                }

            // treat as regular link:
            } else {
                $this->handleRegularLink();
                return '';
            }

        // url with proto, e.g. 'geo:':
        } else {
            $proto = $m[1];
            $url = $m[2];
            if (!$this->args['text']) {
                $this->text = trim($m[2]);
            }
        }
        $title = '';
        $this->class .= " lzy-link-$proto";
        switch ($proto) {
            case 'mail':
            case 'mailto':
                $title = '{{ opens email app }}';
                $this->renderEmailLink();
                break;
            case 'sms':
                $title = '{{ opens messaging app }}';
                $this->text = svg('assets/pagefactory/svg-icons/message_writing.svg').$this->text;
                break;
            case 'tel':
            case 'gsm':
                $title = '{{ opens telephone app }}';
                $this->text = trim(svg('assets/pagefactory/icons/tel.svg')).$this->text;
                break;
            case 'geo':
                $title = '{{ opens map app }}';
                $this->text = svg('assets/pagefactory/svg-icons/globe.svg').$this->text;
                break;
            case 'slack':
                $title = '{{ opens slack app }}';
                $this->text = svg('assets/pagefactory/icons/slack.svg').$this->text;
                break;
            case 'twitter':
                $title = '{{ opens twitter app }}';
                $this->text = svg('assets/pagefactory/icons/twitter.svg').$this->text;
                break;
            case 'facebook':
                $title = '{{ opens facebook app }}';
                $this->text = svg('assets/pagefactory/icons/facebook.svg').$this->text;
                break;
            case 'instagram':
                $title = '{{ opens instagram app }}';
                $this->text = svg('assets/pagefactory/icons/instagram.svg').$this->text;
                break;
            case 'tiktok':
                $title = '{{ opens tiktok app }}';
                $this->text = svg('assets/pagefactory/icons/tiktok.svg').$this->text;
                break;
            case 'pdf':
                $title = '{{ opens in new window }}';
                $this->url = $url;
                $this->renderPdfLink();
                break;
            case 'file':
                break;
            default:
                $this->handleRegularLink();
        }

        if (!$this->args['title']) {
            $this->title .= $title;
        }
    } // handleLinkType


    /**
     * Renders regular links
     */
    private function handleRegularLink(): void
    {
        // naked url beginning with 'www.':
        if (stripos($this->url, 'www.') === 0) {
            $this->url = "https://$this->url";
            if ($this->text) {
                $this->class .= ' lzy-print-url';
            }
            $this->isExternalLink = true;
            $this->text = svg('assets/pagefactory/svg-icons/external.svg').$this->text;

        // url beginning with 'http':
        } elseif (stripos($this->url, 'http') === 0) {
            if ($this->args['text']) {
                $this->class .= ' lzy-print-url';
            } else {
                $this->text = $this->url;
            }
            $this->isExternalLink = true;

        } else {
            // naked url candidate or file -> check for TLD looking like an file extension:
            if (preg_match('|^ [^/\s]* \. (\w{2,8}) |x', $this->url, $m)) {
                if (stripos(",{$this->pfy->config['autoIdentifyTLDs']},", ",{$m[1]},") !== false) {
                    $this->url = "https://$this->url";
                    if ($this->text) {
                        $this->class .= ' lzy-print-url';
                    }
                    $this->isExternalLink = true;
                } else {
                    $this->handleFileType();
                }

            // at this point it must be an internal link:
            } else {
                // homepage?
                if (($this->url === '') || ($this->url === '/') || ($this->url === '~/')) {
                    $pg = PageFactory::$pages->first();
                    $this->url = $pg->url();
                    if (!$this->args['text']) {
                        $this->text = $pg->title();
                    }

                // fine page within site:
                } elseif ($pg = PageFactory::$pages->find(ltrim($this->url, '~/'))) {
                    $this->url = $pg->url();
                    if (!$this->args['text']) {
                        $this->text = $pg->title();
                    }
                } else {
                    $this->handleFileType();
                }
            }
        }
    } // handleRegularLink


    /**
     * Renders links pointing to files
     * @throws \Exception
     */
    private function handleFileType(): void
    {
        $type = strtolower(fileExt($this->url));
        switch ($type) {
            case 'pdf':
                $this->renderPdfLink();
                break;

            case 'png':
            case 'gif':
            case 'jpg':
            case 'jpeg':
                $this->url = image($this->url)->url();
                if (strpos($this->attributes, 'download') === false) {
                    $this->attributes .= ' download';
                }
                break;

            case 'txt':
            case 'doc':
            case 'docx':
            case 'xls':
            case 'xlsx':
            case 'ppt':
            case 'pptx':
            case 'odt':
            case 'ods':
            case 'odp':
                $this->renderDownloadLink();
                break;
        }
    } // handleFileType


    /**
     * Renders special case of a PDF link
     * @throws \Exception
     */
    private function renderPdfLink(): void
    {
        if (!$this->text || ($this->text === $this->url)) {
            $this->text = basename($this->url);
        }
        $this->class .= ' lzy-link-pdf';
        $this->text = svg('assets/pagefactory/icons/pdf.svg').$this->text;
        if (@$this->url[0] !== '~') {
            if ($file = page()->file($this->url)) {
                $this->url = $file->url();
                if ($this->args['target'] === null) {
                    $this->args['target'] = true;
                }
            } else {
                throw new \Exception("Error: file for '$this->url' not found.");
            }
        }
        if ($this->args['target'] === null) {
            $this->args['target'] = true;
        }
    } // renderPdfLink


    /**
     * Renders a download link
     * @throws \Exception
     */
    private function renderDownloadLink(): void
    {
        if (!$this->text || ($this->text === $this->url)) {
            $this->text = basename($this->url);
        }
        $this->class .= ' lzy-link-download';
        $this->text = svg('assets/pagefactory/svg-icons/cloud_download_alt.svg').$this->text;
        if (@$this->url[0] !== '~') {
            if ($file = page()->file($this->url)) {
                $this->url = $file->url();
                if ($this->args['target'] === null) {
                    $this->args['target'] = true;
                }
            } else {
                throw new \Exception("Error: file for '$this->url' not found.");
            }
        }
        if ($this->args['target'] === null) {
            $this->args['target'] = true;
        }
        if (!$this->args['attr']) {
            $this->args['attr'] = ' download';
        }
    } // renderDownloadLink


    /**
     * Renders an email link
     */
    private function renderEmailLink(): void
    {
        $this->class .= " lzy-link-mail";
        $this->text = svg('assets/pagefactory/svg-icons/mail.svg').$this->text;

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
        $this->url = 'mailto:'.$this->url;
    } // renderEmailLink


    /**
     * Assembles the attributes string
     * @return string
     */
    private function getAttributes(): string
    {
        $attributes = $this->attributes;
        $args = &$this->args;
        if ($args['id']) {
            $attributes .= " id='{$args['id']}'";
        }
        if ($args['class']) {
            $this->class = $args['class']." $this->class";
        } else {
            $this->class = "lzy-link lzy-link-$this->inx $this->class";
        }

        if ($args['altText']) {
            $args['text'] = "<span class='lzy-visible' aria-hidden='true'>{$args['text']}</span>";
            $this->hiddenText .= "<span class='lzy-invisible'>{$args['altText']}</span>";
        }

        if ($args['title']) {
            $this->title .= $args['title'];
        }
        if ($this->title) {
            $title = str_replace("'", '&#39;', $this->title);
            $attributes .= " title='$title'";
        }
        if ($args['attr']) {
            $attributes .= " {$args['attr']}";
        }

        // if target requested, add target and rel attr:
        // see: https://developers.google.com/web/tools/lighthouse/audits/noopener
        if (($args['target'] === true) || ($args['target'] === 'true') || ($args['target'] === 'newwin')) {
            $attributes .= " target='_blank' rel='noreferrer'";
            if (strpos($this->class, 'lzy-external-link') === false) {
                $this->class .= ' lzy-external-link lzy-icon-external';
            }
        } elseif ($args['target']) {
            $attributes .= " target='{$args['target']}' rel='noreferrer'";
            if (strpos($this->class, 'lzy-external-link') === false) {
                $this->class .= ' lzy-external-link lzy-icon-external';
            }
        }

        // if config var 'externalLinksIToNewwin' is true, add target
        if ($this->isExternalLink && $this->pfy->config['externalLinksIToNewwin']) {
            if (($this->args['target'] === null) && strpos($attributes, 'target=') === false) {
                $attributes .= " target='_blank' rel='noreferrer'";
                if (strpos($this->class, 'lzy-external-link') === false) {
                    $this->class .= ' lzy-external-link lzy-icon-external';
                }
            }
        }

        $attributes = "$attributes class='$this->class'";

        if ($args['download']) {
            $attributes .= " download";
        }

        return $attributes;
    } // getAttributes

} // Link




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
