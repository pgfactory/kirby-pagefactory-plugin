<?php

// to simply first steps, debug is automatically enabled on localhost:
return [
    'debug' => isLocalhost(),
];

function isLocalhost()
{
    $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
    return (($remoteAddress == 'localhost') || (strpos($remoteAddress, '192.') === 0) || ($remoteAddress == '::1'));
}
