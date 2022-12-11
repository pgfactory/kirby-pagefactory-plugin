<?php

// start output buffering:
ob_start();

// let PageFactory render the page:
$html = $page->pageFactoryRender($pages);

// PageFactory does not create any output, so if there is any, it's some error or warning message.
// -> We store that in a log file.
if (ob_get_length()) {
    if (!file_exists(PFY_LOGS_PATH)) {
        mkdir(PFY_LOGS_PATH);
    }
    file_put_contents('site/log/prematureOutput.txt', ob_get_contents());
    ob_clean();
}

// if HTML contains <?php tags, we need to get that evaluated:
if ((strpos($html, '<?php') !== false) || (strpos($html, '<?=') !== false)) {
    eval(" ?>$html<?php ");
} else {
    echo $html;
}

