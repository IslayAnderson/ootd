<?php
/**
 * Checks used by /health (for uptime monitors and container health checks) and `bin/ootd health`
 *
 * @return array ok => bool, checks => name => [ok, detail]
 */
function health_check(): array
{
    $checks = array();

    try {
        $schema = (int)setting_get("schema_version", 0);
        $checks['database'] = array(
            "ok" => $schema == OOTD_SCHEMA,
            "detail" => $schema == OOTD_SCHEMA ? "schema " . $schema : "schema " . $schema . ", this code needs " . OOTD_SCHEMA . ": run bin/ootd migrate"
        );
    } catch (Throwable $e) {
        $checks['database'] = array("ok" => false, "detail" => "can't connect");
    }

    if (strtolower($_ENV['MEDIA_DRIVER'] ?? "local") == "s3") {
        $checks['storage'] = array("ok" => !empty($_ENV['S3_BUCKET']), "detail" => "s3");
    } else {
        $path = !empty($_ENV['STORAGE_PATH']) ? $_ENV['STORAGE_PATH'] : $_SERVER['DOCUMENT_ROOT'] . "/storage";
        $writable = is_dir($path) && is_writable($path);
        $checks['storage'] = array("ok" => $writable, "detail" => $writable ? "local, writable" : "local, not writable");
    }

    $tmp = is_writable($_SERVER['DOCUMENT_ROOT'] . "/tmp");
    $checks['sessions'] = array("ok" => $tmp, "detail" => $tmp ? "tmp/ writable" : "tmp/ not writable");

    if ($checks['database']['ok']) {
        //a job that should have run an hour ago means no worker or cron is running
        $db = new Mysql();
        $row = $db->Fetch("SELECT COUNT(*) AS late FROM `jobs` WHERE `done_at` IS NULL AND `failed_at` IS NULL AND `run_at` < DATE_SUB(NOW(), INTERVAL 1 HOUR)", null);
        $late = $row[0] != '00000' ? (int)$row[0]->late : 0;
        $counts = Jobs::counts();
        $checks['jobs'] = array(
            "ok" => $late == 0,
            "detail" => $late > 0 ? $late . " overdue: is bin/worker.php or bin/cron.php running?" : $counts['waiting'] . " waiting, " . $counts['failed'] . " failed"
        );
    }

    $ok = true;
    foreach ($checks as $check) {
        $ok = $ok && $check['ok'];
    }
    return array("ok" => $ok, "checks" => $checks);
}
