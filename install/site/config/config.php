<?php


return [
    'debug'  => function() {
        $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
        return (($remoteAddress == 'localhost') || (strpos($remoteAddress, '192.') === 0) || ($remoteAddress == '::1'));
    },
    'markdown' => [
        'extra' => true
    ],
];
