<?php
// /outfit/<outfit_id>
$outfit_id = explode("/", parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))[2] ?? "";
$outfit = new Post\Outfit(Post\Outfit::num_from_id(rawurldecode($outfit_id)));

if (!$outfit->viewable()) {
    http_response_code(404);
    require $_SERVER['DOCUMENT_ROOT'] . '/Pages/404.php';
    return;
}

title_update("Outfit by " . $outfit->user['username']);
if (!empty($outfit->description)) {
    description_update(plain_text($outfit->description));
}
?>
<section class="header">
    <p class="kicker">Outfit</p>
    <h1>By <em><?= escape($outfit->user['username']) ?></em></h1>
    <?php if (!empty($_GET['saved'])): ?>
        <p class="notice">Your changes have been saved.</p>
    <?php endif; ?>
</section>
<div class="feed">
    <?php $outfit->render_outfit(); ?>
</div>
<?php Comment::render_section("outfit", (int)$outfit->outfit_num, (string)$outfit->outfit_id, (int)$outfit->user['user_id']); ?>
<?php if ($outfit->owned()):
    $own_garments = new Post\Garment($_SESSION['user_id']);
    ?>
    <section class="edit">
        <details>
            <summary>Edit outfit</summary>
            <form action="/API/outfit/update.php" method="post" enctype="multipart/form-data">
                <?php CSRF_input(); ?>
                <input type="hidden" name="outfit_id" value="<?= escape($outfit->outfit_id) ?>">
                <label>
                    Caption
                    <textarea name="description" maxlength="1024"><?= escape(plain_text($outfit->description)) ?></textarea>
                </label>
                <label>
                    Garments
                    <select name="garments[]" multiple>
                        <?php foreach ($own_garments->garments as $garment): ?>
                            <option value="<?= escape($garment['garment_num']) ?>"<?= in_array((int)$garment['garment_num'], array_map('intval', $outfit->garment_nums), true) ? " selected" : "" ?>><?= escape($garment['garment_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Tags
                    <input type="text" name="tags" value="<?= escape(tags_display($outfit->tags)) ?>">
                </label>
                <label>
                    Replace photo
                    <input type="file" name="image" accept="image/*">
                </label>
                <label>
                    Describe the photo
                    <input type="text" name="alt_text" maxlength="1500" value="<?= escape($outfit->image_alt) ?>">
                </label>
                <?php visibility_select($outfit->visibility); ?>
                <button type="submit">Save changes</button>
            </form>
        </details>
        <form action="/API/outfit/delete.php" method="post" class="delete" data-confirm="Delete this outfit? This can't be undone.">
            <?php CSRF_input(); ?>
            <input type="hidden" name="outfit_id" value="<?= escape($outfit->outfit_id) ?>">
            <button type="submit" class="link-button">Delete outfit</button>
        </form>
    </section>
<?php endif; ?>
