<?php
$user = unserialize($_SESSION['user']);
title_update("account");
?>
<section class="header">
    <p class="kicker">Account</p>
    <div class="avatar">
        <img src="<?= $user->get_image() ?>" alt="<?= escape($user->username) ?>">
    </div>
    <h1><?= escape($user->get_username($user->user_id)) ?></h1>
    <p class="byline"><?= escape($user->first_name . " " . $user->last_name) ?></p>
    <?php $saved_names = array("journal" => "article", "article" => "article", "setting" => "setting", "outfit" => "outfit", "garment" => "garment", "avatar" => "profile photo", "password" => "password", "wear" => "wear"); ?>
    <?php if (!empty($_GET['posted'])): ?>
        <p class="notice">Your <?= escape($saved_names[$_GET['posted']] ?? "change") ?> has been saved.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <p class="notice">Your <?= escape($saved_names[$_GET['deleted']] ?? "post") ?> has been deleted.</p>
    <?php endif; ?>
    <details class="photo">
        <summary>Change photo</summary>
        <form action="/API/user/avatar.php" method="post" enctype="multipart/form-data">
            <?php CSRF_input(); ?>
            <label for="avatar_image">New photo</label>
            <input type="file" id="avatar_image" name="image" accept="image/*" required>
            <button type="submit">Save photo</button>
        </form>
    </details>
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
<section class="your-data" id="data">
    <h2>Your data</h2>
    <p>Download everything you&rsquo;ve put on <?= escape(instance_name()) ?>: your profile, wardrobe, outfits, articles, wears and photos, as a zip of JSON files and images.</p>
    <form action="/API/user/export.php" method="post">
        <?php CSRF_input(); ?>
        <button type="submit" class="secondary">Download your data</button>
    </form>
</section>
<section class="theme" id="theme">
    <h2>Your theme</h2>
    <?php $theme_owner = "me"; require $_SERVER['DOCUMENT_ROOT']."/partials/theme_form.php"; ?>
</section>
<section class="followers" id="followers">
    <h2>Followers</h2>
    <?php list($followers, $following) = Follow::counts($user->get_user_id()); ?>
    <p class="follow-counts">
        <span><strong><?= $followers ?></strong> <?= $followers == 1 ? "follower" : "followers" ?></span>
        <span><strong><?= $following ?></strong> following</span>
    </p>
    <?php
    $db = new Mysql();
    $approve = $db->Fetch("SELECT `approve_followers` FROM `users` WHERE `user_id` = :a", array(":a" => $user->get_user_id()));
    ?>
    <form action="/API/user/privacy.php" method="post" class="privacy-setting">
        <?php CSRF_input(); ?>
        <label class="checkbox">
            <input type="checkbox" name="approve_followers"<?= (int)($approve[0]->approve_followers ?? 0) === 1 ? " checked" : "" ?>>
            Approve new followers before they can see followers-only posts
        </label>
        <button type="submit" class="secondary">Save</button>
    </form>
    <?php $remote_requests = Follow::remote_requests($user->get_user_id()); ?>
    <?php if (count($remote_requests) > 0): ?>
        <h3>Requests from other blogs</h3>
        <ul class="requests">
            <?php foreach ($remote_requests as $request): ?>
                <li>
                    <a href="<?= escape($request->url ?: $request->ap_id) ?>" rel="nofollow noopener" target="_blank" title="<?= escape(RemoteActor::address($request)) ?>"><?= escape(RemoteActor::display($request)) ?></a>
                    <form action="/API/follow/respond_remote.php" method="post">
                        <?php CSRF_input(); ?>
                        <input type="hidden" name="actor" value="<?= (int)$request->actor_num ?>">
                        <button type="submit" name="answer" value="accept">Accept</button>
                        <button type="submit" name="answer" value="decline" class="secondary">Decline</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php $elsewhere = Federation::enabled() ? RemoteFollow::following($user->get_user_id()) : array(); ?>
    <?php if (count($elsewhere) > 0): ?>
        <h3>Following on other blogs</h3>
        <ul class="requests">
            <?php foreach ($elsewhere as $followed): ?>
                <li>
                    <a href="<?= escape(RemoteActor::local_path($followed)) ?>" title="<?= escape(RemoteActor::address($followed)) ?>"><?= escape(RemoteActor::display($followed)) ?></a>
                    <?php if ($followed->state === Follow::PENDING): ?>
                        <span class="kicker">waiting for <?= escape(RemoteActor::blog_name((string)$followed->host)) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php $requests = Follow::requests($user->get_user_id()); ?>
    <?php if (count($requests) > 0): ?>
        <h3>Requests</h3>
        <ul class="requests">
            <?php foreach ($requests as $request): ?>
                <li>
                    <a href="/user/<?= escape($request->username) ?>"><?= escape($request->username) ?></a>
                    <form action="/API/follow/respond.php" method="post">
                        <?php CSRF_input(); ?>
                        <input type="hidden" name="username" value="<?= escape($request->username) ?>">
                        <button type="submit" name="answer" value="accept">Accept</button>
                        <button type="submit" name="answer" value="decline" class="secondary">Decline</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<section class="new-post">
    <h2>New <em>post</em></h2>
    <div class="post">
        <script src="/vendor/tinymce/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
        <div class="post_tabs">
            <button type="button" data-frame="blog">
                <span>New article</span>
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
                        $sql = "SELECT `outfit_num`, `description`, `timestamp` FROM `outfits` WHERE `user` = :a AND `deleted_at` IS NULL order by `timestamp` DESC limit 100";
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
                <?php visibility_select(); ?>
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
                <label>
                    Describe the photo
                    <input type="text" name="alt_text" maxlength="1500" placeholder="For people using screen readers">
                </label>
                <?php visibility_select(); ?>
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
                <label>
                    Describe the photo
                    <input type="text" name="alt_text" maxlength="1500" placeholder="For people using screen readers">
                </label>
                <?php visibility_select(); ?>
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

</section>
<section class="blog">
    <h2>Articles</h2>
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
<section class="insights">
    <h2>Stats</h2>
    <?php
    $insights = new Insights($user->get_user_id());
    $insights->render_daily_insight();
    ?>
    <p><a href="/insights">See all insights</a></p>
</section>
