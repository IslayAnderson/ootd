<?php
/**
 * Run from cron every minute on hosts that can't keep bin/worker.php running:
 * * * * * * php /path/to/ootd/bin/cron.php
 * Queues daily housekeeping and runs whatever jobs are due, then exits.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . "/bootstrap.php";

Jobs::schedule_daily();
list($ran, $failed) = Jobs::run(100);
if ($ran + $failed > 0) {
    echo date("c") . " ran " . $ran . " job(s), " . $failed . " failed\n";
}
