<?php
// Router für den lokalen PHP-Server. Bildet die .htaccess-Sperre von app/
// nach, die php -S nicht auswertet. Auf dem Server wird diese Datei nicht
// verwendet (liegt außerhalb von web/).

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/app(/|$)#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
return false;
