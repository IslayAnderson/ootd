<?php
title_update("privacy");
?>
<section class="header">
    <p class="kicker">The small print</p>
    <h1>Privacy</h1>
    <p class="byline">What OOTD keeps about you, and why.</p>
</section>
<section class="privacy">
    <h2>What we store</h2>
    <ul>
        <li><strong>Your account</strong>: username, email, first and last name, and your password as a one-way hash.</li>
        <li><strong>What you post</strong>: garments, outfits, blog posts, tags and the photos you upload, including your profile photo.</li>
        <li><strong>What you wear</strong>: each time you press "Wore this today", so we can show your insights.</li>
        <li><strong>Invite requests</strong>: the email address you ask for an invite with.</li>
    </ul>

    <h2>Cookies</h2>
    <p>OOTD sets one cookie, a session cookie that keeps you logged in. There are no tracking or advertising cookies.</p>

    <h2>Who can see it</h2>
    <p>Each post has its own setting: <strong>Public</strong> (anyone, including people who aren&rsquo;t signed in),
        <strong>Members</strong> (anyone signed in here), <strong>Followers</strong> (people you&rsquo;ve accepted as followers)
        or <strong>Only me</strong>. Your username, profile photo and public posts can be seen by anyone; your real name only by members.
        Your email is only visible to admins. Your wear history and insights are only shown to you.</p>

    <?php if (Federation::enabled()): ?>
    <h2>Other servers</h2>
    <p>People on Mastodon and other Fediverse servers can follow you by your handle. Your Public and Followers posts,
        username and profile photo are sent to the servers of people who follow you, and are then held by those servers
        under their own policies. Members and Only me posts never leave <?= escape(instance_name()) ?>.
        Deleting or hiding a post asks those servers to remove it, but they can&rsquo;t be forced to.</p>
    <?php endif; ?>

    <h2>Third parties</h2>
    <p>Fonts and charts are served by <?= escape(instance_name()) ?> itself; pages don&rsquo;t load anything from other services.</p>
    <?php if (trim((string)setting_get("privacy_notice", "")) !== ""): ?>
        <p><?= nl2br(escape(setting_get("privacy_notice", ""))) ?></p>
    <?php endif; ?>

    <h2>Your data</h2>
    <p>You can download everything you&rsquo;ve posted from your <a href="/account#data">account page</a>.
        To have your account removed, ask an admin<?= setting_get("admin_contact", "") ? " (" . escape(setting_get("admin_contact", "")) . ")" : "" ?>.</p>
</section>
