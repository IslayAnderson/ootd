<?php
session_save_path($_SERVER['DOCUMENT_ROOT'] . '/tmp');
ini_set('session.gc_max_lifetime', 1440);
session_start();

require $_SERVER['DOCUMENT_ROOT'] . "/Functions/functions.php";
require $_SERVER['DOCUMENT_ROOT'] . "/Classes/classes.php";
require $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";

use Dotenv\Dotenv;

$dotenv = Dotenv::createMutable(__DIR__);
$dotenv->load();

CSRF_generate();

use ScssPhp\ScssPhp\Compiler;

$compiler = new Compiler();
$compressor = new tubalmartin\CssMin\Minifier();

$source_scss = $_SERVER['DOCUMENT_ROOT'] . '/Assets/stylesheets/sass/main.scss';
$scssContents = file_get_contents($source_scss);
$import_path = $_SERVER['DOCUMENT_ROOT'] . '/Assets/stylesheets/sass';
$compiler->addImportPath($import_path);
$target_css = $_SERVER['DOCUMENT_ROOT'] . '/Assets/stylesheets/css/main.css';

$css = $compiler->compile($scssContents);

if (!empty($css) && is_string($css)) {
    file_put_contents($target_css, $css);
}

$minified_css = $compressor->run(file_get_contents($target_css));
if (!empty($minified_css) && is_string($minified_css)) {
    file_put_contents($target_css, $minified_css);
}

$GLOBALS['sass_len'] = strlen($scssContents);
$GLOBALS['sass_gen'] = "true";

if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_URI'] != "/login" && $_SERVER['REQUEST_URI'] != '/signup') {
        header("location: /login");
    }
} else {
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = serialize(new User($_SESSION['user_id']));
    }
}