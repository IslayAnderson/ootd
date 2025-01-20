<?php
$user = unserialize($_SESSION['user']);
?>
<section class="header" style="border-top: unset;">
    <h1>Account</h1>
    <h2><?= $user->get_username($user->user_id) ?></h2>
    <h3><?= $user->first_name . " " . $user->last_name ?></h3>
</section>
<section class="blog">
    <h2>Blogs</h2>
    <form>
        <script src="/vendor/tinymce/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
        <label>
            <textarea id="blog" name="blog" placeholder="Write a new blog..."></textarea>
        </label>
        <input type="submit" value="" title="Post" class="btn post">
        <script>
            tinymce.init({
                selector: '#blog',
                license_key: '<?= $_ENV['TINYMCE'] ?>'
            });
        </script>
    </form>
</section>
<section class="outfits">
    <h2>Outfits</h2>
    <?php
    $params = array(":a" => $user->get_user_id()); //todo: sanitize this
    $sql = "SELECT `outfit_num` FROM `outfits` 
                    join `users` on outfits.user = users.user_id
                    where users.user_id = :a order by timestamp limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    foreach ($row as $outfit) {
        $post = new Post\Outfit($outfit->outfit_num);
        $post->render_outfit(true);
    }
    ?>
</section>
<section class="wardrobe">
    <h2>Wardrobe</h2>
    <?php
    $wardrobe = new \Post\Wardrobe($user->get_user_id(), 9);
    $wardrobe->render_wardrobe();
    ?>
</section>
