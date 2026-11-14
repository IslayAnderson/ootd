<?php

/**
 * Background jobs, stored in the `jobs` table and run by bin/worker.php (long-running) or
 * bin/cron.php (once a minute from cron). A job of type "x" is handled by the function job_x($payload)
 * in Functions/jobs.php; throwing an exception schedules a retry.
 */
class Jobs
{
    const MAX_ATTEMPTS = 8;
    /** a job locked for longer than this is assumed to belong to a crashed worker */
    const STALE_SECONDS = 600;

    /**
     * @param string $type
     * @param array $payload
     * @param int $delay seconds before it may run
     * @param bool $unique skip if a job of this type with the same payload is already waiting
     * @return void
     */
    public static function dispatch($type, $payload = array(), $delay = 0, $unique = false): void
    {
        $json = json_encode($payload);
        $db = new Mysql();
        if ($unique) {
            $sql = "SELECT `job_num` FROM `jobs` WHERE `type` = :a AND `payload` = :b AND `done_at` IS NULL AND `failed_at` IS NULL";
            $row = $db->Fetch($sql, array(":a" => $type, ":b" => $json));
            if ($row[0] != '00000') {
                return;
            }
        }
        $sql = "INSERT INTO `jobs` (`type`, `payload`, `run_at`) VALUES (:a, :b, DATE_ADD(NOW(), INTERVAL " . max(0, (int)$delay) . " SECOND))";
        $db->Fetch($sql, array(":a" => $type, ":b" => $json));
    }

    /**
     * Run jobs that are due
     *
     * @param int $limit
     * @return array [ran, failed]
     */
    public static function run($limit = 20): array
    {
        $db = new Mysql();
        $sql = "SELECT `job_num` FROM `jobs`
                WHERE `done_at` IS NULL AND `failed_at` IS NULL AND `run_at` <= NOW()
                  AND (`locked_at` IS NULL OR `locked_at` < DATE_SUB(NOW(), INTERVAL " . self::STALE_SECONDS . " SECOND))
                ORDER BY `run_at` ASC LIMIT " . (int)$limit;
        $due = $db->Fetch($sql, null);
        $ran = $failed = 0;
        if ($due[0] == '00000') {
            return array(0, 0);
        }

        foreach ($due as $candidate) {
            //claim it; if another worker got there first the update matches nothing
            $token = bin2hex(random_bytes(16));
            $db->Fetch("UPDATE `jobs` SET `locked_at` = NOW(), `lock_token` = :t
                        WHERE `job_num` = :a AND `done_at` IS NULL AND `failed_at` IS NULL
                          AND (`locked_at` IS NULL OR `locked_at` < DATE_SUB(NOW(), INTERVAL " . self::STALE_SECONDS . " SECOND))",
                array(":a" => $candidate->job_num, ":t" => $token));
            $job = $db->Fetch("SELECT * FROM `jobs` WHERE `job_num` = :a AND `lock_token` = :t", array(":a" => $candidate->job_num, ":t" => $token));
            if ($job[0] == '00000') {
                continue;
            }
            $job = $job[0];

            try {
                $handler = "job_" . $job->type;
                if (!preg_match('/^job_[a-z0-9_]+$/', $handler) || !function_exists($handler)) {
                    throw new Exception('No handler for job type ' . $job->type);
                }
                $handler(json_decode((string)$job->payload, true) ?: array());
                $db->Fetch("UPDATE `jobs` SET `done_at` = NOW(), `locked_at` = NULL, `attempts` = `attempts` + 1 WHERE `job_num` = :a", array(":a" => $job->job_num));
                $ran++;
            } catch (Throwable $e) {
                $attempts = (int)$job->attempts + 1;
                //1, 2, 4 ... minutes, capped at a day
                $backoff = min(86400, 60 * (2 ** ($attempts - 1)));
                $sql = "UPDATE `jobs` SET `attempts` = :n, `last_error` = :e, `locked_at` = NULL,
                        `run_at` = DATE_ADD(NOW(), INTERVAL " . $backoff . " SECOND)"
                    . ($attempts >= self::MAX_ATTEMPTS || $e instanceof PermanentFailure ? ", `failed_at` = NOW()" : "") . " WHERE `job_num` = :a";
                $db->Fetch($sql, array(":a" => $job->job_num, ":n" => $attempts, ":e" => mb_substr($e->getMessage(), 0, 1024)));
                $failed++;
            }
        }
        return array($ran, $failed);
    }

    /**
     * @return array counts for /admin: waiting, failed, done in the last day
     */
    public static function counts(): array
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT
            SUM(`done_at` IS NULL AND `failed_at` IS NULL) AS waiting,
            SUM(`failed_at` IS NOT NULL) AS failed,
            SUM(`done_at` > DATE_SUB(NOW(), INTERVAL 1 DAY)) AS done_today
            FROM `jobs`", null);
        return array(
            "waiting" => (int)($row[0]->waiting ?? 0),
            "failed" => (int)($row[0]->failed ?? 0),
            "done_today" => (int)($row[0]->done_today ?? 0)
        );
    }

    /**
     * @param int $limit
     * @return array most recent failed jobs
     */
    public static function failures($limit = 10): array
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `job_num`, `type`, `attempts`, `last_error`, `failed_at` FROM `jobs`
                           WHERE `failed_at` IS NOT NULL ORDER BY `failed_at` DESC LIMIT " . (int)$limit, null);
        return $row[0] != '00000' ? $row : array();
    }

    /**
     * Put failed jobs back in the queue
     */
    public static function retry_failed(): void
    {
        $db = new Mysql();
        $db->Fetch("UPDATE `jobs` SET `failed_at` = NULL, `attempts` = 0, `run_at` = NOW(), `locked_at` = NULL WHERE `failed_at` IS NOT NULL", null);
    }

    /**
     * Daily housekeeping, queued by bin/cron.php and bin/worker.php
     */
    public static function schedule_daily(): void
    {
        $db = new Mysql();
        $row = $db->Fetch("SELECT `job_num` FROM `jobs` WHERE `type` = 'media_purge' AND `timestamp` > DATE_SUB(NOW(), INTERVAL 1 DAY)", null);
        if ($row[0] == '00000') {
            self::dispatch("media_purge", array(), 0, true);
        }
        //finished jobs are kept for a week for /admin, then dropped
        $db->Fetch("DELETE FROM `jobs` WHERE `done_at` < DATE_SUB(NOW(), INTERVAL 7 DAY)", null);
    }
}
