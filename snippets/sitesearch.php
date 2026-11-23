<?php

namespace PgFactory\PageFactory;

$options = [
    'fieldType'     => $fieldType ?? 'icon',
    'ignore'        => $ignore ?? ['/<script\b.*?<\/script>/s', '/<style\b.*?<\/style>/s'],
    'resultsUrl'    => $resultsUrl ?? pfyPage()->url(),
//    'injectAssets'  => false,
];
$ss = new SiteSearch($options);
echo $ss->renderSearchField();
