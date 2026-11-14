<?php
title_update("request invite");
?>
<div class="invite">
    <h1>Request Invite</h1>
    <p>Discover daily outfit inspiration, trends and personal insights with OOTD. Request an invitation to explore curated looks and fresh style ideas, updated regularly to keep you inspired.</p>
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
</div>