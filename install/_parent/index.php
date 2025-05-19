<?php
/*
// index.php -> version hiding app path in URL
//  -> place in docroot, just above app folder
//
// for debugging:
// if (str_ends_with($_SERVER["REQUEST_URI"], '.js.map') || str_ends_with($_SERVER["REQUEST_URI"], 'com.chrome.devtools.json')) {
//     exit('');
// }
// $ts = date('Y-m-d H:i:s  ');
// file_put_contents('log.txt', $ts.$_SERVER["REQUEST_URI"]."\n", FILE_APPEND);
*/

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
        'base'     => $basePath,
        'assets'   => $basePath . 'assets',
        'content'  => $basePath . 'content',
        'media'    => $basePath . 'media',
        'site'     => $basePath . 'site',
    ],
    // specify relevant URLs:
    'urls' => [
        'index'    => $rootUrl,
        'media'    => $baseUrl . 'media',
        'assets'   => $baseUrl . 'assets',
    ],

]);

echo $kirby->render();
