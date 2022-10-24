<?php

/*
 * Panel Helper
*/

/**
 * Invoked by hook 'route:before' in site/config.php
 * Copies content of .md files in given folder to page's meta file, i.e. zzz_page.txt
 * Note: this is a work-around till somebody develops a panel plugin that directly accesses .md files
 * @param $path
 * @return void
 */
function onPanelLoad($path)
{
    $path = str_replace(['+', 'panel/pages/'], ['/', ''], $path);
    if (!($pg = page($path))) {
        return;
    }
    $path = $pg->root();
    $txts = glob("$path/".PFY_PAGE_DEF_BASENAME."*.txt");
    if (!$txts) {
        return;
    }

    // read all .md files, store in $mdContents:
    $mds = glob("$path/*.md");
    $mdContents = '';
    if ($mds) {
        foreach ($mds as $file) {
            $md = file_get_contents($file);
            $md = preg_replace("/\n----/ms", "\n\\----", $md);
            $name = filenameToVarname($file);
            $mdContents .= "----\n$name:\n$md\n";
        }
    }

    // update .txt files with $mdContents:
    foreach ($txts as $txtFile) {
        $str = file_get_contents($txtFile);
        $parts = explode("\n----\n", $str);
        foreach ($parts as $i => $s) {
            if (preg_match('/^[-\w]+-md:/', trim($s))) {
                unset($parts[$i]);
            }
        }
        $out = implode("\n----\n", $parts)."\n";
        $out .= $mdContents;
        file_put_contents($txtFile, $out);
    }
} // onPanelLoad



/**
 * Invoked by hook 'page.create:after' in site/config.php
 * When user creates a page in panel, meta-file is renamed to PFY_PAGE_DEF_BASENAME (i.e. zzz_page.txt) and
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
    $template = $propertyData['template'];

    // rename .txt file to '~page.xy.txt' if necessary:
    // -> this activates the automatic blueprint
    $path = 'content/' . $page->diruri() . '/';
    $lang = kirby()->language()->code();
    $metaFilename = PFY_PAGE_DEF_BASENAME.".$lang.txt";
    $tmpl1 = "$path$metaFilename";
    if ($template !== PFY_PAGE_DEF_BASENAME) {
        // rename if necessary:
        $tmpl0 = "$path$template.$lang.txt";
        rename($tmpl0, $tmpl1);
    } else {
        $tmpl1 = "$path$metaFilename";
    }
    // add field refering to md content:
    $varname = filenameToVarname($filename);
    file_put_contents($tmpl1, "\n\n----\n$varname:\n\n$md", FILE_APPEND);
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
    //if (preg_match('|^panel/pages/(.*) /files/ (.+) \.md|x', $callPath, $m)) {
    //    $callPath = $m[1];
    //}
    $basename = basename($pgId);

    $targetPage = page($pgId);
    if (!$targetPage) {
        return $session->get($pgId);
    }
    $path = $targetPage->root();
    if (file_exists($path) && ($mds = glob("$path/*.md"))) {
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

