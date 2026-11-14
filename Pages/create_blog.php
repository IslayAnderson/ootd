<?php
// /create-blog: anyone can ask for a TREAT blog of their own (cloud blogs, 0.14.0)
// /create-blog/confirm?t=...: the link from the email starts the build
title_update("create your own blog");
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$domain = CloudBlog::domain();
?>
<div class="login create-blog">
    <?php if (!CloudBlog::enabled()): ?>
        <div class="description">
            <h1>Create your own <em>blog</em></h1>
            <p class="notice">Creating blogs isn&rsquo;t open here.</p>
            <p><a href="/login">Back to login</a></p>
        </div>
    <?php elseif ($path == "/create-blog/confirm"): ?>
        <?php $blog = CloudBlog::confirm($_GET['t'] ?? ""); ?>
        <div class="description">
            <h1>Your new <em>blog</em></h1>
            <?php if (!$blog): ?>
                <p class="notice">That link has expired or was already used. Ask again from the <a href="/create-blog">create a blog</a> page.</p>
            <?php elseif ($blog->state == "unconfirmed"): ?>
                <p class="notice">We&rsquo;ve made as many new blogs as we can today. Open this link again tomorrow to start yours.</p>
            <?php else: ?>
                <p>We&rsquo;re setting up <strong><?= escape($blog->domain) ?></strong>. It takes a few minutes; we&rsquo;ll email
                    <?= escape($blog->email) ?> a link to choose your password as soon as it&rsquo;s ready.</p>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($_GET['sent'])): ?>
        <div class="description">
            <h1>Check your <em>email</em></h1>
            <p>We&rsquo;ve sent a link to confirm your new blog. Nothing is made until you open it; it works for a day.</p>
        </div>
    <?php else: ?>
        <div class="description">
            <h1>Create your own <em>blog</em></h1>
            <p>Your own TREAT blog: a wardrobe, daily outfits and articles, at your own address. You&rsquo;re its admin,
                and people on other blogs and on Mastodon can follow you.</p>
            <?php if (!empty($_GET['error'])): ?>
                <p class="notice"><?= escape($_GET['error']) ?></p>
            <?php endif; ?>
        </div>
        <form action="/API/cloud/create.php" method="post">
            <label for="subdomain">Address</label>
            <div class="address-field">
                <input type="text" name="subdomain" id="subdomain" required pattern="[a-z0-9][a-z0-9\-]{1,28}[a-z0-9]" maxlength="30"
                       placeholder="yourname" autocomplete="off" autocapitalize="none" spellcheck="false"
                       value="<?= escape($_GET['subdomain'] ?? "") ?>">
                <span>.<?= escape($domain) ?></span>
            </div>
            <label for="blog_name">Blog name</label>
            <input type="text" name="blog_name" id="blog_name" required maxlength="60" placeholder="e.g. Mia's Closet" value="<?= escape($_GET['blog_name'] ?? "") ?>">
            <label for="username">Your username</label>
            <input type="text" name="username" id="username" required pattern="[A-Za-z0-9_.\-]{3,32}" maxlength="32" autocomplete="username" value="<?= escape($_GET['username'] ?? "") ?>">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required maxlength="128" autocomplete="email" value="<?= escape($_GET['email'] ?? "") ?>">
            <?php //left empty by people, filled in by form-filling bots ?>
            <label class="visually-hidden" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <label class="checkbox">
                <input type="checkbox" name="agree" required>
                <span>I&rsquo;ve read the <a href="/privacy" target="_blank">privacy page</a> and <a href="/about" target="_blank">rules</a></span>
            </label>
            <button type="submit">Create my blog</button>
            <p><a href="/login">Back to login</a></p>
        </form>
    <?php endif; ?>
</div>
