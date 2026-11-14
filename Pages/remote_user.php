<?php
//someone on another blog, /@name@host: members only, since looking them up makes requests to that blog
if (empty($_SESSION['user_id'])) {
    header("location: /login");
    exit();
}
$handle = rawurldecode(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1));
$actor = Federation::enabled() ? RemoteActor::find($handle) : null;
if (!$actor) {
    http_response_code(404);
    title_update("not found");
    ?>
    <section class="header">
        <p class="kicker">Other blogs</p>
        <h1>Not <em>found</em></h1>
        <p class="notice">
            <?php if (!Federation::enabled()): ?>
                Following people on other blogs is turned off here.
            <?php else: ?>
                Couldn't find <?= escape(RemoteActor::display_handle($handle)) ?>. Check the address, or try again later if their blog is down.
            <?php endif; ?>
        </p>
    </section>
    <?php
    return;
}
$state = RemoteFollow::state($_SESSION['user_id'], $actor->actor_num);
$posts = RemotePost::latest($_SESSION['user_id'], "actor", 30, $actor->actor_num);
$media = RemotePost::media_for($posts);
title_update(RemoteActor::display($actor));
?>
<section class="header">
    <p class="kicker">From <?= escape(RemoteActor::blog_name((string)$actor->host)) ?></p>
    <div class="avatar">
        <img src="<?= asset_url("/Assets/images/avi.jpg") ?>" alt="">
    </div>
    <h1><?= escape($actor->name ?: $actor->username) ?></h1>
    <p class="handle" title="Their address: <?= escape(RemoteActor::address($actor)) ?>"><?= escape(RemoteActor::display($actor)) ?></p>
    <p class="byline">
        <a href="<?= escape($actor->url ?: $actor->ap_id) ?>" rel="nofollow noopener" target="_blank">Their profile on <?= escape(RemoteActor::blog_name((string)$actor->host)) ?></a>
    </p>
    <form action="/API/follow/remote.php" method="post" class="follow">
        <?php CSRF_input(); ?>
        <input type="hidden" name="actor" value="<?= (int)$actor->actor_num ?>">
        <?php if ($state === null): ?>
            <button type="submit" name="action" value="follow">Follow</button>
        <?php elseif ($state === Follow::PENDING): ?>
            <button type="submit" name="action" value="unfollow" class="secondary" title="Cancel your request">Requested</button>
        <?php else: ?>
            <button type="submit" name="action" value="unfollow" class="secondary" title="Unfollow">Following</button>
        <?php endif; ?>
    </form>
</section>
<section class="outfits">
    <h2>Posts</h2>
    <?php if (count($posts) > 0): ?>
        <?php foreach ($posts as $post): ?>
            <?php RemotePost::render($post, $media[(int)$post->post_num] ?? array(), true); ?>
        <?php endforeach; ?>
    <?php elseif ($state === Follow::PENDING): ?>
        <p class="empty">Waiting for <?= escape($actor->host) ?> to accept your follow. Their posts will show here once it does.</p>
    <?php elseif ($state === null): ?>
        <p class="empty">Follow them to see their posts here.</p>
    <?php else: ?>
        <p class="empty">Nothing yet. New posts show here as they're made.</p>
    <?php endif; ?>
</section>
