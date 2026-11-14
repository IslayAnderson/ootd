<?php
title_update("login");
?>
<div class="login">
    <div class="description">
        <?= site_logo() ?>
        <h1>Login</h1>
        <p>I don't know what else to say, it's the login page</p>
        <?php if (!empty($_GET['created'])): ?>
            <p class="notice">Your account is ready, log in below.</p>
        <?php elseif (!empty($_GET['reset'])): ?>
            <p class="notice">Password changed. Log in with the new one.</p>
        <?php endif; ?>
        <?php if (registration_mode() == "open"): ?>
            <p><a href="/signup">Create an account</a></p>
        <?php elseif (registration_mode() == "invite"): ?>
            <p><a href="/signup">Request an invite</a></p>
        <?php endif; ?>
    </div>
    <form action="/API/user/auth.php" method="post">
        <label for="email">Email</label>
        <input type="email" placeholder="Enter Email" name="email" required>

        <label for="password">Password</label>
        <input type="password" placeholder="Enter Password" name="password" required>

        <button type="submit">Login</button>
        <p><a href="/forgot">Forgot password?</a></p>
    </form>
</div>