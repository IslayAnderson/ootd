<?php
// included from Pages/user.php, which has already loaded $user and $path
$blog = new Post\Blog($user->user_id, empty($path[4]) ? "-" : $path[4], 1);

if (count($blog->blogs) == 0) {
    http_response_code(404);
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
    return;
}

title_update($blog->blogs[0]['blog_title']);
description_update($blog->blogs[0]['blog_excerpt']);
?>
<section class="blog">
    <?php if (!empty($_GET['saved'])): ?>
        <p class="notice">Your changes have been saved.</p>
    <?php endif; ?>
    <?php $blog->render_blog(); ?>
</section>
<?php
$post = $blog->blogs[0];
if ($post['user'] == ($_SESSION['user_id'] ?? null)): ?>
    <section class="edit">
        <script src="/vendor/tinymce/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
        <details>
            <summary>Edit blog</summary>
            <form action="/API/blog/update.php" method="post">
                <?php CSRF_input(); ?>
                <input type="hidden" name="blog_id" value="<?= escape($post['blog_id']) ?>">
                <label>
                    Title
                    <input type="text" name="title" maxlength="256" value="<?= escape($post['blog_title']) ?>" required>
                </label>
                <label>
                    Excerpt
                    <textarea name="excerpt" maxlength="1024"><?= escape($post['blog_excerpt']) ?></textarea>
                </label>
                <label>
                    Content
                    <textarea id="blog" name="blog"><?= escape($post['blog_content']) ?></textarea>
                </label>
                <label>
                    Outfits in this post
                    <select name="outfits[]" multiple>
                        <?php
                        $sql = "SELECT `outfit_num`, `description`, `timestamp` FROM `outfits` WHERE `user` = :a AND `deleted_at` IS NULL order by `timestamp` DESC limit 100";
                        $db = new Mysql();
                        $own_outfits = $db->Fetch($sql, array(":a" => $_SESSION['user_id']));
                        if ($own_outfits[0] != '00000'):
                            foreach ($own_outfits as $own_outfit): ?>
                                <option value="<?= escape($own_outfit->outfit_num) ?>"<?= in_array((int)$own_outfit->outfit_num, array_map('intval', $post['outfit_nums']), true) ? " selected" : "" ?>>
                                    <?= escape(date_display($own_outfit->timestamp) . " " . mb_substr((string)$own_outfit->description, 0, 40)) ?>
                                </option>
                            <?php endforeach;
                        endif; ?>
                    </select>
                </label>
                <?php visibility_select($post['visibility']); ?>
                <button type="submit">Save changes</button>
            </form>
        </details>
        <form action="/API/blog/delete.php" method="post" class="delete" data-confirm="Delete this blog post? This can't be undone.">
            <?php CSRF_input(); ?>
            <input type="hidden" name="blog_id" value="<?= escape($post['blog_id']) ?>">
            <button type="submit" class="link-button">Delete blog</button>
        </form>
        <script>
            tinymce.init({
                selector: '#blog',
                license_key: '<?= escape($_ENV['TINYMCE'] ?? 'gpl') ?>'
            });
        </script>
    </section>
<?php endif; ?>
