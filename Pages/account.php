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
<?php //just for demo
$sql = "SELECT * FROM `garments` where garment_num = 8";
$db = new Mysql();
$jacket = $db->Fetch($sql, null);
if ($jacket[0] != '00000'):
?>
<section>
    <div style="float:right;position: relative;margin-right: 3rem;margin-top: 2rem;transform: rotate(-15deg);z-index: 4;width: 18%;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="
    position: absolute;
    right: -12px;
    top: -12px;
">
            <path d="M6.166 16.943l1.4 1.4-4.622 4.657h-2.944l6.166-6.057zm11.768-6.012c2.322-2.322 4.482-.457 6.066-1.931l-8-8c-1.474 1.584.142 3.494-2.18 5.817-3.016 3.016-4.861-.625-10.228 4.742l9.6 9.6c5.367-5.367 1.725-7.211 4.742-10.228z"></path>
        </svg>
        <img loading="lazy"
             src="<?= image_bin_encode($jacket[0]->image) ?>"
             alt="<?= escape($jacket[0]->description) ?>"
             class=""
        >
    </div>
    <h2>Stats</h2>
    <h3>Daily insights:</h3>
    <p>You haven't worn, "<?= escape($jacket[0]->garment_name) ?>" in 6 months.</p>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.js"
            integrity="sha512-9rxMbTkN9JcgG5euudGbdIbhFZ7KGyAuVomdQDI9qXfPply9BJh0iqA7E/moLCatH2JD4xBGHwV6ezBkCpnjRQ=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chartist/0.11.4/chartist.min.css"
          integrity="sha512-V0+DPzYyLzIiMiWCg3nNdY+NyIiK9bED/T1xNBj08CaIUyK3sXRpB26OUCIzujMevxY9TRJFHQIxTwgzb0jVLg=="
          crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <div class="ct-chart ct-golden-section"></div>
    <script>
        new Chartist.Line('.ct-chart', {
            labels: ["February", "March", "April", "May", "June", "July", "August", 'September', "November", "December", "January"],
            series: [
                [1, 5, 1, 3, 12, 0, 0, 0, 0, 0, 0]
            ]
        }, {
            high: 20,
            low: -3,
            fullWidth: true,
            // As this is axis specific we need to tell Chartist to use whole numbers only on the concerned axis
            axisY: {
                onlyInteger: true,
                offset: 20
            }
        });

        setTimeout(
            function () {
                const path = document.querySelector('.ct-series-a path');
                const length = path.getTotalLength();
                console.log(length);
            },
            3000);
    </script>

</section>
<?php endif; ?>
