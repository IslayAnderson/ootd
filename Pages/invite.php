<?php
title_update("signup");
$invite = (new Invite())->find_by_code($_GET['code'] ?? "");
?>
<div class="signup">
    <?php if (!$invite): ?>
        <div class="description">
            <h1>Invite not found</h1>
            <p><?= escape($_GET['error'] ?? "This invite link isn't valid or has already been used.") ?></p>
            <p><a href="/signup">Request an invite</a></p>
        </div>
    <?php else: ?>
    <form action="/API/user/create.php" method="post">
        <input type="hidden" name="code" value="<?= escape($invite->code) ?>">
        <div class="description">
            <h1>You’re In!</h1>
            <p>Complete your signup to get access to <?= escape(instance_name()) ?>, get ready for daily style inspiration and fresh outfit ideas. We’re excited to have you on board!</p>
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
        <input type="email" name="email" value="<?= escape($invite->email) ?>" readonly>

        <label for="username">Username</label>
        <input type="text" placeholder="Enter Username" name="username" required>

        <label for="password">Password</label>
        <input type="password" placeholder="At least 8 characters" name="password" minlength="8" required>

        <button type="submit">Complete signup</button>

    </form>
    <?php endif; ?>
</div>