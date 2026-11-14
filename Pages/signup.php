<?php
title_update(registration_mode() == "open" ? "sign up" : "request invite");
$mode = registration_mode();
?>
<div class="<?= $mode == "open" ? "signup" : "invite" ?>">
    <?php if ($mode == "closed"): ?>
        <h1>Closed</h1>
        <p><?= escape(instance_name()) ?> isn&rsquo;t taking new members right now. If you&rsquo;ve been sent an invite link, use that.</p>
    <?php elseif ($mode == "open"): ?>
        <form action="/API/user/create.php" method="post">
            <div class="description">
                <h1>Join <?= escape(instance_name()) ?></h1>
                <p><?= escape(setting_get("instance_description", "")) ?></p>
            </div>
            <div class="name">
                <label for="first_name">First Name <br>
                    <input type="text" placeholder="Enter First name" name="first_name" required>
                </label>
                <label for="last_name">Last Name <br>
                    <input type="text" placeholder="Enter Last name" name="last_name" required>
                </label>
            </div>
            <?php if (!empty($_GET['error'])): ?>
                <p class="notice"><?= escape($_GET['error']) ?></p>
            <?php endif; ?>
            <label for="email">Email</label>
            <input type="email" placeholder="Enter email" name="email" required>
            <label for="username">Username</label>
            <input type="text" placeholder="Enter Username" name="username" required>
            <label for="password">Password</label>
            <input type="password" placeholder="At least 8 characters" name="password" minlength="8" required>
            <?php if (trim((string)setting_get("instance_rules", "")) !== ""): ?>
                <p>By joining you agree to the <a href="/about">rules</a>.</p>
            <?php endif; ?>
            <button type="submit">Create account</button>
        </form>
    <?php else: ?>
        <h1>Request Invite</h1>
        <p>Discover daily outfit inspiration, trends and personal insights with <?= escape(instance_name()) ?>. Request an invitation to explore curated looks and fresh style ideas, updated regularly to keep you inspired.</p>
        <?php if (!empty($_GET['requested'])): ?>
            <p class="notice">Thanks, we'll be in touch if your request is approved.</p>
        <?php elseif (!empty($_GET['error'])): ?>
            <p class="notice">That email doesn't look right, try again.</p>
        <?php endif; ?>
        <form action="/API/user/invite.php" method="post">
            <label for="email">Email</label>
            <input type="email" placeholder="Enter email" name="email" required>
            <button type="submit">Request invite</button>
        </form>
    <?php endif; ?>
</div>
