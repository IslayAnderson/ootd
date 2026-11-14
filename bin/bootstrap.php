<?php
/**
 * Loads the app for command-line scripts (no session, no page)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
$root = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $root;
chdir($root);

//the class loader evals `use` lines that PHP warns about; keep CLI output readable
error_reporting(E_ALL & ~E_WARNING);
require $root . "/Functions/functions.php";
require $root . "/Classes/classes.php";
require $root . "/vendor/autoload.php";
error_reporting(E_ALL);

Dotenv\Dotenv::createMutable($root)->load();
