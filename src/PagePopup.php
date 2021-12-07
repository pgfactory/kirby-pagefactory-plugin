<?php

namespace Usility\PageFactory;


class PagePopup extends PageElements
{
    public function render($str, $mdCompile)
    {
        if ($mdCompile) {
            $str = compileMarkdown($str);
        }
//        $str = <<<EOT
//    <div id='lzy-overlay' class='lzy-overlay'><button class='lzy-close-overlay'>✕</button>
//$str
//    </div>
//EOT;
//    $this->pfy->pg->addAssets('site/plugins/pagefactory/js/overlay.scss, site/plugins/pagefactory/js/overlay.js');
//    return $str;
        return '"Popup" not implemented yet';
    } // render
}