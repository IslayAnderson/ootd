<?php
title_update("forgot password");
?>
<div class="login">
    <div class="description">
        <h1>Forgot password</h1>
        <?php if (!empty($_GET['sent'])): ?>
            <p class="notice">If that email has an account, a link to reset the password is on its way. It works for an hour.</p>
        <?php else: ?>
            <p>Enter your account&rsquo;s email and we&rsquo;ll send a link to choose a new password.</p>
        <?php endif; ?>
        <?php if (!Mailer::enabled()): ?>
            <p class="notice">This server can&rsquo;t send email yet, so ask its admin<?= setting_get("admin_contact", "") ? " (" . escape(setting_get("admin_contact", "")) . ")" : "" ?> to reset your password.</p>
        <?php endif; ?>
        <p><a href="/login">Back to login</a></p>
    </div>
    <form action="/API/user/forgot.php" method="post">
        <label for="email">Email</label>
        <input type="email" placeholder="Enter Email" name="email" id="email" required>
        <button type="submit">Send link</button>
    </form>
</div>
