<?php
/**
 * /cron: runs due jobs when called with CRON_KEY, for installs that can't have a cron job of their
 * own. Cloud blogs (0.14.0) are woken this way every minute by the blog that created them.
 * Answers straight away and does the work after the reply where PHP-FPM allows it.
 */
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
require $_SERVER['DOCUMENT_ROOT'] . "/Functions/functions.php";
require $_SERVER['DOCUMENT_ROOT'] . "/Classes/classes.php";
require $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";
Dotenv\Dotenv::createMutable($_SERVER['DOCUMENT_ROOT'])->load();
error_reporting(E_ALL);
ini_set('display_errors', 'off');

$key = (string)($_ENV['CRON_KEY'] ?? "");
header("Content-Type: application/json");
header("Cache-Control: no-store");
if ($key === "" || ($_SERVER['REQUEST_METHOD'] ?? "GET") !== "POST" || !hash_equals($key, (string)($_POST['key'] ?? ""))) {
    http_response_code(404);
    echo json_encode(array("error" => "not found"));
    exit();
}
echo json_encode(array("ok" => true));
if (function_exists("fastcgi_finish_request")) {
    fastcgi_finish_request();
}
ignore_user_abort(true);
@set_time_limit(55);
Jobs::schedule_daily();
Jobs::run(50);
