<?php

const PFY_MIME_TYPES = [
    // images
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    // misc
    'txt' => 'text/plain',
    'html' => 'text/html',
    'csv' => 'text/csv',
    'zip' => 'application/zip',
    //'json' => 'application/json',
    // docs
    'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'odt' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ott' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    // spreadsheets
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ods' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ots' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    // presentations
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'odp' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'otp' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    // audio
    'mp4' => 'video/mp4',
];

function respondWithDownload($file, $accessCritearia = 'loggedin|localhost')
{
    $file = urldecode($file);
    if (!is_file($file)) {
        http_response_code(404);
        exit('File not found');
    }
    if (!\PgFactory\MarkdownPlus\Permission::evaluate($accessCritearia)) {
        http_response_code(403);
        exit('Insuffucient permissions to access this file.');
    }

    $path_parts = pathinfo($file);
    $ext = strtolower($path_parts['extension']);
    $mimeType = PFY_MIME_TYPES[$ext] ?? false;
    if (!$mimeType) {
        http_response_code(404);
        exit('File format error');
    }

    if ($ext === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('Accept-Ranges: bytes');

    } elseif ($ext === 'txt') {
        header('Content-Type: text/plain');
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('Accept-Ranges: bytes');

    } else {

        header('Content-Description: File Transfer');
        header("Content-Type: $mimeType");
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
    }

    readfile($file);
    exit;
} // respondWithDownload