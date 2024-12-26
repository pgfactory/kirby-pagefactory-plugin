<?php

namespace PgFactory\PageFactory;

$macroName = basename(__FILE__, '.php');
Macros::instantiateMacroLoader($macroName, dirname(__DIR__) . "/macros/_$macroName.php");

if (!isset($args)) {
    $args = '';
} elseif (is_array($args)) {
    $args = var_export($args, true);
}
$res = Macros::execute($macroName, $args);

echo $res;
