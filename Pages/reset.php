<?php
title_update("reset password");
$token = (string)($_GET['token'] ?? "");
$valid = PasswordReset::user_for($token) !== null;
?>
<div class="login">
    <div class="description">
        <h1>New password</h1>
        <?php if (!$valid): ?>
            <p>This link has expired or has already been used. <a href="/forgot">Ask for a new one</a>.</p>
        <?php elseif (!empty($_GET['error'])): ?>
            <p class="notice"><?= escape($_GET['error']) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($valid): ?>
        <form action="/API/user/reset.php" method="post">
            <input type="hidden" name="token" value="<?= escape($token) ?>">
            <label for="password">New password</label>
            <input type="password" placeholder="At least 8 characters" name="password" id="password" minlength="8" required>
            <button type="submit">Save password</button>
        </form>
    <?php endif; ?>
</div>
