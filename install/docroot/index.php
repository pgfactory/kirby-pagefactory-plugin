<?php
//
// index.php -> version hiding parent folder in URL
//  -> place in docroot, just above onair folder
//

define('PFY_DOCROOT',        __DIR__ . '/');
define('PFY_BASE_OFFSET',    'onair/');


$base = PFY_DOCROOT . PFY_BASE_OFFSET;
if (!file_exists($base)) {
	exit();
}
require $base . 'kirby/bootstrap.php';
$rootUrl = \Kirby\Http\Url::index();

$kirby = new Kirby([
    'roots' => [
        'index'    => __DIR__,
        'base'     => $base,
        'assets'   => $base . 'assets',
        'content'  => $base . 'content',
        'media'    => $base . 'media',
        'site'     => $base . 'site',
    ],
    'urls' => [
        'index'    => $rootUrl,
        'media'    => $rootUrl. '/'.PFY_BASE_OFFSET .'media',
        'assets'   => $rootUrl. '/'.PFY_BASE_OFFSET .'assets',
    ],

]);

echo $kirby->render();
