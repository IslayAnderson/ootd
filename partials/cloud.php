<?php
//cloud blogs made from here (0.14.0): state, and retry / suspend / resume / delete
$cloud_blogs = CloudBlog::all();
$cloud_notice = array("retry" => "Building it again.", "suspend" => "Suspended: the blog no longer answers.", "resume" => "Resumed.",
    "delete" => "Deleted: the subscription, its customer and its address are gone.");
?>
<?php if (isset($cloud_notice[$_GET['cloud'] ?? ""])): ?>
    <p class="notice"><?= escape($cloud_notice[$_GET['cloud']]) ?></p>
<?php endif; ?>
<?php if (!CloudBlog::enabled()): ?>
    <p class="notice">Creating blogs is off: .env doesn&rsquo;t have every CLOUD_, PLESK_ and CLOUDFLARE_ setting (SELF-HOSTING, &ldquo;Cloud blogs&rdquo;).</p>
<?php else: ?>
    <p>Anyone can create a blog at <em>name</em>.<?= escape(CloudBlog::domain()) ?> from the login page, up to <?= CloudBlog::daily_limit() ?> a day.</p>
<?php endif; ?>
<?php if (count($cloud_blogs) == 0): ?>
    <p class="empty">No blogs made from here yet.</p>
<?php else: ?>
    <table class="cloud-blogs">
        <tbody>
        <tr>
            <th>address</th>
            <th>name</th>
            <th>owner</th>
            <th>state</th>
            <th>since</th>
            <th>&nbsp;</th>
        </tr>
        <?php foreach ($cloud_blogs as $cloud): ?>
            <tr>
                <td><?php if ($cloud->state == "ready"): ?><a href="https://<?= escape($cloud->domain) ?>" target="_blank" rel="noopener"><?= escape($cloud->domain) ?></a><?php else: ?><?= escape($cloud->domain) ?><?php endif; ?></td>
                <td><?= escape($cloud->blog_name) ?></td>
                <td><?= escape($cloud->username) ?><br><small><?= escape($cloud->email) ?></small></td>
                <td>
                    <?= escape($cloud->state) ?><?= $cloud->step ? " (" . escape($cloud->step) . ")" : "" ?>
                    <?php if (!empty($cloud->last_error) && $cloud->state != "ready"): ?><br><small><?= escape($cloud->last_error) ?></small><?php endif; ?>
                </td>
                <td><?= escape(date_display($cloud->ready_at ?: $cloud->timestamp)) ?></td>
                <td>
                    <?php if ($cloud->state != "unconfirmed"): ?>
                        <form action="/API/admin/cloud.php" method="post" class="inline">
                            <?php CSRF_input(); ?>
                            <input type="hidden" name="cloud_id" value="<?= escape($cloud->cloud_id) ?>">
                            <?php if ($cloud->state == "failed"): ?>
                                <button type="submit" name="action" value="retry">Retry</button>
                            <?php elseif ($cloud->state == "ready"): ?>
                                <button type="submit" name="action" value="suspend" class="secondary">Suspend</button>
                            <?php elseif ($cloud->state == "suspended"): ?>
                                <button type="submit" name="action" value="resume">Resume</button>
                            <?php endif; ?>
                        </form>
                        <?php if (in_array($cloud->state, array("ready", "suspended", "failed"), true)): ?>
                            <details class="danger">
                                <summary>Delete&hellip;</summary>
                                <form action="/API/admin/cloud.php" method="post">
                                    <?php CSRF_input(); ?>
                                    <input type="hidden" name="cloud_id" value="<?= escape($cloud->cloud_id) ?>">
                                    <label>Type <strong><?= escape($cloud->domain) ?></strong> to confirm
                                        <input type="text" name="confirm" autocomplete="off" required></label>
                                    <p>Removes the Plesk subscription with all its files and database, its customer and its address. This can&rsquo;t be undone.</p>
                                    <button type="submit" name="action" value="delete">Delete blog</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
