<?php
title_update("login");
?>
<div class="login">
    <div class="description">
        <img src="/Assets/images/logo.svg">
        <h1>Login</h1>
        <p>I don't know what else to say, it's the login page</p>
        <?php if (!empty($_GET['created'])): ?>
            <p class="notice">Your account is ready, log in below.</p>
        <?php endif; ?>
        <p><a href="/signup">Request an invite</a></p>
    </div>
    <form action="/API/user/auth.php" method="post">
        <label for="email">Email</label>
        <input type="email" placeholder="Enter Email" name="email" required>

        <label for="password">Password</label>
        <input type="password" placeholder="Enter Password" name="password" required>

        <button type="submit">Login</button>
    </form>
</div>