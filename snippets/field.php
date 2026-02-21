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

// check whether to render the snippet:
$enableCodeBlock = kirby()->option('pgfactory.pagefactory.enableCodeBlock');
if (!$enableCodeBlock || ($enableCodeBlock[0] !== ($place??' ')[0])) {
    return '';
}
// prepare $args for macro execution:
if (!isset($args)) {
    $args = '';
} elseif (is_array($args)) {
    $args = var_export($args, true);
}

// execute macro:
$res = Macros::execute($macroName, $args);

// unshield output:
$res = unshieldStrAll($res, immutable: true);

// wrap output in <section> tag unless empty:
if ($res) {
    $res = <<<EOT

<section class='pfy-section-wrapper pfy-codeblock'>
$res
</section>

EOT;
}

Cache::updatePageCache($res, $cachePrefix);

echo $res;
