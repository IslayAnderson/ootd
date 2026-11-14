<?php
title_update("notifications");
$notifications = Notification::latest($_SESSION['user_id']);
?>
<section class="header">
    <p class="kicker">Notifications</p>
    <h1>What&rsquo;s <em>new</em></h1>
</section>
<section class="notifications">
    <?php if (count($notifications) == 0): ?>
        <p class="empty">Nothing yet. When someone follows you, you&rsquo;ll see it here.</p>
    <?php else: ?>
        <ol>
            <?php foreach ($notifications as $notification): ?>
                <li class="<?= $notification->read_at === null ? "unread" : "" ?>">
                    <?php if (!empty($notification->actor)): ?>
                        <a href="/user/<?= escape($notification->actor) ?>"><?= escape($notification->actor) ?></a>
                    <?php elseif (!empty($notification->object)): ?>
                        <?php //someone on another server, by handle ?>
                        <?php if (($handle = RemoteActor::parse_handle($notification->object))): ?>
                            <a href="/@<?= escape(rawurlencode($handle[0]) . "@" . rawurlencode($handle[1])) ?>"><?= escape($notification->object) ?></a>
                        <?php else: ?>
                            <strong><?= escape($notification->object) ?></strong>
                        <?php endif; ?>
                    <?php else: ?>
                        Someone
                    <?php endif; ?>
                    <?= escape(Notification::TYPES[$notification->type] ?? $notification->type) ?>
                    <?php if (in_array($notification->type, array("like", "announce"), true) && !empty($notification->link)): ?>
                        &middot; <a href="<?= escape($notification->link) ?>">see it</a>
                    <?php endif; ?>
                    <?php if ($notification->type == "follow_request"): ?>
                        &middot; <a href="/account#followers">answer</a>
                    <?php endif; ?>
                    <time datetime="<?= escape($notification->timestamp) ?>"><?= escape(date_display($notification->timestamp)) ?></time>
                </li>
            <?php endforeach; ?>
        </ol>
        <form action="/API/notification/read.php" method="post">
            <?php CSRF_input(); ?>
            <button type="submit" class="link-button">Mark all as read</button>
        </form>
    <?php endif; ?>
</section>
