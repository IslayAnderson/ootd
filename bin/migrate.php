<?php
/**
 * Upgrade the database from the command line, for servers where nobody can log in to /install.php
 * (staging, containers, cron after a deploy). Same steps as the "Upgrade database" button.
 *
 * usage: php bin/migrate.php [--app-url=https://your.site]
 * --app-url is only needed the first time on installs from before 0.7.0, which have no APP_URL in .env
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

$root = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $root;
chdir($root);

foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, "--app-url=") === 0) {
        $url = substr($arg, strlen("--app-url="));
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            fwrite(STDERR, "--app-url must be a full address, e.g. https://ootd.example\n");
            exit(1);
        }
        $GLOBALS['cli_app_url'] = rtrim($url, "/");
    }
}

//the installer prints <br> separated progress for the browser
ob_start(function ($buffer) {
    return str_replace("<br>", "\n", $buffer);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        fwrite(STDERR, "\nUpgrade failed: " . $error['message'] . "\n");
        exit(1);
    }
    echo "\n";
});

try {
    require $root . "/install_actions.php";
} catch (Throwable $e) {
    ob_end_flush();
    fwrite(STDERR, "\nUpgrade failed: " . $e->getMessage() . "\n");
    exit(1);
}
