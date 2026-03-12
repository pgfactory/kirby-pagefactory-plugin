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
 * @param string $pageRef
 * @return void
 */
function onPanelLoad(string $pageRef): void
{
    $allowNonPfyPages = kirby()->option('pgfactory.pagefactory.debug_checkMetaFiles');

    $id = str_replace(['+', 'panel/pages/'], ['/', ''], $pageRef);
    if (!($pg = page($id))) {
        return;
    }
    checkMetaFiles();

    $path = $pg->root();
    $txtFiles = glob("$path/".PFY_PAGE_META_FILE_BASENAME."*.txt");
    if (!$txtFiles) {
        if ($allowNonPfyPages) {
            return;
        } else {
            throw new Exception("Meta-file missing in page folder (i.e. '" . PFY_PAGE_META_FILE_BASENAME . ".txt')");
        }
    }

    // read all .md files, merge into fields:
    $mdFiles = getMdFiles($path);
    $fields = [];
    if ($mdFiles) {
        $fields = $pg->content()->data();
        foreach ($mdFiles as $file) {
            $md = file_get_contents($file);

            // shield frontmatter from being interpreted as fields:
            $md = preg_replace("/\n----/", "\n\\----", $md);
            $name = filenameToVarname($file);
            $fields[$name] = $md;
        }
    }

    // update .txt files with field data:
    $txt = '';
    foreach ($fields as $fieldName => $fieldValue) {
        $fieldName = ucfirst($fieldName);
        if (str_ends_with($fieldName, '_md') || str_contains($fieldValue, "\n")) {
            $txt .= "\n$fieldName:\n\n$fieldValue\n\n----\n";
        } else {
            $txt .= "\n$fieldName: $fieldValue\n\n----\n";
        }
    }
    foreach ($txtFiles as $txtFile) {
        file_put_contents($txtFile, $txt);
    }
} // onPanelLoad


/**
 * Checks all page folders, creates metafiles for all supported languages if missing.
 * If multilang is active, missing lang variants are created based on the primary lang.
 * @return void
 */
function checkMetaFiles(): void
{
    if (!kirby()->option('pgfactory.pagefactory.debug_checkMetaFiles')) {
        return;
    }

    $language = kirby()->language() ?: kirby()->defaultLanguage();
    $langCode = $language ? $language->code() : 'en';
    $languages = kirby()->languages()->toArray();
    $langTag = $languages ? ".$langCode" : '';

    // loop over all pages:
    $pages = site()->pages()->index();
    foreach ($pages as $page) {
        $path = $page->root();
        if (str_contains($path, 'content/assets') ||
            str_contains($path, 'content/error')) {
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
            $code = $lang['code'];
            $metaFilename = "$path/".PFY_PAGE_META_FILE_BASENAME.".$code.txt";
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
function onPageCreateAfter(Kirby\Cms\Page $page): void
{
    $basename = $page->slug();

    $filename = "1_$basename.md";
    $newPageTitle = $page->title();
    $md = "\n\n# $newPageTitle\n\n";
    file_put_contents($page->root() . '/' . $filename, $md);

    $propertyData = $page->propertyData();
    $template = $propertyData['template'] ?? '';

    // rename .txt file to '~page.xy.txt' if necessary:
    // -> this activates the automatic blueprint
    $path = 'content/' . $page->diruri() . '/';
    $language = kirby()->language();
    $lang = $language ? '.' . $language->code() : '';
    $origMetaFile = "$path$template$lang.txt";
    $metaFilename = PFY_PAGE_META_FILE_BASENAME . "$lang.txt";
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
 */
function onPageUpdateAfter(Kirby\Cms\Page $newPage): void
{
    // export data from auto.lang.txt to md-file:
    $fields = $newPage->content()->data();
    $root = $newPage->root();
    $mdFiles = getMdFiles($root);
    foreach ($fields as $fieldName => $text) {
        if (!str_ends_with($fieldName, '_md')) {
            continue; // skip any non-md fields
        }
        // find corresponding file:
        $file = false;
        foreach ($mdFiles as $mdFile) {
            if (filenameToVarname($mdFile) === $fieldName) {
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
 * Invoked by hook 'blueprints' in site/plugins/pagefactory/index.php on 'panel/pages'
 * When user opens panel, dynamically creates a blueprint featuring editing fields from .md files
 * @return array
 */
function assembleBlueprint(): array
{
    $callPath = str_replace('+', '/', kirby()->path());
    $pgId = str_replace(['panel/pages/', 'api/pages/'], '', $callPath);
    $pgId = preg_replace('#/(sections|lock).*#', '', $pgId);
    $basename = basename($pgId);

    $blueprint = [
        'title' => $basename,
        'tabs' => getFirstTab(),
    ];
    $path = getPagePath($pgId);
    if ($path && file_exists($path)) {
        $mdFiles = getMdFiles($path);
        $sidebar = getSidebar();
        foreach ($mdFiles as $i => $file) {
            $tab = getMdEditorTab($basename, $file);
            $tab['columns']['right'] = $sidebar;
            $blueprint['tabs']["tab$i"] = $tab;
        }
    }

    return $blueprint;
} // assembleBlueprint


/**
 * Returns .md files in the given path, excluding files starting with '#'.
 * @param string $path
 * @return array
 */
function getMdFiles(string $path): array
{
    $mdFiles = glob("$path/*.md");
    if (!$mdFiles) {
        return [];
    }
    return array_values(array_filter($mdFiles, function ($file) {
        return basename($file)[0] !== '#';
    }));
} // getMdFiles


/**
 * Finds the filesystem path of a page, recursively and independent of page state.
 * @param string $pattern
 * @return string|null
 */
function getPagePath(string $pattern): ?string
{
    $elems = explode('/', $pattern);
    $obj = site();
    foreach ($elems as $elem) {
        $obj = $obj->findPageOrDraft($elem);
        if (!$obj) {
            return null;
        }
    }
    return $obj->root();
} // getPagePath


/**
 * Helper to assembleBlueprint()
 * Renders blueprint fragment for side bar -> pages and files
 * @return array
 */
function getSidebar(): array
{
    return [
        'width' => '1/3',
        'sections' => [
            'pages' => [
                'type' => 'pages',
                'label' => 'Subpages'
            ],
            'files' => [
                'type' => 'files',
                'label' => 'Files',
            ]
        ]
    ];
} // getSidebar


/**
 * Helper to assembleBlueprint()
 * Renders blueprint fragment for tab containing md editor
 * @param string $basename
 * @param string $file
 * @return array
 */
function getMdEditorTab(string $basename, string $file): array
{
    $name = filenameToVarname($file);
    $filename = basename($file);
    return [
        'label' => $filename,
        'icon'  => 'text',
        'columns' => [
            'left' => [
                'width' => '2/3',
                'sections' => [
                    "section_{$basename}_$name" => [
                        'type'   => 'fields',
                        'fields' => [
                            $name => [
                                'label' => $filename,
                                'type'  => 'textarea',
                                'size'  => 'huge',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
} // getMdEditorTab


/**
 * Helper to assemble the first tab in the default blueprint featuring ContentBlocks.
 * @return array
 */
function getFirstTab(): array
{
    return [
        'CodeBlocks' => [
            'label' => 'Editor',
            'icon' => 'page',
            'columns' => [
                'main' => [
                    'width' => '2/3',
                    'sections' => [
                        'fields00' => [
                            'type' => 'fields',
                            'fields' => [
                                'contentblocks' => [
                                    'type' => 'blocks',
                                    'label' => 'Content',
                                    'size' => 'huge'
                                ]
                            ]
                        ]
                    ]
                ],
                'sidebar' => getSidebar(),
            ]
        ],
    ];
} // getFirstTab


/**
 * Converts a filename to a form compatible with meta-file resp. blueprint.
 * Note: conversion is not reversible, original file needs to be found by searching dir.
 * @param string $filename
 * @return string
 */
function filenameToVarname(string $filename): string
{
    $str = preg_replace('/[_\W]/', '_', basename($filename, '.md'));
    return $str . '_md';
} // filenameToVarname
