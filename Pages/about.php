<?php
title_update("about");
description_update(setting_get("instance_description", ""));
$rules = trim((string)setting_get("instance_rules", ""));
$contact = trim((string)setting_get("admin_contact", ""));
?>
<section class="header">
    <p class="kicker">About</p>
    <h1><?= escape(instance_name()) ?></h1>
    <p><?= nl2br(escape(setting_get("instance_description", ""))) ?></p>
</section>
<section class="about">
    <?php if ($rules !== ""): ?>
        <h2>Rules</h2>
        <ol class="rules">
            <?php foreach (preg_split('/\R+/', $rules) as $rule): ?>
                <li><?= escape($rule) ?></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <h2>Joining</h2>
    <?php if (registration_mode() == "open"): ?>
        <p>Anyone can <a href="/signup">create an account</a>.</p>
    <?php elseif (registration_mode() == "invite"): ?>
        <p>Joining is by invitation. <a href="/signup">Request an invite</a>.</p>
    <?php else: ?>
        <p>This server isn&rsquo;t taking new members.</p>
    <?php endif; ?>
    <?php if ($contact !== ""): ?>
        <h2>Contact</h2>
        <p><?= filter_var($contact, FILTER_VALIDATE_EMAIL) ? '<a href="mailto:' . escape($contact) . '">' . escape($contact) . '</a>' : escape($contact) ?></p>
    <?php endif; ?>
    <p class="small">Runs OOTD <?= escape(OOTD_VERSION) ?>, free software under the GNU AGPL<?php if (setting_get("source_url", "")): ?>
        &middot; <a href="<?= escape(setting_get("source_url", "")) ?>">source code</a><?php endif; ?>.</p>
</section>
