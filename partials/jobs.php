<?php
// background jobs summary for /admin
$job_counts = Jobs::counts();
$job_failures = Jobs::failures();
?>
<p class="jobs-summary">
    <span><strong><?= $job_counts['waiting'] ?></strong> waiting</span>
    <span><strong><?= $job_counts['done_today'] ?></strong> done today</span>
    <span><strong><?= $job_counts['failed'] ?></strong> failed</span>
</p>
<?php if ($job_counts['waiting'] > 0 && $job_counts['done_today'] == 0): ?>
    <p class="notice">Jobs are waiting but none have run today. Is <code>bin/worker.php</code> or the <code>bin/cron.php</code> cron job running?</p>
<?php endif; ?>
<?php if (count($job_failures) > 0): ?>
    <table>
        <thead>
        <tr>
            <th>Job</th>
            <th>Tries</th>
            <th>Last error</th>
            <th>Failed</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($job_failures as $failure): ?>
            <tr>
                <td><?= escape($failure->type) ?></td>
                <td><?= escape($failure->attempts) ?></td>
                <td><?= escape($failure->last_error) ?></td>
                <td><?= escape(date_display($failure->failed_at)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form action="/API/admin/jobs.php" method="post">
        <?php CSRF_input(); ?>
        <button type="submit">Retry failed jobs</button>
    </form>
<?php endif; ?>
