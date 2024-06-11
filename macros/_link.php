<?php
namespace PgFactory\PageFactory;

/*
 * Twig extension
 */

require_once __DIR__.'/../src/Link.php';

return function($argStr = '')
{
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'url' => ['Path or URL to link target', false],
            'text' => ['Link-text. If missing, "href" will be displayed instead.', false],
            'type' => ['[intern, extern or mail, pdf, sms, tel, gsm, geo, slack] "mail" renders link as "mailto:", "intern" suppresses automatic prepending of "https://", "extern" causes link target to be opened in new window.', false],
            'id' => ['ID to be applied to the &lt;a> Tag.', false],
            'class' => ['Class to be applied to the &lt;a> Tag.', false],
            'title' => ['Title attribute to be applied to the &lt;a> Tag.', false],
            'icon' => ['Icon to be added to the link text.', null],
            'iconPosition' => ['[before,after] Where to add the icon.', 'before'],
            'attr' => ['Generic attribute applied to the &lt;a> Tag.', false],
            'download' => ['If true, just adds "download" to tag attributes.', false],
            'hiddenText' => ['Text appended to "text", but made visually hidden. I.e. text only available to assistive technologies.', false],
            'target' => ['[newwin] Target attribute to be applied to the &lt;a> Tag. "newwin" means opening page in new window (or tab).', null],
            'subject' => ['In case of "mail" and "sms": subject to be preset.', false],
            'body' => ['In case of "mail": mail body to be preset.', false],
            'href' => ['Synonyme for "url"', false],
        ],
        'summary' => <<<EOT
# link()

Renders an HTML-link (\<a> tag).

Supported link-types: mail, pdf, sms, tel, geo, gsm, slack, twitter, tiktok, instagram, facebook.

EOT,
        'assetsToLoad' => '',
    ];

    // parse arguments, handle help and showSource:
    if (is_string($str = TransVars::initMacro(__FILE__, $config, $argStr))) {
        return $str;
    } else {
        list($args, $str) = $str;
    }

    if ($args['href']) {
        $args['url'] = $args['href'];
    }
    // assemble output:
    $str .= Link::render($args);

    return $str;
}; // link

