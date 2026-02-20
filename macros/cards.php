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
            'path' => ['Path to folder, where to find the cards in subfolders.', '&#126;page/'],
            'aspectRatio' => ['Aspect ratio of cards.', '1 /1'],
            'background' => ['Background color of cards.', null],
            'hoverEffect' => ['If true, a visuel effect is activated while the pointer '.
                'hovers over a card.', false],
            'cardLiftEffect' => ['If true, cards are visually lifted up during mouse over.', false],
//            '' => ['', ''],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a collection of cards. Cards are optained from subfolders under the given path.

Cards consist of an image and the subpage's title (as defined in the meta-file).

Card image selection: First an image with the same name as the subfolder is selected. 
If no such image exists, the first image in the subfolder is chosen.

Supported image extensions: `.jpg`, `.jpeg`, `.png`, `.gif`, `.webp`

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
    $dir = getDir("$targetFolder*", associative:true, type:'folders', sort:'kirby-content-folders');

    foreach ($dir as $folder => $absFolderPath) {
        $basename = preg_replace('/^(\d{1,3}_)*/', '', $folder);
        $images = getDir("$absFolderPath*.{jpg,jpeg,png,webp,gif}", associative:true);
        $coverImg = '';
        foreach(['jpg','jpeg','png','webp','gif'] as $ext) {
            if (file_exists("$absFolderPath/$basename.$ext")) {
                $coverImg = "$basename.$ext";
                break;
            }
        }
        if (!$coverImg) {
            foreach ($images as $img => $imgFile) {
                if (!$coverImg && base_name($img, false) === $basename) {
                    $coverImg = $imgFile;
                    break;
                }
            }
            if (!$coverImg) {
                $coverImg = reset($images);
            }
        }
        $p = "$targetPath$folder/" . basename($coverImg);
        $options = [
            'src' => $p,
            'responsiveSteps' => [300, 450, 600, 900, 1200],
            'quickzoom' => false,
            'lazyLoading' => true,
            'width' => '100%',
            'wrapperClass' => 'pfy-card-img',
            'link' => "~page/$basename/",
        ];
        $img = new Image($options);
        $imgHtml = $img->render();

        $pg = page()->find($basename);
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
