<?php

/*
 * Panel Helper
*/

if (!defined('PFY_PAGE_META_FILE_BASENAME')) {
    define('PFY_PAGE_META_FILE_BASENAME', 'z');
}

/**
 * Invoked by hook 'route:before' in site/config.php
 * Copies content of .md files in given folder to page's meta file, i.e. z.txt
 * Note: this is a work-around till somebody develops a panel plugin that directly accesses .md files
 * @param $path
 * @return void
 */
function onPanelLoad($path)
{
    $allowNonPfyPages = kirby()->option('debug_checkMetaFiles');

    $path = str_replace(['+', 'panel/pages/'], ['/', ''], $path);
    if (!($pg = page($path))) {
        return;
    }
    checkMetaFiles();

    $path = $pg->root();
    $txts = glob("$path/".PFY_PAGE_META_FILE_BASENAME."*.txt");
    if (!$txts) {
        if ($allowNonPfyPages) {
            return;
        } else {
            throw new Exception("Meta-file missing in page folder (i.e. '" . PFY_PAGE_META_FILE_BASENAME . ".txt')");
        }
    }

    // read all .md files, store in $mdContents:
    $mds = glob("$path/*.md");
    $mdContents = '';
    if ($mds) {
        foreach ($mds as $file) {
            if ((basename($file))[0] === '#') {
                continue;
            }
            $md = file_get_contents($file);
            $md = preg_replace("/\n----/ms", "\n\\----", $md);
            $name = filenameToVarname($file);
            $mdContents .= "----\n$name:\n$md\n";
        }
    }

    // update .txt files with $mdContents:
    if (!$mdContents) {
        $mdContents = "----\nskipped_page-md:\n=== Skipped Page - Do not modify! ===\n";;
    }
    foreach ($txts as $txtFile) {
        $str = file_get_contents($txtFile);
        $parts = explode("\n----\n", $str);
        foreach ($parts as $i => $s) {
            if (preg_match('/^[-\w]+-md:/', trim($s))) {
                unset($parts[$i]);
            }
        }
        $out = implode("\n----\n", $parts) . "\n";
        $out .= $mdContents;
        file_put_contents($txtFile, $out);
    }
} // onPanelLoad


/**
 * Checks all page folders, creates metafiles for all supported languages if missing.
 * If it's missing and debug_checkMetaFiles is true, an exception is thrown.
 * If multilang is active, missing lang variantes are created based on the primary lang.
 * @return void
 * @throws Exception
 */
function checkMetaFiles(): void
{
    if (!kirby()->option('pgfactory.pagefactory.options.debug_checkMetaFiles')) {
        return;
    }

    if (!$language = kirby()->language()) {
        if (!$language = kirby()->defaultLanguage()) {
            $language = 'en';
        }
    }
    $langTag = '.'.$language;
    if (!$languages = kirby()->languages()->toArray()) {
        $languages = [];
        $langTag = '';
    }

    // loop over all pages:
    $pages = site()->pages()->index();
    foreach ($pages as $page) {
        $path = $page->root();
        if ((strpos($path, 'content/assets') !== false) ||
            (strpos($path, 'content/error') !== false)) {
            continue;
        }
        $primaryMetaFilename = "$path/".PFY_PAGE_META_FILE_BASENAME."$langTag.txt";
        if (!file_exists($primaryMetaFilename)) {
            $primaryMetaFilename0 = "$path/".PFY_PAGE_META_FILE_BASENAME.".txt";
            if (file_exists($primaryMetaFilename0)) {
                if ($languages) {
                    rename($primaryMetaFilename0, $primaryMetaFilename);
                }
            } else {
                continue;
            }
        }
        foreach ($languages as $lang) {
            $lang = $lang['code'];
            $metaFilename = "$path/".PFY_PAGE_META_FILE_BASENAME.".$lang.txt";
            if (($primaryMetaFilename === $metaFilename) || file_exists($metaFilename)) {
                continue;
            }
            copy($primaryMetaFilename, $metaFilename);
        }
    }
} // checkMetaFiles



/**
 * Invoked by hook 'page.create:after' in site/config.php
 * When user creates a page in panel, meta-file is renamed to PFY_PAGE_META_FILE_BASENAME (i.e. z.txt) and
 * an .md file is created with H1 preset to pagename
 * Note: this is a work-around till somebody develops a panel plugin that directly accesses .md files
 * @param \Kirby\Cms\Page $page
 * @return void
 */
function onPageCreateAfter(Kirby\Cms\Page $page)
{
    $basename = $page->slug();

    $filename = "1_$basename.md";
    $newPageTitle = $page->title();
    $md = "\n\n# $newPageTitle\n\n";
    file_put_contents($page->root() . '/' . $filename, $md);

    $propertyData = $page->propertyData();
    $template = $propertyData['template']??'';

    // rename .txt file to '~page.xy.txt' if necessary:
    // -> this activates the automatic blueprint
    $path = 'content/' . $page->diruri() . '/';
    $languages = kirby()->language();
    $lang = $languages ? '.'.$languages->code() : '';
    $origMetaFile = "$path$template$lang.txt";
    $metaFilename = PFY_PAGE_META_FILE_BASENAME."$lang.txt";
    $newMetaFile = "$path$metaFilename";
    if (!file_exists($origMetaFile)) {
        return;
    }
    $varname = filenameToVarname($filename);
    file_put_contents($origMetaFile, "\n\n----\n$varname:\n\n$md", FILE_APPEND);
    rename($origMetaFile, $newMetaFile);
} // onPageCreateAfter



/**
 * Invoked by hook 'page.update:after' in site/config.php
 * Reads page's metafile, finds fields containing content data, updates corresponding .md files
 * Note: this is a work-around till somebody develops a panel plugin that directly accesses .md files
 * @param \Kirby\Cms\Page $newPage
 * @return void
 * @throws \Kirby\Exception\InvalidArgumentException
 */
function onPageUpdateAfter(Kirby\Cms\Page $newPage)
{
    // export data from auto.lang.txt to md-file:
    $content = $newPage->content()->data();
    $root = $newPage->root();
    $mds = glob("$root/*.md");
    foreach ($content as $k => $text) {
        if (substr($k, -3) !== '_md') {
            continue;
        }
        // find corresponding file:
        $file = false;
        foreach ($mds as $mdFile) {
            if (filenameToVarname($mdFile, false) === $k) {
                $file = $mdFile;
                break;
            }
        }
        if ($file) {
            file_put_contents($file, $text);
        }
    }
} // onPageUpdateAfter



/**
 * @param \Kirby\Cms\File $newFile
 * @return void
 * @throws \Kirby\Exception\InvalidArgumentException
 */
function onFileUpdateAfter(Kirby\Cms\File $newFile)
{
    $content = $newFile->content()->data();
    foreach ($content as $k => $text) {
        if (substr($k, -3) !== '_md') {
            continue;
        }
        $file = $newFile->parent()->root() . '/' . substr($k, 0, -3) . '.md';
        file_put_contents($file, $text);
    }
} // onFileUpdateAfter



/**
 * Invoked by hook 'blueprints' in site/plugins/pagefactory/index.php on 'panel/pages'
 * When user opens panel, dynamically creates a blueprint featuring editing fields form .md files
 * @return array
 */
function assembleBlueprint()
{
    $callPath = str_replace('+', '/', kirby()->path());
    $pgId = str_replace(['panel/pages/', 'api/pages/'], '', $callPath);
    $pgId = preg_replace('#/(sections|lock).*#', '', $pgId);
    $session = kirby()->session();
    if (strpos($callPath, 'panel/pages') !== 0) {
        $blueprint = $session->get($pgId);
        return $blueprint;
    }
    $basename = basename($pgId);

    $blueprint = [];
    $path = getPagePath($pgId);
    if ($path && file_exists($path)) {
        $mds = glob("$path/*.md");
        if (!$mds) {
            $mds = ["$path/skipped-page.md"];
        }
        $blueprint = [
            'Title' => $basename,
            'tabs'  => [],
        ];
        $sidebar = getSidebar();
        foreach ($mds as $i => $file) {
            $tab = getTab($basename, $file);
            $tab['columns']['right'] = $sidebar;
            $blueprint['tabs']["tab$i"] = $tab;
        }
        $session->set($pgId, $blueprint);
    }
    return $blueprint;
} // assembleBlueprint



/**
 * Finds the filesystem path of a page, recursively and independent of page state.
 * @param $pattern
 * @return string
 */
function getPagePath($pattern)
{
    $elems = explode('/', $pattern);
    $obj = site();
    foreach ($elems as $elem) {
        $obj = $obj->findPageOrDraft($elem);
    }
    if ($obj) {
        $path = $obj->root();
    } else {
        $path = null;
    }
    return $path;
} // getPagePath



/**
 * Helper to assembleBlueprint()
 * Renders blueprint fragment for side bar -> pages and files
 * @return array
 * @throws \Kirby\Exception\InvalidArgumentException
 */
function getSidebar()
{
    $str = <<<EOT
# right:
width: 1/3
sections:
    # a list of subpages
    pages:
        type: pages
        label: Subpages
    # a list of files
    files:
        type: files
        label: Files

EOT;
    return \Kirby\Data\Yaml::decode($str, 'yaml');
} // getSidebar



/**
 * Helper to assembleBlueprint()
 * Renders blueprint fragment for tab containing md editor
 * @param $basename
 * @param $file
 * @return array
 * @throws \Kirby\Exception\InvalidArgumentException
 */
function getTab($basename, $file)
{
    $name = filenameToVarname($file, false);
    $str = <<<EOT
# tab:
label: $name.md
icon: text
columns:
  # main
  left:
    width: 2/3
    sections:
      section_{$basename}_$name:
        type: fields
        fields:
          $name:
            # label: $name
            type: textarea
            size: huge

EOT;
    return \Kirby\Data\Yaml::decode($str, 'yaml');
} // getTab



/**
 * Helper: converts a filename to a form compatible with meta-file resp. blueprint
 * Note: conversion is not reversible, original file needs to be found by searching dir.
 * @param $filename
 * @param $dashedResponse
 * @return array|string|string[]|null
 */
function filenameToVarname($filename, $dashedResponse = true)
{
    if ($dashedResponse) {
        $str = preg_replace('/[_\W]/', '-', basename($filename));
    } else {
        $str = preg_replace('/[_\W]/', '_', basename($filename));
    }
    return $str;
} // filenameToVarname

