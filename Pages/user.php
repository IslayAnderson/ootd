<?php
$path = explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$username = $path[2] ?? "";
$user_id = (new User())->get_user_id_from_username(rawurldecode($username));
if (empty($user_id)) {
    http_response_code(404);
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
    return;
}
$user = new User($user_id);

// /user/<username>/blog/<blog_id>
if (($path[3] ?? "") == "blog") {
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/blog.php';
    return;
}

title_update($user->username);
?>
<section class="header">
    <p class="kicker">Profile</p>
    <div class="avatar">
        <img src="<?= $user->get_image() ?>" alt="<?= escape($user->username) ?>">
    </div>
    <h1><?= escape($user->get_username($user->user_id)) ?></h1>
    <?php if (!empty($_SESSION['user_id'])): ?>
        <?php //real names stay inside the instance, guests only see the username ?>
        <p class="byline"><?= escape($user->first_name . " " . $user->last_name) ?></p>
    <?php else: ?>
        <p class="notice">You're seeing <?= escape($user->username) ?>'s public posts. <a href="/login">Log in</a> to see more.</p>
    <?php endif; ?>
</section>
<section class="blog">
    <h2>Blogs</h2>
    <?php
    $blogs = new Post\Blog($user->get_user_id());
    if (count($blogs->blogs) > 0) {
        $blogs->render_blogs_profile();
    } else {
        ?>
        <p class="empty">Nothing to be found</p>
        <?php
    }
    ?>
</section>
<section class="outfits">
    <h2>Outfits</h2>
    <?php
    $params = array(":a" => $user->get_user_id()); //todo: sanitize this
    $sql = "SELECT `outfit_num` FROM `outfits` 
                    join `users` on outfits.user = users.user_id
                    where users.user_id = :a AND " . visibility_where("outfits", "user") . " order by outfits.timestamp DESC limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    if ($row[0] != '00000') {
        foreach ($row as $outfit) {
            $post = new Post\Outfit($outfit->outfit_num);
            $post->render_outfit(true);
        }
    } else {
        ?>
        <p class="empty">Nothing to be found</p>
        <?php
    }
    ?>
</section>
<section class="wardrobe">
    <h2>Wardrobe</h2>
    <div>
        <?php
        $wardrobe = new \Post\Wardrobe($user->get_user_id(), 9);
        $wardrobe->render_wardrobe();
        ?>
    </div>
</section>
