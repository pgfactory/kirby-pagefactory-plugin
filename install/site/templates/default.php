<?php

ob_start();

$html = $page->pageFactoryRender($pages, [
    'templateFile' =>'page_template.html',
//    'mdVariant' => 'kirby', // or 'extra'
]);

if (ob_get_length()) {
    if (!file_exists(PFY_LOGS_PATH)) {
        mkdir(PFY_LOGS_PATH);
    }
    file_put_contents('site/log/prematureOutput.txt', ob_get_contents());
    ob_clean();
}

// if HTML contains <?php tags, we need to get that evaluated:
if (strpos($html, '<?') !== false) {
    eval(" ?>$html<?php ");
} else {
    echo $html;
}

