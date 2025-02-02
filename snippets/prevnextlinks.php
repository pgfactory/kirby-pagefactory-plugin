<?php

namespace PgFactory\PageFactory;

$macroName = basename(__FILE__, '.php');
$cachePrefix = $macroName . md5($args??'');

$res = Cache::checkPageCache($cachePrefix);
if ($res !== false) {
    echo $res;
    return;
}
Macros::instantiateMacroLoader($macroName, dirname(__DIR__) . "/macros/$macroName.php");

if (!isset($args)) {
    $args = '';
} elseif (is_array($args)) {
    $args = var_export($args, true);
}
$res = Macros::execute($macroName, $args);

Cache::updatePageCache($res, $cachePrefix);

echo $res;
