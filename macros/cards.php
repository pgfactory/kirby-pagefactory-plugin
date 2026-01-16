<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use PgFactory\PageFactoryElements\PageElements;

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'path' => ['(Default: &#126;page/)', null],
            'aspectRatio' => ['', '1 /1'],
            'background' => ['', null],
            'hoverEffect' => ['', false],
            'cardLiftEffect' => ['', false],
//            '' => ['', ''],
        ],
        'summary' => <<<EOT

# $funcName()

ToDo: describe purpose of function
EOT,
    ];

    $wrapperClass = 'pfy-cards';
    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    if ($aspectRatio = ($options['aspectRatio'] ?? false)) {
        Page::addCss(".pfy-card {aspect-ratio: $aspectRatio;}");
    }

    if ($background = ($options['background'] ?? false)) {
        Page::addCss(".pfy-card-content {background: $background;}");
    }

    if ($options['hoverEffect'] ?? false) {
        $wrapperClass .= ' pfy-cards-hover';
    }

    if ($options['cardLiftEffect'] ?? false) {
        $wrapperClass .= ' pfy-cards-lift';
    }

    // assemble output:
    $str .= '';
    $targetPath = $options['path'] ?? '~page/';
    $targetFolder = resolvePath($targetPath);
    $dir = getDir("$targetFolder*", associative:true, type:'folders');

    foreach ($dir as $folder => $absFolderPath) {
        $basename = preg_replace('/^(\d_)*/', '', $folder);
        $images = getDir("$absFolderPath*.{jpg,jpeg,png,webp,gif}", associative:true);
        $coverImg = '';
        foreach ($images as $img => $imgFile) {
            if (!$coverImg && base_name($img, false) === $basename) {
                $coverImg = $imgFile;
                break;
            }
        }
        if (!$coverImg) {
            $coverImg = reset($images);
        }

        $p = "$targetPath$folder/" . basename($coverImg);
        $options = [
            'src' => $p,
            'responsiveSteps' => [300, 450, 600, 900, 1200],
            'quickzoom' => false,
            'lazyLoading' => true,
            'width' => '100%',
            'wrapperClass' => 'card_image',
            'link' => "~page/$basename/",
        ];
        $img = new Image($options);
        $imgHtml = $img->render();

        $pgId = preg_replace('/^(\d_)*/', '', $folder);
        $pg = page()->find($pgId);
        $pageName = $pg->title()->value();
        $link = Link::render([
            'url' => "~page/$basename/",
            'text' => $pageName,
        ]);
        $str .= <<<EOT
<div class="pfy-card">
$imgHtml
    <div class="pfy-card-content">
        $link
    </div>
</div>

EOT;
    }

    $str = <<<EOT
<ul class="$wrapperClass">
$str
</ul>
EOT;

    Page::addAssets('CARDS');
    PageElements::loadIcons();

    return $str;
};
