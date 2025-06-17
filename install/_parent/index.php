<?php

// index.php of parent folder
// ==========================

define('PFY_DOCROOT',        __DIR__ . '/');
define('PFY_BASE_OFFSET',    'onair/');     // your app's folder

$basePath = PFY_DOCROOT . PFY_BASE_OFFSET;  // folder where kirby&content resides
if (!is_dir($basePath)) {
	exit();
}
require $basePath . 'kirby/bootstrap.php';
$rootUrl = \Kirby\Http\Url::index();        // URL to docroot (resp. folder above kirby)
$baseUrl = $rootUrl. '/'.PFY_BASE_OFFSET;   // URL to relevant folders

$kirby = new Kirby([
    // specify paths to main folders:
    'roots' => [
        'index'    => __DIR__,
        'base'     => __DIR__,
        'assets'   => $basePath . 'assets',
        'content'  => $basePath . 'content',
        'media'    => $basePath . 'media',
        'site'     => $basePath . 'site',
    ],
    // specify relevant URLs:
    'urls' => [
        'media'    => $baseUrl . 'media',
    ],

]);

echo $kirby->render();
