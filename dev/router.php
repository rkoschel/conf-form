<?php
// Router für den lokalen PHP-Server. Bildet nach, was auf dem Server Apache
// erledigt und php -S nicht kann: Sperre von app/ (.htaccess) und 404 für
// unbekannte Pfade (php -S liefert sonst stillschweigend die index.php aus).
// Auf dem Server wird diese Datei nicht verwendet (liegt außerhalb von web/).

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/app(/|$)#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if (is_file($file) || (is_dir($file) && is_file(rtrim($file, '/') . '/index.php'))) {
    return false;
}

http_response_code(404);
echo 'Not Found';
return true;
