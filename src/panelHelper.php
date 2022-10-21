<?php

/*
 * Panel Helper
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



function onPageCreateAfter(Kirby\Cms\Page $page)
{
    $basename = $page->slug();
    $propertyData = $page->propertyData();
    $template = $propertyData['template'];
    // rename .txt file to '~page.xy.txt' if necessary:
    // -> this activates the automatic blueprint
    if ($template !== PFY_PAGE_DEF_BASENAME) {
        $path = $page->diruri() . '/';
        $lang = kirby()->language()->code();
        $tmpl0 = "content/$path$template.$lang.txt";
        $tmpl1 = "content/$path".PFY_PAGE_DEF_BASENAME.".$lang.txt";
        rename($tmpl0, $tmpl1);
    }

    $filename = "1_$basename.md";
    $newPageTitle = $page->title();
    $md = "\n\n# $newPageTitle\n\n";
    file_put_contents($page->root() . '/' . $filename, $md);
} // onPageCreateAfter



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


function getTab($basename, $file)
{
    $name = str_replace(['-','.'], '_', basename($file, '.md'));
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
          {$name}_md:
            # label: $name
            type: textarea
            size: huge

EOT;
    return \Kirby\Data\Yaml::decode($str, 'yaml');
} // getTab


function filenameToVarname($filename, $dashedResponse = true)
{
    if ($dashedResponse) {
        $str = preg_replace('/[_\W]/', '-', basename($filename));
    } else {
        $str = preg_replace('/[_\W]/', '_', basename($filename));
    }
    return $str;
} // filenameToVarname

