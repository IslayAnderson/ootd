<?php
// /remote/<post_num>: a post copied from another server, with its comments (members only)
if (empty($_SESSION['user_id'])) {
    header("location: /login");
    exit();
}
$post_num = (int)(explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))[2] ?? 0);
$post = RemotePost::one($_SESSION['user_id'], $post_num);
if (!$post) {
    http_response_code(404);
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
    return;
}
$handle = "@" . $post->username . "@" . $post->host;
title_update("Post by " . $handle);
?>
<section class="header">
    <p class="kicker">Elsewhere &middot; <?= escape($post->host) ?></p>
    <h1>By <em><a href="/@<?= escape(rawurlencode((string)$post->username) . "@" . rawurlencode((string)$post->host)) ?>"><?= escape($handle) ?></a></em></h1>
</section>
<div class="feed">
    <?php RemotePost::render($post, RemotePost::media_for(array($post))[(int)$post->post_num] ?? array()); ?>
</div>
<?php Comment::render_section("remote", (int)$post->post_num, (string)(int)$post->post_num); ?>
