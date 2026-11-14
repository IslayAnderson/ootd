<?php
$user = unserialize($_SESSION['user']);
title_update("account");
?>
<section class="header" style="border-top: unset;">
    <h1>Account</h1>
    <div class="avatar">
        <img src="<?= $user->get_image() ?>" alt="<?= escape($user->username) ?>">
        <form action="/API/user/avatar.php" method="post" enctype="multipart/form-data">
            <?php CSRF_input(); ?>
            <label>
                Change photo
                <input type="file" name="image" accept="image/*" required>
            </label>
            <button type="submit">Save</button>
        </form>
    </div>
    <h2><?= escape($user->get_username($user->user_id)) ?></h2>
    <h3><?= escape($user->first_name . " " . $user->last_name) ?></h3>
    <?php if (!empty($_GET['posted'])): ?>
        <p class="notice">Your <?= escape($_GET['posted']) ?> has been saved.</p>
    <?php endif; ?>
    <details class="password">
        <summary>Change password</summary>
        <form action="/API/user/password.php" method="post">
            <?php CSRF_input(); ?>
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" required>
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" minlength="8" required>
            <button type="submit">Change password</button>
        </form>
    </details>
</section>
<section class="blog">
    <h2>New post</h2>
    <div class="post">
        <script src="/vendor/tinymce/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
        <div class="post_tabs">
            <button type="button" data-frame="blog">
                <span>New blog</span>
            </button>
            <button type="button" data-frame="outfit" class="active">
                <span>New outfit</span>
            </button>
            <button type="button" data-frame="garment">
                <span>New Garment</span>
            </button>
        </div>
        <?php
        $garments = new Post\Garment($user->get_user_id());
        ?>
        <div class="frame blog hidden">
            <form action="/API/blog/create.php" method="post">
                <?php CSRF_input(); ?>
                <label>
                    Title
                    <input type="text" name="title" placeholder="Title" maxlength="256" required>
                </label>
                <label>
                    Excerpt
                    <textarea id="excerpt" name="excerpt" placeholder="Write an excerpt..." maxlength="1024"></textarea>
                </label>
                <label>
                    Content
                    <textarea id="blog" name="blog" placeholder="Write a new blog..."></textarea>
                </label>
                <label>
                    Outfits in this post
                    <select name="outfits[]" multiple>
                        <?php
                        $params = array(":a" => $user->get_user_id());
                        $sql = "SELECT `outfit_num`, `description`, `timestamp` FROM `outfits` WHERE `user` = :a order by `timestamp` DESC limit 100";
                        $db = new Mysql();
                        $own_outfits = $db->Fetch($sql, $params);
                        if ($own_outfits[0] != '00000'):
                            foreach ($own_outfits as $own_outfit): ?>
                                <option value="<?= escape($own_outfit->outfit_num) ?>">
                                    <?= escape($own_outfit->timestamp . " " . mb_substr((string)$own_outfit->description, 0, 40)) ?>
                                </option>
                            <?php endforeach;
                        endif; ?>
                    </select>
                </label>
                <input type="submit" value="" title="Post" class="btn post">
            </form>
        </div>
        <div class="frame outfit">
            <form action="/API/outfit/create.php" method="post" enctype="multipart/form-data">
                <?php CSRF_input(); ?>
                <label>
                    Caption
                    <textarea id="description" name="description" placeholder="Write a caption..." maxlength="1024"></textarea>
                </label>
                <label>
                    Existing garments
                    <select name="garments[]" id="garments" multiple>
                        <?php $garments->render_garment_options(); ?>
                    </select>
                </label>
                <label>
                    Tags
                    <input type="text" name="tags" placeholder="Pick tags...">
                </label>
                <label>
                    Outfit Photo
                    <input type="file" name="image" accept="image/*" placeholder="Pick photo..." required>
                </label>
                <input type="submit" value="" title="Post" class="btn post">
            </form>
        </div>
        <div class="frame garment hidden">
            <form action="/API/garment/create.php" method="post" enctype="multipart/form-data">
                <?php CSRF_input(); ?>
                <label>
                    Name
                    <input type="text" name="garment_name" placeholder="Platform Gazelle" maxlength="128" required>
                </label>
                <label>
                    Description
                    <textarea name="description" placeholder="Describe it..." maxlength="1024"></textarea>
                </label>
                <label>
                    Brand
                    <input type="text" name="brand" list="brands" placeholder="Start typing a brand...">
                    <datalist id="brands">
                        <?php foreach (brands_list() as $brand): ?>
                            <option value="<?= escape($brand) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>
                <label>
                    Category
                    <select name="category">
                        <option value="">Pick a category</option>
                        <?php foreach (categories_list() as $category_id => $category): ?>
                            <option value="<?= escape($category_id) ?>"><?= $category['level'] > 0 ? "&nbsp;&nbsp;" : "" ?><?= escape($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Tags
                    <input type="text" name="tags" placeholder="#streetwear #autumn">
                </label>
                <label>
                    Photo
                    <input type="file" name="image" accept="image/*">
                </label>
                <input type="submit" value="" title="Post" class="btn post">
            </form>
        </div>
        <script>
            tinymce.init({
                selector: '#blog',
                license_key: '<?= escape($_ENV['TINYMCE'] ?? 'gpl') ?>'
            });
        </script>
    </div>

    <h2>Blogs</h2>
    <?php
    $blogs = new Post\Blog($user->get_user_id());
    $blogs->render_blogs_profile();
    ?>
</section>
<section class="outfits">
    <h2>Outfits</h2>
    <?php
    $params = array(":a" => $user->get_user_id()); //todo: sanitize this
    $sql = "SELECT `outfit_num` FROM `outfits` 
                    join `users` on outfits.user = users.user_id
                    where users.user_id = :a order by outfits.timestamp DESC limit 30";
    $db = new Mysql();
    $row = $db->Fetch($sql, $params);

    if ($row[0] != '00000') {
        foreach ($row as $outfit) {
            $post = new Post\Outfit($outfit->outfit_num);
            $post->render_outfit(true);
        }
    } else {
        ?>
        <p>Nothing to be found</p>
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
<section class="insights">
    <h2>Stats</h2>
    <?php
    $insights = new Insights($user->get_user_id());
    $insights->render_daily_insight();
    ?>
    <p><a href="/insights">See all insights</a></p>
</section>
