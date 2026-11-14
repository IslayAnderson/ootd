<?php
/**
 * Long-running job worker (for Docker or a systemd service): runs due jobs, sleeps, repeats.
 * usage: php bin/worker.php [--once] [--sleep=5] [--max-runtime=3600]
 * Exits after --max-runtime seconds so a supervisor can restart it with fresh code after a deploy.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . "/bootstrap.php";

$options = getopt("", array("once", "sleep::", "max-runtime::"));
$sleep = max(1, (int)($options['sleep'] ?? 5));
$max_runtime = max(60, (int)($options['max-runtime'] ?? 3600));
$started = time();
$last_daily = 0;

do {
    if (time() - $last_daily > 3600) {
        Jobs::schedule_daily();
        $last_daily = time();
    }
    list($ran, $failed) = Jobs::run(50);
    if ($ran + $failed > 0) {
        echo date("c") . " ran " . $ran . " job(s), " . $failed . " failed\n";
    }
    if (isset($options['once'])) {
        break;
    }
    if ($ran + $failed == 0) {
        sleep($sleep);
    }
} while (time() - $started < $max_runtime);
